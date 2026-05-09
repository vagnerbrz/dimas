@extends('layouts.app')

@section('header')
    Perfil do Cliente: {{ $customer->name }}
@endsection

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex justify-between items-center">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold text-xl">
                {{ substr($customer->name, 0, 1) }}
            </div>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">{{ $customer->name }}</h2>
                <p class="text-sm text-gray-500 font-medium">{{ $customer->phone }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('customers.edit', $customer->id) }}" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition">Editar Perfil</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-1 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <h3 class="text-sm font-bold text-gray-400 uppercase mb-4">Enderecos Cadastrados</h3>
                <div class="space-y-3">
                    @forelse($customer->addresses as $address)
                        <div class="p-3 rounded-lg border border-gray-100 bg-gray-50 text-sm text-gray-600 relative group hover:bg-blue-50 transition transition-colors">
                            <div class="text-gray-800 font-bold">{{ $address->street }}, {{ $address->number }}</div>
                            <div class="text-xs">{{ $address->neighborhood }} - {{ $address->city }}</div>
                            <div class="flex justify-between items-center mt-2">
                                <div>
                                    @if($address->is_primary)
                                        <span class="px-2 py-0.5 bg-green-100 text-green-700 text-[10px] font-bold rounded-full border border-green-200 uppercase">Principal</span>
                                    @endif
                                </div>

                                <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition">
                                    @if(!$address->is_primary)
                                        <form action="{{ route('addresses.primary', $address->id) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="text-xs font-bold text-blue-600 hover:underline">Definir Principal</button>
                                        </form>
                                    @endif

                                    <form action="{{ route('addresses.destroy', $address->id) }}" method="POST" onsubmit="return confirm('Excluir este endereco?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-bold text-red-600 hover:underline">Excluir</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-500">Nenhum endereco cadastrado.</p>
                    @endforelse

                    <a href="{{ route('customers.address.create', $customer->id) }}" class="block text-center py-2 text-xs font-bold text-blue-600 hover:bg-blue-50 rounded-lg border border-dashed border-blue-300 transition">
                        + Adicionar Novo Endereco
                    </a>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <h3 class="text-sm font-bold text-gray-400 uppercase mb-4">Detalhes da Conta</h3>
                <div class="space-y-3 text-sm text-gray-600">
                    <div class="flex justify-between">
                        <span class="font-medium">E-mail:</span>
                        <span>{{ $customer->email ?? 'Nao informado' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-medium">Sessao WhatsApp:</span>
                        <span>{{ $customer->whatsapp_session ? 'Ativa' : 'Inexistente' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="md:col-span-2">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Historico de Pedidos</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold">
                            <tr>
                                <th class="px-4 py-2 border-b">Pedido</th>
                                <th class="px-4 py-2 border-b">Data</th>
                                <th class="px-4 py-2 border-b">Total</th>
                                <th class="px-4 py-2 border-b">Status</th>
                                <th class="px-4 py-2 border-b text-right">Acao</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($customer->orders as $order)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-4 text-sm font-bold text-gray-800">#{{ $order->id }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-600">{{ $order->created_at->format('d/m/Y') }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-800 font-semibold">R$ {{ number_format($order->total_amount, 2, ',', '.') }}</td>
                                    <td class="px-4 py-4">
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
                                    <td class="px-4 py-4 text-right">
                                        <a href="{{ route('orders.show', $order->id) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Ver Pedido</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Nenhum pedido encontrado para este cliente.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
