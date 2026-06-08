<div class="blog-sidebar-card mb-4">
    <h2 class="h6 fw-bold mb-2">IT deals & insights</h2>
    <p class="small text-muted mb-3">Get new buying guides, product news and special pricing for South African businesses.</p>
    <form action="{{ route('newsletter.store') }}" method="POST" class="d-flex gap-2">
        @csrf
        <input type="email" name="email" class="form-control form-control-sm" placeholder="you@company.co.za" required maxlength="255" aria-label="Email address">
        <button type="submit" class="btn btn-primary btn-sm flex-shrink-0">Subscribe</button>
    </form>
</div>
