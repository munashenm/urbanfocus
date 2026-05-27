<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('authors')) {
            Schema::create('authors', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('title')->nullable();
                $table->text('bio')->nullable();
                $table->string('avatar')->nullable();
                $table->string('meta_title')->nullable();
                $table->string('meta_description', 500)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }

        Schema::table('articles', function (Blueprint $table) {
            if (! Schema::hasColumn('articles', 'author_id')) {
                $table->foreignId('author_id')->nullable()->after('category')->constrained('authors')->nullOnDelete();
            }
            if (! Schema::hasColumn('articles', 'faqs')) {
                $table->json('faqs')->nullable()->after('content');
            }
            if (! Schema::hasColumn('articles', 'focus_keywords')) {
                $table->json('focus_keywords')->nullable()->after('faqs');
            }
            if (! Schema::hasColumn('articles', 'social_snippets')) {
                $table->json('social_snippets')->nullable()->after('focus_keywords');
            }
            if (! Schema::hasColumn('articles', 'toc_enabled')) {
                $table->boolean('toc_enabled')->default(true)->after('social_snippets');
            }
            if (! Schema::hasColumn('articles', 'views')) {
                $table->unsignedInteger('views')->default(0)->after('toc_enabled');
            }
        });

        if (! Schema::hasTable('article_tag')) {
            Schema::create('article_tag', function (Blueprint $table) {
                $table->id();
                $table->foreignId('article_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
                $table->unique(['article_id', 'tag_id']);
            });
        }

        if (! Schema::hasTable('blog_topics')) {
            Schema::create('blog_topics', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('source', 40);
                $table->string('source_url', 500)->nullable();
                $table->unsignedInteger('score')->default(0);
                $table->json('keywords')->nullable();
                $table->json('metadata')->nullable();
                $table->string('status', 20)->default('suggested');
                $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
                $table->timestamp('discovered_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('blog_analytics_snapshots')) {
            Schema::create('blog_analytics_snapshots', function (Blueprint $table) {
                $table->id();
                $table->date('snapshot_date');
                $table->string('source', 40);
                $table->json('payload');
                $table->timestamps();
                $table->unique(['snapshot_date', 'source']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_analytics_snapshots');
        Schema::dropIfExists('blog_topics');
        Schema::dropIfExists('article_tag');
        Schema::table('articles', function (Blueprint $table) {
            foreach (['author_id', 'faqs', 'focus_keywords', 'social_snippets', 'toc_enabled', 'views'] as $col) {
                if (Schema::hasColumn('articles', $col)) {
                    if ($col === 'author_id') {
                        $table->dropConstrainedForeignId('author_id');
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });
        Schema::dropIfExists('tags');
        Schema::dropIfExists('authors');
    }
};
