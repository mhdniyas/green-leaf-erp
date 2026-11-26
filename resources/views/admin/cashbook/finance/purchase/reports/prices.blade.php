@extends('admin.cashbook.layouts.app')

@section('title', 'Price Report — Cashbook')

@section('header_title')
    <i data-lucide="badge-indian-rupee" class="h-5 w-5 text-emerald-600"></i> Price Report
@endsection

@section('header_subtitle')
    Compare actual quantity-weighted purchase price against authoritative selling prices.
@endsection

@section('content')
    @php
        $sortMode = (string) ($filters['sort'] ?? 'code');
        $isCategorySort = $sortMode === 'category';
        $exportQuery = request()->query();
        $startDate = (string) ($filters['start_date'] ?? $filters['date']);
        $endDate = (string) ($filters['end_date'] ?? $filters['date']);
        $periodLabel = $startDate === $endDate
            ? \Carbon\Carbon::parse($startDate)->format('d M Y')
            : \Carbon\Carbon::parse($startDate)->format('d M Y').' – '.\Carbon\Carbon::parse($endDate)->format('d M Y');
        $colCount = $isCategorySort ? 13 : 14;
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        <!-- Header & Action Controls -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900">Purchase Price vs Selling Price</h1>
                <p class="text-xs font-bold text-slate-500 mt-0.5">
                    Grade-wise actual purchase price, last available price &amp; selling price comparison for {{ $periodLabel }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.cashbook.finance.purchase.reports.prices.export.excel', $exportQuery) }}"
                   download
                   class="inline-flex h-10 items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-black text-emerald-800 shadow-xs transition-all hover:bg-emerald-100 cursor-pointer"
                   title="Export Excel (.xlsx)">
                    <i data-lucide="file-spreadsheet" class="h-4 w-4 text-emerald-700"></i>
                    <span>Export Excel (.xlsx)</span>
                </a>
                <a href="{{ route('admin.cashbook.finance.purchase.reports.prices.export.csv', $exportQuery) }}"
                   download
                   class="inline-flex h-10 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-800 shadow-xs transition-all hover:bg-slate-50 cursor-pointer"
                   title="Export CSV (.csv)">
                    <i data-lucide="file-down" class="h-4 w-4 text-slate-600"></i>
                    <span>Export CSV (.csv)</span>
                </a>
                <a href="{{ route('admin.cashbook.finance.purchase.reports.prices.export.pdf', $exportQuery) }}"
                   download
                   class="inline-flex h-10 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-800 shadow-xs transition-all hover:bg-slate-50 cursor-pointer"
                   title="Download A4 PDF Report">
                    <i data-lucide="file-text" class="h-4 w-4 text-rose-600"></i>
                    <span>Download PDF</span>
                </a>
            </div>
        </div>

        @include('admin.cashbook.finance.purchase.reports._price-filters', ['filterRoute' => 'admin.cashbook.finance.purchase.reports.prices'])

        <div class="rounded-2xl border border-blue-200 bg-blue-50/60 px-4 py-2.5 text-xs font-semibold text-blue-900 flex items-center justify-between gap-2">
            <div>
                <span class="font-black">Current Price (Actual Purchase)</span> is quantity-weighted per product &amp; grade within the selected period.
                <span class="font-black">Last Price</span> shows the most recent valid purchase before the selected period when no current purchase exists.
            </div>
            <span class="text-[10px] font-black uppercase tracking-wider text-blue-700 bg-blue-100/80 px-2 py-0.5 rounded-lg flex-shrink-0">
                Sort: {{ $isCategorySort ? 'Category' : 'Code' }}
            </span>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[72rem] text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="p-3.5">Code</th>
                            <th class="p-3.5">Product</th>
                            @if(!$isCategorySort)
                                <th class="p-3.5">Category</th>
                            @endif
                            <th class="p-3.5">Grade</th>
                            <th class="p-3.5">Unit</th>
                            <th class="p-3.5 text-right">Purchase Qty</th>
                            <th class="p-3.5 text-right">Approved Purchase</th>
                            <th class="p-3.5 text-right">Current Price <span class="block text-[9px] font-bold text-slate-400 normal-case">Actual Purchase</span></th>
                            <th class="p-3.5 text-right">Last Price</th>
                            <th class="p-3.5">Last Purchase Date</th>
                            <th class="p-3.5 text-right">Selling Price</th>
                            <th class="p-3.5 text-right">Difference</th>
                            <th class="p-3.5 text-right">Margin %</th>
                            <th class="p-3.5">Price Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @if($rows->isEmpty())
                            <tr>
                                <td colspan="{{ $colCount }}" class="p-12 text-center text-slate-400">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="inbox" class="w-6 h-6"></i>
                                    </div>
                                    <p class="font-bold text-slate-600">
                                        @if(!empty($filters['search']))
                                            No products match your search "{{ $filters['search'] }}".
                                        @else
                                            No price records found for {{ $periodLabel }}.
                                        @endif
                                    </p>
                                </td>
                            </tr>
                        @else
                            @php
                                $groupedCollection = $isCategorySort ? $rows->groupBy('category_name') : collect(['all' => $rows]);
                            @endphp

                            @foreach($groupedCollection as $catName => $categoryRows)
                                @if($isCategorySort)
                                    <tr class="bg-slate-100/80 border-t-2 border-b border-slate-200">
                                        <td colspan="{{ $colCount }}" class="px-3.5 py-2 font-black text-xs uppercase tracking-wider text-slate-800">
                                            <div class="flex items-center justify-between">
                                                <span>{{ $catName ?: 'Uncategorized' }}</span>
                                                <span class="text-[10px] font-bold text-slate-500 font-mono">{{ count($categoryRows) }} item(s)</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endif

                                @foreach($categoryRows as $row)
                                    @php
                                        $approvedPrice = $row->approved_purchase_price !== null ? (float) $row->approved_purchase_price : null;
                                        $actualPrice = $row->actual_purchase_price !== null ? (float) $row->actual_purchase_price : null;
                                        $lastPrice = $row->last_purchase_price !== null ? (float) $row->last_purchase_price : null;
                                        $purchaseQty = $row->purchase_qty !== null ? (float) $row->purchase_qty : null;
                                        $sellingPrice = $row->selling_price !== null ? (float) $row->selling_price : null;
                                        $diff = ($actualPrice !== null && $sellingPrice !== null) ? ($sellingPrice - $actualPrice) : ($approvedPrice !== null && $sellingPrice !== null ? ($sellingPrice - $approvedPrice) : null);
                                        $baseCost = $actualPrice ?? $approvedPrice;
                                        $margin = ($diff !== null && $baseCost > 0) ? ($diff / $baseCost * 100) : null;
                                        $statusClass = match ($row->price_status ?? 'unavailable') {
                                            'current' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                                            'historical' => 'border-amber-200 bg-amber-50 text-amber-800',
                                            default => 'border-slate-200 bg-slate-100 text-slate-600',
                                        };
                                    @endphp
                                    <tr class="transition-colors {{ ($row->price_status ?? 'unavailable') === 'historical' ? 'bg-amber-50/30 hover:bg-amber-50/60' : 'hover:bg-slate-50/80' }}">
                                        <!-- Code -->
                                        <td class="p-3.5 font-mono text-xs font-black text-slate-700 whitespace-nowrap">
                                            @if(!empty($row->product_sku))
                                                <span class="rounded-lg bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-700 border border-slate-200">
                                                    {{ $row->product_sku }}
                                                </span>
                                            @else
                                                <span class="text-slate-400 font-mono">—</span>
                                            @endif
                                        </td>

                                        <!-- Product -->
                                        <td class="p-3.5 font-black text-slate-900 whitespace-nowrap">
                                            <a href="{{ route('admin.cashbook.finance.purchase.reports.prices.product', array_merge(request()->query(), ['product' => $row->product_id])) }}"
                                               class="text-emerald-700 hover:text-emerald-900 hover:underline">
                                                {{ $row->product_name }}
                                            </a>
                                        </td>

                                        <!-- Category (when not in category sort mode) -->
                                        @if(!$isCategorySort)
                                            <td class="p-3.5 text-slate-600 font-medium whitespace-nowrap">
                                                {{ $row->category_name ?: '—' }}
                                            </td>
                                        @endif

                                        <!-- Grade -->
                                        <td class="p-3.5 whitespace-nowrap">
                                            <span class="inline-flex items-center rounded-lg border px-2 py-0.5 text-[11px] font-black {{ \App\Enums\Inventory\ProductGrade::invoiceBadgeClass($row->grade ?? 'A') }}">
                                                {{ $row->grade ?? 'A' }}
                                            </span>
                                        </td>

                                        <!-- Unit -->
                                        <td class="p-3.5 font-extrabold text-slate-500 uppercase font-mono">
                                            {{ strtoupper($row->price_unit ?: $row->product_unit) }}
                                        </td>

                                        <!-- Purchase Qty -->
                                        <td class="p-3.5 text-right font-mono font-bold text-slate-700 whitespace-nowrap">
                                            {{ $purchaseQty !== null ? number_format($purchaseQty, 3) : '—' }}
                                        </td>

                                        <!-- Approved Purchase Price -->
                                        <td class="p-3.5 text-right font-mono font-bold text-slate-800 whitespace-nowrap">
                                            {{ $approvedPrice !== null ? '₹'.number_format($approvedPrice, 2) : '—' }}
                                        </td>

                                        <!-- Current Price (Actual Purchase) -->
                                        <td class="p-3.5 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                                            {{ $actualPrice !== null ? '₹'.number_format($actualPrice, 2) : '—' }}
                                        </td>

                                        <!-- Last Price -->
                                        <td class="p-3.5 text-right font-mono font-bold whitespace-nowrap {{ $lastPrice !== null ? 'text-amber-800' : 'text-slate-400' }}">
                                            @if($lastPrice !== null)
                                                ₹{{ number_format($lastPrice, 2) }}
                                            @elseif($actualPrice === null)
                                                <span class="font-sans text-[11px] font-semibold">No Price Available</span>
                                            @else
                                                —
                                            @endif
                                        </td>

                                        <!-- Last Purchase Date -->
                                        <td class="p-3.5 font-mono text-xs text-slate-600 whitespace-nowrap">
                                            @if(!empty($row->last_purchase_date))
                                                <span>{{ \Carbon\Carbon::parse($row->last_purchase_date)->format('d M Y') }}</span>
                                                @if(isset($row->last_purchase_age_days) && $row->last_purchase_age_days > 0)
                                                    <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">{{ $row->last_purchase_age_days }}d ago</span>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>

                                        <!-- Selling Price -->
                                        <td class="p-3.5 text-right font-mono font-black text-slate-900 whitespace-nowrap">
                                            {{ $sellingPrice !== null ? '₹'.number_format($sellingPrice, 2) : '—' }}
                                        </td>

                                        <!-- Difference -->
                                        <td class="p-3.5 text-right font-mono font-black whitespace-nowrap {{ $diff !== null ? ($diff < 0 ? 'text-rose-700' : 'text-emerald-700') : 'text-slate-400' }}">
                                            @if($diff !== null)
                                                {{ $diff >= 0 ? '+' : '' }}₹{{ number_format($diff, 2) }}
                                            @else
                                                —
                                            @endif
                                        </td>

                                        <!-- Margin % -->
                                        <td class="p-3.5 text-right font-mono font-extrabold whitespace-nowrap {{ $margin !== null ? ($margin < 0 ? 'text-rose-700' : 'text-emerald-700') : 'text-slate-400' }}">
                                            {{ $margin !== null ? number_format($margin, 2).'%' : '—' }}
                                        </td>

                                        <!-- Price Status -->
                                        <td class="p-3.5 whitespace-nowrap">
                                            <span class="inline-flex items-center rounded-lg border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider {{ $statusClass }}">
                                                {{ $row->price_status_label ?? 'Not Available' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>

            @if($rows instanceof \Illuminate\Pagination\LengthAwarePaginator && $rows->hasPages())
                <div class="border-t border-slate-100 p-4 bg-slate-50/50">
                    {{ $rows->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
