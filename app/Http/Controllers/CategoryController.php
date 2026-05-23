<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(Category $category): View
    {
        $products = Product::with('images')
            ->where('category_id', $category->id)
            ->where('is_active', true)
            ->latest()
            ->paginate(24);

        return view('categories.show', compact('category', 'products'));
    }
}
