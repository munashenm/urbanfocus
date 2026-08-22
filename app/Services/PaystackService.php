<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    /**
     * Initialise a Paystack transaction and return the decoded API response.
     * The customer should then be redirected to data.authorization_url.
     *
     * @return array{status?: bool, message?: string, data?: array<string, mixed>}
     */
    public function initializeTransaction(Order $order, string $reference): array
    {
        if (! $this->hasSecretKey()) {
            Log::error('Paystack initialize skipped: secret key is not configured', [
                'order' => $order->order_number,
            ]);

            return [
                'status' => false,
                'message' => 'Card payments are temporarily unavailable. Please use Manual EFT or contact us.',
            ];
        }

        try {
            $response = Http::withToken((string) config('paystack.secret_key'))
                ->acceptJson()
                ->timeout(12)
                ->retry(1, 250)
                ->post($this->endpoint('/transaction/initialize'), [
                    'email' => $order->customer_email,
                    'amount' => $this->toSubunit((float) $order->total),
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
        } catch (ConnectionException $e) {
            Log::error('Paystack initialize connection failed', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => false,
                'message' => 'We could not reach Paystack. Please try again or use Manual EFT.',
            ];
        } catch (\Throwable $e) {
            Log::error('Paystack initialize exception', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => false,
                'message' => 'We could not start the payment. Please try again or use Manual EFT.',
            ];
        }

        $payload = $response->json() ?? [];

        if (! $response->successful() || empty($payload['data']['authorization_url'])) {
            Log::warning('Paystack initialize rejected', [
                'order' => $order->order_number,
                'http_status' => $response->status(),
                'message' => $payload['message'] ?? null,
            ]);

            $payload['status'] = false;
            $payload['message'] ??= 'Paystack could not start this payment. Please try again or use Manual EFT.';
        }

        return $payload;
    }

    /**
     * Verify a transaction with Paystack by its reference.
     *
     * @return array{status?: bool, message?: string, data?: array<string, mixed>}
     */
    public function verifyTransaction(string $reference): array
    {
        if (! $this->hasSecretKey()) {
            return ['status' => false, 'message' => 'Paystack is not configured.'];
        }

        try {
            $response = Http::withToken((string) config('paystack.secret_key'))
                ->acceptJson()
                ->timeout(12)
                ->retry(1, 250)
                ->get($this->endpoint('/transaction/verify/'.rawurlencode($reference)));
        } catch (\Throwable $e) {
            Log::error('Paystack verify exception', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return ['status' => false, 'message' => $e->getMessage()];
        }

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

    public function amountsMatch(int $paidCents, float $orderTotal): bool
    {
        return abs($paidCents - $this->toSubunit($orderTotal)) <= 100;
    }

    public function hasSecretKey(): bool
    {
        $key = trim((string) config('paystack.secret_key'));

        return $key !== '' && ! str_contains($key, 'CHANGE-ME');
    }

    protected function endpoint(string $path): string
    {
        return rtrim((string) config('paystack.base_url'), '/').$path;
    }
}
