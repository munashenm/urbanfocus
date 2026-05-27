<?php

namespace App\Services\Blog;

use App\Models\Article;
use Illuminate\Support\Str;

class BlogSeoService
{
    public function optimize(Article $article): Article
    {
        if (! config('blog_automation.auto_seo', true)) {
            return $article;
        }

        if (empty($article->slug)) {
            $article->slug = $this->optimizeSlug($article->title);
        } else {
            $article->slug = $this->optimizeSlug($article->slug);
        }

        if (empty($article->meta_title)) {
            $article->meta_title = Str::limit($article->title.' | Urban Focus', 60, '');
        }

        if (empty($article->meta_description)) {
            $article->meta_description = Str::limit(
                seo_meta_description(strip_tags($article->excerpt ?: $article->title), [
                    'type' => 'article',
                    'name' => $article->title,
                ]),
                160,
                ''
            );
        }

        if (empty($article->excerpt) && $article->content) {
            $article->excerpt = Str::limit(strip_tags($article->content), 500, '');
        }

        if ($article->faqs === null && $article->content) {
            $article->faqs = $this->suggestFaqsFromContent($article);
        }

        if ($article->focus_keywords === null) {
            $article->focus_keywords = $this->suggestKeywords($article);
        }

        return $article;
    }

    public function optimizeSlug(string $value): string
    {
        $slug = Str::slug(Str::lower(trim($value)));
        $slug = preg_replace('/-+/', '-', $slug) ?? $slug;

        return trim($slug, '-') ?: 'article';
    }

    /** @return list<array{question: string, answer: string}> */
    public function suggestFaqsFromContent(Article $article): array
    {
        $faqs = [];
        $content = strip_tags($article->content ?? '');

        if (preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $article->content ?? '', $matches)) {
            foreach (array_slice($matches[1], 0, 4) as $heading) {
                $heading = trim(strip_tags($heading));
                if ($heading === '') {
                    continue;
                }
                $faqs[] = [
                    'question' => str_ends_with($heading, '?') ? $heading : "What is {$heading}?",
                    'answer' => Str::limit($content, 200, '…'),
                ];
            }
        }

        if ($faqs === [] && $article->title) {
            $faqs[] = [
                'question' => "What should I know about {$article->title}?",
                'answer' => Str::limit(strip_tags($article->excerpt ?: $content), 200, '…'),
            ];
        }

        return array_slice($faqs, 0, 5);
    }

    /** @return list<string> */
    public function suggestKeywords(Article $article): array
    {
        $text = Str::lower($article->title.' '.strip_tags($article->content ?? '').' '.($article->category ?? ''));
        $keywords = [];

        foreach (array_keys(config('blog_automation.internal_link_map', [])) as $term) {
            if (str_contains($text, Str::lower($term))) {
                $keywords[] = $term;
            }
        }

        if ($article->categoryLabel()) {
            $keywords[] = Str::lower($article->categoryLabel());
        }

        $keywords[] = 'south africa';
        $keywords[] = 'urban focus';

        return array_values(array_unique(array_slice($keywords, 0, 10)));
    }

    /** @return array<string, mixed> */
    public function articleSchema(Article $article): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article->title,
            'description' => $article->seoDescription(),
            'image' => [$article->ogImageUrl()],
            'datePublished' => $article->published_at?->toAtomString(),
            'dateModified' => $article->updated_at?->toAtomString(),
            'mainEntityOfPage' => route('blog.show', $article),
            'wordCount' => str_word_count(strip_tags($article->content ?: '')),
            'author' => $this->authorSchema($article),
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Urban Focus',
                'logo' => ['@type' => 'ImageObject', 'url' => asset('images/logo-stacked.png')],
            ],
        ];

        if ($article->categoryLabel()) {
            $schema['articleSection'] = $article->categoryLabel();
        }

        if ($article->keywordList() !== []) {
            $schema['keywords'] = implode(', ', $article->keywordList());
        }

        return $schema;
    }

    /** @return array<string, mixed> */
    protected function authorSchema(Article $article): array
    {
        if ($article->author) {
            return [
                '@type' => 'Person',
                'name' => $article->author->name,
                'url' => route('blog.author', $article->author),
            ];
        }

        return ['@type' => 'Organization', 'name' => 'Urban Focus'];
    }
}
