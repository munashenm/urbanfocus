@php
    $compare = app(\App\Services\CompareService::class);
    $compareProducts = $compare->products();
@endphp
@if($compareProducts->count() && ! request()->routeIs('compare.index'))
<div class="compare-bar" role="region" aria-label="Product comparison">
    <div class="container d-flex align-items-center gap-3 py-2">
        <strong class="small text-nowrap">Compare ({{ $compareProducts->count() }}/{{ \App\Services\CompareService::MAX_ITEMS }})</strong>
        <div class="compare-bar-items">
            @foreach($compareProducts as $item)
                <a href="{{ route('products.show', $item) }}" class="compare-bar-thumb" title="{{ $item->name }}">
                    <img src="{{ $item->display_image_url }}" alt="{{ $item->imageAlt() }}" width="40" height="40">
                </a>
            @endforeach
        </div>
        <div class="ms-auto d-flex gap-2">
            <a href="{{ route('compare.index') }}" class="btn btn-primary btn-sm">{{ $compareProducts->count() >= 2 ? 'Compare' : 'View' }}</a>
            <form action="{{ route('compare.clear') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">Clear</button>
            </form>
        </div>
    </div>
</div>
@endif
