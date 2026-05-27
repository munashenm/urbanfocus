<?php

namespace App\Services\Blog;

use App\Models\Article;
use App\Models\BlogTopic;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BlogAiService
{
    public function draftFromTopic(BlogTopic $topic, string $type = 'buying_guide'): Article
    {
        $content = $this->generateContent($topic, $type);

        $article = Article::create([
            'title' => $this->generateTitle($topic, $type),
            'slug' => Str::slug(Str::limit($topic->title, 80, '')),
            'excerpt' => Str::limit(strip_tags($content), 500),
            'content' => $content,
            'category' => $this->mapCategory($topic),
            'is_published' => false,
            'toc_enabled' => true,
        ]);

        $topic->update(['status' => 'drafted', 'article_id' => $article->id]);

        return $article;
    }

    protected function generateTitle(BlogTopic $topic, string $type): string
    {
        $base = $topic->title;
        $suffix = match ($type) {
            'comparison' => ' — Comparison Guide for South Africa',
            'tutorial' => ' — Setup Guide',
            'news_summary' => ' — What South African IT Teams Should Know',
            default => ' — Buying Guide for South Africa',
        };

        return Str::limit($base.$suffix, 255, '');
    }

    protected function mapCategory(BlogTopic $topic): string
    {
        $text = Str::lower($topic->title);

        return match (true) {
            str_contains($text, 'laptop') || str_contains($text, 'notebook') => 'laptops',
            str_contains($text, 'cctv') || str_contains($text, 'camera') => 'cctv',
            str_contains($text, 'procurement') || str_contains($text, 'bulk') => 'procurement',
            str_contains($text, 'news') => 'news',
            default => 'networking',
        };
    }

    protected function generateContent(BlogTopic $topic, string $type): string
    {
        if (config('blog_automation.openai.enabled') && config('blog_automation.openai.api_key')) {
            $ai = $this->callOpenAi($topic, $type);
            if ($ai !== '') {
                return $ai;
            }
        }

        return $this->templateContent($topic, $type);
    }

    protected function callOpenAi(BlogTopic $topic, string $type): string
    {
        $prompt = $this->buildPrompt($topic, $type);

        $response = Http::timeout(90)
            ->withToken(config('blog_automation.openai.api_key'))
            ->post(rtrim(config('blog_automation.openai.base_url'), '/').'/chat/completions', [
                'model' => config('blog_automation.openai.model'),
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an SEO content writer for Urban Focus, a South African IT distributor. Write HTML with h2/h3 headings, bullet lists, comparison tables where useful, and a FAQ section. Target featured snippets. Include South African context.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
            ]);

        if (! $response->successful()) {
            return '';
        }

        return trim($response->json('choices.0.message.content', ''));
    }

    protected function buildPrompt(BlogTopic $topic, string $type): string
    {
        $keywords = implode(', ', $topic->keywords ?? []);

        return "Write a {$type} blog article about: {$topic->title}. Keywords: {$keywords}. Include: quick answer intro, question-style H2 headings, bullet points, comparison table if relevant, 4 FAQs at the end, internal link placeholders for Urban Focus products.";
    }

    protected function templateContent(BlogTopic $topic, string $type): string
    {
        $title = e($topic->title);
        $source = $topic->source_url ? '<p><em>Topic discovered via '.e($topic->source).'.</em></p>' : '';

        return <<<HTML
<p><strong>Quick answer:</strong> {$title} is a priority topic for South African businesses sourcing IT infrastructure through Urban Focus.</p>
{$source}
<h2>What is {$title}?</h2>
<p>This guide explains key considerations for IT managers, installers and procurement teams evaluating {$title} in South Africa.</p>
<h2>Who is this for?</h2>
<ul>
<li>Businesses upgrading networking or hardware</li>
<li>ISPs and integrators planning rollouts</li>
<li>Procurement teams requesting formal quotes</li>
</ul>
<h2>Key factors to compare</h2>
<table class="table table-bordered">
<thead><tr><th>Factor</th><th>Why it matters</th></tr></thead>
<tbody>
<tr><td>Brand &amp; warranty</td><td>Genuine supply and local support</td></tr>
<tr><td>Availability</td><td>Lead times for project deadlines</td></tr>
<tr><td>Total cost</td><td>VAT invoices and bulk pricing</td></tr>
</tbody>
</table>
<h2>Frequently asked questions</h2>
<h3>Where can I buy this in South Africa?</h3>
<p>Urban Focus supplies IT hardware with VAT invoices and nationwide delivery. <a href="/b2b/quote">Request a quote</a>.</p>
<h3>Do you offer bulk pricing?</h3>
<p>Yes — submit an RFQ for project and fleet quantities.</p>
HTML;
    }
}
