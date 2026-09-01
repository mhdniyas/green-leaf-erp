@extends('admin.cashbook.layouts.app')

@section('title', 'GL Bills & Daily Deliveries — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="receipt" class="w-5 h-5 text-emerald-600"></i> GL Bills &amp; Deliveries
@endsection

@section('header_subtitle')
    Daily shop invoice deliveries &amp; GL procurement bills.
@endsection

@section('content')
    <div x-data="{ activeInvoiceId: null, loading: false }" class="mx-auto max-w-4xl space-y-4">
        <!-- Top Fintech Header -->
        <div class="flex items-center justify-between pt-1">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">GL Bills</h1>
                <p class="text-xs font-bold text-slate-500 mt-0.5">Daily Shop Invoices <span class="text-slate-400 font-medium">&amp; Delivery Records</span></p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @php
                    $exportQuery = [
                        'timeframe' => $timeframe,
                        'shop_id' => $selectedShopId,
                        'product_filter' => $selectedProductFilterUuid,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ];
                @endphp

                <a href="{{ route('admin.cashbook.reports.gl-bills.export.csv', $exportQuery) }}" download class="inline-flex h-10 items-center gap-2 rounded-2xl border border-emerald-200 bg-white px-3.5 text-xs font-black text-emerald-700 shadow-xs transition-all hover:bg-emerald-50" title="{{ $selectedShopId ? 'Export selected outlet as CSV' : 'Export all outlets as CSV' }}">
                    <i data-lucide="file-spreadsheet" class="h-4 w-4"></i>
                    <span>CSV</span>
                </a>
                <a href="{{ route('admin.cashbook.reports.gl-bills.export.pdf', $exportQuery + ['download' => 1]) }}" download class="inline-flex h-10 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3.5 text-xs font-black text-slate-800 shadow-xs transition-all hover:bg-slate-50" title="{{ $selectedShopId ? 'Print selected outlet as PDF' : 'Print all outlets as PDF' }}">
                    <i data-lucide="file-text" class="h-4 w-4"></i>
                    <span>PDF</span>
                </a>

                <!-- Product Filter Dropdown -->
                <div x-data="{ open: false }" class="relative inline-block text-left">
                    <button @click="open = !open" type="button" class="inline-flex items-center justify-between gap-2 h-10 px-3.5 rounded-2xl bg-white border border-slate-200/90 text-xs font-black text-slate-800 shadow-xs hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all cursor-pointer">
                        <div class="flex items-center gap-2">
                            <i data-lucide="filter" class="w-4 h-4 text-emerald-600"></i>
                            <span class="max-w-[130px] truncate">{{ $selectedProductFilter ? $selectedProductFilter->name : 'All Products' }}</span>
                        </div>
                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                    </button>

                    <div x-show="open"
                         @click.away="open = false"
                         x-cloak
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-64 origin-top-right rounded-2xl bg-white p-1.5 shadow-xl ring-1 ring-black/5 z-50 divide-y divide-slate-100 max-h-72 overflow-y-auto custom-scrollbar">

                        <!-- Default option: All Products -->
                        <a href="{{ route('admin.cashbook.reports.gl-bills', ['timeframe' => $timeframe, 'shop_id' => $selectedShopId, 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                           @click="loading = true"
                           class="group flex items-center justify-between rounded-xl px-3 py-2 text-xs font-bold transition-all {{ !$selectedProductFilter ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                            <span>All Products</span>
                            <span class="text-[9px] opacity-70">Clear</span>
                        </a>

                        <!-- Filter Options -->
                        @if($productFilters->isNotEmpty())
                            <div class="py-1 space-y-0.5">
                                @foreach ($productFilters as $pf)
                                    <a href="{{ route('admin.cashbook.reports.gl-bills', ['timeframe' => $timeframe, 'shop_id' => $selectedShopId, 'product_filter' => $pf->uuid, 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                                       @click="loading = true"
                                       class="group flex items-center justify-between rounded-xl px-3 py-2 text-xs font-bold transition-all {{ $selectedProductFilterUuid === $pf->uuid ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                                        <span class="truncate">{{ $pf->name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        <!-- Manage Filters link -->
                        <div class="pt-1">
                            <a href="{{ route('admin.cashbook.finance.purchase.product-filters.index') }}"
                               class="flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50 transition-all">
                                <i data-lucide="settings" class="w-3.5 h-3.5"></i>
                                <span>Manage Product Filters</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Custom Tailwind Shop Selector Dropdown -->
                <div x-data="{ open: false }" class="relative inline-block text-left">
                    <button @click="open = !open" type="button" class="inline-flex items-center justify-between gap-2 h-10 px-3.5 rounded-2xl bg-white border border-slate-200/90 text-xs font-black text-slate-800 shadow-xs hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-900/10 transition-all cursor-pointer">
                        <div class="flex items-center gap-2">
                            <i data-lucide="store" class="w-4 h-4 text-emerald-600"></i>
                            <span class="max-w-[130px] truncate">{{ $selectedShop ? ($selectedShop->name ?: 'Shop #'.($selectedShop->shop_id ?? $selectedShop->id)) : 'All Outlets' }}</span>
                        </div>
                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                    </button>

                    <div x-show="open"
                         @click.away="open = false"
                         x-cloak
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-64 origin-top-right rounded-2xl bg-white p-1.5 shadow-xl ring-1 ring-black/5 z-50 divide-y divide-slate-100 max-h-72 overflow-y-auto custom-scrollbar">

                        <!-- Default option: Clear selection / All Outlets -->
                        <a href="{{ route('admin.cashbook.reports.gl-bills', ['timeframe' => $timeframe, 'product_filter' => $selectedProductFilterUuid, 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                           @click="loading = true"
                           class="group flex items-center justify-between rounded-xl px-3 py-2 text-xs font-bold transition-all {{ !$selectedShopId ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                            <span>All Outlets</span>
                            <span class="text-[9px] opacity-70">Clear</span>
                        </a>

                        <!-- Shop Options -->
                        <div class="py-1 space-y-0.5">
                            @foreach ($shops as $s)
                                @php
                                    $sId = (int) ($s->shop_id ?? $s->id);
                                @endphp
                                <a href="{{ route('admin.cashbook.reports.gl-bills', ['timeframe' => $timeframe, 'shop_id' => $sId, 'product_filter' => $selectedProductFilterUuid, 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                                   @click="loading = true"
                                   class="group flex items-center justify-between rounded-xl px-3 py-2 text-xs font-bold transition-all {{ $selectedShopId === $sId ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                                    <span class="truncate">{{ $s->name ?: ('Shop #' . $sId) }}</span>
                                    <span class="ml-2 rounded-md px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wide {{ $selectedShopId === $sId ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-500' }}">
                                        {{ $s->code }}
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Segmented iOS Timeframe Bar (Today, Week, Month + Calendar Jump) -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
            <div class="inline-flex items-center max-w-full overflow-x-auto rounded-2xl bg-slate-200/70 p-1 shrink-0 gap-0.5">
                <a href="{{ route('admin.cashbook.reports.gl-bills', ['timeframe' => 'today', 'shop_id' => $selectedShopId, 'product_filter' => $selectedProductFilterUuid]) }}" @click="loading = true" class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all {{ $timeframe === 'today' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">Today</a>
                <a href="{{ route('admin.cashbook.reports.gl-bills', ['timeframe' => 'weekly', 'shop_id' => $selectedShopId, 'product_filter' => $selectedProductFilterUuid]) }}" @click="loading = true" class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all {{ $timeframe === 'weekly' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">Week</a>
                <a href="{{ route('admin.cashbook.reports.gl-bills', ['timeframe' => 'monthly', 'shop_id' => $selectedShopId, 'product_filter' => $selectedProductFilterUuid]) }}" @click="loading = true" class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all {{ $timeframe === 'monthly' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">Month</a>
                @php
                    $prevDate = now()->startOfMonth()->subDay();
                    $prevStart = $prevDate->copy()->startOfMonth()->toDateString();
                    $prevEnd = $prevDate->copy()->endOfMonth()->toDateString();
                    $prevName = $prevDate->format('M');
                    $isPrevSelected = $timeframe === 'custom' && $startDate === $prevStart && $endDate === $prevEnd;
                @endphp
                <a href="{{ route('admin.cashbook.reports.gl-bills', ['timeframe' => 'custom', 'shop_id' => $selectedShopId, 'product_filter' => $selectedProductFilterUuid, 'start_date' => $prevStart, 'end_date' => $prevEnd]) }}" @click="loading = true" class="rounded-xl px-3 py-1 text-xs font-extrabold transition-all {{ $isPrevSelected ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">{{ $prevName }}</a>
                <!-- Jump to Date Calendar Picker -->
                <label class="relative flex items-center justify-center cursor-pointer rounded-xl px-2 py-1 text-slate-600 hover:text-slate-900 hover:bg-white/60 transition-all" title="Jump to Specific Date">
                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                    <input
                        type="date"
                        class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                        onchange="window.location.href='{{ route('admin.cashbook.reports.gl-bills', ['timeframe' => 'custom', 'shop_id' => $selectedShopId, 'product_filter' => $selectedProductFilterUuid]) }}&start_date=' + this.value + '&end_date=' + this.value"
                    >
                </label>
            </div>

            <!-- Custom Filter Form -->
            <form method="GET" action="{{ route('admin.cashbook.reports.gl-bills') }}" @submit="loading = true" class="flex flex-wrap sm:flex-nowrap items-center gap-1.5">
                <input type="hidden" name="timeframe" value="custom">
                <input type="hidden" name="shop_id" value="{{ $selectedShopId }}">
                <input type="hidden" name="product_filter" value="{{ $selectedProductFilterUuid }}">
                <x-cashbook.previous-month-button mode="range" size="xs" timeframe="custom" label="{{ $prevName }}" />
                <input type="date" name="start_date" value="{{ $startDate }}" class="h-8 flex-1 min-w-[115px] rounded-xl border border-slate-200 bg-white px-2 text-xs font-bold text-slate-900 shadow-xs">
                <span class="text-xs font-bold text-slate-400">to</span>
                <input type="date" name="end_date" value="{{ $endDate }}" class="h-8 flex-1 min-w-[115px] rounded-xl border border-slate-200 bg-white px-2 text-xs font-bold text-slate-900 shadow-xs">
                <button type="submit" class="h-8 shrink-0 rounded-xl bg-slate-900 px-3.5 text-xs font-bold text-white hover:bg-slate-800">Apply</button>
            </form>
        </div>

        <!-- 3 Summary Metric Cards (1 Row on All Devices) -->
        <div class="grid grid-cols-3 gap-1.5 sm:gap-3">
            <div class="rounded-2xl border border-slate-100 bg-white p-2.5 sm:p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)]">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[8px] sm:text-[10px] font-black uppercase tracking-wider text-slate-400 truncate">Total Billed</span>
                    <div class="flex h-5 w-5 sm:h-7 sm:w-7 items-center justify-center rounded-lg bg-amber-50 text-amber-600 shrink-0">
                        <i data-lucide="receipt" class="w-3 h-3 sm:w-4 sm:h-4"></i>
                    </div>
                </div>
                <p class="mt-1 sm:mt-2 text-xs sm:text-xl font-black text-slate-900 truncate">₹{{ number_format($totals['total_billed'], 2) }}</p>
                <span class="mt-0.5 block text-[8px] sm:text-[10px] font-bold text-slate-400 truncate">{{ $totals['count'] }} invoices</span>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white p-2.5 sm:p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)]">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[8px] sm:text-[10px] font-black uppercase tracking-wider text-slate-400 truncate">Paid Amount</span>
                    <div class="flex h-5 w-5 sm:h-7 sm:w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 shrink-0">
                        <i data-lucide="check-circle-2" class="w-3 h-3 sm:w-4 sm:h-4"></i>
                    </div>
                </div>
                <p class="mt-1 sm:mt-2 text-xs sm:text-xl font-black text-emerald-700 truncate">₹{{ number_format($totals['total_paid'], 2) }}</p>
                <span class="mt-0.5 block text-[8px] sm:text-[10px] font-bold text-emerald-600 truncate">Settled</span>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white p-2.5 sm:p-4 shadow-[0_4px_20px_rgba(0,0,0,0.02)]">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[8px] sm:text-[10px] font-black uppercase tracking-wider text-slate-400 truncate">Balance Due</span>
                    <div class="flex h-5 w-5 sm:h-7 sm:w-7 items-center justify-center rounded-lg bg-rose-50 text-rose-600 shrink-0">
                        <i data-lucide="alert-circle" class="w-3 h-3 sm:w-4 sm:h-4"></i>
                    </div>
                </div>
                <p class="mt-1 sm:mt-2 text-xs sm:text-xl font-black text-rose-700 truncate">₹{{ number_format($totals['total_balance'], 2) }}</p>
                <span class="mt-0.5 block text-[8px] sm:text-[10px] font-bold text-rose-600 truncate">Payable</span>
            </div>
        </div>

        <!-- GL Bills Data Table Card (Clean 3-Column List: Date & Ref, Shop Outlet, Bill Total) -->
        <div class="rounded-[28px] border border-slate-100 bg-white p-4 sm:p-5 shadow-[0_8px_30px_rgba(0,0,0,0.04)] space-y-4">
            <div class="flex items-center justify-between px-1">
                <div>
                    <h3 class="text-sm font-black text-slate-900">Daily Invoices &amp; Bills Table</h3>
                    <p class="text-[10px] font-semibold text-slate-400">
                        {{ $selectedShop ? 'Viewing bills for '.$selectedShop->name : 'Viewing bills for All Outlets' }}
                    </p>
                </div>
                @if ($invoices instanceof \Illuminate\Pagination\LengthAwarePaginator && $invoices->total() > 0)
                    <span class="text-[10px] font-bold text-slate-400">Page {{ $invoices->currentPage() }} of {{ $invoices->lastPage() }}</span>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-[9px] font-black uppercase tracking-wider text-slate-400">
                            <th class="py-2.5 px-3">Date &amp; Ref</th>
                            <th class="py-2.5 px-3">Shop Outlet</th>
                            <th class="py-2.5 px-3 text-right">Bill Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-800">
                        @forelse ($invoices as $inv)
                            <tr @click="activeInvoiceId = {{ $inv->id }}" class="hover:bg-slate-50/80 transition cursor-pointer group">
                                <td class="py-3 px-3">
                                    <span class="font-black text-slate-900 block group-hover:text-indigo-600 transition">{{ optional($inv->business_date)->format('d M Y') }}</span>
                                    <span class="text-[10px] font-bold text-slate-400 block truncate max-w-[140px] sm:max-w-[200px]">{{ $inv->invoice_number }}</span>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="font-black text-slate-900 block">{{ $inv->shop?->name ?: ('Shop #' . $inv->shop_id) }}</span>
                                    <span class="text-[10px] font-bold text-slate-400 block uppercase">{{ $inv->shop?->code }}</span>
                                </td>
                                <td class="py-3 px-3 text-right">
                                    @if ($inv->filtered_display_total !== null)
                                        <span class="font-black text-slate-900 block text-xs sm:text-sm">₹{{ number_format($inv->filtered_display_total, 2) }}</span>
                                        <span class="text-[9px] font-bold text-slate-400 block">of ₹{{ number_format((float) $inv->final_total, 2) }}</span>
                                    @else
                                        <span class="font-black text-slate-900 block text-xs sm:text-sm">₹{{ number_format((float) $inv->final_total, 2) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-8 text-center text-xs font-bold text-slate-400">
                                    @if ($selectedProductFilter)
                                        No GL invoices matching product filter "{{ $selectedProductFilter->name }}" found for {{ $selectedShop?->name ?? 'all outlets' }} in the selected period.
                                    @else
                                        No GL invoices or delivery bills found for {{ $selectedShop?->name ?? 'all outlets' }} in the selected period.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            @if ($invoices instanceof \Illuminate\Pagination\LengthAwarePaginator && $invoices->hasPages())
                <div class="pt-2">
                    {{ $invoices->links() }}
                </div>
            @endif
        </div>

        <!-- Shop Incharge Style Invoice Receipt Modals -->
        @foreach ($invoices as $inv)
            <div
                x-show="activeInvoiceId === {{ $inv->id }}"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-slate-900/60 backdrop-blur-sm overflow-y-auto"
                @click.self="activeInvoiceId = null"
            >
                <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl p-4 sm:p-6 max-h-[90vh] overflow-y-auto custom-scrollbar">
                    <button @click="activeInvoiceId = null" class="absolute top-3 right-3 h-8 w-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center font-bold text-sm z-20">✕</button>
                    @include('shop-owner.finance.partials.invoice-card', [
                        'invoice'               => $inv,
                        'filteredItems'         => $selectedProductFilter ? $inv->items : null,
                        'filteredDisplayTotal'  => $inv->filtered_display_total,
                        'selectedProductFilter' => $selectedProductFilter,
                    ])
                </div>
            </div>
        @endforeach
    </div>
@endsection
