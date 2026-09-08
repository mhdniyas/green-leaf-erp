@extends('shop-owner.layouts.app')

@section('title', 'Payments Overview')
@section('page_title', 'Payments Overview')

@section('content')
    <div class="mx-auto max-w-[96rem] space-y-5 px-1 sm:px-4">
        {{-- Flash Notifications --}}
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs sm:text-sm font-bold text-emerald-800 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-white shrink-0">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </span>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs sm:text-sm font-bold text-rose-800 space-y-1.5 shadow-sm">
                @foreach($errors->all() as $error)
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-600 text-white shrink-0">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </span>
                        <span>{{ $error }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Header Banner / Month Selector / Pay Action --}}
        <div class="rounded-2xl sm:rounded-3xl border border-slate-200 bg-white p-3.5 sm:p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 sm:h-11 sm:w-11 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white shadow-sm">
                        <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-base sm:text-xl font-extrabold uppercase tracking-tight text-slate-900">
                                PAYMENTS · {{ $formattedMonthLabel }}
                            </h1>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600">Shop Cashbook</span>
                        </div>
                        <p class="text-xs text-slate-500 font-medium hidden sm:block">Shop Balance, Expense Payables & Remittance Overview</p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 justify-between sm:justify-end">
                    {{-- Month Filter Pill --}}
                    <form method="GET" action="{{ route('shop-owner.payments.index') }}" class="flex items-center gap-2">
                        <label for="month" class="text-[11px] font-extrabold uppercase text-slate-500 shrink-0">Month:</label>
                        <input type="month" id="month" name="month" value="{{ $targetMonth }}" onchange="this.form.submit()"
                            class="w-full sm:w-auto rounded-xl border border-slate-300 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-800 shadow-inner focus:border-emerald-500 focus:bg-white focus:ring-emerald-500 transition-all cursor-pointer">
                    </form>

                    {{-- Pay to Company Action Button --}}
                    <button type="button" onclick="openPayToCompanyModal()"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3.5 sm:px-4 py-2 text-xs font-extrabold text-white shadow-sm hover:bg-emerald-700 active:scale-95 transition-all cursor-pointer w-full sm:w-auto">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <span class="whitespace-nowrap">Pay to Company</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- SHOP BALANCE Card (Top Highlight Header) --}}
        <div class="rounded-3xl border border-emerald-200 bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-800 p-5 sm:p-6 text-white shadow-lg">
            <span class="text-xs font-black uppercase tracking-wider text-emerald-200">SHOP BALANCE</span>
            <div class="mt-1 font-mono text-3xl sm:text-4xl font-black tracking-tight text-white">
                ₹{{ number_format($shopBalance) }}
            </div>
            <p class="text-xs text-emerald-100 font-medium mt-1">Money retained at shop level (Money kept by shop − Paid expenses − Sent to company)</p>
        </div>

        {{-- MONEY SPLIT Card --}}
        <div class="rounded-3xl border border-slate-200 bg-white p-4 sm:p-6 shadow-xs space-y-3.5 sm:space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Flow Breakdown</span>
                    <h2 class="text-base font-black text-slate-950">MONEY SPLIT</h2>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600">Period Summary</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2.5 sm:gap-3">
                <div onclick="openDirectModal()" class="rounded-2xl border border-teal-200 bg-teal-50/50 p-3 sm:p-3.5 flex flex-col justify-between cursor-pointer hover:border-teal-400 hover:shadow-xs transition-all">
                    <div>
                        <span class="text-[10px] sm:text-[11px] font-extrabold text-teal-800 uppercase tracking-tight">Sales Collections</span>
                        <div class="font-mono text-base sm:text-xl font-black text-teal-950 mt-1">₹{{ number_format($totalSales) }}</div>
                    </div>
                    <div class="flex items-center justify-between text-[9px] font-extrabold text-teal-700 mt-2">
                        <span>Direct: ₹{{ number_format($directToCompany) }}</span>
                        <span>Cash: ₹{{ number_format($cashInShop) }} →</span>
                    </div>
                </div>
                <div onclick="openExpensePayablesModal()" class="rounded-2xl border border-rose-200 bg-rose-50/50 p-3 sm:p-3.5 flex flex-col justify-between cursor-pointer hover:border-rose-400 hover:shadow-xs transition-all">
                    <div>
                        <span class="text-[10px] sm:text-[11px] font-extrabold text-rose-800 uppercase tracking-tight">EXPENSE PAYABLES</span>
                        <div class="font-mono text-base sm:text-xl font-black text-rose-950 mt-1">₹{{ number_format(collect($expensePayables)->sum('remaining_amount')) }}</div>
                    </div>
                    <div class="flex items-center justify-between text-[9px] font-extrabold text-rose-700 mt-2">
                        <span>{{ count($expensePayables) }} active</span>
                        <span>Tap to pay →</span>
                    </div>
                </div>
                <div onclick="openSettledExpensesModal()" class="rounded-2xl border border-indigo-200 bg-indigo-50/50 p-3 sm:p-3.5 flex flex-col justify-between cursor-pointer hover:border-indigo-400 hover:shadow-xs transition-all">
                    <div>
                        <span class="text-[10px] sm:text-[11px] font-extrabold text-indigo-800 uppercase tracking-tight">SETTLED EXPENSES</span>
                        <div class="font-mono text-base sm:text-xl font-black text-indigo-950 mt-1">₹{{ number_format(collect($settledExpenses)->sum('settled_amount')) }}</div>
                    </div>
                    <div class="flex items-center justify-between text-[9px] font-extrabold text-indigo-700 mt-2">
                        <span>{{ collect($settledExpenses)->sum('settled_count') }} settled</span>
                        <span>Tap to view →</span>
                    </div>
                </div>
                <div onclick="openManualModal()" class="rounded-2xl border border-amber-200 bg-amber-50/50 p-3 sm:p-3.5 flex flex-col justify-between cursor-pointer hover:border-amber-400 hover:shadow-xs transition-all">
                    <div>
                        <span class="text-[10px] sm:text-[11px] font-extrabold text-amber-900 uppercase tracking-tight">Sent to Company</span>
                        <div class="font-mono text-base sm:text-xl font-black text-slate-950 mt-1">₹{{ number_format($manualReceived) }}</div>
                    </div>
                    <div class="mt-2 text-[9px] font-extrabold text-amber-800 flex items-center justify-between">
                        <span>Till date approved</span>
                        @if($manualPending > 0)
                            <span class="rounded-full bg-amber-200/80 px-1.5 py-0.2 text-amber-900 font-black">₹{{ number_format($manualPending) }} pending</span>
                        @endif
                    </div>
                </div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-3 sm:p-3.5 flex flex-col justify-between">
                    <div>
                        <span class="text-[10px] sm:text-[11px] font-extrabold text-emerald-900 uppercase tracking-tight">Remaining Shop Balance</span>
                        <div class="font-mono text-base sm:text-xl font-black text-emerald-700 mt-1">₹{{ number_format($shopBalance) }}</div>
                    </div>
                    <span class="text-[9px] font-bold text-emerald-800 mt-2">Net Retained Cash</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Pay Expense Modal --}}
    <div id="pay-expense-modal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3.5 sm:p-4 hidden"
        onclick="if(event.target === this) closePayExpenseModal()">
        <div onclick="event.stopPropagation()"
            class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-5 shadow-2xl transition-all">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-indigo-700">Record Expense Payment</span>
                    <h3 id="pay-expense-title" class="text-sm sm:text-base font-extrabold text-slate-950">Pay Expense</h3>
                </div>
                <button type="button" onclick="closePayExpenseModal()"
                    class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors cursor-pointer"
                    aria-label="Close modal">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('shop-owner.payments.pay-expense') }}" class="space-y-4 mt-4">
                @csrf
                <input type="hidden" name="entry_setting_id" id="pay_expense_setting_id">

                <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-3 text-xs flex justify-between items-center">
                    <span class="text-indigo-950 font-bold">Remaining Payable Amount:</span>
                    <span id="pay-expense-remaining" class="font-mono font-black text-rose-600 text-sm">₹0.00</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="expense_amount" class="block text-xs font-bold uppercase tracking-wide text-slate-700 mb-1">Payment Amount</label>
                        <div class="relative rounded-xl shadow-2xs">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 font-bold text-sm select-none">₹</span>
                            <input type="number" step="0.01" min="0.01" id="expense_amount" name="amount" required placeholder="0.00"
                                class="w-full min-h-[38px] rounded-xl border border-slate-300 pl-7 pr-3 py-1.5 text-base font-mono font-bold text-slate-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition-all">
                        </div>
                    </div>
                    <div>
                        <label for="expense_payment_date" class="block text-xs font-bold uppercase tracking-wide text-slate-700 mb-1">Payment Date</label>
                        <input type="date" id="expense_payment_date" name="payment_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required
                            class="w-full min-h-[38px] rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-700 mb-1">Paid From</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-emerald-300 bg-emerald-50/60 cursor-pointer">
                            <input type="radio" name="paid_from_source" value="shop_balance" checked class="h-4 w-4 text-emerald-600 focus:ring-emerald-500">
                            <div>
                                <div class="text-xs font-black text-emerald-950">Shop Balance</div>
                                <div class="text-[10px] text-emerald-700 font-bold">₹{{ number_format($shopBalance) }}</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-slate-50 cursor-pointer">
                            <input type="radio" name="paid_from_source" value="petty_cash" class="h-4 w-4 text-slate-600 focus:ring-slate-500">
                            <div>
                                <div class="text-xs font-black text-slate-900">Petty Cash</div>
                                <div class="text-[10px] text-slate-500 font-bold">₹{{ number_format($pettyBalance) }}</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label for="expense_notes" class="block text-xs font-bold uppercase tracking-wide text-slate-700 mb-1">Notes / Reference (Optional)</label>
                    <input type="text" id="expense_notes" name="notes" placeholder="e.g. Paid via cash receipt..."
                        class="w-full min-h-[36px] rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-800 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition-all">
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" onclick="closePayExpenseModal()"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-black text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                        class="rounded-xl bg-indigo-600 px-5 py-2 text-xs font-black text-white shadow-sm hover:bg-indigo-700 transition cursor-pointer">
                        Submit Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Expense Payables Modal (Popup) --}}
    <div id="expense-payables-modal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3.5 sm:p-4 hidden"
        onclick="if(event.target === this) closeExpensePayablesModal()">
        <div onclick="event.stopPropagation()"
            class="w-full max-w-lg max-h-[88vh] overflow-y-auto rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 shadow-2xl transition-all space-y-4">
            
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-rose-700">Category Obligations</span>
                    <h3 class="text-sm sm:text-base font-extrabold text-slate-950">EXPENSE PAYABLES</h3>
                </div>
                <button type="button" onclick="closeExpensePayablesModal()"
                    class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors cursor-pointer"
                    aria-label="Close modal">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Summary Card --}}
            <div class="rounded-2xl border border-rose-200 bg-rose-50/60 p-3.5 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wide text-rose-800 block">Total Remaining to Pay</span>
                    <span class="font-mono text-base sm:text-lg font-black text-rose-950">₹{{ number_format(collect($expensePayables)->sum('remaining_amount')) }}</span>
                </div>
                <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-black text-rose-800 border border-rose-200">
                    {{ count($expensePayables) }} active
                </span>
            </div>

            {{-- Active Expense Payables List by Category --}}
            <div class="divide-y divide-slate-100 max-h-72 overflow-y-auto">
                @forelse($expensePayables as $exp)
                    <div class="flex items-center justify-between py-3.5 gap-2">
                        <div class="min-w-0">
                            <div class="text-xs sm:text-sm font-black text-slate-900 truncate">
                                {{ $exp['name'] }}
                            </div>
                            <div class="text-[11px] text-slate-500 font-medium mt-0.5 space-x-2">
                                <span>Total: <strong class="font-mono font-bold text-slate-800">₹{{ number_format((float) ($exp['recorded_amount'] ?? 0)) }}</strong></span>
                                @if((float) ($exp['paid_amount'] ?? 0) > 0)
                                    <span>· Paid: <strong class="font-mono font-bold text-emerald-700">₹{{ number_format((float) ($exp['paid_amount'] ?? 0)) }}</strong></span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <div class="text-right">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tight block">Remaining</span>
                                <span class="font-mono text-xs sm:text-sm font-black text-rose-600">
                                    ₹{{ number_format((float) ($exp['remaining_amount'] ?? 0)) }}
                                </span>
                            </div>
                            <button type="button"
                                onclick="closeExpensePayablesModal(); openPayExpenseModal({{ $exp['entry_setting_id'] ?? 0 }}, '{{ addslashes($exp['name']) }}', {{ (float) ($exp['remaining_amount'] ?? 0) }})"
                                class="rounded-xl bg-indigo-600 px-3.5 py-1.5 text-xs font-black text-white shadow-xs hover:bg-indigo-700 transition cursor-pointer">
                                Pay
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-xs text-slate-400 font-medium italic">No unpaid expense payables for this month</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Settled Expenses Modal (Popup) --}}
    <div id="settled-expenses-modal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3.5 sm:p-4 hidden"
        onclick="if(event.target === this) closeSettledExpensesModal()">
        <div onclick="event.stopPropagation()"
            class="w-full max-w-lg max-h-[88vh] overflow-y-auto rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 shadow-2xl transition-all space-y-4">
            
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-indigo-700">Settled Category Breakdown</span>
                    <h3 class="text-sm sm:text-base font-extrabold text-slate-950">SETTLED EXPENSES</h3>
                </div>
                <button type="button" onclick="closeSettledExpensesModal()"
                    class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors cursor-pointer"
                    aria-label="Close modal">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Summary Card --}}
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-3.5 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wide text-indigo-800 block">TOTAL SETTLED</span>
                    <span class="font-mono text-base sm:text-lg font-black text-indigo-950">₹{{ number_format(collect($settledExpenses)->sum('settled_amount')) }}</span>
                </div>
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-800 border border-emerald-200">
                    {{ collect($settledExpenses)->sum('settled_count') }} settled
                </span>
            </div>

            {{-- Settled Categories List --}}
            <div class="divide-y divide-slate-100 max-h-72 overflow-y-auto">
                @forelse($settledExpenses as $exp)
                    <div class="flex items-center justify-between py-3.5">
                        <div>
                            <div class="text-xs sm:text-sm font-black text-slate-900">
                                {{ $exp['name'] }}
                            </div>
                            <div class="text-[11px] text-emerald-700 font-bold mt-0.5">
                                {{ $exp['settled_count'] ?? 1 }} settled
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs sm:text-sm font-black text-slate-900">
                                ₹{{ number_format((float) ($exp['settled_amount'] ?? $exp['recorded_amount'] ?? 0)) }}
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black text-emerald-800">
                                <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                Settled
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-xs text-slate-400 font-medium italic">No settled expense categories recorded this month</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Payable Breakdown Modal --}}
    <div id="payable-details-modal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3.5 sm:p-4 hidden"
        onclick="if(event.target === this) closePayableModal()">
        <div onclick="event.stopPropagation()"
            class="w-full max-w-lg max-h-[85vh] overflow-y-auto rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 shadow-2xl transition-all">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-indigo-700">Obligations Breakdown</span>
                    <h3 class="text-sm sm:text-base font-extrabold text-slate-950">{{ $payableRelation?->name ?? 'Payable Items' }}</h3>
                </div>
                <button type="button" onclick="closePayableModal()"
                    class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors cursor-pointer"
                    aria-label="Close modal">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="divide-y divide-slate-100 mt-3 text-xs sm:text-sm">
                @forelse($payableItems as $item)
                    <div class="flex items-center justify-between py-2.5">
                        <span class="font-medium text-slate-700">{{ $item['name'] }}</span>
                        <span class="font-mono font-extrabold text-slate-900">
                            {{ ($item['role'] ?? '') === 'subtract' ? '− ' : '+ ' }}₹{{ number_format($item['amount'] ?? 0) }}
                        </span>
                    </div>
                @empty
                    <div class="py-6 text-center text-xs italic text-slate-400">No items configured</div>
                @endforelse
            </div>

            <div class="mt-5 pt-3 border-t border-slate-200 flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Total Payable</span>
                <span class="font-mono text-base font-black text-indigo-700">₹{{ number_format($payable) }}</span>
            </div>
        </div>
    </div>

    {{-- Sales Collections Breakdown Modal --}}
    <div id="direct-details-modal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3.5 sm:p-4 hidden"
        onclick="if(event.target === this) closeDirectModal()">
        <div onclick="event.stopPropagation()"
            class="w-full max-w-lg max-h-[85vh] overflow-y-auto rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 shadow-2xl transition-all">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-teal-700">Total Sales Split</span>
                    <h3 class="text-sm sm:text-base font-extrabold text-slate-950">SALES COLLECTIONS</h3>
                </div>
                <button type="button" onclick="closeDirectModal()"
                    class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors cursor-pointer"
                    aria-label="Close modal">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Summary Chips --}}
            <div class="grid grid-cols-2 gap-2 mt-3">
                <div class="rounded-xl border border-teal-200 bg-teal-50/60 p-2.5 text-center">
                    <span class="text-[10px] font-black uppercase tracking-wide text-teal-800 block">Direct to Company</span>
                    <span class="font-mono text-sm font-black text-teal-950">₹{{ number_format($directToCompany) }}</span>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-2.5 text-center">
                    <span class="text-[10px] font-black uppercase tracking-wide text-amber-800 block">Cash in Shop</span>
                    <span class="font-mono text-sm font-black text-amber-950">₹{{ number_format($cashInShop) }}</span>
                </div>
            </div>

            <div class="divide-y divide-slate-100 mt-4 text-xs sm:text-sm">
                @forelse($salesCollectionItems ?? $directItems as $item)
                    <div class="flex items-center justify-between py-3">
                        <div>
                            <span class="font-black text-slate-900 block text-xs sm:text-sm">{{ $item['name'] }}</span>
                            <div class="flex items-center gap-1.5 text-[11px] {{ ($item['is_direct'] ?? false) ? 'text-teal-800' : 'text-amber-800' }} font-bold mt-0.5">
                                @if($item['is_direct'] ?? false)
                                    <svg class="h-3.5 w-3.5 text-teal-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    <span>Direct to Company{{ !empty($item['bank_name']) ? ' · '.$item['bank_name'] : '' }}</span>
                                @else
                                    <svg class="h-3.5 w-3.5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a1 1 0 11-2 0 1 1 0 012 0z" />
                                    </svg>
                                    <span class="text-amber-900 font-bold">Stays with Shop</span>
                                @endif
                            </div>
                        </div>
                        <span class="font-mono font-black {{ ($item['is_direct'] ?? false) ? 'text-teal-900' : 'text-slate-950' }} text-sm sm:text-base">
                            ₹{{ number_format($item['amount'] ?? 0) }}
                        </span>
                    </div>
                @empty
                    <div class="py-6 text-center text-xs italic text-slate-400">No sales collections configured</div>
                @endforelse
            </div>

            {{-- Bottom Summary Block --}}
            <div class="mt-5 pt-3 border-t border-slate-200 rounded-2xl bg-slate-50 p-4 space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-slate-600">Direct to Company</span>
                    <span class="font-mono font-black text-teal-800">₹{{ number_format($directToCompany) }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-slate-600">Cash in Shop</span>
                    <span class="font-mono font-black text-amber-800">₹{{ number_format($cashInShop) }}</span>
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-slate-200 text-sm">
                    <span class="font-extrabold uppercase tracking-wider text-slate-900">TOTAL SALES</span>
                    <span class="font-mono font-black text-slate-950 text-base">₹{{ number_format($totalSales) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Manual Payments Breakdown Modal --}}
    <div id="manual-details-modal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3.5 sm:p-4 hidden"
        onclick="if(event.target === this) closeManualModal()">
        <div onclick="event.stopPropagation()"
            class="w-full max-w-lg max-h-[85vh] overflow-y-auto rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 shadow-2xl transition-all">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-800">Shop → Company Remittances</span>
                    <h3 class="text-sm sm:text-base font-extrabold text-slate-950">PAYMENTS TO COMPANY</h3>
                </div>
                <button type="button" onclick="closeManualModal()"
                    class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors cursor-pointer"
                    aria-label="Close modal">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="grid grid-cols-2 gap-2 mt-3">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-2.5 text-center">
                    <span class="text-[10px] font-black uppercase tracking-wide text-emerald-800 block">Till Date Approved</span>
                    <span class="font-mono text-sm font-black text-emerald-950">₹{{ number_format($manualReceived) }}</span>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-2.5 text-center">
                    <span class="text-[10px] font-black uppercase tracking-wide text-amber-800 block">Pending Verification</span>
                    <span class="font-mono text-sm font-black text-amber-950">₹{{ number_format($manualPending) }}</span>
                </div>
            </div>

            @php
                $pendingRequests = $manualRequests->where('status', 'pending');
            @endphp
            <div class="mt-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
                    <span class="text-xs font-black text-slate-900 uppercase">Pending Verification Requests</span>
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-extrabold text-amber-800">{{ $pendingRequests->count() }} pending</span>
                </div>
                <div class="divide-y divide-slate-100 max-h-60 overflow-y-auto">
                    @forelse($pendingRequests as $req)
                        <div class="flex items-center justify-between py-3">
                            <div class="min-w-0 pr-2">
                                <div class="font-black text-slate-900 truncate">{{ $req->paymentMethodLabel() }} · {{ $req->created_at?->format('d M Y') }}</div>
                                <div class="text-[10px] text-slate-400 truncate">{{ $req->payment_reference ?: 'Direct Remittance' }}</div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="font-mono font-black text-slate-950">₹{{ number_format((float) $req->requested_amount) }}</div>
                                <span class="inline-block text-[9px] font-black uppercase px-1.5 py-0.2 rounded bg-amber-100 text-amber-800">
                                    {{ $req->statusLabel() }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-slate-500 font-medium">
                            <svg class="h-8 w-8 mx-auto text-emerald-500 mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="font-bold text-slate-700">No pending verification requests</span>
                            <div class="text-[11px] text-slate-400 mt-0.5">All remittances till date are approved (₹{{ number_format($manualReceived) }}).</div>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="mt-5 pt-3 border-t border-slate-200 flex items-center justify-between">
                <button type="button" onclick="closeManualModal(); openPayToCompanyModal();"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black text-white hover:bg-emerald-700 transition cursor-pointer shadow-xs">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Pay to Company</span>
                </button>
                <div class="text-right">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 block">Total Sent</span>
                    <span class="font-mono text-base font-black text-slate-900">₹{{ number_format($manualTotal) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Pay to Company Modal (Pure Tailwind Popup) --}}
    <div id="pay-to-company-modal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-3.5 sm:p-4 hidden"
        onclick="if(event.target === this) closePayToCompanyModal()">
        <div onclick="event.stopPropagation()"
            class="w-full max-w-md max-h-[90vh] overflow-y-auto rounded-3xl border border-slate-200 bg-white p-4 sm:p-5 shadow-2xl transition-all">
            <div class="flex items-center justify-between pb-2.5 sm:pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-900 text-emerald-400 shadow-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a1 1 0 11-2 0 1 1 0 012 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xs sm:text-sm font-extrabold uppercase tracking-tight text-slate-900">
                            PAY TO COMPANY</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Submit payment for reconciliation</p>
                    </div>
                </div>
                <button type="button" onclick="closePayToCompanyModal()"
                    class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors focus:outline-none focus:ring-2 focus:ring-slate-400 shrink-0 cursor-pointer flex items-center justify-center"
                    aria-label="Close modal">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Balances Summary Header --}}
            <div class="my-2.5 rounded-xl border border-slate-200/80 bg-slate-50/70 p-1.5 sm:p-2">
                <div class="grid grid-cols-2 gap-1.5 sm:gap-2">
                    <div class="flex flex-col items-center justify-center rounded-lg bg-white p-1.5 border border-slate-200/60 shadow-2xs text-center min-w-0">
                        <span class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 truncate w-full">Shop Balance</span>
                        <span class="font-mono text-xs sm:text-sm font-bold text-emerald-700 truncate w-full">₹{{ number_format($shopBalance) }}</span>
                    </div>
                    <div class="flex flex-col items-center justify-center rounded-lg bg-white p-1.5 border border-slate-200/60 shadow-2xs text-center min-w-0">
                        <span class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-slate-500 truncate w-full">Petty Balance</span>
                        <span class="font-mono text-xs sm:text-sm font-bold text-slate-700 truncate w-full">₹{{ number_format($pettyBalance) }}</span>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('shop-owner.accounting.payment-requests.store') }}" class="space-y-2.5">
                @csrf
                <input type="hidden" name="amount_mode" value="shop_balance">

                <div>
                    <label for="amount" class="block text-[10px] sm:text-xs font-bold uppercase tracking-wide text-slate-700 mb-0.5">Amount</label>
                    <div class="relative rounded-xl shadow-2xs">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 font-bold text-sm select-none">₹</span>
                        <input type="number" step="0.01" min="0.01" id="amount" name="amount" required placeholder="0.00"
                            class="w-full min-h-[38px] rounded-xl border border-slate-300 pl-7 pr-3 py-1.5 text-base font-mono font-bold text-slate-900 placeholder:text-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] sm:text-xs font-bold uppercase tracking-wide text-slate-700 mb-1">Pay From</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex min-h-[36px] items-center gap-2 p-2 rounded-xl border-2 border-emerald-500 bg-emerald-50/60 cursor-pointer transition-all hover:bg-emerald-50 focus-within:ring-2 focus-within:ring-emerald-500/20">
                            <input type="radio" name="pay_from_source" value="shop_balance" checked class="h-3.5 w-3.5 text-emerald-600 focus:ring-emerald-500 border-slate-300">
                            <span class="text-[11px] sm:text-xs font-extrabold text-emerald-950">Shop Balance</span>
                        </label>
                        <label class="flex min-h-[36px] items-center gap-2 p-2 rounded-xl border border-slate-200 bg-slate-50 opacity-55 cursor-not-allowed"
                            title="Petty cash payment requests are not supported on payment requests engine. Standard petty expenses are recorded in daily ledger.">
                            <input type="radio" name="pay_from_source" value="petty" disabled class="h-3.5 w-3.5 text-slate-400">
                            <span class="text-[11px] sm:text-xs font-bold text-slate-500">Petty (N/A)</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] sm:text-xs font-bold uppercase tracking-wide text-slate-700 mb-1">Payment Method</label>
                    <div class="grid grid-cols-3 gap-1.5 sm:gap-2">
                        <label class="payment-method-label flex min-h-[36px] items-center justify-center p-2 rounded-xl border-2 border-emerald-500 bg-emerald-50/70 text-emerald-950 cursor-pointer text-center transition-all select-none hover:bg-emerald-50 focus-within:ring-2 focus-within:ring-emerald-500/20">
                            <input type="radio" name="payment_method" value="cash" checked class="sr-only" onchange="updatePaymentMethodSelection(this)">
                            <span class="text-[11px] sm:text-xs font-extrabold tracking-tight">Cash</span>
                        </label>
                        <label class="payment-method-label flex min-h-[36px] items-center justify-center p-2 rounded-xl border border-slate-200 bg-slate-50/50 text-slate-700 cursor-pointer text-center transition-all select-none hover:bg-slate-100 focus-within:ring-2 focus-within:ring-slate-300">
                            <input type="radio" name="payment_method" value="online_upi" class="sr-only" onchange="updatePaymentMethodSelection(this)">
                            <span class="text-[11px] sm:text-xs font-extrabold tracking-tight">Online / UPI</span>
                        </label>
                        <label class="payment-method-label flex min-h-[36px] items-center justify-center p-2 rounded-xl border border-slate-200 bg-slate-50/50 text-slate-700 cursor-pointer text-center transition-all select-none hover:bg-slate-100 focus-within:ring-2 focus-within:ring-slate-300">
                            <input type="radio" name="payment_method" value="cheque" class="sr-only" onchange="updatePaymentMethodSelection(this)">
                            <span class="text-[11px] sm:text-xs font-extrabold tracking-tight">Cheque</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label for="payment_reference" class="block text-[10px] sm:text-xs font-bold uppercase tracking-wide text-slate-700 mb-0.5">Reference (Optional)</label>
                    <input type="text" id="payment_reference" name="payment_reference" placeholder="UPI ref / Transaction ID / Cheque #"
                        class="w-full min-h-[36px] rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-800 placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none transition-all">
                </div>

                <div>
                    <label for="shop_note" class="block text-[10px] sm:text-xs font-bold uppercase tracking-wide text-slate-700 mb-0.5">Note (Optional)</label>
                    <textarea id="shop_note" name="shop_note" rows="1" placeholder="Payment notes for admin..."
                        class="w-full min-h-[42px] rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-800 placeholder:text-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 focus:outline-none transition-all resize-none"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-2 pt-2.5 sm:flex sm:flex-row sm:items-center sm:justify-end sm:gap-2.5 border-t border-slate-100">
                    <button type="button" onclick="closePayToCompanyModal()"
                        class="w-full sm:w-auto min-h-[38px] rounded-xl border border-slate-200 px-3.5 py-2 text-xs font-extrabold text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300 transition-all cursor-pointer text-center truncate">
                        Cancel
                    </button>
                    <button type="submit"
                        class="w-full sm:w-auto min-h-[38px] rounded-xl bg-emerald-600 px-4 py-2 text-xs font-extrabold text-white shadow-sm hover:bg-emerald-700 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-emerald-500/50 transition-all cursor-pointer text-center truncate">
                        Submit Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPayExpenseModal(settingId, name, remainingAmount, date) {
            document.getElementById('pay_expense_setting_id').value = settingId;
            document.getElementById('pay-expense-title').textContent = 'Pay ' + name;
            document.getElementById('pay-expense-remaining').textContent = '₹' + Number(remainingAmount).toLocaleString('en-IN', {minimumFractionDigits: 2});
            document.getElementById('expense_amount').value = remainingAmount;
            const dateInput = document.getElementById('expense_payment_date');
            if (dateInput) {
                dateInput.value = date || new Date().toISOString().split('T')[0];
            }
            
            const modal = document.getElementById('pay-expense-modal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
        }

        function closePayExpenseModal() {
            const modal = document.getElementById('pay-expense-modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        function openExpensePayablesModal() {
            const modal = document.getElementById('expense-payables-modal') || document.getElementById('expenses-details-modal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
        }

        function closeExpensePayablesModal() {
            const modal = document.getElementById('expense-payables-modal') || document.getElementById('expenses-details-modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        function openSettledExpensesModal() {
            const modal = document.getElementById('settled-expenses-modal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
        }

        function closeSettledExpensesModal() {
            const modal = document.getElementById('settled-expenses-modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        function openPayableModal() {
            const modal = document.getElementById('payable-details-modal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
        }

        function closePayableModal() {
            const modal = document.getElementById('payable-details-modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        function openDirectModal() {
            const modal = document.getElementById('direct-details-modal') || document.getElementById('paid-details-modal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
        }

        function closeDirectModal() {
            const modal = document.getElementById('direct-details-modal') || document.getElementById('paid-details-modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        function openManualModal() {
            const modal = document.getElementById('manual-details-modal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
        }

        function closeManualModal() {
            const modal = document.getElementById('manual-details-modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        function openExpensesModal() {
            openExpensePayablesModal();
        }

        function closeExpensesModal() {
            closeExpensePayablesModal();
        }

        function openPaidModal() {
            openDirectModal();
        }

        function closePaidModal() {
            closeDirectModal();
        }

        function openPayToCompanyModal() {
            const modal = document.getElementById('pay-to-company-modal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }
        }

        function closePayToCompanyModal() {
            const modal = document.getElementById('pay-to-company-modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        }

        function updatePaymentMethodSelection(radio) {
            document.querySelectorAll('.payment-method-label').forEach(label => {
                label.className = 'payment-method-label flex min-h-[36px] items-center justify-center p-2 rounded-xl border border-slate-200 bg-slate-50/50 text-slate-700 cursor-pointer text-center transition-all select-none hover:bg-slate-100 focus-within:ring-2 focus-within:ring-slate-300';
            });
            const activeLabel = radio.closest('.payment-method-label');
            if (activeLabel) {
                activeLabel.className = 'payment-method-label flex min-h-[36px] items-center justify-center p-2 rounded-xl border-2 border-emerald-500 bg-emerald-50/70 text-emerald-950 cursor-pointer text-center transition-all select-none hover:bg-emerald-50 focus-within:ring-2 focus-within:ring-emerald-500/20';
            }
        }

        function toggleSection(bodyId, chevronId) {
            const body = document.getElementById(bodyId);
            const chevron = document.getElementById(chevronId);
            if (body) {
                const isHidden = body.classList.contains('hidden');
                if (isHidden) {
                    body.classList.remove('hidden');
                    if (chevron) chevron.classList.add('rotate-180');
                } else {
                    body.classList.add('hidden');
                    if (chevron) chevron.classList.remove('rotate-180');
                }
            }
        }

        let payablesVisible = 5;
        function loadMorePayables() {
            const items = document.querySelectorAll('.payable-item');
            const total = items.length;
            payablesVisible += 10;
            items.forEach((item, idx) => {
                if (idx < payablesVisible) {
                    item.classList.remove('hidden');
                }
            });
            const remaining = total - payablesVisible;
            const container = document.getElementById('payables-load-more-container');
            const badge = document.getElementById('payables-remaining-count');
            if (remaining <= 0) {
                if (container) container.classList.add('hidden');
            } else if (badge) {
                badge.textContent = '+' + remaining + ' remaining';
            }
        }

        let settledVisible = 5;
        function loadMoreSettled() {
            const items = document.querySelectorAll('.settled-item');
            const total = items.length;
            settledVisible += 10;
            items.forEach((item, idx) => {
                if (idx < settledVisible) {
                    item.classList.remove('hidden');
                }
            });
            const remaining = total - settledVisible;
            const container = document.getElementById('settled-load-more-container');
            const badge = document.getElementById('settled-remaining-count');
            if (remaining <= 0) {
                if (container) container.classList.add('hidden');
            } else if (badge) {
                badge.textContent = '+' + remaining + ' remaining';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('pay-to-company-modal');
            if (modal) {
                modal.showModal = openPayToCompanyModal;
                modal.close = closePayToCompanyModal;
            }
        });
    </script>
@endsection