<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_conversations', 'assigned_user_id')) {
                $table->unsignedBigInteger('assigned_user_id')->nullable()->after('handover_alerted_at')->index();
            }
            if (! Schema::hasColumn('chat_conversations', 'claimed_at')) {
                $table->timestamp('claimed_at')->nullable()->after('assigned_user_id');
            }
            if (! Schema::hasColumn('chat_conversations', 'bot_paused')) {
                $table->boolean('bot_paused')->default(false)->after('claimed_at');
            }
        });

        if (! Schema::hasTable('chat_agent_presences')) {
            Schema::create('chat_agent_presences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('status', 16)->default('away');
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $permission = Permission::updateOrCreate(
            ['name' => 'chat.agent'],
            ['label' => 'Handle live chat', 'group' => 'Support']
        );

        $agent = Role::updateOrCreate(
            ['slug' => 'chat_agent'],
            [
                'name' => 'Chat agent',
                'description' => 'Claim and reply to live website chats.',
                'is_system' => true,
            ]
        );
        $agent->permissions()->syncWithoutDetaching([$permission->id]);

        foreach (['admin', 'sales', 'support'] as $slug) {
            $role = Role::query()->where('slug', $slug)->first();
            $role?->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_agent_presences');

        Schema::table('chat_conversations', function (Blueprint $table) {
            foreach (['bot_paused', 'claimed_at', 'assigned_user_id'] as $column) {
                if (Schema::hasColumn('chat_conversations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
