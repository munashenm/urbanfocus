<div class="page-hero">
    <div class="container">
        <h1 class="h2 fw-bold mb-2">{{ $title }}</h1>
        @if(!empty($subtitle))<p class="mb-0 opacity-75">{{ $subtitle }}</p>@endif
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="checkout-card">
                <form action="{{ route('b2b.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    @if(!empty($productId))
                        <input type="hidden" name="product_id" value="{{ $productId }}">
                    @endif
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company</label>
                            <input type="text" name="company" class="form-control" value="{{ old('company') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                        </div>
                        @if($type === 'rfq')
                        <div class="col-12">
                            <label class="form-label">Upload RFQ Document</label>
                            <input type="file" name="rfq_file" class="form-control @error('rfq_file') is-invalid @enderror" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt">
                            <div class="form-text">PDF, Word, Excel or CSV — max 10MB</div>
                            @error('rfq_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @endif
                        <div class="col-12">
                            <label class="form-label">{{ $messageLabel ?? 'Message' }} *</label>
                            <textarea name="message" class="form-control @error('message') is-invalid @enderror" rows="5" required>{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-lg">{{ $submitLabel ?? 'Submit Enquiry' }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
