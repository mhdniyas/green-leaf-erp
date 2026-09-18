@extends('admin.cashbook.layouts.app')

@section('title', 'Purchaser Monthly Summary — ' . $summary['formatted_month'])

@section('header_title')
    <i data-lucide="calendar-check" class="w-5 h-5 text-emerald-600"></i> Purchaser Monthly Summary
@endsection

@section('header_subtitle')
    Consolidated end-of-month purchaser cash balances, company funding, cash/credit purchases, expenses, and carry-forward status.
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-6">

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
                    Select a month to inspect audited purchaser funding, bill activity, cash usage, and closing cash positions.
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
            <span class="mt-0.5 block text-[10px] font-semibold text-indigo-600">All purchase bills</span>
        </div>

        <!-- Cash Used -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Cash Used</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-emerald-700">
                ₹{{ number_format((float) ($summary['grand_totals']['cash_used'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-emerald-600">Advance utilized</span>
        </div>

        <!-- Vendor Credit Outstanding -->
        <div class="rounded-2xl border border-amber-200/80 bg-amber-50/40 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-amber-900">Vendor Credit</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-amber-950">
                ₹{{ number_format((float) ($summary['grand_totals']['vendor_credit_outstanding'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-amber-700">Unsettled vendor balance</span>
        </div>

        <!-- Procurement Expenses -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Purchaser Expenses</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-bold text-rose-700">
                ₹{{ number_format((float) ($summary['grand_totals']['procurement_expenses'] ?? 0), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">Fuel, labour, vehicle</span>
        </div>

        <!-- Net Purchaser Cash Position -->
        <div class="rounded-2xl border border-slate-300 bg-slate-900 text-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-300">Net Purchaser Cash</span>
            <span class="mt-1 block font-mono text-lg sm:text-xl font-black text-white">
                ₹{{ number_format(abs((float) ($summary['grand_totals']['closing_cash_net'] ?? 0)), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-bold text-emerald-400">
                {{ (float) ($summary['grand_totals']['closing_cash_net'] ?? 0) >= 0 ? 'Purchaser holds Cash' : 'Company owes Purchaser' }}
            </span>
        </div>
    </div>

    <!-- All Purchasers Table Container -->
    <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/70 px-6 py-4">
            <div>
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <i data-lucide="users" class="h-4 w-4 text-emerald-600"></i>
                    <span>All Purchasers Closing Matrix</span>
                </h2>
                <p class="text-xs font-semibold text-slate-500 mt-0.5">
                    Click any purchaser row to view detailed breakdown, cash reconciliation, and read-only drilldowns.
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
                        <th class="px-4 py-3.5 text-right">Company Funded</th>
                        <th class="px-4 py-3.5 text-right">Total Purchases</th>
                        <th class="px-4 py-3.5 text-right">Cash Used</th>
                        <th class="px-4 py-3.5 text-right">Expenses</th>
                        <th class="px-4 py-3.5 text-right">Pending Items</th>
                        <th class="px-4 py-3.5 text-right">Closing Cash</th>
                        <th class="px-4 py-3.5 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse($summary['purchasers'] as $p)
                        @php
                            $rowUrl = route('admin.cashbook.finance.purchase.monthly-summary.show', ['purchaser' => $p['public_uuid'], 'month' => $month]);
                        @endphp
                        <tr
                            onclick="window.location.href='{{ $rowUrl }}'"
                            class="hover:bg-slate-50/80 cursor-pointer transition"
                        >
                            <!-- Purchaser Name & Info -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-7 w-7 rounded-lg bg-slate-900 text-white flex items-center justify-center font-bold text-[10px]">
                                        {{ strtoupper(substr($p['name'], 0, 2)) }}
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-900 block text-xs">{{ $p['name'] }}</span>
                                        <span class="text-[10px] text-slate-400 font-mono">{{ $p['email'] }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Opening Cash -->
                            <td class="px-4 py-3.5 text-right">
                                <span class="font-mono font-bold text-slate-900 block">
                                    ₹{{ number_format((float) $p['opening']['cash_balance'], 2) }}
                                </span>
                                <span class="text-[10px] font-semibold {{ $p['opening']['direction'] === 'purchaser_holds_company_cash' ? 'text-emerald-600' : ($p['opening']['direction'] === 'company_owes_purchaser' ? 'text-amber-600' : 'text-slate-400') }}">
                                    {{ $p['opening']['direction'] === 'purchaser_holds_company_cash' ? 'Holds Cash' : ($p['opening']['direction'] === 'company_owes_purchaser' ? 'Due to Purchaser' : 'Settled') }}
                                </span>
                            </td>

                            <!-- Company Funded -->
                            <td class="px-4 py-3.5 text-right font-mono font-bold text-slate-900">
                                ₹{{ number_format((float) $p['activity']['company_funded'], 2) }}
                            </td>

                            <!-- Total Purchases -->
                            <td class="px-4 py-3.5 text-right font-mono font-bold text-indigo-700">
                                ₹{{ number_format((float) $p['activity']['total_purchases'], 2) }}
                            </td>

                            <!-- Cash Used -->
                            <td class="px-4 py-3.5 text-right font-mono font-bold text-emerald-700">
                                ₹{{ number_format((float) $p['activity']['cash_used'], 2) }}
                            </td>

                            <!-- Expenses -->
                            <td class="px-4 py-3.5 text-right font-mono font-bold text-rose-700">
                                ₹{{ number_format((float) $p['activity']['procurement_expenses'], 2) }}
                            </td>

                            <!-- Pending Items -->
                            <td class="px-4 py-3.5 text-right">
                                @if((int) $p['pending']['pending_bills_count'] > 0 || (int) $p['pending']['pending_inventory_count'] > 0)
                                    <span class="inline-flex items-center rounded-md bg-amber-100 text-amber-900 px-2 py-0.5 text-[11px] font-mono font-bold">
                                        {{ (int) $p['pending']['pending_bills_count'] + (int) $p['pending']['pending_inventory_count'] }} pending
                                    </span>
                                @else
                                    <span class="text-slate-400 font-mono text-[11px]">0</span>
                                @endif
                            </td>

                            <!-- Closing Cash -->
                            <td class="px-4 py-3.5 text-right">
                                <span class="font-mono font-bold text-slate-900 block">
                                    ₹{{ number_format((float) $p['closing']['cash_balance'], 2) }}
                                </span>
                                <span class="text-[10px] font-semibold {{ $p['closing']['direction'] === 'purchaser_holds_company_cash' ? 'text-emerald-600' : ($p['closing']['direction'] === 'company_owes_purchaser' ? 'text-amber-600' : 'text-slate-400') }}">
                                    {{ $p['closing']['direction'] === 'purchaser_holds_company_cash' ? 'Holds Cash' : ($p['closing']['direction'] === 'company_owes_purchaser' ? 'Due to Purchaser' : 'Settled') }}
                                </span>
                            </td>

                            <!-- Status Badge -->
                            <td class="px-4 py-3.5 text-center">
                                @php
                                    $color = $p['status']['color'] ?? 'slate';
                                    $badgeClass = match($color) {
                                        'emerald' => 'bg-emerald-100 text-emerald-900 border-emerald-300',
                                        'amber' => 'bg-amber-100 text-amber-900 border-amber-300',
                                        'sky' => 'bg-sky-100 text-sky-900 border-sky-300',
                                        'indigo' => 'bg-indigo-100 text-indigo-900 border-indigo-300',
                                        default => 'bg-slate-100 text-slate-800 border-slate-300',
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-md border px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider {{ $badgeClass }}">
                                    {{ $p['status']['label'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="p-8 text-center text-slate-400 font-medium">
                                No active purchasers found for this month.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-50 font-black text-slate-900 border-t border-slate-200/80 text-xs">
                    <tr>
                        <td class="px-5 py-3.5 uppercase tracking-wider">Grand Totals</td>
                        <td class="px-4 py-3.5 text-right font-mono">
                            ₹{{ number_format(abs((float) ($summary['grand_totals']['opening_cash_net'] ?? 0)), 2) }}
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono">
                            ₹{{ number_format((float) ($summary['grand_totals']['company_funded'] ?? 0), 2) }}
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono text-indigo-700">
                            ₹{{ number_format((float) ($summary['grand_totals']['total_purchases'] ?? 0), 2) }}
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono text-emerald-700">
                            ₹{{ number_format((float) ($summary['grand_totals']['cash_used'] ?? 0), 2) }}
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono text-rose-700">
                            ₹{{ number_format((float) ($summary['grand_totals']['procurement_expenses'] ?? 0), 2) }}
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono">
                            {{ (int) ($summary['grand_totals']['pending_bills_count'] ?? 0) }}
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono">
                            ₹{{ number_format(abs((float) ($summary['grand_totals']['closing_cash_net'] ?? 0)), 2) }}
                        </td>
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
</div>
@endsection
