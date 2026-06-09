@extends('layouts.admin')

@section('page_title', 'Roles & Permissions')

@section('content')
<div class="row g-4">
    @foreach($roles as $role)
        <div class="col-lg-6">
            <div class="card admin-card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <strong>{{ $role->name }}</strong>
                        @if($role->is_system)<span class="badge bg-secondary ms-1">System</span>@endif
                    </div>
                    @if($role->slug !== 'super-admin')
                        <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    @endif
                </div>
                <div class="card-body">
                    <p class="text-muted small">{{ $role->description }}</p>
                    <p class="small mb-2"><strong>{{ $role->users_count }}</strong> user(s)</p>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($role->permissions as $permission)
                            <span class="badge bg-light text-dark border">{{ $permission->name }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
