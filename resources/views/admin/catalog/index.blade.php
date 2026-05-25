@extends('layouts.admin')

@section('page_title', 'Catalog — Import, Export & Feeds')

@section('content')
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h5 fw-bold">Import Products (CSV)</h2>
            <p class="small text-muted">Supports WooCommerce exports and distributor CSVs (ProductName, ProductCode, Category, Image, etc.). UTF-8 with comma or semicolon delimiters.</p>

            <div class="alert alert-light border small mb-3">
                <strong>Large imports (1000+ products):</strong>
                <ol class="mb-0 ps-3 mt-2">
                    <li>Upload CSV to <code>urbanfocus/storage/imports/products.csv</code> via File Manager</li>
                    <li>Run <code>deploy/import-csv.php</code> from <code>public_html</code> (no browser timeout)</li>
                </ol>
            </div>

            <ul class="small text-muted">
                <li>Required columns: <strong>Name</strong>, <strong>Images</strong> (full image URLs)</li>
                <li>Recommended: SKU, Categories, Regular price, Stock, Published</li>
                <li>Rows without images or in non-IT categories are skipped</li>
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
        <div class="card h-100 border-warning"><div class="card-body">
            <h2 class="h5 fw-bold text-warning">Remove Non-IT Catalog</h2>
            <p class="small text-muted">Permanently deletes non-IT products <strong>and</strong> their categories (lady shavers, dictionaries, bathroom accessories, stationery, homeware, etc.). IT categories with mixed stock are kept — only matching products are removed. Large catalogs may take several minutes — use <code>deploy/cleanup-non-it.php</code> in <code>public_html</code> if this times out.</p>

            <div class="border rounded p-3 mb-3 bg-light small">
                <div class="row g-2 text-center mb-3">
                    <div class="col-4">
                        <div class="fw-bold">{{ number_format($nonItPreview['total_products']) }}</div>
                        <div class="text-muted">Total products</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold text-warning">{{ number_format($nonItPreview['products_to_delete']) }}</div>
                        <div class="text-muted">To remove</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold text-warning">{{ number_format($nonItPreview['categories_to_delete']) }}</div>
                        <div class="text-muted">Categories</div>
                    </div>
                </div>

                @if($nonItPreview['terms_loaded'] === 0)
                    <div class="alert alert-danger py-2 mb-2">No blocklist terms loaded. Run <code>php artisan config:clear</code> after deploying.</div>
                @else
                    <p class="text-muted mb-2">{{ $nonItPreview['terms_loaded'] }} product blocklist terms · {{ $nonItPreview['it_heads_loaded'] }} IT category heads</p>
                @endif

                @if($nonItPreview['excluded_categories'] !== [])
                    <p class="fw-semibold mb-1">Sample categories:</p>
                    <ul class="mb-2 ps-3">
                        @foreach(array_slice($nonItPreview['excluded_categories'], 0, 8) as $name)
                            <li>{{ $name }}</li>
                        @endforeach
                        @if(count($nonItPreview['excluded_categories']) > 8)
                            <li class="text-muted">… and {{ count($nonItPreview['excluded_categories']) - 8 }} more</li>
                        @endif
                    </ul>
                @endif

                @if($nonItPreview['sample_products'] !== [])
                    <p class="fw-semibold mb-1">Sample products:</p>
                    <ul class="mb-0 ps-3">
                        @foreach($nonItPreview['sample_products'] as $name)
                            <li>{{ $name }}</li>
                        @endforeach
                    </ul>
                @elseif($nonItPreview['products_to_delete'] === 0)
                    <p class="text-success mb-0">No non-IT products detected — catalog looks clean.</p>
                @endif
            </div>

            @if($nonItPreview['products_to_delete'] > 0 || $nonItPreview['categories_to_delete'] > 0)
            <form action="{{ route('admin.catalog.remove-non-it') }}" method="POST" onsubmit="return confirm('Permanently delete {{ number_format($nonItPreview['products_to_delete']) }} non-IT product(s) and {{ number_format($nonItPreview['categories_to_delete']) }} categor(ies)?')">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-warning">Remove Non-IT Products &amp; Categories</button>
            </form>
            @endif
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100 border-danger"><div class="card-body">
            <h2 class="h5 fw-bold text-danger">Clear All Products</h2>
            <p class="small text-muted">Permanently deletes every product and product image. Orders keep their line-item history. Use before a fresh CSV import.</p>
            <form action="{{ route('admin.catalog.clear-products') }}" method="POST" onsubmit="return confirm('This permanently deletes ALL products. Continue?')">
                @csrf
                <div class="mb-3">
                    <label class="form-label small">Type <strong>DELETE ALL PRODUCTS</strong> to confirm</label>
                    <input type="text" name="confirm_phrase" class="form-control form-control-sm" required autocomplete="off">
                </div>
                <button type="submit" class="btn btn-sm btn-outline-danger">Delete All Products</button>
            </form>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h5 fw-bold">Google Merchant Center</h2>
            <p class="small text-muted">Products must have an image, description, brand, price, and SKU or GTIN to appear in the feed.</p>

            <div class="row g-3 mb-3">
                <div class="col-4">
                    <div class="border rounded p-3 text-center">
                        <div class="h4 mb-0 text-success">{{ $feedStats['eligible'] }}</div>
                        <div class="small text-muted">Eligible</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="border rounded p-3 text-center">
                        <div class="h4 mb-0 text-danger">{{ $feedStats['ineligible'] }}</div>
                        <div class="small text-muted">Need fixes</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="border rounded p-3 text-center">
                        <div class="h4 mb-0">{{ $feedStats['total_active'] }}</div>
                        <div class="small text-muted">Active</div>
                    </div>
                </div>
            </div>

            @if($feedStats['ineligible'] > 0)
                <ul class="small text-muted mb-3">
                    @foreach($feedStats['issues'] as $issue => $count)
                        @if($count > 0)
                            <li>
                                <a href="{{ route('admin.products.index', ['merchant_issue' => $issue]) }}">{{ $merchantIssueLabels[$issue] ?? str_replace('_', ' ', ucfirst($issue)) }}</a>: {{ $count }} product(s)
                            </li>
                        @endif
                    @endforeach
                </ul>

                @if($ineligibleSample !== [])
                    <p class="small fw-semibold mb-1">Sample ineligible products:</p>
                    <ul class="small text-muted mb-3 ps-3">
                        @foreach($ineligibleSample as $item)
                            <li>{{ $item['name'] }} <span class="text-danger">({{ implode(', ', array_map(fn ($k) => $merchantIssueLabels[$k] ?? $k, $item['issues'])) }})</span></li>
                        @endforeach
                    </ul>
                @endif

                <a href="{{ route('admin.catalog.export-ineligible') }}" class="btn btn-sm btn-outline-secondary me-2">Export Ineligible CSV</a>
                <form action="{{ route('admin.catalog.bulk-fix-merchant') }}" method="POST" class="d-inline" onsubmit="return confirm('Copy product names into missing short descriptions?')">
                    @csrf
                    <input type="hidden" name="action" value="fill_descriptions">
                    <button type="submit" class="btn btn-sm btn-outline-primary me-2">Auto-fill Descriptions</button>
                </form>
                <form action="{{ route('admin.catalog.bulk-fix-merchant') }}" method="POST" class="d-inline" onsubmit="return confirm('Generate UF-{id} SKUs for products missing identifiers?')">
                    @csrf
                    <input type="hidden" name="action" value="fill_sku">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Generate Missing SKUs</button>
                </form>
            @endif

            <p class="small mb-2"><strong>Setup checklist (in Merchant Center):</strong></p>
            <ol class="small text-muted mb-3">
                <li>Verify domain in Google Search Console</li>
                <li>Add feed URL below (scheduled fetch daily)</li>
                <li>Set business info, shipping &amp; returns for South Africa</li>
                <li>Return policy: <a href="{{ config('google-merchant.return_policy_url') ?: route('returns') }}" target="_blank">{{ config('google-merchant.return_policy_url') ?: route('returns') }}</a></li>
                <li>Link PayFast checkout and ensure prices match the feed</li>
            </ol>
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
