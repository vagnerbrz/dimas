<?php

return [
    'prefer_env' => env('PRINT_PREFER_ENV', false),

    'local_api_token' => env('LOCAL_PRINT_API_TOKEN', ''),
    // Usados pelo agente na maquina do restaurante.
    'local_api_url' => env('LOCAL_PRINT_API_URL'),
    'local_poll_interval' => env('LOCAL_PRINT_POLL_INTERVAL', 5),
    'local_agent_name' => env('LOCAL_PRINT_AGENT_NAME'),
    'local_max_attempts' => env('LOCAL_PRINT_MAX_ATTEMPTS', 10),
    'local_retry_after_seconds' => env('LOCAL_PRINT_RETRY_AFTER_SECONDS', 30),
    // Job reservado por um agente que nao confirmou nesse tempo volta para a fila.
    'local_claim_timeout_seconds' => env('LOCAL_PRINT_CLAIM_TIMEOUT_SECONDS', 120),
    // Sem contato do agente por esse tempo, o painel mostra a impressora offline.
    'local_offline_after_seconds' => env('LOCAL_PRINT_OFFLINE_AFTER_SECONDS', 60),

    'defaults' => [
        'print_enabled' => env('PRINT_ENABLED', 'false'),
        'print_connection' => env('PRINT_CONNECTION', 'network'),
        'print_host' => env('PRINT_HOST'),
        'print_port' => env('PRINT_PORT', '9100'),
        'print_windows_connector' => env('PRINT_WINDOWS_CONNECTOR'),
        'print_file_connector' => env('PRINT_FILE_CONNECTOR'),
        'print_microservice_url' => env('PRINT_MICROSERVICE_URL'),
        'print_microservice_token' => env('PRINT_MICROSERVICE_TOKEN'),
        'print_store_name' => env('PRINT_STORE_NAME', env('APP_NAME', 'Restaurante do Dimas')),
    ],

    'settings_map' => [
        'print_enabled' => 'PRINT_ENABLED',
        'print_connection' => 'PRINT_CONNECTION',
        'print_host' => 'PRINT_HOST',
        'print_port' => 'PRINT_PORT',
        'print_windows_connector' => 'PRINT_WINDOWS_CONNECTOR',
        'print_file_connector' => 'PRINT_FILE_CONNECTOR',
        'print_microservice_url' => 'PRINT_MICROSERVICE_URL',
        'print_microservice_token' => 'PRINT_MICROSERVICE_TOKEN',
        'print_store_name' => 'PRINT_STORE_NAME',
    ],
];
