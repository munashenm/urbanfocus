@php
    $defaults = collect(config('partners.default_brands', []));
    $defaultsByName = $defaults->keyBy('name');
    $defaultsBySlug = $defaults->keyBy('slug');
    $brandItems = collect();

    if (isset($brands) && $brands->count()) {
        $brandItems = $brands->map(function ($brand) use ($defaultsByName, $defaultsBySlug) {
            $slug = $brand->slug ?? \Illuminate\Support\Str::slug($brand->name);
            $logo = $brand->logo ?? null;

            if ($logo && ! str_starts_with($logo, 'images/') && ! str_starts_with($logo, 'http')) {
                $logo = str_starts_with($logo, 'storage/') ? $logo : 'storage/'.$logo;
            }

            if (! $logo && $defaultsBySlug->has($slug)) {
                $logo = $defaultsBySlug->get($slug)['logo'];
            }

            if (! $logo && $defaultsByName->has($brand->name)) {
                $logo = $defaultsByName->get($brand->name)['logo'];
            }

            return [
                'name' => $brand->name,
                'slug' => $slug,
                'url' => ($brand instanceof \App\Models\Brand && $brand->exists && $slug !== '')
                    ? route('brands.show', $slug)
                    : route('shop.index', ['brand' => $brand->name]),
                'logo' => $logo,
            ];
        })->filter(fn (array $brand) => ! empty($brand['logo']));
    }

    if ($brandItems->isEmpty()) {
        $brandItems = $defaults->map(fn ($b) => [
            'name' => $b['name'],
            'slug' => $b['slug'] ?? \Illuminate\Support\Str::slug($b['name']),
            'url' => route('brands.show', $b['slug'] ?? \Illuminate\Support\Str::slug($b['name'])),
            'logo' => $b['logo'],
        ]);
    }
@endphp

@if($brandItems->count())
<div class="brand-carousel d-flex flex-wrap justify-content-center gap-3">
    @foreach($brandItems as $brand)
        <a href="{{ $brand['url'] }}" class="brand-logo-card brand-logo-card--{{ $brand['slug'] ?? \Illuminate\Support\Str::slug($brand['name']) }}" title="{{ $brand['name'] }}">
            @if(!empty($brand['logo']))
                <img src="{{ public_asset_url($brand['logo']) }}" alt="{{ $brand['name'] }}" loading="lazy" width="140" height="42">
            @else
                <span class="brand-logo-fallback">{{ $brand['name'] }}</span>
            @endif
        </a>
    @endforeach
</div>
@endif
