<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'sale_starts_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->timestamp('sale_starts_at')->nullable();
            });
        }

        if (! Schema::hasColumn('products', 'sale_ends_at')) {
            Schema::table('products', function (Blueprint $table) {
                $table->timestamp('sale_ends_at')->nullable();
            });
        }

        if (! Schema::hasColumn('products', 'promo_label')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('promo_label')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['promo_label', 'sale_ends_at', 'sale_starts_at'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                Schema::table('products', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
