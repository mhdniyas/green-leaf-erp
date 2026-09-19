@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop').' — '.($financialReport['period']['label'] ?? 'Action Center'))

@section('content')
@php
    $carbonDate = \Illuminate\Support\Carbon::parse($businessDate);
    $formattedBusinessDate = $carbonDate->format('d M Y');
    $dayOfWeek = $carbonDate->format('l');
    $isToday = $businessDate === $todayDate;

    // Collections partitioning
    $allCollections = collect($dailySettlement['collections'] ?? []);
    $needsAcceptance = $allCollections->filter(fn($c) => !empty($c['can_accept']));
    $needsVerification = $allCollections->filter(fn($c) => in_array($c['tx_status'] ?? '', ['approved', \App\Enums\Cashbook\TransactionStatus::Approved->value], true) && empty($c['is_received']));
    $receivedCollections = $allCollections->filter(fn($c) => !empty($c['is_received']));

    // Totals for headers & badges
    $pendingAcceptanceAmount = $needsAcceptance->sum('amount');
    $pendingVerificationAmount = (float) ($dailySettlement['company_receipt_status']['pending_verification'] ?? 0);
    $cashWithShopAmount = (float) ($dailySettlement['company_receipt_status']['cash_still_with_shop'] ?? 0);
    $verifiedReceivedAmount = (float) ($dailySettlement['company_receipt_status']['verified_received'] ?? 0);
    $expectedPayableAmount = (float) ($dailySettlement['settlement_summary']['expected_payable'] ?? 0);
    $outstandingAmount = (float) ($dailySettlement['settlement_summary']['outstanding_to_settle'] ?? 0);
    $grossSalesAmount = (float) ($dailySettlement['gross_sales'] ?? 0);
    $totalDeductionsAmount = (float) ($dailySettlement['total_deductions'] ?? 0);

    // Calendar month grid calculation
    $monthCarbon = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month);
    $monthTitle = $monthCarbon->format('F Y');
@endphp

