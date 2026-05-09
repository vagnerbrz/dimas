@extends('layouts.app')

@section('header', 'Relatorio de Vendas')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="GET" action="{{ route('reports.sales') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <div class="flex flex-col gap-2">
                <label for="period" class="text-sm font-semibold text-gray-700">Periodo</label>
                <select name="period" id="period" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                    <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Hoje</option>
                    <option value="yesterday" {{ $period === 'yesterday' ? 'selected' : '' }}>Ontem</option>
                    <option value="last_7_days" {{ $period === 'last_7_days' ? 'selected' : '' }}>Ultimos 7 dias</option>
                    <option value="month" {{ $period === 'month' ? 'selected' : '' }}>Mes atual</option>
                    <option value="custom" {{ $period === 'custom' ? 'selected' : '' }}>Personalizado</option>
                </select>
            </div>

            <div class="flex flex-col gap-2">
                <label for="start_date" class="text-sm font-semibold text-gray-700">Data inicial</label>
                <input type="date" name="start_date" id="start_date" value="{{ $startDate }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
            </div>

            <div class="flex flex-col gap-2">
                <label for="end_date" class="text-sm font-semibold text-gray-700">Data final</label>
                <input type="date" name="end_date" id="end_date" value="{{ $endDate }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
            </div>

            <div class="md:col-span-2 flex gap-3">
                <button type="submit" class="px-5 py-3 rounded-lg bg-blue-600 text-white text-sm font-bold hover:bg-blue-500">
                    Atualizar Relatorio
                </button>
                <a href="{{ route('reports.sales') }}" class="px-5 py-3 rounded-lg border border-gray-300 text-gray-700 text-sm font-bold hover:bg-gray-50">
                    Limpar
                </a>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-bold uppercase text-gray-400">Faturamento</div>
            <div class="mt-3 text-3xl font-black text-gray-900">R$ {{ number_format($summary['gross_revenue'], 2, ',', '.') }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-bold uppercase text-gray-400">Pedidos Vendidos</div>
            <div class="mt-3 text-3xl font-black text-gray-900">{{ $summary['orders_count'] }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-bold uppercase text-gray-400">Ticket Medio</div>
            <div class="mt-3 text-3xl font-black text-gray-900">R$ {{ number_format($summary['average_ticket'], 2, ',', '.') }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-bold uppercase text-gray-400">Delivery</div>
            <div class="mt-3 text-3xl font-black text-gray-900">R$ {{ number_format($summary['delivery_revenue'], 2, ',', '.') }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-bold uppercase text-gray-400">Balcao</div>
            <div class="mt-3 text-3xl font-black text-gray-900">R$ {{ number_format($summary['counter_revenue'], 2, ',', '.') }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-bold uppercase text-gray-400">Mesa</div>
            <div class="mt-3 text-3xl font-black text-gray-900">R$ {{ number_format($summary['table_revenue'], 2, ',', '.') }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-lg font-bold text-gray-800">Vendas por Dia</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-6 py-3 border-b">Data</th>
                            <th class="px-6 py-3 border-b">Pedidos</th>
                            <th class="px-6 py-3 border-b text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($dailySales as $day)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ \Carbon\Carbon::parse($day->sale_date)->format('d/m/Y') }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $day->orders_count }}</td>
                                <td class="px-6 py-4 text-sm text-right font-semibold text-gray-900">R$ {{ number_format((float) $day->total_amount, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">Sem vendas no periodo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-lg font-bold text-gray-800">Formas de Pagamento</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-6 py-3 border-b">Metodo</th>
                            <th class="px-6 py-3 border-b">Pedidos</th>
                            <th class="px-6 py-3 border-b text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($paymentBreakdown as $payment)
                            @php
                                $label = match ($payment->payment_method) {
                                    'pix' => 'Pix',
                                    'debit' => 'Debito',
                                    'credit' => 'Credito',
                                    'cash' => 'Dinheiro',
                                    default => 'Nao informado',
                                };
                            @endphp
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $label }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $payment->orders_count }}</td>
                                <td class="px-6 py-4 text-sm text-right font-semibold text-gray-900">R$ {{ number_format((float) $payment->total_amount, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">Sem pagamentos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-lg font-bold text-gray-800">Canal de Venda</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-6 py-3 border-b">Canal</th>
                            <th class="px-6 py-3 border-b">Pedidos</th>
                            <th class="px-6 py-3 border-b text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($channelBreakdown as $channel)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ ['delivery' => 'Delivery', 'counter' => 'Balcao', 'table' => 'Mesa'][$channel->type] ?? ucfirst($channel->type) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $channel->orders_count }}</td>
                                <td class="px-6 py-4 text-sm text-right font-semibold text-gray-900">R$ {{ number_format((float) $channel->total_amount, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">Sem vendas por canal no periodo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-lg font-bold text-gray-800">Produtos Mais Vendidos</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-6 py-3 border-b">Produto</th>
                            <th class="px-6 py-3 border-b">Qtd</th>
                            <th class="px-6 py-3 border-b text-right">Receita</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($topProducts as $item)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $item->product->name ?? 'Produto removido' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ (int) $item->total_quantity }}</td>
                                <td class="px-6 py-4 text-sm text-right font-semibold text-gray-900">R$ {{ number_format((float) $item->total_revenue, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">Sem itens vendidos no periodo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-lg font-bold text-gray-800">Pedidos Considerados na Venda</h2>
                <p class="text-sm text-gray-500 mt-1">Entram apenas pedidos aceitos, em preparo, enviados ou entregues.</p>
            </div>
            <div class="overflow-x-auto max-h-[480px]">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold sticky top-0">
                        <tr>
                            <th class="px-6 py-3 border-b">Pedido</th>
                            <th class="px-6 py-3 border-b">Cliente</th>
                            <th class="px-6 py-3 border-b">Status</th>
                            <th class="px-6 py-3 border-b text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($orders as $order)
                            <tr>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900">#{{ $order->id }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $order->customer->name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    @php
                                        $statusLabel = [
                                            'pending' => 'Pendente',
                                            'preparing' => 'Em Preparo',
                                            'shipped' => 'Saindo para Entrega',
                                            'delivered' => 'Entregue',
                                        ][$order->status] ?? ucfirst($order->status);
                                    @endphp
                                    {{ $statusLabel }}
                                </td>
                                <td class="px-6 py-4 text-sm text-right font-semibold text-gray-900">R$ {{ number_format($order->total_amount, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">Nenhum pedido encontrado para esse filtro.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
