<?php

return [
    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 5),
    'alert_email' => env('LOW_STOCK_ALERT_EMAIL', env('BUSINESS_EMAIL', 'sales@urbanfocus.co.za')),
];
