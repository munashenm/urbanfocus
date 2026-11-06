<?php

namespace App\Http\Controllers;

use App\Models\Brand;
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
        if ($redirect = $this->redirectForLegacySlug($category, $category)) {
            return $redirect;
        }

        $categoryModel = $this->resolveCategoryBySlug($category);

        if ($categoryModel->parent_id) {
            $categoryModel->loadMissing('parent');
            if ($categoryModel->parent) {
                $target = trim(parse_url($categoryModel->url(), PHP_URL_PATH) ?? '', '/');
                $current = trim('category/'.$category, '/');
                if (strcasecmp($target, $current) !== 0) {
                    return redirect($categoryModel->url(), 301);
                }
            }
        }

        if ($redirect = $this->redirectIfCatalogExcluded($categoryModel)) {
            return $redirect;
        }

        return $this->renderCategory($categoryModel, $request);
    }

    public function showChild(string $parent, string $child, Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectForLegacySlug($child, $parent.'/'.$child)) {
            return $redirect;
        }

        [$parentModel, $childModel] = $this->resolveChildCategories($parent, $child);

        if ($redirect = $this->redirectIfCatalogExcluded($childModel)) {
            return $redirect;
        }

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

    protected function redirectForLegacySlug(string $slug, string $currentPath): ?RedirectResponse
    {
        $targetPath = $this->resolveRedirectTarget($slug);

        if ($targetPath === null) {
            return null;
        }

        $targetPath = trim($targetPath, '/');
        $currentPath = trim($currentPath, '/');

        if ($targetPath === '' || strcasecmp($targetPath, $currentPath) === 0) {
            return null;
        }

        $url = str_contains($targetPath, '/')
            ? url('/category/'.$targetPath)
            : route('categories.show', $targetPath);

        return redirect($url, 301);
    }

    /**
     * Follow category_slug_redirects, stopping on a self-target or a cycle.
     * A row whose target is the slug itself must not 301 the canonical URL to itself.
     */
    protected function resolveRedirectTarget(string $slug): ?string
    {
        $seen = [];
        $current = trim($slug, '/');
        $resolved = null;

        for ($hop = 0; $hop < 6; $hop++) {
            $next = CategorySlugRedirect::targetForSlug($current);
            if (! is_string($next) || trim($next) === '') {
                break;
            }

            $next = trim($next, '/');
            if (strcasecmp($next, $current) === 0 || isset($seen[strtolower($next)])) {
                return $resolved;
            }

            $seen[strtolower($current)] = true;
            $resolved = $next;

            if (str_contains($next, '/')) {
                return $next;
            }

            $current = $next;
        }

        return $resolved;
    }

    /**
     * Legacy top-level categories outside the canonical tree used to 301 to
     * themselves. Send those URLs to computer accessories instead of a loop or a 404.
     * Names that the catalogue filter bans still 404.
     */
    protected function redirectIfCatalogExcluded(Category $category): ?RedirectResponse
    {
        if ($this->catalogFilter->isExcludedName($category->name)) {
            abort(404);
        }

        if (! $this->catalogFilter->isCategoryExcluded($category)) {
            return null;
        }

        $fallback = Category::query()->where('slug', 'computer-accessories')->whereNotNull('parent_id')->first();
        if ($fallback && $fallback->id !== $category->id) {
            return redirect($fallback->url(), 301);
        }

        abort(404);
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

        if ($category->slug === 'switches') {
            $this->categoryMapper->excludeMisplacedSwitchProducts($query);
        }

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

        $brandQuery = Product::whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->whereNotNull('brand');
        if ($category->slug === 'switches') {
            $this->categoryMapper->excludeMisplacedSwitchProducts($brandQuery);
        }
        $brands = $brandQuery->distinct()->orderBy('brand')->pluck('brand');

        $relatedBrandModels = $brands->isNotEmpty()
            ? Brand::query()->where('is_active', true)->whereIn('name', $brands)->orderBy('name')->get()
            : collect();

        $navigation = app(\App\Services\CatalogNavigation::class);
        $subcategories = $navigation->filterStocked($category->children, false);

        $siblingCategories = collect();
        if ($category->parent_id) {
            $siblingCategories = $navigation->filterStocked(
                Category::query()
                    ->where('parent_id', $category->parent_id)
                    ->where('is_active', true)
                    ->visibleInCatalog()
                    ->orderBy('sort_order')
                    ->get(),
                false
            );
        }

        $breadcrumbs = [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Shop', 'url' => route('shop.index')],
        ];

        foreach ($category->breadcrumbChain() as $crumb) {
            $breadcrumbs[] = ['name' => $crumb['name'], 'url' => $crumb['category']->url()];
        }

        $hasFilters = $request->hasAny(['brand', 'price_min', 'price_max', 'filter_brand', 'filter_cat', 'min_price', 'max_price', 'q'])
            || ! $this->browse->isDefaultSort($request);

        $paginationMeta = $hasFilters
            ? [
                'canonical' => seo_canonical_url($category->url()),
                'prev' => $products->previousPageUrl(),
                'next' => $products->nextPageUrl(),
            ]
            : $this->seo->paginationMeta($products, $category->url());

        return view('categories.show', [
            'category' => $category,
            'products' => $products,
            'brands' => $brands,
            'relatedBrandModels' => $relatedBrandModels,
            'subcategories' => $subcategories,
            'siblingCategories' => $siblingCategories,
            'canonicalUrl' => $category->url(),
            'currentSort' => $sort,
            'paginationMeta' => $paginationMeta,
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
