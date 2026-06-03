@extends('layouts.admin')

@section('page_title', 'New quotation')

@section('content')
@if(!empty($prefill['source_quote_id']))
    <div class="alert alert-info py-2 small">Prefilled from enquiry #{{ $prefill['source_quote_id'] }}.</div>
@endif
@include('admin.quotations._form', ['quotation' => null, 'prefill' => $prefill ?? [], 'defaultValidUntil' => $defaultValidUntil, 'defaultTerms' => $defaultTerms])
@endsection
