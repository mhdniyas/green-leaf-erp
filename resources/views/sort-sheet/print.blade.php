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
            font-size: 14px;
            font-weight: 900;
            line-height: 1.25;
            margin-bottom: 4px;
            padding-bottom: 3px;
            text-align: center;
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
        thead tr th.print-title-cell {
            border: 0;
            border-bottom: 2px solid #000;
            font-size: 14px;
            font-weight: 900;
            padding: 3px 0;
            text-align: center;
        }
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
        /* Qty cols */
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
            max-width: 100%;
            width: 100%;
        }

        .category-block {
            margin-bottom: 12px;
            max-width: 100%;
        }
        .category-block.has-page-break {
            break-before: page !important;
            page-break-before: always !important;
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
                margin: 5mm;
            }
            html,
            body {
                height: auto;
                overflow: visible;
                padding: 0;
                max-width: 100%;
                width: auto;
            }
            .no-print { display: none !important; }
            .sort-sheet-page {
                height: auto !important;
                overflow: visible !important;
                padding: 0;
                max-width: 100% !important;
                width: 100% !important;
            }
            .category-block.has-page-break {
                break-before: page !important;
                page-break-before: always !important;
                margin-top: 0 !important;
                padding-top: 2mm !important;
            }
            table {
                font-size: 12px;
                max-width: 100%;
                width: 100%;
            }
            thead {
                display: table-header-group;
            }
            thead tr th {
                font-size: 10px;
                font-weight: 900;
                overflow-wrap: anywhere;
                padding: 3px 2px;
            }
            thead tr th.print-title-cell {
                font-size: 14px;
                padding: 2mm 0 1.5mm;
            }
            tbody tr { height: 29px; }
            tbody tr td {
                font-size: 12px;
                font-weight: 700;
                overflow-wrap: anywhere;
                padding: 3px 2px;
            }
            tbody tr td:first-child {
                font-size: 10px;
            }
            .item-cell {
                font-size: 12px;
                font-weight: 700;
            }
            .qty-cell {
                font-size: 13px;
                font-weight: 900;
            }
            .total-cell {
                font-size: 13px;
                font-weight: 900;
            }
            .page-summary {
                font-size: 11px;
                margin-bottom: 6px;
                padding-bottom: 2px;
            }
        }
    </style>
</head>
<body>

    {{-- Top Controls Bar (hidden during printing) --}}
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
            </svg>
            Print / Save PDF
        </button>
        <a class="btn-close" href="javascript:window.close()">Close Window</a>

        <span class="badge">Date: {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</span>
        @if(isset($selectedWarehouse) && $selectedWarehouse)
            <span class="badge">Warehouse: {{ $selectedWarehouse->name }} ({{ $selectedWarehouse->code }})</span>
        @else
            <span class="badge">All Warehouses</span>
        @endif
        <span class="badge">Total Products: {{ count($matrix) }}</span>
        <span class="badge">Total Shops: {{ $filteredShops->count() }}</span>
        <span class="badge">Generated By: {{ $generatedBy }} ({{ $generatedAt }})</span>
    </div>

    @if($filteredShops->count() > 0 && count($matrix) > 0)
    @php
        $slWidth = 4;
        $singleShop = $filteredShops->count() === 1;
        $itemWidth = $singleShop ? 52 : 18;
        $shopWidth = $filteredShops->count() > 0 ? ($singleShop ? 25 : 71 / $filteredShops->count()) : 0;
        $totalWidth = $singleShop ? 15 : 7;
        $formatQty = fn (float $qty): string => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');

        $separateCategoryPages = request()->boolean('separate_category_pages', false);
        $pageBreakCategoryIds = array_map('intval', (array) request()->input('page_break_category_ids', []));
        $showCategoryTitles = request()->has('show_category_titles')
            ? request()->boolean('show_category_titles')
            : true;

        $categoryBlocks = [];
        if ($showCategoryTitles) {
            foreach ($matrix as $productId => $shopQtys) {
                $meta = $productMeta[$productId];
                $catId = (int) ($meta['category_id'] ?? 0);
                $catName = $meta['category_name'] ?? 'General';
                if (! isset($categoryBlocks[$catName])) {
                    $categoryBlocks[$catName] = [
                        'id' => $catId,
                        'name' => $catName,
                        'items' => [],
                    ];
                }
                $categoryBlocks[$catName]['items'][$productId] = $shopQtys;
            }
        } else {
            $categoryBlocks['all_products'] = [
                'id' => 0,
                'name' => 'All Products',
                'items' => $matrix,
            ];
        }

        $previousCatId = null;
        $isFirstBlock = true;
    @endphp

    <div class="sort-sheet-page">
    @foreach($categoryBlocks as $catName => $block)
    @php
        $catId = $block['id'];
        $catItems = $block['items'];

        $shouldBreak = ! $isFirstBlock && (
            $separateCategoryPages ||
            ($previousCatId !== null && in_array($previousCatId, $pageBreakCategoryIds, true))
        );
        $isFirstBlock = false;
        $previousCatId = $catId;
    @endphp
    <div class="category-block {{ $shouldBreak ? 'has-page-break' : '' }}">
        @if($showCategoryTitles)
        <div style="background: #fff; color: #000; font-weight: 900; font-size: 11px; text-transform: uppercase; padding: 4px 0px 2px 0px; border-bottom: 2px solid #000; letter-spacing: 0.05em; margin-top: 8px; margin-bottom: 4px;">
            Category: {{ $catName }} — {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
        </div>
        @endif
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
                    <th class="print-title-cell" colspan="{{ $filteredShops->count() + 3 }}">
                        GREEN LEAF - {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
                    </th>
                </tr>
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
                @foreach($catItems as $productId => $shopQtys)
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
    </div>
    @else
    <div class="sort-sheet-page">
        <div class="page-summary">
            GREEN LEAF - {{ \Carbon\Carbon::parse($date)->format('d M Y') }}
        </div>
    <p style="padding: 20px; text-align: center; color: #000; font-size: 12px;">
        No approved shop orders found for this date.
    </p>
    </div>
    @endif

</body>
</html>
