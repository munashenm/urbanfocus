<?php

namespace App\Services\Blog;

use App\Models\Article;
use Illuminate\Support\Str;

class ArticleFeaturedImageService
{
    /**
     * @return array{
     *     url: string,
     *     webp: ?string,
     *     jpg: ?string,
     *     alt: string,
     *     width: int,
     *     height: int,
     *     path: string,
     *     remote: bool
     * }
     */
    public function resolve(Article $article): array
    {
        $width = (int) config('blog_images.width', 1200);
        $height = (int) config('blog_images.height', 675);

        if ($custom = $this->customImage($article)) {
            return array_merge($custom, [
                'alt' => $this->altFor($article, null),
                'width' => $width,
                'height' => $height,
            ]);
        }

        $assignment = $this->assignmentFor($article);

        return $this->filesFor($assignment['file'], $this->altFor($article, $assignment['alt'] ?? null), $width, $height);
    }

    public function alt(Article $article): string
    {
        return $this->resolve($article)['alt'];
    }

    public function url(Article $article): string
    {
        return $this->resolve($article)['url'];
    }

    /** @return array{url: string, webp: ?string, jpg: ?string, path: string, remote: bool}|null */
    protected function customImage(Article $article): ?array
    {
        $image = trim((string) ($article->image ?? ''));
        if ($image === '' || $this->isGenericPlaceholder($image)) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return [
                'url' => $image,
                'webp' => str_ends_with(strtolower(parse_url($image, PHP_URL_PATH) ?? ''), '.webp') ? $image : null,
                'jpg' => $image,
                'path' => $image,
                'remote' => true,
            ];
        }

        $url = storage_public_url($image) ?? asset(ltrim($image, '/'));

        return [
            'url' => $url,
            'webp' => str_ends_with(strtolower($image), '.webp') ? $url : null,
            'jpg' => $url,
            'path' => ltrim($image, '/'),
            'remote' => false,
        ];
    }

    protected function isGenericPlaceholder(string $image): bool
    {
        $normalized = strtolower($image);

        if (str_ends_with($normalized, '.svg')) {
            return true;
        }

        foreach (config('blog.category_placeholders', []) as $placeholder) {
            if ($placeholder && str_contains($normalized, strtolower((string) $placeholder))) {
                return true;
            }
        }

        return str_contains($normalized, 'images/blog/') && str_contains($normalized, '.svg');
    }

    /** @return array{file: string, alt: ?string} */
    protected function assignmentFor(Article $article): array
    {
        $slug = (string) $article->slug;
        $mapped = config("blog_images.slugs.{$slug}");
        if (is_array($mapped) && ! empty($mapped['file'])) {
            return [
                'file' => (string) $mapped['file'],
                'alt' => isset($mapped['alt']) ? (string) $mapped['alt'] : null,
            ];
        }

        $topic = $this->topicFor($article);
        $options = config("blog_images.topics.{$topic}", config('blog_images.topics.business', []));
        if ($options === []) {
            $options = [['file' => 'open-plan-office-it', 'alt' => 'Office computers in a professional workplace']];
        }

        $index = abs(crc32($slug !== '' ? $slug : $article->title)) % count($options);
        $choice = $options[$index];

        return [
            'file' => (string) ($choice['file'] ?? 'open-plan-office-it'),
            'alt' => isset($choice['alt']) ? (string) $choice['alt'] : null,
        ];
    }

    protected function topicFor(Article $article): string
    {
        $haystack = Str::lower(trim($article->slug.' '.$article->title.' '.$article->category));

        foreach (config('blog_images.keyword_topics', []) as $rule) {
            $pattern = (string) ($rule['pattern'] ?? '');
            $topic = (string) ($rule['topic'] ?? '');
            if ($pattern === '' || $topic === '') {
                continue;
            }

            if (@preg_match('/'.$pattern.'/i', $haystack)) {
                return $topic;
            }
        }

        $category = $article->categoryKey();
        if ($category && is_array(config("blog_images.topics.{$category}"))) {
            return $category;
        }

        return 'business';
    }

    protected function altFor(Article $article, ?string $configured): string
    {
        $configured = trim((string) $configured);
        if ($configured !== '') {
            return $configured;
        }

        $title = trim((string) $article->title);
        if ($title !== '') {
            return Str::limit($title, 110, '');
        }

        return 'Urban Focus Knowledge Centre article';
    }

    /**
     * @return array{
     *     url: string,
     *     webp: ?string,
     *     jpg: ?string,
     *     alt: string,
     *     width: int,
     *     height: int,
     *     path: string,
     *     remote: bool
     * }
     */
    protected function filesFor(string $file, string $alt, int $width, int $height): array
    {
        $directory = trim((string) config('blog_images.directory', 'images/blog/featured'), '/');
        $webpRel = $directory.'/'.$file.'.webp';
        $jpgRel = $directory.'/'.$file.'.jpg';
        $jpegRel = $directory.'/'.$file.'.jpeg';

        $webp = $this->publicFile($webpRel);
        $jpg = $this->publicFile($jpgRel) ?? $this->publicFile($jpegRel);

        $path = $webpRel;
        $url = $webp ?? $jpg;
        if ($url === null) {
            $fallback = $directory.'/open-plan-office-it.webp';
            $url = $this->publicFile($fallback) ?? asset($webpRel);
            $path = $fallback;
            $webp = $this->publicFile($fallback);
        }

        return [
            'url' => $url,
            'webp' => $webp,
            'jpg' => $jpg,
            'alt' => $alt,
            'width' => $width,
            'height' => $height,
            'path' => $path,
            'remote' => false,
        ];
    }

    protected function publicFile(string $relative): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        foreach ([public_path($relative), base_path('public/'.$relative)] as $full) {
            if (is_file($full)) {
                return public_asset_url($relative);
            }
        }

        return null;
    }
}
