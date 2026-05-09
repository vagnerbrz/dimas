@extends('layouts.app')

@section('header', 'Dashboard Operacional')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
    <!-- Stats Section -->
    <div class="md:col-span-2 lg:col-span-3">
        @livewire('dashboard-stats')
    </div>

    <!-- Orders Table -->
    <div class="md:col-span-2 lg:col-span-3 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-3 lg:p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <div>
                <h2 class="text-base lg:text-lg font-bold text-gray-700">Pedidos de Hoje</h2>
                <p class="text-xs text-gray-500 mt-1">Central limpa com os dados do dia atual.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('orders.create') }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-xs lg:text-sm font-bold transition">+ Novo Pedido</a>
                <a href="{{ route('orders.index') }}" class="text-blue-600 hover:underline text-xs lg:text-sm font-medium">Ver todos</a>
            </div>
        </div>
        <div class="overflow-x-auto">
            @livewire('orders-table', ['compact' => true, 'todayOnly' => true])
        </div>
    </div>
</div>
@endsection
