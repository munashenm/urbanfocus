<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@urbanfocus.co.za'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('ChangeMe123!'),
                'is_admin' => true,
                'is_active' => true,
            ]
        );

        $this->call(RolePermissionSeeder::class);
        $this->call(CategorySeeder::class);

        if (Product::count() === 0) {
            $sampleProducts = [
                ['name' => 'Business Laptop 14"', 'sku' => 'UF-LAP-001', 'price' => 12999, 'category' => 'business-laptops', 'brand' => 'Dell'],
                ['name' => '27" QHD Monitor', 'sku' => 'UF-MON-001', 'price' => 5499, 'category' => 'office-monitors', 'brand' => 'LG'],
                ['name' => 'Managed Switch 24-Port', 'sku' => 'UF-NET-001', 'price' => 3899, 'category' => 'network-switches', 'brand' => 'TP-Link'],
                ['name' => 'Microsoft 365 Business', 'sku' => 'UF-SW-001', 'price' => 899, 'category' => 'microsoft-365', 'brand' => 'Microsoft'],
                ['name' => '1TB NVMe SSD', 'sku' => 'UF-SSD-001', 'price' => 1499, 'category' => 'ssds-hdds', 'brand' => 'Samsung'],
                ['name' => 'Wireless Keyboard & Mouse', 'sku' => 'UF-PER-001', 'price' => 699, 'category' => 'keyboards-mice', 'brand' => 'Logitech'],
            ];

            foreach ($sampleProducts as $index => $item) {
                $category = \App\Models\Category::where('slug', $item['category'])->first();

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

        Setting::set('api_key', 'uf_'.\Illuminate\Support\Str::random(32), 'api');

        foreach (config('partners.default_brands', []) as $i => $brand) {
            Brand::firstOrCreate(
                ['slug' => $brand['slug']],
                [
                    'name' => $brand['name'],
                    'logo' => $brand['logo'],
                    'sort_order' => $i,
                    'is_active' => true,
                ]
            );
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('articles') && Article::count() === 0) {
            Article::create([
                'title' => 'How to Choose a Business Laptop in 2026',
                'slug' => 'choose-business-laptop-2026',
                'excerpt' => 'A practical guide for IT managers and procurement teams selecting corporate notebooks.',
                'content' => "Business laptops differ from consumer models in build quality, warranty, manageability and security features.\n\nKey factors: CPU generation, RAM (16GB minimum), SSD storage, Windows Pro licensing, and manufacturer warranty terms.",
                'is_published' => true,
                'published_at' => now(),
            ]);
        }
    }
}
