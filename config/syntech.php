<?php

// Syntech stock & price sync. The feed URL lives ONLY in .env (SYNTECH_FEED_URL).
return [
    'feed_url' => env('SYNTECH_FEED_URL'),
    'supplier_name' => env('SYNTECH_SUPPLIER_NAME', 'Syntech'),
    'vat' => 0.15,
    // v3 price floor
    'fee_percent' => 0.039,
    'fee_fixed' => 1.0,
    'min_profit' => 0.30,
    'cost_buffer' => 0.05,
    'free_shipping_threshold' => 1500,
    'shipping_excl' => 200,
    // Re-use a downloaded feed younger than this (minutes). Keeps us far below 5 downloads/hour.
    'feed_max_age_minutes' => (int) env('SYNTECH_FEED_MAX_AGE', 60),
    'http_timeout' => (int) env('SYNTECH_HTTP_TIMEOUT', 180),
    'chunk_size' => (int) env('SYNTECH_CHUNK', 100),
    'chunk_pause_ms' => (int) env('SYNTECH_CHUNK_PAUSE_MS', 500),
    // Price moves bigger than this (fraction) are logged and NOT applied unless --force-prices.
    'max_price_change' => (float) env('SYNTECH_MAX_PRICE_CHANGE', 0.40),
    // Optional extra SKU list (one per line), for products imported before supplier_name was set.
    'sku_file' => storage_path('app/syntech/skus.txt'),
    'storage_dir' => storage_path('app/syntech'),
    'import_dir' => storage_path('imports/syntech'),
];
