<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'category_id'], 'products_active_category_index');
            $table->index('brand', 'products_brand_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['parent_id', 'is_active', 'sort_order'], 'categories_parent_active_sort_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_active_category_index');
            $table->dropIndex('products_brand_index');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_parent_active_sort_index');
        });
    }
};
