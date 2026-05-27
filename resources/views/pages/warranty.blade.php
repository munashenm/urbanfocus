@extends('layouts.app')

@section('title', $pageSeo['title'] ?? 'Warranty Terms | Urban Focus')
@section('meta_description', $pageSeo['description'] ?? config('seo.defaults.description'))

@section('content')
<div class="page-hero"><div class="container"><h1 class="h2 fw-bold mb-0">Warranty Terms</h1></div></div>
<div class="container py-5"><div class="row justify-content-center"><div class="col-lg-8 legal-content">
<p>Urban Focus supplies genuine IT products backed by manufacturer warranties where applicable. This page explains how warranty support works for products purchased from us.</p>

<h2 class="h5 fw-bold mt-4">Manufacturer warranty</h2>
<p>Most hardware sold by Urban Focus carries a manufacturer warranty. Warranty periods vary by brand and product category — the applicable period is shown on the product page or your invoice where available. Typical coverage includes:</p>
<ul>
    <li>Manufacturing defects in materials and workmanship</li>
    <li>Hardware failure under normal use during the warranty period</li>
</ul>
<p>Warranty does not cover physical damage, liquid damage, unauthorised modifications, misuse, or normal wear and tear.</p>

<h2 class="h5 fw-bold mt-4">How to claim warranty</h2>
<ol>
    <li>Contact us at <a href="mailto:{{ config('business.email') }}">{{ config('business.email') }}</a> with your order number, product serial number and a description of the fault</li>
    <li>We will advise whether the claim should be handled by Urban Focus or directly with the manufacturer or authorised service centre</li>
    <li>You may be asked to return the product for assessment — do not send items without a return authorisation reference</li>
</ol>

<h2 class="h5 fw-bold mt-4">Software &amp; licences</h2>
<p>Software products and licence keys are generally not returnable once activated. Support is provided according to the publisher's terms.</p>

<h2 class="h5 fw-bold mt-4">Dead on arrival (DOA)</h2>
<p>Products that are faulty on arrival must be reported within 7 days of delivery. We will arrange replacement or refund subject to stock availability and verification.</p>

<h2 class="h5 fw-bold mt-4">Contact</h2>
<p><a href="mailto:{{ config('business.email') }}">{{ config('business.email') }}</a> · <a href="tel:{{ config('business.phone_tel') }}">{{ config('business.phone') }}</a></p>
</div></div></div>
@endsection
