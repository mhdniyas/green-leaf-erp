<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Segregation Grid — {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html,
        body {
            background: #fff;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            padding: 8mm;
        }
        .no-print {
            align-items: center;
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
            font-family: system-ui, -apple-system, sans-serif;
        }
        .no-print button {
            background: #fff;
            border: 1px solid #000;
            color: #000;
            cursor: pointer;
            font-size: 11px;
            font-weight: 700;
            padding: 7px 18px;
            border-radius: 4px;
        }
        .no-print button:hover {
            background: #f0f0f0;
        }
        .page {
            break-after: page;
            page-break-after: always;
            width: 100%;
        }
        .page:last-child {
            break-after: auto;
            page-break-after: auto;
        }
        .page-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }
        th,
        td {
            background: #fff;
            border: 1px solid #000;
            color: #000;
            line-height: 1.05;
            padding: 1px 2px;
            text-align: center;
            vertical-align: middle;
            word-break: break-word;
        }
        th {
            font-size: 9px;
            font-weight: 700;
            height: 30px;
        }
        td {
            font-size: 10px;
            font-weight: 700;
        }
        .qty-row,
        .tag-row {
            height: 37px;
        }
        .sl-cell,
        .total-cell {
            font-weight: 700;
        }
        .item-cell {
            font-weight: 700;
            text-align: center;
        }
        .tag-cell {
            font-size: 10px;
            font-weight: 700;
        }
        @media print {
            @page { size: A4 landscape; margin: 8mm; }
            html,
            body {
                height: auto;
                overflow: visible;
                padding: 0;
                width: auto;
            }
            .no-print { display: none !important; }
            .page {
                height: auto;
                overflow: visible;
                padding: 0;
                width: 100%;
            }
            th {
                font-size: 9px;
                height: 30px;
                padding: 1px;
            }
            td {
                font-size: 10px;
                padding: 1px;
            }
            .qty-row,
            .tag-row {
                height: 37px;
            }
            tr {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">Print</button>
        <button type="button" onclick="window.close()">Close</button>
        <span style="font-weight: 700; font-size: 12px; margin-left: 8px;">
            Segregation Grid &middot; {{ $gradeHeading }} &middot; {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
            &middot; {{ count($matrix) }} products &middot; {{ $filteredShops->count() }} shops
            {{ isset($selectedWarehouse) && $selectedWarehouse ? ' &middot; '.$selectedWarehouse->name : '' }}
        </span>
    </div>

    @if(count($matrix) > 0)
        @php
            $totalPages = count($gridPages);
            $shopCount = max(1, $filteredShops->count());
            $shopWidth = (100 - 4 - 15 - 6) / $shopCount;
            $formatQty = fn (float $qty): string => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
        @endphp

        @foreach($gridPages as $pageIndex => $pageMatrix)
            <div class="page">
                <div class="page-meta">
                    <span>{{ $companyName ?? 'Green Leaf Distribution' }} &middot; Segregation Grid ({{ $gradeHeading }})</span>
                    <span>Date: {{ \Carbon\Carbon::parse($date)->format('d M Y') }} &middot; Page {{ $pageIndex + 1 }} of {{ $totalPages }}</span>
                </div>
                <table>
                    <colgroup>
                        <col style="width:4%">
                        <col style="width:15%">
                        @foreach($filteredShops as $shop)
                            <col style="width:{{ $shopWidth }}%">
                        @endforeach
                        <col style="width:6%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>SL</th>
                            <th>Item</th>
                            @foreach($filteredShops as $shop)
                                <th>{{ $shop->name }}</th>
                            @endforeach
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pageMatrix as $rowKey => $shopQtys)
                            @php
                                $meta = $productMeta[$rowKey];
                                $total = array_sum($shopQtys);
                            @endphp
                            <tr class="tag-row">
                                <td></td>
                                <td></td>
                                @foreach($filteredShops as $shop)
                                    <td class="tag-cell">{{ $shop->warehouse_tag ?: '-' }}</td>
                                @endforeach
                                <td></td>
                            </tr>
                            <tr class="qty-row">
                                <td class="sl-cell">{{ $meta['sku'] ?: $meta['product_id'] }}</td>
                                <td class="item-cell">{{ $meta['display_name'] ?? $meta['name'] }}</td>
                                @foreach($filteredShops as $shop)
                                    @php $qty = (float) ($shopQtys[$shop->id] ?? 0); @endphp
                                    <td>{{ $qty > 0 ? $formatQty($qty) : '0' }}</td>
                                @endforeach
                                <td class="total-cell">{{ $formatQty((float) $total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @else
        <p style="padding: 20px; font-size: 14px; text-align: center;">No approved shop orders found for this date.</p>
    @endif
</body>
</html>

