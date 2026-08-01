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
            width: 75%;
        }
        .sheet {
            width: 75%;
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
        .tag-cell {
            display: block;
            font-size: 10px;
            font-weight: 400;
            margin-top: 1px;
        }
        .total-cell {
            font-weight: 500;
        }
        @media print {
            @page { size: A4 portrait; margin: 3mm; }
            html,
            body {
                height: auto;
                overflow: visible;
                padding: 0;
                width: auto;
            }
            .no-print { display: none !important; }
            .page {
                height: calc(297mm - 6mm);
                overflow: hidden;
                padding: 6mm 7mm;
                width: calc(210mm - 6mm);
            }
            .summary {
                font-size: 10px;
                margin-bottom: 2px;
                width: 75%;
            }
            .sheet {
                width: 75%;
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
            $rowsPerPage = 9;
            $formatQty = fn (float $qty): string => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
            $shopPages = [];

            foreach ($filteredShops as $shop) {
                $rows = [];

                foreach ($matrix as $productId => $shopQtys) {
                    $qty = (float) ($shopQtys[$shop->id] ?? 0);

                    if ($qty <= 0) {
                        continue;
                    }

                    $meta = $productMeta[$productId];
                    $rows[] = [
                        'code' => $meta['sku'] ?: $productId,
                        'name' => $meta['name'],
                        'qty' => $formatQty($qty),
                    ];
                }

                foreach (array_chunk($rows, $rowsPerPage) as $chunk) {
                    $shopPages[] = [
                        'shop' => $shop,
                        'rows' => $chunk,
                        'product_count' => count($rows),
                    ];
                }
            }
        @endphp

        @forelse($shopPages as $shopPage)
            <div class="page">
                <div class="summary">
                    <div>{{ $companyName }}{{ isset($selectedWarehouse) && $selectedWarehouse ? ' - '.$selectedWarehouse->name : '' }} · {{ $shopPage['shop']->name }}</div>
                    <div>{{ \Carbon\Carbon::parse($date)->format('d-M') }}</div>
                </div>
                <div class="sheet">
                    <table>
                        <colgroup>
                            <col style="width:12%">
                            <col style="width:48%">
                            <col style="width:20%">
                            <col style="width:20%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Item</th>
                                <th>
                                    {{ $shopPage['shop']->name }}
                                    <span class="tag-cell">{{ $shopPage['shop']->warehouse_tag ?: '-' }}</span>
                                </th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($shopPage['rows'] as $row)
                                <tr class="qty-row">
                                    <td class="sl-cell">{{ $row['code'] }}</td>
                                    <td class="item-cell">{{ $row['name'] }}</td>
                                    <td>{{ $row['qty'] }}</td>
                                    <td class="total-cell"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <p>No approved shop order quantities found for this selection.</p>
        @endforelse
    @else
        <p>No approved shop orders found for this date.</p>
    @endif
</body>
</html>
