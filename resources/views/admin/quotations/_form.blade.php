@php
    $isEdit = isset($quotation) && $quotation;
    $q = $isEdit ? $quotation : null;
    $initialItems = old('items');
    if ($initialItems === null) {
        if ($isEdit) {
            $initialItems = $quotation->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'description' => $i->description,
                'sku' => $i->sku,
                'quantity' => $i->quantity,
                'unit_price' => (float) $i->unit_price,
            ])->values()->all();
        } elseif (!empty($prefill['items'])) {
            $initialItems = $prefill['items'];
        } else {
            $initialItems = [['description' => '', 'sku' => '', 'quantity' => 1, 'unit_price' => 0, 'product_id' => null]];
        }
    }
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.quotations.update', $quotation) : route('admin.quotations.store') }}" id="quotation-form">
    @csrf
    @if($isEdit) @method('PUT') @endif

    @if(!empty($prefill['source_quote_id']) || $q?->source_quote_id)
        <input type="hidden" name="source_quote_id" value="{{ old('source_quote_id', $prefill['source_quote_id'] ?? $q?->source_quote_id) }}">
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card admin-card mb-4">
                <div class="card-header bg-white fw-semibold">Customer</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Contact name *</label>
                            <input type="text" name="customer_name" class="form-control" required value="{{ old('customer_name', $q?->customer_name ?? $prefill['customer_name'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company</label>
                            <input type="text" name="customer_company" class="form-control" value="{{ old('customer_company', $q?->customer_company ?? $prefill['customer_company'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="customer_email" class="form-control" value="{{ old('customer_email', $q?->customer_email ?? $prefill['customer_email'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="customer_phone" class="form-control" value="{{ old('customer_phone', $q?->customer_phone ?? $prefill['customer_phone'] ?? '') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Customer VAT number</label>
                            <input type="text" name="customer_vat_number" class="form-control" value="{{ old('customer_vat_number', $q?->customer_vat_number ?? '') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address line 1</label>
                            <input type="text" name="billing_address_line_1" class="form-control" value="{{ old('billing_address_line_1', $q?->billing_address_line_1 ?? '') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address line 2</label>
                            <input type="text" name="billing_address_line_2" class="form-control" value="{{ old('billing_address_line_2', $q?->billing_address_line_2 ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" name="billing_city" class="form-control" value="{{ old('billing_city', $q?->billing_city ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Province</label>
                            <input type="text" name="billing_province" class="form-control" value="{{ old('billing_province', $q?->billing_province ?? '') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Postal code</label>
                            <input type="text" name="billing_postal_code" class="form-control" value="{{ old('billing_postal_code', $q?->billing_postal_code ?? '') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card admin-card mb-4">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="fw-semibold">Line items</span>
                    <div class="position-relative" style="min-width:220px">
                        <input type="search" id="product-search" class="form-control form-control-sm" placeholder="Add product from catalog…" autocomplete="off">
                        <div id="product-search-results" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index:10;max-height:240px;overflow:auto"></div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0" id="line-items-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th style="width:120px">SKU</th>
                                    <th style="width:80px">Qty</th>
                                    <th style="width:120px">Unit (R)</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody id="line-items-body"></tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="add-line-btn">Add blank line</button>
                    </div>
                </div>
            </div>

            <div class="card admin-card mb-4">
                <div class="card-header bg-white fw-semibold">Notes &amp; terms (on printed quote)</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Customer notes</label>
                        <textarea name="notes" class="form-control" rows="3">{{ old('notes', $q?->notes ?? '') }}</textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Terms &amp; conditions</label>
                        <textarea name="terms" class="form-control" rows="3">{{ old('terms', $q?->terms ?? $defaultTerms) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card admin-card mb-4 sticky-top" style="top:1rem">
                <div class="card-header bg-white fw-semibold">Quotation settings</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            @foreach(['draft','sent','accepted','declined','expired'] as $s)
                                <option value="{{ $s }}" @selected(old('status', $q?->status ?? 'draft') === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Valid until</label>
                        <input type="date" name="valid_until" class="form-control" value="{{ old('valid_until', $q?->valid_until?->format('Y-m-d') ?? $defaultValidUntil) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Discount (R, inc VAT)</label>
                        <input type="number" name="discount_amount" class="form-control" step="0.01" min="0" value="{{ old('discount_amount', $q?->discount_amount ?? 0) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Internal notes (admin only)</label>
                        <textarea name="internal_notes" class="form-control" rows="4">{{ old('internal_notes', $q?->internal_notes ?? $prefill['internal_notes'] ?? '') }}</textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Save quotation' : 'Create quotation' }}</button>
                        <a href="{{ $isEdit ? route('admin.quotations.show', $quotation) : route('admin.quotations.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    const initialItems = @json($initialItems);
    const searchUrl = @json(route('admin.quotations.products.search'));
    const tbody = document.getElementById('line-items-body');
    let lineIndex = 0;

    function addLine(data) {
        const i = lineIndex++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <input type="hidden" name="items[${i}][product_id]" value="${data.product_id || ''}">
                <input type="text" name="items[${i}][description]" class="form-control form-control-sm" required value="${escapeAttr(data.description || '')}">
            </td>
            <td><input type="text" name="items[${i}][sku]" class="form-control form-control-sm" value="${escapeAttr(data.sku || '')}"></td>
            <td><input type="number" name="items[${i}][quantity]" class="form-control form-control-sm" min="1" required value="${data.quantity || 1}"></td>
            <td><input type="number" name="items[${i}][unit_price]" class="form-control form-control-sm" min="0" step="0.01" required value="${data.unit_price ?? 0}"></td>
            <td><button type="button" class="btn btn-sm btn-link text-danger p-0 remove-line" title="Remove">&times;</button></td>
        `;
        tbody.appendChild(tr);
        tr.querySelector('.remove-line').addEventListener('click', () => {
            if (tbody.querySelectorAll('tr').length > 1) tr.remove();
        });
    }

    function escapeAttr(s) {
        return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
    }

    initialItems.forEach(addLine);
    if (!initialItems.length) addLine({});

    document.getElementById('add-line-btn').addEventListener('click', () => addLine({}));

    const searchInput = document.getElementById('product-search');
    const resultsEl = document.getElementById('product-search-results');
    let debounce;

    searchInput.addEventListener('input', function () {
        clearTimeout(debounce);
        const q = this.value.trim();
        if (q.length < 2) {
            resultsEl.classList.add('d-none');
            return;
        }
        debounce = setTimeout(() => {
            fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    resultsEl.innerHTML = '';
                    (data.products || []).forEach(p => {
                        const a = document.createElement('button');
                        a.type = 'button';
                        a.className = 'list-group-item list-group-item-action list-group-item-sm';
                        a.textContent = (p.sku ? p.sku + ' — ' : '') + p.name + ' (R ' + Number(p.price).toFixed(2) + ')';
                        a.addEventListener('click', () => {
                            addLine({ product_id: p.id, description: p.name, sku: p.sku, quantity: 1, unit_price: p.price });
                            searchInput.value = '';
                            resultsEl.classList.add('d-none');
                        });
                        resultsEl.appendChild(a);
                    });
                    resultsEl.classList.toggle('d-none', !resultsEl.children.length);
                });
        }, 250);
    });

    document.addEventListener('click', e => {
        if (!searchInput.contains(e.target) && !resultsEl.contains(e.target)) {
            resultsEl.classList.add('d-none');
        }
    });
})();
</script>
