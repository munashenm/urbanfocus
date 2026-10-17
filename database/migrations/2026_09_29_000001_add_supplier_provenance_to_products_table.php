<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'supplier_cost_price' => fn (Blueprint $table) => $table->decimal('supplier_cost_price', 12, 2)->nullable()->after('cost_price'),
            'supplier_name' => fn (Blueprint $table) => $table->string('supplier_name')->nullable()->after('brand'),
            'supplier_id' => fn (Blueprint $table) => $table->unsignedBigInteger('supplier_id')->nullable()->after('supplier_name'),
            'supplier_sku' => fn (Blueprint $table) => $table->string('supplier_sku')->nullable()->after('supplier_id'),
            'supplier_product_url' => fn (Blueprint $table) => $table->string('supplier_product_url', 500)->nullable()->after('supplier_sku'),
            'supplier_image_url' => fn (Blueprint $table) => $table->string('supplier_image_url', 500)->nullable()->after('supplier_product_url'),
            'import_source' => fn (Blueprint $table) => $table->string('import_source', 32)->nullable()->index()->after('supplier_image_url'),
            'source_file' => fn (Blueprint $table) => $table->string('source_file')->nullable()->after('import_source'),
            'source_external_id' => fn (Blueprint $table) => $table->string('source_external_id')->nullable()->after('source_file'),
            'last_synced_at' => fn (Blueprint $table) => $table->timestamp('last_synced_at')->nullable()->after('source_external_id'),
        ];

        $missing = array_keys(array_filter(
            $columns,
            fn (callable $add, string $column) => ! Schema::hasColumn('products', $column),
            ARRAY_FILTER_USE_BOTH
        ));

        if ($missing !== []) {
            Schema::table('products', function (Blueprint $table) use ($columns, $missing) {
                foreach ($missing as $column) {
                    $columns[$column]($table);
                }
            });
        }

        $indexName = 'products_import_source_supplier_sku_index';
        if (Schema::hasColumn('products', 'import_source')
            && Schema::hasColumn('products', 'supplier_sku')
            && ! Schema::hasIndex('products', $indexName)
        ) {
            Schema::table('products', function (Blueprint $table) use ($indexName) {
                $table->index(['import_source', 'supplier_sku'], $indexName);
            });
        }
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
