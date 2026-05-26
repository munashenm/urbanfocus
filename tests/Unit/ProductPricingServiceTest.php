<?php

namespace Tests\Unit;

use App\Services\ProductPricingService;
use Tests\TestCase;

class ProductPricingServiceTest extends TestCase
{
    private ProductPricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pricing.markup_percent' => 40,
            'pricing.round_to' => 50,
            'pricing.round_mode' => 'up',
            'pricing.low_cost_threshold' => 20,
        ]);

        $this->pricing = new ProductPricingService;
    }

    public function test_low_cost_uses_markup_only_without_round_to_fifty(): void
    {
        $this->assertSame(5.6, $this->pricing->retailPrice(4.0));
        $this->assertSame(19.6, $this->pricing->retailPrice(14.0));
    }

    public function test_standard_cost_rounds_up_to_fifty(): void
    {
        $this->assertSame(150.0, $this->pricing->retailPrice(100.0));
        $this->assertSame(50.0, $this->pricing->retailPrice(20.0));
    }

    public function test_threshold_boundary_uses_round_to_fifty_at_twenty(): void
    {
        $this->assertSame(50.0, $this->pricing->retailPrice(20.0));
        $this->assertSame(28.0, $this->pricing->retailPrice(19.99));
    }

    public function test_scoop_import_adds_vat_before_markup_and_rounding(): void
    {
        config(['app.vat_rate' => 15, 'pricing.scoop_prices_ex_vat' => true]);

        $this->assertSame(546.25, $this->pricing->importCostPrice(475.0, 'scoop'));
        $this->assertSame(800.0, $this->pricing->retailPriceForImport(475.0, 'scoop'));
    }
}
