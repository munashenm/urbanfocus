@if($products->count())
<section class="py-5 {{ !empty($bgLight) ? 'bg-light' : '' }}">
    <div class="container">
        @include('partials.section-header', [
            'title' => $title,
            'subtitle' => $subtitle ?? null,
            'url' => $url ?? null,
            'linkLabel' => $linkLabel ?? 'View All',
        ])
        @if(!empty($sectionKey) && !empty($sectionBrands[$sectionKey]))
            @include('partials.section-brand-strip', ['brands' => $sectionBrands[$sectionKey]])
        @endif
        <div class="row g-4">
            @foreach($products as $product)
                <div class="col-6 col-md-4 col-lg-3">@include('partials.product-card', ['product' => $product])</div>
            @endforeach
        </div>
    </div>
</section>
@endif
