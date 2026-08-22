<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\ProductPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitivePricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pricing.payment_fee_percent' => 0]);
    }

    public function test_reprices_dell_pro_15_from_legacy_forty_percent(): void
    {
        $product = Product::factory()->create([
            'name' => 'Dell Pro 15 Essential Intel Core i7 Professional',
            'brand' => 'Dell',
            'sku' => 'PV15250-RPLU-007-P',
            'slug' => 'dell-pro-15-essential-intel-core-i7-professional',
            'cost_price' => 24950,
            'price' => 24950,
        ]);

        $pricing = new ProductPricingService;
        $this->assertTrue($pricing->applyToProduct($product));

        $product->refresh();
        $this->assertEqualsWithDelta(17821.43, (float) $product->cost_price, 0.02);
        $this->assertSame(19250.0, (float) $product->price);
    }

    public function test_does_not_raise_price_when_real_cost_is_already_stored(): void
    {
        $product = Product::factory()->create([
            'name' => 'Dell Pro 15 Essential Intel Core i7 Professional',
            'brand' => 'Dell',
            'sku' => 'PV15250-RPLU-007-P',
            'cost_price' => 17821.43,
            'price' => 24950,
        ]);

        $pricing = new ProductPricingService;
        $pricing->applyToProduct($product->fresh());

        $this->assertSame(19250.0, (float) $product->fresh()->price);
        $this->assertSame(17821.43, (float) $product->fresh()->cost_price);
    }
}
