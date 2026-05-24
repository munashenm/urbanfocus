<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 0; padding: 24px; font-size: 14px; line-height: 1.5; }
        .toolbar { margin-bottom: 24px; }
        .toolbar button, .toolbar a { display: inline-block; padding: 8px 16px; margin-right: 8px; text-decoration: none; border: 1px solid #ccc; background: #f5f5f5; color: #222; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .toolbar button:hover, .toolbar a:hover { background: #eee; }
        .invoice { max-width: 800px; margin: 0 auto; }
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
        .totals { margin-left: auto; width: 280px; }
        .totals table { width: 100%; border-collapse: collapse; }
        .totals td { padding: 6px 0; }
        .totals .label { text-align: left; }
        .totals .amount { text-align: right; }
        .totals .grand td { font-weight: bold; font-size: 16px; border-top: 2px solid #0a1628; padding-top: 10px; }
        .footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
        <a href="javascript:history.back()">Back</a>
    </div>

    <div class="invoice">
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
                <p><strong>Invoice No:</strong> {{ $order->order_number }}</p>
                <p><strong>Date:</strong> {{ ($order->paid_at ?? $order->created_at)->format('d M Y') }}</p>
                @if($order->payment_status === 'paid')
                    <p><strong>Payment:</strong> Paid</p>
                @else
                    <p><strong>Payment:</strong> {{ ucfirst($order->payment_status) }} ({{ strtoupper($order->payment_method) }})</p>
                @endif
            </div>
        </div>

        <div class="parties">
            <div class="party">
                <h2>Bill To</h2>
                <p><strong>{{ $order->customer_name }}</strong></p>
                @if($order->billing_company)
                    <p>{{ $order->billing_company }}</p>
                @endif
                @if($order->billing_vat_number)
                    <p>VAT No: {{ $order->billing_vat_number }}</p>
                @endif
                <p>{{ $order->billing_address_line_1 }}</p>
                @if($order->billing_address_line_2)
                    <p>{{ $order->billing_address_line_2 }}</p>
                @endif
                <p>{{ $order->billing_city }}, {{ $order->billing_province }} {{ $order->billing_postal_code }}</p>
                <p>{{ $order->customer_email }}</p>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>SKU</th>
                    <th class="num">Qty</th>
                    <th class="num">Unit Price</th>
                    <th class="num">Line Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->product_sku }}</td>
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
                    <td class="label">Subtotal</td>
                    <td class="amount">R {{ number_format($order->subtotal, 2) }}</td>
                </tr>
                @if($order->discount_amount > 0)
                    <tr>
                        <td class="label">Discount</td>
                        <td class="amount">−R {{ number_format($order->discount_amount, 2) }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="label">Shipping</td>
                    <td class="amount">R {{ number_format($order->shipping_cost, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">VAT ({{ number_format($vatRate, 0) }}%)</td>
                    <td class="amount">R {{ number_format($order->tax_amount, 2) }}</td>
                </tr>
                <tr class="grand">
                    <td class="label">Total</td>
                    <td class="amount">R {{ number_format($order->total, 2) }}</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            @if($order->payment_status !== 'paid')
                <p><strong>This is a proforma invoice.</strong> A tax invoice will be issued upon receipt of payment.</p>
            @else
                <p>Thank you for your business.</p>
            @endif
            <p>{{ $seller['name'] }} · {{ $seller['website'] ?? config('app.url') }}</p>
        </div>
    </div>
</body>
</html>
