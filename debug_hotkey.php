<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Auth;
use App\Models\Setting;

echo "=== Debug: Hotkeys Redirecionando para Login ===\n\n";

// 1. Verificar autenticação atual
echo "1. Status de Autenticação:\n";
if (Auth::check()) {
    $user = Auth::user();
    echo "   ✅ AUTENTICADO como: {$user->name} ({$user->email})\n";
    echo "   User ID: {$user->id}\n";
} else {
    echo "   ❌ NÃO AUTENTICADO\n";
    echo "   Isso explica o redirecionamento!\n";
}

// 2. Verificar cookies
echo "\n2. Cookies Presentes:\n";
$cookies = ['laravel_session', 'XSRF-TOKEN', 'dimas_session'];
foreach ($cookies as $cookie) {
    if (isset($_COOKIE[$cookie])) {
        echo "   ✅ {$cookie}: " . substr($_COOKIE[$cookie], 0, 20) . "...\n";
    } else {
        echo "   ❌ {$cookie}: Ausente\n";
    }
}

// 3. Verificar se há problema com a rota
echo "\n3. Rotas e Middleware:\n";
echo "   Rota /dashboard: protegida por middleware('auth')\n";
echo "   Se não autenticado → redireciona para /login\n";

// 4. Testar o método useQuickReply diretamente
echo "\n4. Testando método useQuickReply:\n";
try {
    // Simular chamada do método
    $component = new \App\Livewire\WhatsAppChat();

    // Verificar se quickReplies está carregado
    if (empty($component->quickReplies)) {
        echo "   ⚠️  quickReplies não carregado\n";
    } else {
        echo "   ✅ quickReplies carregado: " . count($component->quickReplies) . " templates\n";
    }

    // Verificar se há selectedPhone
    echo "   selectedPhone: " . ($component->selectedPhone ?: 'Nenhum') . "\n";

} catch (Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

// 5. Possíveis causas
echo "\n5. Possíveis Causas do Problema:\n";
echo "   a) Sessão expirou (mais provável)\n";
echo "   b) Cookie laravel_session corrompido\n";
echo "   c) Problema com CSRF token (Livewire)\n";
echo "   d) Componente sendo remontado com sessão expirada\n";

// 6. Soluções
echo "\n6. Soluções para Testar:\n";
echo "   A. Limpar cookies e fazer login novamente\n";
echo "   B. Aumentar SESSION_LIFETIME no .env\n";
echo "   C. Verificar se está usando 127.0.0.1 consistentemente\n";
echo "   D. Testar em navegador diferente\n";

// 7. Teste prático
echo "\n7. Teste Prático:\n";
echo "   Acesse: http://127.0.0.1:8000/dashboard\n";
echo "   Verifique se está logado (canto superior direito)\n";
echo "   Tente clicar nos botões F1-F8\n";
echo "   Se redirecionar, a sessão expirou\n";

echo "\n=== Fim do Debug ===\n";