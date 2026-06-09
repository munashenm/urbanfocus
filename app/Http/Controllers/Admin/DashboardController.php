<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\LogsAdminActivity;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Quote;
use App\Support\AdminRbac;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use LogsAdminActivity;

    public function index(): View
    {
        $stats = [
            'products' => Product::count(),
            'orders' => Order::count(),
            'customers' => AdminRbac::customersQuery()->count(),
            'revenue' => (float) Order::where('payment_status', 'paid')->sum('total'),
            'pending_orders' => Order::whereIn('status', ['pending', 'pending_payment', 'processing'])->count(),
            'pending_payment' => Order::where('payment_status', 'pending')->count(),
            'quotations' => Quotation::count(),
            'new_enquiries' => Quote::where('status', 'new')->count(),
            'low_stock' => Product::where('manage_stock', true)->where('stock_quantity', '<=', 5)->count(),
        ];

        $recentOrders = Order::latest()->take(8)->get();
        $recentQuotations = Quotation::latest()->take(5)->get();
        $recentCustomers = AdminRbac::customersQuery()->latest()->take(5)->get();

        $lowStockProducts = Product::where('manage_stock', true)
            ->where('stock_quantity', '<=', 5)
            ->orderBy('stock_quantity')
            ->take(8)
            ->get(['id', 'slug', 'name', 'sku', 'stock_quantity', 'in_stock']);

        $topProducts = DB::table('order_items')
            ->select('product_name', 'product_sku', DB::raw('SUM(quantity) as qty_sold'))
            ->groupBy('product_name', 'product_sku')
            ->orderByDesc('qty_sold')
            ->limit(5)
            ->get();

        $salesChart = Order::query()
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as day, SUM(total) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $paymentSummary = Order::query()
            ->select('payment_status', DB::raw('COUNT(*) as count'))
            ->groupBy('payment_status')
            ->pluck('count', 'payment_status');

        return view('admin.dashboard', compact(
            'stats',
            'recentOrders',
            'recentQuotations',
            'recentCustomers',
            'lowStockProducts',
            'topProducts',
            'salesChart',
            'paymentSummary',
        ));
    }
}
