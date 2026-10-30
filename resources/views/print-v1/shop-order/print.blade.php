<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shop Order — {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</title>
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
                size: A4 portrait;
                margin: 8mm 21mm;
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
        <button type="button" onclick="window.print()">Print</button>
        <button type="button" onclick="window.close()">Close</button>
        <span style="font-weight: 700; font-size: 12px; margin-left: 8px;">
            Shop Order &middot; {{ $gradeHeading }} &middot; {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
            &middot; {{ $totalShopsWithOrders }} shops &middot; {{ $totalItemsAllShops }} items
            {{ isset($selectedWarehouse) && $selectedWarehouse ? ' &middot; '.$selectedWarehouse->name : '' }}
        </span>
    </div>

    @if(count($shopSections) > 0)
        @foreach($shopSections as $section)
            @php
                $shop = $section['shop'];
                $rows = $section['rows'];
            @endphp
            @continue(count($rows) === 0)

            <div class="page">
                <table>
                    <colgroup>
                        <col style="width:10%">
                        <col style="width:45%">
                        <col style="width:25%">
                        <col style="width:20%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="shop-title-cell" colspan="4">
                                <div class="shop-title-content">
                                    <strong>{{ $shop->warehouse_tag ? $shop->warehouse_tag.' - '.$shop->name : $shop->name }}</strong>
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
                        @foreach($rows as $row)
                            <tr>
                                <td class="code-cell">{{ $row['code'] }}</td>
                                <td class="item-cell">{{ $row['display_name'] }}</td>
                                <td>{{ $row['formatted_qty'] }}</td>
                                <td class="total-cell"></td>
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

