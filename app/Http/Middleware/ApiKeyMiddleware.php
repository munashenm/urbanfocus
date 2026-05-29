<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = Setting::get('api_key') ?: config('app.api_key');

        if (! $apiKey) {
            return response()->json(['error' => 'API is not configured.'], 503);
        }

        $provided = $request->header('X-API-Key')
            ?? $request->header('Authorization');

        if (str_starts_with((string) $provided, 'Bearer ')) {
            $provided = substr($provided, 7);
        }

        if (! hash_equals($apiKey, (string) $provided)) {
            return response()->json(['error' => 'Invalid API key.'], 401);
        }

        return $next($request);
    }
}
