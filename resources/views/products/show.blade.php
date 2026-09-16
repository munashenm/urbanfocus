@extends('layouts.app')

@section('title', $product->seoTitle())
@section('meta_description', $product->seoDescription())
@section('meta_keywords', $product->seoKeywords())
@section('canonical', route('products.show', $product))
@section('og_title', $product->seoTitle())
@section('og_description', $product->seoDescription())
@section('og_type', 'product')
@if($product->primary_image_url)
@section('og_image'){{ $product->primary_image_url }}@endsection
@section('og_image_alt', $product->imageAlt())
@section('twitter_card', 'summary_large_image')
@endif

@push('head')
<meta property="product:price:amount" content="{{ number_format((float) $product->effective_price, 2, '.', '') }}">
<meta property="product:price:currency" content="ZAR">
<meta property="product:availability" content="{{ $product->schemaAvailabilityUrl() }}">
<meta property="product:condition" content="new">
@if($product->brand)
<meta property="product:brand" content="{{ $product->brand }}">
@endif
@if($product->sku)
<meta property="product:retailer_item_id" content="{{ $product->sku }}">
@endif
@endpush

@section('content')
<div class="container py-4 pb-5 mb-lg-0 mb-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            @if($product->category)
                @php $product->category->loadMissing('parent'); @endphp
                @if($product->category->parent)
                    <li class="breadcrumb-item"><a href="{{ $product->category->parent->url() }}">{{ $product->category->parent->name }}</a></li>
                @endif
                <li class="breadcrumb-item"><a href="{{ $product->category->url() }}">{{ $product->category->name }}</a></li>
            @endif
            <li class="breadcrumb-item active">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="row g-5">
        <div class="col-lg-6">
            <div class="product-detail-image">
                @php $primaryAlt = $product->images->first()?->alt_text ?: $product->imageAlt(); @endphp
                <img id="productMainImage" src="{{ $product->display_image_url }}" alt="{{ $primaryAlt }}" width="500" height="500" loading="eager" fetchpriority="high" decoding="async">
            </div>
            @if($product->images->count() > 1)
                <div class="d-flex gap-2 mt-3 flex-wrap product-thumbs">
                    @foreach($product->images as $img)
                        <img src="{{ $img->url }}" alt="{{ $img->alt_text ?: $product->imageAlt() }}" width="72" height="72" loading="lazy" class="product-thumb {{ $loop->first ? 'active' : '' }}">
                    @endforeach
                </div>
            @endif
        </div>
        <div class="col-lg-6">
            @if($product->brand)
                <div class="product-brand mb-1">
                    @if(!empty($brandModel))
                        <a href="{{ route('brands.show', $brandModel) }}" class="text-decoration-none">{{ $product->brand }}</a>
                    @else
                        {{ $product->brand }}
                    @endif
                </div>
            @endif
            <h1 class="h2 fw-bold text-break">{{ $product->name }}</h1>
            <div class="d-flex flex-wrap gap-3 small text-muted mb-3">
                @if($product->sku)<span>SKU: <strong>{{ $product->sku }}</strong></span>@endif
                @if($product->model_number)<span>MPN: <strong>{{ $product->model_number }}</strong></span>@endif
                @if($product->brand)<span>Brand: <strong>{{ $product->brand }}</strong></span>@endif
                <span>Condition: <strong>New</strong></span>
            </div>

            @php
                $discount = $product->discountPercent();
            @endphp

            <div class="my-3">
                @if($product->is_on_sale)
                    <span class="price-old h5">R {{ number_format($product->price, 2) }}</span>
                @endif
                <span class="price-current h3">R {{ number_format($product->effective_price, 2) }}</span>
                <span class="text-muted small"> incl. VAT</span>
                @if($discount)
                    <span class="badge-sale-inline">Save {{ $discount }}%</span>
                @endif
            </div>

            <div class="product-meta-cards mb-4">
                <div class="product-meta-card">
                    <span class="label">Availability</span>
                    <span class="{{ $product->isAvailable() || $product->isQuoteOnly() ? 'text-success' : 'text-danger' }} fw-semibold">
                        {{ $product->availabilityLabel() }}
                    </span>
                </div>
                <div class="product-meta-card">
                    <span class="label">Delivery</span>
                    <span>{{ $product->deliveryEstimate() }}</span>
                </div>
                <div class="product-meta-card">
                    <span class="label">Warranty</span>
                    <span>{{ $product->warrantyLabel() }}</span>
                </div>
            </div>

            @if($product->storefrontShortDescription())
                <p>{{ $product->storefrontShortDescription() }}</p>
            @endif

            <div class="d-flex flex-wrap gap-2 my-4">
                @if($product->isQuoteOnly())
                    <a href="{{ route('b2b.quote', ['product' => $product->id]) }}" class="btn btn-primary btn-lg" data-analytics-event="request_quote">{{ $product->availabilityKey() === 'contact_licensing' ? 'Contact us for licensing' : 'Request a Quote' }}</a>
                @elseif($product->isAvailable())
                    <form action="{{ route('cart.add', $product) }}" method="POST" class="d-flex gap-2 align-items-center">
                        @csrf
                        <label for="product-qty" class="visually-hidden">Quantity</label>
                        <input id="product-qty" type="number" name="quantity" value="1" min="1" max="{{ $product->stock_quantity ?: 99 }}" class="form-control product-qty-input">
                        <button type="submit" class="btn btn-primary btn-lg">Add to Cart</button>
                    </form>
                    <a href="{{ route('b2b.quote', ['product' => $product->id]) }}" class="btn btn-outline-primary btn-lg" data-analytics-event="request_quote">Request Bulk Quote</a>
                @else
                    <div class="checkout-card w-100">
                        <h2 class="h6 fw-bold mb-2">Notify me when back in stock</h2>
                        <form action="{{ route('products.stock-alert', $product) }}" method="POST" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label small mb-1" for="stock-alert-email">Email</label>
                                <input id="stock-alert-email" type="email" name="email" class="form-control form-control-sm" value="{{ old('email', auth()->user()?->email) }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1" for="stock-alert-name">Name <span class="text-muted">(optional)</span></label>
                                <input id="stock-alert-name" type="text" name="name" class="form-control form-control-sm" value="{{ old('name', auth()->user()?->name) }}">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-outline-primary w-100">Notify Me</button>
                            </div>
                        </form>
                    </div>
                    <a href="{{ route('b2b.quote', ['product' => $product->id]) }}" class="btn btn-outline-primary btn-lg" data-analytics-event="request_quote">Request Bulk Quote</a>
                @endif
            </div>

            <div class="border-top pt-3 small text-muted">
                <p class="mb-1">Free shipping on orders over R {{ number_format(config('shipping.free_threshold'), 0) }}</p>
                <p class="mb-0">Can't find this item? <a href="{{ route('b2b.source') }}">Let us source it for you</a> · Call <a href="tel:{{ config('business.phone_tel') }}">{{ config('business.phone') }}</a></p>
                @if(!empty($solutionUrl))
                    <p class="mb-0 mt-1"><a href="{{ $solutionUrl }}">{{ $product->brand }} supply in South Africa</a>
                    @if($product->category)
                        · <a href="{{ $product->category->url() }}">{{ $product->category->name }}</a>
                    @endif
                    </p>
                @elseif($product->category)
                    <p class="mb-0 mt-1"><a href="{{ $product->category->url() }}">Browse {{ $product->category->name }}</a></p>
                @endif
            </div>
        </div>
    </div>

    <div class="row mt-5 g-4">
        <div class="col-lg-7">
            @if($product->storefrontDescriptionHtml())
                <div class="checkout-card mb-4">
                    <h2 class="h5 fw-bold mb-3">Product overview</h2>
                    <div class="product-description">{!! clean_html($product->storefrontDescriptionHtml()) !!}</div>
                </div>
            @endif
            <div class="checkout-card mb-4">
                <h2 class="h5 fw-bold mb-3">{{ config('trust.why_buy.heading') }}</h2>
                <p class="mb-0">{{ config('trust.why_buy.body') }}</p>
            </div>
            @include('partials.corporate-procurement-cta', [
                'compact' => true,
                'heading' => 'Need 5 or more units?',
                'body' => 'Request corporate pricing or upload an RFQ for a formal Urban Focus quotation.',
                'primaryLabel' => 'Upload RFQ',
                'secondaryLabel' => 'Request Corporate Pricing',
                'secondaryUrl' => route('b2b.quote', ['product' => $product->id]),
            ])
            <div class="checkout-card mt-4">
                <h2 class="h5 fw-bold mb-3">Delivery and availability</h2>
                <p class="mb-1"><strong>Availability:</strong> {{ $product->availabilityLabel() }}</p>
                <p class="mb-1"><strong>Delivery:</strong> {{ $product->deliveryEstimate() }}</p>
                @if($product->warrantyLabel())
                    <p class="mb-0"><strong>Warranty:</strong> {{ $product->warrantyLabel() }}</p>
                @endif
            </div>
            @if(count($product->buyerFitLines()))
            <div class="checkout-card mt-4">
                <h2 class="h5 fw-bold mb-3">Ideal applications</h2>
                <ul class="mb-0">
                    @foreach($product->buyerFitLines() as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
        <div class="col-lg-5">
            @if(count($specs = $product->specificationsList()))
                <div class="checkout-card">
                    <h2 class="h5 fw-bold mb-3">Technical specifications</h2>
                    <table class="table table-sm spec-table mb-0">
                        <tbody>
                            @foreach($specs as $key => $value)
                                <tr><th scope="row">{{ $key }}</th><td>{{ $value }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            @if(count($product->listingFaqs()))
                <div class="checkout-card mt-4">
                    <h2 class="h5 fw-bold mb-3">Frequently asked questions</h2>
                    @foreach($product->listingFaqs() as $faq)
                        <h3 class="h6 fw-semibold mb-1">{{ $faq['question'] }}</h3>
                        <p class="small text-muted">{{ $faq['answer'] }}</p>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if($relatedProducts->count())
    <section class="mt-5 pt-4 border-top">
        <h2 class="section-title">Related Products</h2>
        <div class="row g-4">
            @foreach($relatedProducts as $related)
                <div class="col-6 col-md-3">@include('partials.product-card', ['product' => $related])</div>
            @endforeach
        </div>
    </section>
    @endif

    @if($accessories->count())
    <section class="mt-5 pt-4 border-top">
        <h2 class="section-title">Accessories &amp; Add-ons</h2>
        <div class="row g-4">
            @foreach($accessories as $accessory)
                <div class="col-6 col-md-3">@include('partials.product-card', ['product' => $accessory])</div>
            @endforeach
        </div>
    </section>
    @endif

    @if(!empty($recentlyViewed) && $recentlyViewed->count())
    <section class="mt-5 pt-4 border-top">
        <h2 class="section-title">Recently Viewed</h2>
        <div class="row g-4">
            @foreach($recentlyViewed as $recent)
                <div class="col-6 col-md-3">@include('partials.product-card', ['product' => $recent])</div>
            @endforeach
        </div>
    </section>
    @endif
</div>

<div class="mobile-buy-bar d-lg-none">
    <div class="container d-flex align-items-center justify-content-between gap-2 py-2">
        <div>
            <strong class="d-block">R {{ number_format($product->effective_price, 2) }}</strong>
            <span class="small {{ $product->isAvailable() || $product->isQuoteOnly() ? 'text-success' : 'text-danger' }}">{{ $product->availabilityLabel() }}</span>
        </div>
        @if($product->isQuoteOnly())
            <a href="{{ route('b2b.quote', ['product' => $product->id]) }}" class="btn btn-primary btn-sm">Request Quote</a>
        @elseif($product->isAvailable())
            <form action="{{ route('cart.add', $product) }}" method="POST" class="d-flex align-items-center gap-2">
                @csrf
                <input type="number" name="quantity" value="1" min="1" max="{{ $product->stock_quantity ?: 99 }}" class="form-control form-control-sm product-qty-input" aria-label="Quantity">
                <button type="submit" class="btn btn-primary">Add to Cart</button>
            </form>
        @else
            <a href="{{ route('b2b.quote', ['product' => $product->id]) }}" class="btn btn-outline-primary btn-sm">Get Quote</a>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>document.body.classList.add('has-mobile-buy-bar');</script>
<script src="{{ asset('js/product-gallery.js') }}" defer></script>
<script>window.ufAnalyticsItem = @json($analyticsItem ?? []); window.ufAnalyticsViewItem = true;</script>
@endpush

@push('schema')
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) !!}</script>
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) !!}</script>
@if(!empty($faqSchema))
<script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) !!}</script>
@endif
@endpush
