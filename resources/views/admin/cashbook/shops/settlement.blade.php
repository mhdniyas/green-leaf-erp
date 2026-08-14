@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop #' . $currentShop->shop_id) . ' — Payment & Bill Clearing')

@section('header_title')
    <i data-lucide="wallet" class="w-5 h-5 text-slate-900"></i> Accept Payment & Itemized Bill Clearing for {{ $currentShop->name }}
@endsection

@section('header_subtitle')
    Record configured category payments or lump-sum collections received from shop into company accounts, select itemized bills, and execute clearing.
@endsection

@section('header_actions')
    <a href="{{ route('admin.cashbook.shop.show', $currentShop->slug ?: $currentShop->shop_id) }}" class="px-4 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold text-xs transition-all flex items-center gap-1.5 shadow-sm">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Shop Details
    </a>
@endsection

@section('content')
<div x-data="settlementPageApp()" x-init="init()" class="space-y-6">

    <!-- Shop Overview & Timeframe Control Banner -->
    <div class="white-card p-5 rounded-3xl shadow-sm border border-slate-200 flex flex-col xl:flex-row items-start xl:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="h-14 w-14 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-900 shadow-sm shrink-0">
                <i data-lucide="store" class="w-7 h-7"></i>
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-extrabold text-slate-900">{{ $currentShop->name }}</h2>
                    <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1">
                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span> Settlement Active
                    </span>
                </div>
                <p class="text-xs text-slate-500 font-medium mt-0.5">
                    Client: <span class="font-bold text-slate-800">{{ $currentShop->client->name ?? 'Aiswarya Veg' }}</span> • 
                    Code: <code class="font-mono text-slate-700 bg-slate-100 px-1 py-0.5 rounded">{{ $currentShop->code ?: 'SHOP-'.$currentShop->shop_id }}</code>
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto justify-start xl:justify-end">
            <!-- Header Date Picker & Timeframe Selector -->
            <div class="flex flex-wrap items-center gap-2 bg-slate-100 p-1.5 rounded-2xl border border-slate-200">
                <div class="flex items-center gap-1.5 bg-white px-2.5 py-1.5 rounded-xl border border-slate-200 shadow-xs">
                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-500"></i>
                    <input type="date" x-model="headerDate" @change="changeDate(headerDate)" class="bg-transparent text-xs font-mono font-bold text-slate-800 focus:outline-none cursor-pointer">
                </div>

                <div class="flex items-center gap-1">
                    <button @click="setTimeframe('daily')" :class="timeframe === 'daily' ? 'bg-slate-900 text-white shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'" class="px-2.5 py-1 text-xs rounded-lg transition-all">
                        Day
                    </button>
                    <button @click="setTimeframe('weekly')" :class="timeframe === 'weekly' ? 'bg-slate-900 text-white shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'" class="px-2.5 py-1 text-xs rounded-lg transition-all">
                        Week
                    </button>
                    <button @click="setTimeframe('monthly')" :class="timeframe === 'monthly' ? 'bg-slate-900 text-white shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'" class="px-2.5 py-1 text-xs rounded-lg transition-all">
                        Month
                    </button>
                    <button @click="setTimeframe('custom')" :class="timeframe === 'custom' ? 'bg-slate-900 text-white shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'" class="px-2.5 py-1 text-xs rounded-lg transition-all">
                        Custom
                    </button>
                </div>
            </div>

            <!-- Custom Date Range Picker -->
            <div x-show="timeframe === 'custom'" class="flex items-center gap-1.5 bg-slate-100 p-1.5 rounded-2xl border border-slate-200 text-xs">
                <input type="date" x-model="customStartDate" @change="loadData()" class="bg-white text-xs font-mono font-bold text-slate-800 px-2 py-1 rounded-xl border border-slate-300">
                <span class="text-slate-400 font-bold">to</span>
                <input type="date" x-model="customEndDate" @change="loadData()" class="bg-white text-xs font-mono font-bold text-slate-800 px-2 py-1 rounded-xl border border-slate-300">
            </div>
        </div>
    </div>

    <!-- 4-Vector Balances Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <!-- 1. Company Payable (Configured Categories) -->
        <div class="white-card p-4 sm:p-5 rounded-3xl border border-slate-200 shadow-sm space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] sm:text-[11px] font-bold uppercase text-slate-500 tracking-wider block">1. Company Payable</span>
                <span class="px-2 py-0.5 text-[9px] font-black uppercase tracking-wider rounded-lg"
                      :class="payableBalance <= 0 ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : (payableReceivedTotal > 0 ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700 border border-slate-200')"
                      x-text="payableBalance <= 0 ? 'Fully Received' : (payableReceivedTotal > 0 ? 'Partially Paid' : 'Pending')">
                </span>
            </div>
            <strong id="set-shop-position" class="text-xl sm:text-2xl font-mono font-extrabold text-amber-600 block truncate" x-text="'₹' + payableBalance.toFixed(2)">₹0.00</strong>
            <div class="flex items-center justify-between text-[10px] text-slate-500 font-medium">
                <span>Total: <strong class="text-slate-800 font-mono" x-text="'₹' + payableTotal.toFixed(2)"></strong></span>
                <span>Recv: <strong class="text-emerald-700 font-mono" x-text="'₹' + payableReceivedTotal.toFixed(2)"></strong></span>
            </div>
        </div>

        <!-- 2. GL Bills Due -->
        <div class="white-card p-4 sm:p-5 rounded-3xl border border-slate-200 shadow-sm space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] sm:text-[11px] font-bold uppercase text-slate-500 tracking-wider block">2. GL Bills Due</span>
                <span class="px-2 py-0.5 text-[9px] font-black uppercase tracking-wider bg-slate-100 text-slate-700 border border-slate-200 rounded-lg">Invoices</span>
            </div>
            <strong id="set-gl-bills-total" class="text-xl sm:text-2xl font-mono font-extrabold text-slate-900 block truncate" x-text="'₹' + totalGlBillsAmount.toFixed(2)">₹0.00</strong>
            <span class="text-[10px] text-slate-500 font-semibold block truncate">Bills to Clear</span>
        </div>

        <!-- 3. Company Pending -->
        <div class="white-card p-4 sm:p-5 rounded-3xl border border-slate-200 shadow-sm space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] sm:text-[11px] font-bold uppercase text-slate-500 tracking-wider block">3. Company Pending</span>
                <span class="px-2 py-0.5 text-[9px] font-black uppercase tracking-wider bg-purple-50 text-purple-700 border border-purple-200 rounded-lg">Reimburse</span>
            </div>
            <strong id="set-company-pending" class="text-xl sm:text-2xl font-mono font-extrabold text-purple-600 block truncate" x-text="'₹' + parseFloat(snapshot?.closing_company_pending || 0).toFixed(2)">₹0.00</strong>
            <span class="text-[10px] text-purple-600 font-semibold block truncate">Expenses Paid</span>
        </div>

        <!-- 4. Net Due Position -->
        <div class="white-card p-4 sm:p-5 rounded-3xl border border-slate-200 shadow-sm space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] sm:text-[11px] font-bold uppercase text-slate-500 tracking-wider block">4. Net Position</span>
                <span class="px-2 py-0.5 text-[9px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg">Final</span>
            </div>
            <strong id="set-net-due" class="text-xl sm:text-2xl font-mono font-extrabold block truncate" :class="netDuePosition < 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="'₹' + netDuePosition.toFixed(2)">₹0.00</strong>
            <span class="text-[10px] text-slate-500 font-semibold block truncate">Final Settlement</span>
        </div>
    </div>

    <!-- Configured Categories Breakdown & Receive Action Bar -->
    <div class="white-card p-5 rounded-3xl border border-slate-200 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                    <i data-lucide="layers" class="w-4 h-4 text-amber-600"></i> Configured Payment Categories Received from {{ $currentShop->name }}
                </h3>
                <p class="text-xs text-slate-500">Live received status and remaining balance per configured payment category for (<span class="capitalize font-bold text-slate-800" x-text="timeframe"></span>).</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-1.5 text-xs">
                    <span class="text-slate-500 font-medium">Recorded:</span>
                    <strong class="font-mono text-slate-900" x-text="'₹' + payableTotal.toFixed(2)"></strong>
                </div>
                <div class="flex items-center gap-1.5 text-xs">
                    <span class="text-slate-500 font-medium">Received:</span>
                    <strong class="font-mono text-emerald-700" x-text="'₹' + payableReceivedTotal.toFixed(2)"></strong>
                </div>
                <div class="flex items-center gap-1.5 text-xs">
                    <span class="text-slate-500 font-medium">Balance:</span>
                    <span class="px-2.5 py-0.5 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl font-mono font-extrabold text-xs" x-text="'₹' + payableBalance.toFixed(2)"></span>
                </div>
            </div>
        </div>

        <!-- Category Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3.5">
            <template x-for="cat in payableByCategory" :key="cat.code || cat.name">
                <div class="p-3.5 rounded-2xl border transition-all space-y-2.5 flex flex-col justify-between"
                     :class="cat.status === 'received' ? 'bg-emerald-50/40 border-emerald-200' : (cat.status === 'partial' ? 'bg-amber-50/40 border-amber-200' : 'bg-slate-50 border-slate-200')">
                    
                    <div>
                        <div class="flex items-start justify-between gap-1">
                            <div>
                                <h4 class="text-xs font-black text-slate-900" x-text="cat.name"></h4>
                                <span class="text-[10px] font-mono text-slate-400" x-text="cat.count + ' transactions'"></span>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider"
                                  :class="cat.status === 'received' ? 'bg-emerald-100 text-emerald-800' : (cat.status === 'partial' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700')"
                                  x-text="cat.status"></span>
                        </div>

                        <!-- Amount Breakdown Matrix -->
                        <div class="mt-2.5 grid grid-cols-3 gap-1 pt-1.5 border-t border-slate-200/70 text-center font-mono">
                            <div class="p-1 rounded-lg bg-white/70">
                                <span class="text-[8px] font-sans font-bold uppercase text-slate-400 block">Recorded</span>
                                <strong class="text-[11px] font-bold text-slate-800" x-text="'₹' + cat.recorded_amount.toFixed(2)"></strong>
                            </div>
                            <div class="p-1 rounded-lg bg-white/70">
                                <span class="text-[8px] font-sans font-bold uppercase text-emerald-600 block">Received</span>
                                <strong class="text-[11px] font-bold text-emerald-700" x-text="'₹' + cat.received_amount.toFixed(2)"></strong>
                            </div>
                            <div class="p-1 rounded-lg bg-white/70">
                                <span class="text-[8px] font-sans font-bold uppercase text-amber-600 block">Balance</span>
                                <strong class="text-[11px] font-bold" :class="cat.balance > 0 ? 'text-amber-700' : 'text-slate-400'" x-text="'₹' + cat.balance.toFixed(2)"></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Category Quick Receive Action Button -->
                    <div class="pt-1">
                        <button type="button"
                                x-show="cat.balance > 0"
                                @click="selectCategoryForPayment(cat)"
                                class="w-full py-1.5 px-2.5 rounded-xl text-[11px] font-black transition-all flex items-center justify-center gap-1 bg-white border border-slate-300 hover:bg-slate-900 hover:text-white text-slate-800 shadow-xs">
                            <i data-lucide="arrow-down-left" class="w-3.5 h-3.5 text-emerald-600"></i>
                            <span>Receive <span x-text="cat.name"></span> (₹<span x-text="cat.balance.toFixed(2)"></span>)</span>
                        </button>
                        <div x-show="cat.balance <= 0" class="py-1 text-center text-[10px] font-bold text-emerald-700 flex items-center justify-center gap-1">
                            <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i> Fully Received
                        </div>
                    </div>

                </div>
            </template>
            <div x-show="!payableByCategory || payableByCategory.length === 0" class="col-span-full py-6 text-center text-xs text-slate-400 font-medium">
                No configured payable entries recorded for this period.
            </div>
        </div>
    </div>

    <!-- Main Settlement Working Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left Column: Itemized Green Leaf Bills Selection & Clearing Table -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Category Totals Summary Bar -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 sm:gap-3">
                <div class="p-3.5 bg-white rounded-2xl border border-slate-200 space-y-0.5 shadow-sm">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Green Leaf Bills Category</span>
                    <strong class="font-mono text-sm font-extrabold text-slate-900" x-text="'₹' + totalGlBillsAmount.toFixed(2)"></strong>
                </div>

                <div class="p-3.5 bg-white rounded-2xl border border-slate-200 space-y-0.5 shadow-sm">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Vehicle Transport Category</span>
                    <strong class="font-mono text-sm font-extrabold text-purple-600" x-text="'₹' + totalVehicleAmount.toFixed(2)"></strong>
                </div>

                <div class="p-3.5 bg-white rounded-2xl border border-slate-200 space-y-0.5 shadow-sm">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Selected Items to Clear</span>
                    <strong class="font-mono text-sm font-extrabold text-emerald-600" x-text="'₹' + selectedTotal.toFixed(2)"></strong>
                </div>
            </div>

            <!-- Green Leaf Daily Bills & Expenses Selector Table -->
            <div class="white-card p-6 rounded-3xl space-y-4 shadow-xl border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <i data-lucide="check-square" class="w-4 h-4 text-slate-900"></i> Select Bills & Expenses to Clear
                        </h3>
                        <p class="text-xs text-slate-500">Check specific Green Leaf bills or vehicle expenses below to clear against shop payments.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="toggleSelectAll()" class="px-2.5 py-1 text-[11px] font-extrabold bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg transition-all">
                            <span x-text="selectedIds.length === pendingItems.length && pendingItems.length > 0 ? 'Deselect All' : 'Select All'"></span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left text-xs min-w-[550px]">
                        <thead>
                            <tr class="text-slate-600 bg-slate-100/80 border-b border-slate-200 uppercase tracking-wider font-bold">
                                <th class="py-3 px-3 text-center w-10">Select</th>
                                <th class="py-3 px-3">Date</th>
                                <th class="py-3 px-3">Category / Entry Type</th>
                                <th class="py-3 px-3">Notes</th>
                                <th class="py-3 px-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono text-slate-800">
                            <template x-for="item in pendingItems" :key="item.id">
                                <tr class="hover:bg-slate-50 transition-all cursor-pointer" @click="toggleItem(item.id)">
                                    <td class="py-3 px-3 text-center" @click.stop>
                                        <input type="checkbox" :value="item.id" x-model="selectedIds" class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900 cursor-pointer">
                                    </td>
                                    <td class="py-3 px-3 font-bold text-slate-900" x-text="item.business_date"></td>
                                    <td class="py-3 px-3 font-sans font-semibold text-slate-900">
                                        <span x-text="item.entry_type ? item.entry_type.name : item.entry_type_id"></span>
                                        <span x-show="item.entry_type && item.entry_type.code === 'purchase_bill'" class="ml-1 px-1.5 py-0.5 text-[9px] bg-amber-100 text-amber-800 border border-amber-200 rounded font-bold">GL Bill</span>
                                        <span x-show="item.entry_type && item.entry_type.code === 'vehicle'" class="ml-1 px-1.5 py-0.5 text-[9px] bg-purple-100 text-purple-800 border border-purple-200 rounded font-bold">Vehicle</span>
                                    </td>
                                    <td class="py-3 px-3 font-sans text-slate-600 text-[11px]" x-text="item.notes || '-'"></td>
                                    <td class="py-3 px-3 text-right font-bold text-slate-900" x-text="'₹' + parseFloat(item.amount).toFixed(2)"></td>
                                </tr>
                            </template>
                            <tr x-show="pendingItems.length === 0">
                                <td colspan="5" class="py-8 text-center text-slate-400 font-sans">No pending bills or expenses for this shop in this timeframe.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-200">
                    <span class="text-xs text-slate-500 font-medium">Selected <strong class="font-mono text-slate-900" x-text="selectedIds.length"></strong> items totaling <strong class="font-mono text-emerald-600" x-text="'₹' + selectedTotal.toFixed(2)"></strong></span>
                    <button @click="submitClearSelectedBills()" :disabled="selectedIds.length === 0" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 disabled:opacity-40 text-white font-extrabold text-xs shadow-md transition-all flex items-center gap-1.5">
                        <i data-lucide="check" class="w-4 h-4"></i> Clear Selected Bills & Items
                    </button>
                </div>
            </div>

            <!-- Recent Settlement Logs for Shop -->
            <div class="white-card p-6 rounded-3xl space-y-4 shadow-xl border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i data-lucide="history" class="w-4 h-4 text-slate-900"></i> Recent Payment & Settlement Logs
                    </h3>
                    <span class="text-xs text-slate-500 font-mono font-medium" x-text="settlementHistory.length + ' history logs'"></span>
                </div>

                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-600 bg-slate-100/80 border-b border-slate-200 uppercase tracking-wider font-bold">
                                <th class="py-2.5 px-3">Date</th>
                                <th class="py-2.5 px-3">Entry Type</th>
                                <th class="py-2.5 px-3 text-right">Settled Amount</th>
                                <th class="py-2.5 px-3">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono text-slate-800">
                            <template x-for="h in settlementHistory" :key="h.id">
                                <tr class="hover:bg-slate-50">
                                    <td class="py-2.5 px-3 font-bold text-slate-900" x-text="h.business_date"></td>
                                    <td class="py-2.5 px-3 font-sans font-semibold text-slate-900" x-text="h.entry_type ? h.entry_type.name : h.entry_type_id"></td>
                                    <td class="py-2.5 px-3 text-right font-bold text-emerald-600" x-text="'₹' + parseFloat(h.amount).toFixed(2)"></td>
                                    <td class="py-2.5 px-3 font-sans text-slate-500 text-[11px]" x-text="h.notes || '-'"></td>
                                </tr>
                            </template>
                            <tr x-show="settlementHistory.length === 0">
                                <td colspan="4" class="py-6 text-center text-slate-400 font-sans">No settlement history recorded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Right Column: Step 1 - Accept Payment Only Form -->
        <div class="space-y-6">
            <div class="white-card p-6 rounded-3xl space-y-5 shadow-xl border border-slate-200">
                <div class="border-b border-slate-200 pb-3">
                    <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                        <i data-lucide="wallet" class="w-5 h-5 text-emerald-600"></i> Step 1: Accept Payment Only
                    </h3>
                    <p class="text-xs text-slate-500 font-medium mt-0.5">Records payment received from {{ $currentShop->name }} (category-wise or lump sum) into company bank/cash account.</p>
                </div>

                <form @submit.prevent="submitAcceptPaymentOnly()" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Business Date</label>
                        <input type="date" id="set-form-date" :value="headerDate" @change="onDateChange($event.target.value)" class="w-full bg-slate-50 text-xs font-bold text-slate-900 px-3.5 py-2.5 rounded-xl border border-slate-300">
                    </div>

                    <!-- Payment Allocation / Category Target Selector -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Payment Allocation / Target</label>
                        <select x-model="selectedCategoryCode" @change="onCategorySelectChange()" class="w-full bg-white text-xs font-bold text-slate-900 px-3.5 py-2.5 rounded-xl border-2 border-slate-300 focus:border-slate-900">
                            <option value="all">All Configured Categories (Lump Sum) — Balance: ₹<span x-text="payableBalance.toFixed(2)"></span></option>
                            <template x-for="cat in payableByCategory.filter(c => c.balance > 0)" :key="cat.code || cat.name">
                                <option :value="cat.code" x-text="cat.name + ' (Balance: ₹' + cat.balance.toFixed(2) + ' / Recv: ₹' + cat.received_amount.toFixed(2) + ')'"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700">Total Payment Received from Shop (₹)</label>
                            <button type="button" @click="prefillConfiguredPayable()" class="text-[10px] font-bold text-amber-700 hover:text-amber-800 underline">
                                Use Remaining (₹<span x-text="payableBalance.toFixed(2)"></span>)
                            </button>
                        </div>
                        <input type="number" step="0.01" id="set-form-total-amount" placeholder="0.00" required class="w-full bg-white text-base font-extrabold font-mono text-slate-900 px-4 py-3 rounded-2xl border-2 border-slate-300 focus:border-slate-900 focus:outline-none shadow-sm">
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">1. Settle Payable Buffer (₹)</label>
                            <input type="number" step="0.01" id="set-form-settle-amount" placeholder="0.00" class="w-full bg-slate-50 text-xs font-mono font-bold text-slate-900 px-3 py-2 rounded-xl border border-slate-300">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">2. Top-up Petty Float (₹)</label>
                            <input type="number" step="0.01" id="set-form-petty-amount" placeholder="0.00" class="w-full bg-slate-50 text-xs font-mono font-bold text-slate-900 px-3 py-2 rounded-xl border border-slate-300">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Credited Company Account (Bank / Cash)</label>
                        <select id="set-form-company-account" class="w-full bg-white text-xs font-bold text-slate-900 px-3.5 py-2.5 rounded-xl border-2 border-slate-300 focus:border-slate-900">
                            @foreach($companyAccounts as $acc)
                                <option value="{{ $acc->id }}" {{ $acc->is_default ? 'selected' : '' }}>
                                    {{ $acc->name }} ({{ strtoupper($acc->account_type) }}) {{ $acc->is_default ? '— Default Account' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Payment Memo / Notes</label>
                        <input type="text" id="set-form-notes" placeholder="e.g. Collection received via Cheque / Cash / UPI" class="w-full bg-slate-50 text-xs font-medium text-slate-900 px-3.5 py-2.5 rounded-xl border border-slate-300">
                    </div>

                    <button type="submit" class="w-full py-3.5 rounded-2xl bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs shadow-lg transition-all flex items-center justify-center gap-2">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i> Accept Payment (Credit Company Account)
                    </button>
                </form>
            </div>

            <!-- Company Accounts Balances Matrix Card -->
            <div class="white-card p-6 rounded-3xl space-y-4 shadow-xl border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                    <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                        <i data-lucide="landmark" class="w-4 h-4 text-emerald-600"></i> Company Bank & Cash Balances
                    </h3>
                </div>

                <div class="space-y-3">
                    @foreach($companyAccounts as $acc)
                        <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="h-9 w-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-700 shadow-sm">
                                    @if($acc->account_type == 'bank')
                                        <i data-lucide="landmark" class="w-4 h-4"></i>
                                    @elseif($acc->account_type == 'cash')
                                        <i data-lucide="wallet" class="w-4 h-4"></i>
                                    @else
                                        <i data-lucide="smartphone" class="w-4 h-4"></i>
                                    @endif
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-900">{{ $acc->name }}</h4>
                                    <span class="text-[10px] text-slate-500 font-mono">{{ $acc->bank_name ?: strtoupper($acc->account_type) }} • {{ $acc->account_number ?: 'DEFAULT' }}</span>
                                </div>
                            </div>
                            <strong class="font-mono text-xs font-extrabold text-emerald-600">₹{{ number_format($acc->current_balance, 2) }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>

</div>
@endsection

@push('scripts')
<script>
    const currentShopId = {{ $currentShop->shop_id }};
    let currentDate = '{{ request('date', request('business_date', today()->toDateString())) }}';

    function settlementPageApp() {
        return {
            timeframe: 'daily',
            headerDate: currentDate,
            customStartDate: currentDate,
            customEndDate: currentDate,
            pendingItems: [],
            settlementHistory: [],
            selectedIds: [],
            payableTotal: 0,
            payableReceivedTotal: 0,
            payableBalance: 0,
            payableByCategory: [],
            payableRows: [],
            selectedCategoryCode: 'all',
            snapshot: {},

            init() {
                this.loadData();
            },

            setTimeframe(tf) {
                this.timeframe = tf;
                this.loadData();
            },

            changeDate(newDate) {
                this.headerDate = newDate;
                currentDate = newDate;
                this.loadData();
            },

            onDateChange(newDate) {
                this.changeDate(newDate);
            },

            get totalGlBillsAmount() {
                return (this.pendingItems || [])
                    .filter(i => i.entry_type && (i.entry_type.code === 'purchase_bill' || i.entry_type.category === 'bill'))
                    .reduce((acc, i) => acc + parseFloat(i.amount || 0), 0);
            },

            get totalVehicleAmount() {
                return (this.pendingItems || [])
                    .filter(i => i.entry_type && i.entry_type.code === 'vehicle')
                    .reduce((acc, i) => acc + parseFloat(i.amount || 0), 0);
            },

            get selectedTotal() {
                return (this.pendingItems || [])
                    .filter(i => this.selectedIds.includes(i.id))
                    .reduce((acc, i) => acc + parseFloat(i.amount || 0), 0);
            },

            get netDuePosition() {
                const payable = parseFloat(this.payableBalance || 0);
                const pending = parseFloat(this.snapshot?.closing_company_pending || 0);
                const bills = parseFloat(this.totalGlBillsAmount || 0);
                return (payable + pending) - bills;
            },

            toggleItem(id) {
                if (this.selectedIds.includes(id)) {
                    this.selectedIds = this.selectedIds.filter(i => i !== id);
                } else {
                    this.selectedIds.push(id);
                }
            },

            toggleSelectAll() {
                if (this.selectedIds.length === this.pendingItems.length && this.pendingItems.length > 0) {
                    this.selectedIds = [];
                } else {
                    this.selectedIds = this.pendingItems.map(i => i.id);
                }
            },

            selectCategoryForPayment(cat) {
                this.selectedCategoryCode = cat.code;
                const amt = cat.balance > 0 ? cat.balance.toFixed(2) : cat.recorded_amount.toFixed(2);
                const totalInput = document.getElementById('set-form-total-amount');
                const settleInput = document.getElementById('set-form-settle-amount');
                const notesInput = document.getElementById('set-form-notes');
                if (totalInput) totalInput.value = amt;
                if (settleInput) settleInput.value = amt;
                if (notesInput) notesInput.value = `Payment received for ${cat.name}`;
            },

            onCategorySelectChange() {
                if (this.selectedCategoryCode === 'all') {
                    this.prefillConfiguredPayable();
                    const notesInput = document.getElementById('set-form-notes');
                    if (notesInput) notesInput.value = 'Lump-sum collection received';
                } else {
                    const cat = this.payableByCategory.find(c => c.code === this.selectedCategoryCode);
                    if (cat) {
                        this.selectCategoryForPayment(cat);
                    }
                }
            },

            prefillConfiguredPayable() {
                const amt = this.payableBalance > 0 ? this.payableBalance.toFixed(2) : (this.payableTotal > 0 ? this.payableTotal.toFixed(2) : '0.00');
                const totalInput = document.getElementById('set-form-total-amount');
                const settleInput = document.getElementById('set-form-settle-amount');
                if (totalInput) totalInput.value = amt;
                if (settleInput) settleInput.value = amt;
            },

            async loadData() {
                try {
                    let url = `/admin/cashbook/api/shop-data?shop_id=${currentShopId}&business_date=${this.headerDate}&timeframe=${this.timeframe}`;
                    if (this.timeframe === 'custom') {
                        url += `&start_date=${this.customStartDate}&end_date=${this.customEndDate}`;
                    }

                    const res = await fetch(url);
                    const data = await res.json();

                    if (data.success) {
                        this.snapshot = data.snapshot || {};
                        this.payableTotal = parseFloat(data.payable_total || 0);
                        this.payableReceivedTotal = parseFloat(data.payable_received_total || 0);
                        this.payableBalance = parseFloat(data.payable_balance !== undefined ? data.payable_balance : (this.payableTotal - this.payableReceivedTotal));
                        this.payableByCategory = data.payable_by_category || [];
                        this.payableRows = data.payable_rows || [];

                        // All pending Green Leaf bills & vehicle expenses to clear for timeframe
                        const transactions = data.transactions && Array.isArray(data.transactions) ? data.transactions : (data.transactions?.data || []);
                        this.pendingItems = (transactions || []).filter(t => 
                            t.entry_type && (t.entry_type.code === 'purchase_bill' || t.entry_type.code === 'vehicle' || t.entry_type.category === 'expense' && t.funding_source === 'company')
                        );
                        
                        // Settlement History from month_transactions or transactions
                        this.settlementHistory = (data.month_transactions || []).filter(t => 
                            t.entry_type && (t.entry_type.category === 'settlement' || t.entry_type.code === 'shop_paid_company' || t.entry_type.code === 'sales_to_company')
                        );

                        // Prefill accept payment form with remaining balance
                        if (this.selectedCategoryCode === 'all') {
                            this.prefillConfiguredPayable();
                        } else {
                            const cat = this.payableByCategory.find(c => c.code === this.selectedCategoryCode);
                            if (cat && cat.balance > 0) {
                                this.selectCategoryForPayment(cat);
                            } else {
                                this.selectedCategoryCode = 'all';
                                this.prefillConfiguredPayable();
                            }
                        }

                        if (window.lucide) {
                            this.$nextTick(() => lucide.createIcons());
                        }
                    }
                } catch (err) {
                    showToast('Failed to load shop settlement data', 'error');
                }
            },

            async submitAcceptPaymentOnly() {
                const date = document.getElementById('set-form-date').value || this.headerDate;
                const companyAccountId = document.getElementById('set-form-company-account').value;
                const totalAmount = parseFloat(document.getElementById('set-form-total-amount').value || 0);
                const settleAmount = parseFloat(document.getElementById('set-form-settle-amount').value || 0);
                const pettyAmount = parseFloat(document.getElementById('set-form-petty-amount').value || 0);
                const notes = document.getElementById('set-form-notes').value;

                if (!totalAmount || totalAmount <= 0) {
                    showToast('Please enter a valid payment amount', 'error');
                    return;
                }

                try {
                    const res = await fetch('/admin/cashbook/api/accept-payment', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            shop_id: currentShopId,
                            business_date: date,
                            company_account_id: companyAccountId,
                            category_code: this.selectedCategoryCode,
                            settle_amount: settleAmount > 0 ? settleAmount : totalAmount,
                            petty_amount: pettyAmount,
                            notes: notes || 'Payment accepted from configured shop collections'
                        })
                    });

                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message || 'Payment accepted into company account!', 'success');
                        this.loadData();
                    } else {
                        showToast(data.message || 'Failed to accept payment', 'error');
                    }
                } catch (err) {
                    showToast('Server error accepting payment', 'error');
                }
            },

            async submitClearSelectedBills() {
                if (this.selectedIds.length === 0) return;

                try {
                    const date = document.getElementById('set-form-date').value || this.headerDate;
                    const amountToClear = this.selectedTotal;

                    const res = await fetch('/admin/cashbook/api/record-entry', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            shop_id: currentShopId,
                            business_date: date,
                            entry_type_code: 'sales_to_company',
                            amount: amountToClear,
                            funding_source: 'sales',
                            notes: `Cleared ${this.selectedIds.length} selected bills totaling ₹${amountToClear.toFixed(2)}`
                        })
                    });

                    const data = await res.json();
                    if (data.success) {
                        showToast(`Successfully cleared ${this.selectedIds.length} selected bills (₹${amountToClear.toFixed(2)})!`, 'success');
                        this.selectedIds = [];
                        this.loadData();
                    } else {
                        showToast(data.message || 'Failed to clear selected bills', 'error');
                    }
                } catch (err) {
                    showToast('Server error clearing bills', 'error');
                }
            }
        };
    }
</script>
@endpush
