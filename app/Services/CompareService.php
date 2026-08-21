<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class CompareService
{
    protected string $sessionKey = 'compare';

    public const MAX_ITEMS = 4;

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

        return $this->add($productId) && $this->has($productId);
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

    public function remaining(): int
    {
        return max(0, self::MAX_ITEMS - $this->count());
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

        $products = Product::with(['images', 'category'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn (int $id) => $products->get($id))
            ->filter()
            ->values();
    }

    /**
     * @return array<string, list<string>>
     */
    public function specRows(): array
    {
        $products = $this->products();
        $keys = [];

        foreach ($products as $product) {
            foreach ($product->specificationsList() as $key => $value) {
                $keys[(string) $key] = true;
            }
        }

        $rows = [];
        foreach (array_keys($keys) as $key) {
            $rows[$key] = $products->map(function (Product $product) use ($key) {
                $specs = $product->specificationsList();
                $value = $specs[$key] ?? null;

                if ($value === null || $value === '') {
                    return '—';
                }

                return is_scalar($value) ? (string) $value : json_encode($value);
            })->all();
        }

        return $rows;
    }
}
