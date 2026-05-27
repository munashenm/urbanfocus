@extends('layouts.app')

@section('title', $pageSeo['title'] ?? 'POPIA Compliance | Urban Focus')
@section('meta_description', $pageSeo['description'] ?? config('seo.defaults.description'))

@section('content')
<div class="page-hero"><div class="container"><h1 class="h2 fw-bold mb-0">POPIA — Protection of Personal Information</h1></div></div>
<div class="container py-5"><div class="row justify-content-center"><div class="col-lg-8 legal-content">
<p>Urban Focus is committed to protecting personal information in accordance with the <strong>Protection of Personal Information Act, 2013 (POPIA)</strong> of South Africa.</p>

<h2 class="h5 fw-bold mt-4">Responsible party</h2>
<p><strong>Urban Focus</strong><br>
@include('partials.business-address', ['inline' => true])<br>
Email: <a href="mailto:{{ config('business.email') }}">{{ config('business.email') }}</a><br>
Phone: <a href="tel:{{ config('business.phone_tel') }}">{{ config('business.phone') }}</a></p>

<h2 class="h5 fw-bold mt-4">Information officer</h2>
<p>Information officer enquiries may be directed to <a href="mailto:{{ config('business.email') }}">{{ config('business.email') }}</a>. Mark your email subject line <strong>POPIA Request</strong>.</p>

<h2 class="h5 fw-bold mt-4">Purpose of processing</h2>
<p>We collect and process personal information to:</p>
<ul>
    <li>Process and fulfil orders and deliver products</li>
    <li>Provide customer support, quotes and B2B services</li>
    <li>Comply with tax, accounting and legal obligations</li>
    <li>Send order confirmations and service-related communications</li>
    <li>Improve our website and services (where consent applies)</li>
</ul>

<h2 class="h5 fw-bold mt-4">Categories of data</h2>
<p>Name, contact details, billing and delivery address, company name, order history, payment references (we do not store full card details — payments are processed by PayFast), and account credentials.</p>

<h2 class="h5 fw-bold mt-4">Your rights</h2>
<p>Under POPIA you may request:</p>
<ul>
    <li>Confirmation of whether we hold your personal information</li>
    <li>Access to your personal information</li>
    <li>Correction or deletion of inaccurate information</li>
    <li>Objection to processing in certain circumstances</li>
</ul>
<p>Submit requests to <a href="mailto:{{ config('business.email') }}">{{ config('business.email') }}</a>. We will respond within a reasonable period as required by law.</p>

<h2 class="h5 fw-bold mt-4">Security &amp; retention</h2>
<p>We implement appropriate technical and organisational measures to protect personal information. Data is retained only as long as necessary for the purposes above or as required by law.</p>

<h2 class="h5 fw-bold mt-4">Third parties</h2>
<p>We share information with service providers necessary to operate our business (couriers, payment gateways, email providers) under appropriate confidentiality arrangements. We do not sell personal information.</p>

<p class="mt-4 mb-0">See also our <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>
</div></div></div>
@endsection
