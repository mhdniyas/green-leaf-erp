@php
    $showCode = (bool) ($filters['show_code'] ?? true);
    $showCategoryTitles = (bool) ($filters['show_category_titles'] ?? ($filters['show_category_headers'] ?? true));
    $isMultipleGrades = count($selectedGrades) > 1;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Selection List - {{ \Carbon\Carbon::parse($date)->format('d M Y') }} - {{ $gradeHeading }}</title>
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
            margin-bottom: 12px;
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 11px;
            color: #475569;
        }
        .no-print button {
            background: #0f172a;
            border: 1px solid #0f172a;
            color: #fff;
            cursor: pointer;
            font-size: 11px;
            font-weight: 700;
            padding: 6px 16px;
            border-radius: 6px;
        }
        .no-print button.close-btn {
            background: #fff;
            border: 1px solid #cbd5e1;
            color: #334155;
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
        .shop-header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-bottom: 4px;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
        }
        .shop-title {
            font-size: 16px;
            font-weight: 900;
            line-height: 1.1;
        }
        .shop-subtitle {
            font-size: 11px;
            font-weight: 700;
            color: #333;
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
            font-weight: 700;
            background: #f8fafc;
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
            text-align: left;
            padding-left: 8px;
        }
        .category-header-row td {
            background: #f1f5f9;
            font-weight: 900;
            font-size: 10px;
            text-transform: uppercase;
            text-align: left;
            padding: 2px 6px;
            letter-spacing: 0.5px;
        }
        .grade-tag {
            font-size: 10px;
            font-weight: 900;
            margin-left: 4px;
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
        <button type="button" onclick="window.print()">Print Selection</button>
        <button type="button" class="close-btn" onclick="window.close()">Close</button>
        <span style="margin-left: 8px;">
            <strong>{{ $gradeHeading }}</strong> &middot; {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
            &middot; {{ $totalShopsWithOrders }} shops &middot; {{ $totalItemsAllShops }} items &middot; {{ $formattedTotalQtyAllShops }} kg
            {{ isset($selectedWarehouse) && $selectedWarehouse ? ' &middot; '.$selectedWarehouse->name : '' }}
        </span>
    </div>

    @if($hasData && count($shopPages) > 0)
        @foreach($shopPages as $shopPage)
            @php
                $shop = $shopPage['shop'];
                $rows = $shopPage['rows'];
                $currentCat = null;
            @endphp
            <div class="page">
                <div class="shop-header">
                    <div class="shop-title">
                        {{ $shop->name }}{{ isset($selectedWarehouse) && $selectedWarehouse ? ' - '.$selectedWarehouse->name : '' }}
                        <span style="font-size: 13px; font-weight: normal; margin-left: 8px;">({{ $gradeHeading }})</span>
                    </div>
                    <div class="shop-subtitle">
                        {{ \Carbon\Carbon::parse($date)->format('d M Y') }} &middot;
                        Page {{ $shopPage['page_number'] }} of {{ $shopPage['total_pages'] }}
                        &middot; Total: {{ $shopPage['formatted_shop_total_qty'] }} kg
                    </div>
                </div>

                <table>
                    <colgroup>
                        @if($showCode)
                        <col style="width:16%">
                        <col style="width:66%">
                        <col style="width:18%">
                        @else
                        <col style="width:80%">
                        <col style="width:20%">
                        @endif
                    </colgroup>
                    <thead>
                        <tr>
                            @if($showCode)
                            <th>Code</th>
                            @endif
                            <th style="text-align: left; padding-left: 8px;">Description</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            {{-- Category Heading Separator --}}
                            @if($showCategoryTitles && $currentCat !== $row['category_name'])
                            @php $currentCat = $row['category_name']; @endphp
                            <tr class="category-header-row">
                                <td colspan="{{ $showCode ? 3 : 2 }}">
                                    Category: {{ $row['category_name'] }}
                                </td>
                            </tr>
                            @endif

                            <tr>
                                @if($showCode)
                                <td class="code-cell">{{ $row['code'] }}</td>
                                @endif
                                <td class="item-cell">
                                    {{ $row['name'] }}
                                    @if($isMultipleGrades)
                                    <span class="grade-tag">(Gr.{{ $row['grade'] }})</span>
                                    @endif
                                </td>
                                <td class="qty-cell">{{ $row['formatted_qty'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @else
        <p style="padding: 24px; text-align: center; font-size: 14px;">No approved shop order quantities found for this selection.</p>
    @endif
</body>
</html>

