<?php

$defaultTermsAndConditions = <<<'TEXT'
1. Quotation validity
This quotation is valid until the "Valid until" date shown above. Prices and stock are subject to confirmation at the time of order.

2. Pricing
All prices are quoted in South African Rand (ZAR). Prices include VAT at the prevailing rate unless stated otherwise.

3. Payment
Payment is due as per agreed terms once the quotation is accepted. Goods will only be dispatched after cleared funds are received unless a credit account has been approved in writing.

4. Delivery
Delivery timelines are estimates only and depend on stock availability and courier schedules. Risk passes to the buyer upon handover to the nominated carrier or collection.

5. Returns & warranty
Returns and warranty claims are handled in accordance with our standard store policy and manufacturer warranty terms. Software licences and special-order items may not be returnable.

6. General
Urban Focus reserves the right to amend or withdraw this quotation without notice prior to written acceptance. Errors and omissions excepted (E&OE).
TEXT;

return [
    'number_prefix' => env('QUOTATION_NUMBER_PREFIX', 'UF-Q'),
    'default_validity_days' => (int) env('QUOTATION_VALIDITY_DAYS', 14),
    'default_terms' => env(
        'QUOTATION_DEFAULT_TERMS',
        'Please use your quotation number as the payment reference. Notify us once payment has been made so we can confirm and process your order.'
    ),
    'terms_and_conditions' => env('QUOTATION_TERMS_AND_CONDITIONS')
        ? str_replace('\\n', "\n", (string) env('QUOTATION_TERMS_AND_CONDITIONS'))
        : $defaultTermsAndConditions,
];
