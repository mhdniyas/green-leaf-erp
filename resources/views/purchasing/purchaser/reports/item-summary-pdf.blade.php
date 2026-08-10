<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Purchaser Item Summary</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 24px; }
        h1 { margin: 0 0 8px; }
        .meta { color: #475569; font-size: 12px; margin-bottom: 18px; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 18px; }
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
    <h1>Purchaser Item Summary</h1>
    <p class="meta">Period: {{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d M Y') }}</p>

    <div class="grid">
        <div class="card"><div class="label">Products</div><div class="value">{{ $report['summary']['distinct_products'] }}</div></div>
        <div class="card"><div class="label">Product Units</div><div class="value">{{ $report['summary']['product_unit_rows'] }}</div></div>
        <div class="card"><div class="label">Invoice Lines</div><div class="value">{{ $report['summary']['invoice_lines'] }}</div></div>
    </div>

    <table>
        <thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Unit</th><th class="num">Quantity</th><th class="num">Sales</th><th class="num">Invoices</th><th class="num">Shops</th></tr></thead>
        <tbody>
            @foreach ($report['items'] as $item)
                <tr>
                    <td>{{ $item['product_name'] }}</td>
                    <td>{{ $item['product_sku'] ?? '' }}</td>
                    <td>{{ $item['category_name'] ?? 'Uncategorized' }}</td>
                    <td>{{ $item['unit'] }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item['billed_quantity'], 4, '.', ''), '0'), '.') }}</td>
                    <td class="num">Rs. {{ number_format((float) $item['line_sales_amount'], 2) }}</td>
                    <td class="num">{{ $item['invoice_count'] }}</td>
                    <td class="num">{{ $item['shop_count'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
