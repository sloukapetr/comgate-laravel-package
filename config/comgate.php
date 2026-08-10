<?php

declare(strict_types=1);

return [
    'merchant_id' => env('COMGATE_MERCHANT_ID'),
    'secret' => env('COMGATE_SECRET'),
    'test' => (bool) env('COMGATE_TEST', true),
    'base_url' => env('COMGATE_BASE_URL', 'https://payments.comgate.cz/v2.0'),
    'timeout' => (int) env('COMGATE_TIMEOUT', 30),

    'webhook' => [
        'enabled' => (bool) env('COMGATE_WEBHOOK_ENABLED', true),
        'path' => env('COMGATE_WEBHOOK_PATH', '/comgate/webhook'),
        'middleware' => ['api'],
        'route_name' => env('COMGATE_WEBHOOK_ROUTE_NAME', 'comgate.webhook'),
    ],
];
