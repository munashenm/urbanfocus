@extends('layouts.app')

@section('title', 'Contact Us | Urban Focus')

@section('content')
<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-5">
            <h1 class="h2 fw-bold mb-3">Contact Urban Focus</h1>
            <p class="text-muted">Get in touch for product enquiries, bulk orders, or manual courier quotes.</p>
            <ul class="list-unstyled mt-4">
                <li class="mb-3"><strong>Phone:</strong> <a href="tel:0875501813">087 550 1813</a></li>
                <li class="mb-3"><strong>Email:</strong> <a href="mailto:sales@urbanfocus.co.za">sales@urbanfocus.co.za</a></li>
                <li><strong>Website:</strong> www.urbanfocus.co.za</li>
            </ul>
        </div>
        <div class="col-lg-7">
            <div class="checkout-card">
                <form action="{{ route('contact.store') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name *</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Message *</label>
                            <textarea name="message" class="form-control @error('message') is-invalid @enderror" rows="5" required>{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Send Message</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
