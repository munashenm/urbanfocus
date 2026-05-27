<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\BlogTopic;
use App\Services\Blog\BlogAiService;
use App\Services\Blog\BlogSchema;
use App\Services\Blog\BlogSearchConsoleService;
use App\Services\Blog\BlogTopicDiscoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BlogAutomationController extends Controller
{
    public function index(BlogSearchConsoleService $gsc): View
    {
        $metrics = $gsc->dashboardMetrics();
        $topics = BlogSchema::hasBlogTopics()
            ? BlogTopic::suggested()->take(20)->get()
            : collect();

        $topBlogs = BlogSchema::hasColumn('views')
            ? Article::published()->orderByDesc('views')->take(10)->get()
            : Article::published()->latest('published_at')->take(10)->get();

        $recentDrafts = Article::where('is_published', false)->latest()->take(5)->get();

        return view('admin.blog-strategy.index', [
            'metrics' => $metrics,
            'topics' => $topics,
            'topBlogs' => $topBlogs,
            'recentDrafts' => $recentDrafts,
            'blogMigrationNeeded' => ! BlogSchema::adminReady(),
            'blogMigrationMissing' => BlogSchema::missingForAdmin(),
        ]);
    }

    public function discoverTopics(BlogTopicDiscoveryService $discovery): RedirectResponse
    {
        if (! BlogSchema::hasBlogTopics()) {
            return back()->with('error', 'Run blog migrations first (clear-cache.php?migrate=1).');
        }

        $result = $discovery->discover();

        $message = "Discovered {$result['discovered']} topic(s), skipped {$result['skipped']} duplicate(s).";
        if (! empty($result['errors'])) {
            return back()->with('warning', $message.' '.implode(' | ', $result['errors']));
        }

        return back()->with('success', $message);
    }

    public function syncSearchConsole(BlogSearchConsoleService $gsc): RedirectResponse
    {
        if (! BlogSchema::hasAnalyticsSnapshots()) {
            return back()->with('error', 'Run blog migrations first (clear-cache.php?migrate=1).');
        }

        try {
            $gsc->fetchSearchAnalytics();
            \Illuminate\Support\Facades\Cache::forget('sitemap.xml');

            return back()->with('success', 'Google Search Console snapshot updated.');
        } catch (\Throwable $e) {
            return back()->with('error', 'GSC sync failed: '.$e->getMessage());
        }
    }

    public function draftFromTopic(BlogTopic $topic, BlogAiService $ai): RedirectResponse
    {
        if ($topic->article_id) {
            return redirect()->route('admin.articles.edit', $topic->article_id)
                ->with('warning', 'This topic already has a draft article.');
        }

        $type = request('type', 'buying_guide');
        $article = $ai->draftFromTopic($topic, $type);

        return redirect()->route('admin.articles.edit', $article)
            ->with('success', 'AI draft created — review and publish when ready.');
    }
}
