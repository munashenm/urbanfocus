@extends('layouts.app')

@section('title', 'Checkout | Urban Focus')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<div class="container py-4">
    <nav aria-label="Checkout progress" class="mb-4">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('cart.index') }}">Cart</a></li>
            <li class="breadcrumb-item active" aria-current="page">Checkout</li>
            <li class="breadcrumb-item text-muted">Payment</li>
        </ol>
    </nav>
    <h1 class="h2 fw-bold mb-2">Checkout</h1>
    <p class="text-muted mb-4">No account needed. Pay with card, Instant EFT, Apple Pay or Google Pay — or request a bank transfer.</p>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Please check the highlighted fields</strong> and tap Continue to payment again.
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $authUser = auth()->user();
        $defaultFirst = old('billing_first_name');
        $defaultLast = old('billing_last_name');
        if (! $defaultFirst && $authUser?->name) {
            $nameParts = preg_split('/\s+/', trim($authUser->name), 2);
            $defaultFirst = $nameParts[0] ?? '';
            $defaultLast = $nameParts[1] ?? '';
        }
        $helpWhatsapp = whatsapp_url('Hi Urban Focus, I need help completing checkout.');
    @endphp

    <form action="{{ route('checkout.store') }}" method="POST" id="checkout-form">
        @csrf
        <input type="hidden" name="same_as_billing" value="1">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="checkout-card mb-4">
                    <h2 class="h5 fw-bold mb-1">Your details</h2>
                    <p class="small text-muted mb-3">We’ll deliver to this address and email your order confirmation here.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="billing_first_name">First name *</label>
                            <input id="billing_first_name" type="text" name="billing_first_name" autocomplete="given-name" class="form-control @error('billing_first_name') is-invalid @enderror" value="{{ $defaultFirst }}" required>
                            @error('billing_first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="billing_last_name">Last name *</label>
                            <input id="billing_last_name" type="text" name="billing_last_name" autocomplete="family-name" class="form-control @error('billing_last_name') is-invalid @enderror" value="{{ $defaultLast }}" required>
                            @error('billing_last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer_email">Email *</label>
                            <input id="customer_email" type="email" name="customer_email" autocomplete="email" inputmode="email" class="form-control @error('customer_email') is-invalid @enderror" value="{{ old('customer_email', auth()->user()?->email) }}" required>
                            @error('customer_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer_phone">Phone *</label>
                            <input id="customer_phone" type="tel" name="customer_phone" autocomplete="tel" inputmode="tel" class="form-control @error('customer_phone') is-invalid @enderror" value="{{ old('customer_phone', auth()->user()?->phone) }}" placeholder="e.g. 082 123 4567" required>
                            @error('customer_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="billing_company">Company <span class="text-muted fw-normal">(optional)</span></label>
                            <input id="billing_company" type="text" name="billing_company" autocomplete="organization" class="form-control" value="{{ old('billing_company', auth()->user()?->company_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="billing_vat_number">VAT number <span class="text-muted fw-normal">(optional)</span></label>
                            <input id="billing_vat_number" type="text" name="billing_vat_number" class="form-control" value="{{ old('billing_vat_number', auth()->user()?->vat_number) }}" placeholder="For a tax invoice">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="billing_address_line_1">Street address *</label>
                            <input id="billing_address_line_1" type="text" name="billing_address_line_1" autocomplete="address-line1" class="form-control @error('billing_address_line_1') is-invalid @enderror" value="{{ old('billing_address_line_1') }}" required>
                            @error('billing_address_line_1')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="visually-hidden" for="billing_address_line_2">Apartment, suite, unit</label>
                            <input id="billing_address_line_2" type="text" name="billing_address_line_2" autocomplete="address-line2" class="form-control" placeholder="Apartment, suite, unit (optional)" value="{{ old('billing_address_line_2') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="billing_city">City *</label>
                            <input id="billing_city" type="text" name="billing_city" autocomplete="address-level2" class="form-control @error('billing_city') is-invalid @enderror" value="{{ old('billing_city') }}" required>
                            @error('billing_city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="billing_province">Province *</label>
                            <select id="billing_province" name="billing_province" autocomplete="address-level1" class="form-select @error('billing_province') is-invalid @enderror" required>
                                @foreach(['Gauteng','Western Cape','KwaZulu-Natal','Eastern Cape','Free State','Limpopo','Mpumalanga','North West','Northern Cape'] as $prov)
                                    <option value="{{ $prov }}" @selected(old('billing_province', 'Gauteng') === $prov)>{{ $prov }}</option>
                                @endforeach
                            </select>
                            @error('billing_province')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="billing_postal_code">Postal code *</label>
                            <input id="billing_postal_code" type="text" name="billing_postal_code" autocomplete="postal-code" inputmode="numeric" class="form-control @error('billing_postal_code') is-invalid @enderror" value="{{ old('billing_postal_code') }}" required>
                            @error('billing_postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="customer_notes">Order notes <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea id="customer_notes" name="customer_notes" class="form-control" rows="2" placeholder="Delivery instructions, alternative contact, etc.">{{ old('customer_notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="checkout-card mb-4">
                    <h2 class="h5 fw-bold mb-3">Shipping Method</h2>
                    @foreach($shippingMethods as $method)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="shipping_method" id="ship_{{ $method['method'] }}" value="{{ $method['method'] }}" @checked(old('shipping_method', $shippingMethods[0]['method'] ?? 'courier') === $method['method'])>
                            <label class="form-check-label" for="ship_{{ $method['method'] }}">
                                {{ $method['label'] }}
                                @if($method['requires_quote'])
                                    <span class="text-muted">(quote to be confirmed)</span>
                                @elseif($method['cost'] > 0)
                                    — R {{ number_format($method['cost'], 2) }}
                                @else
                                    — Free
                                @endif
                            </label>
                        </div>
                    @endforeach
                </div>

                <div class="checkout-card mb-4">
                    <h2 class="h5 fw-bold mb-3">Coupon Code</h2>
                    <div class="input-group">
                        <input type="text" name="coupon_code" class="form-control @error('coupon_code') is-invalid @enderror" value="{{ old('coupon_code') }}" placeholder="Enter coupon code">
                        <button type="button" class="btn btn-outline-secondary" id="apply-coupon-btn">Apply</button>
                    </div>
                    @error('coupon_code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    <div id="coupon-feedback" class="small mt-2"></div>
                </div>

                <div class="checkout-card">
                    <h2 class="h5 fw-bold mb-3">Payment Method</h2>
                    @php
                        $checkoutPaymentLogos = collect(config('partners.payments', []))
                            ->whereIn('name', ['Mastercard', 'Visa', 'Apple Pay', 'Google Pay', 'SnapScan'])
                            ->values()
                            ->all();
                    @endphp
                    @include('partials.partner-logos', ['variant' => 'compact', 'logos' => $checkoutPaymentLogos, 'class' => 'partner-logos--checkout mb-3'])
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="payment_method" id="pay_paystack" value="paystack" @checked(old('payment_method', 'paystack') === 'paystack')>
                        <label class="form-check-label fw-semibold" for="pay_paystack">Secure Payment via Paystack</label>
                        <div class="d-flex flex-wrap small text-muted mt-2" style="gap:.25rem 1rem">
                            <span>&#9989; Visa</span>
                            <span>&#9989; Mastercard</span>
                            <span>&#9989; American Express</span>
                            <span>&#9989; Instant EFT</span>
                            <span>&#9989; Apple Pay</span>
                            <span>&#9989; Google Pay</span>
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment_method" id="pay_eft" value="eft" @checked(old('payment_method') === 'eft')>
                        <label class="form-check-label" for="pay_eft">Manual EFT / Bank Transfer</label>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="checkout-card sticky-top">
                    <h2 class="h5 fw-bold mb-3">Order Summary</h2>
                    @php $cart = app(\App\Services\CartService::class); @endphp
                    @foreach($cart->items() as $item)
                        <div class="d-flex justify-content-between small mb-2">
                            <span>{{ $item['product']->name }} x{{ $item['quantity'] }}</span>
                            <span>R {{ number_format($item['line_total'], 2) }}</span>
                        </div>
                    @endforeach
                    <hr>
                    <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span>R {{ number_format($subtotal, 2) }}</span></div>
                    <div class="d-flex justify-content-between mb-2 text-success d-none" id="checkout-discount-row">
                        <span>Discount</span><span id="checkout-discount">−R 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2" id="checkout-shipping-row">
                        <span>Shipping</span><span id="checkout-shipping">—</span>
                    </div>
                    <p class="small text-muted d-none mb-2" id="checkout-shipping-note">Courier cost to be confirmed for manual quote orders.</p>
                    <div class="d-flex justify-content-between mb-2">
                        <span>@if($pricesIncludeVat ?? true)VAT included ({{ $vatRate }}%)@else VAT ({{ $vatRate }}%) @endif</span>
                        <span id="checkout-vat">—</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between h5 mb-0">
                        <span>Total</span><strong id="checkout-total">—</strong>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100 mt-3" data-label="Continue to secure payment">Continue to secure payment</button>
                    <p class="small text-muted text-center mt-3 mb-0">
                        You’ll confirm payment on the next screen.
                        @if($helpWhatsapp)
                            Stuck? <a href="{{ $helpWhatsapp }}" target="_blank" rel="noopener">WhatsApp us</a> or call <a href="tel:0875501813">087 550 1813</a>.
                        @else
                            Stuck? Call <a href="tel:0875501813">087 550 1813</a>.
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </form>
</div>

<script type="application/json" id="checkout-data">
{!! json_encode([
    'subtotal' => $subtotal,
    'vatRate' => $vatRate,
    'pricesIncludeVat' => $pricesIncludeVat ?? true,
    'flatRate' => (float) config('shipping.flat_rate'),
    'freeThreshold' => (float) config('shipping.free_threshold'),
    'csrfToken' => csrf_token(),
    'validateCouponUrl' => route('checkout.validate-coupon'),
]) !!}
</script>
@endsection

@push('scripts')
<script src="{{ asset('js/checkout.js') }}" defer></script>
@endpush
