<?php

return [
    'defaults' => [
        'title_suffix' => '| Urban Focus',
        'description' => 'Urban Focus — South African IT distributor. Buy laptops, networking, CCTV, servers and software with nationwide delivery, VAT invoices and professional support.',
        'keywords' => 'IT supplier South Africa, networking equipment South Africa, buy laptops South Africa, Ubiquiti supplier, Hikvision supplier, business IT supplier',
        'locale' => 'en_ZA',
        'country' => 'ZA',
    ],

    'verification' => [
        'google' => env('GOOGLE_SITE_VERIFICATION'),
        'bing' => env('BING_SITE_VERIFICATION'),
    ],

    'analytics' => [
        'ga4_id' => env('GA4_MEASUREMENT_ID'),
        'google_ads_id' => env('GOOGLE_ADS_ID'),
        'meta_pixel_id' => env('META_PIXEL_ID'),
        'tiktok_pixel_id' => env('TIKTOK_PIXEL_ID'),
    ],

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', true),
        'number' => env('WHATSAPP_NUMBER', '27875501813'),
        'message' => env('WHATSAPP_MESSAGE', 'Hi Urban Focus, I need help with a product or quote.'),
    ],

    'cache' => [
        'sitemap_ttl' => (int) env('SEO_SITEMAP_CACHE_TTL', 3600),
        'feed_ttl' => (int) env('SEO_FEED_CACHE_TTL', 1800),
    ],

    'indexing' => [
        'indexnow_key' => env('INDEXNOW_KEY'),
        'ping_search_engines' => (bool) env('SEO_PING_SEARCH_ENGINES', false),
    ],

    'sa_keywords' => [
        'buy laptops South Africa',
        'networking equipment South Africa',
        'Ubiquiti supplier South Africa',
        'Hikvision supplier South Africa',
        'cheap IT equipment South Africa',
        'computer accessories South Africa',
        'gaming laptops South Africa',
        'business IT supplier',
    ],

    'sa_cities' => [
        'Johannesburg',
        'Cape Town',
        'Durban',
        'Pretoria',
        'Centurion',
        'Limpopo',
    ],

    'robots_disallow' => [
        '/admin',
        '/cart',
        '/checkout',
        '/account',
        '/login',
        '/register',
        '/storage/',
    ],

    'faq' => [
        [
            'question' => 'Does Urban Focus deliver nationwide in South Africa?',
            'answer' => 'Yes. Urban Focus delivers IT products across South Africa including Johannesburg, Cape Town, Durban, Pretoria and regional areas via trusted couriers.',
        ],
        [
            'question' => 'Do you supply VAT invoices?',
            'answer' => 'Yes. VAT invoices are supplied for business, corporate and government orders.',
        ],
        [
            'question' => 'Which payment methods do you accept?',
            'answer' => 'We accept PayFast secure checkout including card, Instant EFT, Apple Pay, Google Pay and EFT bank transfer.',
        ],
    ],
];
