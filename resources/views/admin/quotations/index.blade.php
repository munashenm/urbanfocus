@extends('layouts.admin')

@section('page_title', 'Sales Quotations')

@section('content')
<div class="admin-page-actions">
    <form class="admin-filters" method="GET">
        <input type="search" name="q" class="form-control form-control-sm" placeholder="Search number or customer…" value="{{ request('q') }}">
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All statuses</option>
            @foreach(['draft','sent','accepted','declined','expired'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
        @if(request()->hasAny(['q','status']))
            <a href="{{ route('admin.quotations.index') }}" class="btn btn-sm btn-link">Clear</a>
        @endif
    </form>
    <a href="{{ route('admin.quotations.create') }}" class="btn btn-primary btn-sm">New quotation</a>
</div>

<div class="card admin-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Valid until</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($quotations as $q)
                    <tr>
                        <td><a href="{{ route('admin.quotations.show', $q) }}" class="fw-semibold text-decoration-none">{{ $q->quotation_number }}</a></td>
                        <td>
                            {{ $q->customer_name }}
                            @if($q->customer_company)<br><span class="text-muted small">{{ $q->customer_company }}</span>@endif
                        </td>
                        <td>R {{ number_format($q->total, 2) }}</td>
                        <td><span class="badge bg-{{ $q->status === 'accepted' ? 'success' : ($q->status === 'sent' ? 'primary' : ($q->status === 'draft' ? 'secondary' : 'warning')) }}">{{ $q->statusLabel() }}</span></td>
                        <td>
                            @if($q->valid_until)
                                {{ $q->valid_until->format('d M Y') }}
                                @if($q->isExpired())<span class="text-warning small">Expired</span>@endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-muted small">{{ $q->created_at->format('d M Y') }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.quotations.print', $q) }}" class="btn btn-sm btn-outline-secondary" target="_blank" title="Print">Print</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="admin-empty">No quotations yet. <a href="{{ route('admin.quotations.create') }}">Create your first quotation</a>.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($quotations->hasPages())
        <div class="card-footer">{{ $quotations->links() }}</div>
    @endif
</div>
@endsection
