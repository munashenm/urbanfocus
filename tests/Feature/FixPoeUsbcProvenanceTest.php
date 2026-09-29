<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\CategoryMapperService;
use App\Services\FixPoeUsbcProvenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixPoeUsbcProvenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_only_poe_usbc_product_provenance_and_dedupes_images(): void
    {
        app(CategoryMapperService::class)->ensureCanonicalTree();

        $product = new Product([
            'sku' => 'POE-USBC',
            'name' => 'Procet PoE to USB-C 5V Power and Data Adapter',
            'slug' => 'procet-poe-to-usb-c-5v-power-and-data-adapter',
            'brand' => 'Scoop',
            'price' => 650,
            'cost_price' => 454.25,
            'short_description' => 'Procet PoE to USB-C 5V Power and Data Adapter',
            'description' => 'Procet PoE to USB-C 5V Power and Data Adapter',
            'is_active' => true,
            'in_stock' => true,
            'stock_quantity' => 10,
        ]);
        $product->id = FixPoeUsbcProvenanceService::PRODUCT_ID;
        $product->save();

        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/10873/primary.webp',
            'alt_text' => $product->name,
            'sort_order' => 1,
            'is_primary' => true,
        ]);
        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/10873/duplicate.webp',
            'alt_text' => $product->name,
            'sort_order' => 2,
            'is_primary' => false,
        ]);

        $other = Product::query()->create([
            'sku' => 'POE-24V15W',
            'name' => 'Procet Gigabit 24V 15W PoE Adapter with No Cable',
            'slug' => 'procet-gigabit-24v-15w-poe-adapter-with-no-cable',
            'brand' => 'Scoop',
            'price' => 200,
            'is_active' => true,
        ]);

        $result = app(FixPoeUsbcProvenanceService::class)->run(false);

        $this->assertTrue($result['updated']);
        $this->assertSame(1, $result['images_after']);

        $fresh = $product->fresh(['images', 'category.parent']);
        $this->assertSame('PROCET', $fresh->brand);
        $this->assertSame('PT-PTC-D-AF', $fresh->model_number);
        $this->assertSame('Scoop', $fresh->supplier_name);
        $this->assertSame('POE-USBC', $fresh->supplier_sku);
        $this->assertSame('scoop', $fresh->import_source);
        $this->assertSame('650.00', (string) $fresh->price);
        $this->assertSame('poe-equipment', $fresh->category?->slug);
        $this->assertSame('networking-connectivity', $fresh->category?->parent?->slug);
        $this->assertStringContainsString('5V/2A', $fresh->description);
        $this->assertStringContainsString('PoE-to-USB-C', $fresh->description);
        $this->assertCount(1, $fresh->images);
        $this->assertTrue((bool) $fresh->images->first()->is_primary);

        $untouched = $other->fresh();
        $this->assertSame('Scoop', $untouched->brand);
        $this->assertNull($untouched->supplier_name);
    }
}
