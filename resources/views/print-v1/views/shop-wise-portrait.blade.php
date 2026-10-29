<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shop Wise Segregation - {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</title>
    @php
        $printOrientation = ($settings['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $isPortraitPrint = $printOrientation === 'portrait';
    @endphp
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html {
            background: #fff;
        }
        body {
            background: #fff;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 17px;
            padding: 5px;
        }
        .no-print {
            align-items: center;
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }
        .no-print button, .no-print a {
            background: #fff;
            border: 1px solid #000;
            color: #000;
            cursor: pointer;
            font-size: 11px;
            font-weight: 700;
            padding: 7px 18px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .no-print button.btn-primary {
            background: #000;
            color: #fff;
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
        .shop-title-content {
            align-items: flex-end;
            display: flex;
            font-size: 21px;
            font-weight: 900;
            justify-content: space-between;
            line-height: 1.1;
            min-height: 20px;
        }
        .shop-title-content strong {
            font-weight: 900;
        }
        .shop-title-cell {
            border: 0;
            border-bottom: 2px solid #000;
            padding: 5px 0 6px;
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
            font-size: 14px;
            font-weight: 600;
        }
        td {
            font-size: 15px;
        }
        .code-cell {
            font-weight: 400;
        }
        .item-cell {
            font-weight: 500;
            text-align: center;
        }
        .shop-head {
            font-size: 14px;
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
            @page {
                size: A4 {{ $printOrientation }};
                margin: 8mm {{ $isPortraitPrint ? '21mm' : '8mm' }};
            }
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
                min-height: auto;
                overflow: visible;
                padding: 0;
                width: 100%;
            }
            .shop-title-content {
                font-size: 21px;
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
        <button type="button" class="btn-primary" onclick="window.print()">Print / Save PDF</button>
        <a href="javascript:window.close()">Close Window</a>
        <span style="font-weight: 800;">{{ $result->gradeHeading() }}</span>
        <span>&bull; {{ count($matrix) }} products &bull; {{ $filteredShops->count() }} shops{{ $selectedWarehouse ? ' &bull; '.$selectedWarehouse->name : '' }}</span>
        @if($result->skippedShopCount() > 0)
            <span style="color: #64748b;">({{ $result->skippedShopCount() }} empty shops auto-skipped)</span>
        @endif
    </div>

    @if(count($matrix) > 0 && $filteredShops->count() > 0)
        @php
            $skipEmpty = (bool) ($settings['skip_empty_shops'] ?? true);
            $formatQty = fn (float $qty): string => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
        @endphp

        @foreach($filteredShops as $shop)
            @php
                $shopRows = [];

                foreach ($matrix as $rowKey => $shopQtys) {
                    $qty = (float) ($shopQtys[$shop->id] ?? 0);

                    if ($qty <= 0) {
                        continue;
                    }

                    $meta = $productMeta[$rowKey];
                    $shopRows[] = [
                        'code' => $meta['sku'] ?: $meta['product_id'],
                        'name' => $meta['display_name'] ?? $meta['name'],
                        'qty' => $formatQty($qty),
                    ];
                }
            @endphp

            @continue($skipEmpty && count($shopRows) === 0)

            <div class="page">
                <table>
                    <colgroup>
                        @if($isPortraitPrint)
                            <col style="width:10%">
                            <col style="width:45%">
                            <col style="width:25%">
                            <col style="width:20%">
                        @else
                            <col style="width:6%">
                            <col style="width:54%">
                            <col style="width:25%">
                            <col style="width:15%">
                        @endif
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="shop-title-cell" colspan="4">
                                <div class="shop-title-content">
                                    <strong>{{ $shop->warehouse_tag ? $shop->warehouse_tag.' - '.$shop->name : $shop->name }}</strong>
                                    <span style="font-size: 15px; font-weight: 900; letter-spacing: 0.5px;">{{ $result->gradeHeading() }}</span>
                                    <strong>{{ \Carbon\Carbon::parse($date)->format('d-M') }}</strong>
                                </div>
                            </th>
                        </tr>
                        <tr>
                            <th>Code</th>
                            <th>Item</th>
                            <th class="shop-head">Qty</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($shopRows as $row)
                            <tr>
                                <td class="code-cell">{{ $row['code'] }}</td>
                                <td class="item-cell">{{ $row['name'] }}</td>
                                <td>{{ $row['qty'] }}</td>
                                <td class="total-cell"></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 16px; color: #94a3b8; font-style: italic; font-size: 11px;">
                                    No eligible order items for this date.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endforeach
    @else
        <p>No approved shop orders found for this date.</p>
    @endif
</body>
</html>

