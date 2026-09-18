@extends('admin.cashbook.layouts.app')

@section('title', ($detail['shop']['name'] ?: 'Shop') . ' — Monthly Closing Summary (' . $detail['period']['label'] . ')')

@section('header_title')
    <i data-lucide="calendar-check" class="w-5 h-5 text-emerald-600"></i> {{ $detail['shop']['name'] }} Closing Summary
@endsection

@section('header_subtitle')
    Audited closing balances, who-owes-who position, available credit, and next month carry-forward.
@endsection

@section('content')
@php
    $shop = $detail['shop'];
    $period = $detail['period'];
    $opening = $detail['opening'];
    $activity = $detail['activity'];
    $closing = $detail['closing'];
    $credit = $detail['credit'];
    $carry = $detail['carry_forward'];
    $status = $detail['status'];
    $drilldowns = $detail['drilldowns'];

    $backToAllShopsUrl = route('admin.cashbook.monthly-closing-summary.index', ['month' => $month]);
    $openOperationalWorkspaceUrl = route('admin.cashbook.shop.show', ['shop' => $shop['slug']]);
@endphp

<div class="mx-auto max-w-[96rem] space-y-6 pb-16">

    <!-- Top Navigation & Controls Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-3xl bg-white p-5 border border-slate-200/80 shadow-xs">
        <div class="flex items-center gap-3">
            <a href="{{ $backToAllShopsUrl }}"
               class="inline-flex h-10 items-center gap-2 rounded-xl bg-slate-100 hover:bg-slate-200 px-3.5 text-xs font-bold text-slate-700 transition"
               title="Back to All Shops Summary">
                <i data-lucide="arrow-left" class="h-4 w-4 text-slate-600"></i>
                <span>All Shops</span>
            </a>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-lg font-black tracking-tight text-slate-900 uppercase">
                        {{ $shop['name'] }} — CLOSING SUMMARY
                    </h1>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-black text-slate-600 font-mono">
                        {{ $shop['code'] }}
                    </span>
                    @if($shop['client_name'])
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-500">
                            {{ $shop['client_name'] }}
                        </span>
                    @endif
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
                <label for="shop-month-select" class="text-xs font-bold text-slate-500 uppercase tracking-wider">Month:</label>
                <select
                    id="shop-month-select"
                    onchange="window.location.href='{{ route('admin.cashbook.monthly-closing-summary.show', $shop['slug']) }}?month=' + this.value"
                    class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-900 shadow-xs focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 cursor-pointer min-w-[150px]"
                >
                    @foreach($availableMonths as $m)
                        <option value="{{ $m['value'] }}" {{ $m['value'] === $month ? 'selected' : '' }}>
                            {{ $m['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <a href="{{ $openOperationalWorkspaceUrl }}"
               class="inline-flex h-10 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-xs"
               title="Open Mutation Workspace">
                <i data-lucide="store" class="h-4 w-4 text-slate-500"></i>
                <span>Shop Ledger Workspace</span>
            </a>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION A: OPENING POSITION ──────────────────────────────────────── -->
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
                Brought forward from previous month closing snapshot
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Opening Physical Position -->
            <div class="rounded-2xl bg-slate-50/80 p-4 border border-slate-200/70 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">
                    Opening Physical Position
                </span>
                <div class="flex items-baseline gap-3 mt-1">
                    <span class="font-mono text-2xl font-black text-slate-900">
                        ₹{{ number_format((float) $opening['physical_position'], 2) }}
                    </span>
                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-black uppercase tracking-wider {{ $opening['direction'] === 'shop_owes_company' ? 'bg-amber-100 text-amber-900 border border-amber-300' : ($opening['direction'] === 'company_owes_shop' ? 'bg-indigo-100 text-indigo-900 border border-indigo-300' : 'bg-slate-200 text-slate-700') }}">
                        {{ $opening['direction_label'] }}
                    </span>
                </div>
                <p class="text-[11px] font-semibold text-slate-500 mt-2">
                    Physical unremitted cash held at shop at the beginning of {{ $period['label'] }}.
                </p>
            </div>

            <!-- Previous Available Credit -->
            <div class="rounded-2xl bg-amber-50/40 p-4 border border-amber-200/80 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-amber-900">
                    Previous Available Advance Credit
                </span>
                <div class="flex items-baseline gap-3 mt-1">
                    <span class="font-mono text-2xl font-black text-amber-950">
                        ₹{{ number_format((float) $opening['previous_available_credit'], 2) }}
                    </span>
                    <span class="inline-flex items-center rounded-md bg-amber-100 text-amber-900 border border-amber-300 px-2 py-0.5 text-xs font-black uppercase tracking-wider">
                        Company-Held Credit
                    </span>
                </div>
                <p class="text-[11px] font-semibold text-amber-800/80 mt-2">
                    Unallocated surplus payments held by Company from prior months eligible to absorb current bills.
                </p>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION B: MONTH ACTIVITY ────────────────────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-900 text-white text-xs font-black">B</span>
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-800">
                    {{ $period['label'] }} Real Activity
                </h2>
            </div>
            <span class="text-[11px] font-bold text-slate-400">
                Audited monthly movements &amp; allocations
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
            <!-- Settlement Due -->
            <div class="rounded-2xl bg-white p-3.5 border border-slate-200/80 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Company Settlement Due</span>
                <span class="mt-1 block font-mono text-lg font-bold text-slate-900">
                    ₹{{ number_format((float) $activity['settlement_due'], 2) }}
                </span>
                <a href="#drilldown-settlement-due" class="mt-1 text-[10px] font-bold text-emerald-700 hover:underline inline-block">
                    View Formula Breakdown &darr;
                </a>
            </div>

            <!-- Company Received -->
            <div class="rounded-2xl bg-white p-3.5 border border-slate-200/80 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Company Received</span>
                <span class="mt-1 block font-mono text-lg font-bold text-emerald-700">
                    ₹{{ number_format((float) $activity['company_received'], 2) }}
                </span>
                <a href="#drilldown-payments" class="mt-1 text-[10px] font-bold text-emerald-700 hover:underline inline-block">
                    View Received Payments &darr;
                </a>
            </div>

            <!-- GL Bills -->
            <div class="rounded-2xl bg-white p-3.5 border border-slate-200/80 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">GL Invoices Delivered</span>
                <span class="mt-1 block font-mono text-lg font-bold text-slate-900">
                    ₹{{ number_format((float) $activity['gl_bills'], 2) }}
                </span>
                <a href="#drilldown-gl-bills" class="mt-1 text-[10px] font-bold text-emerald-700 hover:underline inline-block">
                    View Delivered Invoices &darr;
                </a>
            </div>

            <!-- Company Paid Expenses -->
            <div class="rounded-2xl bg-white p-3.5 border border-slate-200/80 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Company-Paid Expenses</span>
                <span class="mt-1 block font-mono text-lg font-bold text-slate-900">
                    ₹{{ number_format((float) $activity['company_paid_expenses'], 2) }}
                </span>
                <a href="#drilldown-company-expenses" class="mt-1 text-[10px] font-bold text-emerald-700 hover:underline inline-block">
                    View Company Expenses &darr;
                </a>
            </div>

            <!-- Shop Local Expenses -->
            <div class="rounded-2xl bg-white p-3.5 border border-slate-200/80 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Shop Local Expenses</span>
                <span class="mt-1 block font-mono text-lg font-bold text-rose-700">
                    ₹{{ number_format((float) $activity['shop_local_expenses'], 2) }}
                </span>
                <span class="mt-1 text-[10px] font-semibold text-slate-400 block">Paid from shop sales</span>
            </div>

            <!-- Allocated This Month -->
            <div class="rounded-2xl bg-white p-3.5 border border-indigo-200 bg-indigo-50/30 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-indigo-900">Total Allocated This Month</span>
                <span class="mt-1 block font-mono text-lg font-bold text-indigo-950">
                    ₹{{ number_format((float) $activity['allocated_this_month'], 2) }}
                </span>
                <a href="#drilldown-allocations" class="mt-1 text-[10px] font-bold text-indigo-700 hover:underline inline-block">
                    View Allocations &darr;
                </a>
            </div>

            <!-- Previous Credit Utilized -->
            <div class="rounded-2xl bg-white p-3.5 border border-amber-200 bg-amber-50/30 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-amber-900">Prior Credit Utilized</span>
                <span class="mt-1 block font-mono text-lg font-bold text-amber-950">
                    ₹{{ number_format((float) $activity['previous_credit_utilized'], 2) }}
                </span>
                <span class="mt-1 text-[10px] font-semibold text-amber-700 block">Absorbed from prior credit</span>
            </div>

            <!-- New Unallocated Credit -->
            <div class="rounded-2xl bg-white p-3.5 border border-amber-200 bg-amber-50/30 shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider text-amber-900">New Credit Created</span>
                <span class="mt-1 block font-mono text-lg font-bold text-amber-950">
                    ₹{{ number_format((float) $activity['new_unallocated_credit'], 2) }}
                </span>
                <span class="mt-1 text-[10px] font-semibold text-amber-700 block">From current receipts</span>
            </div>

            <!-- Pending Verification -->
            <div class="rounded-2xl bg-white p-3.5 border {{ (float) $activity['pending_verification'] > 0 ? 'border-sky-300 bg-sky-50/50' : 'border-slate-200/80' }} shadow-2xs">
                <span class="block text-[10px] font-black uppercase tracking-wider {{ (float) $activity['pending_verification'] > 0 ? 'text-sky-900' : 'text-slate-500' }}">Pending Verification</span>
                <span class="mt-1 block font-mono text-lg font-bold {{ (float) $activity['pending_verification'] > 0 ? 'text-sky-950' : 'text-slate-700' }}">
                    ₹{{ number_format((float) $activity['pending_verification'], 2) }}
                </span>
                <span class="mt-1 text-[10px] font-semibold text-slate-400 block">Awaiting bank reconciliation</span>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION C: CLOSING POSITION (WHO OWES WHO) ───────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl border-2 {{ $closing['direction'] === 'shop_owes_company' ? 'border-amber-400 bg-amber-50/40' : ($closing['direction'] === 'company_owes_shop' ? 'border-indigo-400 bg-indigo-50/40' : 'border-emerald-400 bg-emerald-50/40') }} p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200/80 pb-3">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-900 text-white text-xs font-black">C</span>
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-800">
                    Closing Physical Relationship — {{ Carbon\Carbon::parse($period['end_date'])->format('d M Y') }}
                </h2>
            </div>
            <span class="inline-flex items-center rounded-md px-3 py-1 text-xs font-black uppercase tracking-wider {{ $closing['direction'] === 'shop_owes_company' ? 'bg-amber-100 text-amber-900 border border-amber-300' : ($closing['direction'] === 'company_owes_shop' ? 'bg-indigo-100 text-indigo-900 border border-indigo-300' : 'bg-emerald-100 text-emerald-900 border border-emerald-300') }}">
                {{ $closing['direction_label'] }}
            </span>
        </div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 p-4 rounded-2xl bg-white border border-slate-200/80">
            <div>
                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-500 block">
                    Final Physical Cash Position
                </span>
                <h3 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mt-1">
                    @if($closing['direction'] === 'shop_owes_company')
                        Shop owes Company
                    @elseif($closing['direction'] === 'company_owes_shop')
                        Company owes Shop
                    @else
                        Settled (Zero Outstanding)
                    @endif
                </h3>
                <p class="text-xs font-semibold text-slate-500 mt-1">
                    Net physical cash held at the shop at month close after local expenses and remittances.
                </p>
            </div>

            <div class="text-left md:text-right">
                <span class="font-mono text-3xl sm:text-4xl font-black {{ $closing['direction'] === 'shop_owes_company' ? 'text-amber-900' : ($closing['direction'] === 'company_owes_shop' ? 'text-indigo-900' : 'text-emerald-900') }}">
                    ₹{{ number_format((float) $closing['physical_position'], 2) }}
                </span>
                <span class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-400 mt-0.5">
                    Physical Cash in Hand
                </span>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION D: AVAILABLE CREDIT ──────────────────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl border border-amber-200/80 bg-amber-50/30 p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-amber-200/80 pb-3">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-amber-900 text-white text-xs font-black">D</span>
                <h2 class="text-xs font-black uppercase tracking-wider text-amber-950">
                    Available Company-Held Advance Credit
                </h2>
            </div>
            <span class="text-xs font-mono font-black text-amber-950 bg-amber-100 border border-amber-300 px-3 py-1 rounded-md">
                Closing Credit: ₹{{ number_format((float) $credit['closing_available_credit'], 2) }}
            </span>
        </div>

        <p class="text-xs font-semibold text-amber-900/80">
            This balance represents advance payments received by Company that are not yet allocated to any bills. It is maintained independently from the physical cash in hand.
        </p>

        <!-- Credit Step Breakdown -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
            <div class="rounded-2xl bg-white p-3.5 border border-amber-200/80">
                <span class="block text-[10px] font-black uppercase text-slate-500">1. Opening Credit</span>
                <span class="mt-1 block font-mono text-base font-bold text-slate-900">
                    ₹{{ number_format((float) $credit['opening_available_credit'], 2) }}
                </span>
                <span class="text-[10px] text-slate-400 font-semibold">From prior months</span>
            </div>

            <div class="rounded-2xl bg-white p-3.5 border border-amber-200/80">
                <span class="block text-[10px] font-black uppercase text-amber-800">2. Used This Month</span>
                <span class="mt-1 block font-mono text-base font-bold text-rose-700">
                    -₹{{ number_format((float) $credit['used_this_month'], 2) }}
                </span>
                <span class="text-[10px] text-slate-400 font-semibold">Applied to current bills</span>
            </div>

            <div class="rounded-2xl bg-white p-3.5 border border-amber-200/80">
                <span class="block text-[10px] font-black uppercase text-emerald-800">3. New Credit Created</span>
                <span class="mt-1 block font-mono text-base font-bold text-emerald-700">
                    +₹{{ number_format((float) $credit['new_credit_created'], 2) }}
                </span>
                <span class="text-[10px] text-slate-400 font-semibold">From unallocated receipts</span>
            </div>

            <div class="rounded-2xl bg-amber-100/70 p-3.5 border border-amber-300">
                <span class="block text-[10px] font-black uppercase text-amber-950">4. Closing Available Credit</span>
                <span class="mt-1 block font-mono text-base font-black text-amber-950">
                    = ₹{{ number_format((float) $credit['closing_available_credit'], 2) }}
                </span>
                <span class="text-[10px] text-amber-800 font-semibold">Carries to next month</span>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION E: CARRY FORWARD TO NEXT MONTH ───────────────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl border border-slate-900 bg-slate-900 text-white p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-white text-slate-950 text-xs font-black">E</span>
                <h2 class="text-xs font-black uppercase tracking-wider text-white">
                    Carry Forward to {{ strtoupper($carry['next_month_label']) }}
                </h2>
            </div>
            <span class="text-xs font-bold text-emerald-400 font-mono">
                {{ $carry['next_month'] }}
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Next Month Opening Physical -->
            <div class="rounded-2xl bg-slate-800/90 p-4 border border-slate-700">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">
                    Next Month Opening Physical Position
                </span>
                <div class="flex items-baseline gap-3 mt-1">
                    <span class="font-mono text-2xl font-black text-white">
                        ₹{{ number_format((float) $carry['opening_physical_position'], 2) }}
                    </span>
                    <span class="inline-flex items-center rounded-md bg-slate-700 text-emerald-400 border border-slate-600 px-2 py-0.5 text-xs font-black uppercase">
                        {{ $carry['direction_label'] }}
                    </span>
                </div>
                <p class="text-[11px] font-semibold text-slate-400 mt-2">
                    Exact closing physical position carried into {{ $carry['next_month_label'] }} opening balance.
                </p>
            </div>

            <!-- Next Month Opening Credit -->
            <div class="rounded-2xl bg-slate-800/90 p-4 border border-slate-700">
                <span class="block text-[10px] font-black uppercase tracking-wider text-amber-300">
                    Available Previous Credit Carried
                </span>
                <div class="flex items-baseline gap-3 mt-1">
                    <span class="font-mono text-2xl font-black text-amber-300">
                        ₹{{ number_format((float) $carry['available_previous_credit'], 2) }}
                    </span>
                    <span class="inline-flex items-center rounded-md bg-amber-950/80 text-amber-300 border border-amber-800 px-2 py-0.5 text-xs font-black uppercase">
                        Eligible for {{ $carry['next_month_label'] }}
                    </span>
                </div>
                <p class="text-[11px] font-semibold text-slate-400 mt-2">
                    Advance credit available to settle {{ $carry['next_month_label'] }} obligations.
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

        <!-- 1. Settlement Due Formula Items -->
        <div id="drilldown-settlement-due" class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-3">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                Settlement Due Formula Breakdown
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                        <tr>
                            <th class="px-3.5 py-2.5">Component</th>
                            <th class="px-3.5 py-2.5">Category</th>
                            <th class="px-3.5 py-2.5 text-center">Role</th>
                            <th class="px-3.5 py-2.5 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                        @forelse($drilldowns['settlement_due_items'] as $item)
                            <tr>
                                <td class="px-3.5 py-2 font-bold text-slate-900">{{ $item['name'] ?? 'Entry' }}</td>
                                <td class="px-3.5 py-2 capitalize text-slate-500">{{ $item['category'] ?? 'general' }}</td>
                                <td class="px-3.5 py-2 text-center font-mono font-bold {{ ($item['role'] ?? '') === 'subtract' ? 'text-rose-600' : 'text-emerald-600' }}">
                                    {{ ($item['role'] ?? '') === 'subtract' ? 'Subtract (-)' : 'Add (+)' }}
                                </td>
                                <td class="px-3.5 py-2 text-right font-mono font-bold text-slate-900">
                                    ₹{{ number_format((float) ($item['amount'] ?? 0), 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-4 text-center text-slate-400">No formula items recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. Payments Received -->
        <div id="drilldown-payments" class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-3">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                Payments Received in Period
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                        <tr>
                            <th class="px-3.5 py-2.5">Date</th>
                            <th class="px-3.5 py-2.5">Reference</th>
                            <th class="px-3.5 py-2.5">Method</th>
                            <th class="px-3.5 py-2.5">Account</th>
                            <th class="px-3.5 py-2.5 text-right">Amount</th>
                            <th class="px-3.5 py-2.5 text-right">Allocated</th>
                            <th class="px-3.5 py-2.5 text-right">Unallocated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                        @forelse($drilldowns['payments'] as $p)
                            <tr>
                                <td class="px-3.5 py-2 text-slate-500 whitespace-nowrap">{{ $p['date'] }}</td>
                                <td class="px-3.5 py-2 font-mono font-bold text-slate-900">{{ $p['reference'] }}</td>
                                <td class="px-3.5 py-2 text-slate-700">{{ $p['method'] }}</td>
                                <td class="px-3.5 py-2 text-slate-500">{{ $p['account'] }}</td>
                                <td class="px-3.5 py-2 text-right font-mono font-bold text-emerald-700">₹{{ number_format((float) $p['amount'], 2) }}</td>
                                <td class="px-3.5 py-2 text-right font-mono text-slate-600">₹{{ number_format((float) $p['allocated'], 2) }}</td>
                                <td class="px-3.5 py-2 text-right font-mono font-bold text-amber-700">₹{{ number_format((float) $p['unallocated'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-4 text-center text-slate-400">No payment records in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. Allocations Breakdown -->
        <div id="drilldown-allocations" class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-3">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                Allocations Applied to Obligations
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                        <tr>
                            <th class="px-3.5 py-2.5">Obligation Date</th>
                            <th class="px-3.5 py-2.5">Obligation Item</th>
                            <th class="px-3.5 py-2.5">Payment Reference</th>
                            <th class="px-3.5 py-2.5">Payment Date</th>
                            <th class="px-3.5 py-2.5">Payment Source</th>
                            <th class="px-3.5 py-2.5 text-right">Allocated Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                        @forelse($drilldowns['allocations'] as $a)
                            <tr>
                                <td class="px-3.5 py-2 text-slate-500 whitespace-nowrap">{{ $a['target_date'] }}</td>
                                <td class="px-3.5 py-2 font-bold text-slate-900">{{ $a['target_category'] }}</td>
                                <td class="px-3.5 py-2 font-mono text-slate-600">{{ $a['payment_ref'] }}</td>
                                <td class="px-3.5 py-2 text-slate-500 whitespace-nowrap">{{ $a['payment_date'] }}</td>
                                <td class="px-3.5 py-2">
                                    <span class="rounded px-2 py-0.5 text-[10px] font-bold {{ $a['source_type'] === 'Previous Credit' ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900' }}">
                                        {{ $a['source_type'] }}
                                    </span>
                                </td>
                                <td class="px-3.5 py-2 text-right font-mono font-bold text-slate-900">₹{{ number_format((float) $a['amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-4 text-center text-slate-400">No allocation records for this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. GL Bills & Company Expenses -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div id="drilldown-gl-bills" class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-3">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                    Delivered GL Bills ({{ count($drilldowns['gl_bills']) }})
                </h3>
                <div class="overflow-y-auto max-h-60">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Description</th>
                                <th class="px-3 py-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                            @forelse($drilldowns['gl_bills'] as $b)
                                <tr>
                                    <td class="px-3 py-1.5 text-slate-500 whitespace-nowrap">{{ $b['date'] }}</td>
                                    <td class="px-3 py-1.5 text-slate-800 truncate max-w-[200px]">{{ $b['description'] }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono font-bold text-slate-900">₹{{ number_format((float) $b['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="p-3 text-center text-slate-400">No GL bills in period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="drilldown-company-expenses" class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-3">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                    Company-Paid Expenses ({{ count($drilldowns['company_expenses']) }})
                </h3>
                <div class="overflow-y-auto max-h-60">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Category</th>
                                <th class="px-3 py-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                            @forelse($drilldowns['company_expenses'] as $ce)
                                <tr>
                                    <td class="px-3 py-1.5 text-slate-500 whitespace-nowrap">{{ $ce['date'] }}</td>
                                    <td class="px-3 py-1.5 text-slate-800 font-bold">{{ $ce['category'] }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono font-bold text-slate-900">₹{{ number_format((float) $ce['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="p-3 text-center text-slate-400">No company expenses in period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
