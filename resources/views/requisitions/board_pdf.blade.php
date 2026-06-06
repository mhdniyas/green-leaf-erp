<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $boardTitle }} — {{ $date }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 landscape;
            margin: 15mm;
        }
        body {
            font-family: 'Outfit', sans-serif;
            color: #1e293b;
            background-color: #ffffff;
            margin: 0;
            padding: 10px;
            font-size: 11px;
            line-height: 1.4;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .logo-section h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.05em;
            color: #059669;
        }
        .logo-section p {
            margin: 3px 0 0 0;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.1em;
            color: #64748b;
        }
        .meta-section {
            text-align: right;
        }
        .meta-section h2 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
        }
        .meta-section p {
            margin: 3px 0;
            color: #64748b;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 9px;
        }
        th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-align: center;
            padding: 8px 6px;
            border: 1px solid #cbd5e1;
            text-transform: uppercase;
        }
        th.text-left, td.text-left {
            text-align: left;
        }
        td {
            padding: 8px 6px;
            border: 1px solid #e2e8f0;
            color: #334155;
            text-align: center;
        }
        .product-name {
            font-weight: 600;
            color: #0f172a;
        }
        .sku {
            font-family: monospace;
            color: #64748b;
            font-size: 8px;
        }
        .row-total {
            font-weight: 800;
            color: #0f172a;
            text-align: right;
        }
        .grand-total-row td {
            background-color: #f8fafc;
            font-weight: 800;
            color: #0f172a;
            border-top: 2px solid #cbd5e1;
        }
        .footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
            display: flex;
            justify-content: space-between;
            color: #94a3b8;
            font-size: 9px;
            margin-top: 30px;
        }
        .signatures {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }
        .sig-line {
            width: 250px;
            border-top: 1px solid #cbd5e1;
            text-align: center;
            padding-top: 6px;
            color: #475569;
            font-weight: 600;
        }
        @media print {
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-section">
            <h1>GREEN LEAF ERP</h1>
            <p>{{ $boardTitle }}</p>
        </div>
        <div class="meta-section">
            <h2>Allocations Grid</h2>
            <p><strong>Delivery Target:</strong> {{ \Carbon\Carbon::parse($date)->format('d F Y') }}</p>
            <p><strong>Printed At:</strong> {{ now()->format('d M Y h:i A') }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th class="text-left" style="width: 15%;">Item</th>
                <th style="width: 8%;">Fulfillment</th>
                @foreach($shops as $shop)
                    <th>{{ str_replace([' HYPERMARKET', ' SUPERMARKET', ' STORE', ' SHOP'], '', strtoupper($shop->name)) }}</th>
                @endforeach
                <th style="width: 8%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @php
                $colTotals = array_fill_keys($shops->pluck('id')->toArray(), 0);
            @endphp
            @foreach($products->values() as $index => $product)
                @php
                    $rowTotal = 0;
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="text-left">
                        <span class="product-name">{{ $product->name }}</span><br>
                        <span class="sku">{{ $product->sku }}</span>
                    </td>
                    <td>{{ ucfirst($productFulfillmentTypes[$product->id] ?? 'warehouse') }}</td>
                    @foreach($shops as $shop)
                        @php
                            $cell = $matrix[$product->id][$shop->id] ?? null;
                            $qty = $cell['approved_qty'] ?? $cell['requested_qty'] ?? 0;
                            if ($qty > 0) {
                                $rowTotal += $qty;
                                $colTotals[$shop->id] += $qty;
                            }
                        @endphp
                        <td>{{ $qty > 0 ? number_format((float) $qty, 2) : '-' }}</td>
                    @endforeach
                    <td class="row-total">{{ number_format($rowTotal, 2) }} {{ $product->unit }}</td>
                </tr>
            @endforeach
            <tr class="grand-total-row">
                <td colspan="3">Grand Total (kg)</td>
                @foreach($shops as $shop)
                    <td>{{ number_format($colTotals[$shop->id], 2) }}</td>
                @endforeach
                <td style="text-align: right; font-weight: 900;">{{ number_format(array_sum($colTotals), 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="signatures">
        <div class="sig-line">Prepared By (Inventory Team)</div>
        <div class="sig-line">Approved By (Purchase Manager)</div>
    </div>

    <div class="footer">
        <div>Generated automatically by Green Leaf ERP System.</div>
        <div>Landscape Matrix View</div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 300);
        }
    </script>
</body>
</html>
