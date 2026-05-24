<?php

return [
    'flat_rate' => (float) env('SHIPPING_FLAT_RATE', 99.00),
    'free_threshold' => (float) env('SHIPPING_FREE_THRESHOLD', 1500.00),
    'manual_quote_enabled' => (bool) env('SHIPPING_MANUAL_QUOTE_ENABLED', true),
    'default_delivery_days' => (int) env('SHIPPING_DEFAULT_DELIVERY_DAYS', 3),
    'default_warranty_months' => (int) env('SHIPPING_DEFAULT_WARRANTY_MONTHS', 12),
    'methods' => [
        'courier' => 'Courier Delivery',
        'free' => 'Free Shipping',
        'manual_quote' => 'Manual Courier Quote',
        'collection' => 'Collect from Warehouse',
    ],
];
