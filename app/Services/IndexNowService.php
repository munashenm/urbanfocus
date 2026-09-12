<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IndexNow: notify Bing and compatible engines when public URLs change.
 * Submissions are flushed once per PHP request so catalogue imports do not fan out HTTP calls.
 */
class IndexNowService
{
    /** @var array<string, true> */
    protected static array $pending = [];

    public function enabled(): bool
    {
        return $this->key() !== '' && (bool) config('seo.indexing.indexnow_enabled', true);
    }

    public function key(): string
    {
        $key = trim((string) config('seo.indexing.indexnow_key', ''));

        if ($key === '' || ! preg_match('/^[A-Za-z0-9\-]{8,128}$/', $key)) {
            return '';
        }

        return $key;
    }

    public function keyFileUrl(): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        return rtrim((string) config('app.url'), '/').'/'.$this->key().'.txt';
    }

    /**
     * Queue canonical URL(s) for a single IndexNow POST at request shutdown.
     *
     * @param  string|list<string>  $urls
     */
    public function notify(string|array $urls): void
    {
        if (! $this->enabled()) {
            return;
        }

        foreach ((array) $urls as $url) {
            $canonical = function_exists('seo_canonical_url')
                ? seo_canonical_url((string) $url)
                : (string) $url;
            $canonical = trim($canonical);
            if ($canonical === '' || ! str_starts_with($canonical, 'http')) {
                continue;
            }
            self::$pending[$canonical] = true;
        }

        $this->registerFlush();
    }

    public function flush(): void
    {
        if (! $this->enabled() || self::$pending === []) {
            self::$pending = [];

            return;
        }

        $urlList = array_keys(self::$pending);
        self::$pending = [];

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'www.urbanfocus.co.za';
        $endpoint = (string) config('seo.indexing.indexnow_endpoint', 'https://api.indexnow.org/indexnow');

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, [
                    'host' => $host,
                    'key' => $this->key(),
                    'keyLocation' => $this->keyFileUrl(),
                    'urlList' => array_values($urlList),
                ]);

            if ($response->failed()) {
                Log::notice('IndexNow rejected a payload', [
                    'status' => $response->status(),
                    'urls' => count($urlList),
                ]);
            }
        } catch (\Throwable $e) {
            Log::notice('IndexNow request failed', ['error' => $e->getMessage()]);
        }
    }

    public static function resetState(): void
    {
        self::$pending = [];
    }

    /** @return list<string> */
    public static function pendingUrls(): array
    {
        return array_keys(self::$pending);
    }

    protected function registerFlush(): void
    {
        if (app()->bound('indexnow.flushing')) {
            return;
        }

        app()->instance('indexnow.flushing', true);
        app()->terminating(fn () => $this->flush());
    }
}
