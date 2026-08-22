<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(protected CartService $cart) {}

    public function index(): View
    {
        return view('cart.index', [
            'items' => $this->cart->items(),
            'subtotal' => $this->cart->subtotal(),
        ]);
    }

    public function add(Request $request, Product $product): RedirectResponse
    {
        if (! $product->isAvailable()) {
            return back()->with('error', 'This product is currently unavailable.');
        }

        $quantity = max(1, (int) $request->input('quantity', 1));
        $this->cart->add($product->id, $quantity);

        return redirect()->route('cart.index')->with('success', 'Product added to cart.');
    }

    public function update(Request $request): RedirectResponse
    {
        foreach ($request->input('quantities', []) as $productId => $quantity) {
            $this->cart->update((int) $productId, (int) $quantity);
        }

        return redirect()->route('cart.index')->with('success', 'Cart updated.');
    }

    public function remove(Product $product): RedirectResponse
    {
        $this->cart->remove($product->id);

        return redirect()->route('cart.index')->with('success', 'Item removed.');
    }
}
