<?php

namespace App\Models;

use App\Services\CatalogDeduper;
use App\Services\InternalPricingCopySanitizer;
use App\Support\WarrantyCopy;
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
        'sale_starts_at',
        'sale_ends_at',
        'promo_label',
        'cost_price',
        'supplier_cost_price',
        'stock_quantity',
        'manage_stock',
        'in_stock',
        'brand',
        'supplier_name',
        'supplier_id',
        'supplier_sku',
        'supplier_product_url',
        'supplier_image_url',
        'import_source',
        'source_file',
        'source_external_id',
        'last_synced_at',
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
        'is_oos_listable',
        'last_in_stock_at',
        'oos_hidden_at',
        'views',
        'woocommerce_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'cost_price' => 'decimal:2',
            'supplier_cost_price' => 'decimal:2',
            'weight' => 'decimal:2',
            'manage_stock' => 'boolean',
            'in_stock' => 'boolean',
            'is_featured' => 'boolean',
            'is_deal' => 'boolean',
            'is_active' => 'boolean',
            'is_oos_listable' => 'boolean',
            'specifications' => 'array',
            'last_synced_at' => 'datetime',
            'last_in_stock_at' => 'datetime',
            'oos_hidden_at' => 'datetime',
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

    public function activeSalePrice(): ?float
    {
        if (! $this->salePriceIsLower() || ! $this->saleWindowIsOpen()) {
            return null;
        }

        return round((float) $this->sale_price, 2);
    }

    public function isOnSale(): bool
    {
        return $this->activeSalePrice() !== null;
    }

    public function promoLabel(): string
    {
        $label = trim((string) $this->promo_label);

        return $label !== '' ? $label : 'Black Friday';
    }

    /**
     * A lower sale price that is running now or starts in the future.
     * Expired windows are omitted from the merchant feed.
     */
    public function hasMerchantSale(): bool
    {
        if (! $this->salePriceIsLower()) {
            return false;
        }

        return $this->sale_ends_at === null || now()->lte($this->sale_ends_at);
    }

    public function salePriceEffectiveDate(): ?string
    {
        if (! $this->hasMerchantSale()) {
            return null;
        }

        $start = ($this->sale_starts_at ?? now())->copy()->timezone('Africa/Johannesburg');
        $end = ($this->sale_ends_at ?? $start->copy()->addYear())->copy()->timezone('Africa/Johannesburg');

        return $start->format('Y-m-d\TH:i:sP').'/'.$end->format('Y-m-d\TH:i:sP');
    }

    public function getEffectivePriceAttribute(): float
    {
        return $this->activeSalePrice() ?? (float) $this->price;
    }

    public function getIsOnSaleAttribute(): bool
    {
        return $this->isOnSale();
    }

    public function scopeOnSale($query)
    {
        $now = now();

        return $query
            ->whereNotNull('sale_price')
            ->where('sale_price', '>', 0)
            ->whereColumn('sale_price', '<', 'price')
            ->where(function ($window) use ($now) {
                $window->whereNull('sale_starts_at')->orWhere('sale_starts_at', '<=', $now);
            })
            ->where(function ($window) use ($now) {
                $window->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>=', $now);
            });
    }

    public static function effectivePriceSql(): string
    {
        return 'CASE WHEN sale_price IS NOT NULL AND sale_price > 0 AND sale_price < price'
            .' AND (sale_starts_at IS NULL OR sale_starts_at <= ?)'
            .' AND (sale_ends_at IS NULL OR sale_ends_at >= ?)'
            .' THEN sale_price ELSE price END';
    }

    protected function salePriceIsLower(): bool
    {
        return $this->sale_price !== null
            && (float) $this->sale_price > 0
            && (float) $this->sale_price < (float) $this->price;
    }

    protected function saleWindowIsOpen(): bool
    {
        $now = now();

        if ($this->sale_starts_at !== null && $now->lt($this->sale_starts_at)) {
            return false;
        }

        if ($this->sale_ends_at !== null && $now->gt($this->sale_ends_at)) {
            return false;
        }

        return true;
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
        if (! $image || is_catalog_placeholder_path($image->path)) {
            return null;
        }

        return storage_public_url($image->path);
    }

    public function getDisplayImageUrlAttribute(): string
    {
        return $this->primary_image_url ?? product_image_url();
    }

    public function seoTitle(): string
    {
        $saved = trim(strip_tags((string) ($this->attributes['meta_title'] ?? '')));
        $saved = preg_replace('/\s+/u', ' ', $saved) ?? '';
        if ($saved !== '') {
            $saved = trim(app(InternalPricingCopySanitizer::class)->sanitizePlain($saved));
        }
        if ($saved !== '') {
            return $this->limitedSeoTitle($saved);
        }

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

    private function limitedSeoTitle(string $title): string
    {
        $max = 70;
        if (mb_strlen($title) <= $max) {
            return $title;
        }

        $trimmed = Str::limit($title, $max, '');
        $space = mb_strrpos($trimmed, ' ');
        if ($space !== false && $space >= 16) {
            $trimmed = mb_substr($trimmed, 0, $space);
        }

        return rtrim($trimmed, " \t-|,");
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

    public static function naturalImageAlt(?string $brand, ?string $name): string
    {
        $name = trim((string) $name);
        $brand = trim((string) $brand);

        if ($brand === '') {
            return $name !== '' ? $name : 'Urban Focus product';
        }

        if ($name === '') {
            return $brand;
        }

        if (str_starts_with(mb_strtolower($name), mb_strtolower($brand))) {
            return $name;
        }

        return $brand.' '.$name;
    }

    public function imageAlt(): string
    {
        return static::naturalImageAlt($this->brand, $this->name);
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
        $this->restoreIfArchived();
        $this->forceFill(['is_active' => true])->save();
    }

    protected function draftProduct(): void
    {
        $this->restoreIfArchived();
        $this->forceFill(['is_active' => false])->save();
    }

    protected function archiveProduct(): void
    {
        $this->forceFill(['is_active' => false])->save();

        if (! $this->trashed()) {
            $this->delete();
        }
    }

    protected function restoreIfArchived(): void
    {
        if (! $this->trashed()) {
            return;
        }

        $this->newQueryWithoutScopes()
            ->whereKey($this->getKey())
            ->update([
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

        $this->deleted_at = null;
        $this->exists = true;
        $this->syncOriginalAttribute('deleted_at');
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

    public function scopeAvailableOrListableOos($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($inner) {
                $inner->where('manage_stock', true)->where('stock_quantity', '>', 0);
            })->orWhere(function ($inner) {
                $inner->where('manage_stock', false)->where('in_stock', true);
            })->orWhere(function ($inner) {
                $inner->where('is_oos_listable', true)
                    ->where(function ($oos) {
                        $oos->where(function ($i) {
                            $i->where('manage_stock', true)->where('stock_quantity', '<=', 0);
                        })->orWhere(function ($i) {
                            $i->where('manage_stock', false)->where('in_stock', false);
                        });
                    });
            });
        });
    }

    public function scopeForStorefront($query, ?bool $includeOutOfStock = null)
    {
        $query->where('is_active', true);
        $query->withoutDuplicateListings();

        $includeOutOfStock ??= ! config('catalog.hide_out_of_stock', true);

        if ($includeOutOfStock) {
            if (config('catalog.deprioritize_out_of_stock', true)) {
                $query->orderByRaw(
                    'CASE WHEN (manage_stock = 1 AND stock_quantity > 0) OR (manage_stock = 0 AND in_stock = 1) THEN 0 ELSE 1 END'
                );
            }
        } elseif (config('catalog.list_eligible_out_of_stock', true)) {
            $query->availableOrListableOos();
            if (config('catalog.deprioritize_out_of_stock', true)) {
                $query->orderByRaw(
                    'CASE WHEN (manage_stock = 1 AND stock_quantity > 0) OR (manage_stock = 0 AND in_stock = 1) THEN 0 ELSE 1 END'
                );
            }
        } else {
            $query->availableInStock();
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
            if ($request?->boolean('include_out_of_stock')) {
                if (config('catalog.deprioritize_out_of_stock', true)) {
                    $query->orderByRaw(
                        'CASE WHEN (manage_stock = 1 AND stock_quantity > 0) OR (manage_stock = 0 AND in_stock = 1) THEN 0 ELSE 1 END'
                    );
                }
            } elseif (config('catalog.list_eligible_out_of_stock', true)) {
                $query->availableOrListableOos();
                if (config('catalog.deprioritize_out_of_stock', true)) {
                    $query->orderByRaw(
                        'CASE WHEN (manage_stock = 1 AND stock_quantity > 0) OR (manage_stock = 0 AND in_stock = 1) THEN 0 ELSE 1 END'
                    );
                }
            } else {
                $query->availableInStock();
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
        $max = (int) config('google-merchant.title_max_length', 150);

        if (config('google-merchant.title_format', 'clean') === 'legacy') {
            $parts = array_filter([$this->brand, $this->sku, $this->name]);

            return Str::limit(implode(' ', $parts), $max, '');
        }

        // Clean format: "Brand Name SKU" without repeating the brand or SKU when the name already has them.
        // _BIN_ duplicate SKUs are never shown in the title.
        $name = self::fixMojibake(trim((string) $this->name));
        $brand = trim((string) $this->brand);
        $sku = trim((string) $this->sku);
        $norm = static fn (string $v): string => preg_replace('/[^a-z0-9]+/', '', mb_strtolower($v)) ?? '';

        $parts = [];
        if ($brand !== '' && preg_match('/^'.preg_quote(mb_strtolower($brand), '/').'(?![a-z0-9])/u', mb_strtolower($name)) !== 1) {
            $parts[] = $brand;
        }
        if ($name !== '') {
            $parts[] = $name;
        }
        if ($sku !== '' && ! str_contains(strtoupper($sku), '_BIN_') && ($norm($sku) === '' || ! str_contains($norm($name), $norm($sku)))) {
            $parts[] = $sku;
        }

        return Str::limit(trim(implode(' ', $parts)), $max, '');
    }

    /**
     * Repairs text that was saved as UTF-8 read as Windows-1252 (e.g. "Â°C" -> "°C", "â€“" -> "–").
     * Leaves the text unchanged unless the repaired version is valid UTF-8 and the markers are gone.
     */
    public static function fixMojibake(string $text): string
    {
        if ($text === '' || preg_match('/Ã.|Â|â€/u', $text) !== 1) {
            return $text;
        }

        $fixed = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        if (is_string($fixed) && $fixed !== '' && mb_check_encoding($fixed, 'UTF-8') && preg_match('/Ã.|Â|â€/u', $fixed) !== 1) {
            return $fixed;
        }

        return strtr($text, ['Â°' => '°', 'Â±' => '±', 'Â®' => '®', 'Â©' => '©', 'Âµ' => 'µ', 'Â ' => ' ', 'â€“' => '–', 'â€”' => '—', 'â€™' => '’', 'â€˜' => '‘', 'â€œ' => '“', 'â€¢' => '•', 'â„¢' => '™']);
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

        if ($this->isAvailable()) {
            return 'In Stock';
        }

        if (class_exists(\App\Services\CatalogOosPolicy::class)) {
            return \App\Services\CatalogOosPolicy::customerLabel();
        }

        return 'Out of Stock';
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

        if ($faqs === []) {
            return [];
        }

        $faqs = app(InternalPricingCopySanitizer::class)->sanitizeFaqs($faqs);

        return array_values(array_filter($faqs, function (array $faq): bool {
            $text = $faq['question'].' '.$faq['answer'];

            return preg_match('/google shopping|google merchant|structured data|ai search overview|image alt text/iu', $text) !== 1;
        }));
    }

    /**
     * Optional related SKUs stored in specifications (not shown on the spec table).
     *
     * @return list<string>
     */
    public function relatedSkuList(): array
    {
        $raw = trim((string) (($this->specifications ?? [])['Related SKUs'] ?? ''));
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
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

    /**
     * Google requires availability_date for backorder and preorder items.
     * Date = today + lead time: GOOGLE_MERCHANT_BACKORDER_DAYS if set, otherwise the larger of the
     * availability key's days (config/specialist.php) and the product's delivery_days, else the default.
     * Format: ISO 8601 (YYYY-MM-DDThh:mm+hhmm). Returns null for any other availability.
     */
    public function googleFeedAvailabilityDate(): ?string
    {
        if (! in_array($this->googleFeedAvailability(), ['backorder', 'preorder'], true)) {
            return null;
        }

        $override = config('google-merchant.backorder_lead_days');
        if ($override !== null && $override !== '' && (int) $override > 0) {
            $days = (int) $override;
        } else {
            $meta = $this->availabilityMeta() ?? [];
            $days = max((int) ($meta['days'] ?? 0), (int) ($this->delivery_days ?? 0));
            if ($days <= 0) {
                $days = (int) config('google-merchant.backorder_default_days', 21);
            }
        }

        $days = max(1, min($days, 360));

        return now()->startOfDay()->addDays($days)->format('Y-m-d\TH:iO');
    }

    public function googleFeedDescription(): string
    {
        $sanitizer = app(InternalPricingCopySanitizer::class);
        $short = $this->plainGoogleFeedText((string) $this->short_description, $sanitizer);
        $long = $this->plainGoogleFeedText((string) $this->description, $sanitizer);

        // Prefer the long HTML description (stripped to plain text) when it is longer
        // than short_description. Otherwise keep the existing fallback: short, then
        // long, then the product name. Capped at Google's 5,000-character limit.
        if ($long !== '' && mb_strlen($long) > mb_strlen($short)) {
            $text = $long;
        } elseif ($short !== '') {
            $text = $short;
        } elseif ($long !== '') {
            $text = $long;
        } else {
            $text = $this->plainGoogleFeedText((string) $this->name, $sanitizer);
            if ($text === '') {
                $text = trim((string) $this->name);
            }
        }

        return Str::limit($text, (int) config('google-merchant.description_max_length', 5000), '');
    }

    /**
     * Strip HTML to plain text, decode entities, run the pricing-copy sanitizer,
     * repair mojibake and collapse whitespace. Used only for the Google feed.
     */
    private function plainGoogleFeedText(string $raw, InternalPricingCopySanitizer $sanitizer): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $text = preg_replace('/<\s*(br|\/p|\/div|\/li|\/ul|\/ol|\/h[1-6]|\/tr|\/dt|\/dd)\b[^>]*>/i', ' ', $raw) ?? $raw;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = $sanitizer->sanitizePlain($text);
        $text = self::fixMojibake($text);
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return $text;
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
            ->reject(fn ($image) => is_catalog_placeholder_path($image->path))
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
        return WarrantyCopy::bobShopType($this->warranty_months);
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
            'no_image' => $query->whereDoesntHave('images', function ($q) {
                $q->whereNotNull('path')
                    ->where('path', '!=', '')
                    ->where('path', 'not like', '%placeholder%')
                    ->where('path', 'not like', '%coming-soon%')
                    ->where('path', 'not like', '%no-image%')
                    ->where('path', 'not like', '%noimage%');
            }),
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

    /** @return array<string, string> */
    public static function healthIssueLabels(): array
    {
        return [
            'missing_image' => 'Missing image',
            'broken_image' => 'Broken image',
            'placeholder_image' => 'Placeholder image',
            'missing_description' => 'Missing description',
            'missing_sku' => 'Missing SKU',
            'missing_price' => 'Missing price',
            'missing_brand' => 'Missing brand',
            'missing_title' => 'Missing title',
            'duplicate_sku' => 'Duplicate SKU',
        ];
    }

    public function scopeHealthIssue($query, string $issue)
    {
        $query->where('is_active', true);

        return match ($issue) {
            'missing_image' => $query->merchantIssue('no_image'),
            'broken_image' => $query->whereIn('id', app(\App\Services\CatalogMediaHealthService::class)->lastIssueProductIds('broken_image') ?: [0]),
            'placeholder_image' => $query->whereIn('id', app(\App\Services\CatalogMediaHealthService::class)->lastIssueProductIds('placeholder_image') ?: [0]),
            'missing_description' => $query->merchantIssue('no_description'),
            'missing_sku' => $query->where(function ($q) {
                $q->whereNull('sku')->orWhere('sku', '');
            }),
            'missing_price' => $query->merchantIssue('no_price'),
            'missing_brand' => $query->merchantIssue('no_brand'),
            'missing_title' => $query->where(function ($q) {
                $q->whereNull('name')->orWhere('name', '');
            }),
            'duplicate_sku' => $query->where(function ($q) {
                $keys = Product::query()
                    ->where('is_active', true)
                    ->whereNotNull('sku')
                    ->where('sku', '!=', '')
                    ->selectRaw('LOWER(TRIM(sku)) as sku_key')
                    ->groupByRaw('LOWER(TRIM(sku))')
                    ->havingRaw('COUNT(*) > 1')
                    ->pluck('sku_key');

                if ($keys->isEmpty()) {
                    $q->whereRaw('1 = 0');

                    return;
                }

                foreach ($keys as $index => $key) {
                    $index === 0
                        ? $q->whereRaw('LOWER(TRIM(sku)) = ?', [$key])
                        : $q->orWhereRaw('LOWER(TRIM(sku)) = ?', [$key]);
                }
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
            if ($this->availabilityKey() === 'available_on_order') {
                return 'Ships on confirmed supplier availability';
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
        return WarrantyCopy::label($this->warranty_months);
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

        unset(
            $specs['Urban Focus range'],
            $specs['Sales focus'],
            $specs['Supply'],
            $specs['Availability key'],
            $specs['Listing photo'],
            $specs['Related SKUs'],
            $specs['ICASA note'],
            $specs['Supplier image approval'],
            $specs['Internal notes']
        );

        for ($i = 1; $i <= 6; $i++) {
            unset($specs["FAQ {$i} question"], $specs["FAQ {$i} answer"]);
        }

        return WarrantyCopy::sanitizeSpecs($specs, $this->warranty_months);
    }

    /**
     * Customer-facing use cases shown on the product page.
     *
     * @return list<string>
     */
    public function buyerFitLines(): array
    {
        $html = (string) $this->storefrontDescriptionHtml();
        if ($html !== '' && preg_match('/(?:suitable for|ideal applications)\s*<\/h[1-6]>\s*<ul>(.*?)<\/ul>/is', $html, $match)) {
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
        $properties = WarrantyCopy::withSchemaProperty($properties, $this->warranty_months);
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
