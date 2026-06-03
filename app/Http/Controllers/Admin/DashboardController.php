<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Quote;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'products' => Product::count(),
            'orders' => Order::count(),
            'customers' => User::where('is_admin', false)->count(),
            'revenue' => Order::where('payment_status', 'paid')->sum('total'),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'quotations' => Quotation::count(),
            'new_enquiries' => Quote::where('status', 'new')->count(),
        ];

        $recentOrders = Order::latest()->take(8)->get();
        $recentQuotations = Quotation::latest()->take(5)->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'recentQuotations'));
    }
}
