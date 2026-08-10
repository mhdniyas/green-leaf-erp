<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Purchaser Sales Summary</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 24px; }
        h1 { margin: 0 0 8px; }
        .meta { color: #475569; font-size: 12px; margin-bottom: 18px; }
        .grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 18px; }
        .card { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; }
        .label { color: #64748b; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .value { font-size: 16px; font-weight: bold; margin-top: 4px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cbd5e1; font-size: 12px; padding: 8px; text-align: left; }
        th { background: #e2e8f0; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <h1>Purchaser Sales Summary</h1>
    <p class="meta">Period: {{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d M Y') }}</p>

    <div class="grid">
        <div class="card"><div class="label">Sales</div><div class="value">Rs. {{ number_format((float) $report['totals']['total_sales'], 2) }}</div></div>
        <div class="card"><div class="label">Paid</div><div class="value">Rs. {{ number_format((float) $report['totals']['paid_amount'], 2) }}</div></div>
        <div class="card"><div class="label">Outstanding</div><div class="value">Rs. {{ number_format((float) $report['totals']['outstanding_amount'], 2) }}</div></div>
        <div class="card"><div class="label">Shops</div><div class="value">{{ $report['totals']['total_shops'] }}</div></div>
        <div class="card"><div class="label">Invoices</div><div class="value">{{ $report['totals']['total_invoices'] }}</div></div>
    </div>

    <table>
        <thead><tr><th>Shop</th><th>Code</th><th class="num">Invoices</th><th class="num">Sales</th><th class="num">Paid</th><th class="num">Outstanding</th></tr></thead>
        <tbody>
            @foreach ($report['shops'] as $shop)
                <tr>
                    <td>{{ $shop['shop_name'] }}</td>
                    <td>{{ $shop['shop_code'] }}</td>
                    <td class="num">{{ $shop['invoice_count'] }}</td>
                    <td class="num">Rs. {{ number_format((float) $shop['total_sales'], 2) }}</td>
                    <td class="num">Rs. {{ number_format((float) $shop['paid_amount'], 2) }}</td>
                    <td class="num">Rs. {{ number_format((float) $shop['outstanding_amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
