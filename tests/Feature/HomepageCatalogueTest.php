<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HomepageCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_popular_sa_categories_instead_of_daily_deals(): void
    {
        Product::factory()->create([
            'name' => 'UniFi Switch Ultra',
            'is_deal' => true,
            'price' => 4500,
            'sale_price' => 3950,
        ]);
        Product::factory()->create([
            'name' => 'Dell Latitude 5440',
            'brand' => 'Dell',
            'is_featured' => false,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Daily Deals', false)
            ->assertDontSee('Limited-time specials', false)
            ->assertDontSee('All Deals', false)
            ->assertDontSee('Current Range', false)
            ->assertSee('Top Selling in South Africa', false)
            ->assertSee('UniFi Switch Ultra', false)
            ->assertSee('Dell Latitude 5440', false);
    }

    public function test_homepage_hides_hdmi_and_helmet_accessories(): void
    {
        Product::factory()->create([
            'name' => 'HDMI Port Socket (1Pieces) Compatible with PS5',
            'brand' => 'Sony Playstation 5',
            'sku' => null,
            'slug' => 'hdmi-port-socket-ps5',
        ]);
        Product::factory()->create([
            'name' => 'UrbanFocus SmartGuard BWC-P3 4G Helmet Camera',
            'brand' => 'Urban Focus',
            'sku' => 'UF-BWC-P3-4G-HELMET',
            'slug' => 'smartguard-helmet-camera',
        ]);
        Product::factory()->create([
            'name' => 'UniFi Switch Ultra',
            'brand' => 'Ubiquiti',
            'slug' => 'unifi-switch-ultra',
        ]);
        Product::factory()->create([
            'name' => 'Dell Latitude 5440',
            'brand' => 'Dell',
            'slug' => 'dell-latitude-5440',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Top Selling in South Africa', false)
            ->assertSee('UniFi Switch Ultra', false)
            ->assertSee('Dell Latitude 5440', false)
            ->assertDontSee('HDMI Port Socket', false)
            ->assertDontSee('Helmet Camera', false)
            ->assertDontSee('Current Range', false);
    }

    public function test_homepage_hides_sparse_featured_leftovers(): void
    {
        Product::factory()->create([
            'name' => 'Outdoor Telecom Cabinet',
            'is_featured' => true,
            'is_deal' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Featured Products', false)
            ->assertSee('Top Selling in South Africa', false)
            ->assertSee('Outdoor Telecom Cabinet', false);
    }

    public function test_homepage_featured_brands_enlarge_small_wordmarks(): void
    {
        Cache::flush();

        Brand::create([
            'name' => 'Sophos',
            'slug' => 'sophos',
            'logo' => 'images/brands/sophos.svg',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        Brand::create([
            'name' => 'Lenovo',
            'slug' => 'lenovo',
            'logo' => 'images/brands/lenovo.svg',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('brand-logo-card--sophos', false)
            ->assertSee('brand-logo-card--lenovo', false)
            ->assertSee('images/brands/sophos.svg', false)
            ->assertSee('images/brands/lenovo.svg', false);

        $css = file_get_contents(public_path('css/app.css'));
        $this->assertNotFalse($css);
        $this->assertStringContainsString('min-width: 120px', $css);
        $this->assertStringContainsString('min-height: 56px', $css);
        $this->assertStringContainsString('.brand-logo-card--sophos img', $css);
        $this->assertStringContainsString('.brand-logo-card--lenovo img', $css);

        $sophos = file_get_contents(public_path('images/brands/sophos.svg'));
        $lenovo = file_get_contents(public_path('images/brands/lenovo.svg'));
        $this->assertNotFalse($sophos);
        $this->assertNotFalse($lenovo);
        $this->assertMatchesRegularExpression('/scale\(0\.[7-9]/', $sophos);
        $this->assertMatchesRegularExpression('/scale\([3-9]\./', $lenovo);
        $this->assertStringNotContainsString('M0 0h192', $sophos);
    }
}
