<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageBlackFridaySectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_omits_the_specials_section_when_nothing_is_on_sale(): void
    {
        Product::factory()->create([
            'name' => 'Regular Catalogue Switch',
            'price' => 2000,
            'sale_price' => null,
        ]);
        Product::factory()->create([
            'name' => 'Expired Sale Laptop',
            'price' => 3000,
            'sale_price' => 1500,
            'sale_ends_at' => now()->subDay(),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('bf-home-specials', false)
            ->assertDontSee('Black Friday Specials', false)
            ->assertDontSee('Deals end 30 November', false);
    }

    public function test_homepage_lists_in_stock_sale_products_under_the_hero(): void
    {
        $featured = $this->saleProduct('Featured Hero Switch', 2000, 1800, true);
        $deep = $this->saleProduct('Deep Discount Router', 2000, 1000, false);
        $this->saleProduct('Out Of Stock Camera', 2000, 500, false, [
            'stock_quantity' => 0,
            'in_stock' => false,
        ]);
        Product::factory()->create([
            'name' => 'Full Price Notebook',
            'price' => 4000,
            'sale_price' => null,
        ]);

        $response = $this->get(route('home'))->assertOk();
        $section = $this->specialsSection($response->getContent());

        $this->assertNotSame('', $section);
        $this->assertStringContainsString('Black Friday Specials', $section);
        $this->assertStringContainsString('Deals end 30 November', $section);
        $this->assertStringContainsString(route('black-friday'), $section);
        $this->assertStringContainsString('View all specials', $section);
        $this->assertStringContainsString(route('products.show', $featured), $section);
        $this->assertStringContainsString(route('products.show', $deep), $section);
        $this->assertStringContainsString('Was R', $section);
        $this->assertStringContainsString('badge-black-friday', $section);
        $this->assertStringNotContainsString('Out Of Stock Camera', $section);
        $this->assertStringNotContainsString('Full Price Notebook', $section);
        $this->assertLessThan(
            strpos($section, 'Deep Discount Router'),
            strpos($section, 'Featured Hero Switch')
        );
        $this->assertStringContainsString('bf-banner', $response->getContent());
    }

    public function test_homepage_specials_stop_at_eight_products(): void
    {
        foreach (range(1, 9) as $n) {
            $this->saleProduct('Sale Product '.$n, 1000, 900 - $n, false);
        }

        $section = $this->specialsSection($this->get(route('home'))->assertOk()->getContent());

        $this->assertSame(8, substr_count($section, 'class="product-card '));
        $this->assertStringNotContainsString('Sale Product 1', $section);
    }

    private function saleProduct(string $name, float $price, float $salePrice, bool $featured, array $extra = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => $name,
            'price' => $price,
            'sale_price' => $salePrice,
            'sale_starts_at' => now()->subDay(),
            'sale_ends_at' => now()->addWeek(),
            'promo_label' => 'Black Friday',
            'is_featured' => $featured,
            'stock_quantity' => 4,
            'manage_stock' => true,
            'in_stock' => true,
        ], $extra));
    }

    private function specialsSection(string $html): string
    {
        $start = strpos($html, 'bf-home-specials');
        if ($start === false) {
            return '';
        }

        $end = strpos($html, '</section>', $start);

        return $end === false ? '' : substr($html, $start, $end - $start);
    }
}
