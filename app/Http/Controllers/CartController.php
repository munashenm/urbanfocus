<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Order;
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
        $pendingCheckoutOrder = null;

        if ($this->cart->isEmpty() && ($pendingId = session('checkout_order_id'))) {
            $candidate = Order::query()->find($pendingId);

            if (
                $candidate
                && $candidate->payment_method === 'paystack'
                && $candidate->payment_status !== 'paid'
            ) {
                $pendingCheckoutOrder = $candidate;
            }
        }

        return view('cart.index', [
            'items' => $this->cart->items(),
            'subtotal' => $this->cart->subtotal(),
            'pendingCheckoutOrder' => $pendingCheckoutOrder,
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
        $quantities = $request->input('quantities', []);
        if (! is_array($quantities) || $quantities === []) {
            return back()->with('error', 'Enter a whole number of 0 or more for each quantity. Use 0 to remove an item from the cart.');
        }

        foreach ($quantities as $productId => $quantity) {
            if (! is_numeric($quantity) || (int) $quantity != $quantity || (int) $quantity < 0) {
                return back()->with('error', 'Enter a whole number of 0 or more for each quantity. Use 0 to remove an item from the cart.');
            }

            $this->cart->update((int) $productId, (int) $quantity);
        }

        return redirect()->route('cart.index')->with('success', 'Cart updated.');
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'coupon_code' => 'required|string|max:50',
        ]);

        $code = strtoupper(trim($data['coupon_code']));
        $coupon = Coupon::query()->where('code', $code)->first();
        $message = $coupon?->validationMessageFor($this->cart->subtotal());

        if (! $coupon || $message !== null) {
            return back()->with('error', $message ?: 'That coupon code is not valid. Check the code and try again.');
        }

        session(['coupon_code' => $coupon->code]);

        return back()->with('success', 'Coupon '.$coupon->code.' will be applied at checkout.');
    }

    public function remove(Product $product): RedirectResponse
    {
        $this->cart->remove($product->id);

        return redirect()->route('cart.index')->with('success', 'Item removed.');
    }
}
