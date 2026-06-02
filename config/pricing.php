<?php

return [
    'markup_percent' => (float) env('PRICE_MARKUP_PERCENT', 40),
    'round_to' => (int) env('PRICE_ROUND_TO', 50),
    // "up" = ceil to next step (never below marked-up price). "nearest" = standard round (can reduce).
    'round_mode' => env('PRICE_ROUND_MODE', 'up'),
    // Cost below this threshold: markup only, no round-to-R50 (keeps cheap accessories affordable).
    'low_cost_threshold' => (float) env('PRICE_LOW_COST_THRESHOLD', 20),
    // Scoop dealer prices are ex-VAT; add VAT before markup + rounding.
    'scoop_prices_ex_vat' => (bool) env('SCOOP_PRICES_EX_VAT', true),
];
