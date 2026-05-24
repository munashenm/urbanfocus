@extends('layouts.admin')

@section('page_title', 'Catalog — Import, Export & Feeds')

@section('content')
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h5 fw-bold">Import Products (CSV)</h2>
            <p class="small text-muted">Supports WooCommerce exports and Urban Focus CSV format. UTF-8 with comma or semicolon delimiters.</p>
            <ul class="small text-muted">
                <li>Required column: <strong>Name</strong></li>
                <li>Recommended: SKU, Categories, Regular price, Stock, Published</li>
                <li>Matches existing products by SKU or WooCommerce ID</li>
            </ul>
            <form action="{{ route('admin.catalog.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">CSV File</label>
                    <input type="file" name="csv_file" class="form-control @error('csv_file') is-invalid @enderror" accept=".csv,.txt" required>
                    @error('csv_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary">Import CSV</button>
            </form>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h5 fw-bold">Export Products</h2>
            <p class="small text-muted">Download all products as CSV for backup or re-import.</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.catalog.export') }}" class="btn btn-outline-primary">Export Urban Focus CSV</a>
                <a href="{{ route('admin.catalog.export.woocommerce') }}" class="btn btn-outline-secondary">Export WooCommerce CSV</a>
            </div>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h5 fw-bold">Product Feeds</h2>
            <p class="small text-muted">Use these URLs in Google Merchant Center, PriceCheck, and Bob Shop.</p>
            <table class="table table-sm">
                <thead><tr><th>Feed</th><th>URL</th></tr></thead>
                <tbody>
                    @foreach($feeds as $feed)
                        <tr>
                            <td>{{ $feed['name'] }} <span class="badge bg-light text-dark">{{ $feed['format'] }}</span></td>
                            <td><a href="{{ $feed['url'] }}" target="_blank" class="small">{{ $feed['url'] }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h5 fw-bold">Products API</h2>
            <p class="small text-muted">Pass your API key via header <code>X-API-Key</code> or query <code>?api_key=</code></p>

            @if($apiKey)
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Your API Key</label>
                    <input type="text" class="form-control form-control-sm" value="{{ $apiKey }}" readonly onclick="this.select()">
                </div>
            @else
                <div class="alert alert-warning small">No API key set. Generate one below.</div>
            @endif

            <table class="table table-sm mb-3">
                <thead><tr><th>Method</th><th>Endpoint</th><th>Description</th></tr></thead>
                <tbody>
                    @foreach($apiEndpoints as $endpoint)
                        <tr>
                            <td><code>{{ $endpoint['method'] }}</code></td>
                            <td><code>{{ $endpoint['path'] }}</code></td>
                            <td class="small">{{ $endpoint['description'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="small text-muted mb-2">Example:</p>
            <code class="small d-block mb-3">{{ url('/api/products') }}?api_key=YOUR_KEY</code>

            <form action="{{ route('admin.catalog.api-key') }}" method="POST" onsubmit="return confirm('Regenerate API key? Existing integrations will stop working.')">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger">Regenerate API Key</button>
            </form>
        </div></div>
    </div>
</div>
@endsection
