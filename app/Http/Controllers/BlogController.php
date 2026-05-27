<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Author;
use App\Models\Tag;
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
        $categories = config('blog.categories', []);

        $featured = Article::published()
            ->featured()
            ->latest('published_at')
            ->first();

        if (! $featured) {
            $featured = Article::published()->latest('published_at')->first();
        }

        $articlesQuery = Article::published()
            ->with(['author', 'tags'])
            ->inCategory($activeCategory)
            ->latest('published_at');

        if ($featured) {
            $articlesQuery->where('id', '!=', $featured->id);
        }

        $articles = $articlesQuery->paginate(12)->withQueryString();
        $pagination = $this->seo->paginationMeta($articles);

        return view('blog.index', compact(
            'articles',
            'featured',
            'activeCategory',
            'categories',
            'pagination',
        ));
    }

    public function category(string $category): View
    {
        abort_unless(array_key_exists($category, config('blog.categories', [])), 404);

        $meta = config("blog.categories.{$category}");
        $articles = Article::published()
            ->with(['author', 'tags'])
            ->where('category', $category)
            ->latest('published_at')
            ->paginate(12);

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
        $articles = Article::published()
            ->with(['author', 'tags'])
            ->withTag($tag->slug)
            ->latest('published_at')
            ->paginate(12);

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
        abort_unless($author->is_active, 404);

        $articles = $author->articles()
            ->published()
            ->with('tags')
            ->latest('published_at')
            ->paginate(12);

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
        $article->load(['author', 'tags']);
        $article->increment('views');

        $related = Article::published()
            ->with(['author', 'tags'])
            ->where('id', '!=', $article->id)
            ->when(
                $article->categoryKey(),
                fn ($q) => $q->where('category', $article->categoryKey()),
                fn ($q) => $q
            )
            ->latest('published_at')
            ->take(3)
            ->get();

        if ($related->count() < 3 && $article->tags->isNotEmpty()) {
            $tagIds = $article->tags->pluck('id');
            $tagRelated = Article::published()
                ->with(['author', 'tags'])
                ->where('id', '!=', $article->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds))
                ->latest('published_at')
                ->take(3 - $related->count())
                ->get();
            $related = $related->merge($tagRelated);
        }

        if ($related->count() < 3) {
            $related = Article::published()
                ->with(['author', 'tags'])
                ->where('id', '!=', $article->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->latest('published_at')
                ->take(3 - $related->count())
                ->get()
                ->merge($related);
        }

        $content = $article->content ?? '';
        $tocItems = [];

        if ($article->toc_enabled && $content !== '') {
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
