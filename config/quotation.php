<?php

$defaultTermsAndConditions = <<<'TEXT'
TERMS AND CONDITIONS OF SALE & WARRANTY

Please note that all sales are final and subject to the Terms and Conditions of sale and Warranty Policy.

It is the responsibility of the Purchaser to understand the specifications, limitations, applicability and suitable usage of Products.

Product returns will only be accepted under the terms of Warranty specified and not in the event where the Products are deemed to be unsuitable by the Purchaser or damaged.

Urban Focus offers a 1 year warranty on all products.

Please confirm delivery with your sales person as some of our products are imports.

Prices on the website may vary.

Full terms and conditions are available on our website: www.urbanfocus.co.za

Quotation validity
This quotation is valid until the "Valid until" date shown above. Prices and stock availability are subject to confirmation at the time of order. Errors and omissions excepted (E&OE).
TEXT;

return [
    'number_prefix' => env('QUOTATION_NUMBER_PREFIX', 'UF-Q'),
    'default_validity_days' => (int) env('QUOTATION_VALIDITY_DAYS', 14),
    'default_terms' => env(
        'QUOTATION_DEFAULT_TERMS',
        'Payment: Please use your quotation number as the payment reference. Notify us once payment has been made so we can confirm and process your order.'
    ),
    'terms_and_conditions' => env('QUOTATION_TERMS_AND_CONDITIONS')
        ? str_replace('\\n', "\n", (string) env('QUOTATION_TERMS_AND_CONDITIONS'))
        : $defaultTermsAndConditions,
];
