@extends('layouts.app')

@section('title', 'My Account | Urban Focus')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<div class="container py-4">
    <h1 class="h2 fw-bold mb-4">My Account</h1>
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="{{ route('account.profile.edit') }}" class="btn btn-outline-primary btn-sm">Edit Profile &amp; Password</a>
        @if(auth()->user()->isAdmin())
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">Admin Dashboard</a>
        @endif
    </div>
    <p>Welcome, {{ auth()->user()->name }}.</p>

    <h2 class="h5 fw-bold mt-4 mb-3">Recent Orders</h2>
    @if($orders->count())
        <div class="checkout-card">
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Total</th><th></th></tr></thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr>
                                <td>{{ $order->order_number }}</td>
                                <td>{{ $order->created_at->format('d M Y') }}</td>
                                <td>{{ ucfirst($order->status) }}</td>
                                <td>R {{ number_format($order->total, 2) }}</td>
                                <td><a href="{{ route('account.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $orders->links() }}
        </div>
    @else
        <p class="text-muted">You haven't placed any orders yet.</p>
        <a href="{{ route('shop.index') }}" class="btn btn-primary">Start Shopping</a>
    @endif
</div>
@endsection
