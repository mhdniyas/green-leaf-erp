@extends('shop-owner.layouts.app')

@section('title', 'Cashbook')
@section('page_title', 'Cashbook Dashboard')
@section('page_description', 'Post daily income/expense entries using the new cashbook rules and review ledger updates.')
@section('page_back_url', route('shop-owner.dashboard'))
@php($breadcrumbs = [['label' => 'Dashboard', 'url' => route('shop-owner.dashboard')], ['label' => 'Cashbook']])

@section('page_actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('shop-owner.cashbook.create', ['date' => $selectedDate->toDateString()]) }}" class="inline-flex h-10 items-center rounded-xl bg-emerald-600 px-4 text-xs font-black uppercase tracking-[0.14em] text-white transition hover:bg-emerald-500">
            Create Entry
        </a>
        <a href="{{ route('shop-owner.cashbook.reports', ['date' => $selectedDate->toDateString()]) }}" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black uppercase tracking-[0.14em] text-slate-700 transition hover:bg-slate-50">
            Reports
        </a>
    </div>
@endsection

@section('content')
    <div class="mx-auto w-full max-w-7xl overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm" x-data="cashbookPage()" x-init="init()">
        <header class="flex flex-wrap items-center justify-between gap-3 bg-slate-950 px-4 py-3 text-white sm:px-5">
            <div>
                <p class="text-[10px] font-black uppercase tracking-wider text-emerald-400">Shop Cashbook — {{ $shop->name }}</p>
                <h1 class="text-lg font-black text-white sm:text-xl">Cashbook Dashboard</h1>
                <p class="mt-0.5 text-xs font-semibold text-slate-400">Date: {{ $selectedDate->format('d M Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="openCreate = true" class="rounded-lg border border-emerald-500/60 bg-emerald-500 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-400">
                    + Create Entry
                </button>
                <a href="{{ route('shop-owner.cashbook.reports', ['date' => $selectedDate->toDateString()]) }}" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-bold text-slate-200 transition hover:bg-slate-800">
                    Reports
                </a>
            </div>
        </header>

        <form method="GET" action="{{ route('shop-owner.cashbook.show') }}" class="flex flex-wrap items-center justify-between gap-2.5 border-b border-slate-200 bg-slate-50 px-4 py-2 sm:px-5">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-black uppercase text-slate-500">Business Date</span>
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <input type="date" name="date" value="{{ $selectedDate->toDateString() }}" class="h-8 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:outline-none">
                <button type="submit" class="h-8 rounded-lg bg-slate-900 px-3 text-xs font-bold text-white transition hover:bg-slate-800">
                    Load
                </button>
            </div>
            <div class="flex items-center gap-2" x-show="activeTab === 'cashbook'">
                <span class="text-[10px] font-black uppercase text-slate-500">Timeframe</span>
                <div class="relative" x-data="{ open: false }">
                    <button
                        type="button"
                        @click="open = !open"
                        @click.away="open = false"
                        class="flex h-8 items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-800 transition focus:border-emerald-500 focus:outline-none"
                    >
                        <span class="capitalize" x-text="timeframe"></span>
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
                        class="absolute right-0 top-full z-30 mt-1 min-w-[7rem] rounded-lg border border-slate-200 bg-white p-1 shadow-lg shadow-slate-900/10"
                    >
                        <template x-for="tf in ['daily', 'weekly', 'monthly']" :key="tf">
                            <button
                                type="button"
                                @click="timeframe = tf; loadData(); open = false"
                                class="flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-left text-xs font-bold capitalize transition"
                                :class="timeframe === tf ? 'bg-emerald-50 text-emerald-700' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'"
                            >
                                <span x-text="tf"></span>
                                <svg x-show="timeframe === tf" class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </form>

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
                <button type="button" @click="openCreate = true" class="rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-bold text-white transition hover:bg-emerald-500">
                    + Add Entry
                </button>
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
                            <th class="px-2 py-2">Funding</th>
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
                                <td class="px-2 py-1.5" :class="tx.direction === 'income' ? 'text-emerald-700' : 'text-rose-700'">
                                    <span class="inline-flex items-center gap-1 capitalize font-semibold">
                                        <svg class="h-3.5 w-3.5" :class="tx.funding_source === 'sales' ? 'text-emerald-500' : (tx.funding_source === 'company' ? 'text-amber-500' : (tx.funding_source === 'bank' || tx.funding_source === 'external' ? 'text-sky-500' : 'text-slate-400'))" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path x-show="tx.funding_source === 'sales'" d="M4 14.5h12M5.5 10.5h9M7 6.5h6" />
                                            <path x-show="tx.funding_source === 'company'" d="M4 15h12M6 15V6l4-2 4 2v9" />
                                            <path x-show="tx.funding_source === 'bank' || tx.funding_source === 'external'" d="M3.5 8.5h13M5 8.5v6m4-6v6m4-6v6m4-6v6M3 15.5h14" />
                                            <path x-show="!['sales','company','bank','external'].includes(tx.funding_source)" d="M5 10h10M4 7h12M4 13h12" />
                                        </svg>
                                        <span x-text="fundingSourceLabel(tx.funding_source)"></span>
                                    </span>
                                </td>
                                <td class="px-3 py-1.5 text-right font-bold text-xs whitespace-nowrap" :class="tx.direction === 'income' ? 'text-emerald-700' : 'text-rose-700'" x-text="currency(tx.amount)"></td>
                            </tr>
                        </template>
                        <tr x-show="transactions.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center text-xs font-semibold text-slate-400">No entries found for this period. Click "+ Add Entry" to record transactions.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="activeTab === 'reports'" class="p-3 sm:p-4 space-y-3.5">
            <div>
                <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-emerald-600">Admin Cashbook Metrics</p>
                        <h3 class="text-sm font-black text-slate-950">Shop Snapshot Overview</h3>
                    </div>
                    <span class="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-extrabold uppercase text-emerald-700 border border-emerald-200" x-text="timeframe + ' View'"></span>
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

                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-2 space-y-0.5 cursor-pointer hover:border-sky-400 transition-all" @click="showCardDetails('petty')" title="Click for details">
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider block truncate">Closing Petty</span>
                        <div class="text-xs font-black text-sky-700 truncate whitespace-nowrap" x-text="currency(snapshot.closing_petty || 0)"></div>
                        <span class="text-[9px] text-sky-600 font-bold block truncate">Petty Float</span>
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
                        <span
                            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider"
                            :class="selectedTx?.status === 'approved'
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : (selectedTx?.status === 'void' ? 'border-slate-200 bg-slate-100 text-slate-500' : 'border-amber-200 bg-amber-50 text-amber-700')"
                            x-text="selectedTx?.status === 'approved' ? 'Approved' : (selectedTx?.status_label || selectedTx?.status || 'Posted')"
                        ></span>
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

                    <template x-if="cardModalData.breakdown && cardModalData.breakdown.length > 0">
                        <div>
                            <h4 class="text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Itemized Transactions</h4>
                            <div class="max-h-48 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
                                <template x-for="(item, idx) in cardModalData.breakdown" :key="idx">
                                    <div class="flex items-center justify-between p-2 hover:bg-slate-50">
                                        <div>
                                            <p class="font-bold text-slate-900" x-text="item.name"></p>
                                            <p class="text-[10px] font-semibold text-slate-400"><span x-text="formatDayNumber(item.date)"></span> · <span class="capitalize" x-text="item.source"></span></p>
                                        </div>
                                        <span class="font-black text-xs text-slate-950" x-text="currency(item.amount)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex justify-end p-3 border-t border-slate-100 bg-slate-50">
                    <button type="button" @click="openCardModal = false" class="h-8 px-4 text-xs font-bold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 transition">
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
                timeframe: 'daily',
                transactions: [],
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
                    return this.transactions.filter((tx) => codes.has(tx.entry_type?.code || tx.entry_type_code));
                },

                payableTotal() {
                    return this.payableTransactions().reduce((sum, tx) => sum + parseFloat(tx.amount || 0), 0);
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
                    return tx && tx.status !== 'approved' && tx.status !== 'void';
                },

                showCardDetails(cardType) {
                    if (cardType === 'sales') {
                        const items = this.transactions.filter(tx => tx.direction === 'income' || (tx.entry_type && tx.entry_type.category === 'income'));
                        this.cardModalData = {
                            title: 'Total Sales / Gross Inflow',
                            subtitle: 'Gross revenue and income entries recorded for this timeframe.',
                            value: this.currency(this.displaySales()),
                            tone: 'emerald',
                            description: `Total sales for ${this.timeframe} view as of ${this.selectedDate}.`,
                            breakdown: items.map(tx => ({
                                date: tx.business_date,
                                name: tx.entry_type ? tx.entry_type.name : tx.entry_type_code,
                                source: tx.funding_source || 'default',
                                amount: tx.amount,
                                notes: tx.notes || '-'
                            }))
                        };
                    } else if (cardType === 'expense') {
                        const items = this.transactions.filter(tx => tx.direction === 'expense' || (tx.entry_type && tx.entry_type.category === 'expense'));
                        this.cardModalData = {
                            title: 'Total Expense / P/L Chargeable',
                            subtitle: 'Direct expenses, supplier bills, and operating costs recorded for this period.',
                            value: this.currency(this.displayExpense()),
                            tone: 'rose',
                            description: `Total expenses for ${this.timeframe} view as of ${this.selectedDate}.`,
                            breakdown: items.map(tx => ({
                                date: tx.business_date,
                                name: tx.entry_type ? tx.entry_type.name : tx.entry_type_code,
                                source: tx.funding_source || 'default',
                                amount: tx.amount,
                                notes: tx.notes || '-'
                            }))
                        };
                    } else if (cardType === 'closing_balance' || cardType === 'net') {
                        const net = this.displayClosingBalance();
                        this.cardModalData = {
                            title: 'Closing Balance / Net Position',
                            subtitle: 'Net position calculated as (Total Sales − Total Expenses).',
                            value: this.currency(net),
                            tone: net >= 0 ? 'emerald' : 'rose',
                            description: `Net summary position for ${this.timeframe} view.`,
                            breakdown: [
                                { date: this.selectedDate, name: 'Gross Sales / Income', source: 'Total Inflow', amount: this.displaySales(), notes: 'Sales and income entries' },
                                { date: this.selectedDate, name: 'Total Expense / Debit', source: 'Total Outflow', amount: this.displayExpense(), notes: 'Operating & bill expenses' },
                            ]
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
                            breakdown: rows.map((tx) => ({
                                date: tx.business_date,
                                name: tx.entry_type ? tx.entry_type.name : tx.entry_type_code,
                                source: tx.funding_source || 'none',
                                amount: tx.amount,
                                notes: tx.notes || 'Included in payable'
                            }))
                        };
                    } else if (cardType === 'company_pending') {
                        this.cardModalData = {
                            title: 'Company Pending Reimbursements',
                            subtitle: 'Pending reimbursements and claims submitted to company.',
                            value: this.currency(this.snapshot?.closing_company_pending || 0),
                            tone: 'emerald',
                            description: `Pending claims to be reimbursed by company.`,
                            breakdown: []
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
                        breakdown: (group.lines || []).map((tx) => ({
                            date: tx.business_date,
                            name: tx.entry_type ? tx.entry_type.name : tx.entry_type_id,
                            source: tx.direction,
                            amount: tx.direction === 'expense' ? -Math.abs(parseFloat(tx.amount || 0)) : tx.amount,
                            notes: tx.notes || '-',
                        })),
                    };
                    this.openCardModal = true;
                },

                async loadData() {
                    const params = new URLSearchParams({
                        business_date: this.selectedDate,
                        timeframe: this.timeframe,
                    });

                    const response = await fetch(`{{ route('shop-owner.cashbook.api.shop-data') }}?${params.toString()}`);
                    const payload = await response.json();

                    if (!payload.success) {
                        return;
                    }

                    this.transactions = payload.transactions || [];
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
                    if (this.entryMode === 'normal' && !this.form.entry_type_code) {
                        alert('No rows enabled. Contact admin.');
                        return;
                    }

                    this.submitting = true;

                    try {
                        const url = this.editingTxId
                            ? '{{ route('shop-owner.cashbook.api.update-entry') }}'
                            : '{{ route('shop-owner.cashbook.api.record-entry') }}';
                        const body = this.editingTxId
                            ? { transaction_id: this.editingTxId, amount: this.form.amount, notes: this.form.notes }
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
                                : this.form;
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify(body),
                        });

                        const payload = await response.json();
                        if (!payload.success) {
                            alert(payload.message || 'Unable to create entry.');
                            return;
                        }

                        this.closeCreateModal();
                        this.form.amount = '';
                        this.form.notes = '';
                        this.selectedDate = this.form.business_date;
                        await this.loadData();
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

                    const response = await fetch('{{ route('shop-owner.cashbook.api.delete-entry') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ transaction_id: this.deletingTx.id }),
                    });

                    const payload = await response.json();
                    if (!payload.success) {
                        alert(payload.message || 'Unable to delete entry.');
                        return;
                    }

                    this.openDetailsModal = false;
                    this.openDeleteModal = false;
                    this.deletingTx = null;
                    await this.loadData();
                },

                currency(value) {
                    return `Rs. ${Number(value || 0).toFixed(2)}`;
                },
            };
        }
    </script>
@endpush
