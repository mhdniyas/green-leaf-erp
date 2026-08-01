<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Segregation Matrix - {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html,
        body {
            background: #fff;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            padding: 6mm;
        }
        .no-print {
            align-items: center;
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
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
        .summary {
            align-items: end;
            display: flex;
            font-size: 11px;
            font-weight: 900;
            justify-content: space-between;
            margin-bottom: 3px;
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
            padding: 2px 2px;
            text-align: center;
            vertical-align: middle;
            word-break: break-word;
        }
        th {
            font-size: 10px;
            font-weight: 500;
        }
        td {
            font-size: 10px;
        }
        .sl-cell {
            font-weight: 400;
        }
        .item-cell {
            font-weight: 500;
            text-align: center;
        }
        .qty-row {
            height: 7.3mm;
        }
        .tag-row {
            height: 6.7mm;
        }
        .tag-cell {
            font-size: 10px;
            font-weight: 400;
        }
        .total-cell {
            font-weight: 500;
        }
        @media print {
            @page { size: A4 landscape; margin: 3mm; }
            html,
            body {
                height: auto;
                overflow: visible;
                padding: 0;
                width: auto;
            }
            .no-print { display: none !important; }
            .page {
                height: calc(210mm - 6mm);
                overflow: hidden;
                padding: 1.5mm;
                width: calc(297mm - 6mm);
            }
            .summary {
                font-size: 10px;
                margin-bottom: 2px;
            }
            th {
                font-size: 9px;
                padding: 2px 1px;
            }
            td {
                font-size: 10px;
                padding: 2px 1px;
            }
            .qty-row { height: 7.1mm; }
            .tag-row { height: 6.5mm; }
            .tag-cell { font-size: 9px; }
            tr { page-break-inside: avoid; }
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
            $rowsPerPage = 14;
            $matrixPages = array_chunk($matrix, $rowsPerPage, true);
            $shopCount = max(1, $filteredShops->count());
            $shopWidth = (100 - 3 - 15 - 7) / $shopCount;
            $formatQty = fn (float $qty): string => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
        @endphp

        @foreach($matrixPages as $pageIndex => $pageMatrix)
            <div class="page">
                <div class="summary">
                    <div>{{ $companyName }}{{ isset($selectedWarehouse) && $selectedWarehouse ? ' - '.$selectedWarehouse->name : '' }}</div>
                    <div>{{ \Carbon\Carbon::parse($date)->format('d-M') }}</div>
                </div>
                <table>
                    <colgroup>
                        <col style="width:3%">
                        <col style="width:15%">
                        @foreach($filteredShops as $shop)
                            <col style="width:{{ $shopWidth }}%">
                        @endforeach
                        <col style="width:7%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>SL</th>
                            <th>Item</th>
                            @foreach($filteredShops as $shop)
                                <th>
                                    {{ $shop->name }}
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
                            <tr class="qty-row">
                                <td class="sl-cell">{{ $meta['sku'] ?: $productId }}</td>
                                <td class="item-cell">{{ $meta['name'] }}</td>
                                @foreach($filteredShops as $shop)
                                    @php $qty = (float) ($shopQtys[$shop->id] ?? 0); @endphp
                                    <td>{{ $qty > 0 ? $formatQty($qty) : '0' }}</td>
                                @endforeach
                                <td class="total-cell">{{ $formatQty((float) $total) }}</td>
                            </tr>
                            <tr class="tag-row">
                                <td></td>
                                <td></td>
                                @foreach($filteredShops as $shop)
                                    <td class="tag-cell">{{ $shop->warehouse_tag ?: '-' }}</td>
                                @endforeach
                                <td></td>
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
