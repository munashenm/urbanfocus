<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Product;
use App\Models\SocialPost;
use App\Models\WebhookLog;
use App\Services\Marketing\MakeWebhookService;
use App\Services\Social\SocialPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SocialController extends Controller
{
    public function index(): View
    {
        $stats = [
            'pending' => SocialPost::where('status', 'pending')->count(),
            'posted' => SocialPost::where('status', 'posted')->count(),
            'failed' => SocialPost::where('status', 'failed')->count(),
        ];

        $recent = SocialPost::with('postable')->latest()->take(20)->get();
        $enabled = config('social-posting.enabled');

        $webhookStats = [
            'success' => WebhookLog::where('status', 'success')->count(),
            'failed' => WebhookLog::where('status', 'failed')->count(),
        ];
        $webhookLogs = WebhookLog::latest()->take(20)->get();
        $makeEnabled = config('make.enabled');
        $makeConfigured = (bool) (config('make.webhooks.product') || config('make.webhooks.blog'));
        $feeds = [
            'rss' => route('feeds.rss'),
            'facebook' => route('feeds.facebook'),
        ];

        return view('admin.social.index', compact(
            'stats', 'recent', 'enabled',
            'webhookStats', 'webhookLogs', 'makeEnabled', 'makeConfigured', 'feeds',
        ));
    }

    public function retryWebhook(WebhookLog $webhookLog, MakeWebhookService $make): RedirectResponse
    {
        if (! $make->isEnabled()) {
            return back()->with('error', 'Enable MAKE_ENABLED in .env before re-dispatching webhooks.');
        }

        $model = $webhookLog->target_type && $webhookLog->target_id
            ? ($webhookLog->target_type)::find($webhookLog->target_id)
            : null;

        if (! $model) {
            return back()->with('error', 'The original product or article no longer exists.');
        }

        $result = match (true) {
            $model instanceof Product => $make->dispatchProduct($model, $webhookLog->event),
            $model instanceof Article => $make->dispatchArticle($model, $webhookLog->event),
            default => ['ok' => false, 'reason' => 'Unsupported target'],
        };

        return $result['ok']
            ? back()->with('success', 'Re-dispatched to Make.com successfully.')
            : back()->with('error', 'Re-dispatch failed: '.($result['reason'] ?? 'unknown error'));
    }

    public function publish(SocialPostingService $social): RedirectResponse
    {
        if (! $social->isEnabled()) {
            return back()->with('error', 'Enable SOCIAL_POSTING_ENABLED in .env first.');
        }

        $result = $social->publishPending();

        return back()->with('success', "Posted {$result['posted']}, failed {$result['failed']}, skipped {$result['skipped']}.");
    }

    public function queueAll(SocialPostingService $social): RedirectResponse
    {
        $products = $social->queueAllActiveProducts();
        $articles = 0;
        foreach (\App\Models\Article::published()->get() as $article) {
            $before = \App\Models\SocialPost::where('postable_type', \App\Models\Article::class)->where('postable_id', $article->id)->count();
            $social->queueArticle($article);
            $after = \App\Models\SocialPost::where('postable_type', \App\Models\Article::class)->where('postable_id', $article->id)->count();
            if ($after > $before) {
                $articles++;
            }
        }

        return back()->with('success', "Queued {$products['queued']} product(s) and {$articles} article(s) for social posting.");
    }

    public function retryFailed(SocialPostingService $social): RedirectResponse
    {
        $count = $social->retryFailed();

        return back()->with('success', "Reset {$count} failed post(s) to pending. Click Publish to try again.");
    }
}
