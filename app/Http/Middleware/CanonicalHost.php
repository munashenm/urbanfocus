<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 301 non-canonical hosts to APP_URL in production.
 *
 * urbanfocus.co.za and mail.urbanfocus.co.za were serving the full store.
 * The redirect preserves the path and query string. It runs only when
 * APP_ENV=production and APP_URL is a real hostname, so local dev
 * (APP_ENV=local, or an APP_URL of localhost) is unchanged.
 *
 * Skipped paths:
 * - /admin and /admin/* so a cPanel or temporary hostname can still open
 *   the dashboard without bouncing the session onto www.
 * - /checkout/paystack and /checkout/paystack/* so Paystack's browser
 *   callback and server webhook are not turned into a redirect (a 301 on
 *   the webhook POST would drop the body).
 *
 * Prefer this middleware over an .htaccess-only rule: Apache cannot see
 * APP_ENV, and a host rule in public_html/.htaccess is easy to miss when
 * PUBLIC_PATH points at a different document root.
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production')) {
            return $next($request);
        }

        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        $canonical = $this->canonicalRoot();
        $canonicalHost = strtolower((string) parse_url($canonical, PHP_URL_HOST));
        $requestHost = strtolower($request->getHost());

        if ($canonical === '' || $canonicalHost === '' || $this->isLocalHost($canonicalHost) || $this->isLocalHost($requestHost)) {
            return $next($request);
        }

        if ($requestHost === $canonicalHost) {
            return $next($request);
        }

        $uri = $request->getRequestUri();
        if (! str_starts_with($uri, '/')) {
            $uri = '/'.$uri;
        }

        return redirect()->away($canonical.$uri, 301);
    }

    protected function shouldBypass(Request $request): bool
    {
        return $request->is(
            'admin',
            'admin/*',
            'checkout/paystack',
            'checkout/paystack/*',
            'up',
        );
    }

    protected function canonicalRoot(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    protected function isLocalHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
