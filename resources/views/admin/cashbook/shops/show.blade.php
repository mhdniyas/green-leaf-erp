@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop').($isDayDetail ? ' — '. \Illuminate\Support\Carbon::parse($businessDate)->format('d M Y') : ' — '. $monthlyData['month_title']))

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

    // Day status calculation
    $dayStatusKey = match(true) {
        $needsAcceptance->isNotEmpty() => 'needs_review',
        $needsVerification->isNotEmpty() => 'pending_receipt',
        $outstandingAmount > 0 => 'outstanding',
        default => 'settled',
    };

    $dayStatusLabel = match($dayStatusKey) {
        'needs_review' => 'Needs Review ('.$needsAcceptance->count().')',
        'pending_receipt' => 'Pending Receipt ('.$needsVerification->count().')',
        'outstanding' => 'Outstanding',
        'settled' => 'Fully Settled',
    };

    $dayStatusBadgeClass = match($dayStatusKey) {
        'needs_review' => 'bg-amber-50 text-amber-800 border-amber-200',
        'pending_receipt' => 'bg-sky-50 text-sky-800 border-sky-200',
        'outstanding' => 'bg-slate-100 text-slate-800 border-slate-300',
        'settled' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
    };

    // Calendar month grid calculation
    $monthCarbon = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month);
    $firstDayOfWeek = $monthCarbon->copy()->startOfMonth()->dayOfWeek; // 0 (Sun) to 6 (Sat)
    $daysInCurrentMonth = $monthCarbon->daysInMonth;
    $monthTitle = $monthCarbon->format('F Y');
@endphp

