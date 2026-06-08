<?php

return [
    /*
    |--------------------------------------------------------------------------
    | RSS feeds (free — no API key required)
    |--------------------------------------------------------------------------
    | South African IT / tech news sources. Articles import as drafts for review.
    */
    'rss_feeds' => [
        [
            'name' => 'MyBroadband',
            'url' => 'https://mybroadband.co.za/news/feed',
        ],
        [
            'name' => 'ITWeb',
            'url' => 'https://www.itweb.co.za/rss/articles',
        ],
        [
            'name' => 'TechCentral',
            'url' => 'https://techcentral.co.za/feed/',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | NewsAPI.org (optional — https://newsapi.org/register)
    |--------------------------------------------------------------------------
    | Free tier: 100 requests/day. Good for global tech headlines.
    | Set NEWSAPI_ENABLED=true and NEWSAPI_KEY in .env
    */
    'newsapi' => [
        'enabled' => env('NEWSAPI_ENABLED', false),
        'key' => env('NEWSAPI_KEY'),
        'query' => env('NEWSAPI_QUERY', 'technology OR cybersecurity OR networking OR "artificial intelligence"'),
        'language' => 'en',
        'page_size' => 5,
    ],

    'max_per_feed' => (int) env('NEWS_MAX_PER_FEED', 3),

    // Imported news is published immediately by default. Set
    // NEWS_PUBLISH_AS_DRAFT=true in .env to import as drafts for manual review.
    'publish_as_draft' => env('NEWS_PUBLISH_AS_DRAFT', false),
];
