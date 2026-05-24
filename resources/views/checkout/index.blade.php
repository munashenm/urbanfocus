@extends('layouts.app')

@section('title', 'Checkout | Urban Focus')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<div class="container py-4">
    <h1 class="h2 fw-bold mb-4">Checkout</h1>

    <form action="{{ route('checkout.store') }}" method="POST">
        @csrf
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="checkout-card mb-4">
                    <h2 class="h5 fw-bold mb-3">Billing Details</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="billing_first_name" class="form-control @error('billing_first_name') is-invalid @enderror" value="{{ old('billing_first_name', auth()->user()?->name) }}" required>
                            @error('billing_first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="billing_last_name" class="form-control" value="{{ old('billing_last_name') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Company</label>
                            <input type="text" name="billing_company" class="form-control" value="{{ old('billing_company', auth()->user()?->company_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">VAT Number</label>
                            <input type="text" name="billing_vat_number" class="form-control" value="{{ old('billing_vat_number', auth()->user()?->vat_number) }}" placeholder="Optional — for tax invoice">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address *</label>
                            <input type="text" name="billing_address_line_1" class="form-control" value="{{ old('billing_address_line_1') }}" required>
                        </div>
                        <div class="col-12">
                            <input type="text" name="billing_address_line_2" class="form-control" placeholder="Apartment, suite, etc." value="{{ old('billing_address_line_2') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City *</label>
                            <input type="text" name="billing_city" class="form-control" value="{{ old('billing_city') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Province *</label>
                            <select name="billing_province" class="form-select" required>
                                @foreach(['Gauteng','Western Cape','KwaZulu-Natal','Eastern Cape','Free State','Limpopo','Mpumalanga','North West','Northern Cape'] as $prov)
                                    <option value="{{ $prov }}" @selected(old('billing_province') === $prov)>{{ $prov }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Postal Code *</label>
                            <input type="text" name="billing_postal_code" class="form-control" value="{{ old('billing_postal_code') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="customer_email" class="form-control" value="{{ old('customer_email', auth()->user()?->email) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone *</label>
                            <input type="text" name="customer_phone" class="form-control" value="{{ old('customer_phone', auth()->user()?->phone) }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Order Notes</label>
                            <textarea name="customer_notes" class="form-control" rows="3">{{ old('customer_notes') }}</textarea>
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
                    @include('partials.partner-logos', ['variant' => 'compact', 'class' => 'partner-logos--checkout mb-3'])
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="payment_method" id="pay_payfast" value="payfast" @checked(old('payment_method', 'payfast') === 'payfast')>
                        <label class="form-check-label" for="pay_payfast">PayFast (Card, Instant EFT, etc.)</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment_method" id="pay_eft" value="eft" @checked(old('payment_method') === 'eft')>
                        <label class="form-check-label" for="pay_eft">Manual EFT / Bank Transfer</label>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="checkout-card sticky-top" style="top:80px">
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
                        <span>VAT ({{ $vatRate }}%)</span><span id="checkout-vat">—</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between h5 mb-0">
                        <span>Total</span><strong id="checkout-total">—</strong>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100 mt-3">Place Order</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script type="application/json" id="checkout-data">
{!! json_encode([
    'subtotal' => $subtotal,
    'vatRate' => $vatRate,
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
