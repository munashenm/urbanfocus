@php
    $defaults = collect(config('partners.default_brands', []))->keyBy('name');
    $brandItems = collect();

    if (isset($brands) && $brands->count()) {
        $brandItems = $brands->map(function ($brand) use ($defaults) {
            $logo = $brand->logo ?? null;

            if ($logo && ! str_starts_with($logo, 'images/')) {
                $logo = str_starts_with($logo, 'storage/') ? $logo : 'storage/'.$logo;
            }

            if (! $logo && $defaults->has($brand->name)) {
                $logo = $defaults->get($brand->name)['logo'];
            }

            return [
                'name' => $brand->name,
                'url' => ! empty($brand->slug)
                    ? route('brands.show', $brand->slug)
                    : route('shop.index', ['brand' => $brand->name]),
                'logo' => $logo,
            ];
        });
    }

    if ($brandItems->isEmpty()) {
        $brandItems = $defaults->map(fn ($b) => [
            'name' => $b['name'],
            'url' => route('brands.show', $b['slug'] ?? \Illuminate\Support\Str::slug($b['name'])),
            'logo' => $b['logo'],
        ]);
    }
@endphp

@if($brandItems->count())
<div class="brand-carousel d-flex flex-wrap justify-content-center gap-3">
    @foreach($brandItems as $brand)
        <a href="{{ $brand['url'] }}" class="brand-logo-card" title="{{ $brand['name'] }}">
            @if(!empty($brand['logo']))
                <img src="{{ asset($brand['logo']) }}" alt="{{ $brand['name'] }}" loading="lazy" width="120" height="36">
            @else
                <span class="brand-logo-fallback">{{ $brand['name'] }}</span>
            @endif
        </a>
    @endforeach
</div>
@endif
