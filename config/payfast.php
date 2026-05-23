<?php

return [
    'merchant_id' => env('PAYFAST_MERCHANT_ID'),
    'merchant_key' => env('PAYFAST_MERCHANT_KEY'),
    'passphrase' => env('PAYFAST_PASSPHRASE'),
    'sandbox' => (bool) env('PAYFAST_SANDBOX', true),
    'return_url' => env('PAYFAST_RETURN_URL'),
    'cancel_url' => env('PAYFAST_CANCEL_URL'),
    'notify_url' => env('PAYFAST_NOTIFY_URL'),
    'process_url' => env('PAYFAST_SANDBOX', true)
        ? 'https://sandbox.payfast.co.za/eng/process'
        : 'https://www.payfast.co.za/eng/process',
    'validate_url' => env('PAYFAST_SANDBOX', true)
        ? 'https://sandbox.payfast.co.za/eng/query/validate'
        : 'https://www.payfast.co.za/eng/query/validate',
];
