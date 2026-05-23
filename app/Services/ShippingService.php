<?php

namespace App\Services;

class ShippingService
{
    public function calculate(float $subtotal, string $method = 'courier'): array
    {
        $flatRate = (float) config('shipping.flat_rate');
        $freeThreshold = (float) config('shipping.free_threshold');

        if ($method === 'manual_quote') {
            return [
                'method' => 'manual_quote',
                'label' => config('shipping.methods.manual_quote'),
                'cost' => 0,
                'requires_quote' => true,
            ];
        }

        if ($method === 'collection') {
            return [
                'method' => 'collection',
                'label' => config('shipping.methods.collection'),
                'cost' => 0,
                'requires_quote' => false,
            ];
        }

        if ($subtotal >= $freeThreshold) {
            return [
                'method' => 'free',
                'label' => config('shipping.methods.free'),
                'cost' => 0,
                'requires_quote' => false,
            ];
        }

        return [
            'method' => 'courier',
            'label' => config('shipping.methods.courier'),
            'cost' => $flatRate,
            'requires_quote' => false,
        ];
    }

    public function availableMethods(float $subtotal): array
    {
        $methods = [
            $this->calculate($subtotal, 'courier'),
            $this->calculate($subtotal, 'collection'),
        ];

        if (config('shipping.manual_quote_enabled')) {
            $methods[] = $this->calculate($subtotal, 'manual_quote');
        }

        return $methods;
    }
}
