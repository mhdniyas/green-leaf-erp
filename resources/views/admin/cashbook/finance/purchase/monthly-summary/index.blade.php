@extends('admin.cashbook.layouts.app')

@section('title', 'Purchaser Monthly Summary — ' . $summary['formatted_month'])

@section('header_title')
    <i data-lucide="calendar-check" class="w-5 h-5 text-emerald-600"></i> Purchaser Monthly Summary
@endsection

@section('header_subtitle')
    Consolidated end-of-month purchaser physical cash, vendor purchasing credit, company funding, purchases, expenses, and carry-forward status.
@endsection

@section('content')
<div x-data="{
    creditModalOpen: false,
    activePurchaser: null,
    showInvoicesForSupplier: null,
    openCreditModal(purchaser) {
        this.activePurchaser = purchaser;
        this.showInvoicesForSupplier = null;
        this.creditModalOpen = true;
    }
}" class="mx-auto max-w-[96rem] space-y-6 pb-16">

    <!-- Top Controls & Month Filter Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-3xl bg-white p-5 border border-slate-200/80 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200/80 shadow-xs">
                <i data-lucide="calendar" class="h-5 w-5"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-black tracking-tight text-slate-900">
                        Purchaser Monthly Summary
                    </h1>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-black text-slate-600 font-mono">
                        {{ $summary['formatted_month'] }}
                    </span>
                    <span class="rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider">
                        Read Only
                    </span>
                </div>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">
                    Select a month to inspect purchaser physical cash, vendor purchasing credit pending, company funding, and cash in hand.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label for="month-select" class="text-xs font-bold text-slate-500 uppercase tracking-wider">Month:</label>
                <select
                    id="month-select"
                    onchange="window.location.href='{{ route('admin.cashbook.finance.purchase.monthly-summary.index') }}?month=' + this.value"
                    class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-900 shadow-xs focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 cursor-pointer min-w-[160px]"
                >
                    @foreach($availableMonths as $m)
                        <option value="{{ $m['value'] }}" {{ $m['value'] === $month ? 'selected' : '' }}>
                            {{ $m['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <a href="{{ route('admin.cashbook.finance.purchase.purchasers') }}" class="inline-flex h-10 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-xs">
                <i data-lucide="users" class="h-4 w-4 text-slate-500"></i>
                <span>Purchasers Workspace</span>
            </a>
        </div>
    </div>

    <!-- 6 Consolidated Metric Cards -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <!-- Opening Cash -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Opening Cash</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-slate-900">
                ₹{{ number_format(abs((float) ($summary['grand_totals']['opening_cash_net'] ?? 0)), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-emerald-600">
                {{ (float) ($summary['grand_totals']['opening_cash_net'] ?? 0) == 0 ? 'Settled (Zero Opening)' : 'Continuous Advance' }}
            </span>
        </div>

        <!-- Vendor Credit Pending -->
        <div class="rounded-2xl border border-amber-200/80 bg-amber-50/40 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-amber-900">Credit Pending</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-amber-950">
                ₹{{ number_format((float) ($summary['grand_totals']['credit_pending'] ?? $summary['grand_totals']['vendor_credit_outstanding'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-amber-700">Vendor purchasing obligations</span>
        </div>

        <!-- Total Company Funded -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Company Funded</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-slate-900">
                ₹{{ number_format((float) ($summary['grand_totals']['company_funded'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">Cash advances given</span>
        </div>

        <!-- Total Purchases -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Total Purchases</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-indigo-700">
                ₹{{ number_format((float) ($summary['grand_totals']['total_purchases'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-indigo-600">Cash + Credit Bills</span>
        </div>

        <!-- Cash Used -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Cash Used</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-emerald-700">
                ₹{{ number_format((float) ($summary['grand_totals']['cash_used'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-emerald-600">Physical cash spent</span>
        </div>

        <!-- Cash in Hand -->
        <div class="rounded-2xl border border-slate-300 bg-slate-900 text-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-300">Cash in Hand</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-black text-white">
                ₹{{ number_format(abs((float) ($summary['grand_totals']['cash_in_hand_net'] ?? $summary['grand_totals']['closing_cash_net'] ?? 0)), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-bold text-emerald-400">
                {{ (float) ($summary['grand_totals']['cash_in_hand_net'] ?? $summary['grand_totals']['closing_cash_net'] ?? 0) >= 0 ? 'Purchaser holds Cash' : 'Company owes Purchaser' }}
            </span>
        </div>
    </div>

    <!-- All Purchasers Closing Matrix Table Container -->
    <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/70 px-6 py-4">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <i data-lucide="users" class="h-4 w-4 text-emerald-600"></i>
                    <span>Purchaser Closing Matrix</span>
                </h2>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">
                    Clear separation between Purchaser Physical Cash (Opening &amp; Cash in Hand) and Vendor Purchasing Credit Pending.
                </p>
            </div>
            <span class="rounded-full bg-slate-200/70 px-3 py-1 text-xs font-bold text-slate-700">
                {{ count($summary['purchasers']) }} Purchasers
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-100/70 text-[10px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200/80">
                    <tr>
                        <th class="px-5 py-3.5">Purchaser</th>
                        <th class="px-4 py-3.5 text-right">Opening Cash</th>
                        <th class="px-4 py-3.5 text-right">Credit Pending</th>
                        <th class="px-4 py-3.5 text-right">Company Funded</th>
                        <th class="px-4 py-3.5 text-right">Total Purchases</th>
                        <th class="px-4 py-3.5 text-right">Cash Used</th>
                        <th class="px-4 py-3.5 text-right">Expenses</th>
                        <th class="px-4 py-3.5 text-right">Pending Items</th>
                        <th class="px-4 py-3.5 text-right">Cash in Hand</th>
                        <th class="px-4 py-3.5 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse($summary['purchasers'] as $p)
                        @php
                            $rowUrl = route('admin.cashbook.finance.purchase.monthly-summary.show', ['purchaser' => $p['public_uuid'], 'month' => $month]);
                            $creditPendingVal = (float) ($p['credit_pending'] ?? 0.0);
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition">
                            <!-- Purchaser Name & Info -->
                            <td class="px-5 py-3.5 cursor-pointer" onclick="window.location.href='{{ $rowUrl }}'">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-7 w-7 rounded-lg bg-slate-900 text-white flex items-center justify-center font-bold text-[10px]">
                                        {{ strtoupper(substr($p['name'], 0, 2)) }}
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-900 block text-xs hover:text-emerald-700 transition">{{ $p['name'] }}</span>
                                        <span class="text-[10px] text-slate-400 font-mono">{{ $p['email'] }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Opening Cash (Physical Cash Only) -->
                            <td class="px-4 py-3.5 text-right cursor-pointer" onclick="window.location.href='{{ $rowUrl }}'">
                                <span class="font-mono font-bold text-slate-900 block">
                                    ₹{{ number_format((float) ($p['opening_cash'] ?? $p['opening']['cash_balance'] ?? 0), 2) }}
                                </span>
                                <span class="text-[10px] font-semibold {{ ($p['opening_cash_direction'] ?? $p['opening']['direction'] ?? '') === 'purchaser_holds_company_cash' ? 'text-emerald-600' : (($p['opening_cash_direction'] ?? $p['opening']['direction'] ?? '') === 'company_owes_purchaser' ? 'text-amber-600' : 'text-slate-400') }}">
                                    {{ ($p['opening_cash_direction'] ?? $p['opening']['direction'] ?? '') === 'purchaser_holds_company_cash' ? 'Holds Cash' : (($p['opening_cash_direction'] ?? $p['opening']['direction'] ?? '') === 'company_owes_purchaser' ? 'Due to Purchaser' : 'Settled') }}
                                </span>
                            </td>

                            <!-- Credit Pending (Vendor Purchasing Credit Only — Clickable for Vendor Breakdown) -->
                            <td class="px-4 py-3.5 text-right">
                                <button
                                    type="button"
                                    @click="openCreditModal({{ json_encode($p) }})"
                                    class="inline-flex flex-col items-end group focus:outline-hidden"
                                    title="Click to view vendor-by-vendor credit breakdown"
                                >
                                    <span class="font-mono font-bold block transition {{ $creditPendingVal > 0 ? 'text-amber-800 group-hover:text-amber-950 underline decoration-amber-300 decoration-1 underline-offset-2' : 'text-slate-700' }}">
                                        ₹{{ number_format($creditPendingVal, 2) }}
                                    </span>
                                    <span class="text-[10px] font-semibold flex items-center gap-1 {{ $creditPendingVal > 0 ? 'text-amber-700 group-hover:text-amber-900' : 'text-slate-400' }}">
                                        @if($creditPendingVal > 0)
                                            <i data-lucide="layers" class="h-3 w-3 inline text-amber-600"></i>
                                            <span>Vendor Credit Pending</span>
                                        @else
                                            <span>Settled</span>
                                        @endif
                                    </span>
                                </button>
                            </td>

                            <!-- Company Funded -->
                            <td class="px-4 py-3.5 text-right font-mono font-bold text-slate-900 cursor-pointer" onclick="window.location.href='{{ $rowUrl }}'">
                                ₹{{ number_format((float) ($p['company_funded'] ?? $p['activity']['company_funded'] ?? 0), 2) }}
                            </td>

                            <!-- Total Purchases -->
                            <td class="px-4 py-3.5 text-right font-mono font-bold text-indigo-700 cursor-pointer" onclick="window.location.href='{{ $rowUrl }}'">
                                ₹{{ number_format((float) ($p['total_purchases'] ?? $p['activity']['total_purchases'] ?? 0), 2) }}
                            </td>

                            <!-- Cash Used -->
                            <td class="px-4 py-3.5 text-right font-mono font-bold text-emerald-700 cursor-pointer" onclick="window.location.href='{{ $rowUrl }}'">
                                ₹{{ number_format((float) ($p['cash_used'] ?? $p['activity']['cash_used'] ?? 0), 2) }}
                            </td>

                            <!-- Expenses -->
                            <td class="px-4 py-3.5 text-right font-mono font-bold text-rose-700 cursor-pointer" onclick="window.location.href='{{ $rowUrl }}'">
                                ₹{{ number_format((float) ($p['expenses'] ?? $p['procurement_expenses'] ?? $p['activity']['procurement_expenses'] ?? 0), 2) }}
                            </td>

                            <!-- Pending Items -->
                            <td class="px-4 py-3.5 text-right cursor-pointer" onclick="window.location.href='{{ $rowUrl }}'">
                                @php
                                    $pCount = (int) ($p['pending_items_count'] ?? ((int)($p['pending']['pending_bills_count'] ?? 0) + (int)($p['pending']['pending_inventory_count'] ?? 0)));
                                @endphp
                                @if($pCount > 0)
                                    <span class="inline-flex items-center rounded-md bg-amber-100 text-amber-900 px-2 py-0.5 text-[11px] font-mono font-bold">
                                        {{ $pCount }} pending
                                    </span>
                                @else
                                    <span class="text-slate-400 font-mono text-[11px]">0</span>
                                @endif
                            </td>

                            <!-- Cash in Hand (Physical Cash Consolidated) -->
                            <td class="px-4 py-3.5 text-right cursor-pointer" onclick="window.location.href='{{ $rowUrl }}'">
                                <span class="font-mono font-bold text-slate-900 block">
                                    ₹{{ number_format((float) ($p['cash_in_hand'] ?? $p['closing']['cash_balance'] ?? 0), 2) }}
                                </span>
                                <span class="text-[10px] font-semibold {{ ($p['cash_in_hand_direction'] ?? $p['closing']['direction'] ?? '') === 'purchaser_holds_company_cash' ? 'text-emerald-600' : (($p['cash_in_hand_direction'] ?? $p['closing']['direction'] ?? '') === 'company_owes_purchaser' ? 'text-amber-600' : 'text-slate-400') }}">
                                    {{ ($p['cash_in_hand_direction'] ?? $p['closing']['direction'] ?? '') === 'purchaser_holds_company_cash' ? 'Holds Cash' : (($p['cash_in_hand_direction'] ?? $p['closing']['direction'] ?? '') === 'company_owes_purchaser' ? 'Due to Purchaser' : 'Settled') }}
                                </span>
                            </td>

                            <!-- Status Badge -->
                            <td class="px-4 py-3.5 text-center cursor-pointer" onclick="window.location.href='{{ $rowUrl }}'">
                                @php
                                    $color = $p['status']['color'] ?? 'slate';
                                    $badgeClass = match($color) {
                                        'emerald' => 'bg-emerald-100 text-emerald-900 border-emerald-300',
                                        'amber' => 'bg-amber-100 text-amber-900 border-amber-300',
                                        'sky' => 'bg-sky-100 text-sky-900 border-sky-300',
                                        'indigo' => 'bg-indigo-100 text-indigo-900 border-indigo-300',
                                        'purple' => 'bg-purple-100 text-purple-900 border-purple-300',
                                        default => 'bg-slate-100 text-slate-800 border-slate-300',
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-md border px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider {{ $badgeClass }}">
                                    {{ $p['status']['label'] ?? 'Settled' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="p-8 text-center text-slate-400 font-medium">
                                No active purchasers found for this month.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-50 font-black text-slate-900 border-t border-slate-200/80 text-xs">
                    <tr>
                        <td class="px-5 py-3.5 uppercase tracking-wider">Grand Totals</td>

                        <!-- Opening Cash Total -->
                        <td class="px-4 py-3.5 text-right font-mono">
                            ₹{{ number_format(abs((float) ($summary['grand_totals']['opening_cash_net'] ?? 0)), 2) }}
                        </td>

                        <!-- Credit Pending Total -->
                        <td class="px-4 py-3.5 text-right font-mono text-amber-950">
                            ₹{{ number_format((float) ($summary['grand_totals']['credit_pending'] ?? $summary['grand_totals']['vendor_credit_outstanding'] ?? 0), 2) }}
                        </td>

                        <!-- Company Funded Total -->
                        <td class="px-4 py-3.5 text-right font-mono">
                            ₹{{ number_format((float) ($summary['grand_totals']['company_funded'] ?? 0), 2) }}
                        </td>

                        <!-- Total Purchases Total -->
                        <td class="px-4 py-3.5 text-right font-mono text-indigo-700">
                            ₹{{ number_format((float) ($summary['grand_totals']['total_purchases'] ?? 0), 2) }}
                        </td>

                        <!-- Cash Used Total -->
                        <td class="px-4 py-3.5 text-right font-mono text-emerald-700">
                            ₹{{ number_format((float) ($summary['grand_totals']['cash_used'] ?? 0), 2) }}
                        </td>

                        <!-- Expenses Total -->
                        <td class="px-4 py-3.5 text-right font-mono text-rose-700">
                            ₹{{ number_format((float) ($summary['grand_totals']['expenses'] ?? $summary['grand_totals']['procurement_expenses'] ?? 0), 2) }}
                        </td>

                        <!-- Pending Items Total -->
                        <td class="px-4 py-3.5 text-right font-mono">
                            {{ (int) ($summary['grand_totals']['pending_items_count'] ?? $summary['grand_totals']['pending_bills_count'] ?? 0) }}
                        </td>

                        <!-- Cash in Hand Total -->
                        <td class="px-4 py-3.5 text-right font-mono">
                            ₹{{ number_format(abs((float) ($summary['grand_totals']['cash_in_hand_net'] ?? $summary['grand_totals']['closing_cash_net'] ?? 0)), 2) }}
                        </td>

                        <!-- Status Column -->
                        <td class="px-4 py-3.5 text-center">
                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-700 uppercase">
                                Consolidated
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!-- CREDIT PENDING VENDOR BREAKDOWN MODAL (ALPINE.JS) ────────────────── -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <div
        x-cloak
        x-show="creditModalOpen"
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Backdrop -->
            <div
                x-show="creditModalOpen"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="creditModalOpen = false"
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            ></div>

            <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

            <!-- Modal Content Panel -->
            <div
                x-show="creditModalOpen"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="inline-block w-full max-w-4xl transform overflow-hidden rounded-3xl bg-white text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle"
            >
                <div class="p-6 space-y-5">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-50 text-amber-700 border border-amber-200">
                                <i data-lucide="layers" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base font-black text-slate-900 uppercase" x-text="activePurchaser ? activePurchaser.name + ' — Vendor Credit Breakdown' : 'Vendor Credit Breakdown'"></h3>
                                    <span class="rounded-full bg-amber-100 text-amber-900 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider">
                                        Purchasing Credit
                                    </span>
                                </div>
                                <p class="text-xs font-semibold text-slate-500 mt-0.5">
                                    Authoritative breakdown of vendor purchasing obligations carried forward, added, settled, and currently pending.
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="creditModalOpen = false"
                            class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
                        >
                            <i data-lucide="x" class="h-5 w-5"></i>
                        </button>
                    </div>

                    <!-- Summary KPI Pill Ribbon -->
                    <template x-if="activePurchaser">
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                            <div class="rounded-xl bg-slate-50 p-3 border border-slate-200/70">
                                <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block">Opening Credit</span>
                                <span class="font-mono text-sm font-bold text-slate-900" x-text="'₹' + Number(activePurchaser.opening_credit_pending || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3 border border-slate-200/70">
                                <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block">Month Purchases</span>
                                <span class="font-mono text-sm font-bold text-indigo-700" x-text="'₹' + Number(activePurchaser.credit_purchases || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3 border border-slate-200/70">
                                <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block">Settled / Paid</span>
                                <span class="font-mono text-sm font-bold text-emerald-700" x-text="'₹' + Number(activePurchaser.credit_settled || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-3 border border-slate-200/70">
                                <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block">Discounts</span>
                                <span class="font-mono text-sm font-bold text-amber-700" x-text="'₹' + Number(activePurchaser.credit_discount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                            </div>
                            <div class="rounded-xl bg-amber-50/80 p-3 border border-amber-200/80">
                                <span class="text-[9px] font-black uppercase tracking-wider text-amber-900 block">Current Pending</span>
                                <span class="font-mono text-sm font-black text-amber-950" x-text="'₹' + Number(activePurchaser.credit_pending || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                            </div>
                        </div>
                    </template>

                    <!-- Vendor Breakdown Table -->
                    <div class="overflow-x-auto rounded-2xl border border-slate-200 max-h-96">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3">Vendor / Supplier</th>
                                    <th class="px-3 py-3 text-right">Opening Credit</th>
                                    <th class="px-3 py-3 text-right">Month Purchases</th>
                                    <th class="px-3 py-3 text-right">Paid / Settled</th>
                                    <th class="px-3 py-3 text-right">Discount</th>
                                    <th class="px-3 py-3 text-right">Current Pending</th>
                                    <th class="px-3 py-3 text-center">Bills</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                                <template x-if="activePurchaser && activePurchaser.credit_breakdown_by_vendor && activePurchaser.credit_breakdown_by_vendor.length > 0">
                                    <template x-for="vendor in activePurchaser.credit_breakdown_by_vendor" :key="vendor.supplier_id">
                                        <tr class="hover:bg-slate-50/80">
                                            <td class="px-4 py-3">
                                                <span class="font-bold text-slate-900 block" x-text="vendor.supplier_name"></span>
                                                <span class="text-[10px] text-slate-400 font-mono" x-text="'Vendor #' + vendor.supplier_id"></span>
                                            </td>
                                            <td class="px-3 py-3 text-right font-mono" x-text="'₹' + Number(vendor.opening_credit || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>
                                            <td class="px-3 py-3 text-right font-mono text-indigo-700" x-text="'₹' + Number(vendor.credit_purchases || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>
                                            <td class="px-3 py-3 text-right font-mono text-emerald-700" x-text="'₹' + Number(vendor.credit_settled || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>
                                            <td class="px-3 py-3 text-right font-mono text-amber-700" x-text="'₹' + Number(vendor.credit_discount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>
                                            <td class="px-3 py-3 text-right font-mono font-bold text-amber-950" x-text="'₹' + Number(vendor.credit_pending || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>
                                            <td class="px-3 py-3 text-center">
                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700 font-mono" x-text="vendor.invoices_count + ' bills'"></span>
                                            </td>
                                        </tr>
                                    </template>
                                </template>
                                <template x-if="!activePurchaser || !activePurchaser.credit_breakdown_by_vendor || activePurchaser.credit_breakdown_by_vendor.length === 0">
                                    <tr>
                                        <td colspan="7" class="p-8 text-center text-slate-400 font-medium">
                                            No vendor credit purchases or carried obligations recorded for this purchaser.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Modal Actions Footer -->
                    <div class="flex items-center justify-between border-t border-slate-100 pt-4">
                        <template x-if="activePurchaser">
                            <a
                                :href="'{{ route('admin.cashbook.finance.purchase.monthly-summary.show', ['purchaser' => 'PLACEHOLDER', 'month' => $month]) }}'.replace('PLACEHOLDER', activePurchaser.public_uuid)"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 transition shadow-xs"
                            >
                                <span>Open Full Purchaser Summary Report</span>
                                <i data-lucide="arrow-right" class="h-4 w-4"></i>
                            </a>
                        </template>
                        <button
                            type="button"
                            @click="creditModalOpen = false"
                            class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition ml-auto"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
