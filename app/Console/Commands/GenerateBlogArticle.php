<?php

namespace App\Console\Commands;

use App\Models\BlogTopic;
use App\Services\Blog\BlogAiService;
use Illuminate\Console\Command;

class GenerateBlogArticle extends Command
{
    protected $signature = 'blog:generate-article {topic? : Blog topic ID or slug} {--type=buying_guide : Article type}';

    protected $description = 'Generate an AI-assisted blog draft from a discovered topic';

    public function handle(BlogAiService $ai): int
    {
        $topicArg = $this->argument('topic');

        if (! $topicArg) {
            $topic = BlogTopic::suggested()->orderByDesc('score')->first();
            if (! $topic) {
                $this->error('No suggested topics. Run blog:discover-topics first.');

                return self::FAILURE;
            }
        } else {
            $topic = is_numeric($topicArg)
                ? BlogTopic::findOrFail((int) $topicArg)
                : BlogTopic::where('slug', $topicArg)->firstOrFail();
        }

        if ($topic->article_id) {
            $this->warn("Topic already linked to article #{$topic->article_id}.");

            return self::SUCCESS;
        }

        $article = $ai->draftFromTopic($topic, $this->option('type'));
        $this->info("Draft created: {$article->title} (ID {$article->id})");

        return self::SUCCESS;
    }
}
