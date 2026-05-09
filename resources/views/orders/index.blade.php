@extends('layouts.app')

@section('header', 'Gestao de Pedidos')

@section('content')
<div class="flex justify-between items-center mb-6">
    <div class="flex gap-2">
        <a href="{{ route('orders.index') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">Todos os Pedidos</a>
        <a href="{{ route('orders.index', ['status' => 'awaiting_acceptance']) }}" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">Aguardando Aceite</a>
        <a href="{{ route('orders.index', ['status' => 'pending']) }}" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">Pendentes</a>
        <a href="{{ route('orders.index', ['status' => 'preparing']) }}" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">Em Preparo</a>
    </div>

    <div class="flex items-center gap-3">
        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Atualizacao automatica a cada 10s</div>
        <a href="{{ route('orders.create') }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-bold transition flex items-center gap-2">
            <span>+ Novo Pedido</span>
        </a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <table class="w-full text-left">
        <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold">
            <tr>
                <th class="px-6 py-3 border-b">ID</th>
                <th class="px-6 py-3 border-b">Cliente</th>
                <th class="px-6 py-3 border-b">Status</th>
                <th class="px-6 py-3 border-b">Total</th>
                <th class="px-6 py-3 border-b text-right">Acoes</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($orders as $order)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 font-medium text-gray-900">#{{ $order->id }}</td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-bold text-gray-800">{{ $order->customer->name }}</div>
                        <div class="text-xs text-gray-500">{{ $order->customer->phone }}</div>
                        <div class="text-xs text-gray-400 uppercase mt-1">{{ $order->type_label }}</div>
                    </td>
                    <td class="px-6 py-4">
                        @php
                            $statusColors = [
                                'awaiting_acceptance' => 'bg-amber-100 text-amber-800 border-amber-200',
                                'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                                'preparing' => 'bg-blue-100 text-blue-700 border-blue-200',
                                'shipped' => 'bg-purple-100 text-purple-700 border-purple-200',
                                'delivered' => 'bg-green-100 text-green-700 border-green-200',
                                'cancelled' => 'bg-red-100 text-red-700 border-red-200',
                            ];
                            $color = $statusColors[$order->status] ?? 'bg-gray-100 text-gray-700 border-gray-200';
                            $statusLabels = [
                                'awaiting_acceptance' => 'Aguardando Aceite',
                                'pending' => 'Pendente',
                                'preparing' => 'Em Preparo',
                                'shipped' => 'Saindo para Entrega',
                                'delivered' => 'Entregue',
                                'cancelled' => 'Cancelado',
                            ];
                        @endphp
                        <span class="px-2 py-1 rounded-full text-xs font-medium border {{ $color }}">
                            {{ $statusLabels[$order->status] ?? ucfirst($order->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 font-semibold text-gray-800">R$ {{ number_format($order->total_amount, 2, ',', '.') }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end items-center gap-3">
                            @if($order->status === 'awaiting_acceptance')
                                <form action="{{ route('orders.accept', $order->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-emerald-600 hover:text-emerald-800 font-medium text-sm">
                                        Aceitar
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('orders.print', $order->id) }}" target="_blank" class="text-slate-700 hover:text-slate-900 font-medium text-sm">Imprimir</a>
                            <a href="{{ route('orders.show', $order->id) }}" class="text-blue-600 hover:text-blue-800 font-medium text-sm">Detalhes</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-10 text-center text-gray-500">Nenhum pedido encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-4 border-t border-gray-100">
        {{ $orders->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const refreshIntervalMs = 10000;

        window.setInterval(() => {
            if (document.visibilityState !== 'visible') {
                return;
            }

            window.location.reload();
        }, refreshIntervalMs);
    })();
</script>
@endpush
