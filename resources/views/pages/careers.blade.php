@extends('layouts.app')

@section('title', 'Careers | Urban Focus')

@section('content')
<div class="page-hero">
    <div class="container">
        <h1 class="h2 fw-bold mb-2">Careers</h1>
        <p class="mb-0 opacity-75">Join the Urban Focus team in Centurion.</p>
    </div>
</div>

<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-8">
            <h2 class="h4 fw-bold text-navy">Work with us</h2>
            <p>Urban Focus is a growing South African IT supplier serving businesses, resellers and corporate customers nationwide. We value knowledgeable, customer-focused people who understand technology and deliver excellent service.</p>

            <div class="checkout-card mb-4">
                <h3 class="h5 fw-bold">Current opportunities</h3>
                <p class="mb-0">We do not have fixed vacancies listed at the moment. We welcome speculative applications from candidates with experience in IT sales, procurement, warehouse operations, technical support or e-commerce.</p>
            </div>

            <div class="checkout-card">
                <h3 class="h5 fw-bold">How to apply</h3>
                <p>Send your CV and a brief cover letter to <a href="mailto:{{ config('business.careers_email') }}">{{ config('business.careers_email') }}</a>. Include the role you are interested in and your notice period.</p>
                <p class="mb-0 small text-muted">Only shortlisted candidates will be contacted.</p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="checkout-card">
                <h3 class="h5 fw-bold mb-3">Our office</h3>
                @include('partials.business-address', ['block' => true, 'showLabel' => false])
                <p class="mt-3 mb-2"><strong>Phone:</strong> <a href="tel:{{ config('business.phone_tel') }}">{{ config('business.phone') }}</a></p>
                <p class="mb-0"><strong>Hours:</strong> {{ config('business.hours') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
