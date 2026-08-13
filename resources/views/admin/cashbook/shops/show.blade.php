@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop #' . $currentShop->shop_id) . ' — Detailed Ledger')

@section('header_title')
    <i data-lucide="store" class="w-5 h-5 text-slate-900"></i> {{ $currentShop->name }} ({{ $currentShop->code }})
@endsection

@section('header_subtitle')
    Comprehensive daily ledger activity, collections, and financial balance details.
@endsection

@section('header_actions')
    <button id="toggle-day-btn" onclick="handleToggleDay()" class="px-4 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-semibold text-xs transition-all flex items-center gap-1.5 shadow-sm">
        <i data-lucide="lock" class="w-3.5 h-3.5"></i> Close Day
    </button>
@endsection

@section('content')
<div x-data="shopDetailApp()" x-init="init()" class="space-y-6">

    <!-- Itemized Card Breakdown Modal -->
    <div x-show="showBreakdownModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm" x-transition.opacity>
        <div class="white-card max-w-2xl w-full p-6 rounded-3xl space-y-4 shadow-2xl mx-4 max-h-[85vh] flex flex-col" @click.away="showBreakdownModal = false">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 capitalize" x-text="breakdownType === 'sales' ? 'Total Sales Breakdown' : (breakdownType === 'expense' ? 'Total Expense Breakdown' : 'Net P/L Transactions Breakdown')"></h3>
                    <p class="text-xs text-slate-500 font-medium">Itemized entries for selected timeframe (<span class="capitalize font-bold text-slate-800" x-text="timeframe"></span>).</p>
                </div>
                <button @click="showBreakdownModal = false" class="h-8 w-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-bold">✕</button>
            </div>

            <div class="overflow-y-auto flex-1 custom-scrollbar">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-slate-500 border-b border-slate-200 uppercase font-bold text-[10px] bg-slate-50">
                            <th class="py-2.5 px-3">Date</th>
                            <th class="py-2.5 px-3">Entry Type</th>
                            <th class="py-2.5 px-3">Funding Source</th>
                            <th class="py-2.5 px-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-mono">
                        <template x-for="item in getPaginatedBreakdownItems()" :key="item.id">
                            <tr class="hover:bg-slate-50">
                                <td class="py-2.5 px-3 font-bold text-slate-900" x-text="item.business_date"></td>
                                <td class="py-2.5 px-3 font-sans font-semibold text-slate-800" x-text="item.entry_type ? item.entry_type.name : item.entry_type_code"></td>
                                <td class="py-2.5 px-3 font-sans capitalize text-slate-500" x-text="item.funding_source || 'default'"></td>
                                <td class="py-2.5 px-3 text-right font-bold" :class="item.direction === 'income' ? 'text-emerald-600' : 'text-rose-600'" x-text="'₹' + parseFloat(item.amount).toFixed(2)"></td>
                            </tr>
                        </template>
                        <tr x-show="getBreakdownItems().length === 0">
                            <td colspan="4" class="py-6 text-center text-slate-400 font-sans">No matching entries found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 pt-3 flex items-center justify-between text-xs">
                <span class="text-xs text-slate-500 font-bold" x-text="getBreakdownItems().length + ' entries total'"></span>

                <div x-show="getBreakdownItems().length > modalPerPage" class="flex items-center gap-2">
                    <button @click="modalCurrentPage--" :disabled="modalCurrentPage === 1" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 disabled:opacity-40 disabled:cursor-not-allowed rounded-lg font-bold text-slate-700">
                        Prev
                    </button>
                    <span class="font-mono font-bold text-slate-700" x-text="modalCurrentPage + ' / ' + totalModalPages()"></span>
                    <button @click="modalCurrentPage++" :disabled="modalCurrentPage >= totalModalPages()" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 disabled:opacity-40 disabled:cursor-not-allowed rounded-lg font-bold text-slate-700">
                        Next
                    </button>
                </div>

                <button @click="showBreakdownModal = false" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-bold text-xs">Close</button>
            </div>
        </div>
    </div>

    <!-- Transaction Line Item Full Details Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm" x-transition.opacity>
        <div class="white-card max-w-lg w-full p-6 rounded-3xl space-y-5 shadow-2xl mx-4" @click.away="showModal = false">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div class="flex items-center gap-2">
                    <div class="h-8 w-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-800 font-extrabold text-xs font-mono">
                        #<span x-text="modalTx?.id"></span>
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900" x-text="modalTx?.entry_type ? modalTx.entry_type.name : modalTx?.entry_type_id"></h3>
                        <span class="text-[10px] text-slate-500 font-mono" x-text="modalTx?.business_date"></span>
                    </div>
                </div>
                <button @click="showModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-lg">✕</button>
            </div>

            <div class="space-y-4 text-xs font-sans">
                <!-- Basic Meta Grid -->
                <div class="grid grid-cols-2 gap-3 bg-slate-50 p-3.5 rounded-2xl border border-slate-200">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Transaction Amount</span>
                        <strong class="text-base font-mono font-extrabold text-slate-900">₹<span x-text="parseFloat(modalTx?.amount || 0).toFixed(2)"></span></strong>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Funding Source</span>
                        <span class="font-bold text-slate-800 uppercase px-2 py-0.5 bg-white border border-slate-200 rounded-md inline-block mt-0.5" x-text="modalTx?.funding_source || 'default'"></span>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Payable to Shop</span>
                        <strong class="text-base font-mono font-extrabold text-emerald-700">
                            ₹<span x-text="parseFloat(snapshot?.closing_shop_position || 0).toFixed(2)"></span>
                        </strong>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Collection Balance</span>
                        <strong class="text-base font-mono font-extrabold text-cyan-700">
                            ₹<span x-text="parseFloat((snapshot?.total_sales || 0) - (snapshot?.total_expense || 0)).toFixed(2)"></span>
                        </strong>
                    </div>
                </div>

                <!-- Ledger Impact Vectors Matrix -->
                <div class="space-y-2">
                    <span class="text-[11px] font-extrabold uppercase text-slate-500 tracking-wider block">Accounting Impact Vectors</span>
                    
                    <div class="grid grid-cols-2 gap-2">
                        <div class="p-3 bg-white rounded-xl border border-slate-200 space-y-0.5">
                            <span class="text-[10px] text-slate-500 block">P/L Delta Charge</span>
                            <strong class="font-mono text-sm" :class="parseFloat(modalTx?.pl_delta) < 0 ? 'text-rose-600' : (parseFloat(modalTx?.pl_delta) > 0 ? 'text-emerald-600' : 'text-slate-500')">
                                <span x-text="parseFloat(modalTx?.pl_delta || 0) > 0 ? '+' : ''"></span>₹<span x-text="parseFloat(modalTx?.pl_delta || 0).toFixed(2)"></span>
                            </strong>
                        </div>

                        <div class="p-3 bg-white rounded-xl border border-slate-200 space-y-0.5">
                            <span class="text-[10px] text-slate-500 block">Company Payable Delta</span>
                            <strong class="font-mono text-sm text-amber-600">
                                ₹<span x-text="parseFloat(modalTx?.settlement_delta || 0).toFixed(2)"></span>
                            </strong>
                        </div>

                        <div class="p-3 bg-white rounded-xl border border-slate-200 space-y-0.5">
                            <span class="text-[10px] text-slate-500 block">Petty Float Delta</span>
                            <strong class="font-mono text-sm text-sky-600">
                                ₹<span x-text="parseFloat(modalTx?.petty_delta || 0).toFixed(2)"></span>
                            </strong>
                        </div>

                        <div class="p-3 bg-white rounded-xl border border-slate-200 space-y-0.5">
                            <span class="text-[10px] text-slate-500 block">Company Pending Delta</span>
                            <strong class="font-mono text-sm text-purple-600">
                                ₹<span x-text="parseFloat(modalTx?.company_pending_delta || 0).toFixed(2)"></span>
                            </strong>
                        </div>
                    </div>
                </div>

                <!-- Notes / Reference -->
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-1">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Notes & Audit Memo</span>
                    <p class="text-slate-700 italic text-xs font-mono" x-text="modalTx?.notes || 'No notes provided for this transaction.'"></p>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button @click="showModal = false" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs shadow-sm">
                    Close Details
                </button>
            </div>
        </div>
    </div>

    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm" x-transition.opacity>
        <div class="white-card max-w-md w-full p-6 rounded-3xl space-y-4 shadow-2xl mx-4" @click.away="showEditModal = false">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <h3 class="text-sm font-extrabold text-slate-900">Update Entry Amount</h3>
                <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-lg">✕</button>
            </div>
            <p class="text-xs text-slate-500">Editing transaction <span class="font-mono font-bold text-slate-800" x-text="editingTx ? '#' + editingTx.id : '-' "></span></p>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">New Amount (₹)</label>
                <input type="number" step="0.01" min="0.01" x-model="editAmount" class="w-full bg-white text-xs font-mono font-semibold text-slate-800 px-3.5 py-2.5 rounded-xl border border-slate-300">
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button @click="showEditModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs">Cancel</button>
                <button @click="submitEdit()" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-xs">Update Entry</button>
            </div>
        </div>
    </div>

    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm" x-transition.opacity>
        <div class="white-card max-w-md w-full p-6 rounded-3xl space-y-4 shadow-2xl mx-4" @click.away="showDeleteModal = false">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <h3 class="text-sm font-extrabold text-slate-900">Delete Entry</h3>
                <button @click="showDeleteModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-lg">✕</button>
            </div>
            <p class="text-xs text-slate-500">This will permanently delete transaction <span class="font-mono font-bold text-slate-800" x-text="deletingTx ? '#' + deletingTx.id : '-' "></span> from the ledger.</p>
            <div class="flex justify-end gap-2 pt-1">
                <button @click="showDeleteModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs">Cancel</button>
                <button @click="submitDelete()" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs">Confirm Delete</button>
            </div>
        </div>
    </div>

    <!-- Export Cashbook Modal -->
    <div x-show="openExportModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-transition.opacity>
        <div class="white-card max-w-md w-full p-6 rounded-3xl space-y-4 shadow-2xl mx-4" @click.away="openExportModal = false">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-600">Export Ledger</p>
                    <h3 class="text-base font-extrabold text-slate-900">Export Cashbook Statement</h3>
                </div>
                <button @click="openExportModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-lg">✕</button>
            </div>

            <form @submit.prevent="triggerExport()" class="space-y-4 text-xs">
                <!-- Timeframe Selection -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Date Range / Period</label>
                    <div class="grid grid-cols-4 gap-2">
                        <button type="button" @click="exportForm.timeframe = 'daily'" :class="exportForm.timeframe === 'daily' ? 'bg-slate-900 text-white font-bold' : 'bg-slate-50 text-slate-700 border border-slate-200'" class="py-2 px-2 rounded-xl text-xs font-semibold text-center transition">
                            Day
                        </button>
                        <button type="button" @click="exportForm.timeframe = 'weekly'" :class="exportForm.timeframe === 'weekly' ? 'bg-slate-900 text-white font-bold' : 'bg-slate-50 text-slate-700 border border-slate-200'" class="py-2 px-2 rounded-xl text-xs font-semibold text-center transition">
                            Week
                        </button>
                        <button type="button" @click="exportForm.timeframe = 'monthly'" :class="exportForm.timeframe === 'monthly' ? 'bg-slate-900 text-white font-bold' : 'bg-slate-50 text-slate-700 border border-slate-200'" class="py-2 px-2 rounded-xl text-xs font-semibold text-center transition">
                            Month
                        </button>
                        <button type="button" @click="exportForm.timeframe = 'custom'" :class="exportForm.timeframe === 'custom' ? 'bg-slate-900 text-white font-bold' : 'bg-slate-50 text-slate-700 border border-slate-200'" class="py-2 px-2 rounded-xl text-xs font-semibold text-center transition">
                            Custom
                        </button>
                    </div>
                </div>

                <!-- Custom Date Inputs -->
                <div x-show="exportForm.timeframe === 'custom'" class="grid grid-cols-2 gap-3 p-3 rounded-2xl bg-slate-50 border border-slate-200">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-600 mb-1">From Date</label>
                        <input type="date" x-model="exportForm.start_date" class="w-full bg-white text-xs font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-600 mb-1">To Date</label>
                        <input type="date" x-model="exportForm.end_date" class="w-full bg-white text-xs font-semibold text-slate-800 px-3 py-2 rounded-xl border border-slate-300">
                    </div>
                </div>

                <!-- Format Selection -->
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Export Format</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" @click="exportForm.format = 'pdf'" :class="exportForm.format === 'pdf' ? 'border-rose-500 bg-rose-50 text-rose-700 font-bold' : 'bg-slate-50 text-slate-700 border border-slate-200'" class="py-2.5 px-3 rounded-xl text-xs font-semibold text-center border flex items-center justify-center gap-1.5 transition">
                            <i data-lucide="file-text" class="w-4 h-4 text-rose-600"></i> PDF
                        </button>
                        <button type="button" @click="exportForm.format = 'csv'" :class="exportForm.format === 'csv' ? 'border-emerald-500 bg-emerald-50 text-emerald-700 font-bold' : 'bg-slate-50 text-slate-700 border border-slate-200'" class="py-2.5 px-3 rounded-xl text-xs font-semibold text-center border flex items-center justify-center gap-1.5 transition">
                            <i data-lucide="file-spreadsheet" class="w-4 h-4 text-emerald-600"></i> CSV
                        </button>
                        <button type="button" @click="exportForm.format = 'excel'" :class="exportForm.format === 'excel' ? 'border-sky-500 bg-sky-50 text-sky-700 font-bold' : 'bg-slate-50 text-slate-700 border border-slate-200'" class="py-2.5 px-3 rounded-xl text-xs font-semibold text-center border flex items-center justify-center gap-1.5 transition">
                            <i data-lucide="table" class="w-4 h-4 text-sky-600"></i> EXCEL
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-200">
                    <button type="button" @click="openExportModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="download" class="w-4 h-4"></i> Download Export
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- Shop Meta Overview Banner -->
    <div class="flex flex-col xl:flex-row items-start xl:items-center justify-between gap-4 white-card p-5 rounded-3xl shadow-sm">
        <div class="flex items-center gap-4">
            <div class="h-14 w-14 rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-900 shadow-sm shrink-0">
                <i data-lucide="store" class="w-7 h-7"></i>
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-extrabold text-slate-900">{{ $currentShop->name }}</h2>
                    <span id="dashboard-day-status" class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">Open</span>
                </div>
                <p class="text-xs text-slate-500 font-medium mt-0.5">
                    Real-time shop financial summary &amp; transaction log.
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

            <!-- Custom Date Range Picker (shown when custom is selected) -->
            <div x-show="timeframe === 'custom'" class="flex items-center gap-1.5 bg-slate-100 p-1.5 rounded-2xl border border-slate-200 text-xs">
                <input type="date" x-model="customStartDate" @change="loadData()" class="bg-white text-xs font-mono font-bold text-slate-800 px-2 py-1 rounded-xl border border-slate-300">
                <span class="text-slate-400 font-bold">to</span>
                <input type="date" x-model="customEndDate" @change="loadData()" class="bg-white text-xs font-mono font-bold text-slate-800 px-2 py-1 rounded-xl border border-slate-300">
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2">
                <button type="button" @click="openExportModal = true" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm flex items-center gap-1.5 transition-all">
                    <i data-lucide="download" class="w-4 h-4"></i> Export Cashbook
                </button>
                <a href="{{ route('admin.cashbook.shop.accept-payment', $currentShop->slug ?: $currentShop->shop_id) }}" class="px-3.5 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-900 font-semibold text-xs shadow-sm flex items-center gap-1.5">
                    <i data-lucide="wallet" class="w-4 h-4"></i> Accept Payment
                </a>
                <a href="{{ route('admin.cashbook.shop.post-entry', $currentShop->slug ?: $currentShop->shop_id) }}" class="px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs shadow-sm flex items-center gap-1.5">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Post Entry For Shop
                </a>
            </div>
        </div>
    </div>

    <!-- Daily Approval Queue -->
    <div class="white-card rounded-3xl p-6 space-y-4 shadow-xl">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3" :class="approvalQueueOpen ? 'border-b border-slate-200 pb-4' : ''">
            <div class="cursor-pointer flex-1" @click="approvalQueueOpen = !approvalQueueOpen">
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="badge-check" class="w-5 h-5 text-emerald-600"></i> Approval Queue
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Approve income and expense transactions day by day. Approved entries become locked for shop-side changes.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-3 py-1.5 rounded-xl border text-xs font-bold transition-all"
                      :class="pendingApprovalCount() > 0 ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-emerald-50 text-emerald-800 border-emerald-200'"
                      x-text="pendingApprovalCount() + ' total pending entries'"></span>

                <button type="button"
                        x-show="approvalQueueOpen && pendingApprovalCount() > 0"
                        @click="approvePendingForDay(selectedApprovalDay())"
                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-sm">
                    Approve All for Selected Day
                </button>

                <button type="button"
                        @click="approvalQueueOpen = !approvalQueueOpen"
                        class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                    <span x-text="approvalQueueOpen ? 'Collapse Queue' : 'Expand Queue'"></span>
                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200" :class="approvalQueueOpen ? 'rotate-180' : ''"></i>
                </button>
            </div>
        </div>

        <div x-show="approvalQueueOpen" class="space-y-4">
            <div class="rounded-2xl border px-4 py-3 transition-all" :class="pendingApprovalCount() > 0 ? 'border-amber-200 bg-amber-50/70' : 'border-emerald-200 bg-emerald-50/70'">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider" :class="pendingApprovalCount() > 0 ? 'text-amber-700' : 'text-emerald-700'">Approval Notification</p>
                        <p class="mt-0.5 text-sm font-bold text-slate-900" x-text="approvalNoticeText()"></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-white px-3 py-1 text-[11px] font-black uppercase tracking-wider text-slate-700 border border-slate-200" x-text="'Selected: ' + (selectedApprovalDate ? selectedApprovalDate.slice(8, 10) + '-' + selectedApprovalDate.slice(5, 7) : 'None')"></span>
                        <span class="rounded-full bg-white px-3 py-1 text-[11px] font-black uppercase tracking-wider border" :class="pendingApprovalCount() > 0 ? 'text-amber-700 border-amber-200' : 'text-emerald-700 border-emerald-200'" x-text="pendingApprovalCount() + ' TOTAL PENDING'"></span>
                    </div>
                </div>
            </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="xl:col-span-1 space-y-3 max-h-[34rem] overflow-y-auto pr-1 custom-scrollbar">
                <template x-for="day in approvalDays()" :key="day.date">
                    <button
                        type="button"
                        @click="changeDate(day.date)"
                        class="w-full text-left rounded-2xl border p-4 transition"
                        :class="selectedApprovalDate === day.date ? 'border-emerald-300 bg-emerald-50 shadow-sm' : 'border-slate-200 bg-white hover:bg-slate-50'"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-extrabold text-slate-900" x-text="day.label"></p>
                                <p class="text-[11px] font-semibold text-slate-500" x-text="day.count + ' pending entries'"></p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider" :class="day.count > 0 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'" x-text="day.count > 0 ? 'Pending' : 'Approved'"></span>
                        </div>
                    </button>
                </template>
            </div>

            <div class="xl:col-span-2 rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 pb-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Selected Day</p>
                        <h4 class="text-sm font-bold text-slate-900" x-text="selectedApprovalDayLabel()"></h4>
                    </div>
                    <button type="button" @click="approvePendingForDay(selectedApprovalDate)" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs">
                        Approve Day
                    </button>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" @click="changeDate(todayDate)" class="rounded-full border px-3 py-1.5 text-[11px] font-black uppercase tracking-wider transition" :class="selectedApprovalDate === todayDate ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">
                        Today
                    </button>
                    <button type="button" @click="changeDate(shiftApprovalDate(-1))" class="rounded-full border px-3 py-1.5 text-[11px] font-black uppercase tracking-wider transition" :class="selectedApprovalDate === shiftApprovalDate(-1) ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'">
                        Yesterday
                    </button>
                    <div class="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5">
                        <span class="text-[11px] font-black uppercase tracking-wider text-slate-500">Date</span>
                        <input type="date" x-model="selectedApprovalDate" @change="changeDate(selectedApprovalDate)" class="h-6 border-0 bg-transparent p-0 text-xs font-bold text-slate-800 focus:outline-none">
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <template x-for="day in approvalDays()" :key="'pill-' + day.date">
                        <button
                            type="button"
                            @click="changeDate(day.date)"
                            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[11px] font-black uppercase tracking-wider transition"
                            :class="selectedApprovalDate === day.date ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                        >
                            <span x-text="day.date.slice(8, 10) + '-' + day.date.slice(5, 7)"></span>
                            <span class="rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-black text-amber-700" x-text="day.count"></span>
                        </button>
                    </template>
                    <span x-show="approvalDays().length === 0" class="text-xs font-semibold text-slate-400">No pending approval days.</span>
                </div>

                <div class="mt-4 space-y-3 max-h-[28rem] overflow-y-auto custom-scrollbar">
                    <template x-for="tx in pendingTransactionsForSelectedDay()" :key="tx.id">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-extrabold text-slate-900" x-text="tx.entry_type ? tx.entry_type.name : tx.entry_type_id"></p>
                                        <span class="rounded-full border px-2 py-0.5 text-[10px] font-black uppercase tracking-wider" :class="tx.direction === 'income' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'" x-text="tx.direction"></span>
                                    </div>
                                    <p class="text-[11px] font-semibold text-slate-500">#<span x-text="tx.id"></span> · <span x-text="tx.funding_source || 'default'"></span> · <span x-text="tx.business_date"></span></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-black text-slate-900" x-text="'₹' + parseFloat(tx.amount).toFixed(2)"></p>
                                    <p class="text-[11px] font-bold text-amber-700 capitalize" x-text="tx.status_label || tx.status"></p>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" @click="approveSingle(tx)" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-[11px] font-bold">
                                    Approve
                                </button>
                                <button type="button" @click="openModal(tx)" class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-700 text-[11px] font-bold hover:bg-slate-50">
                                    Details
                                </button>
                            </div>
                        </div>
                    </template>
                    <div x-show="pendingTransactionsForSelectedDay().length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-xs font-semibold text-slate-400">
                        No pending transactions for this day.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Snapshot Metrics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
        <div class="white-card p-4 rounded-2xl space-y-1 cursor-pointer hover:border-emerald-400 hover:shadow-md transition-all" @click="openBreakdownModal('sales')">
            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Total Sales</span>
            <div id="stat-sales" class="text-xl font-bold font-mono text-slate-900">₹0.00</div>
            <span class="text-[10px] text-emerald-600 font-semibold block flex items-center gap-1">Gross Inflow <i data-lucide="chevron-right" class="w-3 h-3"></i></span>
        </div>

        <div class="white-card p-4 rounded-2xl space-y-1 cursor-pointer hover:border-rose-400 hover:shadow-md transition-all" @click="openBreakdownModal('expense')">
            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Total Expense</span>
            <div id="stat-expense" class="text-xl font-bold font-mono text-slate-900">₹0.00</div>
            <span class="text-[10px] text-rose-600 font-semibold block flex items-center gap-1">P/L Chargeable <i data-lucide="chevron-right" class="w-3 h-3"></i></span>
        </div>

        <div class="white-card p-4 rounded-2xl space-y-1 cursor-pointer hover:border-brand-400 hover:shadow-md transition-all" @click="openBreakdownModal('net_pl')">
            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Net P/L</span>
            <div id="stat-net-pl" class="text-xl font-bold font-mono text-slate-900">₹0.00</div>
            <span id="stat-net-pl-sub" class="text-[10px] text-slate-500 font-medium block flex items-center gap-1">Income - Expense <i data-lucide="chevron-right" class="w-3 h-3"></i></span>
        </div>

        <div class="white-card p-4 rounded-2xl space-y-1 cursor-pointer hover:border-sky-400 hover:shadow-md transition-all" @click="activeTab = 'petty_ledger'">
            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Closing Petty</span>
            <div id="stat-petty" class="text-xl font-bold font-mono text-slate-900">₹0.00</div>
            <span class="text-[10px] text-sky-600 font-semibold block flex items-center gap-1">Petty Float <i data-lucide="chevron-right" class="w-3 h-3"></i></span>
        </div>

        <div class="white-card p-4 rounded-2xl space-y-1 cursor-pointer hover:border-amber-400 hover:shadow-md transition-all" @click="activeTab = 'company_payables'">
            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Shop Position</span>
            <div id="stat-settlement" class="text-xl font-bold font-mono text-slate-900">₹0.00</div>
            <span id="stat-settlement-sub" class="text-[10px] text-amber-600 font-semibold block flex items-center gap-1">Payable to Company <i data-lucide="chevron-right" class="w-3 h-3"></i></span>
        </div>

        <div class="white-card p-4 rounded-2xl space-y-1 cursor-pointer hover:border-purple-400 hover:shadow-md transition-all" @click="activeTab = 'company_payables'">
            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Company Pending</span>
            <div id="stat-company-pending" class="text-xl font-bold font-mono text-slate-900">₹0.00</div>
            <span class="text-[10px] text-purple-600 font-semibold block flex items-center gap-1">Reimbursements <i data-lucide="chevron-right" class="w-3 h-3"></i></span>
        </div>

        <div class="white-card p-4 rounded-2xl space-y-1 cursor-pointer hover:border-cyan-400 hover:shadow-md transition-all" @click="activeTab = 'company_payables'">
            <span class="text-[11px] font-bold uppercase text-slate-500 tracking-wider">Collection Balance</span>
            <div id="stat-collection-balance" class="text-xl font-bold font-mono text-slate-900">₹0.00</div>
            <span class="text-[10px] text-cyan-600 font-semibold block flex items-center gap-1">Sales - Expense <i data-lucide="chevron-right" class="w-3 h-3"></i></span>
        </div>
    </div>


    <!-- Alpine.js Shop Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-200 pb-1">
        <button @click="activeTab = 'daily_entries'" :class="activeTab === 'daily_entries' ? 'bg-slate-900 text-white font-extrabold' : 'bg-white text-slate-600 hover:text-slate-900'" class="px-4 py-2 text-xs rounded-xl font-bold transition-all flex items-center gap-1.5 shadow-sm border border-slate-200">
            <i data-lucide="list-ordered" class="w-4 h-4"></i> All Daily Entries
        </button>
        <button @click="activeTab = 'petty_ledger'" :class="activeTab === 'petty_ledger' ? 'bg-slate-900 text-white font-extrabold' : 'bg-white text-slate-600 hover:text-slate-900'" class="px-4 py-2 text-xs rounded-xl font-bold transition-all flex items-center gap-1.5 shadow-sm border border-slate-200">
            <i data-lucide="wallet" class="w-4 h-4"></i> Petty Cash Float Ledger
        </button>
        <button @click="activeTab = 'company_payables'" :class="activeTab === 'company_payables' ? 'bg-slate-900 text-white font-extrabold' : 'bg-white text-slate-600 hover:text-slate-900'" class="px-4 py-2 text-xs rounded-xl font-bold transition-all flex items-center gap-1.5 shadow-sm border border-slate-200">
            <i data-lucide="truck" class="w-4 h-4"></i> Company Payables & Vehicles
        </button>
        <button @click="activeTab = 'payments_breakdown'" :class="activeTab === 'payments_breakdown' ? 'bg-slate-900 text-white font-extrabold' : 'bg-white text-slate-600 hover:text-slate-900'" class="px-4 py-2 text-xs rounded-xl font-bold transition-all flex items-center gap-1.5 shadow-sm border border-slate-200">
            <i data-lucide="credit-card" class="w-4 h-4"></i> Collections & Payment Methods
        </button>
    </div>


    <!-- ========================================================================= -->
    <!-- TAB 1: ALL DAILY ENTRIES TABLE WITH FULL DETAILS BUTTON -->
    <!-- ========================================================================= -->
    <div x-show="activeTab === 'daily_entries'" class="white-card rounded-3xl p-6 space-y-4 shadow-xl">
        <div x-show="collectionSummaries.length > 0" class="rounded-2xl border border-cyan-200 bg-cyan-50/70 p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-black text-slate-900">Collection Details</h3>
                    <p class="text-xs font-semibold text-slate-500">Configured income minus debit entries for this shop.</p>
                </div>
                <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-cyan-800" x-text="'Net ' + money(collectionNetTotal())"></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="text-[10px] font-black uppercase text-cyan-800">
                        <tr>
                            <th class="py-2 pr-3">Date</th>
                            <th class="py-2 pr-3">Collection</th>
                            <th class="py-2 pr-3 text-right">Income</th>
                            <th class="py-2 pr-3 text-right">Debit</th>
                            <th class="py-2 text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-cyan-100">
                        <template x-for="group in collectionSummaries" :key="group.reference_id">
                            <tr class="cursor-pointer hover:bg-white/70" @click="openCollectionBreakdown(group)">
                                <td class="py-2 pr-3 font-mono font-bold text-slate-800" x-text="group.business_date"></td>
                                <td class="py-2 pr-3 font-bold text-slate-900" x-text="group.name"></td>
                                <td class="py-2 pr-3 text-right font-mono font-black text-emerald-700" x-text="money(group.income)"></td>
                                <td class="py-2 pr-3 text-right font-mono font-black text-rose-700" x-text="money(group.expense)"></td>
                                <td class="py-2 text-right font-mono font-black text-cyan-900" x-text="money(group.net)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-slate-200 pb-3">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="list-ordered" class="w-4 h-4 text-slate-900"></i> Transaction Line Items
                </h3>
                <p class="text-xs text-slate-500 font-medium">Viewing <span class="capitalize font-bold text-slate-800" x-text="timeframe"></span> activity log for shop.</p>
            </div>
            
            <div class="flex items-center gap-3">
                <button type="button" @click="openExportModal = true" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-sm flex items-center gap-1.5 transition-all">
                    <i data-lucide="download" class="w-3.5 h-3.5"></i> Export
                </button>
                <!-- Daily / Weekly / Monthly / Custom Range Selector -->
                <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200">
                    <button @click="setTimeframe('daily')" :class="timeframe === 'daily' ? 'bg-slate-900 text-white shadow-sm font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'" class="px-3 py-1 text-xs rounded-lg transition-all">
                        Daily
                    </button>
                    <button @click="setTimeframe('weekly')" :class="timeframe === 'weekly' ? 'bg-slate-900 text-white shadow-sm font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'" class="px-3 py-1 text-xs rounded-lg transition-all">
                        Weekly
                    </button>
                    <button @click="setTimeframe('monthly')" :class="timeframe === 'monthly' ? 'bg-slate-900 text-white shadow-sm font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'" class="px-3 py-1 text-xs rounded-lg transition-all">
                        Monthly
                    </button>
                    <button @click="setTimeframe('custom')" :class="timeframe === 'custom' ? 'bg-slate-900 text-white shadow-sm font-extrabold' : 'text-slate-600 hover:text-slate-900 font-semibold'" class="px-3 py-1 text-xs rounded-lg transition-all">
                        Custom
                    </button>
                </div>

                <div x-show="timeframe === 'custom'" class="flex items-center gap-1.5 bg-slate-100 p-1 rounded-xl border border-slate-200 text-xs">
                    <input type="date" x-model="customStartDate" @change="loadData()" class="bg-white text-xs font-mono font-bold text-slate-800 px-2 py-0.5 rounded-lg border border-slate-300">
                    <span class="text-slate-400 font-bold">to</span>
                    <input type="date" x-model="customEndDate" @change="loadData()" class="bg-white text-xs font-mono font-bold text-slate-800 px-2 py-0.5 rounded-lg border border-slate-300">
                </div>
                <span class="text-xs text-slate-500 font-mono font-medium" x-text="(transactions || []).length + ' entries'"></span>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="text-slate-600 bg-slate-100/70 border-b border-slate-200 uppercase tracking-wider font-bold select-none">
                        <th @click="sortBy('business_date')" class="py-3 px-3 cursor-pointer hover:bg-slate-200/70 transition-all">
                            <div class="flex items-center gap-1">
                                <span>Date</span>
                                <span x-show="sortColumn === 'business_date'" x-text="sortAsc ? '▲' : '▼'" class="text-[10px] text-slate-900 font-black"></span>
                            </div>
                        </th>
                        <th @click="sortBy('entry_type')" class="py-3 px-3 cursor-pointer hover:bg-slate-200/70 transition-all">
                            <div class="flex items-center gap-1">
                                <span>Entry Type</span>
                                <span x-show="sortColumn === 'entry_type'" x-text="sortAsc ? '▲' : '▼'" class="text-[10px] text-slate-900 font-black"></span>
                            </div>
                        </th>
                        <th @click="sortBy('direction')" class="py-3 px-3 cursor-pointer hover:bg-slate-200/70 transition-all">
                            <div class="flex items-center gap-1">
                                <span>Direction</span>
                                <span x-show="sortColumn === 'direction'" x-text="sortAsc ? '▲' : '▼'" class="text-[10px] text-slate-900 font-black"></span>
                            </div>
                        </th>
                        <th @click="sortBy('amount')" class="py-3 px-3 text-right cursor-pointer hover:bg-slate-200/70 transition-all">
                            <div class="flex items-center justify-end gap-1">
                                <span>Amount</span>
                                <span x-show="sortColumn === 'amount'" x-text="sortAsc ? '▲' : '▼'" class="text-[10px] text-slate-900 font-black"></span>
                            </div>
                        </th>
                        <th @click="sortBy('funding_source')" class="py-3 px-3 cursor-pointer hover:bg-slate-200/70 transition-all">
                            <div class="flex items-center gap-1">
                                <span>Funding Source</span>
                                <span x-show="sortColumn === 'funding_source'" x-text="sortAsc ? '▲' : '▼'" class="text-[10px] text-slate-900 font-black"></span>
                            </div>
                        </th>
                        <th @click="sortBy('pl_delta')" class="py-3 px-3 text-right cursor-pointer hover:bg-slate-200/70 transition-all">
                            <div class="flex items-center justify-end gap-1">
                                <span>P/L Delta</span>
                                <span x-show="sortColumn === 'pl_delta'" x-text="sortAsc ? '▲' : '▼'" class="text-[10px] text-slate-900 font-black"></span>
                            </div>
                        </th>
                        <th class="py-3 px-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono text-slate-800">
                    <template x-for="t in getPaginatedTransactions()" :key="t.id">
                        <tr class="hover:bg-slate-50 transition-all">
                            <td class="py-3 px-3 font-mono font-bold text-slate-900" x-text="t.business_date"></td>
                            <td class="py-3 px-3 font-sans font-semibold text-slate-900">
                                <span x-text="t.entry_type ? t.entry_type.name : t.entry_type_id"></span>
                                <span x-show="t.generated_by_rule" class="ml-1 px-1.5 py-0.5 text-[9px] bg-purple-100 text-purple-700 border border-purple-200 rounded font-semibold">Auto-Paired</span>
                            </td>
                            <td class="py-3 px-3 capitalize text-slate-700 font-sans" x-text="t.direction"></td>
                            <td class="py-3 px-3 text-right font-bold text-slate-900" x-text="'₹' + parseFloat(t.amount).toFixed(2)"></td>
                            <td class="py-3 px-3 capitalize text-slate-600 font-sans" x-text="t.funding_source || '-'"></td>
                            <td class="py-3 px-3 text-right font-bold" :class="parseFloat(t.pl_delta) < 0 ? 'text-rose-600' : (parseFloat(t.pl_delta) > 0 ? 'text-emerald-600' : 'text-slate-400')">
                                <span x-text="(parseFloat(t.pl_delta) > 0 ? '+' : '') + parseFloat(t.pl_delta).toFixed(2)"></span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button @click="openModal(t)" class="px-2.5 py-1 text-[11px] font-bold bg-slate-900 hover:bg-slate-800 text-white rounded-lg shadow-sm">
                                        Details
                                    </button>
                                    <button @click="openEditModal(t)" class="px-2.5 py-1 text-[11px] font-bold bg-amber-100 hover:bg-amber-200 text-amber-800 rounded-lg border border-amber-200 shadow-sm">
                                        Edit
                                    </button>
                                    <button @click="openDeleteModal(t)" class="px-2.5 py-1 text-[11px] font-bold bg-rose-100 hover:bg-rose-200 text-rose-800 rounded-lg border border-rose-200 shadow-sm">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!transactions || transactions.length === 0">
                        <td colspan="7" class="py-8 text-center text-slate-400 font-sans">No transactions posted for this timeframe.</td>
                    </tr>
                </tbody>
                <tfoot x-show="transactions && transactions.length > 0" class="bg-slate-900 text-white font-bold border-t-2 border-slate-900">
                    <tr>
                        <td colspan="3" class="py-3.5 px-3 font-extrabold uppercase text-[11px] tracking-wider text-slate-200">
                            Total (<span x-text="transactions.length"></span> Entries)
                        </td>
                        <td class="py-3.5 px-3 text-right font-mono font-black text-sm text-emerald-400" x-text="'₹' + totalAmount.toFixed(2)"></td>
                        <td class="py-3.5 px-3 text-slate-400 font-sans text-[11px]">-</td>
                        <td class="py-3.5 px-3 text-right font-mono font-black text-sm" :class="totalPlDelta < 0 ? 'text-rose-400' : 'text-emerald-400'" x-text="(totalPlDelta > 0 ? '+' : '') + totalPlDelta.toFixed(2)"></td>
                        <td class="py-3.5 px-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Main Table Pagination Controls -->
        <div x-show="getSortedTransactions().length > perPage" class="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-200 pt-3 text-xs">
            <span class="text-slate-500 font-medium font-mono">
                Showing <span x-text="((currentPage - 1) * perPage) + 1"></span> - <span x-text="Math.min(currentPage * perPage, getSortedTransactions().length)"></span> of <span x-text="getSortedTransactions().length"></span> entries
            </span>

            <div class="flex items-center gap-1.5">
                <button @click="currentPage--" :disabled="currentPage === 1" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 disabled:opacity-40 disabled:cursor-not-allowed rounded-xl font-bold text-slate-700 transition-all flex items-center gap-1">
                    <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i> Prev
                </button>

                <div class="flex items-center gap-1">
                    <template x-for="p in totalTransactionPages()" :key="p">
                        <button @click="currentPage = p" :class="currentPage === p ? 'bg-slate-900 text-white font-extrabold shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-semibold'" class="h-7 w-7 rounded-lg text-xs transition-all">
                            <span x-text="p"></span>
                        </button>
                    </template>
                </div>

                <button @click="currentPage++" :disabled="currentPage >= totalTransactionPages()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 disabled:opacity-40 disabled:cursor-not-allowed rounded-xl font-bold text-slate-700 transition-all flex items-center gap-1">
                    Next <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                </button>
            </div>
        </div>
    </div>


    <!-- ========================================================================= -->
    <!-- TAB 2: DEDICATED PETTY CASH FLOAT LEDGER PAGE -->
    <!-- ========================================================================= -->
    <div x-show="activeTab === 'petty_ledger'" class="white-card rounded-3xl p-6 space-y-6 shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 pb-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="wallet" class="w-5 h-5 text-sky-600"></i> Petty Cash Float Ledger & Movement Log
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Comprehensive tracking of top-ups from sales/company and petty cash purchases for {{ $currentShop->name }}.</p>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 block">Current Petty Float</span>
                <span id="petty-tab-float" class="text-xl font-extrabold font-mono text-sky-600">₹0.00</span>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="text-slate-600 bg-slate-100/80 border-b border-slate-200 uppercase tracking-wider font-bold">
                        <th class="py-3 px-3">Date & ID</th>
                        <th class="py-3 px-3">Entry Type</th>
                        <th class="py-3 px-3">Movement Type</th>
                        <th class="py-3 px-3 text-right">Inflow / Top-up</th>
                        <th class="py-3 px-3 text-right">Outflow / Expense</th>
                        <th class="py-3 px-3 text-right">Petty Delta</th>
                        <th class="py-3 px-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono text-slate-800">
                    <template x-for="t in pettyEntries" :key="t.id">
                        <tr class="hover:bg-sky-50/40 transition-all">
                            <td class="py-3 px-3 font-mono text-slate-500">
                                <span class="font-bold text-slate-900" x-text="t.business_date"></span>
                                <span class="block text-[10px] text-slate-400" x-text="'#' + t.id"></span>
                            </td>
                            <td class="py-3 px-3 font-sans font-semibold text-slate-900" x-text="t.entry_type ? t.entry_type.name : t.entry_type_id"></td>
                            <td class="py-3 px-3">
                                <span x-show="parseFloat(t.petty_delta) > 0" class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800">Top-up (+Float)</span>
                                <span x-show="parseFloat(t.petty_delta) < 0" class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-100 text-rose-800">Expense (-Float)</span>
                            </td>
                            <td class="py-3 px-3 text-right font-bold text-emerald-600" x-text="parseFloat(t.petty_delta) > 0 ? '₹' + parseFloat(t.petty_delta).toFixed(2) : '-'"></td>
                            <td class="py-3 px-3 text-right font-bold text-rose-600" x-text="parseFloat(t.petty_delta) < 0 ? '₹' + Math.abs(parseFloat(t.petty_delta)).toFixed(2) : '-'"></td>
                            <td class="py-3 px-3 text-right font-extrabold text-sky-600" x-text="'₹' + parseFloat(t.petty_delta).toFixed(2)"></td>
                            <td class="py-3 px-3 text-center">
                                <button @click="openModal(t)" class="px-2.5 py-1 text-[11px] font-bold bg-slate-900 hover:bg-slate-800 text-white rounded-lg shadow-sm">
                                    Show Details
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!pettyEntries || pettyEntries.length === 0">
                        <td colspan="7" class="py-8 text-center text-slate-400 font-sans">No petty cash movements recorded.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>


    <!-- ========================================================================= -->
    <!-- TAB 3: COMPANY PAYABLES & VEHICLE EXPENSES BREAKDOWN -->
    <!-- ========================================================================= -->
    <div x-show="activeTab === 'company_payables'" class="white-card rounded-3xl p-6 space-y-6 shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 pb-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="truck" class="w-5 h-5 text-purple-600"></i> Company Payables & Vehicle Expense Reimbursements
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Track company-funded invoices, vehicle expenses, and reimbursements owed by the company for {{ $currentShop->name }}.</p>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="text-slate-600 bg-slate-100/80 border-b border-slate-200 uppercase tracking-wider font-bold">
                        <th class="py-3 px-3">Date & ID</th>
                        <th class="py-3 px-3">Expense / Entry Type</th>
                        <th class="py-3 px-3">Funding Type</th>
                        <th class="py-3 px-3 text-right">Incurred Amount</th>
                        <th class="py-3 px-3 text-right">Pending Delta</th>
                        <th class="py-3 px-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono text-slate-800">
                    <template x-for="t in companyPendingEntries" :key="t.id">
                        <tr class="hover:bg-purple-50/40 transition-all">
                            <td class="py-3 px-3 font-mono text-slate-500">
                                <span class="font-bold text-slate-900" x-text="t.business_date"></span>
                                <span class="block text-[10px] text-slate-400" x-text="'#' + t.id"></span>
                            </td>
                            <td class="py-3 px-3 font-sans font-semibold text-slate-900" x-text="t.entry_type ? t.entry_type.name : t.entry_type_id"></td>
                            <td class="py-3 px-3 uppercase text-slate-700 font-sans font-bold" x-text="t.funding_source || 'company'"></td>
                            <td class="py-3 px-3 text-right font-bold text-slate-900" x-text="'₹' + parseFloat(t.amount).toFixed(2)"></td>
                            <td class="py-3 px-3 text-right font-extrabold text-purple-600" x-text="'₹' + parseFloat(t.company_pending_delta).toFixed(2)"></td>
                            <td class="py-3 px-3 text-center">
                                <button @click="openModal(t)" class="px-2.5 py-1 text-[11px] font-bold bg-slate-900 hover:bg-slate-800 text-white rounded-lg shadow-sm">
                                    Show Details
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!companyPendingEntries || companyPendingEntries.length === 0">
                        <td colspan="6" class="py-8 text-center text-slate-400 font-sans">No company-funded expenses recorded.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>


    <!-- ========================================================================= -->
    <!-- TAB 4: COLLECTIONS & PAYMENT METHOD BREAKDOWN -->
    <!-- ========================================================================= -->
    <div x-show="activeTab === 'payments_breakdown'" class="white-card rounded-3xl p-6 space-y-6 shadow-xl">
        <div class="border-b border-slate-200 pb-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="credit-card" class="w-5 h-5 text-emerald-600"></i> Collections & Payment Methods Breakdown
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">Breakdown of gross sales collections by payment channel (Cash, Card, Paytm, UPI, Check).</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <template x-for="(amt, code) in paymentBreakdown" :key="code">
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider block" x-text="code"></span>
                        <strong class="text-lg font-mono font-extrabold text-slate-900" x-text="'₹' + parseFloat(amt).toFixed(2)"></strong>
                    </div>
                    <span class="px-2.5 py-1 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700">Received</span>
                </div>
            </template>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    const currentShopId = {{ $currentShop->shop_id }};
    const todayDate = @json(today()->toDateString());
    let currentDate = @json(request('date', request('business_date', today()->toDateString())));
    const shopEntryTypes = @json($entryTypes);

    function shopDetailApp() {
        return {
            activeTab: 'daily_entries',
            timeframe: 'daily',
            headerDate: currentDate,
            customStartDate: currentDate,
            customEndDate: currentDate,
            approvalQueueOpen: false,
            showModal: false,
            showEditModal: false,
            showDeleteModal: false,
            openExportModal: false,
            showBreakdownModal: false,
            breakdownType: 'sales',
            selectedApprovalDate: currentDate,
            modalTx: null,
            editingTx: null,
            deletingTx: null,
            editAmount: '',
            exportForm: {
                timeframe: 'daily',
                format: 'csv',
                start_date: currentDate,
                end_date: currentDate,
            },
            createForm: {
                business_date: currentDate,
                entry_type_code: '',
                amount: '',
                funding_source: '',
                notes: '',
            },
            sortColumn: 'business_date',
            sortAsc: false,
            currentPage: 1,
            perPage: 11,
            modalCurrentPage: 1,
            modalPerPage: 11,
            transactions: [],
            monthTransactions: [],
            pettyEntries: [],
            companyPendingEntries: [],
            collectionSummaries: [],
            collectionBreakdownRows: [],
            paymentBreakdown: {},
            settings: [],
            entryTypes: shopEntryTypes,
            snapshot: {},

            openBreakdownModal(type) {
                this.breakdownType = type;
                this.modalCurrentPage = 1;
                this.showBreakdownModal = true;
            },

            getBreakdownItems() {
                if (!this.transactions || !Array.isArray(this.transactions)) return [];
                if (this.breakdownType === 'collection') {
                    return this.collectionBreakdownRows || [];
                }
                if (this.breakdownType === 'sales') {
                    return this.transactions.filter(t => t.direction === 'income' || (t.entry_type && t.entry_type.category === 'income'));
                }
                if (this.breakdownType === 'expense') {
                    return this.transactions.filter(t => t.direction === 'expense' || (t.entry_type && t.entry_type.category === 'expense'));
                }
                return this.transactions;
            },

            getPaginatedBreakdownItems() {
                const list = this.getBreakdownItems();
                const start = (this.modalCurrentPage - 1) * this.modalPerPage;
                return list.slice(start, start + this.modalPerPage);
            },

            totalModalPages() {
                return Math.ceil(this.getBreakdownItems().length / this.modalPerPage) || 1;
            },

            sortBy(col) {
                if (this.sortColumn === col) {
                    this.sortAsc = !this.sortAsc;
                } else {
                    this.sortColumn = col;
                    this.sortAsc = (col === 'entry_type' || col === 'direction' || col === 'funding_source');
                }
                this.currentPage = 1;
            },

            getSortedTransactions() {
                if (!this.transactions || !Array.isArray(this.transactions)) return [];
                const list = [...this.transactions];
                const col = this.sortColumn;
                const asc = this.sortAsc;

                return list.sort((a, b) => {
                    let valA = a[col];
                    let valB = b[col];

                    if (col === 'entry_type') {
                        valA = a.entry_type ? a.entry_type.name : (a.entry_type_code || '');
                        valB = b.entry_type ? b.entry_type.name : (b.entry_type_code || '');
                    } else if (col === 'amount' || col === 'pl_delta' || col === 'id') {
                        valA = parseFloat(valA) || 0;
                        valB = parseFloat(valB) || 0;
                    } else {
                        valA = String(valA || '').toLowerCase();
                        valB = String(valB || '').toLowerCase();
                    }

                    if (valA < valB) return asc ? -1 : 1;
                    if (valA > valB) return asc ? 1 : -1;
                    return 0;
                });
            },

            getPaginatedTransactions() {
                const list = this.getSortedTransactions();
                const start = (this.currentPage - 1) * this.perPage;
                return list.slice(start, start + this.perPage);
            },

            totalTransactionPages() {
                return Math.ceil(this.getSortedTransactions().length / this.perPage) || 1;
            },

            get totalAmount() {
                return (this.transactions || []).reduce((sum, t) => sum + (parseFloat(t.amount) || 0), 0);
            },

            get totalPlDelta() {
                return (this.transactions || []).reduce((sum, t) => sum + (parseFloat(t.pl_delta) || 0), 0);
            },

            collectionNetTotal() {
                return (this.collectionSummaries || []).reduce((sum, group) => sum + (parseFloat(group.net) || 0), 0);
            },

            money(value) {
                return '₹' + parseFloat(value || 0).toFixed(2);
            },

            openCollectionBreakdown(group) {
                this.breakdownType = 'collection';
                this.collectionBreakdownRows = group.lines || [];
                this.showBreakdownModal = true;
            },

            init() {
                if (this.entryTypes.length > 0) {
                    this.createForm.entry_type_code = this.entryTypes[0].code;
                }
                this.loadData();
            },

            triggerExport() {
                const params = new URLSearchParams({
                    format: this.exportForm.format,
                    timeframe: this.exportForm.timeframe,
                    date: currentDate,
                    start_date: this.exportForm.start_date || currentDate,
                    end_date: this.exportForm.end_date || currentDate,
                });
                const url = `{{ route('admin.cashbook.shop.export', $currentShop->slug ?: $currentShop->shop_id) }}?${params.toString()}`;
                if (this.exportForm.format === 'pdf') {
                    window.open(url, '_blank');
                } else {
                    window.location.href = url;
                }
                this.openExportModal = false;
            },

            changeDate(d) {
                if (!d) return;
                currentDate = d;
                this.headerDate = d;
                this.createForm.business_date = d;
                this.exportForm.start_date = d;
                this.exportForm.end_date = d;
                this.selectedApprovalDate = d;

                const globalInput = document.getElementById('global-date-input');
                if (globalInput) globalInput.value = d;

                const url = new URL(window.location.href);
                url.searchParams.set('date', d);
                window.history.replaceState({}, '', url.toString());

                this.loadData();
            },

            setTimeframe(tf) {
                this.timeframe = tf;
                this.loadData();
            },

            openModal(tx) {
                this.modalTx = tx;
                this.showModal = true;
            },

            openEditModal(tx) {
                this.editingTx = tx;
                this.editAmount = parseFloat(tx.amount).toFixed(2);
                this.showEditModal = true;
            },

            openDeleteModal(tx) {
                this.deletingTx = tx;
                this.showDeleteModal = true;
            },

            approvalDays() {
                const grouped = {};
                (this.transactions || []).forEach((tx) => {
                    if (!tx || tx.status === 'approved' || tx.status === 'void') {
                        return;
                    }

                    const key = tx.business_date || currentDate;
                    grouped[key] = grouped[key] || { date: key, label: key, count: 0 };
                    grouped[key].count += 1;
                });

                return Object.values(grouped).sort((a, b) => b.date.localeCompare(a.date));
            },

            pendingTransactionsForSelectedDay() {
                return (this.transactions || []).filter((tx) => {
                    if (!tx || tx.status === 'approved' || tx.status === 'void') {
                        return false;
                    }

                    return (tx.business_date || currentDate) === this.selectedApprovalDate;
                });
            },

            selectedApprovalDay() {
                return this.selectedApprovalDate;
            },

            selectedApprovalDayLabel() {
                return this.selectedApprovalDate || 'No day selected';
            },

            pendingApprovalCount() {
                return this.approvalDays().reduce((sum, day) => sum + day.count, 0);
            },

            approvalNoticeText() {
                const totalPending = this.pendingApprovalCount();
                if (totalPending === 0) {
                    return 'All transactions approved. No pending items requiring attention.';
                }

                const daysCount = this.approvalDays().length;
                const selectedMatch = this.approvalDays().find((day) => day.date === this.selectedApprovalDate);
                const selectedCount = selectedMatch ? selectedMatch.count : 0;

                let text = `${totalPending} total pending transaction${totalPending === 1 ? '' : 's'} requiring approval across ${daysCount} day${daysCount === 1 ? '' : 's'}.`;
                if (this.selectedApprovalDate) {
                    const shortDate = this.selectedApprovalDate.slice(8, 10) + '-' + this.selectedApprovalDate.slice(5, 7) + '-' + this.selectedApprovalDate.slice(0, 4);
                    if (selectedCount > 0) {
                        text += ` (${selectedCount} pending for ${shortDate})`;
                    } else {
                        text += ` (0 pending for ${shortDate})`;
                    }
                }

                return text;
            },

            shiftApprovalDate(offsetDays) {
                const base = new Date(`${this.selectedApprovalDate || currentDate}T00:00:00`);
                base.setDate(base.getDate() + offsetDays);
                const year = base.getFullYear();
                const month = String(base.getMonth() + 1).padStart(2, '0');
                const day = String(base.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            },

            async approveSingle(tx) {
                await this.approveRequest('/admin/cashbook/api/approve-entry', { transaction_id: tx.id });
            },

            async approvePendingForDay(day) {
                if (!day) return;
                const selected = day;
                await this.approveRequest('/admin/cashbook/api/approve-day', {
                    shop_id: currentShopId,
                    business_date: selected,
                });
            },

            async approveRequest(url, payload) {
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify(payload)
                    });

                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message || 'Approved successfully', 'success');
                        this.loadData();
                    } else {
                        showToast(data.message || 'Approval failed', 'error');
                    }
                } catch (err) {
                    showToast('Server error while approving entry', 'error');
                }
            },

            async createEntry() {
                const amountVal = parseFloat(this.createForm.amount);
                if (!this.createForm.entry_type_code || !this.createForm.business_date || Number.isNaN(amountVal) || amountVal <= 0) {
                    showToast('Please fill a valid entry type, date and amount', 'error');
                    return;
                }

                try {
                    const res = await fetch('/admin/cashbook/api/record-entry', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            shop_id: currentShopId,
                            business_date: this.createForm.business_date,
                            entry_type_code: this.createForm.entry_type_code,
                            amount: amountVal,
                            funding_source: this.createForm.funding_source || null,
                            notes: this.createForm.notes || null,
                        })
                    });

                    const data = await res.json();
                    if (data.success) {
                        showToast('Entry created successfully', 'success');
                        this.createForm.amount = '';
                        this.createForm.notes = '';
                        currentDate = this.createForm.business_date;
                        this.loadData();
                    } else {
                        showToast(data.message || 'Failed to create entry', 'error');
                    }
                } catch (err) {
                    showToast('Server error while creating entry', 'error');
                }
            },

            async submitEdit() {
                if (!this.editingTx) return;
                const amountVal = parseFloat(this.editAmount);
                if (Number.isNaN(amountVal) || amountVal <= 0) {
                    showToast('Enter a valid amount greater than zero', 'error');
                    return;
                }

                try {
                    const res = await fetch('/admin/cashbook/api/update-entry', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            transaction_id: this.editingTx.id,
                            amount: amountVal,
                        })
                    });

                    const data = await res.json();
                    if (data.success) {
                        showToast('Entry updated successfully', 'success');
                        this.showEditModal = false;
                        this.loadData();
                    } else {
                        showToast(data.message || 'Failed to update entry', 'error');
                    }
                } catch (err) {
                    showToast('Server error while updating entry', 'error');
                }
            },

            async submitDelete() {
                if (!this.deletingTx) return;

                try {
                    const res = await fetch('/admin/cashbook/api/delete-entry', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            transaction_id: this.deletingTx.id,
                        })
                    });

                    const data = await res.json();
                    if (data.success) {
                        showToast('Entry deleted successfully', 'success');
                        this.showDeleteModal = false;
                        this.loadData();
                    } else {
                        showToast(data.message || 'Failed to delete entry', 'error');
                    }
                } catch (err) {
                    showToast('Server error while deleting entry', 'error');
                }
            },

            async loadData() {
                try {
                    const url = `/admin/cashbook/api/shop-data?shop_id=${currentShopId}&business_date=${currentDate}&timeframe=${this.timeframe}&start_date=${this.customStartDate}&end_date=${this.customEndDate}`;
                    const res = await fetch(url);
                    const data = await res.json();

                    if (data.success) {
                        this.currentPage = 1;
                        let txs = data.transactions || [];
                        if (txs && typeof txs === 'object' && Array.isArray(txs.data)) {
                            txs = txs.data;
                        }
                        this.transactions = Array.isArray(txs) ? txs : [];

                        let mtxs = data.month_transactions || [];
                        if (mtxs && typeof mtxs === 'object' && Array.isArray(mtxs.data)) {
                            mtxs = mtxs.data;
                        }
                        this.monthTransactions = Array.isArray(mtxs) ? mtxs : [];

                        this.pettyEntries = data.petty_entries || [];
                        this.companyPendingEntries = data.company_pending_entries || [];
                        this.collectionSummaries = data.collection_summaries || [];
                        this.settings = data.settings || [];
                        if (!this.selectedApprovalDate) {
                            this.selectedApprovalDate = currentDate;
                        }

                        // Compute Payment Breakdown
                        const breakdown = {};
                        (this.transactions || []).forEach(t => {
                            if (t.direction === 'income') {
                                const code = t.entry_type ? t.entry_type.code : t.entry_type_id;
                                breakdown[code] = (breakdown[code] || 0) + parseFloat(t.amount);
                            }
                        });
                        this.paymentBreakdown = breakdown;
                        this.createForm.business_date = currentDate;
                        this.snapshot = data.snapshot || {};

                        renderSnapshot(data.snapshot);
                    }
                } catch (err) {
                    showToast('Failed to load shop details', 'error');
                }
            }
        };
    }

    function syncGlobalDate(newDate) {
        if (!newDate) return;
        currentDate = newDate;
        if (window.Alpine) {
            const app = document.querySelector('[x-data]')?._x_dataStack[0];
            if (app && typeof app.changeDate === 'function') {
                app.changeDate(newDate);
            }
        }
    }

    function renderSnapshot(snapshot) {
        if (!snapshot) return;

        document.getElementById('stat-sales').innerText = `₹${parseFloat(snapshot.total_sales).toFixed(2)}`;
        document.getElementById('stat-expense').innerText = `₹${parseFloat(snapshot.total_expense).toFixed(2)}`;

        const netPl = parseFloat(snapshot.net_pl);
        const netPlEl = document.getElementById('stat-net-pl');
        netPlEl.innerText = `₹${netPl.toFixed(2)}`;
        netPlEl.className = `text-xl font-bold font-mono ${netPl < 0 ? 'text-rose-600' : 'text-emerald-600'}`;

        document.getElementById('stat-petty').innerText = `₹${parseFloat(snapshot.closing_petty).toFixed(2)}`;
        const pettyTabFloat = document.getElementById('petty-tab-float');
        if (pettyTabFloat) pettyTabFloat.innerText = `₹${parseFloat(snapshot.closing_petty).toFixed(2)}`;

        document.getElementById('stat-settlement').innerText = `₹${parseFloat(snapshot.closing_shop_position).toFixed(2)}`;
        document.getElementById('stat-company-pending').innerText = `₹${parseFloat(snapshot.closing_company_pending).toFixed(2)}`;
        const collectionBalance = (parseFloat(snapshot.total_sales) || 0) - (parseFloat(snapshot.total_expense) || 0);
        const collectionBalanceEl = document.getElementById('stat-collection-balance');
        if (collectionBalanceEl) {
            collectionBalanceEl.innerText = `₹${collectionBalance.toFixed(2)}`;
        }

        const isClosed = snapshot.closed_at !== null;
        const statusBadge = document.getElementById('dashboard-day-status');
        const toggleBtn = document.getElementById('toggle-day-btn');

        if (isClosed) {
            if (statusBadge) {
                statusBadge.innerText = 'Closed';
                statusBadge.className = 'px-2.5 py-0.5 text-xs font-bold rounded-full bg-slate-100 text-slate-700 border border-slate-300';
            }
            if (toggleBtn) {
                toggleBtn.innerHTML = '<i data-lucide="unlock" class="w-3.5 h-3.5"></i> Reopen Day';
                toggleBtn.className = 'px-4 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 font-semibold text-xs transition-all flex items-center gap-1.5 shadow-sm';
            }
        } else {
            if (statusBadge) {
                statusBadge.innerText = 'Open';
                statusBadge.className = 'px-2.5 py-0.5 text-xs font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200';
            }
            if (toggleBtn) {
                toggleBtn.innerHTML = '<i data-lucide="lock" class="w-3.5 h-3.5"></i> Close Day';
                toggleBtn.className = 'px-4 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-semibold text-xs transition-all flex items-center gap-1.5 shadow-sm';
            }
        }
        lucide.createIcons();
    }

    async function handleToggleDay() {
        const statusBadge = document.getElementById('dashboard-day-status');
        const isClosed = statusBadge ? statusBadge.innerText.trim() === 'Closed' : false;
        const action = isClosed ? 'reopen' : 'close';

        try {
            const res = await fetch('/admin/cashbook/api/toggle-day', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    shop_id: currentShopId,
                    business_date: currentDate,
                    action: action
                })
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                syncGlobalDate(currentDate);
            } else {
                showToast(data.message, 'error');
            }
        } catch (err) {
            showToast('Error toggling day status', 'error');
        }
    }
</script>
@endpush
