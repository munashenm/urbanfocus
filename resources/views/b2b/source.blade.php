@extends('layouts.app')

@section('title', 'Source a Product | Urban Focus')

@section('content')
@include('b2b._form', [
    'title' => 'Can\'t Find a Product? Let Us Source It',
    'subtitle' => 'Tell us what you need — we\'ll search our supplier network across South Africa.',
    'type' => 'source',
    'messageLabel' => 'Product details (brand, model, specs, quantity)',
    'submitLabel' => 'Submit Sourcing Request',
])
@endsection
