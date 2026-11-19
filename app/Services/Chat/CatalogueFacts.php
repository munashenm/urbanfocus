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
        $intent = $this->interpret($group);

        if ($intent['brand'] !== null || $intent['categories'] !== []) {
            return $this->searchByIntent($intent, $limit);
        }

        return $this->searchByTerms($group, $limit);
    }

    /**
     * Brand and category are required together when the question states both.
     * A spec such as 16GB RAM ranks and filters inside that set. It never
     * matches a USB stick or a backpack on the number alone.
     *
     * @param  array{brand: ?string, categories: list<string>, ram_gb: ?int}  $intent
     * @return Collection<int, Product>
     */
    protected function searchByIntent(array $intent, int $limit): Collection
    {
        $query = Product::query()->where('is_active', true)->withoutDuplicateListings()->with('category');

        if ($intent['brand'] !== null) {
            $aliases = $this->brandAliases()[$intent['brand']];
            $query->where(function ($builder) use ($aliases) {
                foreach ($aliases as $alias) {
                    $builder->orWhereRaw('LOWER(COALESCE(brand, \'\')) = ?', [$alias])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%'.$alias.'%']);
                }
            });
        }

        $categoryWords = $this->categoryWords($intent['categories']);
        if ($categoryWords !== []) {
            $query->where(function ($builder) use ($categoryWords) {
                foreach ($categoryWords as $word) {
                    if (mb_strlen($word) >= 4) {
                        $like = '%'.$word.'%';
                        $builder->orWhereRaw('LOWER(name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(COALESCE(short_description, \'\')) LIKE ?', [$like])
                            ->orWhereHas('category', fn ($category) => $category->whereRaw('LOWER(name) LIKE ?', [$like]));

                        continue;
                    }

                    foreach (['% '.$word.' %', $word.' %', '% '.$word] as $like) {
                        $builder->orWhereRaw('LOWER(name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(COALESCE(short_description, \'\')) LIKE ?', [$like]);
                    }
                }
            });
        }

        $products = $query->orderByDesc('in_stock')->orderBy('name')->limit(150)->get();

        $matched = $products->filter(function (Product $product) use ($intent, $categoryWords) {
            if ($intent['brand'] !== null && ! $this->productMatchesBrand($product, $intent['brand'])) {
                return false;
            }

            if ($categoryWords !== [] && ! $this->textMatchesWords($this->haystack($product), $categoryWords)) {
                return false;
            }

            if ($this->isAccessoryInsteadOfDevice($product, $intent['categories'])) {
                return false;
            }

            return true;
        })->values();

        if ($intent['ram_gb'] !== null) {
            $withRam = $matched->filter(fn (Product $product) => $this->productHasRam($product, $intent['ram_gb']))->values();
            if ($withRam->isNotEmpty()) {
                $matched = $withRam;
            }
        }

        return $matched
            ->sortByDesc(fn (Product $product) => ($product->isAvailable() ? 2 : 0) + ($this->productHasRam($product, (int) ($intent['ram_gb'] ?? 0)) ? 1 : 0))
            ->take($limit)
            ->values();
    }

    /**
     * @return array{brand: ?string, categories: list<string>, ram_gb: ?int}
     */
    protected function interpret(string $text): array
    {
        $text = mb_strtolower($this->withoutPrivateTokens($text));
        $spaced = str_replace(['-', '–', '—', '/'], ' ', $text);

        $brand = null;
        $brandAt = null;
        foreach ($this->brandAliases() as $key => $aliases) {
            foreach ($aliases as $alias) {
                $pattern = '/\b'.preg_quote($alias, '/').'\b/u';
                if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE) || preg_match($pattern, $spaced, $match, PREG_OFFSET_CAPTURE)) {
                    $at = $match[0][1];
                    if ($brandAt === null || $at < $brandAt || ($at === $brandAt && mb_strlen($alias) > mb_strlen($brand ?? ''))) {
                        $brand = $key;
                        $brandAt = $at;
                    }
                }
            }
        }

        $categories = [];
        foreach ($this->categorySynonyms() as $key => $words) {
            foreach ($words as $word) {
                $haystack = str_contains($word, ' ') || str_contains($word, '-') ? $text.' '.$spaced : $text;
                $pattern = str_contains($word, ' ')
                    ? '/'.preg_quote($word, '/').'/u'
                    : '/\b'.preg_quote($word, '/').'\b/u';
                if (preg_match($pattern, $haystack)) {
                    $categories[] = $key;
                    break;
                }
            }
        }

        $ram = null;
        if (preg_match('/\b(\d+)\s*gb\s*ram\b/u', $spaced, $match)) {
            $ram = (int) $match[1];
        } elseif (in_array('laptop', $categories, true) && preg_match('/\b(\d+)\s*gb\b/u', $spaced, $match) && ! preg_match('/\b(ssd|hdd|nvme|storage)\b/u', $spaced)) {
            $ram = (int) $match[1];
        }

        return [
            'brand' => $brand,
            'categories' => array_values(array_unique($categories)),
            'ram_gb' => $ram,
        ];
    }

    protected function searchByTerms(string $group, int $limit): Collection
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
            fn (string $term) => ! preg_match('/^\d+gb$/', $term)
                && $term !== 'ram'
                && (strlen($term) >= 4 || in_array($term, ['hp', 'lg'], true))
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
     * @param  list<string>  $categories
     * @return list<string>
     */
    protected function categoryWords(array $categories): array
    {
        $words = [];
        $groups = $this->categorySynonyms();
        foreach ($categories as $category) {
            foreach ($groups[$category] ?? [] as $word) {
                $words[] = $word;
            }
        }

        return array_values(array_unique($words));
    }

    protected function productMatchesBrand(Product $product, string $brand): bool
    {
        $haystack = mb_strtolower($product->brand.' '.$product->name);

        foreach ($this->brandAliases()[$brand] ?? [] as $alias) {
            if (preg_match('/\b'.preg_quote($alias, '/').'\b/u', $haystack)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $words
     */
    protected function textMatchesWords(string $haystack, array $words): bool
    {
        foreach ($words as $word) {
            $pattern = str_contains($word, ' ')
                ? '/'.preg_quote($word, '/').'/u'
                : '/\b'.preg_quote($word, '/').'\b/u';
            if (preg_match($pattern, $haystack)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A backpack or sleeve whose description mentions a laptop is not a laptop.
     *
     * @param  list<string>  $categories
     */
    protected function isAccessoryInsteadOfDevice(Product $product, array $categories): bool
    {
        if (! array_intersect($categories, ['laptop', 'desktop', 'tablet'])) {
            return false;
        }

        $name = mb_strtolower((string) $product->name);
        $device = '/\b(laptop|laptops|notebook|notebooks|desktop|desktops|tablet|tablets|pc|pcs)\b/u';
        $accessory = '/\b(backpack|rucksack|bag|sleeve|carry case|cover)\b/u';

        return preg_match($accessory, $name) === 1 && preg_match($device, $name) !== 1;
    }

    protected function productHasRam(Product $product, int $gb): bool
    {
        if ($gb < 1) {
            return false;
        }

        $haystack = $this->haystack($product);
        $size = (string) $gb;

        if (preg_match('/\b'.$size.'\s*gb\s*ram\b/u', $haystack)) {
            return true;
        }

        if (preg_match('/\bram\b[^.\n]{0,24}\b'.$size.'\s*gb\b/u', $haystack)) {
            return true;
        }

        if (preg_match('/\b(memory|ddr[345]?)\b[^.\n]{0,30}\b'.$size.'\s*gb\b/u', $haystack)) {
            return true;
        }

        return (bool) (preg_match('/\b'.$size.'\s*gb\b(?!\s*(ssd|nvme|hdd|usb|flash|microsd|sdxc|sdhc|sd\b))/u', $haystack)
            && preg_match('/\b'.$size.'\s*gb\b.{0,30}\b(\d+)\s*(gb|tb)\b/u', $haystack, $next)
            && ($next[2] === 'tb' || (int) $next[1] > $gb));
    }

    protected function haystack(Product $product): string
    {
        $parts = [
            $product->name,
            $product->brand,
            $product->short_description,
            $product->model_number,
            $product->category?->name,
        ];

        $specs = $product->specifications ?? [];
        if (is_array($specs)) {
            foreach ($specs as $key => $value) {
                if (is_scalar($value)) {
                    $parts[] = $key.' '.$value;
                }
            }
        }

        return mb_strtolower(implode(' ', array_filter($parts, fn ($part) => is_string($part) && $part !== '')));
    }

    /**
     * @return array<string, list<string>>
     */
    protected function brandAliases(): array
    {
        return [
            'hp' => ['hp', 'hewlett'],
            'dell' => ['dell'],
            'lenovo' => ['lenovo'],
            'unifi' => ['unifi', 'ubiquiti'],
            'mikrotik' => ['mikrotik', 'microtik'],
            'hikvision' => ['hikvision'],
            'dahua' => ['dahua'],
            'yealink' => ['yealink'],
            'sophos' => ['sophos'],
            'samsung' => ['samsung'],
            'huawei' => ['huawei'],
            'logitech' => ['logitech'],
            'microsoft' => ['microsoft'],
            'lg' => ['lg'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    protected function categorySynonyms(): array
    {
        return [
            'laptop' => ['laptop', 'laptops', 'notebook', 'notebooks'],
            'desktop' => ['desktop', 'desktops', 'pc', 'pcs'],
            'all-in-one' => ['all-in-one', 'all in one', 'aio'],
            'switch' => ['switch', 'switches'],
            'router' => ['router', 'routers'],
            'server' => ['server', 'servers'],
            'monitor' => ['monitor', 'monitors'],
            'printer' => ['printer', 'printers'],
            'camera' => ['camera', 'cameras', 'cctv'],
            'tablet' => ['tablet', 'tablets'],
        ];
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
