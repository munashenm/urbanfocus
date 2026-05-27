<?php

namespace App\Services\Blog;

use App\Models\Article;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

class BlogInternalLinkingService
{
    public function enrich(Article $article): string
    {
        if (! config('blog_automation.auto_internal_links', true)) {
            return (string) $article->content;
        }

        $html = (string) $article->content;
        $html = $this->linkMappedTerms($html);
        $html = $this->appendProductLinksBlock($article, $html);

        return $html;
    }

    protected function linkMappedTerms(string $html): string
    {
        $map = config('blog_automation.internal_link_map', []);
        uksort($map, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($map as $term => $target) {
            $url = $this->resolveTargetUrl($target);
            if (! $url) {
                continue;
            }

            $pattern = '/\b('.preg_quote($term, '/').')\b/i';
            if (! preg_match($pattern, strip_tags($html))) {
                continue;
            }

            $html = preg_replace_callback($pattern, function (array $m) use ($url) {
                static $linkedTerms = [];
                $key = Str::lower($m[1]);
                if (isset($linkedTerms[$key])) {
                    return $m[0];
                }
                $linkedTerms[$key] = true;

                return '<a href="'.e($url).'">'.$m[1].'</a>';
            }, $html, 1) ?? $html;
        }

        return $html;
    }

    /** @param array<string, mixed> $target */
    protected function resolveTargetUrl(array $target): ?string
    {
        return match ($target['type'] ?? '') {
            'brand' => Brand::where('slug', $target['slug'] ?? '')->where('is_active', true)->exists()
                ? route('brands.show', $target['slug'])
                : null,
            'category' => Category::where('slug', $target['slug'] ?? '')->where('is_active', true)->exists()
                ? route('categories.show', $target['slug'])
                : null,
            'solution' => array_key_exists($target['slug'] ?? '', config('seo_landings', []))
                ? route('solutions.show', $target['slug'])
                : null,
            default => null,
        };
    }

    protected function appendProductLinksBlock(Article $article, string $html): string
    {
        if (str_contains($html, 'blog-related-products')) {
            return $html;
        }

        $products = $this->relatedProducts($article);
        if ($products->isEmpty()) {
            return $html;
        }

        $block = '<div class="blog-related-products mt-4"><h2>Related products</h2><ul>';
        foreach ($products as $product) {
            $block .= '<li><a href="'.e(route('products.show', $product)).'">'.e($product->name).'</a></li>';
        }
        $block .= '</ul></div>';

        return $html.$block;
    }

    protected function relatedProducts(Article $article)
    {
        $text = Str::lower($article->title.' '.strip_tags($article->content ?? ''));
        $query = Product::with('images')->forStorefront()->latest();

        foreach (config('blog_automation.product_link_keywords', []) as $terms) {
            foreach ((array) $terms as $term) {
                if (str_contains($text, Str::lower($term))) {
                    return $query->where('name', 'like', '%'.$term.'%')->take(4)->get();
                }
            }
        }

        if ($article->categoryKey()) {
            $category = Category::where('slug', $article->categoryKey())->first();
            if ($category) {
                $ids = Category::descendantIds($category->id);

                return $query->whereIn('category_id', $ids)->take(4)->get();
            }
        }

        return collect();
    }
}
