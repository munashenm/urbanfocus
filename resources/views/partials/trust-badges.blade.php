<section class="trust-badges-strip py-4">
    <div class="container">
        <div class="row g-3 justify-content-center">
            @foreach(config('trust.badges', []) as $badge)
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="trust-badge-card text-center">
                        <span class="trust-badge-icon" aria-hidden="true">✓</span>
                        <span class="trust-badge-label">{{ $badge['label'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
