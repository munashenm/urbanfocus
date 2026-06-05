<?php

$defaultTermsAndConditions = <<<'TEXT'
TERMS & CONDITIONS — All sales are final and subject to our Terms of Sale and Warranty Policy (www.urbanfocus.co.za).
• Purchaser is responsible for confirming product specifications, suitability and intended use.
• Returns accepted only per our warranty policy—not for unsuitability, change of mind, or damage after delivery.
• 1-year warranty on all products unless otherwise stated.
• Confirm delivery lead times with your sales contact; some products are imports.
• Website prices may differ from this quotation.
• Valid until the date shown above; stock and pricing confirmed on order. E&OE.
TEXT;

return [
    'number_prefix' => env('QUOTATION_NUMBER_PREFIX', 'UF-Q'),
    'default_validity_days' => (int) env('QUOTATION_VALIDITY_DAYS', 14),
    'default_terms' => env(
        'QUOTATION_DEFAULT_TERMS',
        'Payment: Use your quotation number as reference and notify us once paid.'
    ),
    'terms_and_conditions' => env('QUOTATION_TERMS_AND_CONDITIONS')
        ? str_replace('\\n', "\n", (string) env('QUOTATION_TERMS_AND_CONDITIONS'))
        : $defaultTermsAndConditions,
];
