@extends('layouts.app')

@section('title', 'Request a Quote | Urban Focus B2B')
@section('meta_description', 'Request a VAT invoice quote for networking, laptops, CCTV and software from Urban Focus. We reply within one business day.')
@if(request()->filled('product'))
@section('meta_robots', 'noindex, follow')
@endif

@section('content')
@include('b2b._form', [
    'title' => 'Request a Quote',
    'subtitle' => 'Tell us what you need — our team responds within one business day.',
    'type' => 'quote',
    'productId' => request('product'),
    'messageLabel' => 'Products & quantities required',
    'submitLabel' => 'Submit Quote Request',
])
@endsection
