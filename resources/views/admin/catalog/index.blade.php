@extends('layouts.admin')

@section('page_title', 'Catalog — Import, Export & Feeds')

@section('content')
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h5 fw-bold">Import Products (CSV)</h2>
            <p class="small text-muted">Supports WooCommerce, Esquire, and <strong>Pinnacle</strong> CSV feeds. Only <strong>IT products with images and a cost price</strong> are imported.</p>

            <div class="alert alert-info small mb-3">
                <strong>Pricing policy:</strong>
                CSV price = cost. Compared products use a lower markup (laptops / Dell / HP / Lenovo 8%, networking &amp; CCTV 12%). Everything else → {{ $importPricing['markup_percent'] }}% fallback.
                @if($importPricing['low_cost_threshold'] > 0)
                    Cost under R{{ number_format($importPricing['low_cost_threshold'], 0) }}: markup only (e.g. R{{ number_format($importPricing['low_cost_example']['cost'], 0) }} → R{{ number_format($importPricing['low_cost_example']['retail'], 2) }}).
                    R{{ number_format($importPricing['low_cost_threshold'], 0) }} and above: rounded {{ $importPricing['round_mode'] === 'up' ? 'up' : 'to nearest' }} to R{{ $importPricing['round_to'] }}
                    (e.g. R{{ number_format($importPricing['example']['cost'], 0) }} → R{{ number_format($importPricing['example']['retail'], 0) }}).
                @else
                    Rounded {{ $importPricing['round_mode'] === 'up' ? 'up' : 'to nearest' }} to R{{ $importPricing['round_to'] }}
                    (e.g. R{{ number_format($importPricing['example']['cost'], 0) }} → R{{ number_format($importPricing['example']['retail'], 0) }}).
                @endif
            </div>

            <div class="alert alert-light border small mb-3">
                <strong>Large imports (1000+ products):</strong>
                <ol class="mb-0 ps-3 mt-2">
                    <li>Upload CSV to <code>urbanfocus/storage/imports/products.csv</code> via File Manager</li>
                    <li>Preview: <code>import-csv.php?key=…&amp;preview=1</code></li>
                    <li>Run <code>deploy/import-csv.php</code> from <code>public_html</code> (no browser timeout)</li>
                </ol>
            </div>

            <ul class="small text-muted mb-3">
                <li><strong>Pinnacle:</strong> StockCode, ProdName, ProdImg, ProdPriceExclVAT, ProdQty, category_tree, BarcodeEAN</li>
                <li><strong>Esquire:</strong> ProductName, ProductCode, CategoryHead, Category, Image, Price (Data Export CSV)</li>
                <li>Required: name, image URL(s), cost/price — skipped if missing</li>
                <li>Cost under R{{ number_format($importPricing['low_cost_threshold'], 0) }}: markup only; R{{ number_format($importPricing['low_cost_threshold'], 0) }}+: rounded to R{{ $importPricing['round_to'] }}</li>
                <li>Matches existing products by SKU or WooCommerce ID</li>
            </ul>

            @if(session('import_preview'))
                @php $preview = session('import_preview'); @endphp
                <div class="alert alert-secondary small mb-3">
                    <strong>Import preview</strong> ({{ $preview['total_rows'] ?? 0 }} data rows scanned)<br>
                    Would create: <strong>{{ $preview['would_create'] ?? 0 }}</strong>,
                    update: <strong>{{ $preview['would_update'] ?? 0 }}</strong>,
                    skip non-IT: {{ $preview['skippedNonIt'] ?? 0 }},
                    skip no image: {{ $preview['skippedNoImage'] ?? 0 }},
                    skip no price: {{ $preview['skippedNoPrice'] ?? 0 }}
                    @if(!empty($preview['samples']['import']))
                        <p class="mb-1 mt-2 fw-semibold">Sample imports (cost → retail):</p>
                        <ul class="mb-0 ps-3">
                            @foreach($preview['samples']['import'] as $sample)
                                <li>{{ $sample['name'] }} — R{{ number_format($sample['cost'], 2) }} → R{{ number_format($sample['retail'], 2) }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <form action="{{ route('admin.catalog.import') }}" method="POST" enctype="multipart/form-data" class="mb-2">
                @csrf
                <div class="mb-3">
                    <label class="form-label">CSV File</label>
                    <input type="file" name="csv_file" class="form-control @error('csv_file') is-invalid @enderror" accept=".csv,.txt" required>
                    @error('csv_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" formaction="{{ route('admin.catalog.import-preview') }}" formmethod="POST" class="btn btn-outline-secondary">Preview Import</button>
                    <button type="submit" class="btn btn-primary">Import CSV</button>
                </div>
            </form>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100 border-success"><div class="card-body">
            @php
                $targetRangeCount = $targetRangeCount ?? 0;
            @endphp
            <h2 class="h5 fw-bold text-success">Add target-range products</h2>
            <p class="small text-muted mb-3">
                Adds up to {{ number_format($targetRangeCount) }} curated business SKUs (laptops, 5G, UniFi, CCTV, UPS, servers).
                Existing store matches are skipped. Each listing gets a full professional description for Google.
                Prices include Paystack/bank charges plus a
                <strong>{{ rtrim(rtrim(number_format(config('pricing.target_range_topup_percent', 15), 1), '0'), '.') }}% top-up</strong>
                so we stay competitive without undercharging.
                Re-run to refresh descriptions, attach missing photos and apply the top-up.
                No Terminal or <code>php artisan</code> needed.
            </p>

            @if(session('target_range_preview'))
                @php $preview = session('target_range_preview'); @endphp
                <div class="alert alert-secondary small mb-3">
                    <strong>Preview</strong> (nothing written yet)<br>
                    Would create: <strong>{{ $preview['created'] ?? 0 }}</strong>,
                    would update prices: {{ $preview['updated'] ?? 0 }},
                    already on store: {{ $preview['skipped'] ?? 0 }},
                    photos: {{ $preview['imaged'] ?? 0 }},
                    errors: {{ $preview['errors'] ?? 0 }}
                    @if(!empty($preview['samples']))
                        <ul class="mb-0 ps-3 mt-2">
                            @foreach(array_slice($preview['samples'], 0, 12) as $sample)
                                <li>
                                    {{ strtoupper($sample['action'] ?? '') }}
                                    {{ $sample['sku'] ?? '' }}
                                    — {{ $sample['name'] ?? '' }}
                                    @if(!empty($sample['reason'])) ({{ $sample['reason'] }}) @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            @if($targetRangeCount > 0)
                <div class="d-flex flex-wrap gap-2">
                    <form action="{{ url('/admin/catalog/sync-target-range/preview') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">Preview (no changes)</button>
                    </form>
                    <form action="{{ url('/admin/catalog/sync-target-range') }}" method="POST" onsubmit="return confirm('Create missing target-range products, refresh professional descriptions, and apply the catalogue price top-up on listings we added? Existing store SKUs will not be duplicated or repriced.')">
                        @csrf
                        <button type="submit" class="btn btn-success">Add missing products / update prices</button>
                    </form>
                </div>
            @else
                <div class="alert alert-warning small mb-0">
                    Pull latest <code>master</code> so <code>database/data/target-range-products.json</code> is on the server, then refresh.
                </div>
            @endif
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100 border-dark"><div class="card-body">
            @php
                $specialistCount = $specialistCount ?? 0;
            @endphp
            <h2 class="h5 fw-bold">Add specialist technology products</h2>
            <p class="small text-muted mb-3">
                Adds up to {{ number_format($specialistCount) }} Nitrokey, PiKVM, Hailo, Proxmox, Nextcloud, OPNsense and Urban Focus solution SKUs.
                Existing store matches are skipped. Each listing is written for South African Google, Google Shopping and Google Images
                (title, MPN, brand, FAQ schema, JPEG photo, availability label).
                Prices include a
                <strong>{{ rtrim(rtrim(number_format(config('pricing.specialist_topup_percent', 15), 1), '0'), '.') }}% top-up</strong>
                on street estimates. Quote-only software and enterprise kits stay orderable via Request a Quote.
                Re-run to refresh descriptions, attach missing photos and apply the top-up.
            </p>

            @if(session('specialist_preview'))
                @php $preview = session('specialist_preview'); @endphp
                <div class="alert alert-secondary small mb-3">
                    <strong>Preview</strong> (nothing written yet)<br>
                    Would create: <strong>{{ $preview['created'] ?? 0 }}</strong>,
                    would update: {{ $preview['updated'] ?? 0 }},
                    already on store: {{ $preview['skipped'] ?? 0 }},
                    photos: {{ $preview['imaged'] ?? 0 }},
                    errors: {{ $preview['errors'] ?? 0 }}
                    @if(!empty($preview['error_reasons']))
                        <div class="mt-2"><strong>Error:</strong> {{ implode(' | ', array_slice($preview['error_reasons'], 0, 3)) }}</div>
                    @endif
                    @if(!empty($preview['samples']))
                        <ul class="mb-0 ps-3 mt-2">
                            @foreach(array_slice($preview['samples'], 0, 12) as $sample)
                                <li>
                                    {{ strtoupper($sample['action'] ?? '') }}
                                    {{ $sample['sku'] ?? '' }}
                                    — {{ $sample['name'] ?? '' }}
                                    @if(!empty($sample['reason'])) ({{ $sample['reason'] }}) @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            @if($specialistCount > 0)
                <div class="d-flex flex-wrap gap-2">
                    <form action="{{ url('/admin/catalog/sync-specialist/preview') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary">Preview (no changes)</button>
                    </form>
                    <form action="{{ url('/admin/catalog/sync-specialist') }}" method="POST" onsubmit="return confirm('Create missing specialist products, refresh SEO descriptions, and apply the specialist price top-up on listings we added? Existing store SKUs will not be duplicated or repriced.')">
                        @csrf
                        <button type="submit" class="btn btn-dark">Add specialist products</button>
                    </form>
                </div>
            @else
                <div class="alert alert-warning small mb-0">
                    Pull latest <code>main</code> so <code>database/data/specialist-products.php</code> is on the server, then refresh.
                </div>
            @endif
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
        <div class="card h-100 border-primary"><div class="card-body">
            <h2 class="h5 fw-bold text-primary">Consolidate Categories</h2>
            <p class="small text-muted">Move imported Esquire/Pinnacle products into the clean 12-department storefront tree (Laptops, Networking, CCTV, etc.) and deactivate empty legacy categories.</p>

            <div class="border rounded p-3 mb-3 bg-light small">
                <div class="row g-2 text-center mb-3">
                    <div class="col-6">
                        <div class="fw-bold text-primary">{{ number_format($categoryConsolidationPreview['products_to_move']) }}</div>
                        <div class="text-muted">Products to reassign</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold">{{ number_format($categoryConsolidationPreview['empty_categories']) }}</div>
                        <div class="text-muted">Empty legacy categories</div>
                    </div>
                </div>

                @if($categoryConsolidationPreview['sample_moves'] !== [])
                    <p class="fw-semibold mb-1">Sample moves:</p>
                    <ul class="mb-0 ps-3">
                        @foreach($categoryConsolidationPreview['sample_moves'] as $move)
                            <li>{{ $move['from'] }} → {{ $move['to'] }} ({{ number_format($move['count']) }})</li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-success mb-0">Products already use the canonical category tree.</p>
                @endif
            </div>

            @if($categoryConsolidationPreview['products_to_move'] > 0 || $categoryConsolidationPreview['empty_categories'] > 0)
            <form action="{{ route('admin.catalog.consolidate-categories') }}" method="POST" onsubmit="return confirm('Reassign {{ number_format($categoryConsolidationPreview['products_to_move']) }} product(s) to the clean category tree?')">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary">Run Category Consolidation</button>
            </form>
            @endif
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100 border-success"><div class="card-body">
            <h2 class="h5 fw-bold text-success">Assign Product Categories</h2>
            <p class="small text-muted">Place uncategorised products and legacy imports (e.g. old Laptops &amp; Notebooks) into the current category tree using product name, brand and keywords. Laptops map to <strong>Computing &amp; Office Technology → Laptops</strong>.</p>
            <p class="small text-muted mb-3">cPanel: <code>deploy/merge-categories.php</code> (full merge) · <code>deploy/assign-product-categories.php</code> (heuristics only)</p>
            <form action="{{ route('admin.catalog.merge-categories') }}" method="POST" class="mb-3" onsubmit="return confirm('Merge ALL products into canonical categories? This remaps legacy categories, creates redirects, and deactivates empty legacy categories. Back up your database first.')">
                @csrf
                <button type="submit" class="btn btn-sm btn-warning">Merge All Product Categories</button>
            </form>
            <form action="{{ route('admin.catalog.assign-categories') }}" method="POST" class="mb-3" onsubmit="return confirm('Assign categories for all products that need it?')">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary">Assign All Product Categories</button>
            </form>
            <h2 class="h5 fw-bold text-success">Optimize Product SEO</h2>
            <p class="small text-muted">Assign categories (if needed), then generate SEO titles, meta descriptions and image alt tags. Run after CSV imports.</p>
            <form action="{{ route('admin.catalog.optimize-seo') }}" method="POST" onsubmit="return confirm('Optimize SEO metadata for all products? This may take a few minutes on large catalogs.')">
                @csrf
                <button type="submit" class="btn btn-sm btn-success">Run SEO Optimization</button>
            </form>
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
            <p class="small text-muted">Products must have an image, description, brand, price, and SKU or GTIN to appear in the feed.
                @unless(request('stats'))
                    <a href="{{ url('/admin/catalog?stats=1') }}">Load detailed stats</a> (slow on a large catalogue).
                @endunless
            </p>

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
                    <button type="submit" class="btn btn-sm btn-outline-primary me-2">Generate Missing SKUs</button>
                </form>
                <form action="{{ route('admin.catalog.bulk-fix-merchant') }}" method="POST" class="d-inline" onsubmit="return confirm('Strip non-digits from barcode fields to normalize GTINs?')">
                    @csrf
                    <input type="hidden" name="action" value="normalize_gtin">
                    <button type="submit" class="btn btn-sm btn-outline-primary me-2">Normalize GTIN/Barcodes</button>
                </form>
                <form action="{{ route('admin.catalog.bulk-fix-merchant') }}" method="POST" class="d-inline" onsubmit="return confirm('Use the first word of the product name as brand where missing?')">
                    @csrf
                    <input type="hidden" name="action" value="fill_brand">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Fill Missing Brands</button>
                </form>
            @endif

            <p class="small mb-2"><strong>Setup checklist (in Merchant Center):</strong></p>
            <ol class="small text-muted mb-3">
                <li>Verify domain in Google Search Console</li>
                <li>Add feed URL below (scheduled fetch daily)</li>
                <li>Set business info, shipping &amp; returns for South Africa</li>
                <li>Return policy: <a href="{{ config('google-merchant.return_policy_url') ?: route('returns') }}" target="_blank">{{ config('google-merchant.return_policy_url') ?: route('returns') }}</a></li>
                <li>Link Paystack checkout and ensure prices match the feed</li>
            </ol>
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h5 fw-bold">Product Feeds</h2>
            <p class="small text-muted">
                <strong>Bob Shop BulkLoad:</strong> download <a href="{{ route('feeds.bobshop.csv') }}" target="_blank">bobshop.csv</a> and upload in Seller View → BulkLoad Items.
                Set <code>BOBSHOP_PRIMARY_CATEGORY_ID</code> in <code>.env</code> to your Bob Shop category number (see their category picker when listing manually).
                <strong>XML trade feed:</strong> <a href="{{ route('feeds.bobshop') }}" target="_blank">bobshop.xml</a> — official Bob Shop spec (<code>ROOT</code> → <code>Products</code> → <code>Product</code>). Register URL with hello@bidorbuy.co.za.
            </p>
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
