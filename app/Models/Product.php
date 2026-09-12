<?php

namespace App\Models;

use App\Services\CatalogDeduper;
use App\Services\InternalPricingCopySanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * AppServiceProvider sets Schema::defaultStringLength(191), so string()
     * columns including meta_keywords are VARCHAR(191) on MySQL.
     */
    public const META_KEYWORDS_MAX_LENGTH = 191;

    protected $fillable = [
        'category_id',
        'sku',
        'model_number',
        'name',
        'slug',
        'short_description',
        'description',
        'price',
        'sale_price',
        'cost_price',
        'stock_quantity',
        'manage_stock',
        'in_stock',
        'brand',
        'barcode',
        'google_product_category',
        'weight',
        'dimensions',
        'warranty_months',
        'delivery_days',
        'specifications',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'is_featured',
        'is_deal',
        'deal_label',
        'is_active',
        'views',
        'woocommerce_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'weight' => 'decimal:2',
            'manage_stock' => 'boolean',
            'in_stock' => 'boolean',
            'is_featured' => 'boolean',
            'is_deal' => 'boolean',
            'is_active' => 'boolean',
            'specifications' => 'array',
        ];
    }

    /**
     * When true, customer-facing copy is sanitised before insert/update.
     * Tests that need legacy dirty rows may set this to false.
     */
    public static bool $sanitizeCustomerCopyOnSave = true;

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });

        static::saving(function (Product $product) {
            if (! static::$sanitizeCustomerCopyOnSave) {
                return;
            }

            app(InternalPricingCopySanitizer::class)->applyToPersistedProduct($product);
        });
    }

    public function setMetaKeywordsAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['meta_keywords'] = $value;

            return;
        }

        $this->attributes['meta_keywords'] = Str::limit($value, self::META_KEYWORDS_MAX_LENGTH, '');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasMany
    {
        return $this->hasMany(ProductImage::class)->where('is_primary', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getEffectivePriceAttribute(): float
    {
        if ($this->sale_price && $this->sale_price > 0 && $this->sale_price < $this->price) {
            return (float) $this->sale_price;
        }

        return (float) $this->price;
    }

    public function getIsOnSaleAttribute(): bool
    {
        return $this->sale_price && $this->sale_price > 0 && $this->sale_price < $this->price;
    }

    public function discountPercent(): ?int
    {
        if (! $this->is_on_sale || (float) $this->price <= 0) {
            return null;
        }

        return (int) round((((float) $this->price - $this->effective_price) / (float) $this->price) * 100);
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        $image = $this->images->firstWhere('is_primary', true) ?? $this->images->first();

        return $image ? storage_public_url($image->path) : null;
    }

    public function getDisplayImageUrlAttribute(): string
    {
        return $this->primary_image_url ?? product_image_url();
    }

    public function seoTitle(): string
    {
        $name = trim($this->name);
        $suffix = ' | South Africa | Urban Focus';
        $max = 70;

        if (mb_strlen($name.$suffix) <= $max) {
            return $name.$suffix;
        }

        $budget = max(20, $max - mb_strlen($suffix));
        $trimmed = Str::limit($name, $budget, '');
        $space = mb_strrpos($trimmed, ' ');
        if ($space !== false && $space >= 16) {
            $trimmed = mb_substr($trimmed, 0, $space);
        }

        return rtrim($trimmed, " \t-|,").$suffix;
    }

    public function seoDescription(): string
    {
        $value = trim((string) ($this->attributes['meta_description'] ?? ''));

        if ($value !== '') {
            $value = app(InternalPricingCopySanitizer::class)->sanitizePlain($value);
        }

        if ($value !== '') {
            return seo_meta_description($value, [
                'type' => 'product',
                'name' => $this->name,
                'brand' => $this->brand,
                'category' => $this->category?->name,
            ]);
        }

        $subject = $this->name;
        $description = 'Buy '.$subject.' from Urban Focus South Africa. VAT-compliant invoicing, nationwide delivery and corporate/bulk procurement support. Request a quote online.';
        $description = preg_replace('/\s+/u', ' ', trim($description)) ?? '';

        return Str::limit($description, (int) config('seo.defaults.max_description_length', 160), '');
    }

    public function seoKeywords(): string
    {
        if (! empty($this->attributes['meta_keywords'])) {
            return $this->attributes['meta_keywords'];
        }

        $keywords = array_filter([
            $this->brand,
            $this->name,
            $this->category?->name,
            'buy online South Africa',
            'Urban Focus',
        ]);

        return implode(', ', array_unique($keywords));
    }

    public function imageAlt(): string
    {
        $image = $this->images->firstWhere('is_primary', true) ?? $this->images->first();

        if ($image && trim((string) $image->alt_text) !== '') {
            return trim($image->alt_text);
        }

        $parts = array_filter([$this->brand, $this->name, 'South Africa']);

        return implode(' — ', $parts);
    }

    public function isAvailable(): bool
    {
        if ($this->trashed() || ! $this->is_active) {
            return false;
        }

        if (! $this->manage_stock) {
            return $this->in_stock;
        }

        return $this->stock_quantity > 0;
    }

    public function publicationStatus(): string
    {
        if ($this->trashed()) {
            return 'archived';
        }

        return $this->is_active ? 'published' : 'draft';
    }

    public static function publicationStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived',
        ];
    }

    public function publicationStatusLabel(): string
    {
        return self::publicationStatuses()[$this->publicationStatus()] ?? ucfirst($this->publicationStatus());
    }

    public function applyPublicationStatus(string $status): void
    {
        match ($status) {
            'published' => $this->publishProduct(),
            'draft' => $this->draftProduct(),
            'archived' => $this->archiveProduct(),
            default => null,
        };
    }

    protected function publishProduct(): void
    {
        if ($this->trashed()) {
            $this->restore();
        }

        $this->update(['is_active' => true]);
    }

    protected function draftProduct(): void
    {
        if ($this->trashed()) {
            $this->restore();
        }

        $this->update(['is_active' => false]);
    }

    protected function archiveProduct(): void
    {
        $this->update(['is_active' => false]);

        if (! $this->trashed()) {
            $this->delete();
        }
    }

    public function scopePublicationStatus($query, string $status)
    {
        return match ($status) {
            'published' => $query->where('is_active', true),
            'draft' => $query->where('is_active', false),
            'archived' => $query->onlyTrashed(),
            default => $query,
        };
    }

    public function scopeAvailableInStock($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($inner) {
                $inner->where('manage_stock', true)->where('stock_quantity', '>', 0);
            })->orWhere(function ($inner) {
                $inner->where('manage_stock', false)->where('in_stock', true);
            });
        });
    }

    public function scopeForStorefront($query, ?bool $includeOutOfStock = null)
    {
        $query->where('is_active', true);
        $query->withoutDuplicateListings();

        $includeOutOfStock ??= ! config('catalog.hide_out_of_stock', true);

        if (! $includeOutOfStock) {
            $query->availableInStock();
        } elseif (config('catalog.deprioritize_out_of_stock', true)) {
            $query->orderByRaw(
                'CASE WHEN (manage_stock = 1 AND stock_quantity > 0) OR (manage_stock = 0 AND in_stock = 1) THEN 0 ELSE 1 END'
            );
        }

        return $query;
    }

    public function scopeWithoutDuplicateListings($query)
    {
        $ids = app(CatalogDeduper::class)->idsToHide();

        if ($ids !== []) {
            $query->whereNotIn($this->getTable().'.id', $ids);
        }

        return $query;
    }

    public static function applyStorefrontStockFilter($query, ?Request $request = null): void
    {
        $query->withoutDuplicateListings();

        if (config('catalog.hide_out_of_stock', true)) {
            if (! $request?->boolean('include_out_of_stock')) {
                $query->availableInStock();
            } elseif (config('catalog.deprioritize_out_of_stock', true)) {
                $query->orderByRaw(
                    'CASE WHEN (manage_stock = 1 AND stock_quantity > 0) OR (manage_stock = 0 AND in_stock = 1) THEN 0 ELSE 1 END'
                );
            }

            return;
        }

        if ($request?->boolean('in_stock')) {
            $query->availableInStock();
        }
    }

    public function googleFeedId(): string
    {
        return $this->sku ?: (string) $this->id;
    }

    public function googleFeedTitle(): string
    {
        $parts = array_filter([$this->brand, $this->sku, $this->name]);

        return Str::limit(implode(' ', $parts), config('google-merchant.title_max_length', 150), '');
    }

    public function googleFeedMpn(): ?string
    {
        return $this->model_number ?: $this->sku ?: null;
    }

    public function availabilityKey(): ?string
    {
        $key = trim((string) (($this->specifications ?? [])['Availability key'] ?? ''));

        return $key !== '' && array_key_exists($key, config('specialist.availability', []))
            ? $key
            : null;
    }

    public function availabilityMeta(): ?array
    {
        $key = $this->availabilityKey();

        return $key ? (config("specialist.availability.{$key}") ?: null) : null;
    }

    public function availabilityLabel(): string
    {
        if ($meta = $this->availabilityMeta()) {
            return (string) $meta['label'];
        }

        return $this->isAvailable() ? 'In Stock' : 'Out of Stock';
    }

    public function isQuoteOnly(): bool
    {
        return (bool) ($this->availabilityMeta()['quote'] ?? false);
    }

    public function listingFaqs(): array
    {
        $specs = $this->specifications ?? [];
        $faqs = [];

        for ($i = 1; $i <= 6; $i++) {
            $question = trim((string) ($specs["FAQ {$i} question"] ?? ''));
            $answer = trim((string) ($specs["FAQ {$i} answer"] ?? ''));
            if ($question !== '' && $answer !== '') {
                $faqs[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $faqs !== []
            ? app(InternalPricingCopySanitizer::class)->sanitizeFaqs($faqs)
            : [];
    }

    public function storefrontShortDescription(): string
    {
        return app(InternalPricingCopySanitizer::class)->sanitizePlain((string) $this->short_description);
    }

    public function storefrontDescriptionHtml(): string
    {
        return app(InternalPricingCopySanitizer::class)->sanitizeHtml((string) $this->description);
    }

    public function googleFeedAvailability(): string
    {
        if ($meta = $this->availabilityMeta()) {
            return (string) ($meta['google'] ?? 'in_stock');
        }

        if ($this->manage_stock) {
            return $this->stock_quantity > 0 ? 'in_stock' : 'out_of_stock';
        }

        return $this->in_stock ? 'in_stock' : 'out_of_stock';
    }

    public function googleFeedDescription(): string
    {
        $sanitizer = app(InternalPricingCopySanitizer::class);
        $text = $sanitizer->sanitizePlain((string) $this->short_description);
        if ($text === '') {
            $text = $sanitizer->sanitizePlain(strip_tags((string) $this->description));
        }
        if ($text === '') {
            $text = (string) $this->name;
        }
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';

        return Str::limit($text, config('google-merchant.description_max_length', 5000), '');
    }

    public function googleProductCategory(): ?string
    {
        return $this->google_product_category ?: null;
    }

    public function googleFeedAdditionalImages(): array
    {
        $primary = $this->images->firstWhere('is_primary', true) ?? $this->images->first();

        return $this->images
            ->when($primary, fn ($images) => $images->where('id', '!=', $primary->id))
            ->take(10)
            ->map(fn ($image) => storage_public_url($image->path))
            ->filter()
            ->values()
            ->all();
    }

    public function hasValidGtin(): bool
    {
        if (! $this->barcode) {
            return false;
        }

        $gtin = $this->normalizedGtin();

        return in_array(strlen($gtin), [8, 12, 13, 14], true) && ctype_digit($gtin);
    }

    public function normalizedGtin(): string
    {
        return preg_replace('/\D/', '', (string) $this->barcode);
    }

    public function googleMerchantIssues(): array
    {
        $issues = [];

        if (! $this->primary_image_url) {
            $issues[] = 'no_image';
        }

        if (strlen($this->googleFeedDescription()) < 10) {
            $issues[] = 'no_description';
        }

        if ($this->effective_price <= 0 || $this->isQuoteOnly()) {
            $issues[] = 'no_price';
        }

        if (! $this->brand) {
            $issues[] = 'no_brand';
        }

        if (! $this->hasValidGtin() && ! $this->sku) {
            $issues[] = 'no_identifier';
        }

        return $issues;
    }

    public function isGoogleMerchantEligible(): bool
    {
        return $this->is_active && $this->googleMerchantIssues() === [];
    }

    public function bobShopStockQuantity(): int
    {
        if ($this->manage_stock) {
            return max(0, (int) $this->stock_quantity);
        }

        return $this->in_stock ? 999 : 0;
    }

    public function bobShopCategoryPath(): string
    {
        $parts = [];
        $category = $this->category;

        while ($category) {
            array_unshift($parts, $category->name);
            $category = $category->parent;
        }

        return $parts !== []
            ? implode(' > ', $parts)
            : (string) config('bobshop.default_category', 'Computers & Electronics');
    }

    public function bobShopDescription(): string
    {
        $text = $this->googleFeedDescription();

        return strip_tags($text, '<p><br><br/>');
    }

    /** @return list<string> */
    public function bobShopIssues(): array
    {
        $issues = [];

        if (! $this->sku) {
            $issues[] = 'no_sku';
        }

        if (config('bobshop.xml.require_gtin', false) && ! $this->hasValidGtin()) {
            $issues[] = 'no_gtin';
        }

        if (strlen($this->googleFeedDescription()) < 10) {
            $issues[] = 'no_description';
        }

        if ($this->effective_price <= 0) {
            $issues[] = 'no_price';
        }

        if ($this->bobShopXmlImageUrls() === []) {
            $issues[] = 'no_image';
        }

        if ($this->bobShopStockQuantity() <= 0) {
            $issues[] = 'no_stock';
        }

        return $issues;
    }

    public function isBobShopEligible(): bool
    {
        $issues = $this->bobShopIssues();

        if (! config('bobshop.xml_require_stock', true)) {
            $issues = array_values(array_diff($issues, ['no_stock']));
        }

        return $this->is_active && $issues === [];
    }

    /** @return list<string> */
    public function bobShopBulkloadIssues(): array
    {
        $issues = [];

        if (! $this->sku) {
            $issues[] = 'no_sku';
        }

        if (strlen($this->googleFeedDescription()) < 10) {
            $issues[] = 'no_description';
        }

        if ($this->effective_price <= 0) {
            $issues[] = 'no_price';
        }

        if (config('bobshop.bulkload.require_stock', false) && $this->bobShopStockQuantity() <= 0) {
            $issues[] = 'no_stock';
        }

        return $issues;
    }

    public function isBobShopBulkloadEligible(): bool
    {
        return $this->is_active && $this->bobShopBulkloadIssues() === [];
    }

    public function bobShopPrimaryCategoryId(): string
    {
        $slug = $this->category?->slug;
        $map = config('bobshop.primary_category_ids', []);

        if ($slug && isset($map[$slug])) {
            return (string) $map[$slug];
        }

        return (string) config('bobshop.default_primary_category_id', '2521');
    }

    public function bobShopWarrantyType(): string
    {
        $months = (int) ($this->warranty_months ?? config('shipping.default_warranty_months', 12));

        return $months > 0 ? 'MANUFACTURER' : 'NOT_OFFERED';
    }

    public function bobShopWarrantyRemarks(): string
    {
        if ($this->bobShopWarrantyType() === 'NOT_OFFERED') {
            return '';
        }

        return Str::limit($this->warrantyLabel(), 300, '');
    }

    /** Bob Shop XML spec: New, Secondhand, or Refurbished. */
    public function bobShopCondition(): string
    {
        return 'New';
    }

    /** Bob Shop XML WarrantyType numeric code (0–3). */
    public function bobShopWarrantyTypeCode(): string
    {
        return match ($this->bobShopWarrantyType()) {
            'REPLACEMENT' => '1',
            'DEALER' => '2',
            'MANUFACTURER' => '3',
            default => '0',
        };
    }

    public function bobShopXmlDescription(): string
    {
        $body = '<p>'.e($this->bobShopDescription()).'</p>'
            .'<p><a href="'.e(route('products.show', $this)).'">View on Urban Focus</a></p>';

        return Str::limit($body, (int) config('bobshop.max_description_length', 8000), '');
    }

    /** @return list<string> */
    public function bobShopXmlImageUrls(): array
    {
        $maxLen = (int) config('bobshop.xml.max_image_url_length', 300);
        $urls = [];

        if ($this->primary_image_url) {
            $urls[] = $this->primary_image_url;
        }

        foreach ($this->googleFeedAdditionalImages() as $imageUrl) {
            $urls[] = $imageUrl;
        }

        if ($urls === [] && config('bobshop.bulkload.use_placeholder_image', true)) {
            $urls[] = product_image_url();
        }

        return array_values(array_filter(array_map(
            fn (string $url) => Str::limit($url, $maxLen, ''),
            array_unique($urls)
        )));
    }

    /** @return array<string, string> */
    public static function googleMerchantIssueLabels(): array
    {
        return [
            'no_image' => 'Missing image',
            'no_description' => 'Missing description',
            'no_price' => 'Missing price',
            'no_brand' => 'Missing brand',
            'no_identifier' => 'Missing SKU/GTIN',
        ];
    }

    public function scopeMerchantIssue($query, string $issue)
    {
        return match ($issue) {
            'no_image' => $query->whereDoesntHave('images'),
            'no_description' => $query->whereRaw(
                "CHAR_LENGTH(TRIM(COALESCE(short_description, ''))) + CHAR_LENGTH(TRIM(COALESCE(description, ''))) < 10"
            ),
            'no_price' => $query->where('price', '<=', 0)
                ->where(function ($q) {
                    $q->whereNull('sale_price')->orWhere('sale_price', '<=', 0);
                }),
            'no_brand' => $query->where(function ($q) {
                $q->whereNull('brand')->orWhere('brand', '');
            }),
            'no_identifier' => $query->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNull('sku')->orWhere('sku', '');
                })->where(function ($q2) {
                    $q2->whereNull('barcode')->orWhere('barcode', '');
                });
            }),
            default => $query,
        };
    }

    public function deliveryEstimate(): string
    {
        if ($meta = $this->availabilityMeta()) {
            if (! empty($meta['quote'])) {
                return 'Quoted lead time after confirmation';
            }

            $days = (int) ($meta['days'] ?? ($this->delivery_days ?? 7));
            if (($this->availabilityKey() === 'eu_stock')) {
                return '5–10 business days from EU stock';
            }
            if ($this->availabilityKey() === 'special_order_eu') {
                return 'Special order from Europe (typically 2–4 weeks)';
            }
            if ($this->availabilityKey() === 'in_stock_za') {
                return '1–3 business days in South Africa';
            }

            return $days.'–'.($days + 2).' business days';
        }

        $days = $this->delivery_days ?? config('shipping.default_delivery_days', 3);

        if (! $this->isAvailable()) {
            return 'Available on request';
        }

        return $days <= 2
            ? '1–2 business days'
            : $days.'–'.($days + 1).' business days';
    }

    public function warrantyLabel(): string
    {
        $months = $this->warranty_months ?? config('shipping.default_warranty_months', 12);

        return $months >= 12
            ? (int) ($months / 12).' year manufacturer warranty'
            : $months.' month manufacturer warranty';
    }

    public function specificationsList(): array
    {
        $specs = $this->specifications ?? [];

        if ($this->model_number) {
            $specs = array_merge(['Model' => $this->model_number], $specs);
        }
        if ($this->brand) {
            $specs = array_merge(['Brand' => $this->brand], $specs);
        }
        if ($this->sku) {
            $specs['SKU'] = $this->sku;
        }
        if ($this->weight) {
            $specs['Weight'] = $this->weight.' kg';
        }
        if ($this->dimensions) {
            $specs['Dimensions'] = $this->dimensions;
        }

        unset($specs['Urban Focus range'], $specs['Sales focus'], $specs['Supply'], $specs['Availability key'], $specs['Listing photo']);

        for ($i = 1; $i <= 6; $i++) {
            unset($specs["FAQ {$i} question"], $specs["FAQ {$i} answer"]);
        }

        return $specs;
    }

    /**
     * Crawlable audience copy for search engines and AI assistants.
     *
     * @return list<string>
     */
    public function buyerFitLines(): array
    {
        $html = (string) $this->storefrontDescriptionHtml();
        if ($html !== '' && preg_match('/suitable for\s*<\/h[1-6]>\s*<ul>(.*?)<\/ul>/is', $html, $match)) {
            preg_match_all('/<li\b[^>]*>(.*?)<\/li>/is', $match[1], $items);
            $lines = [];
            foreach ($items[1] ?? [] as $item) {
                $text = trim(html_entity_decode(strip_tags($item), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($text !== '') {
                    $lines[] = $text;
                }
            }
            if ($lines !== []) {
                return array_values(array_unique(array_slice($lines, 0, 8)));
            }
        }

        $category = $this->category?->name;
        $subject = $category ?: ($this->brand ? $this->brand.' hardware' : 'IT hardware');

        $lines = [
            'South African businesses, integrators and procurement teams buying '.$subject.' with VAT invoices and nationwide courier delivery.',
        ];

        if ($this->brand) {
            $lines[] = $this->brand.' customers who need a local IT supplier rather than importing this product themselves.';
        }

        return $lines;
    }

    public function toSchemaArray(): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $this->name,
            'description' => $this->googleFeedDescription(),
            'brand' => [
                '@type' => 'Brand',
                'name' => $this->brand ?: 'Urban Focus',
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => route('products.show', $this),
                'priceCurrency' => config('google-merchant.currency', 'ZAR'),
                'availability' => $this->schemaAvailabilityUrl(),
                'itemCondition' => 'https://schema.org/NewCondition',
                'areaServed' => [
                    '@type' => 'Country',
                    'name' => 'South Africa',
                ],
                'seller' => [
                    '@type' => 'Organization',
                    'name' => 'Urban Focus',
                    'url' => config('app.url'),
                ],
            ],
        ];

        if (! $this->isQuoteOnly() && $this->effective_price > 0) {
            $schema['offers']['price'] = number_format((float) $this->effective_price, 2, '.', '');
        }

        if ($this->primary_image_url) {
            $images = array_filter(array_merge(
                [$this->primary_image_url],
                $this->googleFeedAdditionalImages()
            ));
            $schema['image'] = count($images) === 1 ? $images[0] : array_values($images);
        }

        $schema['url'] = route('products.show', $this);

        if ($this->sku) {
            $schema['sku'] = $this->sku;
        }

        if ($this->category) {
            $schema['category'] = $this->category->name;
        }

        if ($this->hasValidGtin()) {
            $schema['gtin'.$this->gtinSchemaLength()] = $this->normalizedGtin();
        }

        if ($mpn = $this->googleFeedMpn()) {
            $schema['mpn'] = $mpn;
        }

        if ($this->model_number) {
            $schema['model'] = $this->model_number;
        }

        $properties = [];
        foreach (array_slice($this->specificationsList(), 0, 12, true) as $name => $value) {
            if (! is_scalar($value)) {
                continue;
            }
            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }
            $properties[] = [
                '@type' => 'PropertyValue',
                'name' => (string) $name,
                'value' => $text,
            ];
        }
        if ($properties !== []) {
            $schema['additionalProperty'] = $properties;
        }

        return $schema;
    }

    public function faqSchemaArray(): ?array
    {
        $faqs = $this->listingFaqs();
        if ($faqs === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $faq) => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ], $faqs),
        ];
    }

    public function schemaAvailabilityUrl(): string
    {
        if ($meta = $this->availabilityMeta()) {
            return (string) ($meta['schema'] ?? 'https://schema.org/InStock');
        }

        return $this->isAvailable()
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';
    }

    protected function gtinSchemaLength(): string
    {
        return match (strlen($this->normalizedGtin())) {
            8 => '8',
            12 => '12',
            13 => '13',
            14 => '14',
            default => '',
        };
    }

    public function toBreadcrumbSchema(): array
    {
        $items = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Home',
                'item' => route('home'),
            ],
        ];

        if ($this->category) {
            $this->category->loadMissing('parent');
            $position = 2;

            if ($this->category->parent) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $this->category->parent->name,
                    'item' => $this->category->parent->url(),
                ];
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $this->category->name,
                'item' => $this->category->url(),
            ];
        }

        $items[] = [
            '@type' => 'ListItem',
            'position' => count($items) + 1,
            'name' => $this->name,
            'item' => route('products.show', $this),
        ];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }
}
