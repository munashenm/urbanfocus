<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistAndCompareTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_add_and_view_wishlist(): void
    {
        $product = Product::factory()->create();

        $this->from(route('products.show', $product))
            ->post(route('wishlist.toggle', $product))
            ->assertRedirect(route('products.show', $product));

        $this->get(route('wishlist.index'))
            ->assertOk()
            ->assertSee($product->name, false);
    }

    public function test_guest_can_compare_two_products(): void
    {
        $first = Product::factory()->create(['name' => 'UniFi Switch 8']);
        $second = Product::factory()->create(['name' => 'UniFi Switch 16']);

        $this->post(route('compare.add', $first))->assertRedirect();
        $this->post(route('compare.add', $second))->assertRedirect();

        $this->get(route('compare.index'))
            ->assertOk()
            ->assertSee('UniFi Switch 8', false)
            ->assertSee('UniFi Switch 16', false)
            ->assertSee('Ports', false);
    }

    public function test_compare_rejects_a_fifth_product(): void
    {
        $products = Product::factory()->count(5)->create();

        foreach ($products->take(4) as $product) {
            $this->post(route('compare.add', $product))->assertRedirect();
        }

        $this->from(route('shop.index'))
            ->post(route('compare.add', $products->last()))
            ->assertRedirect(route('shop.index'))
            ->assertSessionHas('warning');
    }

    public function test_in_stock_wishlist_item_can_move_to_cart(): void
    {
        $product = Product::factory()->create();

        $this->post(route('wishlist.add', $product))->assertRedirect();
        $this->post(route('wishlist.move-to-cart', $product))
            ->assertRedirect(route('cart.index'));

        $this->get(route('cart.index'))->assertSee($product->name, false);
        $this->get(route('wishlist.index'))->assertDontSee($product->name, false);
    }

    public function test_cart_item_can_be_saved_for_later(): void
    {
        $product = Product::factory()->create();

        $this->post(route('cart.add', $product))->assertRedirect(route('cart.index'));
        $this->post(route('cart.save-for-later', $product))
            ->assertRedirect(route('wishlist.index'));

        $this->get(route('wishlist.index'))->assertSee($product->name, false);
        $this->get(route('cart.index'))->assertDontSee($product->name, false);
    }
}
