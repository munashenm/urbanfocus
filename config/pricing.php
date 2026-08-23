<?php

return [
    /*
    | Fallback markup when no category, name or brand rule matches.
    | Compared lines (laptops, UniFi, CCTV) use the rules below instead.
    */
    'markup_percent' => (float) env('PRICE_MARKUP_PERCENT', 15),
    'round_to' => (int) env('PRICE_ROUND_TO', 50),
    // "up" = ceil to next step (never below marked-up price). "nearest" = standard round (can reduce).
    'round_mode' => env('PRICE_ROUND_MODE', 'up'),
    // Cost below this threshold: markup only, no round-to-R50 (keeps cheap accessories affordable).
    'low_cost_threshold' => (float) env('PRICE_LOW_COST_THRESHOLD', 20),
    // Scoop dealer prices are ex-VAT; add VAT before markup + rounding.
    'scoop_prices_ex_vat' => (bool) env('SCOOP_PRICES_EX_VAT', true),

    /*
    | Used only when cost_price is missing or equal to the current retail price
    | (legacy apply-markup copied sell price into cost). Reverse this, then
    | apply the competitive rule so we do not mark up an already-marked-up price.
    */
    'legacy_markup_percent' => (float) env('PRICE_LEGACY_MARKUP_PERCENT', 40),

    /*
    | Paystack card/EFT (~2.9% + R1) plus bank receiving charges.
    | Applied after product markup so street prices stay viable.
    */
    'payment_fee_percent' => (float) env('PRICE_PAYMENT_FEE_PERCENT', 3.9),

    /*
    | Extra top-up on curated target-range (new) products, after street price.
    | Rounded up to round_to. Existing catalogue imports are not affected.
    */
    'target_range_topup_percent' => (float) env('PRICE_TARGET_RANGE_TOPUP_PERCENT', 15),
    'specialist_topup_percent' => (float) env('PRICE_SPECIALIST_TOPUP_PERCENT', 15),

    /*
    | Longest category path wins. Values are markup percent on VAT-inclusive cost.
    */
    'category_markups' => [
        'computing-office/laptops' => 8,
        'computing-office/desktops' => 10,
        'computing-office/tablets' => 8,
        'computing-office/software' => 10,
        'computing-office/storage-devices' => 12,
        'computing-office/monitors' => 12,
        'computing-office/computer-accessories' => 28,
        'networking-connectivity' => 12,
        'security-surveillance' => 12,
        'solar-power' => 15,
    ],

    /*
    | First matching name term wins. Put accessories before "laptop" so
    | "Dell laptop charger" stays an accessory.
    */
    'name_term_markups' => [
        'charger' => 28,
        'trunking' => 28,
        'patch cord' => 28,
        'patch panel' => 28,
        'cable tie' => 28,
        'pro 15 essential' => 8,
        'pro 14 essential' => 8,
        'pro 16 essential' => 8,
        'laptop' => 8,
        'notebook' => 8,
        'latitude' => 8,
        'thinkpad' => 8,
        'thinkbook' => 8,
        'elitebook' => 8,
        'probook' => 8,
        'macbook' => 8,
        'workstation' => 10,
        'unifi' => 12,
        'uisp' => 12,
        'access point' => 12,
        'hikvision' => 12,
        'dahua' => 12,
    ],

    /*
    | High-ticket OEM brands that buyers compare on Dell.com / FirstShop.
    | Only applied when resolved cost is at or above competitive_brand_min_cost
    | so a Dell mouse does not get laptop margins.
    */
    'competitive_brand_min_cost' => 4000,
    'competitive_brand_markups' => [
        'dell' => 8,
        'hp' => 8,
        'hewlett-packard' => 8,
        'lenovo' => 8,
        'apple' => 8,
        'microsoft' => 8,
        'asus' => 10,
        'acer' => 10,
    ],
];
