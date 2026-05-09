<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Auth;

echo "=== Teste de Sessão e Autenticação ===\n\n";

// Verificar configurações de sessão
echo "Configurações de Sessão:\n";
echo "- Driver: " . config('session.driver') . "\n";
echo "- Lifetime: " . config('session.lifetime') . " minutos\n";
echo "- Expire on close: " . (config('session.expire_on_close') ? 'Sim' : 'Não') . "\n\n";

// Verificar se há usuário autenticado
if (Auth::check()) {
    $user = Auth::user();
    echo "✅ Usuário autenticado:\n";
    echo "   ID: " . $user->id . "\n";
    echo "   Nome: " . $user->name . "\n";
    echo "   Email: " . $user->email . "\n";

    // Verificar tempo desde último login
    $sessionLifetime = config('session.lifetime', 120);
    echo "   Sessão expira em: " . $sessionLifetime . " minutos de inatividade\n";
} else {
    echo "❌ Nenhum usuário autenticado\n";
    echo "   Acesse: http://127.0.0.1:8000/login\n";
    echo "   Faça login para testar\n";
}

// Verificar cookies de sessão
echo "\n=== Cookies de Sessão ===\n";
if (isset($_COOKIE['laravel_session'])) {
    echo "✅ Cookie laravel_session presente\n";
    echo "   Valor: " . substr($_COOKIE['laravel_session'], 0, 20) . "...\n";
} else {
    echo "❌ Cookie laravel_session não encontrado\n";
}

// Verificar se há problema com CSRF token (Livewire usa)
echo "\n=== Livewire e CSRF ===\n";
if (isset($_COOKIE['XSRF-TOKEN'])) {
    echo "✅ Cookie XSRF-TOKEN presente\n";
} else {
    echo "⚠️  Cookie XSRF-TOKEN não encontrado (pode ser normal)\n";
}

// Dicas para resolver problemas de sessão
echo "\n=== Solução de Problemas de Sessão ===\n";
echo "1. Limpe cookies do navegador e faça login novamente\n";
echo "2. Verifique se o domínio é consistente (127.0.0.1 vs localhost)\n";
echo "3. Aumente o tempo de sessão no .env:\n";
echo "   SESSION_LIFETIME=360 (6 horas)\n";
echo "4. Para desenvolvimento, use sessão em arquivo (já está)\n";

// Testar redirecionamento
echo "\n=== Teste de Redirecionamento ===\n";
echo "URL de login: " . route('login') . "\n";
echo "URL do dashboard: " . route('dashboard') . "\n";

// Verificar middleware de autenticação
echo "\n=== Middleware de Autenticação ===\n";
echo "Dashboard protegido por: middleware('auth')\n";
echo "Isso redireciona para /login se não autenticado\n";

echo "\n✅ Teste completo!\n";