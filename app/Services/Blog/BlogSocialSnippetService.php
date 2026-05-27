<?php

namespace App\Services\Blog;

use App\Models\Article;
use Illuminate\Support\Str;

class BlogSocialSnippetService
{
    /** @return array<string, string> */
    public function generate(Article $article): array
    {
        $url = route('blog.show', $article);
        $title = $article->title;
        $summary = Str::limit(strip_tags($article->excerpt ?: ''), 160);
        $hashtags = config('social-posting.hashtags', '#UrbanFocus #ITSouthAfrica');

        return [
            'facebook' => trim("{$title}\n\n{$summary}\n\n{$url}"),
            'x' => Str::limit("{$title} — {$summary} {$url} {$hashtags}", 280, ''),
            'linkedin' => trim("{$title}\n\n{$summary}\n\nRead more: {$url}\n\n#IT #SouthAfrica #UrbanFocus"),
            'tiktok' => Str::limit("{$title} — {$summary} Link in bio. {$hashtags} #TechTips", 150, ''),
            'instagram' => Str::limit("{$title}\n\n{$summary}\n\n{$hashtags}", 2200, ''),
        ];
    }
}
