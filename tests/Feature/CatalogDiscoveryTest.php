<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_recommends_business_it_ahead_of_newest_imports(): void
    {
        $hdmi = Product::factory()->create([
            'name' => 'HDMI Port Socket (1Pieces) Compatible with PS5',
            'brand' => 'Sony Playstation 5',
            'sku' => 'HDMI-PS5-1',
            'slug' => 'hdmi-port-socket-ps5',
            'created_at' => now(),
        ]);
        $switch = Product::factory()->create([
            'name' => 'Ubiquiti UniFi Switch Ultra',
            'brand' => 'Ubiquiti',
            'sku' => 'USW-U60',
            'slug' => 'unifi-switch-ultra',
            'created_at' => now()->subDay(),
        ]);

        $html = $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('Recommended', false)
            ->assertSee($switch->name, false)
            ->assertSee($hdmi->name, false)
            ->getContent();

        $this->assertLessThan(
            strpos($html, $hdmi->name),
            strpos($html, $switch->name),
            'Business IT should appear before leftover HDMI imports on the default shop page.'
        );
    }

    public function test_search_keeps_sku_matches_ahead_of_newer_unrelated_imports(): void
    {
        Product::factory()->create([
            'name' => 'HDMI Port Socket (1Pieces) Compatible with PS5',
            'brand' => 'Sony Playstation 5',
            'sku' => 'HDMI-PS5-1',
            'slug' => 'hdmi-port-socket-ps5',
            'created_at' => now(),
        ]);
        $switch = Product::factory()->create([
            'name' => 'Ubiquiti UniFi Switch 16 PoE',
            'brand' => 'Ubiquiti',
            'sku' => 'USW-16P',
            'slug' => 'unifi-switch-16-poe',
            'created_at' => now()->subDays(10),
        ]);

        $this->get(route('shop.index', ['q' => 'USW 16P']))
            ->assertOk()
            ->assertSee($switch->name, false)
            ->assertDontSee('HDMI Port Socket', false);
    }

    public function test_search_for_unifi_switch_does_not_sort_by_newest(): void
    {
        $olderSwitch = Product::factory()->create([
            'name' => 'Ubiquiti UniFi Switch Ultra',
            'brand' => 'Ubiquiti',
            'sku' => 'USW-U60',
            'slug' => 'unifi-switch-ultra',
            'created_at' => now()->subMonth(),
        ]);
        $newerCable = Product::factory()->create([
            'name' => 'Cat6 Patch Cable 1m',
            'brand' => 'Generic',
            'sku' => 'CABLE-1M',
            'slug' => 'cat6-patch-cable-1m',
            'created_at' => now(),
        ]);

        $html = $this->get(route('shop.index', ['q' => 'unifi switch']))
            ->assertOk()
            ->assertSee($olderSwitch->name, false)
            ->assertDontSee($newerCable->name, false)
            ->getContent();

        $this->assertStringContainsString('Best match', $html);
    }

    public function test_category_page_uses_recommended_sort(): void
    {
        $networking = Category::query()->create([
            'name' => 'Networking-Active',
            'slug' => 'networking-active',
            'is_active' => true,
        ]);

        $hdmi = Product::factory()->create([
            'name' => 'HDMI Port Socket Compatible with PS5',
            'sku' => 'HDMI-NET-1',
            'slug' => 'hdmi-port-socket-net',
            'category_id' => $networking->id,
            'created_at' => now(),
        ]);
        $switch = Product::factory()->create([
            'name' => 'Ubiquiti UniFi Switch 24',
            'sku' => 'USW-24',
            'slug' => 'unifi-switch-24',
            'category_id' => $networking->id,
            'created_at' => now()->subDay(),
        ]);

        $html = $this->get($networking->url())
            ->assertOk()
            ->assertSee($switch->name, false)
            ->assertSee($hdmi->name, false)
            ->getContent();

        $this->assertLessThan(
            strpos($html, $hdmi->name),
            strpos($html, $switch->name)
        );
    }
}
