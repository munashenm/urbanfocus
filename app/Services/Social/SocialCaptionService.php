<?php

namespace App\Services\Social;

use App\Models\Article;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Generates AI-powered, platform-specific social captions for products and blog
 * posts. Each caption is built around the image, title, URL and SEO description
 * so Make.com can publish ready-to-go posts to Facebook, LinkedIn and X.
 *
 * Falls back to high-quality templates when OpenAI is disabled or unavailable.
 */
class SocialCaptionService
{
    /** Platforms we know how to write for. */
    protected const PLATFORMS = ['facebook', 'linkedin', 'x'];

    /** Caption length budgets per platform (characters). */
    protected const LIMITS = [
        'facebook' => 600,
        'linkedin' => 700,
        'x' => 280,
    ];

    /**
     * Build the full caption bundle for a product, including the asset details
     * (image, title, URL, SEO description) and a caption per platform.
     *
     * @return array<string, mixed>
     */
    public function forProduct(Product $product): array
    {
        $product->loadMissing('images', 'category');

        $assets = [
            'type' => 'product',
            'id' => $product->id,
            'sku' => $product->sku,
            'title' => $product->name,
            'url' => route('products.show', $product),
            'image_url' => $this->absoluteUrl($product->display_image_url),
            'seo_description' => $product->seoDescription(),
            'brand' => $product->brand,
            'category' => $product->category?->name,
            'price' => $product->effective_price,
            'price_formatted' => 'R '.number_format($product->effective_price, 2),
            'on_sale' => $product->is_on_sale,
            'currency' => config('google-merchant.currency', 'ZAR'),
        ];

        $assets['captions'] = $this->captions($assets);

        return $assets;
    }

    /**
     * Build the full caption bundle for a blog article.
     *
     * @return array<string, mixed>
     */
    public function forArticle(Article $article): array
    {
        $assets = [
            'type' => 'article',
            'id' => $article->id,
            'title' => $article->title,
            'url' => route('blog.show', $article),
            'image_url' => $this->absoluteUrl($article->displayImageUrl()),
            'seo_description' => $article->seoDescription(),
            'category' => $article->categoryLabel(),
            'reading_time' => $article->readingTimeMinutes(),
        ];

        $assets['captions'] = $this->captions($assets, $article->socialSnippetList());

        return $assets;
    }

    /**
     * @param  array<string, mixed>  $assets
     * @param  array<string, string>  $existing  Pre-generated snippets to seed from.
     * @return array<string, string>
     */
    protected function captions(array $assets, array $existing = []): array
    {
        $platforms = $this->enabledPlatforms();

        $ai = $this->generateWithAi($assets, $platforms);

        $captions = [];
        foreach ($platforms as $platform) {
            $caption = $ai[$platform] ?? $existing[$platform] ?? $this->templateCaption($platform, $assets);
            $captions[$platform] = Str::limit(trim($caption), self::LIMITS[$platform] ?? 600, '');
        }

        return $captions;
    }

    /** @return list<string> */
    protected function enabledPlatforms(): array
    {
        $configured = config('make.platforms', self::PLATFORMS);

        $platforms = array_values(array_intersect(self::PLATFORMS, (array) $configured));

        return $platforms ?: self::PLATFORMS;
    }

    /**
     * @param  array<string, mixed>  $assets
     * @param  list<string>  $platforms
     * @return array<string, string>
     */
    protected function generateWithAi(array $assets, array $platforms): array
    {
        if (! config('blog_automation.openai.enabled') || ! config('blog_automation.openai.api_key')) {
            return [];
        }

        try {
            $response = Http::timeout(45)
                ->withToken(config('blog_automation.openai.api_key'))
                ->post(rtrim(config('blog_automation.openai.base_url'), '/').'/chat/completions', [
                    'model' => config('blog_automation.openai.model'),
                    'temperature' => 0.8,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $this->userPrompt($assets, $platforms)],
                    ],
                ]);

            if (! $response->successful()) {
                return [];
            }

            $content = trim((string) $response->json('choices.0.message.content', ''));
            $decoded = json_decode($content, true);

            if (! is_array($decoded)) {
                return [];
            }

            $captions = [];
            foreach ($platforms as $platform) {
                if (! empty($decoded[$platform]) && is_string($decoded[$platform])) {
                    $captions[$platform] = $decoded[$platform];
                }
            }

            return $captions;
        } catch (\Throwable $e) {
            Log::warning('AI caption generation failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    protected function systemPrompt(): string
    {
        return 'You are the social media manager for Urban Focus, a South African IT distributor. '
            .'Write engaging, conversion-focused captions in South African English. '
            .'Return ONLY a JSON object whose keys are platform names (facebook, linkedin, x) and values are the caption strings. '
            .'Facebook: warm and benefit-led with 1-2 emojis and 2-3 hashtags. '
            .'LinkedIn: professional, B2B tone aimed at procurement and IT managers, no emojis, 3 hashtags. '
            .'X: punchy, under 270 characters including the link, max 2 hashtags. '
            .'Always include the provided URL in each caption. Never invent prices or specs.';
    }

    /**
     * @param  array<string, mixed>  $assets
     * @param  list<string>  $platforms
     */
    protected function userPrompt(array $assets, array $platforms): string
    {
        $lines = [
            'Content type: '.($assets['type'] ?? 'product'),
            'Title: '.($assets['title'] ?? ''),
            'URL: '.($assets['url'] ?? ''),
            'SEO description: '.($assets['seo_description'] ?? ''),
        ];

        if (! empty($assets['brand'])) {
            $lines[] = 'Brand: '.$assets['brand'];
        }
        if (! empty($assets['category'])) {
            $lines[] = 'Category: '.$assets['category'];
        }
        if (! empty($assets['price_formatted'])) {
            $lines[] = 'Price: '.$assets['price_formatted'].($assets['on_sale'] ?? false ? ' (on sale)' : '');
        }

        $lines[] = 'Generate captions for these platforms: '.implode(', ', $platforms).'.';
        $lines[] = 'Default hashtags to draw from: '.config('social-posting.hashtags', '#UrbanFocus #ITSouthAfrica');

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $assets
     */
    protected function templateCaption(string $platform, array $assets): string
    {
        $title = $assets['title'] ?? '';
        $url = $assets['url'] ?? '';
        $summary = Str::limit((string) ($assets['seo_description'] ?? ''), 180, '');
        $hashtags = config('social-posting.hashtags', '#UrbanFocus #ITSouthAfrica');
        $price = $assets['price_formatted'] ?? null;
        $isProduct = ($assets['type'] ?? 'product') === 'product';

        return match ($platform) {
            'facebook' => trim(
                ($isProduct ? "🔥 {$title}" : "📝 New on the blog: {$title}")."\n\n"
                .($price && $isProduct ? "Now {$price}. " : '').$summary."\n\n"
                .($isProduct ? '🛒 Shop now: ' : '👉 Read more: ').$url."\n\n".$hashtags
            ),
            'linkedin' => trim(
                $title."\n\n".$summary."\n\n"
                .($isProduct ? 'View product and request a VAT quote: ' : 'Read the full guide: ').$url
                ."\n\n#IT #SouthAfrica #UrbanFocus"
            ),
            'x' => Str::limit(
                trim(($isProduct && $price ? "{$title} — {$price}" : $title).' '.$url.' '.$hashtags),
                280,
                ''
            ),
            default => trim("{$title} {$url} {$hashtags}"),
        };
    }

    protected function absoluteUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }
}
