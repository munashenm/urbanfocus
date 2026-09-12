<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CatalogIntegrityService
{
    public function __construct(
        protected CategoryMapperService $categories,
        protected CatalogDeduper $deduper,
        protected SeoService $seo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function audit(): array
    {
        $this->categories->ensureCanonicalTree();

        $products = Product::query()
            ->with(['category.parent', 'images'])
            ->orderBy('id')
            ->get();

        $active = $products->where('is_active', true)->values();

        $duplicateSkus = $this->duplicateSkus($active);
        $skuNameConflicts = $this->skuNameConflicts($active);
        $missingSkuRows = $active->filter(fn (Product $p) => trim((string) $p->sku) === '');
        $missingPriceRows = $active->filter(fn (Product $p) => (float) $p->effective_price <= 0);
        $missingImageRows = $active->filter(fn (Product $p) => blank($p->primary_image_url));
        $missingDescriptionRows = $active->filter(function (Product $p) {
            return trim(strip_tags((string) $p->short_description)) === ''
                && trim(strip_tags((string) $p->description)) === '';
        });
        $missingSkus = $this->sampleMissing($missingSkuRows, 'sku');
        $missingPrices = $this->sampleMissing($missingPriceRows, 'price');
        $missingImages = $this->sampleMissing($missingImageRows, 'image');
        $missingDescriptions = $this->sampleMissing($missingDescriptionRows, 'description');
        $malformedNames = $this->malformedNames($active);
        $wrongBrandSku = $this->wrongBrandSku($active);
        $taxonomyHigh = $this->taxonomyCandidates($active, highConfidence: true);
        $taxonomyReview = $this->taxonomyCandidates($active, highConfidence: false);
        $duplicateNames = $this->duplicateNames($active);
        $seoTitleCollisions = $this->seoTitleCollisions($active);
        $usw16p = $this->skuFocus($products, 'USW-16P');
        $hiddenDuplicates = count($this->deduper->idsToHide());

        return [
            'generated_at' => now()->toIso8601String(),
            'totals' => [
                'all' => $products->count(),
                'active' => $active->count(),
                'inactive' => $products->count() - $active->count(),
            ],
            'counts' => [
                'duplicate_sku_groups' => count($duplicateSkus),
                'sku_name_conflicts' => count($skuNameConflicts),
                'duplicate_name_groups' => count($duplicateNames),
                'seo_title_collisions' => count($seoTitleCollisions),
                'missing_skus' => $missingSkuRows->count(),
                'missing_prices' => $missingPriceRows->count(),
                'missing_images' => $missingImageRows->count(),
                'missing_descriptions' => $missingDescriptionRows->count(),
                'malformed_names' => count($malformedNames),
                'wrong_brand_sku' => count($wrongBrandSku),
                'taxonomy_high_confidence' => count($taxonomyHigh),
                'taxonomy_review' => count($taxonomyReview),
                'hidden_duplicates' => $hiddenDuplicates,
                'usw_16p_rows' => count($usw16p['rows']),
            ],
            'duplicate_skus' => $duplicateSkus,
            'sku_name_conflicts' => $skuNameConflicts,
            'duplicate_names' => $duplicateNames,
            'seo_title_collisions' => $seoTitleCollisions,
            'usw_16p' => $usw16p,
            'missing_skus' => $missingSkus,
            'missing_prices' => $missingPrices,
            'missing_images' => $missingImages,
            'missing_descriptions' => $missingDescriptions,
            'malformed_names' => $malformedNames,
            'wrong_brand_sku' => $wrongBrandSku,
            'taxonomy_high_confidence' => $taxonomyHigh,
            'taxonomy_review' => $taxonomyReview,
            'hidden_duplicates' => $hiddenDuplicates,
        ];
    }

    /**
     * Reassign only high-confidence category mistakes. Does not change SKUs or names.
     *
     * @return array{fixed: int, samples: list<string>}
     */
    public function applyHighConfidenceTaxonomyFixes(): array
    {
        $this->categories->ensureCanonicalTree();

        $fixed = 0;
        $samples = [];

        Product::query()
            ->with(['category.parent'])
            ->where('is_active', true)
            ->orderBy('id')
            ->lazyById(100)
            ->each(function (Product $product) use (&$fixed, &$samples) {
                $target = $this->highConfidenceTarget($product);
                if ($target === null || $target === (int) $product->category_id) {
                    return;
                }

                $from = $product->category?->fullPathLabel() ?? 'Uncategorised';
                $to = Category::find($target)?->fullPathLabel() ?? (string) $target;
                $product->update(['category_id' => $target]);
                $fixed++;
                if (count($samples) < 40) {
                    $samples[] = ($product->sku ?: '#'.$product->id).' '.$product->name.' · '.$from.' → '.$to;
                }
            });

        if ($fixed > 0) {
            $this->seo->clearCache();
        }

        return ['fixed' => $fixed, 'samples' => $samples];
    }

    public function highConfidenceTarget(Product $product): ?int
    {
        $path = $this->highConfidencePath($product);
        if ($path === null) {
            return null;
        }

        return $this->categories->resolveCategoryId($path);
    }

    public function highConfidencePath(Product $product): ?string
    {
        $name = trim($product->name.' '.$product->sku.' '.$product->model_number.' '.str_replace('-', ' ', (string) $product->slug));
        $current = $product->category?->urlPath() ?? '';
        $currentSlug = $product->category?->slug ?? '';

        $inLaptops = in_array($currentSlug, ['laptops', 'business-laptops', 'gaming-laptops', 'chromebooks'], true)
            || str_contains($current, 'laptops');

        $isLaptopAccessory = (bool) preg_match(
            '/\b(charger|power adapter|ac adapter|mains adapter|laptop psu|backpack|laptop bag|notebook bag|laptop sleeve|notebook sleeve|laptop case|notebook case|clamshell)\b/i',
            $name
        );

        if ($isLaptopAccessory && $inLaptops) {
            return 'computing-office/computer-accessories';
        }

        $isBoard = (bool) preg_match('/\b(interactive|smart board|smartboard|ifp\d|ifpd|interactive flat panel|meetingboard)\b/i', $name);
        $inMonitors = in_array($currentSlug, ['monitors', 'office-monitors', 'gaming-monitors', 'commercial-displays'], true)
            || str_contains($current, 'monitor');

        if ($isBoard && $inMonitors) {
            return 'digital-signage/interactive-displays';
        }

        return null;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    protected function duplicateSkus(Collection $products): array
    {
        return $products
            ->filter(fn (Product $p) => trim((string) $p->sku) !== '')
            ->groupBy(fn (Product $p) => mb_strtolower(trim((string) $p->sku)))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(function (Collection $group, string $sku) {
                return [
                    'sku' => $group->first()?->sku,
                    'count' => $group->count(),
                    'names' => $group->pluck('name')->unique()->values()->all(),
                    'ids' => $group->pluck('id')->all(),
                    'slugs' => $group->pluck('slug')->all(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->take(80)
            ->all();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    protected function duplicateNames(Collection $products): array
    {
        return $products
            ->groupBy(fn (Product $p) => mb_strtolower(trim($p->name)))
            ->filter(fn (Collection $group) => $group->count() > 1 && trim((string) $group->first()?->name) !== '')
            ->map(function (Collection $group) {
                return [
                    'name' => $group->first()?->name,
                    'count' => $group->count(),
                    'skus' => $group->pluck('sku')->unique()->values()->all(),
                    'ids' => $group->pluck('id')->all(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->take(40)
            ->all();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    protected function seoTitleCollisions(Collection $products): array
    {
        return $products
            ->groupBy(fn (Product $p) => mb_strtolower($p->seoTitle()))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(function (Collection $group) {
                return [
                    'title' => $group->first()?->seoTitle(),
                    'count' => $group->count(),
                    'skus' => $group->pluck('sku')->unique()->values()->all(),
                    'names' => $group->pluck('name')->unique()->values()->all(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->take(40)
            ->all();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    protected function skuNameConflicts(Collection $products): array
    {
        return collect($this->duplicateSkus($products))
            ->filter(fn (array $row) => count($row['names']) > 1)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<string, mixed>
     */
    protected function skuFocus(Collection $products, string $sku): array
    {
        $needle = mb_strtolower($sku);
        $matches = $products->filter(function (Product $p) use ($needle) {
            return mb_strtolower(trim((string) $p->sku)) === $needle
                || str_contains(mb_strtolower($p->name), $needle)
                || str_contains(mb_strtolower((string) $p->model_number), $needle)
                || str_contains(mb_strtolower((string) $p->slug), str_replace('-', '', $needle));
        })->values();

        return [
            'sku' => $sku,
            'rows' => $matches->map(fn (Product $p) => [
                'id' => $p->id,
                'active' => (bool) $p->is_active,
                'name' => $p->name,
                'sku' => $p->sku,
                'brand' => $p->brand,
                'slug' => $p->slug,
                'url' => $p->slug ? url('/product/'.$p->slug) : null,
                'category' => $p->category?->fullPathLabel(),
                'price' => $p->effective_price,
                'hidden_as_duplicate' => in_array($p->id, $this->deduper->idsToHide(), true),
            ])->all(),
        ];
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    protected function sampleMissing(Collection $products, string $field): array
    {
        return $products
            ->take(60)
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'field' => $field,
                'slug' => $p->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    protected function malformedNames(Collection $products): array
    {
        return $products
            ->filter(function (Product $p) {
                $name = trim($p->name);
                if ($name === '' || mb_strlen($name) < 8) {
                    return true;
                }
                if (preg_match('/[<>]|1Pieces|undefined|null|test product/i', $name)) {
                    return true;
                }
                if (preg_match('/^[A-Z0-9\s\-_/]{12,}$/', $name) && ! preg_match('/[a-z]/', $name) && mb_strlen($name) > 40) {
                    return true;
                }

                return false;
            })
            ->take(40)
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    protected function wrongBrandSku(Collection $products): array
    {
        $rows = [];
        foreach ($products as $product) {
            $sku = strtoupper(trim((string) $product->sku));
            $brand = trim((string) $product->brand);
            if ($sku === '') {
                continue;
            }

            $expected = null;
            if (preg_match('/^(USW|U6-|U7-|UCK|UDM|UAP|UVC|UC-)/', $sku)) {
                $expected = 'Ubiquiti';
            } elseif (preg_match('/^(RB|CRS|CCR|C52|L009)/', $sku)) {
                $expected = 'MikroTik';
            }

            if ($expected && $brand !== '' && stripos($brand, $expected) === false && stripos($product->name, $expected) === false) {
                $rows[] = [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'brand' => $brand,
                    'expected_brand' => $expected,
                ];
            }

            if (count($rows) >= 40) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    protected function taxonomyCandidates(Collection $products, bool $highConfidence): array
    {
        $rows = [];
        foreach ($products as $product) {
            $targetPath = $highConfidence
                ? $this->highConfidencePath($product)
                : $this->reviewPath($product);

            if ($targetPath === null) {
                continue;
            }

            $targetId = $this->categories->resolveCategoryId($targetPath);
            if ($targetId === null || $targetId === (int) $product->category_id) {
                continue;
            }

            $rows[] = [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'from' => $product->category?->fullPathLabel(),
                'to' => $targetPath,
                'confidence' => $highConfidence ? 'high' : 'review',
            ];

            if (! $highConfidence && count($rows) >= 80) {
                break;
            }
        }

        return $rows;
    }

    protected function reviewPath(Product $product): ?string
    {
        if ($this->highConfidencePath($product)) {
            return null;
        }

        $inferred = $this->categories->pathFromCatalogProduct($product);
        $current = $product->category?->urlPath();
        if ($inferred === '' || $current === null || $inferred === $current) {
            return null;
        }

        $inferredParent = Str::before($inferred, '/');
        $currentParent = Str::before($current, '/');
        if ($inferredParent === $currentParent) {
            return null;
        }

        return $inferred;
    }
}
