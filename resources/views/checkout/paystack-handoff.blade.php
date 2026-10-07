@extends('layouts.app')

@section('title', 'Redirecting to payment | Urban Focus')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<div class="container py-5 text-center">
    <div class="checkout-card mx-auto" style="max-width:560px">
        <div class="text-primary mb-3" style="font-size:3rem" aria-hidden="true">&#9203;</div>
        <h1 class="h2 fw-bold">Taking you to Paystack</h1>
        <p class="text-muted mb-4">
            Order <strong>{{ $order->order_number }}</strong> is ready.
            You’ll confirm payment on the next screen.
        </p>
        <a id="paystack-continue" class="btn btn-primary btn-lg" rel="noopener" href="{{ $authorizationUrl }}">
            Continue to payment
        </a>
        <p class="small text-muted mt-3 mb-0">
            If nothing happens, tap the button above or call
            <a href="tel:{{ config('business.phone_tel') }}">{{ config('business.phone') }}</a>.
        </p>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var url = @json($authorizationUrl);
    if (!url) return;
    window.setTimeout(function () {
        window.location.replace(url);
    }, 50);
})();
</script>
@endpush
