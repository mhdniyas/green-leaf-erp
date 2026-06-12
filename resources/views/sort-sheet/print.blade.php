<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sort Sheet — {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 10px;
            color: #1a2e1a;
            background: #fff;
            padding: 12mm 10mm;
        }

        /* ── Header ── */
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1a6632;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .company-name {
            font-size: 16px;
            font-weight: 900;
            color: #1a6632;
            letter-spacing: -0.3px;
        }
        .doc-title {
            font-size: 12px;
            font-weight: 700;
            color: #2d6a4f;
            margin-top: 2px;
        }
        .meta-block {
            text-align: right;
            font-size: 9px;
            color: #555;
            line-height: 1.6;
        }
        .meta-block strong { color: #1a2e1a; }

        /* ── Table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
            margin-top: 4px;
        }
        thead tr th {
            background: #1a6632;
            color: #fff;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8.5px;
            letter-spacing: 0.4px;
            padding: 5px 6px;
            border: 1px solid #155724;
            text-align: center;
            white-space: nowrap;
        }
        thead tr th:nth-child(2) { text-align: left; }

        tbody tr td {
            padding: 4px 6px;
            border: 1px solid #cde8d0;
            vertical-align: middle;
        }
        tbody tr:nth-child(even) td { background: #f5fbf6; }
        tbody tr:hover td { background: #eaf7ed; }

        /* SL col */
        tbody tr td:first-child {
            text-align: center;
            color: #888;
            font-size: 8px;
            font-family: monospace;
        }
        /* Item col */
        tbody tr td:nth-child(2) {
            font-weight: 600;
            text-align: left;
            white-space: nowrap;
        }
        /* Qty cols (dynamic, all between item and unit) */
        .qty-cell {
            text-align: center;
            font-family: monospace;
            font-weight: 700;
        }
        .qty-cell.zero { color: #ccc; font-weight: 400; }
        /* Total col */
        .total-cell {
            text-align: center;
            font-weight: 900;
            background: #d4edda !important;
            color: #155724;
            font-family: monospace;
        }
        /* Unit col */
        .unit-cell {
            text-align: center;
            font-size: 8px;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.3px;
        }

        /* Footer row */
        tfoot tr td {
            background: #1a6632 !important;
            color: #fff;
            font-weight: 900;
            padding: 5px 6px;
            border: 1px solid #155724;
            text-align: center;
            font-size: 9px;
        }
        tfoot tr td:nth-child(2) { text-align: left; }
        .footer-total { color: #a8f0c4; }

        /* ── Print Controls ── */
        .no-print {
            margin-bottom: 10px;
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .no-print button, .no-print a {
            padding: 7px 18px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-print { background: #1a6632; color: #fff; }
        .btn-close  { background: #f1f5f9; color: #334155; }
        .badge {
            display: inline-block;
            background: #d4edda;
            color: #155724;
            border: 1px solid #b7dfc2;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 9px;
            font-weight: 700;
        }

        /* ── Print Media ── */
        @media print {
            @page { size: A4 landscape; margin: 10mm 8mm; }
            body { padding: 0; font-size: 9px; }
            .no-print { display: none !important; }
            table { font-size: 8.5px; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
            tr { page-break-inside: avoid; }
            a { text-decoration: none; }
        }
    </style>
</head>
<body>

    {{-- Print / Close Buttons (hidden on print) --}}
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">
            🖨️ Print
        </button>
        <button class="btn-close" onclick="window.close()">
            ✕ Close
        </button>
        <span class="badge">{{ count($matrix) }} products · {{ $filteredShops->count() }} shops</span>
    </div>

    {{-- Document Header --}}
    <div class="print-header">
        <div>
            <div class="company-name">{{ $companyName }}</div>
            <div class="doc-title">📋 Sort Sheet — {{ \Carbon\Carbon::parse($date)->format('d M Y, l') }}</div>
        </div>
        <div class="meta-block">
            <div><strong>Generated By:</strong> {{ $generatedBy }}</div>
            <div><strong>Generated At:</strong> {{ $generatedAt }}</div>
            <div><strong>Products:</strong> {{ count($matrix) }} &nbsp;|&nbsp; <strong>Shops:</strong> {{ $filteredShops->count() }}</div>
            <div><strong>Date:</strong> {{ \Carbon\Carbon::parse($date)->format('Y-m-d') }}</div>
        </div>
    </div>

    {{-- Sort Sheet Table --}}
    @if(count($matrix) > 0)
    <table>
        <thead>
            <tr>
                <th style="width:32px">SL</th>
                <th style="text-align:left; min-width:120px">Item</th>
                @foreach($filteredShops as $shop)
                <th style="min-width:55px">
                    {{ $shop->name }}
                    @if($shop->warehouse_tag)
                    <br><span style="font-size:7.5px; font-weight:900; color:#a8f0c4; letter-spacing:1px;">{{ $shop->warehouse_tag }}</span>
                    @endif
                </th>
                @endforeach
                <th style="background:#0d4a24; min-width:55px">Total</th>
                <th style="min-width:40px">Unit</th>
            </tr>
        </thead>
        <tbody>
            @php $sl = 1; @endphp
            @foreach($matrix as $productId => $shopQtys)
            @php
                $meta = $productMeta[$productId];
                $total = array_sum($shopQtys);
            @endphp
            <tr>
                <td>{{ $sl++ }}</td>
                <td>{{ $meta['name'] }}</td>
                @foreach($filteredShops as $shop)
                @php $qty = $shopQtys[$shop->id] ?? 0; @endphp
                <td class="qty-cell {{ $qty <= 0 ? 'zero' : '' }}">
                    {{ $qty > 0 ? ($qty == intval($qty) ? intval($qty) : number_format($qty, 2)) : '0' }}
                </td>
                @endforeach
                <td class="total-cell">
                    {{ $total == intval($total) ? intval($total) : number_format($total, 2) }}
                </td>
                <td class="unit-cell">{{ $meta['unit'] }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">Grand Total ({{ count($matrix) }} items)</td>
                @foreach($filteredShops as $shop)
                @php $colTotal = collect($matrix)->sum(fn($shopQtys) => $shopQtys[$shop->id] ?? 0); @endphp
                <td class="footer-total">
                    {{ $colTotal > 0 ? ($colTotal == intval($colTotal) ? intval($colTotal) : number_format($colTotal, 2)) : '—' }}
                </td>
                @endforeach
                @php $grandTotal = collect($matrix)->sum(fn($shopQtys) => array_sum($shopQtys)); @endphp
                <td class="footer-total" style="font-size:10px">
                    {{ $grandTotal == intval($grandTotal) ? intval($grandTotal) : number_format($grandTotal, 2) }}
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    @else
    <p style="padding: 20px; text-align: center; color: #888; font-size: 12px;">
        No approved shop orders found for this date.
    </p>
    @endif

</body>
</html>
