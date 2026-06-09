@extends('layouts.admin')

@section('page_title', $user->exists ? 'Edit User' : 'Add User')

@section('content')
<form action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" method="POST">
    @csrf
    @if($user->exists) @method('PUT') @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card admin-card"><div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mt-4">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $user->is_active ?? true)) @disabled($user->exists && $user->id === auth()->id())>
                            <label class="form-check-label" for="is_active">Active account</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label d-block">Roles</label>
                        <div class="row g-2">
                            @foreach($roles as $role)
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="roles[]" value="{{ $role->slug }}" id="role_{{ $role->id }}" @checked(in_array($role->slug, old('roles', $user->exists ? $user->roles->pluck('slug')->all() : []))) @disabled($user->exists && $user->id === auth()->id() && $role->slug === 'super-admin')>
                                        <label class="form-check-label" for="role_{{ $role->id }}"><strong>{{ $role->name }}</strong><br><small class="text-muted">{{ $role->description }}</small></label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('roles')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div></div>
        </div>

        <div class="col-lg-4">
            <div class="card admin-card"><div class="card-body">
                <h3 class="h6 fw-bold">Password</h3>
                @if($user->exists)
                    <p class="small text-muted">Leave blank to keep the current password.</p>
                    @if($user->last_login_at)
                        <p class="small text-muted">Last login: {{ $user->last_login_at->format('d M Y H:i') }}</p>
                    @endif
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" minlength="8">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="password_confirmation" class="form-control" minlength="8">
                    </div>
                @else
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required minlength="8">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" name="password_confirmation" class="form-control" required minlength="8">
                    </div>
                @endif
                <button type="submit" class="btn btn-primary w-100">{{ $user->exists ? 'Update User' : 'Create User' }}</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
            </div></div>
        </div>
    </div>
</form>
@endsection
