@extends('admin.cashbook.layouts.app')

@section('title', ($detail['purchaser']['name'] ?: 'Purchaser') . ' — Monthly Summary (' . $detail['period']['label'] . ')')

@section('header_title')
    <i data-lucide="calendar-check" class="w-5 h-5 text-emerald-600"></i> {{ $detail['purchaser']['name'] }} Monthly Summary
@endsection

@section('header_subtitle')
    Audited closing cash balances, vendor purchasing credit pending, company funding, cash/credit purchases, expenses, and carry-forward status.
@endsection

@section('content')
@php
    $purchaser = $detail['purchaser'];
    $period = $detail['period'];
    $opening = $detail['opening'];
    $credit = $detail['credit'];
    $activity = $detail['activity'];
    $cashDetails = $detail['cash_details'];
    $bills = $detail['bills'];
    $pending = $detail['pending'];
    $closing = $detail['closing'];
    $carry = $detail['carry_forward'];
    $status = $detail['status'];
    $drilldowns = $detail['drilldowns'];

    $backToAllUrl = route('admin.cashbook.finance.purchase.monthly-summary.index', ['month' => $month]);
    $openOperationalUrl = route('admin.cashbook.finance.purchase.purchasers.show', ['purchaser' => $purchaser['public_uuid']]);
@endphp

