<?php

return [
    'markup_percent' => (float) env('PRICE_MARKUP_PERCENT', 40),
    'round_to' => (int) env('PRICE_ROUND_TO', 100),
    // "up" = ceil to next R100 (never below marked-up price). "nearest" = standard round (can reduce).
    'round_mode' => env('PRICE_ROUND_MODE', 'up'),
];
