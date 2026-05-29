<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;

class PaystackService
{
    /**
     * Initialise a Paystack transaction and return the decoded API response.
     * The customer should then be redirected to data.authorization_url.
     */
    public function initializeTransaction(Order $order, string $reference): array
    {
        $response = Http::withToken((string) config('paystack.secret_key'))
            ->acceptJson()
            ->post($this->endpoint('/transaction/initialize'), [
                'email' => $order->customer_email,
                'amount' => $this->toSubunit($order->total),
                'currency' => config('paystack.currency', 'ZAR'),
                'reference' => $reference,
                'callback_url' => route('checkout.paystack.callback'),
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'custom_fields' => [
                        [
                            'display_name' => 'Order Number',
                            'variable_name' => 'order_number',
                            'value' => $order->order_number,
                        ],
                    ],
                ],
            ]);

        return $response->json() ?? [];
    }

    /**
     * Verify a transaction with Paystack by its reference.
     */
    public function verifyTransaction(string $reference): array
    {
        $response = Http::withToken((string) config('paystack.secret_key'))
            ->acceptJson()
            ->get($this->endpoint('/transaction/verify/'.rawurlencode($reference)));

        return $response->json() ?? [];
    }

    /**
     * Verify that a webhook payload was signed by Paystack.
     */
    public function isValidWebhookSignature(string $payload, ?string $signature): bool
    {
        if (empty($signature)) {
            return false;
        }

        $computed = hash_hmac('sha512', $payload, (string) config('paystack.secret_key'));

        return hash_equals($computed, $signature);
    }

    /**
     * Convert a rand amount to the Paystack subunit (cents).
     */
    public function toSubunit(float $amount): int
    {
        return (int) round($amount * 100);
    }

    protected function endpoint(string $path): string
    {
        return rtrim((string) config('paystack.base_url'), '/').$path;
    }
}
