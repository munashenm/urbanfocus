<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'is_oos_listable')) {
                $table->boolean('is_oos_listable')->default(false)->after('is_active')->index();
            }
            if (! Schema::hasColumn('products', 'last_in_stock_at')) {
                $table->timestamp('last_in_stock_at')->nullable()->after('last_synced_at')->index();
            }
            if (! Schema::hasColumn('products', 'oos_hidden_at')) {
                $table->timestamp('oos_hidden_at')->nullable()->after('last_in_stock_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['is_oos_listable', 'last_in_stock_at', 'oos_hidden_at'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
