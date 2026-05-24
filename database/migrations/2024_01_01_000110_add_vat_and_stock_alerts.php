<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'billing_vat_number')) {
                $table->string('billing_vat_number', 50)->nullable()->after('billing_company');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'company_name')) {
                $table->string('company_name')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'vat_number')) {
                $table->string('vat_number', 50)->nullable()->after('company_name');
            }
        });

        if (! Schema::hasTable('stock_alerts')) {
            Schema::create('stock_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('email');
                $table->string('name')->nullable();
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();

                $table->unique(['product_id', 'email']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');

        Schema::table('users', function (Blueprint $table) {
            foreach (['vat_number', 'company_name'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'billing_vat_number')) {
                $table->dropColumn('billing_vat_number');
            }
        });
    }
};
