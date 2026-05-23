<?php

namespace App\Services;

use App\Models\Order;

class PayFastService
{
    public function buildPaymentData(Order $order): array
    {
        $data = [
            'merchant_id' => config('payfast.merchant_id'),
            'merchant_key' => config('payfast.merchant_key'),
            'return_url' => config('payfast.return_url'),
            'cancel_url' => config('payfast.cancel_url'),
            'notify_url' => config('payfast.notify_url'),
            'name_first' => $order->billing_first_name,
            'name_last' => $order->billing_last_name,
            'email_address' => $order->customer_email,
            'cell_number' => $order->customer_phone,
            'm_payment_id' => $order->order_number,
            'amount' => number_format($order->total, 2, '.', ''),
            'item_name' => 'Urban Focus Order '.$order->order_number,
            'item_description' => 'IT products from Urban Focus',
        ];

        $data['signature'] = $this->generateSignature($data);

        return $data;
    }

    public function generateSignature(array $data, ?string $passphrase = null): string
    {
        unset($data['signature']);

        $passphrase = $passphrase ?? config('payfast.passphrase');
        $pfOutput = '';

        foreach ($data as $key => $val) {
            if ($val !== '') {
                $pfOutput .= $key.'='.urlencode(trim($val)).'&';
            }
        }

        $pfOutput = rtrim($pfOutput, '&');

        if ($passphrase) {
            $pfOutput .= '&passphrase='.urlencode(trim($passphrase));
        }

        return md5($pfOutput);
    }

    public function validateNotification(array $data): bool
    {
        if (! isset($data['signature'])) {
            return false;
        }

        $signature = $data['signature'];
        unset($data['signature']);

        return $signature === $this->generateSignature($data);
    }

    public function verifyWithPayFast(array $data): bool
    {
        $validateUrl = config('payfast.validate_url');

        $postData = http_build_query($data);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
            ],
        ]);

        $response = @file_get_contents($validateUrl, false, $context);

        return trim($response) === 'VALID';
    }
}
