<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\CatalogFilterService;
use App\Services\CategoryMapperService;
use App\Services\ProductImportService;
use App\Services\ProductPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImportServiceTest extends TestCase
{
    use RefreshDatabase;
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
            new CategoryMapperService,
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
        $this->assertSame('Peripherals & Accessories > Cables & Adapters', $data['categories']);

        $result = $this->import->evaluateRow($data);

        $this->assertSame('create', $result['action']);
        $this->assertSame(4.0, $result['cost_price']);
        $this->assertSame(5.6, $result['retail_price']);
    }

    public function test_astrum_row_without_image_is_accepted_when_placeholder_enabled(): void
    {
        config(['catalog.import_placeholder_image' => true, 'pricing.astrum_retail_from' => 'price']);

        $data = $this->import->normalizeImportRow([
            'sku' => 'AMP180GB',
            'name' => 'Mouse Pad with Rubber Base - MP180G (BLACK)',
            'regular_price' => '19',
            'srp_price' => '29',
            'stock' => '100',
            'category' => 'Cooling and Mousepad',
        ]);

        $this->assertSame('astrum', $data['import_source']);

        $result = $this->import->evaluateRow($data);

        $this->assertSame('create', $result['action']);
        $this->assertSame(19.0, $result['retail_price']);
    }

    public function test_astrum_import_attaches_placeholder_image(): void
    {
        config(['catalog.import_placeholder_image' => true, 'pricing.astrum_retail_from' => 'price']);

        $csv = tempnam(sys_get_temp_dir(), 'astrum_import_');
        file_put_contents($csv, "sku,name,price,stock,category\nTEST-PH,Astrum Placeholder Test,99,5,Mouse\n");

        $result = $this->import->importFromPath($csv);
        @unlink($csv);

        $this->assertSame(1, $result['imported']);

        $product = Product::where('sku', 'TEST-PH')->first();
        $this->assertNotNull($product);
        $this->assertTrue($product->images()->exists());
        $this->assertStringEndsWith('.svg', $product->images()->first()->path);
    }

    public function test_astrum_uses_price_column_as_retail_without_markup(): void
    {
        config(['pricing.astrum_retail_from' => 'price']);

        $data = $this->import->normalizeImportRow([
            'sku' => 'A92020-B',
            'name' => 'Smart Wireless Charging Pad 7.5W (Black / Black)',
            'regular_price' => '399',
            'srp_price' => '299',
            'stock' => '0',
            'category' => 'Mobile Chargers',
            'images' => 'https://example.com/charger.jpg',
        ]);

        $result = $this->import->evaluateRow($data);

        $this->assertSame('create', $result['action']);
        $this->assertSame(0.0, $result['cost_price']);
        $this->assertSame(399.0, $result['retail_price']);
    }

    public function test_astrum_pricelist_row_detects_part_no_headers(): void
    {
        config(['pricing.astrum_retail_from' => 'price']);

        $data = $this->import->normalizeImportRow([
            'sku' => 'AS128GX',
            'name' => '2.5" 128GB SSD',
            'model_number' => 'S128GX',
            'regular_price' => '499',
            'srp_price' => '499',
            'category' => 'USB Peripherals',
            'images' => 'https://example.com/ssd.jpg',
            'warranty' => '36 Months',
        ]);

        $this->assertSame('astrum', $data['import_source']);

        $result = $this->import->evaluateRow($data);

        $this->assertSame('create', $result['action']);
        $this->assertSame(499.0, $result['retail_price']);
    }

    public function test_accepts_scoop_pricelist_row_with_image_and_markup(): void
    {
        $data = $this->import->normalizeImportRow([
            'sku' => 'ACB-ISP',
            'description' => 'Ubiquiti UISP airCube ISP WiFi Access Point',
            'cost_price' => '475',
            'list_price' => '650',
            'stock' => '331',
            'brand' => 'Ubiquiti',
            'images' => 'https://scoop.co.za/download/marketing/images/ACB-ISP.jpg',
        ]);

        $this->assertSame('scoop', $data['import_source']);
        $this->assertSame('Ubiquiti UISP airCube ISP WiFi Access Point', $data['name']);
        $this->assertSame('Networking > Wireless Access Points', $data['categories']);

        $result = $this->import->evaluateRow($data);

        $this->assertSame('create', $result['action']);
        $this->assertSame(546.25, $result['cost_price']);
        $this->assertSame(800.0, $result['retail_price']);
    }

    public function test_skips_scoop_row_without_image(): void
    {
        $data = $this->import->normalizeImportRow([
            'sku' => 'ACB-ISP',
            'description' => 'Ubiquiti UISP airCube ISP WiFi Access Point',
            'cost_price' => '475',
            'stock' => '331',
            'brand' => 'Ubiquiti',
            'images' => '',
        ]);

        $this->assertNotSame('scoop', $data['import_source'] ?? null);

        $data['import_source'] = 'scoop';
        $data['name'] = 'Ubiquiti UISP airCube ISP WiFi Access Point';

        $result = $this->import->evaluateRow($data);

        $this->assertSame('skip', $result['action']);
        $this->assertSame('no_image', $result['reason']);
    }

    public function test_scoop_import_skips_non_it_filters(): void
    {
        config(['catalog.excluded_product_terms' => ['torch', 'flashlight']]);

        $data = $this->import->normalizeImportRow([
            'sku' => 'LED-TORCH',
            'description' => 'Ubiquiti LED Torch Flashlight Accessory',
            'cost_price' => '99',
            'stock' => '10',
            'brand' => 'Ubiquiti',
            'images' => 'https://scoop.co.za/download/marketing/images/LED-TORCH.jpg',
        ]);

        $this->assertSame('scoop', $data['import_source']);

        $result = $this->import->evaluateRow($data);

        $this->assertSame('create', $result['action']);
    }

    public function test_resolve_import_slug_avoids_soft_deleted_slug_collision(): void
    {
        $product = Product::create([
            'name' => 'Manhattan iPad 2 Silicon Slip',
            'slug' => 'manhattan-ipad-2-silicon-slip',
            'sku' => '450256',
            'price' => 150,
        ]);
        $product->delete();

        $method = new \ReflectionMethod($this->import, 'resolveImportSlug');
        $method->setAccessible(true);

        $slug = $method->invoke($this->import, 'Manhattan iPad 2 Silicon Slip', '450287', null);

        $this->assertSame('manhattan-ipad-2-silicon-slip-450287', $slug);
    }
}
