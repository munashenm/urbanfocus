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

        return [
            'message' => Str::limit($message, 280, ''),
            'link_url' => $url,
            'image_url' => $product->display_image_url,
        ];
    }

    public function article(Article $article): array
    {
        $url = route('blog.show', $article);
        $message = trim("{$article->title}\n".Str::limit(strip_tags($article->excerpt ?: ''), 120)."\n{$url}\n".config('social-posting.hashtags'));

        return [
            'message' => Str::limit($message, 280, ''),
            'link_url' => $url,
            'image_url' => $article->image ? url('/storage/'.ltrim($article->image, '/')) : null,
        ];
    }
}
