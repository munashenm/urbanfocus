<?php

namespace App\Services\Blog;

use App\Models\Article;
use App\Models\Brand;
use App\Models\Product;
use App\Services\CategoryMapperService;
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

        // Split out existing anchors so re-running never nests links inside
        // already-linked text. Captured delimiters land on odd indices.
        $segments = preg_split('/(<a\b[^>]*>.*?<\/a>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($segments === false) {
            return $html;
        }

        $linkedTerms = [];

        foreach ($segments as $i => $segment) {
            if ($i % 2 === 1) {
                continue;
            }

            foreach ($map as $term => $target) {
                if (isset($linkedTerms[Str::lower($term)])) {
                    continue;
                }

                $url = $this->resolveTargetUrl($target);
                if (! $url) {
                    continue;
                }

                $pattern = '/\b('.preg_quote($term, '/').')\b/i';
                $replaced = preg_replace_callback($pattern, function (array $m) use ($url, &$linkedTerms) {
                    $linkedTerms[Str::lower($m[1])] = true;

                    return '<a href="'.e($url).'">'.$m[1].'</a>';
                }, $segment, 1);

                if ($replaced !== null) {
                    $segment = $replaced;
                }
            }

            $segments[$i] = $segment;
        }

        return implode('', $segments);
    }

    /** @param array<string, mixed> $target */
    protected function resolveTargetUrl(array $target): ?string
    {
        return match ($target['type'] ?? '') {
            'brand' => Brand::where('slug', $target['slug'] ?? '')->where('is_active', true)->exists()
                ? route('brands.show', $target['slug'])
                : null,
            'category' => ($path = trim((string) ($target['path'] ?? $target['slug'] ?? ''), '/')) !== ''
                ? app(CategoryMapperService::class)->categoryUrlForPath($path)
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
