<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $articles = Article::published()->latest('published_at')->paginate(12);

        return view('blog.index', compact('articles'));
    }

    public function show(Article $article): View
    {
        abort_unless($article->is_published, 404);

        $related = Article::published()
            ->where('id', '!=', $article->id)
            ->latest('published_at')
            ->take(3)
            ->get();

        return view('blog.show', compact('article', 'related'));
    }
}