<div class="mx-auto max-w-[96rem] space-y-6 pb-16">

    <!-- Top Navigation & Controls Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-3xl bg-white p-5 border border-slate-200/80 shadow-xs">
        <div class="flex items-center gap-3">
            <a href="{{ $backToAllUrl }}"
               class="inline-flex h-10 items-center gap-2 rounded-xl bg-slate-100 hover:bg-slate-200 px-3.5 text-xs font-bold text-slate-700 transition"
               title="Back to All Purchasers Summary">
                <i data-lucide="arrow-left" class="h-4 w-4 text-slate-600"></i>
                <span>All Purchasers</span>
            </a>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-lg font-black tracking-tight text-slate-900 uppercase">
                        {{ $purchaser['name'] }} — PURCHASER SUMMARY
                    </h1>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-black text-slate-600 font-mono">
                        {{ $purchaser['email'] }}
                    </span>
                    <span class="rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider">
                        Read Only
                    </span>
                </div>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">
                    {{ $period['label'] }} &bull; {{ Carbon\Carbon::parse($period['start_date'])->format('d M Y') }} – {{ Carbon\Carbon::parse($period['end_date'])->format('d M Y') }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <div class="flex items-center gap-2">
                <label for="purchaser-month-select" class="text-xs font-bold text-slate-500 uppercase tracking-wider">Month:</label>
                <select
                    id="purchaser-month-select"
                    onchange="window.location.href='{{ route('admin.cashbook.finance.purchase.monthly-summary.show', $purchaser['public_uuid']) }}?month=' + this.value"
                    class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-900 shadow-xs focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 cursor-pointer min-w-[150px]"
                >
                    @foreach($availableMonths as $m)
                        <option value="{{ $m['value'] }}" {{ $m['value'] === $month ? 'selected' : '' }}>
                            {{ $m['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <a href="{{ $openOperationalUrl }}"
               class="inline-flex h-10 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-xs"
               title="Open Purchaser Operation Workspace">
                <i data-lucide="wallet" class="h-4 w-4 text-slate-500"></i>
                <span>Purchaser Finance Workspace</span>
            </a>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION A: OPENING POSITION (01 OF THE MONTH) ────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-900 text-white text-xs font-black">A</span>
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-800">
                    Opening Position — 01 {{ strtoupper(Carbon\Carbon::parse($period['start_date'])->format('M Y')) }}
                </h2>
            </div>
            <span class="text-[11px] font-bold text-slate-400">
                Accounting Start / Carry Forward Position prior to {{ Carbon\Carbon::parse($period['start_date'])->format('d M Y') }}
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Opening Physical Cash Position -->
            <div class="rounded-2xl bg-slate-50/80 p-4 border border-slate-200/70 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">
                    Opening Purchaser Physical Cash
                </span>
                <div class="flex items-baseline gap-3 mt-1">
                    <span class="font-mono text-2xl font-black text-slate-900">
                        ₹{{ number_format((float) $opening['cash_balance'], 2) }}
                    </span>
                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-black uppercase tracking-wider {{ $opening['direction'] === 'purchaser_holds_company_cash' ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : ($opening['direction'] === 'company_owes_purchaser' ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-slate-200 text-slate-700') }}">
                        {{ $opening['direction_label'] }}
                    </span>
                </div>
                <p class="text-[11px] font-semibold text-slate-500 mt-2">
                    Physical unremitted company cash / advance in purchaser's hands at month start (₹0.00 for accounting start).
                </p>
            </div>

            <!-- Opening Vendor Purchasing Credit Pending -->
            <div class="rounded-2xl bg-amber-50/50 p-4 border border-amber-200/80 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-amber-900">
                    Opening Vendor Purchase Credit Pending
                </span>
                <div class="flex items-baseline gap-3 mt-1">
                    <span class="font-mono text-2xl font-black text-amber-950">
                        ₹{{ number_format((float) ($credit['opening_credit_pending'] ?? $opening['credit_pending'] ?? 0), 2) }}
                    </span>
                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-black uppercase tracking-wider {{ (float)($credit['opening_credit_pending'] ?? 0) > 0 ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-slate-200 text-slate-700' }}">
                        {{ (float)($credit['opening_credit_pending'] ?? 0) > 0 ? 'Carried Vendor Credit' : 'Zero Carried Credit' }}
                    </span>
                </div>
                <p class="text-[11px] font-semibold text-amber-800/80 mt-2">
                    Legitimate unpaid vendor purchasing obligations carried forward across the accounting boundary.
                </p>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION B: VENDOR PURCHASING CREDIT BREAKDOWN ────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl border border-amber-200/90 bg-white p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-amber-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-amber-800 text-white text-xs font-black">B</span>
                <h2 class="text-xs font-black uppercase tracking-wider text-amber-950">
                    Vendor Purchasing Credit Position &amp; Breakdown
                </h2>
            </div>
            <span class="text-xs font-mono font-black text-amber-950 bg-amber-100 border border-amber-300 px-3 py-1 rounded-md">
                Current Credit Pending: ₹{{ number_format((float) $credit['credit_pending'], 2) }}
            </span>
        </div>

        <p class="text-xs font-semibold text-slate-500">
            Authoritative tracking of vendor purchasing credit obligations associated with this purchaser: opening obligations + month credit purchases - settlements - settlement discounts.
        </p>

        <!-- Credit Formula KPI Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-xs">
            <div class="rounded-2xl bg-slate-50 p-3.5 border border-slate-200/80">
                <span class="block text-[10px] font-black uppercase text-slate-500">1. Opening Credit</span>
                <span class="mt-1 block font-mono text-base font-bold text-slate-900">
                    ₹{{ number_format((float) $credit['opening_credit_pending'], 2) }}
                </span>
                <span class="text-[10px] text-slate-400 font-semibold">Carried from August</span>
            </div>

            <div class="rounded-2xl bg-indigo-50/40 p-3.5 border border-indigo-200/80">
                <span class="block text-[10px] font-black uppercase text-indigo-800">2. Credit Purchases</span>
                <span class="mt-1 block font-mono text-base font-bold text-indigo-700">
                    +₹{{ number_format((float) $credit['credit_purchases'], 2) }}
                </span>
                <span class="text-[10px] text-indigo-600 font-semibold">New credit bills in month</span>
            </div>

            <div class="rounded-2xl bg-emerald-50/40 p-3.5 border border-emerald-200/80">
                <span class="block text-[10px] font-black uppercase text-emerald-800">3. Paid / Settled</span>
                <span class="mt-1 block font-mono text-base font-bold text-emerald-700">
                    -₹{{ number_format((float) $credit['credit_settled'], 2) }}
                </span>
                <span class="text-[10px] text-emerald-600 font-semibold">Company settlements paid</span>
            </div>

            <div class="rounded-2xl bg-amber-50/40 p-3.5 border border-amber-200/80">
                <span class="block text-[10px] font-black uppercase text-amber-800">4. Discounts</span>
                <span class="mt-1 block font-mono text-base font-bold text-amber-700">
                    -₹{{ number_format((float) $credit['credit_discount'], 2) }}
                </span>
                <span class="text-[10px] text-amber-600 font-semibold">Settlement discounts</span>
            </div>

            <div class="rounded-2xl bg-amber-950 text-white p-3.5 border border-amber-900 shadow-sm">
                <span class="block text-[10px] font-black uppercase text-amber-300">5. Credit Pending</span>
                <span class="mt-1 block font-mono text-base font-black text-amber-100">
                    = ₹{{ number_format((float) $credit['credit_pending'], 2) }}
                </span>
                <span class="text-[10px] text-amber-300 font-semibold">Current vendor payable</span>
            </div>
        </div>

        <!-- Vendor Breakdown Table -->
        <div class="space-y-3 pt-2">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center justify-between">
                <span>Vendor-by-Vendor Credit Breakdown ({{ count($credit['breakdown_by_vendor'] ?? []) }} Vendors)</span>
            </h3>

            <div class="overflow-x-auto rounded-2xl border border-slate-200/80">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-100/70 text-[10px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3">Vendor / Supplier</th>
                            <th class="px-3.5 py-3 text-right">Opening Credit</th>
                            <th class="px-3.5 py-3 text-right">Month Purchases</th>
                            <th class="px-3.5 py-3 text-right">Paid / Settled</th>
                            <th class="px-3.5 py-3 text-right">Discount</th>
                            <th class="px-3.5 py-3 text-right">Current Pending</th>
                            <th class="px-3.5 py-3 text-center">Bills Count</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                        @forelse($credit['breakdown_by_vendor'] as $vendor)
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="px-4 py-3">
                                    <span class="font-bold text-slate-900 block">{{ $vendor['supplier_name'] }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">Vendor #{{ $vendor['supplier_id'] }}</span>
                                </td>
                                <td class="px-3.5 py-3 text-right font-mono text-slate-900">
                                    ₹{{ number_format((float) $vendor['opening_credit'], 2) }}
                                </td>
                                <td class="px-3.5 py-3 text-right font-mono text-indigo-700">
                                    ₹{{ number_format((float) $vendor['credit_purchases'], 2) }}
                                </td>
                                <td class="px-3.5 py-3 text-right font-mono text-emerald-700">
                                    ₹{{ number_format((float) $vendor['credit_settled'], 2) }}
                                </td>
                                <td class="px-3.5 py-3 text-right font-mono text-amber-700">
                                    ₹{{ number_format((float) $vendor['credit_discount'], 2) }}
                                </td>
                                <td class="px-3.5 py-3 text-right font-mono font-bold text-amber-950">
                                    ₹{{ number_format((float) $vendor['credit_pending'], 2) }}
                                </td>
                                <td class="px-3.5 py-3 text-center">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700 font-mono">
                                        {{ $vendor['invoices_count'] }} bills
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-6 text-center text-slate-400 font-medium">
                                    No vendor purchasing credit records found for this purchaser.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($credit['breakdown_by_vendor'] ?? []) > 0)
                        <tfoot class="bg-slate-50 font-black text-slate-900 border-t border-slate-200 text-xs">
                            <tr>
                                <td class="px-4 py-3 uppercase tracking-wider">Total Credit</td>
                                <td class="px-3.5 py-3 text-right font-mono">
                                    ₹{{ number_format((float) $credit['opening_credit_pending'], 2) }}
                                </td>
                                <td class="px-3.5 py-3 text-right font-mono text-indigo-700">
                                    ₹{{ number_format((float) $credit['credit_purchases'], 2) }}
                                </td>
                                <td class="px-3.5 py-3 text-right font-mono text-emerald-700">
                                    ₹{{ number_format((float) $credit['credit_settled'], 2) }}
                                </td>
                                <td class="px-3.5 py-3 text-right font-mono text-amber-700">
                                    ₹{{ number_format((float) $credit['credit_discount'], 2) }}
                                </td>
                                <td class="px-3.5 py-3 text-right font-mono text-amber-950">
                                    ₹{{ number_format((float) $credit['credit_pending'], 2) }}
                                </td>
                                <td class="px-3.5 py-3 text-center">
                                    <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-700">
                                        Consolidated
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION C: MONTH PURCHASING & FUNDING ACTIVITY ───────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-900 text-white text-xs font-black">C</span>
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-800">
                    {{ $period['label'] }} Purchasing &amp; Funding Activity
                </h2>
            </div>
            <span class="text-[11px] font-bold text-slate-400">
                Audited monthly transactions by accounting date
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5">
            <!-- Company Funded -->
            <div class="rounded-2xl bg-white p-3.5 border border-slate-200/80 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Company Funded</span>
                <span class="mt-1 block font-mono text-lg font-bold text-slate-900">
                    ₹{{ number_format((float) $activity['company_funded'], 2) }}
                </span>
                <a href="#drilldown-funding" class="mt-1 text-[10px] font-bold text-emerald-700 hover:underline inline-block">
                    View Advances &darr;
                </a>
            </div>

            <!-- Cash Returned -->
            <div class="rounded-2xl bg-white p-3.5 border border-slate-200/80 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Cash Returned</span>
                <span class="mt-1 block font-mono text-lg font-bold text-slate-900">
                    ₹{{ number_format((float) $activity['cash_returned'], 2) }}
                </span>
                <span class="mt-1 text-[10px] font-semibold text-slate-400 block">Returned to company</span>
            </div>

            <!-- Total Purchases -->
            <div class="rounded-2xl bg-white p-3.5 border border-slate-200/80 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Total Purchases</span>
                <span class="mt-1 block font-mono text-lg font-bold text-indigo-700">
                    ₹{{ number_format((float) $activity['total_purchases'], 2) }}
                </span>
                <a href="#drilldown-invoices" class="mt-1 text-[10px] font-bold text-indigo-700 hover:underline inline-block">
                    View Invoices ({{ $bills['total_count'] }}) &darr;
                </a>
            </div>

            <!-- Cash Purchases / Used -->
            <div class="rounded-2xl bg-white p-3.5 border border-slate-200/80 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Cash Purchases</span>
                <span class="mt-1 block font-mono text-lg font-bold text-emerald-700">
                    ₹{{ number_format((float) $activity['cash_purchases'], 2) }}
                </span>
                <span class="mt-1 text-[10px] font-semibold text-emerald-600 block">Advance utilized</span>
            </div>

            <!-- Vendor Credit Purchases -->
            <div class="rounded-2xl bg-white p-3.5 border border-amber-200 bg-amber-50/20 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-amber-900">Credit Purchases</span>
                <span class="mt-1 block font-mono text-lg font-bold text-amber-950">
                    ₹{{ number_format((float) $activity['credit_purchases'], 2) }}
                </span>
                <span class="mt-1 text-[10px] font-semibold text-amber-700 block">Due to vendors</span>
            </div>

            <!-- Procurement Expenses -->
            <div class="rounded-2xl bg-white p-3.5 border border-rose-200 bg-rose-50/20 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-rose-900">Purchaser Expenses</span>
                <span class="mt-1 block font-mono text-lg font-bold text-rose-700">
                    ₹{{ number_format((float) $activity['procurement_expenses'], 2) }}
                </span>
                <a href="#drilldown-expenses" class="mt-1 text-[10px] font-bold text-rose-700 hover:underline inline-block">
                    View Expenses &darr;
                </a>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION D: PURCHASER PHYSICAL CASH RECONCILIATION ────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-700 text-white text-xs font-black">D</span>
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-800">
                    Purchaser Physical Cash Reconciliation
                </h2>
            </div>
            <span class="text-xs font-mono font-black text-emerald-950 bg-emerald-100 border border-emerald-300 px-3 py-1 rounded-md">
                Cash in Hand: ₹{{ number_format((float) $cashDetails['cash_in_hand'], 2) }}
            </span>
        </div>

        <p class="text-xs font-semibold text-slate-500">
            Reconciles opening cash (₹0.00 for Sep), monthly funding received, cash spent on purchase invoices, cash returned to company, and procurement expenses.
        </p>

        <!-- Cash Step Breakdown -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
            <!-- 1. Opening Cash -->
            <div class="rounded-2xl bg-slate-50/80 p-3.5 border border-slate-200/80">
                <span class="block text-[10px] font-black uppercase text-slate-500">1. Opening Cash</span>
                <span class="mt-1 block font-mono text-base font-bold text-slate-900">
                    ₹{{ number_format((float) $cashDetails['opening_cash'], 2) }}
                </span>
                <span class="text-[10px] text-slate-400 font-semibold">Physical opening cash</span>
            </div>

            <!-- 2. Company Funded -->
            <div class="rounded-2xl bg-emerald-50/40 p-3.5 border border-emerald-200/80">
                <span class="block text-[10px] font-black uppercase text-emerald-800">2. Company Funded</span>
                <span class="mt-1 block font-mono text-base font-bold text-emerald-700">
                    +₹{{ number_format((float) $cashDetails['company_funded'], 2) }}
                </span>
                <span class="text-[10px] text-emerald-600 font-semibold">Advances given in month</span>
            </div>

            <!-- 3. Cash Used Purchases -->
            <div class="rounded-2xl bg-rose-50/40 p-3.5 border border-rose-200/80">
                <span class="block text-[10px] font-black uppercase text-rose-800">3. Cash Used for Bills</span>
                <span class="mt-1 block font-mono text-base font-bold text-rose-700">
                    -₹{{ number_format((float) $cashDetails['cash_used_purchases'], 2) }}
                </span>
                <span class="text-[10px] text-rose-600 font-semibold">Advance utilized for invoices</span>
            </div>

            <!-- 4. Cash Returned -->
            <div class="rounded-2xl bg-amber-50/40 p-3.5 border border-amber-200/80">
                <span class="block text-[10px] font-black uppercase text-amber-800">4. Cash Returned</span>
                <span class="mt-1 block font-mono text-base font-bold text-amber-700">
                    -₹{{ number_format((float) $cashDetails['cash_returned'], 2) }}
                </span>
                <span class="text-[10px] text-amber-600 font-semibold">Returned to company</span>
            </div>

            <!-- 5. Closing Purchaser Cash -->
            <div class="rounded-2xl bg-slate-900 text-white p-3.5 border border-slate-700 shadow-sm">
                <span class="block text-[10px] font-black uppercase text-slate-400">5. Cash in Hand</span>
                <span class="mt-1 block font-mono text-base font-black text-white">
                    = ₹{{ number_format((float) $cashDetails['cash_in_hand'], 2) }}
                </span>
                <span class="text-[10px] text-emerald-400 font-semibold">Consolidated cash position</span>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION E: PURCHASE BILLS & VENDOR DETAILS ───────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-indigo-700 text-white text-xs font-black">E</span>
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-800">
                    Purchase &amp; Bill Summary
                </h2>
            </div>
            <span class="text-[11px] font-bold text-slate-400">
                Total Bills: {{ $bills['total_count'] }} &bull; Value: ₹{{ number_format((float) $bills['total_amount'], 2) }}
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div class="rounded-2xl bg-slate-50 p-3 border border-slate-200/70">
                <span class="text-[10px] font-black uppercase text-slate-500 block">Cash Bills</span>
                <span class="font-mono font-bold text-sm text-slate-900 mt-1 block">
                    {{ $bills['cash_bills_count'] }} bills (₹{{ number_format((float) $bills['cash_bills_amount'], 2) }})
                </span>
            </div>

            <div class="rounded-2xl bg-amber-50/40 p-3 border border-amber-200/70">
                <span class="text-[10px] font-black uppercase text-amber-900 block">Credit Bills</span>
                <span class="font-mono font-bold text-sm text-amber-950 mt-1 block">
                    {{ $bills['credit_bills_count'] }} bills (₹{{ number_format((float) $bills['credit_bills_amount'], 2) }})
                </span>
                <span class="text-[10px] text-amber-700 font-semibold block mt-0.5">
                    Outstanding: ₹{{ number_format((float) $bills['credit_outstanding'], 2) }}
                </span>
            </div>

            <div class="rounded-2xl bg-emerald-50/40 p-3 border border-emerald-200/70">
                <span class="text-[10px] font-black uppercase text-emerald-900 block">Inventory Matched</span>
                <span class="font-mono font-bold text-sm text-emerald-950 mt-1 block">
                    {{ $bills['inventory_matched_count'] }} / {{ $bills['total_count'] }} matched
                </span>
                <span class="text-[10px] text-emerald-700 font-semibold block mt-0.5">
                    GRN verified
                </span>
            </div>

            <div class="rounded-2xl bg-slate-50 p-3 border border-slate-200/70">
                <span class="text-[10px] font-black uppercase text-slate-500 block">Cancelled / Reverted</span>
                <span class="font-mono font-bold text-sm text-slate-700 mt-1 block">
                    {{ $bills['cancelled_count'] }} bills (₹{{ number_format((float) $bills['cancelled_amount'], 2) }})
                </span>
                <span class="text-[10px] text-slate-400 font-semibold block mt-0.5">
                    Excluded from live totals
                </span>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION F: PENDING & UNRESOLVED ITEMS ─────────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    @if((int) $pending['pending_bills_count'] > 0 || (int) $pending['pending_inventory_count'] > 0 || (float) $pending['credit_outstanding'] > 0.0001)
        <div class="rounded-3xl border border-amber-200 bg-amber-50/40 p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-amber-200/80 pb-3">
                <div class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-amber-900 text-white text-xs font-black">F</span>
                    <h2 class="text-xs font-black uppercase tracking-wider text-amber-950">
                        Pending &amp; Unresolved Items
                    </h2>
                </div>
                <span class="text-xs font-bold text-amber-800">
                    Action required to achieve clean closing
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                <!-- Pending Invoices / Carts -->
                <div class="rounded-2xl bg-white p-3.5 border border-amber-200/80 shadow-2xs">
                    <span class="block text-[10px] font-black uppercase text-amber-900">Unbilled Carts</span>
                    <span class="mt-1 block font-mono text-base font-bold text-amber-950">
                        {{ $pending['pending_bills_count'] }} carts (₹{{ number_format((float) $pending['pending_bills_amount'], 2) }})
                    </span>
                    <a href="#drilldown-pending-carts" class="mt-1 text-[10px] font-bold text-amber-800 hover:underline inline-block">
                        View Unbilled Carts &darr;
                    </a>
                </div>

                <!-- Pending Inventory Matching -->
                <div class="rounded-2xl bg-white p-3.5 border border-amber-200/80 shadow-2xs">
                    <span class="block text-[10px] font-black uppercase text-amber-900">Pending Inventory Matching</span>
                    <span class="mt-1 block font-mono text-base font-bold text-amber-950">
                        {{ $pending['pending_inventory_count'] }} bills (₹{{ number_format((float) $pending['pending_inventory_amount'], 2) }})
                    </span>
                    <span class="text-[10px] text-amber-700 font-semibold block mt-0.5">Awaiting GRN link</span>
                </div>

                <!-- Unsettled Vendor Credit -->
                <div class="rounded-2xl bg-white p-3.5 border border-amber-200/80 shadow-2xs">
                    <span class="block text-[10px] font-black uppercase text-amber-900">Unsettled Vendor Credit</span>
                    <span class="mt-1 block font-mono text-base font-bold text-amber-950">
                        ₹{{ number_format((float) $pending['credit_outstanding'], 2) }}
                    </span>
                    <span class="text-[10px] text-amber-700 font-semibold block mt-0.5">Payable to suppliers</span>
                </div>
            </div>
        </div>
    @endif

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION G: CLOSING POSITION HERO CARD ─────────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl border-2 {{ $closing['direction'] === 'purchaser_holds_company_cash' ? 'border-emerald-400 bg-emerald-50/40' : ($closing['direction'] === 'company_owes_purchaser' ? 'border-amber-400 bg-amber-50/40' : 'border-slate-400 bg-slate-50/40') }} p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/80 pb-3">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-900 text-white text-xs font-black">G</span>
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-800">
                    Closing Cash in Hand — {{ Carbon\Carbon::parse($period['end_date'])->format('d M Y') }}
                </h2>
            </div>
            <span class="inline-flex items-center rounded-md px-3 py-1 text-xs font-black uppercase tracking-wider {{ $closing['direction'] === 'purchaser_holds_company_cash' ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : ($closing['direction'] === 'company_owes_purchaser' ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-slate-100 text-slate-900 border border-slate-300') }}">
                {{ $closing['direction_label'] }}
            </span>
        </div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 p-4 rounded-2xl bg-white border border-slate-200/80">
            <div>
                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-500 block">
                    Final Purchaser Physical Cash
                </span>
                <h3 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mt-1">
                    @if($closing['direction'] === 'purchaser_holds_company_cash')
                        Purchaser holds Company Cash
                    @elseif($closing['direction'] === 'company_owes_purchaser')
                        Company owes Purchaser
                    @else
                        Settled (Zero Cash Outstanding)
                    @endif
                </h3>
                <p class="text-xs font-semibold text-slate-500 mt-1">
                    Net physical cash balance in purchaser's hands at month close after advances, bill payments, returns, and expenses.
                </p>
            </div>

            <div class="text-left md:text-right">
                <span class="font-mono text-3xl sm:text-4xl font-black {{ $closing['direction'] === 'purchaser_holds_company_cash' ? 'text-emerald-900' : ($closing['direction'] === 'company_owes_purchaser' ? 'text-amber-900' : 'text-slate-900') }}">
                    ₹{{ number_format((float) $closing['cash_balance'], 2) }}
                </span>
                <span class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-400 mt-0.5">
                    Purchaser Cash in Hand
                </span>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION H: CARRY FORWARD TO NEXT MONTH ───────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl border border-slate-900 bg-slate-900 text-white p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-white text-slate-950 text-xs font-black">H</span>
                <h2 class="text-xs font-black uppercase tracking-wider text-white">
                    Carry Forward to {{ strtoupper($carry['next_month_label']) }}
                </h2>
            </div>
            <span class="text-xs font-bold text-emerald-400 font-mono">
                {{ $carry['next_month'] }}
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Next Month Opening Cash -->
            <div class="rounded-2xl bg-slate-800/90 p-4 border border-slate-700">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">
                    Next Month Opening Purchaser Cash
                </span>
                <div class="flex items-baseline gap-3 mt-1">
                    <span class="font-mono text-2xl font-black text-white">
                        ₹{{ number_format((float) $carry['opening_cash_balance'], 2) }}
                    </span>
                    <span class="inline-flex items-center rounded-md bg-slate-700 text-emerald-400 border border-slate-600 px-2 py-0.5 text-xs font-black uppercase">
                        {{ $carry['direction_label'] }}
                    </span>
                </div>
                <p class="text-[11px] font-semibold text-slate-400 mt-2">
                    Exact closing cash balance carried forward into {{ $carry['next_month_label'] }} opening balance.
                </p>
            </div>

            <!-- Carried Vendor Credit -->
            <div class="rounded-2xl bg-slate-800/90 p-4 border border-slate-700">
                <span class="block text-[10px] font-black uppercase tracking-wider text-amber-300">
                    Carried Vendor Credit Position
                </span>
                <div class="flex items-baseline gap-3 mt-1">
                    <span class="font-mono text-2xl font-black text-amber-300">
                        ₹{{ number_format((float) $carry['vendor_credit_outstanding'], 2) }}
                    </span>
                    <span class="inline-flex items-center rounded-md bg-amber-950/80 text-amber-300 border border-amber-800 px-2 py-0.5 text-xs font-black uppercase">
                        Vendor Payables
                    </span>
                </div>
                <p class="text-[11px] font-semibold text-slate-400 mt-2">
                    Vendor purchasing payables carried forward for settlement in subsequent periods.
                </p>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- READ-ONLY DRILLDOWNS (TRACEABILITY) ──────────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="space-y-6 pt-4">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 border-b border-slate-200 pb-2 flex items-center gap-2">
            <i data-lucide="layers" class="h-4 w-4 text-emerald-600"></i>
            <span>Read-Only Drilldown Details</span>
        </h2>

        <!-- 1. Cash & Funding Movements -->
        <div id="drilldown-funding" class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-3">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                Purchaser Cash &amp; Funding Movements ({{ count($drilldowns['funding']) }})
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                        <tr>
                            <th class="px-3.5 py-2.5">Date</th>
                            <th class="px-3.5 py-2.5">Type</th>
                            <th class="px-3.5 py-2.5">Account / Source</th>
                            <th class="px-3.5 py-2.5">Reference / Notes</th>
                            <th class="px-3.5 py-2.5 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                        @forelse($drilldowns['funding'] as $creditRow)
                            <tr>
                                <td class="px-3.5 py-2 text-slate-500 whitespace-nowrap">{{ $creditRow['business_date'] ? Carbon\Carbon::parse($creditRow['business_date'])->format('d M Y') : '—' }}</td>
                                <td class="px-3.5 py-2">
                                    <span class="rounded px-2 py-0.5 text-[10px] font-bold {{ $creditRow['type'] === 'in' ? 'bg-emerald-100 text-emerald-900' : ($creditRow['purchase_invoice_id'] ? 'bg-indigo-100 text-indigo-900' : 'bg-amber-100 text-amber-900') }}">
                                        {{ $creditRow['type'] === 'in' ? 'Company Funded' : ($creditRow['purchase_invoice_id'] ? 'Bill Payment' : 'Cash Returned') }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-2 text-slate-600">{{ $creditRow['company_account_name'] ?: ($creditRow['payment_source'] ?: '—') }}</td>
                                <td class="px-3.5 py-2 text-slate-700">
                                    {{ $creditRow['reference'] ?: ($creditRow['invoice_number'] ?: ($creditRow['description'] ?: '—')) }}
                                </td>
                                <td class="px-3.5 py-2 text-right font-mono font-bold {{ $creditRow['type'] === 'in' ? 'text-emerald-700' : 'text-slate-900' }}">
                                    {{ $creditRow['type'] === 'in' ? '+' : '-' }}₹{{ number_format((float) $creditRow['amount'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-4 text-center text-slate-400">No funding movements in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. Purchase Bills -->
        <div id="drilldown-invoices" class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-3">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                Delivered Purchase Bills ({{ count($drilldowns['invoices']) }})
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                        <tr>
                            <th class="px-3.5 py-2.5">Date</th>
                            <th class="px-3.5 py-2.5">Invoice #</th>
                            <th class="px-3.5 py-2.5">Vendor</th>
                            <th class="px-3.5 py-2.5">Payment Method</th>
                            <th class="px-3.5 py-2.5">GRN #</th>
                            <th class="px-3.5 py-2.5 text-right">Net Amount</th>
                            <th class="px-3.5 py-2.5 text-right">Paid</th>
                            <th class="px-3.5 py-2.5 text-right">Outstanding</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                        @forelse($drilldowns['invoices'] as $inv)
                            @php
                                $net = max(0.0, (float) $inv['amount'] - (float) $inv['discount_amount']);
                                $paid = (float) $inv['paid_amount'];
                                $due = max(0.0, $net - $paid);
                            @endphp
                            <tr>
                                <td class="px-3.5 py-2 text-slate-500 whitespace-nowrap">{{ $inv['business_date'] ? Carbon\Carbon::parse($inv['business_date'])->format('d M Y') : '—' }}</td>
                                <td class="px-3.5 py-2 font-mono font-bold text-slate-900">{{ $inv['invoice_number'] ?: '—' }}</td>
                                <td class="px-3.5 py-2 text-slate-800">{{ $inv['supplier_name'] ?: '—' }}</td>
                                <td class="px-3.5 py-2">
                                    <span class="rounded px-2 py-0.5 text-[10px] font-bold {{ $inv['payment_class'] === 'cash' ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-900' }}">
                                        {{ ucfirst($inv['payment_class']) }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-2 font-mono text-slate-600">{{ $inv['grn_number'] ?: '—' }}</td>
                                <td class="px-3.5 py-2 text-right font-mono font-bold text-slate-900">₹{{ number_format($net, 2) }}</td>
                                <td class="px-3.5 py-2 text-right font-mono text-slate-600">₹{{ number_format($paid, 2) }}</td>
                                <td class="px-3.5 py-2 text-right font-mono font-bold {{ $due > 0 ? 'text-amber-700' : 'text-emerald-700' }}">₹{{ number_format($due, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-4 text-center text-slate-400">No purchase bills in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. Procurement Expenses & Pending Carts Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Procurement Expenses -->
            <div id="drilldown-expenses" class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-3">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                    Purchaser Expenses ({{ count($drilldowns['expenses']) }})
                </h3>
                <div class="overflow-y-auto max-h-60">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Category</th>
                                <th class="px-3 py-2">Note</th>
                                <th class="px-3 py-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                            @forelse($drilldowns['expenses'] as $e)
                                <tr>
                                    <td class="px-3 py-1.5 text-slate-500 whitespace-nowrap">{{ $e['date'] }}</td>
                                    <td class="px-3 py-1.5 font-bold text-slate-800">{{ $e['category'] }}</td>
                                    <td class="px-3 py-1.5 text-slate-500 truncate max-w-[150px]">{{ $e['note'] }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono font-bold text-rose-700">₹{{ number_format((float) $e['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="p-3 text-center text-slate-400">No expenses recorded in period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Unbilled Carts / Pending Bills -->
            <div id="drilldown-pending-carts" class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-3">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                    Unbilled Carts ({{ count($drilldowns['pending_carts']) }})
                </h3>
                <div class="overflow-y-auto max-h-60">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Cart #</th>
                                <th class="px-3 py-2">Vendor</th>
                                <th class="px-3 py-2 text-right">Estimated Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                            @forelse($drilldowns['pending_carts'] as $cart)
                                <tr>
                                    <td class="px-3 py-1.5 text-slate-500 whitespace-nowrap">{{ $cart['date'] }}</td>
                                    <td class="px-3 py-1.5 font-mono font-bold text-slate-800">{{ $cart['cart_number'] }}</td>
                                    <td class="px-3 py-1.5 text-slate-600">{{ $cart['supplier_name'] }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono font-bold text-amber-700">₹{{ number_format((float) $cart['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="p-3 text-center text-slate-400">No unbilled carts in period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 4. Cancelled / Reverted Bills (if any) -->
        @if(count($drilldowns['cancelled_invoices']) > 0)
            <div class="rounded-3xl border border-rose-200 bg-rose-50/30 p-5 shadow-xs space-y-3">
                <h3 class="text-xs font-black uppercase tracking-wider text-rose-900">
                    Cancelled / Reverted Bills ({{ count($drilldowns['cancelled_invoices']) }})
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-rose-100/50 text-[10px] font-black uppercase text-rose-800">
                            <tr>
                                <th class="px-3.5 py-2.5">Date</th>
                                <th class="px-3.5 py-2.5">Invoice #</th>
                                <th class="px-3.5 py-2.5">Vendor</th>
                                <th class="px-3.5 py-2.5 text-right">Amount</th>
                                <th class="px-3.5 py-2.5 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-rose-100 font-semibold text-slate-700">
                            @foreach($drilldowns['cancelled_invoices'] as $cinv)
                                <tr>
                                    <td class="px-3.5 py-2 text-slate-500 whitespace-nowrap">{{ $cinv['business_date'] ? Carbon\Carbon::parse($cinv['business_date'])->format('d M Y') : '—' }}</td>
                                    <td class="px-3.5 py-2 font-mono text-slate-700 line-through">{{ $cinv['invoice_number'] ?: '—' }}</td>
                                    <td class="px-3.5 py-2 text-slate-600">{{ $cinv['supplier_name'] ?: '—' }}</td>
                                    <td class="px-3.5 py-2 text-right font-mono text-slate-500 line-through">₹{{ number_format(max(0.0, (float) $cinv['amount'] - (float) $cinv['discount_amount']), 2) }}</td>
                                    <td class="px-3.5 py-2 text-center">
                                        <span class="rounded bg-rose-100 text-rose-800 px-2 py-0.5 text-[10px] font-black uppercase">Cancelled</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
