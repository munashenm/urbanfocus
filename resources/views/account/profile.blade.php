@extends('layouts.app')

@section('title', 'My Profile | Urban Focus')

@section('content')
<div class="container py-4">
    <h1 class="h2 fw-bold mb-4">My Profile</h1>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="checkout-card">
                <h2 class="h5 fw-bold mb-3">Account Details</h2>
                <form action="{{ route('account.profile.update') }}" method="POST">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $user->company_name) }}" placeholder="Optional — pre-fills at checkout">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">VAT Number</label>
                        <input type="text" name="vat_number" class="form-control" value="{{ old('vat_number', $user->vat_number) }}" placeholder="Optional — for tax invoices">
                    </div>
                    @if($user->is_admin)
                        <p class="small text-muted mb-3">Role: <span class="badge bg-primary">Administrator</span></p>
                    @endif
                    <button type="submit" class="btn btn-primary">Save Profile</button>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="checkout-card">
                <h2 class="h5 fw-bold mb-3">Change Password</h2>
                <p class="small text-muted">For security, your current password cannot be displayed. Enter it below to set a new one.</p>
                <form action="{{ route('account.profile.password') }}" method="POST">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required>
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required minlength="8">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="password_confirmation" class="form-control" required minlength="8">
                    </div>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('account.dashboard') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
</div>
@endsection
