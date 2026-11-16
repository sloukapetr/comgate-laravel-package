<?php

declare(strict_types=1);

return [
    'merchant_id' => env('COMGATE_MERCHANT_ID'),
    'secret' => env('COMGATE_SECRET'),
    'test' => (bool) env('COMGATE_TEST', true),
    'base_url' => env('COMGATE_BASE_URL', 'https://payments.comgate.cz/v2.0'),
    'timeout' => (int) env('COMGATE_TIMEOUT', 30),
    'force_ipv4' => (bool) env('COMGATE_FORCE_IPV4', false),
    'merchants' => [],

    'status' => [
        'base_url' => env('COMGATE_STATUS_BASE_URL', 'https://status.comgate.cz'),
        'timeout' => (int) env('COMGATE_STATUS_TIMEOUT', (int) env('COMGATE_TIMEOUT', 30)),
    ],

    'webhook' => [
        'enabled' => (bool) env('COMGATE_WEBHOOK_ENABLED', true),
        'path' => env('COMGATE_WEBHOOK_PATH', '/comgate/webhook'),
        'middleware' => ['api'],
        'route_name' => env('COMGATE_WEBHOOK_ROUTE_NAME', 'comgate.webhook'),
        'verify_status' => (bool) env('COMGATE_WEBHOOK_VERIFY_STATUS', true),
        'verify_ip' => (bool) env('COMGATE_WEBHOOK_VERIFY_IP', false),
        'ips_url' => env('COMGATE_WEBHOOK_IPS_URL', 'https://payments.comgate.cz/ips-v4'),
    ],

    'defaults' => [
        'country' => env('COMGATE_COUNTRY', 'CZ'),
        'currency' => env('COMGATE_CURRENCY', 'CZK'),
        'lang' => env('COMGATE_LANG', 'cs'),
        'method' => env('COMGATE_METHOD', 'ALL'),
        'category' => env('COMGATE_CATEGORY', 'OTHER'),
        'delivery' => env('COMGATE_DELIVERY', 'ELECTRONIC_DELIVERY'),
    ],
];
