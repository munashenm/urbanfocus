<?php

return [
    'phone' => env('BUSINESS_PHONE', '087 550 1813'),
    'phone_tel' => env('BUSINESS_PHONE_TEL', '0875501813'),
    'whatsapp' => env('BUSINESS_WHATSAPP', env('BUSINESS_PHONE_TEL', '0875501813')),
    'email' => env('BUSINESS_EMAIL', 'sales@urbanfocus.co.za'),
    'website' => env('APP_URL', 'https://www.urbanfocus.co.za'),
    'hours' => env('BUSINESS_HOURS', 'Mon–Fri 8:00–17:00'),
    'address' => [
        'line1' => env('BUSINESS_ADDRESS_LINE1', '17 Waterloo RD'),
        'line2' => env('BUSINESS_ADDRESS_LINE2', 'Samrand Business Park'),
        'city' => env('BUSINESS_CITY', 'Centurion'),
        'province' => env('BUSINESS_PROVINCE', 'Gauteng'),
        'postal_code' => env('BUSINESS_POSTAL_CODE', ''),
        'country' => 'South Africa',
    ],
    'careers_email' => env('CAREERS_EMAIL', 'sales@urbanfocus.co.za'),
    'vat_number' => env('BUSINESS_VAT_NUMBER', ''),
    'company_reg' => env('BUSINESS_COMPANY_REG', ''),

    'banking' => [
        'bank_name' => env('BUSINESS_BANK_NAME', ''),
        'branch_code' => env('BUSINESS_BANK_BRANCH_CODE', ''),
        'account_number' => env('BUSINESS_BANK_ACCOUNT_NUMBER', ''),
        'swift_code' => env('BUSINESS_BANK_SWIFT', ''),
    ],
];
