@extends('layouts.app')

@section('title', 'Login | Urban Focus')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="checkout-card">
                <div class="text-center mb-4">
                    <a href="{{ route('home') }}" class="site-logo site-logo--auth d-inline-flex">
                        <img src="{{ asset('images/logo-stacked.png') }}" alt="Urban Focus" width="180" height="88">
                    </a>
                </div>
                <h1 class="h4 fw-bold mb-4 text-center">Login</h1>
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
                <p class="text-center mt-3 mb-0 small">Don't have an account? <a href="{{ route('register') }}">Register</a></p>
            </div>
        </div>
    </div>
</div>
@endsection
