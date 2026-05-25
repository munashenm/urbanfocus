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
            'catalog.pinnacle_category_roots' => ['computing', 'security', 'networking'],
            'catalog.excluded_pinnacle_roots' => ['on-promo'],
            'pricing.markup_percent' => 40,
            'pricing.round_to' => 50,
            'pricing.round_mode' => 'up',
            'pricing.low_cost_threshold' => 20,
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

    public function test_accepts_pinnacle_computing_row(): void
    {
        $data = $this->import->normalizeImportRow([
            'sku' => 'SDCZ50-032G-B35',
            'name' => 'Sandisk Cruzer Blade 32GB USB-A Flash Drive',
            'brand' => 'Sandisk',
            'category_tree' => 'computing/storage/flash',
            'images' => 'https://www.pinnacle.co.za/media/catalog/product/usb.jpg',
            'regular_price' => '138.45',
            'stock' => '3274',
            'barcode' => '619659069193',
            'top_cat' => 'FLASH DRIVE',
            'highlight_feature_1_option' => 'Storage Capacity',
            'highlight_feature_1_value' => '32GB',
        ]);

        $this->assertSame('Computing > Storage > Flash', $data['categories']);
        $this->assertSame('pinnacle', $data['import_source']);
        $this->assertStringContainsString('32GB', $data['short_description']);

        $result = $this->import->evaluateRow($data);

        $this->assertSame('create', $result['action']);
        $this->assertSame(138.45, $result['cost_price']);
        $this->assertSame(200.0, $result['retail_price']);
    }

    public function test_skips_pinnacle_on_promo_root(): void
    {
        $data = $this->import->normalizeImportRow([
            'name' => 'Promo Bundle',
            'category_tree' => 'on-promo/specials',
            'images' => 'https://example.com/promo.jpg',
            'regular_price' => '100',
        ]);

        $result = $this->import->evaluateRow($data);

        $this->assertSame('skip', $result['action']);
        $this->assertSame('non_it', $result['reason']);
    }

    public function test_accepts_esquire_data_export_row(): void
    {
        config(['catalog.it_category_heads' => ['Cables & Adapters']]);

        $data = $this->import->normalizeImportRow([
            'name' => 'DC CONNECTOR MALE',
            'sku' => '="DCW"',
            'category_head' => 'Cables & Adapters ',
            'category' => 'Cable: Power',
            'images' => 'https://api.esquire.co.za/Resources/Images/Products/Big_images-(1).jfif.png',
            'regular_price' => '4.000045',
            'stock' => '106',
            'brand' => 'Securnix',
            'short_description' => 'DC Male Connector Cable',
        ]);

        $this->assertSame('DCW', $data['sku']);
        $this->assertSame('Cables & Adapters > Cable: Power', $data['categories']);

        $result = $this->import->evaluateRow($data);

        $this->assertSame('create', $result['action']);
        $this->assertSame(4.0, $result['cost_price']);
        $this->assertSame(5.6, $result['retail_price']);
    }
}
