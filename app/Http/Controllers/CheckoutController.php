<?php

namespace App\Http\Controllers;

use App\Mail\NewOrderNotification;
use App\Mail\OrderConfirmation;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CartService;
use App\Services\PayFastService;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected ShippingService $shipping,
        protected PayFastService $payfast
    ) {}

    public function index(): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $subtotal = $this->cart->subtotal();
        $shippingMethods = $this->shipping->availableMethods($subtotal);
        $vatRate = config('app.vat_rate', 15);
        $pricesIncludeVat = config('app.prices_include_vat', true);

        return view('checkout.index', compact('subtotal', 'shippingMethods', 'vatRate', 'pricesIncludeVat'));
    }

    public function validateCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'coupon_code' => 'required|string|max:50',
        ]);

        if ($this->cart->isEmpty()) {
            return response()->json([
                'valid' => false,
                'message' => 'Your cart is empty.',
            ], 422);
        }

        $subtotal = $this->cart->subtotal();
        $coupon = Coupon::where('code', strtoupper(trim($request->coupon_code)))->first();

        if (! $coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid coupon code.',
            ], 422);
        }

        if ($message = $coupon->validationMessageFor($subtotal)) {
            return response()->json([
                'valid' => false,
                'message' => $message,
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'code' => $coupon->code,
            'discount' => $coupon->discountAmount($subtotal),
            'discounted_subtotal' => max(0, $subtotal - $coupon->discountAmount($subtotal)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $validated = $request->validate([
            'billing_first_name' => 'required|string|max:100',
            'billing_last_name' => 'required|string|max:100',
            'billing_company' => 'nullable|string|max:150',
            'billing_vat_number' => 'nullable|string|max:50',
            'billing_address_line_1' => 'required|string|max:255',
            'billing_address_line_2' => 'nullable|string|max:255',
            'billing_city' => 'required|string|max:100',
            'billing_province' => 'required|string|max:100',
            'billing_postal_code' => 'required|string|max:20',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:30',
            'shipping_method' => 'required|in:courier,free,manual_quote,collection',
            'payment_method' => 'required|in:payfast,eft',
            'customer_notes' => 'nullable|string|max:1000',
            'coupon_code' => 'nullable|string|max:50',
            'same_as_billing' => 'nullable|boolean',
            'shipping_first_name' => 'nullable|string|max:100',
            'shipping_last_name' => 'nullable|string|max:100',
            'shipping_address_line_1' => 'nullable|string|max:255',
            'shipping_city' => 'nullable|string|max:100',
            'shipping_province' => 'nullable|string|max:100',
            'shipping_postal_code' => 'nullable|string|max:20',
        ]);

        $subtotal = $this->cart->subtotal();
        $discountAmount = 0.0;
        $coupon = null;

        if (! empty($validated['coupon_code'])) {
            $coupon = Coupon::where('code', strtoupper(trim($validated['coupon_code'])))->first();

            if (! $coupon) {
                return back()->withErrors(['coupon_code' => 'Invalid coupon code.'])->withInput();
            }

            if ($message = $coupon->validationMessageFor($subtotal)) {
                return back()->withErrors(['coupon_code' => $message])->withInput();
            }

            $discountAmount = $coupon->discountAmount($subtotal);
        }

        $discountedSubtotal = max(0, $subtotal - $discountAmount);
        $shippingData = $this->shipping->calculate($discountedSubtotal, $validated['shipping_method']);
        $shippingCost = $shippingData['cost'];
        [$taxAmount, $total] = $this->calculateTaxAndTotal($discountedSubtotal, $shippingCost);
        $deferStock = $validated['payment_method'] === 'payfast';

        $order = DB::transaction(function () use ($validated, $subtotal, $discountAmount, $shippingData, $shippingCost, $taxAmount, $total, $deferStock) {
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'user_id' => auth()->id(),
                'status' => 'pending',
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'pending',
                'shipping_method' => $shippingData['method'],
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'shipping_cost' => $shippingCost,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'customer_email' => $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'],
                'billing_first_name' => $validated['billing_first_name'],
                'billing_last_name' => $validated['billing_last_name'],
                'billing_company' => $validated['billing_company'] ?? null,
                'billing_vat_number' => $validated['billing_vat_number'] ?? null,
                'billing_address_line_1' => $validated['billing_address_line_1'],
                'billing_address_line_2' => $validated['billing_address_line_2'] ?? null,
                'billing_city' => $validated['billing_city'],
                'billing_province' => $validated['billing_province'],
                'billing_postal_code' => $validated['billing_postal_code'],
                'billing_country' => 'ZA',
                'shipping_first_name' => $validated['shipping_first_name'] ?? $validated['billing_first_name'],
                'shipping_last_name' => $validated['shipping_last_name'] ?? $validated['billing_last_name'],
                'shipping_address_line_1' => $validated['shipping_address_line_1'] ?? $validated['billing_address_line_1'],
                'shipping_city' => $validated['shipping_city'] ?? $validated['billing_city'],
                'shipping_province' => $validated['shipping_province'] ?? $validated['billing_province'],
                'shipping_postal_code' => $validated['shipping_postal_code'] ?? $validated['billing_postal_code'],
                'shipping_country' => 'ZA',
                'customer_notes' => $validated['customer_notes'] ?? null,
            ]);

            foreach ($this->cart->items() as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'product_sku' => $item['product']->sku,
                    'unit_price' => $item['product']->effective_price,
                    'quantity' => $item['quantity'],
                    'line_total' => $item['line_total'],
                ]);

                if (! $deferStock && $item['product']->manage_stock) {
                    $item['product']->decrement('stock_quantity', $item['quantity']);
                }
            }

            return $order;
        });

        if ($coupon && $discountAmount > 0) {
            $coupon->increment('used_count');
        }

        $this->cart->clear();

        session(['checkout_order_id' => $order->id]);

        Mail::to($order->customer_email)->send(new OrderConfirmation($order));
        Mail::to(config('app.email'))->send(new NewOrderNotification($order));

        if ($validated['payment_method'] === 'payfast') {
            return redirect()->route('checkout.payfast.redirect', $order);
        }

        return redirect()->route('checkout.success', $order)->with('success', 'Order placed. Please complete EFT payment using the reference on the confirmation page.');
    }

    public function payfastRedirect(Order $order): View|RedirectResponse
    {
        if (session('checkout_order_id') !== $order->id) {
            abort(403);
        }

        if ($order->payment_status === 'paid') {
            return redirect()->route('checkout.success', $order);
        }

        $paymentData = $this->payfast->buildPaymentData($order);

        return view('checkout.payfast-redirect', [
            'order' => $order,
            'paymentData' => $paymentData,
            'processUrl' => config('payfast.process_url'),
        ]);
    }

    public function payfastReturn(Request $request): RedirectResponse
    {
        $orderNumber = $request->get('m_payment_id');
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $message = $request->get('payment_status') === 'COMPLETE'
            ? 'Payment received. Thank you for your order.'
            : 'Your order has been received. We will confirm payment once PayFast completes processing.';

        return redirect()->route('checkout.success', $order)->with('success', $message);
    }

    public function payfastCancel(): RedirectResponse
    {
        return redirect()->route('cart.index')->with('error', 'Payment was cancelled.');
    }

    public function payfastNotify(Request $request): void
    {
        $data = $request->all();

        if (! $this->payfast->validateNotification($data)) {
            abort(400);
        }

        if (! $this->payfast->verifyWithPayFast($data)) {
            abort(400);
        }

        $order = Order::where('order_number', $data['m_payment_id'] ?? '')->first();

        if ($order && $data['payment_status'] === 'COMPLETE' && $order->payment_status !== 'paid') {
            $order->load('items.product');

            foreach ($order->items as $orderItem) {
                $product = $orderItem->product;

                if ($product && $product->manage_stock) {
                    $product->decrement('stock_quantity', $orderItem->quantity);
                }
            }

            $order->update([
                'payment_status' => 'paid',
                'status' => 'processing',
                'payfast_payment_id' => $data['pf_payment_id'] ?? null,
                'paid_at' => now(),
            ]);
        }
    }

    public function success(Order $order): View
    {
        $order->load('items');

        return view('checkout.success', compact('order'));
    }

    /** @return array{0: float, 1: float} [taxAmount, total] */
    protected function calculateTaxAndTotal(float $discountedSubtotal, float $shippingCost): array
    {
        $vatRate = config('app.vat_rate', 15);

        if (config('app.prices_include_vat', true)) {
            $total = $discountedSubtotal + $shippingCost;
            $taxAmount = round($total * ($vatRate / (100 + $vatRate)), 2);

            return [$taxAmount, $total];
        }

        $taxable = $discountedSubtotal + $shippingCost;
        $taxAmount = round($taxable * ($vatRate / 100), 2);

        return [$taxAmount, $taxable + $taxAmount];
    }
}
