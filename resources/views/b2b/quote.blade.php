@extends('layouts.app')

@section('title', 'Request a Quote | Urban Focus B2B')

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
