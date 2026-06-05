<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — {{ $quotation->quotation_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 0; padding: 24px; font-size: 14px; line-height: 1.5; }
        .toolbar { margin-bottom: 24px; }
        .toolbar button, .toolbar a { display: inline-block; padding: 8px 16px; margin-right: 8px; text-decoration: none; border: 1px solid #ccc; background: #f5f5f5; color: #222; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .toolbar button:hover, .toolbar a:hover { background: #eee; }
        .doc { max-width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 32px; border-bottom: 2px solid #0a1628; padding-bottom: 16px; }
        .header h1 { margin: 0 0 4px; font-size: 24px; color: #0a1628; }
        .header .doc-type { font-size: 18px; font-weight: bold; color: #555; }
        .parties { display: flex; justify-content: space-between; gap: 32px; margin-bottom: 32px; }
        .party h2 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #666; margin: 0 0 8px; }
        .party p { margin: 0 0 4px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        table.items th, table.items td { padding: 10px 8px; text-align: left; border-bottom: 1px solid #ddd; }
        table.items th { background: #f5f7fa; font-size: 12px; text-transform: uppercase; }
        table.items .num { text-align: right; }
        .totals { margin-left: auto; width: 300px; }
        .totals table { width: 100%; border-collapse: collapse; }
        .totals td { padding: 6px 0; }
        .totals .label { text-align: left; }
        .totals .amount { text-align: right; }
        .totals .grand td { font-weight: bold; font-size: 16px; border-top: 2px solid #0a1628; padding-top: 10px; }
        .notes { margin-top: 24px; padding: 12px; background: #f9fafb; border-radius: 4px; }
        .legal-section { margin-top: 20px; padding-top: 14px; border-top: 1px solid #ddd; }
        .legal-section h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #0a1628; margin: 0 0 8px; }
        .legal-section.banking-section { margin-top: 16px; padding-top: 12px; }
        .legal-section.terms-section { margin-top: 12px; padding-top: 10px; }
        .banking-box { background: #f5f7fa; border: 1px solid #dde3ea; border-radius: 4px; padding: 10px 12px; margin-bottom: 0; }
        .banking-box table { width: 100%; border-collapse: collapse; font-size: 11px; line-height: 1.25; }
        .banking-box td { padding: 2px 8px 2px 0; vertical-align: top; }
        .banking-box td:first-child { color: #666; width: 120px; white-space: nowrap; }
        .banking-box .reference { margin-top: 6px; padding-top: 6px; border-top: 1px dashed #ccc; font-weight: bold; font-size: 11px; color: #0a1628; }
        .terms-text {
            font-size: 9px;
            color: #444;
            line-height: 1.2;
            white-space: pre-wrap;
            margin: 0;
        }
        .footer { margin-top: 12px; padding-top: 8px; font-size: 10px; color: #888; }
        .badge-expired { color: #b45309; font-weight: bold; }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; font-size: 12px; line-height: 1.35; }
            .header { margin-bottom: 18px; padding-bottom: 10px; }
            .header h1 { font-size: 20px; }
            .parties { margin-bottom: 16px; }
            .party p { margin: 0 0 2px; }
            table.items th, table.items td { padding: 6px 6px; }
            table.items { margin-bottom: 12px; }
            .totals td { padding: 3px 0; }
            .totals .grand td { font-size: 14px; padding-top: 6px; }
            .notes { margin-top: 12px; padding: 8px; font-size: 11px; }
            .legal-section { margin-top: 10px; padding-top: 8px; }
            .legal-section h3 { font-size: 9px; margin-bottom: 4px; }
            .banking-box { padding: 6px 8px; }
            .banking-box table { font-size: 9px; line-height: 1.15; }
            .banking-box .reference { margin-top: 4px; padding-top: 4px; font-size: 9px; }
            .terms-text { font-size: 7.5px; line-height: 1.12; }
            .footer { margin-top: 6px; padding-top: 4px; font-size: 8px; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
        @if(Route::has('admin.quotations.show'))
            <a href="{{ route('admin.quotations.show', $quotation) }}">Back to quotation</a>
        @else
            <a href="javascript:history.back()">Back</a>
        @endif
    </div>

    <div class="doc">
        <div class="header">
            <div>
                <h1>{{ $seller['name'] }}</h1>
                @if($seller['vat_number'])
                    <p>VAT No: {{ $seller['vat_number'] }}</p>
                @endif
                @if($seller['company_reg'])
                    <p>Reg No: {{ $seller['company_reg'] }}</p>
                @endif
                <p>{{ $seller['email'] }} · {{ $seller['phone'] }}</p>
                @php $addr = $seller['address']; @endphp
                <p>
                    {{ $addr['line1'] }}@if($addr['line2']), {{ $addr['line2'] }}@endif<br>
                    {{ $addr['city'] }}, {{ $addr['province'] }}@if($addr['postal_code']) {{ $addr['postal_code'] }}@endif<br>
                    {{ $addr['country'] }}
                </p>
            </div>
            <div style="text-align:right">
                <div class="doc-type">{{ $title }}</div>
                <p><strong>Quote No:</strong> {{ $quotation->quotation_number }}</p>
                <p><strong>Date:</strong> {{ $quotation->created_at->format('d M Y') }}</p>
                @if($quotation->valid_until)
                    <p><strong>Valid until:</strong> {{ $quotation->valid_until->format('d M Y') }}
                        @if($quotation->isExpired()) <span class="badge-expired">(Expired)</span> @endif
                    </p>
                @endif
                <p><strong>Status:</strong> {{ $quotation->statusLabel() }}</p>
            </div>
        </div>

        <div class="parties">
            <div class="party">
                <h2>Quote For</h2>
                <p><strong>{{ $quotation->customer_name }}</strong></p>
                @if($quotation->customer_company)
                    <p>{{ $quotation->customer_company }}</p>
                @endif
                @if($quotation->customer_vat_number)
                    <p>VAT No: {{ $quotation->customer_vat_number }}</p>
                @endif
                @if($quotation->billing_address_line_1)
                    <p>{{ $quotation->billing_address_line_1 }}</p>
                @endif
                @if($quotation->billing_address_line_2)
                    <p>{{ $quotation->billing_address_line_2 }}</p>
                @endif
                @if($quotation->billing_city)
                    <p>{{ $quotation->billing_city }}@if($quotation->billing_province), {{ $quotation->billing_province }}@endif {{ $quotation->billing_postal_code }}</p>
                @endif
                @if($quotation->customer_email)
                    <p>{{ $quotation->customer_email }}</p>
                @endif
                @if($quotation->customer_phone)
                    <p>{{ $quotation->customer_phone }}</p>
                @endif
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>SKU</th>
                    <th class="num">Qty</th>
                    <th class="num">Unit Price{{ $pricesIncludeVat ? ' (inc VAT)' : '' }}</th>
                    <th class="num">Line Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quotation->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->sku ?? '—' }}</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">R {{ number_format($item->unit_price, 2) }}</td>
                        <td class="num">R {{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <td class="label">Subtotal (ex VAT)</td>
                    <td class="amount">R {{ number_format($quotation->subtotal, 2) }}</td>
                </tr>
                @if($quotation->discount_amount > 0)
                    <tr>
                        <td class="label">Discount applied</td>
                        <td class="amount">−R {{ number_format($quotation->discount_amount, 2) }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="label">VAT ({{ number_format($vatRate, 0) }}%)</td>
                    <td class="amount">R {{ number_format($quotation->tax_amount, 2) }}</td>
                </tr>
                <tr class="grand">
                    <td class="label">Total{{ $pricesIncludeVat ? ' (inc VAT)' : '' }}</td>
                    <td class="amount">R {{ number_format($quotation->total, 2) }}</td>
                </tr>
            </table>
        </div>

        @if($quotation->notes)
            <div class="notes">
                <strong>Notes</strong>
                <p style="margin:8px 0 0">{!! nl2br(e($quotation->notes)) !!}</p>
            </div>
        @endif

        @if(!empty($banking))
            <div class="legal-section banking-section">
                <h3>Banking details</h3>
                <div class="banking-box">
                    <table>
                        @if($banking['bank_name'])
                            <tr><td>Bank</td><td>{{ $banking['bank_name'] }}</td></tr>
                        @endif
                        @if($banking['branch_code'])
                            <tr><td>Branch code</td><td>{{ $banking['branch_code'] }}</td></tr>
                        @endif
                        <tr><td>Account number</td><td>{{ $banking['account_number'] }}</td></tr>
                        @if($banking['swift_code'])
                            <tr><td>SWIFT</td><td>{{ $banking['swift_code'] }}</td></tr>
                        @endif
                    </table>
                    <p class="reference">Payment reference: {{ $quotation->quotation_number }}</p>
                </div>
            </div>
        @endif

        @if(!empty($termsText))
            <div class="legal-section terms-section">
                <h3>Terms &amp; conditions</h3>
                <div class="terms-text">{{ $termsText }}</div>
            </div>
        @endif

        <div class="footer">
            <p>{{ $seller['name'] }} · {{ $seller['website'] ?? config('app.url') }}</p>
        </div>
    </div>
</body>
</html>
