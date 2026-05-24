@extends('layouts.app')

@section('title', 'Track Your Order | Urban Focus')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="checkout-card">
                <h1 class="h2 fw-bold mb-2">Track Your Order</h1>
                <p class="text-muted mb-4">Enter your order number and the email address used at checkout.</p>

                <form action="{{ route('orders.track.lookup') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Order Number *</label>
                        <input type="text" name="order_number" class="form-control @error('order_number') is-invalid @enderror" value="{{ old('order_number') }}" placeholder="e.g. UF-ABC12345" required>
                        @error('order_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Find Order</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
