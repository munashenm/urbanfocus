<?php

return [
    'country' => env('GOOGLE_MERCHANT_COUNTRY', 'ZA'),
    'currency' => env('GOOGLE_MERCHANT_CURRENCY', 'ZAR'),
    'condition' => 'new',

    'shipping' => [
        'country' => env('GOOGLE_MERCHANT_COUNTRY', 'ZA'),
        'service' => 'Standard Courier',
        // Paid courier fee incl. VAT for items at or under free_threshold.
        // Items strictly above the threshold are R0 (R201 is already built into
        // those selling prices). The old blanket GOOGLE_MERCHANT_SHIPPING_PRICE
        // env (R199 on every item) is no longer read, so a leftover 199 in .env
        // cannot flatten the feed again.
        'paid_price' => env('GOOGLE_MERCHANT_SHIPPING_PAID_PRICE', 150.00),
        'free_threshold' => env('GOOGLE_MERCHANT_SHIPPING_FREE_THRESHOLD', null), // null = config('shipping.free_threshold') (R1,500)
    ],

    'return_policy_url' => env('GOOGLE_MERCHANT_RETURN_URL', null), // defaults to returns route

    'return_policy_label' => env('GOOGLE_MERCHANT_RETURN_LABEL', '14 Day Returns'),

    // 'clean' = Brand + Name + SKU without repeats (brand/SKU dropped when the name already has them,
    // _BIN_ SKUs never shown). 'legacy' = old "Brand SKU Name" format (rollback switch).
    'title_format' => env('GOOGLE_MERCHANT_TITLE_FORMAT', 'clean'),

    // availability_date for backorder/preorder items = today + lead days.
    // null = use the product's own lead time (availability key days / delivery_days).
    'backorder_lead_days' => env('GOOGLE_MERCHANT_BACKORDER_DAYS', null),
    'backorder_default_days' => 21,

    // SKUs left out of the Google Merchant feed only (still sold on the website and still in other feeds).
    // UF-FP-GEN6: Fairphone Gen 6 hidden from Google (Munashe, 14 Nov 2026).
    'exclude_skus' => ['UF-FP-GEN6'],

    // Optional extra exclude list, one SKU per line, file in storage/app/ (not present = nothing extra excluded).
    'exclude_file' => 'google_feed_exclude.txt',

    'title_max_length' => 150,
    'description_max_length' => 5000,
];
