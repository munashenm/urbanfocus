<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            if (! Schema::hasColumn('articles', 'category')) {
                $table->string('category', 50)->nullable()->after('image');
            }
            if (! Schema::hasColumn('articles', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            if (Schema::hasColumn('articles', 'is_featured')) {
                $table->dropColumn('is_featured');
            }
            if (Schema::hasColumn('articles', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
