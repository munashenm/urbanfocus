<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NewsSyncService
{
    /** @return array{imported: int, skipped: int, errors: array<int, string>} */
    public function sync(): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach (config('news.rss_feeds', []) as $feed) {
            try {
                $result = $this->importRssFeed($feed['url'], $feed['name']);
                $imported += $result['imported'];
                $skipped += $result['skipped'];
            } catch (\Throwable $e) {
                $errors[] = ($feed['name'] ?? 'RSS').': '.$e->getMessage();
            }
        }

        if (config('news.newsapi.enabled') && config('news.newsapi.key')) {
            try {
                $result = $this->importNewsApi();
                $imported += $result['imported'];
                $skipped += $result['skipped'];
            } catch (\Throwable $e) {
                $errors[] = 'NewsAPI: '.$e->getMessage();
            }
        }

        return compact('imported', 'skipped', 'errors');
    }

    /** @return array{imported: int, skipped: int} */
    protected function importRssFeed(string $url, string $sourceName): array
    {
        $response = Http::timeout(20)->withHeaders([
            'User-Agent' => 'UrbanFocusBlogBot/1.0 (+https://www.urbanfocus.co.za)',
        ])->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()} fetching {$url}");
        }

        $xml = @simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            throw new \RuntimeException('Invalid RSS XML');
        }

        $items = $xml->channel->item ?? $xml->entry ?? [];
        $imported = 0;
        $skipped = 0;
        $limit = config('news.max_per_feed', 3);
        $count = 0;

        foreach ($items as $item) {
            if ($count >= $limit) {
                break;
            }

            $link = trim((string) ($item->link['href'] ?? $item->link ?? ''));
            $title = trim(strip_tags((string) ($item->title ?? '')));
            $description = trim(strip_tags((string) ($item->description ?? $item->summary ?? '')));
            $pubDate = trim((string) ($item->pubDate ?? $item->published ?? ''));

            if ($title === '' || $link === '') {
                $skipped++;

                continue;
            }

            $externalId = hash('sha256', $link);
            if (Article::where('external_id', $externalId)->exists()) {
                $skipped++;

                continue;
            }

            $slug = $this->uniqueSlug(Str::slug(Str::limit($title, 80, '')));
            $publishedAt = $pubDate ? date('Y-m-d H:i:s', strtotime($pubDate) ?: time()) : now();

            Article::create([
                'title' => Str::limit($title, 255, ''),
                'slug' => $slug,
                'excerpt' => Str::limit($description, 500, ''),
                'content' => $this->buildContent($description, $link, $sourceName),
                'source_url' => $link,
                'source_name' => $sourceName,
                'external_id' => $externalId,
                'category' => 'news',
                'is_published' => ! config('news.publish_as_draft', true),
                'published_at' => config('news.publish_as_draft', true) ? null : $publishedAt,
            ]);

            $imported++;
            $count++;
        }

        return compact('imported', 'skipped');
    }

    /** @return array{imported: int, skipped: int} */
    protected function importNewsApi(): array
    {
        $response = Http::timeout(20)->get('https://newsapi.org/v2/everything', [
            'q' => config('news.newsapi.query'),
            'language' => config('news.newsapi.language', 'en'),
            'sortBy' => 'publishedAt',
            'pageSize' => config('news.newsapi.page_size', 5),
            'apiKey' => config('news.newsapi.key'),
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('NewsAPI HTTP '.$response->status());
        }

        $imported = 0;
        $skipped = 0;

        foreach ($response->json('articles', []) as $article) {
            $link = trim($article['url'] ?? '');
            $title = trim($article['title'] ?? '');
            $description = trim($article['description'] ?? '');
            $sourceName = trim($article['source']['name'] ?? 'NewsAPI');

            if ($title === '' || $link === '') {
                $skipped++;

                continue;
            }

            $externalId = hash('sha256', $link);
            if (Article::where('external_id', $externalId)->exists()) {
                $skipped++;

                continue;
            }

            Article::create([
                'title' => Str::limit($title, 255, ''),
                'slug' => $this->uniqueSlug(Str::slug(Str::limit($title, 80, ''))),
                'excerpt' => Str::limit($description, 500, ''),
                'content' => $this->buildContent($description, $link, $sourceName),
                'source_url' => $link,
                'source_name' => $sourceName,
                'external_id' => $externalId,
                'category' => 'news',
                'is_published' => ! config('news.publish_as_draft', true),
                'published_at' => config('news.publish_as_draft', true) ? null : now(),
            ]);

            $imported++;
        }

        return compact('imported', 'skipped');
    }

    protected function buildContent(string $description, string $link, string $sourceName): string
    {
        $paragraphs = $description !== '' ? '<p>'.e($description).'</p>' : '';
        $paragraphs .= '<p><em>Source: '.e($sourceName).' — <a href="'.e($link).'" target="_blank" rel="noopener noreferrer">Read the full article</a></em></p>';

        return $paragraphs;
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = $base ?: 'article';
        $original = $slug;
        $i = 1;

        while (Article::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$i++;
        }

        return $slug;
    }
}
