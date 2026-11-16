<?php

// Pinnacle STOCK-ONLY sync. The feed URL lives ONLY in .env (PINNACLE_FEED_URL); never logged.
return [
    'feed_url' => env('PINNACLE_FEED_URL'),
    'supplier_name' => env('PINNACLE_SUPPLIER_NAME', 'Pinnacle'),
    // Re-use a downloaded feed younger than this (minutes); at most one download per run.
    'feed_max_age_minutes' => (int) env('PINNACLE_FEED_MAX_AGE', 60),
    'http_timeout' => (int) env('PINNACLE_HTTP_TIMEOUT', 180),
    // Safety stops
    'min_feed_items' => (int) env('PINNACLE_MIN_FEED_ITEMS', 1000),
    'max_feed_age_hours' => (int) env('PINNACLE_MAX_FEED_AGE_HOURS', 24),   // <LastUpdated> older than this -> abort
    'max_oos_fraction' => (float) env('PINNACLE_MAX_OOS_FRACTION', 0.30),   // > this share of listed SKUs flipping to OOS -> abort
    // Listed SKUs missing from the feed are marked out of stock (Esquire-style).
    'missing_oos' => (bool) env('PINNACLE_MISSING_OOS', true),
    // OOS guard: never flip a product to out of stock while another supplier's CACHED feed on this server
    // (downloaded by syntech:sync / esquire:sync) shows stock for the same part number or EAN. Local files only.
    'oos_guard_feeds' => [
        ['name' => 'Syntech', 'path' => storage_path('app/syntech/feed.xml'), 'item' => 'product', 'code' => 'sku', 'ean' => 'ean', 'qty' => ['cptstock', 'jhbstock', 'dbnstock']],
        ['name' => 'Esquire', 'path' => storage_path('app/esquire/feed.xml'), 'item' => 'Product', 'code' => 'ProductCode', 'ean' => null, 'qty' => ['AvailableQty']],
    ],
    'oos_guard_max_age_hours' => (int) env('PINNACLE_OOS_GUARD_MAX_AGE_HOURS', 24),
    // Optional review-only CSV of cost vs current price (never changes prices).
    'price_report' => (bool) env('PINNACLE_PRICE_REPORT', true),
    'chunk_size' => (int) env('PINNACLE_CHUNK', 100),
    'chunk_pause_ms' => (int) env('PINNACLE_CHUNK_PAUSE_MS', 500),
    // One line per product: OUR_SKU [TAB PINNACLE_STOCKCODE]. Lines starting with # are ignored.
    'sku_file' => storage_path('app/pinnacle/skus.txt'),
    'storage_dir' => storage_path('app/pinnacle'),
    'log_file' => storage_path('logs/pinnacle_sync.log'),
];
