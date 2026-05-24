<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            if (! Schema::hasColumn('articles', 'source_url')) {
                $table->string('source_url', 500)->nullable()->after('image');
            }
            if (! Schema::hasColumn('articles', 'source_name')) {
                $table->string('source_name')->nullable()->after('source_url');
            }
            if (! Schema::hasColumn('articles', 'external_id')) {
                $table->string('external_id', 64)->nullable()->unique()->after('source_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            foreach (['external_id', 'source_name', 'source_url'] as $column) {
                if (Schema::hasColumn('articles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
