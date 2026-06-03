@extends('layouts.admin')
@section('page_title', 'Enquiries (RFQ)')
@section('content')
<div class="card"><div class="table-responsive"><table class="table mb-0">
<thead><tr><th>Date</th><th>Type</th><th>Name</th><th>Company</th><th>Email</th><th>Status</th><th></th></tr></thead>
<tbody>@forelse($quotes as $quote)<tr>
<td>{{ $quote->created_at->format('d M Y') }}</td>
<td>{{ $quote->typeLabel() }}</td>
<td>{{ $quote->name }}</td><td>{{ $quote->company ?? '—' }}</td><td>{{ $quote->email }}</td>
<td><span class="badge bg-light text-dark">{{ $quote->status }}</span></td>
<td class="text-end"><a href="{{ route('admin.quotes.show', $quote) }}" class="btn btn-sm btn-outline-primary">View</a></td>
</tr>@empty<tr><td colspan="7" class="text-muted">No enquiries yet.</td></tr>@endforelse</tbody></table></div></div>
<div class="mt-3">{{ $quotes->links() }}</div>
@endsection
