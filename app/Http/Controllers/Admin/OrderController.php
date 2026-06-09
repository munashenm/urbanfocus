<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\LogsAdminActivity;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    use LogsAdminActivity;

    public function index(Request $request): View
    {
        $query = Order::latest();

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($payment = $request->get('payment_status')) {
            $query->where('payment_status', $payment);
        }

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        $orders = $query->paginate(20)->withQueryString();
        $statuses = config('orders.statuses', []);
        $paymentStatuses = config('orders.payment_statuses', []);

        return view('admin.orders.index', compact('orders', 'statuses', 'paymentStatuses'));
    }

    public function show(Order $order): View
    {
        $order->load('items.product', 'user');
        $statuses = config('orders.statuses', []);
        $paymentStatuses = config('orders.payment_statuses', []);

        return view('admin.orders.show', compact('order', 'statuses', 'paymentStatuses'));
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $statusKeys = implode(',', array_keys(config('orders.statuses', [])));
        $paymentKeys = implode(',', array_keys(config('orders.payment_statuses', [])));

        $validated = $request->validate([
            'status' => 'required|in:'.$statusKeys,
            'payment_status' => 'required|in:'.$paymentKeys,
            'admin_notes' => 'nullable|string',
        ]);

        $previous = $order->only(['status', 'payment_status']);
        $order->update($validated);

        if (in_array($validated['status'], ['shipped', 'ready_for_dispatch'], true) && ! $order->shipped_at) {
            $order->update(['shipped_at' => now()]);
        }

        if (in_array($validated['status'], ['delivered', 'completed'], true) && ! $order->completed_at) {
            $order->update(['completed_at' => now()]);
        }

        if ($validated['payment_status'] === 'paid' && ! $order->paid_at) {
            $order->update(['paid_at' => now()]);
        }

        $this->audit('orders.update', $order, [
            'previous' => $previous,
            'current' => $order->only(['status', 'payment_status']),
        ]);

        return back()->with('success', 'Order updated.');
    }
}
