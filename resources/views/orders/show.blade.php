@extends('layouts.app')

@section('header', 'Detalhes do Pedido #' . $order->id)

@section('content')
@php
    $statusLabels = [
        'awaiting_acceptance' => 'Aguardando Aceite',
        'pending' => 'Pendente',
        'preparing' => 'Em Preparo',
        'shipped' => 'Saindo para Entrega',
        'delivered' => 'Entregue',
        'cancelled' => 'Cancelado',
    ];

    $paymentLabels = [
        'pix' => 'Pix',
        'debit' => 'Debito',
        'credit' => 'Credito',
        'cash' => 'Dinheiro',
    ];
@endphp

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex justify-between items-center">
        <a href="{{ route('orders.index') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-6-6m0 0l6-6m6 12h-12"></path></svg>
            Voltar para lista
        </a>

        <div class="flex gap-2">
            <a href="{{ route('orders.print', $order->id) }}" target="_blank" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-bold hover:bg-slate-800">
                Imprimir Cupom
            </a>

            @if($order->status === 'awaiting_acceptance')
                <form action="{{ route('orders.accept', $order->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-500">
                        Aceitar Pedido
                    </button>
                </form>
            @endif

            <form action="{{ route('orders.update', $order->id) }}" method="POST">
                @csrf
                @method('PUT')
                <select name="status" onchange="this.form.submit()" class="p-2 rounded-lg border border-gray-300 text-sm font-bold outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="awaiting_acceptance" {{ $order->status == 'awaiting_acceptance' ? 'selected' : '' }}>Aguardando Aceite</option>
                    <option value="pending" {{ $order->status == 'pending' ? 'selected' : '' }}>Pendente</option>
                    <option value="preparing" {{ $order->status == 'preparing' ? 'selected' : '' }}>Em Preparo</option>
                    <option value="shipped" {{ $order->status == 'shipped' ? 'selected' : '' }}>Saindo para Entrega</option>
                    <option value="delivered" {{ $order->status == 'delivered' ? 'selected' : '' }}>Entregue</option>
                    <option value="cancelled" {{ $order->status == 'cancelled' ? 'selected' : '' }}>Cancelado</option>
                </select>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-1 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <h3 class="text-sm font-bold text-gray-400 uppercase mb-4">Aceite Operacional</h3>

                @if($order->status === 'awaiting_acceptance')
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 space-y-3">
                        <div class="text-sm font-bold text-amber-900">Pedido aguardando liberacao do operador.</div>
                        <div class="text-sm text-amber-800">
                            O cliente concluiu o pedido no WhatsApp, mas ele ainda nao entrou na fila de preparo.
                        </div>
                        <form action="{{ route('orders.accept', $order->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-500">
                                Aceitar Pedido Agora
                            </button>
                        </form>
                    </div>
                @else
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                        <div class="text-sm font-bold text-emerald-900">
                            Pedido liberado para operacao: {{ $statusLabels[$order->status] ?? ucfirst($order->status) }}
                        </div>
                    </div>
                @endif
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <h3 class="text-sm font-bold text-gray-400 uppercase mb-4">Tipo de Pedido</h3>
                <div class="flex items-center gap-2">
                    @if($order->isDelivery())
                        <span class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded-full border border-blue-200 uppercase">Delivery</span>
                    @elseif($order->isTable())
                        <span class="px-3 py-1 bg-emerald-100 text-emerald-700 text-xs font-bold rounded-full border border-emerald-200 uppercase">Mesa</span>
                    @else
                        <span class="px-3 py-1 bg-orange-100 text-orange-700 text-xs font-bold rounded-full border border-orange-200 uppercase">Balcao</span>
                    @endif
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <h3 class="text-sm font-bold text-gray-400 uppercase mb-4">Cliente</h3>
                <div class="space-y-3">
                    <div class="text-lg font-bold text-gray-800">{{ $order->customer->name }}</div>
                    <div class="text-sm text-gray-600">{{ $order->customer->phone }}</div>

                    @if($order->isDelivery() && $order->address)
                        <div class="pt-4 border-t border-gray-100">
                            <div class="text-xs font-bold text-gray-400 uppercase mb-1">Endereco de Entrega</div>
                            <div class="text-sm text-gray-600">
                                {{ $order->address->street }}, {{ $order->address->number }}<br>
                                {{ $order->address->neighborhood }} - {{ $order->address->city }}
                            </div>
                        </div>
                    @elseif($order->isCounter())
                        <div class="pt-4 border-t border-gray-100">
                            <div class="text-xs font-bold text-gray-400 uppercase mb-1">Retirada</div>
                            <div class="text-sm text-gray-600">
                                Pedido para retirada no balcao.
                            </div>
                        </div>
                    @elseif($order->isTable())
                        <div class="pt-4 border-t border-gray-100">
                            <div class="text-xs font-bold text-gray-400 uppercase mb-1">Consumo no Local</div>
                            <div class="text-sm text-gray-600">
                                Pedido para consumo na mesa.
                            </div>
                        </div>
                    @endif

                    <div class="pt-4 border-t border-gray-100">
                        <div class="text-xs font-bold text-gray-400 uppercase mb-1">Pagamento</div>
                        <div class="text-sm text-gray-600">
                            {{ $paymentLabels[$order->payment_method] ?? 'Nao informado' }}
                            @if($order->payment_method === 'cash')
                                <br>
                                <span class="text-xs text-gray-500">
                                    Troco para:
                                    {{ $order->change_for ? 'R$ ' . number_format($order->change_for, 2, ',', '.') : 'Sem troco' }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="md:col-span-2 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Itens do Pedido</h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold">
                            <tr>
                                <th class="px-4 py-2 border-b">Produto</th>
                                <th class="px-4 py-2 border-b text-center">Qtd</th>
                                <th class="px-4 py-2 border-b text-right">Preco Unit.</th>
                                <th class="px-4 py-2 border-b text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($order->items as $item)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-4 text-sm text-gray-800 font-medium">{{ $item->product->name }}</td>
                                    <td class="px-4 py-4 text-center text-sm text-gray-600">{{ $item->quantity }}x</td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-600">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-800 font-bold">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end mt-6 pt-6 border-t border-gray-100">
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Itens: R$ {{ number_format($order->items->sum('subtotal'), 2, ',', '.') }}</div>
                        @if($order->isDelivery())
                            <div class="text-sm text-gray-500">Entrega: R$ {{ number_format((float) $order->delivery_fee, 2, ',', '.') }}</div>
                            @if($order->delivery_distance_km !== null)
                                <div class="text-sm text-gray-500">Distancia: {{ number_format((float) $order->delivery_distance_km, 2, ',', '.') }} km</div>
                            @endif
                        @endif
                        <span class="text-gray-500 text-sm uppercase font-bold">Total Geral:</span>
                        <div class="text-4xl font-black text-gray-900">R$ {{ number_format($order->total_amount, 2, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
