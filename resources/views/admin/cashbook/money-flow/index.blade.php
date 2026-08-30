@extends('admin.cashbook.layouts.app')

@section('title', 'Money Flow — Cashbook')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-12">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="p-2 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <i data-lucide="activity" class="w-5 h-5"></i>
                </span>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Money Flow</h1>
            </div>
            <p class="text-xs text-slate-500 font-medium mt-1">
                Unified real-time company money position &amp; retail collection flows for 
                <span class="font-bold text-slate-800 font-mono">{{ \Carbon\Carbon::parse($businessDate)->format('d M Y') }}</span>
            </p>
        </div>

        <!-- Date & Filter Controls -->
        <form method="GET" action="{{ route('admin.cashbook.money-flow') }}" id="money-flow-filter-form" class="flex items-center gap-2 flex-wrap">
            <input type="hidden" name="status" value="{{ $selectedStatus }}">
            <input type="hidden" name="shop_id" value="{{ $selectedShopId }}">
            <input type="hidden" name="calendar_month" value="{{ $calendarData['calendar_month'] ?? '' }}">

            <!-- Quick Day Buttons -->
            <div class="inline-flex rounded-xl bg-slate-100 p-1 border border-slate-200 text-xs font-bold">
                <a href="{{ route('admin.cashbook.money-flow', ['date' => today()->toDateString(), 'shop_id' => $selectedShopId, 'status' => $selectedStatus, 'calendar_month' => $calendarData['calendar_month'] ?? null]) }}"
                   class="px-3 py-1.5 rounded-lg transition {{ $businessDate === today()->toDateString() ? 'bg-white text-emerald-800 shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900' }}">
                    Today
                </a>
                <a href="{{ route('admin.cashbook.money-flow', ['date' => today()->subDay()->toDateString(), 'shop_id' => $selectedShopId, 'status' => $selectedStatus, 'calendar_month' => $calendarData['calendar_month'] ?? null]) }}"
                   class="px-3 py-1.5 rounded-lg transition {{ $businessDate === today()->subDay()->toDateString() ? 'bg-white text-emerald-800 shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900' }}">
                    Yesterday
                </a>
            </div>

            <div class="flex items-center gap-1.5 bg-slate-100 px-3 py-1.5 rounded-xl border border-slate-200">
                <i data-lucide="calendar" class="w-4 h-4 text-slate-500"></i>
                <input type="date" name="date" value="{{ $businessDate }}" onchange="document.getElementById('money-flow-filter-form').submit()"
                       class="bg-transparent text-xs font-mono font-bold text-slate-800 border-none focus:outline-none cursor-pointer">
            </div>

            <!-- Calendar Shortcut on Top -->
            <a href="#monthly-pending-calendar"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-200 transition"
               title="Jump to Monthly Pending Calendar">
                <i data-lucide="calendar-days" class="w-4 h-4 text-emerald-700"></i>
                <span>Calendar</span>
            </a>
        </form>
    </div>

    <!-- Position KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- 1. COMPANY RECEIVED -->
        <div class="bg-white p-5 rounded-3xl border border-emerald-100 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-700">Company Received</span>
                <span class="p-2 rounded-xl bg-emerald-50 text-emerald-600">
                    <i data-lucide="badge-check" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-black text-slate-900 font-mono tracking-tight">
                    ₹{{ number_format($summary['verified_company_money'] ?? 0, 2) }}
                </div>
                <div class="flex items-center gap-1.5 text-[11px] font-bold text-emerald-700 mt-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    Verified in bank &amp; cash box
                </div>
            </div>
        </div>

        <!-- 2. NEEDS ATTENTION -->
        <div class="bg-white p-5 rounded-3xl border border-amber-100 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-amber-700">Needs Attention</span>
                <span class="p-2 rounded-xl bg-amber-50 text-amber-600">
                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-black text-slate-900 font-mono tracking-tight">
                    ₹{{ number_format($summary['pending_verification_total'] ?? 0, 2) }}
                </div>
                <div class="flex items-center gap-1.5 text-[11px] font-bold text-amber-700 mt-1">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    {{ $summary['pending_verification_count'] ?? 0 }} pending verifications
                </div>
            </div>
        </div>

        <!-- 3. CASH WITH SHOPS -->
        <div class="bg-white p-5 rounded-3xl border border-sky-100 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-sky-700">Cash With Shops</span>
                <span class="p-2 rounded-xl bg-sky-50 text-sky-600">
                    <i data-lucide="store" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-black text-slate-900 font-mono tracking-tight">
                    ₹{{ number_format($summary['cash_with_shops']['total_cash_with_shops'] ?? 0, 2) }}
                </div>
                <div class="flex items-center gap-1.5 text-[11px] font-bold text-sky-700 mt-1">
                    <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                    Retained physical retail cash
                </div>
            </div>
        </div>

        <!-- 4. FLOATING CHEQUES -->
        <div class="bg-white p-5 rounded-3xl border border-purple-100 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-purple-700">Floating Cheques</span>
                <span class="p-2 rounded-xl bg-purple-50 text-purple-600">
                    <i data-lucide="clock" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-black text-slate-900 font-mono tracking-tight">
                    ₹{{ number_format($summary['floating_cheques']['total_floating'] ?? 0, 2) }}
                </div>
                <div class="flex items-center gap-1.5 text-[11px] font-bold text-purple-700 mt-1">
                    <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                    {{ $summary['floating_cheques']['floating_count'] ?? 0 }} uncleared cheques
                </div>
            </div>
        </div>

    </div>

    <!-- Shop Tabs Filter -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
        <a href="{{ route('admin.cashbook.money-flow', ['date' => $businessDate, 'shop_id' => null, 'status' => $selectedStatus, 'calendar_month' => $calendarData['calendar_month'] ?? null]) }}"
           class="px-4 py-2 rounded-2xl text-xs font-extrabold transition-all whitespace-nowrap {{ is_null($selectedShopId) ? 'bg-slate-900 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
            All Shops
        </a>
        @foreach($shops as $sh)
            <a href="{{ route('admin.cashbook.money-flow', ['date' => $businessDate, 'shop_id' => $sh->shop_id, 'status' => $selectedStatus, 'calendar_month' => $calendarData['calendar_month'] ?? null]) }}"
               class="px-4 py-2 rounded-2xl text-xs font-extrabold transition-all whitespace-nowrap {{ (int) $selectedShopId === (int) $sh->shop_id ? 'bg-slate-900 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                {{ $sh->name }}
            </a>
        @endforeach
    </div>

    <!-- Status Tabs Filter -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
        @php
            $statusTabs = [
                'all' => 'All Collections',
                'needs_attention' => 'Needs Attention / Unverified',
                'verified' => 'Company Received',
                'cash_with_shop' => 'Cash With Shop',
                'floating' => 'Floating Cheques',
            ];
        @endphp
        @foreach($statusTabs as $k => $label)
            <a href="{{ route('admin.cashbook.money-flow', ['date' => $businessDate, 'shop_id' => $selectedShopId, 'status' => $k, 'calendar_month' => $calendarData['calendar_month'] ?? null]) }}"
               class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold transition-all whitespace-nowrap {{ $selectedStatus === $k ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <!-- ── SHOP MONEY FLOW SUMMARY CARDS ────────────────────────────────── -->
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                <i data-lucide="store" class="w-4 h-4 text-emerald-600"></i>
                <span>Shop Summary Positions</span>
                <span class="text-xs text-slate-400 font-mono font-normal">({{ count($shopCards) }} {{ Str::plural('shop', count($shopCards)) }})</span>
            </h2>
        </div>

        @if(empty($shopCards))
            <div class="p-8 text-center bg-white rounded-3xl border border-slate-200 shadow-sm">
                <p class="text-xs text-slate-400 font-bold">No active shops found matching the selected filter.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($shopCards as $card)
                    @php
                        $statusBadgeClass = match($card['status_key']) {
                            'needs_attention' => 'bg-rose-50 text-rose-800 border-rose-200',
                            'needs_acceptance' => 'bg-amber-50 text-amber-800 border-amber-200',
                            'pending_verification' => 'bg-sky-50 text-sky-800 border-sky-200',
                            default => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                        };
                    @endphp
                    <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all flex flex-col justify-between gap-4">

                        <!-- Top: Shop Name, Status & Open Action -->
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-black text-base text-slate-900">{{ $card['shop_name'] }}</h3>
                                    @if(!empty($card['shop_code']))
                                        <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-[10px] font-bold text-slate-600 border border-slate-200 font-mono">
                                            {{ $card['shop_code'] }}
                                        </span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-extrabold px-2.5 py-0.5 rounded-lg border {{ $statusBadgeClass }}">
                                        {{ $card['status'] }}
                                    </span>
                                    @if($card['pending_operation_count'] > 0)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500 text-white shadow-xs" title="{{ $card['pending_operation_count'] }} pending operations">
                                            {{ $card['pending_operation_count'] }} pending
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <a href="{{ $card['open_shop_url'] }}"
                               class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-emerald-700 text-white text-xs font-extrabold transition-all shadow-xs flex-shrink-0 cursor-pointer"
                               title="Open {{ $card['shop_name'] }} Cashbook">
                                <span>Open Shop</span>
                                <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                            </a>
                        </div>

                        <!-- Mid: Detailed Breakdown Metrics -->
                        <div class="grid grid-cols-2 gap-2.5 p-3 rounded-2xl bg-slate-50 border border-slate-100 text-xs">
                            <div>
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Collection</span>
                                <div class="font-mono font-bold text-slate-800 text-sm mt-0.5">
                                    ₹{{ number_format($card['total_collection'], 2) }}
                                </div>
                            </div>
                            <div>
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Company Received</span>
                                <div class="font-mono font-bold text-emerald-800 text-sm mt-0.5">
                                    ₹{{ number_format($card['company_received'], 2) }}
                                </div>
                            </div>
                            <div>
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-700">Pending Acceptance</span>
                                <div class="font-mono font-bold text-amber-800 text-sm mt-0.5">
                                    ₹{{ number_format($card['pending_acceptance'], 2) }}
                                </div>
                            </div>
                            <div>
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-sky-700">Pending Verification</span>
                                <div class="font-mono font-bold text-sky-800 text-sm mt-0.5">
                                    ₹{{ number_format($card['pending_verification'], 2) }}
                                </div>
                            </div>
                        </div>

                        <!-- Bottom: Current Outstanding -->
                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs font-extrabold text-slate-500 uppercase tracking-wider">Current Outstanding</span>
                            <span class="font-mono text-base font-black text-slate-900">
                                ₹{{ number_format($card['current_outstanding'], 2) }}
                            </span>
                        </div>

                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Daily Collections List -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                <i data-lucide="layers" class="w-4 h-4 text-emerald-600"></i>
                <span>Daily Money Flow Collections</span>
                <span class="text-xs text-slate-400 font-mono font-normal">({{ $items->total() }} records)</span>
            </h2>
        </div>

        @if($items->isEmpty())
            <div class="p-8 text-center bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                <p class="text-xs text-slate-400 font-bold">No collections recorded for this filter on {{ \Carbon\Carbon::parse($businessDate)->format('d M Y') }}.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($items as $item)
                    <div class="p-4 rounded-2xl border border-slate-100 bg-slate-50/70 hover:bg-white hover:border-slate-200 hover:shadow-xs transition-all flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        
                        <!-- Left Block: Method Icon, Shop & Destination -->
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200 flex items-center justify-center text-slate-700 shadow-xs flex-shrink-0">
                                @if(str_contains(strtolower($item['payment_method']), 'paytm') || str_contains(strtolower($item['payment_method']), 'upi'))
                                    <i data-lucide="qr-code" class="w-5 h-5 text-sky-600"></i>
                                @elseif(str_contains(strtolower($item['payment_method']), 'cash'))
                                    <i data-lucide="banknote" class="w-5 h-5 text-emerald-600"></i>
                                @elseif(str_contains(strtolower($item['payment_method']), 'cheque'))
                                    <i data-lucide="file-check-2" class="w-5 h-5 text-purple-600"></i>
                                @elseif(str_contains(strtolower($item['payment_method']), 'card'))
                                    <i data-lucide="credit-card" class="w-5 h-5"></i>
                                @else
                                    <i data-lucide="smartphone" class="w-5 h-5"></i>
                                @endif
                            </div>

                            <div class="space-y-0.5">
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-sm text-slate-900">{{ $item['shop_name'] }}</span>
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-[10px] font-extrabold uppercase tracking-wider text-slate-600 border border-slate-200">
                                        {{ $item['payment_method'] }}
                                    </span>
                                </div>

                                <div class="text-xs text-slate-500 font-medium">
                                    @if($item['destination_name'])
                                        <span class="font-bold text-slate-700">{{ $item['destination_name'] }}</span>
                                    @elseif($item['location_name'])
                                        <span class="font-bold text-slate-700">{{ $item['location_name'] }}</span>
                                    @else
                                        <span>Company Bank Account</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Right Block: Amount, Status & Action -->
                        <div class="flex items-center justify-between sm:justify-end gap-4 w-full sm:w-auto border-t sm:border-t-0 pt-3 sm:pt-0 border-slate-100">
                            
                            <div class="text-left sm:text-right">
                                <div class="text-base sm:text-lg font-black text-slate-900 font-mono">
                                    ₹{{ number_format($item['amount'], 2) }}
                                </div>

                                <div>
                                    @if($item['display_status'] === 'RECEIVED')
                                        <span class="inline-flex items-center gap-1 text-[11px] font-extrabold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                                            <i data-lucide="check" class="w-3 h-3"></i> RECEIVED
                                        </span>
                                    @elseif($item['display_status'] === 'NEEDS VERIFICATION')
                                        <span class="inline-flex items-center gap-1 text-[11px] font-extrabold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200">
                                            <i data-lucide="alert-circle" class="w-3 h-3"></i> NEEDS VERIFICATION
                                        </span>
                                    @elseif($item['display_status'] === 'CASH WITH SHOP')
                                        <span class="inline-flex items-center gap-1 text-[11px] font-extrabold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-md border border-sky-200">
                                            <i data-lucide="store" class="w-3 h-3"></i> CASH WITH SHOP
                                        </span>
                                    @elseif($item['display_status'] === 'FLOATING')
                                        <span class="inline-flex items-center gap-1 text-[11px] font-extrabold text-purple-700 bg-purple-50 px-2 py-0.5 rounded-md border border-purple-200">
                                            <i data-lucide="clock" class="w-3 h-3"></i> FLOATING
                                        </span>
                                    @elseif($item['display_status'] === 'REJECTED')
                                        <span class="inline-flex items-center gap-1 text-[11px] font-extrabold text-rose-700 bg-rose-50 px-2 py-0.5 rounded-md border border-rose-200">
                                            <i data-lucide="x" class="w-3 h-3"></i> REJECTED
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-[11px] font-extrabold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">
                                            {{ $item['display_status'] }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- Single Allowed Action: VIEW -->
                            <a href="{{ $item['detail_url'] }}"
                               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-900 text-slate-700 hover:text-white text-xs font-extrabold transition-all shadow-xs flex-shrink-0 cursor-pointer">
                                <span>View</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </a>

                        </div>

                    </div>
                @endforeach
            </div>

            <!-- Transaction Paginator Links -->
            @if($items->hasPages())
                <div class="pt-4 border-t border-slate-100">
                    {{ $items->links() }}
                </div>
            @endif
        @endif
    </div>

    <!-- ── MONTHLY PENDING CALENDAR (Mobile-Optimized) ────────────────────── -->
    <div id="monthly-pending-calendar" class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
        
        <!-- Calendar Header: Navigation & Month Label -->
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-xl bg-slate-100 text-slate-700">
                        <i data-lucide="calendar-days" class="w-4 h-4"></i>
                    </span>
                    <h3 class="text-base sm:text-lg font-black text-slate-900">{{ $calendarData['month_title'] }}</h3>
                </div>
                <p class="text-[11px] font-bold text-slate-400 mt-0.5">
                    Click any date to switch day view; badges show pending items.
                </p>
            </div>

            <!-- Month Switcher (‹ Month ›) -->
            <div class="flex items-center gap-1.5">
                <a href="{{ route('admin.cashbook.money-flow', ['date' => $businessDate, 'calendar_month' => $calendarData['prev_month'], 'shop_id' => $selectedShopId, 'status' => $selectedStatus]) }}"
                   class="inline-flex items-center justify-center p-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 shadow-xs transition"
                   title="Previous Month"
                   aria-label="Previous Month">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </a>

                <a href="{{ route('admin.cashbook.money-flow', ['date' => today()->toDateString(), 'calendar_month' => today()->format('Y-m'), 'shop_id' => $selectedShopId]) }}"
                   class="px-2.5 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-black text-slate-700 shadow-xs transition">
                    Current
                </a>

                <a href="{{ route('admin.cashbook.money-flow', ['date' => $businessDate, 'calendar_month' => $calendarData['next_month'], 'shop_id' => $selectedShopId, 'status' => $selectedStatus]) }}"
                   class="inline-flex items-center justify-center p-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 shadow-xs transition"
                   title="Next Month"
                   aria-label="Next Month">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>
            </div>
        </div>

        <!-- 7-Column Day Names (M T W T F S S) -->
        <div class="grid grid-cols-7 gap-1 sm:gap-1.5 text-center text-[10px] sm:text-xs font-black uppercase text-slate-400 border-b border-slate-100 pb-2">
            <div>M</div>
            <div>T</div>
            <div>W</div>
            <div>T</div>
            <div>F</div>
            <div>S</div>
            <div>S</div>
        </div>

        <!-- Calendar Month Grid -->
        <div class="grid grid-cols-7 gap-1 sm:gap-1.5">
            @foreach($calendarData['weeks'] as $week)
                @foreach($week as $day)
                    @if($day === null)
                        <div class="h-11 sm:h-12 rounded-2xl bg-slate-50/40"></div>
                    @else
                        @php
                            $dateUrl = route('admin.cashbook.money-flow', [
                                'date' => $day['date'],
                                'calendar_month' => $calendarData['calendar_month'],
                                'shop_id' => $selectedShopId,
                            ]);
                            $pendingUrl = route('admin.cashbook.money-flow', [
                                'date' => $day['date'],
                                'status' => 'pending',
                                'calendar_month' => $calendarData['calendar_month'],
                                'shop_id' => $selectedShopId,
                            ]);
                        @endphp
                        <div class="h-11 sm:h-12 p-1 rounded-2xl flex flex-col items-center justify-between transition-all relative border {{ $day['is_selected'] ? 'bg-slate-900 border-slate-900 text-white shadow-xs' : ($day['is_today'] ? 'bg-emerald-50/60 border-emerald-300 text-emerald-950 ring-2 ring-emerald-500/20' : 'bg-slate-50/80 border-slate-100 hover:border-slate-300 text-slate-700') }}">
                            
                            <!-- Date Link -->
                            <a href="{{ $dateUrl }}" class="font-mono text-xs sm:text-sm font-black w-full text-center flex-1 flex items-center justify-center cursor-pointer">
                                {{ $day['day'] }}
                            </a>

                            <!-- Pending Badge (Only if > 0) -->
                            @if($day['pending_count'] > 0)
                                <a href="{{ $pendingUrl }}"
                                   class="px-1.5 py-0.2 rounded-full bg-amber-500 hover:bg-amber-600 text-white text-[9px] font-black leading-none shadow-xs transition cursor-pointer"
                                   title="{{ $day['pending_count'] }} pending on {{ $day['date'] }}">
                                    {{ $day['pending_count'] }}
                                </a>
                            @endif
                        </div>
                    @endif
                @endforeach
            @endforeach
        </div>

    </div>

</div>
@endsection
