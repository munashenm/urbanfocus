<?php

return [
    'name' => env('APP_NAME', 'Urban Focus'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'https://www.urbanfocus.co.za'),
    'timezone' => env('APP_TIMEZONE', 'Africa/Johannesburg'),
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_ZA'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],
    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
    'phone' => env('BUSINESS_PHONE', '087 550 1813'),
    'email' => env('BUSINESS_EMAIL', 'sales@urbanfocus.co.za'),
    'address' => env('BUSINESS_ADDRESS', 'South Africa'),
    'vat_rate' => (float) env('VAT_RATE', 15),
    'api_key' => env('API_KEY'),
];
