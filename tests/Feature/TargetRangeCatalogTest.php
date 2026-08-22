<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductPricingService;
use App\Services\TargetRangeCatalogService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetRangeCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'social-posting.enabled' => false,
            'catalog.target_range_path' => base_path('tests/fixtures/target-range-sample.json'),
        ]);
    }

    public function test_creates_missing_target_range_products_at_street_prices(): void
    {
        $result = app(TargetRangeCatalogService::class)->sync();

        $this->assertSame(9, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(0, $result['errors']);

        $router = Product::where('sku', 'RUTX50')->first();
        $this->assertNotNull($router);
        $this->assertSame(10400.0, (float) $router->price);
        $this->assertFalse($router->manage_stock);
        $this->assertTrue($router->in_stock);
        $this->assertSame(0, $router->stock_quantity);
        $this->assertSame('Teltonika', $router->brand);
        $this->assertTrue($router->images()->exists());
    }

    public function test_skips_existing_sku_and_does_not_duplicate(): void
    {
        Product::factory()->create([
            'sku' => 'AD3U3ET',
            'name' => 'HP EliteBook 8 G1i 16 already listed',
            'slug' => 'existing-elitebook-16',
            'brand' => 'HP',
        ]);

        $result = app(TargetRangeCatalogService::class)->sync();

        $this->assertSame(8, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, Product::where('sku', 'AD3U3ET')->count());
    }

    public function test_backfills_a_photo_when_an_existing_match_has_none(): void
    {
        $product = Product::factory()->create([
            'sku' => 'RUTX50',
            'name' => 'Teltonika RUTX50 already listed',
            'slug' => 'existing-rutx50',
            'brand' => 'Teltonika',
        ]);

        $result = app(TargetRangeCatalogService::class)->sync();

        $this->assertSame(8, $result['created']);
        $this->assertGreaterThan(0, $result['imaged']);
        $this->assertTrue($product->fresh()->images()->exists());
    }

    public function test_skips_existing_name_match_with_a_different_sku(): void
    {
        Product::factory()->create([
            'sku' => 'U7-PRO-EU',
            'name' => 'Ubiquiti UniFi U7 Pro Wi-Fi 7 AP',
            'slug' => 'existing-u7-pro',
            'brand' => 'Ubiquiti',
        ]);

        $result = app(TargetRangeCatalogService::class)->sync();

        $this->assertSame(8, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, Product::where('sku', 'U7-PRO')->count());
    }

    public function test_does_not_treat_sibling_models_as_duplicates(): void
    {
        $result = app(TargetRangeCatalogService::class)->sync();

        $this->assertSame(9, $result['created']);
        $this->assertNotNull(Product::where('sku', 'U7-PRO')->first());
        $this->assertNotNull(Product::where('sku', 'U7-PRO-MAX')->first());
        $this->assertNotNull(Product::where('sku', '21QC001HZA')->first());
        $this->assertNotNull(Product::where('sku', '21QC000YZA')->first());
        $this->assertNotNull(Product::where('sku', 'RALLY-BAR')->first());
        $this->assertNotNull(Product::where('sku', 'RALLY-BAR-MINI')->first());
    }

    public function test_dry_run_does_not_write_products(): void
    {
        $result = app(TargetRangeCatalogService::class)->sync(dryRun: true);

        $this->assertSame(9, $result['created']);
        $this->assertSame(0, Product::count());
    }

    public function test_artisan_command_dry_run_reports_without_creating(): void
    {
        $this->artisan('catalog:sync-target-range', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('would be created');

        $this->assertSame(0, Product::count());
    }

    public function test_street_price_survives_pricing_apply_after_fee_buffer(): void
    {
        app(TargetRangeCatalogService::class)->sync();

        $product = Product::where('sku', 'RUTX50')->firstOrFail();
        $pricing = new ProductPricingService;

        $this->assertTrue($pricing->applyToProduct($product->fresh()));
        $this->assertSame(10400.0, (float) $product->fresh()->price);
    }

    public function test_full_catalog_creates_one_hundred_unique_products_then_skips_on_rerun(): void
    {
        config(['catalog.target_range_path' => database_path('data/target-range-products.json')]);

        $service = app(TargetRangeCatalogService::class);
        $first = $service->sync();

        $this->assertSame(0, $first['errors']);
        $this->assertSame(100, $first['created']);
        $this->assertSame(100, Product::count());
        $this->assertSame(100, Product::query()->distinct()->count('sku'));
        $this->assertSame(100, Product::has('images')->count());

        $second = app(TargetRangeCatalogService::class)->sync();

        $this->assertSame(0, $second['created']);
        $this->assertSame(100, $second['skipped']);
        $this->assertSame(100, Product::count());
    }

    public function test_admin_can_preview_and_add_target_range_without_artisan(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $admin->syncRoles(['super-admin']);

        $this->actingAs($admin)
            ->post(route('admin.catalog.sync-target-range-preview'))
            ->assertRedirect();

        $this->assertSame(0, Product::count());

        $this->actingAs($admin)
            ->from(route('admin.catalog.index'))
            ->post(route('admin.catalog.sync-target-range'))
            ->assertRedirect(route('admin.catalog.index'))
            ->assertSessionHas('success');

        $this->assertSame(9, Product::count());
        $this->assertNotNull(Product::where('sku', 'RUTX50')->first());
    }
}
