<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_token')->unique();
            $table->string('status', 32)->default('open');
            $table->string('topic', 32)->default('general');
            $table->string('visitor_name')->nullable();
            $table->string('visitor_email')->nullable();
            $table->string('visitor_phone')->nullable();
            $table->string('page_url', 500)->nullable();
            $table->timestamp('staff_read_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_message_at']);
            $table->index('topic');
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sender', 32);
            $table->string('kind', 32)->default('message');
            $table->text('body');
            $table->json('cards')->nullable();
            $table->json('payload')->nullable();
            $table->string('review_status', 32)->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->index('review_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
