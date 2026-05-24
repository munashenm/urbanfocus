@php
    $addr = config('business.address');
    $lines = array_filter([
        $addr['line1'] ?? '',
        $addr['line2'] ?? '',
        trim(($addr['city'] ?? '').(! empty($addr['province']) ? ', '.$addr['province'] : '')),
    ]);
@endphp
@if(!empty($lines))
    @if($inline ?? false)
        <span class="{{ $class ?? '' }}">{!! implode(', ', array_map('e', $lines)) !!}</span>
    @elseif($block ?? false)
        <div class="{{ $class ?? '' }}">
            @if($showLabel ?? true)<strong class="d-block text-navy mb-1">Address</strong>@endif
            {!! implode('<br>', array_map('e', $lines)) !!}
        </div>
    @else
        <li class="{{ $class ?? '' }}">
            @if($showLabel ?? true)<strong class="d-block text-white mb-1">Address</strong>@endif
            <span class="text-white-50">{!! implode('<br>', array_map('e', $lines)) !!}</span>
        </li>
    @endif
@endif
