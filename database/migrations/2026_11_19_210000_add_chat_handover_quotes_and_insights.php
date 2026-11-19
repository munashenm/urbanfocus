<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_conversations', 'handover_alerted_at')) {
                $table->timestamp('handover_alerted_at')->nullable()->after('staff_read_at');
            }
            if (! Schema::hasColumn('chat_conversations', 'quote_draft')) {
                $table->json('quote_draft')->nullable()->after('page_url');
            }
        });

        if (! Schema::hasTable('chat_insights')) {
            Schema::create('chat_insights', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->nullable()->constrained('chat_conversations')->cascadeOnDelete();
                $table->string('kind', 32);
                $table->string('question', 500)->nullable();
                $table->timestamps();

                $table->index(['kind', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_insights');

        Schema::table('chat_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('chat_conversations', 'quote_draft')) {
                $table->dropColumn('quote_draft');
            }
            if (Schema::hasColumn('chat_conversations', 'handover_alerted_at')) {
                $table->dropColumn('handover_alerted_at');
            }
        });
    }
};
