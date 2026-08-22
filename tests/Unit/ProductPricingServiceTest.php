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
        $this->assertSame(27.99, $this->pricing->retailPrice(19.99));
    }

    public function test_scoop_import_adds_vat_before_markup_and_rounding(): void
    {
        config(['app.vat_rate' => 15, 'pricing.scoop_prices_ex_vat' => true]);

        $this->assertSame(546.25, $this->pricing->importCostPrice(475.0, 'scoop'));
        $this->assertSame(800.0, $this->pricing->retailPriceForImport(475.0, 'scoop'));
    }

    public function test_dell_pro_essential_uses_street_margin_not_forty_percent(): void
    {
        $price = $this->pricing->retailPrice(17821.43, null, [
            'name' => 'Dell Pro 15 Essential Intel Core i7 Professional',
            'brand' => 'Dell',
        ]);

        $this->assertSame(19250.0, $price);
        $this->assertSame(8.0, $this->pricing->markupPercentFor(17821.43, null, [
            'name' => 'Dell Pro 15 Essential Intel Core i7 Professional',
            'brand' => 'Dell',
        ]));
    }

    public function test_high_ticket_dell_without_laptop_in_title_still_uses_eight_percent(): void
    {
        $this->assertSame(8.0, $this->pricing->markupPercentFor(17821.43, null, [
            'name' => 'Dell PV15250 Core i7 Professional',
            'brand' => 'Dell',
        ]));
    }

    public function test_dell_accessory_keeps_higher_markup(): void
    {
        $this->assertSame(28.0, $this->pricing->markupPercentFor(200, null, [
            'name' => 'Dell laptop charger 65W',
            'brand' => 'Dell',
        ]));
        $this->assertSame(40.0, $this->pricing->markupPercentFor(150, null, [
            'name' => 'Dell MS116 Mouse',
            'brand' => 'Dell',
        ]));
    }

    public function test_laptop_category_path_uses_eight_percent(): void
    {
        $this->assertSame(8.0, $this->pricing->markupPercentFor(10000, null, [
            'name' => 'Business notebook',
            'category_path' => 'computing-office/laptops',
        ]));
    }
}
