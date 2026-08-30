@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop').($isDayDetail ? ' — '. \Illuminate\Support\Carbon::parse($businessDate)->format('d M Y') : ' — '. $monthlyData['month_title']))

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-12">

    @if(!$isDayDetail)
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- ── MONTHLY SHOP MONEY FLOW VIEW ──────────────────────────────── -->
        <!-- ══════════════════════════════════════════════════════════════════ -->

        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.cashbook.money-flow') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-extrabold transition-all">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                        <span>Money Flow</span>
                    </a>
                    <span class="text-slate-300">/</span>
                    <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold font-mono">
                        {{ $currentShop->code }}
                    </span>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 uppercase flex items-center gap-2">
                    <span>{{ $currentShop->name }}</span>
                    <span class="text-base font-normal text-slate-400 font-mono">&mdash; {{ $monthlyData['month_title'] }}</span>
                </h1>
                <p class="text-xs text-slate-500 font-medium">
                    Monthly operational collections and daily settlement rows for <span class="font-bold text-slate-800">{{ $currentShop->name }}</span>
                </p>
            </div>

            <!-- Month Switcher & Shop Controls -->
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Month Switcher -->
                <div class="inline-flex items-center rounded-xl bg-slate-100 p-1 border border-slate-200 text-xs font-extrabold">
                    <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'month' => $monthlyData['prev_month']]) }}"
                       class="p-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-white transition"
                       title="Previous Month">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </a>
                    <span class="px-3 py-1 font-mono text-slate-800">{{ $monthlyData['month_title'] }}</span>
                    <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'month' => $monthlyData['next_month']]) }}"
                       class="p-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-white transition"
                       title="Next Month">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </a>
                </div>

                <!-- Shop Dropdown Selector -->
                <div>
                    <select onchange="window.location.href='/admin/cashbook/shops/' + this.value + '?month={{ $month }}'"
                            class="bg-slate-50 text-xs font-bold text-slate-800 px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-600 cursor-pointer">
                        @foreach($shops as $shopOption)
                            <option value="{{ $shopOption->slug ?: $shopOption->shop_id }}" {{ $currentShop->shop_id == $shopOption->shop_id ? 'selected' : '' }}>
                                {{ $shopOption->name }} ({{ $shopOption->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Month KPI Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <!-- Total Collections -->
            <div class="p-5 rounded-3xl bg-white border border-slate-200 shadow-sm">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Month Collections</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-slate-900 mt-1">
                    ₹{{ number_format($monthlyData['summary']['total_collections'], 2) }}
                </div>
                <span class="text-[10px] text-slate-400 font-bold mt-1 block">{{ $monthlyData['summary']['active_days_count'] }} active days</span>
            </div>

            <!-- Company Received -->
            <div class="p-5 rounded-3xl bg-white border border-emerald-200 shadow-sm">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Company Received</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-emerald-800 mt-1">
                    ₹{{ number_format($monthlyData['summary']['company_received'], 2) }}
                </div>
                <span class="text-[10px] text-emerald-600 font-bold mt-1 block">Verified &amp; reconciled</span>
            </div>

            <!-- Pending Acceptance -->
            <div class="p-5 rounded-3xl bg-white border border-amber-200 shadow-sm">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-700">Pending Acceptance</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-amber-800 mt-1">
                    ₹{{ number_format($monthlyData['summary']['pending_acceptance'], 2) }}
                </div>
                <span class="text-[10px] text-amber-600 font-bold mt-1 block">Unapproved entries</span>
            </div>

            <!-- Pending Verification -->
            <div class="p-5 rounded-3xl bg-white border border-sky-200 shadow-sm">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-sky-700">Pending Verification</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-sky-800 mt-1">
                    ₹{{ number_format($monthlyData['summary']['pending_verification'], 2) }}
                </div>
                <span class="text-[10px] text-sky-600 font-bold mt-1 block">Awaiting company verification</span>
            </div>

            <!-- Month Outstanding -->
            <div class="p-5 rounded-3xl bg-slate-900 text-white border border-slate-800 shadow-sm col-span-2 sm:col-span-1">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Month Outstanding</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-white mt-1">
                    ₹{{ number_format($monthlyData['summary']['outstanding'], 2) }}
                </div>
                <span class="text-[10px] text-slate-400 font-bold mt-1 block">{{ $monthlyData['summary']['pending_count'] }} pending operations</span>
            </div>
        </div>

        <!-- Daily Summary Rows Table/List -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <i data-lucide="calendar" class="w-4 h-4 text-emerald-600"></i>
                    <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">
                        Daily Settlement Summaries &mdash; {{ $monthlyData['month_title'] }}
                    </h2>
                </div>
                <span class="text-xs text-slate-400 font-mono font-bold">
                    {{ count($monthlyData['days']) }} {{ Str::plural('day', count($monthlyData['days'])) }} recorded
                </span>
            </div>

            @if(empty($monthlyData['days']))
                <div class="p-12 text-center text-slate-400">
                    <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                    <p class="text-xs font-bold">No ledger activity recorded for {{ $currentShop->name }} in {{ $monthlyData['month_title'] }}.</p>
                </div>
            @else
                <!-- Desktop Table View -->
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                                <th class="py-3 px-4 rounded-l-xl">Date</th>
                                <th class="py-3 px-4 text-right">Collections</th>
                                <th class="py-3 px-4 text-right">Company Received</th>
                                <th class="py-3 px-4 text-right">Pending Acceptance</th>
                                <th class="py-3 px-4 text-right">Pending Verification</th>
                                <th class="py-3 px-4 text-right">Still To Settle</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-right rounded-r-xl">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono">
                            @foreach($monthlyData['days'] as $day)
                                @php
                                    $statusBadgeClass = match($day['status_key']) {
                                        'needs_attention' => 'bg-rose-50 text-rose-800 border-rose-200',
                                        'needs_acceptance' => 'bg-amber-50 text-amber-800 border-amber-200',
                                        'pending_verification' => 'bg-sky-50 text-sky-800 border-sky-200',
                                        default => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                    };
                                    $dayDetailUrl = route('admin.cashbook.shop.show', [
                                        'shop' => $currentShop->slug ?: $currentShop->shop_id,
                                        'date' => $day['business_date'],
                                    ]);
                                @endphp
                                <tr class="hover:bg-slate-50/80 transition-colors {{ $day['is_today'] ? 'bg-emerald-50/30' : '' }}">
                                    <td class="py-3.5 px-4 font-sans">
                                        <div class="flex items-center gap-2">
                                            <span class="font-extrabold text-slate-900 text-sm">{{ $day['formatted_date'] }}</span>
                                            <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-500 font-mono text-[10px] font-bold">
                                                {{ $day['day_name'] }}
                                            </span>
                                            @if($day['is_today'])
                                                <span class="px-1.5 py-0.5 rounded-md bg-emerald-600 text-white text-[9px] font-black uppercase tracking-wider">
                                                    Today
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-bold text-slate-800">
                                        ₹{{ number_format($day['total_collection'], 2) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-bold text-emerald-700">
                                        ₹{{ number_format($day['company_received'], 2) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-bold {{ $day['pending_acceptance'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                                        ₹{{ number_format($day['pending_acceptance'], 2) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-bold {{ $day['pending_verification'] > 0 ? 'text-sky-700' : 'text-slate-400' }}">
                                        ₹{{ number_format($day['pending_verification'], 2) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-black text-slate-900">
                                        ₹{{ number_format($day['outstanding'], 2) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center font-sans">
                                        <span class="inline-flex items-center text-[10px] font-extrabold px-2.5 py-1 rounded-lg border {{ $statusBadgeClass }}">
                                            {{ $day['status'] }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-sans">
                                        <a href="{{ $dayDetailUrl }}"
                                           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-emerald-700 text-white text-xs font-extrabold transition-all shadow-xs cursor-pointer"
                                           title="Open {{ $day['formatted_date'] }} settlement details">
                                            <span>Open Day Details</span>
                                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card Stack -->
                <div class="lg:hidden space-y-3">
                    @foreach($monthlyData['days'] as $day)
                        @php
                            $statusBadgeClass = match($day['status_key']) {
                                'needs_attention' => 'bg-rose-50 text-rose-800 border-rose-200',
                                'needs_acceptance' => 'bg-amber-50 text-amber-800 border-amber-200',
                                'pending_verification' => 'bg-sky-50 text-sky-800 border-sky-200',
                                default => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                            };
                            $dayDetailUrl = route('admin.cashbook.shop.show', [
                                'shop' => $currentShop->slug ?: $currentShop->shop_id,
                                'date' => $day['business_date'],
                            ]);
                        @endphp
                        <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-black text-sm text-slate-900">{{ $day['formatted_date'] }}</span>
                                    <span class="text-xs text-slate-500 font-mono">({{ $day['day_name'] }})</span>
                                </div>
                                <span class="inline-flex items-center text-[10px] font-extrabold px-2 py-0.5 rounded-lg border {{ $statusBadgeClass }}">
                                    {{ $day['status'] }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs font-mono">
                                <div>
                                    <span class="text-[10px] font-sans font-extrabold text-slate-400 uppercase">Collections</span>
                                    <div class="font-bold text-slate-800">₹{{ number_format($day['total_collection'], 2) }}</div>
                                </div>
                                <div>
                                    <span class="text-[10px] font-sans font-extrabold text-emerald-700 uppercase">Received</span>
                                    <div class="font-bold text-emerald-800">₹{{ number_format($day['company_received'], 2) }}</div>
                                </div>
                                <div>
                                    <span class="text-[10px] font-sans font-extrabold text-amber-700 uppercase">Pending Accept</span>
                                    <div class="font-bold text-amber-800">₹{{ number_format($day['pending_acceptance'], 2) }}</div>
                                </div>
                                <div>
                                    <span class="text-[10px] font-sans font-extrabold text-sky-700 uppercase">Pending Verify</span>
                                    <div class="font-bold text-sky-800">₹{{ number_format($day['pending_verification'], 2) }}</div>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-slate-200 flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] font-sans font-extrabold text-slate-500 uppercase">Still To Settle: </span>
                                    <span class="font-mono font-black text-slate-900 text-sm">₹{{ number_format($day['outstanding'], 2) }}</span>
                                </div>
                                <a href="{{ $dayDetailUrl }}"
                                   class="inline-flex items-center gap-1 px-3 py-1 rounded-xl bg-slate-900 text-white text-xs font-extrabold">
                                    <span>Details</span>
                                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    @else
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- ── SINGLE DAY SETTLEMENT DETAILS VIEW ────────────────────────── -->
        <!-- ══════════════════════════════════════════════════════════════════ -->

        <!-- Day Details Header with Back to Month button -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
            <div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'month' => $month]) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-extrabold transition-all">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                        <span>Back to Month Summary</span>
                    </a>
                </div>
                <div class="flex items-center gap-2 mt-2">
                    <span class="p-2 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <i data-lucide="store" class="w-5 h-5"></i>
                    </span>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 uppercase">
                        {{ $currentShop->name }} &mdash; {{ \Illuminate\Support\Carbon::parse($businessDate)->format('d M Y') }}
                    </h1>
                </div>
                <p class="text-xs text-slate-500 font-medium mt-1">
                    Daily operational collection and settlement position for
                    <span class="font-bold text-slate-800">{{ $currentShop->name }} ({{ $currentShop->code }})</span>
                </p>
            </div>

            <!-- Date & Shop Filter Controls -->
            <form method="GET" action="{{ route('admin.cashbook.shop.show', $currentShop->slug ?: $currentShop->shop_id) }}" id="shop-day-filter-form" class="flex items-center gap-2 flex-wrap">

                <!-- Quick Day Buttons -->
                <div class="inline-flex rounded-xl bg-slate-100 p-1 border border-slate-200 text-xs font-bold">
                    <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'date' => today()->toDateString()]) }}"
                       class="px-3 py-1.5 rounded-lg transition {{ $businessDate === today()->toDateString() ? 'bg-white text-emerald-800 shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900' }}">
                        Today
                    </a>
                    <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'date' => today()->subDay()->toDateString()]) }}"
                       class="px-3 py-1.5 rounded-lg transition {{ $businessDate === today()->subDay()->toDateString() ? 'bg-white text-emerald-800 shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900' }}">
                        Yesterday
                    </a>
                </div>

                <div class="flex items-center gap-1.5 bg-slate-100 px-3 py-1.5 rounded-xl border border-slate-200">
                    <i data-lucide="calendar" class="w-4 h-4 text-slate-500"></i>
                    <input type="date" name="date" value="{{ $businessDate }}" onchange="document.getElementById('shop-day-filter-form').submit()"
                           class="bg-transparent text-xs font-mono font-bold text-slate-800 border-none focus:outline-none cursor-pointer">
                </div>

                <!-- Shop Dropdown Selector -->
                <div class="flex items-center">
                    <select onchange="window.location.href='/admin/cashbook/shops/' + this.value + '?date={{ $businessDate }}'"
                            class="bg-slate-50 text-xs font-bold text-slate-800 px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-600 cursor-pointer">
                        @foreach($shops as $shopOption)
                            <option value="{{ $shopOption->slug ?: $shopOption->shop_id }}" {{ $currentShop->shop_id == $shopOption->shop_id ? 'selected' : '' }}>
                                {{ $shopOption->name }} ({{ $shopOption->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

    <!-- 1. TODAY'S SETTLEMENT KPI BANNER -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">TODAY'S SETTLEMENT</h2>
            <span class="text-xs font-bold text-slate-500 font-mono">{{ \Illuminate\Support\Carbon::parse($businessDate)->format('d M Y') }}</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <!-- Gross Sales -->
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Gross Sales</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-slate-900 mt-1">
                    ₹{{ number_format($dailySettlement['gross_sales'] ?? 0, 2) }}
                </div>
            </div>

            <!-- Company Received -->
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-100">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Company Received</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-emerald-900 mt-1">
                    ₹{{ number_format($dailySettlement['company_receipt_status']['verified_received'] ?? 0, 2) }}
                </div>
            </div>

            <!-- Needs Verification -->
            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-100">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-700">Needs Verification</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-amber-900 mt-1">
                    ₹{{ number_format($dailySettlement['company_receipt_status']['pending_verification'] ?? 0, 2) }}
                </div>
            </div>

            <!-- Cash With Shop -->
            <div class="p-4 rounded-2xl bg-sky-50 border border-sky-100">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-sky-700">Cash With Shop</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-sky-900 mt-1">
                    ₹{{ number_format($dailySettlement['company_receipt_status']['cash_still_with_shop'] ?? 0, 2) }}
                </div>
            </div>

            <!-- Floating Cheques -->
            <div class="p-4 rounded-2xl bg-purple-50 border border-purple-100">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-purple-700">Floating Cheques</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-purple-900 mt-1">
                    ₹{{ number_format($dailySettlement['company_receipt_status']['floating_cheques'] ?? 0, 2) }}
                </div>
            </div>

            <!-- Still To Settle -->
            <div class="p-4 rounded-2xl bg-slate-900 text-white border border-slate-800">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-300">Still To Settle</span>
                <div class="text-xl sm:text-2xl font-black font-mono text-emerald-400 mt-1">
                    ₹{{ number_format($dailySettlement['settlement_summary']['outstanding_to_settle'] ?? 0, 2) }}
                </div>
            </div>
        </div>
    </div>

    <!-- 2-COLUMN MAIN CONTENT: HOW SALES WERE COLLECTED & ADJUSTMENTS -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left 7 Cols: HOW SALES WERE COLLECTED -->
        <div class="lg:col-span-7 space-y-4">
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-sm font-extrabold uppercase tracking-tight text-slate-900">
                        HOW SALES WERE COLLECTED
                    </h2>
                    <span class="text-xs font-bold text-slate-500 font-mono">
                        {{ count($dailySettlement['collections'] ?? []) }} entries
                    </span>
                </div>

                @if(empty($dailySettlement['collections']))
                    <div class="py-10 text-center text-slate-400 text-xs font-bold">
                        No collections recorded for this business date.
                    </div>
                @else
                    @php
                        $needsAcceptance = collect($dailySettlement['collections'])->filter(fn($c) => !empty($c['can_accept']));
                        $needsVerification = collect($dailySettlement['collections'])->filter(fn($c) => !empty($c['can_verify']));
                        $receivedCollections = collect($dailySettlement['collections'])->filter(fn($c) => !empty($c['is_received']));
                    @endphp

                    <!-- ── 1. NEEDS ACCEPTANCE BATCH SECTION ──────────────────────── -->
                    @if($needsAcceptance->isNotEmpty())
                        <div class="p-4 rounded-2xl border border-amber-200 bg-amber-50/40 space-y-3"
                             x-data="{
                                selectedIds: [],
                                selectedTotal: 0,
                                showConfirmModal: false,
                                isSubmitting: false,
                                allSelected: false,
                                toggleAll(event) {
                                    if (event.target.checked) {
                                        this.selectedIds = Array.from(this.$el.querySelectorAll('.acceptance-checkbox')).map(el => parseInt(el.value));
                                        this.allSelected = true;
                                    } else {
                                        this.selectedIds = [];
                                        this.allSelected = false;
                                    }
                                    this.updateTotal();
                                },
                                updateTotal() {
                                    let total = 0;
                                    const checkboxes = this.$el.querySelectorAll('.acceptance-checkbox');
                                    checkboxes.forEach(cb => {
                                        if (this.selectedIds.includes(parseInt(cb.value))) {
                                            total += parseFloat(cb.dataset.amount || 0);
                                        }
                                    });
                                    this.selectedTotal = total;
                                    this.allSelected = checkboxes.length > 0 && this.selectedIds.length === checkboxes.length;
                                },
                                submitBatch() {
                                    if (this.isSubmitting || this.selectedIds.length === 0) return;
                                    this.isSubmitting = true;
                                    this.$refs.acceptForm.submit();
                                }
                             }">

                            <form method="POST" action="{{ route('admin.cashbook.shop.day.accept-selected', $currentShop->slug ?: $currentShop->shop_id) }}" x-ref="acceptForm">
                                @csrf
                                <input type="hidden" name="business_date" value="{{ $businessDate }}">

                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-amber-200/60 pb-3">
                                    <div class="flex items-center gap-2">
                                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                            <input type="checkbox"
                                                   class="w-4 h-4 rounded border-amber-300 text-amber-600 focus:ring-amber-500 cursor-pointer"
                                                   x-model="allSelected"
                                                   @change="toggleAll($event)">
                                            <span class="text-xs font-black uppercase tracking-wider text-amber-900">Needs Acceptance ({{ $needsAcceptance->count() }})</span>
                                        </label>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <div class="text-xs font-mono font-bold text-amber-800" x-show="selectedIds.length > 0">
                                            Selected: <span x-text="selectedIds.length"></span> (<span x-text="'₹' + Number(selectedTotal).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>)
                                        </div>
                                        <button type="button"
                                                @click="showConfirmModal = true"
                                                :disabled="selectedIds.length === 0"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-extrabold shadow-xs transition-all cursor-pointer">
                                            <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                                            <span>Accept Selected</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="divide-y divide-amber-100/80 mt-3 space-y-2">
                                    @foreach($needsAcceptance as $col)
                                        <div class="p-3 rounded-xl bg-white border border-amber-100 flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-3">
                                                <input type="checkbox"
                                                       name="transaction_ids[]"
                                                       value="{{ $col['id'] }}"
                                                       data-amount="{{ $col['amount'] }}"
                                                       x-model="selectedIds"
                                                       @change="updateTotal"
                                                       class="acceptance-checkbox w-4 h-4 rounded border-amber-300 text-amber-600 focus:ring-amber-500 cursor-pointer">
                                                <div>
                                                    <div class="font-extrabold text-sm text-slate-900">
                                                        {{ $col['payment_method'] ?? $col['category_name'] }}
                                                    </div>
                                                    <div class="text-xs text-slate-500">
                                                        @if(!empty($col['destination_name']))
                                                            <span class="font-bold text-slate-700">{{ $col['destination_name'] }}</span>
                                                        @elseif(!empty($col['location_name']))
                                                            <span class="font-bold text-slate-700">{{ $col['location_name'] }}</span>
                                                        @elseif(!empty($col['destination_account']))
                                                            <span class="font-bold text-slate-700">→ {{ $col['destination_account'] }}</span>
                                                        @else
                                                            <span>📍 {{ $currentShop->name }} Shop</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-3">
                                                <div class="text-right">
                                                    <div class="text-sm font-black font-mono text-slate-900">
                                                        ₹{{ number_format($col['amount'], 2) }}
                                                    </div>
                                                    <span class="inline-flex items-center text-[10px] font-extrabold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-md">
                                                        POSTED
                                                    </span>
                                                </div>
                                                <a href="{{ route('admin.cashbook.transaction.show', $col['id']) }}"
                                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-900 text-slate-700 hover:text-white text-xs font-bold transition">
                                                    <span>View</span>
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </form>

                            <!-- Acceptance Modal -->
                            <div x-show="showConfirmModal"
                                 style="display: none;"
                                 class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs">
                                <div class="bg-white rounded-3xl p-6 max-w-md w-full border border-slate-200 shadow-2xl space-y-4"
                                     @click.away="if(!isSubmitting) showConfirmModal = false">
                                    <div class="flex items-center gap-3">
                                        <div class="p-3 rounded-2xl bg-amber-50 text-amber-700 border border-amber-200">
                                            <i data-lucide="check-circle" class="w-6 h-6"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-base font-black text-slate-900">Confirm Acceptance</h3>
                                            <p class="text-xs text-slate-500 font-medium">Verify correct recording in shop cashbook</p>
                                        </div>
                                    </div>

                                    <div class="p-4 rounded-2xl bg-amber-50/50 border border-amber-100 text-xs text-slate-700 space-y-2">
                                        <p class="font-extrabold text-amber-950">
                                            Accept <span x-text="selectedIds.length"></span> cashbook <span x-text="selectedIds.length === 1 ? 'entry' : 'entries'"></span> totalling <span x-text="'₹' + Number(selectedTotal).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>?
                                        </p>
                                        <p class="text-slate-500 text-[11px] leading-relaxed">
                                            This confirms the entries are correct. It does not confirm that the company received the money.
                                        </p>
                                    </div>

                                    <div class="flex items-center justify-end gap-2 pt-2">
                                        <button type="button"
                                                @click="showConfirmModal = false"
                                                :disabled="isSubmitting"
                                                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                                            Cancel
                                        </button>
                                        <button type="button"
                                                @click="submitBatch"
                                                :disabled="isSubmitting"
                                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-extrabold shadow-sm transition cursor-pointer disabled:opacity-50">
                                            <span x-text="isSubmitting ? 'Accepting...' : 'Confirm Acceptance'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- ── 2. NEEDS VERIFICATION BATCH SECTION ────────────────────── -->
                    @if($needsVerification->isNotEmpty())
                        <div class="p-4 rounded-2xl border border-sky-200 bg-sky-50/40 space-y-3"
                             x-data="{
                                selectedIds: [],
                                selectedTotal: 0,
                                showConfirmModal: false,
                                isSubmitting: false,
                                allSelected: false,
                                toggleAll(event) {
                                    if (event.target.checked) {
                                        this.selectedIds = Array.from(this.$el.querySelectorAll('.verification-checkbox')).map(el => parseInt(el.value));
                                        this.allSelected = true;
                                    } else {
                                        this.selectedIds = [];
                                        this.allSelected = false;
                                    }
                                    this.updateTotal();
                                },
                                updateTotal() {
                                    let total = 0;
                                    const checkboxes = this.$el.querySelectorAll('.verification-checkbox');
                                    checkboxes.forEach(cb => {
                                        if (this.selectedIds.includes(parseInt(cb.value))) {
                                            total += parseFloat(cb.dataset.amount || 0);
                                        }
                                    });
                                    this.selectedTotal = total;
                                    this.allSelected = checkboxes.length > 0 && this.selectedIds.length === checkboxes.length;
                                },
                                submitBatch() {
                                    if (this.isSubmitting || this.selectedIds.length === 0) return;
                                    this.isSubmitting = true;
                                    this.$refs.verifyForm.submit();
                                }
                             }">

                            <form method="POST" action="{{ route('admin.cashbook.shop.day.verify-selected', $currentShop->slug ?: $currentShop->shop_id) }}" x-ref="verifyForm">
                                @csrf
                                <input type="hidden" name="business_date" value="{{ $businessDate }}">

                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-sky-200/60 pb-3">
                                    <div class="flex items-center gap-2">
                                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                            <input type="checkbox"
                                                   class="w-4 h-4 rounded border-sky-300 text-sky-600 focus:ring-sky-500 cursor-pointer"
                                                   x-model="allSelected"
                                                   @change="toggleAll($event)">
                                            <span class="text-xs font-black uppercase tracking-wider text-sky-900">Needs Verification ({{ $needsVerification->count() }})</span>
                                        </label>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <div class="text-xs font-mono font-bold text-sky-800" x-show="selectedIds.length > 0">
                                            Selected: <span x-text="selectedIds.length"></span> (<span x-text="'₹' + Number(selectedTotal).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>)
                                        </div>
                                        <button type="button"
                                                @click="showConfirmModal = true"
                                                :disabled="selectedIds.length === 0"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-sky-700 hover:bg-sky-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-extrabold shadow-xs transition-all cursor-pointer">
                                            <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                                            <span>Confirm Selected Received</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="divide-y divide-sky-100/80 mt-3 space-y-2">
                                    @foreach($needsVerification as $col)
                                        <div class="p-3 rounded-xl bg-white border border-sky-100 flex flex-col gap-2">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="flex items-center gap-3">
                                                    <input type="checkbox"
                                                           name="transaction_ids[]"
                                                           value="{{ $col['id'] }}"
                                                           data-amount="{{ $col['amount'] }}"
                                                           x-model="selectedIds"
                                                           @change="updateTotal"
                                                           class="verification-checkbox w-4 h-4 rounded border-sky-300 text-sky-600 focus:ring-sky-500 cursor-pointer">
                                                    <div>
                                                        <div class="font-extrabold text-sm text-slate-900">
                                                            {{ $col['payment_method'] ?? $col['category_name'] }}
                                                        </div>
                                                        <div class="text-xs text-slate-500 flex items-center gap-1">
                                                            @if(!empty($col['destination_name']))
                                                                <span class="font-extrabold text-emerald-800">{{ $col['destination_name'] }}</span>
                                                            @elseif(!empty($col['location_name']))
                                                                <span class="font-extrabold text-slate-700">{{ $col['location_name'] }}</span>
                                                            @elseif(!empty($col['destination_account']))
                                                                <span class="font-extrabold text-emerald-800">→ {{ $col['destination_account'] }}</span>
                                                            @else
                                                                <span>📍 {{ $currentShop->name }} Shop</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-3">
                                                    <div class="text-right">
                                                        <div class="text-sm font-black font-mono text-slate-900">
                                                            ₹{{ number_format($col['amount'], 2) }}
                                                        </div>
                                                        @if($col['status'] === 'CASH WITH SHOP')
                                                            <span class="inline-flex items-center text-[10px] font-extrabold text-sky-800 bg-sky-100 px-2 py-0.5 rounded-md">
                                                                CASH WITH SHOP
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center text-[10px] font-extrabold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-md">
                                                                NEEDS VERIFICATION
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <a href="{{ route('admin.cashbook.transaction.show', $col['id']) }}"
                                                       class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-900 text-slate-700 hover:text-white text-xs font-bold transition">
                                                        <span>View</span>
                                                    </a>
                                                </div>
                                            </div>

                                            @if(!empty($col['has_bank_adjustment_rules']))
                                                <div class="mt-1 pt-2 border-t border-slate-100 text-xs" x-data="{
                                                    open: {{ ($col['adjustment_total'] ?? 0) != 0 ? 'true' : 'false' }},
                                                    baseAmount: {{ (float) ($col['amount'] ?? 0) }},
                                                    adjustments: {{ json_encode($col['bank_adjustment_rules'] ?? []) }},
                                                    saving: false,
                                                    get expectedAmount() {
                                                        let total = this.baseAmount;
                                                        this.adjustments.forEach(a => {
                                                            const val = parseFloat(a.amount) || 0;
                                                            if (a.direction === 'plus') total += val;
                                                            else total -= val;
                                                        });
                                                        return total;
                                                    },
                                                    get adjustmentTotal() {
                                                        let adj = 0;
                                                        this.adjustments.forEach(a => {
                                                            const val = parseFloat(a.amount) || 0;
                                                            if (a.direction === 'plus') adj += val;
                                                            else adj -= val;
                                                        });
                                                        return adj;
                                                    },
                                                    async saveAdjustments() {
                                                        this.saving = true;
                                                        try {
                                                            const res = await fetch('{{ route('admin.cashbook.api.shops.bank-settlement-adjustments.save', $currentShop->slug ?: $currentShop->shop_id) }}', {
                                                                method: 'POST',
                                                                headers: {
                                                                    'Content-Type': 'application/json',
                                                                    'Accept': 'application/json',
                                                                    'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || '{{ csrf_token() }}',
                                                                    'X-Requested-With': 'XMLHttpRequest'
                                                                },
                                                                body: JSON.stringify({
                                                                    business_date: '{{ $businessDate }}',
                                                                    entry_type_id: {{ $col['entry_type_id'] }},
                                                                    adjustments: this.adjustments.map(a => ({
                                                                        rule_id: a.rule_id,
                                                                        amount: parseFloat(a.amount) || 0,
                                                                        notes: a.notes || ''
                                                                    }))
                                                                })
                                                            });
                                                            const data = await res.json();
                                                            if (!res.ok || !data.success) {
                                                                alert(data.message || 'Failed to save bank adjustments');
                                                            } else {
                                                                window.location.reload();
                                                            }
                                                        } catch (err) {
                                                            console.error(err);
                                                            alert('Failed to save bank adjustments');
                                                        } finally {
                                                            this.saving = false;
                                                        }
                                                    }
                                                }">
                                                    <div class="flex items-center justify-between text-[11px] text-slate-500">
                                                        <span>Bank Expectation: <strong class="font-mono text-slate-800">₹<span x-text="Number(expectedAmount).toFixed(2)"></span></strong></span>
                                                        <button type="button" @click="open = !open" class="text-emerald-700 hover:underline font-bold">
                                                            <span x-text="open ? 'Hide Adjustments' : 'Configure Adjustments'"></span>
                                                        </button>
                                                    </div>
                                                    <div x-show="open" class="mt-2 space-y-2 p-3 bg-slate-50 rounded-xl border border-slate-200">
                                                        <template x-for="adj in adjustments" :key="adj.rule_id">
                                                            <div class="flex items-center justify-between gap-2">
                                                                <span class="text-xs text-slate-600 font-bold" x-text="adj.label"></span>
                                                                <input type="number" step="0.01" x-model="adj.amount" class="w-24 px-2 py-1 bg-white border border-slate-300 rounded-lg text-right font-mono text-xs">
                                                            </div>
                                                        </template>
                                                        <div class="flex justify-end pt-1">
                                                            <button type="button" @click="saveAdjustments" :disabled="saving" class="px-3 py-1 bg-slate-900 text-white rounded-lg text-xs font-bold">
                                                                <span x-text="saving ? 'Saving...' : 'Save'"></span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </form>

                            <!-- Verification Modal -->
                            <div x-show="showConfirmModal"
                                 style="display: none;"
                                 class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs">
                                <div class="bg-white rounded-3xl p-6 max-w-md w-full border border-slate-200 shadow-2xl space-y-4"
                                     @click.away="if(!isSubmitting) showConfirmModal = false">
                                    <div class="flex items-center gap-3">
                                        <div class="p-3 rounded-2xl bg-sky-50 text-sky-700 border border-sky-200">
                                            <i data-lucide="shield-check" class="w-6 h-6"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-base font-black text-slate-900">Confirm Company Receipt</h3>
                                            <p class="text-xs text-slate-500 font-medium">Verify funds deposited into company accounts</p>
                                        </div>
                                    </div>

                                    <div class="p-4 rounded-2xl bg-sky-50/50 border border-sky-100 text-xs text-slate-700 space-y-2">
                                        <p class="font-extrabold text-sky-950">
                                            Confirm that the company received <span x-text="selectedIds.length"></span> <span x-text="selectedIds.length === 1 ? 'payment' : 'payments'"></span> totalling <span x-text="'₹' + Number(selectedTotal).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>?
                                        </p>
                                        <p class="text-slate-500 text-[11px] leading-relaxed">
                                            This will update company accounts and reduce Shop Outstanding.
                                        </p>
                                    </div>

                                    <div class="flex items-center justify-end gap-2 pt-2">
                                        <button type="button"
                                                @click="showConfirmModal = false"
                                                :disabled="isSubmitting"
                                                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                                            Cancel
                                        </button>
                                        <button type="button"
                                                @click="submitBatch"
                                                :disabled="isSubmitting"
                                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-sky-700 hover:bg-sky-800 text-white text-xs font-extrabold shadow-sm transition cursor-pointer disabled:opacity-50">
                                            <span x-text="isSubmitting ? 'Confirming...' : 'Confirm Received'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- ── 3. RECEIVED / VERIFIED COLLECTIONS (READ-ONLY) ─────────── -->
                    @if($receivedCollections->isNotEmpty())
                        <div class="p-4 rounded-2xl border border-emerald-200 bg-emerald-50/30 space-y-3">
                            <div class="flex items-center justify-between border-b border-emerald-200/60 pb-2">
                                <span class="text-xs font-black uppercase tracking-wider text-emerald-900 flex items-center gap-1.5">
                                    <i data-lucide="check-check" class="w-4 h-4 text-emerald-600"></i>
                                    <span>Received Collections ({{ $receivedCollections->count() }})</span>
                                </span>
                                <span class="text-[11px] font-bold text-emerald-700 font-mono">
                                    ₹{{ number_format($receivedCollections->sum('amount'), 2) }}
                                </span>
                            </div>

                            <div class="divide-y divide-emerald-100/80 space-y-2">
                                @foreach($receivedCollections as $col)
                                    <div class="p-3 rounded-xl bg-white border border-emerald-100 flex items-center justify-between gap-3">
                                        <div>
                                            <div class="font-extrabold text-sm text-slate-900">
                                                {{ $col['payment_method'] ?? $col['category_name'] }}
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                @if(!empty($col['destination_name']))
                                                    <span class="font-extrabold text-emerald-800">{{ $col['destination_name'] }}</span>
                                                @elseif(!empty($col['location_name']))
                                                    <span class="font-extrabold text-slate-700">{{ $col['location_name'] }}</span>
                                                @elseif(!empty($col['destination_account']))
                                                    <span class="font-extrabold text-emerald-800">→ {{ $col['destination_account'] }}</span>
                                                @else
                                                    <span>📍 {{ $currentShop->name }} Shop</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <div class="text-right">
                                                <div class="text-sm font-black font-mono text-slate-900">
                                                    ₹{{ number_format($col['amount'], 2) }}
                                                </div>
                                                <span class="inline-flex items-center gap-1 text-[10px] font-extrabold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                                                    <i data-lucide="check" class="w-3 h-3"></i> RECEIVED
                                                </span>
                                            </div>
                                            <a href="{{ route('admin.cashbook.transaction.show', $col['id']) }}"
                                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-900 text-slate-700 hover:text-white text-xs font-bold transition">
                                                <span>View</span>
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            <!-- SETTLEMENT ADJUSTMENTS -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4"
                 x-data="{
                    showAddModal: false,
                    showReverseModal: false,
                    targetAdjustmentId: null,
                    targetAdjustmentName: '',
                    targetAdjustmentAmount: '',
                    reverseReason: '',
                    isSubmitting: false,
                    openReverse(id, name, amount) {
                        this.targetAdjustmentId = id;
                        this.targetAdjustmentName = name;
                        this.targetAdjustmentAmount = amount;
                        this.reverseReason = '';
                        this.showReverseModal = true;
                    }
                 }">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-extrabold uppercase tracking-tight text-slate-900">
                            SETTLEMENT ADJUSTMENTS
                        </h2>
                        <span class="text-xs font-bold text-slate-500 font-mono">
                            {{ count($dailySettlement['settlement_adjustments'] ?? []) }} entries
                        </span>
                    </div>

                    <button type="button" @click="showAddModal = true"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-black shadow-xs transition cursor-pointer">
                        <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                        <span>Add Adjustment</span>
                    </button>
                </div>

                @if(empty($dailySettlement['settlement_adjustments']))
                    <div class="py-8 text-center text-slate-400 text-xs font-bold">
                        No settlement adjustments recorded for this business date.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                                    <th class="py-2.5 px-3">Time</th>
                                    <th class="py-2.5 px-3">Type</th>
                                    <th class="py-2.5 px-3">Note</th>
                                    <th class="py-2.5 px-3 text-right">Amount</th>
                                    <th class="py-2.5 px-3 text-right">Outstanding Effect</th>
                                    <th class="py-2.5 px-3">Admin</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                    <th class="py-2.5 px-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100/80">
                                @foreach($dailySettlement['settlement_adjustments'] as $adj)
                                    <tr class="hover:bg-slate-50/60 transition {{ !empty($adj['is_reversed']) ? 'opacity-60 bg-slate-50/30' : '' }}">
                                        <td class="py-3 px-3 font-mono text-slate-500 font-semibold">{{ $adj['time'] }}</td>
                                        <td class="py-3 px-3">
                                            <span class="font-extrabold text-slate-900 block">{{ $adj['type'] }}</span>
                                            <span class="text-[10px] text-slate-400 font-bold">{{ $adj['name'] }}</span>
                                        </td>
                                        <td class="py-3 px-3 max-w-[200px] truncate text-slate-600 font-medium" title="{{ $adj['note'] }}">
                                            {{ $adj['note'] }}
                                            @if(!empty($adj['is_reversal']))
                                                <a href="{{ route('admin.cashbook.transaction.show', $adj['original_id']) }}" class="text-sky-600 hover:underline font-bold block text-[10px]">
                                                    Original #{{ $adj['original_id'] }}
                                                </a>
                                            @elseif(!empty($adj['is_reversed']) && !empty($adj['reversal_id']))
                                                <a href="{{ route('admin.cashbook.transaction.show', $adj['reversal_id']) }}" class="text-rose-600 hover:underline font-bold block text-[10px]">
                                                    Reversal #{{ $adj['reversal_id'] }}
                                                </a>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right font-black font-mono text-slate-900">
                                            ₹{{ number_format($adj['amount'], 2) }}
                                        </td>
                                        <td class="py-3 px-3 text-right font-black font-mono {{ ($adj['effect_on_payable'] ?? 0) < 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                                            {{ ($adj['effect_on_payable'] ?? 0) < 0 ? '-' : '+' }}₹{{ number_format(abs($adj['effect_on_payable'] ?? 0), 2) }}
                                        </td>
                                        <td class="py-3 px-3 text-slate-600 font-medium">{{ $adj['admin'] }}</td>
                                        <td class="py-3 px-3 text-center">
                                            @if(!empty($adj['is_reversed']))
                                                <span class="inline-flex items-center text-[9px] font-black uppercase px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 border border-rose-200">
                                                    REVERSED
                                                </span>
                                            @elseif(!empty($adj['is_reversal']))
                                                <span class="inline-flex items-center text-[9px] font-black uppercase px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 border border-purple-200">
                                                    REVERSAL
                                                </span>
                                            @else
                                                <span class="inline-flex items-center text-[9px] font-black uppercase px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200">
                                                    {{ $adj['status_label'] ?? $adj['status'] }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right">
                                            @if(!empty($adj['can_reverse']))
                                                <button type="button"
                                                        @click="openReverse({{ $adj['id'] }}, '{{ addslashes($adj['name']) }}', '{{ number_format($adj['amount'], 2) }}')"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 text-[11px] font-extrabold transition cursor-pointer border border-rose-200">
                                                    <i data-lucide="rotate-ccw" class="w-3 h-3"></i>
                                                    <span>Reverse</span>
                                                </button>
                                            @else
                                                <a href="{{ route('admin.cashbook.transaction.show', $adj['id']) }}"
                                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-900 text-slate-700 hover:text-white text-[11px] font-bold transition">
                                                    <span>View</span>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <!-- Add Adjustment Modal -->
                <div x-show="showAddModal" style="display: none;"
                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs">
                    <div class="bg-white rounded-3xl p-6 max-w-md w-full border border-slate-200 shadow-2xl space-y-4"
                         @click.away="if(!isSubmitting) showAddModal = false">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="text-base font-black text-slate-900">Add Settlement Adjustment</h3>
                            <button type="button" @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                        </div>

                        <form method="POST" action="{{ route('admin.cashbook.shop.day.adjustments.store', $currentShop->slug ?: $currentShop->shop_id) }}" @submit="isSubmitting = true">
                            @csrf
                            <input type="hidden" name="business_date" value="{{ $businessDate }}">

                            <div class="space-y-4 text-xs">
                                <div>
                                    <label class="block font-extrabold text-slate-700 mb-1">Adjustment Type</label>
                                    <select name="type" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl font-bold text-slate-900 focus:outline-none focus:border-slate-500">
                                        <option value="expense">Shop Expense (Reduces Shop Outstanding)</option>
                                        <option value="income">Shop Income (Increases Shop Outstanding)</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block font-extrabold text-slate-700 mb-1">Amount (₹)</label>
                                    <input type="number" name="amount" step="0.01" min="0.01" max="10000000" required placeholder="0.00"
                                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl font-mono font-bold text-slate-900 focus:outline-none focus:border-slate-500">
                                </div>

                                <div>
                                    <label class="block font-extrabold text-slate-700 mb-1">Note / Reason</label>
                                    <textarea name="notes" rows="3" required minlength="3" maxlength="500" placeholder="Describe the reason for adjustment..."
                                              class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl font-medium text-slate-900 focus:outline-none focus:border-slate-500"></textarea>
                                </div>

                                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                                    <button type="button" @click="showAddModal = false" :disabled="isSubmitting"
                                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                                        Cancel
                                    </button>
                                    <button type="submit" :disabled="isSubmitting"
                                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-extrabold shadow-sm transition cursor-pointer disabled:opacity-50">
                                        <span x-text="isSubmitting ? 'Recording...' : 'Record Adjustment'"></span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Reverse Adjustment Modal -->
                <div x-show="showReverseModal" style="display: none;"
                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs">
                    <div class="bg-white rounded-3xl p-6 max-w-md w-full border border-slate-200 shadow-2xl space-y-4"
                         @click.away="if(!isSubmitting) showReverseModal = false">
                        <div class="flex items-center gap-3 text-rose-700">
                            <div class="p-3 rounded-2xl bg-rose-50 border border-rose-200">
                                <i data-lucide="rotate-ccw" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-black text-slate-900">Reverse Adjustment</h3>
                                <p class="text-xs text-slate-500 font-medium">Create offsetting immutable ledger transaction</p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('admin.cashbook.shop.day.adjustments.reverse', $currentShop->slug ?: $currentShop->shop_id) }}" @submit="isSubmitting = true">
                            @csrf
                            <input type="hidden" name="business_date" value="{{ $businessDate }}">
                            <input type="hidden" name="adjustment_id" :value="targetAdjustmentId">

                            <div class="space-y-4 text-xs">
                                <div class="p-3 rounded-xl bg-rose-50/50 border border-rose-100 text-slate-700 space-y-1">
                                    <div class="font-extrabold text-rose-950" x-text="'Reverse ' + targetAdjustmentName + ' of ₹' + targetAdjustmentAmount"></div>
                                    <p class="text-[11px] text-slate-500">This will record an exact opposite ledger entry to neutralize the outstanding effect while preserving audit history.</p>
                                </div>

                                <div>
                                    <label class="block font-extrabold text-slate-700 mb-1">Reversal Reason (Optional)</label>
                                    <input type="text" name="reason" x-model="reverseReason" placeholder="e.g. Incorrect amount recorded"
                                           class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl font-medium text-slate-900 focus:outline-none focus:border-slate-500">
                                </div>

                                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                                    <button type="button" @click="showReverseModal = false" :disabled="isSubmitting"
                                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                                        Cancel
                                    </button>
                                    <button type="submit" :disabled="isSubmitting"
                                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-extrabold shadow-sm transition cursor-pointer disabled:opacity-50">
                                        <span x-text="isSubmitting ? 'Reversing...' : 'Confirm Reversal'"></span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right 5 Cols: SETTLEMENT SUMMARY & BREAKDOWN -->
        <div class="lg:col-span-5 space-y-6">

            <!-- SETTLEMENT SUMMARY -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-sm font-extrabold uppercase tracking-tight text-slate-900">
                        SETTLEMENT SUMMARY
                    </h2>
                </div>

                <div class="space-y-3 text-xs font-bold">
                    <div class="flex items-center justify-between text-slate-700">
                        <span>Gross Sales</span>
                        <span class="font-mono text-sm font-extrabold text-slate-900">
                            ₹{{ number_format($dailySettlement['settlement_summary']['gross_sales'] ?? 0, 2) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-rose-600">
                        <span>Less: Sales-funded deductions</span>
                        <span class="font-mono text-sm font-extrabold">
                            -₹{{ number_format($dailySettlement['settlement_summary']['settlement_deductions'] ?? 0, 2) }}
                        </span>
                    </div>

                    <div class="border-t border-slate-200 pt-3 flex items-center justify-between text-slate-900 font-extrabold">
                        <span>Expected Payable</span>
                        <span class="font-mono text-base font-black">
                            ₹{{ number_format($dailySettlement['settlement_summary']['expected_payable'] ?? 0, 2) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-emerald-700">
                        <span>Less: Company Received</span>
                        <span class="font-mono text-sm font-extrabold">
                            -₹{{ number_format($dailySettlement['settlement_summary']['verified_company_received'] ?? 0, 2) }}
                        </span>
                    </div>

                    <div class="border-t-2 border-slate-900 pt-3 flex items-center justify-between text-slate-900 font-black">
                        <span class="text-sm uppercase tracking-wide">Still To Settle</span>
                        <span class="font-mono text-lg font-black text-emerald-700">
                            ₹{{ number_format($dailySettlement['settlement_summary']['outstanding_to_settle'] ?? 0, 2) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- BREAKDOWN OF STILL TO SETTLE -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-sm font-extrabold uppercase tracking-tight text-slate-900">
                        BREAKDOWN OF STILL TO SETTLE
                    </h2>
                </div>

                <div class="space-y-3 text-xs font-bold">
                    <div class="flex items-center justify-between text-amber-800 bg-amber-50/80 p-2.5 rounded-xl border border-amber-100">
                        <span>Needs Verification</span>
                        <span class="font-mono text-sm font-black text-amber-900">
                            ₹{{ number_format($dailySettlement['company_receipt_status']['pending_verification'] ?? 0, 2) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-sky-800 bg-sky-50/80 p-2.5 rounded-xl border border-sky-100">
                        <span>Cash With Shop</span>
                        <span class="font-mono text-sm font-black text-sky-900">
                            ₹{{ number_format($dailySettlement['company_receipt_status']['cash_still_with_shop'] ?? 0, 2) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-purple-800 bg-purple-50/80 p-2.5 rounded-xl border border-purple-100">
                        <span>Floating Cheques</span>
                        <span class="font-mono text-sm font-black text-purple-900">
                            ₹{{ number_format($dailySettlement['company_receipt_status']['floating_cheques'] ?? 0, 2) }}
                        </span>
                    </div>

                    <div class="border-t border-slate-200 pt-3 flex items-center justify-between text-slate-900 font-black">
                        <span>Total</span>
                        <span class="font-mono text-base font-black text-slate-900">
                            ₹{{ number_format(($dailySettlement['company_receipt_status']['pending_verification'] ?? 0) + ($dailySettlement['company_receipt_status']['cash_still_with_shop'] ?? 0) + ($dailySettlement['company_receipt_status']['floating_cheques'] ?? 0), 2) }}
                        </span>
                    </div>
                </div>
            </div>

        </div>

    </div>
    @endif

    <!-- Collapsible Monthly Financial Overview (Preserves Backward Compatibility) -->
    <details class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4" {{ request()->has('month') ? 'open' : '' }}>
        <summary class="cursor-pointer text-xs font-black uppercase tracking-wider text-slate-500 hover:text-slate-900 flex items-center justify-between">
            <span>Shop Financial Overview &amp; Monthly Summary ({{ \Illuminate\Support\Carbon::parse($monthStart)->format('F Y') }})</span>
            <span class="text-[10px] font-bold px-2 py-1 rounded-lg bg-slate-100 text-slate-600">Toggle Monthly Overview</span>
        </summary>

        <div class="pt-4 border-t border-slate-100 space-y-6">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.cashbook.shop.accept-payment', ['shop' => $currentShop->uuid, 'month' => $month]) }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-emerald-700 px-4 text-xs font-black text-white hover:bg-emerald-800">
                    <i data-lucide="wallet" class="h-4 w-4"></i> Receive Payment
                </a>
                <a href="{{ route('admin.cashbook.reports.mobile-ledger', ['shop' => $currentShop->uuid, 'timeframe' => 'monthly', 'date' => $monthEnd]) }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-xs font-black text-slate-700 hover:bg-slate-50">
                    <i data-lucide="book-open" class="h-4 w-4"></i> View Ledger
                </a>
            </div>

            @php
                $shopPosition = (float) ($position->closing_shop_position ?? 0);
                $companyPending = (float) ($position->closing_company_pending ?? 0);
                $pettyBalance = (float) ($position->closing_petty ?? 0);
                $netBalance = (float) ($position->total_sales ?? 0) - (float) ($position->total_expense ?? 0);
                $awaitingBank = $floatingPayments;
                $awaitingSettlement = max(0, $cashBankReceived - $ledgerSettled);
            @endphp

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <article class="rounded-2xl border border-slate-300 bg-slate-950 p-4 text-white">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Pending Payable to Company</p>
                    <p class="mt-2 text-right font-mono text-2xl font-black">₹{{ number_format($pendingPayable, 2) }}</p>
                </article>
                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-amber-700">Payment Received</p>
                    <p class="mt-2 text-right font-mono text-2xl font-black text-amber-950">₹{{ number_format($cashBankReceived, 2) }}</p>
                </article>
                <article class="rounded-2xl border border-violet-200 bg-violet-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-violet-700">Company → Shop Pending</p>
                    <p class="mt-2 text-right font-mono text-2xl font-black text-violet-950">₹{{ number_format($companyPending, 2) }}</p>
                </article>
                <article class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-sky-700">GL Bill Pending</p>
                    <p class="mt-2 text-right font-mono text-2xl font-black text-sky-950">₹{{ number_format($glBillPending, 2) }}</p>
                </article>
                <article class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-rose-700">Current Net Balance</p>
                    <p class="mt-2 text-right font-mono text-2xl font-black text-rose-950">₹{{ number_format($netBalance, 2) }}</p>
                </article>
            </div>

            <!-- Recent Payments Bound Listing -->
            <div class="border border-slate-100 rounded-2xl p-4 divide-y divide-slate-100">
                @foreach($recentPayments as $payment)
                    <div class="py-2 flex items-center justify-between text-xs font-mono">
                        <span class="font-bold text-slate-800">{{ $payment->payment_reference }}</span>
                        <span>₹{{ number_format((float) $payment->requested_amount, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </details>

</div>
@endsection
