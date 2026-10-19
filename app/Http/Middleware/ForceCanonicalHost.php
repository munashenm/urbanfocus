<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Production-only redirect from the apex host to https://www.urbanfocus.co.za.
 * HTTP→HTTPS for other hosts is left to the web server / Cloudflare so local
 * HTTP and untrusted proxy schemes are not redirected into a loop.
 */
class ForceCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production')) {
            return $next($request);
        }

        $host = strtolower($request->getHost());
        if ($host === 'urbanfocus.co.za') {
            return redirect()->away('https://www.urbanfocus.co.za'.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
