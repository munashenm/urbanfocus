<?php

namespace App\Http\Controllers;

use App\Services\SeoService;
use Illuminate\View\View;

class CaseStudyController extends Controller
{
    public function __construct(protected SeoService $seo) {}

    public function index(): View
    {
        $items = $this->publishedItems();

        return view('case-studies.index', [
            'items' => $items,
            'breadcrumbSchema' => $this->seo->breadcrumbSchema([
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Case Studies', 'url' => route('case-studies.index')],
            ]),
        ]);
    }

    public function show(string $slug): View
    {
        $item = collect($this->publishedItems())->firstWhere('slug', $slug);
        abort_unless(is_array($item), 404);

        return view('case-studies.show', [
            'item' => $item,
            'breadcrumbSchema' => $this->seo->breadcrumbSchema([
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Case Studies', 'url' => route('case-studies.index')],
                ['name' => $item['title'], 'url' => route('case-studies.show', $item['slug'])],
            ]),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function publishedItems(): array
    {
        return array_values(array_filter(
            config('case_studies.items', []),
            fn ($item) => is_array($item) && ! empty($item['slug']) && ! empty($item['title']) && ($item['published'] ?? false)
        ));
    }
}
