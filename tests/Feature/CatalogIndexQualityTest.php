<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogIntegrityService;
use App\Services\CategoryMapperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogIndexQualityTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_filter_and_shop_pagination_are_noindexed(): void
    {
        $this->get('/shop?filter_cat=12')
            ->assertOk()
            ->assertSee('name="robots" content="noindex, follow', false)
            ->assertHeader('X-Robots-Tag', 'noindex, follow, max-image-preview:large');

        $this->get('/shop?min_price=100&max_price=500')
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);

        $this->get('/?etheme-product-grid=1&page=2')
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);

        $this->get('/shop?page=2')
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);

        $this->get('/?page=2')
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);

        $this->get('/shop?q=USW-16P')
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);
    }

    public function test_category_pagination_stays_indexable_while_filters_do_not(): void
    {
        app(CategoryMapperService::class)->ensureCanonicalTree();
        $parent = Category::query()->where('slug', 'computing-office')->whereNull('parent_id')->firstOrFail();
        $laptops = Category::query()->where('slug', 'laptops')->where('parent_id', $parent->id)->firstOrFail();

        Product::factory()->count(3)->create(['category_id' => $laptops->id]);

        $pageTwo = $this->get($laptops->url().'?page=2')->assertOk();
        $pageTwo->assertDontSee('name="robots" content="noindex', false);
        $pageTwo->assertSee('rel="canonical"', false);
        $pageTwo->assertSee('page=2', false);

        $this->get($laptops->url().'?brand=Dell&filter_brand=Dell')
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);
    }

    public function test_product_pages_stay_indexable_with_schema_and_sitemap_entry(): void
    {
        $products = Product::factory()->count(5)->create();

        foreach ($products as $product) {
            $html = $this->get(route('products.show', $product))->assertOk()->getContent();
            $this->assertStringNotContainsString('name="robots" content="noindex', $html);
            $this->assertStringContainsString('rel="canonical"', $html);
            $this->assertMatchesRegularExpression('/<h1[^>]*>'.preg_quote($product->name, '/').'/', $html);
            $this->assertMatchesRegularExpression('/"@type":\s*"Product"/', $html);
            $this->assertMatchesRegularExpression('/"@type":\s*"Offer"/', $html);
            $this->assertMatchesRegularExpression('/"priceCurrency":\s*"ZAR"/', $html);
            $this->assertMatchesRegularExpression('/"@type":\s*"BreadcrumbList"/', $html);
            $this->assertStringContainsString((string) $product->sku, $html);
        }

        $xml = $this->get('/sitemap-products.xml')->assertOk()->getContent();
        $this->assertStringContainsString(route('products.show', $products->first()), $xml);

        $parameterised = $this->get(route('products.show', $products->first()).'?filter_brand=Dell&sort=price_asc')
            ->assertOk();
        $parameterised->assertDontSee('name="robots" content="noindex', false);
        $parameterised->assertSee('rel="canonical"', false);
        $parameterised->assertSee(route('products.show', $products->first()), false);
    }

    public function test_cart_checkout_and_account_remain_noindex(): void
    {
        $this->get(route('cart.index'))->assertOk()->assertSee('noindex, nofollow', false);
        $this->get(route('checkout.index'))->assertRedirect();
        $this->get(route('login'))->assertOk()->assertSee('noindex, nofollow', false);
    }

    public function test_robots_txt_blocks_legacy_filter_params_but_not_category_paths(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Disallow: /*?filter_cat=', $body);
        $this->assertStringContainsString('Disallow: /*?min_price=', $body);
        $this->assertStringContainsString('Disallow: /shop?page=', $body);
        $this->assertStringNotContainsString('Disallow: /category', $body);
        $this->assertStringNotContainsString('Disallow: /product', $body);
    }

    public function test_high_confidence_taxonomy_moves_chargers_and_boards(): void
    {
        app(CategoryMapperService::class)->ensureCanonicalTree();
        $computing = Category::query()->where('slug', 'computing-office')->whereNull('parent_id')->firstOrFail();
        $laptops = Category::query()->where('slug', 'laptops')->where('parent_id', $computing->id)->firstOrFail();
        $monitors = Category::query()->where('slug', 'monitors')->where('parent_id', $computing->id)->firstOrFail();
        $accessories = Category::query()->where('slug', 'computer-accessories')->where('parent_id', $computing->id)->firstOrFail();
        $signage = Category::query()->where('slug', 'digital-signage')->whereNull('parent_id')->firstOrFail();
        $boards = Category::query()->where('slug', 'interactive-displays')->where('parent_id', $signage->id)->firstOrFail();

        $charger = Product::factory()->create([
            'name' => 'Dell 65W Laptop Charger',
            'sku' => 'DELL-65W-CHG',
            'category_id' => $laptops->id,
        ]);
        $bag = Product::factory()->create([
            'name' => 'HP Travel Grey 15.6 Backpack',
            'sku' => '7J597AA',
            'slug' => 'hp-travel-grey-156-backpack',
            'category_id' => $laptops->id,
        ]);
        $board = Product::factory()->create([
            'name' => 'ViewSonic 65" Interactive Smart Board',
            'sku' => 'IFP6550',
            'category_id' => $monitors->id,
        ]);

        $result = app(CatalogIntegrityService::class)->applyHighConfidenceTaxonomyFixes();
        $this->assertGreaterThanOrEqual(3, $result['fixed']);

        $this->assertSame($accessories->id, $charger->fresh()->category_id);
        $this->assertSame($accessories->id, $bag->fresh()->category_id);
        $this->assertSame($boards->id, $board->fresh()->category_id);
    }

    public function test_usw_16p_sku_name_conflicts_are_reported_not_silently_renamed(): void
    {
        Product::factory()->create([
            'name' => 'Ubiquiti UniFi Switch 16 PoE',
            'sku' => 'USW-16P',
            'slug' => 'unifi-switch-16-poe',
            'brand' => 'Ubiquiti',
        ]);
        Product::factory()->create([
            'name' => 'HDMI Port Socket Compatible with PS5',
            'sku' => 'USW-16P',
            'slug' => 'hdmi-port-socket-usw',
            'brand' => 'Generic',
        ]);

        $report = app(CatalogIntegrityService::class)->audit();
        $this->assertNotEmpty($report['sku_name_conflicts']);
        $this->assertGreaterThanOrEqual(2, count($report['usw_16p']['rows']));
        $this->assertSame('HDMI Port Socket Compatible with PS5', Product::query()->where('slug', 'hdmi-port-socket-usw')->value('name'));
    }
}
