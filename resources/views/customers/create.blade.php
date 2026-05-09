@extends('layouts.app')

@section('header', 'Novo Cliente')

@section('content')
<div class="max-w-4xl bg-white p-8 rounded-xl shadow-sm border border-gray-200 mx-auto">
    <form action="{{ route('customers.store') }}" method="POST" class="space-y-8">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Dados Pessoais -->
            <div class="space-y-6">
                <h3 class="text-lg font-bold text-gray-800 border-b pb-2">Dados Pessoais</h3>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700">Nome Completo</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required placeholder="Ex: João Silva">
                    @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700">Telefone / WhatsApp</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required placeholder="(11) 99999-9999">
                    @error('phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700">E-mail (Opcional)</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="email@cliente.com">
                    @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            <!-- Dados de Endereço -->
            <div class="space-y-6">
                <h3 class="text-lg font-bold text-gray-800 border-b pb-2">Endereço de Entrega Principal</h3>
                <div class="grid grid-cols-1 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">CEP</label>
                        <input type="text" name="address[zip_code]" value="{{ old('address.zip_code') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required placeholder="00000-000">
                        @error('address.zip_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">Rua / Logradouro</label>
                        <input type="text" name="address[street]" value="{{ old('address.street') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required placeholder="Rua Exemplo">
                        @error('address.street') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-gray-700">Número</label>
                            <input type="text" name="address[number]" value="{{ old('address.number') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required placeholder="123">
                            @error('address.number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-gray-700">Complemento</label>
                            <input type="text" name="address[complement]" value="{{ old('address.complement') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Apto 12">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-gray-700">Bairro</label>
                            <input type="text" name="address[neighborhood]" value="{{ old('address.neighborhood') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required placeholder="Bairro Centro">
                            @error('address.neighborhood') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-gray-700">Cidade</label>
                            <input type="text" name="address[city]" value="{{ old('address.city') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required placeholder="São Paulo">
                            @error('address.city') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-gray-700">Estado (UF)</label>
                            <input type="text" name="address[state]" maxlength="2" value="{{ old('address.state') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required placeholder="SP" style="text-transform: uppercase;">
                            @error('address.state') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-gray-700">Referência</label>
                            <input type="text" name="address[reference]" value="{{ old('address.reference') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Ao lado da farmácia">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-4 pt-6 border-t border-gray-100">
            <a href="{{ route('customers.index') }}" class="px-6 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition">Cancelar</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg text-sm font-bold transition shadow-lg">Salvar Cliente e Endereço</button>
        </div>
    </form>
</div>
@endsection