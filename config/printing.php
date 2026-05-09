<?php

return [
    'prefer_env' => env('PRINT_PREFER_ENV', false),

    'local_api_token' => env('LOCAL_PRINT_API_TOKEN', ''),
    'local_max_attempts' => env('LOCAL_PRINT_MAX_ATTEMPTS', 10),
    'local_retry_after_seconds' => env('LOCAL_PRINT_RETRY_AFTER_SECONDS', 30),

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
