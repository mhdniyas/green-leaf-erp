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
            display: inline;
            font-family: monospace;
            font-size: 8px;
            font-weight: 600;
            line-height: 1;
            margin-left: 8px;
            white-space: nowrap;
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
        .shop-hidden {
            display: none !important;
        }

        .shops-filter-panel {
            background: #fff;
            border: 1px solid #000;
            border-radius: 8px;
            padding: 8px 12px;
            margin-top: 8px;
            width: 100%;
        }
        .shops-filter-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 6px;
        }
        .shops-filter-title {
            font-size: 10.5px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .shops-filter-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-filter-action {
            background: #fff;
            border: 1px solid #000;
            border-radius: 4px;
            padding: 2px 8px;
            font-size: 9.5px;
            font-weight: 700;
            cursor: pointer;
            color: #000;
        }
        .btn-filter-action:hover {
            background: #000;
            color: #fff;
        }
        .shops-checkbox-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            max-height: 140px;
            overflow-y: auto;
            padding: 2px 0;
        }
        .shop-filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #fff;
            border: 1px solid #000;
            border-radius: 6px;
            padding: 3px 8px;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
        }
        .shop-filter-chip:hover {
            background: #f1f5f9;
        }
        .shop-filter-chip input[type="checkbox"] {
            cursor: pointer;
        }
        .shop-chip-tag {
            font-family: monospace;
            font-size: 8.5px;
            font-weight: 900;
            background: #e2e8f0;
            color: #000;
            padding: 1px 4px;
            border-radius: 3px;
        }
        .warning-box {
            background: #fee2e2;
            border: 1px solid #ef4444;
            color: #b91c1c;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 700;
            margin-top: 6px;
            display: none;
        }
    </style>
