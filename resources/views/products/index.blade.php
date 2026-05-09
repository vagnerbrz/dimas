@extends('layouts.app')

@section('header', 'Gestao de Cardapio')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h2 class="text-lg font-semibold text-gray-700">Produtos do sistema</h2>
    <a href="{{ route('products.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
        <span>+ Novo Produto</span>
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @foreach($products as $product)
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-between transition hover:shadow-md">
            <div class="flex justify-between items-start mb-4 gap-3">
                <div class="space-y-2">
                    <h3 class="font-bold text-gray-800 text-lg">{{ $product->name }}</h3>
                    <p class="text-gray-500 text-sm">{{ Str::limit($product->description, 60) }}</p>
                    <div class="flex flex-wrap gap-2">
                        @if($product->is_additional_offer)
                            <span class="px-2 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                Oferta adicional
                            </span>
                        @else
                            <span class="px-2 py-1 rounded-full text-[11px] font-bold bg-blue-100 text-blue-700 border border-blue-200">
                                Cardapio principal
                            </span>
                        @endif
                    </div>
                </div>

                <div class="text-lg font-bold text-blue-600">
                    R$ {{ number_format($product->price, 2, ',', '.') }}
                </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                <form action="{{ route('products.update', $product->id) }}" method="POST" class="inline">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="is_active" value="{{ $product->is_active ? '0' : '1' }}">
                    <button type="submit" class="group flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold transition-all duration-200 border {{ $product->is_active ? 'bg-green-100 text-green-700 border-green-200 hover:bg-green-200' : 'bg-gray-100 text-gray-500 border-gray-200 hover:bg-gray-200' }}">
                        <span class="w-2 h-2 rounded-full {{ $product->is_active ? 'bg-green-500 ring-2 ring-green-200' : 'bg-gray-400' }}"></span>
                        {{ $product->is_active ? 'Ativo' : 'Inativo' }}
                    </button>
                </form>

                <div class="flex gap-2">
                    <a href="{{ route('products.edit', $product->id) }}" class="p-2 text-gray-400 hover:text-blue-600 transition" title="Editar">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                    </a>
                    <form action="{{ route('products.destroy', $product->id) }}" method="POST" onsubmit="return confirm('Deseja remover este produto?')">
                        @csrf
                        @method('DELETE')
                        <button class="p-2 text-gray-400 hover:text-red-600 transition" title="Excluir">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
