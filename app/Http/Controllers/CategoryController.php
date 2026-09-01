<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategorySlugRedirect;
use App\Models\Product;
use App\Services\CatalogBrowseService;
use App\Services\CatalogFilterService;
use App\Services\CategoryMapperService;
use App\Services\SeoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        protected CatalogFilterService $catalogFilter,
        protected CategoryMapperService $categoryMapper,
        protected SeoService $seo,
        protected CatalogBrowseService $browse,
    ) {}

    public function show(string $category, Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectForLegacySlug($category)) {
            return $redirect;
        }

        $categoryModel = $this->resolveCategoryBySlug($category);

        if ($categoryModel->parent_id) {
            return redirect($categoryModel->url(), 301);
        }

        return $this->renderCategory($categoryModel, $request);
    }

    public function showChild(string $parent, string $child, Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectForLegacySlug($child)) {
            return $redirect;
        }

        [$parentModel, $childModel] = $this->resolveChildCategories($parent, $child);

        return $this->renderCategory($childModel, $request, $parentModel);
    }

    protected function resolveCategoryBySlug(string $slug): Category
    {
        $this->categoryMapper->ensureCanonicalTree();

        return Category::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /** @return array{0: Category, 1: Category} */
    protected function resolveChildCategories(string $parentSlug, string $childSlug): array
    {
        $this->categoryMapper->ensureCanonicalTree();

        $parent = Category::query()
            ->where('slug', $parentSlug)
            ->whereNull('parent_id')
            ->firstOrFail();

        $child = Category::query()
            ->where('slug', $childSlug)
            ->where('parent_id', $parent->id)
            ->firstOrFail();

        return [$parent, $child];
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

        $sort = $this->browse->requestedSort($request);
        $this->browse->applySort($query, $sort);

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
            'currentSort' => $sort,
            'paginationMeta' => $this->seo->paginationMeta($products),
            'breadcrumbSchema' => $this->seo->breadcrumbSchema($breadcrumbs),
            'collectionPageSchema' => $this->seo->collectionPageSchema(
                $category->name,
                $category->url(),
                $category->description ?: 'Browse '.$category->name.' at Urban Focus',
                $products,
            ),
        ]);
    }
}
