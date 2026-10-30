<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sort Sheet V1 — {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            background: #fff;
            color: #000;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 10px;
        }
        @page {
            size: A4 landscape;
            margin: 8mm 6mm 8mm 6mm;
        }
        .print-container {
            width: 100%;
            margin: 0 auto;
        }
        .header-bar {
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
            margin-bottom: 8px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
        }
        .header-title {
            font-size: 16px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -0.02em;
        }
        .header-subtitle {
            font-size: 11px;
            font-weight: 700;
            color: #333;
            margin-top: 2px;
        }
        .header-meta {
            text-align: right;
            font-size: 9px;
            color: #444;
            line-height: 1.3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            table-layout: auto;
        }
        th, td {
            border: 1px solid #000;
            padding: 3px 4px;
            vertical-align: middle;
        }
        thead th {
            background: #f1f5f9;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: 0.3px;
            text-align: center;
        }
        th.item-col { text-align: left; min-width: 140px; }
        th.sku-col { text-align: center; width: 45px; }
        th.shop-col {
            text-align: center;
            min-width: 48px;
            white-space: normal;
            word-break: break-word;
            line-height: 1.1;
        }
        .shop-name {
            font-size: 8px;
            font-weight: 800;
            display: block;
        }
        .shop-tag {
            font-size: 7px;
            font-weight: 900;
            font-family: monospace;
            display: block;
            margin-top: 1px;
        }
        .category-row td {
            background: #0f172a !important;
            color: #fff !important;
            font-weight: 900;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 3px 6px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .qty-cell {
            text-align: center;
            font-family: monospace;
            font-size: 9px;
        }
        .qty-has {
            font-weight: 800;
            color: #000;
        }
        .qty-none {
            color: #ccc;
        }
        .total-cell {
            text-align: center;
            font-family: monospace;
            font-weight: 900;
            background: #f8fafc;
        }
        tfoot tr td {
            font-weight: 900;
            background: #f1f5f9;
            font-size: 9.5px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .grade-badge {
            display: inline-block;
            font-size: 7.5px;
            font-weight: 900;
            padding: 1px 3px;
            border: 1px solid #000;
            border-radius: 2px;
            text-transform: uppercase;
            margin-left: 3px;
            vertical-align: middle;
        }
        @media print {
            .no-print { display: none !important; }
            tr { page-break-inside: avoid; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
        }
        .screen-toolbar {
            background: #1e293b;
            color: #fff;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            border-radius: 8px;
        }
        .btn-print {
            background: #10b981;
            color: #fff;
            border: 0;
            padding: 6px 14px;
            font-weight: 800;
            font-size: 11px;
            border-radius: 6px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="screen-toolbar no-print">
        <div>
            <strong>Sort Sheet V1 Printable Document</strong> &middot; {{ \Carbon\Carbon::parse($date)->format('d M Y') }} &middot; {{ $gradeHeading }}
        </div>
        <div>
            <button onclick="window.print()" class="btn-print">Print Document</button>
            <button onclick="window.close()" style="background:#475569; color:#fff; border:0; padding:6px 12px; font-weight:700; font-size:11px; border-radius:6px; cursor:pointer; margin-left:6px;">Close</button>
        </div>
    </div>

    <div class="print-container">
        <div class="header-bar">
            <div>
                <div class="header-title">{{ $companyName }} &mdash; Sort Sheet V1</div>
                <div class="header-subtitle">
                    {{ $gradeHeading }} &middot; Business Date: {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
                    @if($selectedWarehouse) &middot; Warehouse: {{ $selectedWarehouse->name }} @endif
                </div>
            </div>
            <div class="header-meta">
                <div>Generated: {{ $generatedAt }}</div>
                <div>User: {{ $generatedBy }}</div>
                <div>Total: {{ number_format($grandTotal, 2) }} kg</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    @if($filters['show_code'] ?? true)
                    <th class="sku-col">SKU</th>
                    @endif
                    <th class="item-col">Item Description</th>
                    @foreach($filteredShops as $shop)
                    <th class="shop-col">
                        <span class="shop-name">{{ $shop->name }}</span>
                        @if($shop->warehouse_tag)
                        <span class="shop-tag">{{ $shop->warehouse_tag }}</span>
                        @endif
                    </th>
                    @endforeach
                    <th style="width: 55px; text-align: center;">Total</th>
                    <th style="width: 35px; text-align: center;">Unit</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $currentCategoryName = null;
                    $showCategoryTitles = (bool) ($filters['show_category_titles'] ?? ($filters['show_category_headers'] ?? true));
                    $isMultipleGrades = count($selectedGrades) > 1;
                @endphp
                @foreach($matrix as $rowKey => $shopQtys)
                @php
                    $meta = $productMeta[$rowKey];
                    $catName = $meta['category_name'] ?? 'General';
                    $rowTotal = array_sum($shopQtys);
                @endphp

                @if($showCategoryTitles && $currentCategoryName !== $catName)
                @php $currentCategoryName = $catName; @endphp
                <tr class="category-row">
                    <td colspan="{{ (($filters['show_code'] ?? true) ? 3 : 2) + $filteredShops->count() }}">
                        CATEGORY: {{ $catName }}
                    </td>
                </tr>
                @endif

                <tr>
                    @if($filters['show_code'] ?? true)
                    <td style="text-align: center; font-family: monospace; font-weight: 700;">
                        {{ $meta['sku'] }}
                    </td>
                    @endif
                    <td style="text-align: left; font-weight: 600;">
                        <span>{{ $meta['display_name'] }}</span>
                        @if($isMultipleGrades)
                        <span class="grade-badge">Gr.{{ $meta['grade'] }}</span>
                        @endif
                    </td>
                    @foreach($filteredShops as $shop)
                    @php $qty = $shopQtys[$shop->id] ?? 0.0; @endphp
                    <td class="qty-cell {{ $qty > 0 ? 'qty-has' : 'qty-none' }}">
                        {{ $qty > 0 ? ($qty == intval($qty) ? intval($qty) : number_format($qty, 2)) : '—' }}
                    </td>
                    @endforeach
                    <td class="total-cell">
                        {{ $rowTotal == intval($rowTotal) ? intval($rowTotal) : number_format($rowTotal, 2) }}
                    </td>
                    <td style="text-align: center; text-transform: uppercase; font-size: 8px;">
                        {{ $meta['unit'] }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="{{ ($filters['show_code'] ?? true) ? 2 : 1 }}" style="text-align: center;">
                        Grand Total ({{ count($matrix) }} items)
                    </td>
                    @foreach($filteredShops as $shop)
                    @php $colTotal = $shopTotals[$shop->id] ?? 0.0; @endphp
                    <td style="text-align: center; font-family: monospace;">
                        {{ $colTotal > 0 ? ($colTotal == intval($colTotal) ? intval($colTotal) : number_format($colTotal, 2)) : '—' }}
                    </td>
                    @endforeach
                    <td style="text-align: center; font-family: monospace;">
                        {{ $grandTotal == intval($grandTotal) ? intval($grandTotal) : number_format($grandTotal, 2) }}
                    </td>
                    <td style="text-align: center; font-size: 8px;">KG</td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
