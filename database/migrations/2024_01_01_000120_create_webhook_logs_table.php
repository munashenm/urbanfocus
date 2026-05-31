<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webhook_logs')) {
            Schema::create('webhook_logs', function (Blueprint $table) {
                $table->id();
                $table->string('event', 50);
                $table->string('target_type')->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('target_label', 255)->nullable();
                $table->string('destination', 50)->default('make');
                $table->string('webhook_url', 500)->nullable();
                $table->json('platforms')->nullable();
                $table->json('payload')->nullable();
                $table->string('status', 20)->default('pending');
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->text('response')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['status', 'event']);
                $table->index(['target_type', 'target_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
