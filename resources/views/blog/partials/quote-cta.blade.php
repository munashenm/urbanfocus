@php
    $defaultMessage = old('message', 'I read your article "'.$article->title.'" and would like a quote for related products.');
@endphp
<section class="blog-quote-cta mt-5" id="get-a-quote">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-lg-5">
            <div class="row g-4 align-items-start">
                <div class="col-lg-5">
                    <h2 class="h4 fw-bold mb-2">Get a Quote</h2>
                    <p class="text-muted mb-3">Tell us what you need. Urban Focus supplies IT hardware and software across South Africa with VAT invoices, bulk pricing and fast turnaround.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('b2b.quote') }}" class="btn btn-outline-primary btn-sm">Request a Quote</a>
                        <a href="{{ route('contact') }}" class="btn btn-outline-secondary btn-sm">Contact Urban Focus</a>
                    </div>
                </div>
                <div class="col-lg-7">
                    @if($errors->any())
                        <div class="alert alert-danger py-2 small">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif
                    <form action="{{ route('b2b.store') }}" method="POST" class="row g-3">
                        @csrf
                        <input type="hidden" name="type" value="quote">
                        <div class="col-sm-6">
                            <label class="form-label small">Name<span class="text-danger">*</span></label>
                            <input type="text" name="name" value="{{ old('name') }}" class="form-control" required maxlength="100">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small">Company</label>
                            <input type="text" name="company" value="{{ old('company') }}" class="form-control" maxlength="150">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small">Email<span class="text-danger">*</span></label>
                            <input type="email" name="email" value="{{ old('email') }}" class="form-control" required maxlength="255">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label small">Phone</label>
                            <input type="text" name="phone" value="{{ old('phone') }}" class="form-control" maxlength="30">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">What do you need a quote for?<span class="text-danger">*</span></label>
                            <textarea name="message" rows="3" class="form-control" required maxlength="5000">{{ $defaultMessage }}</textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Request a Quote</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
