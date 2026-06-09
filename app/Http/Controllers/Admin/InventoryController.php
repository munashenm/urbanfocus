<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query()->with('category');

        if ($request->get('filter') === 'out_of_stock') {
            $query->where('in_stock', false);
        } elseif ($request->get('filter') === 'low_stock') {
            $query->where('manage_stock', true)->where('stock_quantity', '<=', 5);
        } elseif ($request->get('filter') === 'in_stock') {
            $query->where('in_stock', true);
        }

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('stock_quantity')->paginate(25)->withQueryString();

        $stats = [
            'total' => Product::count(),
            'in_stock' => Product::where('in_stock', true)->count(),
            'low_stock' => Product::where('manage_stock', true)->where('stock_quantity', '<=', 5)->count(),
            'out_of_stock' => Product::where('in_stock', false)->count(),
        ];

        return view('admin.inventory.index', compact('products', 'stats'));
    }
}
