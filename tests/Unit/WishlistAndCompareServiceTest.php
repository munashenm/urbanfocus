<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\CompareService;
use App\Services\WishlistService;
use Tests\TestCase;

class WishlistAndCompareServiceTest extends TestCase
{
    private WishlistService $wishlist;

    private CompareService $compare;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wishlist = app(WishlistService::class);
        $this->compare = app(CompareService::class);
        $this->wishlist->clear();
        $this->compare->clear();
    }

    public function test_wishlist_adds_toggles_and_counts_unique_ids(): void
    {
        $this->assertTrue($this->wishlist->add(10));
        $this->assertTrue($this->wishlist->add(10));
        $this->assertTrue($this->wishlist->add(11));

        $this->assertSame(2, $this->wishlist->count());
        $this->assertTrue($this->wishlist->has(10));
        $this->assertSame([10, 11], $this->wishlist->ids());

        $this->assertFalse($this->wishlist->toggle(10));
        $this->assertFalse($this->wishlist->has(10));
        $this->assertTrue($this->wishlist->toggle(12));
        $this->assertSame([11, 12], $this->wishlist->ids());
    }

    public function test_wishlist_respects_capacity(): void
    {
        for ($id = 1; $id <= WishlistService::MAX_ITEMS; $id++) {
            $this->assertTrue($this->wishlist->add($id));
        }

        $this->assertFalse($this->wishlist->add(WishlistService::MAX_ITEMS + 1));
        $this->assertSame(WishlistService::MAX_ITEMS, $this->wishlist->count());
    }

    public function test_compare_caps_at_four_products(): void
    {
        $this->assertTrue($this->compare->add(1));
        $this->assertTrue($this->compare->add(2));
        $this->assertTrue($this->compare->add(3));
        $this->assertTrue($this->compare->add(4));
        $this->assertFalse($this->compare->add(5));
        $this->assertSame(4, $this->compare->count());
        $this->assertSame(0, $this->compare->remaining());

        $this->assertFalse($this->compare->toggle(2));
        $this->assertTrue($this->compare->add(5));
        $this->assertSame([1, 3, 4, 5], $this->compare->ids());
    }

    public function test_discount_percent_rounds_sale_saving(): void
    {
        $product = new Product([
            'price' => 2000,
            'sale_price' => 1500,
        ]);

        $this->assertSame(25, $product->discountPercent());

        $fullPrice = new Product([
            'price' => 1999,
            'sale_price' => null,
        ]);

        $this->assertNull($fullPrice->discountPercent());
    }

    public function test_whatsapp_url_normalises_south_african_numbers(): void
    {
        config(['business.whatsapp' => '087 550 1813']);

        $this->assertSame(
            'https://wa.me/27875501813?text='.rawurlencode('Hello'),
            whatsapp_url('Hello')
        );
    }
}
