<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\SeoService;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class SeoServiceTest extends TestCase
{
    public function test_collection_page_schema_uses_string_name_and_url(): void
    {
        $schema = app(SeoService::class)->collectionPageSchema(
            'Network Cables',
            'https://www.urbanfocus.co.za/category/networking-connectivity/network-cables',
            'Browse Network Cables at Urban Focus',
        );

        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        $decoded = json_decode((string) $json, true);

        $this->assertIsArray($decoded);
        $this->assertSame('CollectionPage', $decoded['@type']);
        $this->assertSame('Network Cables', $decoded['name']);
        $this->assertIsString($decoded['name']);
        $this->assertSame(
            'https://www.urbanfocus.co.za/category/networking-connectivity/network-cables',
            $decoded['url']
        );
        $this->assertArrayNotHasKey('numberOfItems', $decoded);
        $this->assertArrayNotHasKey('mainEntity', $decoded);
    }

    public function test_collection_page_schema_puts_item_count_on_item_list(): void
    {
        $product = new Product(['name' => 'Cat6 Patch Cable', 'slug' => 'cat6-patch-cable']);
        $product->id = 1;

        $paginator = new LengthAwarePaginator(
            [$product],
            48,
            24,
            1,
            ['path' => '/category/networking-connectivity/network-cables']
        );

        $schema = app(SeoService::class)->collectionPageSchema(
            'Network Cables',
            'https://www.urbanfocus.co.za/category/networking-connectivity/network-cables',
            'Browse Network Cables at Urban Focus',
            $paginator,
        );

        $this->assertSame('ItemList', $schema['mainEntity']['@type']);
        $this->assertSame(1, $schema['mainEntity']['numberOfItems']);
        $this->assertSame(1, $schema['mainEntity']['itemListElement'][0]['position']);
        $this->assertIsString($schema['mainEntity']['itemListElement'][0]['url']);
        $this->assertArrayNotHasKey('numberOfItems', $schema);
    }
}
