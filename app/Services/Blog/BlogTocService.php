<?php

namespace App\Services\Blog;

use Illuminate\Support\Str;

class BlogTocService
{
    /** @return array{html: string, items: list<array{id: string, text: string, level: int}>} */
    public function process(string $html): array
    {
        $items = [];
        $index = 0;

        $processed = preg_replace_callback('/<h([23])[^>]*>(.*?)<\/h\1>/is', function (array $match) use (&$items, &$index) {
            $level = (int) $match[1];
            $text = trim(strip_tags($match[2]));
            if ($text === '') {
                return $match[0];
            }

            $id = 'section-'.(++$index).'-'.Str::slug($text);
            $items[] = ['id' => $id, 'text' => $text, 'level' => $level];

            return "<h{$level} id=\"{$id}\">{$match[2]}</h{$level}>";
        }, $html) ?? $html;

        return ['html' => $processed, 'items' => $items];
    }
}
