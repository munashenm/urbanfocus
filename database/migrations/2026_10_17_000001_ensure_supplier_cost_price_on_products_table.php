<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ensure products.supplier_cost_price exists for CSV imports.
 *
 * The importer stores each row's supplier cost (ex-VAT) here beside the
 * selling price so markup/margins can be tracked per supplier. Production
 * databases that never applied the provenance migration (or only partially
 * applied it) fail every import until this column is present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'supplier_cost_price')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('supplier_cost_price', 12, 2)->nullable()->after('cost_price');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'supplier_cost_price')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('supplier_cost_price');
        });
    }
};
