<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;

echo "=== Todas as Configurações ===\n\n";

$settings = Setting::all();

if ($settings->isEmpty()) {
    echo "Nenhuma configuração encontrada.\n";
} else {
    foreach ($settings as $setting) {
        $value = $setting->value;
        if (strlen($value) > 50) {
            $value = substr($value, 0, 20) . '...' . substr($value, -20);
        }
        echo str_pad($setting->key, 30) . ": $value\n";
    }
}

// Verificar especificamente configurações do WhatsApp
echo "\n=== Configurações do WhatsApp ===\n";
$whatsappSettings = Setting::where('key', 'like', '%whatsapp%')->get();

$required = [
    'whatsapp_server_url' => 'URL do servidor WPPConnect',
    'whatsapp_session_name' => 'Nome da sessão',
    'whatsapp_session_token' => 'Token da sessão'
];

foreach ($required as $key => $description) {
    $setting = $whatsappSettings->firstWhere('key', $key);
    if ($setting) {
        $value = $setting->value;
        if ($key === 'whatsapp_session_token') {
            $value = substr($value, 0, 10) . '...' . substr($value, -10);
        }
        echo "✅ $description: $value\n";
    } else {
        echo "❌ $description: FALTANDO\n";
    }
}