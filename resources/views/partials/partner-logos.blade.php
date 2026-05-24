@php
    $variant = $variant ?? 'full';
    $payments = config('partners.payments', []);
    $shipping = config('partners.shipping', []);
    $trust = $variant === 'compact' ? [] : config('partners.trust', []);
    $all = $variant === 'payments'
        ? $payments
        : ($variant === 'compact' ? array_merge($payments, $shipping) : array_merge($payments, $shipping, $trust));
@endphp

@if(count($all))
<div class="partner-logos {{ $class ?? '' }}">
    @if(!empty($title))
        <p class="partner-logos-title">{{ $title }}</p>
    @endif
    <div class="partner-logos-grid">
        @foreach($all as $partner)
            <img src="{{ asset($partner['logo']) }}"
                 alt="{{ $partner['alt'] ?? $partner['name'] }}"
                 title="{{ $partner['name'] }}"
                 class="partner-logo"
                 loading="lazy"
                 height="32">
        @endforeach
    </div>
</div>
@endif
