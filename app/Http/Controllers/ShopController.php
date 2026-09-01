<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogBrowseService;
use App\Services\CategoryMapperService;
use App\Services\SearchService;
use App\Services\SeoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function __construct(
        protected SearchService $search,
        protected SeoService $seo,
        protected CategoryMapperService $categoryMapper,
        protected CatalogBrowseService $browse,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $searchTerm = trim((string) $request->get('q'));

        if ($categorySlug = trim((string) $request->get('category'), '/')) {
            $category = $this->categoryMapper->resolveCategoryForFilter($categorySlug);

            if ($category && $this->categoryMapper->isLegacyCategoryPath($categorySlug)) {
                $query = $request->except('category');
                $url = $category->url();

                if ($query !== []) {
                    $url .= '?'.http_build_query($query);
                }

                return redirect($url, 301);
            }
        }

        if ($searchTerm !== '') {
            $query = $this->search->productQuery($searchTerm);
            if ((clone $query)->count() === 0) {
                $query = $this->search->productQuery($searchTerm, fuzzy: true);
            }
            Product::applyStorefrontStockFilter($query, $request);
        } else {
            $query = Product::with(['category', 'images'])->where('is_active', true);
            Product::applyStorefrontStockFilter($query, $request);
        }

        if ($categorySlug = $request->get('category')) {
            $category = $this->categoryMapper->resolveCategoryForFilter((string) $categorySlug);
            if ($category) {
                $query->whereIn('category_id', Category::descendantIds($category->id));
            }
        }

        if ($brand = $request->get('brand')) {
            $query->where('brand', $brand);
        }

        if ($request->boolean('deals')) {
            $query->where(function ($q) {
                $q->whereNotNull('sale_price');
                if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'is_deal')) {
                    $q->orWhere('is_deal', true);
                }
            });
        }

        if ($min = $request->get('price_min')) {
            $query->whereRaw('COALESCE(sale_price, price) >= ?', [(float) $min]);
        }

        if ($max = $request->get('price_max')) {
            $query->whereRaw('COALESCE(sale_price, price) <= ?', [(float) $max]);
        }

        $sort = $this->browse->requestedSort($request);
        $this->browse->applySort($query, $sort, $searchTerm !== '' ? $searchTerm : null);

        $products = $query->paginate(24)->withQueryString();
        $categories = Category::where('is_active', true)->whereNull('parent_id')->visibleInCatalog()->with(['children' => fn ($q) => $q->where('is_active', true)->visibleInCatalog()->orderBy('sort_order')])->orderBy('sort_order')->get();
        $brands = Product::where('is_active', true)->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand');

        return view('shop.index', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'currentSort' => $sort,
            'paginationMeta' => $this->seo->paginationMeta($products),
            'breadcrumbSchema' => $this->seo->breadcrumbSchema([
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Shop', 'url' => route('shop.index')],
            ]),
            'collectionPageSchema' => $this->seo->collectionPageSchema(
                'Shop IT Products',
                route('shop.index'),
                'Browse laptops, desktops, networking, storage and software from Urban Focus.',
                $products,
            ),
        ]);
    }
}
