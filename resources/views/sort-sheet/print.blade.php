<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sort Sheet — {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        html {
            background: #fff;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 10px;
            color: #000;
            background: #fff;
            padding: 12mm 10mm;
        }

        .page-summary {
            border-bottom: 2px solid #000;
            font-size: 12px;
            font-weight: 900;
            line-height: 1.25;
            margin-bottom: 4px;
            padding-bottom: 3px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* ── Table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9.5px;
        }
        thead tr th {
            background: #fff;
            color: #000;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8.5px;
            letter-spacing: 0.4px;
            padding: 4px 3px;
            border: 1px solid #000;
            text-align: center;
            line-height: 1.15;
            white-space: normal;
            word-break: break-word;
        }
        thead tr th.item-heading { text-align: left; }
        .shop-heading-name {
            display: -webkit-box;
            overflow: hidden;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }
        .shop-heading-tag {
            display: block;
            font-family: monospace;
            font-size: 7.5px;
            font-weight: 900;
            line-height: 1;
            margin-top: 2px;
        }

        tbody tr td {
            padding: 4px 3px;
            border: 1px solid #000;
            vertical-align: middle;
            background: #fff;
            color: #000;
        }

        /* SL col */
        tbody tr td:first-child {
            text-align: center;
            color: #000;
            font-size: 8px;
            font-family: monospace;
        }
        .item-cell {
            font-weight: 600;
            text-align: left;
            word-break: break-word;
        }
        /* Qty cols (dynamic, all between item and unit) */
        .qty-cell {
            text-align: center;
            font-family: monospace;
            font-weight: 700;
        }
        .qty-cell.zero { color: #000; font-weight: 400; }
        /* Total col */
        .total-cell {
            text-align: center;
            font-weight: 900;
            background: #fff !important;
            color: #000;
            font-family: monospace;
        }
        tbody tr {
            height: 24px;
        }
        .sort-sheet-page {
            page-break-after: always;
            overflow: hidden;
            width: 100%;
        }
        .sort-sheet-page:last-child {
            page-break-after: auto;
        }

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
            border: 1px solid #000;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-print { background: #000; color: #fff; }
        .btn-close  { background: #fff; color: #000; }
        .badge {
            display: inline-block;
            background: #fff;
            color: #000;
            border: 1px solid #000;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 9px;
            font-weight: 700;
        }

        /* ── Print Media ── */
        @media print {
            @page {
                size: A4 {{ $filteredShops->count() === 1 ? 'portrait' : 'landscape' }};
                margin: 3mm;
            }
            html,
            body {
                height: auto;
                overflow: visible;
                padding: 0;
                width: auto;
            }
            .no-print { display: none !important; }
            .sort-sheet-page {
                break-after: page;
                height: calc({{ $filteredShops->count() === 1 ? '297mm' : '210mm' }} - 6mm);
                overflow: hidden;
                padding: 2mm;
                width: calc({{ $filteredShops->count() === 1 ? '210mm' : '297mm' }} - 6mm);
            }
            table { font-size: 12px; }
            thead tr th {
                font-size: 10px;
                font-weight: 900;
                padding: 3px 2px;
            }
            tbody tr { height: 29px; }
            tbody tr td {
                font-size: 12px;
                padding: 3px 2px;
            }
            tbody tr td:first-child {
                font-size: 11px;
                font-weight: 900;
            }
            .item-cell {
                font-size: 13px;
                font-weight: 900;
            }
            .qty-cell,
            .total-cell {
                font-size: 13px;
                font-weight: 900;
            }
            .page-summary {
                font-size: 10px;
                line-height: 1.1;
                margin-bottom: 2px;
                padding-bottom: 2px;
            }
            .shop-heading-name {
                display: block;
                overflow: visible;
                -webkit-line-clamp: unset;
            }
            .shop-heading-tag { font-size: 9px; }
            thead { display: table-row-group; }
            tr { page-break-inside: avoid; }
            .sort-sheet-page:last-child { break-after: auto; }
            a { text-decoration: none; }
        }
    </style>
</head>
<body>

    {{-- Print / Close Buttons (hidden on print) --}}
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">
            Print
        </button>
        <button class="btn-close" onclick="window.close()">
            Close
        </button>
        <span class="badge">{{ count($matrix) }} products · {{ $filteredShops->count() }} shops</span>
    </div>

    {{-- Sort Sheet Table --}}
    @if(count($matrix) > 0)
    @php
        $rowsPerPage = 22;
        $matrixPages = array_chunk($matrix, $rowsPerPage, true);
        $singleShop = $filteredShops->count() === 1;
        $slWidth = $singleShop ? 8 : 4;
        $itemWidth = $singleShop ? 52 : 18;
        $shopWidth = $filteredShops->count() > 0 ? ($singleShop ? 25 : 71 / $filteredShops->count()) : 0;
        $totalWidth = $singleShop ? 15 : 7;
        $formatQty = fn (float $qty): string => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
    @endphp
    @foreach($matrixPages as $pageIndex => $pageMatrix)
    <div class="sort-sheet-page">
    <div class="page-summary">
        {{ $companyName }}. Order Summary Sheet — {{ \Carbon\Carbon::parse($date)->format('d M Y, l') }}{{ isset($selectedWarehouse) && $selectedWarehouse ? ' Warehouse: '.$selectedWarehouse->name : '' }} Products: {{ count($matrix) }} | Shops: {{ $filteredShops->count() }}
    </div>
    <table>
        <colgroup>
            <col style="width:{{ $slWidth }}%">
            <col style="width:{{ $itemWidth }}%">
            @foreach($filteredShops as $shop)
                <col style="width:{{ $shopWidth }}%">
            @endforeach
            <col style="width:{{ $totalWidth }}%">
        </colgroup>
        <thead>
            <tr>
                <th>Code</th>
                <th class="item-heading">Item</th>
                @foreach($filteredShops as $shop)
                <th>
                    <span class="shop-heading-name">{{ $shop->name }}</span>
                    @if($shop->warehouse_tag)
                        <span class="shop-heading-tag">{{ $shop->warehouse_tag }}</span>
                    @endif
                </th>
                @endforeach
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pageMatrix as $productId => $shopQtys)
            @php
                $meta = $productMeta[$productId];
                $total = array_sum($shopQtys);
            @endphp
            <tr>
                <td>{{ $meta['sku'] }}</td>
                <td class="item-cell">{{ $meta['name'] }}</td>
                @foreach($filteredShops as $shop)
                @php $qty = $shopQtys[$shop->id] ?? 0; @endphp
                <td class="qty-cell {{ $qty <= 0 ? 'zero' : '' }}">
                    {{ $qty > 0 ? $formatQty((float) $qty) : '0' }}
                </td>
                @endforeach
                <td class="total-cell">
                    {{ $formatQty((float) $total) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    @endforeach
    @else
    <p style="padding: 20px; text-align: center; color: #000; font-size: 12px;">
        No approved shop orders found for this date.
    </p>
    @endif

</body>
</html>
