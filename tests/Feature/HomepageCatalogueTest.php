<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\CategoryMapperService;
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
        Brand::create([
            'name' => 'Cambium Networks',
            'slug' => 'cambium-networks',
            'logo' => 'images/brands/cambium-networks.svg',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('brand-logo-card--sophos', false)
            ->assertSee('brand-logo-card--lenovo', false)
            ->assertSee('brand-logo-card--cambium-networks', false)
            ->assertSee('images/brands/sophos.svg', false)
            ->assertSee('images/brands/lenovo.svg', false)
            ->assertSee('images/brands/cambium-networks.svg', false);

        $css = file_get_contents(public_path('css/app.css'));
        $this->assertNotFalse($css);
        $this->assertStringContainsString('min-width: 120px', $css);
        $this->assertStringContainsString('min-height: 56px', $css);
        $this->assertStringContainsString('.brand-logo-card--sophos img', $css);
        $this->assertStringContainsString('.brand-logo-card--lenovo img', $css);
        $this->assertStringContainsString('.brand-logo-card--cambium-networks img', $css);

        $sophos = file_get_contents(public_path('images/brands/sophos.svg'));
        $lenovo = file_get_contents(public_path('images/brands/lenovo.svg'));
        $cambium = file_get_contents(public_path('images/brands/cambium-networks.svg'));
        $this->assertNotFalse($sophos);
        $this->assertNotFalse($lenovo);
        $this->assertNotFalse($cambium);
        $this->assertMatchesRegularExpression('/scale\(0\.[7-9]/', $sophos);
        $this->assertMatchesRegularExpression('/scale\([3-9]\./', $lenovo);
        $this->assertMatchesRegularExpression('/scale\(0\.[5-9]/', $cambium);
        $this->assertStringNotContainsString('M0 0h192', $sophos);
        $this->assertStringNotContainsString('Layer_1', $cambium);
        $this->assertLessThan(4000, strlen($cambium));
    }

    public function test_homepage_product_rows_each_show_eight_products(): void
    {
        Cache::flush();
        $this->seedBalancedHomepageCatalogue();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame(8, $this->productCardsInSection($html, 'Top Selling in South Africa'));
        $this->assertSame(8, $this->productCardsInSection($html, 'Specialist Technology'));
        $this->assertSame(8, $this->productCardsInSection($html, 'Top Sellers'));
        $this->assertSame(8, $this->productCardsInSection($html, 'Business Laptops'));
        $this->assertSame(8, $this->productCardsInSection($html, 'Networking Solutions'));
        $this->assertSame(8, $this->productCardsInSection($html, 'CCTV & Security'));
        $this->assertSame(1, substr_count($html, 'id="heroCarousel"'), 'Homepage header should not repeat inside product rows.');
        $this->assertStringContainsString('Shop Specialist Tech', $html);
        $this->assertStringContainsString('Hardware Security Keys', $html);
    }

    protected function seedBalancedHomepageCatalogue(): void
    {
        app(CategoryMapperService::class)->ensureCanonicalTree();

        foreach ([
            ['Ubiquiti', 'ubiquiti'],
            ['MikroTik', 'mikrotik'],
            ['TP-Link', 'tp-link'],
            ['Dell', 'dell'],
            ['HP', 'hp'],
            ['Lenovo', 'lenovo'],
            ['Hikvision', 'hikvision'],
            ['Dahua', 'dahua'],
            ['Nitrokey', 'nitrokey'],
        ] as [$name, $slug]) {
            Brand::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_active' => true]
            );
        }

        $networking = Category::query()->where('slug', 'networking-connectivity')->whereNull('parent_id')->firstOrFail();
        $computing = Category::query()->where('slug', 'computing-office')->whereNull('parent_id')->firstOrFail();
        $laptops = Category::query()->where('slug', 'laptops')->where('parent_id', $computing->id)->firstOrFail();
        $storage = Category::query()->where('slug', 'storage-devices')->where('parent_id', $computing->id)->firstOrFail();
        $security = Category::query()->where('slug', 'security-surveillance')->whereNull('parent_id')->firstOrFail();
        $specialist = Category::query()->where('slug', 'specialist-technology')->whereNull('parent_id')->firstOrFail();

        foreach (range(1, 12) as $i) {
            Product::factory()->create([
                'name' => "UniFi Switch Ultra {$i}",
                'brand' => 'Ubiquiti',
                'sku' => "NET-SW-{$i}",
                'category_id' => $networking->id,
            ]);
            Product::factory()->create([
                'name' => "Dell Latitude 544{$i}",
                'brand' => 'Dell',
                'sku' => "LAP-DELL-{$i}",
                'category_id' => $laptops->id,
            ]);
            Product::factory()->create([
                'name' => "Hikvision Dome Camera {$i}",
                'brand' => 'Hikvision',
                'sku' => "CCTV-HK-{$i}",
                'category_id' => $security->id,
            ]);
            Product::factory()->create([
                'name' => "NAS Storage Unit {$i}",
                'brand' => 'ASUS',
                'sku' => "STOR-{$i}",
                'category_id' => $storage->id,
            ]);
            Product::factory()->create([
                'name' => "Nitrokey Passkey FIDO2 {$i}",
                'brand' => 'Nitrokey',
                'sku' => "UF-NK-{$i}",
                'category_id' => $specialist->id,
                'is_featured' => $i <= 4,
            ]);
        }
    }

    protected function productCardsInSection(string $html, string $title): int
    {
        $heading = '<h2 class="section-title mb-0">'.$title.'</h2>';
        $encoded = '<h2 class="section-title mb-0">'.e($title).'</h2>';
        $start = strpos($html, $heading);
        if ($start === false) {
            $start = strpos($html, $encoded);
        }
        $this->assertNotFalse($start, "Missing homepage section: {$title}");

        $nextHeadings = [
            'Top Selling in South Africa',
            'Specialist Technology',
            'Top Sellers',
            'Business Laptops',
            'Networking Solutions',
            'CCTV & Security',
            'Featured Products',
            'Trusted by Businesses Across South Africa',
        ];

        $end = strlen($html);
        foreach ($nextHeadings as $next) {
            if ($next === $title) {
                continue;
            }

            foreach ([$next, e($next)] as $needle) {
                $headingHtml = '<h2 class="section-title mb-0">'.$needle.'</h2>';
                $pos = strpos($html, $headingHtml, $start + 1);
                if ($pos !== false && $pos < $end) {
                    $end = $pos;
                }
            }
        }

        return substr_count(substr($html, $start, $end - $start), 'class="product-card h-100"');
    }
}
