<?php

namespace Tests\Unit;

use App\Models\Product;
use Tests\TestCase;

class StorefrontHelpersTest extends TestCase
{
    public function test_discount_percent_rounds_sale_saving(): void
    {
        $product = new Product([
            'price' => 2000,
            'sale_price' => 1500,
        ]);

        $this->assertSame(25, $product->discountPercent());

        $fullPrice = new Product([
            'price' => 1999,
            'sale_price' => null,
        ]);

        $this->assertNull($fullPrice->discountPercent());
    }
}
