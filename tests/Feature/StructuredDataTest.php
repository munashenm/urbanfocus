<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\CategoryMapperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_collection_json_ld_is_parseable(): void
    {
        app(CategoryMapperService::class)->ensureCanonicalTree();

        $parent = Category::query()->where('slug', 'networking-connectivity')->firstOrFail();
        $category = Category::query()
            ->where('slug', 'network-cables')
            ->where('parent_id', $parent->id)
            ->firstOrFail();

        Product::factory()->create([
            'name' => 'Cat6 Patch Cable 1m',
            'slug' => 'cat6-patch-cable-1m',
            'category_id' => $category->id,
        ]);

        $html = $this->get($category->url())->assertOk()->getContent();
        $blocks = $this->jsonLdBlocks($html);

        $this->assertNotEmpty($blocks);

        $collection = $this->firstSchemaOfType($blocks, 'CollectionPage');
        $this->assertIsArray($collection);
        $this->assertSame('Network Cables', $collection['name']);
        $this->assertIsString($collection['name']);
        $this->assertIsString($collection['url']);
        $this->assertSame('ItemList', $collection['mainEntity']['@type'] ?? null);
        $this->assertIsInt($collection['mainEntity']['numberOfItems']);
        $this->assertIsInt($collection['mainEntity']['itemListElement'][0]['position']);
        $this->assertIsString($collection['mainEntity']['itemListElement'][0]['url']);
    }

    public function test_product_json_ld_includes_offer_brand_and_zar_price(): void
    {
        $product = Product::factory()->create([
            'name' => 'UniFi Switch Ultra',
            'slug' => 'unifi-switch-ultra-schema',
            'sku' => 'USW-Ultra',
            'brand' => 'Ubiquiti',
            'price' => 3499,
            'specifications' => ['PoE' => 'Yes', 'Ports' => '8'],
        ]);

        $html = $this->get(route('products.show', $product))->assertOk()->getContent();
        $productSchema = $this->firstSchemaOfType($this->jsonLdBlocks($html), 'Product');

        $this->assertIsArray($productSchema);
        $this->assertSame('UniFi Switch Ultra', $productSchema['name']);
        $this->assertSame('USW-Ultra', $productSchema['sku']);
        $this->assertSame('Brand', $productSchema['brand']['@type'] ?? null);
        $this->assertSame('Ubiquiti', $productSchema['brand']['name'] ?? null);
        $this->assertSame('Offer', $productSchema['offers']['@type'] ?? null);
        $this->assertSame('ZAR', $productSchema['offers']['priceCurrency'] ?? null);
        $this->assertSame('3499.00', $productSchema['offers']['price'] ?? null);
        $this->assertNotEmpty($productSchema['offers']['availability'] ?? null);
        $this->assertSame('https://schema.org/NewCondition', $productSchema['offers']['itemCondition'] ?? null);
        $this->assertNotEmpty($productSchema['additionalProperty'] ?? []);
    }

    public function test_shop_collection_json_ld_does_not_put_item_count_on_collection_page(): void
    {
        Product::factory()->create([
            'name' => 'UniFi Switch Ultra',
            'slug' => 'unifi-switch-ultra',
        ]);

        $html = $this->get(route('shop.index'))->assertOk()->getContent();
        $collection = $this->firstSchemaOfType($this->jsonLdBlocks($html), 'CollectionPage');

        $this->assertIsArray($collection);
        $this->assertSame('Shop IT Products', $collection['name']);
        $this->assertArrayNotHasKey('numberOfItems', $collection);
        $this->assertSame('ItemList', $collection['mainEntity']['@type'] ?? null);
        $this->assertIsInt($collection['mainEntity']['numberOfItems']);
    }

    /** @return list<array<string, mixed>> */
    protected function jsonLdBlocks(string $html): array
    {
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

        $blocks = [];
        foreach ($matches[1] as $raw) {
            $this->assertStringNotContainsString('&quot;', $raw, 'JSON-LD must not be HTML-escaped.');
            $decoded = json_decode($raw, true);
            $this->assertIsArray($decoded, json_last_error_msg().': '.$raw);
            $blocks[] = $decoded;
        }

        return $blocks;
    }

    /** @param list<array<string, mixed>> $blocks */
    protected function firstSchemaOfType(array $blocks, string $type): ?array
    {
        foreach ($blocks as $block) {
            if (($block['@type'] ?? null) === $type) {
                return $block;
            }
        }

        return null;
    }
}
