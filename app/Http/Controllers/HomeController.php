<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $featuredProducts = Product::with('images')
            ->where('is_active', true)
            ->where('is_featured', true)
            ->latest()
            ->take(8)
            ->get();

        $dealQuery = Product::with('images')->where('is_active', true);

        if (Schema::hasColumn('products', 'is_deal')) {
            $dealQuery->where(function ($q) {
                $q->where('is_deal', true)->orWhereNotNull('sale_price');
            });
        } else {
            $dealQuery->whereNotNull('sale_price');
        }

        $dealProducts = $dealQuery->latest()->take(8)->get();

        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->take(9)
            ->get();

        $newProducts = Product::with('images')
            ->where('is_active', true)
            ->latest()
            ->take(8)
            ->get();

        $brands = Schema::hasTable('brands')
            ? Brand::where('is_active', true)->orderBy('sort_order')->take(12)->get()
            : collect();

        if ($brands->isEmpty()) {
            $brands = Product::where('is_active', true)
                ->whereNotNull('brand')
                ->distinct()
                ->pluck('brand')
                ->take(12)
                ->map(fn ($name) => (object) ['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'logo' => null]);
        }

        $banners = Schema::hasTable('banners')
            ? Banner::active('home')->take(3)->get()
            : collect();

        return view('home', compact('featuredProducts', 'dealProducts', 'categories', 'newProducts', 'brands', 'banners'));
    }
}