<div class="mx-auto max-w-7xl space-y-6 pb-16"
     x-data="{
        showCalendarModal: false,
        showAdjustmentsDrawer: false,
        showAddAdjustmentModal: false,
        showReverseModal: false,
        showReceivePaymentModal: false,
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

            // Sort settlements oldest business_date first (FIFO), then by ID
            let sortedSettlements = [...this.openSettlementsList].sort((a, b) => {
                let dateA = new Date(a.business_date);
                let dateB = new Date(b.business_date);
                if (dateA < dateB) return -1;
                if (dateA > dateB) return 1;
                return (parseInt(a.id) || 0) - (parseInt(b.id) || 0);
            });

            for (let s of sortedSettlements) {
                let due = parseFloat(s.remaining_due || 0);
                if (due <= 0) continue;

                if (paymentRemaining > 0) {
                    let alloc = Math.min(paymentRemaining, due);
                    alloc = Math.round(alloc * 100) / 100;
                    if (alloc > 0) {
                        this.allocationsInput[s.id] = alloc.toFixed(2);
                        paymentRemaining = Math.round((paymentRemaining - alloc) * 100) / 100;
                    }
                }
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
            let unalloc = parseFloat(this.selectedPaymentForAlloc.unallocated_amount_calc || 0);
            return Math.round((unalloc - this.totalAllocatedSum()) * 100) / 100;
        }
     }"
     @shop-cashbook-updated.window="onShopCashbookUpdated($event.detail)">

    @if(!$isDayDetail)
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- ── MONTHLY SHOP MONEY FLOW VIEW ──────────────────────────────── -->
        <!-- ══════════════════════════════════════════════════════════════════ -->

        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.cashbook.money-flow') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-extrabold transition-all">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                        <span>Money Flow</span>
                    </a>
                    <span class="text-slate-300">/</span>
                    <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold font-mono">
                        {{ $currentShop->code }}
                    </span>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 uppercase flex items-center gap-2">
                    <span>{{ $currentShop->name }}</span>
                    <span class="text-base font-normal text-slate-400 font-mono">&mdash; {{ $monthlyData['month_title'] }}</span>
                </h1>
                <p class="text-xs text-slate-500 font-medium">
                    Monthly operational collections, payments received, and daily settlement allocations for <span class="font-bold text-slate-800">{{ $currentShop->name }}</span>
                </p>
            </div>

            <!-- Month Switcher & Shop Controls -->
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Receive Payment Primary Action -->
                <button type="button"
                        @click="openReceivePayment()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-black shadow-sm transition cursor-pointer">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    <span>Receive Payment</span>
                </button>

                <!-- Month Switcher -->
                <div class="inline-flex items-center rounded-xl bg-slate-100 p-1 border border-slate-200 text-xs font-extrabold">
                    <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'month' => $monthlyData['prev_month']]) }}"
                       class="p-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-white transition"
                       title="Previous Month">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </a>
                    <span class="px-3 py-1 font-mono text-slate-800">{{ $monthlyData['month_title'] }}</span>
                    <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'month' => $monthlyData['next_month']]) }}"
                       class="p-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-white transition"
                       title="Next Month">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </a>
                </div>

                <!-- Shop Dropdown Selector -->
                <div>
                    <select onchange="window.location.href='/admin/cashbook/shops/' + this.value + '?month={{ $month }}'"
                            class="bg-slate-50 text-xs font-bold text-slate-800 px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-600 cursor-pointer">
                        @foreach($shops as $shopOption)
                            <option value="{{ $shopOption->slug ?: $shopOption->shop_id }}" {{ $currentShop->shop_id == $shopOption->shop_id ? 'selected' : '' }}>
                                {{ $shopOption->name }} ({{ $shopOption->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- ── CUMULATIVE SETTLEMENT & PAYMENT NET POSITION CARDS ─────────── -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Settlement Obligations Card -->
            <div class="p-5 rounded-3xl bg-white border border-slate-200 shadow-sm space-y-2">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                        <i data-lucide="receipt" class="w-3.5 h-3.5 text-slate-400"></i>
                        Settlement Obligations
                    </span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-600">All-Time</span>
                </div>
                <div class="grid grid-cols-3 gap-2 pt-1 font-mono">
                    <div>
                        <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Total Due</span>
                        <span class="text-sm font-black text-slate-900">₹<span x-text="formatCurrency(settlementDue)">{{ number_format($netSettlementDue, 2) }}</span></span>
                    </div>
                    <div>
                        <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Allocated</span>
                        <span class="text-sm font-black text-emerald-700">₹<span x-text="formatCurrency(settlementAllocated)">{{ number_format($totalSettlementAllocated, 2) }}</span></span>
                    </div>
                    <div>
                        <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Outstanding</span>
                        <span class="text-sm font-black" :class="settlementOutstanding > 0 ? 'text-amber-700' : 'text-slate-500'">₹<span x-text="formatCurrency(settlementOutstanding)">{{ number_format($settlementOutstanding, 2) }}</span></span>
                    </div>
                </div>
            </div>

            <!-- Payments Received Card -->
            <div class="p-5 rounded-3xl bg-white border border-slate-200 shadow-sm space-y-2">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <span class="text-[11px] font-black uppercase tracking-wider text-emerald-700 flex items-center gap-1.5">
                        <i data-lucide="wallet" class="w-3.5 h-3.5 text-emerald-600"></i>
                        Company Money Received
                    </span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700">Actual Cash/Bank</span>
                </div>
                <div class="grid grid-cols-3 gap-2 pt-1 font-mono">
                    <div>
                        <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Received</span>
                        <span class="text-sm font-black text-slate-900">₹<span x-text="formatCurrency(paymentsReceived)">{{ number_format($totalPaymentsReceived, 2) }}</span></span>
                    </div>
                    <div>
                        <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Allocated</span>
                        <span class="text-sm font-black text-emerald-700">₹<span x-text="formatCurrency(paymentsAllocated)">{{ number_format($totalPaymentsAllocated, 2) }}</span></span>
                    </div>
                    <div>
                        <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Unallocated</span>
                        <span class="text-sm font-black" :class="paymentsUnallocated > 0 ? 'text-sky-700' : 'text-slate-500'">₹<span x-text="formatCurrency(paymentsUnallocated)">{{ number_format($unallocatedPayments, 2) }}</span></span>
                    </div>
                </div>
            </div>

            <!-- Net Position Directional Card -->
            <div class="p-5 rounded-3xl border shadow-sm flex flex-col justify-between"
                 :class="netPositionDirection === 'shop_owes_company' ? 'bg-amber-950 text-white border-amber-900' : (netPositionDirection === 'company_owes_shop' ? 'bg-sky-950 text-white border-sky-900' : 'bg-slate-900 text-white border-slate-800')">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Net Position Direction</span>
                    <span class="text-[9px] font-black px-2 py-0.5 rounded-full"
                          :class="netPositionDirection === 'shop_owes_company' ? 'bg-amber-500/20 text-amber-300' : (netPositionDirection === 'company_owes_shop' ? 'bg-sky-500/20 text-sky-300' : 'bg-emerald-500/20 text-emerald-300')"
                          x-text="netPositionDirection.replace(/_/g, ' ').toUpperCase()">
                        {{ strtoupper(str_replace('_', ' ', $netPositionDirection)) }}
                    </span>
                </div>
                <div class="mt-2">
                    <p class="text-xs font-bold text-slate-300 uppercase"
                       x-text="netPositionDirection === 'shop_owes_company' ? 'Shop Owes Company' : (netPositionDirection === 'company_owes_shop' ? 'Company Owes Shop (Advance/Credit)' : 'Fully Balanced & Settled')">
                        @if($netPositionDirection === 'shop_owes_company')
                            Shop Owes Company
                        @elseif($netPositionDirection === 'company_owes_shop')
                            Company Owes Shop (Advance/Credit)
                        @else
                            Fully Balanced &amp; Settled
                        @endif
                    </p>
                    <p class="text-2xl font-black font-mono mt-0.5"
                       :class="netPositionDirection === 'shop_owes_company' ? 'text-amber-400' : (netPositionDirection === 'company_owes_shop' ? 'text-sky-400' : 'text-emerald-400')">
                        ₹<span x-text="formatCurrency(netPositionAmount)">{{ number_format($netPositionAmount, 2) }}</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- ── SHOP PAYMENTS & SETTLEMENT ALLOCATION SUMMARY ──────────────── -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <i data-lucide="layers" class="w-4 h-4 text-emerald-600"></i>
                    <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">
                        Shop Payments &amp; Settlement Allocation
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-400 font-mono font-bold">
                        <span x-text="allPaymentsCount">{{ $shopPaymentSummary['payment_count'] }}</span> <span x-text="allPaymentsCount === 1 ? 'Payment' : 'Payments'">{{ Str::plural('Payment', $shopPaymentSummary['payment_count']) }}</span> Recorded
                        <span class="sr-only">{{ $shopPaymentSummary['payment_count'] }} {{ Str::plural('Payment', $shopPaymentSummary['payment_count']) }} Recorded</span>
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <span class="text-[10px] font-black uppercase text-slate-400">Total Received</span>
                    <p class="mt-1 text-xl font-black font-mono text-slate-900">₹<span x-text="formatCurrency(paymentsReceived)">{{ number_format($totalPaymentsReceived, 2) }}</span></p>
                </div>
                <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
                    <span class="text-[10px] font-black uppercase text-sky-700">Total Allocated</span>
                    <p class="mt-1 text-xl font-black font-mono text-sky-800">₹<span x-text="formatCurrency(paymentsAllocated)">{{ number_format($totalPaymentsAllocated, 2) }}</span></p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <span class="text-[10px] font-black uppercase text-amber-700">Total Unallocated</span>
                    <p class="mt-1 text-xl font-black font-mono text-amber-800">₹<span x-text="formatCurrency(paymentsUnallocated)">{{ number_format($unallocatedPayments, 2) }}</span></p>
                </div>
                <div class="rounded-2xl border p-4"
                     :class="eligibleUnallocated > 0 ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50'">
                    <span class="text-[10px] font-black uppercase" :class="eligibleUnallocated > 0 ? 'text-emerald-700' : 'text-slate-400'">Eligible Unallocated</span>
                    <p class="mt-1 text-xl font-black font-mono" :class="eligibleUnallocated > 0 ? 'text-emerald-800' : 'text-slate-500'">₹<span x-text="formatCurrency(eligibleUnallocated)">{{ number_format($eligibleUnallocated, 2) }}</span></p>
                </div>
                <div class="rounded-2xl border p-4"
                     :class="settlementOutstanding > 0 ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50'">
                    <span class="text-[10px] font-black uppercase" :class="settlementOutstanding > 0 ? 'text-rose-700' : 'text-emerald-700'">Settlement Outstanding</span>
                    <p class="mt-1 text-xl font-black font-mono" :class="settlementOutstanding > 0 ? 'text-rose-800' : 'text-emerald-800'">₹<span x-text="formatCurrency(settlementOutstanding)">{{ number_format($settlementOutstanding, 2) }}</span></p>
                </div>
            </div>

            {{-- Status message: distinguish money-exhausted from settlements-cleared --}}
            <div class="pt-2" x-show="paymentsReceived > 0 || settlementOutstanding > 0">
                <div x-show="eligibleUnallocated <= 0 && settlementOutstanding > 0"
                     class="p-3 bg-amber-50 rounded-2xl border border-amber-200 text-amber-900 text-xs font-bold">
                    <i data-lucide="info" class="w-3.5 h-3.5 inline-block mr-1"></i>
                    All available money allocated. Settlement outstanding: ₹<span x-text="formatCurrency(settlementOutstanding)"></span>.
                </div>
                <div x-show="settlementOutstanding <= 0 && eligibleUnallocated > 0"
                     class="p-3 bg-emerald-50 rounded-2xl border border-emerald-200 text-emerald-900 text-xs font-bold">
                    <i data-lucide="check-circle" class="w-3.5 h-3.5 inline-block mr-1"></i>
                    All eligible settlements cleared. Unallocated money: ₹<span x-text="formatCurrency(eligibleUnallocated)"></span>.
                </div>
                <div x-show="settlementOutstanding <= 0 && eligibleUnallocated <= 0 && paymentsReceived > 0"
                     class="p-3 bg-emerald-50 rounded-2xl border border-emerald-200 text-emerald-900 text-xs font-bold">
                    <i data-lucide="check-circle" class="w-3.5 h-3.5 inline-block mr-1"></i>
                    Fully settled — all money allocated and all settlements cleared.
                </div>
                <div x-show="paymentsUnallocated > 0 && eligibleUnallocated <= 0 && paymentsUnallocated !== eligibleUnallocated"
                     class="p-3 bg-slate-50 rounded-2xl border border-slate-200 text-slate-700 text-xs font-bold mt-2">
                    <i data-lucide="alert-circle" class="w-3.5 h-3.5 inline-block mr-1"></i>
                    ₹<span x-text="formatCurrency(paymentsUnallocated - eligibleUnallocated)"></span> unallocated but ineligible (pending cheque or unverified).
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-2 border-t border-slate-100 pt-4">
                <button type="button"
                        @click="openDetailsListModal()"
                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black transition cursor-pointer">
                    <i data-lucide="eye" class="w-4 h-4"></i>
                    <span>Details</span>
                </button>
                <form method="POST"
                      action="{{ route('admin.cashbook.shop.allocate-payments.clear-all', $currentShop->slug) }}"
                      class="inline-block"
                      @submit.prevent="
                          const paymentsAffected = (paymentsList || []).filter(p => Number(p.allocated || 0) > 0).length;
                          const settlementsAffected = (openSettlementsList || []).filter(s => Number(s.already_allocated || 0) > 0).length;
                          const msg = 'Clear all payment allocations for this shop?\n\nThis will remove all bill allocations and return the allocated amounts to the payments\' unallocated balance.\n\nThe original payment records will remain unchanged.\n\nImpact:\n• Payments affected: ' + paymentsAffected + '\n• Amount released to release: ₹' + formatCurrency(paymentsAllocated) + '\n• Bills/ledger entries affected: ' + settlementsAffected;
                          if (window.confirm(msg)) $el.submit();
                      ">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <button type="submit"
                            :disabled="paymentsAllocated <= 0"
                            class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 disabled:opacity-40 disabled:cursor-not-allowed text-rose-700 border border-rose-200 text-xs font-black transition cursor-pointer">
                        <i data-lucide="trash-2" class="w-4 h-4 text-rose-600"></i>
                        <span>Clear All Allocations</span>
                    </button>
                </form>
                <button type="button"
                        @click="openBulkAllocateModal()"
                        :disabled="eligibleUnallocated <= 0 || bulkSettlementOutstanding <= 0"
                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-black shadow-sm transition cursor-pointer">
                    <i data-lucide="check-square" class="w-4 h-4"></i>
                    <span>Auto Allocate All</span>
                </button>
            </div>
        </div>

        @include('admin.cashbook.settings.settlements.summary')

        <!-- Daily Summary Rows Table/List -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <i data-lucide="calendar" class="w-4 h-4 text-emerald-600"></i>
                    <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">
                        Daily Settlement Summaries &mdash; {{ $monthlyData['month_title'] }}
                    </h2>
                </div>
                <span class="text-xs text-slate-400 font-mono font-bold">
                    {{ count($monthlyData['days']) }} {{ Str::plural('day', count($monthlyData['days'])) }} recorded
                </span>
            </div>

            @if(empty($monthlyData['days']))
                <div class="p-12 text-center text-slate-400">
                    <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                    <p class="text-xs font-bold">No ledger activity recorded for {{ $currentShop->name }} in {{ $monthlyData['month_title'] }}.</p>
                </div>
            @else
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                                <th class="py-3 px-4 rounded-l-xl">Date</th>
                                <th class="py-3 px-4 text-right">Collections</th>
                                <th class="py-3 px-4 text-right">Company Received</th>
                                <th class="py-3 px-4 text-right">Pending Acceptance</th>
                                <th class="py-3 px-4 text-right">Pending Verification</th>
                                <th class="py-3 px-4 text-right">Still To Settle</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-right rounded-r-xl">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono">
                            @foreach($monthlyData['days'] as $day)
                                @php
                                    $statusBadgeClass = match($day['status_key']) {
                                        'needs_attention' => 'bg-rose-50 text-rose-800 border-rose-200',
                                        'needs_acceptance' => 'bg-amber-50 text-amber-800 border-amber-200',
                                        'pending_verification' => 'bg-sky-50 text-sky-800 border-sky-200',
                                        default => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                    };
                                    $dayDetailUrl = route('admin.cashbook.shop.show', [
                                        'shop' => $currentShop->slug ?: $currentShop->shop_id,
                                        'date' => $day['business_date'],
                                    ]);
                                @endphp
                                <tr class="hover:bg-slate-50/80 transition-colors {{ $day['is_today'] ? 'bg-emerald-50/30' : '' }}">
                                    <td class="py-3.5 px-4 font-sans">
                                        <div class="flex items-center gap-2">
                                            <span class="font-extrabold text-slate-900 text-sm">{{ $day['formatted_date'] }}</span>
                                            <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-500 font-mono text-[10px] font-bold">
                                                {{ $day['day_name'] }}
                                            </span>
                                            @if($day['is_today'])
                                                <span class="px-1.5 py-0.5 rounded-md bg-emerald-600 text-white text-[9px] font-black uppercase tracking-wider">
                                                    Today
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-bold text-slate-800">
                                        ₹{{ number_format($day['total_collection'], 2) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-bold text-emerald-700">
                                        ₹{{ number_format($day['company_received'], 2) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-bold {{ $day['pending_acceptance'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                                        ₹{{ number_format($day['pending_acceptance'], 2) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-bold {{ $day['pending_verification'] > 0 ? 'text-sky-700' : 'text-slate-400' }}">
                                        ₹{{ number_format($day['pending_verification'], 2) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-black text-slate-900">
                                        ₹{{ number_format($day['outstanding'], 2) }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center font-sans">
                                        <span class="inline-flex items-center text-[10px] font-extrabold px-2.5 py-1 rounded-lg border {{ $statusBadgeClass }}">
                                            {{ $day['status'] }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-sans">
                                        <a href="{{ $dayDetailUrl }}"
                                           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-emerald-700 text-white text-xs font-extrabold transition-all shadow-xs cursor-pointer">
                                            <span>Open Day Details</span>
                                            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card List View -->
                <div class="lg:hidden space-y-3 font-mono">
                    @foreach($monthlyData['days'] as $day)
                        @php
                            $statusBadgeClass = match($day['status_key']) {
                                'needs_attention' => 'bg-rose-50 text-rose-800 border-rose-200',
                                'needs_acceptance' => 'bg-amber-50 text-amber-800 border-amber-200',
                                'pending_verification' => 'bg-sky-50 text-sky-800 border-sky-200',
                                default => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                            };
                            $dayDetailUrl = route('admin.cashbook.shop.show', [
                                'shop' => $currentShop->slug ?: $currentShop->shop_id,
                                'date' => $day['business_date'],
                            ]);
                        @endphp
                        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-xs space-y-3">
                            <div class="flex items-center justify-between font-sans">
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-slate-900">{{ $day['formatted_date'] }}</span>
                                    <span class="text-[10px] text-slate-400 font-bold uppercase font-mono">{{ $day['day_name'] }}</span>
                                </div>
                                <span class="inline-flex items-center text-[9px] font-extrabold px-2 py-0.5 rounded-md border {{ $statusBadgeClass }}">
                                    {{ $day['status'] }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-slate-100">
                                <div>
                                    <span class="text-[10px] font-sans font-extrabold text-slate-400 uppercase">Collection</span>
                                    <div class="font-bold text-slate-800">₹{{ number_format($day['total_collection'], 2) }}</div>
                                </div>
                                <div>
                                    <span class="text-[10px] font-sans font-extrabold text-emerald-700 uppercase">Company Received</span>
                                    <div class="font-bold text-emerald-800">₹{{ number_format($day['company_received'], 2) }}</div>
                                </div>
                                <div>
                                    <span class="text-[10px] font-sans font-extrabold text-amber-700 uppercase">Pending Accept</span>
                                    <div class="font-bold text-amber-800">₹{{ number_format($day['pending_acceptance'], 2) }}</div>
                                </div>
                                <div>
                                    <span class="text-[10px] font-sans font-extrabold text-sky-700 uppercase">Pending Verify</span>
                                    <div class="font-bold text-sky-800">₹{{ number_format($day['pending_verification'], 2) }}</div>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-slate-200 flex items-center justify-between">
                                <div>
                                    <span class="text-[10px] font-sans font-extrabold text-slate-500 uppercase">Still To Settle: </span>
                                    <span class="font-mono font-black text-slate-900 text-sm">₹{{ number_format($day['outstanding'], 2) }}</span>
                                </div>
                                <a href="{{ $dayDetailUrl }}"
                                   class="inline-flex items-center gap-1 px-3 py-1 rounded-xl bg-slate-900 text-white text-xs font-extrabold">
                                    <span>Details</span>
                                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- ── BANKING / PAYMENT VERIFICATION SECTION (MONTHLY VIEW) ── -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-6"
             x-data="monthlyBankingManager({
                 statusFilter: '{{ $bankingStatusFilter }}',
                 pendingCount: {{ (int) $bankingTotals['pending_count'] }},
                 pendingAmount: {{ (float) $bankingTotals['pending_amount'] }},
                 verifiedCount: {{ (int) $bankingTotals['verified_count'] }},
                 verifiedAmount: {{ (float) $bankingTotals['verified_amount'] }},
                 totalCount: {{ (int) $bankingTotals['total_count'] }},
                 totalAmount: {{ (float) $bankingTotals['total_amount'] }},
                 initialPageCount: {{ $bankingPagination ? (int) $bankingPagination->count() : 0 }}
             })">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="landmark" class="w-5 h-5 text-indigo-600"></i>
                        <h2 class="text-base font-black text-slate-900 uppercase tracking-wide">
                            Banking &middot; Payment Verification
                        </h2>
                    </div>
                    <p class="text-xs text-slate-500 font-medium mt-0.5">
                        Verify shop-reported digital and card collections deposited directly into company bank accounts.
                    </p>
                </div>

                <!-- Bank Account Selector Filter -->
                @if($connectedBankAccounts->isNotEmpty())
                    <form method="GET" action="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id]) }}" class="flex items-center gap-2 flex-wrap">
                        <input type="hidden" name="month" value="{{ $month }}">
                        <input type="hidden" name="banking_status" value="{{ $bankingStatusFilter }}">
                        <label for="bank_account_id" class="text-xs font-bold text-slate-600">Connected Bank:</label>
                        <select name="bank_account_id"
                                id="bank_account_id"
                                onchange="this.form.submit()"
                                class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 shadow-xs focus:border-indigo-500 focus:ring-indigo-500 cursor-pointer">
                            @foreach($connectedBankAccounts as $bank)
                                <option value="{{ $bank->id }}" {{ (int) $selectedBankId === (int) $bank->id ? 'selected' : '' }}>
                                    {{ $bank->name }} ({{ $bank->bank_name ?: 'Bank' }})
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif
            </div>

            @if($connectedBankAccounts->isEmpty())
                <div class="p-8 text-center bg-amber-50/50 rounded-2xl border border-amber-200 text-amber-900 space-y-2">
                    <i data-lucide="alert-circle" class="w-8 h-8 mx-auto text-amber-600"></i>
                    <p class="text-sm font-extrabold">No Connected Bank Accounts Found</p>
                    <p class="text-xs text-amber-800 max-w-md mx-auto">
                        No company bank accounts are configured for collection entries at {{ $currentShop->name }}. Configure account destinations in Shop Settings to enable payment verification.
                    </p>
                </div>
            @else
                <!-- 3 Metric Cards for Selected Bank -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="p-4 rounded-2xl bg-amber-50/60 border border-amber-200">
                        <div class="text-[10px] font-black uppercase tracking-wider text-amber-800">Awaiting Verification</div>
                        <div class="text-lg font-black font-mono text-amber-950 mt-1">
                            ₹<span x-text="formatCurrency(pendingAmount)">{{ number_format($bankingTotals['pending_amount'], 2) }}</span>
                        </div>
                        <div class="text-[10px] font-bold text-amber-700 mt-0.5">
                            <span x-text="pendingCount">{{ $bankingTotals['pending_count'] }}</span> <span x-text="pendingCount === 1 ? 'payment' : 'payments'">{{ Str::plural('payment', $bankingTotals['pending_count']) }}</span> pending receipt
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-emerald-50/60 border border-emerald-200">
                        <div class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Verified Received</div>
                        <div class="text-lg font-black font-mono text-emerald-950 mt-1">
                            ₹<span x-text="formatCurrency(verifiedAmount)">{{ number_format($bankingTotals['verified_amount'], 2) }}</span>
                        </div>
                        <div class="text-[10px] font-bold text-emerald-700 mt-0.5">
                            <span x-text="verifiedCount">{{ $bankingTotals['verified_count'] }}</span> <span x-text="verifiedCount === 1 ? 'payment' : 'payments'">{{ Str::plural('payment', $bankingTotals['verified_count']) }}</span> confirmed in bank
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                        <div class="text-[10px] font-black uppercase tracking-wider text-slate-600">Total Filtered Payments</div>
                        <div class="text-lg font-black font-mono text-slate-900 mt-1">
                            ₹<span x-text="formatCurrency(totalAmount)">{{ number_format($bankingTotals['total_amount'], 2) }}</span>
                        </div>
                        <div class="text-[10px] font-bold text-slate-500 mt-0.5">
                            <span x-text="totalCount">{{ $bankingTotals['total_count'] }}</span> <span x-text="totalCount === 1 ? 'payment' : 'payments'">{{ Str::plural('payment', $bankingTotals['total_count']) }}</span> in {{ $monthlyData['month_title'] }}
                        </div>
                    </div>
                </div>

                <!-- Status Tabs Filter -->
                <div class="flex items-center gap-2 border-b border-slate-100 pb-3 flex-wrap">
                    @php
                        $baseParams = [
                            'shop' => $currentShop->slug ?: $currentShop->shop_id,
                            'month' => $month,
                            'bank_account_id' => $selectedBankId,
                        ];
                    @endphp
                    <a href="{{ route('admin.cashbook.shop.show', array_merge($baseParams, ['banking_status' => 'pending'])) }}"
                       class="px-3 py-1.5 rounded-xl text-xs font-extrabold transition {{ $bankingStatusFilter === 'pending' ? 'bg-amber-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        Awaiting Verification (<span x-text="pendingCount">{{ $bankingTotals['pending_count'] }}</span>)
                    </a>
                    <a href="{{ route('admin.cashbook.shop.show', array_merge($baseParams, ['banking_status' => 'verified'])) }}"
                       class="px-3 py-1.5 rounded-xl text-xs font-extrabold transition {{ $bankingStatusFilter === 'verified' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        Verified (<span x-text="verifiedCount">{{ $bankingTotals['verified_count'] }}</span>)
                    </a>
                    <a href="{{ route('admin.cashbook.shop.show', array_merge($baseParams, ['banking_status' => 'all'])) }}"
                       class="px-3 py-1.5 rounded-xl text-xs font-extrabold transition {{ $bankingStatusFilter === 'all' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        All Payments (<span x-text="totalCount">{{ $bankingTotals['total_count'] }}</span>)
                    </a>
                </div>

                <!-- Empty State (shown initially or when all pending items on page are verified) -->
                <div x-show="hasNoVisibleRows()" class="p-12 text-center text-slate-400">
                    <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                    <p class="text-xs font-bold">No payments found matching this filter for {{ $selectedBankAccount?->name ?? 'selected bank' }}.</p>
                </div>

                <!-- Table -->
                @if($bankingPagination && $bankingPagination->isNotEmpty())
                    <div x-show="!hasNoVisibleRows()" class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                                    <th class="py-3 px-4 rounded-l-xl">Date</th>
                                    <th class="py-3 px-4">Payment Type</th>
                                    <th class="py-3 px-4">Reference</th>
                                    <th class="py-3 px-4 text-right">Amount</th>
                                    <th class="py-3 px-4">Bank Account</th>
                                    <th class="py-3 px-4 text-center">Verification Status</th>
                                    <th class="py-3 px-4 text-right rounded-r-xl">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-mono">
                                @foreach($bankingPagination as $tx)
                                    @php
                                        $stmt = $tx->statementEntries->first();
                                        $isReconciled = (bool) ($stmt && $stmt->is_finalized && $stmt->status === 'reconciled');
                                        $isApproved = in_array($tx->status, [\App\Enums\Cashbook\TransactionStatus::Approved->value, 'approved'], true);
                                        $dateFormatted = $tx->business_date ? \Illuminate\Support\Carbon::parse($tx->business_date)->format('d M Y') : '—';
                                        $paymentType = $tx->entryType?->name ?: $tx->entry_type_code;
                                        $accountName = $selectedBankAccount?->name ?? ($tx->companyAccount?->name ?: 'Connected Bank');
                                    @endphp
                                    <tr class="hover:bg-slate-50/80 transition-colors"
                                        x-show="isRowVisible({{ $tx->id }}, {{ $isReconciled ? 'true' : 'false' }})"
                                        id="banking-row-{{ $tx->id }}"
                                        data-tx-id="{{ $tx->id }}">
                                        <td class="py-3.5 px-4 font-sans font-extrabold text-slate-900">
                                            {{ $dateFormatted }}
                                        </td>
                                        <td class="py-3.5 px-4 font-sans font-bold text-slate-800">
                                            {{ $paymentType }}
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-500 font-mono text-[11px]">
                                            {{ $tx->reference_id ?: '#'.$tx->id }}
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-black text-slate-900 font-mono text-sm">
                                            ₹{{ number_format($tx->amount, 2) }}
                                        </td>
                                        <td class="py-3.5 px-4 font-sans font-bold text-indigo-900">
                                            <span class="inline-flex items-center gap-1">
                                                <i data-lucide="landmark" class="w-3.5 h-3.5 text-indigo-600"></i>
                                                <span>{{ $accountName }}</span>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-sans">
                                            <template x-if="isRowVerified({{ $tx->id }}, {{ $isReconciled ? 'true' : 'false' }})">
                                                <span class="inline-flex items-center gap-1 text-[10px] font-extrabold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-md">
                                                    <i data-lucide="check-check" class="w-3 h-3 text-emerald-600"></i>
                                                    <span>Verified</span>
                                                </span>
                                            </template>
                                            <template x-if="!isRowVerified({{ $tx->id }}, {{ $isReconciled ? 'true' : 'false' }})">
                                                @if($isApproved)
                                                    <span class="inline-flex items-center text-[10px] font-extrabold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-md">
                                                        Awaiting Verification
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center text-[10px] font-extrabold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">
                                                        {{ strtoupper($tx->status) }}
                                                    </span>
                                                @endif
                                            </template>
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-sans">
                                            <template x-if="isRowVerified({{ $tx->id }}, {{ $isReconciled ? 'true' : 'false' }})">
                                                <span class="text-xs text-slate-400 font-bold flex items-center justify-end gap-1">
                                                    <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-600"></i>
                                                    <span>Confirmed</span>
                                                </span>
                                            </template>
                                            <template x-if="!isRowVerified({{ $tx->id }}, {{ $isReconciled ? 'true' : 'false' }})">
                                                @if($isApproved)
                                                    <form method="POST"
                                                          action="{{ route('admin.cashbook.shop.day.verify-selected', [$currentShop->slug ?: $currentShop->shop_id]) }}"
                                                          @submit.prevent="verifyRow({{ $tx->id }}, '{{ $tx->business_date?->toDateString() }}', $el)">
                                                        @csrf
                                                        <input type="hidden" name="business_date" value="{{ $tx->business_date?->toDateString() }}">
                                                        <input type="hidden" name="transaction_ids[]" value="{{ $tx->id }}">
                                                        <input type="hidden" name="return_to" value="monthly">
                                                        <input type="hidden" name="month" value="{{ $month }}">
                                                        <input type="hidden" name="bank_account_id" value="{{ $selectedBankId }}">
                                                        <input type="hidden" name="banking_status" value="{{ $bankingStatusFilter }}">
                                                        <input type="hidden" name="banking_page" value="{{ $bankingPagination->currentPage() }}">

                                                        <button type="submit"
                                                                :disabled="isRowSubmitting({{ $tx->id }})"
                                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-sky-700 hover:bg-sky-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-extrabold shadow-xs transition-all cursor-pointer">
                                                            <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                                                            <span x-text="isRowSubmitting({{ $tx->id }}) ? 'Verifying...' : 'Verify received'">Verify received</span>
                                                        </button>
                                                    </form>
                                                @else
                                                    <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'date' => $tx->business_date?->toDateString()]) }}"
                                                       class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                                                        <span>Review in Day</span>
                                                    </a>
                                                @endif
                                            </template>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Links -->
                    @if($bankingPagination->hasPages())
                        <div x-show="!hasNoVisibleRows()" class="pt-4 border-t border-slate-100 flex items-center justify-between">
                            <div class="text-xs text-slate-500 font-bold">
                                Showing {{ $bankingPagination->firstItem() }} to {{ $bankingPagination->lastItem() }} of {{ $bankingPagination->total() }} payments
                            </div>
                            <div>
                                {{ $bankingPagination->links() }}
                            </div>
                        </div>
                    @endif
                @endif
            @endif
        </div>

    @else
        @include('admin.cashbook.settings.settlements.summary')
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- ── 1. PAGE HEADER & DATE NAVIGATION ────────────────────────── -->
        <!-- ══════════════════════════════════════════════════════════════════ -->

        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
            <div class="space-y-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'month' => $month]) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-extrabold transition-all">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                        <span>Month Summary</span>
                    </a>
                    <span class="text-slate-300">/</span>
                    <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold font-mono">
                        {{ $currentShop->code }}
                    </span>
                    <span class="inline-flex items-center text-xs font-extrabold px-2.5 py-1 rounded-lg border {{ $dayStatusBadgeClass }}">
                        {{ $dayStatusLabel }}
                    </span>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 uppercase flex items-center gap-2">
                    <span>{{ $currentShop->name }}</span>
                    <span class="text-base font-normal text-slate-400 font-mono">&mdash; {{ $formattedBusinessDate }}</span>
                    <span class="text-xs font-bold text-slate-400 font-sans">({{ $dayOfWeek }})</span>
                </h1>
            </div>

            <!-- Date Switcher, Calendar & Shop Controls -->
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Prev Day / Today / Next Day Navigation -->
                <div class="inline-flex items-center rounded-xl bg-slate-100 p-1 border border-slate-200 text-xs font-extrabold">
                    <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'date' => $prevDate]) }}"
                       class="p-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-white transition"
                       title="Previous Day ({{ \Illuminate\Support\Carbon::parse($prevDate)->format('d M') }})">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </a>

                    <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'date' => $todayDate]) }}"
                       class="px-2.5 py-1 rounded-lg transition {{ $isToday ? 'bg-emerald-600 text-white font-black' : 'text-slate-700 hover:bg-white' }}">
                        Today
                    </a>

                    <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'date' => $nextDate]) }}"
                       class="p-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-white transition"
                       title="Next Day ({{ \Illuminate\Support\Carbon::parse($nextDate)->format('d M') }})">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </a>
                </div>

                <!-- Calendar Modal Trigger Button -->
                <button type="button"
                        @click="showCalendarModal = true"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-extrabold border border-slate-200 transition cursor-pointer">
                    <i data-lucide="calendar" class="w-4 h-4 text-emerald-700"></i>
                    <span>Calendar</span>
                </button>

                <!-- Shop Dropdown Selector -->
                <div>
                    <select onchange="window.location.href='/admin/cashbook/shops/' + this.value + '?date={{ $businessDate }}'"
                            class="bg-slate-50 text-xs font-bold text-slate-800 px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-600 cursor-pointer">
                        @foreach($shops as $shopOption)
                            <option value="{{ $shopOption->slug ?: $shopOption->shop_id }}" {{ $currentShop->shop_id == $shopOption->shop_id ? 'selected' : '' }}>
                                {{ $shopOption->name }} ({{ $shopOption->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- ── 3. PRIMARY SHOP POSITION (MAJOR CONTENT SECTION) ───────────── -->
        <!-- ══════════════════════════════════════════════════════════════════ -->

        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <i data-lucide="scale" class="w-4 h-4"></i>
                    </span>
                    <h2 class="text-sm font-extrabold uppercase tracking-wide text-slate-900">
                        Shop Settlement Statement &mdash; Shop Position
                    </h2>
                </div>
                <!-- Canonical Named Dominant Result Badge -->
                <div class="px-3.5 py-1.5 rounded-xl text-xs font-black tracking-wide uppercase border {{ ($dailySettlement['net_direction'] ?? 'settled') === 'shop_owes_company' ? 'bg-slate-900 text-white border-slate-800' : (($dailySettlement['net_direction'] ?? 'settled') === 'company_owes_shop' ? 'bg-indigo-900 text-white border-indigo-800' : 'bg-emerald-100 text-emerald-900 border-emerald-300') }}">
                    {{ $dailySettlement['display_statement'] ?? ($currentShop->name . ' OWES GREEN LEAF ₹' . number_format($outstandingAmount, 2)) }}
                </div>
            </div>

            <!-- Primary 3 Dominant Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- 1. Net Company Receivable -->
                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 space-y-1">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                        Net Company Receivable &mdash; Net Shop Obligation
                    </span>
                    <div class="text-2xl font-black font-mono text-slate-900">
                        ₹{{ number_format($dailySettlement['shop_outstanding'] ?? $expectedPayableAmount, 2) }}
                    </div>
                    <div class="text-[11px] text-slate-500 font-medium pt-1">
                        Gross: <strong class="text-slate-800">₹{{ number_format($dailySettlement['shop_obligation_gross'] ?? $grossSalesAmount, 2) }}</strong> &minus; Used: <strong class="text-slate-800">₹{{ number_format($dailySettlement['shop_sales_deductions'] ?? $totalDeductionsAmount, 2) }}</strong>
                    </div>
                </div>

                <!-- 2. Received by Company -->
                <div class="p-5 rounded-2xl bg-emerald-50/80 border border-emerald-200 space-y-1">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-800">
                        Received by Company
                    </span>
                    <div class="text-2xl font-black font-mono text-emerald-900">
                        ₹{{ number_format($verifiedReceivedAmount, 2) }}
                    </div>
                    <div class="text-[11px] text-emerald-700 font-medium pt-1">
                        Verified &amp; deposited into company accounts
                    </div>
                </div>

                <!-- 3. Still To Settle (Visually Dominant) -->
                <div class="p-5 rounded-2xl bg-slate-900 text-white border border-slate-800 shadow-md space-y-1">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                        Still To Settle
                    </span>
                    <div class="text-3xl font-black font-mono text-white">
                        ₹{{ number_format($outstandingAmount, 2) }}
                    </div>
                    <div class="text-[11px] text-slate-400 font-medium pt-1">
                        Net receivable &minus; company received
                    </div>
                </div>
            </div>

            <!-- Supporting Secondary 3 Metrics Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-slate-100 text-xs">
                <div class="p-3 rounded-xl bg-slate-50/50 border border-slate-100 flex items-center justify-between">
                    <span class="font-extrabold text-slate-500">Still With Shop (Cash)</span>
                    <span class="font-mono font-black text-slate-800">₹{{ number_format($cashWithShopAmount, 2) }}</span>
                </div>
                <div class="p-3 rounded-xl bg-sky-50/50 border border-sky-100 flex items-center justify-between">
                    <span class="font-extrabold text-sky-800">Pending Verification</span>
                    <span class="font-mono font-black text-sky-900">₹{{ number_format($pendingVerificationAmount, 2) }}</span>
                </div>
                <div class="p-3 rounded-xl bg-amber-50/50 border border-amber-100 flex items-center justify-between">
                    <span class="font-extrabold text-amber-800">Pending Review</span>
                    <span class="font-mono font-black text-amber-900">₹{{ number_format($pendingAcceptanceAmount, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- ── 2-COLUMN MAIN WORKSPACE: COLLECTION FLOW & SETTLEMENT ──────── -->
        <!-- ══════════════════════════════════════════════════════════════════ -->

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- LEFT 7 COLS: COLLECTION BREAKDOWN & WORKFLOWS -->
            <div class="lg:col-span-7 space-y-6">

                <!-- ── 4. MONEY LOCATION / PAYMENT METHOD BREAKDOWN ─────────── -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="text-sm font-extrabold uppercase tracking-tight text-slate-900">
                                Money Location &amp; Payment Breakdown
                            </h3>
                            <p class="text-xs text-slate-500 font-medium">How sales were collected and allocated on this date</p>
                        </div>
                        <span class="text-xs font-bold text-slate-400 font-mono">
                            {{ $allCollections->count() }} {{ Str::plural('method', $allCollections->count()) }}
                        </span>
                    </div>

                    @if($allCollections->isEmpty())
                        <div class="py-8 text-center text-slate-400 text-xs font-bold">
                            No collections recorded for this business date.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-slate-100 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                                        <th class="py-2.5 px-3">Method</th>
                                        <th class="py-2.5 px-3 text-right">Collected</th>
                                        <th class="py-2.5 px-3 text-right">Used From Method</th>
                                        <th class="py-2.5 px-3 text-right">Net Receivable</th>
                                        <th class="py-2.5 px-3">Destination</th>
                                        <th class="py-2.5 px-3 text-center">State</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100/80 font-mono">
                                    @foreach($allCollections as $col)
                                        @php
                                            $methodCollected = (float) ($col['amount'] ?? 0);
                                            $methodUsed = $col['is_cash'] ? (float) $totalDeductionsAmount : 0.0;
                                            $methodNet = max(0.0, round($methodCollected - $methodUsed, 2));

                                            $stateBadge = match(true) {
                                                !empty($col['is_received']) => ['label' => 'Received', 'class' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                                                $col['status'] === 'CASH WITH SHOP' => ['label' => 'Cash With Shop', 'class' => 'bg-sky-50 text-sky-800 border-sky-200'],
                                                $col['tx_status'] === 'approved' => ['label' => 'Accepted — Awaiting Receipt', 'class' => 'bg-sky-50 text-sky-800 border-sky-200'],
                                                default => ['label' => 'Awaiting Review', 'class' => 'bg-amber-50 text-amber-800 border-amber-200'],
                                            };
                                        @endphp
                                        <tr class="hover:bg-slate-50/60 transition">
                                            <td class="py-3 px-3 font-sans">
                                                <span class="font-extrabold text-slate-900 text-xs block">{{ $col['payment_method'] ?? $col['category_name'] }}</span>
                                                <span class="text-[10px] text-slate-400 font-mono font-bold">{{ $col['code'] }}</span>
                                            </td>
                                            <td class="py-3 px-3 text-right font-black text-slate-900">
                                                ₹{{ number_format($methodCollected, 2) }}
                                            </td>
                                            <td class="py-3 px-3 text-right font-bold {{ $methodUsed > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                                                {{ $methodUsed > 0 ? '-₹'.number_format($methodUsed, 2) : '—' }}
                                            </td>
                                            <td class="py-3 px-3 text-right font-black text-slate-900">
                                                ₹{{ number_format($methodNet, 2) }}
                                            </td>
                                            <td class="py-3 px-3 font-sans text-slate-600 text-[11px] font-medium">
                                                @if(!empty($col['has_account_mapping']))
                                                    {{ $col['destination_account_name'] }}
                                                @else
                                                    <span class="text-amber-700 font-bold">Destination account not configured</span>
                                                @endif
                                            </td>
                                            <td class="py-3 px-3 text-center font-sans">
                                                <span class="inline-flex items-center text-[9px] font-black uppercase px-2 py-0.5 rounded-md border {{ $stateBadge['class'] }}">
                                                    {{ $stateBadge['label'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <!-- ── 5. REVIEW COLLECTIONS (GUIDED APPROVAL WORKFLOW) ─────── -->
                @if($needsAcceptance->isNotEmpty())
                    @php
                        $acceptanceEntries = $needsAcceptance->map(fn($c) => [
                            'id' => (int) $c['id'],
                            'amount_paise' => (int) round(((float) $c['amount']) * 100),
                            'amount' => (float) $c['amount'],
                        ])->values();
                    @endphp
                    <div class="p-5 rounded-3xl border border-amber-200 bg-amber-50/30 space-y-4"
                         x-data="{
                            entries: @js($acceptanceEntries),
                            selectedIds: [],
                            showConfirmModal: false,
                            isSubmitting: false,
                            init() {
                                this.$watch('selectedIds', () => this.syncMasterCheckbox());
                                this.syncMasterCheckbox();
                            },
                            get eligibleCount() {
                                return this.entries.length;
                            },
                            get selectedCount() {
                                return this.selectedIds.length;
                            },
                            get isAllSelected() {
                                return this.eligibleCount > 0 && this.selectedCount === this.eligibleCount;
                            },
                            get isSomeSelected() {
                                return this.selectedCount > 0 && this.selectedCount < this.eligibleCount;
                            },
                            get selectedTotalPaise() {
                                const selectedSet = new Set(this.selectedIds.map(Number));
                                return this.entries.reduce((sum, e) => selectedSet.has(e.id) ? sum + e.amount_paise : sum, 0);
                            },
                            get selectedTotalFormatted() {
                                const rupees = this.selectedTotalPaise / 100;
                                return '₹' + rupees.toLocaleString('en-IN', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                });
                            },
                            toggleAll() {
                                if (this.isAllSelected) {
                                    this.selectedIds = [];
                                } else {
                                    this.selectedIds = this.entries.map(e => e.id);
                                }
                                this.syncMasterCheckbox();
                            },
                            syncMasterCheckbox() {
                                this.$nextTick(() => {
                                    if (this.$refs.masterAcceptanceCheckbox) {
                                        this.$refs.masterAcceptanceCheckbox.checked = this.isAllSelected;
                                        this.$refs.masterAcceptanceCheckbox.indeterminate = this.isSomeSelected;
                                    }
                                });
                            },
                            submitBatch() {
                                if (this.isSubmitting || this.selectedCount === 0) return;
                                this.isSubmitting = true;
                                this.$refs.acceptForm.submit();
                            }
                         }">

                        <form method="POST" action="{{ route('admin.cashbook.shop.day.accept-selected', $currentShop->slug ?: $currentShop->shop_id) }}" x-ref="acceptForm">
                            @csrf
                            <input type="hidden" name="business_date" value="{{ $businessDate }}">

                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-amber-200/70 pb-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                            <input type="checkbox"
                                                   x-ref="masterAcceptanceCheckbox"
                                                   @click="toggleAll"
                                                   class="w-4 h-4 rounded border-amber-300 text-amber-600 focus:ring-amber-500 cursor-pointer">
                                            <span class="text-xs font-black uppercase tracking-wider text-amber-950">
                                                Review Collections ({{ $needsAcceptance->count() }})
                                            </span>
                                        </label>
                                    </div>
                                    <p class="text-[11px] text-amber-800 font-medium mt-0.5">
                                        Reviewing confirms the shop-reported collection and prepares it for company receipt tracking.
                                    </p>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="text-xs font-mono font-bold text-amber-900" x-show="selectedCount > 0">
                                        Selected: <span x-text="selectedCount"></span> (<span x-text="selectedTotalFormatted"></span>)
                                    </div>
                                    <button type="button"
                                            @click="showConfirmModal = true"
                                            :disabled="selectedCount === 0 || isSubmitting"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-extrabold shadow-xs transition-all cursor-pointer">
                                        <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                                        <span>Approve for receipt tracking</span>
                                    </button>
                                </div>
                            </div>

                            <div class="divide-y divide-amber-100/80 mt-3 space-y-2">
                                @foreach($needsAcceptance as $col)
                                    <div class="p-3.5 rounded-2xl bg-white border border-amber-100 flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <input type="checkbox"
                                                   name="transaction_ids[]"
                                                   :value="{{ (int) $col['id'] }}"
                                                   x-model.number="selectedIds"
                                                   class="acceptance-checkbox w-4 h-4 rounded border-amber-300 text-amber-600 focus:ring-amber-500 cursor-pointer">
                                            <div>
                                                <div class="font-extrabold text-sm text-slate-900">
                                                    {{ $col['payment_method'] ?? $col['category_name'] }}
                                                </div>
                                                <div class="text-xs text-slate-500">
                                                    @if(!empty($col['has_account_mapping']))
                                                        Destination: <span class="font-bold text-slate-700">{{ $col['destination_account_name'] }}</span>
                                                    @else
                                                        <span class="text-amber-700 font-bold">Destination account not configured</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-3">
                                            <div class="text-right">
                                                <div class="text-sm font-black font-mono text-slate-900">
                                                    ₹{{ number_format($col['amount'], 2) }}
                                                </div>
                                                <span class="inline-flex items-center text-[9px] font-extrabold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-md">
                                                    Awaiting Review
                                                </span>
                                            </div>
                                            <a href="{{ route('admin.cashbook.transaction.show', $col['id']) }}"
                                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-900 text-slate-700 hover:text-white text-xs font-bold transition">
                                                <span>View</span>
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </form>

                        <!-- Approval Confirmation Modal -->
                        <div x-show="showConfirmModal"
                             style="display: none;"
                             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs">
                            <div class="bg-white rounded-3xl p-6 max-w-md w-full border border-slate-200 shadow-2xl space-y-4"
                                 @click.away="if(!isSubmitting) showConfirmModal = false">
                                <div class="flex items-center gap-3">
                                    <div class="p-3 rounded-2xl bg-amber-50 text-amber-700 border border-amber-200">
                                        <i data-lucide="check-circle" class="w-6 h-6"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-black text-slate-900">Approve for Receipt Tracking</h3>
                                        <p class="text-xs text-slate-500 font-medium">Confirm shop-reported collection records</p>
                                    </div>
                                </div>

                                <div class="p-4 rounded-2xl bg-amber-50/50 border border-amber-100 text-xs text-slate-700 space-y-2">
                                    <p class="font-extrabold text-amber-950">
                                        Approve <span x-text="selectedCount"></span> <span x-text="selectedCount === 1 ? 'collection' : 'collections'"></span> totalling <span x-text="selectedTotalFormatted"></span>?
                                    </p>
                                    <ul class="text-[11px] text-slate-600 list-disc list-inside space-y-1 pt-1">
                                        <li>Confirms the reported collection is recorded correctly in the shop cashbook.</li>
                                        <li>Creates and prepares the pending company account statement entry.</li>
                                        <li class="font-bold text-amber-900">Does NOT mark money as received. Receipt must be confirmed separately.</li>
                                    </ul>
                                </div>

                                <div class="flex items-center justify-end gap-2 pt-2">
                                    <button type="button"
                                            @click="showConfirmModal = false"
                                            :disabled="isSubmitting"
                                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                                        Cancel
                                    </button>
                                    <button type="button"
                                            @click="submitBatch"
                                            :disabled="isSubmitting || selectedCount === 0"
                                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-extrabold shadow-sm transition cursor-pointer disabled:opacity-50">
                                        <span x-text="isSubmitting ? 'Approving...' : 'Approve for receipt tracking'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- ── 6. CONFIRM COMPANY RECEIPT (VERIFICATION WORKFLOW) ──── -->
                @if($needsVerification->isNotEmpty())
                    @php
                        $verificationEntries = $needsVerification->filter(fn($c) => !empty($c['can_verify']))->map(fn($c) => [
                            'id' => (int) $c['id'],
                            'amount_paise' => (int) round(((float) $c['amount']) * 100),
                            'amount' => (float) $c['amount'],
                        ])->values();
                    @endphp
                    <div class="p-5 rounded-3xl border border-sky-200 bg-sky-50/30 space-y-4"
                         x-data="{
                            entries: @js($verificationEntries),
                            selectedIds: [],
                            showConfirmModal: false,
                            isSubmitting: false,
                            init() {
                                this.$watch('selectedIds', () => this.syncMasterCheckbox());
                                this.syncMasterCheckbox();
                            },
                            get eligibleCount() {
                                return this.entries.length;
                            },
                            get selectedCount() {
                                return this.selectedIds.length;
                            },
                            get isAllSelected() {
                                return this.eligibleCount > 0 && this.selectedCount === this.eligibleCount;
                            },
                            get isSomeSelected() {
                                return this.selectedCount > 0 && this.selectedCount < this.eligibleCount;
                            },
                            get selectedTotalPaise() {
                                const selectedSet = new Set(this.selectedIds.map(Number));
                                return this.entries.reduce((sum, e) => selectedSet.has(e.id) ? sum + e.amount_paise : sum, 0);
                            },
                            get selectedTotalFormatted() {
                                const rupees = this.selectedTotalPaise / 100;
                                return '₹' + rupees.toLocaleString('en-IN', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                });
                            },
                            toggleAll() {
                                if (this.isAllSelected) {
                                    this.selectedIds = [];
                                } else {
                                    this.selectedIds = this.entries.map(e => e.id);
                                }
                                this.syncMasterCheckbox();
                            },
                            syncMasterCheckbox() {
                                this.$nextTick(() => {
                                    if (this.$refs.masterVerificationCheckbox) {
                                        this.$refs.masterVerificationCheckbox.checked = this.isAllSelected;
                                        this.$refs.masterVerificationCheckbox.indeterminate = this.isSomeSelected;
                                    }
                                });
                            },
                            submitBatch() {
                                if (this.isSubmitting || this.selectedCount === 0) return;
                                this.isSubmitting = true;
                                this.$refs.verifyForm.submit();
                            }
                         }">

                        <form method="POST" action="{{ route('admin.cashbook.shop.day.verify-selected', $currentShop->slug ?: $currentShop->shop_id) }}" x-ref="verifyForm">
                            @csrf
                            <input type="hidden" name="business_date" value="{{ $businessDate }}">

                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-sky-200/70 pb-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                            <input type="checkbox"
                                                   x-ref="masterVerificationCheckbox"
                                                   @click="toggleAll"
                                                   class="w-4 h-4 rounded border-sky-300 text-sky-600 focus:ring-sky-500 cursor-pointer">
                                            <span class="text-xs font-black uppercase tracking-wider text-sky-950">
                                                Confirm Company Receipt ({{ $needsVerification->count() }})
                                            </span>
                                        </label>
                                    </div>
                                    <p class="text-[11px] text-sky-800 font-medium mt-0.5">
                                        Use this only after the money is visible in the mapped company bank or cash account.
                                    </p>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="text-xs font-mono font-bold text-sky-900" x-show="selectedCount > 0">
                                        Selected: <span x-text="selectedCount"></span> (<span x-text="selectedTotalFormatted"></span>)
                                    </div>
                                    <button type="button"
                                            @click="showConfirmModal = true"
                                            :disabled="selectedCount === 0 || isSubmitting"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-sky-700 hover:bg-sky-800 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-extrabold shadow-xs transition-all cursor-pointer">
                                        <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                                        <span>Confirm received</span>
                                    </button>
                                </div>
                            </div>

                            <div class="divide-y divide-sky-100/80 mt-3 space-y-2">
                                @foreach($needsVerification as $col)
                                    @php
                                        $canVerifyRow = !empty($col['can_verify']);
                                    @endphp
                                    <div class="p-3.5 rounded-2xl bg-white border border-sky-100 flex flex-col gap-2 {{ !$canVerifyRow ? 'opacity-90 bg-slate-50/50' : '' }}">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-3">
                                                @if($canVerifyRow)
                                                    <input type="checkbox"
                                                           name="transaction_ids[]"
                                                           :value="{{ (int) $col['id'] }}"
                                                           x-model.number="selectedIds"
                                                           class="verification-checkbox w-4 h-4 rounded border-sky-300 text-sky-600 focus:ring-sky-500 cursor-pointer">
                                                @else
                                                    <input type="checkbox"
                                                           disabled
                                                           class="w-4 h-4 rounded border-slate-200 bg-slate-100 text-slate-400 cursor-not-allowed"
                                                           title="{{ $col['verification_block_reason'] ?? 'Cannot verify' }}">
                                                @endif
                                                <div>
                                                    <div class="font-extrabold text-sm text-slate-900 flex items-center gap-2 flex-wrap">
                                                        <span>{{ $col['payment_method'] ?? $col['category_name'] }}</span>
                                                        @if(!empty($col['is_cash']) && empty($col['has_account_mapping']))
                                                            <span class="inline-flex items-center text-[9px] font-extrabold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">
                                                                Currently with shop
                                                            </span>
                                                        @endif
                                                        @if(empty($col['has_account_mapping']))
                                                            <span class="inline-flex items-center text-[9px] font-extrabold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-md">
                                                                Setup required
                                                            </span>
                                                        @elseif(!empty($col['has_account_mismatch']))
                                                            <span class="inline-flex items-center text-[9px] font-extrabold text-rose-800 bg-rose-100 px-2 py-0.5 rounded-md">
                                                                Account mismatch — review required
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="text-xs text-slate-500 flex items-center gap-1">
                                                        @if(!empty($col['has_account_mapping']))
                                                            Destination: <span class="font-bold text-emerald-800">{{ $col['destination_account_name'] }}</span>
                                                        @else
                                                            <span class="text-amber-700 font-bold">Destination account not configured</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-3">
                                                <div class="text-right">
                                                    <div class="text-sm font-black font-mono text-slate-900">
                                                        ₹{{ number_format($col['amount'], 2) }}
                                                    </div>
                                                    @if($col['status'] === 'CASH WITH SHOP')
                                                        <span class="inline-flex items-center text-[9px] font-extrabold text-sky-800 bg-sky-100 px-2 py-0.5 rounded-md">
                                                            Cash With Shop
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center text-[9px] font-extrabold text-sky-800 bg-sky-100 px-2 py-0.5 rounded-md">
                                                            Needs Verification
                                                        </span>
                                                    @endif
                                                </div>
                                                <a href="{{ route('admin.cashbook.transaction.show', $col['id']) }}"
                                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-900 text-slate-700 hover:text-white text-xs font-bold transition">
                                                <span>View</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </form>

                    <!-- Verification Confirmation Modal -->
                    <div x-show="showConfirmModal"
                         style="display: none;"
                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs">
                        <div class="bg-white rounded-3xl p-6 max-w-md w-full border border-slate-200 shadow-2xl space-y-4"
                             @click.away="if(!isSubmitting) showConfirmModal = false">
                            <div class="flex items-center gap-3">
                                <div class="p-3 rounded-2xl bg-sky-50 text-sky-700 border border-sky-200">
                                    <i data-lucide="shield-check" class="w-6 h-6"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-black text-slate-900">Confirm Company Receipt</h3>
                                    <p class="text-xs text-slate-500 font-medium">Verify funds deposited into company accounts</p>
                                </div>
                            </div>

                            <div class="p-4 rounded-2xl bg-sky-50/50 border border-sky-100 text-xs text-slate-700 space-y-2">
                                <p class="font-extrabold text-sky-950">
                                    Confirm company received <span x-text="selectedCount"></span> <span x-text="selectedCount === 1 ? 'payment' : 'payments'"></span> totalling <span x-text="selectedTotalFormatted"></span>?
                                </p>
                                <p class="text-slate-500 text-[11px] leading-relaxed">
                                    This will update company bank and cash account balances and reduce Shop Outstanding.
                                </p>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-2">
                                <button type="button"
                                        @click="showConfirmModal = false"
                                        :disabled="isSubmitting"
                                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                                    Cancel
                                </button>
                                <button type="button"
                                        @click="submitBatch"
                                        :disabled="isSubmitting || selectedCount === 0"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-sky-700 hover:bg-sky-800 text-white text-xs font-extrabold shadow-sm transition cursor-pointer disabled:opacity-50">
                                    <span x-text="isSubmitting ? 'Confirming...' : 'Confirm received'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

                <!-- ── RECEIVED COLLECTIONS (READ-ONLY) ────────────────────── -->
                @if($receivedCollections->isNotEmpty())
                    <div class="p-5 rounded-3xl border border-emerald-200 bg-emerald-50/20 space-y-3">
                        <div class="flex items-center justify-between border-b border-emerald-200/60 pb-2">
                            <span class="text-xs font-black uppercase tracking-wider text-emerald-950 flex items-center gap-1.5">
                                <i data-lucide="check-check" class="w-4 h-4 text-emerald-600"></i>
                                <span>Received Collections ({{ $receivedCollections->count() }})</span>
                            </span>
                            <span class="text-xs font-bold text-emerald-800 font-mono">
                                ₹{{ number_format($receivedCollections->sum('amount'), 2) }}
                            </span>
                        </div>

                        <div class="divide-y divide-emerald-100/80 space-y-2">
                            @foreach($receivedCollections as $col)
                                <div class="p-3.5 rounded-2xl bg-white border border-emerald-100 flex items-center justify-between gap-3">
                                    <div>
                                        <div class="font-extrabold text-sm text-slate-900">
                                            {{ $col['payment_method'] ?? $col['category_name'] }}
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            Destination: <span class="font-bold text-emerald-800">{{ $col['destination_account'] ?? 'Company Account' }}</span>
                                            @if(!empty($col['verified_by']))
                                                <span class="text-slate-400 font-medium"> &middot; Verified by {{ $col['verified_by'] }} {{ $col['verified_at'] ? '('.$col['verified_at'].')' : '' }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <div class="text-right">
                                            <div class="text-sm font-black font-mono text-slate-900">
                                                ₹{{ number_format($col['amount'], 2) }}
                                            </div>
                                            <span class="inline-flex items-center gap-1 text-[9px] font-extrabold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                                                <i data-lucide="check" class="w-3 h-3"></i> RECEIVED
                                            </span>
                                        </div>
                                        <a href="{{ route('admin.cashbook.transaction.show', $col['id']) }}"
                                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-900 text-slate-700 hover:text-white text-xs font-bold transition">
                                            <span>View</span>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- ── 7. COMPACT ADJUSTMENTS SUMMARY ROW & TRIGGER ─────────── -->
                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="p-2 rounded-2xl bg-slate-100 text-slate-700 border border-slate-200">
                            <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
                        </span>
                        <div>
                            <h4 class="text-sm font-extrabold text-slate-900">Adjustments</h4>
                            <p class="text-xs text-slate-500 font-medium">
                                {{ count($dailySettlement['settlement_adjustments'] ?? []) }} entries &middot;
                                <strong class="font-mono text-slate-800">₹{{ number_format($totalDeductionsAmount, 2) }}</strong> used from collection
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="showAdjustmentsDrawer = true"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-extrabold transition cursor-pointer">
                            <i data-lucide="list" class="w-3.5 h-3.5"></i>
                            <span>View details</span>
                        </button>
                        <button type="button"
                                @click="showAddAdjustmentModal = true"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-extrabold transition cursor-pointer shadow-xs">
                            <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                            <span>Add adjustment</span>
                        </button>
                    </div>
                </div>

            </div>

            <!-- RIGHT 5 COLS: SETTLEMENT SUMMARY & BREAKDOWN ───────────────── -->
            <div class="lg:col-span-5 space-y-6">

                <!-- ── 8. SETTLEMENT SUMMARY (DOMINANT VISUAL RESULT) ───────── -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-5">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-sm font-extrabold uppercase tracking-tight text-slate-900">
                            Settlement Summary
                        </h2>
                    </div>

                    <div class="space-y-3.5 text-xs font-bold">
                        <div class="flex items-center justify-between text-slate-600">
                            <span>Gross Collection</span>
                            <span class="font-mono text-sm font-black text-slate-900">
                                ₹{{ number_format($grossSalesAmount, 2) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-rose-600">
                            <span>Less: Used From Collection</span>
                            <span class="font-mono text-sm font-black">
                                -₹{{ number_format($totalDeductionsAmount, 2) }}
                            </span>
                        </div>

                        <div class="border-t border-slate-200 pt-3 flex items-center justify-between text-slate-900 font-extrabold">
                            <span>Net Company Receivable</span>
                            <span class="font-mono text-base font-black">
                                ₹{{ number_format($expectedPayableAmount, 2) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-emerald-700">
                            <span>Less: Company Received</span>
                            <span class="font-mono text-sm font-black">
                                -₹{{ number_format($verifiedReceivedAmount, 2) }}
                            </span>
                        </div>

                        <div class="border-t-2 border-slate-900 pt-4 flex items-center justify-between text-slate-900 font-black">
                            <span class="text-sm uppercase tracking-wide">Still To Settle</span>
                            <span class="font-mono text-2xl font-black text-slate-950">
                                ₹{{ number_format($outstandingAmount, 2) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ── 9. BREAKDOWN OF STILL TO SETTLE (SUPPORTING PANEL) ───── -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4"
                     x-data="{ showBreakdown: true }">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-xs font-extrabold uppercase tracking-tight text-slate-700">
                            Breakdown of Still to Settle
                        </h3>
                        <button type="button" @click="showBreakdown = !showBreakdown" class="text-slate-400 hover:text-slate-600 text-xs font-bold">
                            <span x-text="showBreakdown ? 'Hide' : 'Show'"></span>
                        </button>
                    </div>

                    <div x-show="showBreakdown" class="space-y-2.5 text-xs font-bold">
                        <div class="flex items-center justify-between text-sky-900 bg-sky-50/70 p-3 rounded-2xl border border-sky-100">
                            <span>Cash With Shop</span>
                            <span class="font-mono text-sm font-black">
                                ₹{{ number_format($cashWithShopAmount, 2) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-amber-900 bg-amber-50/70 p-3 rounded-2xl border border-amber-100">
                            <span>Pending Verification (Bank/Other)</span>
                            <span class="font-mono text-sm font-black">
                                ₹{{ number_format($pendingVerificationAmount, 2) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-purple-900 bg-purple-50/70 p-3 rounded-2xl border border-purple-100">
                            <span>Floating Cheques</span>
                            <span class="font-mono text-sm font-black">
                                ₹{{ number_format((float) ($dailySettlement['company_receipt_status']['floating_cheques'] ?? 0), 2) }}
                            </span>
                        </div>

                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-slate-500 font-bold text-[11px]">
                            <span>Reconciled Total</span>
                            <span class="font-mono font-extrabold text-slate-800">
                                ₹{{ number_format($cashWithShopAmount + $pendingVerificationAmount + (float) ($dailySettlement['company_receipt_status']['floating_cheques'] ?? 0), 2) }}
                            </span>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- ── CALENDAR NAVIGATION MODAL / DRAWER ────────────────────────── -->
        <!-- ══════════════════════════════════════════════════════════════════ -->

        <div x-show="showCalendarModal"
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs">
            <div class="bg-white rounded-3xl p-6 max-w-md w-full border border-slate-200 shadow-2xl space-y-4"
                 @click.away="showCalendarModal = false">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'month' => $prevMonth, 'date' => $businessDate]) }}"
                           class="p-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition"
                           title="Previous Month">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                        </a>
                        <h3 class="text-base font-black text-slate-900">{{ $monthTitle }}</h3>
                        <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'month' => $nextMonth, 'date' => $businessDate]) }}"
                           class="p-1.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition"
                           title="Next Month">
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </a>
                    </div>
                    <button type="button" @click="showCalendarModal = false" class="text-slate-400 hover:text-slate-600 font-black text-lg cursor-pointer">&times;</button>
                </div>

                <!-- 7-Col Calendar Grid -->
                <div class="space-y-2">
                    <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                        <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                    </div>

                    <div class="grid grid-cols-7 gap-1 text-xs">
                        @for($i = 0; $i < $firstDayOfWeek; $i++)
                            <div class="h-9"></div>
                        @endfor

                        @for($day = 1; $day <= $daysInCurrentMonth; $day++)
                            @php
                                $dateStr = sprintf('%s-%02d', $month, $day);
                                $isCalSelected = $dateStr === $businessDate;
                                $isCalToday = $dateStr === $todayDate;
                                $hasActivity = in_array($dateStr, $activeDatesInMonth ?? [], true);
                                $dayUrl = route('admin.cashbook.shop.show', [
                                    'shop' => $currentShop->slug ?: $currentShop->shop_id,
                                    'date' => $dateStr,
                                ]);
                            @endphp
                            <a href="{{ $dayUrl }}"
                               class="h-9 rounded-xl flex flex-col items-center justify-center transition cursor-pointer relative
                                      {{ $isCalSelected ? 'bg-slate-900 text-white font-black shadow-xs' : ($isCalToday ? 'border-2 border-emerald-600 font-extrabold text-emerald-950 hover:bg-emerald-50' : 'text-slate-700 hover:bg-slate-100 font-bold') }}">
                                <span>{{ $day }}</span>
                                @if($hasActivity)
                                    <span class="w-1 h-1 rounded-full {{ $isCalSelected ? 'bg-emerald-400' : 'bg-emerald-600' }} absolute bottom-1"></span>
                                @endif
                            </a>
                        @endfor
                    </div>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-100 text-xs text-slate-500 font-medium">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-600"></span> Activity</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full border border-emerald-600"></span> Today</span>
                    </div>
                    <button type="button" @click="showCalendarModal = false" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl font-bold">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- ── ADJUSTMENTS FULL AUDIT DRAWER / MODAL ─────────────────────── -->
        <!-- ══════════════════════════════════════════════════════════════════ -->

        <div x-show="showAdjustmentsDrawer"
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs">
            <div class="bg-white rounded-3xl p-6 max-w-2xl w-full border border-slate-200 shadow-2xl space-y-4 max-h-[90vh] flex flex-col"
                 @click.away="showAdjustmentsDrawer = false">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-black text-slate-900">Settlement Adjustments Details</h3>
                        <p class="text-xs text-slate-500 font-medium">Audit records for {{ $formattedBusinessDate }}</p>
                    </div>
                    <button type="button" @click="showAdjustmentsDrawer = false" class="text-slate-400 hover:text-slate-600 font-black text-lg cursor-pointer">&times;</button>
                </div>

                <div class="overflow-y-auto flex-1 pr-1">
                    @if(empty($dailySettlement['settlement_adjustments']))
                        <div class="py-12 text-center text-slate-400 text-xs font-bold">
                            No adjustments recorded for this business date.
                        </div>
                    @else
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                                    <th class="py-2.5 px-3">Time</th>
                                    <th class="py-2.5 px-3">Type</th>
                                    <th class="py-2.5 px-3">Note</th>
                                    <th class="py-2.5 px-3 text-right">Amount</th>
                                    <th class="py-2.5 px-3 text-right">Outstanding Effect</th>
                                    <th class="py-2.5 px-3">Admin</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                    <th class="py-2.5 px-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100/80">
                                @foreach($dailySettlement['settlement_adjustments'] as $adj)
                                    <tr class="hover:bg-slate-50/60 transition {{ !empty($adj['is_reversed']) ? 'opacity-60 bg-slate-50/30' : '' }}">
                                        <td class="py-3 px-3 font-mono text-slate-500 font-semibold">{{ $adj['time'] }}</td>
                                        <td class="py-3 px-3">
                                            <span class="font-extrabold text-slate-900 block">{{ $adj['type'] }}</span>
                                            <span class="text-[10px] text-slate-400 font-bold">{{ $adj['name'] }}</span>
                                        </td>
                                        <td class="py-3 px-3 max-w-[160px] truncate text-slate-600 font-medium" title="{{ $adj['note'] }}">
                                            {{ $adj['note'] }}
                                            @if(!empty($adj['is_reversal']))
                                                <a href="{{ route('admin.cashbook.transaction.show', $adj['original_id']) }}" class="text-sky-600 hover:underline font-bold block text-[10px]">
                                                    Original #{{ $adj['original_id'] }}
                                                </a>
                                            @elseif(!empty($adj['is_reversed']) && !empty($adj['reversal_id']))
                                                <a href="{{ route('admin.cashbook.transaction.show', $adj['reversal_id']) }}" class="text-rose-600 hover:underline font-bold block text-[10px]">
                                                    Reversal #{{ $adj['reversal_id'] }}
                                                </a>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right font-black font-mono text-slate-900">
                                            ₹{{ number_format($adj['amount'], 2) }}
                                        </td>
                                        <td class="py-3 px-3 text-right font-black font-mono {{ ($adj['effect_on_payable'] ?? 0) < 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                                            {{ ($adj['effect_on_payable'] ?? 0) < 0 ? '-' : '+' }}₹{{ number_format(abs($adj['effect_on_payable'] ?? 0), 2) }}
                                        </td>
                                        <td class="py-3 px-3 text-slate-600 font-medium">{{ $adj['admin'] }}</td>
                                        <td class="py-3 px-3 text-center">
                                            @if(!empty($adj['is_reversed']))
                                                <span class="inline-flex items-center text-[9px] font-black uppercase px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 border border-rose-200">
                                                    REVERSED
                                                </span>
                                            @elseif(!empty($adj['is_reversal']))
                                                <span class="inline-flex items-center text-[9px] font-black uppercase px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 border border-purple-200">
                                                    REVERSAL
                                                </span>
                                            @else
                                                <span class="inline-flex items-center text-[9px] font-black uppercase px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200">
                                                    {{ $adj['status_label'] ?? $adj['status'] }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right">
                                            @if(!empty($adj['can_reverse']))
                                                <div class="inline-flex items-center justify-end gap-1.5">
                                                    @if(($adj['status'] ?? null) === 'approved')
                                                        <form method="POST" action="{{ route('admin.cashbook.transaction.revert-approval', $adj['id']) }}" class="inline-flex">
                                                            @csrf
                                                            <button type="submit"
                                                                    @click="showAdjustmentsDrawer = false"
                                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 text-[11px] font-extrabold transition cursor-pointer border border-amber-200">
                                                                <i data-lucide="undo-2" class="w-3 h-3"></i>
                                                                <span>Revert</span>
                                                            </button>
                                                        </form>
                                                    @else
                                                        <button type="button"
                                                                disabled
                                                                title="Only approved entries can be reverted"
                                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-400 text-[11px] font-extrabold border border-slate-200 cursor-not-allowed">
                                                            <i data-lucide="undo-2" class="w-3 h-3"></i>
                                                            <span>Revert</span>
                                                        </button>
                                                    @endif

                                                    <button type="button"
                                                            @click="showAdjustmentsDrawer = false; openReverse({{ $adj['id'] }}, '{{ addslashes($adj['name']) }}', '{{ number_format($adj['amount'], 2) }}')"
                                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 text-[11px] font-extrabold transition cursor-pointer border border-rose-200">
                                                        <i data-lucide="rotate-ccw" class="w-3 h-3"></i>
                                                        <span>Reverse</span>
                                                    </button>
                                                </div>
                                            @else
                                                <a href="{{ route('admin.cashbook.transaction.show', $adj['id']) }}"
                                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-900 text-slate-700 hover:text-white text-[11px] font-bold transition">
                                                    <span>View</span>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="flex items-center justify-end pt-3 border-t border-slate-100">
                    <button type="button" @click="showAdjustmentsDrawer = false" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- ── ADD ADJUSTMENT MODAL ──────────────────────────────────────── -->
        <!-- ══════════════════════════════════════════════════════════════════ -->

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

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- ── REVERSE ADJUSTMENT MODAL ──────────────────────────────────── -->
        <!-- ══════════════════════════════════════════════════════════════════ -->

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

    @endif

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- ── MONTHLY FINANCIAL OVERVIEW (BACKWARD COMPATIBILITY) ───────── -->
    <!-- ══════════════════════════════════════════════════════════════════ -->

    <details class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4" {{ request()->has('month') ? 'open' : '' }}>
        <summary class="cursor-pointer text-xs font-black uppercase tracking-wider text-slate-500 hover:text-slate-900 flex items-center justify-between">
            <span>Shop Financial Overview &amp; Monthly Summary ({{ \Illuminate\Support\Carbon::parse($monthStart)->format('F Y') }})</span>
            <span class="text-[10px] font-bold px-2 py-1 rounded-lg bg-slate-100 text-slate-600">Toggle Monthly Overview</span>
        </summary>

        <div class="pt-4 border-t border-slate-100 space-y-6">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.cashbook.shop.accept-payment', ['shop' => $currentShop->uuid, 'month' => $month]) }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-emerald-700 px-4 text-xs font-black text-white hover:bg-emerald-800">
                    <i data-lucide="wallet" class="h-4 w-4"></i> Receive Payment
                </a>
                <a href="{{ route('admin.cashbook.reports.mobile-ledger', ['shop' => $currentShop->uuid, 'timeframe' => 'monthly', 'date' => $monthEnd]) }}" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-xs font-black text-slate-700 hover:bg-slate-50">
                    <i data-lucide="book-open" class="h-4 w-4"></i> View Ledger
                </a>
            </div>

            @php
                $shopPosition = (float) ($position->closing_shop_position ?? 0);
                $companyPending = (float) ($position->closing_company_pending ?? 0);
                $pettyBalance = (float) ($position->closing_petty ?? 0);
                $netBalance = (float) ($position->total_sales ?? 0) - (float) ($position->total_expense ?? 0);
                $awaitingBank = $floatingPayments;
                $awaitingSettlement = max(0, $cashBankReceived - $ledgerSettled);
            @endphp

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <article class="rounded-2xl border border-slate-300 bg-slate-950 p-4 text-white">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Pending Payable to Company</p>
                    <p class="mt-2 text-right font-mono text-2xl font-black">₹{{ number_format($pendingPayable, 2) }}</p>
                </article>
                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-amber-700">Payment Received</p>
                    <p class="mt-2 text-right font-mono text-2xl font-black text-amber-950">₹{{ number_format($cashBankReceived, 2) }}</p>
                </article>
                <article class="rounded-2xl border border-violet-200 bg-violet-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-violet-700">Company → Shop Pending</p>
                    <p class="mt-2 text-right font-mono text-2xl font-black text-violet-950">₹{{ number_format($companyPending, 2) }}</p>
                </article>
                <article class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-sky-700">GL Bill Pending</p>
                    <p class="mt-2 text-right font-mono text-2xl font-black text-sky-950">₹{{ number_format($glBillPending, 2) }}</p>
                </article>
                <article class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-rose-700">Current Net Balance</p>
                    <p class="mt-2 text-right font-mono text-2xl font-black text-rose-950">₹{{ number_format($netBalance, 2) }}</p>
                </article>
            </div>

            <!-- Recent Payments Listing -->
            <div class="border border-slate-100 rounded-2xl p-4 divide-y divide-slate-100">
                @foreach($recentPayments as $payment)
                    <div class="py-2 flex items-center justify-between text-xs font-mono">
                        <span class="font-bold text-slate-800">{{ $payment->payment_reference }}</span>
                        <span>₹{{ number_format((float) $payment->requested_amount, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </details>

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

                <div class="p-3 bg-amber-50 rounded-2xl border border-amber-200 text-amber-900 text-[11px] font-medium leading-relaxed">
                    <span class="font-black">Notice:</span> Recording this receipt moves money into the Company Account and keeps the ₹ amount <strong>unallocated</strong>. You can manually allocate it to daily settlements afterwards.
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
                        <h3 class="text-sm font-black uppercase tracking-wide">Manual Settlement Allocation</h3>
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

                    @if($errors->has('allocations'))
                        <div class="p-3 bg-rose-50 rounded-2xl border border-rose-200 text-rose-800 text-xs font-bold font-sans">
                            <i data-lucide="alert-circle" class="w-4 h-4 inline-block mr-1"></i>
                            {{ $errors->first('allocations') }}
                        </div>
                    @endif

                    <!-- Selected Payment Summary -->
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 font-mono text-xs space-y-3">
                        <div x-show="selectedPaymentForAlloc.allocation_has_error" x-cloak class="p-3 bg-rose-50 rounded-2xl border border-rose-200 text-rose-800 text-xs font-bold font-sans">
                            ⚠ Current Allocation Error
                        </div>
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
                        <div>
                            <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Payment Method &amp; Account</span>
                            <span class="text-[10px] text-slate-500 block" x-text="selectedPaymentForAlloc.method + ' • ' + selectedPaymentForAlloc.account"></span>
                        </div>
                        <div>
                            <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Last Allocated Date</span>
                            <span class="text-[11px] font-bold text-slate-700" x-text="selectedPaymentForAlloc.last_allocated_date || 'Not allocated yet'"></span>
                        </div>
                        <div>
                            <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Remaining After Allocation</span>
                            <span class="text-base font-black"
                                  :class="remainingUnallocatedPayment() < 0 ? 'text-rose-600' : 'text-emerald-700'"
                                  x-text="'₹' + Number(remainingUnallocatedPayment()).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
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
                                                @if((float) ($settlement['already_allocated'] ?? 0) > 0)
                                                    • Settled: ₹{{ number_format((float) $settlement['already_allocated'], 2) }}
                                                @endif
                                                • <strong class="text-slate-800">Remaining Due: ₹{{ number_format((float) $settlement['remaining_due'], 2) }}</strong>
                                            </span>
                                            @if((float) ($settlement['deductions'] ?? 0) > 0)
                                                <span class="text-[9px] text-slate-400 block font-mono">
                                                    (Gross: ₹{{ number_format((float) $settlement['gross_sales'], 2) }} − Deductions: ₹{{ number_format((float) $settlement['deductions'], 2) }})
                                                </span>
                                            @endif
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

                    <div x-show="remainingUnallocatedPayment() < 0" x-cloak class="p-3 bg-rose-50 rounded-2xl border border-rose-200 text-rose-800 text-xs font-bold">
                        Warning: Allocated total exceeds available payment amount!
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                        <div class="font-mono text-xs">
                            <span class="text-slate-400 font-bold">Total Selected: </span>
                            <span class="font-black text-slate-900" x-text="'₹' + Number(totalAllocatedSum()).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                        </div>

                        <div class="flex items-center gap-2">
                            <template x-if="isLocalRepairMode">
                                <button type="button"
                                        @click="submitLocalRepairAction('{{ route('admin.cashbook.shop.allocate-payment.clear', $currentShop->slug ?: $currentShop->shop_id) }}', 'Clear all current allocations for this payment? This local repair action cannot be undone.')"
                                        class="px-4 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold transition text-xs border border-rose-200">
                                    Clear Allocation
                                </button>
                            </template>
                            <template x-if="isLocalRepairMode">
                                <button type="button"
                                        @click="submitLocalRepairAction('{{ route('admin.cashbook.shop.allocate-payment.clear-reallocate', $currentShop->slug ?: $currentShop->shop_id) }}', 'Clear all current allocations and re-run auto allocation from zero for this payment? Local-only repair action.')"
                                        class="px-4 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold transition text-xs border border-amber-200">
                                    Clear &amp; Reallocate
                                </button>
                            </template>
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

    <!-- 2B. BULK SETTLEMENT ALLOCATION MODAL -->
    <div x-show="showBulkAllocateModal"
         x-cloak
         @keydown.escape.window="showBulkAllocateModal = false"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showBulkAllocateModal = false"
               class="bg-white rounded-3xl max-w-2xl w-full border border-slate-200 shadow-2xl overflow-hidden max-h-[92vh] flex flex-col animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 bg-gradient-to-r from-slate-900 to-slate-800 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-white/10">
                        <i data-lucide="check-square" class="w-5 h-5 text-emerald-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wide">Settlement Allocation</h3>
                        <p class="text-[11px] text-slate-300 font-medium">Allocate available payments against this shop&rsquo;s open settlements</p>
                    </div>
                </div>
                <button type="button" @click="showBulkAllocateModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST"
                  action="{{ route('admin.cashbook.shop.allocate-payments.bulk', $currentShop->slug ?: $currentShop->shop_id) }}"
                class="p-6 space-y-4 overflow-y-auto"
                  @submit="isSubmitting = true">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="expected_total" :value="bulkSelectedTotal.toFixed(2)">
                <input type="hidden" name="submission_uuid" :value="bulkSubmissionUuid">

                @if($errors->has('expected_total'))
                    <div class="p-3 bg-rose-50 rounded-2xl border border-rose-200 text-rose-800 text-xs font-bold font-sans">
                        <i data-lucide="alert-circle" class="w-4 h-4 inline-block mr-1"></i>
                        {{ $errors->first('expected_total') }}
                    </div>
                @endif
                @if($errors->has('submission_uuid'))
                    <div class="p-3 bg-rose-50 rounded-2xl border border-rose-200 text-rose-800 text-xs font-bold font-sans">
                        <i data-lucide="alert-circle" class="w-4 h-4 inline-block mr-1"></i>
                        {{ $errors->first('submission_uuid') }}
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 font-mono text-xs">
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200">
                        <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Total Received</span>
                        <span class="text-base font-black text-slate-900">₹<span x-text="formatCurrency(paymentsReceived)"></span></span>
                        <span class="text-[10px] text-slate-500 block font-sans">Payments: {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</span>
                    </div>
                    <div class="p-4 bg-amber-50 rounded-2xl border border-amber-200">
                        <span class="text-[9px] font-extrabold uppercase text-amber-700 block">Eligible Unallocated</span>
                        <span class="text-base font-black text-amber-800">₹<span x-text="formatCurrency(eligibleUnallocated)"></span></span>
                        <span class="text-[10px] text-amber-700 block font-sans">Available for allocation</span>
                    </div>
                    <div class="p-4 rounded-2xl border" :class="bulkSettlementOutstanding > 0 ? 'bg-rose-50 border-rose-200' : 'bg-emerald-50 border-emerald-200'">
                        <span class="text-[9px] font-extrabold uppercase block" :class="bulkSettlementOutstanding > 0 ? 'text-rose-700' : 'text-emerald-700'">Settlement Outstanding</span>
                        <span class="text-base font-black" :class="bulkSettlementOutstanding > 0 ? 'text-rose-800' : 'text-emerald-800'">₹<span x-text="formatCurrency(bulkSettlementOutstanding)"></span></span>
                        <span class="text-[10px] block font-sans" :class="bulkSettlementOutstanding > 0 ? 'text-rose-600' : 'text-emerald-600'">Includes older unpaid days</span>
                    </div>
                    <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-200">
                        <span class="text-[9px] font-extrabold uppercase text-emerald-700 block">Remaining After Allocation</span>
                        <span class="text-base font-black text-emerald-800">₹<span x-text="formatCurrency(bulkRemainingAfter)"></span></span>
                        <span class="text-[10px] text-emerald-700 block font-sans">Unallocated amount left after the proposed allocation</span>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-3 text-xs font-sans">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 font-bold text-slate-700">
                        <div>Eligible payments: <span class="font-mono text-slate-950">{{ count($bulkEligiblePayments) }}</span></div>
                        <div>Open settlements: <span class="font-mono text-slate-950">{{ $openSettlementTransactions->where('remaining_due', '>', 0)->count() }}</span></div>
                        <div>Total settlement outstanding: <span class="font-mono text-slate-950">₹{{ number_format((float) $openSettlementTransactions->sum('remaining_due'), 2) }}</span></div>
                        <div>Eligible amount available: <span class="font-mono text-slate-950">₹{{ number_format((float) collect($bulkEligiblePayments)->sum('unallocated'), 2) }}</span></div>
                        <div class="sm:col-span-2">Total selected for allocation: <span class="font-mono text-emerald-800">₹<span x-text="formatCurrency(bulkSelectedTotal)"></span></span></div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <button type="button"
                                @click="autoAllocateAllPayments()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 text-[11px] font-extrabold transition cursor-pointer">
                            <i data-lucide="zap" class="w-3.5 h-3.5 text-emerald-600"></i>
                            <span>Auto Allocate All</span>
                        </button>
                        <button type="button"
                                @click="clearBulkAllocation()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-[11px] font-extrabold transition cursor-pointer">
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span>Clear All</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                    <div class="font-mono text-xs">
                        <span class="text-slate-400 font-bold">Total Selected: </span>
                        <span class="font-black text-slate-900">₹<span x-text="formatCurrency(bulkSelectedTotal)"></span></span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="showBulkAllocateModal = false; clearBulkAllocation()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition text-xs">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="!bulkAutoAllocated || bulkSelectedTotal <= 0 || isSubmitting"
                                class="px-5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 disabled:opacity-50 disabled:cursor-not-allowed text-white font-black text-xs shadow-sm transition cursor-pointer">
                            Confirm Settlement Allocation
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 2C. PAYMENT DETAILS LIST MODAL -->
    <div x-show="showPaymentDetailsListModal"
         x-cloak
         @keydown.escape.window="showPaymentDetailsListModal = false"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showPaymentDetailsListModal = false"
               class="bg-white rounded-3xl max-w-5xl w-full border border-slate-200 shadow-2xl overflow-hidden max-h-[92vh] flex flex-col animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-white/10">
                        <i data-lucide="wallet" class="w-5 h-5 text-emerald-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wide">Shop Payment Details</h3>
                        <p class="text-[11px] text-slate-300 font-medium">Individual receipts and single-payment allocation remain here</p>
                    </div>
                </div>
                <button type="button" @click="showPaymentDetailsListModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto">
                <div class="mb-4 grid grid-cols-3 gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-200 text-center font-mono">
                    <div>
                        <span class="text-[9px] font-extrabold uppercase text-slate-400 block font-sans">Total Received</span>
                        <span class="text-sm font-black text-slate-900">₹{{ number_format($totalPaymentsReceived, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-[9px] font-extrabold uppercase text-slate-400 block font-sans">Total Allocated</span>
                        <span class="text-sm font-black text-emerald-700">₹{{ number_format($totalPaymentsAllocated, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-[9px] font-extrabold uppercase text-slate-400 block font-sans">Total Unallocated</span>
                        <span class="text-sm font-black text-amber-700">₹{{ number_format($unallocatedPayments, 2) }}</span>
                    </div>
                </div>

                <div x-show="paymentsList.length === 0" class="p-8 text-center text-slate-400 border border-dashed border-slate-200 rounded-2xl">
                    <i data-lucide="wallet" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                    <p class="text-xs font-bold">No payment detail rows are available on this page.</p>
                </div>
                <div x-show="paymentsList.length > 0" class="overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                                <th class="py-3 px-4 rounded-l-xl">Date &amp; Ref</th>
                                <th class="py-3 px-4">Source</th>
                                <th class="py-3 px-4">Method &amp; Account</th>
                                <th class="py-3 px-4 text-right">Received</th>
                                <th class="py-3 px-4 text-right">Allocated</th>
                                <th class="py-3 px-4 text-right">Unallocated</th>
                                <th class="py-3 px-4">Allocated Date</th>
                                <th class="py-3 px-4 text-right rounded-r-xl">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-mono">
                            <template x-for="payment in paymentsList" :key="payment.key || payment.id">
                                <tr class="hover:bg-slate-50/80 transition-colors" :class="payment.allocation_has_error ? 'bg-rose-50/40' : ''">
                                    <td class="py-3 px-4 font-sans">
                                        <span class="font-extrabold text-slate-900 text-sm block" x-text="payment.date"></span>
                                        <span class="text-[10px] text-slate-400 font-mono" x-text="payment.reference || 'No reference'"></span>
                                    </td>
                                    <td class="py-3 px-4 font-sans">
                                        <span class="inline-flex items-center text-[10px] font-extrabold px-2 py-0.5 rounded-lg border" :class="payment.source_badge" x-text="payment.source"></span>
                                    </td>
                                    <td class="py-3 px-4 font-sans">
                                        <span class="font-bold text-slate-800 uppercase text-[11px] block" x-text="payment.method"></span>
                                        <span class="text-[10px] text-slate-500 font-mono" x-text="payment.account"></span>
                                    </td>
                                    <td class="py-3 px-4 text-right font-bold text-slate-900">₹<span x-text="formatCurrency(payment.amount)"></span></td>
                                    <td class="py-3 px-4 text-right font-bold text-emerald-700">₹<span x-text="formatCurrency(payment.allocated)"></span></td>
                                    <td class="py-3 px-4 text-right font-black" :class="Number(payment.unallocated) > 0 ? 'text-amber-700' : 'text-slate-400'">₹<span x-text="formatCurrency(payment.unallocated)"></span></td>
                                    <td class="py-3 px-4 font-sans">
                                        <span class="text-[11px] font-bold" :class="payment.last_allocated_date ? 'text-slate-700' : 'text-slate-400'" x-text="payment.last_allocated_date || '—'"></span>
                                    </td>
                                    <td class="py-3 px-4 text-right font-sans">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span class="inline-flex items-center text-[10px] font-extrabold px-2.5 py-1 rounded-lg border" :class="payment.status_badge" x-text="payment.allocation_status_label"></span>
                                            <template x-if="payment.allocation_has_error">
                                                <span class="inline-flex items-center text-[10px] font-black px-2.5 py-1 rounded-lg border bg-rose-100 text-rose-800 border-rose-300">⚠ Fix Allocation</span>
                                            </template>
                                            <button type="button" @click="openDetailsModal(payment)" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition cursor-pointer">
                                                <i data-lucide="eye" class="w-3 h-3"></i>
                                                <span>Details</span>
                                            </button>
                                            <template x-if="Number(payment.allocated) > 0">
                                                <form method="POST"
                                                      action="{{ route('admin.cashbook.shop.allocate-payment.clear', $currentShop->slug) }}"
                                                      class="inline-block"
                                                      @submit.prevent="if (window.confirm('Clear allocations for this payment? The received payment record will remain, but its allocations will be cleared.')) $el.submit()">
                                                    @csrf
                                                    <input type="hidden" name="payment_request_id" :value="payment.id">
                                                    <input type="hidden" name="month" value="{{ $month }}">
                                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold transition cursor-pointer">
                                                        <i data-lucide="x-circle" class="w-3 h-3 text-rose-600"></i>
                                                        <span>Clear Allocation</span>
                                                    </button>
                                                </form>
                                            </template>
                                            <template x-if="payment.can_allocate && Number(payment.unallocated) > 0 && payment.cheque_status !== 'pending'">
                                                <button type="button" @click="openAllocateModal(payment)" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition shadow-xs cursor-pointer">
                                                    <i data-lucide="check-square" class="w-3 h-3"></i>
                                                    <span>Allocate</span>
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                @if ($allShopPayments->hasPages())
                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 font-sans">
                        <div>
                            Showing <span class="font-bold text-slate-800">{{ $allShopPayments->firstItem() ?? 0 }}</span> to <span class="font-bold text-slate-800">{{ $allShopPayments->lastItem() ?? 0 }}</span> of <span class="font-bold text-slate-800">{{ $allShopPayments->total() }}</span> payments
                        </div>
                        <div>
                            {{ $allShopPayments->appends(request()->query())->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- 3. PAYMENT DETAILS MODAL -->
    <div x-show="showPaymentDetailsModal"
         x-cloak
         @keydown.escape.window="showPaymentDetailsModal = false"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showPaymentDetailsModal = false"
               class="bg-white rounded-3xl max-w-xl w-full border border-slate-200 shadow-2xl overflow-hidden max-h-[92vh] flex flex-col animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-white/10">
                        <i data-lucide="info" class="w-5 h-5 text-emerald-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wide">Payment Details &amp; Audit Trail</h3>
                        <p class="text-[11px] text-slate-300 font-medium">Traceability, allocations &amp; company reconciliation</p>
                    </div>
                </div>
                <button type="button" @click="showPaymentDetailsModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <template x-if="selectedPaymentForDetails">
                <div class="p-6 space-y-4 text-xs font-sans overflow-y-auto">
                    <!-- Summary Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-200">
                        <div>
                            <span class="text-[9px] font-black uppercase text-slate-400 block">Total Received</span>
                            <span class="text-base font-black text-slate-900 font-mono" x-text="'₹' + Number(selectedPaymentForDetails.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                        </div>
                        <div>
                            <span class="text-[9px] font-black uppercase text-slate-400 block">Allocated / Unallocated</span>
                            <span class="text-xs font-bold text-emerald-700 font-mono" x-text="'₹' + Number(selectedPaymentForDetails.allocated).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                            <span class="text-slate-400"> / </span>
                            <span class="text-xs font-bold font-mono"
                                  :class="parseFloat(selectedPaymentForDetails.unallocated) > 0 ? 'text-amber-700' : 'text-slate-400'"
                                  x-text="'₹' + Number(selectedPaymentForDetails.unallocated).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                        </div>
                        <div>
                            <span class="text-[9px] font-black uppercase text-slate-400 block">Payment Source</span>
                            <span class="inline-flex items-center text-[10px] font-extrabold px-2 py-0.5 rounded-lg border bg-white text-slate-800 border-slate-300" x-text="selectedPaymentForDetails.source || 'MANUAL'"></span>
                        </div>
                        <div>
                            <span class="text-[9px] font-black uppercase text-slate-400 block">Method &amp; Account</span>
                            <span class="font-bold text-slate-800" x-text="selectedPaymentForDetails.method + ' (' + selectedPaymentForDetails.account + ')'"></span>
                        </div>
                        <div class="sm:col-span-2">
                            <span class="text-[9px] font-black uppercase text-slate-400 block">Date &amp; Reference</span>
                            <span class="font-bold text-slate-800" x-text="selectedPaymentForDetails.date + ' • ' + (selectedPaymentForDetails.reference || 'No ref')"></span>
                        </div>
                        <div class="sm:col-span-3">
                            <span class="text-[9px] font-black uppercase text-slate-400 block">Allocated Date</span>
                            <span class="font-bold" :class="selectedPaymentForDetails.last_allocated_date ? 'text-slate-800' : 'text-slate-400'" x-text="selectedPaymentForDetails.last_allocated_date || 'Not allocated yet'"></span>
                        </div>
                    </div>

                    <!-- 1. Settlement Allocation Status & Breakdown Link -->
                    <div class="p-4 rounded-2xl space-y-2" :class="selectedPaymentForDetails.allocation_has_error ? 'border border-rose-200 bg-rose-50' : 'border border-emerald-100 bg-emerald-50/40'">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-wide text-emerald-900 block">Settlement Allocation</span>
                                <span class="text-[11px] text-emerald-700 font-bold" x-text="selectedPaymentForDetails.allocation_status_label"></span>
                            </div>
                            <button type="button"
                                    @click="showPaymentDetailsModal = false; openAllocationBreakdownModal(selectedPaymentForDetails)"
                                    class="inline-flex items-center gap-1 px-3 py-1 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-[11px] font-bold shadow-xs transition cursor-pointer">
                                <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                                <span>View Allocation Breakdown</span>
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-500 font-medium">
                            <span class="font-bold text-slate-800" x-text="selectedPaymentForDetails.allocations.length"></span> daily settlements cleared with this payment.
                        </p>
                        <template x-if="selectedPaymentForDetails.allocation_has_error">
                            <p class="text-[11px] font-bold text-rose-700">⚠ Current Allocation Error. Please open allocation and fix.</p>
                        </template>
                    </div>

                    <!-- 2. Company Cash Movement / Bank Reconciliation Section -->
                    <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black uppercase tracking-wide text-slate-700 block">Company Reconciliation</span>
                            <template x-if="selectedPaymentForDetails.is_reconciled">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-emerald-100 text-emerald-900 text-[10px] font-black uppercase border border-emerald-300">
                                    <i data-lucide="check" class="w-3 h-3 text-emerald-700"></i>
                                    <span>Reconciled</span>
                                </span>
                            </template>
                            <template x-if="!selectedPaymentForDetails.is_reconciled">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-amber-100 text-amber-900 text-[10px] font-black uppercase border border-amber-300">
                                    <span x-text="selectedPaymentForDetails.reconciliation_status === 'floating' ? 'Pending Cheque' : 'Unreconciled'"></span>
                                </span>
                            </template>
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-1 font-mono text-[11px]">
                            <div>
                                <span class="text-[9px] font-sans font-bold text-slate-400 block uppercase">Destination Account</span>
                                <span class="font-bold text-slate-800 font-sans" x-text="selectedPaymentForDetails.account"></span>
                            </div>
                            <div>
                                <span class="text-[9px] font-sans font-bold text-slate-400 block uppercase">Statement Reference</span>
                                <span class="font-bold text-slate-800" x-text="selectedPaymentForDetails.statement_ref || 'Matched via cashbook receipt'"></span>
                            </div>
                            <div>
                                <span class="text-[9px] font-sans font-bold text-slate-400 block uppercase">Reconciled At</span>
                                <span class="text-slate-700" x-text="selectedPaymentForDetails.reconciled_at || 'Pending verification'"></span>
                            </div>
                            <div>
                                <span class="text-[9px] font-sans font-bold text-slate-400 block uppercase">Reconciled By</span>
                                <span class="text-slate-700 font-sans" x-text="selectedPaymentForDetails.reconciled_by || '—'"></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                        <div>
                            @if(auth()->user()?->hasRole('admin') || auth()->user()?->isMainAdmin())
                                <template x-if="selectedPaymentForDetails && selectedPaymentForDetails.id > 0">
                                    <form method="POST"
                                          action="{{ route('admin.cashbook.shop.payments.destroy', [$currentShop->slug ?: $currentShop->shop_id]) }}"
                                          @submit.prevent="if (window.confirm('Are you sure you want to delete this payment? Any linked allocation or reconciliation will be reversed where required.')) $el.submit()">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="payment_request_id" :value="selectedPaymentForDetails.id">
                                        @foreach(request()->query() as $queryKey => $queryVal)
                                            @if(!in_array($queryKey, ['_token', '_method', 'payment_request_id'], true) && is_scalar($queryVal))
                                                <input type="hidden" name="{{ $queryKey }}" value="{{ $queryVal }}">
                                            @endif
                                        @endforeach
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-xs hover:shadow transition cursor-pointer">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            <span>Delete Payment</span>
                                        </button>
                                    </form>
                                </template>
                            @endif
                        </div>
                        <button type="button" @click="showPaymentDetailsModal = false" class="px-4 py-2 rounded-xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition cursor-pointer">
                            Close
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- 4. ALLOCATION BREAKDOWN MODAL -->
    <div x-show="showAllocationBreakdownModal"
         x-cloak
         @keydown.escape.window="showAllocationBreakdownModal = false"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showAllocationBreakdownModal = false"
               class="bg-white rounded-3xl max-w-2xl w-full border border-slate-200 shadow-2xl overflow-hidden max-h-[92vh] flex flex-col animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 bg-gradient-to-r from-slate-900 to-slate-800 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-white/10">
                        <i data-lucide="layers" class="w-5 h-5 text-emerald-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wide">Settlement Allocation Breakdown</h3>
                        <p class="text-[11px] text-slate-300 font-medium">Daily company payable settlements cleared by this payment</p>
                    </div>
                </div>
                <button type="button" @click="showAllocationBreakdownModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <template x-if="selectedPaymentForBreakdown">
                <div class="p-6 space-y-4 overflow-y-auto">
                    <!-- Payment Header Summary -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 bg-slate-50 rounded-2xl border border-slate-200 font-sans text-xs">
                        <div>
                            <span class="text-[9px] font-black uppercase text-slate-400 block">Payment Date &amp; Ref</span>
                            <span class="font-extrabold text-slate-900 block" x-text="selectedPaymentForBreakdown.date"></span>
                            <span class="text-[10px] text-slate-500 font-mono" x-text="selectedPaymentForBreakdown.reference || 'No ref'"></span>
                        </div>
                        <div>
                            <span class="text-[9px] font-black uppercase text-slate-400 block">Received Amount</span>
                            <span class="text-sm font-black text-slate-900 font-mono" x-text="'₹' + Number(selectedPaymentForBreakdown.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                            <span class="text-[10px] text-slate-500" x-text="selectedPaymentForBreakdown.method"></span>
                        </div>
                        <div>
                            <span class="text-[9px] font-black uppercase text-slate-400 block">Total Allocated</span>
                            <span class="text-sm font-black text-emerald-700 font-mono" x-text="'₹' + Number(selectedPaymentForBreakdown.allocated).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                            <span class="text-[10px] text-emerald-600 font-bold" x-text="selectedPaymentForBreakdown.allocation_status_label"></span>
                        </div>
                        <div>
                            <span class="text-[9px] font-black uppercase text-slate-400 block">Unallocated</span>
                            <span class="text-sm font-black font-mono"
                                  :class="parseFloat(selectedPaymentForBreakdown.unallocated) > 0 ? 'text-amber-700' : 'text-slate-400'"
                                  x-text="'₹' + Number(selectedPaymentForBreakdown.unallocated).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                        </div>
                    </div>

                    <!-- Allocations Table -->
                    <div class="space-y-2">
                        <span class="text-[11px] font-black uppercase tracking-wide text-slate-700 block font-sans">
                            Settlements Cleared By This Payment (<span x-text="selectedPaymentForBreakdown.allocations.length"></span>)
                        </span>

                        <template x-if="selectedPaymentForBreakdown.allocations.length === 0">
                            <div class="p-6 text-center text-slate-400 border border-dashed border-slate-200 rounded-2xl text-xs font-bold font-sans">
                                This payment has not been allocated to any daily settlements yet.
                            </div>
                        </template>

                        <template x-if="selectedPaymentForBreakdown.allocations.length > 0">
                            <div class="overflow-x-auto rounded-2xl border border-slate-200">
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-slate-200 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50 font-sans">
                                            <th class="py-2.5 px-3">Date</th>
                                            <th class="py-2.5 px-3 text-right">Company Payable</th>
                                            <th class="py-2.5 px-3 text-right">Applied From This Payment</th>
                                            <th class="py-2.5 px-3 text-right">Remaining After</th>
                                            <th class="py-2.5 px-3 text-center">Settlement Status</th>
                                            <th class="py-2.5 px-3 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 font-mono">
                                        <template x-for="alloc in selectedPaymentForBreakdown.allocations" :key="alloc.id">
                                            <tr class="hover:bg-slate-50/80 transition-colors">
                                                <td class="py-3 px-3 font-sans">
                                                    <span class="font-extrabold text-slate-900 block" x-text="alloc.date"></span>
                                                    <span class="text-[10px] text-slate-400" x-text="alloc.name || 'Daily Settlement'"></span>
                                                </td>
                                                <td class="py-3 px-3 text-right font-bold text-slate-800" x-text="'₹' + Number(alloc.company_payable).toLocaleString('en-IN', {minimumFractionDigits: 2})"></td>
                                                <td class="py-3 px-3 text-right font-black text-emerald-700" x-text="'₹' + Number(alloc.applied_amount).toLocaleString('en-IN', {minimumFractionDigits: 2})"></td>
                                                <td class="py-3 px-3 text-right font-bold"
                                                    :class="parseFloat(alloc.remaining_after) > 0 ? 'text-amber-700' : 'text-slate-400'"
                                                    x-text="'₹' + Number(alloc.remaining_after).toLocaleString('en-IN', {minimumFractionDigits: 2})"></td>
                                                <td class="py-3 px-3 text-center font-sans">
                                                    <span class="inline-flex items-center text-[9px] font-black uppercase px-2 py-0.5 rounded-md border"
                                                          :class="alloc.settlement_status === 'SETTLED' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200'"
                                                          x-text="alloc.settlement_status"></span>
                                                </td>
                                                <td class="py-3 px-3 text-right font-sans">
                                                    <form method="POST" :action="'/admin/cashbook/shops/{{ $currentShop->slug ?: $currentShop->shop_id }}/allocations/' + alloc.id + '/remove'">
                                                        @csrf
                                                        <button type="submit"
                                                                onclick="return confirm('Remove this settlement allocation? The payment unallocated balance will increase.')"
                                                                class="p-1 rounded-lg text-rose-600 hover:bg-rose-50 hover:text-rose-800 transition cursor-pointer"
                                                                title="Remove Allocation">
                                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot>
                                        <tr class="border-t border-slate-200 bg-slate-50 font-bold font-mono">
                                            <td class="py-2.5 px-3 font-sans uppercase text-[10px] text-slate-500">Total Applied</td>
                                            <td class="py-2.5 px-3"></td>
                                            <td class="py-2.5 px-3 text-right text-sm font-black text-emerald-700"
                                                x-text="'₹' + Number(selectedPaymentForBreakdown.allocated).toLocaleString('en-IN', {minimumFractionDigits: 2})"></td>
                                            <td colspan="3" class="py-2.5 px-3 text-right font-sans text-[10px] text-slate-400">
                                                Matches payment allocated total
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </template>
                    </div>

                    <div class="flex justify-end pt-2 border-t border-slate-100 font-sans">
                        <button type="button" @click="showAllocationBreakdownModal = false" class="px-4 py-2 rounded-xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition cursor-pointer">
                            Close
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- 5. RECONCILE PAYMENT MODAL -->
    <div x-show="showReconcilePaymentModal"
         x-cloak
         @keydown.escape.window="showReconcilePaymentModal = false"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showReconcilePaymentModal = false"
             class="bg-white rounded-3xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 bg-gradient-to-r from-slate-900 to-indigo-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-white/10">
                        <i data-lucide="link-2" class="w-5 h-5 text-indigo-400"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wide">Reconcile Company Payment</h3>
                        <p class="text-[11px] text-slate-300 font-medium">Verify actual company bank / cash movement</p>
                    </div>
                </div>
                <button type="button" @click="showReconcilePaymentModal = false" class="p-1 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <template x-if="selectedPaymentForReconcile">
                <form method="POST"
                      :action="'/admin/cashbook/finance/payments/' + selectedPaymentForReconcile.id + '/reconcile'"
                      class="p-6 space-y-4 text-xs font-medium text-slate-700">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                    <input type="hidden" name="difference_action" value="none">

                    <!-- Payment Details Card -->
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 grid grid-cols-2 gap-3 font-sans">
                        <div>
                            <span class="text-[9px] font-black uppercase text-slate-400 block">Payment Date &amp; Ref</span>
                            <span class="font-extrabold text-slate-900 block" x-text="selectedPaymentForReconcile.date"></span>
                            <span class="text-[10px] text-slate-500 font-mono" x-text="selectedPaymentForReconcile.reference || 'No ref'"></span>
                        </div>
                        <div>
                            <span class="text-[9px] font-black uppercase text-slate-400 block">Payment Amount</span>
                            <span class="text-base font-black text-slate-900 font-mono" x-text="'₹' + Number(selectedPaymentForReconcile.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                            <span class="text-[10px] text-slate-500 uppercase" x-text="selectedPaymentForReconcile.method"></span>
                        </div>
                    </div>

                    <!-- Destination Company Account -->
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                            Company Account <span class="text-rose-500">*</span>
                        </label>
                        <select name="company_account_id"
                                x-model="selectedPaymentForReconcile.company_account_id"
                                required
                                class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-300 font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-600 focus:outline-none cursor-pointer">
                            @foreach($companyAccounts as $acc)
                                <option value="{{ $acc->id }}">
                                    {{ $acc->name }} ({{ ucfirst($acc->account_type) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Cleared Amount -->
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                            Cleared Amount (₹) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 font-bold">₹</span>
                            <input type="number"
                                   step="0.01"
                                   min="0.01"
                                   name="cleared_amount"
                                   :value="selectedPaymentForReconcile.amount"
                                   required
                                   class="w-full pl-7 pr-3 py-2 bg-slate-50 rounded-xl border border-slate-300 font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-600 focus:outline-none">
                        </div>
                    </div>

                    <!-- Statement Entry (Optional Match) -->
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                            Match Bank Statement Entry (Optional)
                        </label>
                        <select name="statement_entry_id"
                                class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-300 font-medium text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-600 focus:outline-none cursor-pointer">
                            <option value="">-- Direct Clear (No Bank Statement Link) --</option>
                            @foreach($unmatchedStatementEntries as $entry)
                                <option value="{{ $entry->id }}">
                                    {{ $entry->transaction_date?->format('d M Y') }} • ₹{{ number_format((float) $entry->amount, 2) }} • {{ $entry->reference ?: $entry->narration ?: 'Statement #'.$entry->id }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Admin Note -->
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">
                            Reconciliation Notes
                        </label>
                        <textarea name="admin_note"
                                  rows="2"
                                  placeholder="Add any verification notes..."
                                  class="w-full px-3 py-2 bg-slate-50 rounded-xl border border-slate-300 font-medium text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-600 focus:outline-none"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 font-sans">
                        <button type="button" @click="showReconcilePaymentModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-700 hover:bg-indigo-800 text-white font-black shadow-sm transition cursor-pointer">
                            Confirm Reconciliation
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    function monthlyBankingManager(config) {
        return {
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
