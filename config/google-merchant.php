<?php

return [
    'country' => env('GOOGLE_MERCHANT_COUNTRY', 'ZA'),
    'currency' => env('GOOGLE_MERCHANT_CURRENCY', 'ZAR'),
    'condition' => 'new',

    'shipping' => [
        'country' => env('GOOGLE_MERCHANT_COUNTRY', 'ZA'),
        'service' => 'Standard Courier',
        'price' => env('GOOGLE_MERCHANT_SHIPPING_PRICE', null), // null = use config('shipping.flat_rate')
    ],

    'return_policy_url' => env('GOOGLE_MERCHANT_RETURN_URL', null), // defaults to returns route

    'return_policy_label' => env('GOOGLE_MERCHANT_RETURN_LABEL', '30 Day Returns'),

    'title_max_length' => 150,
    'description_max_length' => 5000,
];
