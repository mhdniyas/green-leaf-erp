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
        .item-sku {
            font-family: monospace;
            font-size: 7.5px;
            color: #000;
            font-weight: 400;
            display: block;
        }
        .qty-cell {
            text-align: center;
            font-weight: 700;
            font-size: 9px;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #000;
        }
        .total-cell {
            text-align: right;
            font-weight: 800;
            font-size: 9.5px;
            background: #fff;
            color: #000;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .category-header-row td {
            background: #000 !important;
            color: #fff !important;
            font-weight: 900;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 2.5px 5px;
            border: 1px solid #000;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Top Bar ── */
        .no-print {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
            padding: 8px 12px;
            background: #fff;
            border: 1px solid #000;
            border-radius: 8px;
        }
        .btn-print, .btn-close {
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
            .item-sku {
                font-size: 9px;
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
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <button class="btn-print" onclick="window.print()">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
                </svg>
                Print / Save PDF
            </button>
            <a class="btn-close" href="javascript:window.close()">Close Window</a>

            <span class="badge">Date: {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</span>
            <span class="badge" style="font-weight: 800;">{{ $result->gradeHeading() }}</span>
            @if($selectedWarehouse)
                <span class="badge">Warehouse: {{ $selectedWarehouse->name }}</span>
            @else
                <span class="badge">All Warehouses</span>
            @endif
            <span class="badge">Products: {{ count($matrix) }}</span>
            <span class="badge">Active Shops: {{ $filteredShops->count() }}</span>
            @if($result->skippedShopCount() > 0)
                <span class="badge" title="Shops with 0 order quantity were automatically excluded">
                    Empty Shops Skipped: {{ $result->skippedShopCount() }}
                </span>
            @endif
            @if(isset($preset) && $preset)
                <span class="badge">Preset: {{ $preset->name }}</span>
            @endif
        </div>
    </div>

    @if($filteredShops->count() > 0 && count($matrix) > 0)
    @php
        $slWidth = 4;
        $singleShop = $filteredShops->count() === 1;
        $itemWidth = $singleShop ? 52 : 18;
        $shopWidth = $filteredShops->count() > 0 ? ($singleShop ? 25 : (100 - $slWidth - $itemWidth - ($singleShop ? 15 : 7)) / $filteredShops->count()) : 0;
        $totalWidth = $singleShop ? 15 : 7;
        $formatQty = fn (float $qty): string => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');

        $separateCategoryPages = (bool) ($settings['separate_category_pages'] ?? false);
        $pageBreakCategoryIds = array_map('intval', (array) ($settings['page_break_category_ids'] ?? []));
        $showCategoryTitles = (bool) ($settings['show_category_titles'] ?? true);

        $categoryBlocks = [];
        if ($showCategoryTitles) {
            foreach ($matrix as $rowKey => $shopQtys) {
                $meta = $productMeta[$rowKey];
                $catId = (int) ($meta['category_id'] ?? 0);
                $catName = $meta['category_name'] ?? 'General';
                if (! isset($categoryBlocks[$catName])) {
                    $categoryBlocks[$catName] = [
                        'id' => $catId,
                        'name' => $catName,
                        'items' => [],
                    ];
                }
                $categoryBlocks[$catName]['items'][$rowKey] = $shopQtys;
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
        $hasBreak = false;
        if (! $isFirstBlock) {
            if ($separateCategoryPages) {
                $hasBreak = true;
            } elseif (! empty($pageBreakCategoryIds) && in_array((int) $block['id'], $pageBreakCategoryIds, true)) {
                $hasBreak = true;
            }
        }
    @endphp

    <div class="category-block {{ $hasBreak ? 'has-page-break' : '' }}">
        <table>
            <colgroup>
                <col style="width: {{ $slWidth }}%;">
                <col style="width: {{ $itemWidth }}%;">
                @foreach($filteredShops as $shop)
                    <col style="width: {{ $shopWidth }}%;">
                @endforeach
                <col style="width: {{ $totalWidth }}%;">
            </colgroup>
            <thead>
                <tr>
                    <th colspan="{{ 2 + $filteredShops->count() + 1 }}" class="print-title-cell">
                        <div class="page-summary">
                            {{ $companyName }} &bull; Sort Sheet &bull; {{ $result->gradeHeading() }} &bull; {{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}
                            @if($selectedWarehouse)
                                &bull; {{ $selectedWarehouse->name }}
                            @endif
                        </div>
                    </th>
                </tr>
                <tr>
                    <th>SL</th>
                    <th class="item-heading">Item Description</th>
                    @foreach($filteredShops as $shop)
                        <th>
                            <span class="shop-heading-name">{{ $shop->name }}</span>
                            @if($shop->warehouse_tag)
                                <span class="shop-heading-tag">[{{ $shop->warehouse_tag }}]</span>
                            @endif
                        </th>
                    @endforeach
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @if($showCategoryTitles && $catName !== 'all_products')
                    <tr class="category-header-row">
                        <td colspan="{{ 2 + $filteredShops->count() + 1 }}">{{ $catName }}</td>
                    </tr>
                @endif

                @php $sl = 1; @endphp
                @foreach($block['items'] as $rowKey => $shopQtys)
                    @php
                        $meta = $productMeta[$rowKey];
                        $total = array_sum($shopQtys);
                    @endphp
                    <tr>
                        <td>{{ $sl++ }}</td>
                        <td class="item-cell">
                            {{ $meta['display_name'] }}
                            @if(! empty($meta['sku']))
                                <span class="item-sku">{{ $meta['sku'] }}</span>
                            @endif
                        </td>
                        @foreach($filteredShops as $shop)
                            @php $qty = (float) ($shopQtys[$shop->id] ?? 0); @endphp
                            <td class="qty-cell">
                                {{ $qty > 0 ? $formatQty($qty) : '-' }}
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
    @php
        $isFirstBlock = false;
        $previousCatId = (int) $block['id'];
    @endphp
    @endforeach
    </div>

    @else
        <div style="padding: 40px; text-align: center; font-weight: 700; font-size: 14px; border: 2px dashed #000; border-radius: 8px;">
            No approved shop orders found for {{ \Carbon\Carbon::parse($date)->format('d M Y') }} matching the active settings.
        </div>
    @endif

</body>
</html>

