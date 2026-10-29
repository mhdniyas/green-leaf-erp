<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Selection List - {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { background: #fff; }
        body {
            background: #fff;
            color: #000;
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 12px;
            padding: 8mm;
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
            overflow: hidden;
            width: 100%;
        }
        .page:last-child {
            break-after: auto;
            page-break-after: auto;
        }
        .shop-title {
            font-size: 16px;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 1px;
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
            line-height: 1.08;
            padding: 3px 4px;
            text-align: center;
            vertical-align: middle;
            word-break: break-word;
        }
        th {
            font-size: 13px;
            font-weight: 400;
        }
        td {
            font-size: 13px;
        }
        tbody tr {
            height: 24px;
        }
        .code-cell,
        .qty-cell {
            font-family: Georgia, 'Times New Roman', serif;
            font-weight: 700;
        }
        .item-cell {
            font-weight: 700;
            text-align: center;
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
                padding: 2mm;
                width: calc(210mm - 6mm);
            }
            .shop-title {
                font-size: 17px;
                margin-bottom: 1px;
            }
            th {
                font-size: 13px;
                padding: 3px 4px;
            }
            td {
                font-size: 14px;
                padding: 3px 4px;
            }
            tbody tr {
                height: 24px;
            }
            .code-cell,
            .qty-cell {
                font-size: 15px;
                font-weight: 900;
            }
            .item-cell {
                font-size: 14px;
                font-weight: 800;
            }
            tr { page-break-inside: avoid; }
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
            $rowsPerPage = (int) ($settings['items_per_page'] ?? 36);
            $skipEmpty = (bool) ($settings['skip_empty_shops'] ?? true);
            $formatQty = fn (float $qty): string => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
            $shopPages = [];

            foreach ($filteredShops as $shop) {
                $rows = [];

                foreach ($matrix as $rowKey => $shopQtys) {
                    $qty = (float) ($shopQtys[$shop->id] ?? 0);

                    if ($qty <= 0) {
                        continue;
                    }

                    $meta = $productMeta[$rowKey];
                    $rows[] = [
                        'code' => $meta['sku'] ?: $meta['product_id'],
                        'name' => $meta['display_name'] ?? $meta['name'],
                        'qty' => $formatQty($qty),
                    ];
                }

                if (empty($rows)) {
                    if ($skipEmpty) {
                        continue;
                    }
                    $shopPages[] = [
                        'shop' => $shop,
                        'rows' => [],
                    ];
                    continue;
                }

                foreach (array_chunk($rows, $rowsPerPage) as $chunk) {
                    $shopPages[] = [
                        'shop' => $shop,
                        'rows' => $chunk,
                    ];
                }
            }
        @endphp

        @forelse($shopPages as $shopPage)
            <div class="page">
                <div class="shop-title">
                    {{ $shopPage['shop']->warehouse_tag ? '['.$shopPage['shop']->warehouse_tag.'] ' : '' }}{{ $shopPage['shop']->name }}{{ $selectedWarehouse ? ' - '.$selectedWarehouse->name : '' }} &bull; {{ $result->gradeHeading() }} &bull; {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
                </div>

                <table>
                    <colgroup>
                        <col style="width:16%">
                        <col style="width:66%">
                        <col style="width:18%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Description</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($shopPage['rows'] as $row)
                            <tr>
                                <td class="code-cell">{{ $row['code'] }}</td>
                                <td class="item-cell">{{ $row['name'] }}</td>
                                <td class="qty-cell">{{ $row['qty'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 16px; color: #94a3b8; font-style: italic; font-size: 11px;">
                                    No eligible order items for this date.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @empty
            <p>No approved shop order quantities found for this selection.</p>
        @endforelse
    @else
        <p>No approved shop orders found for this date.</p>
    @endif
</body>
</html>

