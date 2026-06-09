<?php

namespace App\Services;

use App\Models\Order;

class InvoiceService
{
    public function data(Order $order): array
    {
        $order->loadMissing('items');

        $vatRate = (float) config('app.vat_rate', 15);
        $isPaid = $order->payment_status === 'paid';

        return [
            'order' => $order,
            'title' => $isPaid ? 'Tax Invoice' : 'Proforma Invoice',
            'vatRate' => $vatRate,
            'seller' => [
                'name' => config('app.name', 'Urban Focus'),
                'email' => config('business.email'),
                'phone' => config('business.phone'),
                'vat_number' => $isPaid ? config('business.vat_number') : '',
                'company_reg' => $isPaid ? config('business.company_reg') : '',
                'address' => config('business.address'),
                'website' => config('business.website'),
            ],
        ];
    }
}
