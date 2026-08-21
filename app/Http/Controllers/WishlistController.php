<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use App\Services\WishlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function __construct(protected WishlistService $wishlist) {}

    public function index(): View
    {
        return view('wishlist.index', [
            'products' => $this->wishlist->products(),
        ]);
    }

    public function add(Product $product): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        if (! $this->wishlist->add($product->id)) {
            return back()->with('warning', 'Your wishlist is full. Remove an item before adding another.');
        }

        return back()->with('success', $product->name.' added to your wishlist.');
    }

    public function remove(Product $product): RedirectResponse
    {
        $this->wishlist->remove($product->id);

        return back()->with('success', 'Item removed from your wishlist.');
    }

    public function toggle(Product $product): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $added = $this->wishlist->toggle($product->id);

        if ($added) {
            return back()->with('success', $product->name.' added to your wishlist.');
        }

        return back()->with('success', $product->name.' removed from your wishlist.');
    }

    public function moveToCart(Product $product, CartService $cart): RedirectResponse
    {
        abort_unless($product->isAvailable(), 422, 'Product is not available.');

        $cart->add($product->id, 1);
        $this->wishlist->remove($product->id);

        return redirect()->route('cart.index')->with('success', $product->name.' moved to your cart.');
    }

    public function addAllToCart(CartService $cart): RedirectResponse
    {
        $added = 0;

        foreach ($this->wishlist->products() as $product) {
            if ($product->isAvailable()) {
                $cart->add($product->id, 1);
                $this->wishlist->remove($product->id);
                $added++;
            }
        }

        if ($added === 0) {
            return back()->with('warning', 'No in-stock wishlist items to add to cart.');
        }

        return redirect()->route('cart.index')->with('success', $added.' item(s) moved to your cart.');
    }

    public function clear(): RedirectResponse
    {
        $this->wishlist->clear();

        return redirect()->route('wishlist.index')->with('success', 'Wishlist cleared.');
    }
}
