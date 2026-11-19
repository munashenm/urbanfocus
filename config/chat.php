<?php

return [
    /*
    | Customer chat uses the same OpenAI environment variables as blog
    | automation (OPENAI_ENABLED, OPENAI_API_KEY, OPENAI_BASE_URL, OPENAI_MODEL).
    | There is no second key. Leave OPENAI_ENABLED false and the product
    | assistant answers from the catalogue without calling the API.
    */
    'enabled' => env('CHAT_ENABLED', true),

    'openai' => [
        'enabled' => env('OPENAI_ENABLED', false),
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 12),
    ],

    /*
    | Structured hours for the availability flag. The words shown to
    | customers always come from config('business.hours'), which is the
    | published line (Mon–Fri 8:00–17:00). Keep these two in step.
    */
    'timezone' => 'Africa/Johannesburg',
    'open_days' => [1, 2, 3, 4, 5],
    'opens_at' => '08:00',
    'closes_at' => '17:00',
];
