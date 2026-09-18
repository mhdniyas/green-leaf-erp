@extends('admin.cashbook.layouts.app')

@php
    $selectedProductFilter = $filters['product_filter'] ?? null;
    $activePurchaseTab = 'overview';
    $periodMode = $filters['period_mode'] ?? 'month';
    $month = $filters['month'] ?? today('Asia/Kolkata')->format('Y-m');
    $monthStart = $filters['month_start'] ?? \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
    $monthEnd = $filters['month_end'] ?? \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();
    $prevMonth = $filters['prev_month'] ?? \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->subMonth()->format('Y-m');
    $nextMonth = $filters['next_month'] ?? \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->addMonth()->format('Y-m');
    $monthTitle = $filters['month_title'] ?? \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y');
    $periodStart = $filters['start_date'] ?? $monthStart;
    $periodEnd = $filters['end_date'] ?? $monthEnd;
    $formattedRange = $filters['formatted_range'] ?? ($periodStart === $periodEnd ? \Illuminate\Support\Carbon::parse($periodStart)->format('d M Y') : \Illuminate\Support\Carbon::parse($periodStart)->format('d M Y').' – '.\Illuminate\Support\Carbon::parse($periodEnd)->format('d M Y'));

    $purchaseContext = ['period' => $filters['period']];
    if ($selectedProductFilter) {
        $purchaseContext['product_filter'] = $selectedProductFilter;
    }
    if (in_array($filters['period'], ['custom', 'between', 'range'], true)) {
        $purchaseContext += ['start_date' => $periodStart, 'end_date' => $periodEnd];
    }
    if (request()->filled('period_mode') || (request()->filled('month') && !request()->filled('period'))) {
        $purchaseContext['month'] = $month;
        $purchaseContext['period_mode'] = $periodMode;
        if ($periodMode === 'day') {
            $purchaseContext['date'] = $periodStart;
        } elseif ($periodMode === 'custom') {
            $purchaseContext += ['from' => $periodStart, 'to' => $periodEnd];
        }
    }

    $summary = $dashboard['summary'];
    $totalPaid = (float) $summary->cash_purchase + (float) $summary->credit_paid;
@endphp

@section('title', 'Purchase Finance — '.$monthTitle)
@section('header_title')
    <i data-lucide="shopping-basket" class="h-5 w-5 text-emerald-600"></i> Purchase Finance
@endsection

@section('header_subtitle')
    Procurement Action Center &bull; Monthly Financial Execution &amp; Ledger Summary
