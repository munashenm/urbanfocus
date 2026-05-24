<section class="testimonials-section py-5 bg-light">
    <div class="container">
        @include('partials.section-header', [
            'title' => 'Trusted by Businesses Across South Africa',
            'subtitle' => 'What our corporate and SME customers say about Urban Focus.',
        ])

        <div class="row g-4">
            @foreach(config('trust.testimonials', []) as $testimonial)
                <div class="col-md-4">
                    <div class="testimonial-card h-100">
                        <div class="testimonial-stars mb-2" aria-label="{{ $testimonial['rating'] }} out of 5 stars">
                            @for($i = 0; $i < ($testimonial['rating'] ?? 5); $i++)★@endfor
                        </div>
                        <p class="testimonial-quote">"{{ $testimonial['quote'] }}"</p>
                        <div class="testimonial-author">
                            <strong>{{ $testimonial['name'] }}</strong>
                            <span class="d-block small text-muted">{{ $testimonial['company'] }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @php $reviews = config('trust.google_reviews'); @endphp
        @if(!empty($reviews['enabled']) || !empty($reviews['url']))
            <div class="google-reviews-bar mt-4 text-center">
                <div class="d-inline-flex align-items-center gap-3 flex-wrap justify-content-center bg-white border rounded-pill px-4 py-2">
                    <span class="fw-bold">{{ $reviews['rating'] ?? '4.8' }} ★</span>
                    <span class="text-muted small">Google Reviews · {{ $reviews['count'] ?? '50' }}+ reviews</span>
                    @if(!empty($reviews['url']))
                        <a href="{{ $reviews['url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Leave a Review</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</section>
