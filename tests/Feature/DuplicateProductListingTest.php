<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\CatalogDeduper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateProductListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_wishlist_and_compare_routes_are_removed(): void
    {
        $this->get('/wishlist')->assertNotFound();
        $this->get('/compare')->assertNotFound();
    }

    public function test_storefront_hides_duplicate_title_without_sku(): void
    {
        $older = $this->hdmiProduct('hdmi-port-socket-ps5-older');
        $newer = $this->hdmiProduct('hdmi-port-socket-ps5-newer');

        $this->assertSame([$older->id], app(CatalogDeduper::class)->idsToHide());

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($newer->name, false)
            ->assertSee('/product/hdmi-port-socket-ps5-newer', false)
            ->assertDontSee('/product/hdmi-port-socket-ps5-older', false);

        $this->get(route('shop.index', ['q' => 'HDMI Port Socket']))
            ->assertOk()
            ->assertSee($newer->name, false)
            ->assertSee('/product/hdmi-port-socket-ps5-newer', false)
            ->assertDontSee('/product/hdmi-port-socket-ps5-older', false);
    }

    public function test_duplicate_product_page_redirects_to_canonical(): void
    {
        $older = $this->hdmiProduct('hdmi-port-socket-ps5-older');
        $newer = $this->hdmiProduct('hdmi-port-socket-ps5-newer');

        $this->get(route('products.show', $older))
            ->assertRedirect(route('products.show', $newer));
    }

    public function test_deactivate_duplicates_unpublishes_older_copy(): void
    {
        $older = $this->hdmiProduct('hdmi-port-socket-ps5-older');
        $newer = $this->hdmiProduct('hdmi-port-socket-ps5-newer');

        $result = app(CatalogDeduper::class)->deactivateDuplicates();

        $this->assertSame(1, $result['hidden']);
        $this->assertFalse($older->fresh()->is_active);
        $this->assertTrue($newer->fresh()->is_active);
    }

    protected function hdmiProduct(string $slug): Product
    {
        return Product::factory()->create([
            'name' => 'HDMI Port Socket (1Pieces) Compatible with PS5',
            'brand' => 'Sony Playstation 5',
            'sku' => null,
            'slug' => $slug,
        ]);
    }
}
