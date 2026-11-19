<?php

return [
    /*
    | The API key, base URL and model are the existing OpenAI settings shared
    | with blog automation (OPENAI_API_KEY, OPENAI_BASE_URL, OPENAI_MODEL).
    | There is no second key. Chat does not follow OPENAI_ENABLED, so blog
    | captions can stay on while the product assistant stays off.
    | Set CHAT_OPENAI_ENABLED=true only after the server key is confirmed.
    */
    'enabled' => env('CHAT_ENABLED', true),

    'openai' => [
        'enabled' => env('CHAT_OPENAI_ENABLED', false),
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

    /*
    | Optional SMS when a visitor asks for a person. Disabled unless
    | CHAT_SMS_ENABLED=true and the chosen provider's credentials are set.
    | There is no WhatsApp channel.
    */
    'sms' => [
        'enabled' => env('CHAT_SMS_ENABLED', false),
        'driver' => env('CHAT_SMS_DRIVER', 'bulksms'),
        'to' => env('CHAT_SMS_TO'),
        'bulksms' => [
            'token_id' => env('BULKSMS_TOKEN_ID'),
            'token_secret' => env('BULKSMS_TOKEN_SECRET'),
            'url' => env('BULKSMS_URL', 'https://api.bulksms.com/v1/messages'),
        ],
        'clickatell' => [
            'api_key' => env('CLICKATELL_API_KEY'),
            'url' => env('CLICKATELL_URL', 'https://platform.clickatell.com/v1/message'),
        ],
    ],
];
