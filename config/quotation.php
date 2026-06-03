<?php

return [
    'number_prefix' => env('QUOTATION_NUMBER_PREFIX', 'UF-Q'),
    'default_validity_days' => (int) env('QUOTATION_VALIDITY_DAYS', 14),
    'default_terms' => env('QUOTATION_DEFAULT_TERMS', 'Prices are valid for the period stated above. Stock availability is subject to confirmation at time of order. E&OE.'),
];
