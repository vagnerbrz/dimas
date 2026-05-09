<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurante do Dimas - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-slate-900 text-white flex flex-col">
            <div class="p-6 text-2xl font-bold border-b border-slate-800">
                Restaurante do Dimas
            </div>
            <nav class="flex-1 p-4 space-y-2">
                <a href="{{ route('dashboard') }}" class="block p-3 rounded-lg transition {{ request()->routeIs('dashboard') ? 'bg-blue-600' : 'hover:bg-slate-800' }}">
                    📊 Dashboard
                </a>
                <a href="{{ route('products.index') }}" class="block p-3 rounded-lg transition {{ request()->routeIs('products.*') ? 'bg-blue-600' : 'hover:bg-slate-800' }}">
                    📋 Cardápio
                </a>
                <a href="{{ route('orders.index') }}" class="block p-3 rounded-lg transition {{ request()->routeIs('orders.*') ? 'bg-blue-600' : 'hover:bg-slate-800' }}">
                    🛍️ Pedidos
                </a>
                <a href="{{ route('customers.index') }}" class="block p-3 rounded-lg transition {{ request()->routeIs('customers.*') ? 'bg-blue-600' : 'hover:bg-slate-800' }}">
                    👥 Clientes
                </a>
                <a href="{{ route('human-conversations.index') }}" class="block p-3 rounded-lg transition {{ request()->routeIs('human-conversations.*') ? 'bg-blue-600' : 'hover:bg-slate-800' }}">
                    Atendimento Humano
                </a>
                <a href="{{ route('whatsapp.settings') }}" class="block p-3 rounded-lg transition {{ request()->routeIs('whatsapp.settings') ? 'bg-blue-600' : 'hover:bg-slate-800' }}">
                    Autenticador WhatsApp
                </a>
                <a href="{{ route('reports.sales') }}" class="block p-3 rounded-lg transition {{ request()->routeIs('reports.sales') ? 'bg-blue-600' : 'hover:bg-slate-800' }}">
                    Relatorio de Vendas
                </a>
            </nav>
            <div class="p-4 border-t border-slate-800 flex flex-col gap-2">
                <form action="{{ route('logout') }}" method="POST" class="w-full">
                    @csrf
                    <button type="submit" class="w-full text-left p-3 rounded-lg transition text-slate-400 hover:bg-slate-800 hover:text-white flex items-center gap-2 text-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7"></path></svg>
                        Sair do Sistema
                    </button>
                </form>
            </div>
            <div class="p-4 border-t border-slate-800 text-sm text-slate-400 text-center">
                v1.0 - Alta Performance
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col overflow-y-auto">
            <header class="bg-white shadow-sm p-4 flex justify-between items-center">
                <h1 class="text-xl font-semibold text-gray-800">@yield('header')</h1>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-500">Olá, Administrador</span>
                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">A</div>
                </div>
            </header>
            <div class="p-6">
                @yield('content')
            </div>
        </main>
    </div>
    @livewireScripts

    <!-- Laravel Echo -->
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.0/dist/echo.iife.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>

    <script>
        // Configurar Laravel Echo
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: '{{ env('PUSHER_APP_KEY') }}',
            wsHost: '{{ env('PUSHER_HOST', '127.0.0.1') }}',
            wsPort: {{ env('PUSHER_PORT', 6001) }},
            wssPort: {{ env('PUSHER_PORT', 6001) }},
            forceTLS: false,
            encrypted: false,
            disableStats: true,
            enabledTransports: ['ws', 'wss'],
            cluster: '{{ env('PUSHER_APP_CLUSTER', 'mt1') }}',
        });
    </script>

    @stack('scripts')
</body>
</html>
