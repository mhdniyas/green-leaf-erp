@extends('admin.cashbook.layouts.app')
@section('title', 'Purchaser Purchase & Expense Report - Cashbook')

@section('header_title')
    <i data-lucide="receipt" class="h-5 w-5 text-emerald-600"></i> Purchaser Purchase & Expense Report
@endsection

@section('header_subtitle')
    Combined purchase totals and procurement expenses by purchaser.
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-5">
    @include('admin.cashbook.finance.purchase.reports._header', [
        'reportName' => 'Purchaser Purchase & Expense Report',
        'reportDescription' => 'Canonical combined report of purchaser orders, bills, and procurement expenses.'
    ])

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Purchase</span>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="font-mono text-2xl font-black text-slate-900">₹{{ number_format($summary['total_purchase'], 2) }}</span>
                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">{{ $summary['bills_count'] }} Bills</span>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Expenses</span>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="font-mono text-2xl font-black text-amber-600">₹{{ number_format($summary['total_expenses'], 2) }}</span>
                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-700">{{ $summary['expenses_count'] }} Expenses</span>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Combined Total</span>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="font-mono text-2xl font-black text-emerald-700">₹{{ number_format($summary['combined_total'], 2) }}</span>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">Purchase + Expense</span>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Entries / Records</span>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="font-mono text-2xl font-black text-slate-900">{{ number_format($summary['total_entries']) }}</span>
                <span class="text-xs font-semibold text-slate-500">{{ $summary['date_from_formatted'] }} - {{ $summary['date_to_formatted'] }}</span>
            </div>
        </div>
    </div>

    <!-- Filters & Export Header -->
    @component('admin.cashbook.finance.purchase.partials._period-filter', [
        'action' => route('admin.cashbook.finance.purchase.purchaser-expenses'),
        'filters' => $filters,
        'extraParams' => array_filter([
            'purchaser' => request('purchaser', $filters['purchaser'] ?? null),
            'purchaser_id' => request('purchaser_id', $filters['purchaser_id'] ?? null),
            'supplier_id' => request('supplier_id', $filters['supplier_id'] ?? null),
            'expense_type' => request('expense_type', $filters['expense_type'] ?? null),
            'search' => request('search', $filters['search'] ?? null),
        ], fn ($v) => $v !== null && $v !== ''),
    ])
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4 items-end">
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Purchaser</label>
                <select name="purchaser" onchange="this.form.submit()" class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">All Purchasers</option>
                    @foreach($purchasers as $p)
                        <option value="{{ $p->public_uuid }}" {{ request('purchaser') == $p->public_uuid || request('purchaser_id') == $p->id ? 'selected' : '' }}>
                            {{ $p->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Supplier</label>
                <select name="supplier_id" onchange="this.form.submit()" class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" {{ request('supplier_id') == $s->id ? 'selected' : '' }}>
                            {{ $s->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Expense Type</label>
                <select name="expense_type" onchange="this.form.submit()" class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">All Types / Purchases</option>
                    @foreach($expenseTypes as $key => $label)
                        <option value="{{ $key }}" {{ request('expense_type') == $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Ref, item, note..."
                       class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
            </div>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-3 border-t border-slate-100">
            <div class="flex items-center gap-2">
                <button type="submit" class="inline-flex min-h-9 items-center gap-1.5 rounded-xl bg-emerald-700 px-4 text-xs font-black text-white hover:bg-emerald-800 shadow-xs cursor-pointer">
                    <i data-lucide="filter" class="h-3.5 w-3.5"></i> Apply
                </button>
                <a href="{{ route('admin.cashbook.finance.purchase.purchaser-expenses') }}" class="inline-flex min-h-9 items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                    <i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i>
                </a>
            </div>

            <!-- Export Buttons -->
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.cashbook.finance.purchase.purchaser-expenses.export.pdf', request()->query()) }}" target="_blank"
                   class="inline-flex min-h-9 items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3 text-xs font-black text-rose-700 hover:bg-rose-100 shadow-xs transition">
                    <i data-lucide="file-text" class="h-3.5 w-3.5"></i> PDF
                </a>
                <a href="{{ route('admin.cashbook.finance.purchase.purchaser-expenses.export.csv', request()->query()) }}"
                   class="inline-flex min-h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-black text-slate-700 hover:bg-slate-100 shadow-xs transition">
                    <i data-lucide="download" class="h-3.5 w-3.5"></i> CSV
                </a>
                <a href="{{ route('admin.cashbook.finance.purchase.purchaser-expenses.export.excel', request()->query()) }}"
                   class="inline-flex min-h-9 items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-xs font-black text-emerald-800 hover:bg-emerald-100 shadow-xs transition">
                    <i data-lucide="sheet" class="h-3.5 w-3.5"></i> Excel
                </a>
            </div>
        </div>
    @endcomponent

    <!-- Data Table -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[56rem] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500 border-b border-slate-100">
                    <tr>
                        <th class="p-3">Date</th>
                        <th class="p-3">Type</th>
                        <th class="p-3">Purchaser</th>
                        <th class="p-3">Supplier</th>
                        <th class="p-3">Reference</th>
                        <th class="p-3">Details / Expense Type</th>
                        <th class="p-3 text-right">Purchase (₹)</th>
                        <th class="p-3 text-right">Expense (₹)</th>
                        <th class="p-3 text-right">Total (₹)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="p-3 font-mono text-slate-600 whitespace-nowrap">{{ $row['date_formatted'] }}</td>
                            <td class="p-3">
                                @if($row['type'] === 'purchase')
                                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200">
                                        Purchase
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-200">
                                        Expense
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 font-bold text-slate-900">{{ $row['purchaser_name'] }}</td>
                            <td class="p-3 font-medium text-slate-700">{{ $row['supplier_name'] }}</td>
                            <td class="p-3 font-mono font-semibold text-slate-800 whitespace-nowrap">{{ $row['reference'] }}</td>
                            <td class="p-3 text-slate-600 max-w-xs truncate" title="{{ $row['type'] === 'expense' ? $row['expense_type'] . ' - ' . $row['details'] : $row['details'] }}">
                                @if($row['type'] === 'expense')
                                    <span class="font-bold text-amber-800">{{ $row['expense_type'] }}:</span>
                                @endif
                                {{ $row['details'] }}
                            </td>
                            <td class="p-3 text-right font-mono font-medium text-slate-900">
                                {{ $row['purchase_amount'] > 0 ? '₹' . number_format($row['purchase_amount'], 2) : '-' }}
                            </td>
                            <td class="p-3 text-right font-mono font-medium text-amber-600">
                                {{ $row['expense_amount'] > 0 ? '₹' . number_format($row['expense_amount'], 2) : '-' }}
                            </td>
                            <td class="p-3 text-right font-mono font-bold text-slate-900">
                                ₹{{ number_format($row['total'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="p-8 text-center text-slate-400 font-medium">
                                No purchase or expense records found for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($rows) > 0)
                    <tfoot class="bg-slate-50 border-t border-slate-200 text-xs font-black">
                        <tr>
                            <td colspan="6" class="p-3 text-slate-700 text-right uppercase tracking-wider">Page Total</td>
                            <td class="p-3 text-right font-mono text-slate-900">₹{{ number_format(collect($rows)->sum('purchase_amount'), 2) }}</td>
                            <td class="p-3 text-right font-mono text-amber-600">₹{{ number_format(collect($rows)->sum('expense_amount'), 2) }}</td>
                            <td class="p-3 text-right font-mono text-emerald-700">₹{{ number_format(collect($rows)->sum('total'), 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if($paginator && $paginator->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50">
                {{ $paginator->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
