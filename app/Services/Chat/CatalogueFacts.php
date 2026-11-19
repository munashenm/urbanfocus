<?php

namespace App\Services\Chat;

use App\Models\Product;
use Illuminate\Support\Collection;

class CatalogueFacts
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forQuestion(string $question, int $limit = 4): array
    {
        return $this->search($question, $limit)
            ->map(fn (Product $product) => $this->describe($product))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Product>
     */
    public function search(string $question, int $limit = 4): Collection
    {
        $groups = $this->groups($question);
        $found = collect();

        foreach ($groups as $group) {
            $found = $found->merge($this->searchGroup($group, $limit));
        }

        return $found->unique('id')->take($limit)->values();
    }

    /**
     * Public catalogue fields only. Cost, supplier and other internal fields are omitted.
     *
     * @return array<string, mixed>
     */
    public function describe(Product $product): array
    {
        $onSale = $product->isOnSale();
        $specs = [];

        foreach ($product->specificationsList() as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $label = trim((string) $key);
            if ($label === '' || preg_match('/internal|cost|supplier|margin|price/i', $label)) {
                continue;
            }

            $specs[$label] = trim((string) $value);
            if (count($specs) >= 8) {
                break;
            }
        }

        $effective = (float) $product->effective_price;

        return [
            'name' => $product->name,
            'sku' => $product->sku,
            'brand' => $product->brand,
            'price' => number_format($effective, 2, '.', ''),
            'price_label' => 'R '.number_format($effective, 2),
            'list_price' => $onSale ? number_format((float) $product->price, 2, '.', '') : null,
            'list_price_label' => $onSale ? 'R '.number_format((float) $product->price, 2) : null,
            'discount_percent' => $onSale ? $product->discountPercent() : null,
            'availability' => $product->availabilityLabel(),
            'available' => $product->isAvailable(),
            'warranty' => $product->warrantyLabel(),
            'delivery_guidance' => $product->deliveryEstimate(),
            'specifications' => $specs,
            'url' => route('products.show', $product),
        ];
    }

    /**
     * @return list<string>
     */
    protected function groups(string $question): array
    {
        $question = $this->withoutPrivateTokens($question);

        if (preg_match('/\b(compare|versus|vs\.?)\b/i', $question)) {
            $parts = preg_split('/\b(?:compare|versus|vs\.?|and)\b/i', $question) ?: [];
            $groups = array_values(array_filter(array_map('trim', $parts)));

            if (count($groups) >= 2) {
                return $groups;
            }
        }

        return [$question];
    }

    /**
     * @return Collection<int, Product>
     */
    protected function searchGroup(string $group, int $limit): Collection
    {
        $terms = $this->terms($group);
        if ($terms === []) {
            return collect();
        }

        $base = Product::query()->where('is_active', true)->withoutDuplicateListings();

        $strict = (clone $base);
        foreach ($terms as $term) {
            $strict->where(function ($query) use ($term) {
                $like = '%'.$term.'%';
                $query->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('model_number', 'like', $like)
                    ->orWhere('short_description', 'like', $like);
            });
        }

        $matches = $strict->limit($limit)->get();
        if ($matches->isNotEmpty()) {
            return $matches;
        }

        $distinctive = array_values(array_filter(
            $terms,
            fn (string $term) => strlen($term) >= 4 || in_array($term, ['hp', 'lg'], true)
        ));
        if ($distinctive === []) {
            return collect();
        }

        return (clone $base)
            ->where(function ($query) use ($distinctive) {
                foreach ($distinctive as $term) {
                    $like = '%'.$term.'%';
                    $query->orWhere('name', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('brand', 'like', $like)
                        ->orWhere('model_number', 'like', $like);
                }
            })
            ->limit($limit)
            ->get();
    }

    /**
     * @return list<string>
     */
    protected function terms(string $text): array
    {
        $text = $this->withoutPrivateTokens($text);
        $raw = preg_split('/[^\p{L}\p{N}\-]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = array_flip([
            'a', 'an', 'the', 'and', 'or', 'vs', 'versus', 'compare', 'comparison', 'with', 'for', 'from',
            'price', 'prices', 'stock', 'availability', 'available', 'buy', 'need', 'want', 'looking',
            'please', 'what', 'which', 'how', 'much', 'does', 'do', 'is', 'are', 'of', 'to', 'in', 'on',
            'our', 'your', 'me', 'my', 'you', 'urban', 'focus', 'product', 'products', 'between', 'than',
            'better', 'difference', 'cost', 'costs', 'have', 'has', 'about', 'tell', 'show', 'can', 'i',
            'we', 'it', 'this', 'that', 'current', 'warranty', 'offer', 'offers', 'offering', 'service',
            'services', 'information', 'info', 'provide', 'provides', 'provided', 'company', 'business',
            'help', 'please', 'assist', 'assistance', 'catalogue', 'catalog', 'sell', 'sells', 'selling',
        ]);

        $terms = [];
        foreach ($raw as $term) {
            if (strlen($term) < 2 || isset($stop[$term])) {
                continue;
            }
            $terms[] = $term;
        }

        return array_values(array_unique($terms));
    }

    protected function withoutPrivateTokens(string $text): string
    {
        $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', ' ', $text) ?? $text;
        $text = preg_replace('/\bUF-[A-Z0-9]+\b/i', ' ', $text) ?? $text;

        return $text;
    }
}
