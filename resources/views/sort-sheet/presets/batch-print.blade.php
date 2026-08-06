<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Batch Presets Print — {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        html { background: #fff; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 10px;
            color: #000;
            background: #fff;
            padding: 12mm 10mm;
        }

        .preset-header-banner {
            background: #1e293b;
            color: #fff;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            border-radius: 4px;
        }

        .page-summary {
            border-bottom: 2px solid #000;
            font-size: 11px;
            font-weight: 900;
            line-height: 1.25;
            margin-bottom: 6px;
            padding-bottom: 3px;
        }

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
            padding: 4px 3px;
            border: 1px solid #000;
            text-align: center;
        }
        thead tr th.item-heading { text-align: left; }
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
            margin-top: 2px;
        }

        tbody tr td {
            padding: 4px 3px;
            border: 1px solid #000;
            vertical-align: middle;
            background: #fff;
            color: #000;
        }
        tbody tr td:first-child {
            text-align: center;
            font-size: 8px;
            font-family: monospace;
        }
        .item-cell {
            font-weight: 600;
            text-align: left;
            word-break: break-word;
        }
        .qty-cell {
            text-align: center;
            font-family: monospace;
            font-weight: 700;
        }
        .qty-cell.zero { color: #000; font-weight: 400; }
        .total-cell {
            text-align: center;
            font-weight: 900;
            background: #fff !important;
            color: #000;
            font-family: monospace;
        }

        .preset-container {
            margin-bottom: 20px;
        }
        .preset-container.has-preset-break {
            break-before: page !important;
            page-break-before: always !important;
        }

        .category-block {
            margin-bottom: 12px;
        }
        .category-block.has-page-break {
            break-before: page !important;
            page-break-before: always !important;
        }

        .page-heading {
            border-bottom: 2px solid #000;
            margin: 4px 0 6px;
            padding-bottom: 3px;
        }
        .page-heading .title {
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .page-heading .meta {
            margin-top: 1px;
            font-size: 9px;
            font-weight: 700;
        }

        .no-print {
            margin-bottom: 12px;
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

        @media print {
            @page {
                size: A4 landscape;
                margin: 7mm 5mm 5mm 5mm;
            }
            html, body {
                height: auto;
                overflow: visible;
                padding: 0;
                width: auto;
            }
            .no-print { display: none !important; }
            .preset-container {
                height: auto !important;
                overflow: visible !important;
                padding: 0;
                width: 100% !important;
            }
            .preset-container.has-preset-break {
                break-before: page !important;
                page-break-before: always !important;
            }
            .category-block.has-page-break {
                break-before: page !important;
                page-break-before: always !important;
            }
            table { font-size: 12px; }
            thead tr th { font-size: 10px; font-weight: 900; padding: 3px 2px; }
            tbody tr td { font-size: 12px; font-weight: 700; padding: 3px 2px; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
            </svg>
            Print All Selected Presets / Save PDF
        </button>
        <a class="btn-close" href="javascript:window.close()">Close Window</a>

        <span class="badge">Date: {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</span>
        <span class="badge">Presets Included: {{ count($batchData) }}</span>
        <span class="badge">Generated By: {{ $generatedBy }} ({{ $generatedAt }})</span>
    </div>

    @forelse($batchData as $bIndex => $item)
    @php
        $preset = $item['preset'];
        $matrix = $item['matrix'];
        $filteredShops = $item['filteredShops'];
        $productMeta = $item['productMeta'];
        $selectedWarehouse = $item['selectedWarehouse'];

        $slWidth = 4;
        $singleShop = $filteredShops->count() === 1;
        $itemWidth = $singleShop ? 52 : 18;
        $shopWidth = $filteredShops->count() > 0 ? ($singleShop ? 25 : 71 / $filteredShops->count()) : 0;
        $totalWidth = $singleShop ? 15 : 7;
        $formatQty = fn (float $qty): string => rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');

        $separateCategoryPages = $preset->separate_category_pages;
        $pageBreakCategoryIds = array_map('intval', (array) ($preset->page_break_category_ids ?? []));

        $categoryBlocks = [];
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

        $previousCatId = null;
        $isFirstCategory = true;
    @endphp

    <div class="preset-container {{ $bIndex > 0 ? 'has-preset-break' : '' }}">
        @foreach($categoryBlocks as $catName => $block)
        @php
            $catId = $block['id'];
            $catItems = $block['items'];

            $shouldBreak = ! $isFirstCategory && (
                $separateCategoryPages ||
                ($previousCatId !== null && in_array($previousCatId, $pageBreakCategoryIds, true))
            );
            $showPageHeading = $isFirstCategory || $shouldBreak;
            $isFirstCategory = false;
            $previousCatId = $catId;
        @endphp
        <div class="category-block {{ $shouldBreak ? 'has-page-break' : '' }}">
            @if($showPageHeading)
            <div class="page-heading">
                <div class="title">{{ $preset->name }} — Sort Sheet</div>
                <div class="meta">Date: {{ \Carbon\Carbon::parse($date)->format('d M Y') }} @if($selectedWarehouse) | Warehouse: {{ $selectedWarehouse->name }} @endif</div>
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
    @empty
    <p style="padding: 20px; text-align: center; color: #000; font-size: 12px;">
        No presets selected for batch printing.
    </p>
    @endforelse

</body>
</html>
