<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialPost;
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

        return view('admin.social.index', compact('stats', 'recent', 'enabled'));
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
}
