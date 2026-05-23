<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@urbanfocus.co.za',
            'password' => Hash::make('ChangeMe123!'),
            'is_admin' => true,
        ]);

        $categories = [
            ['name' => 'Laptops & Notebooks', 'slug' => 'laptops-notebooks'],
            ['name' => 'Monitors & Displays', 'slug' => 'monitors-displays'],
            ['name' => 'Networking', 'slug' => 'networking'],
            ['name' => 'Software & Licensing', 'slug' => 'software-licensing'],
            ['name' => 'Components & Storage', 'slug' => 'components-storage'],
            ['name' => 'Peripherals', 'slug' => 'peripherals'],
        ];

        foreach ($categories as $index => $cat) {
            Category::create([
                ...$cat,
                'sort_order' => $index,
                'is_active' => true,
                'description' => 'Quality '.$cat['name'].' from Urban Focus.',
            ]);
        }

        $sampleProducts = [
            ['name' => 'Business Laptop 14"', 'sku' => 'UF-LAP-001', 'price' => 12999, 'category' => 'laptops-notebooks', 'brand' => 'Dell'],
            ['name' => '27" QHD Monitor', 'sku' => 'UF-MON-001', 'price' => 5499, 'category' => 'monitors-displays', 'brand' => 'LG'],
            ['name' => 'Managed Switch 24-Port', 'sku' => 'UF-NET-001', 'price' => 3899, 'category' => 'networking', 'brand' => 'TP-Link'],
            ['name' => 'Microsoft 365 Business', 'sku' => 'UF-SW-001', 'price' => 899, 'category' => 'software-licensing', 'brand' => 'Microsoft'],
            ['name' => '1TB NVMe SSD', 'sku' => 'UF-SSD-001', 'price' => 1499, 'category' => 'components-storage', 'brand' => 'Samsung'],
            ['name' => 'Wireless Keyboard & Mouse', 'sku' => 'UF-PER-001', 'price' => 699, 'category' => 'peripherals', 'brand' => 'Logitech'],
        ];

        foreach ($sampleProducts as $index => $item) {
            $category = Category::where('slug', $item['category'])->first();

            Product::create([
                'category_id' => $category?->id,
                'sku' => $item['sku'],
                'name' => $item['name'],
                'slug' => \Illuminate\Support\Str::slug($item['name']),
                'short_description' => 'Professional IT product supplied by Urban Focus.',
                'description' => '<p>Reliable '.$item['name'].' for business and home office use. Backed by Urban Focus support.</p>',
                'price' => $item['price'],
                'stock_quantity' => 25,
                'manage_stock' => true,
                'in_stock' => true,
                'brand' => $item['brand'],
                'is_featured' => $index < 4,
                'is_active' => true,
            ]);
        }
    }
}
