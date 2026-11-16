<?php

/**
 * Optional fragment: copy the 'astrum' key below into the array returned by
 * config/services.php. Do not overwrite that file. config/astrum.php already
 * loads the same values, so this is only needed if you want them under
 * config('services.astrum').
 */
return [
    'astrum' => [
        'key' => env('ASTRUM_API_KEY'),
        'base_url' => env('ASTRUM_BASE_URL', 'https://astrum.africa/v1'),
    ],
];
