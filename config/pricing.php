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

    /*
    | Astrum import retail source (no extra markup/rounding):
    | - price: use the CSV "price" column as the storefront price (your pre-calculated file)
    | - srp:   use the "srp_price" column as storefront price
    | - markup: treat "price" as dealer cost and apply markup_percent + round_to above
    */
    'astrum_retail_from' => env('ASTRUM_RETAIL_FROM', 'price'),
];