</head>
<body>

    {{-- Top Controls Bar (hidden during printing) --}}
    <div class="no-print">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; width: 100%;">
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <button class="btn-print" id="btn-browser-print" onclick="triggerPrint()">
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
                <span class="badge" id="badge-shops-summary">Shops: <strong id="selected-shops-count">{{ $filteredShops->count() }}</strong>/{{ $filteredShops->count() }}</span>
                <span class="badge">Generated By: {{ $generatedBy }} ({{ $generatedAt }})</span>
            </div>
        </div>

        @if($filteredShops->count() > 0)
        {{-- Shops to Print Multi-Select Filter --}}
        <div class="shops-filter-panel">
            <div class="shops-filter-header">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span class="shops-filter-title">Shops to Print:</span>
                    <span style="font-size: 9.5px; color: #475569;">(Untick shops to exclude them from the printed output and recalculate totals)</span>
                </div>
                <div class="shops-filter-actions">
                    <button type="button" class="btn-filter-action" onclick="selectAllShops()">Select All</button>
                    <button type="button" class="btn-filter-action" onclick="clearAllShops()">Clear All</button>
                </div>
            </div>
            <div class="shops-checkbox-grid" id="shops-checkbox-container">
                @foreach($filteredShops as $shop)
                    <label class="shop-filter-chip" data-shop-id="{{ $shop->id }}">
                        <input type="checkbox" class="shop-checkbox" value="{{ $shop->id }}" checked onchange="toggleShop({{ $shop->id }}, this.checked)">
                        <span>{{ $shop->name }}</span>
                        @if($shop->warehouse_tag)
                            <span class="shop-chip-tag">{{ $shop->warehouse_tag }}</span>
                        @endif
                    </label>
                @endforeach
            </div>
            <div id="no-shops-warning" class="warning-box">
                Select at least one shop to print.
            </div>
        </div>
        @endif
    </div>

    <div id="no-shops-page-msg" style="display: none; padding: 40px 20px; text-align: center; font-weight: 800; font-size: 14px; color: #b91c1c; border: 2px dashed #ef4444; border-radius: 8px; margin-top: 10px;">
        Select at least one shop.
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

    <div class="sort-sheet-page" id="sort-sheet-print-content">
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
                <col class="col-sl" style="width:{{ $slWidth }}%">
                <col class="col-item" style="width:{{ $itemWidth }}%">
                @foreach($filteredShops as $shop)
                    <col class="col-shop" data-shop-id="{{ $shop->id }}" style="width:{{ $shopWidth }}%">
                @endforeach
                <col class="col-total" style="width:{{ $totalWidth }}%">
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
                    <th class="shop-th" data-shop-id="{{ $shop->id }}">
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
                    <td class="item-cell">
                        {{ $meta['name'] }}
                        <small class="item-sku">-- [ {{ $meta['sku'] }} ]</small>
                    </td>
                    @foreach($filteredShops as $shop)
                    @php $qty = $shopQtys[$shop->id] ?? 0; @endphp
                    <td class="qty-cell {{ $qty <= 0 ? 'zero' : '' }}" data-shop-id="{{ $shop->id }}" data-qty="{{ (float) $qty }}">
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

    <script>
        function triggerPrint() {
            const selectedCount = document.querySelectorAll('.shop-checkbox:checked').length;
            if (selectedCount === 0) {
                alert('Select at least one shop to print.');
                return;
            }
            window.print();
        }

        function toggleShop(shopId, isChecked) {
            const shopElements = document.querySelectorAll(`[data-shop-id="${shopId}"]`);
            shopElements.forEach(el => {
                if (isChecked) {
                    el.classList.remove('shop-hidden');
                } else {
                    el.classList.add('shop-hidden');
                }
            });

            recalculatePrintView();
        }

        function selectAllShops() {
            const checkboxes = document.querySelectorAll('.shop-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = true;
            });
            document.querySelectorAll('[data-shop-id]').forEach(el => el.classList.remove('shop-hidden'));
            recalculatePrintView();
        }

        function clearAllShops() {
            const checkboxes = document.querySelectorAll('.shop-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = false;
            });
            document.querySelectorAll('[data-shop-id]').forEach(el => el.classList.add('shop-hidden'));
            recalculatePrintView();
        }

        function recalculatePrintView() {
            const selectedCheckboxes = document.querySelectorAll('.shop-checkbox:checked');
            const selectedCount = selectedCheckboxes.length;

            const countEl = document.getElementById('selected-shops-count');
            if (countEl) countEl.textContent = selectedCount;

            const btnPrint = document.getElementById('btn-browser-print');
            const warningEl = document.getElementById('no-shops-warning');
            const noShopsPageMsg = document.getElementById('no-shops-page-msg');
            const printContent = document.getElementById('sort-sheet-print-content');

            if (selectedCount === 0) {
                if (btnPrint) {
                    btnPrint.disabled = true;
                    btnPrint.style.opacity = '0.5';
                    btnPrint.style.cursor = 'not-allowed';
                }
                if (warningEl) warningEl.style.display = 'block';
                if (noShopsPageMsg) noShopsPageMsg.style.display = 'block';
                if (printContent) printContent.style.display = 'none';
                return;
            }

            if (btnPrint) {
                btnPrint.disabled = false;
                btnPrint.style.opacity = '1';
                btnPrint.style.cursor = 'pointer';
            }
            if (warningEl) warningEl.style.display = 'none';
            if (noShopsPageMsg) noShopsPageMsg.style.display = 'none';
            if (printContent) printContent.style.display = 'block';

            const singleShop = selectedCount === 1;
            const shopWidth = singleShop ? 25 : (71 / selectedCount);
            const itemWidth = singleShop ? 52 : 18;
            const totalWidth = singleShop ? 15 : 7;

            document.querySelectorAll('.col-item').forEach(col => col.style.width = itemWidth + '%');
            document.querySelectorAll('.col-total').forEach(col => col.style.width = totalWidth + '%');
            document.querySelectorAll('.col-shop:not(.shop-hidden)').forEach(col => col.style.width = shopWidth + '%');

            document.querySelectorAll('.print-title-cell').forEach(th => {
                th.colSpan = selectedCount + 3;
            });

            document.querySelectorAll('tbody tr').forEach(row => {
                let rowTotal = 0;
                const visibleQtyCells = row.querySelectorAll('.qty-cell:not(.shop-hidden)');
                visibleQtyCells.forEach(cell => {
                    const qty = parseFloat(cell.getAttribute('data-qty') || 0);
                    if (!isNaN(qty)) rowTotal += qty;
                });

                const totalCell = row.querySelector('.total-cell');
                if (totalCell) {
                    const formatted = rowTotal === 0 ? '0' : (rowTotal % 1 === 0 ? rowTotal.toString() : parseFloat(rowTotal.toFixed(2)).toString());
                    totalCell.textContent = formatted;
                }
            });
        }
    </script>
</body>
</html>
