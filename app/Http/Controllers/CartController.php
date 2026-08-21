<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use App\Services\WishlistService;
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
        abort_unless($product->isAvailable(), 422, 'Product is not available.');

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

    public function saveForLater(Product $product, WishlistService $wishlist): RedirectResponse
    {
        if (! $wishlist->add($product->id)) {
            return redirect()->route('cart.index')->with('warning', 'Your wishlist is full. Remove an item before saving another.');
        }

        $this->cart->remove($product->id);

        return redirect()->route('wishlist.index')->with('success', $product->name.' saved for later.');
    }
}
