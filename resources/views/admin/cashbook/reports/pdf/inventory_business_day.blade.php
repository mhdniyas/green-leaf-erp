<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }} — {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</title>
    <style>
        @page {
            margin: 14mm 14mm 14mm 14mm;
            size: A4 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 0;
            font-size: 11px;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #059669;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
            padding: 0;
        }
        .title {
            color: #059669;
            font-size: 20px;
            font-weight: bold;
            margin: 0 0 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .subtitle {
            font-size: 11px;
            color: #334155;
            font-weight: 600;
        }
        .meta-right {
            text-align: right;
            font-size: 10px;
            color: #64748b;
            line-height: 1.4;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.data-table thead {
            display: table-header-group;
        }
        table.data-table tr {
            page-break-inside: avoid;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 7px 8px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }
        table.data-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
            vertical-align: middle;
        }
        table.data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .text-center {
            text-align: center;
        }
        .text-left {
            text-align: left;
        }
        .text-right {
            text-align: right;
        }
        .font-mono {
            font-family: 'Courier New', Courier, monospace;
            font-weight: 600;
        }
        .code-badge {
            display: inline-block;
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            color: #334155;
        }
        .product-name {
            font-weight: 600;
            color: #0f172a;
        }
        .advance-qty {
            font-weight: bold;
            color: #0f172a;
        }
        .footer {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="title">{{ $title }}</div>
                    <div class="subtitle">
                        Date: <strong>{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</strong>
                        &nbsp;&nbsp;·&nbsp;&nbsp;
                        Warehouse: <strong>{{ $warehouse?->name ?? 'All Warehouses' }}</strong>
                        &nbsp;&nbsp;·&nbsp;&nbsp;
                        Total Items: <strong>{{ count($rows) }}</strong>
                    </div>
                </td>
                <td class="meta-right">
                    <div><strong>Green Leaf Fruits and Vegetables</strong></div>
                    <div>Generated: {{ $generatedAt }}</div>
                    <div>User: {{ $generatedBy }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 8%;">#</th>
                <th class="text-center" style="width: 14%;">CODE</th>
                <th class="text-left" style="width: 54%;">PRODUCT</th>
                <th class="text-right" style="width: 24%;">ADVANCE</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td class="text-center font-mono" style="color: #64748b;">{{ $index + 1 }}</td>
                    <td class="text-center font-mono">
                        <span class="code-badge">{{ $row['product_code'] ?: ($row['sku'] ?? '—') }}</span>
                    </td>
                    <td class="text-left product-name">
                        {{ $row['product_name'] }}
                    </td>
                    <td class="text-right font-mono advance-qty">
                        {{ $row['formatted_advance'] }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center" style="padding: 20px; color: #64748b;">
                        No products found for this business day.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Green Leaf ERP &nbsp;·&nbsp; Business Day Advance Inventory Report
    </div>
</body>
</html>

