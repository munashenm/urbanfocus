<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\SeoService;
use Illuminate\View\View;

class SeoLandingController extends Controller
{
    public function __construct(
        protected SeoService $seo,
    ) {}

    public function show(string $slug): View
    {
        $page = config("seo_landings.{$slug}");

        abort_unless(is_array($page), 404);

        $featuredProducts = $this->featuredProducts($page);
        $categories = $this->resolveCategories($page);
        $brands = $this->resolveBrands($page);

        $faqs = $page['faqs'] ?? [];
        $faqSchema = $faqs !== [] ? $this->seo->faqSchema($faqs) : [];

        return view('seo-landings.show', compact('slug', 'page', 'featuredProducts', 'categories', 'brands', 'faqs', 'faqSchema'));
    }

    /** @param array<string, mixed> $page */
    protected function featuredProducts(array $page)
    {
        $query = Product::with('images')
            ->where('is_active', true)
            ->availableInStock();

        if (! empty($page['brand_slug'])) {
            $brand = Brand::where('slug', $page['brand_slug'])->first();
            if ($brand) {
                $query->where('brand', $brand->name);
            }
        } elseif (! empty($page['brand_slugs'])) {
            $names = Brand::whereIn('slug', $page['brand_slugs'])->pluck('name');
            if ($names->isNotEmpty()) {
                $query->whereIn('brand', $names);
            }
        }

        if (! empty($page['category_slugs'])) {
            $categoryIds = $this->categoryIdsFromSlugs($page['category_slugs']);
            if ($categoryIds !== []) {
                $query->whereIn('category_id', $categoryIds);
            }
        }

        return $query->latest()->limit(8)->get();
    }

    /** @param array<string, mixed> $page */
    protected function resolveCategories(array $page)
    {
        $slugs = $page['category_slugs'] ?? [];

        return collect($slugs)
            ->map(fn (string $path) => $this->categoryFromPath($path))
            ->filter()
            ->values();
    }

    /** @param array<string, mixed> $page */
    protected function resolveBrands(array $page)
    {
        $slugs = array_filter(array_merge(
            [$page['brand_slug'] ?? null],
            $page['brand_slugs'] ?? []
        ));

        if ($slugs === []) {
            return collect();
        }

        return Brand::whereIn('slug', $slugs)->where('is_active', true)->get();
    }

    /** @param list<string> $paths */
    protected function categoryIdsFromSlugs(array $paths): array
    {
        $ids = [];

        foreach ($paths as $path) {
            $category = $this->categoryFromPath($path);
            if ($category) {
                $ids = array_merge($ids, Category::descendantIds($category->id));
            }
        }

        return array_values(array_unique($ids));
    }

    protected function categoryFromPath(string $path): ?Category
    {
        [$parentSlug, $childSlug] = array_pad(explode('/', $path, 2), 2, null);

        $parent = Category::where('slug', $parentSlug)->whereNull('parent_id')->first();

        if (! $parent) {
            return Category::where('slug', $path)->first();
        }

        if ($childSlug) {
            return Category::where('slug', $childSlug)->where('parent_id', $parent->id)->first()
                ?? $parent;
        }

        return $parent;
    }
}
