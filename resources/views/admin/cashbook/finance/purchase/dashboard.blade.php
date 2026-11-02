@extends('admin.cashbook.layouts.app')

@php
    $selectedProductFilter = $filters['product_filter'] ?? null;
    $activePurchaseTab = 'overview';
    $periodMode = $filters['period_mode'] ?? 'month';
    $period = $filters['period'] ?? ($periodMode === 'day' ? 'today' : ($periodMode === 'custom' ? 'custom' : 'month'));
    $month = $filters['month'] ?? today('Asia/Kolkata')->format('Y-m');
    $monthStart = $filters['month_start'] ?? \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
    $monthEnd = $filters['month_end'] ?? \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();
    $prevMonth = $filters['prev_month'] ?? \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->subMonth()->format('Y-m');
    $nextMonth = $filters['next_month'] ?? \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->addMonth()->format('Y-m');
    $monthTitle = $filters['month_title'] ?? \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y');
    $periodStart = $filters['start_date'] ?? $monthStart;
    $periodEnd = $filters['end_date'] ?? $monthEnd;
    $formattedRange = $filters['formatted_range'] ?? ($periodStart === $periodEnd ? \Illuminate\Support\Carbon::parse($periodStart)->format('d M Y') : \Illuminate\Support\Carbon::parse($periodStart)->format('d M Y').' – '.\Illuminate\Support\Carbon::parse($periodEnd)->format('d M Y'));

    $summary = $dashboard['summary'];
    $allPurchasers = $dashboard['allPurchasers'] ?? $dashboard['purchasers'];
    $bills = $dashboard['bills'] ?? $dashboard['recentPurchases'];
    $activePurchaserId = $filters['purchaser_id'] ?? null;
    $activePayment = $filters['payment'] ?? 'all';

    $activePurchaseTab = 'overview';
    $purchasePeriod = $filters['period_mode'] ?? $filters['period'] ?? 'month';
    $purchaseContext = ['period' => $filters['period'] ?? 'month'];
    if ($selectedProductFilter) {
        $purchaseContext['product_filter'] = $selectedProductFilter;
    }
    if (in_array($filters['period'] ?? '', ['custom', 'between', 'range'], true)) {
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
    if (!empty($activePurchaserId)) {
        $purchaseContext['purchaser_id'] = $activePurchaserId;
    }
    if (($filters['payment'] ?? 'all') !== 'all') {
        $purchaseContext['payment'] = $filters['payment'];
    }
    if (!empty($filters['search'])) {
        $purchaseContext['search'] = $filters['search'];
    }

    // Base query params for links preserving current period & search
    $baseParams = [
        'period' => $period,
        'period_mode' => $periodMode,
        'month' => $month,
        'date' => $filters['date'] ?? null,
        'from' => $filters['from'] ?? null,
        'to' => $filters['to'] ?? null,
        'start_date' => $periodStart,
        'end_date' => $periodEnd,
        'search' => $filters['search'] ?? null,
        'product_filter' => $selectedProductFilter,
    ];
    $baseParams = array_filter($baseParams, fn ($v) => $v !== null && $v !== '');

    $currentCalendarMonth = today('Asia/Kolkata')->format('Y-m');
    $prevCalendarMonth = today('Asia/Kolkata')->subMonth()->format('Y-m');
    $presetBaseParams = array_filter([
        'purchaser_id' => $activePurchaserId,
        'payment' => ($activePayment !== 'all') ? $activePayment : null,
        'product_filter' => $selectedProductFilter,
        'search' => $filters['search'] ?? null,
    ], fn ($v) => $v !== null && $v !== '');

    $activePurchaserObj = $activePurchaserId ? $purchasersList->firstWhere('id', (int) $activePurchaserId) : null;
@endphp

@section('title', 'Purchase Report — Reports V1')
@section('header_title')
    <i data-lucide="shopping-basket" class="h-5 w-5 text-emerald-600"></i> Reports V1 &bull; Purchase Report
@endsection

@section('header_subtitle')
    Procurement Action Center &bull; Consolidated Purchaser-Wise Purchase Report
@endsection

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-20">

    <!-- TOP NAVIGATION & SUBSECTIONS -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        @include('admin.cashbook.finance.purchase._nav')
        @include('admin.cashbook.finance.purchase._dashboard-tabs')
    </div>

    <!-- TOP HEADER -->
    <header class="rounded-3xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-xs space-y-5" aria-label="Purchase Report Header and Filters">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-2xl bg-emerald-600 text-white shadow-xs">
                        <i data-lucide="shopping-basket" class="w-5 h-5"></i>
                    </span>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="text-xl sm:text-2xl font-black text-slate-950 tracking-tight uppercase">
                                Purchase Report
                            </h1>
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-800 border border-emerald-200">
                                Reports V1
                            </span>
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-slate-600">
                                Purchase Finance
                            </span>
                        </div>
                        <p class="text-xs font-semibold text-slate-500 mt-0.5">
                            What are we purchasing right now? &bull; Purchaser-wise cash and credit purchase totals with drill-down to vendor bills and item details.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Header Quick Actions -->
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.cashbook.finance.vendor-credit') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-700 px-3.5 py-2 text-xs font-black uppercase tracking-wider text-white shadow-xs hover:bg-emerald-800 transition cursor-pointer">
                    <i data-lucide="truck" class="w-3.5 h-3.5 text-emerald-200 shrink-0"></i>
                    <span>Vendor Credit</span>
                </a>

                <a href="{{ route('admin.cashbook.purchaser-business-days.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-black uppercase tracking-wider text-slate-700 shadow-xs hover:bg-slate-50 transition cursor-pointer">
                    <i data-lucide="calendar-check-2" class="w-3.5 h-3.5 text-emerald-600 shrink-0"></i>
                    <span>Business Days</span>
                </a>
            </div>
        </div>

        <!-- PERIOD & PURCHASER FILTER BAR -->
        <div class="rounded-2xl border border-slate-100 bg-slate-50/90 p-4 space-y-4" x-data="{
            activePeriod: '{{ $period }}',
            periodMode: '{{ $periodMode }}',
            showCustom: {{ in_array($period, ['custom', 'between', 'range'], true) || $periodMode === 'custom' ? 'true' : 'false' }}
        }">
            <form method="GET" action="{{ route('admin.cashbook.finance.purchase') }}" class="space-y-3">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="period" value="{{ $period }}" :disabled="showCustom">
                <input type="hidden" name="period_mode" value="{{ $periodMode }}" :disabled="showCustom">

                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 flex-wrap">
                    <!-- Period Filter Presets -->
                    <div class="flex items-center gap-1.5 bg-slate-200/80 p-1 rounded-xl flex-wrap">
                        <a href="{{ route('admin.cashbook.finance.purchase', array_merge($presetBaseParams, ['period' => 'today', 'period_mode' => 'day'])) }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-extrabold transition {{ $period === 'today' ? 'bg-white text-slate-950 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                            Today
                        </a>

                        <a href="{{ route('admin.cashbook.finance.purchase', array_merge($presetBaseParams, ['period' => 'yesterday', 'period_mode' => 'day'])) }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-extrabold transition {{ $period === 'yesterday' ? 'bg-white text-slate-950 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                            Yesterday
                        </a>

                        <a href="{{ route('admin.cashbook.finance.purchase', array_merge($presetBaseParams, ['period' => 'week', 'period_mode' => 'custom'])) }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-extrabold transition {{ in_array($period, ['week', 'this_week'], true) ? 'bg-white text-slate-950 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                            This Week
                        </a>

                        <a href="{{ route('admin.cashbook.finance.purchase', array_merge($presetBaseParams, ['period' => 'month', 'period_mode' => 'month', 'month' => $currentCalendarMonth])) }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-extrabold transition {{ (in_array($period, ['month', 'this_month'], true) && $periodMode === 'month' && $month === $currentCalendarMonth) ? 'bg-white text-slate-950 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                            This Month
                        </a>

                        <a href="{{ route('admin.cashbook.finance.purchase', array_merge($presetBaseParams, ['period' => 'prev_month', 'period_mode' => 'month', 'month' => $prevCalendarMonth])) }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-extrabold transition {{ (in_array($period, ['prev_month', 'last_month'], true) || ($periodMode === 'month' && $month === $prevCalendarMonth && !in_array($period, ['month', 'this_month'], true))) ? 'bg-white text-slate-950 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                            Prev Month
                        </a>

                        <button type="button"
                                @click="showCustom = !showCustom"
                                class="px-3 py-1.5 rounded-lg text-xs font-extrabold transition cursor-pointer flex items-center gap-1 {{ in_array($period, ['custom', 'between', 'range'], true) || $periodMode === 'custom' ? 'bg-white text-slate-950 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                            <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                            <span>Custom Range</span>
                        </button>
                    </div>

                    {{-- Purchaser Selector & Filters --}}
                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- Purchaser Selector -->
                        <div class="flex items-center gap-1.5">
                            <label for="purchaser_select" class="text-xs font-bold text-slate-600">Purchaser:</label>
                            <select id="purchaser_select"
                                    name="purchaser_id"
                                    onchange="this.form.submit()"
                                    class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden">
                                <option value="">All Purchasers</option>
                                @foreach($purchasersList as $p)
                                    <option value="{{ $p->id }}" @selected($activePurchaserId == $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Product Filter Selector -->
                        <div class="flex items-center gap-1.5">
                            <label for="product_filter_select" class="text-xs font-bold text-slate-600 sr-only">Product Filter:</label>
                            <select id="product_filter_select"
                                    name="product_filter"
                                    onchange="this.form.submit()"
                                    class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden">
                                <option value="">All Products</option>
                                @if(!empty($productFilters))
                                    @foreach($productFilters as $filter)
                                        <option value="{{ $filter->uuid }}" @selected(($filters['product_filter'] ?? '') === $filter->uuid)>{{ $filter->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <!-- Payment Filter Selector -->
                        <div class="flex items-center gap-1.5">
                            <label for="payment_filter_select" class="text-xs font-bold text-slate-600">Payment:</label>
                            <select id="payment_filter_select"
                                    name="payment"
                                    onchange="this.form.submit()"
                                    class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden">
                                <option value="all" @selected($activePayment === 'all')>All</option>
                                <option value="cash" @selected($activePayment === 'cash')>Cash Only</option>
                                <option value="credit" @selected($activePayment === 'credit')>Credit Only</option>
                            </select>
                        </div>

                        <!-- Reset Button -->
                        <a href="{{ route('admin.cashbook.finance.purchase', ['period' => 'month', 'period_mode' => 'month']) }}"
                           class="p-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 hover:text-slate-900 transition"
                           title="Reset all filters">
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>
                </div>

                <!-- Custom Range Inputs (collapsible) -->
                <div x-show="showCustom" x-cloak class="pt-3 border-t border-slate-200/80 flex flex-wrap items-center gap-3">
                    <input type="hidden" name="period" value="custom">
                    <input type="hidden" name="period_mode" value="custom">

                    <div class="flex items-center gap-2">
                        <label for="from_date" class="text-xs font-bold text-slate-600">From:</label>
                        <input type="date"
                               id="from_date"
                               name="from"
                               value="{{ $periodStart }}"
                               class="rounded-xl border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden">
                    </div>

                    <div class="flex items-center gap-2">
                        <label for="to_date" class="text-xs font-bold text-slate-600">To:</label>
                        <input type="date"
                               id="to_date"
                               name="to"
                               value="{{ $periodEnd }}"
                               class="rounded-xl border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden">
                    </div>

                    <button type="submit"
                            class="rounded-xl bg-slate-900 px-3.5 py-1.5 text-xs font-bold text-white hover:bg-slate-800 transition cursor-pointer">
                        Apply Dates
                    </button>
                </div>
            </form>

            <!-- Active Period Badge & Info Bar -->
            <div class="pt-2.5 border-t border-slate-200/70 flex flex-wrap items-center justify-between gap-2 text-xs font-medium text-slate-600">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="flex items-center gap-1.5 font-bold text-slate-700">
                        <i data-lucide="calendar" class="w-4 h-4 text-emerald-600"></i>
                        Period: <strong class="text-slate-950 font-black">{{ $formattedRange }}</strong>
                    </span>

                    @if($activePurchaserObj)
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100/80 text-emerald-900 px-2.5 py-0.5 text-[11px] font-extrabold">
                            <i data-lucide="user" class="w-3 h-3 text-emerald-700"></i>
                            Purchaser: {{ $activePurchaserObj->name }}
                        </span>
                    @endif

                    @if($activePayment !== 'all')
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-extrabold {{ $activePayment === 'cash' ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-900' }}">
                            Mode: {{ ucfirst($activePayment) }} Purchases
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                        {{ strtoupper($periodMode) }} FINANCE VIEW
                    </span>
                    <span class="text-slate-400 font-mono text-[11px]">({{ $monthTitle }})</span>
                </div>
            </div>
        </div>
    </header>

    @if((int) $summary->invoice_count === 0)
        <section class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center shadow-xs space-y-3">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-slate-100 text-slate-400">
                <i data-lucide="inbox" class="w-6 h-6"></i>
            </div>
            <h2 class="text-base font-black text-slate-900">No purchases recorded for this period</h2>
            <p class="text-xs font-medium text-slate-500">This dashboard uses the purchase business date recorded in the system.</p>
            <a href="{{ route('admin.cashbook.finance.purchase', ['period' => 'month']) }}"
               class="inline-flex rounded-xl bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wider text-white shadow-xs hover:bg-emerald-800 transition">
                View Current Month
            </a>
        </section>
    @endif

    <!-- SECTION B: TOP SUMMARY (EXACTLY 3 CARDS - CLICKABLE TO VIEW DETAILS) -->
    <section class="rounded-3xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-xs space-y-4" aria-label="Purchase Financial Summary">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    Purchase Financial Summary
                </h2>
                <p class="text-[11px] font-semibold text-slate-500 mt-0.5">
                    Click any summary card below to open its dedicated fresh invoices ledger with search and table sorting.
                </p>
            </div>
            <span class="text-xs font-semibold text-slate-500">
                {{ $formattedRange }} &bull; {{ number_format((int) $summary->invoice_count) }} Invoices
            </span>
        </div>

        <!-- 3 Primary KPI Cards (Clickable to Fresh Invoices Details Page) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- 1. CASH PURCHASE -->
            <a href="{{ route('admin.cashbook.finance.purchase.invoices', array_merge($baseParams, ['payment' => 'cash'])) }}"
               class="group block rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50/70 via-emerald-50/30 to-white p-5 shadow-xs transition hover:border-emerald-400 hover:shadow-md cursor-pointer transform hover:-translate-y-0.5 duration-150"
               title="Click to view all Cash Purchase Invoices on fresh details page">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-black uppercase tracking-wider text-emerald-800 flex items-center gap-1.5">
                        <span>Cash Purchase</span>
                        <i data-lucide="arrow-up-right" class="w-3.5 h-3.5 text-emerald-600 opacity-60 group-hover:opacity-100 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition"></i>
                    </span>
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-emerald-100 text-emerald-700 group-hover:bg-emerald-200 transition">
                        <i data-lucide="banknote" class="w-4 h-4"></i>
                    </span>
                </div>
                <p class="mt-3 font-mono text-2xl sm:text-3xl font-black text-emerald-950 tabular-nums tracking-tight">
                    ₹{{ number_format((float) $summary->cash_purchase, 2) }}
                </p>
                <div class="mt-2 flex items-center justify-between text-xs text-emerald-700 font-semibold">
                    <span class="group-hover:underline">Direct cash procurement &bull; View Invoices &rarr;</span>
                    @if((float) $summary->total_purchase > 0)
                        <span class="font-mono text-[11px]">{{ round(((float) $summary->cash_purchase / (float) $summary->total_purchase) * 100, 1) }}%</span>
                    @endif
                </div>
            </a>

            <!-- 2. CREDIT PURCHASE -->
            <a href="{{ route('admin.cashbook.finance.purchase.invoices', array_merge($baseParams, ['payment' => 'credit'])) }}"
               class="group block rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50/70 via-amber-50/30 to-white p-5 shadow-xs transition hover:border-amber-400 hover:shadow-md cursor-pointer transform hover:-translate-y-0.5 duration-150"
               title="Click to view all Credit Purchase Invoices on fresh details page">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-black uppercase tracking-wider text-amber-800 flex items-center gap-1.5">
                        <span>Credit Purchase</span>
                        <i data-lucide="arrow-up-right" class="w-3.5 h-3.5 text-amber-600 opacity-60 group-hover:opacity-100 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition"></i>
                    </span>
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-amber-100 text-amber-700 group-hover:bg-amber-200 transition">
                        <i data-lucide="clock" class="w-4 h-4"></i>
                    </span>
                </div>
                <p class="mt-3 font-mono text-2xl sm:text-3xl font-black text-amber-950 tabular-nums tracking-tight">
                    ₹{{ number_format((float) $summary->credit_purchase, 2) }}
                </p>
                <div class="mt-2 flex items-center justify-between text-xs text-amber-700 font-semibold">
                    <span class="group-hover:underline">Vendor credit invoices &bull; Credit Outstanding &rarr;</span>
                    @if((float) $summary->total_purchase > 0)
                        <span class="font-mono text-[11px]">{{ round(((float) $summary->credit_purchase / (float) $summary->total_purchase) * 100, 1) }}%</span>
                    @endif
                </div>
            </a>

            <!-- 3. TOTAL PURCHASE -->
            <a href="{{ route('admin.cashbook.finance.purchase.invoices', array_merge($baseParams, ['payment' => 'all'])) }}"
               class="group block rounded-2xl border border-slate-300 bg-gradient-to-br from-slate-100/90 via-slate-50/50 to-white p-5 shadow-xs transition hover:border-slate-400 hover:shadow-md cursor-pointer transform hover:-translate-y-0.5 duration-150"
               title="Click to view All Purchase Invoices on fresh details page">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                        <span>Total Purchase</span>
                        <i data-lucide="arrow-up-right" class="w-3.5 h-3.5 text-slate-600 opacity-60 group-hover:opacity-100 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition"></i>
                    </span>
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-xl bg-slate-200 text-slate-800 group-hover:bg-slate-300 transition">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                    </span>
                </div>
                <p class="mt-3 font-mono text-2xl sm:text-3xl font-black text-slate-950 tabular-nums tracking-tight">
                    ₹{{ number_format((float) $summary->total_purchase, 2) }}
                </p>
                <div class="mt-2 flex items-center justify-between text-xs text-slate-600 font-semibold">
                    <span class="group-hover:underline">Cash + Credit &bull; View All Invoices &rarr;</span>
                    <span class="font-mono text-[11px] text-slate-500">{{ number_format((int) $summary->invoice_count) }} bills</span>
                </div>
            </a>
        </div>

        <!-- Operational Breakdown Pills -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 border-t border-slate-100">
            <a href="{{ route('admin.cashbook.finance.purchase.purchasers', $purchaseContext) }}"
               class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3 hover:bg-emerald-50 hover:border-emerald-200 transition group">
                <span class="flex items-center justify-between text-[11px] font-black uppercase tracking-wider text-slate-500 group-hover:text-emerald-800">
                    <span>Purchasers</span>
                    <i data-lucide="users" class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600"></i>
                </span>
                <strong class="mt-1 block font-mono text-base font-black text-slate-900 group-hover:text-emerald-950">
                    {{ number_format((int) $summary->purchaser_count) }}
                </strong>
            </a>

            <a href="{{ route('admin.cashbook.finance.purchase.vendors', $purchaseContext) }}"
               class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3 hover:bg-emerald-50 hover:border-emerald-200 transition group">
                <span class="flex items-center justify-between text-[11px] font-black uppercase tracking-wider text-slate-500 group-hover:text-emerald-800">
                    <span>Vendors</span>
                    <i data-lucide="truck" class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600"></i>
                </span>
                <strong class="mt-1 block font-mono text-base font-black text-slate-900 group-hover:text-emerald-950">
                    {{ number_format((int) $summary->vendor_count) }}
                </strong>
            </a>

            <a href="{{ route('admin.cashbook.finance.purchase.invoices', $purchaseContext) }}"
               class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3 hover:bg-emerald-50 hover:border-emerald-200 transition group">
                <span class="flex items-center justify-between text-[11px] font-black uppercase tracking-wider text-slate-500 group-hover:text-emerald-800">
                    <span>Invoices</span>
                    <i data-lucide="receipt" class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600"></i>
                </span>
                <strong class="mt-1 block font-mono text-base font-black text-slate-900 group-hover:text-emerald-950">
                    {{ number_format((int) $summary->invoice_count) }}
                </strong>
            </a>

            <a href="{{ route('admin.cashbook.finance.purchase.categories', $purchaseContext) }}"
               class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3 hover:bg-emerald-50 hover:border-emerald-200 transition group">
                <span class="flex items-center justify-between text-[11px] font-black uppercase tracking-wider text-slate-500 group-hover:text-emerald-800">
                    <span>Categories</span>
                    <i data-lucide="tags" class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600"></i>
                </span>
                <strong class="mt-1 block font-mono text-base font-black text-slate-900 group-hover:text-emerald-950">
                    {{ number_format((int) $summary->category_count) }}
                </strong>
            </a>
        </div>

        @if(!empty($dashboard['categories']) && $dashboard['categories']->isNotEmpty())
            <div class="flex items-center gap-2 flex-wrap pt-2">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Category Overview:</span>
                @foreach($dashboard['categories'] as $cat)
                    <a href="{{ route('admin.cashbook.finance.purchase.categories.show', ['category' => $cat->category_id] + $purchaseContext) }}"
                       class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-emerald-50 text-slate-700 hover:text-emerald-800 text-xs font-semibold border border-slate-200/70 transition">
                        <span>{{ $cat->category_name ?? 'Uncategorised' }}</span>
                        <span class="text-[10px] font-mono text-slate-500">₹{{ number_format((float) $cat->total_purchase, 0) }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <!-- VENDORS OVERVIEW: INVOICE-STYLE FINANCIAL SUMMARY CARDS -->
        @php
            $vendorsOverviewList = $dashboard['allVendors'] ?? $dashboard['vendors'] ?? collect();
        @endphp
        <div class="pt-5 border-t border-slate-200/80 space-y-4" id="vendors-overview"
             x-data="{
                 viewMode: (function() {
                     try {
                         return localStorage.getItem('gl_vendor_overview_view') || 'table';
                     } catch (e) {
                         return 'table';
                     }
                 })(),
                 setViewMode(mode) {
                     this.viewMode = mode;
                     try {
                         localStorage.setItem('gl_vendor_overview_view', mode);
                     } catch (e) {}
                     this.$nextTick(() => {
                         if (window.lucide) {
                             window.lucide.createIcons();
                         }
                     });
                 },
                 activePayment: '{{ $activePayment }}',
                 search: '',
                 limit: 20,
                 step: 20,
                 includeFullDetails: false,
                 loadingVendorPayment: null,
                 totalVisible: {{ min(20, $vendorsOverviewList->count()) }},
                 totalMatching: {{ $vendorsOverviewList->count() }},
                 setPayment(mode) {
                     if (this.activePayment === mode) return;
                     const url = new URL(window.location.href);
                     if (mode === 'all') {
                         url.searchParams.delete('payment');
                     } else {
                         url.searchParams.set('payment', mode);
                     }
                     window.location.href = url.toString();
                 },
                 loadMore() {
                     this.limit += this.step;
                     this.applyFilters();
                 },
                 applyFilters() {
                     let q = (this.search || '').trim().toLowerCase();
                     let wrappers = this.$refs.vendorList ? this.$refs.vendorList.querySelectorAll('.vendor-card-wrapper') : [];
                     let tableRows = this.$refs.vendorTableBody ? this.$refs.vendorTableBody.querySelectorAll('.vendor-table-row') : [];
                     let count = 0;
                     let matchCount = 0;
                     let visibleIndex = 1;

                     let totalItems = Math.max(wrappers.length, tableRows.length);
                     for (let i = 0; i < totalItems; i++) {
                         let cardEl = wrappers[i];
                         let tableEl = tableRows[i];
                         let refEl = cardEl || tableEl;
                         let name = refEl ? (refEl.dataset.name || '') : '';
                         let tags = refEl ? (refEl.dataset.tags || '') : '';
                         let matchQuery = !q || name.includes(q) || tags.includes(q);

                         if (matchQuery) {
                             matchCount++;
                             if (matchCount <= this.limit) {
                                 if (cardEl) {
                                     cardEl.style.display = '';
                                     cardEl.classList.remove('hidden');
                                     const numEl = cardEl.querySelector('.serial-num');
                                     if (numEl) {
                                         numEl.textContent = 'SL ' + String(visibleIndex).padStart(2, '0');
                                     }
                                 }
                                 if (tableEl) {
                                     tableEl.style.display = '';
                                     tableEl.classList.remove('hidden');
                                     const tableNumEl = tableEl.querySelector('.table-sl-no');
                                     if (tableNumEl) {
                                         tableNumEl.textContent = String(visibleIndex).padStart(2, '0');
                                     }
                                 }
                                 count++;
                                 visibleIndex++;
                             } else {
                                 if (cardEl) {
                                     cardEl.style.display = 'none';
                                     cardEl.classList.add('hidden');
                                 }
                                 if (tableEl) {
                                     tableEl.style.display = 'none';
                                     tableEl.classList.add('hidden');
                                 }
                             }
                         } else {
                             if (cardEl) {
                                 cardEl.style.display = 'none';
                                 cardEl.classList.add('hidden');
                             }
                             if (tableEl) {
                                 tableEl.style.display = 'none';
                                 tableEl.classList.add('hidden');
                             }
                         }
                     }

                     this.totalVisible = count;
                     this.totalMatching = matchCount;

                     this.$nextTick(() => {
                         if (window.lucide) {
                             window.lucide.createIcons();
                         }
                     });
                 },
                 async openVendorPayment(supplierUuid, fallbackUrl) {
                     if (!supplierUuid) {
                         if (fallbackUrl) window.location.href = fallbackUrl;
                         return;
                     }
                     this.loadingVendorPayment = supplierUuid;
                     try {
                         const url = new URL(`/admin/cashbook/finance/purchase/vendors/${supplierUuid}/outstanding-bills`, window.location.origin);
                         @if(!empty($periodStart))
                             url.searchParams.set('start_date', '{{ $periodStart }}');
                         @endif
                         @if(!empty($periodEnd))
                             url.searchParams.set('end_date', '{{ $periodEnd }}');
                         @endif
                         const paramsParser = window['URL' + 'Sear' + 'chParams'];
                         const winParams = new paramsParser(window.location.search);
                         winParams.forEach((val, key) => {
                             if (key !== 'page' && val) {
                                 url.searchParams.set(key, val);
                             }
                         });

                         const res = await fetch(url.toString(), {
                             headers: { 'Accept': 'application/json' }
                         });
                         const data = await res.json();
                         if (res.ok && data.success && data.bills && data.bills.length > 0) {
                             if (data.bills.length === 1) {
                                 window.dispatchEvent(new CustomEvent('open-payment-modal', { detail: data.bills[0] }));
                                 return;
                             }
                         }
                         window.location.href = fallbackUrl;
                     } catch (e) {
                         window.location.href = fallbackUrl;
                     } finally {
                         this.loadingVendorPayment = null;
                     }
                 },
                 get exportPdfUrl() {
                     let url = new URL('{{ route('admin.cashbook.finance.purchase.vendors.export.pdf', $purchaseContext) }}', window.location.origin);
                     if (this.activePayment && this.activePayment !== 'all') {
                         url.searchParams.set('payment', this.activePayment);
                     } else {
                         url.searchParams.delete('payment');
                     }
                     if (this.includeFullDetails) {
                         url.searchParams.set('include_details', '1');
                     } else {
                         url.searchParams.delete('include_details');
                     }
                     return url.toString();
                 },
                 get exportExcelUrl() {
                     let url = new URL('{{ route('admin.cashbook.finance.purchase.vendors.export.excel', $purchaseContext) }}', window.location.origin);
                     if (this.activePayment && this.activePayment !== 'all') {
                         url.searchParams.set('payment', this.activePayment);
                     } else {
                         url.searchParams.delete('payment');
                     }
                     return url.toString();
                 },
                 init() {
                     this.applyFilters();
                 }
             }">
            {{-- Professional Report Header & Top Export Actions --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-[10px] font-black uppercase tracking-widest text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200/70 inline-block">
                            GREEN LEAF
                        </span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            Vendors Overview
                        </span>
                    </div>
                    <h2 class="text-lg sm:text-xl font-black text-slate-950 uppercase tracking-tight flex items-center gap-2">
                        <i data-lucide="truck" class="w-5 h-5 text-emerald-600"></i>
                        <span>VENDOR PURCHASE REPORT</span>
                    </h2>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5 flex items-center gap-1.5 flex-wrap">
                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                        <span>Period: <strong class="text-slate-800 font-bold">{{ $formattedRange }}</strong></span>
                        @if($activePurchaserObj)
                            <span class="text-slate-300">&bull;</span>
                            <span>Purchaser: <strong class="text-slate-800 font-bold">{{ $activePurchaserObj->name }}</strong></span>
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    {{-- VIEW SWITCHER: Table View / Card View --}}
                    <div class="inline-flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200/80 text-xs font-bold" role="group" aria-label="View Switcher">
                        <button type="button"
                                @click="setViewMode('table')"
                                :class="viewMode === 'table' ? 'bg-white text-slate-950 shadow-2xs font-extrabold border border-slate-200/80' : 'text-slate-600 hover:text-slate-900 border border-transparent'"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition-all duration-150 cursor-pointer">
                            <i data-lucide="table" class="w-3.5 h-3.5" :class="viewMode === 'table' ? 'text-emerald-600' : 'text-slate-400'"></i>
                            <span>Table View</span>
                        </button>
                        <button type="button"
                                @click="setViewMode('card')"
                                :class="viewMode === 'card' ? 'bg-white text-slate-950 shadow-2xs font-extrabold border border-slate-200/80' : 'text-slate-600 hover:text-slate-900 border border-transparent'"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition-all duration-150 cursor-pointer">
                            <i data-lucide="layout-grid" class="w-3.5 h-3.5" :class="viewMode === 'card' ? 'text-emerald-600' : 'text-slate-400'"></i>
                            <span>Card View</span>
                        </button>
                    </div>

                    <label class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 cursor-pointer select-none bg-white border border-slate-200 px-2.5 py-1.5 rounded-xl shadow-2xs hover:bg-slate-50 transition">
                        <input type="checkbox"
                               x-model="includeFullDetails"
                               class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 h-3.5 w-3.5">
                        <span>Include Full Details</span>
                    </label>
                    <a :href="exportPdfUrl"
                       href="{{ route('admin.cashbook.finance.purchase.vendors.export.pdf', $purchaseContext) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-black uppercase tracking-wider text-slate-700 shadow-xs hover:bg-slate-50 hover:border-slate-400 transition cursor-pointer"
                       title="Export all qualifying vendors to PDF">
                        <i data-lucide="file-text" class="w-3.5 h-3.5 text-rose-600"></i>
                        <span>Export PDF</span>
                    </a>
                    <a :href="exportExcelUrl"
                       href="{{ route('admin.cashbook.finance.purchase.vendors.export.excel', $purchaseContext) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-black uppercase tracking-wider text-slate-700 shadow-xs hover:bg-slate-50 hover:border-slate-400 transition cursor-pointer"
                       title="Export all qualifying vendors to Excel (.xlsx)">
                        <i data-lucide="sheet" class="w-3.5 h-3.5 text-emerald-600"></i>
                        <span>Export Excel</span>
                    </a>
                </div>
            </div>

            {{-- TOOLBAR: Smooth Segmented Switch (All / Cash / Credit) & Vendor Search Input --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-50 p-2.5 rounded-2xl border border-slate-200/70">
                <!-- Smooth Cash / Credit / All Switch -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 w-full sm:w-auto">
                    <div class="inline-flex items-center bg-slate-200/70 p-1 rounded-xl text-xs font-extrabold relative w-full sm:w-auto justify-between sm:justify-start">
                        <button type="button"
                                @click="setPayment('all')"
                                :class="activePayment === 'all' ? 'bg-white text-slate-950 shadow-xs font-black' : 'text-slate-600 hover:text-slate-900'"
                                class="flex-1 sm:flex-initial text-center justify-center px-3 sm:px-3.5 py-1.5 rounded-lg transition-all duration-200 ease-in-out cursor-pointer">
                            All
                        </button>
                        <button type="button"
                                @click="setPayment('cash')"
                                :class="activePayment === 'cash' ? 'bg-emerald-600 text-white shadow-xs font-black' : 'text-slate-600 hover:text-slate-900'"
                                class="flex-1 sm:flex-initial justify-center px-3 sm:px-3.5 py-1.5 rounded-lg transition-all duration-200 ease-in-out flex items-center gap-1.5 cursor-pointer">
                            <span class="w-2 h-2 rounded-full transition-colors duration-200"
                                  :class="activePayment === 'cash' ? 'bg-white' : 'bg-emerald-500'"></span>
                            <span>Cash</span>
                        </button>
                        <button type="button"
                                @click="setPayment('credit')"
                                :class="activePayment === 'credit' ? 'bg-slate-900 text-white shadow-xs font-black' : 'text-slate-600 hover:text-slate-900'"
                                class="flex-1 sm:flex-initial justify-center px-3 sm:px-3.5 py-1.5 rounded-lg transition-all duration-200 ease-in-out flex items-center gap-1.5 cursor-pointer">
                            <span class="w-2 h-2 rounded-full transition-colors duration-200"
                                  :class="activePayment === 'credit' ? 'bg-emerald-400' : 'bg-rose-500'"></span>
                            <span>Credit</span>
                        </button>
                    </div>

                    <span class="text-xs font-bold text-slate-500 pl-0.5 sm:pl-1 whitespace-nowrap"
                          x-text="`Showing ${totalVisible} of ${totalMatching} ${totalMatching === 1 ? 'vendor' : 'vendors'}`">
                        Showing {{ min(20, $vendorsOverviewList->count()) }} of {{ $vendorsOverviewList->count() }} vendors
                    </span>
                </div>

                {{-- Live vendor filter box --}}
                <div class="relative w-full sm:w-72">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="search" class="w-3.5 h-3.5"></i>
                    </div>
                    <input type="text"
                           x-model="search"
                           @input.debounce.150ms="limit = 20; applyFilters()"
                           placeholder="Filter vendor or product..."
                           class="w-full pl-9 pr-8 py-1.5 text-xs font-bold text-slate-800 bg-white border border-slate-300 rounded-xl focus:outline-hidden focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 shadow-2xs transition placeholder:text-slate-400 placeholder:font-normal">
                    <button type="button"
                            x-show="search.length > 0"
                            x-cloak
                            @click="search = ''; limit = 20; applyFilters()"
                            class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-700 cursor-pointer"
                            title="Clear search">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            </div>

            @if($vendorsOverviewList->isNotEmpty())
                {{-- TABLE VIEW (DEFAULT) --}}
                <div x-show="viewMode === 'table'" class="overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-2xs">
                    @include('admin.cashbook.finance.purchase._vendor-table', [
                        'vendors' => $vendorsOverviewList,
                        'purchaseContext' => $purchaseContext,
                    ])
                </div>

                {{-- CARD VIEW (ALTERNATIVE) --}}
                <div x-show="viewMode === 'card'" x-cloak class="space-y-2.5" x-ref="vendorList">
                    @foreach($vendorsOverviewList as $index => $vendor)
                        @php
                            $vCash = (float) ($vendor->cash_purchase ?? 0);
                            $vCredit = (float) ($vendor->credit_purchase ?? 0);
                            $vName = strtolower($vendor->supplier_name ?? '');
                            $vTags = strtolower($vendor->category_tags ?? '');
                        @endphp
                        <div class="vendor-card-wrapper transition-all duration-200 {{ $index >= 20 ? 'hidden' : '' }}"
                             data-cash="{{ $vCash }}"
                             data-credit="{{ $vCredit }}"
                             data-name="{{ $vName }}"
                             data-tags="{{ $vTags }}"
                             style="{{ $index >= 20 ? 'display: none;' : '' }}">
                            @include('admin.cashbook.finance.purchase._vendor-invoice-card', [
                                'vendor' => $vendor,
                                'serialNumber' => $index + 1,
                                'purchaseContext' => $purchaseContext,
                                'formattedRange' => $formattedRange,
                                'periodStart' => $periodStart,
                                'periodEnd' => $periodEnd,
                            ])
                        </div>
                    @endforeach
                </div>

                {{-- Load More Action Button --}}
                <div x-show="totalMatching > limit" x-cloak class="pt-2 text-center">
                    <button type="button"
                            @click="loadMore()"
                            class="inline-flex items-center gap-2 rounded-2xl bg-white hover:bg-slate-50 border border-slate-300 text-slate-800 hover:text-slate-950 px-6 py-2.5 text-xs font-black uppercase tracking-wider shadow-xs hover:shadow-sm transition-all duration-200 cursor-pointer active:scale-98">
                        <i data-lucide="chevron-down" class="w-4 h-4 text-emerald-600"></i>
                        <span>Load More Vendors</span>
                        <span class="rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 text-[10px] font-black"
                              x-text="`+${Math.min(step, totalMatching - limit)} (${totalVisible}/${totalMatching})`"></span>
                    </button>
                </div>

                {{-- Empty Search / Filter Feedback --}}
                <div x-show="totalMatching === 0" x-cloak
                     class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/70 p-6 text-center text-xs font-semibold text-slate-500 space-y-2">
                    <p>No vendors match the current filter or search criteria.</p>
                    <button type="button"
                            @click="search = ''; setPayment('all')"
                            class="inline-flex items-center gap-1 text-emerald-700 hover:text-emerald-900 font-bold hover:underline cursor-pointer">
                        <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                        <span>Reset vendor search & filters</span>
                    </button>
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/70 p-6 text-center text-xs font-semibold text-slate-500">
                    No vendor purchases recorded for this period.
                </div>
            @endif
        </div>
    </section>

    <!-- SECTION C: PURCHASER-WISE SUMMARY TABLE (WITH SORTABLE HEADERS) -->
    <section class="rounded-3xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-xs space-y-4" aria-label="Purchaser-Wise Summary">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <i data-lucide="users" class="w-4 h-4 text-emerald-600"></i>
                    Purchaser-Wise Summary &bull; Purchasers Overview
                </h2>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">
                    Click table headers to sort &bull; Click any Cash, Credit, or Total amount below to inspect individual bills.
                </p>
            </div>

            @if($activePurchaserId || $activePayment !== 'all')
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-500">Filtered view active:</span>
                    <a href="{{ route('admin.cashbook.finance.purchase', array_merge($baseParams, ['purchaser_id' => null, 'payment' => 'all'])) }}#bills-section"
                       class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">
                        <i data-lucide="x" class="w-3 h-3 text-slate-500"></i>
                        Clear Purchaser / Type Filter
                    </a>
                </div>
            @endif
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200/70"
             x-data="{
                 sortCol: 3,
                 sortAsc: false,
                 sortTable(colIndex, type = 'num') {
                     const table = (this.$el && this.$el.closest ? this.$el.closest('table') : null) || (this.$root ? this.$root.querySelector('table') : null);
                     if (!table) return;
                     const tbody = table.querySelector('tbody');
                     if (!tbody) return;
                     const rows = Array.from(tbody.querySelectorAll('tr:not(.no-sort)'));
                     if (rows.length <= 1) return;

                     if (this.sortCol === colIndex) {
                         this.sortAsc = !this.sortAsc;
                     } else {
                         this.sortCol = colIndex;
                         this.sortAsc = (type === 'num' ? false : true);
                     }

                     rows.sort((a, b) => {
                         const cellA = a.children[colIndex];
                         const cellB = b.children[colIndex];
                         if (!cellA || !cellB) return 0;

                         let valA = cellA.getAttribute('data-value') ?? cellA.innerText.trim();
                         let valB = cellB.getAttribute('data-value') ?? cellB.innerText.trim();

                         if (type === 'num') {
                             const numA = parseFloat(valA.replace(/[^\d.-]/g, '')) || 0;
                             const numB = parseFloat(valB.replace(/[^\d.-]/g, '')) || 0;
                             return this.sortAsc ? numA - numB : numB - numA;
                         }

                         return this.sortAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
                     });

                     rows.forEach(row => tbody.appendChild(row));
                 }
             }">
            <table class="w-full min-w-[36rem] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-600 border-b border-slate-200/80">
                    <tr>
                        <th @click="sortTable(0, 'text')" class="p-3.5 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Purchaser Name">
                            <div class="flex items-center gap-1.5">
                                <span>Purchaser</span>
                                <span class="text-[9px]" :class="sortCol === 0 ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 0 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable(1, 'num')" class="p-3.5 text-right cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Cash Purchase">
                            <div class="flex items-center justify-end gap-1.5">
                                <span>Cash Purchase</span>
                                <span class="text-[9px]" :class="sortCol === 1 ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 1 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable(2, 'num')" class="p-3.5 text-right cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Credit Purchase">
                            <div class="flex items-center justify-end gap-1.5">
                                <span>Credit Purchase</span>
                                <span class="text-[9px]" :class="sortCol === 2 ? 'text-amber-700 font-black' : 'text-slate-400'" x-text="sortCol === 2 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable(3, 'num')" class="p-3.5 text-right cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Total Purchase">
                            <div class="flex items-center justify-end gap-1.5">
                                <span>Total Purchase</span>
                                <span class="text-[9px]" :class="sortCol === 3 ? 'text-slate-900 font-black' : 'text-slate-400'" x-text="sortCol === 3 ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    @forelse($allPurchasers as $purchaser)
                        @php
                            $isRowActive = (int) $activePurchaserId === (int) $purchaser->purchaser_id;
                        @endphp
                        <tr class="transition {{ $isRowActive ? 'bg-emerald-50/60 font-semibold' : 'hover:bg-slate-50/80' }}">
                            <td class="p-3.5" data-value="{{ $purchaser->purchaser_name ?? 'Unassigned' }}">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ strtoupper(substr($purchaser->purchaser_name ?? 'U', 0, 1)) }}
                                    </div>
                                    <div>
                                        @if(!empty($purchaser->purchaser_public_uuid))
                                            <a href="{{ route('admin.cashbook.finance.purchase.purchasers.show', ['purchaser' => $purchaser->purchaser_public_uuid] + $purchaseContext) }}"
                                               class="font-bold text-slate-900 hover:text-emerald-700 transition">
                                                {{ $purchaser->purchaser_name ?? 'Unassigned' }}
                                            </a>
                                        @else
                                            <span class="font-bold text-slate-900 block">
                                                {{ $purchaser->purchaser_name ?? 'Unassigned' }}
                                            </span>
                                        @endif
                                        <span class="text-[11px] text-slate-500 font-medium block">
                                            {{ number_format((int) $purchaser->invoice_count) }} bills
                                        </span>
                                    </div>
                                    @if($isRowActive)
                                        <span class="ml-2 inline-flex items-center rounded-md bg-emerald-100 px-1.5 py-0.5 text-[9px] font-black uppercase text-emerald-800">
                                            Active Filter
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Clickable Cash Purchase -->
                            <td class="p-3.5 text-right" data-value="{{ (float) $purchaser->cash_purchase }}">
                                <a href="{{ route('admin.cashbook.finance.purchase', array_merge($baseParams, ['purchaser_id' => $purchaser->purchaser_id, 'payment' => 'cash'])) }}#bills-section"
                                   title="Click to view Cash Bills for {{ $purchaser->purchaser_name }}"
                                   class="font-mono font-bold text-emerald-700 hover:text-emerald-950 hover:underline inline-block tabular-nums transition py-1 px-2 rounded-lg hover:bg-emerald-100/50">
                                    ₹{{ number_format((float) $purchaser->cash_purchase, 2) }}
                                </a>
                            </td>

                            <!-- Clickable Credit Purchase -->
                            <td class="p-3.5 text-right" data-value="{{ (float) $purchaser->credit_purchase }}">
                                <a href="{{ route('admin.cashbook.finance.purchase', array_merge($baseParams, ['purchaser_id' => $purchaser->purchaser_id, 'payment' => 'credit'])) }}#bills-section"
                                   title="Click to view Credit Bills for {{ $purchaser->purchaser_name }}"
                                   class="font-mono font-bold text-amber-700 hover:text-amber-950 hover:underline inline-block tabular-nums transition py-1 px-2 rounded-lg hover:bg-amber-100/50">
                                    ₹{{ number_format((float) $purchaser->credit_purchase, 2) }}
                                </a>
                            </td>

                            <!-- Clickable Total Purchase -->
                            <td class="p-3.5 text-right" data-value="{{ (float) $purchaser->total_purchase }}">
                                <a href="{{ route('admin.cashbook.finance.purchase', array_merge($baseParams, ['purchaser_id' => $purchaser->purchaser_id, 'payment' => 'all'])) }}#bills-section"
                                   title="Click to view All Bills for {{ $purchaser->purchaser_name }}"
                                   class="font-mono font-black text-slate-950 hover:text-emerald-700 hover:underline inline-block tabular-nums transition py-1 px-2 rounded-lg hover:bg-slate-100">
                                    ₹{{ number_format((float) $purchaser->total_purchase, 2) }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr class="no-sort">
                            <td colspan="4" class="p-8 text-center text-xs font-semibold text-slate-400">
                                No purchases recorded for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-50/90 font-black text-xs border-t-2 border-slate-200">
                    <tr>
                        <td class="p-3.5 uppercase tracking-wider text-slate-800">TOTAL</td>
                        <td class="p-3.5 text-right font-mono text-emerald-800 tabular-nums">
                            <a href="{{ route('admin.cashbook.finance.purchase', array_merge($baseParams, ['purchaser_id' => null, 'payment' => 'cash'])) }}#bills-section"
                               title="Click to view all Cash bills"
                               class="hover:underline py-1 px-2 rounded-lg hover:bg-emerald-100/50">
                                ₹{{ number_format((float) $summary->cash_purchase, 2) }}
                            </a>
                        </td>
                        <td class="p-3.5 text-right font-mono text-amber-800 tabular-nums">
                            <a href="{{ route('admin.cashbook.finance.purchase', array_merge($baseParams, ['purchaser_id' => null, 'payment' => 'credit'])) }}#bills-section"
                               title="Click to view all Credit bills"
                               class="hover:underline py-1 px-2 rounded-lg hover:bg-amber-100/50">
                                ₹{{ number_format((float) $summary->credit_purchase, 2) }}
                            </a>
                        </td>
                        <td class="p-3.5 text-right font-mono text-slate-950 tabular-nums text-sm">
                            <a href="{{ route('admin.cashbook.finance.purchase', array_merge($baseParams, ['purchaser_id' => null, 'payment' => 'all'])) }}#bills-section"
                               title="Click to view all bills"
                               class="hover:underline py-1 px-2 rounded-lg hover:bg-slate-200/50">
                                ₹{{ number_format((float) $summary->total_purchase, 2) }}
                            </a>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <!-- SECTION D: RECENT PURCHASES • BILLS & TRANSACTIONS (WITH SEARCH & SORTABLE HEADERS) -->
    <section id="bills-section"
             class="rounded-3xl border border-slate-200/80 bg-white p-5 sm:p-6 shadow-xs space-y-4"
             aria-label="Purchase Bills and Line Items"
             x-data="{
                 searchQuery: '{{ addslashes($filters['search'] ?? '') }}',
                 sortCol: 'date',
                 sortAsc: false,
                 matchedCount: {{ $bills->count() }},
                 init() {
                     if (this.searchQuery) {
                         this.filterRows();
                     }
                 },
                 filterRows() {
                     const q = this.searchQuery.toLowerCase().trim();
                     const root = this.$root || document.getElementById('bills-section');
                     if (!root) return;
                     const groups = Array.from(root.querySelectorAll('.bill-group'));
                     let count = 0;
                     groups.forEach(group => {
                         const text = group.getAttribute('data-search-text') || '';
                         const matches = !q || text.includes(q);
                         group.style.display = matches ? '' : 'none';
                         if (matches) count++;
                     });
                     this.matchedCount = count;
                 },
                 sortTable(colKey, type = 'text') {
                     const table = (this.$el && this.$el.closest ? this.$el.closest('table') : null) || document.getElementById('bills-table');
                     if (!table) return;
                     const groups = Array.from(table.querySelectorAll('.bill-group'));
                     if (groups.length <= 1) return;

                     if (this.sortCol === colKey) {
                         this.sortAsc = !this.sortAsc;
                     } else {
                         this.sortCol = colKey;
                         this.sortAsc = (type === 'date' || type === 'num' ? false : true);
                     }

                     groups.sort((a, b) => {
                         let valA = a.getAttribute('data-' + colKey) || '';
                         let valB = b.getAttribute('data-' + colKey) || '';

                         if (type === 'num') {
                             const numA = parseFloat(valA) || 0;
                             const numB = parseFloat(valB) || 0;
                             return this.sortAsc ? numA - numB : numB - numA;
                         }

                         if (type === 'date') {
                             return this.sortAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
                         }

                         return this.sortAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
                     });

                     groups.forEach(group => table.appendChild(group));
                 }
             }">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <i data-lucide="receipt" class="w-4 h-4 text-emerald-600"></i>
                    <span>Recent Purchases &bull; Bills &amp; Transactions</span>
                </h2>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">
                    @if($activePurchaserObj)
                        Showing purchases for <strong class="text-slate-900">{{ $activePurchaserObj->name }}</strong>
                        @if($activePayment !== 'all')
                            &bull; <strong class="text-emerald-700 uppercase">{{ $activePayment }} Only</strong>
                        @endif
                        &bull; ({{ $bills->total() }} bills found)
                    @elseif($activePayment !== 'all')
                        Showing <strong class="text-emerald-700 uppercase">{{ $activePayment }} Purchases</strong> across all purchasers &bull; ({{ $bills->total() }} bills found)
                    @else
                        All recorded purchase transactions for the selected period &bull; ({{ $bills->total() }} bills found)
                    @endif
                </p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <!-- Real-time bill filter: bill number, vendor, purchaser, product or any text -->
                <form method="GET" action="{{ route('admin.cashbook.finance.purchase', array_merge($baseParams, ['purchaser_id' => $activePurchaserId, 'payment' => $activePayment])) }}#bills-section" class="relative flex items-center">
                    <input type="hidden" name="period" value="{{ $period }}">
                    <input type="hidden" name="period_mode" value="{{ $periodMode }}">
                    <input type="hidden" name="month" value="{{ $month }}">
                    @if($activePurchaserId)
                        <input type="hidden" name="purchaser_id" value="{{ $activePurchaserId }}">
                    @endif
                    @if($activePayment !== 'all')
                        <input type="hidden" name="payment" value="{{ $activePayment }}">
                    @endif
                    @if(!empty($selectedProductFilter))
                        <input type="hidden" name="product_filter" value="{{ $selectedProductFilter }}">
                    @endif

                    <div class="relative w-full sm:w-80">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </span>
                        <input type="text"
                               name="search"
                               x-model="searchQuery"
                               @input="filterRows()"
                               placeholder="Filter bill #, vendor, purchaser, item..."
                               class="w-full rounded-xl border border-slate-300 bg-white pl-9 pr-8 py-2 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:border-emerald-600 focus:outline-hidden focus:ring-1 focus:ring-emerald-600 transition shadow-2xs">
                        <button type="button"
                                x-show="searchQuery"
                                @click="searchQuery = ''; filterRows()"
                                class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-400 hover:text-slate-600"
                                title="Clear filter">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </form>

                @if($activePurchaserId || $activePayment !== 'all' || !empty($filters['search']))
                    <a href="{{ route('admin.cashbook.finance.purchase', array_merge($baseParams, ['purchaser_id' => null, 'payment' => 'all', 'search' => null])) }}#bills-section"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-black uppercase tracking-wider text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                        <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                        <span>Show All Bills</span>
                    </a>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200/70">
            <table id="bills-table" class="w-full min-w-[50rem] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-600 border-b border-slate-200/80">
                    <tr>
                        <th class="p-3.5 text-center w-12 font-black uppercase text-[10px] tracking-wider text-slate-600 select-none">SL No</th>
                        <th @click="sortTable('date', 'date')" class="p-3.5 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Date">
                            <div class="flex items-center gap-1.5">
                                <span>Date</span>
                                <span class="text-[9px]" :class="sortCol === 'date' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'date' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable('ref', 'text')" class="p-3.5 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Bill Ref">
                            <div class="flex items-center gap-1.5">
                                <span>Bill Ref</span>
                                <span class="text-[9px]" :class="sortCol === 'ref' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'ref' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable('vendor', 'text')" class="p-3.5 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Vendor">
                            <div class="flex items-center gap-1.5">
                                <span>Vendor</span>
                                <span class="text-[9px]" :class="sortCol === 'vendor' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'vendor' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable('purchaser', 'text')" class="p-3.5 cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Purchaser">
                            <div class="flex items-center gap-1.5">
                                <span>Purchaser</span>
                                <span class="text-[9px]" :class="sortCol === 'purchaser' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'purchaser' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable('type', 'text')" class="p-3.5 text-center cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Payment Type">
                            <div class="flex items-center justify-center gap-1.5">
                                <span>Type</span>
                                <span class="text-[9px]" :class="sortCol === 'type' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'type' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable('amount', 'num')" class="p-3.5 text-right cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Amount">
                            <div class="flex items-center justify-end gap-1.5">
                                <span>Amount</span>
                                <span class="text-[9px]" :class="sortCol === 'amount' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'amount' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th @click="sortTable('status', 'text')" class="p-3.5 text-center cursor-pointer hover:bg-slate-100 transition select-none" title="Click to sort by Status">
                            <div class="flex items-center justify-center gap-1.5">
                                <span>Status</span>
                                <span class="text-[9px]" :class="sortCol === 'status' ? 'text-emerald-700 font-black' : 'text-slate-400'" x-text="sortCol === 'status' ? (sortAsc ? '▲' : '▼') : '↕'"></span>
                            </div>
                        </th>
                        <th class="p-3.5 text-center">Items</th>
                    </tr>
                </thead>
                @forelse($bills as $index => $bill)
                    @php
                        $isCredit = strtolower((string) ($bill->payment_class ?? '')) === 'credit';
                        $invoiceItems = $bill->items ?? collect();
                        $statusStr = (string) ($bill->invoice_status ?? $bill->payment_status ?? 'approved');
                        $billSearchText = strtolower(($bill->invoice_number ?: (string)$bill->invoice_id) . ' ' . ($bill->supplier_name ?? '') . ' ' . ($bill->purchaser_name ?? '') . ' ' . ($bill->payment_class ?? '') . ' ' . ($bill->total_purchase ?? '') . ' ' . $statusStr . ' ' . $invoiceItems->pluck('product_name')->join(' '));
                    @endphp
                    <tbody class="bill-group divide-y divide-slate-100 border-b border-slate-100 last:border-b-0"
                           x-data="{ open: false }"
                           data-date="{{ $bill->business_date }}"
                           data-ref="{{ strtolower($bill->invoice_number ?: (string)$bill->invoice_id) }}"
                           data-vendor="{{ strtolower($bill->supplier_name ?? '') }}"
                           data-purchaser="{{ strtolower($bill->purchaser_name ?? '') }}"
                           data-type="{{ strtolower($bill->payment_class ?? '') }}"
                           data-amount="{{ (float) $bill->total_purchase }}"
                           data-status="{{ strtolower($statusStr) }}"
                           data-search-text="{{ $billSearchText }}">
                        <tr class="transition hover:bg-slate-50/70">
                            <!-- Main Bill Row Content -->
                            <td class="p-3.5 text-center font-mono font-bold text-slate-500 tabular-nums text-xs">
                                {{ str_pad((string) (($bills instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $bills->firstItem() : 1) + $index), 2, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="p-3.5 font-mono font-bold text-slate-700 tabular-nums">
                                {{ \Illuminate\Support\Carbon::parse($bill->business_date)->format('d M Y') }}
                            </td>
                            <td class="p-3.5 font-bold">
                                <a href="{{ route('purchasing.invoices.show', $bill->invoice_public_uuid) }}"
                                   class="text-emerald-700 hover:text-emerald-950 hover:underline font-mono"
                                   title="Open canonical purchase invoice page">
                                    {{ $bill->invoice_number ?: 'Invoice #'.$bill->invoice_id }}
                                </a>
                            </td>
                            <td class="p-3.5 font-semibold text-slate-900">
                                @if(!empty($bill->supplier_public_uuid))
                                    <a href="{{ route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $bill->supplier_public_uuid] + $purchaseContext) }}"
                                       class="font-semibold text-slate-900 hover:text-emerald-700 transition">
                                        {{ $bill->supplier_name ?? 'Vendor' }}
                                    </a>
                                @else
                                    {{ $bill->supplier_name ?? 'Vendor' }}
                                @endif
                            </td>
                            <td class="p-3.5 font-medium text-slate-800">
                                {{ $bill->purchaser_name ?? 'Purchaser' }}
                            </td>
                            <td class="p-3.5 text-center">
                                @if($isCredit)
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-extrabold uppercase text-amber-800 border border-amber-200">
                                        Credit
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-extrabold uppercase text-emerald-800 border border-emerald-200">
                                        Cash
                                    </span>
                                @endif
                            </td>
                            <td class="p-3.5 text-right font-mono font-black text-slate-950 tabular-nums text-sm">
                                ₹{{ number_format((float) $bill->total_purchase, 2) }}
                            </td>
                            <td class="p-3.5 text-center">
                                @if(in_array(strtolower($statusStr), ['paid', 'approved'], true))
                                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200">
                                        {{ ucfirst($statusStr) }}
                                    </span>
                                @elseif(strtolower($statusStr) === 'partial')
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-800 border border-amber-200">
                                        Partial
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700 border border-slate-200">
                                        {{ ucfirst(str_replace('_', ' ', $statusStr)) }}
                                    </span>
                                @endif
                            </td>
                            <td class="p-3.5 text-center">
                                <button type="button"
                                        @click="open = !open"
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-extrabold text-slate-700 hover:bg-slate-100 transition cursor-pointer">
                                    <span x-text="open ? 'Hide' : 'Items ({{ $invoiceItems->count() }})'">Items ({{ $invoiceItems->count() }})</span>
                                    <svg class="h-3 w-3 transition-transform" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <!-- Expandable Line Items Drilldown Row -->
                        <tr x-show="open" x-cloak class="bg-slate-50/90 border-t border-slate-200">
                            <td colspan="9" class="p-4 sm:p-5">
                                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs space-y-3">
                                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="font-extrabold text-xs text-slate-900">
                                                Line Items — {{ $bill->invoice_number }}
                                            </span>
                                            <span class="text-[11px] text-slate-500 font-medium">
                                                (Vendor: {{ $bill->supplier_name }} &bull; Purchaser: {{ $bill->purchaser_name }})
                                            </span>
                                        </div>
                                        <a href="{{ route('purchasing.invoices.show', $bill->invoice_public_uuid) }}"
                                           target="_blank"
                                           class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 hover:underline">
                                            <span>Open Full Bill</span>
                                            <i data-lucide="external-link" class="w-3 h-3"></i>
                                        </a>
                                    </div>

                                    @if($invoiceItems->isNotEmpty())
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-left text-xs">
                                                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 rounded-lg">
                                                    <tr>
                                                        <th class="p-2.5">Product</th>
                                                        <th class="p-2.5 text-center">Grade</th>
                                                        <th class="p-2.5 text-right">Quantity</th>
                                                        <th class="p-2.5 text-right">Rate</th>
                                                        <th class="p-2.5 text-right">Line Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    @foreach($invoiceItems as $item)
                                                        <tr class="hover:bg-slate-50/50">
                                                            <td class="p-2.5 font-bold text-slate-900">
                                                                <div>{{ $item->product_name }}</div>
                                                                @if(!empty($item->category_name))
                                                                    <div class="text-[10px] text-slate-400 font-normal">
                                                                        @if(!empty($item->category_id))
                                                                            <a href="{{ route('admin.cashbook.finance.purchase.categories.show', ['category' => $item->category_id] + $purchaseContext) }}"
                                                                               class="hover:text-emerald-700 underline">
                                                                                {{ $item->category_name }}
                                                                            </a>
                                                                        @else
                                                                            {{ $item->category_name }}
                                                                        @endif
                                                                    </div>
                                                                @endif
                                                            </td>
                                                            <td class="p-2.5 text-center text-slate-600">
                                                                @if(!empty($item->grade))
                                                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-700">
                                                                        Grade {{ $item->grade }}
                                                                    </span>
                                                                @else
                                                                    <span class="text-slate-400">-</span>
                                                                @endif
                                                            </td>
                                                            <td class="p-2.5 text-right font-mono font-bold text-slate-800 tabular-nums">
                                                                {{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}
                                                            </td>
                                                            <td class="p-2.5 text-right font-mono text-slate-600 tabular-nums">
                                                                ₹{{ number_format((float) $item->unit_price, 2) }}
                                                            </td>
                                                            <td class="p-2.5 text-right font-mono font-black text-slate-950 tabular-nums">
                                                                ₹{{ number_format((float) $item->line_total, 2) }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot class="border-t border-slate-200 bg-slate-50/70 font-black text-xs">
                                                    <tr>
                                                        <td colspan="4" class="p-2.5 text-right uppercase tracking-wider text-slate-600">
                                                            Final Bill Total
                                                        </td>
                                                        <td class="p-2.5 text-right font-mono text-slate-950 tabular-nums">
                                                            ₹{{ number_format((float) $bill->total_purchase, 2) }}
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    @else
                                        <p class="py-4 text-center text-xs font-semibold text-slate-400">
                                            No specific line items recorded on this cart.
                                        </p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="9" class="p-8 text-center text-xs font-semibold text-slate-400">
                                No bills found matching the current filters.
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </table>

            <!-- Real-time filter empty state -->
            <div x-show="matchedCount === 0" x-cloak class="p-8 text-center text-xs font-semibold text-slate-400 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200 m-4">
                No bills match "<span class="font-bold text-slate-700" x-text="searchQuery"></span>" on this page.
                <button type="button" @click="searchQuery = ''; filterRows()" class="ml-2 text-emerald-700 font-bold hover:underline cursor-pointer">Clear filter</button>
            </div>
        </div>

        <!-- Pagination Controls -->
        @if($bills instanceof \Illuminate\Pagination\LengthAwarePaginator && $bills->hasPages())
            <div class="pt-3">
                {{ $bills->fragment('bills-section')->links() }}
            </div>
        @endif
    </section>

    {{-- Single Bill Payment Update Modal (reusing Vendor Credit workflow) --}}
    @include('admin.cashbook.finance.purchase._payment-modal')

    {{-- Flash Toast for Payment Success --}}
    <div x-data="{
            showToast: false,
            message: '',
            init() {
                const flash = sessionStorage.getItem('purchase_payment_flash_success');
                if (flash) {
                    this.message = flash;
                    this.showToast = true;
                    sessionStorage.removeItem('purchase_payment_flash_success');
                    setTimeout(() => { this.showToast = false; }, 6000);
                }
            }
         }"
         x-show="showToast"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed bottom-5 right-5 z-50 flex items-center gap-3 rounded-2xl bg-emerald-950 text-white px-5 py-3.5 shadow-2xl border border-emerald-700/80">
        <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
            <i data-lucide="check-circle" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="text-[10px] font-black uppercase tracking-wider text-emerald-300">Payment Updated</div>
            <div class="text-xs font-bold" x-text="message"></div>
        </div>
        <button type="button" @click="showToast = false" class="ml-2 text-emerald-300 hover:text-white cursor-pointer">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>

</div>
@endsection
