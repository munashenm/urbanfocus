<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\CategoryMapperService;
use App\Services\SeoService;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function __construct(
        protected SeoService $seo,
        protected CategoryMapperService $categoryMapper,
    ) {}

    public function index(): View
    {
        $brands = Schema::hasTable('brands')
            ? Brand::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
            : collect();

        return view('brands.index', compact('brands'));
    }

    public function show(Brand $brand): View
    {
        abort_unless($brand->is_active, 404);

        $brandSeo = config("brand_seo.{$brand->slug}", []);
        $pagination = null;

        $products = Product::with(['category', 'images'])
            ->where('is_active', true)
            ->withoutDuplicateListings()
            ->availableInStock()
            ->where('brand', $brand->name)
            ->latest()
            ->paginate(24);

        $pagination = $this->seo->paginationMeta($products);

        $featuredProducts = Product::with('images')
            ->where('is_active', true)
            ->availableInStock()
            ->where('brand', $brand->name)
            ->where('is_featured', true)
            ->latest()
            ->limit(4)
            ->get();

        if ($featuredProducts->isEmpty()) {
            $featuredProducts = $products->take(4);
        }

        $linkCategories = collect($brandSeo['links'] ?? [])
            ->map(function (array $link) {
                $path = $link['category_path'] ?? $link['category'] ?? null;

                if (! $path) {
                    return null;
                }

                return $this->categoryMapper->resolveCategoryForFilter($path);
            })
            ->filter()
            ->values();

        $faqs = $brandSeo['faqs'] ?? [];
        $faqSchema = $faqs !== [] ? $this->seo->faqSchema($faqs) : [];

        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->visibleInCatalog()
            ->with(['children' => fn ($q) => $q->where('is_active', true)->visibleInCatalog()->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return view('brands.show', compact(
            'brand',
            'brandSeo',
            'products',
            'featuredProducts',
            'linkCategories',
            'categories',
            'pagination',
            'faqs',
            'faqSchema',
        ));
    }
}