@endsection

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-16">
    <!-- TOP NAVIGATION & SUBSECTIONS -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        @include('admin.cashbook.finance.purchase._nav')
        @include('admin.cashbook.finance.purchase._dashboard-tabs')
    </div>

    <!-- HEADER / FILTERS (SHOP CASHBOOK STYLE) -->
    <header class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-5" aria-label="Purchase Finance Header and Period Navigation">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-950 tracking-tight uppercase flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-emerald-600 text-white shadow-xs">
                            <i data-lucide="shopping-basket" class="w-4 h-4"></i>
                        </span>
                        Purchase Finance
                    </h1>
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-800 border border-emerald-200">
                        Monthly Procurement
                    </span>
                    @if($selectedProductFilter)
                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-bold text-indigo-700 border border-indigo-200">
                            <i data-lucide="filter" class="w-3 h-3"></i>
                            Filtered: {{ $productFilters->firstWhere('uuid', $selectedProductFilter)?->name ?? 'Custom Filter' }}
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-xs font-semibold text-slate-500">
                    What are we purchasing right now? &bull; Centralized Procurement Financial Overview &bull; Invoices, Vendors, Purchasers &amp; Settlements
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.cashbook.finance.vendor-credit') }}"
                   class="inline-flex items-center gap-1.5 rounded-2xl bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wider text-white shadow-xs hover:bg-emerald-800 transition cursor-pointer">
                    <i data-lucide="truck" class="w-4 h-4 text-emerald-200 shrink-0"></i>
                    <span>Vendor Credit</span>
                </a>

                <a href="{{ route('admin.cashbook.finance.purchase.product-filters.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-2xl border border-slate-300 bg-white px-4 py-2 text-xs font-black uppercase tracking-wider text-slate-800 shadow-xs hover:border-slate-400 hover:bg-slate-50 transition cursor-pointer">
                    <i data-lucide="sliders" class="w-4 h-4 text-slate-500 shrink-0"></i>
                    <span>Manage Filters</span>
                </a>

                <a href="{{ route('admin.cashbook.purchaser-business-days.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-2xl border border-slate-300 bg-white px-4 py-2 text-xs font-black uppercase tracking-wider text-slate-800 shadow-xs hover:border-slate-400 hover:bg-slate-50 transition cursor-pointer">
                    <i data-lucide="calendar-check-2" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                    <span>Business Days</span>
                </a>
            </div>
        </div>

        <!-- Period Selector Form & Switcher -->
        <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-3 sm:p-4" x-data="{ activeMode: '{{ $periodMode }}' }">
            <form method="GET" action="{{ route('admin.cashbook.finance.purchase') }}" class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="period_mode" :value="activeMode">

                <!-- Mode Switcher Buttons -->
                <div class="flex items-center gap-1.5 bg-slate-200/70 p-1 rounded-xl w-fit flex-wrap">
                    <button type="submit"
                            @click="activeMode = 'month'"
                            name="period_mode"
                            value="month"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-extrabold transition cursor-pointer {{ $periodMode === 'month' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                        Month
                    </button>

                    <button type="button"
                            @click="activeMode = 'day'"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-extrabold transition cursor-pointer {{ $periodMode === 'day' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                        Day
                    </button>

                    <button type="button"
                            @click="activeMode = 'custom'"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-extrabold transition cursor-pointer {{ $periodMode === 'custom' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                        Custom
                    </button>
                </div>

                <!-- Month Selection & Mode Specific Inputs -->
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Month Navigator -->
                    <div class="flex items-center gap-1">
                        <a href="{{ route('admin.cashbook.finance.purchase', array_merge($purchaseContext, ['month' => $prevMonth, 'period_mode' => 'month'])) }}"
                           class="p-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 transition" title="Previous Month">
                            &larr;
                        </a>
                        <span class="px-2.5 py-1 text-xs font-black text-slate-900 uppercase">
                            {{ $monthTitle }}
                        </span>
                        <a href="{{ route('admin.cashbook.finance.purchase', array_merge($purchaseContext, ['month' => $nextMonth, 'period_mode' => 'month'])) }}"
                           class="p-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 transition" title="Next Month">
                            &rarr;
                        </a>
                    </div>

                    <!-- Day Mode Input -->
                    <div x-show="activeMode === 'day'" class="flex flex-wrap items-center gap-2" style="{{ $periodMode === 'day' ? '' : 'display: none;' }}">
                        <label for="day_picker" class="text-xs font-bold text-slate-600">Day:</label>
                        <input type="date"
                               id="day_picker"
                               name="date"
                               value="{{ $periodStart }}"
                               min="{{ $monthStart }}"
                               max="{{ $monthEnd }}"
                               class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden max-w-[150px]">
                        <button type="submit" class="rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800 transition cursor-pointer">
                            Apply Day
                        </button>
                    </div>

                    <!-- Custom Range Mode Inputs -->
                    <div x-show="activeMode === 'custom'" class="flex flex-wrap items-center gap-2" style="{{ $periodMode === 'custom' ? '' : 'display: none;' }}">
                        <div class="flex items-center gap-1">
                            <label for="from_picker" class="text-xs font-bold text-slate-600">From:</label>
                            <input type="date"
                                   id="from_picker"
                                   name="from"
                                   value="{{ $periodStart }}"
                                   min="{{ $monthStart }}"
                                   max="{{ $monthEnd }}"
                                   class="rounded-xl border border-slate-300 bg-white px-2 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden max-w-[130px]">
                        </div>
                        <div class="flex items-center gap-1">
                            <label for="to_picker" class="text-xs font-bold text-slate-600">To:</label>
                            <input type="date"
                                   id="to_picker"
                                   name="to"
                                   value="{{ $periodEnd }}"
                                   min="{{ $monthStart }}"
                                   max="{{ $monthEnd }}"
                                   class="rounded-xl border border-slate-300 bg-white px-2 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden max-w-[130px]">
                        </div>
                        <button type="submit" class="rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800 transition cursor-pointer">
                            Apply Range
                        </button>
                    </div>

                    <!-- Product Filter Selector -->
                    <div class="flex items-center gap-1.5">
                        <label for="product_filter_select" class="text-xs font-bold text-slate-600 sr-only">Product Filter:</label>
                        <select id="product_filter_select"
                                name="product_filter"
                                onchange="this.form.submit()"
                                class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden">
                            <option value="">All Products</option>
                            @foreach($productFilters as $filter)
                                <option value="{{ $filter->uuid }}" @selected($selectedProductFilter === $filter->uuid)>{{ $filter->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>

            <!-- Current Period Active Badge -->
            <div class="mt-3 pt-3 border-t border-slate-200/60 flex flex-wrap items-center justify-between gap-2">
                <span class="text-xs font-bold text-slate-600 flex items-center gap-1.5">
                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                    Period: <strong class="text-slate-950 font-black">{{ $formattedRange }}</strong>
                </span>
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                    {{ strtoupper($periodMode) }} FINANCE VIEW
                </span>
            </div>
        </div>
    </header>

    <!-- MONTHLY-FIRST FINANCIAL SUMMARY (SHOP CASHBOOK PATTERN) -->
    <section class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4" aria-label="Purchase Financial Summary">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                Purchase Financial Summary
            </h2>
            <span class="text-xs font-semibold text-slate-500">{{ $monthTitle }} Configured Values</span>
        </div>

        <!-- 4 Primary Numbers Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. TOTAL PURCHASES -->
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-4 sm:p-5 transition hover:bg-emerald-50/70 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <span class="text-xs font-black uppercase tracking-wider text-emerald-800">Total Purchases</span>
                        <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-mono font-bold text-emerald-800">
                            {{ number_format((int) $summary->invoice_count) }} Invoices
                        </span>
                    </div>
                    <p class="mt-2 font-mono text-xl sm:text-2xl lg:text-3xl font-black text-emerald-950 tabular-nums tracking-tight leading-tight break-words">
                        ₹{{ number_format((float) $summary->total_purchase, 2) }}
                    </p>
                    <p class="mt-1 text-xs font-medium text-emerald-700">Gross Procurement Volume</p>
                </div>
            </div>

            <!-- 2. CASH PURCHASES -->
            <div class="rounded-2xl border border-teal-100 bg-teal-50/40 p-4 sm:p-5 transition hover:bg-teal-50/70 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <span class="text-xs font-black uppercase tracking-wider text-teal-800">Cash Purchases</span>
                        @if((float) $summary->total_purchase > 0)
                            <span class="inline-flex items-center rounded-md bg-teal-100 px-2 py-0.5 text-[10px] font-mono font-bold text-teal-800">
                                {{ round(((float) $summary->cash_purchase / (float) $summary->total_purchase) * 100, 1) }}% of Total
                            </span>
                        @endif
                    </div>
                    <p class="mt-2 font-mono text-xl sm:text-2xl lg:text-3xl font-black text-teal-950 tabular-nums tracking-tight leading-tight break-words">
                        ₹{{ number_format((float) $summary->cash_purchase, 2) }}
                    </p>
                    <p class="mt-1 text-xs font-medium text-teal-700">Direct Cash Procurement</p>
                </div>
            </div>

            <!-- 3. CREDIT PURCHASES -->
            <div class="rounded-2xl border border-indigo-100 bg-indigo-50/40 p-4 sm:p-5 transition hover:bg-indigo-50/70 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <span class="text-xs font-black uppercase tracking-wider text-indigo-800">Credit Purchases</span>
                        @if((float) $summary->total_purchase > 0)
                            <span class="inline-flex items-center rounded-md bg-indigo-100 px-2 py-0.5 text-[10px] font-mono font-bold text-indigo-800">
                                {{ round(((float) $summary->credit_purchase / (float) $summary->total_purchase) * 100, 1) }}% of Total
                            </span>
                        @endif
                    </div>
                    <p class="mt-2 font-mono text-xl sm:text-2xl lg:text-3xl font-black text-indigo-950 tabular-nums tracking-tight leading-tight break-words">
                        ₹{{ number_format((float) $summary->credit_purchase, 2) }}
                    </p>
                    <p class="mt-1 text-xs font-medium text-indigo-700">Credit Invoices Issued</p>
                </div>
            </div>

            <!-- 4. CREDIT OUTSTANDING -->
            <div class="rounded-2xl border border-rose-100 bg-rose-50/40 p-4 sm:p-5 transition hover:bg-rose-50/70 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <span class="text-xs font-black uppercase tracking-wider text-rose-800">Credit Outstanding</span>
                        <a href="{{ route('admin.cashbook.finance.vendor-credit') }}" class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-700 hover:text-rose-900 underline">
                            Vendor Credit &rarr;
                        </a>
                    </div>
                    <p class="mt-2 font-mono text-xl sm:text-2xl lg:text-3xl font-black text-rose-950 tabular-nums tracking-tight leading-tight break-words">
                        ₹{{ number_format((float) $summary->credit_outstanding, 2) }}
                    </p>
                    <p class="mt-1 text-xs font-medium text-rose-700">Pending Settlement to Vendors</p>
                </div>
            </div>
        </div>

        <!-- OPERATIONAL DRILLDOWNS (4 CARDS) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 border-t border-slate-100">
            <a href="{{ route('admin.cashbook.finance.purchase.purchasers', $purchaseContext) }}"
               class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-3.5 hover:bg-emerald-50 hover:border-emerald-200 transition group">
                <span class="flex items-center justify-between text-[11px] font-black uppercase tracking-wider text-slate-500 group-hover:text-emerald-800">
                    <span>Purchasers</span>
                    <i data-lucide="users" class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600"></i>
                </span>
                <strong class="mt-1.5 block font-mono text-lg font-black text-slate-900 group-hover:text-emerald-950">
                    {{ number_format((int) $summary->purchaser_count) }}
                </strong>
            </a>

            <a href="{{ route('admin.cashbook.finance.purchase.vendors', $purchaseContext) }}"
               class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-3.5 hover:bg-emerald-50 hover:border-emerald-200 transition group">
                <span class="flex items-center justify-between text-[11px] font-black uppercase tracking-wider text-slate-500 group-hover:text-emerald-800">
                    <span>Vendors</span>
                    <i data-lucide="truck" class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600"></i>
                </span>
                <strong class="mt-1.5 block font-mono text-lg font-black text-slate-900 group-hover:text-emerald-950">
                    {{ number_format((int) $summary->vendor_count) }}
                </strong>
            </a>

            <a href="{{ route('admin.cashbook.finance.purchase.invoices', $purchaseContext) }}"
               class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-3.5 hover:bg-emerald-50 hover:border-emerald-200 transition group">
                <span class="flex items-center justify-between text-[11px] font-black uppercase tracking-wider text-slate-500 group-hover:text-emerald-800">
                    <span>Invoices</span>
                    <i data-lucide="receipt" class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600"></i>
                </span>
                <strong class="mt-1.5 block font-mono text-lg font-black text-slate-900 group-hover:text-emerald-950">
                    {{ number_format((int) $summary->invoice_count) }}
                </strong>
            </a>

            <a href="{{ route('admin.cashbook.finance.purchase.categories', $purchaseContext) }}"
               class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-3.5 hover:bg-emerald-50 hover:border-emerald-200 transition group">
                <span class="flex items-center justify-between text-[11px] font-black uppercase tracking-wider text-slate-500 group-hover:text-emerald-800">
                    <span>Categories</span>
                    <i data-lucide="tags" class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600"></i>
                </span>
                <strong class="mt-1.5 block font-mono text-lg font-black text-slate-900 group-hover:text-emerald-950">
                    {{ number_format((int) $summary->category_count) }}
                </strong>
            </a>
        </div>
    </section>

    @if((int) $summary->invoice_count === 0)
        <section class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center shadow-xs space-y-3">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-slate-100 text-slate-400">
                <i data-lucide="inbox" class="w-6 h-6"></i>
            </div>
            <h2 class="text-base font-black text-slate-900">No purchases recorded for this period</h2>
            <p class="text-xs font-medium text-slate-500">This dashboard uses the purchase business date recorded in the system.</p>
            <a href="{{ route('admin.cashbook.finance.purchase', ['period' => 'month', 'period_mode' => 'month', 'month' => today('Asia/Kolkata')->format('Y-m')]) }}"
               class="inline-flex rounded-xl bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wider text-white shadow-xs hover:bg-emerald-800 transition">
                View Current Month
            </a>
        </section>
    @endif

    @if($dashboard['unsupportedLegacyCount'] > 0)
        <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-xs font-semibold text-amber-900 flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0"></i>
            <span>{{ $dashboard['unsupportedLegacyCount'] }} legacy invoice(s) have no reliable cart-item link and are excluded from category reporting.</span>
        </div>
    @endif

    <!-- 2-COLUMN OVERVIEWS: PURCHASERS & VENDORS -->
    <section class="grid gap-6 xl:grid-cols-2">
        <!-- Purchasers Overview Card -->
        <div id="purchasers" class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-950">Purchasers Overview</h2>
                </div>
                <a href="{{ route('admin.cashbook.finance.purchase.purchasers', $purchaseContext) }}"
                   class="text-xs font-black text-emerald-700 hover:text-emerald-900 transition flex items-center gap-1">
                    <span>View All</span>
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($dashboard['purchasers'] as $purchaser)
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs">
                        <div>
                            <a class="font-bold text-slate-900 hover:text-emerald-700 transition"
                               href="{{ route('admin.cashbook.finance.purchase.purchasers.show', ['purchaser' => $purchaser->purchaser_public_uuid] + $purchaseContext) }}">
                                {{ $purchaser->purchaser_name ?? 'Unassigned' }}
                            </a>
                            <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-500 font-medium">
                                <span>{{ number_format((int) $purchaser->invoice_count) }} invoices</span>
                                <span>&bull;</span>
                                <span>Cash: ₹{{ number_format((float) $purchaser->cash_purchase, 2) }}</span>
                                <span>&bull;</span>
                                <span>Credit: ₹{{ number_format((float) $purchaser->credit_purchase, 2) }}</span>
                            </div>
                        </div>
                        <div class="text-left sm:text-right">
                            <strong class="font-mono text-sm font-black text-slate-950 tabular-nums">
                                ₹{{ number_format((float) $purchaser->total_purchase, 2) }}
                            </strong>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-xs font-semibold text-slate-400">No purchaser activity recorded for this period.</p>
                @endforelse
            </div>
        </div>

        <!-- Vendors Overview Card -->
        <div id="vendors" class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-teal-600"></span>
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-950">Vendors Overview</h2>
                </div>
                <a href="{{ route('admin.cashbook.finance.purchase.vendors', $purchaseContext) }}"
                   class="text-xs font-black text-emerald-700 hover:text-emerald-900 transition flex items-center gap-1">
                    <span>View All</span>
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($dashboard['vendors'] as $vendor)
                    @php($tags = $vendor->category_tags ? array_map(fn ($tag) => explode('|', $tag, 2), array_filter(explode(',', $vendor->category_tags))) : [])
                    <div class="py-3 space-y-2 text-xs">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-1">
                            <div>
                                <a class="font-bold text-slate-900 hover:text-emerald-700 transition"
                                   href="{{ route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $vendor->supplier_public_uuid] + $purchaseContext) }}">
                                    {{ $vendor->supplier_name ?? 'Unassigned' }}
                                </a>
                                @if($tags !== [])
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach(array_slice($tags, 0, 3) as [$categoryId, $categoryName])
                                            <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">
                                                {{ $categoryName ?: 'Uncategorised' }}
                                            </span>
                                        @endforeach
                                        @if(count($tags) > 3)
                                            <span class="rounded-md bg-slate-800 px-1.5 py-0.5 text-[10px] font-bold text-white">
                                                +{{ count($tags) - 3 }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <strong class="font-mono text-sm font-black text-slate-950 tabular-nums">
                                ₹{{ number_format((float) $vendor->total_purchase, 2) }}
                            </strong>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-[11px] text-slate-500 font-medium">
                            <div>Cash: <span class="font-mono font-bold text-slate-800">₹{{ number_format((float) $vendor->cash_purchase, 2) }}</span></div>
                            <div>Credit: <span class="font-mono font-bold text-slate-800">₹{{ number_format((float) $vendor->credit_purchase, 2) }}</span></div>
                            <div>Pending: <span class="font-mono font-bold text-rose-700">₹{{ number_format((float) $vendor->outstanding, 2) }}</span></div>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-xs font-semibold text-slate-400">No vendor activity recorded for this period.</p>
                @endforelse
            </div>
        </div>
    </section>

    <!-- CATEGORY OVERVIEW TABLE -->
    <section id="categories" class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-950">Category Overview</h2>
            </div>
            <a href="{{ route('admin.cashbook.finance.purchase.categories', $purchaseContext) }}"
               class="text-xs font-black text-emerald-700 hover:text-emerald-900 transition flex items-center gap-1">
                <span>View All Categories</span>
                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[36rem] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 rounded-xl">
                    <tr>
                        <th class="p-3">Category</th>
                        <th class="p-3 text-right">Purchase Value</th>
                        <th class="p-3 text-right">Invoices</th>
                        <th class="p-3 text-right">Vendors</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($dashboard['categories'] as $category)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-3 font-bold">
                                <a class="text-slate-900 hover:text-emerald-700 transition"
                                   href="{{ route('admin.cashbook.finance.purchase.categories.show', ['category' => $category->category_id] + $purchaseContext) }}">
                                    {{ $category->category_name ?? 'Uncategorised' }}
                                </a>
                            </td>
                            <td class="p-3 text-right font-mono font-black text-slate-950 tabular-nums">
                                ₹{{ number_format((float) $category->total_purchase, 2) }}
                            </td>
                            <td class="p-3 text-right font-mono font-bold text-slate-700 tabular-nums">
                                {{ number_format((int) $category->invoice_count) }}
                            </td>
                            <td class="p-3 text-right font-mono font-bold text-slate-700 tabular-nums">
                                {{ number_format((int) $category->vendor_count) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-6 text-center text-xs font-semibold text-slate-400">No category activity recorded for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- RECENT PURCHASES TABLE -->
    <section id="invoices" class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-slate-900"></span>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-950">Recent Purchases</h2>
            </div>
            <a href="{{ route('admin.cashbook.finance.purchase.invoices', $purchaseContext) }}"
               class="text-xs font-black text-emerald-700 hover:text-emerald-900 transition flex items-center gap-1">
                <span>View All Invoices</span>
                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[50rem] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 rounded-xl">
                    <tr>
                        <th class="p-3">Date</th>
                        <th class="p-3">Invoice</th>
                        <th class="p-3">Vendor</th>
                        <th class="p-3">Purchaser</th>
                        <th class="p-3">Produce Type</th>
                        <th class="p-3 text-center">Payment</th>
                        <th class="p-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($dashboard['recentPurchases'] as $invoice)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-3 font-mono font-bold text-slate-600">
                                {{ \Illuminate\Support\Carbon::parse($invoice->business_date)->format('d M Y') }}
                            </td>
                            <td class="p-3 font-bold">
                                <a class="text-emerald-700 hover:underline font-mono" href="{{ route('purchasing.invoices.show', $invoice->invoice_public_uuid) }}">
                                    {{ $invoice->invoice_number ?: 'Invoice' }}
                                </a>
                            </td>
                            <td class="p-3 font-medium text-slate-800">{{ $invoice->supplier_name }}</td>
                            <td class="p-3 font-medium text-slate-800">{{ $invoice->purchaser_name }}</td>
                            <td class="p-3 text-slate-600">
                                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700">
                                    {{ $invoice->produce_types ?: 'General' }}
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                @if(strtolower($invoice->payment_class) === 'credit')
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-extrabold uppercase text-amber-800 border border-amber-200">
                                        Credit
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-extrabold uppercase text-emerald-800 border border-emerald-200">
                                        Cash
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 text-right font-mono font-black text-slate-950 tabular-nums">
                                ₹{{ number_format((float) $invoice->total_purchase, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-6 text-center text-xs font-semibold text-slate-400">No recent purchase invoices found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
