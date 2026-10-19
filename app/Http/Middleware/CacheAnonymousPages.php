<?php

namespace App\Http\Middleware;

use App\Services\CatalogNavigation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Short anonymous HTML cache. The CSRF token is swapped for a placeholder
 * before storage and restored on a hit so a cached page cannot reuse another
 * visitor's token. Full-page CDN caching of HTML is only safe with the same
 * placeholder (see the PR notes) because the session cookie otherwise marks
 * the response uncacheable.
 */
class CacheAnonymousPages
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldAttempt($request)) {
            return $next($request);
        }

        $key = $this->cacheKey($request);
        $cached = Cache::get($key);

        if (is_string($cached) && $cached !== '') {
            $html = str_replace('___UF_CSRF___', csrf_token(), $cached);

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => $this->cacheControl(),
                'X-UF-Page-Cache' => 'HIT',
            ]);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($this->isCacheableResponse($request, $response)) {
            $html = (string) $response->getContent();
            $token = csrf_token();
            if ($token !== '' && str_contains($html, $token)) {
                Cache::put($key, str_replace($token, '___UF_CSRF___', $html), 120);
                $response->headers->set('Cache-Control', $this->cacheControl());
                $response->headers->set('X-UF-Page-Cache', 'MISS');
            }
        }

        return $response;
    }

    protected function shouldAttempt(Request $request): bool
    {
        if (app()->runningUnitTests() || app()->runningInConsole()) {
            return false;
        }

        if (! $request->isMethod('GET') || $request->ajax()) {
            return false;
        }

        if ($request->user()) {
            return false;
        }

        $cart = session('cart', []);
        if (is_array($cart) && $cart !== []) {
            return false;
        }

        if (session()->has('success') || session()->has('error') || session()->has('status')) {
            return false;
        }

        $path = $request->path();
        foreach (['admin', 'cart', 'checkout', 'account', 'login', 'register', 'password', 'api', 'storage'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return false;
            }
        }

        return true;
    }

    protected function isCacheableResponse(Request $request, Response $response): bool
    {
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $type = (string) $response->headers->get('Content-Type', '');
        if ($type !== '' && ! str_contains($type, 'text/html')) {
            return false;
        }

        if ($response->headers->has('Set-Cookie') && $request->hasSession() && $request->session()->isStarted()) {
            // The session cookie is set on the first visit. Cache the HTML body
            // only; the live response still sends its own Set-Cookie.
        }

        return true;
    }

    protected function cacheKey(Request $request): string
    {
        $version = (int) Cache::get(CatalogNavigation::PAGE_VERSION_KEY, 1);

        return 'page.html.'.$version.'.'.sha1($request->getPathInfo().'?'.$request->getQueryString());
    }

    protected function cacheControl(): string
    {
        return 'public, max-age=0, s-maxage=300, stale-while-revalidate=600';
    }
}
