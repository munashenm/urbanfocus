@extends('layouts.admin')
@section('page_title', 'Social Media Auto-Post')
@section('content')

@if(! $enabled)
<div class="alert alert-warning">Direct auto-posting is off. Set <code>SOCIAL_POSTING_ENABLED=true</code> in <code>.env</code> after adding API keys below.</div>
@endif

<div class="card mb-4 border-primary">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h2 class="h5 fw-bold mb-1">Make.com Marketing Automation</h2>
                <p class="text-muted small mb-2">When a product or blog post is published, Urban Focus sends an AI-captioned payload to Make.com, which posts to Facebook, LinkedIn &amp; X.</p>
                <div>
                    @if($makeEnabled && $makeConfigured)
                        <span class="badge bg-success">Active</span>
                    @elseif($makeEnabled && ! $makeConfigured)
                        <span class="badge bg-warning text-dark">Enabled — add webhook URLs</span>
                    @else
                        <span class="badge bg-secondary">Disabled</span>
                    @endif
                    @foreach(config('make.platforms', []) as $p)
                        <span class="badge bg-light text-dark border">{{ ucfirst($p) }}</span>
                    @endforeach
                </div>
            </div>
            <div class="text-end small">
                <div class="h4 mb-0 text-success">{{ $webhookStats['success'] }}</div>
                <div class="text-muted">webhook deliveries</div>
                @if($webhookStats['failed'])<div class="text-danger">{{ $webhookStats['failed'] }} failed</div>@endif
            </div>
        </div>
        @if(! $makeEnabled || ! $makeConfigured)
        <ul class="text-muted small mt-2 mb-0">
            <li>Create scenarios in <a href="https://www.make.com" target="_blank" rel="noopener">Make.com</a> with a <em>Custom webhook</em> trigger.</li>
            <li>Add <code>MAKE_PRODUCT_WEBHOOK_URL</code> and <code>MAKE_BLOG_WEBHOOK_URL</code> to <code>.env</code>, then set <code>MAKE_ENABLED=true</code>.</li>
            <li>Optionally set <code>MAKE_WEBHOOK_SECRET</code> (sent as the <code>X-Make-Secret</code> header) and <code>OPENAI_ENABLED=true</code> for AI captions.</li>
            <li>Webhooks are queued — run a worker (<code>php artisan queue:work</code>) or set <code>QUEUE_CONNECTION=sync</code> to send inline.</li>
        </ul>
        @endif
    </div>
</div>

<div class="card mb-4"><div class="card-body">
    <h2 class="h6 fw-bold mb-2">Marketing Feeds</h2>
    <div class="row g-2 small">
        <div class="col-md-6">
            <strong>Blog RSS</strong>
            <div><a href="{{ $feeds['rss'] }}" target="_blank" rel="noopener">{{ $feeds['rss'] }}</a></div>
        </div>
        <div class="col-md-6">
            <strong>Facebook Catalog</strong>
            <div><a href="{{ $feeds['facebook'] }}" target="_blank" rel="noopener">{{ $feeds['facebook'] }}</a></div>
        </div>
    </div>
    <p class="text-muted small mt-2 mb-0">Use the Facebook Catalog URL as a scheduled data feed in Commerce Manager, and the RSS feed to drive Make.com / email automations.</p>
</div></div>

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

<h2 class="h6 fw-bold mb-2">Social Publishing Attempts</h2>
<div class="card mb-4"><div class="table-responsive"><table class="table mb-0 small">
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

<h2 class="h6 fw-bold mb-2">Make.com Webhook Activity</h2>
<div class="card"><div class="table-responsive"><table class="table mb-0 small">
<thead><tr><th>Event</th><th>Item</th><th>Platforms</th><th>Status</th><th>When</th><th>Detail</th><th></th></tr></thead>
<tbody>
@forelse($webhookLogs as $log)
<tr>
    <td><code>{{ $log->event }}</code></td>
    <td>{{ \Illuminate\Support\Str::limit($log->target_label, 40) ?: class_basename($log->target_type ?? '—') }}</td>
    <td class="text-muted">{{ implode(', ', $log->platformList()) }}</td>
    <td><span class="badge bg-{{ $log->status === 'success' ? 'success' : ($log->status === 'failed' ? 'danger' : 'secondary') }}">{{ $log->status }}</span>@if($log->http_status) <span class="text-muted">{{ $log->http_status }}</span>@endif</td>
    <td>{{ $log->created_at->format('d M Y H:i') }}</td>
    <td class="text-muted">{{ \Illuminate\Support\Str::limit($log->error_message ?: $log->response, 60) }}</td>
    <td class="text-end">
        @if($log->status === 'failed' && $log->target_id)
        <form action="{{ route('admin.social.webhook-retry', $log) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger py-0">Retry</button>
        </form>
        @endif
    </td>
</tr>
@empty
<tr><td colspan="7" class="text-muted p-4">No webhook deliveries yet. Publish a product or blog post (with <code>MAKE_ENABLED=true</code>) to trigger Make.com.</td></tr>
@endforelse
</tbody></table></div></div>
@endsection
