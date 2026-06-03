@extends('layouts.admin')

@section('page_title', $quotation->quotation_number)

@section('content')
<div class="admin-page-actions mb-4">
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.quotations.print', $quotation) }}" class="btn btn-primary btn-sm" target="_blank">Print / PDF</a>
        <a href="{{ route('admin.quotations.download', $quotation) }}" class="btn btn-outline-primary btn-sm">Download</a>
        <a href="{{ route('admin.quotations.edit', $quotation) }}" class="btn btn-outline-secondary btn-sm">Edit</a>
        <a href="{{ route('admin.quotations.index') }}" class="btn btn-link btn-sm">All quotations</a>
    </div>
    <form action="{{ route('admin.quotations.destroy', $quotation) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this quotation permanently?')">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
    </form>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card admin-card mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1">{{ $quotation->customer_name }}</h2>
                        @if($quotation->customer_company)<p class="text-muted mb-0">{{ $quotation->customer_company }}</p>@endif
                    </div>
                    <span class="badge bg-{{ $quotation->status === 'accepted' ? 'success' : ($quotation->status === 'sent' ? 'primary' : 'secondary') }} fs-6">{{ $quotation->statusLabel() }}</span>
                </div>
                <p class="mb-1">
                    @if($quotation->customer_email)<a href="mailto:{{ $quotation->customer_email }}">{{ $quotation->customer_email }}</a> · @endif
                    {{ $quotation->customer_phone ?? '' }}
                </p>
                @if($quotation->valid_until)
                    <p class="small text-muted mb-0">Valid until {{ $quotation->valid_until->format('d M Y') }}
                        @if($quotation->isExpired()) <span class="text-warning">(expired)</span>@endif
                    </p>
                @endif
                @if($quotation->sourceQuote)
                    <p class="small mt-2 mb-0">From enquiry <a href="{{ route('admin.quotes.show', $quotation->sourceQuote) }}">#{{ $quotation->sourceQuote->id }}</a></p>
                @endif
            </div>
        </div>

        <div class="card admin-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Description</th><th>SKU</th><th>Qty</th><th>Unit</th><th class="text-end">Line</th></tr></thead>
                    <tbody>
                        @foreach($quotation->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td>{{ $item->sku ?? '—' }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>R {{ number_format($item->unit_price, 2) }}</td>
                                <td class="text-end">R {{ number_format($item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td colspan="4" class="text-end">Subtotal (ex VAT)</td><td class="text-end">R {{ number_format($quotation->subtotal, 2) }}</td></tr>
                        @if($quotation->discount_amount > 0)
                            <tr><td colspan="4" class="text-end">Discount</td><td class="text-end">−R {{ number_format($quotation->discount_amount, 2) }}</td></tr>
                        @endif
                        <tr><td colspan="4" class="text-end">VAT</td><td class="text-end">R {{ number_format($quotation->tax_amount, 2) }}</td></tr>
                        <tr class="fw-bold"><td colspan="4" class="text-end">Total</td><td class="text-end">R {{ number_format($quotation->total, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        @if($quotation->notes)
            <div class="card admin-card mb-4"><div class="card-body">
                <h3 class="h6 fw-bold">Customer notes</h3>
                <p class="mb-0 small">{!! nl2br(e($quotation->notes)) !!}</p>
            </div></div>
        @endif
        @if($quotation->internal_notes)
            <div class="card admin-card mb-4"><div class="card-body">
                <h3 class="h6 fw-bold">Internal notes</h3>
                <p class="mb-0 small">{!! nl2br(e($quotation->internal_notes)) !!}</p>
            </div></div>
        @endif
        <div class="card admin-card"><div class="card-body small text-muted">
            Created {{ $quotation->created_at->format('d M Y H:i') }}
            @if($quotation->creator) by {{ $quotation->creator->name }}@endif
        </div></div>
    </div>
</div>
@endsection
