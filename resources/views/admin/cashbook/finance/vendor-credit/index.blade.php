@extends('admin.cashbook.layouts.app')

@php
    $selectedProductFilter = $filters['product_filter'] ?? null;
    $activePurchaseTab = 'reports';
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

    $reportContext = [
        'month' => $month,
        'period_mode' => $periodMode,
    ];
    if ($selectedProductFilter) {
        $reportContext['product_filter'] = $selectedProductFilter;
    }
    if ($status && $status !== 'all') {
        $reportContext['status'] = $status;
    }
    if ($search) {
        $reportContext['search'] = $search;
    }
    if ($periodMode === 'day') {
        $reportContext['date'] = $periodStart;
    } elseif ($periodMode === 'custom') {
        $reportContext += ['from' => $periodStart, 'to' => $periodEnd];
    }
@endphp

@section('title', 'Credit Purchase Report — '.$monthTitle)

@section('header_title')
    <i data-lucide="truck" class="h-5 w-5 text-emerald-600"></i> Credit Purchase Report
@endsection

@section('header_subtitle')
    Credit purchases, vendor position, paid and outstanding liabilities &bull; {{ $monthTitle }}
@endsection

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-16">
    <!-- TOP NAVIGATION & SUBSECTIONS -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        @include('admin.cashbook.finance.purchase._nav')
        @include('admin.cashbook.finance.purchase.reports._tabs')
    </div>

    <!-- HEADER / FILTERS (SHOP CASHBOOK STYLE) -->
    <header class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-5" aria-label="Credit Purchase Report Header and Period Navigation">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-950 tracking-tight uppercase flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-emerald-600 text-white shadow-xs">
                            <i data-lucide="truck" class="w-4 h-4"></i>
                        </span>
                        Credit Purchase Report
                    </h1>
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-800 border border-emerald-200">
                        Vendor Liabilities
                    </span>
                    @if($selectedProductFilter)
                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-bold text-indigo-700 border border-indigo-200">
                            <i data-lucide="filter" class="w-3 h-3"></i>
                            Filtered: {{ $productFilters->firstWhere('uuid', $selectedProductFilter)?->name ?? 'Custom Filter' }}
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-xs font-semibold text-slate-500">
                    Credit purchases, vendor positions, paid and outstanding balances &bull; {{ $formattedRange }}
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.cashbook.finance.vendor-credit.settlements') }}"
                   class="inline-flex items-center gap-1.5 rounded-2xl bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wider text-white shadow-xs hover:bg-emerald-800 transition cursor-pointer">
                    <i data-lucide="history" class="w-4 h-4 text-emerald-200 shrink-0"></i>
                    <span>Vendor Settlement History</span>
                </a>
                <a href="{{ route('admin.cashbook.finance.journal', ['tab' => 'vendor_payment']) }}"
                   class="inline-flex items-center gap-1.5 rounded-2xl border border-slate-300 bg-white px-4 py-2 text-xs font-black uppercase tracking-wider text-slate-800 shadow-xs hover:border-slate-400 hover:bg-slate-50 transition cursor-pointer">
                    <i data-lucide="book-open-check" class="w-4 h-4 text-slate-500 shrink-0"></i>
                    <span>Payment Journal</span>
                </a>
                <a href="{{ route('admin.cashbook.finance.reconciliation') }}"
                   class="inline-flex items-center gap-1.5 rounded-2xl bg-slate-900 px-4 py-2 text-xs font-black uppercase tracking-wider text-white shadow-xs hover:bg-slate-800 transition cursor-pointer">
                    <i data-lucide="git-compare-arrows" class="w-4 h-4 shrink-0"></i>
                    <span>Reconciliation</span>
                </a>
            </div>
        </div>

        <!-- Period Selector Form & Switcher -->
        <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-3 sm:p-4" x-data="{ activeMode: '{{ $periodMode }}' }">
            <form method="GET" action="{{ route('admin.cashbook.finance.purchase.reports.credit-purchases') }}" class="flex flex-col gap-4">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="period_mode" :value="activeMode">

                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
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
                            <a href="{{ route('admin.cashbook.finance.purchase.reports.credit-purchases', array_merge($reportContext, ['month' => $prevMonth, 'period_mode' => 'month'])) }}"
                               class="p-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 transition" title="Previous Month">
                                &larr;
                            </a>
                            <span class="px-2.5 py-1 text-xs font-black text-slate-900 uppercase">
                                {{ $monthTitle }}
                            </span>
                            <a href="{{ route('admin.cashbook.finance.purchase.reports.credit-purchases', array_merge($reportContext, ['month' => $nextMonth, 'period_mode' => 'month'])) }}"
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
                </div>

                <!-- Secondary Filters: Status, Search, Apply & Reset -->
                <div class="flex flex-wrap items-center gap-3 pt-3 border-t border-slate-200/60">
                    <div class="flex items-center gap-1.5">
                        <label for="status_select" class="text-xs font-bold text-slate-600">Status:</label>
                        <select id="status_select"
                                name="status"
                                onchange="this.form.submit()"
                                class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden">
                            @foreach(['all' => 'All Statuses', 'unpaid' => 'Unpaid Only', 'partially_paid' => 'Partially Paid', 'paid' => 'Fully Settled'] as $value => $label)
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center flex-1 min-w-[200px] max-w-md">
                        <input type="text"
                               name="search"
                               value="{{ $search }}"
                               placeholder="Search vendor name or phone..."
                               class="w-full rounded-l-xl border border-r-0 border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 focus:border-emerald-600 focus:outline-hidden">
                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-r-xl border border-emerald-700 bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-800 transition cursor-pointer"
                                aria-label="Search"
                                title="Search">
                            <i data-lucide="search" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit" class="inline-flex items-center gap-1 rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800 transition cursor-pointer">
                            <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                            <span>Filter</span>
                        </button>
                        <a href="{{ route('admin.cashbook.finance.purchase.reports.credit-purchases') }}"
                           class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white p-1.5 text-slate-600 hover:bg-slate-100 transition"
                           aria-label="Reset filters"
                           title="Reset filters">
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </header>

    <!-- KPI METRICS (6-COL / 4-COL RESPONSIVE GRID) -->
    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Credit Purchase Key Metrics">
        <div class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs transition hover:shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Credit Purchases</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                    <i data-lucide="receipt" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="mt-2 font-mono text-2xl font-black tabular-nums text-slate-950">₹{{ number_format($kpi['total_invoiced'], 2) }}</div>
            <span class="mt-1 block text-xs font-bold text-slate-500">{{ number_format($kpi['invoice_count']) }} bills recorded</span>
        </div>

        <div class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs transition hover:shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-emerald-600">Total Settled / Paid</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                    <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="mt-2 font-mono text-2xl font-black tabular-nums text-emerald-700">₹{{ number_format($kpi['total_paid'], 2) }}</div>
            <span class="mt-1 block text-xs font-bold text-emerald-600">Company payments cleared</span>
        </div>

        <div class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs transition hover:shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-rose-600">Outstanding Payables</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-50 text-rose-700">
                    <i data-lucide="alert-circle" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="mt-2 font-mono text-2xl font-black tabular-nums text-rose-700">₹{{ number_format($kpi['total_outstanding'], 2) }}</div>
            <span class="mt-1 block text-xs font-bold text-rose-600">Pending settlement</span>
        </div>

        <div class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs transition hover:shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Active Vendors</span>
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                    <i data-lucide="truck" class="w-4 h-4"></i>
                </div>
            </div>
            <div class="mt-2 font-mono text-2xl font-black tabular-nums text-slate-900">{{ number_format($kpi['vendor_count']) }}</div>
            <span class="mt-1 block text-xs font-bold text-slate-500">Suppliers in this period</span>
        </div>
    </section>

    <!-- VENDOR CREDIT SUMMARY TABLE -->
    <section class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4" aria-label="Supplier Credit Payables Table">
        <div class="flex flex-col gap-2 border-b border-slate-100 pb-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-black text-slate-950 uppercase tracking-tight">Supplier Credit Payables</h2>
                <p class="mt-0.5 text-xs font-semibold text-slate-500">Credit purchases grouped by supplier with payment progress and outstanding liabilities.</p>
            </div>
            <span class="font-mono text-xs font-bold text-slate-400 bg-slate-100 px-3 py-1 rounded-xl">{{ $vendors->total() }} suppliers</span>
        </div>

        <!-- MOBILE CARDS -->
        <div class="space-y-3 lg:hidden">
            @forelse($vendors as $vendor)
                <a href="{{ route('admin.cashbook.finance.vendor-credit.show', $vendor['supplier_public_uuid']) }}" class="block rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 transition hover:bg-slate-100/80 shadow-xs">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="break-words text-sm font-black text-slate-950">{{ $vendor['supplier_name'] }}</div>
                            <div class="mt-0.5 text-xs font-semibold text-slate-500">{{ $vendor['invoice_count'] }} bills / {{ $vendor['supplier_contact'] }}</div>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-[9px] font-black uppercase {{ $vendor['total_outstanding'] > 0 ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }}">
                            {{ $vendor['total_outstanding'] > 0 ? 'Attention Needed' : 'Settled' }}
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                        <div class="rounded-xl bg-white p-2 border border-slate-200/80">
                            <span class="block text-[9px] font-black uppercase text-slate-400">Total</span>
                            <strong class="font-mono tabular-nums text-slate-900">₹{{ number_format($vendor['total_net'], 2) }}</strong>
                        </div>
                        <div class="rounded-xl bg-white p-2 border border-slate-200/80">
                            <span class="block text-[9px] font-black uppercase text-slate-400">Paid</span>
                            <strong class="font-mono tabular-nums text-emerald-700">₹{{ number_format($vendor['total_paid'], 2) }}</strong>
                        </div>
                        <div class="rounded-xl bg-white p-2 border border-slate-200/80">
                            <span class="block text-[9px] font-black uppercase text-slate-400">Due</span>
                            <strong class="font-mono tabular-nums text-rose-700">₹{{ number_format($vendor['total_outstanding'], 2) }}</strong>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between border-t border-slate-200/60 pt-2 text-[11px] font-bold text-slate-500">
                        <span>Oldest: {{ $vendor['oldest_date']?->format('d M Y') ?? '—' }}</span>
                        <span class="text-emerald-700 hover:underline inline-flex items-center gap-1">
                            View Splits <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                        </span>
                    </div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm font-bold text-slate-400">No vendor credit records found for the selected criteria.</div>
            @endforelse
        </div>

        <!-- DESKTOP TABLE -->
        <div class="hidden overflow-x-auto custom-scrollbar lg:block">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200/80 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                        <th class="px-4 py-3 rounded-l-xl">Vendor / Supplier</th>
                        <th class="px-3 py-3 text-center">Bills</th>
                        <th class="px-3 py-3 text-right">Total Purchases</th>
                        <th class="px-3 py-3 text-right">Paid Amount</th>
                        <th class="px-3 py-3 text-right">Outstanding</th>
                        <th class="px-3 py-3 text-center">Oldest Bill</th>
                        <th class="px-3 py-3 text-center">Last Bill</th>
                        <th class="px-3 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right rounded-r-xl">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($vendors as $vendor)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-4 py-3.5">
                                <a href="{{ route('admin.cashbook.finance.vendor-credit.show', $vendor['supplier_public_uuid']) }}" class="inline-flex items-center gap-1 text-sm font-extrabold text-emerald-700 hover:underline" aria-label="View vendor credit for {{ $vendor['supplier_name'] }}">
                                    {{ $vendor['supplier_name'] }}
                                    <i data-lucide="arrow-up-right" class="h-3.5 w-3.5"></i>
                                </a>
                                <div class="text-[11px] font-semibold text-slate-400">{{ $vendor['supplier_contact'] }}</div>
                            </td>
                            <td class="px-3 py-3.5 text-center font-mono font-bold text-slate-700">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $vendor['invoice_count'] }}</span>
                            </td>
                            <td class="px-3 py-3.5 text-right font-mono tabular-nums font-bold text-slate-900">₹{{ number_format($vendor['total_net'], 2) }}</td>
                            <td class="px-3 py-3.5 text-right font-mono tabular-nums font-bold text-emerald-700">₹{{ number_format($vendor['total_paid'], 2) }}</td>
                            <td class="px-3 py-3.5 text-right font-mono tabular-nums font-extrabold {{ $vendor['total_outstanding'] > 0 ? 'text-rose-700' : 'text-slate-400' }}">
                                ₹{{ number_format($vendor['total_outstanding'], 2) }}
                            </td>
                            <td class="px-3 py-3.5 text-center font-mono tabular-nums text-[11px] font-semibold text-slate-600">
                                {{ $vendor['oldest_date']?->format('Y-m-d') ?? '—' }}
                            </td>
                            <td class="px-3 py-3.5 text-center font-mono tabular-nums text-[11px] font-semibold text-slate-600">
                                {{ $vendor['last_date']?->format('Y-m-d') ?? '—' }}
                            </td>
                            <td class="px-3 py-3.5 text-center">
                                @if($vendor['total_outstanding'] > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200 px-2 py-0.5 text-[10px] font-black text-rose-700">
                                        Attention Needed
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-black text-emerald-700">
                                        Settled
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <a href="{{ route('admin.cashbook.finance.vendor-credit.show', $vendor['supplier_public_uuid']) }}" class="inline-flex items-center gap-1 rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800 transition">
                                    <span>View Details</span>
                                    <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-sm font-bold text-slate-400">No vendor credit records found for the selected criteria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $vendors->links() }}
        </div>
    </section>
</div>
@endsection
