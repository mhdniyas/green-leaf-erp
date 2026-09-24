@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop').' — Monthly Payments & Settlement Control')

@section('content')
<div x-data="shopMonthlyPaymentsControl()" class="mx-auto max-w-7xl space-y-6 pb-16">
    <!-- Header with Back & Settings buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cashbook.shop.show', ['shop' => $shopKey, 'month' => $month]) }}"
               class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition"
               title="Back to Shop Overview">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-black text-slate-900 tracking-tight">{{ $currentShop->name }}</h1>
                    <span class="text-xs font-bold px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-600 font-mono">{{ $currentShop->code }}</span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Monthly Payments, Verification &amp; Settlement Allocation</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.cashbook.settings.shop.payments.index', $shopKey) }}#allocation"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-xs transition"
               title="Allocation Settings">
                <i data-lucide="sliders" class="w-4 h-4 text-slate-500"></i>
                <span>Allocation Settings</span>
            </a>
            <a href="{{ route('admin.cashbook.settings.shop', $shopKey) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-xs transition"
               title="Cashbook Settings">
                <i data-lucide="settings" class="w-4 h-4 text-slate-500"></i>
                <span>Settings</span>
            </a>
        </div>
    </div>

    <!-- Unified Cashbook Navigation Bar -->
    <div class="flex items-center gap-2 border-b border-slate-200">
        <a href="{{ route('admin.cashbook.shop.history.payments', ['shop' => $shopKey, 'month' => $month]) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-black border-b-2 border-slate-900 text-slate-900 transition">
            <i data-lucide="receipt" class="w-4 h-4 text-slate-800"></i>
            <span>Payment History</span>
        </a>
        <a href="{{ route('admin.cashbook.shop.history.allocations', ['shop' => $shopKey, 'month' => $month]) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-black border-b-2 border-transparent text-slate-500 hover:text-slate-900 hover:border-slate-300 transition">
            <i data-lucide="split" class="w-4 h-4 text-slate-400"></i>
            <span>Expense Allocations</span>
        </a>
    </div>

    <!-- 1. MONTH FILTER & PRIMARY ACTIONS BAR -->
    @php
        $currentMonthCarbon = \Carbon\Carbon::createFromFormat('Y-m', $month);
        $prevMonth = $currentMonthCarbon->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentMonthCarbon->copy()->addMonth()->format('Y-m');
        $isCurrentMonth = ($month === now()->format('Y-m'));
    @endphp
    <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-3 text-xs font-bold">
        <!-- Month Navigator -->
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.cashbook.shop.history.payments', ['shop' => $shopKey, 'month' => $prevMonth]) }}"
               class="p-2 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 transition"
               title="Previous Month">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </a>

            <form method="GET" action="{{ route('admin.cashbook.shop.history.payments', $shopKey) }}" class="flex items-center gap-2">
                <div class="flex items-center gap-1.5">
                    <label class="text-slate-400 uppercase text-[10px] tracking-wider font-mono">Month:</label>
                    <input type="month"
                           name="month"
                           value="{{ $month }}"
                           onchange="this.form.submit()"
                           class="px-3 py-1.5 bg-slate-50 rounded-xl border border-slate-300 font-mono text-slate-900 focus:bg-white focus:outline-none cursor-pointer">
                </div>
            </form>

            <a href="{{ route('admin.cashbook.shop.history.payments', ['shop' => $shopKey, 'month' => $nextMonth]) }}"
               class="p-2 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 transition"
               title="Next Month">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </a>

            @if(! $isCurrentMonth)
                <a href="{{ route('admin.cashbook.shop.history.payments', ['shop' => $shopKey, 'month' => now()->format('Y-m')]) }}"
                   class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold transition">
                    Current Month
                </a>
            @endif

            <div class="hidden sm:flex items-center gap-2 text-slate-500 font-mono text-xs pl-2 border-l border-slate-200">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>{{ $currentMonthCarbon->format('F Y') }}</span>
            </div>
        </div>

        <!-- Primary Top Action Buttons -->
        <div class="flex items-center gap-2.5">
            @if($summary['unverified_direct_count'] > 0)
                <button type="button"
                        @click="showBulkVerifyModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-black shadow-xs transition cursor-pointer">
                    <i data-lucide="check-check" class="w-4 h-4"></i>
                    <span>Verify All Direct Bank</span>
                    <span class="px-2 py-0.5 rounded-md bg-amber-600/60 font-mono text-[11px]">
                        ₹{{ number_format($summary['unverified_direct_total'], 2) }} ({{ $summary['unverified_direct_count'] }})
                    </span>
                </button>
            @endif

            <button type="button"
                    @click="openRecordCashModal()"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-xs transition cursor-pointer">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Record Cash Receipt</span>
            </button>
        </div>
    </div>

    <!-- 2. TOP SUMMARY (5 CARDS) -->
    <!-- 2. TOP SUMMARY (5 CARDS) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
        <!-- Card 1: COMPANY PAYABLE -->
        <div class="p-4 bg-white rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between transition hover:shadow-sm">
            <div>
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="text-[11px] font-black uppercase text-slate-500 tracking-wider">Company Payable</span>
                    <div class="p-1.5 rounded-lg bg-slate-100 text-slate-600 shrink-0">
                        <i data-lucide="calculator" class="w-4 h-4"></i>
                    </div>
                </div>
                <div class="text-xl font-black text-slate-900 tracking-tight font-mono">
                    ₹{{ number_format($summary['company_payable'], 2) }}
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between gap-1 text-[11px] font-sans">
                <span class="inline-flex items-center gap-1 text-slate-600">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 shrink-0"></span>
                    Bank: <strong class="font-mono text-slate-800 font-bold">₹{{ number_format($summary['payable_bank'] ?? 0, 0) }}</strong>
                </span>
                <span class="inline-flex items-center gap-1 text-slate-600">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                    Cash: <strong class="font-mono text-slate-800 font-bold">₹{{ number_format($summary['payable_cash'] ?? 0, 0) }}</strong>
                </span>
            </div>
        </div>

        <!-- Card 2: RECEIVED / VERIFIED -->
        <div class="p-4 bg-emerald-50/40 rounded-2xl border border-emerald-200/90 shadow-xs flex flex-col justify-between transition hover:shadow-sm">
            <div>
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="text-[11px] font-black uppercase text-emerald-800 tracking-wider">Received / Verified</span>
                    <div class="p-1.5 rounded-lg bg-emerald-100 text-emerald-700 shrink-0">
                        <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                    </div>
                </div>
                <div class="text-xl font-black text-emerald-700 tracking-tight font-mono">
                    ₹{{ number_format($summary['received'], 2) }}
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-emerald-200/50 flex items-center justify-between gap-1 text-[11px] font-sans">
                <span class="inline-flex items-center gap-1 text-emerald-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 shrink-0"></span>
                    Bank: <strong class="font-mono text-emerald-950 font-bold">₹{{ number_format($summary['received_bank'] ?? 0, 0) }}</strong>
                </span>
                <span class="inline-flex items-center gap-1 text-emerald-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 shrink-0"></span>
                    Cash: <strong class="font-mono text-emerald-950 font-bold">₹{{ number_format($summary['received_cash'] ?? 0, 0) }}</strong>
                </span>
            </div>
        </div>

        <!-- Card 3: PENDING VERIFICATION -->
        <div class="p-4 rounded-2xl border shadow-xs flex flex-col justify-between transition hover:shadow-sm {{ $summary['pending_verification'] > 0.01 ? 'bg-amber-50/50 border-amber-200' : 'bg-white border-slate-200' }}">
            <div>
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="text-[11px] font-black uppercase tracking-wider {{ $summary['pending_verification'] > 0.01 ? 'text-amber-800' : 'text-slate-500' }}">Pending Verification</span>
                    <div class="p-1.5 rounded-lg shrink-0 {{ $summary['pending_verification'] > 0.01 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-400' }}">
                        <i data-lucide="clock" class="w-4 h-4"></i>
                    </div>
                </div>
                <div class="text-xl font-black tracking-tight font-mono {{ $summary['pending_verification'] > 0.01 ? 'text-amber-700' : 'text-slate-400' }}">
                    ₹{{ number_format($summary['pending_verification'], 2) }}
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t flex items-center justify-between gap-1 text-[11px] font-sans {{ $summary['pending_verification'] > 0.01 ? 'border-amber-200/50' : 'border-slate-100' }}">
                <span class="inline-flex items-center gap-1 {{ $summary['pending_verification'] > 0.01 ? 'text-amber-800' : 'text-slate-400' }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 shrink-0"></span>
                    Bank: <strong class="font-mono font-bold {{ $summary['pending_verification'] > 0.01 ? 'text-amber-950' : 'text-slate-500' }}">₹{{ number_format($summary['pending_bank'] ?? 0, 0) }}</strong>
                </span>
                <span class="inline-flex items-center gap-1 {{ $summary['pending_verification'] > 0.01 ? 'text-amber-800' : 'text-slate-400' }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                    Cash: <strong class="font-mono font-bold {{ $summary['pending_verification'] > 0.01 ? 'text-amber-950' : 'text-slate-500' }}">₹{{ number_format($summary['pending_cash'] ?? 0, 0) }}</strong>
                </span>
            </div>
        </div>

        <!-- Card 4: PENDING MATCH -->
        <div class="p-4 rounded-2xl border shadow-xs flex flex-col justify-between transition hover:shadow-sm {{ $summary['pending_payable_match'] > 0.01 ? 'bg-indigo-50/50 border-indigo-200' : 'bg-white border-slate-200' }}">
            <div>
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="text-[11px] font-black uppercase tracking-wider {{ $summary['pending_payable_match'] > 0.01 ? 'text-indigo-800' : 'text-slate-500' }}">Pending Match</span>
                    <div class="p-1.5 rounded-lg shrink-0 {{ $summary['pending_payable_match'] > 0.01 ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-400' }}">
                        <i data-lucide="link" class="w-4 h-4"></i>
                    </div>
                </div>
                <div class="text-xl font-black tracking-tight font-mono {{ $summary['pending_payable_match'] > 0.01 ? 'text-indigo-800' : 'text-slate-400' }}">
                    ₹{{ number_format($summary['pending_payable_match'], 2) }}
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t flex items-center justify-between gap-1 text-[11px] font-sans {{ $summary['pending_payable_match'] > 0.01 ? 'border-indigo-200/50' : 'border-slate-100' }}">
                <span class="inline-flex items-center gap-1 {{ $summary['pending_payable_match'] > 0.01 ? 'text-indigo-800' : 'text-slate-400' }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 shrink-0"></span>
                    Bank: <strong class="font-mono font-bold {{ $summary['pending_payable_match'] > 0.01 ? 'text-indigo-950' : 'text-slate-500' }}">₹{{ number_format($summary['pending_match_bank'] ?? 0, 0) }}</strong>
                </span>
                <span class="inline-flex items-center gap-1 {{ $summary['pending_payable_match'] > 0.01 ? 'text-indigo-800' : 'text-slate-400' }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                    Cash: <strong class="font-mono font-bold {{ $summary['pending_payable_match'] > 0.01 ? 'text-indigo-950' : 'text-slate-500' }}">₹{{ number_format($summary['pending_match_cash'] ?? 0, 0) }}</strong>
                </span>
            </div>
        </div>

        <!-- Card 5: PENDING ALLOCATION -->
        <div class="p-4 rounded-2xl border shadow-xs flex flex-col justify-between transition hover:shadow-sm {{ $summary['pending_allocation'] > 0.01 ? 'bg-sky-50/50 border-sky-200' : 'bg-white border-slate-200' }}">
            <div>
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="text-[11px] font-black uppercase tracking-wider {{ $summary['pending_allocation'] > 0.01 ? 'text-sky-800' : 'text-slate-500' }}">Pending Allocation</span>
                    <div class="p-1.5 rounded-lg shrink-0 {{ $summary['pending_allocation'] > 0.01 ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-400' }}">
                        <i data-lucide="layers" class="w-4 h-4"></i>
                    </div>
                </div>
                <div class="text-xl font-black tracking-tight font-mono {{ $summary['pending_allocation'] > 0.01 ? 'text-sky-800' : 'text-slate-400' }}">
                    ₹{{ number_format($summary['pending_allocation'], 2) }}
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t flex items-center justify-between gap-1 text-[11px] font-sans {{ $summary['pending_allocation'] > 0.01 ? 'border-sky-200/50' : 'border-slate-100' }}">
                <span class="inline-flex items-center gap-1 {{ $summary['pending_allocation'] > 0.01 ? 'text-sky-800' : 'text-slate-400' }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 shrink-0"></span>
                    Bank: <strong class="font-mono font-bold {{ $summary['pending_allocation'] > 0.01 ? 'text-sky-950' : 'text-slate-500' }}">₹{{ number_format($summary['pending_alloc_bank'] ?? 0, 0) }}</strong>
                </span>
                <span class="inline-flex items-center gap-1 {{ $summary['pending_allocation'] > 0.01 ? 'text-sky-800' : 'text-slate-400' }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                    Cash: <strong class="font-mono font-bold {{ $summary['pending_allocation'] > 0.01 ? 'text-sky-950' : 'text-slate-500' }}">₹{{ number_format($summary['pending_alloc_cash'] ?? 0, 0) }}</strong>
                </span>
            </div>
        </div>
    </div>

    <!-- 3. DAILY CONTROL BREAKDOWN TABLE -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <i data-lucide="calendar" class="w-4 h-4 text-emerald-600"></i>
                    <span>Daily Company Payable Breakdown</span>
                    <span class="text-xs font-bold text-slate-500 font-mono">({{ $currentMonthCarbon->format('F Y') }})</span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Gross Company Payable vs Attributed Receipts per business date</p>
            </div>
        </div>

        @if(empty($dailyRows))
            <div class="py-12 px-4 text-center rounded-2xl bg-slate-50 border border-slate-200 text-xs font-bold text-slate-500">
                <i data-lucide="inbox" class="w-6 h-6 mx-auto mb-2 text-slate-400"></i>
                No cashbook activity or payments recorded for {{ $currentMonthCarbon->format('F Y') }}.
            </div>
        @else
            <div class="overflow-x-auto rounded-2xl border border-slate-200">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                            <th class="py-3 px-4 rounded-l-xl">Date</th>
                            @foreach($dynamicContributors as $contrib)
                                <th class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>{{ $contrib['label'] }}</span>
                                        <span class="text-[9px] px-1 py-0.2 rounded {{ $contrib['is_direct_bank'] ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-600' }}">
                                            {{ $contrib['is_direct_bank'] ? 'Direct' : 'Shop' }}
                                        </span>
                                    </div>
                                </th>
                            @endforeach
                            <th class="py-3 px-4 text-right bg-slate-100/50 font-black text-slate-800">Gross Payable</th>
                            <th class="py-3 px-4 text-right font-black text-emerald-800">Received</th>
                            <th class="py-3 px-4 text-right font-black text-amber-800">Pending</th>
                            <th class="py-3 px-4 text-center rounded-r-xl">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-mono text-xs">
                        @foreach($dailyRows as $row)
                            <tr class="hover:bg-slate-50/80 transition-colors {{ $row['pending'] > 0.01 ? 'bg-amber-50/15' : '' }}">
                                <!-- Date -->
                                <td class="py-3 px-4 font-sans">
                                    <span class="font-extrabold text-slate-900 block text-sm">{{ $row['formatted_date'] }}</span>
                                    <span class="text-[10px] text-slate-400 uppercase font-semibold font-mono">{{ $row['day_name'] }}</span>
                                </td>

                                <!-- Dynamic Contributor Columns -->
                                @foreach($dynamicContributors as $contrib)
                                    @php
                                        $val = $row['contributors'][$contrib['key']] ?? 0.0;
                                    @endphp
                                    <td class="py-3 px-4 text-right {{ $val > 0.01 ? 'font-bold text-slate-800' : 'text-slate-300' }}">
                                        {{ $val > 0.01 ? '₹'.number_format($val, 2) : '—' }}
                                    </td>
                                @endforeach

                                <!-- Company Payable (Gross) -->
                                <td class="py-3 px-4 text-right font-bold text-slate-900 bg-slate-50/50">
                                    ₹{{ number_format($row['company_payable'], 2) }}
                                </td>

                                <!-- Received / Verified -->
                                <td class="py-3 px-4 text-right font-bold text-emerald-700">
                                    ₹{{ number_format($row['received'], 2) }}
                                </td>

                                <!-- Pending for Date -->
                                <td class="py-3 px-4 text-right font-bold {{ $row['pending'] > 0.01 ? 'text-amber-700' : 'text-slate-300' }}">
                                    {{ $row['pending'] > 0.01 ? '₹'.number_format($row['pending'], 2) : '₹0.00' }}
                                </td>

                                <!-- Action -->
                                <td class="py-3 px-4 text-center font-sans">
                                    <div class="inline-flex items-center justify-center gap-1.5 flex-wrap">
                                        @if($row['action'] === 'verify_direct')
                                            <button type="button"
                                                    @click="openVerifyModal({{ json_encode($row) }})"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-black shadow-xs transition cursor-pointer"
                                                    title="Confirm &amp; Verify direct-bank collections for this day">
                                                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                                <span>VERIFY DIRECT</span>
                                            </button>
                                        @elseif($row['action'] === 'record_cash')
                                            <button type="button"
                                                    @click="openRecordCashModal('{{ $row['business_date'] }}', {{ $row['pending'] }})"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-xs transition cursor-pointer"
                                                    title="Record received cash / shop-held payment">
                                                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                                <span>RECORD CASH</span>
                                            </button>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-extrabold">
                                                <i data-lucide="check-check" class="w-3.5 h-3.5 text-emerald-600"></i>
                                                <span>COMPLETED</span>
                                            </span>
                                        @endif

                                        @if($row['received'] > 0.01 && ! empty($row['active_matches']))
                                            <button type="button"
                                                    @click="openDayMatchesModal({{ json_encode($row) }})"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-extrabold shadow-xs transition cursor-pointer"
                                                    title="View active receipt matches contributing to this date">
                                                <i data-lucide="list-filter" class="w-3.5 h-3.5 text-slate-500"></i>
                                                <span>Matches ({{ count($row['active_matches']) }})</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- 4. RECEIVED MONEY & WORK QUEUE SECTION -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <i data-lucide="wallet" class="w-4 h-4 text-indigo-600"></i>
                    <span>Received Money &amp; Work Queue</span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Verified company receipts, Company Payable attribution, and expense settlement allocations</p>
            </div>

            @if($autoAllocateEnabled && $summary['pending_allocation'] > 0.01)
                <form method="POST" action="{{ route('admin.cashbook.shop.allocate-payments.bulk', $shopKey) }}">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="hidden" name="expected_total" value="{{ $summary['pending_allocation'] }}">
                    <input type="hidden" name="submission_uuid" :value="generateUuid()">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-extrabold shadow-xs transition cursor-pointer">
                        <i data-lucide="zap" class="w-3.5 h-3.5 text-sky-200"></i>
                        <span>Auto Allocate All Expenses</span>
                    </button>
                </form>
            @endif
        </div>

        @if(empty($workQueue))
            <div class="py-10 px-4 text-center rounded-2xl bg-slate-50 border border-slate-200 text-xs font-bold text-slate-500">
                <i data-lucide="receipt" class="w-6 h-6 mx-auto mb-2 text-slate-400"></i>
                No verified receipts in {{ $currentMonthCarbon->format('F Y') }}.
            </div>
        @else
            <div class="overflow-x-auto rounded-2xl border border-slate-200">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                            <th class="py-3 px-4 rounded-l-xl">Receipt Date &amp; Ref</th>
                            <th class="py-3 px-4">Method &amp; Account</th>
                            <th class="py-3 px-4 text-right font-black text-slate-800">Amount</th>
                            <th class="py-3 px-4 text-center">Payable Match</th>
                            <th class="py-3 px-4 text-center">Expense Allocation</th>
                            <th class="py-3 px-4 text-center rounded-r-xl">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        @foreach($workQueue as $receipt)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <!-- Date & Ref -->
                                <td class="py-3.5 px-4">
                                    <span class="font-extrabold text-slate-900 block">{{ $receipt['formatted_date'] }}</span>
                                    <span class="text-[10px] text-slate-500 font-mono">{{ $receipt['reference'] }}</span>
                                </td>

                                <!-- Method & Account -->
                                <td class="py-3.5 px-4">
                                    <span class="font-bold text-slate-800 block">{{ $receipt['payment_method'] }}</span>
                                    <span class="text-[10px] text-slate-500">{{ $receipt['account_name'] }}</span>
                                </td>

                                <!-- Amount -->
                                <td class="py-3.5 px-4 text-right font-mono font-black text-slate-900 text-sm">
                                    ₹{{ number_format($receipt['amount'], 2) }}
                                </td>

                                <!-- Payable Match Status -->
                                <td class="py-3.5 px-4 text-center font-sans">
                                    @if($receipt['match_status'] === 'fully_matched')
                                        <div class="space-y-1">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-extrabold">
                                                <i data-lucide="check" class="w-3 h-3 text-emerald-600"></i>
                                                <span>Matched ₹{{ number_format($receipt['matched_amount'], 2) }}</span>
                                            </span>
                                            <div>
                                                <form method="POST"
                                                      action="{{ route('admin.cashbook.shop.history.payments.undo-receipt-matches', $shopKey) }}"
                                                      onsubmit="return confirm('Clear payable matches for this receipt? This will release ₹{{ number_format($receipt['matched_amount'], 2) }} back to open payable dates.')"
                                                      class="inline-block">
                                                    @csrf
                                                    <input type="hidden" name="payment_request_id" value="{{ $receipt['id'] }}">
                                                    <input type="hidden" name="month" value="{{ $month }}">
                                                    <button type="submit"
                                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-[10px] font-bold transition cursor-pointer"
                                                            title="Undo / Clear Payable Match">
                                                        <i data-lucide="undo-2" class="w-3 h-3"></i>
                                                        <span>Undo Match</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @elseif($receipt['match_status'] === 'partially_matched')
                                        <div class="space-y-1">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-800 border border-indigo-200 text-[10px] font-extrabold">
                                                <span>Matched ₹{{ number_format($receipt['matched_amount'], 2) }} of ₹{{ number_format($receipt['amount'], 2) }}</span>
                                            </span>
                                            <span class="block text-[10px] text-amber-700 font-bold font-mono">Unmatched: ₹{{ number_format($receipt['unmatched_amount'], 2) }}</span>
                                            <div>
                                                <form method="POST"
                                                      action="{{ route('admin.cashbook.shop.history.payments.undo-receipt-matches', $shopKey) }}"
                                                      onsubmit="return confirm('Clear payable matches for this receipt? This will release ₹{{ number_format($receipt['matched_amount'], 2) }} back to open payable dates.')"
                                                      class="inline-block">
                                                    @csrf
                                                    <input type="hidden" name="payment_request_id" value="{{ $receipt['id'] }}">
                                                    <input type="hidden" name="month" value="{{ $month }}">
                                                    <button type="submit"
                                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-[10px] font-bold transition cursor-pointer"
                                                            title="Undo / Clear Payable Match">
                                                        <i data-lucide="undo-2" class="w-3 h-3"></i>
                                                        <span>Undo Match</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-extrabold">
                                            <i data-lucide="alert-circle" class="w-3 h-3 text-amber-600"></i>
                                            <span>Unmatched</span>
                                        </span>
                                    @endif
                                </td>

                                <!-- Expense Allocation Status -->
                                <td class="py-3.5 px-4 text-center font-sans">
                                    @if($receipt['allocation_status'] === 'fully_allocated')
                                        <div class="space-y-1">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-sky-50 text-sky-800 border border-sky-200 text-[11px] font-extrabold">
                                                <i data-lucide="check" class="w-3 h-3 text-sky-600"></i>
                                                <span>Allocated ₹{{ number_format($receipt['allocated_amount'], 2) }}</span>
                                            </span>
                                            <div>
                                                <form method="POST"
                                                      action="{{ route('admin.cashbook.shop.history.payments.undo-receipt-allocations', $shopKey) }}"
                                                      onsubmit="return confirm('Clear expense allocations for this receipt? This will restore ₹{{ number_format($receipt['allocated_amount'], 2) }} unallocated balance.')"
                                                      class="inline-block">
                                                    @csrf
                                                    <input type="hidden" name="payment_request_id" value="{{ $receipt['id'] }}">
                                                    <input type="hidden" name="month" value="{{ $month }}">
                                                    <button type="submit"
                                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-[10px] font-bold transition cursor-pointer"
                                                            title="Undo / Clear Expense Allocation">
                                                        <i data-lucide="undo-2" class="w-3 h-3"></i>
                                                        <span>Undo Allocation</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @elseif($receipt['allocation_status'] === 'partially_allocated')
                                        <div class="space-y-1">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-sky-50 text-sky-800 border border-sky-200 text-[10px] font-extrabold">
                                                <span>Allocated ₹{{ number_format($receipt['allocated_amount'], 2) }} of ₹{{ number_format($receipt['amount'], 2) }}</span>
                                            </span>
                                            <span class="block text-[10px] text-slate-500 font-bold font-mono">Unallocated: ₹{{ number_format($receipt['unallocated_amount'], 2) }}</span>
                                            <div>
                                                <form method="POST"
                                                      action="{{ route('admin.cashbook.shop.history.payments.undo-receipt-allocations', $shopKey) }}"
                                                      onsubmit="return confirm('Clear expense allocations for this receipt? This will restore ₹{{ number_format($receipt['allocated_amount'], 2) }} unallocated balance.')"
                                                      class="inline-block">
                                                    @csrf
                                                    <input type="hidden" name="payment_request_id" value="{{ $receipt['id'] }}">
                                                    <input type="hidden" name="month" value="{{ $month }}">
                                                    <button type="submit"
                                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-[10px] font-bold transition cursor-pointer"
                                                            title="Undo / Clear Expense Allocation">
                                                        <i data-lucide="undo-2" class="w-3 h-3"></i>
                                                        <span>Undo Allocation</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 border border-slate-200 text-[11px] font-extrabold">
                                            <span>Unallocated</span>
                                        </span>
                                    @endif
                                </td>

                                <!-- Actions -->
                                <td class="py-3.5 px-4 text-center font-sans space-x-1">
                                    @if($receipt['unmatched_amount'] > 0.01)
                                        <button type="button"
                                                @click="openFifoMatchModal({{ $receipt['id'] }})"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-extrabold shadow-xs transition cursor-pointer"
                                                title="Match to open Company Payable business dates via FIFO">
                                            <i data-lucide="link-2" class="w-3.5 h-3.5"></i>
                                            <span>Match (FIFO)</span>
                                        </button>
                                    @endif

                                    @if($receipt['unallocated_amount'] > 0.01)
                                        <form method="POST" action="{{ route('admin.cashbook.shop.history.payments.allocate-expenses', $shopKey) }}" class="inline-block">
                                            @csrf
                                            <input type="hidden" name="payment_request_id" value="{{ $receipt['id'] }}">
                                            <input type="hidden" name="month" value="{{ $month }}">
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-[11px] font-extrabold shadow-xs transition cursor-pointer"
                                                    title="Allocate against shop expenses">
                                                <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                                                <span>Allocate</span>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- 5. MODAL: BULK DIRECT BANK VERIFICATION -->
    <div x-show="showBulkVerifyModal"
         x-cloak
         @keydown.escape.window="showBulkVerifyModal = false"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showBulkVerifyModal = false"
             class="bg-white rounded-3xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 bg-gradient-to-r from-amber-600 to-amber-700 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-white/20 text-white">
                        <i data-lucide="check-check" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wide">Verify All Direct Bank Collections</h3>
                        <p class="text-[11px] text-amber-100 font-medium">{{ $currentMonthCarbon->format('F Y') }} · {{ $currentShop->name }}</p>
                    </div>
                </div>
                <button type="button" @click="showBulkVerifyModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition cursor-pointer">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST"
                  action="{{ route('admin.cashbook.shop.history.payments.verify-all-direct', $shopKey) }}"
                  class="p-6 space-y-4 font-sans">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">

                <div class="p-4 bg-amber-50/60 rounded-2xl border border-amber-200 space-y-2.5 font-mono text-xs">
                    <div class="flex items-center justify-between border-b border-amber-200/80 pb-2">
                        <span class="text-amber-900 font-sans font-bold">Unverified Items Count:</span>
                        <span class="font-black text-amber-900 text-sm">{{ $summary['unverified_direct_count'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-amber-900 font-sans font-bold">Total Direct Bank Amount:</span>
                        <span class="text-base font-black text-amber-800">₹{{ number_format($summary['unverified_direct_total'], 2) }}</span>
                    </div>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    This will process and verify all direct-bank transactions for <strong>{{ $currentMonthCarbon->format('F Y') }}</strong> into company accounts and automatically record 1:1 Company Payable matches for each originating business date.
                </p>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="showBulkVerifyModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition text-xs cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-black text-xs shadow-xs transition cursor-pointer">
                        Confirm &amp; Verify All
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 6. MODAL: RECORD MANUAL CASH RECEIPT -->
    <div x-show="showRecordCashModal"
         x-cloak
         @keydown.escape.window="showRecordCashModal = false"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showRecordCashModal = false"
             class="bg-white rounded-3xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 bg-gradient-to-r from-emerald-700 to-emerald-800 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-emerald-600 text-white">
                        <i data-lucide="plus-circle" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wide">Record Cash / Shop-Held Receipt</h3>
                        <p class="text-[11px] text-emerald-200 font-medium">{{ $currentShop->name }}</p>
                    </div>
                </div>
                <button type="button" @click="showRecordCashModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition cursor-pointer">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST"
                  action="{{ route('admin.cashbook.shop.history.payments.record-cash', $shopKey) }}"
                  class="p-6 space-y-4 font-sans text-xs">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">

                <!-- Amount & Date -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Receipt Amount (₹) *</label>
                        <input type="number"
                               step="0.01"
                               min="0.01"
                               name="amount"
                               x-model="cashForm.amount"
                               required
                               class="w-full px-3.5 py-2 rounded-xl border border-slate-300 font-mono font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Receipt Date *</label>
                        <input type="date"
                               name="payment_date"
                               x-model="cashForm.payment_date"
                               required
                               class="w-full px-3.5 py-2 rounded-xl border border-slate-300 font-mono font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                    </div>
                </div>

                <!-- Destination Company Account & Method -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Destination Company Account *</label>
                        <select name="company_account_id"
                                x-model="cashForm.company_account_id"
                                required
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-300 font-sans font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                            <option value="">Select Account</option>
                            @foreach($companyAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }} ({{ ucfirst($acc->account_type) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Payment Method *</label>
                        <select name="payment_method"
                                x-model="cashForm.payment_method"
                                required
                                class="w-full px-3.5 py-2 rounded-xl border border-slate-300 font-sans font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="upi">UPI / QR</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                </div>

                <!-- Reference & Notes -->
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Reference / Voucher # (Optional)</label>
                    <input type="text"
                           name="payment_reference"
                           x-model="cashForm.payment_reference"
                           placeholder="e.g. CASH-SEPT-01"
                           class="w-full px-3.5 py-2 rounded-xl border border-slate-300 font-mono text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Admin Notes (Optional)</label>
                    <textarea name="notes"
                              x-model="cashForm.notes"
                              rows="2"
                              placeholder="Notes about this cash receipt"
                              class="w-full px-3.5 py-2 rounded-xl border border-slate-300 font-sans text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
                </div>

                <!-- Auto FIFO Match Toggle -->
                <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 flex items-center justify-between gap-3">
                    <div>
                        <span class="font-extrabold text-slate-900 block text-xs">Auto-Match to Open Company Payable Dates (FIFO)</span>
                        <span class="text-[11px] text-slate-500">Automatically attributes this money to the oldest open payable dates</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="auto_match_fifo" value="1" checked class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="showRecordCashModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition text-xs cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs shadow-xs transition cursor-pointer">
                        Record Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 7. MODAL: FIFO MATCH PREVIEW & CONFIRM -->
    <div x-show="showFifoMatchModal"
         x-cloak
         @keydown.escape.window="showFifoMatchModal = false"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showFifoMatchModal = false"
             class="bg-white rounded-3xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 bg-gradient-to-r from-indigo-700 to-indigo-800 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-indigo-600 text-white">
                        <i data-lucide="link-2" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wide">Match Receipt to Open Company Payable</h3>
                        <p class="text-[11px] text-indigo-200 font-medium">FIFO Oldest Open Dates · {{ $currentShop->name }}</p>
                    </div>
                </div>
                <button type="button" @click="showFifoMatchModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition cursor-pointer">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-6 space-y-4 font-sans text-xs">
                <!-- Loading State -->
                <div x-show="fifoLoading" class="py-8 text-center space-y-2">
                    <div class="w-6 h-6 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin mx-auto"></div>
                    <p class="text-xs text-slate-500 font-bold">Fetching outstanding Company Payable dates...</p>
                </div>

                <!-- Preview Content -->
                <div x-show="!fifoLoading && fifoPreview">
                    <form method="POST" action="{{ route('admin.cashbook.shop.history.payments.match-payable', $shopKey) }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="payment_request_id" :value="fifoPaymentId">
                        <input type="hidden" name="mode" value="fifo">
                        <input type="hidden" name="month" value="{{ $month }}">

                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2 font-mono text-xs">
                            <div class="flex items-center justify-between border-b border-slate-200/80 pb-2">
                                <span class="text-slate-500 font-sans font-bold">Total Receipt Amount:</span>
                                <span class="font-black text-slate-900" x-text="'₹' + Number(fifoPreview?.total_payment || 0).toFixed(2)"></span>
                            </div>
                            <div class="flex items-center justify-between border-b border-slate-200/80 pb-2">
                                <span class="text-indigo-800 font-sans font-bold">Unmatched Available:</span>
                                <span class="font-black text-indigo-700" x-text="'₹' + Number(fifoPreview?.unmatched_available || 0).toFixed(2)"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-emerald-800 font-sans font-bold">Proposed Match Total:</span>
                                <span class="font-black text-emerald-700" x-text="'₹' + Number(fifoPreview?.total_proposed_match || 0).toFixed(2)"></span>
                            </div>
                        </div>

                        <!-- Proposed Dates List -->
                        <div class="space-y-2">
                            <span class="text-[11px] font-black uppercase tracking-wide text-slate-700 block">
                                Proposed FIFO Date Attribution
                            </span>
                            <template x-if="fifoPreview?.matches && fifoPreview.matches.length > 0">
                                <div class="rounded-2xl border border-slate-200 divide-y divide-slate-100 overflow-hidden text-xs max-h-56 overflow-y-auto">
                                    <template x-for="(m, idx) in fifoPreview.matches" :key="idx">
                                        <div class="p-3 bg-white flex items-center justify-between gap-2 font-mono">
                                            <div>
                                                <span class="font-bold text-slate-900 block font-sans" x-text="m.formatted_date"></span>
                                                <span class="text-[10px] text-slate-400" x-text="'Open due: ₹' + Number(m.remaining_due).toFixed(2)"></span>
                                            </div>
                                            <span class="font-black text-emerald-700 text-sm" x-text="'+₹' + Number(m.match_now).toFixed(2)"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!fifoPreview?.matches || fifoPreview.matches.length === 0">
                                <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs">
                                    No open Company Payable dates found. All past dates are fully matched.
                                </div>
                            </template>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                            <button type="button" @click="showFifoMatchModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition text-xs cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                    :disabled="!fifoPreview?.matches || fifoPreview.matches.length === 0"
                                    class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-black text-xs shadow-xs transition cursor-pointer">
                                Confirm FIFO Match
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 8. MODAL: DAILY DIRECT BANK VERIFY -->
    <div x-show="showVerifyModal"
         x-cloak
         @keydown.escape.window="showVerifyModal = false"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showVerifyModal = false"
             class="bg-white rounded-3xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 bg-gradient-to-r from-slate-900 to-slate-800 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-amber-500/20 text-amber-400">
                        <i data-lucide="check-circle" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wide">Verify Direct Bank Receipt</h3>
                        <p class="text-[11px] text-slate-300 font-medium" x-text="selectedRow ? (selectedRow.formatted_date + ' · ' + '{{ $currentShop->name }}') : ''"></p>
                    </div>
                </div>
                <button type="button" @click="showVerifyModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition cursor-pointer">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <template x-if="selectedRow">
                <form method="POST"
                      action="{{ route('admin.cashbook.shop.history.payments.verify-day', $shopKey) }}"
                      class="p-6 space-y-4 font-sans text-xs">
                    @csrf
                    <input type="hidden" name="business_date" :value="selectedRow.business_date">
                    <input type="hidden" name="month" value="{{ $month }}">

                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2 font-mono text-xs">
                        <div class="flex items-center justify-between border-b border-slate-200/80 pb-2">
                            <span class="text-slate-500 font-sans font-bold">Business Date:</span>
                            <span class="font-extrabold text-slate-900" x-text="selectedRow.business_date"></span>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-200/80 pb-2">
                            <span class="text-slate-500 font-sans font-bold">Gross Company Payable:</span>
                            <span class="font-black text-slate-900" x-text="'₹' + Number(selectedRow.company_payable).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-amber-800 font-sans font-bold">Direct Bank Amount:</span>
                            <span class="text-base font-black text-amber-700" x-text="'₹' + Number(selectedRow.direct_bank_total).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                        </div>
                    </div>

                    <!-- Account Mappings Breakdown -->
                    <div class="space-y-2">
                        <span class="text-[11px] font-black uppercase tracking-wide text-slate-700 block">
                            Direct Bank Contributors
                        </span>
                        <div class="rounded-2xl border border-slate-200 divide-y divide-slate-100 overflow-hidden text-xs">
                            <template x-for="(item, idx) in selectedRow.unverified_contributors" :key="idx">
                                <div class="p-3 bg-white flex items-center justify-between gap-2" x-show="item.is_direct_bank">
                                    <div>
                                        <span class="font-bold text-slate-900 block" x-text="item.label"></span>
                                        <span class="text-[10px] text-slate-500 font-mono" x-text="'Target Account: ' + item.account"></span>
                                    </div>
                                    <span class="font-mono font-black text-slate-900" x-text="'₹' + Number(item.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <p class="text-xs text-slate-500">
                        Confirming receipt will verify this money in configured company accounts and automatically record a 1:1 Company Payable match for this date.
                    </p>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="showVerifyModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition text-xs cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-black text-xs shadow-xs transition cursor-pointer">
                            Confirm &amp; Verify Direct Receipt
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>

    <!-- 9. MODAL: VIEW DAY MATCHES & UNDO -->
    <div x-show="showDayMatchesModal"
         x-cloak
         @keydown.escape.window="showDayMatchesModal = false"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showDayMatchesModal = false"
             class="bg-white rounded-3xl max-w-xl w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 bg-gradient-to-r from-slate-900 to-slate-800 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-indigo-500/20 text-indigo-400">
                        <i data-lucide="link" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wide">Attributed Receipt Matches</h3>
                        <p class="text-[11px] text-slate-300 font-medium" x-text="selectedDayRow ? (selectedDayRow.formatted_date + ' (' + selectedDayRow.day_name + ') · ' + '{{ $currentShop->name }}') : ''"></p>
                    </div>
                </div>
                <button type="button" @click="showDayMatchesModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition cursor-pointer">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <template x-if="selectedDayRow">
                <div class="p-6 space-y-4 font-sans text-xs">
                    <!-- Day Summary Banner -->
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 flex items-center justify-between font-mono">
                        <div>
                            <span class="text-slate-500 font-sans font-bold block text-[11px]">Total Matched to Date</span>
                            <span class="text-base font-black text-emerald-700" x-text="'₹' + Number(selectedDayRow.received).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                        </div>
                        <div class="text-right">
                            <span class="text-slate-500 font-sans font-bold block text-[11px]">Active Matches</span>
                            <span class="text-sm font-black text-slate-900" x-text="(selectedDayRow.active_matches?.length || 0) + ' match(es)'"></span>
                        </div>
                    </div>

                    <!-- Matches Table -->
                    <div class="space-y-2">
                        <span class="text-[11px] font-black uppercase tracking-wide text-slate-700 block">
                            Active Contributing Matches
                        </span>

                        <template x-if="selectedDayRow.active_matches && selectedDayRow.active_matches.length > 0">
                            <div class="rounded-2xl border border-slate-200 divide-y divide-slate-100 overflow-hidden text-xs max-h-64 overflow-y-auto">
                                <template x-for="m in selectedDayRow.active_matches" :key="m.id">
                                    <div class="p-3 bg-white flex items-center justify-between gap-3 hover:bg-slate-50 transition">
                                        <div class="space-y-0.5">
                                            <div class="flex items-center gap-2">
                                                <span class="font-extrabold text-slate-900 font-sans" x-text="m.payment_method"></span>
                                                <span class="text-[10px] text-slate-500 font-mono" x-text="m.reference"></span>
                                            </div>
                                            <span class="text-[10px] text-slate-400 block" x-text="m.account_name"></span>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <span class="font-mono font-black text-slate-900" x-text="'₹' + Number(m.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                                            
                                            <form method="POST"
                                                  action="{{ route('admin.cashbook.shop.history.payments.undo-match', $shopKey) }}"
                                                  onsubmit="return confirm('Undo this specific match? This will release the match amount back to the receipt.')">
                                                @csrf
                                                <input type="hidden" name="match_id" :value="m.id">
                                                <input type="hidden" name="month" value="{{ $month }}">
                                                <button type="submit"
                                                        class="px-2 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-bold text-[10px] transition cursor-pointer"
                                                        title="Undo individual match">
                                                    Undo
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="!selectedDayRow.active_matches || selectedDayRow.active_matches.length === 0">
                            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-slate-500 text-xs text-center">
                                No active matches found for this business date.
                            </div>
                        </template>
                    </div>

                    <!-- Footer Actions -->
                    <div class="flex items-center justify-between gap-2 pt-3 border-t border-slate-100">
                        <template x-if="selectedDayRow.active_matches && selectedDayRow.active_matches.length > 0">
                            <form method="POST"
                                  action="{{ route('admin.cashbook.shop.history.payments.undo-day-matches', $shopKey) }}"
                                  :onsubmit="`return confirm('WARNING: Clear ALL ' + (selectedDayRow.active_matches?.length || 0) + ' matches for ' + selectedDayRow.business_date + '? Total amount to be released: ₹' + Number(selectedDayRow.received).toLocaleString(\'en-IN\', {minimumFractionDigits: 2}))`">
                                @csrf
                                <input type="hidden" name="business_date" :value="selectedDayRow.business_date">
                                <input type="hidden" name="month" value="{{ $month }}">
                                <button type="submit"
                                        class="px-3.5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-black text-xs shadow-xs transition cursor-pointer">
                                    Clear All Matches for Day
                                </button>
                            </form>
                        </template>

                        <button type="button" @click="showDayMatchesModal = false" class="ml-auto px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition text-xs cursor-pointer">
                            Close
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function shopMonthlyPaymentsControl() {
    return {
        showBulkVerifyModal: false,
        showVerifyModal: false,
        showRecordCashModal: false,
        showFifoMatchModal: false,
        showDayMatchesModal: false,
        selectedRow: null,
        selectedDayRow: null,
        fifoPaymentId: null,
        fifoPreview: null,
        fifoLoading: false,

        cashForm: {
            amount: '',
            payment_date: '{{ now()->toDateString() }}',
            payment_method: 'cash',
            company_account_id: '{{ $companyAccounts->first()?->id ?? "" }}',
            payment_reference: '',
            notes: ''
        },

        generateUuid() {
            if (typeof window !== 'undefined' && window.crypto && typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = (Math.random() * 16) | 0;
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            });
        },

        openVerifyModal(row) {
            this.selectedRow = row;
            this.showVerifyModal = true;
        },

        openDayMatchesModal(row) {
            this.selectedDayRow = row;
            this.showDayMatchesModal = true;
        },

        openRecordCashModal(date = null, amount = null) {
            this.cashForm.payment_date = date || '{{ now()->toDateString() }}';
            this.cashForm.amount = amount ? Number(amount).toFixed(2) : '';
            this.cashForm.payment_reference = '';
            this.cashForm.notes = '';
            this.showRecordCashModal = true;
        },

        async openFifoMatchModal(paymentId) {
            this.fifoPaymentId = paymentId;
            this.fifoPreview = null;
            this.fifoLoading = true;
            this.showFifoMatchModal = true;

            try {
                const url = `{{ route('admin.cashbook.shop.history.payments.fifo-preview', $shopKey) }}?payment_request_id=${paymentId}`;
                const resp = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (resp.ok) {
                    this.fifoPreview = await resp.json();
                } else {
                    alert('Could not load FIFO match preview.');
                    this.showFifoMatchModal = false;
                }
            } catch (e) {
                console.error(e);
                alert('Error loading FIFO match preview.');
                this.showFifoMatchModal = false;
            } finally {
                this.fifoLoading = false;
            }
        }
    };
}
</script>
@endsection
