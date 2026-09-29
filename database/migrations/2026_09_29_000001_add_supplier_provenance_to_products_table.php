<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'supplier_cost_price')) {
                $table->decimal('supplier_cost_price', 12, 2)->nullable()->after('cost_price');
            }

            if (! Schema::hasColumn('products', 'supplier_name')) {
                $table->string('supplier_name')->nullable()->after('brand');
            }

            if (! Schema::hasColumn('products', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('supplier_name');
            }

            if (! Schema::hasColumn('products', 'supplier_sku')) {
                $table->string('supplier_sku')->nullable()->after('supplier_id');
            }

            if (! Schema::hasColumn('products', 'supplier_product_url')) {
                $table->string('supplier_product_url', 500)->nullable()->after('supplier_sku');
            }

            if (! Schema::hasColumn('products', 'supplier_image_url')) {
                $table->string('supplier_image_url', 500)->nullable()->after('supplier_product_url');
            }

            if (! Schema::hasColumn('products', 'import_source')) {
                $table->string('import_source', 32)->nullable()->index()->after('supplier_image_url');
            }

            if (! Schema::hasColumn('products', 'source_file')) {
                $table->string('source_file')->nullable()->after('import_source');
            }

            if (! Schema::hasColumn('products', 'source_external_id')) {
                $table->string('source_external_id')->nullable()->after('source_file');
            }

            if (! Schema::hasColumn('products', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('source_external_id');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['import_source', 'supplier_sku'], 'products_import_source_supplier_sku_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            try {
                $table->dropIndex('products_import_source_supplier_sku_index');
            } catch (\Throwable) {
            }

            $columns = [
                'supplier_cost_price',
                'supplier_name',
                'supplier_id',
                'supplier_sku',
                'supplier_product_url',
                'supplier_image_url',
                'import_source',
                'source_file',
                'source_external_id',
                'last_synced_at',
            ];

            $existing = array_values(array_filter(
                $columns,
                fn (string $column) => Schema::hasColumn('products', $column)
            ));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
