<?php

namespace App\Services\Social;

use App\Models\Article;
use App\Models\Product;
use Illuminate\Support\Str;

class SocialPostFormatter
{
    public function product(Product $product): array
    {
        $url = route('products.show', $product);
        $price = 'R '.number_format($product->effective_price, 2);
        $message = trim("{$product->name} — {$price}\n{$url}\n".config('social-posting.hashtags'));
        $imageUrl = $product->primary_image_url;

        return [
            'message' => Str::limit($message, 280, ''),
            'link_url' => $url,
            'image_url' => $imageUrl ? $this->absoluteUrl($imageUrl) : null,
        ];
    }

    public function article(Article $article): array
    {
        $url = route('blog.show', $article);
        $snippets = $article->socialSnippetList();

        $message = $snippets['x'] ?? $snippets['facebook'] ?? trim("{$article->title}\n".Str::limit(strip_tags($article->excerpt ?: ''), 120)."\n{$url}\n".config('social-posting.hashtags'));
        $imageUrl = $article->displayImageUrl();

        return [
            'message' => Str::limit($message, 280, ''),
            'link_url' => $url,
            'image_url' => $this->absoluteUrl($imageUrl),
            'snippets' => $snippets,
        ];
    }

    protected function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }
}
