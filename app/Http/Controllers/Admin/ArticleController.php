<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Author;
use App\Models\Tag;
use App\Services\Blog\BlogSchema;
use App\Services\NewsSyncService;
use Database\Seeders\BlogSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(): View
    {
        $articles = BlogSchema::withOptionalRelations(
            Article::query()->latest('created_at')
        )->paginate(20);

        return view('admin.articles.index', [
            'articles' => $articles,
            'blogMigrationNeeded' => ! BlogSchema::adminReady(),
            'blogMigrationMissing' => BlogSchema::missingForAdmin(),
        ]);
    }

    public function create(): View
    {
        return view('admin.articles.form', $this->formData(new Article(
            BlogSchema::hasColumn('toc_enabled') ? ['toc_enabled' => true] : []
        )));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $article = Article::create($data);
        $this->syncTags($article, $request->input('tags'));

        return redirect()->route('admin.articles.index')->with('success', 'Article created.');
    }

    public function edit(Article $article): View
    {
        if (BlogSchema::hasTags()) {
            $article->load('tags');
        }

        return view('admin.articles.form', $this->formData($article));
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $article->update($this->validated($request, $article->id));
        $this->syncTags($article, $request->input('tags'));

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

    public function seedPillars(): RedirectResponse
    {
        if (! BlogSchema::hasColumn('category')) {
            return back()->with('error', 'Run blog migrations first (clear-cache.php?migrate=1).');
        }

        (new BlogSeeder)->run();
        \Illuminate\Support\Facades\Cache::forget('sitemap.xml');

        return back()->with('success', 'SEO pillar articles seeded. Review published posts at /blog.');
    }

    /** @return array<string, mixed> */
    protected function formData(Article $article): array
    {
        $allTags = '';
        if ($article->exists && BlogSchema::hasTags() && $article->relationLoaded('tags')) {
            $allTags = $article->tags->pluck('name')->implode(', ');
        }

        return [
            'article' => $article,
            'authors' => BlogSchema::hasAuthors()
                ? Author::where('is_active', true)->orderBy('name')->get()
                : collect(),
            'allTags' => $allTags,
            'blogFeatures' => [
                'category' => BlogSchema::hasColumn('category'),
                'authors' => BlogSchema::hasAuthors(),
                'tags' => BlogSchema::hasTags(),
                'faqs' => BlogSchema::hasColumn('faqs'),
                'toc' => BlogSchema::hasColumn('toc_enabled'),
                'featured' => BlogSchema::hasFeatured(),
                'social' => BlogSchema::hasColumn('social_snippets'),
            ],
            'blogMigrationNeeded' => ! BlogSchema::adminReady(),
            'blogMigrationMissing' => BlogSchema::missingForAdmin(),
        ];
    }

    protected function validated(Request $request, ?int $id = null): array
    {
        if ($request->input('category') === '') {
            $request->merge(['category' => null]);
        }

        $rules = [
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:articles,slug,'.$id,
            'excerpt' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'image' => 'nullable|string|max:500',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ];

        if (BlogSchema::hasColumn('category')) {
            $rules['category'] = ['nullable', 'string', 'max:50', Rule::in(array_keys(config('blog.categories', [])))];
        }

        if (BlogSchema::hasAuthors()) {
            $rules['author_id'] = 'nullable|exists:authors,id';
        }

        if (BlogSchema::hasFeatured()) {
            $rules['is_featured'] = 'boolean';
        }

        if (BlogSchema::hasColumn('toc_enabled')) {
            $rules['toc_enabled'] = 'boolean';
        }

        if (BlogSchema::hasColumn('faqs')) {
            $rules['faqs'] = 'nullable|array';
            $rules['faqs.*.question'] = 'nullable|string|max:500';
            $rules['faqs.*.answer'] = 'nullable|string|max:2000';
        }

        $data = $request->validate($rules);

        $data['slug'] = Str::slug($data['slug'] ?: $data['title']);
        $data['is_published'] = $request->boolean('is_published');

        if (BlogSchema::hasFeatured()) {
            $data['is_featured'] = $request->boolean('is_featured');
        }

        if (BlogSchema::hasColumn('toc_enabled')) {
            $data['toc_enabled'] = $request->boolean('toc_enabled', true);
        }

        if (BlogSchema::hasColumn('faqs')) {
            $data['faqs'] = $this->normalizeFaqs($request->input('faqs', []));
        }

        if ($data['is_published'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return BlogSchema::onlyExistingColumns($data);
    }

    /** @param list<array<string, string|null>> $faqs */
    protected function normalizeFaqs(array $faqs): ?array
    {
        $clean = array_values(array_filter(array_map(function ($faq) {
            if (! is_array($faq)) {
                return null;
            }
            $question = trim((string) ($faq['question'] ?? ''));
            $answer = trim((string) ($faq['answer'] ?? ''));

            return ($question !== '' && $answer !== '') ? compact('question', 'answer') : null;
        }, $faqs)));

        return $clean === [] ? null : $clean;
    }

    protected function syncTags(Article $article, ?string $tagsInput): void
    {
        if ($tagsInput === null || ! BlogSchema::hasTags()) {
            return;
        }

        $ids = [];
        foreach (array_filter(array_map('trim', explode(',', $tagsInput))) as $name) {
            $tag = Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
            $ids[] = $tag->id;
        }

        $article->tags()->sync($ids);
    }
}
