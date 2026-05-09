<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function create(Customer $customer)
    {
        return view('customers.address-create', compact('customer'));
    }

    public function store(Request $request, Customer $customer)
    {
        $request->validate([
            'street' => 'required|string|max:255',
            'number' => 'required|string|max:20',
            'neighborhood' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:2',
            'zip_code' => 'required|string|max:10',
            'complement' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $address = $customer->addresses()->create([
                'street' => $request->street,
                'number' => $request->number,
                'complement' => $request->complement,
                'neighborhood' => $request->neighborhood,
                'city' => $request->city,
                'state' => $request->state,
                'zip_code' => $request->zip_code,
                'reference' => $request->reference,
                'is_primary' => false,
            ]);

            DB::commit();
            return redirect()->route('customers.show', $customer->id)->with('success', 'Endereço adicionado com sucesso!');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors('Erro ao adicionar endereço: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Address $address)
    {
        $request->validate([
            'street' => 'required|string|max:255',
            'number' => 'required|string|max:20',
            'neighborhood' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:2',
            'zip_code' => 'required|string|max:10',
            'complement' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'is_primary' => 'boolean',
        ]);

        $address->update($request->all());

        if ($request->is_primary) {
            Address::where('customer_id', $address->customer_id)
                ->where('id', '!=', $address->id)
                ->update(['is_primary' => false]);
        }

        return redirect()->route('customers.show', $address->customer_id)->with('success', 'Endereço atualizado com sucesso!');
    }

    public function destroy(Address $address)
    {
        $address->delete();
        return redirect()->route('customers.show', $address->customer_id)->with('success', 'Endereço removido com sucesso!');
    }

    public function setPrimary(Address $address)
    {
        Address::where('customer_id', $address->customer_id)
            ->update(['is_primary' => false]);

        $address->update(['is_primary' => true]);

        return redirect()->route('customers.show', $address->customer_id)->with('success', 'Endereço definido como principal!');
    }
}
