<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('admin.reports.index', [
            'salesTotal' => (float) Order::where('payment_status', 'paid')->sum('total'),
            'ordersCount' => Order::count(),
            'customersCount' => User::whereDoesntHave('roles')->where('is_admin', false)->count(),
            'rfqCount' => Quote::count(),
            'quotationCount' => Quotation::count(),
            'lowStockCount' => Product::where('manage_stock', true)->where('stock_quantity', '<=', 5)->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $type = $request->get('type', 'sales');

        return match ($type) {
            'products' => $this->exportProducts(),
            'customers' => $this->exportCustomers(),
            'stock' => $this->exportStock(),
            'rfqs' => $this->exportRfqs(),
            default => $this->exportSales(),
        };
    }

    protected function exportSales(): StreamedResponse
    {
        $filename = 'sales-report-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order', 'Date', 'Customer', 'Status', 'Payment', 'Total']);

            Order::latest()->chunk(200, function ($orders) use ($handle) {
                foreach ($orders as $order) {
                    fputcsv($handle, [
                        $order->order_number,
                        $order->created_at->format('Y-m-d H:i'),
                        $order->customer_email,
                        $order->status,
                        $order->payment_status,
                        $order->total,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function exportProducts(): StreamedResponse
    {
        $filename = 'products-report-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Name', 'Brand', 'Price', 'Stock', 'In Stock', 'Active']);

            Product::chunk(200, function ($products) use ($handle) {
                foreach ($products as $product) {
                    fputcsv($handle, [
                        $product->sku,
                        $product->name,
                        $product->brand,
                        $product->price,
                        $product->stock_quantity,
                        $product->in_stock ? 'Yes' : 'No',
                        $product->is_active ? 'Yes' : 'No',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function exportCustomers(): StreamedResponse
    {
        $filename = 'customers-report-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'Phone', 'Company', 'Orders', 'Registered']);

            User::whereDoesntHave('roles')->where('is_admin', false)->withCount('orders')->chunk(200, function ($users) use ($handle) {
                foreach ($users as $user) {
                    fputcsv($handle, [
                        $user->name,
                        $user->email,
                        $user->phone,
                        $user->company_name,
                        $user->orders_count,
                        $user->created_at->format('Y-m-d'),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function exportStock(): StreamedResponse
    {
        $filename = 'stock-report-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Name', 'Stock Qty', 'Manage Stock', 'In Stock']);

            Product::chunk(200, function ($products) use ($handle) {
                foreach ($products as $product) {
                    fputcsv($handle, [
                        $product->sku,
                        $product->name,
                        $product->stock_quantity,
                        $product->manage_stock ? 'Yes' : 'No',
                        $product->in_stock ? 'Yes' : 'No',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function exportRfqs(): StreamedResponse
    {
        $filename = 'rfq-report-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Type', 'Name', 'Company', 'Email', 'Status', 'Date']);

            Quote::latest()->chunk(200, function ($quotes) use ($handle) {
                foreach ($quotes as $quote) {
                    fputcsv($handle, [
                        $quote->type,
                        $quote->name,
                        $quote->company,
                        $quote->email,
                        $quote->status,
                        $quote->created_at->format('Y-m-d'),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
