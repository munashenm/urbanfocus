<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_posts')) {
            Schema::create('social_posts', function (Blueprint $table) {
                $table->id();
                $table->string('postable_type');
                $table->unsignedBigInteger('postable_id');
                $table->string('platform', 20);
                $table->string('status', 20)->default('pending');
                $table->text('message')->nullable();
                $table->string('image_url', 500)->nullable();
                $table->string('link_url', 500)->nullable();
                $table->string('external_id')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();

                $table->unique(['postable_type', 'postable_id', 'platform']);
                $table->index(['status', 'platform']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
