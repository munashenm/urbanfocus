<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class WishlistService
{
    protected string $sessionKey = 'wishlist';

    public const MAX_ITEMS = 50;

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_unique(array_map('intval', session($this->sessionKey, []))));
    }

    public function add(int $productId): bool
    {
        $ids = $this->ids();

        if (in_array($productId, $ids, true)) {
            return true;
        }

        if (count($ids) >= self::MAX_ITEMS) {
            return false;
        }

        $ids[] = $productId;
        session([$this->sessionKey => $ids]);

        return true;
    }

    public function remove(int $productId): void
    {
        session([$this->sessionKey => array_values(array_filter(
            $this->ids(),
            fn (int $id) => $id !== $productId
        ))]);
    }

    public function toggle(int $productId): bool
    {
        if ($this->has($productId)) {
            $this->remove($productId);

            return false;
        }

        $this->add($productId);

        return $this->has($productId);
    }

    public function has(int $productId): bool
    {
        return in_array($productId, $this->ids(), true);
    }

    public function clear(): void
    {
        session()->forget($this->sessionKey);
    }

    public function count(): int
    {
        return count($this->ids());
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function products(): Collection
    {
        $ids = $this->ids();

        if ($ids === []) {
            return collect();
        }

        $products = Product::with('images')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn (int $id) => $products->get($id))
            ->filter()
            ->values();
    }
}
