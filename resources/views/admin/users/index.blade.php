@extends('layouts.admin')

@section('page_title', 'Users')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <form class="d-flex" method="GET">
        <input type="search" name="q" class="form-control form-control-sm me-2" placeholder="Search users..." value="{{ request('q') }}">
        <button class="btn btn-sm btn-outline-secondary">Search</button>
    </form>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">Add User</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->phone ?? '—' }}</td>
                        <td>
                            @if($user->is_admin)
                                <span class="badge bg-primary">Admin</span>
                            @else
                                <span class="badge bg-secondary">Customer</span>
                            @endif
                        </td>
                        <td>{{ $user->created_at->format('d M Y') }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            @if($user->id !== auth()->id())
                                <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this user?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
