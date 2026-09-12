@extends('layouts.app')

@section('title', config('page_seo.contact.title', 'Contact Us | Urban Focus'))
@section('meta_description', config('page_seo.contact.description', config('seo.defaults.description')))
@section('canonical', route('contact'))

@push('schema')
<script type="application/ld+json">{!! json_encode(app(\App\Services\SeoService::class)->localBusinessSchema(), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<div class="page-hero">
    <div class="container">
        <h1 class="h2 fw-bold mb-2">Contact Us</h1>
        <p class="mb-0 opacity-75">Product enquiries, bulk orders and courier quotes.</p>
    </div>
</div>

<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item active">Contact</li>
        </ol>
    </nav>

    <div class="row g-5">
        <div class="col-lg-5">
            <div class="checkout-card h-100">
                <h2 class="h5 fw-bold mb-3">Get in touch</h2>
                <p class="text-muted">Our team typically responds within one business day.</p>
                <ul class="list-unstyled mt-4 mb-4 contact-details">
                    <li class="mb-3">
                        <strong class="d-block text-navy">Address</strong>
                        @include('partials.business-address', ['inline' => true, 'class' => 'text-muted'])
                    </li>
                    <li class="mb-3">
                        <strong class="d-block text-navy">Phone</strong>
                        <a href="tel:{{ config('business.phone_tel') }}">{{ config('business.phone') }}</a>
                    </li>
                    <li class="mb-3">
                        <strong class="d-block text-navy">Email</strong>
                        <a href="mailto:{{ config('business.email') }}">{{ config('business.email') }}</a>
                    </li>
                    <li>
                        <strong class="d-block text-navy">Business hours</strong>
                        {{ config('business.hours') }}
                    </li>
                </ul>
                <p class="small text-muted mb-0">For urgent orders, call us directly — we can assist with stock checks and manual courier quotes.</p>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="checkout-card">
                <form action="{{ route('contact.store') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="contact-name">Name *</label>
                            <input id="contact-name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="contact-phone">Phone</label>
                            <input id="contact-phone" type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="contact-email">Email *</label>
                            <input id="contact-email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="contact-message">Message *</label>
                            <textarea id="contact-message" name="message" class="form-control @error('message') is-invalid @enderror" rows="5" required>{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Send Message</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
