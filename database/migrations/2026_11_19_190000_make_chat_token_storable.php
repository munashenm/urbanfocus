<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_conversations')) {
            Schema::create('chat_conversations', function (Blueprint $table) {
                $table->id();
                $table->char('public_token', 36)->unique();
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
        }

        if (! Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->nullable()->index();
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

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $column = DB::selectOne(
            'select DATA_TYPE as data_type from information_schema.COLUMNS where TABLE_SCHEMA = database() and TABLE_NAME = ? and COLUMN_NAME = ?',
            ['chat_conversations', 'public_token']
        );

        if ($column && strtolower((string) $column->data_type) === 'uuid') {
            DB::statement('alter table chat_conversations modify public_token char(36) not null');
        }
    }

    public function down(): void
    {
        // The chat tables belong to the earlier migration. Leave them in place.
    }
};
