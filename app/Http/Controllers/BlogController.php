<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Author;
use App\Models\Tag;
use App\Services\Blog\BlogSchema;
use App\Services\Blog\BlogSeoService;
use App\Services\Blog\BlogTocService;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function __construct(
        protected SeoService $seo,
        protected BlogTocService $toc,
        protected BlogSeoService $blogSeo,
    ) {}

    public function index(Request $request): View
    {
        $activeCategory = $request->query('category');
        $search = trim((string) $request->query('q', ''));
        $categories = config('blog.categories', []);
        $isFiltered = $activeCategory || $search !== '';

        $featured = null;
        if (! $isFiltered) {
            if (BlogSchema::hasFeatured()) {
                $featured = Article::published()
                    ->featured()
                    ->latest('published_at')
                    ->first();
            }

            if (! $featured) {
                $featured = Article::published()->latest('published_at')->first();
            }
        }

        $articlesQuery = BlogSchema::withOptionalRelations(
            Article::published()->inCategory($activeCategory)->latest('published_at')
        );

        if ($search !== '') {
            $articlesQuery->where(function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where('title', 'like', $like)
                    ->orWhere('excerpt', 'like', $like)
                    ->orWhere('content', 'like', $like);
            });
        }

        if ($featured) {
            $articlesQuery->where('id', '!=', $featured->id);
        }

        $articles = $articlesQuery->paginate(12)->withQueryString();
        $pagination = $this->seo->paginationMeta($articles);

        $popular = $this->popularArticles($featured?->id);

        return view('blog.index', compact(
            'articles',
            'featured',
            'popular',
            'activeCategory',
            'search',
            'categories',
            'pagination',
        ));
    }

    /** @return \Illuminate\Support\Collection<int, Article> */
    protected function popularArticles(?int $excludeId = null): \Illuminate\Support\Collection
    {
        $query = Article::published()
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId));

        $query = BlogSchema::hasColumn('views')
            ? $query->orderByDesc('views')->orderByDesc('published_at')
            : $query->latest('published_at');

        return $query->take(5)->get();
    }

    public function category(string $category): View|\Illuminate\Http\RedirectResponse
    {
        abort_unless(array_key_exists($category, config('blog.categories', [])), 404);

        if (! BlogSchema::hasColumn('category')) {
            return redirect()->route('blog.index', ['category' => $category]);
        }

        $meta = config("blog.categories.{$category}");
        $articles = BlogSchema::withOptionalRelations(
            Article::published()->inCategory($category)->latest('published_at')
        )->paginate(12);

        $pagination = $this->seo->paginationMeta($articles);

        return view('blog.archive', [
            'articles' => $articles,
            'pagination' => $pagination,
            'archiveType' => 'category',
            'archiveKey' => $category,
            'archiveTitle' => $meta['label'].' Articles',
            'archiveDescription' => $meta['description'] ?? config('blog.index_description'),
        ]);
    }

    public function tag(Tag $tag): View
    {
        abort_unless(BlogSchema::hasTags(), 404);

        $articles = BlogSchema::withOptionalRelations(
            Article::published()->withTag($tag->slug)->latest('published_at')
        )->paginate(12);

        $pagination = $this->seo->paginationMeta($articles);

        return view('blog.archive', [
            'articles' => $articles,
            'pagination' => $pagination,
            'archiveType' => 'tag',
            'archiveKey' => $tag->slug,
            'archiveTitle' => 'Tagged: '.$tag->name,
            'archiveDescription' => "Articles tagged {$tag->name} on the Urban Focus blog.",
            'tag' => $tag,
        ]);
    }

    public function author(Author $author): View
    {
        abort_unless($author->is_active && BlogSchema::hasAuthors(), 404);

        $articles = BlogSchema::withOptionalRelations(
            $author->articles()->published()->latest('published_at')
        )->paginate(12);

        $pagination = $this->seo->paginationMeta($articles);

        return view('blog.archive', [
            'articles' => $articles,
            'pagination' => $pagination,
            'archiveType' => 'author',
            'archiveKey' => $author->slug,
            'archiveTitle' => 'Articles by '.$author->name,
            'archiveDescription' => $author->seoDescription(),
            'author' => $author,
        ]);
    }

    public function show(Article $article): View
    {
        if (BlogSchema::hasAuthors() || BlogSchema::hasTags()) {
            $with = array_filter([
                BlogSchema::hasAuthors() ? 'author' : null,
                BlogSchema::hasTags() ? 'tags' : null,
            ]);
            $article->load($with);
        }

        if (BlogSchema::hasColumn('views')) {
            $article->increment('views');
        }

        $related = BlogSchema::withOptionalRelations(
            Article::published()
                ->where('id', '!=', $article->id)
                ->when(
                    $article->categoryKey() && BlogSchema::hasColumn('category'),
                    fn ($q) => $q->where('category', $article->categoryKey()),
                    fn ($q) => $q
                )
                ->latest('published_at')
        )->take(3)->get();

        if ($related->count() < 3 && BlogSchema::hasTags() && $article->relationLoaded('tags') && $article->tags->isNotEmpty()) {
            $tagIds = $article->tags->pluck('id');
            $tagRelated = BlogSchema::withOptionalRelations(
                Article::published()
                    ->where('id', '!=', $article->id)
                    ->whereNotIn('id', $related->pluck('id'))
                    ->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds))
                    ->latest('published_at')
            )->take(3 - $related->count())->get();
            $related = $related->merge($tagRelated);
        }

        if ($related->count() < 3) {
            $related = BlogSchema::withOptionalRelations(
                Article::published()
                    ->where('id', '!=', $article->id)
                    ->whereNotIn('id', $related->pluck('id'))
                    ->latest('published_at')
            )->take(3 - $related->count())->get()->merge($related);
        }

        $content = $article->content ?? '';
        $tocItems = [];

        if (BlogSchema::hasColumn('toc_enabled') && $article->toc_enabled && $content !== '') {
            $processed = $this->toc->process($content);
            $content = $processed['html'];
            $tocItems = $processed['items'];
        }

        $categoryCta = $article->categoryCta();
        $articleSchema = $this->blogSeo->articleSchema($article);

        return view('blog.show', compact(
            'article',
            'related',
            'categoryCta',
            'content',
            'tocItems',
            'articleSchema',
        ));
    }
}
