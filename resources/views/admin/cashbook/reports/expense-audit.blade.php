@extends('admin.cashbook.layouts.app')

@section('title', 'Expense Audit Report')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-16">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.cashbook.reports') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-extrabold transition">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    <span>Reports</span>
                </a>
                <span class="text-slate-300">/</span>
                <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-bold font-mono">Audit</span>
            </div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 uppercase flex items-center gap-2">
                <span>Expense Audit Report</span>
            </h1>
            <p class="text-xs text-slate-500 font-medium">Audit shop cashbook expenses, settlement effects, and company payment coverage</p>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
        <form method="GET" action="{{ route('admin.cashbook.reports.expense-audit') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 items-end text-xs">
            <div>
                <label class="block font-extrabold uppercase tracking-wider text-slate-500 text-[10px] mb-1">Shop</label>
                <select name="shop_id" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500">
                    <option value="">All Shops</option>
                    @foreach($shops as $s)
                        <option value="{{ $s->shop_id }}" {{ (string)$shopId === (string)$s->shop_id ? 'selected' : '' }}>{{ $s->name }} ({{ $s->code }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block font-extrabold uppercase tracking-wider text-slate-500 text-[10px] mb-1">Expense Type</label>
                <select name="entry_type_id" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500">
                    <option value="">All Categories</option>
                    @foreach($expenseTypes as $et)
                        <option value="{{ $et->id }}" {{ (string)$entryTypeId === (string)$et->id ? 'selected' : '' }}>{{ $et->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block font-extrabold uppercase tracking-wider text-slate-500 text-[10px] mb-1">Funding Source</label>
                <select name="funding_source" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500">
                    <option value="">All Funding</option>
                    <option value="sales" {{ $fundingSource === 'sales' ? 'selected' : '' }}>Sales</option>
                    <option value="company" {{ $fundingSource === 'company' ? 'selected' : '' }}>Company</option>
                    <option value="company_later" {{ $fundingSource === 'company_later' ? 'selected' : '' }}>Company Later (Shop Paid)</option>
                    <option value="petty" {{ $fundingSource === 'petty' ? 'selected' : '' }}>Petty</option>
                </select>
            </div>

            <div>
                <label class="block font-extrabold uppercase tracking-wider text-slate-500 text-[10px] mb-1">Coverage Status</label>
                <select name="coverage_status" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500">
                    <option value="">All Coverage</option>
                    <option value="Uncovered" {{ $coverageStatus === 'Uncovered' ? 'selected' : '' }}>Uncovered</option>
                    <option value="Partially covered" {{ $coverageStatus === 'Partially covered' ? 'selected' : '' }}>Partially covered</option>
                    <option value="Covered" {{ $coverageStatus === 'Covered' ? 'selected' : '' }}>Covered</option>
                    <option value="Non-Payable" {{ $coverageStatus === 'Non-Payable' ? 'selected' : '' }}>Non-Payable</option>
                </select>
            </div>

            <div>
                <div class="mb-1 flex items-center justify-between">
                    <label class="block font-extrabold uppercase tracking-wider text-slate-500 text-[10px]">From Date</label>
                    <x-cashbook.previous-month-button mode="range" startField="from_date" endField="to_date" size="xs" label="{{ now()->startOfMonth()->subDay()->format('M') }}" />
                </div>
                <input type="date" name="from_date" value="{{ $fromDate }}" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block font-extrabold uppercase tracking-wider text-slate-500 text-[10px] mb-1">To Date</label>
                <div class="flex items-center gap-2">
                    <input type="date" name="to_date" value="{{ $toDate }}" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500">
                    <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-emerald-700 text-white rounded-xl font-black transition">Filter</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Expenses Table -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h2 class="text-sm font-extrabold uppercase tracking-wide text-slate-900">
                Audited Expenses ({{ count($auditItems) }})
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse font-sans">
                <thead>
                    <tr class="border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-50/50">
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Shop</th>
                        <th class="py-3 px-4">Expense Category</th>
                        <th class="py-3 px-4">Funding Source</th>
                        <th class="py-3 px-4 text-right">Amount</th>
                        <th class="py-3 px-4 text-right">Covered</th>
                        <th class="py-3 px-4 text-right">Remaining</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4">Notes / Payment Ref</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-mono">
                    @forelse($auditItems as $item)
                        @php
                            $tx = $item['transaction'];
                            $badgeClass = match($item['coverage_status']) {
                                'Covered' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                'Partially covered' => 'bg-amber-50 text-amber-800 border-amber-200',
                                'Uncovered' => 'bg-rose-50 text-rose-800 border-rose-200',
                                default => 'bg-slate-50 text-slate-700 border-slate-200',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3.5 px-4 font-sans font-bold text-slate-900">
                                {{ $tx->business_date?->format('d M Y') }}
                            </td>
                            <td class="py-3.5 px-4 font-sans font-bold text-slate-800">
                                {{ $tx->shop?->name ?? ('Shop #'.$tx->shop_id) }}
                            </td>
                            <td class="py-3.5 px-4 font-sans font-extrabold text-slate-900">
                                {{ $tx->entryType?->name ?: ($tx->entry_type_code ?? 'Expense') }}
                            </td>
                            <td class="py-3.5 px-4 font-sans">
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[10px] font-extrabold uppercase">
                                    {{ $tx->funding_source }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right font-black text-slate-900">
                                ₹{{ number_format($tx->amount, 2) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-bold text-emerald-700">
                                ₹{{ number_format($item['covered_amount'], 2) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-bold text-slate-800">
                                ₹{{ number_format($item['remaining_amount'], 2) }}
                            </td>
                            <td class="py-3.5 px-4 text-center font-sans">
                                <span class="inline-flex items-center text-[10px] font-extrabold px-2.5 py-1 rounded-lg border {{ $badgeClass }}">
                                    {{ $item['coverage_status'] }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-sans text-xs text-slate-500">
                                {{ $tx->notes ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-slate-400 font-sans font-bold">
                                No expenses found matching the selected criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
