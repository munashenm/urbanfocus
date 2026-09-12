<?php

namespace App\Http\Middleware;

use App\Services\SeoIndexPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ControlSearchIndex
{
    public function __construct(
        protected SeoIndexPolicy $policy,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $directive = $this->policy->robotsDirective($request);
        if ($directive && ! $response->headers->has('X-Robots-Tag')) {
            $response->headers->set('X-Robots-Tag', $directive);
        }

        return $response;
    }
}
