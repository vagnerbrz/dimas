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
            $requestPhone = preg_replace('/\D+/', '', (string) $request->phone);
            if (strlen($requestPhone) >= 8) {
                $selectedCustomer = $customers->first(function ($customer) use ($requestPhone) {
                    $customerPhone = preg_replace('/\D+/', '', (string) $customer->phone);

                    if (strlen($customerPhone) < 8) {
                        return false;
                    }

                    return $customerPhone === $requestPhone
                        || str_ends_with($customerPhone, $requestPhone)
                        || str_ends_with($requestPhone, $customerPhone);
                });
            }
        }

        return view('orders.create', compact('customers', 'products', 'selectedCustomer'));
    }

    public function store(Request $request)
    {
        $customerMode = $request->input('customer_mode', 'existing');
        $addressMode = $customerMode === 'new' ? 'new' : $request->input('address_mode', 'existing');
        $shouldCreateAddress = $request->type === Order::TYPE_DELIVERY && $addressMode === 'new';

        $validated = $request->validate([
            'customer_mode' => ['required', Rule::in(['existing', 'new'])],
            'address_mode' => ['nullable', Rule::in(['existing', 'new'])],
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
            'address_id' => $request->type === Order::TYPE_DELIVERY && $customerMode === 'existing' && $addressMode === 'existing'
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
            'new_address.street' => $shouldCreateAddress
                ? 'required|string|max:255'
                : 'nullable|string|max:255',
            'new_address.number' => $shouldCreateAddress
                ? 'required|string|max:20'
                : 'nullable|string|max:20',
            'new_address.complement' => 'nullable|string|max:255',
            'new_address.neighborhood' => $shouldCreateAddress
                ? 'required|string|max:255'
                : 'nullable|string|max:255',
            'new_address.city' => 'nullable|string|max:255',
            'new_address.state' => 'nullable|string|max:2',
            'new_address.zip_code' => 'nullable|string|max:10',
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

                if ($shouldCreateAddress) {
                    $address = $customer->addresses()->create([
                        'street' => $validated['new_address']['street'],
                        'number' => $validated['new_address']['number'],
                        'complement' => $validated['new_address']['complement'] ?? null,
                        'neighborhood' => $validated['new_address']['neighborhood'],
                        'city' => 'Manaus',
                        'state' => 'AM',
                        'zip_code' => $validated['new_address']['zip_code'] ?? '',
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
        $order->load(['customer', 'address', 'items.product']);
        $orderProductIds = $order->items->pluck('product_id');
        $products = Product::query()
            ->where('is_active', true)
            ->orWhereIn('id', $orderProductIds)
            ->orderBy('name')
            ->get();

        return view('orders.edit', compact('order', 'products'));
    }

    public function update(Request $request, Order $order)
    {
        if (!$request->has('items')) {
            $request->validate([
                'status' => 'required|string',
                'observations' => 'nullable|string',
            ]);

            $data = [
                'status' => $request->status,
            ];

            if ($request->has('observations')) {
                $data['observations'] = $request->observations;
            }

            $order->update($data);

            return redirect()->route('orders.show', $order)->with('success', 'Pedido atualizado com sucesso!');
        }

        $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('customers', 'phone')->ignore($order->customer_id),
            ],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'type' => 'required|in:counter,delivery,table',
            'delivery_fee' => $request->type === Order::TYPE_DELIVERY ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'status' => 'required|in:awaiting_acceptance,pending,preparing,shipped,delivered,cancelled',
            'payment_method' => 'required|in:pix,debit,credit,cash',
            'change_for' => 'nullable|numeric|min:0',
            'observations' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'address.street' => $request->type === Order::TYPE_DELIVERY
                ? 'required|string|max:255'
                : 'nullable|string|max:255',
            'address.number' => $request->type === Order::TYPE_DELIVERY
                ? 'required|string|max:20'
                : 'nullable|string|max:20',
            'address.complement' => 'nullable|string|max:255',
            'address.neighborhood' => $request->type === Order::TYPE_DELIVERY
                ? 'required|string|max:255'
                : 'nullable|string|max:255',
            'address.reference' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $order->load('customer');

            $order->customer->update([
                'name' => $request->customer_name,
                'phone' => $request->customer_phone,
                'email' => $request->customer_email,
            ]);

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
            $addressId = null;

            if ($request->type === Order::TYPE_DELIVERY) {
                $deliveryFee = (float) $request->delivery_fee;
                $addressData = [
                    'customer_id' => $order->customer_id,
                    'street' => $request->input('address.street'),
                    'number' => $request->input('address.number'),
                    'complement' => $request->input('address.complement'),
                    'neighborhood' => $request->input('address.neighborhood'),
                    'city' => 'Manaus',
                    'state' => 'AM',
                    'zip_code' => '',
                    'reference' => $request->input('address.reference'),
                    'is_primary' => true,
                    'last_delivery_fee' => $deliveryFee,
                    'last_delivery_fee_updated_at' => now(),
                ];

                $address = $order->address;

                if ($address) {
                    $address->update($addressData);
                } else {
                    $address = Address::create($addressData);
                }

                $addressId = $address->id;
            }

            $order->update([
                'type' => $request->type,
                'address_id' => $addressId,
                'status' => $request->status,
                'total_amount' => $itemsSubtotal + $deliveryFee,
                'delivery_fee' => $deliveryFee,
                'delivery_distance_km' => $request->type === Order::TYPE_DELIVERY ? $order->delivery_distance_km : null,
                'payment_method' => $request->payment_method,
                'change_for' => $request->payment_method === 'cash' && $request->filled('change_for')
                    ? (float) $request->change_for
                    : null,
                'observations' => $request->observations,
            ]);

            $order->items()->delete();

            foreach ($itemsData as $item) {
                $order->items()->create($item);
            }

            DB::commit();

            return redirect()->route('orders.show', $order)->with('success', 'Pedido atualizado com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->withErrors('Erro ao atualizar pedido: ' . $e->getMessage());
        }
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
