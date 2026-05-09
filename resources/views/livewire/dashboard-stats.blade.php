<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-blue-500 border border-gray-200">
        <div class="text-sm font-medium text-gray-500 uppercase tracking-wider">Pedidos Hoje</div>
        <div class="text-3xl font-bold text-gray-800 mt-1">{{ $totalOrders }}</div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-green-500 border border-gray-200">
        <div class="text-sm font-medium text-gray-500 uppercase tracking-wider">Receita Hoje</div>
        <div class="text-3xl font-bold text-gray-800 mt-1">R$ {{ number_format($totalRevenue, 2, ',', '.') }}</div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-purple-500 border border-gray-200">
        <div class="text-sm font-medium text-gray-500 uppercase tracking-wider">Produtos Ativos</div>
        <div class="text-3xl font-bold text-gray-800 mt-1">{{ $totalProducts }}</div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-yellow-500 border border-gray-200">
        <div class="text-sm font-medium text-gray-500 uppercase tracking-wider">Pedidos Ativos Hoje</div>
        <div class="text-3xl font-bold text-gray-800 mt-1">{{ $activeOrders }}</div>
    </div>
</div>
