<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::when($request->search, function($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(15);

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:customers,phone',
            'email' => 'nullable|email|max:255',
            'address.street' => 'required|string|max:255',
            'address.number' => 'required|string|max:20',
            'address.neighborhood' => 'required|string|max:255',
            'address.city' => 'required|string|max:255',
            'address.state' => 'required|string|max:2',
            'address.zip_code' => 'required|string|max:10',
        ]);

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $customer = Customer::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
            ]);

            $customer->addresses()->create([
                'street' => $request->address['street'],
                'number' => $request->address['number'],
                'complement' => $request->address['complement'] ?? null,
                'neighborhood' => $request->address['neighborhood'],
                'city' => $request->address['city'],
                'state' => $request->address['state'],
                'zip_code' => $request->address['zip_code'],
                'is_primary' => true,
                'reference' => $request->address['reference'] ?? null,
            ]);

            \Illuminate\Support\Facades\DB::commit();
            return redirect()->route('customers.index')->with('success', 'Cliente e endereço cadastrados com sucesso!');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollback();
            return back()->withErrors('Erro ao cadastrar cliente: ' . $e->getMessage());
        }
    }

    public function show(Customer $customer)
    {
        $customer->load(['orders', 'addresses']);
        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'max:20', Rule::unique('customers')->ignore($customer->id)],
            'email' => 'nullable|email|max:255',
        ]);

        $customer->update($validated);

        return redirect()->route('customers.index')->with('success', 'Cliente atualizado com sucesso!');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return redirect()->route('customers.index')->with('success', 'Cliente removido com sucesso!');
    }
}
