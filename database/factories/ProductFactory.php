<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(4, true).' Switch';

        return [
            'sku' => strtoupper(fake()->unique()->bothify('UF-####??')),
            'name' => ucfirst($name),
            'short_description' => fake()->sentence(),
            'price' => 1999.00,
            'sale_price' => null,
            'stock_quantity' => 10,
            'manage_stock' => true,
            'in_stock' => true,
            'brand' => 'Ubiquiti',
            'is_featured' => false,
            'is_deal' => false,
            'is_active' => true,
            'specifications' => [
                'Ports' => '8',
                'PoE' => 'Yes',
            ],
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn () => [
            'stock_quantity' => 0,
            'in_stock' => false,
        ]);
    }

    public function onSale(): static
    {
        return $this->state(fn () => [
            'price' => 2000.00,
            'sale_price' => 1500.00,
        ]);
    }
}
