<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Restaurante do Dimas</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 flex items-center justify-center h-screen">
    <div class="w-full max-w-md p-6">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
            <div class="p-8 text-center bg-slate-900 text-white">
                <h1 class="text-2xl font-bold">Restaurante do Dimas</h1>
                <p class="text-slate-400 text-sm mt-2">Entre com suas credenciais para acessar</p>
            </div>

            <div class="p-8">
                @if($errors->any())
                    <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg text-sm border border-red-200">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form action="{{ route('login') }}" method="POST" class="space-y-6">
                    @csrf
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">E-mail</label>
                        <input type="email" name="email" required
                               class="w-full p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none transition"
                               placeholder=" email@email.com">
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">Senha</label>
                        <input type="password" name="password" required
                               class="w-full p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none transition"
                               placeholder="••••••••">
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition duration-200 shadow-lg">
                        Entrar no Painel
                    </button>
                </form>
            </div>
        </div>
        <p class="text-center text-gray-500 text-xs mt-8">
            © {{ date('Y') }} Restaurante do Dimas - Sistema de Gestao Operacional
        </p>
    </div>
</body>
</html>
