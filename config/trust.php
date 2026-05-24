<?php

return [
    'badges' => [
        ['label' => 'Genuine Products', 'icon' => 'shield'],
        ['label' => 'VAT Invoices', 'icon' => 'receipt'],
        ['label' => 'Warranty Support', 'icon' => 'warranty'],
        ['label' => 'Nationwide Delivery', 'icon' => 'truck'],
        ['label' => 'Secure Checkout', 'icon' => 'lock'],
        ['label' => 'B2B Quotes', 'icon' => 'quote'],
    ],

    'testimonials' => [
        [
            'name' => 'Thabo M.',
            'company' => 'IT Manager, Johannesburg',
            'quote' => 'Urban Focus consistently delivers genuine networking gear with fast turnaround. Their B2B quote process is straightforward and professional.',
            'rating' => 5,
        ],
        [
            'name' => 'Sarah K.',
            'company' => 'Procurement Lead, Cape Town',
            'quote' => 'We use Urban Focus for bulk laptop orders and software licensing. VAT invoices and delivery tracking make finance happy every time.',
            'rating' => 5,
        ],
        [
            'name' => 'David N.',
            'company' => 'Systems Integrator, Durban',
            'quote' => 'Reliable stock on Ubiquiti and Dahua products. The team knows their stuff and helps us win installer projects across KZN.',
            'rating' => 5,
        ],
    ],

    'google_reviews' => [
        'enabled' => env('GOOGLE_REVIEWS_ENABLED', false),
        'place_id' => env('GOOGLE_PLACE_ID', ''),
        'rating' => env('GOOGLE_RATING', '4.8'),
        'count' => env('GOOGLE_REVIEW_COUNT', '50'),
        'url' => env('GOOGLE_REVIEWS_URL', 'https://g.page/r/urbanfocus/review'),
    ],
];
