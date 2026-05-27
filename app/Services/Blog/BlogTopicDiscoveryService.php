<?php

namespace App\Services\Blog;

use App\Models\BlogTopic;
use App\Services\NewsSyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BlogTopicDiscoveryService
{
    /** @return array{discovered: int, skipped: int, errors: list<string>} */
    public function discover(): array
    {
        $discovered = 0;
        $skipped = 0;
        $errors = [];

        foreach ([
            'reddit' => fn () => $this->fromReddit(),
            'newsapi' => fn () => $this->fromNewsApi(),
            'serpapi' => fn () => $this->fromSerpApiTrends(),
            'youtube' => fn () => $this->fromYouTube(),
            'rss' => fn () => $this->fromRssHeadlines(),
        ] as $source => $callback) {
            try {
                $result = $callback();
                foreach ($result as $topic) {
                    $saved = $this->storeTopic($topic);
                    $saved ? $discovered++ : $skipped++;
                }
            } catch (\Throwable $e) {
                $errors[] = "{$source}: ".$e->getMessage();
            }
        }

        return compact('discovered', 'skipped', 'errors');
    }

    /** @param array<string, mixed> $topic */
    protected function storeTopic(array $topic): bool
    {
        $slug = Str::slug(Str::limit($topic['title'], 80, ''));

        if (BlogTopic::where('slug', $slug)->exists()) {
            return false;
        }

        BlogTopic::create([
            'title' => $topic['title'],
            'slug' => $slug,
            'source' => $topic['source'],
            'source_url' => $topic['url'] ?? null,
            'score' => (int) ($topic['score'] ?? 0),
            'keywords' => $topic['keywords'] ?? [],
            'metadata' => $topic['metadata'] ?? [],
            'status' => 'suggested',
            'discovered_at' => now(),
        ]);

        return true;
    }

    /** @return list<array<string, mixed>> */
    protected function fromReddit(): array
    {
        if (! config('blog_automation.reddit.enabled', true)) {
            return [];
        }

        $topics = [];
        $agent = config('blog_automation.reddit.user_agent');
        $limit = (int) config('blog_automation.reddit.limit_per_sub', 5);

        foreach (config('blog_automation.reddit.subreddits', []) as $sub) {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => $agent])
                ->get("https://www.reddit.com/r/{$sub}/hot.json", ['limit' => $limit]);

            if (! $response->successful()) {
                continue;
            }

            foreach ($response->json('data.children', []) as $child) {
                $post = $child['data'] ?? [];
                $title = trim($post['title'] ?? '');
                if ($title === '' || ($post['stickied'] ?? false)) {
                    continue;
                }

                $topics[] = [
                    'title' => $title,
                    'source' => 'reddit',
                    'url' => 'https://reddit.com'.($post['permalink'] ?? ''),
                    'score' => (int) ($post['score'] ?? 0),
                    'keywords' => [$sub, 'reddit'],
                    'metadata' => ['subreddit' => $sub, 'comments' => $post['num_comments'] ?? 0],
                ];
            }
        }

        return $topics;
    }

    /** @return list<array<string, mixed>> */
    protected function fromNewsApi(): array
    {
        if (! config('news.newsapi.enabled') || ! config('news.newsapi.key')) {
            return [];
        }

        $response = Http::timeout(20)->get('https://newsapi.org/v2/everything', [
            'q' => config('news.newsapi.query'),
            'language' => config('news.newsapi.language', 'en'),
            'sortBy' => 'publishedAt',
            'pageSize' => 8,
            'apiKey' => config('news.newsapi.key'),
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('NewsAPI HTTP '.$response->status());
        }

        $topics = [];
        foreach ($response->json('articles', []) as $article) {
            $title = trim($article['title'] ?? '');
            if ($title === '') {
                continue;
            }

            $topics[] = [
                'title' => $title,
                'source' => 'newsapi',
                'url' => $article['url'] ?? null,
                'score' => 50,
                'keywords' => ['news', 'tech'],
                'metadata' => ['source' => $article['source']['name'] ?? 'NewsAPI'],
            ];
        }

        return $topics;
    }

    /** @return list<array<string, mixed>> */
    protected function fromSerpApiTrends(): array
    {
        if (! config('blog_automation.serpapi.enabled') || ! config('blog_automation.serpapi.api_key')) {
            return [];
        }

        $topics = [];
        foreach (config('blog_automation.serpapi.trending_queries', []) as $query) {
            $response = Http::timeout(20)->get('https://serpapi.com/search.json', [
                'engine' => 'google_trends',
                'q' => $query,
                'geo' => config('blog_automation.serpapi.geo', 'ZA'),
                'api_key' => config('blog_automation.serpapi.api_key'),
            ]);

            if (! $response->successful()) {
                continue;
            }

            $topics[] = [
                'title' => ucfirst($query).' — South Africa trend report',
                'source' => 'google_trends',
                'url' => null,
                'score' => 80,
                'keywords' => explode(' ', $query),
                'metadata' => ['query' => $query, 'response' => $response->json('interest_over_time') ?? []],
            ];
        }

        return $topics;
    }

    /** @return list<array<string, mixed>> */
    protected function fromYouTube(): array
    {
        if (! config('blog_automation.youtube.enabled') || ! config('blog_automation.youtube.api_key')) {
            return [];
        }

        $topics = [];
        foreach (config('blog_automation.youtube.queries', []) as $query) {
            $response = Http::timeout(20)->get('https://www.googleapis.com/youtube/v3/search', [
                'part' => 'snippet',
                'q' => $query,
                'type' => 'video',
                'maxResults' => config('blog_automation.youtube.max_results', 5),
                'regionCode' => config('blog_automation.youtube.region', 'ZA'),
                'relevanceLanguage' => 'en',
                'key' => config('blog_automation.youtube.api_key'),
            ]);

            if (! $response->successful()) {
                continue;
            }

            foreach ($response->json('items', []) as $item) {
                $title = trim($item['snippet']['title'] ?? '');
                if ($title === '') {
                    continue;
                }

                $videoId = $item['id']['videoId'] ?? null;
                $topics[] = [
                    'title' => $title,
                    'source' => 'youtube',
                    'url' => $videoId ? "https://www.youtube.com/watch?v={$videoId}" : null,
                    'score' => 60,
                    'keywords' => explode(' ', $query),
                    'metadata' => ['channel' => $item['snippet']['channelTitle'] ?? null],
                ];
            }
        }

        return $topics;
    }

    /** @return list<array<string, mixed>> */
    protected function fromRssHeadlines(): array
    {
        $topics = [];

        foreach (config('news.rss_feeds', []) as $feed) {
            $response = Http::timeout(15)->withHeaders([
                'User-Agent' => 'UrbanFocusBlogBot/1.0',
            ])->get($feed['url']);

            if (! $response->successful()) {
                continue;
            }

            $xml = @simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml === false) {
                continue;
            }

            foreach (array_slice(iterator_to_array($xml->channel->item ?? []), 0, 3) as $item) {
                $title = trim(strip_tags((string) ($item->title ?? '')));
                if ($title === '') {
                    continue;
                }

                $topics[] = [
                    'title' => $title,
                    'source' => 'rss',
                    'url' => trim((string) ($item->link ?? '')),
                    'score' => 40,
                    'keywords' => [Str::slug($feed['name'] ?? 'rss')],
                    'metadata' => ['feed' => $feed['name'] ?? 'RSS'],
                ];
            }
        }

        return $topics;
    }
}
