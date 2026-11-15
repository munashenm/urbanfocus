<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('products', ['category_id', 'is_active'], 'products_category_active_idx');
        $this->addIndex('products', ['is_active', 'brand'], 'products_active_brand_idx');
        $this->addIndex('products', ['is_active', 'views'], 'products_active_views_idx');
        $this->addIndex('product_images', ['product_id', 'is_primary', 'sort_order'], 'product_images_primary_sort_idx');
        $this->addIndex('categories', ['parent_id', 'is_active', 'sort_order'], 'categories_parent_active_sort_idx');
    }

    public function down(): void
    {
        $this->dropIndex('products', 'products_category_active_idx');
        $this->dropIndex('products', 'products_active_brand_idx');
        $this->dropIndex('products', 'products_active_views_idx');
        $this->dropIndex('product_images', 'product_images_primary_sort_idx');
        $this->dropIndex('categories', 'categories_parent_active_sort_idx');
    }

    /** @param list<string> $columns */
    protected function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table) || Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    protected function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }
};
