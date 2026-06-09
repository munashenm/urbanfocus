@extends('layouts.admin')

@section('page_title', 'Audit Logs')

@section('content')
<form class="admin-filters mb-3" method="GET">
    <input type="search" name="action" class="form-control form-control-sm" placeholder="Filter by action…" value="{{ request('action') }}">
    <button class="btn btn-sm btn-outline-secondary">Filter</button>
</form>

<div class="card admin-card admin-data-table">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="small">{{ $log->created_at->format('d M Y H:i') }}</td>
                        <td>{{ $log->user?->name ?? 'System' }}</td>
                        <td><code>{{ $log->action }}</code></td>
                        <td class="small text-muted">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                        <td class="small">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="admin-empty">No audit logs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
