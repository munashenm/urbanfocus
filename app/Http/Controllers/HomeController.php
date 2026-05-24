<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Article;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $cacheMinutes = 10;

        $featuredProducts = Cache::remember('home.featured', $cacheMinutes * 60, fn () => Product::with('images')
            ->where('is_active', true)->where('is_featured', true)->latest()->take(8)->get());

        $dealProducts = Cache::remember('home.deals', $cacheMinutes * 60, function () {
            $q = Product::with('images')->where('is_active', true);
            if (Schema::hasColumn('products', 'is_deal')) {
                $q->where(fn ($w) => $w->where('is_deal', true)->orWhereNotNull('sale_price'));
            } else {
                $q->whereNotNull('sale_price');
            }

            return $q->latest()->take(8)->get();
        });

        $topSellers = Cache::remember('home.top_sellers', $cacheMinutes * 60, fn () => Product::with('images')
            ->where('is_active', true)
            ->when(Schema::hasColumn('products', 'views'), fn ($q) => $q->orderByDesc('views'), fn ($q) => $q->latest())
            ->take(8)
            ->get());

        $networkingProducts = Cache::remember('home.networking', $cacheMinutes * 60, fn () => $this->categoryProducts('networking', 8));

        $laptopProducts = Cache::remember('home.laptops', $cacheMinutes * 60, fn () => $this->categoryProducts('laptops-notebooks', 8));

        $categories = Cache::remember('home.categories', $cacheMinutes * 60, fn () => Category::where('is_active', true)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->take(12)
            ->get());

        $newProducts = Cache::remember('home.new', $cacheMinutes * 60, fn () => Product::with('images')
            ->where('is_active', true)->latest()->take(8)->get());

        $brands = Cache::remember('home.brands', $cacheMinutes * 60, function () {
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

        $articles = Schema::hasTable('articles')
            ? Article::published()->latest('published_at')->take(3)->get()
            : collect();

        $heroSlides = config('homepage.hero_slides', []);
        $solutionBlocks = config('homepage.solution_blocks', []);
        $categoryIcons = config('homepage.category_icons', []);

        return view('home', compact(
            'featuredProducts', 'dealProducts', 'topSellers', 'networkingProducts',
            'laptopProducts', 'categories', 'newProducts', 'brands', 'banners',
            'articles', 'heroSlides', 'solutionBlocks', 'categoryIcons'
        ));
    }

    protected function categoryProducts(string $slug, int $limit)
    {
        $category = Category::where('slug', $slug)->first();
        if (! $category) {
            return collect();
        }

        $ids = Category::descendantIds($category->id);

        return Product::with('images')
            ->where('is_active', true)
            ->whereIn('category_id', $ids)
            ->latest()
            ->take($limit)
            ->get();
    }
}
