@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop').' — Expense Settlement Allocations')

@section('content')
<div x-data="shopExpenseAllocationsControl()" class="mx-auto max-w-7xl space-y-6 pb-16">
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
                <p class="text-xs text-slate-500 mt-0.5">Monthly Expense Settlement Allocations &amp; Balance Management</p>
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
           class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-black border-b-2 border-transparent text-slate-500 hover:text-slate-900 hover:border-slate-300 transition">
            <i data-lucide="receipt" class="w-4 h-4 text-slate-400"></i>
            <span>Payment History</span>
        </a>
        <a href="{{ route('admin.cashbook.shop.history.allocations', ['shop' => $shopKey, 'month' => $month]) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-black border-b-2 border-slate-900 text-slate-900 transition">
            <i data-lucide="split" class="w-4 h-4 text-slate-800"></i>
            <span>Expense Allocations</span>
        </a>
    </div>

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-3">
            <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-3">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600 shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold space-y-1">
            <div class="flex items-center gap-2 font-black">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                <span>Validation Error</span>
            </div>
            <ul class="list-disc pl-5 font-medium">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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
            <a href="{{ route('admin.cashbook.shop.history.allocations', ['shop' => $shopKey, 'month' => $prevMonth]) }}"
               class="p-2 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 transition"
               title="Previous Month">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </a>

            <form method="GET" action="{{ route('admin.cashbook.shop.history.allocations', $shopKey) }}" class="flex items-center gap-2">
                <div class="flex items-center gap-1.5">
                    <label class="text-slate-400 uppercase text-[10px] tracking-wider font-mono">Month:</label>
                    <input type="month"
                           name="month"
                           value="{{ $month }}"
                           onchange="this.form.submit()"
                           class="px-3 py-1.5 bg-slate-50 rounded-xl border border-slate-300 font-mono text-slate-900 focus:bg-white focus:outline-none cursor-pointer">
                </div>
            </form>

            <a href="{{ route('admin.cashbook.shop.history.allocations', ['shop' => $shopKey, 'month' => $nextMonth]) }}"
               class="p-2 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 transition"
               title="Next Month">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </a>

            @if(! $isCurrentMonth)
                <a href="{{ route('admin.cashbook.shop.history.allocations', ['shop' => $shopKey, 'month' => now()->format('Y-m')]) }}"
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
            @if($allocateAllProposal['amount_to_allocate'] > 0)
                <button type="button"
                        @click="openAllocateAllModal()"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black shadow-xs transition cursor-pointer">
                    <i data-lucide="sparkles" class="w-4 h-4"></i>
                    <span>Allocate All</span>
                    <span class="px-2 py-0.5 rounded-md bg-indigo-700/60 font-mono text-[11px]">
                        ₹{{ number_format($allocateAllProposal['amount_to_allocate'], 2) }}
                    </span>
                </button>
            @else
                <button type="button"
                        disabled
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 text-slate-400 text-xs font-bold cursor-not-allowed">
                    <i data-lucide="sparkles" class="w-4 h-4"></i>
                    <span>Allocate All</span>
                    <span class="text-[10px] font-mono text-slate-400">(No open pairs)</span>
                </button>
            @endif
        </div>
    </div>

    <!-- 2. TOP SUMMARY (4 CARDS) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <!-- Card 1: RECEIVED PAYMENTS -->
        <div class="p-4 bg-white rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between transition hover:shadow-sm">
            <div>
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="text-[11px] font-black uppercase text-slate-500 tracking-wider">Payments Received</span>
                    <div class="p-1.5 rounded-lg bg-blue-50 text-blue-600 shrink-0">
                        <i data-lucide="arrow-down-left" class="w-4 h-4"></i>
                    </div>
                </div>
                <div class="text-xl font-black text-slate-900 tracking-tight font-mono">
                    ₹{{ number_format($summary['received'], 2) }}
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span>{{ $summary['payments_count'] }} payment {{ Str::plural('record', $summary['payments_count']) }}</span>
                <span class="font-mono font-bold text-slate-700">{{ $currentMonthCarbon->format('M Y') }}</span>
            </div>
        </div>

        <!-- Card 2: TOTAL ALLOCATED -->
        <div class="p-4 bg-emerald-50/40 rounded-2xl border border-emerald-200/90 shadow-xs flex flex-col justify-between transition hover:shadow-sm">
            <div>
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="text-[11px] font-black uppercase text-emerald-800 tracking-wider">Allocated to Expenses</span>
                    <div class="p-1.5 rounded-lg bg-emerald-100 text-emerald-700 shrink-0">
                        <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                    </div>
                </div>
                <div class="text-xl font-black text-emerald-700 tracking-tight font-mono">
                    ₹{{ number_format($summary['allocated'], 2) }}
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-emerald-200/50 flex items-center justify-between text-[11px] text-emerald-700">
                <span>Settled against payables</span>
                <span class="font-mono font-bold">{{ $summary['received'] > 0 ? round(($summary['allocated'] / $summary['received']) * 100, 1) : 0 }}% absorbed</span>
            </div>
        </div>

        <!-- Card 3: UNALLOCATED PAYMENT BALANCE -->
        <div class="p-4 bg-amber-50/40 rounded-2xl border border-amber-200/90 shadow-xs flex flex-col justify-between transition hover:shadow-sm">
            <div>
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="text-[11px] font-black uppercase text-amber-800 tracking-wider">Unallocated Balance</span>
                    <div class="p-1.5 rounded-lg bg-amber-100 text-amber-700 shrink-0">
                        <i data-lucide="wallet" class="w-4 h-4"></i>
                    </div>
                </div>
                <div class="text-xl font-black text-amber-800 tracking-tight font-mono">
                    ₹{{ number_format($summary['unallocated'], 2) }}
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-amber-200/50 flex items-center justify-between text-[11px] text-amber-800">
                <span>Available for allocation</span>
                <span class="font-mono font-bold">{{ $summary['unallocated_payments_count'] }} open</span>
            </div>
        </div>

        <!-- Card 4: OPEN EXPENSES -->
        <div class="p-4 bg-rose-50/40 rounded-2xl border border-rose-200/90 shadow-xs flex flex-col justify-between transition hover:shadow-sm">
            <div>
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="text-[11px] font-black uppercase text-rose-800 tracking-wider">Open Eligible Expenses</span>
                    <div class="p-1.5 rounded-lg bg-rose-100 text-rose-700 shrink-0">
                        <i data-lucide="receipt" class="w-4 h-4"></i>
                    </div>
                </div>
                <div class="text-xl font-black text-rose-700 tracking-tight font-mono">
                    ₹{{ number_format($summary['open_expenses'], 2) }}
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-rose-200/50 flex items-center justify-between text-[11px] text-rose-700">
                <span>Remaining due in period</span>
                <span class="font-mono font-bold">{{ $summary['open_expenses_count'] }} pending</span>
            </div>
        </div>
    </div>

    <!-- 3. VIEW TOGGLE BAR & SEARCH -->
    <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-3">
        <!-- View Tabs (Default to By Expense) -->
        <div class="flex items-center gap-1.5 bg-slate-100 p-1 rounded-2xl">
            <button type="button"
                    @click="viewMode = 'expenses'"
                    :class="viewMode === 'expenses' ? 'bg-white text-slate-900 shadow-xs font-black' : 'text-slate-600 hover:text-slate-900 font-bold'"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs transition cursor-pointer">
                <i data-lucide="layers" class="w-4 h-4"></i>
                <span>By Expense</span>
                <span class="px-2 py-0.5 rounded-md bg-slate-200 text-slate-700 text-[10px] font-mono">
                    {{ count($expenses) }}
                </span>
            </button>

            <button type="button"
                    @click="viewMode = 'payments'"
                    :class="viewMode === 'payments' ? 'bg-white text-slate-900 shadow-xs font-black' : 'text-slate-600 hover:text-slate-900 font-bold'"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs transition cursor-pointer">
                <i data-lucide="credit-card" class="w-4 h-4"></i>
                <span>By Payment</span>
                <span class="px-2 py-0.5 rounded-md bg-slate-200 text-slate-700 text-[10px] font-mono">
                    {{ count($payments) }}
                </span>
            </button>
        </div>

        <!-- Search input -->
        <form method="GET" action="{{ route('admin.cashbook.shop.history.allocations', $shopKey) }}" class="flex items-center gap-2 flex-1 max-w-md">
            <input type="hidden" name="month" value="{{ $month }}">
            <div class="relative w-full">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                <input type="text"
                       name="search"
                       value="{{ $search }}"
                       placeholder="Search reference, notes, category..."
                       class="w-full pl-9 pr-4 py-2 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-900 focus:bg-white focus:outline-none focus:border-slate-400 transition">
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-black shadow-xs transition shrink-0">
                Filter
            </button>
            @if($search)
                <a href="{{ route('admin.cashbook.shop.history.allocations', ['shop' => $shopKey, 'month' => $month]) }}"
                   class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition shrink-0">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- 4. VIEW 1: BY EXPENSE (DEFAULT) -->
    <div x-show="viewMode === 'expenses'" x-cloak class="space-y-4">
        <!-- Desktop Table -->
        <div class="hidden md:block bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-[11px] font-black uppercase text-slate-500 tracking-wider select-none">
                        <!-- Sortable: Date -->
                        <th class="py-3.5 px-4 font-mono cursor-pointer hover:bg-slate-100/80 transition" @click="sortExpenses('business_date')">
                            <div class="flex items-center gap-1.5">
                                <span>Date</span>
                                <span class="text-slate-400" x-html="renderSortIcon('expenses', 'business_date')"></span>
                            </div>
                        </th>

                        <!-- Sortable: Expense Category / Description -->
                        <th class="py-3.5 px-4 cursor-pointer hover:bg-slate-100/80 transition" @click="sortExpenses('category_name')">
                            <div class="flex items-center gap-1.5">
                                <span>Expense Category / Description</span>
                                <span class="text-slate-400" x-html="renderSortIcon('expenses', 'category_name')"></span>
                            </div>
                        </th>

                        <!-- Sortable: Original Amount -->
                        <th class="py-3.5 px-4 text-right cursor-pointer hover:bg-slate-100/80 transition" @click="sortExpenses('amount')">
                            <div class="flex items-center justify-end gap-1.5">
                                <span>Original Amount</span>
                                <span class="text-slate-400" x-html="renderSortIcon('expenses', 'amount')"></span>
                            </div>
                        </th>

                        <!-- Sortable: Allocated -->
                        <th class="py-3.5 px-4 text-right cursor-pointer hover:bg-slate-100/80 transition" @click="sortExpenses('allocated_amount')">
                            <div class="flex items-center justify-end gap-1.5">
                                <span>Allocated</span>
                                <span class="text-slate-400" x-html="renderSortIcon('expenses', 'allocated_amount')"></span>
                            </div>
                        </th>

                        <!-- Sortable: Remaining Due -->
                        <th class="py-3.5 px-4 text-right cursor-pointer hover:bg-slate-100/80 transition" @click="sortExpenses('remaining_due')">
                            <div class="flex items-center justify-end gap-1.5">
                                <span>Remaining Due</span>
                                <span class="text-slate-400" x-html="renderSortIcon('expenses', 'remaining_due')"></span>
                            </div>
                        </th>

                        <!-- Sortable: Status -->
                        <th class="py-3.5 px-4 text-center cursor-pointer hover:bg-slate-100/80 transition" @click="sortExpenses('status')">
                            <div class="flex items-center justify-center gap-1.5">
                                <span>Status</span>
                                <span class="text-slate-400" x-html="renderSortIcon('expenses', 'status')"></span>
                            </div>
                        </th>

                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-bold">
                    <template x-for="e in sortedExpenses" :key="e.id">
                        <tr class="hover:bg-slate-50/60 transition group">
                            <!-- Date -->
                            <td class="py-3.5 px-4 font-mono text-slate-700 whitespace-nowrap" x-text="e.business_date"></td>

                            <!-- Category & Description -->
                            <td class="py-3.5 px-4">
                                <div class="font-black text-slate-900" x-text="e.category_name"></div>
                                <template x-if="e.description && e.description !== e.category_name">
                                    <p class="text-[11px] text-slate-400 font-normal mt-0.5 truncate max-w-xs" x-text="e.description"></p>
                                </template>
                            </td>

                            <!-- Original Amount -->
                            <td class="py-3.5 px-4 text-right font-mono font-black text-slate-900" x-text="'₹' + Number(e.amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>

                            <!-- Allocated Amount -->
                            <td class="py-3.5 px-4 text-right font-mono text-emerald-700" x-text="'₹' + Number(e.allocated_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>

                            <!-- Remaining Due -->
                            <td class="py-3.5 px-4 text-right font-mono"
                                :class="e.remaining_due > 0.005 ? 'text-rose-700 font-black' : 'text-slate-400'"
                                x-text="'₹' + Number(e.remaining_due).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>

                            <!-- Status Badge -->
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <template x-if="e.status === 'PAID'">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Paid
                                    </span>
                                </template>
                                <template x-if="e.status === 'PARTIAL'">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 text-[10px] font-black">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Partial
                                    </span>
                                </template>
                                <template x-if="e.status === 'UNPAID'">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-100 text-rose-800 text-[10px] font-black">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Unpaid
                                    </span>
                                </template>
                            </td>

                            <!-- Actions -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <button type="button"
                                        @click="toggleExpenseExpanded(e.id)"
                                        class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition cursor-pointer"
                                        title="View matched payments">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="sortedExpenses.length === 0">
                        <td colspan="7" class="py-8 text-center text-slate-400 text-xs">
                            No eligible expenses found for this period.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards: By Expense -->
        <div class="md:hidden space-y-3">
            <template x-for="e in sortedExpenses" :key="e.id">
                <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-xs text-slate-500" x-text="e.business_date"></span>
                        <template x-if="e.status === 'PAID'">
                            <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black">Paid</span>
                        </template>
                        <template x-if="e.status === 'PARTIAL'">
                            <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-black">Partial</span>
                        </template>
                        <template x-if="e.status === 'UNPAID'">
                            <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 text-[10px] font-black">Unpaid</span>
                        </template>
                    </div>

                    <div>
                        <div class="text-base font-black text-slate-900" x-text="e.category_name"></div>
                        <div class="text-xs text-slate-600 font-mono mt-0.5" x-text="'Original: ₹' + Number(e.amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 text-xs font-mono">
                        <div>
                            <span class="text-slate-400 text-[10px] block uppercase">Allocated</span>
                            <span class="text-emerald-700 font-bold" x-text="'₹' + Number(e.allocated_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block uppercase">Remaining Due</span>
                            <span class="text-rose-700 font-black" x-text="'₹' + Number(e.remaining_due).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
                        <button type="button"
                                @click="toggleExpenseExpanded(e.id)"
                                class="inline-flex items-center gap-1 text-xs text-slate-600 hover:text-slate-900 font-bold">
                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                            <span x-text="'Contributing Payments (' + (e.allocations ? e.allocations.length : 0) + ')'"></span>
                        </button>
                    </div>

                    <!-- Mobile Expandable Sub-List -->
                    <div x-show="isExpenseExpanded(e.id)" x-cloak class="pt-2 border-t border-slate-100 space-y-2">
                        <template x-for="alloc in (e.allocations || [])" :key="alloc.id">
                            <div class="p-2 bg-slate-50 rounded-xl flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-bold text-slate-800 font-mono block" x-text="alloc.payment_reference"></span>
                                    <span class="text-[10px] text-slate-400 font-mono" x-text="alloc.payment_date"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-black text-emerald-700" x-text="'₹' + Number(alloc.amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                    <button type="button"
                                            @click="confirmReversal(alloc.id, e.category_name, alloc.amount)"
                                            class="p-1 text-rose-600 hover:text-rose-800">
                                        <i data-lucide="undo-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                        <div x-show="!e.allocations || e.allocations.length === 0" class="text-[11px] text-slate-400 italic">
                            No allocations yet.
                        </div>
                    </div>
                </div>
            </template>
            <div x-show="sortedExpenses.length === 0" class="bg-white p-6 rounded-2xl text-center text-slate-400 text-xs">
                No eligible expenses found for this period.
            </div>
        </div>
    </div>

    <!-- 5. VIEW 2: BY PAYMENT -->
    <div x-show="viewMode === 'payments'" x-cloak class="space-y-4">
        <!-- Desktop Table -->
        <div class="hidden md:block bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-[11px] font-black uppercase text-slate-500 tracking-wider select-none">
                        <!-- Sortable: Date -->
                        <th class="py-3.5 px-4 font-mono cursor-pointer hover:bg-slate-100/80 transition" @click="sortPayments('payment_date')">
                            <div class="flex items-center gap-1.5">
                                <span>Date</span>
                                <span class="text-slate-400" x-html="renderSortIcon('payments', 'payment_date')"></span>
                            </div>
                        </th>

                        <!-- Sortable: Payment Reference -->
                        <th class="py-3.5 px-4 cursor-pointer hover:bg-slate-100/80 transition" @click="sortPayments('payment_reference')">
                            <div class="flex items-center gap-1.5">
                                <span>Payment Reference</span>
                                <span class="text-slate-400" x-html="renderSortIcon('payments', 'payment_reference')"></span>
                            </div>
                        </th>

                        <!-- Sortable: Payment Amount -->
                        <th class="py-3.5 px-4 text-right cursor-pointer hover:bg-slate-100/80 transition" @click="sortPayments('amount')">
                            <div class="flex items-center justify-end gap-1.5">
                                <span>Payment Amount</span>
                                <span class="text-slate-400" x-html="renderSortIcon('payments', 'amount')"></span>
                            </div>
                        </th>

                        <!-- Sortable: Allocated -->
                        <th class="py-3.5 px-4 text-right cursor-pointer hover:bg-slate-100/80 transition" @click="sortPayments('allocated_amount')">
                            <div class="flex items-center justify-end gap-1.5">
                                <span>Allocated</span>
                                <span class="text-slate-400" x-html="renderSortIcon('payments', 'allocated_amount')"></span>
                            </div>
                        </th>

                        <!-- Sortable: Available Balance -->
                        <th class="py-3.5 px-4 text-right cursor-pointer hover:bg-slate-100/80 transition" @click="sortPayments('unallocated_amount')">
                            <div class="flex items-center justify-end gap-1.5">
                                <span>Available</span>
                                <span class="text-slate-400" x-html="renderSortIcon('payments', 'unallocated_amount')"></span>
                            </div>
                        </th>

                        <!-- Sortable: Status -->
                        <th class="py-3.5 px-4 text-center cursor-pointer hover:bg-slate-100/80 transition" @click="sortPayments('status')">
                            <div class="flex items-center justify-center gap-1.5">
                                <span>Status</span>
                                <span class="text-slate-400" x-html="renderSortIcon('payments', 'status')"></span>
                            </div>
                        </th>

                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-bold">
                    <template x-for="p in sortedPayments" :key="p.id">
                        <tr class="hover:bg-slate-50/60 transition group">
                            <!-- Date -->
                            <td class="py-3.5 px-4 font-mono text-slate-700 whitespace-nowrap" x-text="p.payment_date"></td>

                            <!-- Payment Reference -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-black text-slate-900" x-text="p.payment_reference"></span>
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 uppercase font-mono text-[10px]" x-text="p.payment_method"></span>
                                </div>
                                <template x-if="p.notes">
                                    <p class="text-[11px] text-slate-400 font-normal mt-0.5 truncate max-w-xs" x-text="p.notes"></p>
                                </template>
                            </td>

                            <!-- Total Payment Amount -->
                            <td class="py-3.5 px-4 text-right font-mono font-black text-slate-900" x-text="'₹' + Number(p.amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>

                            <!-- Allocated Amount -->
                            <td class="py-3.5 px-4 text-right font-mono text-emerald-700" x-text="'₹' + Number(p.allocated_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>

                            <!-- Available Balance -->
                            <td class="py-3.5 px-4 text-right font-mono"
                                :class="p.unallocated_amount > 0.005 ? 'text-amber-700 font-black' : 'text-slate-400'"
                                x-text="'₹' + Number(p.unallocated_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>

                            <!-- Status Badge -->
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <template x-if="p.status === 'PAID'">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Fully Allocated
                                    </span>
                                </template>
                                <template x-if="p.status === 'PARTIAL'">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 text-[10px] font-black">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Partial
                                    </span>
                                </template>
                                <template x-if="p.status === 'UNALLOCATED'">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 text-[10px] font-black">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Unallocated
                                    </span>
                                </template>
                            </td>

                            <!-- Actions -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- View Dropdown Toggle -->
                                    <button type="button"
                                            @click="togglePaymentExpanded(p.id)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition cursor-pointer"
                                            title="View child allocations">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>

                                    <template x-if="p.unallocated_amount > 0.005 && availableOpenSettlements.length > 0">
                                        <div class="flex items-center gap-1.5">
                                            <!-- Manual Allocate Modal Trigger -->
                                            <button type="button"
                                                    @click="openManualModal(p)"
                                                    class="px-2.5 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition cursor-pointer">
                                                Manual
                                            </button>

                                            <!-- Auto Allocate Single Payment -->
                                            <form method="POST" action="{{ route('admin.cashbook.shop.history.payments.allocate-expenses', $shopKey) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="payment_request_id" :value="p.id">
                                                <input type="hidden" name="month" value="{{ $month }}">
                                                <input type="hidden" name="redirect_to" value="allocations">
                                                <button type="submit"
                                                        class="px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition cursor-pointer">
                                                    Auto
                                                </button>
                                            </form>
                                        </div>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="sortedPayments.length === 0">
                        <td colspan="7" class="py-8 text-center text-slate-400 text-xs">
                            No payments found for this period.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile Cards: By Payment -->
        <div class="md:hidden space-y-3">
            <template x-for="p in sortedPayments" :key="p.id">
                <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-xs text-slate-500" x-text="p.payment_date"></span>
                        <template x-if="p.status === 'PAID'">
                            <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black">Fully Allocated</span>
                        </template>
                        <template x-if="p.status === 'PARTIAL'">
                            <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-black">Partial</span>
                        </template>
                        <template x-if="p.status === 'UNALLOCATED'">
                            <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[10px] font-black">Unallocated</span>
                        </template>
                    </div>

                    <div>
                        <div class="text-base font-black text-slate-900 font-mono" x-text="'₹' + Number(p.amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></div>
                        <div class="text-xs text-slate-600 font-mono mt-0.5" x-text="p.payment_reference + ' (' + p.payment_method + ')'"></div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 text-xs font-mono">
                        <div>
                            <span class="text-slate-400 text-[10px] block uppercase">Allocated</span>
                            <span class="text-emerald-700 font-bold" x-text="'₹' + Number(p.allocated_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block uppercase">Available</span>
                            <span class="text-amber-700 font-black" x-text="'₹' + Number(p.unallocated_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
                        <button type="button"
                                @click="togglePaymentExpanded(p.id)"
                                class="inline-flex items-center gap-1 text-xs text-slate-600 hover:text-slate-900 font-bold">
                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                            <span x-text="'Details (' + (p.allocations ? p.allocations.length : 0) + ')'"></span>
                        </button>

                        <div class="flex items-center gap-1.5">
                            <template x-if="p.unallocated_amount > 0.005 && availableOpenSettlements.length > 0">
                                <div class="flex items-center gap-1.5">
                                    <button type="button"
                                            @click="openManualModal(p)"
                                            class="px-2.5 py-1 rounded-lg bg-slate-900 text-white text-xs font-bold">
                                        Manual
                                    </button>
                                    <form method="POST" action="{{ route('admin.cashbook.shop.history.payments.allocate-expenses', $shopKey) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="payment_request_id" :value="p.id">
                                        <input type="hidden" name="month" value="{{ $month }}">
                                        <input type="hidden" name="redirect_to" value="allocations">
                                        <button type="submit" class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white text-xs font-bold">
                                            Auto
                                        </button>
                                    </form>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Mobile Expandable Sub-List -->
                    <div x-show="isPaymentExpanded(p.id)" x-cloak class="pt-2 border-t border-slate-100 space-y-2">
                        <template x-for="alloc in (p.allocations || [])" :key="alloc.id">
                            <div class="p-2 bg-slate-50 rounded-xl flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-bold text-slate-800 block" x-text="alloc.category_name"></span>
                                    <span class="text-[10px] text-slate-400 font-mono" x-text="alloc.transaction_date"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-black text-emerald-700" x-text="'₹' + Number(alloc.amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                    <button type="button"
                                            @click="confirmReversal(alloc.id, alloc.category_name, alloc.amount)"
                                            class="p-1 text-rose-600 hover:text-rose-800">
                                        <i data-lucide="undo-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                        <div x-show="!p.allocations || p.allocations.length === 0" class="text-[11px] text-slate-400 italic">
                            No allocations yet.
                        </div>
                    </div>
                </div>
            </template>
            <div x-show="sortedPayments.length === 0" class="bg-white p-6 rounded-2xl text-center text-slate-400 text-xs">
                No payments found for this period.
            </div>
        </div>
    </div>

    <!-- 6. MODAL: MANUAL ALLOCATION -->
    <div x-show="showManualModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="closeManualModal()"
             class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-2xl w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-150">
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h2 class="text-base font-black text-slate-900 tracking-tight">Manual Expense Allocation</h2>
                    <p class="text-xs text-slate-500 font-mono mt-0.5" x-text="'Payment #' + activePayment.payment_reference + ' (' + activePayment.payment_date + ')'"></p>
                </div>
                <button type="button" @click="closeManualModal()" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 transition">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Payment Summary Card inside Modal -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 grid grid-cols-3 gap-3 text-xs font-mono">
                <div>
                    <span class="text-slate-400 text-[10px] block uppercase">Payment Total</span>
                    <span class="font-black text-slate-900 text-sm" x-text="'₹' + Number(activePayment.amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                </div>
                <div>
                    <span class="text-slate-400 text-[10px] block uppercase">Already Allocated</span>
                    <span class="font-bold text-emerald-700 text-sm" x-text="'₹' + Number(activePayment.allocated_amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                </div>
                <div>
                    <span class="text-slate-400 text-[10px] block uppercase">Available Now</span>
                    <span class="font-black text-amber-700 text-sm" x-text="'₹' + Number(activePayment.unallocated_amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                </div>
            </div>

            <!-- Open Expenses Form List -->
            <form method="POST" action="{{ route('admin.cashbook.shop.allocate-payment', $shopKey) }}" @submit="isSubmittingManual = true" class="space-y-4">
                @csrf
                <input type="hidden" name="payment_request_id" :value="activePayment.id">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="redirect_to" value="allocations">

                <div class="space-y-2">
                    <label class="text-xs font-black uppercase text-slate-500 tracking-wider">Select &amp; Allocate Open Expenses</label>
                    <div class="max-h-64 overflow-y-auto divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white">
                        <template x-for="(exp, index) in openExpensesList" :key="exp.id">
                            <div class="p-3 flex items-center justify-between gap-3 hover:bg-slate-50/70 transition">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-slate-500 text-xs" x-text="exp.business_date"></span>
                                        <span class="font-black text-slate-900 text-xs truncate" x-text="exp.name"></span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                        Due: <strong class="text-rose-600 font-bold" x-text="'₹' + Number(exp.remaining_due).toFixed(2)"></strong>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            @click="fillMaxAllocation(index)"
                                            class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-mono font-bold transition">
                                        Max
                                    </button>
                                    <input type="hidden" :name="'allocations[' + index + '][ledger_transaction_id]'" :value="exp.id">
                                    <div class="relative w-28">
                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 font-mono text-xs">₹</span>
                                        <input type="number"
                                               step="0.01"
                                               min="0"
                                               :max="exp.remaining_due"
                                               :name="'allocations[' + index + '][amount]'"
                                               x-model.number="exp.allocate_amount"
                                               @input="recalculateManualTotals()"
                                               placeholder="0.00"
                                               class="w-full pl-6 pr-2 py-1.5 bg-slate-50 rounded-xl border border-slate-200 text-xs font-mono text-right text-slate-900 focus:bg-white focus:outline-none focus:border-slate-400">
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div x-show="openExpensesList.length === 0" class="p-6 text-center text-slate-400 text-xs">
                            No open eligible expenses found for allocation.
                        </div>
                    </div>
                </div>

                <!-- Live Client-Side Calculation Bar -->
                <div class="p-4 bg-slate-900 text-white rounded-2xl flex flex-wrap items-center justify-between gap-3 text-xs font-mono">
                    <div>
                        <span class="text-slate-400 text-[10px] block uppercase">Selected Total</span>
                        <span class="font-black text-emerald-400 text-sm" x-text="'₹' + Number(manualSelectedTotal).toFixed(2)"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block uppercase">Remaining Payment</span>
                        <span :class="manualRemainingPayment < 0 ? 'text-rose-400 font-black text-sm' : 'text-slate-200 text-sm'"
                              x-text="'₹' + Number(manualRemainingPayment).toFixed(2)"></span>
                    </div>
                </div>

                <!-- Client-Side Warning Alert -->
                <div x-show="manualRemainingPayment < 0" class="p-3 bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold rounded-xl flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                    <span>Allocations exceed the available payment balance by ₹<span x-text="Math.abs(manualRemainingPayment).toFixed(2)"></span>. Please reduce entered amounts.</span>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="closeManualModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                        Cancel
                    </button>
                    <button type="submit"
                            :disabled="manualSelectedTotal <= 0.005 || manualRemainingPayment < 0 || isSubmittingManual"
                            class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 disabled:bg-slate-200 disabled:text-slate-400 text-white text-xs font-black shadow-xs transition cursor-pointer flex items-center gap-2">
                        <span x-show="!isSubmittingManual" x-text="'Allocate ₹' + Number(manualSelectedTotal).toFixed(2)"></span>
                        <span x-show="isSubmittingManual" class="flex items-center gap-1.5">
                            <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Allocating...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 7. MODAL: ALLOCATE ALL PREVIEW & CONFIRMATION -->
    <div x-show="showAllocateAllModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showAllocateAllModal = false"
             class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-indigo-50 text-indigo-600">
                        <i data-lucide="sparkles" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-black text-slate-900 tracking-tight">Auto-Allocate All</h2>
                        <p class="text-xs text-slate-500 font-mono">Oldest Eligible Expense First (FIFO)</p>
                    </div>
                </div>
                <button type="button" @click="showAllocateAllModal = false" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 transition">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Preview Breakdown Table -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-2.5 text-xs font-mono">
                <div class="flex items-center justify-between text-slate-600">
                    <span>Available Payment Balance</span>
                    <strong class="text-slate-900">₹{{ number_format($allocateAllProposal['available_payment_balance'], 2) }}</strong>
                </div>
                <div class="flex items-center justify-between text-slate-600">
                    <span>Open Eligible Expenses</span>
                    <strong class="text-rose-700">₹{{ number_format($allocateAllProposal['open_expenses_total'], 2) }}</strong>
                </div>
                <div class="pt-2 border-t border-slate-200 flex items-center justify-between font-black text-sm">
                    <span class="text-indigo-900 uppercase tracking-wider text-[11px]">Amount to Allocate</span>
                    <strong class="text-indigo-600">₹{{ number_format($allocateAllProposal['amount_to_allocate'], 2) }}</strong>
                </div>
                <div class="flex items-center justify-between text-slate-500 text-[11px]">
                    <span>Remaining Open Expenses</span>
                    <span>₹{{ number_format($allocateAllProposal['remaining_open_expenses'], 2) }}</span>
                </div>
            </div>

            <p class="text-xs text-slate-600 leading-relaxed">
                Allocate <strong>₹{{ number_format($allocateAllProposal['amount_to_allocate'], 2) }}</strong> automatically using FIFO (oldest open expense date first)?
            </p>

            <form method="POST" action="{{ route('admin.cashbook.shop.allocate-payments.bulk', $shopKey) }}" @submit="isSubmittingAllocateAll = true" class="flex items-center justify-end gap-3 pt-2">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="expected_total" value="{{ $allocateAllProposal['amount_to_allocate'] }}">
                <input type="hidden" name="submission_uuid" value="{{ $allocateAllProposal['submission_uuid'] }}">
                <input type="hidden" name="redirect_to" value="allocations">

                <button type="button" @click="showAllocateAllModal = false" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                    Cancel
                </button>
                <button type="submit"
                        :disabled="isSubmittingAllocateAll"
                        class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-300 text-white text-xs font-black shadow-xs transition cursor-pointer flex items-center gap-2">
                    <span x-show="!isSubmittingAllocateAll">Confirm &amp; Allocate</span>
                    <span x-show="isSubmittingAllocateAll" class="flex items-center gap-1.5">
                        <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Allocating...
                    </span>
                </button>
            </form>
        </div>
    </div>

    <!-- 8. MODAL: CONFIRM REVERSAL / UNDO -->
    <div x-show="showReversalModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showReversalModal = false"
             class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-md w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-rose-50 text-rose-600">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                </div>
                <div>
                    <h2 class="text-base font-black text-slate-900 tracking-tight">Undo Allocation?</h2>
                    <p class="text-xs text-slate-500 font-mono mt-0.5">Reversal will safely free the balances</p>
                </div>
            </div>

            <p class="text-xs text-slate-600 leading-relaxed">
                Are you sure you want to reverse the allocation of <strong class="text-slate-900 font-mono font-black" x-text="'₹' + Number(reversalTarget.amount).toFixed(2)"></strong> for <strong class="text-slate-900" x-text="reversalTarget.category"></strong>?
            </p>

            <form method="POST" :action="'{{ url('admin/cashbook/shops/'.$shopKey.'/allocations') }}/' + reversalTarget.id + '/remove'" @submit="isSubmittingReversal = true" class="flex items-center justify-end gap-3 pt-2">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="redirect_to" value="allocations">

                <button type="button" @click="showReversalModal = false" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                    Cancel
                </button>
                <button type="submit"
                        :disabled="isSubmittingReversal"
                        class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 disabled:bg-rose-300 text-white text-xs font-black shadow-xs transition cursor-pointer flex items-center gap-2">
                    <span x-show="!isSubmittingReversal">Confirm Undo</span>
                    <span x-show="isSubmittingReversal" class="flex items-center gap-1.5">
                        <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Reversing...
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function shopExpenseAllocationsControl() {
    return {
        viewMode: 'expenses', // Default tab: By Expense
        expensesSortKey: 'business_date',
        expensesSortDir: 'asc',
        paymentsSortKey: 'payment_date',
        paymentsSortDir: 'asc',
        rawExpenses: @json($expenses),
        rawPayments: @json($payments),
        expandedPayments: {},
        expandedExpenses: {},
        showManualModal: false,
        showAllocateAllModal: false,
        showReversalModal: false,
        isSubmittingManual: false,
        isSubmittingAllocateAll: false,
        isSubmittingReversal: false,
        activePayment: {},
        openExpensesList: [],
        manualSelectedTotal: 0.0,
        manualRemainingPayment: 0.0,
        reversalTarget: { id: null, category: '', amount: 0 },
        availableOpenSettlements: @json($openSettlements),

        init() {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        },

        get sortedExpenses() {
            const list = [...this.rawExpenses];
            const key = this.expensesSortKey;
            const dir = this.expensesSortDir === 'asc' ? 1 : -1;

            return list.sort((a, b) => {
                let valA = a[key] ?? '';
                let valB = b[key] ?? '';

                if (typeof valA === 'number' && typeof valB === 'number') {
                    return (valA - valB) * dir;
                }

                return String(valA).localeCompare(String(valB), undefined, { numeric: true, sensitivity: 'base' }) * dir;
            });
        },

        get sortedPayments() {
            const list = [...this.rawPayments];
            const key = this.paymentsSortKey;
            const dir = this.paymentsSortDir === 'asc' ? 1 : -1;

            return list.sort((a, b) => {
                let valA = a[key] ?? '';
                let valB = b[key] ?? '';

                if (typeof valA === 'number' && typeof valB === 'number') {
                    return (valA - valB) * dir;
                }

                return String(valA).localeCompare(String(valB), undefined, { numeric: true, sensitivity: 'base' }) * dir;
            });
        },

        sortExpenses(key) {
            if (this.expensesSortKey === key) {
                this.expensesSortDir = this.expensesSortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.expensesSortKey = key;
                this.expensesSortDir = 'asc';
            }
            this.$nextTick(() => {
                if (window.lucide) window.lucide.createIcons();
            });
        },

        sortPayments(key) {
            if (this.paymentsSortKey === key) {
                this.paymentsSortDir = this.paymentsSortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.paymentsSortKey = key;
                this.paymentsSortDir = 'asc';
            }
            this.$nextTick(() => {
                if (window.lucide) window.lucide.createIcons();
            });
        },

        renderSortIcon(table, key) {
            const activeKey = table === 'expenses' ? this.expensesSortKey : this.paymentsSortKey;
            const activeDir = table === 'expenses' ? this.expensesSortDir : this.paymentsSortDir;

            if (activeKey !== key) {
                return '<svg class="w-3.5 h-3.5 inline opacity-40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/></svg>';
            }

            if (activeDir === 'asc') {
                return '<svg class="w-3.5 h-3.5 inline text-slate-900 font-bold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m18 15-6-6-6 6"/></svg>';
            }

            return '<svg class="w-3.5 h-3.5 inline text-slate-900 font-bold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>';
        },

        togglePaymentExpanded(id) {
            this.expandedPayments[id] = !this.expandedPayments[id];
            this.$nextTick(() => {
                if (window.lucide) window.lucide.createIcons();
            });
        },

        isPaymentExpanded(id) {
            return !!this.expandedPayments[id];
        },

        toggleExpenseExpanded(id) {
            this.expandedExpenses[id] = !this.expandedExpenses[id];
            this.$nextTick(() => {
                if (window.lucide) window.lucide.createIcons();
            });
        },

        isExpenseExpanded(id) {
            return !!this.expandedExpenses[id];
        },

        openManualModal(payment) {
            this.activePayment = payment;
            this.isSubmittingManual = false;
            this.openExpensesList = this.availableOpenSettlements.map(item => ({
                id: item.id,
                name: item.name || item.entry_type_name || 'Expense',
                business_date: item.business_date,
                original_amount: Number(item.amount || 0),
                remaining_due: Number(item.remaining_due || 0),
                allocate_amount: null,
            }));
            this.recalculateManualTotals();
            this.showManualModal = true;
            this.$nextTick(() => {
                if (window.lucide) window.lucide.createIcons();
            });
        },

        closeManualModal() {
            this.showManualModal = false;
        },

        openAllocateAllModal() {
            this.isSubmittingAllocateAll = false;
            this.showAllocateAllModal = true;
            this.$nextTick(() => {
                if (window.lucide) window.lucide.createIcons();
            });
        },

        fillMaxAllocation(index) {
            const exp = this.openExpensesList[index];
            const currentSelectedWithoutThis = this.openExpensesList.reduce((acc, curr, idx) => {
                return idx === index ? acc : acc + (Number(curr.allocate_amount) || 0);
            }, 0);

            const availableFromPayment = Math.max(0, Number(this.activePayment.unallocated_amount || 0) - currentSelectedWithoutThis);
            const maxAllowable = Math.min(Number(exp.remaining_due || 0), availableFromPayment);

            exp.allocate_amount = maxAllowable > 0 ? Number(maxAllowable.toFixed(2)) : null;
            this.recalculateManualTotals();
        },

        recalculateManualTotals() {
            this.manualSelectedTotal = this.openExpensesList.reduce((acc, curr) => {
                const val = Number(curr.allocate_amount);
                return (!isNaN(val) && val > 0) ? acc + val : acc;
            }, 0);

            this.manualRemainingPayment = Number(this.activePayment.unallocated_amount || 0) - this.manualSelectedTotal;
        },

        confirmReversal(allocId, category, amount) {
            this.reversalTarget = {
                id: allocId,
                category: category,
                amount: amount,
            };
            this.isSubmittingReversal = false;
            this.showReversalModal = true;
            this.$nextTick(() => {
                if (window.lucide) window.lucide.createIcons();
            });
        }
    };
}
</script>
@endsection
