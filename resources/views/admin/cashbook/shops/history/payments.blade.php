@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop').' — Payments & Allocation History')

@section('content')
<div x-data="shopPaymentHistoryData()" class="mx-auto max-w-7xl space-y-6 pb-16">
    <!-- Header with Back & Settings buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'month' => $month ?: now()->format('Y-m')]) }}"
               class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition"
               title="Back to Shop Operation Center">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-black text-slate-900 tracking-tight">{{ $currentShop->name }}</h1>
                    <span class="text-xs font-bold px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-600 font-mono">{{ $currentShop->code }}</span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Shop Payment Receipts, Allocation Status &amp; Settlement History</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if(auth()->user() && (auth()->user()->isMainAdmin() || auth()->user()->hasRole('admin')))
                <a href="{{ route('admin.cashbook.settings.shop', $currentShop->slug ?: $currentShop->shop_id) }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-xs transition"
                   title="Cashbook Settings">
                    <i data-lucide="settings" class="w-4 h-4 text-slate-500"></i>
                    <span>Settings</span>
                </a>
            @endif
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="{{ route('admin.cashbook.shop.history.payments', $currentShop->slug ?: $currentShop->shop_id) }}"
          class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center gap-3 text-xs font-bold">
        <div class="flex items-center gap-1.5">
            <label class="text-slate-400 uppercase text-[10px]">Month:</label>
            <input type="month" name="month" value="{{ $month }}" class="px-2.5 py-1.5 bg-slate-50 rounded-xl border border-slate-300 font-mono text-slate-900 focus:bg-white focus:outline-none">
        </div>

        <div class="flex items-center gap-1.5">
            <label class="text-slate-400 uppercase text-[10px]">Method:</label>
            <select name="payment_method" class="px-2.5 py-1.5 bg-slate-50 rounded-xl border border-slate-300 text-slate-900 focus:bg-white focus:outline-none">
                <option value="">All Methods</option>
                <option value="bank" {{ $paymentMethod === 'bank' ? 'selected' : '' }}>Bank Transfer</option>
                <option value="cash" {{ $paymentMethod === 'cash' ? 'selected' : '' }}>Cash</option>
                <option value="upi" {{ $paymentMethod === 'upi' ? 'selected' : '' }}>UPI / Online</option>
                <option value="cheque" {{ $paymentMethod === 'cheque' ? 'selected' : '' }}>Cheque</option>
            </select>
        </div>

        <div class="flex items-center gap-1.5 flex-1 min-w-[200px]">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search reference, note..." class="w-full px-3 py-1.5 bg-slate-50 rounded-xl border border-slate-300 text-slate-900 focus:bg-white focus:outline-none">
        </div>

        <button type="submit" class="px-4 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-extrabold shadow-xs transition cursor-pointer">
            Filter
        </button>
        @if($month || $search || $paymentMethod)
            <a href="{{ route('admin.cashbook.shop.history.payments', $currentShop->slug ?: $currentShop->shop_id) }}" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition">
                Reset
            </a>
        @endif
    </form>

    <!-- 1. ALLOCATION SUMMARY (TOP SECTION) -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <i data-lucide="pie-chart" class="w-4 h-4 text-emerald-600"></i>
                    <span>Allocation Summary</span>
                    @if($month)
                        <span class="text-xs font-bold text-slate-500 font-mono">({{ \Carbon\Carbon::parse($month.'-01')->format('F Y') }})</span>
                    @else
                        <span class="text-xs font-bold text-slate-500 font-mono">(All Time)</span>
                    @endif
                </h2>
                <p class="text-[11px] text-slate-500">Overview of money received, allocated settlements, and remaining balances</p>
            </div>

            <!-- Auto Allocate Trigger Button -->
            <div class="flex items-center gap-2">
                @if($autoAllocateEnabled)
                    <button type="button"
                            @click="openBulkAllocateModal()"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold shadow-xs transition cursor-pointer">
                        <i data-lucide="zap" class="w-4 h-4 text-emerald-200"></i>
                        <span>Auto Allocate</span>
                        @if($autoAllocateProposal['proposed_total'] > 0)
                            <span class="px-1.5 py-0.5 rounded-md bg-emerald-800 text-[10px] font-mono font-bold">
                                ₹{{ number_format($autoAllocateProposal['proposed_total'], 2) }}
                            </span>
                        @endif
                    </button>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 text-slate-500 text-xs font-bold" title="Auto allocation is disabled in Shop Settings">
                        <i data-lucide="lock" class="w-3.5 h-3.5 text-slate-400"></i>
                        <span>Auto Allocate (Disabled)</span>
                    </span>
                @endif
            </div>
        </div>

        <!-- 6 Metrics Grid -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 font-mono">
            <!-- Total Payment Amount -->
            <div class="p-3.5 bg-slate-50/80 rounded-2xl border border-slate-200/80">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 block font-sans tracking-wide">Total Payments</span>
                <span class="text-base font-black text-slate-900 block mt-0.5">
                    ₹{{ number_format($allocationSummary['total_payment_amount'], 2) }}
                </span>
            </div>

            <!-- Allocated Amount -->
            <div class="p-3.5 bg-emerald-50/50 rounded-2xl border border-emerald-100">
                <span class="text-[10px] font-extrabold uppercase text-emerald-800 block font-sans tracking-wide">Allocated Amount</span>
                <span class="text-base font-black text-emerald-700 block mt-0.5">
                    ₹{{ number_format($allocationSummary['allocated_amount'], 2) }}
                </span>
            </div>

            <!-- Unallocated Amount -->
            <div class="p-3.5 rounded-2xl border {{ $allocationSummary['unallocated_amount'] > 0 ? 'bg-amber-50/70 border-amber-200' : 'bg-slate-50/80 border-slate-200/80' }}">
                <span class="text-[10px] font-extrabold uppercase {{ $allocationSummary['unallocated_amount'] > 0 ? 'text-amber-800' : 'text-slate-400' }} block font-sans tracking-wide">Unallocated Amount</span>
                <span class="text-base font-black {{ $allocationSummary['unallocated_amount'] > 0 ? 'text-amber-700' : 'text-slate-500' }} block mt-0.5">
                    ₹{{ number_format($allocationSummary['unallocated_amount'], 2) }}
                </span>
            </div>

            <!-- Shop → Company Allocated -->
            <div class="p-3.5 bg-sky-50/50 rounded-2xl border border-sky-100">
                <span class="text-[10px] font-extrabold uppercase text-sky-800 block font-sans tracking-wide">Shop → Company</span>
                <span class="text-base font-black text-sky-900 block mt-0.5">
                    ₹{{ number_format($allocationSummary['shop_to_company_allocated'], 2) }}
                </span>
            </div>

            <!-- Company → Shop Allocated -->
            <div class="p-3.5 bg-indigo-50/50 rounded-2xl border border-indigo-100">
                <span class="text-[10px] font-extrabold uppercase text-indigo-800 block font-sans tracking-wide">Company → Shop</span>
                <span class="text-base font-black text-indigo-900 block mt-0.5">
                    ₹{{ number_format($allocationSummary['company_to_shop_allocated'], 2) }}
                </span>
            </div>

            <!-- Allocation Count -->
            <div class="p-3.5 bg-slate-50/80 rounded-2xl border border-slate-200/80">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 block font-sans tracking-wide">Allocation Count</span>
                <span class="text-base font-black text-slate-900 block mt-0.5">
                    {{ number_format($allocationSummary['allocation_count']) }} <span class="text-xs font-semibold text-slate-500">records</span>
                </span>
            </div>
        </div>
    </div>

    <!-- 2. UNALLOCATED / PARTIALLY ALLOCATED SECTION -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-1.5">
                    <i data-lucide="clock" class="w-4 h-4 text-amber-600"></i>
                    <span>Unallocated / Partially Allocated</span>
                </h2>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full {{ $unallocatedPayments->isNotEmpty() ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' }} font-mono">
                    {{ $unallocatedPayments->count() }}
                </span>
            </div>
            <p class="text-xs text-slate-500 font-medium">Payments with money available for settlement allocation</p>
        </div>

        @if($unallocatedPayments->isEmpty())
            <div class="py-6 px-4 text-center rounded-2xl bg-slate-50/60 border border-slate-100 text-xs font-bold text-slate-500 font-sans">
                <i data-lucide="check-circle-2" class="w-5 h-5 mx-auto mb-1 text-emerald-500"></i>
                All payments for the selected period are fully allocated.
            </div>
        @else
            <div class="overflow-x-auto rounded-2xl border border-slate-200">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                            <th class="py-2.5 px-4 rounded-l-xl">Date</th>
                            <th class="py-2.5 px-4">Payment / Entry</th>
                            <th class="py-2.5 px-4">Direction</th>
                            <th class="py-2.5 px-4 text-right">Original Amount</th>
                            <th class="py-2.5 px-4 text-right">Allocated</th>
                            <th class="py-2.5 px-4 text-right">Remaining</th>
                            <th class="py-2.5 px-4 text-center">Status</th>
                            <th class="py-2.5 px-4 text-right rounded-r-xl">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-mono">
                        @foreach($unallocatedPayments as $unalloc)
                            @php
                                $uDate = $unalloc->payment_date ? \Carbon\Carbon::parse($unalloc->payment_date)->format('d M Y') : '—';
                                $uMethod = ucfirst($unalloc->payment_method ?? 'Bank');
                                $uAccount = $unalloc->reconciliations->first()?->companyAccount?->name ?? 'Company Account';
                                $uOrig = (float) $unalloc->payment_total_calc;
                                $uAlloc = (float) $unalloc->allocated_amount_calc;
                                $uRem = (float) $unalloc->unallocated_amount_calc;
                                $uIsPartial = $uAlloc > 0.01;
                            @endphp
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-2.5 px-4 font-sans font-bold text-slate-900">
                                    {{ $uDate }}
                                </td>
                                <td class="py-2.5 px-4 font-sans">
                                    <span class="font-bold text-slate-800 block">{{ $uMethod }} · <span class="font-mono text-[11px] text-slate-500">{{ $unalloc->payment_reference ?: 'No ref' }}</span></span>
                                    <span class="text-[10px] text-slate-400 font-mono">{{ $uAccount }}</span>
                                </td>
                                <td class="py-2.5 px-4 font-sans">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-sky-800 bg-sky-50 px-2 py-0.5 rounded-md border border-sky-100">
                                        Shop → Company
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 text-right font-bold text-slate-900">
                                    ₹{{ number_format($uOrig, 2) }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-bold text-emerald-700">
                                    ₹{{ number_format($uAlloc, 2) }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-black text-amber-700">
                                    ₹{{ number_format($uRem, 2) }}
                                </td>
                                <td class="py-2.5 px-4 text-center font-sans">
                                    <span class="inline-flex items-center text-[10px] font-extrabold px-2 py-0.5 rounded-md border {{ $uIsPartial ? 'bg-sky-50 text-sky-800 border-sky-200' : 'bg-amber-50 text-amber-800 border-amber-200' }}">
                                        {{ $uIsPartial ? 'Partially Allocated' : 'Unallocated' }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-4 text-right font-sans">
                                    @if($unalloc->cheque_status !== 'pending')
                                        <button type="button"
                                                @click="openAllocateModal({{ json_encode($unalloc) }})"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs cursor-pointer">
                                            <i data-lucide="check-square" class="w-3 h-3"></i>
                                            <span>Allocate</span>
                                        </button>
                                    @else
                                        <span class="text-[10px] text-slate-400 font-bold">Floating Cheque</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- 3. ALL PAYMENT RECORDS (EXTENDED TABLE WITH EXPANDABLE ALLOCATIONS) -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900">Payment History &amp; Allocation Details</h2>
                <p class="text-xs text-slate-500 mt-0.5">Click "View Allocation" on any payment to view source-to-destination settlement mappings</p>
            </div>
            <span class="text-xs text-slate-500 font-mono font-bold">Total: {{ $payments->total() }} records</span>
        </div>

        @include('admin.cashbook.shops.partials.payments-table', [
            'payments' => $payments,
            'isFull' => true,
            'currentShop' => $currentShop,
            'openSettlementTransactions' => $openSettlementTransactions,
            'month' => $month,
        ])

        @if($payments->hasPages())
            <div class="pt-4 border-t border-slate-100">
                {{ $payments->links() }}
            </div>
        @endif
    </div>

    <!-- 4. BULK AUTO ALLOCATE MODAL -->
    <div x-show="showBulkAllocateModal"
         x-cloak
         @keydown.escape.window="showBulkAllocateModal = false"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showBulkAllocateModal = false"
             class="bg-white rounded-3xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 bg-gradient-to-r from-slate-900 to-slate-800 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-emerald-500/20 text-emerald-400">
                        <i data-lucide="zap" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wide">Auto Allocate Payments</h3>
                        <p class="text-[11px] text-slate-300 font-medium">{{ $currentShop->name }} · {{ \Carbon\Carbon::parse($autoAllocateProposal['target_month'].'-01')->format('F Y') }}</p>
                    </div>
                </div>
                <button type="button" @click="showBulkAllocateModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition cursor-pointer">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST"
                  action="{{ route('admin.cashbook.shop.allocate-payments.bulk', $currentShop->slug ?: $currentShop->shop_id) }}"
                  class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="month" value="{{ $autoAllocateProposal['target_month'] }}">
                <input type="hidden" name="expected_total" :value="bulkExpectedTotal">
                <input type="hidden" name="submission_uuid" :value="bulkSubmissionUuid">

                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 font-mono text-xs space-y-3">
                    <div class="grid grid-cols-2 gap-3 font-sans">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase text-slate-400 block">Available Payments</span>
                            <span class="text-base font-black text-slate-900 font-mono">
                                ₹{{ number_format($autoAllocateProposal['eligible_amount'], 2) }}
                            </span>
                            <span class="text-[10px] text-slate-400 block mt-0.5 font-mono">{{ $autoAllocateProposal['eligible_count'] }} eligible</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-extrabold uppercase text-slate-400 block">Open Settlements</span>
                            <span class="text-base font-black text-slate-900 font-mono">
                                ₹{{ number_format($autoAllocateProposal['settlement_outstanding'], 2) }}
                            </span>
                            <span class="text-[10px] text-slate-400 block mt-0.5 font-mono">{{ $autoAllocateProposal['settlement_count'] }} obligations</span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-200/80 flex items-center justify-between font-sans">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase text-emerald-800 block">Proposed Allocation</span>
                            <span class="text-xl font-black text-emerald-700 font-mono" x-text="'₹' + Number(bulkExpectedTotal).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                        </div>
                        <div class="text-right text-[11px] text-slate-500">
                            FIFO matching by business date
                        </div>
                    </div>
                </div>

                <p class="text-xs text-slate-500 font-sans">
                    Auto-allocation will safely match eligible unallocated payments against the oldest outstanding daily settlements within a database transaction.
                </p>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 font-sans">
                    <button type="button" @click="showBulkAllocateModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition text-xs cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit"
                            :disabled="bulkExpectedTotal <= 0"
                            class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-black text-xs shadow-xs transition cursor-pointer">
                        Confirm &amp; Execute Auto Allocation
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 5. MANUAL SINGLE ALLOCATE MODAL -->
    <div x-show="showAllocateModal"
         x-cloak
         @keydown.escape.window="showAllocateModal = false"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showAllocateModal = false"
             class="bg-white rounded-3xl max-w-2xl w-full border border-slate-200 shadow-2xl overflow-hidden max-h-[92vh] flex flex-col animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 bg-gradient-to-r from-slate-900 to-slate-800 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-white/10">
                        <i data-lucide="check-square" class="w-5 h-5 text-emerald-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wide">Manual Payment Allocation</h3>
                        <p class="text-[11px] text-slate-300 font-medium">Select daily settlements to clear with this payment</p>
                    </div>
                </div>
                <button type="button" @click="showAllocateModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition cursor-pointer">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <template x-if="selectedPaymentForAlloc">
                <form method="POST"
                      action="{{ route('admin.cashbook.shop.allocate-payment', $currentShop->slug ?: $currentShop->shop_id) }}"
                      class="p-6 space-y-4 overflow-y-auto font-sans">
                    @csrf
                    <input type="hidden" name="payment_request_id" :value="selectedPaymentForAlloc.id">
                    <input type="hidden" name="month" value="{{ $month }}">

                    <!-- Selected Payment Summary -->
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 font-mono text-xs space-y-3">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div>
                                <span class="text-[9px] font-extrabold uppercase text-slate-400 block font-sans">Total Amount</span>
                                <span class="text-base font-black text-slate-900" x-text="'₹' + Number(selectedPaymentForAlloc.payment_total_calc || selectedPaymentForAlloc.requested_amount || selectedPaymentForAlloc.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                            </div>
                            <div>
                                <span class="text-[9px] font-extrabold uppercase text-slate-400 block font-sans">Actual Allocated</span>
                                <span class="text-base font-black text-emerald-700" x-text="'₹' + Number(selectedPaymentForAlloc.allocated_amount_calc || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                            </div>
                            <div>
                                <span class="text-[9px] font-extrabold uppercase text-slate-400 block font-sans">Actual Unallocated</span>
                                <span class="text-base font-black" :class="Number(selectedPaymentForAlloc.unallocated_amount_calc || 0) < 0 ? 'text-rose-700' : 'text-amber-700'" x-text="'₹' + Number(selectedPaymentForAlloc.unallocated_amount_calc || 0).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                            </div>
                            <div>
                                <span class="text-[9px] font-extrabold uppercase text-slate-400 block font-sans">Status</span>
                                <span class="text-[11px] font-black text-slate-800" x-text="(selectedPaymentForAlloc.allocation_status || 'OK').toUpperCase()"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Settlements Picker Table -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-black uppercase tracking-wide text-slate-700 block">
                                Open Daily Settlements ({{ $openSettlementTransactions->where('remaining_due', '>', 0)->count() }} Available)
                            </span>
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        @click="autoAllocateSingle()"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 text-[11px] font-extrabold transition cursor-pointer">
                                    <i data-lucide="zap" class="w-3 h-3 text-emerald-600"></i>
                                    <span>Auto Fill</span>
                                </button>
                                <button type="button"
                                        @click="clearAllAllocations()"
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-[11px] font-extrabold transition cursor-pointer">
                                    <i data-lucide="rotate-ccw" class="w-3 h-3 text-slate-400"></i>
                                    <span>Clear</span>
                                </button>
                            </div>
                        </div>

                        @if($openSettlementTransactions->where('remaining_due', '>', 0)->isEmpty())
                            <div class="p-6 text-center text-slate-400 border border-dashed border-slate-200 rounded-2xl text-xs font-bold">
                                No open settlement obligations found for {{ $currentShop->name }}.
                            </div>
                        @else
                            <div class="max-h-64 overflow-y-auto rounded-2xl border border-slate-200 divide-y divide-slate-100 font-mono text-xs">
                                @foreach($openSettlementTransactions->where('remaining_due', '>', 0) as $index => $settlement)
                                    <div class="p-3 hover:bg-slate-50 flex items-center justify-between gap-3">
                                        <div class="font-sans">
                                            <span class="font-extrabold text-slate-900 text-xs block">
                                                {{ $settlement['formatted_date'] }} · <span class="text-slate-600">{{ $settlement['entry_name'] }}</span>
                                            </span>
                                            <span class="text-[10px] text-slate-500 font-mono">
                                                Company Payable: ₹{{ number_format((float) $settlement['company_payable'], 2) }}
                                                • <strong class="text-slate-800">Remaining Due: ₹{{ number_format((float) $settlement['remaining_due'], 2) }}</strong>
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <input type="hidden" name="allocations[{{ $index }}][ledger_transaction_id]" value="{{ $settlement['id'] }}">
                                            <div class="relative w-32">
                                                <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400 text-xs font-bold">₹</span>
                                                <input type="number"
                                                       step="0.01"
                                                       min="0"
                                                       max="{{ $settlement['remaining_due'] }}"
                                                       placeholder="0.00"
                                                       name="allocations[{{ $index }}][amount]"
                                                       x-model="allocationsInput['{{ $settlement['id'] }}']"
                                                       class="w-full pl-6 pr-2 py-1.5 bg-slate-50 rounded-xl border border-slate-300 font-mono font-bold text-xs text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 font-sans">
                        <div class="font-mono text-xs">
                            <span class="text-slate-400 font-bold">Total Selected: </span>
                            <span class="font-black text-slate-900" x-text="'₹' + Number(totalAllocatedSum()).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" @click="showAllocateModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition text-xs cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit"
                                    :disabled="totalAllocatedSum() <= 0 || remainingUnallocatedPayment() < 0"
                                    class="px-5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 disabled:cursor-not-allowed text-white font-black text-xs shadow-xs transition cursor-pointer">
                                Save Allocation
                            </button>
                        </div>
                    </div>
                </form>
            </template>
        </div>
    </div>
</div>

<script>
function shopPaymentHistoryData() {
    return {
        showBulkAllocateModal: false,
        showAllocateModal: false,
        expandedPaymentId: null,
        selectedPaymentForAlloc: null,
        allocationsInput: {},
        bulkExpectedTotal: {{ (float) $autoAllocateProposal['proposed_total'] }},
        bulkSubmissionUuid: '',
        openSettlementsList: @json($openSettlementTransactions->values()),

        generateUuid() {
            if (typeof window !== 'undefined' && window.crypto && typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = (Math.random() * 16) | 0;
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            });
        },

        toggleExpandPayment(id) {
            this.expandedPaymentId = this.expandedPaymentId === id ? null : id;
        },

        openBulkAllocateModal() {
            this.bulkSubmissionUuid = this.generateUuid();
            this.showBulkAllocateModal = true;
        },

        openAllocateModal(payment) {
            this.selectedPaymentForAlloc = payment;
            this.allocationsInput = {};
            this.showAllocateModal = true;
        },

        autoAllocateSingle() {
            if (!this.selectedPaymentForAlloc) return;
            let paymentRemaining = parseFloat(this.selectedPaymentForAlloc.unallocated_amount_calc || 0);
            if (isNaN(paymentRemaining) || paymentRemaining <= 0) return;

            this.allocationsInput = {};
            let sortedSettlements = [...this.openSettlementsList].sort((a, b) => {
                let dateA = new Date(a.business_date);
                let dateB = new Date(b.business_date);
                if (dateA < dateB) return -1;
                if (dateA > dateB) return 1;
                return (parseInt(a.id) || 0) - (parseInt(b.id) || 0);
            });

            for (let settlement of sortedSettlements) {
                let due = parseFloat(settlement.remaining_due || 0);
                if (due <= 0) continue;

                let alloc = Math.min(paymentRemaining, due);
                this.allocationsInput[settlement.id] = alloc.toFixed(2);
                paymentRemaining -= alloc;

                if (paymentRemaining <= 0) break;
            }
        },

        clearAllAllocations() {
            this.allocationsInput = {};
        },

        totalAllocatedSum() {
            let sum = 0;
            for (let k in this.allocationsInput) {
                let val = parseFloat(this.allocationsInput[k]);
                if (!isNaN(val) && val > 0) sum += val;
            }
            return Math.round(sum * 100) / 100;
        },

        remainingUnallocatedPayment() {
            if (!this.selectedPaymentForAlloc) return 0;
            let base = parseFloat(this.selectedPaymentForAlloc.unallocated_amount_calc || 0);
            return Math.round((base - this.totalAllocatedSum()) * 100) / 100;
        }
    };
}
</script>
@endsection
