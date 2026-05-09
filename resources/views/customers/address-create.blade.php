@extends('layouts.app')

@section('header', 'Novo Endereço')

@section('content')
<div class="max-w-2xl bg-white p-8 rounded-xl shadow-sm border border-gray-200 mx-auto">
    <form action="{{ route('customers.address.store', $customer->id) }}" method="POST" class="space-y-6">
        @csrf
        <div class="grid grid-cols-1 gap-6">
            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-gray-700">CEP</label>
                <input type="text" name="zip_code" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required placeholder="00000-000">
            </div>
            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-gray-700">Rua / Logradouro</label>
                <input type="text" name="street" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required placeholder="Rua Exemplo">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700">Número</label>
                    <input type="text" name="number" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required placeholder="123">
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700">Complemento</label>
                    <input type="text" name="complement" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Apto 12">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700">Bairro</label>
                    <input type="text" name="neighborhood" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required placeholder="Bairro Centro">
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700">Cidade</label>
                    <input type="text" name="city" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required placeholder="São Paulo">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700">Estado (UF)</label>
                    <input type="text" name="state" maxlength="2" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required placeholder="SP">
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700">Referência</label>
                    <input type="text" name="reference" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Ao lado da farmácia">
                </div>
            </div>
            <div class="flex justify-end gap-4 pt-4">
                <a href="{{ route('customers.show', $customer->id) }}" class="px-6 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition">Cancelar</a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg text-sm font-bold transition">Salvar Endereço</button>
            </div>
        </div>
    </form>
</div>
@endsection