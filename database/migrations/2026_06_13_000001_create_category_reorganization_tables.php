<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('old_slug')->unique();
            $table->string('target_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->timestamps();
        });

        Schema::create('category_migration_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('old_category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('new_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('old_slug');
            $table->string('new_slug_path')->nullable();
            $table->string('match_method', 50);
            $table->unsignedInteger('products_moved')->default(0);
            $table->timestamps();
        });

        Schema::create('category_migration_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('old_category_id')->nullable();
            $table->string('old_category_slug')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_migration_backups');
        Schema::dropIfExists('category_migration_mappings');
        Schema::dropIfExists('category_slug_redirects');
    }
};
