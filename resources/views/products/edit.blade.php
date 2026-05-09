@extends('layouts.app')

@section('header', 'Editar Produto')

@section('content')
<div class="max-w-2xl bg-white p-8 rounded-xl shadow-sm border border-gray-200 mx-auto">
    <form action="{{ route('products.update', $product->id) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-6">
            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-gray-700">Nome do Produto</label>
                <input type="text" name="name" value="{{ old('name', $product->name) }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required>
                @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-gray-700">Descricao</label>
                <textarea name="description" rows="3" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none">{{ old('description', $product->description) }}</textarea>
                @error('description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700">Preco (R$)</label>
                    <input type="number" step="0.01" name="price" value="{{ old('price', $product->price) }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" required>
                    @error('price') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700">Posicao no Cardapio</label>
                    <input type="number" name="position" value="{{ old('position', $product->position) }}" class="p-3 rounded-lg border border-gray-300 outline-none" placeholder="0">
                </div>
            </div>

            <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg border border-gray-100">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $product->is_active) ? 'checked' : '' }} class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500">
                <label class="text-sm font-medium text-gray-700">Produto ativo</label>
            </div>

            <div class="flex items-center gap-3 p-4 bg-amber-50 rounded-lg border border-amber-100">
                <input type="checkbox" name="is_additional_offer" value="1" {{ old('is_additional_offer', $product->is_additional_offer) ? 'checked' : '' }} class="w-5 h-5 text-amber-600 rounded focus:ring-amber-500">
                <label class="text-sm font-medium text-gray-700">Oferta adicional no WhatsApp, sem aparecer no cardapio principal</label>
            </div>

            <div class="flex justify-end gap-4 pt-4">
                <a href="{{ route('products.index') }}" class="px-6 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition">Cancelar</a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg text-sm font-bold transition">Salvar Alteracoes</button>
            </div>
        </div>
    </form>
</div>
@endsection
