@extends('layouts.admin')

@section('page_title', 'Edit '.$role->name)

@section('content')
<form action="{{ route('admin.roles.update', $role) }}" method="POST">
    @csrf @method('PUT')
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card admin-card">
                <div class="card-header bg-white fw-semibold">Permissions</div>
                <div class="card-body">
                    @foreach($permissions as $group => $items)
                        <h3 class="h6 fw-bold mt-3">{{ $group }}</h3>
                        <div class="row g-2 mb-3">
                            @foreach($items as $permission)
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="permissions[]" value="{{ $permission->name }}" id="perm_{{ $permission->id }}" @checked($role->permissions->contains('id', $permission->id))>
                                        <label class="form-check-label" for="perm_{{ $permission->id }}">{{ $permission->label }} <code class="small">{{ $permission->name }}</code></label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card admin-card">
                <div class="card-body">
                    @unless($role->is_system)
                        <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4">{{ old('description', $role->description) }}</textarea></div>
                    @endunless
                    <button class="btn btn-primary w-100">Save role</button>
                    <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
