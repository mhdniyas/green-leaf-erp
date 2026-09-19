@extends('admin.cashbook.layouts.app')

@section('title', 'Cashbook — Account Balance Report')

@section('header_title')
    <i data-lucide="scale" class="w-5 h-5 text-emerald-600"></i> Account Balance Report
@endsection

@section('header_subtitle')
    Company-wide read-only financial position across bank/cash accounts, floating items, receivables, payables, and period movements.
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-6">

    <!-- Top Controls & Professional Period Filter Bar -->
    <div class="rounded-3xl bg-white p-6 border border-slate-200/90 shadow-sm space-y-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white shadow-md shadow-emerald-600/20">
                    <i data-lucide="scale" class="h-6 w-6"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-xl font-extrabold tracking-tight text-slate-900">
                            Account Balance Report
                        </h1>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200/90 px-3 py-0.5 text-xs font-black font-mono shadow-2xs">
                            <i data-lucide="calendar" class="w-3 h-3 text-emerald-600"></i>
                            {{ $report->period['label'] }}
                        </span>
                        <span class="rounded-full bg-slate-900 text-slate-100 px-3 py-0.5 text-[10px] font-extrabold uppercase tracking-widest shadow-2xs">
                            Read Only
                        </span>
                    </div>
                    <p class="text-xs font-medium text-slate-500 mt-1">
                        Company-wide audited balances, floating items, receivables, payables, and period activity matrix.
                    </p>
                </div>
            </div>

            <!-- Quick Active Summary Badge -->
            <div class="flex items-center gap-2 bg-slate-50 p-2 rounded-2xl border border-slate-200/70 self-start md:self-auto">
                <div class="px-3 py-1.5 rounded-xl bg-white border border-slate-200 shadow-2xs text-right">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Actual Balance</span>
                    <span class="text-sm font-black font-mono text-slate-900">₹{{ number_format($report->summary['actual_balance'], 2) }}</span>
                </div>
                <div class="px-3 py-1.5 rounded-xl bg-emerald-600 text-white shadow-2xs text-right">
                    <span class="text-[10px] uppercase font-bold text-emerald-200 block tracking-wider">Expected Balance</span>
                    <span class="text-sm font-black font-mono text-white">₹{{ number_format($report->summary['expected_balance'], 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Period Preset Selector Form -->
        <form method="GET" action="{{ route('admin.cashbook.account-balance') }}" id="period-filter-form" class="pt-4 border-t border-slate-100 space-y-4">
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <span class="text-[11px] font-extrabold uppercase text-slate-400 tracking-wider flex items-center gap-1.5">
                    <i data-lucide="filter" class="w-3.5 h-3.5 text-emerald-600"></i> Select Period Filter:
                </span>
                <span class="text-xs font-bold text-slate-500 font-mono">
                    From <strong class="text-slate-800 font-bold">{{ $report->period['from_date'] }}</strong> to <strong class="text-slate-800 font-bold">{{ $report->period['to_date'] }}</strong>
                </span>
            </div>

            <!-- Segmented Filter Buttons -->
            <div class="p-1.5 bg-slate-100/80 rounded-2xl border border-slate-200/70 flex flex-wrap items-center gap-1">
                @php
                    $presets = [
                        'today' => 'Today',
                        'yesterday' => 'Yesterday',
                        'this_week' => 'This Week',
                        'last_week' => 'Last Week',
                        'this_month' => 'This Month',
                        'last_month' => 'Last Month',
                        'this_quarter' => 'This Quarter',
                        'this_year' => 'This Year',
                        'all_time' => 'All Time',
                        'custom' => 'Custom Range',
                    ];
                    $currentPreset = $report->period['preset'];
                @endphp

                @foreach($presets as $key => $name)
                    <button type="button" onclick="selectPreset('{{ $key }}')"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold transition-all duration-200 cursor-pointer flex items-center gap-1.5 {{ $currentPreset === $key ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30' : 'bg-transparent text-slate-600 hover:bg-white hover:text-slate-900' }}">
                        @if($currentPreset === $key)
                            <i data-lucide="check-circle-2" class="w-3.5 h-3.5 text-white"></i>
                        @endif
                        {{ $name }}
                    </button>
                @endforeach
                <input type="hidden" name="preset" id="selected-preset" value="{{ $currentPreset }}">
            </div>

            <!-- Custom Date Range Picker Container (Smooth Toggle) -->
            <div id="custom-date-container" class="p-4 rounded-2xl bg-slate-50 border border-slate-200/90 space-y-3 {{ $currentPreset === 'custom' ? 'block' : 'hidden' }}">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-extrabold text-slate-700 flex items-center gap-1.5">
                        <i data-lucide="calendar-range" class="w-4 h-4 text-emerald-600"></i> Custom Date Range Selection
                    </span>
                    <span class="text-[11px] font-medium text-slate-400">Select start and end dates then click Apply</span>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <label for="from_date" class="text-xs font-bold text-slate-600">From Date:</label>
                        <input type="date" id="from_date" name="from_date" value="{{ $report->period['from_date'] }}"
                            class="px-3.5 py-2 rounded-xl border border-slate-300 bg-white text-xs font-extrabold text-slate-900 focus:ring-2 focus:ring-emerald-600 focus:outline-none shadow-2xs">
                    </div>
                    <div class="flex items-center gap-2">
                        <label for="to_date" class="text-xs font-bold text-slate-600">To Date:</label>
                        <input type="date" id="to_date" name="to_date" value="{{ $report->period['to_date'] }}"
                            class="px-3.5 py-2 rounded-xl border border-slate-300 bg-white text-xs font-extrabold text-slate-900 focus:ring-2 focus:ring-emerald-600 focus:outline-none shadow-2xs">
                    </div>
                    <button type="submit"
                        class="px-5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-extrabold hover:bg-emerald-700 transition shadow-md shadow-emerald-600/20 cursor-pointer flex items-center gap-1.5">
                        <i data-lucide="filter" class="w-4 h-4"></i> Apply Custom Filter
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- MAIN SUMMARY KPI CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Actual Balance -->
        <div class="rounded-3xl bg-white p-5 border border-slate-200/80 shadow-xs relative overflow-hidden group hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Actual Balance</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 group-hover:scale-105 transition">
                    <i data-lucide="landmark" class="w-5 h-5"></i>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-black text-slate-900 font-mono">₹{{ number_format($report->summary['actual_balance'], 2) }}</span>
            </div>
            <p class="mt-1 text-[11px] font-medium text-slate-400">Confirmed money in company bank &amp; cash accounts</p>
        </div>

        <!-- Floating In -->
        <div class="rounded-3xl bg-white p-5 border border-slate-200/80 shadow-xs relative overflow-hidden group hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-600">Floating In</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600 group-hover:scale-105 transition">
                    <i data-lucide="arrow-down-left" class="w-5 h-5"></i>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-black text-amber-600 font-mono">₹{{ number_format($report->summary['floating_in'], 2) }}</span>
            </div>
            <p class="mt-1 text-[11px] font-medium text-slate-400">Inbound payments pending clearance</p>
        </div>

        <!-- Floating Out -->
        <div class="rounded-3xl bg-white p-5 border border-slate-200/80 shadow-xs relative overflow-hidden group hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-rose-600">Floating Out</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600 group-hover:scale-105 transition">
                    <i data-lucide="arrow-up-right" class="w-5 h-5"></i>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-black text-rose-600 font-mono">₹{{ number_format($report->summary['floating_out'], 2) }}</span>
            </div>
            <p class="mt-1 text-[11px] font-medium text-slate-400">Outbound payments pending clearance</p>
        </div>

        <!-- Expected Balance -->
        <div class="rounded-3xl bg-slate-900 text-white p-5 shadow-sm relative overflow-hidden group hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-400">Expected Balance</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/20 text-emerald-400 group-hover:scale-105 transition">
                    <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-black text-emerald-400 font-mono">₹{{ number_format($report->summary['expected_balance'], 2) }}</span>
            </div>
            <p class="mt-1 text-[11px] font-medium text-slate-400">Actual + Floating In - Floating Out</p>
        </div>
    </div>

    <!-- SECONDARY SUMMARY KPI CARDS (Receivables & Payables & Net Floating) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Net Floating -->
        <div class="rounded-3xl bg-white p-4 border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                <span>Net Floating</span>
                <span class="font-mono text-slate-800 font-extrabold">₹{{ number_format($report->summary['net_floating'], 2) }}</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Floating In - Floating Out</p>
        </div>

        <!-- Receivables -->
        <div class="rounded-3xl bg-white p-4 border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                <span>Receivables (Shops)</span>
                <span class="font-mono text-emerald-700 font-extrabold">₹{{ number_format($report->summary['receivables'], 2) }}</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Net money owed to company (after floating offsets)</p>
        </div>

        <!-- Payables -->
        <div class="rounded-3xl bg-white p-4 border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                <span>Payables (Vendors)</span>
                <span class="font-mono text-rose-600 font-extrabold">₹{{ number_format($report->summary['payables'], 2) }}</span>
            </div>
            <p class="text-[11px] text-slate-400 mt-1">Money company owes to third parties</p>
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
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">
                            Account Name <span class="sort-icon ml-1 text-slate-300">↕</span>
                        </th>
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">
                            Type <span class="sort-icon ml-1 text-slate-300">↕</span>
                        </th>
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">
                            Bank / Institution <span class="sort-icon ml-1 text-slate-300">↕</span>
                        </th>
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">
                            Account No. <span class="sort-icon ml-1 text-slate-300">↕</span>
                        </th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">
                            Opening Actual <span class="sort-icon ml-1 text-slate-300">↕</span>
                        </th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">
                            Confirmed In <span class="sort-icon ml-1 text-slate-300">↕</span>
                        </th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">
                            Confirmed Out <span class="sort-icon ml-1 text-slate-300">↕</span>
                        </th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">
                            Closing Actual <span class="sort-icon ml-1 text-slate-300">↕</span>
                        </th>
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

        <!-- Table Pagination Footer -->
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
                            <th class="py-2.5 px-2 cursor-pointer hover:text-slate-900 transition" data-sort="string">From <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 cursor-pointer hover:text-slate-900 transition" data-sort="string">To Account <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Amount <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 cursor-pointer hover:text-slate-900 transition" data-sort="string">Status <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Age <span class="sort-icon text-slate-300">↕</span></th>
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
                            </tr>
                        @empty
                            <tr class="no-records-row">
                                <td colspan="6" class="py-6 text-center text-slate-400 font-medium">No active floating in items.</td>
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
                            </tr>
                        @empty
                            <tr class="no-records-row">
                                <td colspan="6" class="py-6 text-center text-slate-400 font-medium">No active floating out items.</td>
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

    <!-- 3. RECEIVABLES & PAYABLES -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Receivables Table -->
        <div class="rounded-3xl bg-white p-6 border border-slate-200/90 shadow-xs space-y-4" id="section-receivables">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <i data-lucide="hand-coins" class="w-5 h-5 text-emerald-600"></i> Receivables Breakdown
                </h2>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2"></i>
                        <input type="text" data-table-search="table-receivables" placeholder="Search..."
                            class="pl-8 pr-2.5 py-1 rounded-xl border border-slate-200 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-emerald-600 focus:outline-none w-36 shadow-2xs">
                    </div>
                    <select data-table-size="table-receivables" class="px-2 py-1 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white focus:outline-none">
                        <option value="5" selected>5 / page</option>
                        <option value="10">10 / page</option>
                        <option value="50">All</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse text-xs" id="table-receivables">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider select-none bg-slate-50/50">
                            <th class="py-2.5 px-2 cursor-pointer hover:text-slate-900 transition" data-sort="string">Party / Shop <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Opening <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">New <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Received <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Float Offset <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Closing Net <span class="sort-icon text-slate-300">↕</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($report->receivables as $rec)
                            <tr class="hover:bg-slate-50/80 transition table-row-item">
                                <td class="py-2.5 px-2 font-bold text-slate-900">{{ $rec['party'] }}</td>
                                <td class="py-2.5 px-2 text-right font-mono text-slate-500" data-val="{{ $rec['opening_outstanding'] }}">₹{{ number_format($rec['opening_outstanding'], 2) }}</td>
                                <td class="py-2.5 px-2 text-right font-mono text-slate-600" data-val="{{ $rec['new_receivable'] }}">+₹{{ number_format($rec['new_receivable'], 2) }}</td>
                                <td class="py-2.5 px-2 text-right font-mono text-emerald-600" data-val="{{ $rec['received'] }}">-₹{{ number_format($rec['received'], 2) }}</td>
                                <td class="py-2.5 px-2 text-right font-mono text-amber-600" data-val="{{ $rec['floating_in_offset'] }}">-₹{{ number_format($rec['floating_in_offset'], 2) }}</td>
                                <td class="py-2.5 px-2 text-right font-mono font-bold text-slate-900" data-val="{{ $rec['closing_outstanding'] }}">₹{{ number_format($rec['closing_outstanding'], 2) }}</td>
                            </tr>
                        @empty
                            <tr class="no-records-row">
                                <td colspan="6" class="py-6 text-center text-slate-400 font-medium">No receivables records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 border-t border-slate-100 text-xs font-semibold text-slate-500 overflow-hidden" data-table-footer="table-receivables">
                <span data-table-info="table-receivables">Showing 0 entries</span>
                <div class="flex items-center gap-1 flex-wrap justify-end max-w-full overflow-x-auto" data-table-pagination="table-receivables"></div>
            </div>
        </div>

        <!-- Payables Table -->
        <div class="rounded-3xl bg-white p-6 border border-slate-200/90 shadow-xs space-y-4" id="section-payables">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <i data-lucide="receipt text-rose-600" class="w-5 h-5 text-rose-600"></i> Payables Breakdown
                </h2>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2"></i>
                        <input type="text" data-table-search="table-payables" placeholder="Search..."
                            class="pl-8 pr-2.5 py-1 rounded-xl border border-slate-200 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-rose-500 focus:outline-none w-36 shadow-2xs">
                    </div>
                    <select data-table-size="table-payables" class="px-2 py-1 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white focus:outline-none">
                        <option value="5" selected>5 / page</option>
                        <option value="10">10 / page</option>
                        <option value="50">All</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse text-xs" id="table-payables">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider select-none bg-slate-50/50">
                            <th class="py-2.5 px-2 cursor-pointer hover:text-slate-900 transition" data-sort="string">Party / Vendor <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Opening <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">New Payable <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Paid <span class="sort-icon text-slate-300">↕</span></th>
                            <th class="py-2.5 px-2 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Closing Payable <span class="sort-icon text-slate-300">↕</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        @forelse($report->payables as $pay)
                            <tr class="hover:bg-slate-50/80 transition table-row-item">
                                <td class="py-2.5 px-2 font-bold text-slate-900">{{ $pay['party'] }}</td>
                                <td class="py-2.5 px-2 text-right font-mono text-slate-500" data-val="{{ $pay['opening_payable'] }}">₹{{ number_format($pay['opening_payable'], 2) }}</td>
                                <td class="py-2.5 px-2 text-right font-mono text-slate-600" data-val="{{ $pay['new_payable'] }}">+₹{{ number_format($pay['new_payable'], 2) }}</td>
                                <td class="py-2.5 px-2 text-right font-mono text-emerald-600" data-val="{{ $pay['paid'] }}">-₹{{ number_format($pay['paid'], 2) }}</td>
                                <td class="py-2.5 px-2 text-right font-mono font-bold text-slate-900" data-val="{{ $pay['closing_payable'] }}">₹{{ number_format($pay['closing_payable'], 2) }}</td>
                            </tr>
                        @empty
                            <tr class="no-records-row">
                                <td colspan="5" class="py-6 text-center text-slate-400 font-medium">No payables records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 border-t border-slate-100 text-xs font-semibold text-slate-500 overflow-hidden" data-table-footer="table-payables">
                <span data-table-info="table-payables">Showing 0 entries</span>
                <div class="flex items-center gap-1 flex-wrap justify-end max-w-full overflow-x-auto" data-table-pagination="table-payables"></div>
            </div>
        </div>
    </div>

    <!-- 4. PERIOD MOVEMENT MATRIX TABLE -->
    <div class="rounded-3xl bg-white p-6 border border-slate-200/90 shadow-xs space-y-4" id="section-movements">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
                <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <i data-lucide="line-chart" class="w-5 h-5 text-indigo-600"></i> Period Movement Accounting Matrix
                </h2>
                <p class="text-xs font-medium text-slate-400 mt-0.5">Summary of position transitions over selected period</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                    <input type="text" data-table-search="table-movements" placeholder="Search metrics..."
                        class="pl-9 pr-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-indigo-600 focus:outline-none w-48 shadow-2xs">
                </div>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse text-xs" id="table-movements">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 font-extrabold uppercase text-[10px] tracking-wider select-none bg-slate-50/50">
                        <th class="py-3 px-3 cursor-pointer hover:text-slate-900 transition" data-sort="string">Metric Category <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Opening Position <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Period Activity <span class="sort-icon text-slate-300">↕</span></th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-slate-900 transition" data-sort="number">Closing Position <span class="sort-icon text-slate-300">↕</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    <tr class="hover:bg-slate-50/80 transition table-row-item">
                        <td class="py-3 px-3 font-bold text-slate-900 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Actual Company Balance
                        </td>
                        <td class="py-3 px-3 text-right font-mono" data-val="{{ $report->movements['actual_balance']['opening'] }}">₹{{ number_format($report->movements['actual_balance']['opening'], 2) }}</td>
                        <td class="py-3 px-3 text-right font-mono {{ $report->movements['actual_balance']['activity'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}" data-val="{{ $report->movements['actual_balance']['activity'] }}">
                            {{ $report->movements['actual_balance']['activity'] >= 0 ? '+' : '' }}₹{{ number_format($report->movements['actual_balance']['activity'], 2) }}
                        </td>
                        <td class="py-3 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $report->movements['actual_balance']['closing'] }}">₹{{ number_format($report->movements['actual_balance']['closing'], 2) }}</td>
                    </tr>
                    <tr class="hover:bg-slate-50/80 transition table-row-item">
                        <td class="py-3 px-3 font-bold text-slate-900 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-blue-500"></span> Shop Receivables
                        </td>
                        <td class="py-3 px-3 text-right font-mono" data-val="{{ $report->movements['receivables']['opening'] }}">₹{{ number_format($report->movements['receivables']['opening'], 2) }}</td>
                        <td class="py-3 px-3 text-right font-mono" data-val="{{ $report->movements['receivables']['activity'] }}">
                            {{ $report->movements['receivables']['activity'] >= 0 ? '+' : '' }}₹{{ number_format($report->movements['receivables']['activity'], 2) }}
                        </td>
                        <td class="py-3 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $report->movements['receivables']['closing'] }}">₹{{ number_format($report->movements['receivables']['closing'], 2) }}</td>
                    </tr>
                    <tr class="hover:bg-slate-50/80 transition table-row-item">
                        <td class="py-3 px-3 font-bold text-slate-900 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-rose-500"></span> Vendor Payables
                        </td>
                        <td class="py-3 px-3 text-right font-mono" data-val="{{ $report->movements['payables']['opening'] }}">₹{{ number_format($report->movements['payables']['opening'], 2) }}</td>
                        <td class="py-3 px-3 text-right font-mono" data-val="{{ $report->movements['payables']['activity'] }}">
                            {{ $report->movements['payables']['activity'] >= 0 ? '+' : '' }}₹{{ number_format($report->movements['payables']['activity'], 2) }}
                        </td>
                        <td class="py-3 px-3 text-right font-mono font-bold text-slate-900" data-val="{{ $report->movements['payables']['closing'] }}">₹{{ number_format($report->movements['payables']['closing'], 2) }}</td>
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

        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-100 text-xs font-semibold text-slate-500 overflow-hidden" data-table-footer="table-movements">
            <span data-table-info="table-movements">Showing 5 entries</span>
            <div class="flex items-center gap-1 flex-wrap justify-end max-w-full overflow-x-auto" data-table-pagination="table-movements"></div>
        </div>
    </div>

    <!-- 5. ALL TRANSACTIONS DRILL-DOWN TABLE -->
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
                        </tr>
                    @empty
                        <tr class="no-records-row">
                            <td colspan="7" class="py-6 text-center text-slate-400 font-medium">No transactions found for the selected period.</td>
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

<!-- Client-side Interactive Engine (Period Preset Switcher + Instant Table Search/Sort/Pagination) -->
<script>
    function selectPreset(presetKey) {
        const form = document.getElementById('period-filter-form');
        const input = document.getElementById('selected-preset');
        const customContainer = document.getElementById('custom-date-container');

        input.value = presetKey;

        if (presetKey === 'custom') {
            customContainer.classList.remove('hidden');
            customContainer.classList.add('block');
        } else {
            customContainer.classList.add('hidden');
            customContainer.classList.remove('block');
            form.submit();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Initialize all tables on page with Search, Column Sorting, and Pagination
        const tableIds = [
            'table-accounts',
            'table-floating-in',
            'table-floating-out',
            'table-receivables',
            'table-payables',
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

        // 1. Search Filter Handler
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

        // 2. Page Size Handler
        if (sizeSelect) {
            sizeSelect.addEventListener('change', function () {
                pageSize = parseInt(this.value, 10);
                currentPage = 1;
                render();
            });
        }

        // 3. Column Header Sorting Handler
        headers.forEach((header, colIndex) => {
            header.addEventListener('click', function () {
                const sortType = this.getAttribute('data-sort') || 'string';

                if (currentSortCol === colIndex) {
                    currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSortCol = colIndex;
                    currentSortDir = 'asc';
                }

                // Update sort indicator icons in header
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

                // Sort filtered rows array
                filteredRows.sort((rowA, rowB) => {
                    const cellA = rowA.children[colIndex];
                    const cellB = rowB.children[colIndex];

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

        // 4. Render Table Pagination & Visible Rows
        function render() {
            // Hide all original rows first
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

            // Append and display visible page rows
            for (let i = startIdx; i < endIdx; i++) {
                if (filteredRows[i]) {
                    filteredRows[i].style.display = '';
                    tbody.appendChild(filteredRows[i]);
                }
            }

            // Update Info text
            if (infoSpan) {
                infoSpan.textContent = `Showing ${startIdx + 1} to ${endIdx} of ${total} entries`;
            }

            // Render Pagination Buttons
            if (paginationContainer) {
                paginationContainer.innerHTML = '';

                if (totalPages <= 1) return;

                // Smart Ellipsis Range Generator
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

                // Previous Button
                const prevBtn = document.createElement('button');
                prevBtn.type = 'button';
                prevBtn.innerHTML = '‹ Prev';
                prevBtn.disabled = currentPage === 1;
                prevBtn.className = `px-2.5 py-1 rounded-lg text-xs font-bold transition ${currentPage === 1 ? 'text-slate-300 cursor-not-allowed opacity-50' : 'text-slate-700 bg-slate-100 hover:bg-slate-200 cursor-pointer'}`;
                prevBtn.onclick = () => { if (currentPage > 1) { currentPage--; render(); } };
                paginationContainer.appendChild(prevBtn);

                // Page Range Buttons with Ellipsis
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

                // Next Button
                const nextBtn = document.createElement('button');
                nextBtn.type = 'button';
                nextBtn.innerHTML = 'Next ›';
                nextBtn.disabled = currentPage === totalPages;
                nextBtn.className = `px-2.5 py-1 rounded-lg text-xs font-bold transition ${currentPage === totalPages ? 'text-slate-300 cursor-not-allowed opacity-50' : 'text-slate-700 bg-slate-100 hover:bg-slate-200 cursor-pointer'}`;
                nextBtn.onclick = () => { if (currentPage < totalPages) { currentPage++; render(); } };
                paginationContainer.appendChild(nextBtn);
            }
        }

        // Initial render
        render();
    }
</script>
@endsection
