<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
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

        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->take(8)
            ->get();

        $newProducts = Product::with('images')
            ->where('is_active', true)
            ->latest()
            ->take(8)
            ->get();

        return view('home', compact('featuredProducts', 'categories', 'newProducts'));
    }
}
