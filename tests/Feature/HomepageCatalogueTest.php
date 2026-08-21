<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_current_catalogue_instead_of_daily_deals(): void
    {
        Product::factory()->create([
            'name' => 'UniFi Switch Ultra',
            'is_deal' => true,
            'price' => 4500,
            'sale_price' => 3950,
        ]);
        Product::factory()->create([
            'name' => 'Dell Latitude 5440',
            'is_featured' => false,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Daily Deals', false)
            ->assertDontSee('Limited-time specials', false)
            ->assertDontSee('All Deals', false)
            ->assertSee('Current Range', false)
            ->assertSee('UniFi Switch Ultra', false)
            ->assertSee('Dell Latitude 5440', false);
    }

    public function test_homepage_hides_sparse_featured_leftovers(): void
    {
        Product::factory()->create([
            'name' => 'Outdoor Telecom Cabinet',
            'is_featured' => true,
            'is_deal' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Featured Products', false)
            ->assertSee('Current Range', false)
            ->assertSee('Outdoor Telecom Cabinet', false);
    }
}
