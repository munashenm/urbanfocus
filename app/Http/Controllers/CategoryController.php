<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategorySlugRedirect;
use App\Models\Product;
use App\Services\CatalogFilterService;
use App\Services\SeoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        protected CatalogFilterService $catalogFilter,
        protected SeoService $seo,
    ) {}

    public function show(Category $category, Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectForLegacySlug($category->slug)) {
            return $redirect;
        }

        if ($category->parent_id) {
            return redirect($category->url(), 301);
        }

        return $this->renderCategory($category, $request);
    }

    public function showChild(Category $parent, Category $child, Request $request): View|RedirectResponse
    {
        if ($child->parent_id !== $parent->id) {
            abort(404);
        }

        if ($redirect = $this->redirectForLegacySlug($child->slug)) {
            return $redirect;
        }

        return $this->renderCategory($child, $request, $parent);
    }

    protected function redirectForLegacySlug(string $slug): ?RedirectResponse
    {
        $targetPath = CategorySlugRedirect::targetForSlug($slug);

        if (! $targetPath) {
            return null;
        }

        $url = str_contains($targetPath, '/')
            ? url('/category/'.$targetPath)
            : route('categories.show', $targetPath);

        return redirect($url, 301);
    }

    protected function renderCategory(Category $category, Request $request, ?Category $parent = null): View
    {
        if ($this->catalogFilter->isCategoryExcluded($category)) {
            abort(404);
        }

        $category->load([
            'parent',
            'children' => fn ($q) => $q->where('is_active', true)->visibleInCatalog()->orderBy('sort_order'),
        ]);

        $categoryIds = Category::descendantIds($category->id);

        $query = Product::with('images')
            ->whereIn('category_id', $categoryIds)
            ->where('is_active', true);

        Product::applyStorefrontStockFilter($query, $request);

        if ($brand = $request->get('brand')) {
            $query->where('brand', $brand);
        }

        if ($min = $request->get('price_min')) {
            $query->whereRaw('COALESCE(sale_price, price) >= ?', [(float) $min]);
        }

        if ($max = $request->get('price_max')) {
            $query->whereRaw('COALESCE(sale_price, price) <= ?', [(float) $max]);
        }

        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'price_asc' => $query->orderByRaw('COALESCE(sale_price, price) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(sale_price, price) DESC'),
            'name' => $query->orderBy('name'),
            'popular' => $query->orderByDesc('views'),
            default => $query->latest(),
        };

        $products = $query->paginate(24)->withQueryString();

        $brands = Product::whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->whereNotNull('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        $subcategories = $category->children;

        $siblingCategories = collect();
        if ($category->parent_id) {
            $siblingCategories = Category::query()
                ->where('parent_id', $category->parent_id)
                ->where('is_active', true)
                ->visibleInCatalog()
                ->orderBy('sort_order')
                ->get();
        }

        $breadcrumbs = [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Shop', 'url' => route('shop.index')],
        ];

        foreach ($category->breadcrumbChain() as $crumb) {
            $breadcrumbs[] = ['name' => $crumb['name'], 'url' => $crumb['category']->url()];
        }

        return view('categories.show', [
            'category' => $category,
            'products' => $products,
            'brands' => $brands,
            'subcategories' => $subcategories,
            'siblingCategories' => $siblingCategories,
            'canonicalUrl' => $category->url(),
            'paginationMeta' => $this->seo->paginationMeta($products),
            'breadcrumbSchema' => $this->seo->breadcrumbSchema($breadcrumbs),
        ]);
    }
}
