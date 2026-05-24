@php
    $title = $title ?? '';
    $subtitle = $subtitle ?? null;
    $url = $url ?? null;
    $linkLabel = $linkLabel ?? 'View All';
@endphp
<div class="section-header d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
    <div>
        <h2 class="section-title mb-0">{{ $title }}</h2>
        @if($subtitle)<p class="section-subtitle text-muted mb-0 mt-1">{{ $subtitle }}</p>@endif
    </div>
    @if($url)
        <a href="{{ $url }}" class="btn btn-outline-primary btn-sm">{{ $linkLabel }}</a>
    @endif
</div>
