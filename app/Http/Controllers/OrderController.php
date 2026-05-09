<?php

namespace App\Http\Controllers;

use App\Jobs\PrintOrderReceipt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with(['customer', 'address'])
            ->when($request->status, function($query, $status) {
                return $query->where('status', $status);
            })
            ->latest()
            ->paginate(15);

        return view('orders.index', compact('orders'));
    }

    public function create(Request $request)
    {
        $customers = Customer::query()
            ->with(['addresses' => function ($query) {
                $query->orderByDesc('is_primary')->orderBy('street');
            }])
            ->orderBy('name')
            ->get();
        $products = Product::where('is_active', true)->get();

        $selectedCustomer = null;
        if ($request->has('phone')) {
            $selectedCustomer = $customers->firstWhere('phone', $request->phone);
        }

        return view('orders.create', compact('customers', 'products', 'selectedCustomer'));
    }

    public function store(Request $request)
    {
        $customerMode = $request->input('customer_mode', 'existing');

        $validated = $request->validate([
            'customer_mode' => ['required', Rule::in(['existing', 'new'])],
            'customer_id' => [$customerMode === 'existing' ? 'required' : 'nullable', 'nullable', 'exists:customers,id'],
            'customer_name' => [$customerMode === 'new' ? 'required' : 'nullable', 'nullable', 'string', 'max:255'],
            'customer_phone' => [
                $customerMode === 'new' ? 'required' : 'nullable',
                'nullable',
                'string',
                'max:20',
                Rule::unique('customers', 'phone'),
            ],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'type' => 'required|in:counter,delivery,table',
            'address_id' => $request->type === 'delivery' && $customerMode === 'existing'
                ? 'required|exists:addresses,id'
                : 'nullable|exists:addresses,id',
            'delivery_fee' => $request->type === 'delivery' ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'status' => 'required|string',
            'payment_method' => 'required|in:pix,debit,credit,cash',
            'change_for' => 'nullable|numeric|min:0',
            'observations' => 'nullable|string',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'new_address.street' => $request->type === Order::TYPE_DELIVERY && $customerMode === 'new'
                ? 'required|string|max:255'
                : 'nullable|string|max:255',
            'new_address.number' => $request->type === Order::TYPE_DELIVERY && $customerMode === 'new'
                ? 'required|string|max:20'
                : 'nullable|string|max:20',
            'new_address.complement' => 'nullable|string|max:255',
            'new_address.neighborhood' => $request->type === Order::TYPE_DELIVERY && $customerMode === 'new'
                ? 'required|string|max:255'
                : 'nullable|string|max:255',
            'new_address.city' => $request->type === Order::TYPE_DELIVERY && $customerMode === 'new'
                ? 'required|string|max:255'
                : 'nullable|string|max:255',
            'new_address.state' => $request->type === Order::TYPE_DELIVERY && $customerMode === 'new'
                ? 'required|string|max:2'
                : 'nullable|string|max:2',
            'new_address.zip_code' => $request->type === Order::TYPE_DELIVERY && $customerMode === 'new'
                ? 'required|string|max:10'
                : 'nullable|string|max:10',
            'new_address.reference' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            if ($validated['customer_mode'] === 'new') {
                $customer = Customer::create([
                    'name' => $validated['customer_name'],
                    'phone' => $validated['customer_phone'],
                    'email' => $validated['customer_email'] ?? null,
                ]);
            } else {
                $customer = Customer::query()->findOrFail($validated['customer_id']);
            }

            $itemsData = [];
            $itemsSubtotal = 0.0;

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $product->price;
                $subtotal = $quantity * $unitPrice;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];

                $itemsSubtotal += $subtotal;
            }

            $deliveryFee = 0.0;
            $deliveryDistanceKm = null;
            $addressId = $validated['address_id'] ?? null;

            if ($request->type === Order::TYPE_DELIVERY) {
                $deliveryFee = (float) $request->delivery_fee;

                if ($validated['customer_mode'] === 'new') {
                    $address = $customer->addresses()->create([
                        'street' => $validated['new_address']['street'],
                        'number' => $validated['new_address']['number'],
                        'complement' => $validated['new_address']['complement'] ?? null,
                        'neighborhood' => $validated['new_address']['neighborhood'],
                        'city' => $validated['new_address']['city'],
                        'state' => $validated['new_address']['state'],
                        'zip_code' => $validated['new_address']['zip_code'],
                        'reference' => $validated['new_address']['reference'] ?? null,
                        'is_primary' => true,
                        'last_delivery_fee' => $deliveryFee,
                        'last_delivery_fee_updated_at' => now(),
                    ]);

                    $addressId = $address->id;
                } elseif ($addressId) {
                    $address = Address::query()
                        ->whereKey($addressId)
                        ->where('customer_id', $customer->id)
                        ->first();

                    if (!$address) {
                        throw new \RuntimeException('O endereco selecionado nao pertence ao cliente informado.');
                    }

                    $address->update([
                        'last_delivery_fee' => $deliveryFee,
                        'last_delivery_fee_updated_at' => now(),
                    ]);
                }
            }

            $order = Order::create([
                'customer_id' => $customer->id,
                'type' => $request->type,
                'address_id' => $addressId,
                'status' => $request->status,
                'total_amount' => $itemsSubtotal + $deliveryFee,
                'delivery_fee' => $deliveryFee,
                'delivery_distance_km' => $deliveryDistanceKm,
                'payment_method' => $request->payment_method,
                'change_for' => $request->payment_method === 'cash' && $request->filled('change_for')
                    ? (float) $request->change_for
                    : null,
                'observations' => $request->observations,
            ]);

            foreach ($itemsData as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            DB::commit();

            PrintOrderReceipt::dispatch($order->id);

            return redirect()->route('orders.index')->with('success', 'Pedido criado com sucesso!');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors('Erro ao criar pedido: ' . $e->getMessage());
        }
    }

    public function show(Order $order)
    {
        $order->load(['customer', 'address', 'items.product']);
        return view('orders.show', compact('order'));
    }

    public function print(Order $order)
    {
        $order->load(['customer', 'address', 'items.product']);

        return view('orders.print', compact('order'));
    }

    public function edit(Order $order)
    {
        return view('orders.edit', compact('order'));
    }

    public function update(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|string',
            'observations' => 'nullable|string',
        ]);

        $order->update([
            'status' => $request->status,
            'observations' => $request->observations,
        ]);

        return redirect()->route('orders.index')->with('success', 'Pedido atualizado com sucesso!');
    }

    public function accept(Order $order)
    {
        if ($order->status !== 'awaiting_acceptance') {
            return redirect()->route('orders.show', $order)->withErrors('Esse pedido nao esta aguardando aceite.');
        }

        $order->update([
            'status' => 'pending',
        ]);

        PrintOrderReceipt::dispatch($order->id);

        return redirect()->route('orders.show', $order)->with('success', 'Pedido aceito e enviado para a fila de preparo.');
    }

    public function destroy(Order $order)
    {
        $order->delete();
        return redirect()->route('orders.index')->with('success', 'Pedido removido com sucesso!');
    }
}
