<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

class ProductSeoService
{
    public function __construct(
        protected CategoryMapperService $categoryMapper,
    ) {}

    /** @return array{processed: int, categorized: int, meta_updated: int, images_updated: int} */
    public function optimizeCatalog(bool $dryRun = false, ?int $limit = null): array
    {
        $this->categoryMapper->ensureCanonicalTree();

        $stats = [
            'processed' => 0,
            'categorized' => 0,
            'meta_updated' => 0,
            'images_updated' => 0,
        ];

        $query = Product::query()
            ->with(['images', 'category'])
            ->orderBy('id');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $query->lazyById(100)->each(function (Product $product) use ($dryRun, &$stats) {
            $stats['processed']++;
            $changes = $this->optimizeProduct($product, $dryRun);

            if ($changes['category']) {
                $stats['categorized']++;
            }
            if ($changes['meta']) {
                $stats['meta_updated']++;
            }
            $stats['images_updated'] += $changes['images'];
        });

        if (! $dryRun) {
            app(SeoService::class)->clearCache();
        }

        return $stats;
    }

    /** @return array{category: bool, meta: bool, images: int} */
    public function optimizeProduct(Product $product, bool $dryRun = false): array
    {
        $result = ['category' => false, 'meta' => false, 'images' => 0];
        $updates = [];

        if ($product->category_id === null) {
            $displayPath = $this->categoryMapper->pathFromCatalogProduct($product);
            $categoryId = $this->categoryMapper->resolveCategoryId($displayPath);

            if ($categoryId !== null) {
                $updates['category_id'] = $categoryId;
                $result['category'] = true;
            }
        }

        $metaTitle = $this->buildSeoTitle($product);
        $metaDescription = $this->buildMetaDescription($product);

        if ($this->shouldUpdateMeta($product->meta_title, $metaTitle)) {
            $updates['meta_title'] = $metaTitle;
            $result['meta'] = true;
        }

        if ($this->shouldUpdateMeta($product->meta_description, $metaDescription)) {
            $updates['meta_description'] = $metaDescription;
            $result['meta'] = true;
        }

        if ($updates !== [] && ! $dryRun) {
            $product->update($updates);
            $product->refresh();
        }

        $alt = $this->buildImageAlt($product);
        foreach ($product->images as $image) {
            if ($this->shouldUpdateMeta($image->alt_text, $alt)) {
                $result['images']++;
                if (! $dryRun) {
                    $image->update(['alt_text' => $alt]);
                }
            }
        }

        return $result;
    }

    public function buildSeoTitle(Product $product): string
    {
        $name = trim($product->name);
        $brand = trim((string) $product->brand);
        $model = trim((string) $product->model_number);

        $name = preg_replace('/\s*[-|–—]\s*(buy|shop|online|south africa).*$/iu', '', $name) ?? $name;
        $name = trim($name);

        if ($this->isGenericProductName($name)) {
            $specs = $this->extractSpecSnippet($product);
            $parts = array_filter([$brand, $name, $specs, $model]);

            return Str::limit(trim(implode(' ', $parts)), 65, '');
        }

        $title = $name;

        if ($brand !== '' && ! Str::contains(Str::lower($title), Str::lower($brand))) {
            $title = $brand.' '.$title;
        }

        if ($model !== '' && ! Str::contains($title, $model)) {
            $title .= ' '.$model;
        }

        return Str::limit(trim($title), 65, '');
    }

    public function buildMetaDescription(Product $product): string
    {
        $source = trim(strip_tags((string) ($product->short_description ?: $product->description)));

        if ($source === '') {
            $specs = $this->extractSpecSnippet($product);
            $source = trim(($product->brand ? $product->brand.' ' : '').$product->name.($specs !== '' ? ' — '.$specs : ''));
        }

        if ($product->sku) {
            $source .= ' SKU: '.$product->sku.'.';
        }

        return seo_meta_description($source, [
            'type' => 'product',
            'name' => $product->name,
            'brand' => $product->brand,
            'category' => $product->category?->name,
        ]);
    }

    public function buildImageAlt(Product $product): string
    {
        $title = $this->buildSeoTitle($product);

        return Str::limit($title.' — Urban Focus South Africa', 125, '');
    }

    protected function isGenericProductName(string $name): bool
    {
        $patterns = [
            '/^(gaming|business|office|home|portable|wireless|bluetooth|mechanical)\s+(laptop|notebook|pc|computer|mouse|keyboard|headset|router|switch|camera|printer)$/iu',
            '/^(laptop|notebook|router|switch|camera|printer|monitor|server|ups)$/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name)) {
                return true;
            }
        }

        return mb_strlen($name) < 18 && ! preg_match('/\d/', $name);
    }

    protected function extractSpecSnippet(Product $product): string
    {
        $text = implode(' ', array_filter([
            $product->name,
            $product->short_description,
            strip_tags((string) $product->description),
            is_array($product->specifications) ? implode(' ', $product->specifications) : '',
        ]));

        $parts = [];

        if (preg_match('/\b(intel\s+core\s+i[3579][-\s]?\d{4,5}[a-z]?|ryzen\s+[3579]\s*\d{4}[a-z]?|apple\s+m[123]\s*(pro|max|ultra)?)\b/i', $text, $m)) {
            $parts[] = trim($m[0]);
        }

        if (preg_match('/\b(rtx\s*\d{4}|gtx\s*\d{4}|rx\s*\d{4})\b/i', $text, $m)) {
            $parts[] = strtoupper(preg_replace('/\s+/', ' ', trim($m[0])));
        }

        if (preg_match('/\b(\d{1,3})\s*gb\s*(ram|ddr[345])?\b/i', $text, $m)) {
            $parts[] = $m[1].'GB RAM';
        }

        if (preg_match('/\b(\d{3,4})\s*gb\s*(ssd|nvme|hdd)?\b/i', $text, $m)) {
            $parts[] = $m[1].'GB Storage';
        }

        if (preg_match('/\b(\d{1,2})\s*["\u{201d}]\s*(fhd|uhd|4k)?\b/iu', $text, $m)) {
            $parts[] = $m[1].'" Display';
        }

        return implode(' ', array_unique($parts));
    }

    protected function shouldUpdateMeta(?string $current, string $proposed): bool
    {
        $current = trim((string) $current);

        if ($current === '') {
            return $proposed !== '';
        }

        if ($this->isWeakMeta($current) && mb_strlen($proposed) > mb_strlen($current)) {
            return true;
        }

        return false;
    }

    protected function isWeakMeta(string $value): bool
    {
        $lower = Str::lower($value);

        return Str::contains($lower, ['gaming laptop', 'generic', 'uncategorized', 'product'])
            || mb_strlen($value) < 25;
    }
}
