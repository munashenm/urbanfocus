<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Make.com (Integromat) Marketing Automation
    |--------------------------------------------------------------------------
    |
    | Urban Focus pushes product and blog events to Make.com webhooks. Make.com
    | scenarios then fan the payload out to Facebook, LinkedIn and X using the
    | AI-generated captions included in each payload. The app records every
    | dispatch (success and failure) so it can be reviewed in the admin
    | dashboard under Admin → Social Media.
    |
    */

    'enabled' => env('MAKE_ENABLED', false),

    'webhooks' => [
        // Fired when a product is published / activated.
        'product' => env('MAKE_PRODUCT_WEBHOOK_URL'),
        // Fired when a blog post is published.
        'blog' => env('MAKE_BLOG_WEBHOOK_URL'),
    ],

    // Optional shared secret sent as the X-Make-Secret header so the Make.com
    // scenario can verify the request really came from Urban Focus.
    'secret' => env('MAKE_WEBHOOK_SECRET'),

    'timeout' => (int) env('MAKE_WEBHOOK_TIMEOUT', 15),

    'retries' => (int) env('MAKE_WEBHOOK_RETRIES', 2),

    /*
    | Webhooks are dispatched via a queued job so the HTTP call never blocks an
    | admin save. Leave these null to use the app's default queue connection
    | (QUEUE_CONNECTION) and its default queue, so a plain `php artisan
    | queue:work` will process them. Set MAKE_QUEUE to isolate them on a
    | dedicated queue (then run `queue:work --queue=<name>`). Set
    | QUEUE_CONNECTION=sync to send inline during local testing.
    */
    'queue_connection' => env('MAKE_QUEUE_CONNECTION'),

    'queue' => env('MAKE_QUEUE'),

    /*
    | Social platforms Make.com should publish to. Each enabled platform gets a
    | dedicated AI caption in the webhook payload and a logged publishing
    | attempt in the admin dashboard.
    */
    'platforms' => array_values(array_filter([
        env('MAKE_PLATFORM_FACEBOOK', true) ? 'facebook' : null,
        env('MAKE_PLATFORM_LINKEDIN', true) ? 'linkedin' : null,
        env('MAKE_PLATFORM_X', true) ? 'x' : null,
    ])),
];
