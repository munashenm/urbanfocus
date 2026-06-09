@extends('layouts.admin')

@section('page_title', $customer->name)

@section('content')
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card admin-card">
            <div class="card-header bg-white fw-semibold">Customer details</div>
            <div class="card-body">
                @permission('customers.edit')
                <form action="{{ route('admin.customers.update', $customer) }}" method="POST">
                    @csrf @method('PUT')
                    <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="{{ old('name', $customer->name) }}" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $customer->email) }}" required></div>
                    <div class="mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="{{ old('phone', $customer->phone) }}"></div>
                    <div class="mb-3"><label class="form-label">Company</label><input type="text" name="company_name" class="form-control" value="{{ old('company_name', $customer->company_name) }}"></div>
                    <div class="mb-3"><label class="form-label">VAT number</label><input type="text" name="vat_number" class="form-control" value="{{ old('vat_number', $customer->vat_number) }}"></div>
                    <div class="form-check mb-3">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $customer->is_active))>
                        <label class="form-check-label" for="is_active">Active account</label>
                    </div>
                    <button class="btn btn-primary w-100">Save changes</button>
                </form>
                @else
                    <p><strong>{{ $customer->name }}</strong></p>
                    <p class="mb-1">{{ $customer->email }}</p>
                    <p class="mb-1">{{ $customer->phone ?: '—' }}</p>
                    <p class="mb-0">{{ $customer->company_name ?: '—' }}</p>
                @endpermission
                <hr>
                <p class="small text-muted mb-0">Registered {{ $customer->created_at->format('d M Y') }} · {{ $customer->orders_count }} order(s)</p>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card admin-card admin-data-table">
            <div class="card-header bg-white fw-semibold">Order history</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Order</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td><a href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a></td>
                                <td>R {{ number_format($order->total, 2) }}</td>
                                <td>@include('partials.admin-status-badge', ['status' => $order->status])</td>
                                <td>{{ $order->created_at->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="admin-empty">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $orders->links() }}</div>
    </div>
</div>
@endsection
