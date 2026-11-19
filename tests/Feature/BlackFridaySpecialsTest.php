<?php

namespace Tests\Feature;

use App\Console\Commands\SyncEsquireStock;
use App\Models\Product;
use App\Models\Setting;
use App\Services\BlackFridaySpecialService;
use App\Services\ProductPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlackFridaySpecialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_specials_apply_below_the_current_price_and_leave_a_genuine_was_price(): void
    {
        $gpu = Product::factory()->create([
            'sku' => 'RTX3070TIGAMINGXTRIO8G',
            'name' => 'MSI Nvidia GeForce RTX 3070 Ti GAMING X TRIO 8G',
            'price' => 23250,
            'sale_price' => null,
            'supplier_name' => 'Esquire',
        ]);
        $nas = Product::factory()->create([
            'sku' => 'AS6810T',
            'name' => 'Asustor Lockerstor 10 Bay Gen 3 NAS Enclosure',
            'price' => 53200,
            'sale_price' => null,
        ]);
        $result = app(BlackFridaySpecialService::class)->apply();

        $gpu->refresh();
        $nas->refresh();
        $this->assertSame('18999.00', $gpu->sale_price);
        $this->assertSame('23250.00', $gpu->price);
        $this->assertSame('Black Friday', $gpu->promo_label);
        $this->assertSame('2026-11-30 23:59:00', $gpu->sale_ends_at->timezone('Africa/Johannesburg')->format('Y-m-d H:i:s'));
        $this->assertNull($nas->sale_price);
        $this->assertSame('53200.00', $nas->price);
        $this->assertContains('RTX3070TIGAMINGXTRIO8G', $result['applied']);
        $this->assertTrue(collect($result['pending'])->contains(fn ($label) => str_contains($label, 'AS6810T')));
        $this->assertTrue(collect($result['pending'])->contains(fn ($label) => str_contains($label, 'AS6512RD')));

        $this->get(route('products.show', $gpu))
            ->assertOk()
            ->assertSee('Was R 23,250.00', false)
            ->assertSee('R 18,999.00', false);

        $this->withSession(['cart' => [$gpu->id => 1]])
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('R 18,999.00', false);

        $gpu->update(['sale_ends_at' => now()->addDay()]);
        app(BlackFridaySpecialService::class)->apply();
        $gpu->refresh();
        $this->assertTrue($gpu->sale_ends_at->lt(now()->addDays(2)));
        $this->assertNotSame('2026-11-30 23:59:00', $gpu->sale_ends_at->timezone('Africa/Johannesburg')->format('Y-m-d H:i:s'));
    }

    public function test_new_esquire_listings_have_no_was_price_and_keep_the_asustor_brand(): void
    {
        $rack = Product::factory()->create([
            'sku' => 'AS7112RDX',
            'name' => 'Asus 2U Rack Mount 12 bay AS7112RDX NAS',
            'brand' => 'Asustor',
            'price' => 84000,
            'sale_price' => null,
        ]);

        $result = app(BlackFridaySpecialService::class)->apply();

        $laptop = Product::query()->where('sku', 'UX8407AA-OU93210G1W')->first();
        $drive = Product::query()->where('sku', 'HDEB034ZSA51F')->first();
        $this->assertNotNull($laptop);
        $this->assertNotNull($drive);
        $this->assertSame('Asus', $laptop->brand);
        $this->assertSame('59999.00', $laptop->price);
        $this->assertNull($laptop->sale_price);
        $this->assertSame('computing-office', $laptop->category?->parent?->slug);
        $this->assertSame('laptops', $laptop->category?->slug);
        $this->assertTrue($laptop->images()->exists());
        $this->assertSame('Toshiba', $drive->brand);
        $this->assertSame('19999.00', $drive->price);
        $this->assertNull($drive->sale_price);
        $this->assertSame('storage-devices', $drive->category?->slug);
        $this->assertTrue($drive->images()->exists());
        $this->assertContains('UX8407AA-OU93210G1W', $result['created']);
        $this->assertContains('HDEB034ZSA51F', $result['created']);

        $this->get(route('products.show', $laptop))
            ->assertOk()
            ->assertSee('Black Friday', false)
            ->assertSee('R 59,999.00', false)
            ->assertDontSee('Was R', false);
        $this->get(route('black-friday'))
            ->assertOk()
            ->assertSee($laptop->name, false)
            ->assertSee($drive->name, false);

        $rack->refresh();
        $this->assertSame('Asustor', $rack->brand);
        $this->assertSame('66999.00', $rack->sale_price);
        $this->assertSame('84000.00', $rack->price);

        app(BlackFridaySpecialService::class)->apply();
        $this->assertSame(1, Product::query()->where('sku', 'UX8407AA-OU93210G1W')->count());
        $this->assertNull($laptop->fresh()->sale_price);
    }

    public function test_esquire_url_and_exact_name_match_existing_products(): void
    {
        $byUrl = Product::factory()->create([
            'sku' => 'CARD-1',
            'name' => 'Listed graphics card',
            'price' => 22400,
            'supplier_product_url' => 'https://www.esquire.co.za/msi-nvidia-geforce-rtx-3070-ti-ventus-3x-8g-oc/8426713',
        ]);
        $byName = Product::factory()->create([
            'sku' => 'TESTER-1',
            'name' => 'NOYAFA NF-8209 Pro Network Cable Tester',
            'price' => 2749,
        ]);

        app(BlackFridaySpecialService::class)->apply();

        $this->assertSame('18299.00', $byUrl->fresh()->sale_price);
        $this->assertSame('2499.00', $byName->fresh()->sale_price);
        $this->assertNull(Product::query()->where('sku', 'HDEB034GEA51F')->first());
    }

    public function test_pinned_specials_lead_the_sale_page_and_home_and_specials_redirects(): void
    {
        Product::factory()->create([
            'sku' => 'DEEP-1',
            'name' => 'Deep Discount Router',
            'price' => 10000,
            'sale_price' => 1000,
            'sale_ends_at' => now()->addWeek(),
            'promo_label' => 'Black Friday',
            'stock_quantity' => 5,
            'in_stock' => true,
        ]);
        $gpu = Product::factory()->create([
            'sku' => 'RTX3070TIGAMINGXTRIO8G',
            'name' => 'MSI Nvidia GeForce RTX 3070 Ti GAMING X TRIO 8G',
            'price' => 23250,
            'sale_price' => 18999,
            'sale_ends_at' => now()->addWeek(),
            'promo_label' => 'Black Friday',
            'stock_quantity' => 2,
            'in_stock' => true,
        ]);

        $page = $this->get(route('black-friday'))->assertOk()->getContent();
        $home = $this->get(route('home'))->assertOk()->getContent();
        $this->assertLessThan(strpos($page, 'Deep Discount Router'), strpos($page, $gpu->name));
        $sectionStart = strpos($home, 'bf-home-specials');
        $section = substr($home, $sectionStart, strpos($home, '</section>', $sectionStart) - $sectionStart);
        $this->assertLessThan(strpos($section, 'Deep Discount Router'), strpos($section, $gpu->name));

        $this->get('/specials')->assertRedirect('/black-friday');
    }

    public function test_esquire_sync_keeps_the_special_price_and_still_updates_other_products(): void
    {
        $dir = sys_get_temp_dir().'/uf-esquire-'.uniqid();
        mkdir($dir);
        $gpu = Product::factory()->create([
            'sku' => 'RTX3070TIGAMINGXTRIO8G',
            'name' => 'MSI Nvidia GeForce RTX 3070 Ti GAMING X TRIO 8G',
            'price' => 23250,
            'sale_price' => null,
            'cost_price' => 100,
            'stock_quantity' => 10,
            'in_stock' => true,
            'supplier_name' => 'Esquire',
            'brand' => 'MSI',
        ]);
        $plain = Product::factory()->create([
            'sku' => 'PLAIN-SKU',
            'name' => 'Plain Esquire Item',
            'price' => 1000,
            'sale_price' => 9000,
            'cost_price' => 100,
            'stock_quantity' => 10,
            'in_stock' => true,
            'supplier_name' => 'Esquire',
            'brand' => 'Other',
        ]);
        app(BlackFridaySpecialService::class)->apply();

        $xml = <<<'XML'
<?xml version="1.0"?>
<Catalogue>
  <Product>
    <ProductCode>RTX3070TIGAMINGXTRIO8G</ProductCode>
    <Price>5000</Price>
    <AvailableQty>3</AvailableQty>
    <Category>Graphics Card</Category>
    <CategoryHead>Components</CategoryHead>
    <ProductAttributes><Brand>MSI</Brand></ProductAttributes>
  </Product>
  <Product>
    <ProductCode>PLAIN-SKU</ProductCode>
    <Price>5000</Price>
    <AvailableQty>6</AvailableQty>
    <Category>Cables</Category>
    <CategoryHead>Accessories</CategoryHead>
    <ProductAttributes><Brand>Other</Brand></ProductAttributes>
  </Product>
</Catalogue>
XML;
        file_put_contents($dir.'/feed.xml', $xml.'<!-- '.str_repeat('x', 100000).' -->');
        config([
            'esquire.storage_dir' => $dir,
            'esquire.price_basis' => 'cost_excl',
            'esquire.min_feed_items' => 1,
            'esquire.missing_oos' => false,
            'esquire.chunk_pause_ms' => 0,
            'esquire.sanity_ratio_min' => 0.01,
            'esquire.sanity_ratio_max' => 20,
            'esquire.feed_max_age_minutes' => 60,
        ]);

        $floor = app(SyncEsquireStock::class)->floorPrice(5000, 0.30);
        $this->artisan('esquire:sync', ['--force-prices' => true])->assertSuccessful();

        $gpu->refresh();
        $plain->refresh();
        $this->assertSame('23250.00', $gpu->price);
        $this->assertSame('18999.00', $gpu->sale_price);
        $this->assertSame(3, $gpu->stock_quantity);
        $this->assertSame('5000.00', $gpu->cost_price);
        $this->assertSame(number_format($floor, 2, '.', ''), $plain->price);
        $this->assertNull($plain->sale_price);
        $this->assertSame(6, $plain->stock_quantity);

        $pricing = new ProductPricingService;
        $this->assertFalse($pricing->applyToProduct($gpu->fresh()));
        $this->assertSame('18999.00', $gpu->fresh()->sale_price);

        Setting::set('api_key', 'test-api-key', 'api');
        $this->patchJson('/api/store/products/RTX3070TIGAMINGXTRIO8G/price', [
            'unitPriceCents' => 100,
        ], ['Authorization' => 'Bearer test-api-key'])->assertOk();
        $gpu->refresh();
        $this->assertSame('23250.00', $gpu->price);
        $this->assertSame('18999.00', $gpu->sale_price);
    }
}
