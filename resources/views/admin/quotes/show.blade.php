@extends('layouts.admin')
@section('page_title', 'Quote #'.$quote->id)
@section('content')
<div class="row g-4">
<div class="col-lg-7"><div class="card"><div class="card-body">
<p><strong>Type:</strong> {{ $quote->typeLabel() }}</p>
<p><strong>Name:</strong> {{ $quote->name }}</p>
<p><strong>Company:</strong> {{ $quote->company ?? '—' }}</p>
<p><strong>Email:</strong> <a href="mailto:{{ $quote->email }}">{{ $quote->email }}</a></p>
<p><strong>Phone:</strong> {{ $quote->phone ?? '—' }}</p>
@if($quote->product)<p><strong>Product:</strong> <a href="{{ route('products.show', $quote->product) }}">{{ $quote->product->name }}</a></p>@endif
@if($quote->file_path)<p><strong>RFQ File:</strong> <a href="{{ storage_public_url($quote->file_path) }}" target="_blank">Download</a></p>@endif
@if($quote->message)<hr><p class="mb-0"><strong>Message:</strong><br>{!! nl2br(e($quote->message)) !!}</p>@endif
</div></div></div>
<div class="col-lg-5"><div class="card"><div class="card-body">
<form action="{{ route('admin.quotes.update', $quote) }}" method="POST">@csrf @method('PUT')
<div class="mb-3"><label class="form-label">Status</label>
<select name="status" class="form-select">
@foreach(['new','in_progress','quoted','closed'] as $s)<option value="{{ $s }}" @selected($quote->status===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach
</select></div>
<div class="mb-3"><label class="form-label">Admin Notes</label><textarea name="admin_notes" class="form-control" rows="4">{{ old('admin_notes', $quote->admin_notes) }}</textarea></div>
<button type="submit" class="btn btn-primary">Update</button>
</form></div></div></div>
</div>
@endsection
