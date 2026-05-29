<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'paystack_reference')) {
            if (Schema::hasColumn('orders', 'payfast_payment_id')) {
                Schema::table('orders', function (Blueprint $table) {
                    $table->renameColumn('payfast_payment_id', 'paystack_reference');
                });
            } else {
                Schema::table('orders', function (Blueprint $table) {
                    $table->string('paystack_reference')->nullable()->after('payment_status');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'paystack_reference') && ! Schema::hasColumn('orders', 'payfast_payment_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->renameColumn('paystack_reference', 'payfast_payment_id');
            });
        }
    }
};
