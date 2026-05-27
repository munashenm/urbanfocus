<?php

namespace App\Services\Blog;

use App\Models\Article;
use App\Models\BlogAnalyticsSnapshot;
use Illuminate\Support\Facades\Http;

class BlogSearchConsoleService
{
    /** @return array{rows: list<array<string, mixed>>, summary: array<string, mixed>}|null */
    public function fetchSearchAnalytics(): ?array
    {
        if (! config('blog_automation.google_search_console.enabled')) {
            return null;
        }

        $token = $this->accessToken();
        if (! $token) {
            return null;
        }

        $siteUrl = urlencode(config('blog_automation.google_search_console.site_url'));
        $end = now()->subDay()->toDateString();
        $start = now()->subDays(config('blog_automation.google_search_console.days', 28))->toDateString();

        $response = Http::timeout(30)
            ->withToken($token)
            ->post("https://www.googleapis.com/webmasters/v3/sites/{$siteUrl}/searchAnalytics/query", [
                'startDate' => $start,
                'endDate' => $end,
                'dimensions' => ['page', 'query'],
                'rowLimit' => 100,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('GSC API '.$response->status().': '.$response->body());
        }

        $rows = $response->json('rows', []);
        $payload = [
            'rows' => $rows,
            'summary' => $this->summarize($rows),
            'period' => compact('start', 'end'),
        ];

        BlogAnalyticsSnapshot::updateOrCreate(
            ['snapshot_date' => now()->toDateString(), 'source' => 'gsc'],
            ['payload' => $payload]
        );

        return $payload;
    }

    /** @param list<array<string, mixed>> $rows */
    protected function summarize(array $rows): array
    {
        $clicks = 0;
        $impressions = 0;
        $blogRows = [];

        foreach ($rows as $row) {
            $clicks += (int) ($row['clicks'] ?? 0);
            $impressions += (int) ($row['impressions'] ?? 0);
            $page = $row['keys'][0] ?? '';
            if (str_contains($page, '/blog')) {
                $blogRows[] = $row;
            }
        }

        usort($blogRows, fn ($a, $b) => ($b['clicks'] ?? 0) <=> ($a['clicks'] ?? 0));

        return [
            'total_clicks' => $clicks,
            'total_impressions' => $impressions,
            'avg_ctr' => $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0,
            'top_blog_pages' => array_slice($blogRows, 0, 10),
        ];
    }

    public function latestSnapshot(): ?array
    {
        $snap = BlogAnalyticsSnapshot::where('source', 'gsc')->latest('snapshot_date')->first();

        return $snap?->payload;
    }

    protected function accessToken(): ?string
    {
        $path = config('blog_automation.google_search_console.credentials_path');
        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        $json = json_decode(file_get_contents($path), true);
        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            return null;
        }

        $now = time();
        $claim = [
            'iss' => $json['client_email'],
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode($claim)), '+/', '-_'), '=');
        $input = "{$header}.{$payload}";

        openssl_sign($input, $signature, $json['private_key'], OPENSSL_ALGO_SHA256);
        $jwt = $input.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        return $response->successful() ? $response->json('access_token') : null;
    }

    /** @return list<array<string, mixed>> */
    public function dashboardMetrics(): array
    {
        $gsc = $this->latestSnapshot();
        $published = Article::published()->count();
        $indexedEstimate = $published + count(config('seo_landings', [])) + 20;

        return [
            'published_articles' => $published,
            'indexed_pages_estimate' => $indexedEstimate,
            'gsc' => $gsc['summary'] ?? null,
            'top_queries' => array_slice($gsc['rows'] ?? [], 0, 15),
        ];
    }
}
