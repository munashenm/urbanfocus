<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderTrackingController extends Controller
{
    public function showForm(): View
    {
        return view('orders.track');
    }

    public function lookup(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'order_number' => 'required|string|max:50',
            'email' => 'required|email|max:255',
        ]);

        $order = Order::where('order_number', strtoupper(trim($validated['order_number'])))
            ->whereRaw('LOWER(customer_email) = ?', [strtolower(trim($validated['email']))])
            ->first();

        if (! $order) {
            return back()
                ->withErrors(['order_number' => 'No order found with those details. Check your order number and email address.'])
                ->withInput();
        }

        $order->load('items');

        return view('orders.show', compact('order'));
    }
}
