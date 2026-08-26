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
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        <!-- Header & Action Controls -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900">Purchase Price vs Selling Price</h1>
                <p class="text-xs font-bold text-slate-500 mt-0.5">
                    Actual purchase price vs authoritative selling price comparison on {{ \Carbon\Carbon::parse($filters['date'])->format('d M Y') }}
                </p>
            </div>

            <div class="flex items-center gap-2">
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
                <span class="font-black">Actual Purchase Price</span> is quantity-weighted from invoiced purchaser cart items for the selected business date.
                <span class="font-black">Selling Price</span> is the active baseline selling price.
            </div>
            <span class="text-[10px] font-black uppercase tracking-wider text-blue-700 bg-blue-100/80 px-2 py-0.5 rounded-lg flex-shrink-0">
                Sort: {{ $isCategorySort ? 'Category' : 'Code' }}
            </span>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[56rem] text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-500 border-b border-slate-100">
                        <tr>
                            <th class="p-3.5">Code</th>
                            <th class="p-3.5">Product</th>
                            @if(!$isCategorySort)
                                <th class="p-3.5">Category</th>
                            @endif
                            <th class="p-3.5">Unit</th>
                            <th class="p-3.5 text-right">Actual Purchase Price</th>
                            <th class="p-3.5 text-right">Selling Price</th>
                            <th class="p-3.5 text-right">Difference</th>
                            <th class="p-3.5 text-right">Margin %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @if($rows->isEmpty())
                            <tr>
                                <td colspan="{{ $isCategorySort ? 7 : 8 }}" class="p-12 text-center text-slate-400">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="inbox" class="w-6 h-6"></i>
                                    </div>
                                    <p class="font-bold text-slate-600">
                                        @if(!empty($filters['search']))
                                            No products match your search "{{ $filters['search'] }}".
                                        @else
                                            No price records found for {{ \Carbon\Carbon::parse($filters['date'])->format('d M Y') }}.
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
                                        <td colspan="7" class="px-3.5 py-2 font-black text-xs uppercase tracking-wider text-slate-800">
                                            <div class="flex items-center justify-between">
                                                <span>{{ $catName ?: 'Uncategorized' }}</span>
                                                <span class="text-[10px] font-bold text-slate-500 font-mono">{{ count($categoryRows) }} item(s)</span>
                                            </div>
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
                                    <tr class="hover:bg-slate-50/80 transition-colors">
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

                                        <!-- Unit -->
                                        <td class="p-3.5 font-extrabold text-slate-500 uppercase font-mono">
                                            {{ strtoupper($row->price_unit ?: $row->product_unit) }}
                                        </td>

                                        <!-- Actual Purchase Price -->
                                        <td class="p-3.5 text-right font-mono font-bold text-slate-800 whitespace-nowrap">
                                            {{ $actualPrice !== null ? '₹'.number_format($actualPrice, 2) : '—' }}
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
