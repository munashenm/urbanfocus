<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user || ! $user->canAccessAdmin()) {
            abort(403, 'Unauthorized');
        }

        if (! $user->is_active) {
            auth()->logout();
            abort(403, 'Your account has been deactivated.');
        }

        return $next($request);
    }
}
