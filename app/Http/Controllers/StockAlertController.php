<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\StockAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockAlertController extends Controller
{
    public function store(Request $request, Product $product, StockAlertService $alerts): RedirectResponse
    {
        if ($product->isAvailable()) {
            return back()->with('warning', 'This product is already in stock.');
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:100',
        ]);

        $alerts->subscribe($product, $validated['email'], $validated['name'] ?? null);

        return back()->with('success', 'We will email you when this product is back in stock.');
    }
}
