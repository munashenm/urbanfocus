<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('brands')) {
            Schema::create('brands', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('logo')->nullable();
                $table->string('website')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('banners')) {
            Schema::create('banners', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->string('image')->nullable();
                $table->string('link')->nullable();
                $table->string('button_text')->nullable();
                $table->string('placement')->default('home');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('quotes')) {
            Schema::create('quotes', function (Blueprint $table) {
                $table->id();
                $table->string('type');
                $table->string('name');
                $table->string('company')->nullable();
                $table->string('email');
                $table->string('phone')->nullable();
                $table->text('message')->nullable();
                $table->string('file_path')->nullable();
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->string('status')->default('new');
                $table->text('admin_notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('type')->default('percent');
                $table->decimal('value', 10, 2);
                $table->decimal('min_order', 12, 2)->nullable();
                $table->unsignedInteger('max_uses')->nullable();
                $table->unsignedInteger('used_count')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'model_number')) {
                $table->string('model_number')->nullable()->after('sku');
            }
            if (! Schema::hasColumn('products', 'warranty_months')) {
                $table->unsignedSmallInteger('warranty_months')->nullable()->after('dimensions');
            }
            if (! Schema::hasColumn('products', 'delivery_days')) {
                $table->unsignedSmallInteger('delivery_days')->nullable()->after('warranty_months');
            }
            if (! Schema::hasColumn('products', 'specifications')) {
                $table->json('specifications')->nullable()->after('delivery_days');
            }
            if (! Schema::hasColumn('products', 'is_deal')) {
                $table->boolean('is_deal')->default(false)->after('is_featured');
            }
            if (! Schema::hasColumn('products', 'deal_label')) {
                $table->string('deal_label')->nullable()->after('is_deal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $columns = ['model_number', 'warranty_months', 'delivery_days', 'specifications', 'is_deal', 'deal_label'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('coupons');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('brands');
    }
};
