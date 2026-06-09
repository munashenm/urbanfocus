@extends('layouts.admin')

@section('page_title', 'Customers')

@section('content')
<div class="admin-page-actions">
    <form class="admin-filters" method="GET">
        <input type="search" name="q" class="form-control form-control-sm" placeholder="Search customers…" value="{{ request('q') }}">
        <button class="btn btn-sm btn-outline-secondary">Search</button>
    </form>
</div>

<div class="card admin-card admin-data-table">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Name</th><th>Email</th><th>Company</th><th>Orders</th><th>Joined</th><th></th></tr></thead>
            <tbody>
                @forelse($customers as $customer)
                    <tr>
                        <td>{{ $customer->name }}</td>
                        <td>{{ $customer->email }}</td>
                        <td>{{ $customer->company_name ?: '—' }}</td>
                        <td>{{ $customer->orders_count }}</td>
                        <td>{{ $customer->created_at->format('d M Y') }}</td>
                        <td class="text-end"><a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="admin-empty">No customers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $customers->links() }}</div>
@endsection
