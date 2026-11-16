<?php

// Astrum API. The key lives ONLY in .env (ASTRUM_API_KEY). Never log, print or write it.
// Selling prices use a tiered margin on ex-VAT cost (20% under R500, 15% for R500–R5,000,
// 10% above R5,000), chosen by the resulting price. No FirstShop cap. Never below no-loss.
// Optional ASTRUM_BASE_URL defaults to https://astrum.africa/v1.
// Commands also accept the same values under config('services.astrum.*') if that key is added
// to config/services.php. Do not replace services.php; append the key if you want it there.

return [
    'key' => env('ASTRUM_API_KEY'),
    'base_url' => env('ASTRUM_BASE_URL', 'https://astrum.africa/v1'),
    'http_timeout' => (int) env('ASTRUM_HTTP_TIMEOUT', 45),
    'stock_batch' => (int) env('ASTRUM_STOCK_BATCH', 40),
    'chunk_pause_ms' => (int) env('ASTRUM_CHUNK_PAUSE_MS', 600),
    'min_products' => (int) env('ASTRUM_MIN_PRODUCTS', 500),
    'max_oos_fraction' => (float) env('ASTRUM_MAX_OOS_FRACTION', 0.30),
    'missing_oos' => (bool) env('ASTRUM_MISSING_OOS', true),
    'max_price_change' => (float) env('ASTRUM_MAX_PRICE_CHANGE', 0.40),
    // Safety cap on a single upward move in astrum:sync (default 200%).
    'max_price_increase' => (float) env('ASTRUM_MAX_PRICE_INCREASE', 2.0),
    // Safety cap on a single move in the planned astrum:reprice run (default 200%).
    'reprice_max_change' => (float) env('ASTRUM_REPRICE_MAX_CHANGE', 2.0),
    // Tiered margin bands: walk-down by the resulting incl-VAT price (see AstrumApi::tieredPrice).
    'price_band_20' => (int) env('ASTRUM_PRICE_BAND_20', 500),
    'price_band_15' => (int) env('ASTRUM_PRICE_BAND_15', 5000),
    'margin_20' => 0.20,
    'margin_15' => 0.15,
    'margin_10' => 0.10,
    'oos_guard_feeds' => [
        ['name' => 'Syntech', 'path' => storage_path('app/syntech/feed.xml'), 'item' => 'product', 'code' => 'sku', 'ean' => 'ean', 'qty' => ['cptstock', 'jhbstock', 'dbnstock']],
        ['name' => 'Esquire', 'path' => storage_path('app/esquire/feed.xml'), 'item' => 'Product', 'code' => 'ProductCode', 'ean' => null, 'qty' => ['AvailableQty']],
    ],
    'oos_guard_max_age_hours' => (int) env('ASTRUM_OOS_GUARD_MAX_AGE_HOURS', 24),
    'sku_file' => storage_path('app/astrum/skus.txt'),
    'watch_file' => storage_path('app/astrum/import_new_watch.txt'),
    'catalog_file' => storage_path('app/astrum/import_new_catalog.csv'),
    'storage_dir' => storage_path('app/astrum'),
    'log_file' => storage_path('logs/astrum_sync.log'),
    'import_new_max' => (int) env('ASTRUM_IMPORT_NEW_MAX', 25),
    'import_new_service_hashes' => [
        '49b2b930f163630703520f8ad2547aa8',
        'fa966431c3cfa88103fd0ecde12a669c',
    ],
    'import_new_sync_hashes' => [
        '0122e605203436b2d753d8847bcae286',
    ],
];
