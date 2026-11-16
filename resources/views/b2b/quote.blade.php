@extends('layouts.app')

@section('title', ($isOosEnquiry ?? false) ? 'Request a quote / Ask for ETA | Urban Focus' : 'Request a Quote | Urban Focus B2B')

@section('content')
@include('b2b._form', [
    'title' => ($isOosEnquiry ?? false) ? 'Request a quote / Ask for ETA' : 'Request a Quote',
    'subtitle' => ($isOosEnquiry ?? false)
        ? \App\Services\CatalogOosPolicy::customerMessage()
        : 'Tell us what you need — our team responds within one business day.',
    'type' => ($isOosEnquiry ?? false) ? 'eta' : 'quote',
    'productId' => isset($product) && $product ? $product->id : request('product'),
    'product' => $product ?? null,
    'quantity' => $quantity ?? 1,
    'prefill' => $prefill ?? '',
    'messageLabel' => ($isOosEnquiry ?? false) ? 'Anything we should know (optional notes)' : 'Products & quantities required',
    'submitLabel' => ($isOosEnquiry ?? false) ? \App\Services\CatalogOosPolicy::ctaLabel() : 'Submit Quote Request',
    'requireMessage' => !($isOosEnquiry ?? false),
])
@endsection
