<?php

return [
    'eft' => [
        'bank_name' => env('EFT_BANK_NAME', ''),
        'account_name' => env('EFT_ACCOUNT_NAME', 'Urban Focus'),
        'account_number' => env('EFT_ACCOUNT_NUMBER', ''),
        'branch_code' => env('EFT_BRANCH_CODE', ''),
        'account_type' => env('EFT_ACCOUNT_TYPE', 'Current'),
    ],
];
