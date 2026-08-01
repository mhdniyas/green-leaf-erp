<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Segregation Grid - {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</title>
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
        }
        .no-print button {
            background: #fff;
            border: 1px solid #000;
            color: #000;
            cursor: pointer;
            font-size: 11px;
            font-weight: 700;
            padding: 7px 18px;
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
            font-weight: 500;
            height: 30px;
        }
        td {
            font-size: 10px;
        }
        .qty-row,
        .tag-row {
            height: 37px;
        }
        .sl-cell,
        .total-cell {
            font-weight: 500;
        }
        .item-cell {
            font-weight: 500;
            text-align: center;
        }
        .tag-cell {
            font-size: 10px;
            font-weight: 400;
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
        <span>{{ count($matrix) }} products · {{ $filteredShops->count() }} shops{{ isset($selectedWarehouse) && $selectedWarehouse ? ' · '.$selectedWarehouse->name : '' }}</span>
    </div>

    @if(count($matrix) > 0)
        @php
            $rowsPerPage = 9;
            $matrixPages = array_chunk($matrix, $rowsPerPage, true);
            $shopCount = max(1, $filteredShops->count());
            $shopWidth = (100 - 4 - 15 - 6) / $shopCount;
            $formatQty = fn (float $qty): string => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
        @endphp

        @foreach($matrixPages as $pageMatrix)
            <div class="page">
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
                        @foreach($pageMatrix as $productId => $shopQtys)
                            @php
                                $meta = $productMeta[$productId];
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
                                <td class="sl-cell">{{ $meta['sku'] ?: $productId }}</td>
                                <td class="item-cell">{{ $meta['name'] }}</td>
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
        <p>No approved shop orders found for this date.</p>
    @endif
</body>
</html>
