<?php

namespace Tests\Unit;

use App\Services\CatalogFilterService;
use App\Services\ProductImportService;
use App\Services\ProductPricingService;
use Tests\TestCase;

class ProductImportServiceTest extends TestCase
{
    private ProductImportService $import;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'catalog.it_category_heads' => ['Computer Memory', 'Networking-Active'],
            'catalog.it_category_exceptions' => ['Cable Clips'],
            'catalog.excluded_category_terms' => ['men shaver', 'pencil case'],
            'catalog.excluded_product_terms' => [],
            'pricing.markup_percent' => 40,
            'pricing.round_to' => 50,
            'pricing.round_mode' => 'up',
        ]);

        $this->import = new ProductImportService(
            app(\App\Services\ImageService::class),
            new ProductPricingService,
            new CatalogFilterService,
        );
    }

    public function test_accepts_valid_it_row_with_image_and_price(): void
    {
        $result = $this->import->evaluateRow([
            'name' => 'Kingston 32GB DDR5 RAM',
            'category_head' => 'Computer Memory',
            'category' => 'DDR5',
            'categories' => 'Computer Memory > DDR5',
            'images' => 'https://example.com/ram.jpg',
            'regular_price' => '100',
        ]);

        $this->assertSame('create', $result['action']);
        $this->assertSame(100.0, $result['cost_price']);
        $this->assertSame(150.0, $result['retail_price']);
    }

    public function test_skips_non_it_category_head(): void
    {
        $result = $this->import->evaluateRow([
            'name' => 'Men Shaver Pro',
            'category_head' => 'Personal Care',
            'images' => 'https://example.com/shaver.jpg',
            'regular_price' => '50',
        ]);

        $this->assertSame('skip', $result['action']);
        $this->assertSame('non_it', $result['reason']);
    }

    public function test_skips_row_without_image_url(): void
    {
        $result = $this->import->evaluateRow([
            'name' => 'Network Switch',
            'category_head' => 'Networking-Active',
            'regular_price' => '200',
            'images' => '',
        ]);

        $this->assertSame('skip', $result['action']);
        $this->assertSame('no_image', $result['reason']);
    }

    public function test_skips_row_without_cost_price(): void
    {
        $result = $this->import->evaluateRow([
            'name' => 'Network Switch',
            'category_head' => 'Networking-Active',
            'images' => 'https://example.com/switch.jpg',
            'regular_price' => '0',
        ]);

        $this->assertSame('skip', $result['action']);
        $this->assertSame('no_price', $result['reason']);
    }

    public function test_uses_cost_price_column_when_regular_price_empty(): void
    {
        $result = $this->import->evaluateRow([
            'name' => 'Patch Cable',
            'category_head' => 'Networking-Active',
            'images' => 'https://example.com/cable.jpg',
            'cost_price' => '75',
        ]);

        $this->assertSame('create', $result['action']);
        $this->assertSame(75.0, $result['cost_price']);
        $this->assertSame(150.0, $result['retail_price']);
    }
}
