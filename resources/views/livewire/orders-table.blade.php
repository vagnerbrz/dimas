<div class="flex flex-col h-full">
    <div class="p-4 flex items-center gap-4 bg-white border-b border-gray-100">
        <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </span>
            <input wire:model.live="search" type="text" placeholder="Buscar por cliente..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 outline-none transition">
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold">
                <tr>
                    <th class="px-6 py-3 border-b">ID</th>
                    <th class="px-6 py-3 border-b">Cliente</th>
                    <th class="px-6 py-3 border-b">Total</th>
                    <th class="px-6 py-3 border-b">Status</th>
                    <th class="px-6 py-3 border-b text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($orders as $order)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 font-medium text-gray-900">#{{ $order->id }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $order->customer->name }}</td>
                        <td class="px-6 py-4 font-semibold text-gray-800">R$ {{ number_format($order->total_amount, 2, ',', '.') }}</td>
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
                                $statusLabels = [
                                    'awaiting_acceptance' => 'Aguardando Aceite',
                                    'pending' => 'Pendente',
                                    'preparing' => 'Em Preparo',
                                    'shipped' => 'Saindo para Entrega',
                                    'delivered' => 'Entregue',
                                    'cancelled' => 'Cancelado',
                                ];
                                $color = $statusColors[$order->status] ?? 'bg-gray-100 text-gray-700 border-gray-200';
                            @endphp
                            <span class="px-2 py-1 rounded-full text-xs font-medium border {{ $color }}">
                                {{ $statusLabels[$order->status] ?? ucfirst($order->status) }}
                            </span>
                        </td>
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
                                <a href="{{ route('orders.show', $order->id) }}" class="text-blue-600 hover:text-blue-800 font-medium text-sm">Ver Detalhes</a>
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
    </div>

    <div class="p-4 border-t border-gray-100">
        {{ $orders->links() }}
    </div>
</div>
