@extends('admin.cashbook.layouts.app')

@section('title', config('greenleaf.name', 'Green Leaf') . ' — Accounts')

@section('header_title')
    <i data-lucide="scale" class="w-5 h-5 text-emerald-600"></i> {{ config('greenleaf.name', 'Green Leaf') }} — Accounts
@endsection

@section('header_subtitle')
    Whole-company financial position report across bank/cash accounts, floating items, receivables, payables, and period movements.
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-6">

    <!-- Top Controls & Professional Reference-Style Period Filter Bar -->
    <div class="rounded-3xl bg-white p-6 border border-slate-200/90 shadow-sm space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white shadow-md shadow-emerald-600/20">
                    <i data-lucide="scale" class="h-6 w-6"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-xl font-black tracking-tight text-slate-900">
                            {{ config('greenleaf.name', 'Green Leaf') }} — Accounts
                        </h1>
                        <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-slate-100 text-slate-700 border border-slate-200/80">
                            Company Finance
                        </span>
                        <span class="rounded-full bg-slate-900 text-slate-100 px-3 py-0.5 text-[10px] font-extrabold uppercase tracking-widest shadow-2xs">
                            Read Only
                        </span>
                    </div>
                    <p class="text-xs font-medium text-slate-500 mt-0.5">
                        Operational Action Center • Financial Execution & Reporting
                    </p>
                </div>
            </div>

            <!-- Action Buttons matching Reference Image -->
            <div class="flex items-center gap-2 flex-wrap self-start md:self-auto">
                <a href="{{ route('admin.cashbook.finance') }}" class="px-4 py-2 rounded-2xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-black shadow-xs transition flex items-center gap-2">
                    <i data-lucide="wallet" class="w-4 h-4"></i> COMPANY FINANCE
                </a>
                <a href="{{ route('admin.cashbook.account-balance') }}" class="px-4 py-2 rounded-2xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200/90 text-xs font-black shadow-2xs transition flex items-center gap-2">
                    <i data-lucide="refresh-cw" class="w-4 h-4 text-indigo-600"></i> REFRESH & RECALCULATE
                </a>
                <a href="{{ route('admin.cashbook.settings') }}" class="px-3.5 py-2 rounded-2xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-bold shadow-2xs transition flex items-center gap-1.5">
                    <i data-lucide="settings" class="w-4 h-4 text-slate-500"></i> SETTINGS
                </a>
            </div>
        </div>

        <!-- Period Selector Box (Identical Layout & Styling to Reference Image) -->
        <div class="bg-slate-50/70 p-4 rounded-3xl border border-slate-200/80 space-y-3" id="period-filter-container">
            <form method="GET" action="{{ route('admin.cashbook.account-balance') }}" id="period-filter-form" class="space-y-3">
                <input type="hidden" name="preset" id="selected-preset" value="{{ $report->period['preset'] }}">
                <input type="hidden" name="period_mode" value="{{ $report->period['mode'] }}">

                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <!-- Left: Mode Segmented Capsule Pills (Month, Day, Custom) + Quick Presets -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Segmented Mode Pills -->
                        <div class="flex items-center gap-1 bg-slate-200/70 p-1 rounded-2xl border border-slate-300/60 shadow-inner">
                            <a href="{{ route('admin.cashbook.account-balance', ['preset' => 'this_month', 'period_mode' => 'month']) }}"
                               class="px-4 py-1.5 rounded-xl text-xs font-black transition-all {{ in_array($report->period['preset'], ['this_month', 'last_month', 'month']) || $report->period['mode'] === 'month' ? 'bg-white text-slate-900 shadow-sm border border-slate-200/90' : 'text-slate-600 hover:text-slate-900' }}">
                                Month
                            </a>
                            <a href="{{ route('admin.cashbook.account-balance', ['preset' => 'today', 'period_mode' => 'day']) }}"
                               class="px-4 py-1.5 rounded-xl text-xs font-black transition-all {{ in_array($report->period['preset'], ['today', 'yesterday', 'day']) || $report->period['mode'] === 'day' ? 'bg-white text-slate-900 shadow-sm border border-slate-200/90' : 'text-slate-600 hover:text-slate-900' }}">
                                Day
                            </a>
                            <a href="{{ route('admin.cashbook.account-balance', ['preset' => 'custom', 'period_mode' => 'custom']) }}"
                               class="px-4 py-1.5 rounded-xl text-xs font-black transition-all {{ $report->period['preset'] === 'custom' || $report->period['mode'] === 'custom' ? 'bg-white text-slate-900 shadow-sm border border-slate-200/90' : 'text-slate-600 hover:text-slate-900' }}">
                                Custom
                            </a>
                        </div>

                        <!-- Quick Preset Badges -->
                        <div class="flex items-center gap-1 flex-wrap">
                            @php
                                $quickPresets = [
                                    'this_week' => 'This Week',
                                    'last_month' => 'Last Month',
                                    'quarter' => 'Quarter',
                                    'year' => 'Year',
                                    'all_time' => 'All Time',
                                ];
                            @endphp
                            @foreach($quickPresets as $qKey => $qLabel)
                                <a href="{{ route('admin.cashbook.account-balance', ['preset' => $qKey]) }}"
                                   class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition border {{ $report->period['preset'] === $qKey ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 hover:bg-slate-100 border-slate-200' }}">
                                    {{ $qLabel }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <!-- Right: Month Display & Left/Right Arrows (Exact Reference Image Style) -->
                    <div class="flex items-center gap-2 bg-white p-1 rounded-2xl border border-slate-200/90 shadow-2xs self-start md:self-auto">
                        <a href="{{ route('admin.cashbook.account-balance', ['preset' => 'month', 'period_mode' => 'month', 'month' => $report->period['prev_month']]) }}"
                           class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-50 text-slate-600 border border-slate-200 hover:bg-slate-100 hover:text-slate-900 transition"
                           title="Previous Month ({{ $report->period['prev_month'] }})">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        </a>

                        <span class="px-3 text-xs font-black tracking-wider text-slate-900 font-mono uppercase select-none">
                            {{ $report->period['month_title'] }}
                        </span>

                        <a href="{{ route('admin.cashbook.account-balance', ['preset' => 'month', 'period_mode' => 'month', 'month' => $report->period['next_month']]) }}"
                           class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-50 text-slate-600 border border-slate-200 hover:bg-slate-100 hover:text-slate-900 transition"
                           title="Next Month ({{ $report->period['next_month'] }})">
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>
                </div>

                <!-- Date Pickers (Displayed for Day or Custom modes) -->
                @if(in_array($report->period['preset'], ['custom', 'day']) || in_array($report->period['mode'], ['custom', 'day']))
                    <div class="flex flex-wrap items-center gap-3 bg-white p-3 rounded-2xl border border-slate-200/80 shadow-2xs pt-2">
                        @if($report->period['preset'] === 'day' || $report->period['mode'] === 'day')
                            <div class="flex items-center gap-2">
                                <label for="date_input" class="text-xs font-bold text-slate-700">Select Date:</label>
                                <input type="date" id="date_input" name="date" value="{{ $report->period['from_date'] }}"
                                       class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                            </div>
                        @else
                            <div class="flex items-center gap-2">
                                <label for="from_date_input" class="text-xs font-bold text-slate-700">From Date:</label>
                                <input type="date" id="from_date_input" name="from_date" value="{{ $report->period['from_date'] }}"
                                       class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                            </div>
                            <div class="flex items-center gap-2">
                                <label for="to_date_input" class="text-xs font-bold text-slate-700">To Date:</label>
                                <input type="date" id="to_date_input" name="to_date" value="{{ $report->period['to_date'] }}"
                                       class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                            </div>
                        @endif
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-1.5 text-xs font-bold text-white hover:bg-slate-800 transition cursor-pointer shadow-xs">
                            Apply Filter
                        </button>
                    </div>
                @endif

                <!-- Bottom Information Bar (Period range + Live status + Mode View) -->
                <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-200/70 text-xs">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-slate-600">
                            Period: <strong class="text-slate-950 font-black">{{ $report->period['formatted_range'] }}</strong>
                        </span>
                        <span class="text-slate-300">•</span>
                        <span class="text-slate-500 font-medium flex items-center gap-1">
                            <i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i>
                            Last recalculated: <strong class="text-slate-700 font-bold">Live account position</strong>
                        </span>
                    </div>

                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">
                        {{ strtoupper($report->period['mode']) }} VIEW
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- 7 TOP SUMMARY KPI CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-3 scroll-mt-6" id="section-summary">
        <!-- 1. Actual Balance -->
        <div class="rounded-2xl bg-white p-4 border border-slate-200/80 shadow-xs group hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Actual Balance</span>
                <i data-lucide="landmark" class="w-4 h-4 text-emerald-600"></i>
            </div>
            <div class="mt-2">
                <span class="text-lg font-black text-slate-900 font-mono">₹{{ number_format($report->summary['actual_balance'], 2) }}</span>
            </div>
            <p class="mt-1 text-[10px] font-medium text-slate-400">Confirmed bank/cash balance</p>
        </div>

        <!-- 2. Floating In -->
        <div class="rounded-2xl bg-white p-4 border border-slate-200/80 shadow-xs group hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-600">Floating In</span>
                <i data-lucide="arrow-down-left" class="w-4 h-4 text-amber-600"></i>
            </div>
            <div class="mt-2">
                <span class="text-lg font-black text-amber-600 font-mono">₹{{ number_format($report->summary['floating_in'], 2) }}</span>
            </div>
            <p class="mt-1 text-[10px] font-medium text-slate-400">Pending inbound money</p>
        </div>

        <!-- 3. Floating Out -->
        <div class="rounded-2xl bg-white p-4 border border-slate-200/80 shadow-xs group hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-rose-600">Floating Out</span>
                <i data-lucide="arrow-up-right" class="w-4 h-4 text-rose-600"></i>
            </div>
            <div class="mt-2">
                <span class="text-lg font-black text-rose-600 font-mono">₹{{ number_format($report->summary['floating_out'], 2) }}</span>
            </div>
            <p class="mt-1 text-[10px] font-medium text-slate-400">Pending outbound money</p>
        </div>

        <!-- 4. Net Floating -->
        <div class="rounded-2xl bg-white p-4 border border-slate-200/80 shadow-xs group hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-600">Net Floating</span>
                <i data-lucide="repeat" class="w-4 h-4 text-blue-600"></i>
            </div>
            <div class="mt-2">
                <span class="text-lg font-black text-blue-600 font-mono">₹{{ number_format($report->summary['net_floating'], 2) }}</span>
            </div>
            <p class="mt-1 text-[10px] font-medium text-slate-400">Floating In - Floating Out</p>
        </div>

        <!-- 5. Total Receivables -->
        <div class="rounded-2xl bg-white p-4 border border-slate-200/80 shadow-xs group hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Total Receivables</span>
                <i data-lucide="hand-coins" class="w-4 h-4 text-emerald-700"></i>
            </div>
            <div class="mt-2">
                <span class="text-lg font-black text-emerald-700 font-mono">₹{{ number_format($report->summary['receivables'], 2) }}</span>
            </div>
            <p class="mt-1 text-[10px] font-medium text-slate-400">Owed to company</p>
        </div>

        <!-- 6. Total Payables -->
        <div class="rounded-2xl bg-white p-4 border border-slate-200/80 shadow-xs group hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-rose-700">Total Payables</span>
                <i data-lucide="receipt" class="w-4 h-4 text-rose-700"></i>
            </div>
            <div class="mt-2">
                <span class="text-lg font-black text-rose-700 font-mono">₹{{ number_format($report->summary['payables'], 2) }}</span>
            </div>
            <p class="mt-1 text-[10px] font-medium text-slate-400">Company liabilities</p>
        </div>

        <!-- 7. Expected Balance -->
        <div class="rounded-2xl bg-slate-900 text-white p-4 shadow-sm group hover:shadow-md transition col-span-1 sm:col-span-2 lg:col-span-4 xl:col-span-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-400">Expected Balance</span>
                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400"></i>
            </div>
            <div class="mt-2">
                <span class="text-lg font-black text-emerald-400 font-mono">₹{{ number_format($report->summary['expected_balance'], 2) }}</span>
            </div>
            <p class="mt-1 text-[10px] font-medium text-slate-400">Actual + Net Floating</p>
        </div>
    </div>

    <!-- 1. ACCOUNT BREAKDOWN (ACTUAL BALANCE) TABLE -->
    <div class="rounded-3xl bg-white p-6 border border-slate-200/90 shadow-xs space-y-4" id="section-accounts">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
                <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <i data-lucide="building-2" class="w-5 h-5 text-emerald-600"></i> Account Breakdown (Actual Balance)
                </h2>
                <p class="text-xs font-medium text-slate-400 mt-0.5">Configured bank accounts and company cash vaults</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                    <input type="text" data-table-search="table-accounts" placeholder="Search accounts..."
                        class="pl-9 pr-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-emerald-600 focus:outline-none w-48 sm:w-64 shadow-2xs">
                </div>
                <select data-table-size="table-accounts" class="px-2.5 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white focus:outline-none shadow-2xs">
                    <option value="5">5 / page</option>
                    <option value="10" selected>10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="100">All</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse text-xs" id="table-accounts">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider select-none bg-slate-50/50">
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">Account Name <span class="sort-icon ml-1 text-slate-300">↕</span></th>
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">Type <span class="sort-icon ml-1 text-slate-300">↕</span></th>
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">Bank / Institution <span class="sort-icon ml-1 text-slate-300">↕</span></th>
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">Account No. <span class="sort-icon ml-1 text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Opening Actual <span class="sort-icon ml-1 text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Confirmed In <span class="sort-icon ml-1 text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Confirmed Out <span class="sort-icon ml-1 text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Closing Actual <span class="sort-icon ml-1 text-slate-300">↕</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($report->accounts as $acc)
                        <tr class="hover:bg-slate-50/80 transition table-row-item">
                            <td class="py-3 px-3 font-bold text-slate-900">
                                {{ $acc['name'] }}
                                @if($acc['is_default'])
                                    <span class="ml-1.5 px-1.5 py-0.5 text-[9px] font-black uppercase rounded bg-emerald-50 text-emerald-800 border border-emerald-200">Default</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 uppercase text-[10px] font-bold text-slate-500">{{ $acc['account_type'] }}</td>
                            <td class="py-3 px-3 text-slate-600">{{ $acc['bank_name'] }}</td>
                            <td class="py-3 px-3 font-mono text-slate-500">{{ $acc['account_number'] }}</td>
                            <td class="py-3 px-3 text-right font-mono" data-val="{{ $acc['opening_actual'] }}">₹{{ number_format($acc['opening_actual'], 2) }}</td>
                            <td class="py-3 px-3 text-right font-mono text-emerald-600" data-val="{{ $acc['confirmed_in'] }}">+₹{{ number_format($acc['confirmed_in'], 2) }}</td>
                            <td class="py-3 px-3 text-right font-mono text-rose-600" data-val="{{ $acc['confirmed_out'] }}">-₹{{ number_format($acc['confirmed_out'], 2) }}</td>
                            <td class="py-3 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $acc['closing_actual'] }}">₹{{ number_format($acc['closing_actual'], 2) }}</td>
                        </tr>
                    @empty
                        <tr class="no-records-row">
                            <td colspan="8" class="py-6 text-center text-slate-400 font-medium">No company accounts configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-100 text-xs font-semibold text-slate-500 overflow-hidden" data-table-footer="table-accounts">
            <span data-table-info="table-accounts">Showing 0 entries</span>
            <div class="flex items-center gap-1 flex-wrap justify-end max-w-full overflow-x-auto" data-table-pagination="table-accounts"></div>
        </div>
    </div>

    <!-- 2. FLOATING MONEY (FLOATING IN & FLOATING OUT) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Floating In Table -->
        <div class="rounded-3xl bg-white p-6 border border-slate-200/90 shadow-xs space-y-4" id="section-floating-in">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <h2 class="text-base font-black text-amber-700 flex items-center gap-2">
                    <i data-lucide="arrow-down-left" class="w-5 h-5 text-amber-600"></i> Floating In Money
                </h2>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2"></i>
                        <input type="text" data-table-search="table-floating-in" placeholder="Search..."
                            class="pl-8 pr-2.5 py-1 rounded-xl border border-slate-200 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none w-36 shadow-2xs">
                    </div>
                    <select data-table-size="table-floating-in" class="px-2 py-1 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white focus:outline-none">
                        <option value="5" selected>5 / page</option>
                        <option value="10">10 / page</option>
                        <option value="50">All</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse text-xs" id="table-floating-in">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider select-none bg-slate-50/50">
                            <th class="py-2.5 px-2 cursor-pointer hover:text-slate-900 transition" data-sort="string">Date <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 cursor-pointer hover:text-slate-900 transition" data-sort="string">From Party <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 cursor-pointer hover:text-slate-900 transition" data-sort="string">To Account <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Amount <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 cursor-pointer hover:text-slate-900 transition" data-sort="string">Status <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Age <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="string">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($report->floatingIn as $item)
                            <tr class="hover:bg-slate-50/80 transition table-row-item">
                                <td class="py-2.5 px-2 font-mono text-slate-500 whitespace-nowrap">{{ $item['date'] }}</td>
                                <td class="py-2.5 px-2 font-bold text-slate-900">{{ $item['from'] }}</td>
                                <td class="py-2.5 px-2 text-slate-600">{{ $item['to_account'] }}</td>
                                <td class="py-2.5 px-2 text-right font-mono font-bold text-amber-700" data-val="{{ $item['amount'] }}">₹{{ number_format($item['amount'], 2) }}</td>
                                <td class="py-2.5 px-2">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-amber-50 text-amber-800 border border-amber-200">
                                        {{ $item['status'] }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-2 text-right font-mono text-slate-400" data-val="{{ $item['age'] }}">{{ $item['age'] }}d</td>
                                <td class="py-2.5 px-2 text-right font-medium whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if(!empty($item['details_url']))
                                            <a href="{{ $item['details_url'] }}" title="View Details" class="p-1 rounded-lg text-slate-500 hover:text-amber-700 hover:bg-amber-50 transition inline-flex items-center">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </a>
                                        @endif
                                        @if(!empty($item['delete_url']))
                                            <form action="{{ $item['delete_url'] }}" method="POST" onsubmit="return confirm('Are you sure you want to delete/reject this floating entry?');" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Delete / Reject Entry" class="p-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition inline-flex items-center">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="no-records-row">
                                <td colspan="7" class="py-6 text-center text-slate-400 font-medium">No active floating in items.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 border-t border-slate-100 text-xs font-semibold text-slate-500 overflow-hidden" data-table-footer="table-floating-in">
                <span data-table-info="table-floating-in">Showing 0 entries</span>
                <div class="flex items-center gap-1 flex-wrap justify-end max-w-full overflow-x-auto" data-table-pagination="table-floating-in"></div>
            </div>
        </div>

        <!-- Floating Out Table -->
        <div class="rounded-3xl bg-white p-6 border border-slate-200/90 shadow-xs space-y-4" id="section-floating-out">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <h2 class="text-base font-black text-rose-700 flex items-center gap-2">
                    <i data-lucide="arrow-up-right" class="w-5 h-5 text-rose-600"></i> Floating Out Money
                </h2>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2"></i>
                        <input type="text" data-table-search="table-floating-out" placeholder="Search..."
                            class="pl-8 pr-2.5 py-1 rounded-xl border border-slate-200 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-rose-500 focus:outline-none w-36 shadow-2xs">
                    </div>
                    <select data-table-size="table-floating-out" class="px-2 py-1 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white focus:outline-none">
                        <option value="5" selected>5 / page</option>
                        <option value="10">10 / page</option>
                        <option value="50">All</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse text-xs" id="table-floating-out">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider select-none bg-slate-50/50">
                            <th class="py-2.5 px-2 cursor-pointer hover:text-slate-900 transition" data-sort="string">Date <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 cursor-pointer hover:text-slate-900 transition" data-sort="string">From Account <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 cursor-pointer hover:text-slate-900 transition" data-sort="string">To Party <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Amount <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 cursor-pointer hover:text-slate-900 transition" data-sort="string">Status <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Age <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="string">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($report->floatingOut as $item)
                            <tr class="hover:bg-slate-50/80 transition table-row-item">
                                <td class="py-2.5 px-2 font-mono text-slate-500 whitespace-nowrap">{{ $item['date'] }}</td>
                                <td class="py-2.5 px-2 font-bold text-slate-900">{{ $item['from_account'] }}</td>
                                <td class="py-2.5 px-2 text-slate-600">{{ $item['to'] }}</td>
                                <td class="py-2.5 px-2 text-right font-mono font-bold text-rose-700" data-val="{{ $item['amount'] }}">₹{{ number_format($item['amount'], 2) }}</td>
                                <td class="py-2.5 px-2">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-rose-50 text-rose-800 border border-rose-200">
                                        {{ $item['status'] }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-2 text-right font-mono text-slate-400" data-val="{{ $item['age'] }}">{{ $item['age'] }}d</td>
                                <td class="py-2.5 px-2 text-right font-medium whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if(!empty($item['details_url']))
                                            <a href="{{ $item['details_url'] }}" title="View Details" class="p-1 rounded-lg text-slate-500 hover:text-rose-700 hover:bg-rose-50 transition inline-flex items-center">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </a>
                                        @endif
                                        @if(!empty($item['delete_url']))
                                            <form action="{{ $item['delete_url'] }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this statement entry?');" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Delete Entry" class="p-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition inline-flex items-center">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="no-records-row">
                                <td colspan="7" class="py-6 text-center text-slate-400 font-medium">No active floating out items.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 border-t border-slate-100 text-xs font-semibold text-slate-500 overflow-hidden" data-table-footer="table-floating-out">
                <span data-table-info="table-floating-out">Showing 0 entries</span>
                <div class="flex items-center gap-1 flex-wrap justify-end max-w-full overflow-x-auto" data-table-pagination="table-floating-out"></div>
            </div>
        </div>
    </div>

    <!-- 3. RECEIVABLES SECTION -->
    <div class="rounded-3xl bg-white p-6 border border-slate-200/90 shadow-xs space-y-4" id="section-receivables">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
                <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <i data-lucide="hand-coins" class="w-5 h-5 text-emerald-600"></i> Shop Receivables Table
                </h2>
                <p class="text-xs font-medium text-slate-400 mt-0.5">Shop-level detail with double-count prevention via floating allocations</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                    <input type="text" data-table-search="table-receivables" placeholder="Search shops..."
                        class="pl-9 pr-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-emerald-600 focus:outline-none w-48 sm:w-64 shadow-2xs">
                </div>
                <select data-table-size="table-receivables" class="px-2.5 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white focus:outline-none shadow-2xs">
                    <option value="5">5 / page</option>
                    <option value="10" selected>10 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">All</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse text-xs" id="table-receivables">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider select-none bg-slate-50/50">
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">Shop <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Opening Receivable <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">New Receivable <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Received <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Floating Allocation <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Adjustment <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Closing Receivable <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="string">View</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($report->receivables['shop_receivables'] ?? [] as $rec)
                        <tr class="hover:bg-slate-50/80 transition table-row-item">
                            <td class="py-3 px-3 font-bold text-slate-900">{{ $rec['party'] }}</td>
                            <td class="py-3 px-3 text-right font-mono text-slate-500" data-val="{{ $rec['opening_outstanding'] }}">₹{{ number_format($rec['opening_outstanding'], 2) }}</td>
                            <td class="py-3 px-3 text-right font-mono text-slate-600" data-val="{{ $rec['new_receivable'] }}">+₹{{ number_format($rec['new_receivable'], 2) }}</td>
                            <td class="py-3 px-3 text-right font-mono text-emerald-600" data-val="{{ $rec['received'] }}">-₹{{ number_format($rec['received'], 2) }}</td>
                            <td class="py-3 px-3 text-right font-mono text-amber-600" data-val="{{ $rec['floating_in_offset'] }}">-₹{{ number_format($rec['floating_in_offset'], 2) }}</td>
                            <td class="py-3 px-3 text-right font-mono text-rose-500" data-val="{{ $rec['reversed'] }}">±₹{{ number_format($rec['reversed'], 2) }}</td>
                            <td class="py-3 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $rec['closing_outstanding'] }}">₹{{ number_format($rec['closing_outstanding'], 2) }}</td>
                            <td class="py-3 px-3 text-right font-medium whitespace-nowrap">
                                @if(!empty($rec['details_url']))
                                    <a href="{{ $rec['details_url'] }}" title="View Shop Cashbook" class="p-1.5 rounded-lg text-slate-600 hover:text-emerald-700 hover:bg-emerald-50 transition inline-flex items-center gap-1 font-bold text-xs">
                                        <i data-lucide="external-link" class="w-3.5 h-3.5"></i> View Shop
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr class="no-records-row">
                            <td colspan="8" class="py-6 text-center text-slate-400 font-medium">No shop receivables found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-100 text-xs font-semibold text-slate-500 overflow-hidden" data-table-footer="table-receivables">
            <span data-table-info="table-receivables">Showing 0 entries</span>
            <div class="flex items-center gap-1 flex-wrap justify-end max-w-full overflow-x-auto" data-table-pagination="table-receivables"></div>
        </div>
    </div>

    <!-- 4. PAYABLES SECTION & SUMMARY MATRIX -->
    <div class="rounded-3xl bg-white p-6 border border-slate-200/90 shadow-xs space-y-6" id="section-payables">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
                <h2 class="text-base font-black text-rose-700 flex items-center gap-2">
                    <i data-lucide="receipt" class="w-5 h-5 text-rose-600"></i> TOTAL PAYABLES BREAKDOWN
                </h2>
                <p class="text-xs font-medium text-slate-400 mt-0.5">Comprehensive whole-company payables matrix and detailed category ledgers</p>
            </div>
            <div class="px-4 py-2 rounded-2xl bg-rose-50 border border-rose-200/80 text-right">
                <span class="text-[10px] uppercase font-bold text-rose-500 block tracking-wider">TOTAL COMPANY PAYABLES</span>
                <span class="text-lg font-black font-mono text-rose-700">₹{{ number_format($report->summary['payables'], 2) }}</span>
            </div>
        </div>

        <!-- PAYABLE SUMMARY MATRIX CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/70">
                <span class="text-[10px] font-extrabold uppercase text-slate-500 block tracking-wider">Purchaser Outstanding</span>
                <span class="text-base font-black font-mono text-slate-900 mt-1 block">₹{{ number_format($report->payables['summary']['purchaser_outstanding'] ?? 0, 2) }}</span>
            </div>
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/70">
                <span class="text-[10px] font-extrabold uppercase text-slate-500 block tracking-wider">Vendor Outstanding</span>
                <span class="text-base font-black font-mono text-slate-900 mt-1 block">₹{{ number_format($report->payables['summary']['vendor_outstanding'] ?? 0, 2) }}</span>
            </div>
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/70">
                <span class="text-[10px] font-extrabold uppercase text-slate-500 block tracking-wider">Company Owes Shops</span>
                <span class="text-base font-black font-mono text-slate-900 mt-1 block">₹{{ number_format($report->payables['summary']['company_owes_shops'] ?? 0, 2) }}</span>
            </div>
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/70">
                <span class="text-[10px] font-extrabold uppercase text-slate-500 block tracking-wider">Petty / Reimbursements</span>
                <span class="text-base font-black font-mono text-slate-900 mt-1 block">₹{{ number_format($report->payables['summary']['petty_outstanding'] ?? 0, 2) }}</span>
            </div>
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/70">
                <span class="text-[10px] font-extrabold uppercase text-slate-500 block tracking-wider">Other Payables</span>
                <span class="text-base font-black font-mono text-slate-900 mt-1 block">₹{{ number_format($report->payables['summary']['other_payables'] ?? 0, 2) }}</span>
            </div>
        </div>

        <!-- 4A. PURCHASER PAYABLES TABLE -->
        <div class="space-y-3 pt-2 scroll-mt-6" id="section-purchasers">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                    <i data-lucide="user-check" class="w-4 h-4 text-purple-600"></i> PURCHASER PAYABLES
                </h3>
                <span class="text-xs font-mono font-bold text-slate-600">
                    Total: ₹{{ number_format($report->payables['summary']['purchaser_outstanding'] ?? 0, 2) }}
                </span>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse text-xs" id="table-purchaser-payables">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider select-none bg-slate-50/50">
                            <th class="py-2.5 px-3 cursor-pointer hover:text-slate-900" data-sort="string">Purchaser</th>
                            <th class="py-2.5 px-3 text-right cursor-pointer hover:text-slate-900" data-sort="number">Opening Outstanding</th>
                            <th class="py-2.5 px-3 text-right cursor-pointer hover:text-slate-900" data-sort="number">New Bills / Liability</th>
                            <th class="py-2.5 px-3 text-right cursor-pointer hover:text-slate-900" data-sort="number">Paid</th>
                            <th class="py-2.5 px-3 text-right cursor-pointer hover:text-slate-900" data-sort="number">Settlement Discount</th>
                            <th class="py-2.5 px-3 text-right cursor-pointer hover:text-slate-900" data-sort="number">Adjustment</th>
                            <th class="py-2.5 px-3 text-right cursor-pointer hover:text-slate-900" data-sort="number">Closing Outstanding</th>
                            <th class="py-2.5 px-3 text-right" data-sort="string">View</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($report->payables['purchaser_payables'] ?? [] as $item)
                            <tr class="hover:bg-slate-50/80 transition table-row-item">
                                <td class="py-2.5 px-3 font-bold text-slate-900">{{ $item['party'] }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-500" data-val="{{ $item['opening_outstanding'] }}">₹{{ number_format($item['opening_outstanding'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-600" data-val="{{ $item['new_liability'] }}">+₹{{ number_format($item['new_liability'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-emerald-600" data-val="{{ $item['paid'] }}">-₹{{ number_format($item['paid'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-purple-600" data-val="{{ $item['discount'] }}">-₹{{ number_format($item['discount'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-500" data-val="{{ $item['adjustment'] }}">±₹{{ number_format($item['adjustment'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $item['closing_outstanding'] }}">₹{{ number_format($item['closing_outstanding'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-medium whitespace-nowrap">
                                    @if(!empty($item['details_url']))
                                        <a href="{{ $item['details_url'] }}" title="View Purchaser Settlement" class="p-1.5 rounded-lg text-slate-600 hover:text-purple-700 hover:bg-purple-50 transition inline-flex items-center gap-1 font-bold text-xs">
                                            <i data-lucide="external-link" class="w-3.5 h-3.5"></i> View
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="no-records-row">
                                <td colspan="8" class="py-4 text-center text-slate-400">No purchaser payables found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4B. PURCHASER PAYMENTS SUMMARY TABLE -->
        <div class="space-y-3 pt-2 scroll-mt-6" id="section-purchaser-payments">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-2">
                <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                    <i data-lucide="send" class="w-4 h-4 text-purple-600"></i> PURCHASER PAYMENTS (SELECTED PERIOD)
                </h3>
                <div class="flex items-center gap-4 text-xs font-mono font-bold flex-wrap">
                    <span class="text-slate-700">Total: ₹{{ number_format($report->payables['purchaser_payments']['total_paid'] ?? 0, 2) }}</span>
                    <span class="text-emerald-600">Cleared: ₹{{ number_format($report->payables['purchaser_payments']['total_cleared'] ?? 0, 2) }}</span>
                    <span class="text-amber-600">Floating: ₹{{ number_format($report->payables['purchaser_payments']['total_floating'] ?? 0, 2) }}</span>
                    <span class="text-blue-600">Unallocated: ₹{{ number_format($report->payables['purchaser_payments']['total_unallocated'] ?? 0, 2) }}</span>
                </div>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse text-xs" id="table-purchaser-payments">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider select-none bg-slate-50/50">
                            <th class="py-2.5 px-3" data-sort="string">Date</th>
                            <th class="py-2.5 px-3" data-sort="string">Purchaser</th>
                            <th class="py-2.5 px-3" data-sort="string">From Account</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Payment Amount</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Allocated Amount</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Unallocated Amount</th>
                            <th class="py-2.5 px-3" data-sort="string">Status</th>
                            <th class="py-2.5 px-3" data-sort="string">Clearance Status</th>
                            <th class="py-2.5 px-3" data-sort="string">Reference</th>
                            <th class="py-2.5 px-3 text-right">View</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($report->payables['purchaser_payments']['items'] ?? [] as $item)
                            <tr class="hover:bg-slate-50/80 transition table-row-item">
                                <td class="py-2.5 px-3 font-mono text-slate-500 whitespace-nowrap">{{ $item['date'] }}</td>
                                <td class="py-2.5 px-3 font-bold text-slate-900">{{ $item['purchaser'] }}</td>
                                <td class="py-2.5 px-3 text-slate-600">{{ $item['from_account'] }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $item['amount'] }}">₹{{ number_format($item['amount'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-emerald-600" data-val="{{ $item['allocated_amount'] }}">₹{{ number_format($item['allocated_amount'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-blue-600" data-val="{{ $item['unallocated_amount'] }}">₹{{ number_format($item['unallocated_amount'], 2) }}</td>
                                <td class="py-2.5 px-3 uppercase text-[10px] font-bold">{{ $item['status'] }}</td>
                                <td class="py-2.5 px-3">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase {{ $item['clearance_status'] === 'CLEARED' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
                                        {{ $item['clearance_status'] }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 font-mono text-slate-500">{{ $item['reference'] }}</td>
                                <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                    @if(!empty($item['details_url']))
                                        <a href="{{ $item['details_url'] }}" class="p-1 rounded text-slate-500 hover:text-slate-900"><i data-lucide="eye" class="w-4 h-4"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="no-records-row">
                                <td colspan="10" class="py-4 text-center text-slate-400">No purchaser payments in selected period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4C. VENDOR PAYABLES TABLE -->
        <div class="space-y-3 pt-2 scroll-mt-6" id="section-vendors">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                    <i data-lucide="truck" class="w-4 h-4 text-rose-600"></i> VENDOR PAYABLES
                </h3>
                <span class="text-xs font-mono font-bold text-slate-600">
                    Total: ₹{{ number_format($report->payables['summary']['vendor_outstanding'] ?? 0, 2) }}
                </span>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse text-xs" id="table-vendor-payables">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider select-none bg-slate-50/50">
                            <th class="py-2.5 px-3" data-sort="string">Vendor</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Opening Outstanding</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">New Credit Purchases</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Paid</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Settlement Discount</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Adjustment</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Closing Outstanding</th>
                            <th class="py-2.5 px-3 text-right">View</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($report->payables['vendor_payables'] ?? [] as $item)
                            <tr class="hover:bg-slate-50/80 transition table-row-item">
                                <td class="py-2.5 px-3 font-bold text-slate-900">{{ $item['party'] }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-500" data-val="{{ $item['opening_outstanding'] }}">₹{{ number_format($item['opening_outstanding'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-600" data-val="{{ $item['new_credit_purchases'] }}">+₹{{ number_format($item['new_credit_purchases'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-emerald-600" data-val="{{ $item['paid'] }}">-₹{{ number_format($item['paid'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-purple-600" data-val="{{ $item['settlement_discount'] }}">-₹{{ number_format($item['settlement_discount'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-500" data-val="{{ $item['adjustment'] }}">±₹{{ number_format($item['adjustment'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $item['closing_outstanding'] }}">₹{{ number_format($item['closing_outstanding'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-medium whitespace-nowrap">
                                    @if(!empty($item['details_url']))
                                        <a href="{{ $item['details_url'] }}" title="View Vendor Credit" class="p-1.5 rounded-lg text-slate-600 hover:text-rose-700 hover:bg-rose-50 transition inline-flex items-center gap-1 font-bold text-xs">
                                            <i data-lucide="external-link" class="w-3.5 h-3.5"></i> View
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="no-records-row">
                                <td colspan="8" class="py-4 text-center text-slate-400">No vendor payables found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4D. VENDOR PAYMENTS TABLE -->
        <div class="space-y-3 pt-2 scroll-mt-6" id="section-vendor-payments">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                    <i data-lucide="credit-card" class="w-4 h-4 text-rose-600"></i> VENDOR PAYMENTS (SELECTED PERIOD)
                </h3>
                <span class="text-xs font-mono font-bold text-slate-600">
                    Total Paid to Vendors: ₹{{ number_format($report->payables['vendor_payments']['total_paid'] ?? 0, 2) }}
                </span>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse text-xs" id="table-vendor-payments">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider select-none bg-slate-50/50">
                            <th class="py-2.5 px-3" data-sort="string">Date</th>
                            <th class="py-2.5 px-3" data-sort="string">Vendor</th>
                            <th class="py-2.5 px-3" data-sort="string">From Account</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Amount</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Allocated</th>
                            <th class="py-2.5 px-3" data-sort="string">Status</th>
                            <th class="py-2.5 px-3" data-sort="string">Settlement Reference</th>
                            <th class="py-2.5 px-3" data-sort="string">Related Bills</th>
                            <th class="py-2.5 px-3 text-right">View</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($report->payables['vendor_payments']['items'] ?? [] as $item)
                            <tr class="hover:bg-slate-50/80 transition table-row-item">
                                <td class="py-2.5 px-3 font-mono text-slate-500 whitespace-nowrap">{{ $item['date'] }}</td>
                                <td class="py-2.5 px-3 font-bold text-slate-900">{{ $item['vendor'] }}</td>
                                <td class="py-2.5 px-3 text-slate-600">{{ $item['from_account'] }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $item['amount'] }}">₹{{ number_format($item['amount'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-emerald-600" data-val="{{ $item['allocated'] }}">₹{{ number_format($item['allocated'], 2) }}</td>
                                <td class="py-2.5 px-3 uppercase text-[10px] font-bold">{{ $item['status'] }}</td>
                                <td class="py-2.5 px-3 font-mono text-slate-500">{{ $item['settlement_reference'] }}</td>
                                <td class="py-2.5 px-3 text-slate-600">{{ $item['related_bills'] }}</td>
                                <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                    @if(!empty($item['details_url']))
                                        <a href="{{ $item['details_url'] }}" class="p-1 rounded text-slate-500 hover:text-slate-900"><i data-lucide="eye" class="w-4 h-4"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="no-records-row">
                                <td colspan="9" class="py-4 text-center text-slate-400">No vendor payments in selected period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4E. COMPANY OWES SHOPS TABLE -->
        <div class="space-y-3 pt-2 scroll-mt-6" id="section-company-owes-shops">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                    <i data-lucide="store" class="w-4 h-4 text-indigo-600"></i> COMPANY OWES SHOPS
                </h3>
                <span class="text-xs font-mono font-bold text-slate-600">
                    Total: ₹{{ number_format($report->payables['summary']['company_owes_shops'] ?? 0, 2) }}
                </span>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse text-xs" id="table-company-owes-shops">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider select-none bg-slate-50/50">
                            <th class="py-2.5 px-3" data-sort="string">Shop</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Opening Payable</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">New Amount Owed</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Paid to Shop</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Adjustment</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Closing Payable</th>
                            <th class="py-2.5 px-3 text-right">View</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($report->payables['company_owes_shops'] ?? [] as $item)
                            <tr class="hover:bg-slate-50/80 transition table-row-item">
                                <td class="py-2.5 px-3 font-bold text-slate-900">{{ $item['shop'] }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-500" data-val="{{ $item['opening_payable'] }}">₹{{ number_format($item['opening_payable'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-600" data-val="{{ $item['new_amount_owed'] }}">+₹{{ number_format($item['new_amount_owed'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-emerald-600" data-val="{{ $item['paid_to_shop'] }}">-₹{{ number_format($item['paid_to_shop'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-500" data-val="{{ $item['adjustment'] }}">±₹{{ number_format($item['adjustment'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $item['closing_payable'] }}">₹{{ number_format($item['closing_payable'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-medium whitespace-nowrap">
                                    @if(!empty($item['view']))
                                        <a href="{{ $item['view'] }}" class="p-1 rounded text-slate-500 hover:text-slate-900"><i data-lucide="external-link" class="w-4 h-4"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="no-records-row">
                                <td colspan="7" class="py-4 text-center text-slate-400">Company owes zero shops.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4F. PETTY / REIMBURSEMENT PAYABLES TABLE -->
        <div class="space-y-3 pt-2 scroll-mt-6" id="section-petty">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                    <i data-lucide="wallet" class="w-4 h-4 text-amber-600"></i> PETTY / REIMBURSEMENT PAYABLES
                </h3>
                <span class="text-xs font-mono font-bold text-slate-600">
                    Total: ₹{{ number_format($report->payables['summary']['petty_outstanding'] ?? 0, 2) }}
                </span>
            </div>
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse text-xs" id="table-petty-payables">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider select-none bg-slate-50/50">
                            <th class="py-2.5 px-3" data-sort="string">Party / Shop</th>
                            <th class="py-2.5 px-3" data-sort="string">Source</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Opening</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Created</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Paid</th>
                            <th class="py-2.5 px-3 text-right" data-sort="number">Closing</th>
                            <th class="py-2.5 px-3" data-sort="string">Status</th>
                            <th class="py-2.5 px-3" data-sort="string">Reference</th>
                            <th class="py-2.5 px-3 text-right">View</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($report->payables['petty_payables'] ?? [] as $item)
                            <tr class="hover:bg-slate-50/80 transition table-row-item">
                                <td class="py-2.5 px-3 font-bold text-slate-900">{{ $item['party'] }}</td>
                                <td class="py-2.5 px-3 text-slate-600">{{ $item['source'] }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-500" data-val="{{ $item['opening'] }}">₹{{ number_format($item['opening'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-600" data-val="{{ $item['created'] }}">+₹{{ number_format($item['created'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono text-emerald-600" data-val="{{ $item['paid'] }}">-₹{{ number_format($item['paid'], 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $item['closing'] }}">₹{{ number_format($item['closing'], 2) }}</td>
                                <td class="py-2.5 px-3 uppercase text-[10px] font-bold">{{ $item['status'] }}</td>
                                <td class="py-2.5 px-3 font-mono text-slate-500">{{ $item['reference'] }}</td>
                                <td class="py-2.5 px-3 text-right font-medium whitespace-nowrap">
                                    @if(!empty($item['view']))
                                        <a href="{{ $item['view'] }}" class="p-1 rounded text-slate-500 hover:text-slate-900"><i data-lucide="external-link" class="w-4 h-4"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="no-records-row">
                                <td colspan="9" class="py-4 text-center text-slate-400">No petty / reimbursement payables found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4G. OTHER PAYABLES TABLE -->
        @if(!empty($report->payables['other_payables']))
            <div class="space-y-3 pt-2">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                        <i data-lucide="help-circle" class="w-4 h-4 text-slate-600"></i> OTHER PAYABLES
                    </h3>
                </div>
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse text-xs" id="table-other-payables">
                        <thead>
                            <tr class="border-b border-slate-200 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider bg-slate-50/50">
                                <th class="py-2.5 px-3">Type</th>
                                <th class="py-2.5 px-3">Party</th>
                                <th class="py-2.5 px-3">Source Module</th>
                                <th class="py-2.5 px-3 text-right">Closing</th>
                                <th class="py-2.5 px-3">Status</th>
                                <th class="py-2.5 px-3">Reference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            @foreach($report->payables['other_payables'] as $item)
                                <tr class="hover:bg-slate-50/80 transition table-row-item">
                                    <td class="py-2.5 px-3 font-bold">{{ $item['type'] ?? 'UNMAPPED' }}</td>
                                    <td class="py-2.5 px-3">{{ $item['party'] ?? 'N/A' }}</td>
                                    <td class="py-2.5 px-3">{{ $item['source_module'] ?? 'N/A' }}</td>
                                    <td class="py-2.5 px-3 text-right font-mono font-bold">₹{{ number_format($item['closing'] ?? 0, 2) }}</td>
                                    <td class="py-2.5 px-3 uppercase text-[10px] font-bold">{{ $item['status'] ?? 'N/A' }}</td>
                                    <td class="py-2.5 px-3 font-mono text-slate-500">{{ $item['reference'] ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <!-- 5. PERIOD MOVEMENT ACCOUNTING MATRIX -->
    <div class="rounded-3xl bg-white p-6 border border-slate-200/90 shadow-xs space-y-4" id="section-movements">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
                <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <i data-lucide="trending-up" class="w-5 h-5 text-emerald-600"></i> Period Movement Accounting Matrix
                </h2>
                <p class="text-xs font-medium text-slate-400 mt-0.5">Opening position, period movement, and closing position reconciliation across all financial pillars</p>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse text-xs" id="table-movements">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider select-none bg-slate-50/50">
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">Financial Category <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Opening Position <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Period Movement <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Closing Position <span class="sort-icon text-slate-300">↕</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    <tr class="hover:bg-slate-50/80 transition table-row-item bg-slate-50/30">
                        <td class="py-3 px-3 font-bold text-slate-900 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Actual Company Balance
                        </td>
                        <td class="py-3 px-3 text-right font-mono" data-val="{{ $report->movements['actual_balance']['opening'] }}">₹{{ number_format($report->movements['actual_balance']['opening'], 2) }}</td>
                        <td class="py-3 px-3 text-right font-mono {{ $report->movements['actual_balance']['activity'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}" data-val="{{ $report->movements['actual_balance']['activity'] }}">
                            {{ $report->movements['actual_balance']['activity'] >= 0 ? '+' : '' }}₹{{ number_format($report->movements['actual_balance']['activity'], 2) }}
                        </td>
                        <td class="py-3 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $report->movements['actual_balance']['closing'] }}">₹{{ number_format($report->movements['actual_balance']['closing'], 2) }}</td>
                    </tr>
                    <tr class="hover:bg-slate-50/80 transition table-row-item font-bold">
                        <td class="py-3 px-3 text-slate-900 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-blue-500"></span> Total Receivables
                        </td>
                        <td class="py-3 px-3 text-right font-mono" data-val="{{ $report->movements['total_receivables']['opening'] }}">₹{{ number_format($report->movements['total_receivables']['opening'], 2) }}</td>
                        <td class="py-3 px-3 text-right font-mono" data-val="{{ $report->movements['total_receivables']['activity'] }}">
                            {{ $report->movements['total_receivables']['activity'] >= 0 ? '+' : '' }}₹{{ number_format($report->movements['total_receivables']['activity'], 2) }}
                        </td>
                        <td class="py-3 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $report->movements['total_receivables']['closing'] }}">₹{{ number_format($report->movements['total_receivables']['closing'], 2) }}</td>
                    </tr>
                    <tr class="hover:bg-slate-50/80 transition table-row-item text-slate-600">
                        <td class="py-2.5 px-3 pl-8 flex items-center gap-2">
                            └ Shop Receivables
                        </td>
                        <td class="py-2.5 px-3 text-right font-mono" data-val="{{ $report->movements['shop_receivables']['opening'] }}">₹{{ number_format($report->movements['shop_receivables']['opening'], 2) }}</td>
                        <td class="py-2.5 px-3 text-right font-mono" data-val="{{ $report->movements['shop_receivables']['activity'] }}">
                            {{ $report->movements['shop_receivables']['activity'] >= 0 ? '+' : '' }}₹{{ number_format($report->movements['shop_receivables']['activity'], 2) }}
                        </td>
                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $report->movements['shop_receivables']['closing'] }}">₹{{ number_format($report->movements['shop_receivables']['closing'], 2) }}</td>
                    </tr>
                    <tr class="hover:bg-slate-50/80 transition table-row-item font-bold">
                        <td class="py-3 px-3 text-slate-900 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-rose-500"></span> Total Payables
                        </td>
                        <td class="py-3 px-3 text-right font-mono" data-val="{{ $report->movements['total_payables']['opening'] }}">₹{{ number_format($report->movements['total_payables']['opening'], 2) }}</td>
                        <td class="py-3 px-3 text-right font-mono" data-val="{{ $report->movements['total_payables']['activity'] }}">
                            {{ $report->movements['total_payables']['activity'] >= 0 ? '+' : '' }}₹{{ number_format($report->movements['total_payables']['activity'], 2) }}
                        </td>
                        <td class="py-3 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $report->movements['total_payables']['closing'] }}">₹{{ number_format($report->movements['total_payables']['closing'], 2) }}</td>
                    </tr>
                    <tr class="hover:bg-slate-50/80 transition table-row-item text-slate-600">
                        <td class="py-2.5 px-3 pl-8 flex items-center gap-2">└ Purchaser Payables</td>
                        <td class="py-2.5 px-3 text-right font-mono" data-val="{{ $report->movements['purchaser_payables']['opening'] }}">₹{{ number_format($report->movements['purchaser_payables']['opening'], 2) }}</td>
                        <td class="py-2.5 px-3 text-right font-mono" data-val="{{ $report->movements['purchaser_payables']['activity'] }}">
                            {{ $report->movements['purchaser_payables']['activity'] >= 0 ? '+' : '' }}₹{{ number_format($report->movements['purchaser_payables']['activity'], 2) }}
                        </td>
                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $report->movements['purchaser_payables']['closing'] }}">₹{{ number_format($report->movements['purchaser_payables']['closing'], 2) }}</td>
                    </tr>
                    <tr class="hover:bg-slate-50/80 transition table-row-item text-slate-600">
                        <td class="py-2.5 px-3 pl-8 flex items-center gap-2">└ Vendor Payables</td>
                        <td class="py-2.5 px-3 text-right font-mono" data-val="{{ $report->movements['vendor_payables']['opening'] }}">₹{{ number_format($report->movements['vendor_payables']['opening'], 2) }}</td>
                        <td class="py-2.5 px-3 text-right font-mono" data-val="{{ $report->movements['vendor_payables']['activity'] }}">
                            {{ $report->movements['vendor_payables']['activity'] >= 0 ? '+' : '' }}₹{{ number_format($report->movements['vendor_payables']['activity'], 2) }}
                        </td>
                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $report->movements['vendor_payables']['closing'] }}">₹{{ number_format($report->movements['vendor_payables']['closing'], 2) }}</td>
                    </tr>
                    <tr class="hover:bg-slate-50/80 transition table-row-item text-slate-600">
                        <td class="py-2.5 px-3 pl-8 flex items-center gap-2">└ Company Owes Shops</td>
                        <td class="py-2.5 px-3 text-right font-mono" data-val="{{ $report->movements['company_owes_shops']['opening'] }}">₹{{ number_format($report->movements['company_owes_shops']['opening'], 2) }}</td>
                        <td class="py-2.5 px-3 text-right font-mono" data-val="{{ $report->movements['company_owes_shops']['activity'] }}">
                            {{ $report->movements['company_owes_shops']['activity'] >= 0 ? '+' : '' }}₹{{ number_format($report->movements['company_owes_shops']['activity'], 2) }}
                        </td>
                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $report->movements['company_owes_shops']['closing'] }}">₹{{ number_format($report->movements['company_owes_shops']['closing'], 2) }}</td>
                    </tr>
                    <tr class="hover:bg-slate-50/80 transition table-row-item text-slate-600">
                        <td class="py-2.5 px-3 pl-8 flex items-center gap-2">└ Petty Payables</td>
                        <td class="py-2.5 px-3 text-right font-mono" data-val="{{ $report->movements['petty_payables']['opening'] }}">₹{{ number_format($report->movements['petty_payables']['opening'], 2) }}</td>
                        <td class="py-2.5 px-3 text-right font-mono" data-val="{{ $report->movements['petty_payables']['activity'] }}">
                            {{ $report->movements['petty_payables']['activity'] >= 0 ? '+' : '' }}₹{{ number_format($report->movements['petty_payables']['activity'], 2) }}
                        </td>
                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $report->movements['petty_payables']['closing'] }}">₹{{ number_format($report->movements['petty_payables']['closing'], 2) }}</td>
                    </tr>
                    <tr class="hover:bg-slate-50/80 transition table-row-item">
                        <td class="py-3 px-3 font-bold text-slate-900 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-amber-500"></span> Floating In
                        </td>
                        <td class="py-3 px-3 text-right font-mono" data-val="{{ $report->movements['floating_in']['opening'] }}">₹{{ number_format($report->movements['floating_in']['opening'], 2) }}</td>
                        <td class="py-3 px-3 text-right font-mono text-amber-600" data-val="{{ $report->movements['floating_in']['activity'] }}">
                            +₹{{ number_format($report->movements['floating_in']['activity'], 2) }}
                        </td>
                        <td class="py-3 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $report->movements['floating_in']['closing'] }}">₹{{ number_format($report->movements['floating_in']['closing'], 2) }}</td>
                    </tr>
                    <tr class="hover:bg-slate-50/80 transition table-row-item">
                        <td class="py-3 px-3 font-bold text-slate-900 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-purple-500"></span> Floating Out
                        </td>
                        <td class="py-3 px-3 text-right font-mono" data-val="{{ $report->movements['floating_out']['opening'] }}">₹{{ number_format($report->movements['floating_out']['opening'], 2) }}</td>
                        <td class="py-3 px-3 text-right font-mono text-rose-600" data-val="{{ $report->movements['floating_out']['activity'] }}">
                            +₹{{ number_format($report->movements['floating_out']['activity'], 2) }}
                        </td>
                        <td class="py-3 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $report->movements['floating_out']['closing'] }}">₹{{ number_format($report->movements['floating_out']['closing'], 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 6. ALL CONTRIBUTING TRANSACTIONS AUDIT TABLE -->
    <div class="rounded-3xl bg-white p-6 border border-slate-200/90 shadow-xs space-y-4" id="section-transactions">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
                <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <i data-lucide="list-filter" class="w-5 h-5 text-slate-600"></i> All Contributing Transactions
                </h2>
                <p class="text-xs font-medium text-slate-400 mt-0.5">Filterable transaction audit trail for selected period</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                    <input type="text" data-table-search="table-transactions" placeholder="Search transactions..."
                        class="pl-9 pr-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-slate-800 focus:outline-none w-48 sm:w-64 shadow-2xs">
                </div>
                <select data-table-size="table-transactions" class="px-2.5 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white focus:outline-none shadow-2xs">
                    <option value="10" selected>10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="200">All</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse text-xs" id="table-transactions">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider select-none bg-slate-50/50">
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">Date <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">Account <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">Description <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">Direction <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Amount <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">Status <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">Reference <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="string">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($report->transactions as $tx)
                        <tr class="hover:bg-slate-50/80 transition table-row-item">
                            <td class="py-3 px-3 font-mono text-slate-500 whitespace-nowrap">{{ $tx['date'] }}</td>
                            <td class="py-3 px-3 font-bold text-slate-900">{{ $tx['account'] }}</td>
                            <td class="py-3 px-3 text-slate-600">{{ $tx['description'] }}</td>
                            <td class="py-3 px-3 uppercase text-[10px] font-black {{ $tx['direction'] === 'IN' ? 'text-emerald-700' : 'text-rose-600' }}">{{ $tx['direction'] }}</td>
                            <td class="py-3 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $tx['amount'] }}">₹{{ number_format($tx['amount'], 2) }}</td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase {{ $tx['status'] === 'CLEARED' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
                                    {{ $tx['status'] }}
                                </span>
                            </td>
                            <td class="py-3 px-3 font-mono text-slate-500">{{ $tx['reference'] }}</td>
                            <td class="py-3 px-3 text-right font-medium whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if(!empty($tx['details_url']))
                                        <a href="{{ $tx['details_url'] }}" title="View Details" class="p-1 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition inline-flex items-center">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </a>
                                    @endif
                                    @if(!empty($tx['delete_url']))
                                        <form action="{{ $tx['delete_url'] }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this statement entry?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Delete Entry" class="p-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition inline-flex items-center">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="no-records-row">
                            <td colspan="8" class="py-6 text-center text-slate-400 font-medium">No transactions found for the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-100 text-xs font-semibold text-slate-500 overflow-hidden" data-table-footer="table-transactions">
            <span data-table-info="table-transactions">Showing 0 entries</span>
            <div class="flex items-center gap-1 flex-wrap justify-end max-w-full overflow-x-auto" data-table-pagination="table-transactions"></div>
        </div>
    </div>

</div>

<!-- Client-side Interactive Engine (Instant Table Search/Sort/Pagination) -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tableIds = [
            'table-accounts',
            'table-floating-in',
            'table-floating-out',
            'table-receivables',
            'table-purchaser-payables',
            'table-purchaser-payments',
            'table-vendor-payables',
            'table-vendor-payments',
            'table-company-owes-shops',
            'table-petty-payables',
            'table-other-payables',
            'table-movements',
            'table-transactions'
        ];

        tableIds.forEach(tableId => initDataTableEngine(tableId));
    });

    function initDataTableEngine(tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;

        const tbody = table.querySelector('tbody');
        const originalRows = Array.from(tbody.querySelectorAll('tr.table-row-item'));
        const noRecordsRow = tbody.querySelector('tr.no-records-row');
        const searchInput = document.querySelector(`[data-table-search="${tableId}"]`);
        const sizeSelect = document.querySelector(`[data-table-size="${tableId}"]`);
        const infoSpan = document.querySelector(`[data-table-info="${tableId}"]`);
        const paginationContainer = document.querySelector(`[data-table-pagination="${tableId}"]`);
        const headers = table.querySelectorAll('thead th[data-sort]');

        let filteredRows = [...originalRows];
        let currentPage = 1;
        let pageSize = sizeSelect ? parseInt(sizeSelect.value, 10) : 10;
        let currentSortCol = null;
        let currentSortDir = 'asc';

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const query = this.value.toLowerCase().trim();
                if (!query) {
                    filteredRows = [...originalRows];
                } else {
                    filteredRows = originalRows.filter(row => {
                        return row.textContent.toLowerCase().includes(query);
                    });
                }
                currentPage = 1;
                render();
            });
        }

        if (sizeSelect) {
            sizeSelect.addEventListener('change', function () {
                pageSize = parseInt(this.value, 10);
                currentPage = 1;
                render();
            });
        }

        headers.forEach((header, colIndex) => {
            header.addEventListener('click', function () {
                const sortType = this.getAttribute('data-sort') || 'string';

                if (currentSortCol === colIndex) {
                    currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSortCol = colIndex;
                    currentSortDir = 'asc';
                }

                headers.forEach((h, idx) => {
                    const icon = h.querySelector('.sort-icon');
                    if (icon) {
                        if (idx === currentSortCol) {
                            icon.textContent = currentSortDir === 'asc' ? '↑' : '↓';
                            icon.className = 'sort-icon ml-1 text-emerald-600 font-bold';
                        } else {
                            icon.textContent = '↕';
                            icon.className = 'sort-icon ml-1 text-slate-300';
                        }
                    }
                });

                filteredRows.sort((rowA, rowB) => {
                    const cellA = rowA.children[colIndex];
                    const cellB = rowB.children[colIndex];
                    if (!cellA || !cellB) return 0;

                    let valA = cellA.getAttribute('data-val') !== null
                        ? parseFloat(cellA.getAttribute('data-val'))
                        : cellA.textContent.trim();

                    let valB = cellB.getAttribute('data-val') !== null
                        ? parseFloat(cellB.getAttribute('data-val'))
                        : cellB.textContent.trim();

                    if (sortType === 'number') {
                        valA = typeof valA === 'number' && !isNaN(valA) ? valA : parseFloat(valA.toString().replace(/[^0-9.-]+/g, '')) || 0;
                        valB = typeof valB === 'number' && !isNaN(valB) ? valB : parseFloat(valB.toString().replace(/[^0-9.-]+/g, '')) || 0;
                        return currentSortDir === 'asc' ? valA - valB : valB - valA;
                    } else {
                        valA = valA.toString().toLowerCase();
                        valB = valB.toString().toLowerCase();
                        if (valA < valB) return currentSortDir === 'asc' ? -1 : 1;
                        if (valA > valB) return currentSortDir === 'asc' ? 1 : -1;
                        return 0;
                    }
                });

                render();
            });
        });

        function render() {
            originalRows.forEach(row => row.style.display = 'none');
            if (noRecordsRow) noRecordsRow.style.display = 'none';

            const total = filteredRows.length;

            if (total === 0) {
                if (noRecordsRow) noRecordsRow.style.display = '';
                if (infoSpan) infoSpan.textContent = 'Showing 0 to 0 of 0 entries';
                if (paginationContainer) paginationContainer.innerHTML = '';
                return;
            }

            const totalPages = Math.ceil(total / pageSize);
            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const startIdx = (currentPage - 1) * pageSize;
            const endIdx = Math.min(startIdx + pageSize, total);

            for (let i = startIdx; i < endIdx; i++) {
                if (filteredRows[i]) {
                    filteredRows[i].style.display = '';
                    tbody.appendChild(filteredRows[i]);
                }
            }

            if (infoSpan) {
                infoSpan.textContent = `Showing ${startIdx + 1} to ${endIdx} of ${total} entries`;
            }

            if (paginationContainer) {
                paginationContainer.innerHTML = '';

                if (totalPages <= 1) return;

                function getPaginationRange(current, total) {
                    if (total <= 7) {
                        return Array.from({ length: total }, (_, i) => i + 1);
                    }
                    if (current <= 4) {
                        return [1, 2, 3, 4, 5, '...', total];
                    }
                    if (current >= total - 3) {
                        return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
                    }
                    return [1, '...', current - 1, current, current + 1, '...', total];
                }

                const prevBtn = document.createElement('button');
                prevBtn.type = 'button';
                prevBtn.innerHTML = '‹ Prev';
                prevBtn.disabled = currentPage === 1;
                prevBtn.className = `px-2.5 py-1 rounded-lg text-xs font-bold transition ${currentPage === 1 ? 'text-slate-300 cursor-not-allowed opacity-50' : 'text-slate-700 bg-slate-100 hover:bg-slate-200 cursor-pointer'}`;
                prevBtn.onclick = () => { if (currentPage > 1) { currentPage--; render(); } };
                paginationContainer.appendChild(prevBtn);

                const range = getPaginationRange(currentPage, totalPages);
                range.forEach(item => {
                    if (item === '...') {
                        const span = document.createElement('span');
                        span.className = 'px-2 py-1 text-xs text-slate-400 font-bold select-none';
                        span.textContent = '...';
                        paginationContainer.appendChild(span);
                    } else {
                        const pageBtn = document.createElement('button');
                        pageBtn.type = 'button';
                        pageBtn.textContent = item;
                        pageBtn.className = `px-2.5 py-1 rounded-lg text-xs font-extrabold transition cursor-pointer ${item === currentPage ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-600 bg-slate-100 hover:bg-slate-200'}`;
                        pageBtn.onclick = () => { currentPage = item; render(); };
                        paginationContainer.appendChild(pageBtn);
                    }
                });

                const nextBtn = document.createElement('button');
                nextBtn.type = 'button';
                nextBtn.innerHTML = 'Next ›';
                nextBtn.disabled = currentPage === totalPages;
                nextBtn.className = `px-2.5 py-1 rounded-lg text-xs font-bold transition ${currentPage === totalPages ? 'text-slate-300 cursor-not-allowed opacity-50' : 'text-slate-700 bg-slate-100 hover:bg-slate-200 cursor-pointer'}`;
                nextBtn.onclick = () => { if (currentPage < totalPages) { currentPage++; render(); } };
                paginationContainer.appendChild(nextBtn);
            }
        }

        render();
    }
</script>
@endsection
