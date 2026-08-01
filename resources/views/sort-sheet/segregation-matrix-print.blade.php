<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shop Wise Segregation - {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html {
            background: #fff;
        }
        body {
            background: #fff;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            padding: 5px;
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
            min-height: calc(100vh - 10px);
            width: 100%;
        }
        .page:last-child {
            break-after: auto;
            page-break-after: auto;
        }
        .shop-title {
            align-items: flex-end;
            display: flex;
            font-size: 14px;
            font-weight: 900;
            justify-content: space-between;
            line-height: 1.1;
            margin: 5px 0 6px;
            min-height: 20px;
        }
        .shop-title strong {
            font-weight: 900;
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
            height: 24px;
            line-height: 1.05;
            padding: 1px 3px;
            text-align: center;
            vertical-align: middle;
            word-break: break-word;
        }
        th {
            font-size: 9px;
            font-weight: 600;
        }
        td {
            font-size: 10px;
        }
        .code-cell {
            font-weight: 400;
        }
        .item-cell {
            font-weight: 500;
            text-align: center;
        }
        .shop-head {
            font-size: 9px;
            font-weight: 700;
            line-height: 1.05;
        }
        .total-cell {
            font-weight: 500;
        }
        thead {
            display: table-header-group;
        }
        tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        @media print {
            @page { size: A4 landscape; margin: 5px; }
            html,
            body {
                height: auto;
                overflow: visible;
                padding: 0;
                width: auto;
            }
            .no-print { display: none !important; }
            .page {
                break-after: page;
                min-height: calc(210mm - 10px);
                overflow: visible;
                padding: 0;
                width: calc(297mm - 10px);
            }
            .shop-title {
                font-size: 14px;
                margin: 5px 0 6px;
            }
            th,
            td {
                height: 24px;
                padding: 1px 3px;
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
            $formatQty = fn (float $qty): string => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
        @endphp

        @foreach($filteredShops as $shop)
            @php
                $shopRows = [];

                foreach ($matrix as $productId => $shopQtys) {
                    $qty = (float) ($shopQtys[$shop->id] ?? 0);

                    if ($qty <= 0) {
                        continue;
                    }

                    $meta = $productMeta[$productId];
                    $shopRows[] = [
                        'code' => $meta['sku'] ?: $productId,
                        'name' => $meta['name'],
                        'qty' => $formatQty($qty),
                    ];
                }
            @endphp

            @continue(count($shopRows) === 0)

            <div class="page">
                <div class="shop-title">
                    <strong>{{ $shop->warehouse_tag ? $shop->warehouse_tag.' - '.$shop->name : $shop->name }}</strong>
                    <strong>{{ \Carbon\Carbon::parse($date)->format('d-M') }}</strong>
                </div>
                <table>
                    <colgroup>
                        <col style="width:6%">
                        <col style="width:54%">
                        <col style="width:25%">
                        <col style="width:15%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Item</th>
                            <th class="shop-head">{{ $shop->name }}</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($shopRows as $row)
                            <tr>
                                <td class="code-cell">{{ $row['code'] }}</td>
                                <td class="item-cell">{{ $row['name'] }}</td>
                                <td>{{ $row['qty'] }}</td>
                                <td class="total-cell"></td>
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
