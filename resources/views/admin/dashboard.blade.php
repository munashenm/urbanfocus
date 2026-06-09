@extends('layouts.admin')

@section('page_title', 'Dashboard')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('admin.orders.index') }}" class="stat-link">
            <div class="stat-card"><div class="text-muted small">Total sales</div><div class="value">R {{ number_format($stats['revenue'], 0) }}</div></div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('admin.orders.index') }}?status=pending" class="stat-link">
            <div class="stat-card"><div class="text-muted small">Pending orders</div><div class="value">{{ $stats['pending_orders'] }}</div></div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('admin.inventory.index') }}?filter=low_stock" class="stat-link">
            <div class="stat-card"><div class="text-muted small">Low stock</div><div class="value">{{ $stats['low_stock'] }}</div></div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('admin.quotes.index') }}?status=new" class="stat-link">
            <div class="stat-card"><div class="text-muted small">New RFQs</div><div class="value">{{ $stats['new_enquiries'] }}</div></div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card"><div class="text-muted small">Customers</div><div class="value">{{ $stats['customers'] }}</div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card"><div class="text-muted small">Products</div><div class="value">{{ $stats['products'] }}</div></div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card admin-card admin-chart-card">
            <div class="card-header bg-white fw-semibold">Sales (last 30 days)</div>
            <div class="card-body">
                <canvas id="salesChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card admin-card h-100">
            <div class="card-header bg-white fw-semibold">Payment status</div>
            <div class="card-body">
                @forelse($paymentSummary as $status => $count)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>@include('partials.admin-status-badge', ['status' => $status])</span>
                        <strong>{{ $count }}</strong>
                    </div>
                @empty
                    <p class="text-muted mb-0">No orders yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card admin-card h-100">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                <span>Recent orders</span>
                <a href="{{ route('admin.orders.index') }}" class="small">View all</a>
            </div>
            <div class="table-responsive admin-data-table">
                <table class="table table-hover mb-0 align-middle">
                    <thead><tr><th>Order</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                            <tr>
                                <td><a href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a><br><small class="text-muted">{{ $order->customer_email }}</small></td>
                                <td>R {{ number_format($order->total, 2) }}</td>
                                <td>@include('partials.admin-status-badge', ['status' => $order->status])</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="admin-empty">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card admin-card h-100">
            <div class="card-header bg-white fw-semibold">Top selling products</div>
            <div class="table-responsive admin-data-table">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Product</th><th>SKU</th><th class="text-end">Sold</th></tr></thead>
                    <tbody>
                        @forelse($topProducts as $row)
                            <tr>
                                <td>{{ $row->product_name }}</td>
                                <td>{{ $row->product_sku }}</td>
                                <td class="text-end">{{ $row->qty_sold }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="admin-empty">No sales data yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card admin-card">
            <div class="card-header bg-white fw-semibold">Stock alerts</div>
            <div class="list-group list-group-flush">
                @forelse($lowStockProducts as $product)
                    <a href="{{ route('admin.products.edit', $product) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
                        <span>{{ $product->name }}</span>
                        <span class="badge bg-warning text-dark">{{ $product->stock_quantity }} left</span>
                    </a>
                @empty
                    <div class="list-group-item text-muted">All stocked products look healthy.</div>
                @endforelse
            </div>
        </div>
    </div>
    @if(Route::has('admin.customers.index'))
    <div class="col-lg-4">
        <div class="card admin-card">
            <div class="card-header bg-white fw-semibold">Recent customers</div>
            <div class="list-group list-group-flush">
                @forelse($recentCustomers as $customer)
                    <a href="{{ route('admin.customers.show', $customer) }}" class="list-group-item list-group-item-action">
                        <strong>{{ $customer->name }}</strong><br>
                        <small class="text-muted">{{ $customer->email }}</small>
                    </a>
                @empty
                    <div class="list-group-item text-muted">No registered customers yet.</div>
                @endforelse
            </div>
        </div>
    </div>
    @endif
    @if(Route::has('admin.quotations.index') && $recentQuotations->isNotEmpty())
    <div class="col-lg-4">
        <div class="card admin-card">
            <div class="card-header bg-white fw-semibold">Recent quotations</div>
            <div class="list-group list-group-flush">
                @foreach($recentQuotations as $q)
                    <a href="{{ route('admin.quotations.show', $q) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
                        <span>{{ $q->quotation_number }}<br><small>{{ $q->customer_name }}</small></span>
                        <span>R {{ number_format($q->total, 0) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
    const labels = @json($salesChart->pluck('day'));
    const values = @json($salesChart->pluck('total'));
    const ctx = document.getElementById('salesChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Sales (ZAR)',
                data: values,
                borderColor: '#2271b1',
                backgroundColor: 'rgba(34, 113, 177, 0.12)',
                fill: true,
                tension: 0.3,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
})();
</script>
@endpush
