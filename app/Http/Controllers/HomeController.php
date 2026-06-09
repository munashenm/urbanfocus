<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Article;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $featuredProducts = $this->remember('home.featured', fn () => Product::with('images')
            ->forStorefront()->where('is_featured', true)->latest()->take(8)->get());

        $dealProducts = $this->remember('home.deals', function () {
            $q = Product::with('images')->forStorefront();
            if (Schema::hasColumn('products', 'is_deal')) {
                $q->where(fn ($w) => $w->where('is_deal', true)->orWhereNotNull('sale_price'));
            } else {
                $q->whereNotNull('sale_price');
            }

            return $q->latest()->take(8)->get();
        });

        $topSellers = $this->remember('home.top_sellers_v2', fn () => $this->topSellerProducts(8));

        $networkingProducts = $this->remember('home.networking_v3', fn () => $this->networkingShowcaseProducts(8));

        $laptopProducts = $this->remember('home.laptops_v2', fn () => $this->categoryProducts(
            'laptops-notebooks',
            8,
            config('homepage.section_product_brands.laptops-notebooks', [])
        ));

        $categories = $this->remember('home.categories', fn () => Category::where('is_active', true)
            ->whereNull('parent_id')
            ->visibleInCatalog()
            ->with(['children' => fn ($q) => $q->where('is_active', true)->visibleInCatalog()->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->take(12)
            ->get());

        $newProducts = $this->remember('home.new', fn () => Product::with('images')
            ->forStorefront()->latest()->take(8)->get());

        $brands = $this->remember('home.brands', function () {
            if (Schema::hasTable('brands')) {
                return Brand::where('is_active', true)->orderBy('sort_order')->take(20)->get();
            }

            return Product::where('is_active', true)->whereNotNull('brand')->distinct()->pluck('brand')
                ->take(12)
                ->map(fn ($name) => (object) ['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'logo' => null]);
        });

        $banners = Schema::hasTable('banners')
            ? Banner::active('home')->take(4)->get()
            : collect();

        $featuredArticle = null;
        $articles = collect();
        if (Schema::hasTable('articles')) {
            if (Schema::hasColumn('articles', 'is_featured')) {
                $featuredArticle = Article::published()->where('is_featured', true)->latest('published_at')->first();
            }

            $latestQuery = Article::published();
            if (Schema::hasColumn('articles', 'category')) {
                $latestQuery->where('category', '!=', 'news');
            }
            $latestQuery->latest('published_at');
            if ($featuredArticle) {
                $latestQuery->where('id', '!=', $featuredArticle->id);
            }
            $articles = $latestQuery->take(3)->get();
        }

        $heroSlides = config('homepage.hero_slides', []);
        $solutionBlocks = config('homepage.solution_blocks', []);
        $categoryIcons = config('homepage.category_icons', []);
        $sectionBrands = $this->remember('home.section_brands', fn () => $this->sectionBrands());

        return view('home', compact(
            'featuredProducts', 'dealProducts', 'topSellers', 'networkingProducts',
            'laptopProducts', 'categories', 'newProducts', 'brands', 'banners',
            'articles', 'featuredArticle', 'heroSlides', 'solutionBlocks', 'categoryIcons', 'sectionBrands'
        ));
    }

    /** @return array<string, \Illuminate\Support\Collection<int, Brand>> */
    protected function sectionBrands(): array
    {
        $map = config('homepage.section_brands', []);
        $result = [];

        if (! Schema::hasTable('brands') || $map === []) {
            return $result;
        }

        $allSlugs = collect($map)->flatten()->unique()->values()->all();
        $bySlug = Brand::where('is_active', true)
            ->whereIn('slug', $allSlugs)
            ->get()
            ->keyBy('slug');

        foreach ($map as $section => $slugs) {
            $result[$section] = collect($slugs)
                ->map(fn (string $slug) => $bySlug->get($slug))
                ->filter()
                ->values();
        }

        return $result;
    }

    /**
     * @param  list<string>  $preferredBrandSlugs
     */
    protected function categoryProducts(string $slug, int $limit, array $preferredBrandSlugs = [])
    {
        $category = Category::where('slug', $slug)->first();
        if (! $category) {
            return collect();
        }

        $ids = Category::descendantIds($category->id);

        $base = Product::with('images')
            ->forStorefront()
            ->whereIn('category_id', $ids);

        if ($preferredBrandSlugs !== []) {
            $brandNames = $this->brandNamesForSlugs($preferredBrandSlugs);

            if ($brandNames !== []) {
                $preferred = (clone $base)
                    ->whereIn(DB::raw('LOWER(TRIM(brand))'), $brandNames)
                    ->latest()
                    ->take($limit)
                    ->get();

                if ($preferred->isNotEmpty()) {
                    return $preferred;
                }
            }
        }

        return $base->latest()->take($limit)->get();
    }

    protected function networkingShowcaseProducts(int $limit)
    {
        $config = config('homepage.networking_showcase', []);
        $brandSlugs = $config['brand_slugs'] ?? [];
        $brandNames = $this->brandNamesForSlugs($brandSlugs);

        if ($brandNames === []) {
            return collect();
        }

        $categoryIds = $this->categoryIdsForSlugs($config['category_slugs'] ?? []);
        if ($categoryIds === []) {
            $categoryIds = $this->categoryIdsForSlugs(['networking']);
        }

        $query = Product::with('images')
            ->forStorefront()
            ->whereIn('category_id', $categoryIds)
            ->whereIn(DB::raw('LOWER(TRIM(brand))'), $brandNames);

        $this->applyExcludedBrands($query, $config['exclude_brands'] ?? []);
        $this->applyExcludedNameTerms($query, $config['exclude_name_terms'] ?? []);

        $query->orderByRaw(
            "CASE
                WHEN LOWER(name) LIKE '%switch%' OR LOWER(name) LIKE '% crs%' OR LOWER(name) LIKE '%catalyst%' THEN 0
                WHEN LOWER(name) LIKE '%access point%' OR LOWER(name) LIKE '% u6-%' OR LOWER(name) LIKE '% u7-%' OR LOWER(name) LIKE '%unifi ap%' THEN 1
                WHEN LOWER(name) LIKE '%router%' OR LOWER(name) LIKE '%routerboard%' OR LOWER(name) LIKE '% hap%' OR LOWER(name) LIKE '%gateway%' THEN 2
                ELSE 3
            END"
        );

        $results = $query
            ->when(Schema::hasColumn('products', 'views'), fn ($q) => $q->orderByDesc('views'), fn ($q) => $q->latest())
            ->take($limit)
            ->get();

        if ($results->count() < min(4, $limit)) {
            $fallback = $this->networkingShowcaseFallback($limit, $brandNames, $config);

            return $fallback->count() > $results->count() ? $fallback : $results;
        }

        return $results;
    }

    /** @param list<string> $brandNames @param array<string, mixed> $config */
    protected function networkingShowcaseFallback(int $limit, array $brandNames, array $config)
    {
        $categoryIds = $this->categoryIdsForSlugs(['networking']);

        $query = Product::with('images')
            ->forStorefront()
            ->whereIn('category_id', $categoryIds)
            ->whereIn(DB::raw('LOWER(TRIM(brand))'), $brandNames);

        $this->applyExcludedBrands($query, $config['exclude_brands'] ?? []);
        $this->applyExcludedNameTerms($query, $config['exclude_name_terms'] ?? []);

        $query->orderByRaw(
            "CASE
                WHEN LOWER(name) LIKE '%switch%' OR LOWER(name) LIKE '% crs%' OR LOWER(name) LIKE '%catalyst%' THEN 0
                WHEN LOWER(name) LIKE '%access point%' OR LOWER(name) LIKE '% u6-%' OR LOWER(name) LIKE '% u7-%' OR LOWER(name) LIKE '%unifi ap%' THEN 1
                WHEN LOWER(name) LIKE '%router%' OR LOWER(name) LIKE '%routerboard%' OR LOWER(name) LIKE '% hap%' OR LOWER(name) LIKE '%gateway%' THEN 2
                ELSE 3
            END"
        );

        return $query
            ->when(Schema::hasColumn('products', 'views'), fn ($q) => $q->orderByDesc('views'), fn ($q) => $q->latest())
            ->take($limit)
            ->get();
    }

    /** @param list<string> $brands */
    protected function applyExcludedBrands($query, array $brands): void
    {
        foreach ($brands as $brand) {
            $brand = mb_strtolower(trim($brand));
            if ($brand !== '') {
                $query->whereRaw('LOWER(TRIM(brand)) != ?', [$brand]);
            }
        }
    }

    /** @param list<string> $terms */
    protected function applyExcludedNameTerms($query, array $terms): void
    {
        foreach ($terms as $term) {
            $term = trim($term);
            if ($term !== '') {
                $query->where('name', 'not like', '%'.$term.'%');
            }
        }
    }

    /** @param list<string> $slugs @return list<string> */
    protected function brandNamesForSlugs(array $slugs): array
    {
        if (! Schema::hasTable('brands')) {
            return [];
        }

        return Brand::where('is_active', true)
            ->whereIn('slug', $slugs)
            ->pluck('name')
            ->map(fn (string $name) => mb_strtolower(trim($name)))
            ->values()
            ->all();
    }

    protected function topSellerProducts(int $limit)
    {
        $brands = config('homepage.top_seller_brands', []);
        $categorySlugs = config('homepage.top_seller_categories', []);

        $query = Product::with('images')->forStorefront();

        if ($brands !== []) {
            $normalized = array_map(fn (string $brand) => mb_strtolower(trim($brand)), $brands);
            $query->whereIn(DB::raw('LOWER(TRIM(brand))'), $normalized);
        }

        $categoryIds = $this->categoryIdsForSlugs($categorySlugs);
        if ($categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        }

        return $query
            ->when(Schema::hasColumn('products', 'views'), fn ($q) => $q->orderByDesc('views'), fn ($q) => $q->latest())
            ->take($limit)
            ->get();
    }

    /** @param list<string> $slugs */
    protected function categoryIdsForSlugs(array $slugs): array
    {
        $ids = [];

        foreach ($slugs as $slug) {
            $category = Category::where('slug', $slug)->first();
            if ($category) {
                $ids = array_merge($ids, Category::descendantIds($category->id));
            }
        }

        return array_values(array_unique($ids));
    }

    protected function remember(string $key, callable $callback, int $minutes = 10): mixed
    {
        try {
            return Cache::remember($key, $minutes * 60, $callback);
        } catch (\Throwable) {
            return $callback();
        }
    }
}
