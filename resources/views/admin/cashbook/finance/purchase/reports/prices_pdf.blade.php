<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Purchase Price vs Selling Price — {{ \Carbon\Carbon::parse($filters['date'])->format('d M Y') }}</title>
    <style>
        @page {
            margin: 20px 25px;
            size: a4 portrait;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 10px;
            line-height: 1.3;
        }
        .header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .company-title {
            font-size: 14px;
            font-weight: bold;
            color: #047857;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .report-title {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
            text-transform: uppercase;
        }
        .meta-bar {
            margin-top: 6px;
            font-size: 9px;
            color: #475569;
        }
        .meta-item {
            display: inline-block;
            margin-right: 18px;
        }
        .meta-label {
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border-bottom: 1px solid #e2e8f0;
            padding: 5px 6px;
            text-align: left;
        }
        th {
            background-color: #f1f5f9;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #475569;
            letter-spacing: 0.03em;
        }
        .right {
            text-align: right;
        }
        .center {
            text-align: center;
        }
        .mono {
            font-family: DejaVu Sans, monospace;
        }
        .bold {
            font-weight: bold;
        }
        .positive {
            color: #047857;
        }
        .negative {
            color: #be123c;
        }
        .muted {
            color: #94a3b8;
        }
        .category-row td {
            background-color: #f8fafc;
            border-top: 1.5px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            font-weight: bold;
            font-size: 9.5px;
            color: #0f172a;
            text-transform: uppercase;
            padding: 6px;
        }
        .footer {
            margin-top: 15px;
            font-size: 8px;
            color: #94a3b8;
            text-align: right;
        }
    </style>
</head>
<body>
    @php
        $sortMode = (string) ($filters['sort'] ?? 'code');
        $isCategorySort = $sortMode === 'category';
    @endphp

    <div class="header">
        <div class="company-title">Green Leaf Vegetables &amp; Fruits</div>
        <div class="report-title">Purchase Price vs Selling Price</div>
        <div class="meta-bar">
            <span class="meta-item"><span class="meta-label">Date:</span> {{ \Carbon\Carbon::parse($filters['date'])->format('d M Y') }}</span>
            <span class="meta-item"><span class="meta-label">Produce:</span> {{ $produceName ?? 'All Produce' }}</span>
            <span class="meta-item"><span class="meta-label">Sort:</span> {{ $isCategorySort ? 'Category' : 'Code' }}</span>
            @if(!empty($filters['search']))
                <span class="meta-item"><span class="meta-label">Search:</span> "{{ $filters['search'] }}"</span>
            @endif
            <span class="meta-item" style="float: right;"><span class="meta-label">Generated:</span> {{ $generatedAt->format('d M Y H:i') }}</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 12%;">Code</th>
                <th style="width: {{ $isCategorySort ? '36%' : '26%' }};">Product</th>
                @if(!$isCategorySort)
                    <th style="width: 16%;">Category</th>
                @endif
                <th class="center" style="width: 8%;">Unit</th>
                <th class="right" style="width: 15%;">Purchase Price</th>
                <th class="right" style="width: 14%;">Selling Price</th>
                <th class="right" style="width: 12%;">Difference</th>
                <th class="right" style="width: 11%;">Margin %</th>
            </tr>
        </thead>
        <tbody>
            @if($rows->isEmpty())
                <tr>
                    <td colspan="{{ $isCategorySort ? 7 : 8 }}" class="center muted" style="padding: 24px;">
                        No price records found for the selected date and filters.
                    </td>
                </tr>
            @else
                @php
                    $groupedCollection = $isCategorySort ? $rows->groupBy('category_name') : collect(['all' => $rows]);
                @endphp

                @foreach($groupedCollection as $catName => $categoryRows)
                    @if($isCategorySort)
                        <tr class="category-row">
                            <td colspan="7">
                                {{ $catName ?: 'Uncategorized' }} ({{ count($categoryRows) }} items)
                            </td>
                        </tr>
                    @endif

                    @foreach($categoryRows as $row)
                        @php
                            $actualPrice = $row->actual_purchase_price !== null ? (float) $row->actual_purchase_price : null;
                            $sellingPrice = $row->selling_price !== null ? (float) $row->selling_price : null;
                            $diff = ($actualPrice !== null && $sellingPrice !== null) ? ($sellingPrice - $actualPrice) : null;
                            $margin = ($diff !== null && $actualPrice > 0) ? ($diff / $actualPrice * 100) : null;
                        @endphp
                        <tr>
                            <!-- Code -->
                            <td class="mono bold">{{ $row->product_sku ?: '—' }}</td>

                            <!-- Product -->
                            <td class="bold">{{ $row->product_name }}</td>

                            <!-- Category (when not grouped) -->
                            @if(!$isCategorySort)
                                <td>{{ $row->category_name ?: '—' }}</td>
                            @endif

                            <!-- Unit -->
                            <td class="center uppercase mono">{{ strtoupper($row->price_unit ?: $row->product_unit) }}</td>

                            <!-- Purchase Price (Actual) -->
                            <td class="right mono bold">
                                {{ $actualPrice !== null ? '₹'.number_format($actualPrice, 2) : '—' }}
                            </td>

                            <!-- Selling Price -->
                            <td class="right mono bold">
                                {{ $sellingPrice !== null ? '₹'.number_format($sellingPrice, 2) : '—' }}
                            </td>

                            <!-- Difference -->
                            <td class="right mono bold {{ $diff !== null ? ($diff < 0 ? 'negative' : 'positive') : 'muted' }}">
                                @if($diff !== null)
                                    {{ $diff >= 0 ? '+' : '' }}₹{{ number_format($diff, 2) }}
                                @else
                                    —
                                @endif
                            </td>

                            <!-- Margin % -->
                            <td class="right mono bold {{ $margin !== null ? ($margin < 0 ? 'negative' : 'positive') : 'muted' }}">
                                {{ $margin !== null ? number_format($margin, 2).'%' : '—' }}
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            @endif
        </tbody>
    </table>

    <div class="footer">
        Page 1 • Confidential • Green Leaf ERP System
    </div>
</body>
</html>
