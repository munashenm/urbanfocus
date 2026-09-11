@php
    $variant = $variant ?? 'default';
    $heading = $heading ?? 'Buying for a business or organisation?';
    $body = $body ?? 'Upload your equipment list, BOM or RFQ and receive a formal quotation from Urban Focus.';
    $primaryLabel = $primaryLabel ?? 'Upload RFQ';
    $primaryUrl = $primaryUrl ?? route('b2b.rfq');
    $secondaryLabel = $secondaryLabel ?? 'Request Corporate Pricing';
    $secondaryUrl = $secondaryUrl ?? route('b2b.quote');
    $compact = $compact ?? false;
@endphp
<aside class="corporate-procurement-cta {{ $compact ? 'corporate-procurement-cta--compact' : '' }}" aria-label="Corporate procurement">
    <div class="corporate-procurement-cta__body">
        <h2 class="{{ $compact ? 'h6' : 'h5' }} fw-bold mb-1">{{ $heading }}</h2>
        <p class="small text-muted mb-3">{{ $body }}</p>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ $primaryUrl }}" class="btn btn-primary {{ $compact ? 'btn-sm' : '' }}" data-analytics-event="upload_rfq">{{ $primaryLabel }}</a>
            <a href="{{ $secondaryUrl }}" class="btn btn-outline-primary {{ $compact ? 'btn-sm' : '' }}" data-analytics-event="corporate_pricing_request">{{ $secondaryLabel }}</a>
        </div>
    </div>
</aside>