<div class="mx-auto max-w-7xl space-y-6 pb-16"
     x-data="{
        showCalendarModal: false,
        showAdjustmentsDrawer: false,
        showAddAdjustmentModal: false,
        showReverseModal: false,
        showReceivePaymentModal: false,
        showFundPettyModal: false,
        showAllocateModal: false,
        showBulkAllocateModal: false,
        showPaymentDetailsModal: false,
        showPaymentDetailsListModal: false,
        showAllocationBreakdownModal: false,
        showReconcilePaymentModal: false,
        selectedPaymentForAlloc: null,
        selectedPaymentForDetails: null,
        selectedPaymentForBreakdown: null,
        selectedPaymentForReconcile: null,
        allocationsInput: {},
        targetAdjustmentId: null,
        targetAdjustmentName: '',
        targetAdjustmentAmount: '',
        reverseReason: '',
        isSubmitting: false,
        openSettlementsList: @js($openSettlementTransactions->values()),
        paymentsReceived: {{ (float) $totalPaymentsReceived }},
        paymentsAllocated: {{ (float) $totalPaymentsAllocated }},
        paymentsUnallocated: {{ (float) $unallocatedPayments }},
        eligibleUnallocated: {{ (float) $eligibleUnallocated }},
        settlementDue: {{ (float) $netSettlementDue }},
        settlementAllocated: {{ (float) $totalSettlementAllocated }},
        settlementOutstanding: {{ (float) $settlementOutstanding }},
        netPositionDirection: '{{ $netPositionDirection }}',
        netPositionAmount: {{ (float) $netPositionAmount }},
        allPaymentsCount: {{ (int) $shopPaymentSummary['payment_count'] }},
        paymentsList: @js($allShopPaymentsFormattedList ?? []),
        bulkEligiblePayments: @js($bulkEligiblePayments),
        bulkAutoAllocated: false,
        bulkSelectedTotal: 0,
        bulkRemainingAfter: {{ (float) $unallocatedPayments }},
        bulkEligibleAmount: {{ (float) collect($bulkEligiblePayments)->sum('unallocated') }},
        bulkSettlementOutstanding: {{ (float) $openSettlementTransactions->sum('remaining_due') }},
        bulkSubmissionUuid: '',
        isLocalRepairMode: @js(app()->environment(['local', 'testing'])),
        formatCurrency(num) {
            return Number(num || 0).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },
        formatAllocationFlag(flag) {
            const safeFlag = String(flag || 'OK').toUpperCase();
            if (safeFlag === 'OK') return 'OK';
            return safeFlag.replaceAll('_', ' ');
        },
        submitLocalRepairAction(actionUrl, confirmMessage) {
            if (!this.isLocalRepairMode || !this.selectedPaymentForAlloc) return;
            if (!window.confirm(confirmMessage)) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = actionUrl;

            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = '{{ csrf_token() }}';

            const paymentInput = document.createElement('input');
            paymentInput.type = 'hidden';
            paymentInput.name = 'payment_request_id';
            paymentInput.value = String(this.selectedPaymentForAlloc.id || '');

            const monthInput = document.createElement('input');
            monthInput.type = 'hidden';
            monthInput.name = 'month';
            monthInput.value = '{{ $month }}';

            form.appendChild(tokenInput);
            form.appendChild(paymentInput);
            form.appendChild(monthInput);
            document.body.appendChild(form);
            this.isSubmitting = true;
            form.submit();
        },
        onShopCashbookUpdated(data) {
            if (data.company_money_received) {
                this.paymentsReceived = Number(data.company_money_received.received || 0);
                this.paymentsAllocated = Number(data.company_money_received.allocated || 0);
                this.paymentsUnallocated = Number(data.company_money_received.unallocated || 0);
                this.eligibleUnallocated = Number(data.company_money_received.eligible_unallocated || 0);
            }
            if (data.settlement_summary) {
                this.settlementDue = Number(data.settlement_summary.due || 0);
                this.settlementAllocated = Number(data.settlement_summary.allocated || 0);
                this.settlementOutstanding = Number(data.settlement_summary.outstanding || 0);
            }
            if (data.net_position) {
                this.netPositionDirection = data.net_position.direction || 'settled';
                this.netPositionAmount = Number(data.net_position.amount || 0);
            }
            if (data.shop_payments) {
                this.allPaymentsCount = Number(data.shop_payments.total || 0);
                if (Array.isArray(data.shop_payments.items)) {
                    this.paymentsList = data.shop_payments.items;
                }
            }
            if (window.lucide) {
                setTimeout(() => window.lucide.createIcons(), 50);
            }
        },
        openReverse(id, name, amount) {
            this.targetAdjustmentId = id;
            this.targetAdjustmentName = name;
            this.targetAdjustmentAmount = amount;
            this.reverseReason = '';
            this.showReverseModal = true;
        },
        openReceivePayment() {
            this.showReceivePaymentModal = true;
        },
        openReceivePaymentModal() {
            this.showReceivePaymentModal = true;
        },
        openAllocateModal(payment) {
            this.selectedPaymentForAlloc = payment;
            this.allocationsInput = {};
            this.showAllocateModal = true;
        },
        openDetailsModal(payment) {
            this.selectedPaymentForDetails = payment;
            this.showPaymentDetailsModal = true;
        },
        openDetailsListModal() {
            this.showPaymentDetailsListModal = true;
        },
        generateUuid() {
            if (typeof window !== 'undefined' && window.crypto && typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }
            if (typeof window !== 'undefined' && window.crypto && typeof window.crypto.getRandomValues === 'function') {
                return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, function (c) {
                    return (c ^ window.crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16);
                });
            }
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = (Math.random() * 16) | 0;
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            });
        },
        openBulkAllocateModal() {
            this.bulkSubmissionUuid = this.generateUuid();
            this.autoAllocateAllPayments();
            this.showBulkAllocateModal = true;
        },
        autoAllocateAllPayments() {
            this.bulkSelectedTotal = Math.round(Math.min(this.bulkEligibleAmount, this.bulkSettlementOutstanding) * 100) / 100;
            this.bulkRemainingAfter = Math.round((this.paymentsUnallocated - this.bulkSelectedTotal) * 100) / 100;
            this.bulkAutoAllocated = this.bulkSelectedTotal > 0;
        },
        clearBulkAllocation() {
            this.bulkSelectedTotal = 0;
            this.bulkRemainingAfter = this.paymentsUnallocated;
            this.bulkAutoAllocated = false;
        },
        openAllocationBreakdownModal(payment) {
            this.selectedPaymentForBreakdown = payment;
            this.showAllocationBreakdownModal = true;
        },
        openReconcileModal(payment) {
            this.selectedPaymentForReconcile = payment;
            this.showReconcilePaymentModal = true;
        },
        autoAllocate() {
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
            for (let id in this.allocationsInput) {
                let val = parseFloat(this.allocationsInput[id]);
                if (!isNaN(val) && val > 0) sum += val;
            }
            return Math.round(sum * 100) / 100;
        },
        remainingUnallocatedPayment() {
            if (!this.selectedPaymentForAlloc) return 0;
            let base = parseFloat(this.selectedPaymentForAlloc.unallocated_amount_calc || 0);
            return Math.round((base - this.totalAllocatedSum()) * 100) / 100;
        }
     }">

    <!-- STEP 3 — SIMPLE PAGE HEADER & PERIOD SWITCHER -->
    @include('admin.cashbook.shops.partials.header-period-nav')

    <!-- TOP CARDS — BALANCE AFTER ALLOCATION & NET OPERATING BALANCE (1 ROW) -->
    @include('admin.cashbook.shops.partials.top-balance-cards')

    <!-- STEP 4 — SHOP SUMMARY (6 PRIMARY METRICS) -->
    @include('admin.cashbook.shops.partials.shop-summary-cards')

    <!-- SALES REPORT TAB SECTION -->
    @include('admin.cashbook.shops.partials.sales-report-section')

    <!-- STEP 5 & 6 — COMPANY SETTLEMENT & RECEIVED BY -->
    @include('admin.cashbook.shops.partials.settlement-and-payment-modes')

    <!-- STEP 7 — CURRENT POSITION -->
    @include('admin.cashbook.shops.partials.current-position-card')

    <!-- STEP 8 — PETTY -->
    @include('admin.cashbook.shops.partials.petty-summary-card')

    <!-- STEP 9 & 10 — SHOP EXPENSES & FUNDING SPLIT -->
    @include('admin.cashbook.shops.partials.shop-expenses-table')

    <!-- STEP 11 — OPERATIONS (ORDERED COLLAPSIBLE SECTIONS) -->
    @include('admin.cashbook.shops.partials.operations-section')

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- ── MODALS: RECEIVE PAYMENT, ALLOCATION, PAYMENT DETAILS ──────── -->
    <!-- ══════════════════════════════════════════════════════════════════ -->

    <!-- 1. RECEIVE PAYMENT MODAL -->
    <div x-show="showReceivePaymentModal"
         x-cloak
         @keydown.escape.window="showReceivePaymentModal = false"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showReceivePaymentModal = false"
             class="bg-white rounded-3xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 bg-gradient-to-r from-emerald-800 to-teal-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-white/10">
                        <i data-lucide="wallet" class="w-5 h-5 text-emerald-300"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wide">Receive Shop Payment</h3>
                        <p class="text-[11px] text-emerald-200 font-medium">Record incoming money from {{ $currentShop->name }}</p>
                    </div>
                </div>
                <button type="button" @click="showReceivePaymentModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST"
                  action="{{ route('admin.cashbook.shop.receive-payment', $currentShop->slug ?: $currentShop->shop_id) }}"
                  class="p-6 space-y-4 text-xs font-medium text-slate-700"
                  x-data="{ paymentMethod: 'bank' }">
                @csrf

                <div class="grid grid-cols-2 gap-4">
                    <!-- Amount -->
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                            Amount (₹) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 font-bold">₹</span>
                            <input type="number"
                                   step="0.01"
                                   min="0.01"
                                   name="amount"
                                   required
                                   placeholder="0.00"
                                   class="w-full pl-7 pr-3 py-2 bg-slate-50 rounded-xl border border-slate-300 font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                        </div>
                    </div>

                    <!-- Payment Date -->
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                            Business Date <span class="text-rose-500">*</span>
                        </label>
                        <input type="date"
                               name="payment_date"
                               value="{{ $businessDate }}"
                               required
                               class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-300 font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Payment Method -->
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                            Payment Method <span class="text-rose-500">*</span>
                        </label>
                        <select name="payment_method"
                                x-model="paymentMethod"
                                required
                                class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-300 font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-600 focus:outline-none cursor-pointer">
                            <option value="bank">Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="upi">UPI / Online</option>
                            <option value="card">Card</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>

                    <!-- Destination Company Account -->
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                            Destination Account <span class="text-rose-500">*</span>
                        </label>
                        <select name="company_account_id"
                                required
                                class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-300 font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-600 focus:outline-none cursor-pointer">
                            @foreach($companyAccounts as $acc)
                                <option value="{{ $acc->id }}">
                                    {{ $acc->name }} ({{ ucfirst($acc->account_type) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Cheque fields if Cheque selected -->
                <div x-show="paymentMethod === 'cheque'" x-cloak class="p-3 bg-violet-50 rounded-2xl border border-violet-200 grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[9px] font-black uppercase text-violet-800 mb-1">Cheque Bank Name</label>
                        <input type="text" name="cheque_bank_name" placeholder="e.g. HDFC / SBI" class="w-full px-2.5 py-1.5 bg-white rounded-lg border border-violet-300 text-xs font-bold text-slate-900">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase text-violet-800 mb-1">Cheque Date</label>
                        <input type="date" name="cheque_date" class="w-full px-2.5 py-1.5 bg-white rounded-lg border border-violet-300 text-xs font-bold text-slate-900">
                    </div>
                </div>

                <!-- Payment Reference -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                        Reference Number / Transaction ID
                    </label>
                    <input type="text"
                           name="payment_reference"
                           placeholder="e.g. UTR / IMPS / Cheque # / Deposit Slip"
                           class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-300 font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                        Notes / Remarks
                    </label>
                    <textarea name="notes"
                              rows="2"
                              placeholder="Add any internal remarks regarding this shop payment..."
                              class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-300 font-medium text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-600 focus:outline-none"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="showReceivePaymentModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-black shadow-sm transition cursor-pointer">
                        Record Received Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. ALLOCATE / VERIFY SETTLEMENT MODAL -->
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
                        <h3 class="text-sm font-black uppercase tracking-wide">Manual Expense Allocation</h3>
                        <p class="text-[11px] text-slate-300 font-medium">Select daily settlements to clear with this payment</p>
                    </div>
                </div>
                <button type="button" @click="showAllocateModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <template x-if="selectedPaymentForAlloc">
                <form method="POST"
                      action="{{ route('admin.cashbook.shop.allocate-payment', $currentShop->slug ?: $currentShop->shop_id) }}"
                      class="p-6 space-y-4 overflow-y-auto">
                    @csrf
                    <input type="hidden" name="payment_request_id" :value="selectedPaymentForAlloc.id">
                    <input type="hidden" name="month" value="{{ $month }}">

                    <!-- Selected Payment Summary -->
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 font-mono text-xs space-y-3">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div>
                                <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Total Amount</span>
                                <span class="text-base font-black text-slate-900" x-text="'₹' + Number(selectedPaymentForAlloc.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                            </div>
                            <div>
                                <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Actual Allocated</span>
                                <span class="text-base font-black text-emerald-700" x-text="'₹' + Number(selectedPaymentForAlloc.actual_allocated).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                            </div>
                            <div>
                                <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Actual Unallocated</span>
                                <span class="text-base font-black" :class="Number(selectedPaymentForAlloc.actual_unallocated) < 0 ? 'text-rose-700' : 'text-amber-700'" x-text="'₹' + Number(selectedPaymentForAlloc.actual_unallocated).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                            </div>
                            <div>
                                <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Allocation Status</span>
                                <span class="text-[11px] font-black text-slate-800" x-text="formatAllocationFlag(selectedPaymentForAlloc.allocation_integrity_flag)"></span>
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
                                        @click="autoAllocate()"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 text-[11px] font-extrabold transition cursor-pointer">
                                    <i data-lucide="zap" class="w-3 h-3 text-emerald-600"></i>
                                    <span>Auto Allocate</span>
                                </button>
                                <button type="button"
                                        @click="clearAllAllocations()"
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-[11px] font-extrabold transition cursor-pointer">
                                    <i data-lucide="rotate-ccw" class="w-3 h-3 text-slate-400"></i>
                                    <span>Clear All</span>
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
                                                {{ $settlement['formatted_date'] }}
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

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                        <div class="font-mono text-xs">
                            <span class="text-slate-400 font-bold">Total Selected: </span>
                            <span class="font-black text-slate-900" x-text="'₹' + Number(totalAllocatedSum()).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" @click="showAllocateModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition text-xs">
                                Cancel
                            </button>
                            <button type="submit"
                                    :disabled="totalAllocatedSum() <= 0 || remainingUnallocatedPayment() < 0"
                                    class="px-5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 disabled:cursor-not-allowed text-white font-black text-xs shadow-sm transition cursor-pointer">
                                Submit
                            </button>
                        </div>
                    </div>
                </form>
            </template>
        </div>
    </div>

    <!-- 3. ADD ADJUSTMENT MODAL -->
    <div x-show="showAddAdjustmentModal" style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs">
        <div class="bg-white rounded-3xl p-6 max-w-md w-full border border-slate-200 shadow-2xl space-y-4"
             @click.away="if(!isSubmitting) showAddAdjustmentModal = false">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-black text-slate-900">Add Settlement Adjustment</h3>
                <button type="button" @click="showAddAdjustmentModal = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.cashbook.shop.day.adjustments.store', $currentShop->slug ?: $currentShop->shop_id) }}" @submit="isSubmitting = true">
                @csrf
                <input type="hidden" name="business_date" value="{{ $businessDate }}">

                <div class="space-y-4 text-xs">
                    <div>
                        <label class="block font-extrabold text-slate-700 mb-1">Adjustment Type</label>
                        <select name="type" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl font-bold text-slate-900 focus:outline-none focus:border-slate-500">
                            <option value="expense">Shop Expense (Reduces Shop Outstanding)</option>
                            <option value="income">Shop Income (Increases Shop Outstanding)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-extrabold text-slate-700 mb-1">Amount (₹)</label>
                        <input type="number" name="amount" step="0.01" min="0.01" max="10000000" required placeholder="0.00"
                               class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl font-mono font-bold text-slate-900 focus:outline-none focus:border-slate-500">
                    </div>

                    <div>
                        <label class="block font-extrabold text-slate-700 mb-1">Note / Reason</label>
                        <textarea name="notes" rows="3" required minlength="3" maxlength="500" placeholder="Describe the reason for adjustment..."
                                  class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl font-medium text-slate-900 focus:outline-none focus:border-slate-500"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="showAddAdjustmentModal = false" :disabled="isSubmitting"
                                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" :disabled="isSubmitting"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-extrabold shadow-sm transition cursor-pointer disabled:opacity-50">
                            <span x-text="isSubmitting ? 'Recording...' : 'Record Adjustment'"></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. REVERSE ADJUSTMENT MODAL -->
    <div x-show="showReverseModal" style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs">
        <div class="bg-white rounded-3xl p-6 max-w-md w-full border border-slate-200 shadow-2xl space-y-4"
             @click.away="if(!isSubmitting) showReverseModal = false">
            <div class="flex items-center gap-3 text-rose-700">
                <div class="p-3 rounded-2xl bg-rose-50 border border-rose-200">
                    <i data-lucide="rotate-ccw" class="w-6 h-6"></i>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900">Reverse Adjustment</h3>
                    <p class="text-xs text-slate-500 font-medium">Create offsetting immutable ledger transaction</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.cashbook.shop.day.adjustments.reverse', $currentShop->slug ?: $currentShop->shop_id) }}" @submit="isSubmitting = true">
                @csrf
                <input type="hidden" name="business_date" value="{{ $businessDate }}">
                <input type="hidden" name="adjustment_id" :value="targetAdjustmentId">

                <div class="space-y-4 text-xs">
                    <div class="p-3 rounded-xl bg-rose-50/50 border border-rose-100 text-slate-700 space-y-1">
                        <div class="font-extrabold text-rose-950" x-text="'Reverse ' + targetAdjustmentName + ' of ₹' + targetAdjustmentAmount"></div>
                        <p class="text-[11px] text-slate-500">This will record an exact opposite ledger entry to neutralize the outstanding effect while preserving audit history.</p>
                    </div>

                    <div>
                        <label class="block font-extrabold text-slate-700 mb-1">Reversal Reason (Optional)</label>
                        <input type="text" name="reason" x-model="reverseReason" placeholder="e.g. Incorrect amount recorded"
                               class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl font-medium text-slate-900 focus:outline-none focus:border-slate-500">
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="showReverseModal = false" :disabled="isSubmitting"
                                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" :disabled="isSubmitting"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-extrabold shadow-sm transition cursor-pointer disabled:opacity-50">
                            <span x-text="isSubmitting ? 'Reversing...' : 'Confirm Reversal'"></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 5. FUND SHOP PETTY CASH MODAL -->
    @include('admin.cashbook.shops.partials.fund-petty-modal')

</div>
@endsection

@push('scripts')
<script>
    function monthlyBankingManager(config) {
        return {
            expanded: Boolean(config.expanded ?? false),
            statusFilter: config.statusFilter || 'pending',
            pendingCount: Number(config.pendingCount || 0),
            pendingAmount: Number(config.pendingAmount || 0),
            verifiedCount: Number(config.verifiedCount || 0),
            verifiedAmount: Number(config.verifiedAmount || 0),
            totalCount: Number(config.totalCount || 0),
            totalAmount: Number(config.totalAmount || 0),
            initialPageCount: Number(config.initialPageCount || 0),
            submittingIds: [],
            verifiedIds: [],

            formatCurrency(num) {
                return Number(num || 0).toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            },

            isRowSubmitting(txId) {
                return this.submittingIds.includes(Number(txId));
            },

            isRowVerified(txId, originallyReconciled) {
                return Boolean(originallyReconciled) || this.verifiedIds.includes(Number(txId));
            },

            isRowVisible(txId, originallyReconciled) {
                const isVerified = this.isRowVerified(txId, originallyReconciled);
                if (this.statusFilter === 'pending') {
                    return !isVerified;
                }
                if (this.statusFilter === 'verified') {
                    return isVerified;
                }
                return true;
            },

            hasNoVisibleRows() {
                if (this.initialPageCount === 0) {
                    return true;
                }
                if (this.statusFilter === 'pending') {
                    return this.pendingCount <= 0 || (this.initialPageCount - this.verifiedIds.length) <= 0;
                }
                return false;
            },

            async verifyRow(txId, businessDate, formEl) {
                const numericId = Number(txId);
                if (this.submittingIds.includes(numericId) || this.verifiedIds.includes(numericId)) {
                    return;
                }
                this.submittingIds.push(numericId);

                const formData = new FormData(formEl);
                const actionUrl = formEl.action;
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                try {
                    const response = await fetch(actionUrl, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken || ''
                        }
                    });

                    const contentType = response.headers.get('content-type') || '';
                    if (response.status === 401 || response.redirected || !contentType.includes('application/json')) {
                        if (response.status === 401 || (response.url && response.url.includes('login'))) {
                            showToast('Your session has expired. Please refresh the page to log in.', 'error');
                        } else {
                            showToast('Unexpected server response. Please refresh the page.', 'error');
                        }
                        this.submittingIds = this.submittingIds.filter(id => id !== numericId);
                        return;
                    }

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        const errorMsg = data.message || data.error || 'Verification failed. Please try again.';
                        showToast(errorMsg, 'error');
                        this.submittingIds = this.submittingIds.filter(id => id !== numericId);
                        return;
                    }

                    // Success
                    this.verifiedIds.push(numericId);
                    this.submittingIds = this.submittingIds.filter(id => id !== numericId);

                    if (data.banking_totals) {
                        this.pendingCount = Number(data.banking_totals.pending_count);
                        this.pendingAmount = Number(data.banking_totals.pending_amount);
                        this.verifiedCount = Number(data.banking_totals.verified_count);
                        this.verifiedAmount = Number(data.banking_totals.verified_amount);
                        this.totalCount = Number(data.banking_totals.total_count);
                        this.totalAmount = Number(data.banking_totals.total_amount);
                    }

                    window.dispatchEvent(new CustomEvent('shop-cashbook-updated', { detail: data }));

                    showToast(data.message || 'Payment successfully verified!', 'success');

                    if (window.lucide) {
                        setTimeout(() => window.lucide.createIcons(), 50);
                    }
                } catch (err) {
                    console.error('Verification error:', err);
                    showToast(err.message || 'Network error occurred while verifying payment.', 'error');
                    this.submittingIds = this.submittingIds.filter(id => id !== numericId);
                }
            }
        };
    }
</script>
@endpush
