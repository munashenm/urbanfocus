<?php

// Esquire stock & price sync. The feed URL lives ONLY in .env (ESQUIRE_FEED_URL).
return [
    'feed_url' => env('ESQUIRE_FEED_URL') ?: (is_file(storage_path('app/esquire/feed_url.txt')) ? trim(file_get_contents(storage_path('app/esquire/feed_url.txt'))) : null),
    'supplier_name' => env('ESQUIRE_SUPPLIER_NAME', 'Esquire'),
    // What <Price> in the feed means. Prices are only changed when this is 'cost_excl'.
    //   stock_only  = update stock only (DEFAULT until the Price field is confirmed)
    //   cost_excl   = feed Price is our dealer cost excl VAT -> v3 price rule
    'price_basis' => env('ESQUIRE_PRICE_BASIS', 'stock_only'),
    'vat' => 0.15,
    'fee_percent' => 0.039,   // Paystack 2.9% + bank 1%
    'fee_fixed' => 1.0,
    'min_profit' => 0.25,
    'free_shipping_threshold' => 1500,
    'shipping_excl' => 200,
    'feed_max_age_minutes' => (int) env('ESQUIRE_FEED_MAX_AGE', 60),
    'http_timeout' => (int) env('ESQUIRE_HTTP_TIMEOUT', 180),
    'min_feed_items' => (int) env('ESQUIRE_MIN_FEED_ITEMS', 1000),
    'chunk_size' => 100,
    'chunk_pause_ms' => 500,
    'max_price_change' => (float) env('ESQUIRE_MAX_PRICE_CHANGE', 0.40),
    // Safety: if the median new/old price ratio is outside this band the feed price basis is
    // probably wrong (e.g. retail, not cost) -> no prices are changed in that run.
    'sanity_ratio_min' => 0.75,
    'sanity_ratio_max' => 1.35,
    // Feed lists only in-stock items, so "missing from feed" usually means out of stock.
    'missing_oos' => (bool) env('ESQUIRE_MISSING_OOS', true),
    'sku_file' => storage_path('app/esquire/skus.txt'),
    'storage_dir' => storage_path('app/esquire'),
];
