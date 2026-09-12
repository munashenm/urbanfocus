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
        $all = Product::query()->count();
        $activeCount = Product::query()->where('is_active', true)->count();

        $duplicateSkus = $this->duplicateSkuRowsFromDatabase();
        $skuNameConflicts = collect($duplicateSkus)
            ->filter(fn (array $row) => count($row['names']) > 1)
            ->values()
            ->all();
        $duplicateNames = $this->duplicateNameRowsFromDatabase();

        $missingSkuQuery = Product::query()->where('is_active', true)->where(function ($q) {
            $q->whereNull('sku')->orWhere('sku', '');
        });
        $missingPriceQuery = Product::query()->where('is_active', true)
            ->whereRaw('COALESCE(sale_price, price) <= 0');
        $missingImageQuery = Product::query()->where('is_active', true)->whereDoesntHave('images');

        $missingSkus = $this->sampleMissing($missingSkuQuery->clone()->orderBy('id')->limit(60)->get(), 'sku');
        $missingPrices = $this->sampleMissing($missingPriceQuery->clone()->orderBy('id')->limit(60)->get(), 'price');
        $missingImages = $this->sampleMissing($missingImageQuery->clone()->orderBy('id')->limit(60)->get(), 'image');

        $malformedNames = [];
        $wrongBrandSku = [];
        $taxonomyHigh = [];
        $taxonomyReview = [];
        $missingDescriptions = [];
        $seoTitleCollisions = [];
        $titleIndex = [];
        $malformedCount = 0;
        $wrongBrandCount = 0;
        $taxonomyHighCount = 0;
        $taxonomyReviewCount = 0;
        $missingDescriptionCount = 0;
        $seoTitleCollisionCount = 0;

        Product::query()
            ->with(['category.parent'])
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById(80, function (Collection $chunk) use (
                &$malformedNames,
                &$wrongBrandSku,
                &$taxonomyHigh,
                &$taxonomyReview,
                &$missingDescriptions,
                &$titleIndex,
                &$malformedCount,
                &$wrongBrandCount,
                &$taxonomyHighCount,
                &$taxonomyReviewCount,
                &$missingDescriptionCount,
            ) {
                foreach ($chunk as $product) {
                    if (trim(strip_tags((string) $product->short_description)) === ''
                        && trim(strip_tags((string) $product->description)) === '') {
                        $missingDescriptionCount++;
                        if (count($missingDescriptions) < 60) {
                            $missingDescriptions[] = $this->sampleRow($product, 'description');
                        }
                    }

                    if ($this->isMalformedName($product)) {
                        $malformedCount++;
                        if (count($malformedNames) < 40) {
                            $malformedNames[] = [
                                'id' => $product->id,
                                'sku' => $product->sku,
                                'name' => $product->name,
                            ];
                        }
                    }

                    if ($mismatch = $this->brandSkuMismatch($product)) {
                        $wrongBrandCount++;
                        if (count($wrongBrandSku) < 40) {
                            $wrongBrandSku[] = $mismatch;
                        }
                    }

                    $highPath = $this->highConfidencePath($product);
                    $targetId = $highPath ? $this->highConfidenceTarget($product) : null;
                    if ($highPath && $targetId && $targetId !== (int) $product->category_id) {
                        $taxonomyHighCount++;
                        if (count($taxonomyHigh) < 80) {
                            $taxonomyHigh[] = [
                                'id' => $product->id,
                                'sku' => $product->sku,
                                'name' => $product->name,
                                'from' => $product->category?->fullPathLabel(),
                                'to' => $highPath,
                                'confidence' => 'high',
                            ];
                        }
                    } elseif ($reviewPath = $this->reviewPath($product)) {
                        $targetId = $this->categories->resolveCategoryId($reviewPath);
                        if ($targetId && $targetId !== (int) $product->category_id) {
                            $taxonomyReviewCount++;
                            if (count($taxonomyReview) < 80) {
                                $taxonomyReview[] = [
                                    'id' => $product->id,
                                    'sku' => $product->sku,
                                    'name' => $product->name,
                                    'from' => $product->category?->fullPathLabel(),
                                    'to' => $reviewPath,
                                    'confidence' => 'review',
                                ];
                            }
                        }
                    }

                    $titleKey = mb_strtolower($product->seoTitle());
                    $titleIndex[$titleKey]['title'] = $product->seoTitle();
                    $titleIndex[$titleKey]['skus'][] = $product->sku;
                    $titleIndex[$titleKey]['names'][] = $product->name;
                }
            });

        foreach ($titleIndex as $group) {
            $skus = array_values(array_unique($group['skus'] ?? []));
            $names = array_values(array_unique($group['names'] ?? []));
            if (count($group['skus'] ?? []) < 2) {
                continue;
            }
            $seoTitleCollisionCount++;
            if (count($seoTitleCollisions) < 40) {
                $seoTitleCollisions[] = [
                    'title' => $group['title'],
                    'count' => count($group['skus']),
                    'skus' => $skus,
                    'names' => $names,
                ];
            }
        }

        $uswMatches = Product::query()
            ->with(['category.parent'])
            ->where(function ($q) {
                $q->whereRaw("LOWER(TRIM(COALESCE(sku, ''))) = ?", ['usw-16p'])
                    ->orWhere('name', 'like', '%USW-16P%')
                    ->orWhere('model_number', 'like', '%USW-16P%')
                    ->orWhere('slug', 'like', '%usw-16p%')
                    ->orWhere('slug', 'like', '%usw16p%');
            })
            ->orderBy('id')
            ->get();
        $usw16p = $this->skuFocus($uswMatches, 'USW-16P');
        $hiddenDuplicates = 0;

        return [
            'generated_at' => now()->toIso8601String(),
            'totals' => [
                'all' => $all,
                'active' => $activeCount,
                'inactive' => $all - $activeCount,
            ],
            'counts' => [
                'duplicate_sku_groups' => count($duplicateSkus),
                'sku_name_conflicts' => count($skuNameConflicts),
                'duplicate_name_groups' => count($duplicateNames),
                'seo_title_collisions' => $seoTitleCollisionCount,
                'missing_skus' => $missingSkuQuery->count(),
                'missing_prices' => $missingPriceQuery->count(),
                'missing_images' => $missingImageQuery->count(),
                'missing_descriptions' => $missingDescriptionCount,
                'malformed_names' => $malformedCount,
                'wrong_brand_sku' => $wrongBrandCount,
                'taxonomy_high_confidence' => $taxonomyHighCount,
                'taxonomy_review' => $taxonomyReviewCount,
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

        return $this->categories->resolveCategoryIdFromPath($path)
            ?? $this->categories->resolveCategoryId($path);
    }

    public function highConfidencePath(Product $product): ?string
    {
        $name = trim($product->name.' '.$product->sku.' '.$product->model_number.' '.str_replace('-', ' ', (string) $product->slug));
        $hay = strtolower(trim(
            ($product->category?->slug ?? '').' '
            .($product->category?->urlPath() ?? '').' '
            .($product->category?->name ?? '').' '
            .($product->category?->parent?->name ?? '')
        ));
        $inAccessories = str_contains($hay, 'accessor');
        $inLaptops = (str_contains($hay, 'laptop') || str_contains($hay, 'notebook') || str_contains($hay, 'chromebook'))
            && ! $inAccessories;
        $inMonitors = (str_contains($hay, 'monitor') || str_contains($hay, 'commercial-display'))
            && ! str_contains($hay, 'interactive');

        $isLaptopAccessory = (bool) preg_match(
            '/\b(charger|power adapter|ac adapter|mains adapter|laptop psu|backpack|laptop bag|notebook bag|laptop sleeve|notebook sleeve|laptop case|notebook case|clamshell|notebook stand|laptop stand|notebook lock|laptop lock|security lock)\b/i',
            $name
        );

        if ($isLaptopAccessory && $inLaptops) {
            return 'computing-office/computer-accessories';
        }

        $isComputerCharger = (bool) preg_match(
            '/\b(laptop charger|notebook charger|gan wall charger|ac adapter|power adapter|laptop psu)\b/i',
            $name
        ) && ! preg_match('/\b(solar|battery equalis|floodlamp|lead acid|battery pack|4g|camera)\b/i', $name);

        if ($isComputerCharger && ! $inAccessories) {
            return 'computing-office/computer-accessories';
        }

        $isBoard = (bool) preg_match('/\b(interactive|smart board|smartboard|ifp\d|ifpd|interactive flat panel|meetingboard)\b/i', $name);
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
     * @return list<array<string, mixed>>
     */
    protected function duplicateSkuRowsFromDatabase(): array
    {
        $keys = Product::query()
            ->where('is_active', true)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->selectRaw('LOWER(TRIM(sku)) as sku_key, COUNT(*) as cnt')
            ->groupByRaw('LOWER(TRIM(sku))')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('cnt')
            ->limit(80)
            ->get();

        $rows = [];
        foreach ($keys as $group) {
            $items = Product::query()
                ->where('is_active', true)
                ->whereRaw('LOWER(TRIM(sku)) = ?', [$group->sku_key])
                ->get(['id', 'sku', 'name', 'slug']);
            $rows[] = [
                'sku' => $items->first()?->sku,
                'count' => $items->count(),
                'names' => $items->pluck('name')->unique()->values()->all(),
                'ids' => $items->pluck('id')->all(),
                'slugs' => $items->pluck('slug')->all(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function duplicateNameRowsFromDatabase(): array
    {
        $keys = Product::query()
            ->where('is_active', true)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->selectRaw('LOWER(TRIM(name)) as name_key, COUNT(*) as cnt')
            ->groupByRaw('LOWER(TRIM(name))')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('cnt')
            ->limit(40)
            ->get();

        $rows = [];
        foreach ($keys as $group) {
            $items = Product::query()
                ->where('is_active', true)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$group->name_key])
                ->get(['id', 'sku', 'name']);
            $rows[] = [
                'name' => $items->first()?->name,
                'count' => $items->count(),
                'skus' => $items->pluck('sku')->unique()->values()->all(),
                'ids' => $items->pluck('id')->all(),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    protected function sampleMissing(Collection $products, string $field): array
    {
        return $products
            ->map(fn (Product $p) => $this->sampleRow($p, $field))
            ->values()
            ->all();
    }

    /**
     * @return array{id:int,sku:mixed,name:mixed,field:string,slug:mixed}
     */
    protected function sampleRow(Product $product, string $field): array
    {
        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'field' => $field,
            'slug' => $product->slug,
        ];
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

        $hidden = [];

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
                'hidden_as_duplicate' => isset($hidden[$p->id]),
            ])->all(),
        ];
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    protected function malformedNames(Collection $products): array
    {
        return $products
            ->filter(fn (Product $p) => $this->isMalformedName($p))
            ->take(40)
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
            ])
            ->values()
            ->all();
    }

    protected function isMalformedName(Product $product): bool
    {
        $name = trim($product->name);
        if ($name === '' || mb_strlen($name) < 8) {
            return true;
        }
        if (preg_match('/[<>]|1Pieces|undefined|null|test product/i', $name)) {
            return true;
        }

        return (bool) preg_match('/^[A-Z0-9\s\-_\/]{12,}$/', $name)
            && ! preg_match('/[a-z]/', $name)
            && mb_strlen($name) > 40;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    protected function wrongBrandSku(Collection $products): array
    {
        $rows = [];
        foreach ($products as $product) {
            $mismatch = $this->brandSkuMismatch($product);
            if ($mismatch) {
                $rows[] = $mismatch;
            }
            if (count($rows) >= 40) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return array{id:int,sku:mixed,name:mixed,brand:string,expected_brand:string}|null
     */
    protected function brandSkuMismatch(Product $product): ?array
    {
        $sku = strtoupper(trim((string) $product->sku));
        $brand = trim((string) $product->brand);
        if ($sku === '') {
            return null;
        }

        $expected = null;
        if (preg_match('/^(USW|U6-|U7-|UCK|UDM|UAP|UVC|UC-)/', $sku)) {
            $expected = 'Ubiquiti';
        } elseif (preg_match('/^(RB|CRS|CCR|C52|L009)/', $sku)) {
            $expected = 'MikroTik';
        }

        if ($expected && $brand !== '' && stripos($brand, $expected) === false && stripos($product->name, $expected) === false) {
            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'brand' => $brand,
                'expected_brand' => $expected,
            ];
        }

        return null;
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
