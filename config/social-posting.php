<?php

return [
    'enabled' => env('SOCIAL_POSTING_ENABLED', false),

    'max_per_run' => (int) env('SOCIAL_POST_MAX_PER_RUN', 5),

    // Platforms for the direct-API posting path (Meta/X/TikTok drivers).
    // LinkedIn is handled via the Make.com workflow (see config/make.php).
    'platforms' => [
        'facebook' => env('SOCIAL_POST_FACEBOOK', true),
        'instagram' => env('SOCIAL_POST_INSTAGRAM', true),
        'x' => env('SOCIAL_POST_X', true),
        'tiktok' => env('SOCIAL_POST_TIKTOK', false),
    ],

    /*
    | Meta (Facebook Page + Instagram Business)
    | https://developers.facebook.com/apps — needs pages_manage_posts, instagram_content_publish
    */
    'meta' => [
        'page_id' => env('META_PAGE_ID'),
        'page_access_token' => env('META_PAGE_ACCESS_TOKEN'),
        'instagram_account_id' => env('META_INSTAGRAM_ACCOUNT_ID'),
        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
    ],

    /*
    | X (Twitter) API v2
    | https://developer.x.com — OAuth 2.0 user access token with tweet.write
    */
    'x' => [
        'api_key' => env('X_API_KEY'),
        'api_secret' => env('X_API_SECRET'),
        'access_token' => env('X_ACCESS_TOKEN'),
        'access_token_secret' => env('X_ACCESS_TOKEN_SECRET'),
        'bearer_token' => env('X_BEARER_TOKEN'),
    ],

    /*
    | TikTok Content Posting API (photo/video)
    | https://developers.tiktok.com — app review required
    */
    'tiktok' => [
        'client_key' => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'access_token' => env('TIKTOK_ACCESS_TOKEN'),
    ],

    'hashtags' => env('SOCIAL_HASHTAGS', '#UrbanFocus #ITSouthAfrica #Tech'),
];
