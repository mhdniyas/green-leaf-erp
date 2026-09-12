@extends('admin.cashbook.layouts.app')
@section('title', 'Daily Purchase Report - Cashbook')

@section('header_title')
    <i data-lucide="calendar" class="h-5 w-5 text-emerald-600"></i> Daily Purchase Report
@endsection

@section('header_subtitle')
    Compare sales against purchase cost and purchaser expenses per business day.
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-5" x-data="{ expandedDates: {} }">
    @include('admin.cashbook.finance.purchase.reports._header', [
        'reportName' => 'Daily Report',
        'reportDescription' => 'Compare daily sales against purchase costs and purchaser expenses.'
    ])

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Sales</span>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="font-mono text-xl font-black text-slate-900">₹{{ number_format($reportData['summary']['total_sales'], 2) }}</span>
            </div>
            <p class="mt-1 text-[11px] font-medium text-slate-500">From shop invoices</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Purchase</span>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="font-mono text-xl font-black text-slate-900">₹{{ number_format($reportData['summary']['total_purchase'], 2) }}</span>
            </div>
            <p class="mt-1 text-[11px] font-medium text-slate-500">Procurement net cost</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Purchaser Expenses</span>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="font-mono text-xl font-black text-amber-600">₹{{ number_format($reportData['summary']['purchaser_expenses'], 2) }}</span>
            </div>
            <p class="mt-1 text-[11px] font-medium text-slate-500">Vehicle, labour, food, fuel, etc.</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Cost</span>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="font-mono text-xl font-black text-slate-900">₹{{ number_format($reportData['summary']['total_cost'], 2) }}</span>
            </div>
            <p class="mt-1 text-[11px] font-medium text-slate-500">Purchase + Purchaser Expenses</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Difference</span>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="font-mono text-xl font-black {{ $reportData['summary']['difference'] >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                    {{ $reportData['summary']['difference'] < 0 ? '-' : '' }}₹{{ number_format(abs($reportData['summary']['difference']), 2) }}
                </span>
            </div>
            <p class="mt-1 text-[11px] font-medium text-slate-500">Sales - Total Cost</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-4">
        <form method="GET" action="{{ route('admin.cashbook.finance.purchase.reports.daily') }}" class="space-y-4">
            <!-- Quick Filter Chips -->
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-bold text-slate-500 mr-1">Period:</span>
                <a href="{{ route('admin.cashbook.finance.purchase.reports.daily', array_merge(request()->except(['period', 'start_date', 'end_date']), ['period' => 'today'])) }}"
                   class="rounded-lg border px-3 py-1.5 text-xs font-bold transition-colors {{ ($filters['period'] ?? '') === 'today' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}">
                    Today
                </a>
                <a href="{{ route('admin.cashbook.finance.purchase.reports.daily', array_merge(request()->except(['period', 'start_date', 'end_date']), ['period' => 'yesterday'])) }}"
                   class="rounded-lg border px-3 py-1.5 text-xs font-bold transition-colors {{ ($filters['period'] ?? '') === 'yesterday' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}">
                    Yesterday
                </a>
                <a href="{{ route('admin.cashbook.finance.purchase.reports.daily', array_merge(request()->except(['period', 'start_date', 'end_date']), ['period' => 'month'])) }}"
                   class="rounded-lg border px-3 py-1.5 text-xs font-bold transition-colors {{ in_array(($filters['period'] ?? ''), ['month', 'this_month'], true) ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}">
                    This Month
                </a>
                <a href="{{ route('admin.cashbook.finance.purchase.reports.daily', array_merge(request()->except(['period']), ['period' => 'custom', 'start_date' => $reportData['start_date'], 'end_date' => $reportData['end_date']])) }}"
                   class="rounded-lg border px-3 py-1.5 text-xs font-bold transition-colors {{ in_array(($filters['period'] ?? ''), ['custom', 'between', 'range'], true) ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}">
                    Custom Range
                </a>
            </div>

            <!-- Filter Controls -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4 xl:grid-cols-5 items-end">
                <input type="hidden" name="period" value="custom">

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400">Date From</label>
                    <input type="date" name="start_date" value="{{ $reportData['start_date'] }}"
                           class="mt-1 block w-full rounded-lg border-slate-200 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400">Date To</label>
                    <input type="date" name="end_date" value="{{ $reportData['end_date'] }}"
                           class="mt-1 block w-full rounded-lg border-slate-200 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400">Warehouse</label>
                    <select name="warehouse_id" class="mt-1 block w-full rounded-lg border-slate-200 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">All Warehouses</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" @selected(($filters['warehouse_id'] ?? null) == $w->id)>
                                {{ $w->name }} ({{ $w->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400">Purchaser</label>
                    <select name="purchaser_id" class="mt-1 block w-full rounded-lg border-slate-200 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">All Purchasers</option>
                        @foreach($purchasers as $p)
                            <option value="{{ $p->id }}" @selected(($filters['purchaser_id'] ?? null) == $p->id)>
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg bg-emerald-700 px-4 text-xs font-black text-white hover:bg-emerald-800 transition">
                        <i data-lucide="filter" class="h-4 w-4"></i> Apply
                    </button>
                    <a href="{{ route('admin.cashbook.finance.purchase.reports.daily') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-slate-600 hover:bg-slate-50 transition" title="Reset filters">
                        <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Daily Comparison Table -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 px-4 py-3 sm:px-6 flex items-center justify-between">
            <h2 class="text-sm font-black text-slate-900">Daily Purchase vs Sales Comparison</h2>
            <span class="text-xs font-medium text-slate-500">Click a date to expand details</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                    <tr>
                        <th scope="col" class="py-3 px-4">Date</th>
                        <th scope="col" class="py-3 px-4 text-right">Sales</th>
                        <th scope="col" class="py-3 px-4 text-right">Purchase</th>
                        <th scope="col" class="py-3 px-4 text-right">Purchaser Expenses</th>
                        <th scope="col" class="py-3 px-4 text-right">Total Cost</th>
                        <th scope="col" class="py-3 px-4 text-right">Difference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($reportData['rows'] as $row)
                        @php
                            $dateKey = $row['date'];
                        @endphp
                        <tr class="hover:bg-slate-50/80 cursor-pointer transition"
                            @click="expandedDates['{{ $dateKey }}'] = !expandedDates['{{ $dateKey }}']"
                            :class="{ 'bg-emerald-50/40': expandedDates['{{ $dateKey }}'] }">
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2">
                                    <button type="button" class="text-slate-400 hover:text-slate-600 transition">
                                        <i data-lucide="chevron-right" class="h-4 w-4 transition-transform duration-200"
                                           :class="{ 'rotate-90 text-emerald-700': expandedDates['{{ $dateKey }}'] }"></i>
                                    </button>
                                    <div>
                                        <span class="font-bold text-slate-900 hover:text-emerald-700 hover:underline">{{ $row['date_formatted'] }}</span>
                                        <span class="ml-1 text-[10px] font-semibold text-slate-400">({{ $row['day_name'] }})</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">
                                ₹{{ number_format($row['sales'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">
                                ₹{{ number_format($row['purchase'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-amber-600">
                                ₹{{ number_format($row['purchaser_expenses'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">
                                ₹{{ number_format($row['total_cost'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-black {{ $row['difference'] >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                {{ $row['difference'] < 0 ? '-' : '' }}₹{{ number_format(abs($row['difference']), 2) }}
                            </td>
                        </tr>

                        <!-- Expanded Breakdown Row -->
                        <tr x-show="expandedDates['{{ $dateKey }}']" x-cloak class="bg-slate-50/60 border-t border-slate-200">
                            <td colspan="6" class="p-4 sm:p-6 space-y-4">
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                    <!-- 1. Sales Breakdown by Shop -->
                                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs">
                                        <div class="flex items-center justify-between pb-2 border-b border-slate-100 mb-3">
                                            <h3 class="text-xs font-black uppercase text-slate-700 flex items-center gap-1.5">
                                                <i data-lucide="store" class="h-3.5 w-3.5 text-emerald-600"></i> Sales by Shop
                                            </h3>
                                            <span class="font-mono text-xs font-black text-slate-900">₹{{ number_format($row['sales'], 2) }}</span>
                                        </div>
                                        @if(count($row['sales_breakdown']) > 0)
                                            <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                                                @foreach($row['sales_breakdown'] as $shopSale)
                                                    <div class="flex items-center justify-between text-xs py-1 border-b border-slate-50 last:border-0">
                                                        <div>
                                                            <strong class="text-slate-800">{{ $shopSale['shop_name'] }}</strong>
                                                            <span class="text-[10px] text-slate-400 block">{{ $shopSale['shop_code'] }} · {{ $shopSale['invoice_count'] }} inv</span>
                                                        </div>
                                                        <span class="font-mono font-bold text-slate-900">₹{{ number_format($shopSale['sales'], 2) }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-xs text-slate-400 py-3 text-center">No sales recorded for this date.</p>
                                        @endif
                                    </div>

                                    <!-- 2. Purchase Breakdown -->
                                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs">
                                        <div class="flex items-center justify-between pb-2 border-b border-slate-100 mb-3">
                                            <h3 class="text-xs font-black uppercase text-slate-700 flex items-center gap-1.5">
                                                <i data-lucide="shopping-cart" class="h-3.5 w-3.5 text-emerald-600"></i> Purchase Details
                                            </h3>
                                            <span class="font-mono text-xs font-black text-slate-900">₹{{ number_format($row['purchase'], 2) }}</span>
                                        </div>
                                        @if($row['purchase'] > 0)
                                            <div class="space-y-3 max-h-64 overflow-y-auto pr-1">
                                                @if(count($row['purchase_breakdown']['by_warehouse']) > 0)
                                                    <div>
                                                        <span class="text-[10px] font-black uppercase text-slate-400 block mb-1">By Warehouse</span>
                                                        @foreach($row['purchase_breakdown']['by_warehouse'] as $wData)
                                                            <div class="flex items-center justify-between text-xs py-0.5">
                                                                <span class="text-slate-700 font-medium">{{ $wData['warehouse_name'] }} ({{ $wData['warehouse_code'] }})</span>
                                                                <span class="font-mono font-bold text-slate-900">₹{{ number_format($wData['amount'], 2) }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                @if(count($row['purchase_breakdown']['by_purchaser']) > 0)
                                                    <div class="pt-2 border-t border-slate-100">
                                                        <span class="text-[10px] font-black uppercase text-slate-400 block mb-1">By Purchaser</span>
                                                        @foreach($row['purchase_breakdown']['by_purchaser'] as $pData)
                                                            <div class="flex items-center justify-between text-xs py-0.5">
                                                                <span class="text-slate-700 font-medium">{{ $pData['purchaser_name'] }}</span>
                                                                <span class="font-mono font-bold text-slate-900">₹{{ number_format($pData['amount'], 2) }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                @if(count($row['purchase_breakdown']['by_supplier']) > 0)
                                                    <div class="pt-2 border-t border-slate-100">
                                                        <span class="text-[10px] font-black uppercase text-slate-400 block mb-1">By Vendor</span>
                                                        @foreach($row['purchase_breakdown']['by_supplier'] as $sData)
                                                            <div class="flex items-center justify-between text-xs py-0.5">
                                                                <span class="text-slate-700 font-medium">{{ $sData['supplier_name'] }}</span>
                                                                <span class="font-mono font-bold text-slate-900">₹{{ number_format($sData['amount'], 2) }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <p class="text-xs text-slate-400 py-3 text-center">No purchases recorded for this date.</p>
                                        @endif
                                    </div>

                                    <!-- 3. Purchaser Expense Breakdown -->
                                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs">
                                        <div class="flex items-center justify-between pb-2 border-b border-slate-100 mb-3">
                                            <h3 class="text-xs font-black uppercase text-slate-700 flex items-center gap-1.5">
                                                <i data-lucide="receipt" class="h-3.5 w-3.5 text-amber-600"></i> Purchaser Expenses
                                            </h3>
                                            <span class="font-mono text-xs font-black text-amber-600">₹{{ number_format($row['purchaser_expenses'], 2) }}</span>
                                        </div>
                                        @if(count($row['expense_breakdown']) > 0)
                                            <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                                                @foreach($row['expense_breakdown'] as $exp)
                                                    <div class="flex items-start justify-between text-xs py-1 border-b border-slate-50 last:border-0">
                                                        <div>
                                                            <div class="flex items-center gap-1.5">
                                                                <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 border border-amber-200">
                                                                    {{ $exp['category_label'] }}
                                                                </span>
                                                                <span class="text-slate-700 font-semibold">{{ $exp['purchaser_name'] }}</span>
                                                            </div>
                                                            @if(!empty($exp['note']))
                                                                <p class="text-[11px] text-slate-500 mt-0.5">{{ $exp['note'] }}</p>
                                                            @endif
                                                        </div>
                                                        <span class="font-mono font-bold text-amber-700">₹{{ number_format($exp['amount'], 2) }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-xs text-slate-400 py-3 text-center">No purchaser expenses recorded for this date.</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-400">
                                No records found for the selected period and filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
