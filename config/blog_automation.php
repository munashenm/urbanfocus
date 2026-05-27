<?php

return [
    'auto_seo' => env('BLOG_AUTO_SEO', true),
    'auto_internal_links' => env('BLOG_AUTO_INTERNAL_LINKS', true),
    'auto_social_snippets' => env('BLOG_AUTO_SOCIAL_SNIPPETS', true),

    'openai' => [
        'enabled' => env('OPENAI_ENABLED', false),
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'reddit' => [
        'enabled' => env('REDDIT_ENABLED', true),
        'user_agent' => env('REDDIT_USER_AGENT', 'UrbanFocusBlogBot/1.0 (+https://www.urbanfocus.co.za)'),
        'subreddits' => [
            'HomeNetworking', 'Ubiquiti', 'Starlink', 'sysadmin', 'networking', 'homelab',
        ],
        'limit_per_sub' => 5,
    ],

    'youtube' => [
        'enabled' => env('YOUTUBE_ENABLED', false),
        'api_key' => env('YOUTUBE_API_KEY'),
        'region' => env('YOUTUBE_REGION', 'ZA'),
        'queries' => [
            'ubiquiti unifi review',
            'mikrotik router setup',
            'business laptop review',
            'cctv camera installation',
            'load shedding ups south africa',
        ],
        'max_results' => 5,
    ],

    'serpapi' => [
        'enabled' => env('SERPAPI_ENABLED', false),
        'api_key' => env('SERPAPI_KEY'),
        'geo' => env('SERPAPI_GEO', 'ZA'),
        'trending_queries' => [
            'ubiquiti south africa',
            'mikrotik south africa',
            'business laptops south africa',
            'cctv cameras south africa',
            'load shedding ups',
            'starlink south africa',
        ],
    ],

    'google_search_console' => [
        'enabled' => env('GSC_ENABLED', false),
        'site_url' => env('GSC_SITE_URL', env('APP_URL')),
        'credentials_path' => env('GSC_CREDENTIALS_PATH', storage_path('app/google-search-console.json')),
        'days' => (int) env('GSC_LOOKBACK_DAYS', 28),
    ],

    'article_types' => [
        'buying_guide' => 'Buying guide',
        'comparison' => 'Product comparison',
        'tutorial' => 'Technical tutorial',
        'news_summary' => 'Industry news summary',
    ],

    /*
    | Keyword → internal link targets (first match wins per keyword in content).
    */
    'internal_link_map' => [
        'ubiquiti' => ['type' => 'brand', 'slug' => 'ubiquiti'],
        'unifi' => ['type' => 'brand', 'slug' => 'ubiquiti'],
        'mikrotik' => ['type' => 'brand', 'slug' => 'mikrotik'],
        'routerboard' => ['type' => 'brand', 'slug' => 'mikrotik'],
        'tp-link' => ['type' => 'brand', 'slug' => 'tp-link'],
        'hikvision' => ['type' => 'brand', 'slug' => 'hikvision'],
        'dahua' => ['type' => 'brand', 'slug' => 'dahua'],
        'dell latitude' => ['type' => 'brand', 'slug' => 'dell'],
        'thinkpad' => ['type' => 'brand', 'slug' => 'lenovo'],
        'ups' => ['type' => 'category', 'slug' => 'ups-systems'],
        'load shedding' => ['type' => 'solution', 'slug' => 'bulk-it-procurement'],
        'network switch' => ['type' => 'category', 'slug' => 'network-switches'],
        'access point' => ['type' => 'category', 'slug' => 'access-points'],
        'ip camera' => ['type' => 'category', 'slug' => 'ip-cameras'],
        'nvr' => ['type' => 'category', 'slug' => 'nvr-dvr'],
        'business laptop' => ['type' => 'solution', 'slug' => 'business-laptops-south-africa'],
        'fibre' => ['type' => 'solution', 'slug' => 'fibre-networking-solutions'],
        'cctv' => ['type' => 'solution', 'slug' => 'cctv-equipment-supplier'],
    ],

    'product_link_keywords' => [
        'ups' => ['ups', 'backup power', 'inverter'],
        'switch' => ['network switch', 'poe switch', 'managed switch'],
        'access point' => ['access point', 'wifi ap', 'wireless ap'],
        'laptop' => ['business laptop', 'notebook', 'latitude', 'thinkpad', 'elitebook'],
        'camera' => ['ip camera', 'bullet camera', 'dome camera', 'cctv camera'],
    ],
];
