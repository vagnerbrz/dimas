@extends('layouts.app')

@section('header', 'Editar Cliente')

@section('content')
<div class="max-w-2xl bg-white p-8 rounded-xl shadow-sm border border-gray-200 mx-auto">
    <form action="{{ route('customers.update', $customer->id) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 gap-6">
            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-gray-700">Nome Completo</label>
                <input type="text" name="name" value="{{ $customer->name }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required>
                @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-gray-700">Telefone / WhatsApp</label>
                <input type="text" name="phone" value="{{ $customer->phone }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required>
                @error('phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-gray-700">E-mail (Opcional)</label>
                <input type="email" name="email" value="{{ $customer->email }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="email@cliente.com">
                @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div class="flex justify-end gap-4 pt-4">
                <a href="{{ route('customers.index') }}" class="px-6 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition">Cancelar</a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg text-sm font-bold transition">Salvar Alterações</button>
            </div>
        </div>
    </form>
</div>
@endsection