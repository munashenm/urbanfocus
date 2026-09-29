<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FixPoeUsbcProvenanceService
{
    public const PRODUCT_ID = 10873;

    public const SKU = 'POE-USBC';

    /**
     * @return array{
     *     updated: bool,
     *     product_id: int|null,
     *     sku: string|null,
     *     brand: string|null,
     *     category: string|null,
     *     images_before: int,
     *     images_after: int,
     *     price: string|null,
     *     message: string
     * }
     */
    public function run(bool $dryRun = false): array
    {
        $product = Product::withTrashed()
            ->with(['images', 'category.parent'])
            ->where(function ($query) {
                $query->where('id', self::PRODUCT_ID)
                    ->orWhere('sku', self::SKU);
            })
            ->first();

        if (! $product) {
            return [
                'updated' => false,
                'product_id' => null,
                'sku' => null,
                'brand' => null,
                'category' => null,
                'images_before' => 0,
                'images_after' => 0,
                'price' => null,
                'message' => 'Product 10873 / SKU POE-USBC was not found.',
            ];
        }

        if ((int) $product->id !== self::PRODUCT_ID && strtoupper((string) $product->sku) !== self::SKU) {
            return [
                'updated' => false,
                'product_id' => (int) $product->id,
                'sku' => $product->sku,
                'brand' => $product->brand,
                'category' => $product->category?->name,
                'images_before' => $product->images->count(),
                'images_after' => $product->images->count(),
                'price' => (string) $product->price,
                'message' => 'Refusing to update an unexpected product match.',
            ];
        }

        $category = $this->resolvePoeEquipmentCategory();
        $imagesBefore = $product->images->count();
        $description = $this->technicalDescription();
        $shortDescription = 'PROCET PoE-to-USB-C power and data adapter with 5V/2A output (model PT-PTC-D-AF).';

        $attributes = [
            'sku' => self::SKU,
            'name' => 'Procet PoE to USB-C 5V Power and Data Adapter',
            'brand' => 'PROCET',
            'model_number' => 'PT-PTC-D-AF',
            'short_description' => $shortDescription,
            'description' => $description,
            'supplier_name' => 'Scoop',
            'supplier_sku' => self::SKU,
            'supplier_product_url' => 'https://scoop.co.za/procet-poe-to-usb-c-5v-power-and-data-adapter.html',
            'supplier_image_url' => $product->supplier_image_url
                ?: 'https://scoop.co.za/media/catalog/product/p/o/poe-usbc.jpg',
            'import_source' => 'scoop',
            'source_file' => $product->source_file ?: 'scoop_pricelist.csv',
            'source_external_id' => self::SKU,
            'last_synced_at' => now(),
            'supplier_cost_price' => $product->supplier_cost_price ?: 395.00,
            // Keep retail price unchanged.
            'price' => $product->price,
        ];

        if ($category) {
            $attributes['category_id'] = $category->id;
        }

        if ($dryRun) {
            return [
                'updated' => false,
                'product_id' => (int) $product->id,
                'sku' => $product->sku,
                'brand' => $attributes['brand'],
                'category' => $category?->name ?? $product->category?->name,
                'images_before' => $imagesBefore,
                'images_after' => max(1, min(1, $imagesBefore)),
                'price' => (string) $product->price,
                'message' => 'Dry run only — no database changes were made.',
            ];
        }

        return DB::transaction(function () use ($product, $attributes, $category, $imagesBefore) {
            if ($product->trashed()) {
                $product->restore();
            }

            $product->update($attributes);
            $imagesAfter = $this->dedupeImages($product->fresh(['images']));

            return [
                'updated' => true,
                'product_id' => (int) $product->id,
                'sku' => self::SKU,
                'brand' => 'PROCET',
                'category' => $category?->name ?? $product->category?->name,
                'images_before' => $imagesBefore,
                'images_after' => $imagesAfter,
                'price' => (string) $product->fresh()->price,
                'message' => 'Updated product provenance, brand, model, description, category and images.',
            ];
        });
    }

    protected function resolvePoeEquipmentCategory(): ?Category
    {
        app(CategoryMapperService::class)->ensureCanonicalTree();

        $parent = Category::query()
            ->whereNull('parent_id')
            ->where('slug', 'networking-connectivity')
            ->first();

        if (! $parent) {
            return null;
        }

        return Category::query()
            ->where('parent_id', $parent->id)
            ->where('slug', 'poe-equipment')
            ->first();
    }

    protected function technicalDescription(): string
    {
        return <<<'HTML'
<p>PROCET POE-USBC (manufacturer model <strong>PT-PTC-D-AF</strong>) is an indoor <strong>PoE-to-USB-C power and data adapter</strong>. It converts IEEE 802.3af/at PoE (44–57V) from a PoE injector or PoE switch into USB-C output with <strong>5V/2A (10W)</strong> power and network data for compatible USB-C devices.</p>
<p>The compact IP40 white plastic housing provides an RJ45 PoE input and a USB-C output. It supports PD2.0/PD3.0 charging protocols and is designed for phones, tablets and other low-power USB-C devices that need both power and a data connection over PoE cabling up to 100 m.</p>
<ul>
<li>Input: 44–57V DC IEEE 802.3af/at PoE</li>
<li>Output: USB-C 5V/2A power and data</li>
<li>Data rate: 10/100 Mbps</li>
<li>Typical pack includes a 0.5 m USB-C cable</li>
<li>Operating temperature: −20°C to 40°C</li>
<li>Dimensions: 65 × 29 × 16.2 mm · Weight: approx. 26 g</li>
</ul>
<p>Supplied via Scoop distribution. Urban Focus lists the Scoop stock code as <strong>POE-USBC</strong>.</p>
HTML;
    }

    protected function dedupeImages(Product $product): int
    {
        $images = $product->images()->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')->get();
        if ($images->count() <= 1) {
            if ($images->count() === 1 && ! $images->first()->is_primary) {
                $images->first()->update(['is_primary' => true, 'sort_order' => 1]);
            }

            return $images->count();
        }

        $keep = $images->first();
        $keep->update([
            'is_primary' => true,
            'sort_order' => 1,
            'alt_text' => $product->name,
        ]);

        foreach ($images->skip(1) as $image) {
            $this->deleteImageFile($image);
            $image->delete();
        }

        return 1;
    }

    protected function deleteImageFile(ProductImage $image): void
    {
        $path = trim((string) $image->path);
        if ($path === '') {
            return;
        }

        foreach (['public', 'local'] as $disk) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            } catch (\Throwable) {
                // Best-effort cleanup only.
            }
        }
    }
}
