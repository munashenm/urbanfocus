<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogFilterService;
use App\Services\CategoryMapperService;
use App\Services\SpecialistCatalogService;
use App\Services\SpecialistListingCopy;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialistCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'social-posting.enabled' => false,
            'catalog.specialist_path' => base_path('tests/fixtures/specialist-sample.json'),
        ]);
    }

    public function test_creates_missing_specialist_products_with_merchant_and_seo_fields(): void
    {
        $catalog = app(SpecialistCatalogService::class);
        $result = $catalog->sync();

        $this->assertSame(5, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(0, $result['errors']);

        $key = Product::where('sku', 'UF-NK-PASSKEY')->first();
        $this->assertNotNull($key);
        $this->assertSame($catalog->retailStreetPrice(['street_price' => 580]), (float) $key->price);
        $this->assertFalse($key->manage_stock);
        $this->assertTrue($key->in_stock);
        $this->assertSame('Nitrokey', $key->brand);
        $this->assertSame('NK-PASSKEY', $key->model_number);
        $this->assertNotEmpty($key->google_product_category);
        $this->assertTrue($key->images()->exists());
        $this->assertStringContainsString('South Africa', (string) $key->images()->first()?->alt_text);
        $this->assertSame(
            SpecialistCatalogService::CATALOG_RANGE_SPEC_VALUE,
            $key->specifications[SpecialistCatalogService::CATALOG_RANGE_SPEC_KEY] ?? null
        );
        $this->assertSame('eu_stock', $key->availabilityKey());
        $this->assertSame('EU STOCK – 5–10 BUSINESS DAYS', $key->availabilityLabel());
        $this->assertFalse($key->isQuoteOnly());
        $this->assertSame('in_stock', $key->googleFeedAvailability());
        $this->assertNotEmpty($key->listingFaqs());
        $this->assertNotNull($key->faqSchemaArray());
        $this->assertStringContainsString('South Africa', (string) $key->meta_title);
        $this->assertLessThanOrEqual(70, mb_strlen((string) $key->meta_title));
        $this->assertNotEmpty($key->meta_keywords);
        $this->assertLessThanOrEqual(255, mb_strlen((string) $key->meta_keywords));
        $this->assertStringContainsString('<h3>Advantages</h3>', (string) $key->description);
        $this->assertStringContainsString('<h3>Key specifications</h3>', (string) $key->description);
    }

    public function test_quote_and_special_order_availability_labels(): void
    {
        app(SpecialistCatalogService::class)->sync();

        $licence = Product::where('sku', 'UF-PX-VE')->firstOrFail();
        $this->assertSame('contact_licensing', $licence->availabilityKey());
        $this->assertTrue($licence->isQuoteOnly());
        $this->assertSame('CONTACT US FOR LICENSING', $licence->availabilityLabel());
        $this->assertSame('in_stock', $licence->googleFeedAvailability());

        $quote = Product::where('sku', 'UF-SOL-PWLESS')->firstOrFail();
        $this->assertSame('request_quote', $quote->availabilityKey());
        $this->assertTrue($quote->isQuoteOnly());
        $this->assertSame('REQUEST A QUOTE', $quote->availabilityLabel());

        $phone = Product::where('sku', 'UF-FP-GEN6')->firstOrFail();
        $this->assertSame('special_order_eu', $phone->availabilityKey());
        $this->assertFalse($phone->isQuoteOnly());
        $this->assertSame('SPECIAL ORDER – EUROPE', $phone->availabilityLabel());
        $this->assertSame('backorder', $phone->googleFeedAvailability());
    }

    public function test_skips_existing_sku_and_does_not_duplicate(): void
    {
        Product::factory()->create([
            'sku' => 'UF-NK-PASSKEY',
            'name' => 'Existing Nitrokey listing',
            'slug' => 'existing-nitrokey-passkey',
            'brand' => 'Nitrokey',
        ]);

        $result = app(SpecialistCatalogService::class)->sync();

        $this->assertSame(4, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, Product::where('sku', 'UF-NK-PASSKEY')->count());
        $this->assertStringContainsString('Key specifications', (string) Product::where('sku', 'UF-NK-PASSKEY')->first()?->description);
    }

    public function test_attaches_photos_when_web_root_is_separate_from_laravel_public(): void
    {
        $emptyPublic = sys_get_temp_dir().'/uf-specialist-public-'.uniqid();
        mkdir($emptyPublic.'/images', 0755, true);
        $this->app->usePublicPath($emptyPublic);

        $result = app(SpecialistCatalogService::class)->sync();
        $product = Product::where('sku', 'UF-NK-PASSKEY')->first();

        $this->assertSame(5, $result['created']);
        $this->assertGreaterThan(0, $result['imaged']);
        $this->assertNotNull($product);
        $this->assertTrue($product->images()->exists());
        $this->assertFileExists($emptyPublic.'/images/specialist/security-key.jpg');
    }

    public function test_dry_run_does_not_write_products(): void
    {
        $result = app(SpecialistCatalogService::class)->sync(dryRun: true);

        $this->assertSame(5, $result['created']);
        $this->assertSame(0, Product::count());
    }

    public function test_artisan_command_dry_run_reports_without_creating(): void
    {
        $this->artisan('catalog:sync-specialist', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('would be created');

        $this->assertSame(0, Product::count());
    }

    public function test_does_not_reprice_preexisting_store_skus_that_are_not_ours(): void
    {
        $product = Product::factory()->create([
            'sku' => 'UF-NK-PASSKEY',
            'name' => 'Existing Nitrokey listing',
            'slug' => 'existing-nitrokey-passkey',
            'brand' => 'Nitrokey',
            'price' => 1999,
        ]);

        $result = app(SpecialistCatalogService::class)->sync();

        $this->assertSame(4, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1999.0, (float) $product->fresh()->price);
        $this->assertStringContainsString('Key specifications', (string) $product->fresh()->description);
    }

    public function test_admin_catalog_page_loads_with_specialist_card(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $admin->syncRoles(['super-admin']);

        $this->actingAs($admin)
            ->get(route('admin.catalog.index'))
            ->assertOk()
            ->assertSee('Add specialist technology products', false)
            ->assertSee('Add specialist products', false);
    }

    public function test_admin_can_preview_and_add_specialist_without_artisan(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $admin->syncRoles(['super-admin']);

        $this->actingAs($admin)
            ->post(route('admin.catalog.sync-specialist-preview'))
            ->assertRedirect();

        $this->assertSame(0, Product::count());

        $this->actingAs($admin)
            ->from(route('admin.catalog.index'))
            ->post(route('admin.catalog.sync-specialist'))
            ->assertRedirect(route('admin.catalog.index'))
            ->assertSessionHas('success');

        $this->assertSame(5, Product::count());
        $this->assertNotNull(Product::where('sku', 'UF-NK-PASSKEY')->first());
    }

    public function test_description_layout_is_spec_sheet_and_south_africa_focused(): void
    {
        $html = app(SpecialistListingCopy::class)->descriptionHtml([
            'sku' => 'UF-NK-3C-NFC',
            'name' => 'Nitrokey 3C NFC USB-C FIDO2 OpenPGP Security Key',
            'brand' => 'Nitrokey',
            'category_path' => 'specialist-technology/hardware-security-keys',
            'family' => 'fido-key',
            'availability' => 'eu_stock',
            'short_description' => 'USB-C plus NFC FIDO2 key for South African Microsoft 365 MFA.',
            'specs' => ['Interface' => 'USB-C + NFC'],
        ]);

        $this->assertStringContainsString('<h3>Advantages</h3>', $html);
        $this->assertStringContainsString('<h3>Suitable for</h3>', $html);
        $this->assertStringContainsString('<h3>Key specifications</h3>', $html);
        $this->assertStringContainsString('<h3>South African supply</h3>', $html);
        $this->assertStringContainsString('Nitrokey 3C NFC', $html);
        $this->assertStringContainsString('Johannesburg', $html);
    }

    public function test_canonical_specialist_categories_are_visible_in_catalog(): void
    {
        app(CategoryMapperService::class)->ensureCanonicalTree();

        $root = Category::query()->where('slug', 'specialist-technology')->whereNull('parent_id')->first();
        $child = Category::query()->where('slug', 'hardware-security-keys')->first();

        $this->assertNotNull($root);
        $this->assertNotNull($child);

        $filter = app(CatalogFilterService::class);
        $this->assertFalse($filter->isCategoryExcluded($root));
        $this->assertFalse($filter->isCategoryExcluded($child));
        $this->assertTrue($filter->isCanonicalTreeRoot($root));
    }

    public function test_listing_copy_fits_mysql_varchar_columns(): void
    {
        config(['catalog.specialist_path' => database_path('data/specialist-products.php')]);

        $copy = app(SpecialistListingCopy::class);

        foreach (app(SpecialistCatalogService::class)->items() as $item) {
            $this->assertLessThanOrEqual(70, mb_strlen($copy->metaTitle($item)), $item['sku']);
            $this->assertLessThanOrEqual(160, mb_strlen($copy->metaDescription($item)), $item['sku']);
            $this->assertLessThanOrEqual(255, mb_strlen($copy->metaKeywords($item)), $item['sku']);
            $this->assertNotSame('', $copy->metaKeywords($item), $item['sku']);
        }
    }

    public function test_product_truncates_oversized_meta_keywords_before_save(): void
    {
        $product = Product::factory()->create([
            'meta_keywords' => str_repeat('nitrokey, fido2, south africa, ', 20),
        ]);

        $this->assertLessThanOrEqual(255, mb_strlen((string) $product->meta_keywords));
        $this->assertLessThanOrEqual(255, mb_strlen((string) $product->fresh()->meta_keywords));
    }

    public function test_full_specialist_catalog_has_unique_skus_and_valid_categories(): void
    {
        config(['catalog.specialist_path' => database_path('data/specialist-products.php')]);

        $items = app(SpecialistCatalogService::class)->items();
        $this->assertGreaterThanOrEqual(250, count($items));

        $skus = array_map(fn (array $item) => $item['sku'], $items);
        $this->assertSame(count($skus), count(array_unique($skus)));

        $allowed = $this->allowedCategoryPaths();
        $availabilities = array_keys(config('specialist.availability'));

        foreach ($items as $item) {
            $this->assertContains($item['category_path'], $allowed, $item['sku']);
            $this->assertContains($item['availability'], $availabilities, $item['sku']);
            $this->assertNotEmpty($item['image_key'], $item['sku']);
            $this->assertGreaterThan(0, (float) $item['street_price'], $item['sku']);
            $this->assertNotEmpty($item['brand'], $item['sku']);
            $this->assertNotEmpty($item['short_description'], $item['sku']);
            $this->assertStringContainsString('South Africa', $item['short_description'], $item['sku']);

            $html = app(SpecialistListingCopy::class)->descriptionHtml($item);
            $this->assertStringContainsString('<h3>Advantages</h3>', $html, $item['sku']);
            $this->assertStringContainsString('<h3>Key specifications</h3>', $html, $item['sku']);
            $this->assertGreaterThan(300, strlen(strip_tags($html)), $item['sku']);
        }
    }

    /** @return list<string> */
    protected function allowedCategoryPaths(): array
    {
        $paths = [];

        foreach (config('category_tree.tree', []) as $parent) {
            $paths[] = $parent['slug'];
            foreach ($parent['children'] ?? [] as $child) {
                $paths[] = $parent['slug'].'/'.$child['slug'];
            }
        }

        return $paths;
    }
}
