<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BlackFridaySpecialService
{
    /**
     * Apply the configured specials onto products that already exist.
     * Does not create products and does not raise a regular price.
     *
     * @return array{
     *     applied: list<string>,
     *     created: list<string>,
     *     introduced: list<string>,
     *     unchanged: list<string>,
     *     pending: list<string>,
     *     above_current_price: list<array{sku: string, price: string, sale_price: string}>,
     *     not_found: list<string>
     * }
     */
    public function apply(): array
    {
        $result = [
            'applied' => [],
            'created' => [],
            'introduced' => [],
            'unchanged' => [],
            'pending' => [],
            'above_current_price' => [],
            'not_found' => [],
        ];

        foreach ($this->offers() as $offer) {
            if (! $this->priced($offer)) {
                $result['pending'][] = $this->label($offer);

                continue;
            }

            $products = $this->matchingProducts($offer);
            if ($products->isEmpty() && isset($offer['listing'])) {
                $created = $this->createListing($offer);
                if ($created) {
                    $products = collect([$created]);
                    $result['created'][] = (string) $created->sku;
                }
            }

            if ($products->isEmpty()) {
                $result['not_found'][] = $this->label($offer);

                continue;
            }

            $sale = round((float) $offer['sale_price'], 2);
            foreach ($products as $product) {
                $price = round((float) $product->price, 2);
                if (! empty($offer['introductory']) && abs($price - $sale) < 0.009 && $product->sale_price === null) {
                    $result['introduced'][] = (string) $product->sku;

                    continue;
                }

                if ($sale <= 0 || $price <= $sale) {
                    $result['above_current_price'][] = [
                        'sku' => (string) $product->sku,
                        'price' => number_format($price, 2, '.', ''),
                        'sale_price' => number_format($sale, 2, '.', ''),
                    ];

                    continue;
                }

                if ($product->sale_price !== null) {
                    $result['unchanged'][] = (string) $product->sku;

                    continue;
                }

                $product->update([
                    'sale_price' => $sale,
                    'sale_starts_at' => null,
                    'sale_ends_at' => $this->endsAt(),
                    'promo_label' => (string) config('black_friday.label', 'Black Friday'),
                ]);
                $result['applied'][] = (string) $product->sku;
            }
        }

        return $result;
    }

    /**
     * Products on the specials page: a lower sale price, or a new listing that
     * has no earlier selling price to show as "was".
     */
    public function constrainToSpecials(Builder $query): void
    {
        $skus = $this->introductorySkus();
        $query->where(function (Builder $specials) use ($skus) {
            $specials->where(function (Builder $onSale) {
                $onSale->onSale();
            });
            if ($skus === []) {
                return;
            }
            $now = now();
            $specials->orWhere(function (Builder $introduced) use ($skus, $now) {
                $introduced->whereIn('sku', $skus)
                    ->where(function (Builder $window) use ($now) {
                        $window->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>=', $now);
                    });
            });
        });
    }

    public function constrainHomepage(Builder $query): void
    {
        $skus = $this->introductorySkus();
        $query->where(function (Builder $specials) use ($skus) {
            $specials->where(function (Builder $onSale) {
                $onSale->onSale()->availableInStock();
            });
            if ($skus === []) {
                return;
            }
            $now = now();
            $specials->orWhere(function (Builder $introduced) use ($skus, $now) {
                $introduced->whereIn('sku', $skus)
                    ->where(function (Builder $window) use ($now) {
                        $window->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>=', $now);
                    })
                    ->availableOrListableOos();
            });
        });
    }

    /**
     * A live special whose sale price is the configured one. Supplier syncs may
     * still update stock and cost, but must not replace this selling price.
     */
    public function preservesSellingPrice(Product $product): bool
    {
        if (now()->gt($this->endsAt())) {
            return false;
        }

        $offer = $this->offerFor($product);
        if ($offer === null || ! $this->priced($offer)) {
            return false;
        }

        $target = (float) $offer['sale_price'];
        if ($product->sale_price !== null && abs((float) $product->sale_price - $target) < 0.009) {
            return true;
        }

        return ! empty($offer['introductory'])
            && $product->sale_price === null
            && abs((float) $product->price - $target) < 0.009;
    }

    /**
     * @return list<string>
     */
    public function introductorySkus(): array
    {
        $skus = [];
        foreach ($this->offers() as $offer) {
            if (empty($offer['introductory']) || ! $this->priced($offer)) {
                continue;
            }
            foreach ($offer['skus'] ?? [] as $sku) {
                $sku = strtoupper(trim((string) $sku));
                if ($sku !== '') {
                    $skus[] = $sku;
                }
            }
        }

        return array_values(array_unique($skus));
    }

    public function orderPinnedFirst(Builder $query): void
    {
        $skus = $this->pinnedSkus();
        if ($skus === []) {
            return;
        }

        $marks = implode(',', array_fill(0, count($skus), '?'));
        $query->orderByRaw("CASE WHEN sku IN ($marks) THEN 0 ELSE 1 END", $skus);
    }

    public function endsAt(): Carbon
    {
        return Carbon::parse(
            (string) config('black_friday.ends_at'),
            (string) config('black_friday.timezone', 'Africa/Johannesburg')
        );
    }

    /**
     * @return list<array{name: string, sale_price: float|int, skus: list<string>, esquire_ids: list<string>}>
     */
    public function offers(): array
    {
        return array_values(config('black_friday.offers', []));
    }

    /**
     * @return list<string>
     */
    public function pinnedSkus(): array
    {
        $skus = [];
        foreach ($this->offers() as $offer) {
            foreach ($offer['skus'] ?? [] as $sku) {
                $sku = strtoupper(trim((string) $sku));
                if ($sku !== '') {
                    $skus[] = $sku;
                }
            }
        }

        return array_values(array_unique($skus));
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function priced(array $offer): bool
    {
        return isset($offer['sale_price']) && $offer['sale_price'] !== '' && $offer['sale_price'] !== null
            && (float) $offer['sale_price'] > 0;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function createListing(array $offer): ?Product
    {
        $listing = $offer['listing'] ?? null;
        $sku = strtoupper(trim((string) ($offer['skus'][0] ?? '')));
        if (! is_array($listing) || $sku === '') {
            return null;
        }

        $name = trim((string) ($offer['name'] ?? $sku));
        $price = round((float) $offer['sale_price'], 2);
        $category = app(CategoryMapperService::class)->resolveCategoryForFilter((string) ($listing['category_path'] ?? ''));
        $slug = Str::slug($name);
        if ($slug === '' || Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = Str::slug($name.' '.$sku);
        }

        $product = Product::create([
            'sku' => $sku,
            'model_number' => $sku,
            'name' => $name,
            'slug' => $slug,
            'brand' => (string) ($listing['brand'] ?? ''),
            'category_id' => $category?->id,
            'supplier_name' => 'Esquire',
            'supplier_sku' => $sku,
            'supplier_product_url' => (string) ($listing['supplier_product_url'] ?? ''),
            'source_external_id' => (string) ($offer['esquire_ids'][0] ?? ''),
            'short_description' => (string) ($listing['short_description'] ?? ''),
            'description' => (string) ($listing['description'] ?? ''),
            'specifications' => $listing['specifications'] ?? [],
            'price' => $price,
            'sale_price' => null,
            'sale_starts_at' => null,
            'sale_ends_at' => $this->endsAt(),
            'promo_label' => (string) config('black_friday.label', 'Black Friday'),
            'warranty_months' => $listing['warranty_months'] ?? null,
            'stock_quantity' => 0,
            'manage_stock' => true,
            'in_stock' => false,
            'is_oos_listable' => true,
            'is_active' => true,
        ]);

        $this->attachListingImage($product, (string) ($listing['image'] ?? ''));

        return $product;
    }

    protected function attachListingImage(Product $product, string $relativePath): void
    {
        if ($relativePath === '' || $product->images()->exists()) {
            return;
        }

        $source = base_path($relativePath);
        if (! is_file($source)) {
            return;
        }

        $contents = file_get_contents($source);
        if ($contents === false) {
            return;
        }

        $extension = pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg';
        $path = app(ImageService::class)->storeProductImageFromBinary($contents, (int) $product->id, $extension);
        if ($path === null) {
            return;
        }

        ProductImage::create([
            'product_id' => $product->id,
            'path' => $path,
            'alt_text' => $product->imageAlt(),
            'sort_order' => 0,
            'is_primary' => true,
        ]);
    }

    /**
     * @param  array{name?: string, sale_price?: float|int|null, skus?: list<string>, esquire_ids?: list<string>}  $offer
     */
    protected function matchingProducts(array $offer): Collection
    {
        $codes = $this->codes($offer['skus'] ?? []);
        $ids = $this->codes($offer['esquire_ids'] ?? []);

        $products = Product::query()
            ->where(function (Builder $query) use ($codes, $ids) {
                $started = false;
                foreach (['sku', 'supplier_sku', 'model_number', 'source_external_id'] as $column) {
                    if ($codes === []) {
                        break;
                    }
                    $started = true;
                    $query->orWhereIn($column, $codes);
                }
                foreach ($ids as $id) {
                    $started = true;
                    $query->orWhere('supplier_sku', $id)
                        ->orWhere('source_external_id', $id)
                        ->orWhere('supplier_product_url', 'like', '%/'.$id)
                        ->orWhere('supplier_product_url', 'like', '%/'.$id.'/%');
                }
                if (! $started) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->get();

        if ($products->isNotEmpty()) {
            return $products;
        }

        $name = trim((string) ($offer['name'] ?? ''));
        if ($name === '') {
            return collect();
        }

        $byName = Product::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->get();

        return $byName->count() === 1 ? $byName : collect();
    }

    protected function offerFor(Product $product): ?array
    {
        $haystack = array_filter([
            strtoupper(trim((string) $product->sku)),
            strtoupper(trim((string) $product->supplier_sku)),
            strtoupper(trim((string) $product->model_number)),
            strtoupper(trim((string) $product->source_external_id)),
        ]);

        foreach ($this->offers() as $offer) {
            $codes = $this->codes(array_merge($offer['skus'] ?? [], $offer['esquire_ids'] ?? []));
            if ($haystack !== [] && array_intersect($haystack, $codes) !== []) {
                return $offer;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    protected function codes(array $values): array
    {
        $codes = [];
        foreach ($values as $value) {
            $value = strtoupper(trim((string) $value));
            if ($value !== '') {
                $codes[] = $value;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param  array{name?: string, skus?: list<string>}  $offer
     */
    protected function label(array $offer): string
    {
        $sku = $offer['skus'][0] ?? '';

        return trim((string) ($offer['name'] ?? '')).($sku !== '' ? ' ('.$sku.')' : '');
    }
}
