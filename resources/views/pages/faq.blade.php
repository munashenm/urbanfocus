@extends('layouts.app')

@section('title', 'Frequently Asked Questions | Urban Focus')
@section('meta_description', seo_meta_description('Find answers about ordering, delivery, returns, warranty, VAT invoices and B2B support at Urban Focus.', ['type' => 'article', 'name' => 'FAQ']))

@section('content')
<div class="page-hero">
    <div class="container">
        <h1 class="h2 fw-bold mb-2">Frequently Asked Questions</h1>
        <p class="mb-0 opacity-75">Answers to common questions about ordering, delivery, returns and support at Urban Focus.</p>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            @php
                $faqs = collect(config('seo.faq', []));
                $groups = $faqs->groupBy(fn ($item) => $item['group'] ?? 'General');
            @endphp

            @foreach($groups as $group => $items)
                <div class="checkout-card mb-4">
                    <h2 class="h5 fw-bold mb-3">{{ $group }}</h2>
                    <div class="accordion" id="faq-{{ Str::slug($group) }}">
                        @foreach($items as $faq)
                            @php $itemId = 'faq-'.Str::slug($group).'-'.$loop->index; @endphp
                            <div class="accordion-item">
                                <h3 class="accordion-header" id="{{ $itemId }}-heading">
                                    <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $itemId }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="{{ $itemId }}">
                                        {{ $faq['question'] }}
                                    </button>
                                </h3>
                                <div id="{{ $itemId }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" aria-labelledby="{{ $itemId }}-heading" data-bs-parent="#faq-{{ Str::slug($group) }}">
                                    <div class="accordion-body">
                                        {{ $faq['answer'] }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="checkout-card">
                <h2 class="h5 fw-bold mb-2">Still need help?</h2>
                <p class="mb-3">Our team can assist with product selection, quotes, order tracking and after-sales support.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('contact') }}" class="btn btn-primary">Contact Us</a>
                    <a href="{{ route('b2b.quote') }}" class="btn btn-outline-primary">Request a Quote</a>
                    <a href="tel:{{ config('business.phone_tel') }}" class="btn btn-outline-secondary">{{ config('business.phone') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('schema')
@php $faqSchema = app(\App\Services\SeoService::class)->faqSchema(); @endphp
@if($faqSchema !== [])
<script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) !!}</script>
@endif
@endpush
