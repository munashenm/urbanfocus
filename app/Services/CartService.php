<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class CartService
{
    protected string $sessionKey = 'cart';

    public function items(): Collection
    {
        $cart = collect(session($this->sessionKey, []));

        if ($cart->isEmpty()) {
            return collect();
        }

        $products = Product::with('images')
            ->whereIn('id', $cart->keys())
            ->get()
            ->keyBy('id');

        return $cart->map(function ($quantity, $productId) use ($products) {
            $product = $products->get($productId);
            if (! $product) {
                return null;
            }

            return [
                'product' => $product,
                'quantity' => (int) $quantity,
                'line_total' => $product->effective_price * (int) $quantity,
            ];
        })->filter();
    }

    public function add(int $productId, int $quantity = 1): void
    {
        $cart = session($this->sessionKey, []);
        $cart[$productId] = ($cart[$productId] ?? 0) + $quantity;
        session([$this->sessionKey => $cart]);
    }

    public function update(int $productId, int $quantity): void
    {
        $cart = session($this->sessionKey, []);

        if ($quantity <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = $quantity;
        }

        session([$this->sessionKey => $cart]);
    }

    public function remove(int $productId): void
    {
        $cart = session($this->sessionKey, []);
        unset($cart[$productId]);
        session([$this->sessionKey => $cart]);
    }

    public function clear(): void
    {
        session()->forget($this->sessionKey);
    }

    public function count(): int
    {
        return (int) collect(session($this->sessionKey, []))->sum();
    }

    public function subtotal(): float
    {
        return (float) $this->items()->sum('line_total');
    }

    public function isEmpty(): bool
    {
        return $this->items()->isEmpty();
    }
}
