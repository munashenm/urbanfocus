<?php

namespace App\Http\Controllers;

use App\Support\PublicAssetSync;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicAssetController extends Controller
{
    /** @var list<string> */
    private const ALLOWED_ROOT_FILES = [
        'favicon.svg',
        'favicon.png',
        'robots.txt',
    ];

    public function show(string $path, ?string $prefix = null): BinaryFileResponse|Response
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $relative = $prefix ? rtrim($prefix, '/').'/'.$path : $path;

        if ($path === '' || str_contains($path, '..') || str_contains($relative, '..')) {
            abort(404);
        }

        if (! $this->isAllowedPath($relative)) {
            abort(404);
        }

        $absolute = $this->resolveFile($relative);

        if (! $absolute) {
            abort(404);
        }

        PublicAssetSync::ensureFile($relative);

        return response()->file($absolute, [
            'Content-Type' => $this->mimeType($relative),
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    protected function isAllowedPath(string $path): bool
    {
        if (in_array($path, self::ALLOWED_ROOT_FILES, true)) {
            return true;
        }

        return str_starts_with($path, 'css/')
            || str_starts_with($path, 'js/')
            || str_starts_with($path, 'images/');
    }

    protected function resolveFile(string $path): ?string
    {
        $roots = array_unique(array_filter([
            realpath(public_path()),
            realpath(base_path('public')),
        ]));

        foreach ($roots as $root) {
            $candidate = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
            $resolved = realpath($candidate);

            if (! $resolved || ! is_file($resolved)) {
                continue;
            }

            if (! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR) && $resolved !== $root) {
                continue;
            }

            return $resolved;
        }

        return null;
    }

    protected function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'txt' => 'text/plain; charset=UTF-8',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            default => 'application/octet-stream',
        };
    }
}
