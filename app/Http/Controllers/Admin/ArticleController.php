<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\NewsSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(): View
    {
        $articles = Article::latest('created_at')->paginate(20);

        return view('admin.articles.index', compact('articles'));
    }

    public function create(): View
    {
        return view('admin.articles.form', ['article' => new Article]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Article::create($data);

        return redirect()->route('admin.articles.index')->with('success', 'Article created.');
    }

    public function edit(Article $article): View
    {
        return view('admin.articles.form', compact('article'));
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $article->update($this->validated($request, $article->id));

        return redirect()->route('admin.articles.index')->with('success', 'Article updated.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $article->delete();

        return redirect()->route('admin.articles.index')->with('success', 'Article deleted.');
    }

    public function syncNews(NewsSyncService $sync): RedirectResponse
    {
        $result = $sync->sync();
        \Illuminate\Support\Facades\Cache::forget('sitemap.xml');

        $message = "Imported {$result['imported']} new article(s), skipped {$result['skipped']} duplicate(s).";
        if (! empty($result['errors'])) {
            return back()->with('warning', $message.' Errors: '.implode(' | ', $result['errors']));
        }

        return back()->with('success', $message);
    }

    protected function validated(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:articles,slug,'.$id,
            'excerpt' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        $data['slug'] = Str::slug($data['slug'] ?: $data['title']);
        $data['is_published'] = $request->boolean('is_published');

        if ($data['is_published'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
