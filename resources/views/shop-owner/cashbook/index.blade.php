@extends('shop-owner.layouts.app')

@section('title', 'Cashbook')
@section('page_title', 'Cashbook Dashboard')
@section('page_description', 'Post daily income/expense entries using the new cashbook rules and review ledger updates.')
@section('page_back_url', route('shop-owner.dashboard'))
@php($breadcrumbs = [['label' => 'Dashboard', 'url' => route('shop-owner.dashboard')], ['label' => 'Cashbook']])

@section('page_actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('shop-owner.cashbook.create', ['date' => $selectedDate->toDateString()]) }}" class="inline-flex h-10 items-center rounded-xl bg-emerald-600 px-4 text-xs font-black uppercase tracking-[0.14em] text-white transition hover:bg-emerald-500">
            Create Entry
        </a>
        <a href="{{ route('shop-owner.cashbook.show', ['date' => $selectedDate->toDateString()]) }}" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black uppercase tracking-[0.14em] text-slate-700 transition hover:bg-slate-50">
            Cashbook
        </a>
        <a href="{{ route('shop-owner.cashbook.reports', ['date' => $selectedDate->toDateString()]) }}" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black uppercase tracking-[0.14em] text-slate-700 transition hover:bg-slate-50">
            Reports
        </a>
        <button type="button" @click="reloadPage()" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-xs transition hover:bg-slate-50 hover:text-slate-900" title="Reload Cashbook Data">
            <svg class="h-4 w-4" :class="{ 'animate-spin': loading }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8" />
                <path d="M21 3v5h-5" />
            </svg>
        </button>
    </div>
@endsection

@section('content')
    <div class="mx-auto w-full max-w-7xl overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm" x-data="cashbookPage()" x-init="init()">
        <header class="flex flex-wrap items-center justify-between gap-3 bg-slate-950 px-4 py-3 text-white sm:px-5">
            <div>
                <p class="text-[10px] font-black uppercase tracking-wider text-emerald-400">Shop Cashbook — {{ $shop->name }}</p>
                <h1 class="text-lg font-black text-white sm:text-xl" x-text="activeTab === 'reports' ? 'Cashbook Reports' : 'Cashbook Dashboard'"></h1>
                <p class="mt-0.5 text-xs font-semibold text-slate-400">Date: {{ $selectedDate->format('d M Y') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="openCreateModal()" class="rounded-lg border border-emerald-500/60 bg-emerald-500 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-400">
                    + Create Entry
                </button>
                <button
                    type="button"
                    @click="activeTab = 'cashbook'"
                    class="rounded-lg border px-3 py-1.5 text-xs font-bold transition"
                    :class="activeTab === 'cashbook' ? 'border-emerald-500 bg-emerald-600 text-white' : 'border-slate-700 bg-slate-900 text-slate-200 hover:bg-slate-800'"
                >
                    Cashbook
                </button>
                <button
                    type="button"
                    @click="activeTab = 'reports'"
                    class="rounded-lg border px-3 py-1.5 text-xs font-bold transition"
                    :class="activeTab === 'reports' ? 'border-emerald-500 bg-emerald-600 text-white' : 'border-slate-700 bg-slate-900 text-slate-200 hover:bg-slate-800'"
                >
                    Reports
                </button>
                <button
                    type="button"
                    @click="reloadPage()"
                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-700 bg-slate-900 text-slate-200 transition hover:bg-slate-800 hover:text-white"
                    title="Reload Cashbook Data"
                >
                    <svg class="h-4 w-4" :class="{ 'animate-spin': loading }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8" />
                        <path d="M21 3v5h-5" />
                    </svg>
                </button>
            </div>
        </header>

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2.5 sm:px-5">
            <!-- Quick Access Timeframe Presets: Today | Yesterday | Week | Month | Custom Range -->
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-[10px] font-black uppercase text-slate-500 mr-1">Timeframe:</span>
                <button
                    type="button"
                    @click="setPreset('today')"
                    class="rounded-lg px-2.5 py-1 text-xs font-extrabold transition"
                    :class="activePreset === 'today' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                >
                    Today
                </button>
                <button
                    type="button"
                    @click="setPreset('yesterday')"
                    class="rounded-lg px-2.5 py-1 text-xs font-extrabold transition"
                    :class="activePreset === 'yesterday' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                >
                    Yesterday
                </button>
                <button
                    type="button"
                    @click="setPreset('weekly')"
                    class="rounded-lg px-2.5 py-1 text-xs font-extrabold transition"
                    :class="activePreset === 'weekly' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                >
                    Week
                </button>
                <button
                    type="button"
                    @click="setPreset('monthly')"
                    class="rounded-lg px-2.5 py-1 text-xs font-extrabold transition"
                    :class="activePreset === 'monthly' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                >
                    Month
                </button>
                <button
                    type="button"
                    @click="setPreset('custom')"
                    class="rounded-lg px-2.5 py-1 text-xs font-extrabold transition"
                    :class="activePreset === 'custom' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                >
                    Custom Range
                </button>
            </div>

            <!-- Date Pickers -->
            <div class="flex items-center gap-2">
                <template x-if="timeframe !== 'custom'">
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-black uppercase text-slate-500">Business Date</span>
                        <input
                            type="date"
                            x-model="selectedDate"
                            @change="startDate = selectedDate; endDate = selectedDate; activePreset = ''; form.business_date = selectedDate; loadData()"
                            class="h-8 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:outline-none"
                        >
                    </div>
                </template>

                <template x-if="timeframe === 'custom'">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[10px] font-black uppercase text-slate-500">From</span>
                        <input
                            type="date"
                            x-model="startDate"
                            class="h-8 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:outline-none"
                        >
                        <span class="text-[10px] font-black uppercase text-slate-500">To</span>
                        <input
                            type="date"
                            x-model="endDate"
                            class="h-8 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:outline-none"
                        >
                        <button
                            type="button"
                            @click="selectedDate = startDate; loadData()"
                            class="h-8 rounded-lg bg-slate-900 px-3 text-xs font-bold text-white transition hover:bg-slate-800"
                        >
                            Apply Range
                        </button>
                    </div>
                </template>

                <button
                    type="button"
                    @click="reloadPage()"
                    class="flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50 hover:text-slate-900"
                    title="Reload Data"
                >
                    <svg class="h-3.5 w-3.5 text-slate-600" :class="{ 'animate-spin': loading }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8" />
                        <path d="M21 3v5h-5" />
                    </svg>
                    <span>Reload</span>
                </button>
            </div>
        </div>

        <section class="grid grid-cols-3 border-b border-slate-200">
            <div class="border-r border-slate-200 px-1 py-2 text-center sm:px-3 cursor-pointer hover:bg-slate-100/70 transition-colors" @click="showCardDetails('sales')" title="Click for details">
                <p class="text-[9px] font-black uppercase text-slate-400 sm:text-[10px]">Total Sales</p>
                <p class="mt-0.5 truncate text-xs font-black tracking-tight text-emerald-700 sm:text-sm whitespace-nowrap" x-text="currency(displaySales())"></p>
            </div>
            <div class="border-r border-slate-200 px-1 py-2 text-center sm:px-3 cursor-pointer hover:bg-slate-100/70 transition-colors" @click="showCardDetails('expense')" title="Click for details">
                <p class="text-[9px] font-black uppercase text-slate-400 sm:text-[10px]">Total Expense</p>
                <p class="mt-0.5 truncate text-xs font-black tracking-tight text-rose-700 sm:text-sm whitespace-nowrap" x-text="currency(displayExpense())"></p>
            </div>
            <div class="px-1 py-2 text-center sm:px-3 cursor-pointer hover:bg-slate-100/70 transition-colors" @click="showCardDetails('closing_balance')" title="Click for details">
                <p class="text-[9px] font-black uppercase text-slate-400 sm:text-[10px]">Closing Balance</p>
                <p class="mt-0.5 truncate text-xs font-black tracking-tight sm:text-sm whitespace-nowrap" :class="displayClosingBalance() >= 0 ? 'text-emerald-700' : 'text-rose-700'" x-text="currency(displayClosingBalance())"></p>
            </div>
        </section>

        <div x-show="activeTab === 'cashbook'">
            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/60 px-4 py-2 sm:px-5">
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-700">Daily Ledger Entries</h2>
                <div class="flex items-center gap-1.5">
                    <button type="button" @click="openCopyYesterday()" :disabled="copyLoading" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 shadow-xs transition hover:bg-slate-50 hover:text-slate-900 disabled:opacity-50">
                        <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <span x-text="copyLoading ? 'Loading...' : 'Copy Yesterday'"></span>
                    </button>
                    <button type="button" @click="openCreateModal()" class="rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-bold text-white transition hover:bg-emerald-500">
                        + Add Entry
                    </button>
                </div>
            </div>

            <div x-show="collectionSummaries.length > 0" class="border-b border-slate-100 bg-cyan-50/60 px-4 py-2 sm:px-5">
                <template x-for="group in collectionSummaries" :key="group.reference_id">
                    <button type="button" @click="showCollectionDetails(group)" class="mb-1 mr-1 inline-flex items-center gap-2 rounded-lg border border-cyan-200 bg-white px-2.5 py-1 text-[11px] font-black text-cyan-800 shadow-sm">
                        <span x-text="group.name"></span>
                        <span class="text-emerald-700" x-text="'+' + currency(group.income)"></span>
                        <span class="text-rose-700" x-text="'-' + currency(group.expense)"></span>
                        <span class="rounded bg-cyan-100 px-1.5 py-0.5 text-cyan-900" x-text="currency(group.net)"></span>
                    </button>
                </template>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-2 py-2 text-center">Status</th>
                            <th class="px-2 py-2">Entry Type</th>
                            <th class="px-3 py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="tx in transactions" :key="tx.id">
                            <tr class="cursor-pointer transition hover:bg-slate-100/70" :class="tx.status === 'approved' ? 'bg-emerald-50/40' : ''" @click="openDetails(tx)">
                                <td class="px-3 py-1.5 font-black" :class="tx.direction === 'income' ? 'text-emerald-700' : 'text-rose-700'">
                                    <div class="flex items-center gap-2">
                                        <span x-text="tx.business_date ? tx.business_date.slice(8, 10) + '-' + tx.business_date.slice(5, 7) : formatDayNumber(tx.business_date)"></span>
                                    </div>
                                </td>
                                <td class="px-2 py-1.5 text-center">
                                    <span class="inline-flex h-5.5 w-5.5 items-center justify-center rounded-full border" :class="tx.status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'" title="Approval Status">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path x-show="tx.status === 'approved'" d="m4.2 10.4 3.2 3.2 8-8" />
                                            <path x-show="tx.status !== 'approved'" d="M10 4v8" />
                                            <path x-show="tx.status !== 'approved'" d="M10 14h.01" />
                                        </svg>
                                    </span>
                                </td>
                                <td class="px-2 py-1.5 font-semibold" :class="tx.direction === 'income' ? 'text-emerald-700' : 'text-rose-700'">
                                    <span x-text="entryTypeName(tx)"></span>
                                </td>
                                <td class="px-3 py-1.5 text-right font-bold text-xs whitespace-nowrap" :class="tx.direction === 'income' ? 'text-emerald-700' : 'text-rose-700'" x-text="currency(tx.amount)"></td>
                            </tr>
                        </template>
                        <tr x-show="transactions.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center text-xs font-semibold text-slate-400">No entries found for this period. Click "+ Add Entry" or "Copy Yesterday" to record transactions.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Inline Quick Entry: Copy from Yesterday --}}
            <div x-show="showCopyYesterday" x-cloak class="border-t border-slate-200 bg-slate-50/80 p-3 sm:p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </span>
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                Quick Fill from <span x-text="yesterdayDateLabel">Yesterday</span>
                            </h3>
                            <p class="text-[10px] font-semibold text-slate-500">Review amounts for today (<span x-text="selectedDate"></span>) and click Save All.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="clearCopyRows()" class="text-xs font-bold text-rose-600 hover:text-rose-800 transition" title="Clear all input amounts">
                            Clear All
                        </button>
                        <span class="text-slate-300">|</span>
                        <button type="button" @click="showCopyYesterday = false" class="text-xs font-bold text-slate-400 hover:text-slate-600">
                            ✕ Cancel
                        </button>
                    </div>
                </div>

                <div x-show="copyError" class="rounded-lg bg-rose-50 border border-rose-200 p-2.5 text-xs font-bold text-rose-700" x-text="copyError"></div>

                <div class="rounded-xl border border-slate-200 bg-white overflow-hidden shadow-xs">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-100/70 text-[9px] font-black uppercase text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="px-3 py-2">Entry Type</th>
                                <th class="px-3 py-2 text-right">Amount (₹)</th>
                                <th class="px-2 py-2 text-center w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="(row, index) in copyRows" :key="index">
                                <tr class="hover:bg-slate-50/50">
                                    <td class="px-3 py-2">
                                        <div class="font-bold" :class="row.direction === 'income' ? 'text-emerald-700' : 'text-rose-700'" x-text="row.name"></div>
                                        <div class="text-[9px] text-slate-400 font-medium" x-text="row.funding_source && row.funding_source !== 'none' ? row.funding_source : 'default'"></div>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <div class="relative inline-block w-32 sm:w-44">
                                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs font-black text-slate-400">₹</span>
                                            <input type="number" step="0.01" min="0.01" x-model="row.amount"
                                                class="w-full rounded-lg border border-slate-200 bg-slate-50/50 py-1 pl-6 pr-2 text-right text-xs font-black text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                                placeholder="0" />
                                        </div>
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="copyRows.splice(index, 1)" class="text-slate-300 hover:text-rose-600 transition" title="Remove row">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="copyRows.length === 0">
                                <td colspan="3" class="px-4 py-6 text-center text-xs font-semibold text-slate-400">No eligible entries found to copy from yesterday.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between pt-1" x-show="copyRows.length > 0">
                    <span class="text-xs font-bold text-slate-500">
                        <span x-text="copyRows.length"></span> entries ready for <strong class="text-slate-800" x-text="selectedDate"></strong>
                    </span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="clearCopyRows()" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition shadow-2xs">
                            Clear All
                        </button>
                        <button type="button" @click="showCopyYesterday = false" class="rounded-lg px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-200/60 transition">
                            Cancel
                        </button>
                        <button type="button" @click="saveCopyRows()" :disabled="copySaving" class="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-emerald-500 transition disabled:opacity-50 inline-flex items-center gap-1.5">
                            <svg x-show="copySaving" class="h-3.5 w-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span x-text="copySaving ? 'Saving...' : 'Save All (' + copyRows.length + ')'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="activeTab === 'reports'" class="p-3 sm:p-4 space-y-3.5">
            <div>
                <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-emerald-600">Admin Cashbook Metrics</p>
                        <h3 class="text-sm font-black text-slate-950">Shop Snapshot Overview</h3>
                    </div>
                    <span class="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-extrabold uppercase text-emerald-700 border border-emerald-200" x-text="timeframeLabel() + ' View'"></span>
                </div>

                <!-- 6 Metric Cards from Admin Cashbook -->
                <div class="mt-2.5 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-7">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-2 space-y-0.5 cursor-pointer hover:border-emerald-400 transition-all" @click="showCardDetails('sales')" title="Click for details">
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider block truncate">Total Sales</span>
                        <div class="text-xs font-black text-emerald-700 truncate whitespace-nowrap" x-text="currency(displaySales())"></div>
                        <span class="text-[9px] text-emerald-600 font-bold block truncate">Gross Inflow</span>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-2 space-y-0.5 cursor-pointer hover:border-rose-400 transition-all" @click="showCardDetails('expense')" title="Click for details">
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider block truncate">Total Expense</span>
                        <div class="text-xs font-black text-rose-700 truncate whitespace-nowrap" x-text="currency(displayExpense())"></div>
                        <span class="text-[9px] text-rose-600 font-bold block truncate">P/L Chargeable</span>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-2 space-y-0.5 cursor-pointer hover:border-emerald-400 transition-all" @click="showCardDetails('net')" title="Click for details">
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider block truncate">Net P/L</span>
                        <div class="text-xs font-black truncate whitespace-nowrap" :class="(displaySales() - displayExpense()) >= 0 ? 'text-emerald-700' : 'text-rose-700'" x-text="currency(displaySales() - displayExpense())"></div>
                        <span class="text-[9px] text-slate-500 font-medium block truncate">Income - Expense</span>
                    </div>

                    <div
                        class="rounded-lg border p-2 space-y-0.5 cursor-pointer transition-all"
                        :class="(snapshot.closing_petty || 0) < 0 ? 'border-rose-300 bg-rose-50 hover:border-rose-400' : 'border-slate-200 bg-slate-50 hover:border-sky-400'"
                        @click="showCardDetails('petty')" title="Click for details"
                    >
                        <span class="text-[9px] font-black uppercase tracking-wider block truncate" :class="(snapshot.closing_petty || 0) < 0 ? 'text-rose-500' : 'text-slate-400'">Petty Float</span>
                        <div class="text-xs font-black truncate whitespace-nowrap" :class="(snapshot.closing_petty || 0) < 0 ? 'text-rose-700' : 'text-sky-700'" x-text="currency(snapshot.closing_petty ?? 0)"></div>
                        <span class="text-[9px] font-bold block truncate" :class="(snapshot.closing_petty || 0) < 0 ? 'text-rose-500' : 'text-sky-600'" x-text="(snapshot.closing_petty || 0) < 0 ? '⚠ Deficit — top-up needed' : 'Petty Float'"></span>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-2 space-y-0.5 cursor-pointer hover:border-amber-400 transition-all" @click="showCardDetails('shop_position')" title="Click for details">
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider block truncate">Shop Position</span>
                        <div class="text-xs font-black text-amber-700 truncate whitespace-nowrap" x-text="currency(displayClosingBalance())"></div>
                        <span class="text-[9px] text-amber-600 font-bold block truncate">Company Payable</span>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-2 space-y-0.5 cursor-pointer hover:border-cyan-400 transition-all" @click="showCardDetails('company_payable')" title="Click for details">
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider block truncate">Payable</span>
                        <div class="text-xs font-black text-cyan-700 truncate whitespace-nowrap" x-text="currency(payableTotal())"></div>
                        <span class="text-[9px] text-cyan-600 font-bold block truncate">Configured Income Rows</span>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-2 space-y-0.5 cursor-pointer hover:border-purple-400 transition-all" @click="showCardDetails('company_pending')" title="Click for details">
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider block truncate">Company Pending</span>
                        <div class="text-xs font-black text-purple-700 truncate whitespace-nowrap" x-text="currency(snapshot.closing_company_pending || 0)"></div>
                        <span class="text-[9px] text-purple-600 font-bold block truncate">Reimbursements</span>
                    </div>
                </div>
            </div>

            <!-- Date Period Summary Cards -->
            <div>
                <div class="flex items-center justify-between border-b border-slate-200 pb-1.5">
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-800">Date Period Summary</h4>
                    <span class="text-[10px] font-semibold text-slate-400">Date: {{ $selectedDate->format('d M Y') }}</span>
                </div>

                <div class="mt-2 grid grid-cols-3 gap-2">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-2 text-center cursor-pointer hover:border-slate-400 transition-all" @click="showCardDetails('net')" title="Click for details">
                        <p class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Today (Daily)</p>
                        <p class="mt-0.5 text-xs font-black truncate whitespace-nowrap" :class="displayClosingBalance() >= 0 ? 'text-emerald-700' : 'text-rose-700'" x-text="currency(displayClosingBalance())"></p>
                        <span class="text-[9px] font-bold text-slate-500 block mt-0.5" x-text="selectedDate"></span>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-2 text-center cursor-pointer hover:border-emerald-400 transition-all" @click="showCardDetails('month_sales')" title="Click for details">
                        <p class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Month Sales</p>
                        <p class="mt-0.5 text-xs font-black text-emerald-700 truncate whitespace-nowrap" x-text="currency(reporting.sales)"></p>
                        <span class="text-[9px] font-bold text-emerald-600 block mt-0.5">Gross Month</span>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-2 text-center cursor-pointer hover:border-slate-400 transition-all" @click="showCardDetails('month_net')" title="Click for details">
                        <p class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Month Net</p>
                        <p class="mt-0.5 text-xs font-black truncate whitespace-nowrap" :class="reporting.net >= 0 ? 'text-emerald-700' : 'text-rose-700'" x-text="currency(reporting.net)"></p>
                        <span class="text-[9px] font-bold text-slate-500 block mt-0.5">Month Net Total</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Entry Modal / Popup -->
        <div
            x-cloak
            x-show="openCreate"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-4 backdrop-blur-xs"
            @keydown.escape.window="openCreate = false"
        >
            <div class="w-full max-w-md overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl" @click.away="openCreate = false">
                <div class="flex items-center justify-between bg-slate-950 px-4 py-2.5 text-white">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-emerald-400">Shop Cashbook</p>
                        <h3 class="text-sm font-black text-white" x-text="editingTxId ? 'Edit Cashbook Entry' : 'Create Cashbook Entry'"></h3>
                    </div>
                    <button type="button" @click="openCreate = false" class="rounded-lg border border-slate-700 bg-slate-900 p-1 text-slate-400 transition hover:bg-slate-800 hover:text-white" aria-label="Close">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                    </button>
                </div>

                <form class="p-4 space-y-3" @submit.prevent="submitEntry()">
                    <div x-show="collectionGroups.length > 0 && !editingTxId" class="grid grid-cols-2 gap-2 rounded-lg border border-slate-200 bg-slate-50 p-1">
                        <button type="button" @click="entryMode = 'normal'" class="h-8 rounded-md text-xs font-black" :class="entryMode === 'normal' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500'">Normal Entry</button>
                        <button type="button" @click="entryMode = 'collection'; selectDefaultCollectionGroup()" class="h-8 rounded-md text-xs font-black" :class="entryMode === 'collection' ? 'bg-white text-cyan-800 shadow-sm' : 'text-slate-500'">Collection</button>
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Business Date</label>
                            <input type="date" x-model="form.business_date" class="mt-1 h-8 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Entry Category</label>
                            <div class="relative mt-1" x-data="{ open: false }">
                                <button
                                    type="button"
                                    @click="open = !open"
                                    @click.away="open = false"
                                    class="flex h-8 w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold capitalize text-slate-900 transition focus:border-emerald-500 focus:bg-white focus:outline-none"
                                >
                                    <span x-text="form.entry_category ? (form.entry_category.charAt(0).toUpperCase() + form.entry_category.slice(1)) : 'Select category'"></span>
                                    <svg class="h-3.5 w-3.5 text-slate-500 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                    </svg>
                                </button>
                                <div
                                    x-show="open"
                                    x-cloak
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    class="absolute left-0 right-0 top-full z-30 mt-1 max-h-48 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-lg shadow-slate-900/10"
                                >
                                    <template x-for="cat in availableCategories()" :key="cat">
                                        <button
                                            type="button"
                                            @click="form.entry_category = cat; onEntryCategoryChange(); open = false"
                                            class="flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-left text-xs font-semibold capitalize transition"
                                            :class="form.entry_category === cat ? 'bg-emerald-50 text-emerald-700 font-bold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'"
                                        >
                                            <span x-text="cat"></span>
                                            <svg x-show="form.entry_category === cat" class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Entry Type</label>
                            <div class="relative mt-1" x-data="{ open: false }">
                                <button
                                    type="button"
                                    @click="open = !open"
                                    @click.away="open = false"
                                    class="flex h-8 w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 transition focus:border-emerald-500 focus:bg-white focus:outline-none"
                                >
                                    <span class="truncate" x-text="selectedEntryTypeName() || 'Select entry type'"></span>
                                    <svg class="h-3.5 w-3.5 shrink-0 text-slate-500 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                    </svg>
                                </button>
                                <div
                                    x-show="open"
                                    x-cloak
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    class="absolute left-0 right-0 top-full z-30 mt-1 max-h-48 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-lg shadow-slate-900/10"
                                >
                                    <template x-for="entryType in filteredEntryTypes()" :key="entryType.code">
                                        <button
                                            type="button"
                                            @click="form.entry_type_code = entryType.code; onEntryTypeChange(); open = false"
                                            class="flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-left text-xs font-semibold transition"
                                            :class="form.entry_type_code === entryType.code ? 'bg-emerald-50 text-emerald-700 font-bold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'"
                                        >
                                            <span class="truncate" x-text="entryType.name"></span>
                                            <svg x-show="form.entry_type_code === entryType.code" class="h-3.5 w-3.5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                        </button>
                                    </template>
                                    <div x-show="filteredEntryTypes().length === 0" class="px-2.5 py-2 text-xs font-semibold text-slate-400">
                                        No rows enabled. Contact admin.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Funding Source</label>
                            <div class="relative mt-1" x-data="{ open: false }">
                                <button
                                    type="button"
                                    @click="open = !open"
                                    @click.away="open = false"
                                    class="flex h-8 w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 transition focus:border-emerald-500 focus:bg-white focus:outline-none"
                                >
                                    <span class="truncate" x-text="fundingSourceLabel(form.funding_source)"></span>
                                    <svg class="h-3.5 w-3.5 shrink-0 text-slate-500 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                    </svg>
                                </button>
                                <div
                                    x-show="open"
                                    x-cloak
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    class="absolute left-0 right-0 top-full z-30 mt-1 max-h-48 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-lg shadow-slate-900/10"
                                >
                                    <template x-for="item in fundingOptions" :key="item.value">
                                        <button
                                            type="button"
                                            @click="form.funding_source = item.value; open = false"
                                            class="flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-left text-xs font-semibold transition"
                                            :class="form.funding_source === item.value ? 'bg-emerald-50 text-emerald-700 font-bold' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'"
                                        >
                                            <span x-text="item.label"></span>
                                            <svg x-show="form.funding_source === item.value" class="h-3.5 w-3.5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="entryMode === 'normal'">
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Amount (₹)</label>
                        <input type="number" step="0.01" min="0.01" x-model="form.amount" placeholder="0.00" class="mt-1 h-8.5 w-full rounded-lg border border-emerald-300 bg-white px-3 text-sm font-black text-slate-950 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" :required="entryMode === 'normal'">
                    </div>

                    <div x-show="entryMode === 'collection'" class="space-y-2 rounded-lg border border-cyan-200 bg-cyan-50/70 p-3">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-cyan-700">Collection Type</label>
                            <select x-model="form.collection_group_id" @change="resetCollectionAmounts()" class="mt-1 h-8 w-full rounded-lg border border-cyan-200 bg-white px-2 text-xs font-bold text-slate-900">
                                <template x-for="group in collectionGroups" :key="group.id">
                                    <option :value="group.id" x-text="group.name"></option>
                                </template>
                            </select>
                        </div>
                        <template x-for="line in selectedCollectionLines()" :key="line.entry_type_id">
                            <div class="grid grid-cols-[1fr_8rem] items-center gap-2">
                                <label class="text-xs font-bold" :class="line.role === 'income' ? 'text-emerald-700' : 'text-rose-700'" x-text="line.entry_type.name"></label>
                                <input type="number" step="0.01" min="0" x-model="form.collection_amounts[line.entry_type_id]" class="h-8 rounded-lg border border-cyan-200 bg-white px-2 text-right text-xs font-black text-slate-950">
                            </div>
                        </template>
                        <div class="flex items-center justify-between border-t border-cyan-200 pt-2">
                            <span class="text-[10px] font-black uppercase tracking-wider text-cyan-700">Net Collection</span>
                            <strong class="text-sm font-black text-cyan-900" x-text="currency(collectionNet())"></strong>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Notes</label>
                        <textarea x-model="form.notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:bg-white focus:outline-none" placeholder="Optional memo"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="openCreate = false" class="h-8 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="h-8 rounded-lg bg-emerald-600 px-4 text-xs font-bold text-white transition hover:bg-emerald-500" :disabled="submitting">
                            <span x-show="!submitting" x-text="editingTxId ? 'Update Entry' : 'Save Entry'"></span>
                            <span x-show="submitting">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Transaction Details Modal / Popup -->
        <div
            x-cloak
            x-show="openDetailsModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-4 backdrop-blur-xs"
            @keydown.escape.window="openDetailsModal = false"
        >
            <div class="w-full max-w-sm overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl" @click.away="openDetailsModal = false">
                <div class="flex items-center justify-between bg-slate-950 px-4 py-2.5 text-white">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-emerald-400">Transaction Details</p>
                        <h3 class="text-sm font-black text-white" x-text="selectedTx?.entry_type ? selectedTx.entry_type.name : selectedTx?.entry_type_code"></h3>
                    </div>
                    <button type="button" @click="openDetailsModal = false" class="rounded-lg border border-slate-700 bg-slate-900 p-1 text-slate-400 transition hover:bg-slate-800 hover:text-white" aria-label="Close">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-4 space-y-2.5 text-xs">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                        <span class="text-[10px] font-black uppercase text-slate-400">Business Date</span>
                        <span class="font-bold text-slate-900" x-text="selectedTx?.business_date"></span>
                    </div>
                    <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                        <span class="text-[10px] font-black uppercase text-slate-400">Entry Type</span>
                        <span class="font-bold text-slate-900" x-text="selectedTx?.entry_type ? selectedTx.entry_type.name : selectedTx?.entry_type_code"></span>
                    </div>
                    <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                        <span class="text-[10px] font-black uppercase text-slate-400">Category / Direction</span>
                        <span class="font-bold uppercase text-xs" :class="selectedTx?.direction === 'income' ? 'text-emerald-700' : 'text-rose-700'" x-text="selectedTx?.direction || selectedTx?.entry_type?.category || 'entry'"></span>
                    </div>
                    <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                        <span class="text-[10px] font-black uppercase text-slate-400">Funding Source</span>
                        <span class="font-bold text-slate-800 capitalize" x-text="fundingSourceLabel(selectedTx?.funding_source)"></span>
                    </div>
                    <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                        <span class="text-[10px] font-black uppercase text-slate-400">Approval Status</span>
                        <template x-if="selectedTx && (selectedTx.reference_type === 'App\\Models\\ShopInvoice' || selectedTx.reference_type === 'ShopInvoice')">
                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-slate-600">
                                Read-Only (Auto Invoice Bill)
                            </span>
                        </template>
                        <template x-if="!selectedTx || !(selectedTx.reference_type === 'App\\Models\\ShopInvoice' || selectedTx.reference_type === 'ShopInvoice')">
                            <span
                                class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider"
                                :class="selectedTx?.status === 'approved'
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    : (selectedTx?.status === 'void' ? 'border-slate-200 bg-slate-100 text-slate-500' : 'border-amber-200 bg-amber-50 text-amber-700')"
                                x-text="selectedTx?.status === 'approved' ? 'Approved' : (selectedTx?.status_label || selectedTx?.status || 'Posted')"
                            ></span>
                        </template>
                    </div>
                    <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                        <span class="text-[10px] font-black uppercase text-slate-400">Amount</span>
                        <span class="font-black text-sm text-slate-950" x-text="currency(selectedTx?.amount)"></span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-black uppercase text-slate-400 mb-1">Notes / Memo</span>
                        <p class="rounded-lg border border-slate-200 bg-slate-50 p-2.5 text-xs text-slate-800 font-medium break-words leading-relaxed" x-text="selectedTx?.notes || 'No notes provided.'"></p>
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-2 p-3 border-t border-slate-100 bg-slate-50">
                    <template x-if="selectedTx && selectedTx.reference_type === 'collection_group' && selectedTx.reference_id && canMutate(selectedTx)">
                        <button
                            type="button"
                            @click="submitDeleteCollection(selectedTx.reference_id)"
                            class="h-8 px-3 text-xs font-bold rounded-lg border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 transition"
                        >
                            Delete Collection
                        </button>
                    </template>
                    <button
                        type="button"
                        x-show="selectedTx && canMutate(selectedTx)"
                        @click="openEdit(selectedTx)"
                        class="h-8 px-4 text-xs font-bold rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition"
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        x-show="selectedTx && canMutate(selectedTx)"
                        @click="deleteEntry(selectedTx)"
                        class="h-8 px-4 text-xs font-bold rounded-lg border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 transition"
                    >
                        Delete
                    </button>
                    <button type="button" @click="openDetailsModal = false" class="h-8 px-4 text-xs font-bold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 transition">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Metric Card Details Popup / Modal -->
        <div
            x-cloak
            x-show="openCardModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-4 backdrop-blur-xs"
            @keydown.escape.window="openCardModal = false"
        >
            <div class="w-full max-w-md overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl" @click.away="openCardModal = false">
                <div class="flex items-center justify-between bg-slate-950 px-4 py-2.5 text-white">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-emerald-400">Metric Card Details</p>
                        <h3 class="text-sm font-black text-white" x-text="cardModalData.title"></h3>
                    </div>
                    <button type="button" @click="openCardModal = false" class="rounded-lg border border-slate-700 bg-slate-900 p-1 text-slate-400 transition hover:bg-slate-800 hover:text-white" aria-label="Close">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-4 space-y-3 text-xs">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-center">
                        <p class="text-[10px] font-black uppercase text-slate-400" x-text="cardModalData.subtitle"></p>
                        <p class="mt-1 text-lg font-black" :class="toneClass(cardModalData.tone)" x-text="cardModalData.value"></p>
                        <p class="mt-1 text-[11px] font-semibold text-slate-500" x-text="cardModalData.description"></p>
                    </div>

                    <template x-if="getModalBreakdown() && getModalBreakdown().length > 0">
                        <div>
                            <div class="flex items-center justify-between mb-1.5" x-show="cardModalData.rawBreakdown && cardModalData.rawBreakdown.length > 0">
                                <h4 class="text-[10px] font-black uppercase tracking-wider text-slate-500">Itemized Transactions</h4>
                                <label class="flex items-center gap-1.5 text-[10px] font-bold text-slate-600 cursor-pointer">
                                    <input type="checkbox" x-model="showTotalsOnly" class="rounded text-emerald-600 focus:ring-emerald-500 h-3.5 w-3.5">
                                    <span>Totals only</span>
                                </label>
                            </div>
                            <div class="max-h-48 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
                                <template x-for="(item, idx) in getModalBreakdown()" :key="idx">
                                    <div class="flex items-center justify-between p-2 hover:bg-slate-50">
                                        <div>
                                            <p class="font-bold text-slate-900" x-text="item.name"></p>
                                            <p class="text-[10px] font-semibold text-slate-400"><span x-text="formatDayNumber(item.date)"></span> · <span class="capitalize" x-text="item.source"></span></p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-xs text-slate-950" x-text="currency(item.amount)"></span>
                                            <template x-if="item.tx && canMutate(item.tx)">
                                                <div class="flex items-center gap-1">
                                                    <button type="button" @click="openEdit(item.tx); openCardModal = false" class="px-2 py-0.5 text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded hover:bg-emerald-100">Edit</button>
                                                    <button type="button" @click="openDelete(item.tx); openCardModal = false" class="px-2 py-0.5 text-[10px] font-bold text-rose-700 bg-rose-50 border border-rose-200 rounded hover:bg-rose-100">Delete</button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex justify-between items-center p-3 border-t border-slate-100 bg-slate-50">
                    <template x-if="cardModalData.isCollection && cardModalData.reference_id">
                        <button type="button" @click="submitDeleteCollection(cardModalData.reference_id)" class="h-8 px-3 text-xs font-bold rounded-lg border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 transition">
                            Delete Collection
                        </button>
                    </template>
                    <button type="button" @click="openCardModal = false" class="h-8 px-4 text-xs font-bold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 transition ml-auto">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <div
            x-cloak
            x-show="openDeleteModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-3 sm:p-4 backdrop-blur-xs"
            @keydown.escape.window="openDeleteModal = false"
        >
            <div class="w-full max-w-md overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl" @click.away="openDeleteModal = false">
                <div class="flex items-center justify-between bg-slate-950 px-4 py-2.5 text-white">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-rose-400">Delete Entry</p>
                        <h3 class="text-sm font-black text-white">Confirm removal</h3>
                    </div>
                    <button type="button" @click="openDeleteModal = false" class="rounded-lg border border-slate-700 bg-slate-900 p-1 text-slate-400 transition hover:bg-slate-800 hover:text-white" aria-label="Close">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-4 space-y-3">
                    <p class="text-sm font-semibold text-slate-700">Delete this entry?</p>
                    <p class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600" x-text="deletingTx ? (deletingTx.entry_type ? deletingTx.entry_type.name : deletingTx.entry_type_code) + ' · ₹' + parseFloat(deletingTx.amount || 0).toFixed(2) : ''"></p>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="openDeleteModal = false" class="h-8 px-4 text-xs font-bold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 transition">
                            Cancel
                        </button>
                        <button type="button" @click="submitDelete()" class="h-8 px-4 text-xs font-bold rounded-lg border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 transition">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
        function cashbookPage() {
            return {
                selectedDate: '{{ $selectedDate->toDateString() }}',
                activeTab: '{{ $activeTab }}',
                openCreate: {{ $openModal ? 'true' : 'false' }},
                openDetailsModal: false,
                openDeleteModal: false,
                openCardModal: false,
                selectedTx: null,
                deletingTx: null,
                editingTxId: null,
                cardModalData: {
                    title: '',
                    subtitle: '',
                    value: '',
                    tone: 'emerald',
                    description: '',
                    breakdown: []
                },
                submitting: false,
                loading: false,
                showTotalsOnly: false,
                showCopyYesterday: false,
                copyRows: [],
                copyLoading: false,
                copySaving: false,
                copyError: '',
                yesterdayDateLabel: '',
                timeframe: '{{ $timeframe ?? request('timeframe', 'daily') }}',
                activePreset: '{{ ($timeframe ?? request('timeframe')) === 'weekly' ? 'weekly' : (($timeframe ?? request('timeframe')) === 'monthly' ? 'monthly' : (($timeframe ?? request('timeframe')) === 'custom' ? 'custom' : ($selectedDate->toDateString() === today()->subDay()->toDateString() ? 'yesterday' : 'today'))) }}',
                startDate: '{{ $startDate ?? request('start_date', $selectedDate->toDateString()) }}',
                endDate: '{{ $endDate ?? request('end_date', $selectedDate->toDateString()) }}',
                transactions: [],
                companyPendingEntries: [],
                collectionGroups: @json($collectionGroups ?? []),
                collectionSummaries: [],
                snapshot: @json($snapshot),
                entryTypes: @json($entryTypes),
                settings: @json($settings),
                defaultEntryCategory: @json($entryTypes->first()?->category ?? ''),
                defaultEntryTypeCode: @json($entryTypes->first()?->code ?? ''),
                form: {
                    business_date: '{{ $selectedDate->toDateString() }}',
                    entry_category: '{{ $entryTypes->first()?->category ?? '' }}',
                    entry_type_code: '{{ $entryTypes->first()?->code ?? '' }}',
                    amount: '',
                    funding_source: 'none',
                    notes: '',
                    collection_group_id: '',
                    collection_amounts: {},
                },
                entryMode: 'normal',
                fundingOptions: [
                    { value: 'none', label: 'Default' },
                    { value: 'sales', label: 'Sales' },
                    { value: 'petty', label: 'Petty' },
                    { value: 'company', label: 'Company' },
                    { value: 'bank', label: 'Bank / Transfer' },
                    { value: 'external', label: 'External Transfer' },
                    { value: 'company_later', label: 'Company Later' },
                ],
                reporting: {
                    sales: 0,
                    expense: 0,
                    net: 0,
                },

                init() {
                    this.ensureValidEntrySelection();
                    this.onEntryTypeChange();
                    this.loadData();
                },

                displaySales() {
                    const txSales = this.transactions
                        .filter((tx) => tx.direction === 'income' || (tx.entry_type && tx.entry_type.category === 'income'))
                        .reduce((sum, tx) => sum + parseFloat(tx.amount || 0), 0);
                    if (txSales > 0 || this.transactions.length > 0) {
                        return txSales;
                    }
                    return parseFloat(this.snapshot?.total_sales || 0);
                },

                displayExpense() {
                    const txExpense = this.transactions
                        .filter((tx) => tx.direction === 'expense' || (tx.entry_type && tx.entry_type.category === 'expense'))
                        .reduce((sum, tx) => sum + parseFloat(tx.amount || 0), 0);
                    if (txExpense > 0 || this.transactions.length > 0) {
                        return txExpense;
                    }
                    return parseFloat(this.snapshot?.total_expense || 0);
                },

                displayClosingBalance() {
                    const sales = parseFloat(this.displaySales() || 0);
                    const expense = parseFloat(this.displayExpense() || 0);
                    if (sales > 0 || expense > 0) {
                        return sales - expense;
                    }
                    if (this.snapshot && typeof this.snapshot.closing_shop_position !== 'undefined' && parseFloat(this.snapshot.closing_shop_position) !== 0) {
                        return parseFloat(this.snapshot.closing_shop_position);
                    }
                    return sales - expense;
                },

                payableRowCodes() {
                    return this.settings
                        .filter((setting) => setting.include_in_payable && setting.entry_type)
                        .map((setting) => setting.entry_type.code);
                },

                payableTransactions() {
                    const codes = new Set(this.payableRowCodes());
                    return this.transactions.filter((tx) => codes.has(tx.entry_type?.code || tx.entry_type_code) || tx.reference_type === 'collection_group');
                },

                payableTotal() {
                    return this.payableTransactions().reduce((sum, tx) => {
                        const code = tx.entry_type?.code || tx.entry_type_code;
                        const dir = tx.direction || tx.entry_type?.category || 'income';
                        const setting = this.settings.find((s) => s.entry_type_id === tx.entry_type_id || s.entry_type?.code === code);
                        const isDeduction = setting?.payable_direction
                            ? setting.payable_direction === 'minus'
                            : (dir === 'expense' || ['company_to_petty', 'company_paid_shop', 'company_paid_vendor'].includes(code));
                        return sum + (isDeduction ? -parseFloat(tx.amount || 0) : parseFloat(tx.amount || 0));
                    }, 0);
                },

                companyPayablesLedger() {
                    if (!this.companyPendingEntries || !Array.isArray(this.companyPendingEntries) || this.companyPendingEntries.length === 0) {
                        return [];
                    }

                    const dateGroups = {};
                    this.companyPendingEntries.forEach(tx => {
                        const d = tx.business_date || 'Unknown';
                        if (!dateGroups[d]) {
                            dateGroups[d] = {
                                date: d,
                                items: [],
                                out_amount: 0,
                                in_amount: 0,
                            };
                        }

                        const amt = parseFloat(tx.amount || 0);
                        const delta = parseFloat(tx.company_pending_delta || 0);
                        const code = tx.entry_type ? (tx.entry_type.code || '') : (tx.entry_type_code || '');
                        const category = tx.entry_type ? (tx.entry_type.category || '') : '';
                        const direction = tx.direction || category || 'expense';

                        const setting = (this.settings || []).find(s => s.entry_type_id === tx.entry_type_id || (s.entry_type && s.entry_type.code === code));
                        const payableDir = setting ? setting.payable_direction : null;

                        let isOut = false;
                        let isIn = false;

                        if (payableDir === 'add') {
                            isOut = true;
                        } else if (payableDir === 'minus') {
                            isIn = true;
                        } else if (delta > 0) {
                            isOut = true;
                        } else if (delta < 0) {
                            isIn = true;
                        } else if (direction === 'expense' || category === 'expense' || tx.funding_source === 'company' || code === 'vehicle') {
                            isOut = true;
                        } else if (direction === 'income' || category === 'settlement' || code.includes('paid_shop') || code.includes('reimburse')) {
                            isIn = true;
                        } else {
                            isOut = true;
                        }

                        const itemOut = isOut ? amt : 0;
                        const itemIn = isIn ? amt : 0;

                        dateGroups[d].items.push({
                            ...tx,
                            item_out: itemOut,
                            item_in: itemIn,
                            is_out: isOut,
                            is_in: isIn,
                            type_name: tx.entry_type ? tx.entry_type.name : (tx.entry_type_code || 'Expense'),
                        });

                        dateGroups[d].out_amount += itemOut;
                        dateGroups[d].in_amount += itemIn;
                    });

                    const sortedDates = Object.keys(dateGroups).sort((a, b) => a.localeCompare(b));
                    let runningBalance = 0;
                    const ledgerRows = [];

                    sortedDates.forEach(dateStr => {
                        const g = dateGroups[dateStr];
                        runningBalance = runningBalance + g.out_amount - g.in_amount;

                        ledgerRows.push({
                            date: dateStr,
                            items: g.items,
                            count: g.items.length,
                            out_amount: g.out_amount,
                            in_amount: g.in_amount,
                            net_change: g.out_amount - g.in_amount,
                            running_balance: runningBalance,
                        });
                    });

                    return ledgerRows;
                },

                companyPayablesTotalOut() {
                    return this.companyPayablesLedger().reduce((sum, r) => sum + r.out_amount, 0);
                },

                companyPayablesTotalIn() {
                    return this.companyPayablesLedger().reduce((sum, r) => sum + r.in_amount, 0);
                },

                companyPayablesFinalBalance() {
                    return this.companyPayablesTotalOut() - this.companyPayablesTotalIn();
                },

                toneClass(tone) {
                    return {
                        rose: 'text-rose-700',
                        emerald: 'text-emerald-700',
                        cyan: 'text-cyan-700',
                    }[tone] || 'text-slate-900';
                },

                formatDayNumber(dateStr) {
                    if (!dateStr) return '-';
                    const parts = dateStr.split('-');
                    return parts.length === 3 ? parts[2] : dateStr;
                },

                openDetails(tx) {
                    this.selectedTx = tx;
                    this.openDetailsModal = true;
                },

                openCreateModal(category = null) {
                    this.editingTxId = null;
                    this.entryMode = 'normal';
                    this.form = {
                        business_date: this.selectedDate || '{{ $selectedDate->toDateString() }}',
                        entry_category: category || this.form.entry_category || this.defaultEntryCategory,
                        entry_type_code: this.form.entry_type_code || this.defaultEntryTypeCode,
                        amount: '',
                        funding_source: 'none',
                        notes: '',
                        collection_group_id: '',
                        collection_amounts: {},
                    };
                    if (category) {
                        this.form.entry_category = category;
                    }
                    this.ensureValidEntrySelection();
                    this.onEntryTypeChange();
                    this.openCreate = true;
                },

                openEdit(tx) {
                    this.editingTxId = tx.id;
                    this.form = {
                        business_date: tx.business_date,
                        entry_category: tx.entry_type?.category || this.form.entry_category,
                        entry_type_code: tx.entry_type?.code || tx.entry_type_code || '',
                        amount: tx.amount,
                        funding_source: tx.funding_source || 'none',
                        notes: tx.notes || '',
                    };
                    this.onEntryTypeChange();
                    this.openCreate = true;
                    this.openDetailsModal = false;
                },

                openDelete(tx) {
                    this.deletingTx = tx;
                    this.openDeleteModal = true;
                    this.openDetailsModal = false;
                },

                closeCreateModal() {
                    this.openCreate = false;
                    this.editingTxId = null;
                    this.entryMode = 'normal';
                    this.form = {
                        business_date: this.selectedDate,
                        entry_category: this.defaultEntryCategory,
                        entry_type_code: this.defaultEntryTypeCode,
                        amount: '',
                        funding_source: 'none',
                        notes: '',
                        collection_group_id: '',
                        collection_amounts: {},
                    };
                    this.onEntryTypeChange();
                },

                canMutate(tx) {
                    if (!tx) return false;
                    if (
                        tx.reference_type === 'App\\Models\\ShopInvoice' ||
                        tx.reference_type === 'ShopInvoice'
                    ) {
                        return false;
                    }
                    return tx.status !== 'approved' && tx.status !== 'void';
                },

                showCardDetails(cardType) {
                    this.showTotalsOnly = (this.timeframe !== 'daily');
                    this.cardModalData = {
                        title: '',
                        subtitle: '',
                        value: '',
                        tone: 'emerald',
                        description: '',
                        rawBreakdown: [],
                        staticBreakdown: null,
                        breakdown: []
                    };

                    if (cardType === 'sales') {
                        const items = this.transactions.filter(tx => tx.direction === 'income' || (tx.entry_type && tx.entry_type.category === 'income'));
                        this.cardModalData = {
                            title: 'Total Sales / Gross Inflow',
                            subtitle: 'Gross revenue and income entries recorded for this timeframe.',
                            value: this.currency(this.displaySales()),
                            tone: 'emerald',
                            description: `Total sales for ${this.timeframe} view as of ${this.selectedDate}.`,
                            rawBreakdown: items,
                            breakdown: items
                        };
                    } else if (cardType === 'expense') {
                        const items = this.transactions.filter(tx => tx.direction === 'expense' || (tx.entry_type && tx.entry_type.category === 'expense'));
                        this.cardModalData = {
                            title: 'Total Expense / P/L Chargeable',
                            subtitle: 'Direct expenses, supplier bills, and operating costs recorded for this period.',
                            value: this.currency(this.displayExpense()),
                            tone: 'rose',
                            description: `Total expenses for ${this.timeframe} view as of ${this.selectedDate}.`,
                            rawBreakdown: items,
                            breakdown: items
                        };
                    } else if (cardType === 'closing_balance' || cardType === 'net') {
                        const net = this.displayClosingBalance();
                        const staticItems = [
                            { date: this.selectedDate, name: 'Gross Sales / Income', source: 'Total Inflow', amount: this.displaySales(), notes: 'Sales and income entries' },
                            { date: this.selectedDate, name: 'Total Expense / Debit', source: 'Total Outflow', amount: this.displayExpense(), notes: 'Operating & bill expenses' },
                        ];
                        this.cardModalData = {
                            title: 'Closing Balance / Net Position',
                            subtitle: 'Net position calculated as (Total Sales − Total Expenses).',
                            value: this.currency(net),
                            tone: net >= 0 ? 'emerald' : 'rose',
                            description: `Net summary position for ${this.timeframe} view.`,
                            staticBreakdown: staticItems,
                            breakdown: staticItems
                        };
                    } else if (cardType === 'petty') {
                        this.cardModalData = {
                            title: 'Closing Petty Float',
                            subtitle: 'Cash float held at shop for petty expenses.',
                            value: this.currency(this.snapshot?.closing_petty || 0),
                            tone: 'emerald',
                            description: `Opening: ${this.currency(this.snapshot?.opening_petty || 0)} | In: ${this.currency(this.snapshot?.petty_in || 0)} | Out: ${this.currency(this.snapshot?.petty_out || 0)}`,
                            breakdown: []
                        };
                    } else if (cardType === 'shop_position') {
                        const pos = this.displayClosingBalance();
                        this.cardModalData = {
                            title: 'Shop Position (Company Payable)',
                            subtitle: 'Net accumulated shop balance payable to company.',
                            value: this.currency(pos),
                            tone: pos >= 0 ? 'emerald' : 'rose',
                            description: `Net balance generated by shop sales and expenses.`,
                            breakdown: []
                        };
                    } else if (cardType === 'company_payable') {
                        const rows = this.payableTransactions();
                        const total = this.payableTotal();
                        this.cardModalData = {
                            title: 'Payable to Company',
                            subtitle: 'Only the rows enabled in shop settings are counted.',
                            value: this.currency(total),
                            tone: 'cyan',
                            description: `Configured payable rows for ${this.selectedDate}.`,
                            rawBreakdown: rows,
                            breakdown: rows
                        };
                    } else if (cardType === 'company_pending') {
                        const ledger = this.companyPayablesLedger();
                        const finalBalance = this.companyPayablesFinalBalance();
                        const totalOut = this.companyPayablesTotalOut();
                        const totalIn = this.companyPayablesTotalIn();
                        const val = (finalBalance !== 0 || ledger.length > 0) ? finalBalance : (parseFloat(this.snapshot?.closing_company_pending || 0));

                        const breakdownItems = [];
                        ledger.forEach(group => {
                            group.items.forEach(item => {
                                breakdownItems.push({
                                    date: item.business_date,
                                    entry_type_name: item.type_name,
                                    amount: item.amount,
                                    is_out: item.is_out,
                                    status: item.company_payable_status ? item.company_payable_status.replace('_', ' ') : null,
                                    notes: item.notes || (item.is_out ? 'Expense Owed by Company' : 'Reimbursement Paid'),
                                });
                            });
                        });

                        this.cardModalData = {
                            title: 'Company Pending Reimbursements',
                            subtitle: 'Vehicle expenses, company-funded claims, and reimbursements.',
                            value: this.currency(val),
                            tone: val >= 0 ? 'emerald' : 'rose',
                            description: `Total Out: ${this.currency(totalOut)} | Total In: ${this.currency(totalIn)}`,
                            rawBreakdown: breakdownItems,
                            breakdown: breakdownItems
                        };
                    } else if (cardType === 'month_sales') {
                        this.cardModalData = {
                            title: 'Month Gross Sales',
                            subtitle: 'Accumulated gross sales for the current month.',
                            value: this.currency(this.reporting.sales),
                            tone: 'emerald',
                            description: `Monthly total sales accumulated from daily receipts.`,
                            breakdown: []
                        };
                    } else if (cardType === 'month_net') {
                        this.cardModalData = {
                            title: 'Month Net Position',
                            subtitle: 'Monthly net result (Month Sales − Month Expenses).',
                            value: this.currency(this.reporting.net),
                            tone: this.reporting.net >= 0 ? 'emerald' : 'rose',
                            description: `Monthly net position snapshot.`,
                            breakdown: []
                        };
                    }

                    this.openCardModal = true;
                },

                getModalBreakdown() {
                    if (this.cardModalData.staticBreakdown) {
                        return this.cardModalData.staticBreakdown;
                    }
                    const list = this.cardModalData.rawBreakdown || [];
                    if (this.showTotalsOnly) {
                        const grouped = {};
                        list.forEach(tx => {
                            const entryTypeName = tx.entry_type ? tx.entry_type.name : (tx.entry_type_code || 'Other');
                            const source = tx.funding_source || 'default';
                            const key = entryTypeName + '||' + source + '||' + (tx.direction || 'expense');
                            if (!grouped[key]) {
                                grouped[key] = {
                                    min_date: tx.business_date,
                                    max_date: tx.business_date,
                                    name: entryTypeName,
                                    source: source,
                                    amount: 0,
                                    notes: 'Grouped totals'
                                };
                            }
                            grouped[key].amount += parseFloat(tx.amount) || 0;
                            if (tx.business_date < grouped[key].min_date) grouped[key].min_date = tx.business_date;
                            if (tx.business_date > grouped[key].max_date) grouped[key].max_date = tx.business_date;
                        });
                        return Object.values(grouped).map(g => {
                            let dateStr = g.min_date;
                            if (g.min_date !== g.max_date) {
                                dateStr = `${g.min_date} to ${g.max_date}`;
                            }
                            return {
                                date: dateStr,
                                name: g.name,
                                source: g.source,
                                amount: g.amount,
                                notes: g.notes
                            };
                        });
                    }
                    return list.map(tx => ({
                        date: tx.business_date,
                        name: tx.entry_type ? tx.entry_type.name : tx.entry_type_code,
                        source: tx.funding_source || 'default',
                        amount: tx.amount,
                        notes: tx.notes || '-'
                    }));
                },

                entryTypeName(tx) {
                    if (!tx) return '-';
                    if (tx.entry_type && tx.entry_type.name) {
                        return tx.entry_type.name;
                    }
                    const code = tx.entry_type_code || (tx.entry_type ? tx.entry_type.code : null);
                    if (code) {
                        const found = (this.entryTypes || []).find((row) => row.code === code);
                        if (found && found.name) {
                            return found.name;
                        }
                        return code.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
                    }
                    return '-';
                },

                selectedEntryTypeName() {
                    const entry = this.entryTypes.find((row) => row.code === this.form.entry_type_code);
                    return entry ? entry.name : '';
                },

                fundingSourceLabel(value) {
                    const found = this.fundingOptions.find((opt) => opt.value === value);
                    return found ? found.label : 'Default';
                },

                filteredEntryTypes() {
                    return this.entryTypes.filter((entryType) => entryType.category === this.form.entry_category);
                },

                availableCategories() {
                    return [...new Set(this.entryTypes.map((entryType) => entryType.category).filter(Boolean))];
                },

                ensureValidEntrySelection() {
                    const categories = this.availableCategories();
                    if (!categories.length) {
                        this.form.entry_category = '';
                        this.form.entry_type_code = '';
                        return;
                    }

                    if (!categories.includes(this.form.entry_category)) {
                        this.form.entry_category = categories[0];
                    }

                    this.onEntryCategoryChange();
                },

                onEntryCategoryChange() {
                    const options = this.filteredEntryTypes();
                    if (!options.length) {
                        this.form.entry_type_code = '';
                        return;
                    }

                    if (!options.some((option) => option.code === this.form.entry_type_code)) {
                        this.form.entry_type_code = options[0].code;
                    }
                },

                onEntryTypeChange() {
                    const entry = this.entryTypes.find((row) => row.code === this.form.entry_type_code);
                    if (entry && entry.category) {
                        this.form.entry_category = entry.category;
                    }
                },

                selectDefaultCollectionGroup() {
                    if (!this.form.collection_group_id && this.collectionGroups.length > 0) {
                        this.form.collection_group_id = this.collectionGroups[0].id;
                    }
                    this.resetCollectionAmounts();
                },

                selectedCollectionGroup() {
                    return this.collectionGroups.find((group) => Number(group.id) === Number(this.form.collection_group_id)) || null;
                },

                selectedCollectionLines() {
                    return this.selectedCollectionGroup()?.entry_types || [];
                },

                resetCollectionAmounts() {
                    const amounts = {};
                    this.selectedCollectionLines().forEach((line) => {
                        amounts[line.entry_type_id] = this.form.collection_amounts[line.entry_type_id] || '';
                    });
                    this.form.collection_amounts = amounts;
                },

                collectionNet() {
                    return this.selectedCollectionLines().reduce((sum, line) => {
                        const amount = parseFloat(this.form.collection_amounts[line.entry_type_id] || 0);
                        return line.role === 'income' ? sum + amount : sum - amount;
                    }, 0);
                },

                showCollectionDetails(group) {
                    this.cardModalData = {
                        title: group.name,
                        subtitle: 'Collection income, debit lines, and net amount.',
                        value: this.currency(group.net),
                        tone: group.net >= 0 ? 'emerald' : 'rose',
                        description: `Income ${this.currency(group.income)} - expense ${this.currency(group.expense)}`,
                        isCollection: true,
                        reference_id: group.reference_id,
                        breakdown: (group.lines || []).map((tx) => ({
                            date: tx.business_date,
                            name: tx.entry_type ? tx.entry_type.name : tx.entry_type_id,
                            source: tx.direction,
                            amount: tx.direction === 'expense' ? -Math.abs(parseFloat(tx.amount || 0)) : tx.amount,
                            notes: tx.notes || '-',
                            tx: tx,
                        })),
                    };
                    this.openCardModal = true;
                },

                async reloadPage(fullPage = false) {
                    if (fullPage) {
                        window.location.reload();
                        return;
                    }
                    this.loading = true;
                    try {
                        await this.loadData();
                    } finally {
                        setTimeout(() => { this.loading = false; }, 300);
                    }
                },

                setPreset(preset) {
                    this.activePreset = preset;
                    const todayStr = '{{ today()->toDateString() }}';
                    const yesterdayStr = '{{ today()->subDay()->toDateString() }}';

                    if (preset === 'today') {
                        this.selectedDate = todayStr;
                        this.startDate = todayStr;
                        this.endDate = todayStr;
                        this.form.business_date = todayStr;
                        this.timeframe = 'daily';
                    } else if (preset === 'yesterday') {
                        this.selectedDate = yesterdayStr;
                        this.startDate = yesterdayStr;
                        this.endDate = yesterdayStr;
                        this.form.business_date = yesterdayStr;
                        this.timeframe = 'daily';
                    } else if (preset === 'weekly') {
                        this.selectedDate = todayStr;
                        this.form.business_date = todayStr;
                        this.timeframe = 'weekly';
                    } else if (preset === 'monthly') {
                        this.selectedDate = todayStr;
                        this.form.business_date = todayStr;
                        this.timeframe = 'monthly';
                    } else if (preset === 'custom') {
                        this.timeframe = 'custom';
                        if (!this.startDate) this.startDate = todayStr;
                        if (!this.endDate) this.endDate = todayStr;
                        this.form.business_date = this.startDate;
                        return;
                    }
                    this.loadData();
                },

                timeframeLabel() {
                    if (this.timeframe === 'custom') {
                        return (this.startDate || '') + ' to ' + (this.endDate || '');
                    }
                    return this.timeframe;
                },

                async loadData() {
                    try {
                        const url = new URL(window.location.href);
                        url.searchParams.set('date', this.selectedDate);
                        url.searchParams.set('tab', this.activeTab);
                        url.searchParams.set('timeframe', this.timeframe);
                        if (this.startDate) url.searchParams.set('start_date', this.startDate);
                        if (this.endDate) url.searchParams.set('end_date', this.endDate);
                        window.history.replaceState({}, '', url);
                    } catch (e) {}

                    const params = new URLSearchParams({
                        business_date: this.selectedDate,
                        timeframe: this.timeframe,
                        start_date: this.startDate || '',
                        end_date: this.endDate || '',
                    });

                    const response = await fetch(`{{ route('shop-owner.cashbook.api.shop-data') }}?${params.toString()}`);
                    const payload = await response.json();

                    if (!payload.success) {
                        return;
                    }

                    this.transactions = payload.transactions || [];
                    this.companyPendingEntries = payload.company_pending_entries || [];
                    this.entryTypes = (payload.settings || [])
                        .map((setting) => setting.entry_type)
                        .filter(Boolean);
                    this.ensureValidEntrySelection();
                    this.collectionGroups = payload.collection_groups || this.collectionGroups;
                    this.collectionSummaries = payload.collection_summaries || [];
                    this.snapshot = payload.snapshot || this.snapshot;

                    const monthRows = payload.month_transactions || [];
                    this.reporting.sales = monthRows
                        .filter((row) => row.direction === 'income')
                        .reduce((sum, row) => sum + parseFloat(row.amount || 0), 0);
                    this.reporting.expense = monthRows
                        .filter((row) => row.direction === 'expense')
                        .reduce((sum, row) => sum + parseFloat(row.amount || 0), 0);
                    this.reporting.net = this.reporting.sales - this.reporting.expense;
                },

                async submitEntry() {
                    if (this.entryMode === 'normal') {
                        if (!this.form.entry_type_code) {
                            alert('Please select an entry type. If no entry types are available, contact admin.');
                            return;
                        }
                        if (!this.form.amount || parseFloat(this.form.amount) <= 0) {
                            alert('Please enter a valid amount greater than 0.');
                            return;
                        }
                    }

                    this.submitting = true;

                    try {
                        const url = this.editingTxId
                            ? '{{ route('shop-owner.cashbook.api.update-entry') }}'
                            : '{{ route('shop-owner.cashbook.api.record-entry') }}';
                        const body = this.editingTxId
                            ? {
                                transaction_id: this.editingTxId,
                                amount: this.form.amount,
                                funding_source: this.form.funding_source,
                                notes: this.form.notes,
                            }
                            : this.entryMode === 'collection'
                                ? {
                                    business_date: this.form.business_date,
                                    collection_group_id: this.form.collection_group_id,
                                    collection_lines: this.selectedCollectionLines().map((line) => ({
                                        entry_type_id: line.entry_type_id,
                                        amount: this.form.collection_amounts[line.entry_type_id] || 0,
                                    })),
                                    notes: this.form.notes,
                                }
                                : {
                                    business_date: this.form.business_date,
                                    entry_type_code: this.form.entry_type_code,
                                    amount: this.form.amount,
                                    funding_source: this.form.funding_source,
                                    notes: this.form.notes,
                                };

                        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = csrfMeta ? csrfMeta.content : '{{ csrf_token() }}';

                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify(body),
                        });

                        let payload;
                        try {
                            payload = await response.json();
                        } catch (e) {
                            alert('Server returned an invalid response (HTTP ' + response.status + '). Please reload the page.');
                            return;
                        }

                        if (!response.ok || !payload.success) {
                            let errorMsg = payload.message || 'Unable to save entry.';
                            if (payload.errors) {
                                const errorList = Object.values(payload.errors).flat().join('\n');
                                if (errorList && errorList !== errorMsg) errorMsg += '\n' + errorList;
                            }
                            alert(errorMsg);
                            return;
                        }

                        const targetDate = this.form.business_date || this.selectedDate;
                        this.closeCreateModal();
                        this.selectedDate = targetDate;
                        this.startDate = targetDate;
                        this.endDate = targetDate;
                        this.form.business_date = targetDate;
                        await this.loadData();
                    } catch (err) {
                        alert('Network or system error occurred: ' + (err.message || 'Please try again.'));
                    } finally {
                        this.submitting = false;
                    }
                },

                async deleteEntry(tx) {
                    this.openDelete(tx);
                },

                async submitDelete() {
                    if (!this.deletingTx) {
                        return;
                    }

                    this.submitting = true;
                    try {
                        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = csrfMeta ? csrfMeta.content : '{{ csrf_token() }}';

                        const response = await fetch('{{ route('shop-owner.cashbook.api.delete-entry') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({ transaction_id: this.deletingTx.id }),
                        });

                        let payload;
                        try {
                            payload = await response.json();
                        } catch (e) {
                            alert('Server returned an invalid response (HTTP ' + response.status + '). Please reload the page.');
                            return;
                        }

                        if (!response.ok || !payload.success) {
                            alert(payload.message || 'Unable to delete entry.');
                            return;
                        }

                        this.openDetailsModal = false;
                        this.openDeleteModal = false;
                        this.deletingTx = null;
                        await this.loadData();
                    } catch (err) {
                        alert('Network error: ' + (err.message || 'Please try again.'));
                    } finally {
                        this.submitting = false;
                    }
                },

                async submitDeleteCollection(referenceId) {
                    if (!confirm('Are you sure you want to remove this collection and all its lines?')) {
                        return;
                    }

                    this.submitting = true;
                    try {
                        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = csrfMeta ? csrfMeta.content : '{{ csrf_token() }}';

                        const response = await fetch('{{ route('shop-owner.cashbook.api.delete-collection') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({ reference_id: referenceId }),
                        });

                        let payload;
                        try {
                            payload = await response.json();
                        } catch (e) {
                            alert('Server returned an invalid response (HTTP ' + response.status + '). Please reload the page.');
                            return;
                        }

                        if (!response.ok || !payload.success) {
                            alert(payload.message || 'Unable to delete collection.');
                            return;
                        }

                        this.openCardModal = false;
                        await this.loadData();
                    } catch (err) {
                        alert('Network error: ' + (err.message || 'Please try again.'));
                    } finally {
                        this.submitting = false;
                    }
                },

                async openCopyYesterday() {
                    this.copyError = '';
                    this.copyLoading = true;
                    try {
                        const curDate = new Date(this.selectedDate || '{{ $selectedDate->toDateString() }}');
                        curDate.setDate(curDate.getDate() - 1);
                        const yDate = curDate.toISOString().split('T')[0];
                        this.yesterdayDateLabel = yDate;

                        const params = new URLSearchParams({
                            business_date: yDate,
                            timeframe: 'daily',
                        });

                        const response = await fetch(`{{ route('shop-owner.cashbook.api.shop-data') }}?${params.toString()}`);
                        const payload = await response.json();

                        if (!payload.success) {
                            this.copyError = payload.message || 'Unable to fetch yesterday\'s entries.';
                            this.showCopyYesterday = true;
                            this.copyRows = [];
                            return;
                        }

                        const rawTxs = payload.transactions || [];
                        const filtered = rawTxs.filter((tx) => {
                            if (tx.reference_type === 'App\\Models\\ShopInvoice' || tx.reference_type === 'ShopInvoice') {
                                return false;
                            }
                            const code = tx.entry_type_code || tx.entry_type?.code;
                            if (['gl_bill', 'purchase_bill'].includes(code)) {
                                return false;
                            }
                            return true;
                        });

                        if (filtered.length === 0) {
                            this.copyError = `No eligible entries found from yesterday (${yDate}).`;
                            this.showCopyYesterday = true;
                            this.copyRows = [];
                            return;
                        }

                        this.copyRows = filtered.map((tx) => {
                            const val = parseFloat(tx.amount || 0);
                            return {
                                entry_type_code: tx.entry_type_code || tx.entry_type?.code || '',
                                name: this.entryTypeName(tx),
                                amount: val > 0 ? (val % 1 === 0 ? val.toFixed(0) : val.toString()) : '',
                                funding_source: tx.funding_source || 'none',
                                direction: tx.direction || tx.entry_type?.category || 'expense',
                                notes: '',
                            };
                        });

                        this.showCopyYesterday = true;
                    } catch (err) {
                        this.copyError = 'Failed to load yesterday\'s data: ' + (err.message || 'Please try again.');
                        this.showCopyYesterday = true;
                    } finally {
                        this.copyLoading = false;
                    }
                },

                clearCopyRows() {
                    (this.copyRows || []).forEach((row) => {
                        row.amount = '';
                    });
                },

                async saveCopyRows() {
                    if (!this.copyRows.length) {
                        this.copyError = 'No entries to save.';
                        return;
                    }

                    const invalid = this.copyRows.some((r) => !r.amount || parseFloat(r.amount) <= 0);
                    if (invalid) {
                        this.copyError = 'All entries must have a valid amount greater than 0.';
                        return;
                    }

                    this.copySaving = true;
                    this.copyError = '';

                    try {
                        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = csrfMeta ? csrfMeta.content : '{{ csrf_token() }}';

                        const body = {
                            business_date: this.selectedDate || '{{ $selectedDate->toDateString() }}',
                            entries: this.copyRows.map((r) => ({
                                entry_type_code: r.entry_type_code,
                                amount: parseFloat(r.amount),
                                funding_source: r.funding_source || 'none',
                                notes: r.notes || null,
                            })),
                        };

                        const response = await fetch('{{ route('shop-owner.cashbook.api.bulk-record-entries') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify(body),
                        });

                        let payload;
                        try {
                            payload = await response.json();
                        } catch (e) {
                            this.copyError = 'Server returned an invalid response (HTTP ' + response.status + ').';
                            return;
                        }

                        if (!response.ok || !payload.success) {
                            this.copyError = payload.message || 'Unable to save entries.';
                            return;
                        }

                        this.showCopyYesterday = false;
                        this.copyRows = [];
                        await this.loadData();
                    } catch (err) {
                        this.copyError = 'Network error: ' + (err.message || 'Please try again.');
                    } finally {
                        this.copySaving = false;
                    }
                },

                currency(value) {
                    const num = Number(value || 0);
                    const formatted = num % 1 === 0 ? num.toFixed(0) : num.toFixed(2);
                    return `Rs. ${formatted}`;
                },
            };
        }
    </script>
@endpush
