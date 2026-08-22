<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogDeduper;
use App\Services\CategoryMapperService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $limit = (int) config('homepage.row_limit', 8);

        [$popularProducts, $topSellers, $laptopProducts, $networkingProducts, $securityProducts, $featuredProducts] = $this->remember(
            'home.product_rows_v1',
            fn () => $this->homepageProductRows($limit)
        );

        $categories = $this->remember('home.categories_v2', fn () => $this->homepageCategories(12));

        if ($featuredProducts->count() < 4) {
            $featuredProducts = collect();
        }

        $brands = $this->remember('home.brands', function () {
            if (Schema::hasTable('brands')) {
                return Brand::where('is_active', true)->orderBy('sort_order')->take(20)->get();
            }

            return Product::where('is_active', true)->whereNotNull('brand')->distinct()->pluck('brand')
                ->take(12)
                ->map(fn ($name) => (object) ['name' => $name, 'slug' => Str::slug($name), 'logo' => null]);
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
        $solutionBlocks = $this->resolveSolutionBlocks();
        $categoryIcons = config('homepage.category_icons', []);
        $sectionBrands = $this->remember('home.section_brands', fn () => $this->sectionBrands());

        return view('home', compact(
            'featuredProducts', 'popularProducts', 'topSellers', 'networkingProducts',
            'laptopProducts', 'securityProducts', 'categories', 'brands', 'banners',
            'articles', 'featuredArticle', 'heroSlides', 'solutionBlocks', 'categoryIcons', 'sectionBrands'
        ));
    }

    /**
     * Build unique homepage rows of equal length. Category rows are filled
     * before Top Sellers so a laptop shown in Business Laptops is not also
     * consumed by an earlier overlapping query.
     *
     * @return list<Collection<int, Product>>
     */
    protected function homepageProductRows(int $limit): array
    {
        $popular = $this->popularSouthAfricaProducts($limit);
        $used = $popular->pluck('id')->all();

        $laptops = $this->categoryProducts(
            'computing-office/laptops',
            $limit,
            config('homepage.section_product_brands.laptops', []),
            $used
        );
        $used = array_merge($used, $laptops->pluck('id')->all());

        $networking = $this->networkingShowcaseProducts($limit, $used);
        $used = array_merge($used, $networking->pluck('id')->all());

        $security = $this->categoryProducts(
            'security-surveillance',
            $limit,
            config('homepage.section_product_brands.cctv', []),
            $used
        );
        $used = array_merge($used, $security->pluck('id')->all());

        $topSellers = $this->topSellerProducts($limit, $used);
        $used = array_merge($used, $topSellers->pluck('id')->all());

        $featured = $this->curatedFeaturedProducts($limit, $used);

        return [$popular, $topSellers, $laptops, $networking, $security, $featured];
    }

    /**
     * Featured row only when there is a real curated set from the live catalogue.
     * Sparse leftover "deal" flags should not become a one-product homepage section.
     */
    protected function curatedFeaturedProducts(int $limit, array $excludeIds = [])
    {
        $featured = Product::with('images')
            ->forStorefront()
            ->where('is_featured', true);
        $this->applyExcludedIds($featured, $excludeIds);
        $featured = $featured->latest()->take($limit)->get();

        return $featured->count() >= 4 ? $featured : collect();
    }

    /** @return array<string, Collection<int, Brand>> */
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
     * @param  list<int>  $excludeIds
     */
    protected function categoryProducts(string $slug, int $limit, array $preferredBrandSlugs = [], array $excludeIds = [])
    {
        $category = app(CategoryMapperService::class)->resolveCategoryForFilter($slug);
        if (! $category) {
            return collect();
        }

        $ids = Category::descendantIds($category->id);

        $base = Product::with('images')
            ->forStorefront()
            ->whereIn('category_id', $ids);

        $this->applyHomepageExclusions($base);
        $this->applyExcludedIds($base, $excludeIds);

        $selected = collect();

        if ($preferredBrandSlugs !== []) {
            $brandNames = $this->brandNamesForSlugs($preferredBrandSlugs);

            if ($brandNames !== []) {
                $selected = (clone $base)
                    ->whereIn(DB::raw('LOWER(TRIM(brand))'), $brandNames)
                    ->latest()
                    ->take($limit)
                    ->get();
            }
        }

        if ($selected->count() < $limit) {
            $rest = (clone $base)
                ->when($selected->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $selected->pluck('id')->all()))
                ->latest()
                ->take($limit - $selected->count())
                ->get();

            $selected = $selected->concat($rest);
        }

        return $selected->values();
    }

    protected function networkingShowcaseProducts(int $limit, array $excludeIds = [])
    {
        $config = config('homepage.networking_showcase', []);
        $brandSlugs = $config['brand_slugs'] ?? [];
        $brandNames = $this->brandNamesForSlugs($brandSlugs);

        if ($brandNames === []) {
            return $this->categoryProducts('networking-connectivity', $limit, [], $excludeIds);
        }

        $categoryIds = $this->categoryIdsForSlugs($config['category_slugs'] ?? []);
        if ($categoryIds === []) {
            $categoryIds = $this->categoryIdsForSlugs(['networking-connectivity']);
        }

        $query = Product::with('images')
            ->forStorefront()
            ->whereIn('category_id', $categoryIds)
            ->whereIn(DB::raw('LOWER(TRIM(brand))'), $brandNames);

        $this->applyHomepageExclusions($query);
        $this->applyExcludedIds($query, $excludeIds);
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

        if ($results->count() < $limit) {
            $fallback = $this->networkingShowcaseFallback($limit, $brandNames, $config, $excludeIds);
            $results = $this->mergeUniqueProducts($results, $fallback, $limit);
        }

        if ($results->count() < $limit) {
            $results = $this->mergeUniqueProducts(
                $results,
                $this->categoryProducts('networking-connectivity', $limit, [], $excludeIds),
                $limit
            );
        }

        return $results->values();
    }

    /** @param list<string> $brandNames @param array<string, mixed> $config @param list<int> $excludeIds */
    protected function networkingShowcaseFallback(int $limit, array $brandNames, array $config, array $excludeIds = [])
    {
        $categoryIds = $this->categoryIdsForSlugs(['networking-connectivity']);

        $query = Product::with('images')
            ->forStorefront()
            ->whereIn('category_id', $categoryIds)
            ->whereIn(DB::raw('LOWER(TRIM(brand))'), $brandNames);

        $this->applyHomepageExclusions($query);
        $this->applyExcludedIds($query, $excludeIds);
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

    /**
     * @param  Collection<int, Product>  $primary
     * @param  Collection<int, Product>  $extra
     * @return Collection<int, Product>
     */
    protected function mergeUniqueProducts(Collection $primary, Collection $extra, int $limit): Collection
    {
        $seen = $primary->pluck('id')->flip();

        foreach ($extra as $product) {
            if ($primary->count() >= $limit) {
                break;
            }

            if ($seen->has($product->id)) {
                continue;
            }

            $seen->put($product->id, true);
            $primary->push($product);
        }

        return $primary->values();
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

    protected function applyHomepageExclusions($query): void
    {
        $this->applyExcludedNameTerms($query, config('homepage.exclude_name_terms', []));
    }

    /** @param list<int> $ids */
    protected function applyExcludedIds($query, array $ids): void
    {
        if ($ids !== []) {
            $query->whereNotIn('id', $ids);
        }
    }

    protected function applyPopularTypeOrder($query): void
    {
        $query->orderByRaw(
            "CASE
                WHEN LOWER(name) LIKE '%switch%' OR LOWER(name) LIKE '%access point%' OR LOWER(name) LIKE '%unifi%' OR LOWER(name) LIKE '%router%' THEN 0
                WHEN LOWER(name) LIKE '%laptop%' OR LOWER(name) LIKE '%latitude%' OR LOWER(name) LIKE '%thinkpad%' OR LOWER(name) LIKE '%elitebook%' OR LOWER(name) LIKE '%notebook%' THEN 1
                WHEN (LOWER(name) LIKE '%camera%' OR LOWER(name) LIKE '% nvr%' OR LOWER(name) LIKE '%hikvision%' OR LOWER(name) LIKE '%dahua%') AND LOWER(name) NOT LIKE '%helmet%' THEN 2
                WHEN LOWER(name) LIKE '%nas%' OR LOWER(name) LIKE '%storage%' OR LOWER(name) LIKE '%ssd%' THEN 3
                ELSE 8
            END"
        );
    }

    /**
     * Mix products from the categories SA businesses buy most, instead of newest imports.
     */
    protected function popularSouthAfricaProducts(int $limit)
    {
        $perGroup = 2;
        $collected = collect();
        $paths = config('homepage.popular_category_paths', []);
        $laptopBrands = config('homepage.section_product_brands.laptops', []);
        $cctvBrands = config('homepage.section_product_brands.cctv', []);

        foreach ($paths as $path) {
            $preferred = match ($path) {
                'computing-office/laptops' => $laptopBrands,
                'security-surveillance' => $cctvBrands,
                default => [],
            };

            if ($path === 'networking-connectivity') {
                $batch = $this->networkingShowcaseProducts($perGroup);
                if ($batch->isEmpty()) {
                    $batch = $this->categoryProducts($path, $perGroup);
                }
            } else {
                $batch = $this->categoryProducts($path, $perGroup, $preferred);
            }

            $collected = $collected->concat($batch);
        }

        if ($collected->count() < $limit) {
            $fallback = Product::with('images')->forStorefront();
            $this->applyHomepageExclusions($fallback);

            if ($collected->isNotEmpty()) {
                $fallback->whereNotIn('id', $collected->pluck('id')->all());
            }

            $this->applyPopularTypeOrder($fallback);
            $fallback->when(Schema::hasColumn('products', 'views'), fn ($q) => $q->orderByDesc('views'), fn ($q) => $q->latest());

            $collected = $collected->concat($fallback->take($limit)->get());
        }

        return app(CatalogDeduper::class)->uniqueCollection($collected)->take($limit);
    }

    protected function homepageCategories(int $limit)
    {
        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->visibleInCatalog()
            ->with(['children' => fn ($q) => $q->where('is_active', true)->visibleInCatalog()->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $priority = config('homepage.category_priority', []);
        if ($priority === []) {
            return $categories->take($limit)->values();
        }

        return $categories->sortBy(function (Category $category) use ($priority) {
            $index = array_search($category->slug, $priority, true);

            return $index === false ? 1000 + (int) $category->sort_order : $index;
        })->take($limit)->values();
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

    protected function topSellerProducts(int $limit, array $excludeIds = [])
    {
        $brands = config('homepage.top_seller_brands', []);
        $categorySlugs = config('homepage.top_seller_categories', []);

        $query = Product::with('images')->forStorefront();
        $this->applyHomepageExclusions($query);
        $this->applyExcludedIds($query, $excludeIds);

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

        $mapper = app(CategoryMapperService::class);

        foreach ($slugs as $slug) {
            $category = $mapper->resolveCategoryForFilter($slug);
            if ($category) {
                $ids = array_merge($ids, Category::descendantIds($category->id));
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<array<string, mixed>> */
    protected function resolveSolutionBlocks(): array
    {
        $mapper = app(CategoryMapperService::class);

        return collect(config('homepage.solution_blocks', []))
            ->map(function (array $block) use ($mapper) {
                $path = $block['category_path'] ?? $block['category_slug'] ?? '';
                $category = $path !== '' ? $mapper->resolveCategoryForFilter($path) : null;
                if ($category) {
                    $category->loadMissing('parent');
                }
                $block['category'] = $category;
                $block['url'] = $category?->url() ?? ($path !== '' ? route('shop.index', ['category' => $path]) : route('shop.index'));

                return $block;
            })
            ->all();
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
