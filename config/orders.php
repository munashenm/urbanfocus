<?php

return [
    'statuses' => [
        'pending_payment' => 'Pending payment',
        'paid' => 'Paid',
        'processing' => 'Processing',
        'awaiting_supplier' => 'Awaiting supplier',
        'ready_for_dispatch' => 'Ready for dispatch',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
        // Legacy statuses kept for existing records
        'pending' => 'Pending',
        'completed' => 'Completed',
    ],

    'payment_statuses' => [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
    ],
];
