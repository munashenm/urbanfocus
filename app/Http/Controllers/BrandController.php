<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class BrandController extends Controller
{
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

        $products = Product::with(['category', 'images'])
            ->where('is_active', true)
            ->where('brand', $brand->name)
            ->latest()
            ->paginate(24);

        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return view('brands.show', compact('brand', 'products', 'categories'));
    }
}
