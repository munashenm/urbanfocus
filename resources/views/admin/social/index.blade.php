@extends('layouts.admin')
@section('page_title', 'Social Media Auto-Post')
@section('content')

@if(! $enabled)
<div class="alert alert-warning">Auto-posting is off. Set <code>SOCIAL_POSTING_ENABLED=true</code> in <code>.env</code> after adding API keys below.</div>
@endif

<div class="row g-4 mb-4">
    <div class="col-md-4"><div class="card"><div class="card-body text-center"><div class="h3 mb-0">{{ $stats['pending'] }}</div><div class="small text-muted">Pending</div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body text-center"><div class="h3 mb-0 text-success">{{ $stats['posted'] }}</div><div class="small text-muted">Posted</div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body text-center"><div class="h3 mb-0 text-danger">{{ $stats['failed'] }}</div><div class="small text-muted">Failed</div></div></div></div>
</div>

<form action="{{ route('admin.social.publish') }}" method="POST" class="d-inline mb-4">
    @csrf
    <button type="submit" class="btn btn-primary">Publish Pending Posts Now</button>
</form>
<form action="{{ route('admin.social.retry-failed') }}" method="POST" class="d-inline mb-4 ms-2">
    @csrf
    <button type="submit" class="btn btn-outline-danger">Retry Failed Posts</button>
</form>
<form action="{{ route('admin.social.queue-all') }}" method="POST" class="d-inline mb-4 ms-2">
    @csrf
    <button type="submit" class="btn btn-outline-primary">Queue All Products &amp; Articles</button>
</form>

<div class="card mb-4"><div class="card-body">
    <h2 class="h5 fw-bold">Setup (add to .env)</h2>
    <div class="row g-3 small">
        <div class="col-md-6">
            <strong>Facebook &amp; Instagram (Meta)</strong>
            <ul class="text-muted mb-0">
                <li><a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">Meta Developer App</a></li>
                <li><code>META_PAGE_ID</code>, <code>META_PAGE_ACCESS_TOKEN</code></li>
                <li><code>META_INSTAGRAM_ACCOUNT_ID</code> (Business account)</li>
            </ul>
        </div>
        <div class="col-md-6">
            <strong>X — <a href="{{ config('social.x') }}" target="_blank" rel="noopener">{{ '@urbanfocusza' }}</a></strong>
            <ul class="text-muted mb-0">
                <li><a href="https://developer.x.com" target="_blank" rel="noopener">X Developer Portal</a></li>
                <li><strong>Recommended:</strong> <code>X_API_KEY</code>, <code>X_API_SECRET</code>, <code>X_ACCESS_TOKEN</code>, <code>X_ACCESS_TOKEN_SECRET</code></li>
                <li>Or OAuth 2 user token: <code>X_BEARER_TOKEN</code> (not the app-only bearer token)</li>
            </ul>
        </div>
        <div class="col-md-6">
            <strong>TikTok — <a href="{{ config('social.tiktok') }}" target="_blank" rel="noopener">{{ '@urbanfocussa' }}</a></strong>
            <ul class="text-muted mb-0">
                <li><a href="https://developers.tiktok.com" target="_blank" rel="noopener">TikTok for Developers</a></li>
                <li><code>TIKTOK_CLIENT_KEY</code>, <code>TIKTOK_ACCESS_TOKEN</code></li>
                <li>Set <code>SOCIAL_POST_TIKTOK=true</code> when approved</li>
            </ul>
        </div>
        <div class="col-md-6">
            <strong>Automation (cPanel cron)</strong>
            <p class="text-muted mb-0">Run <code>deploy/social-post.php</code> every 15–30 min, or set a cron URL hit after copying to <code>public_html</code>.</p>
        </div>
    </div>
</div></div>

<div class="card"><div class="table-responsive"><table class="table mb-0 small">
<thead><tr><th>Platform</th><th>Item</th><th>Status</th><th>When</th><th>Error</th></tr></thead>
<tbody>
@forelse($recent as $row)
<tr>
    <td>{{ ucfirst($row->platform) }}</td>
    <td>{{ class_basename($row->postable_type) }} #{{ $row->postable_id }}</td>
    <td><span class="badge bg-{{ $row->status === 'posted' ? 'success' : ($row->status === 'failed' ? 'danger' : 'secondary') }}">{{ $row->status }}</span></td>
    <td>{{ $row->posted_at?->format('d M Y H:i') ?: $row->created_at->format('d M Y H:i') }}</td>
    <td class="text-muted">{{ \Illuminate\Support\Str::limit($row->error_message, 60) }}</td>
</tr>
@empty
<tr><td colspan="5" class="text-muted p-4">No social posts queued yet. Publish a product or blog article to queue posts.</td></tr>
@endforelse
</tbody></table></div></div>
@endsection
