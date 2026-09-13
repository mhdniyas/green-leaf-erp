@extends('admin.cashbook.layouts.app')

@section('title', 'Daily Auto Match — Green Leaf ERP')

@section('header_title')
    <i data-lucide="sparkles" class="w-5 h-5 text-emerald-600"></i> Daily Auto Match
@endsection

@section('header_subtitle')
    Deterministic daily purchase bill to advance reconciliation scoped to warehouse and business date period.
@endsection

@php
    $getSortUrl = function (string $column) use ($selectedWarehouseId, $selectedFromDate, $selectedToDate, $cursor, $sort, $direction) {
        $nextDirection = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
        $params = [
            'warehouse_id' => $selectedWarehouseId,
            'from_date' => $selectedFromDate,
            'to_date' => $selectedToDate,
            'sort' => $column,
            'direction' => $nextDirection,
        ];
        if ($cursor) {
            $params['cursor'] = $cursor;
        }
        return route('admin.cashbook.auto-match', $params);
    };
@endphp

@section('content')
<div x-data="dailyAutoMatchComponent()" x-init="init()" class="space-y-6">

    <!-- Top Action Bar & Filters -->
    <div class="rounded-3xl border border-slate-200/90 bg-white p-5 shadow-xs space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            
            <!-- Left: Warehouse & Date Period Selection Form -->
            <form method="GET" action="{{ route('admin.cashbook.auto-match') }}" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">

                <!-- Warehouse Select -->
                <div class="inline-flex items-center gap-1.5 rounded-2xl bg-slate-100 p-1.5 border border-slate-200/80">
                    <label for="warehouse-select" class="pl-2 text-xs font-black text-slate-600 flex items-center gap-1 cursor-pointer">
                        <i data-lucide="warehouse" class="w-4 h-4 text-slate-500"></i>
                        <span>Warehouse:</span>
                    </label>
                    <select id="warehouse-select"
                            name="warehouse_id"
                            class="rounded-xl bg-white px-3 py-1.5 text-xs font-black text-slate-800 shadow-xs border-0 focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                        @foreach($availableWarehouses as $wh)
                            <option value="{{ $wh->id }}" @selected($wh->id == $selectedWarehouseId)>
                                {{ $wh->name }} ({{ $wh->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- From Date Picker -->
                <div class="inline-flex items-center gap-1.5 rounded-2xl bg-slate-100 p-1.5 border border-slate-200/80">
                    <label for="from-date-input" class="pl-2 text-xs font-black text-slate-600 flex items-center gap-1 cursor-pointer">
                        <i data-lucide="calendar" class="w-4 h-4 text-slate-500"></i>
                        <span>From:</span>
                    </label>
                    <input type="date"
                           id="from-date-input"
                           name="from_date"
                           value="{{ $selectedFromDate }}"
                           class="rounded-xl bg-white px-3 py-1.5 text-xs font-black text-slate-800 shadow-xs border-0 focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                </div>

                <!-- To Date Picker -->
                <div class="inline-flex items-center gap-1.5 rounded-2xl bg-slate-100 p-1.5 border border-slate-200/80">
                    <label for="to-date-input" class="pl-2 text-xs font-black text-slate-600 flex items-center gap-1 cursor-pointer">
                        <i data-lucide="calendar" class="w-4 h-4 text-slate-500"></i>
                        <span>To:</span>
                    </label>
                    <input type="date"
                           id="to-date-input"
                           name="to_date"
                           value="{{ $selectedToDate }}"
                           class="rounded-xl bg-white px-3 py-1.5 text-xs font-black text-slate-800 shadow-xs border-0 focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                </div>

                <!-- Apply Button -->
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-2xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-black transition shadow-xs cursor-pointer">
                    <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                    <span>Apply</span>
                </button>

                @if($cursor)
                    <input type="hidden" name="cursor" value="{{ $cursor }}">
                    <a href="{{ route('admin.cashbook.auto-match', ['warehouse_id' => $selectedWarehouseId, 'from_date' => $selectedFromDate, 'to_date' => $selectedToDate, 'sort' => $sort, 'direction' => $direction]) }}"
                       class="px-2.5 py-1.5 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-black transition">
                        Reset to First 100
                    </a>
                @endif
            </form>

            <!-- Right: Actions -->
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.cashbook.inventory', ['warehouse_id' => $selectedWarehouseId, 'date' => $selectedFromDate]) }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black transition">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span>Inventory Center</span>
                </a>

                <button type="button"
                        @click="refreshPreview()"
                        :disabled="isLoading"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black transition cursor-pointer disabled:opacity-50">
                    <i data-lucide="refresh-cw" class="w-4 h-4" :class="{'animate-spin': isLoading}"></i>
                    <span>Preview Match</span>
                </button>

                @if(!empty($plan['next_cursor']))
                    <a href="{{ route('admin.cashbook.auto-match', ['warehouse_id' => $selectedWarehouseId, 'from_date' => $selectedFromDate, 'to_date' => $selectedToDate, 'sort' => $sort, 'direction' => $direction, 'cursor' => $plan['next_cursor']]) }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-2xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-black transition shadow-xs cursor-pointer">
                        <span>Next 100 Bills</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                @endif

                <button type="button"
                        @click="openConfirmModal()"
                        :disabled="isLoading || isExecuting || !hasMatchableBills"
                        class="inline-flex items-center gap-2 px-5 py-2 rounded-2xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-black transition shadow-md hover:shadow-lg cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                    <i data-lucide="play" class="w-4 h-4 text-emerald-200"></i>
                    <span x-show="!isExecuting">Confirm Auto Match</span>
                    <span x-show="isExecuting">Executing...</span>
                </button>
            </div>
        </div>

        <!-- Scope Banner & Information -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100 text-xs font-bold text-slate-500">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200/80 font-black">
                    <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                    Zero Inventory Impact
                </span>
                <span>
                    @if($isDateRange)
                        Period: <strong>{{ \Carbon\Carbon::parse($selectedFromDate)->format('d M Y') }}</strong> to <strong>{{ \Carbon\Carbon::parse($selectedToDate)->format('d M Y') }}</strong>. Each date is evaluated and matched <strong>strictly within its own business day</strong>.
                    @else
                        Business Date: <strong>{{ \Carbon\Carbon::parse($selectedFromDate)->format('d M Y') }}</strong>. Only approved bills and advances on this date are matched.
                    @endif
                </span>
            </div>
            <div class="flex items-center gap-3">
                <span>Batch: <strong>100 Bills / Day</strong></span>
                @if($cursor)
                    <span class="text-indigo-600 font-black">Cursor: &gt; GRN #{{ $cursor }}</span>
                @endif
                <span class="text-slate-400">|</span>
                <span>Sort: <strong class="text-slate-800">{{ ucwords(str_replace('_', ' ', $sort)) }} ({{ strtoupper($direction) }})</strong></span>
            </div>
        </div>
    </div>

    <!-- KPI Summary Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- Total Bills on Date -->
        <div class="p-4 rounded-3xl bg-white border border-slate-200/80 shadow-xs flex flex-col justify-between">
            <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Total Bills (Period)</span>
            <div class="mt-2 flex items-baseline gap-1">
                <span class="text-2xl font-black text-slate-900" x-text="plan?.summary?.total_bills_on_date ?? '{{ $plan['summary']['total_bills_on_date'] ?? 0 }}'"></span>
                <span class="text-xs font-bold text-slate-500">bills</span>
            </div>
        </div>

        <!-- Evaluated in Batch -->
        <div class="p-4 rounded-3xl bg-white border border-slate-200/80 shadow-xs flex flex-col justify-between">
            <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Batch Evaluated</span>
            <div class="mt-2 flex items-baseline gap-1">
                <span class="text-2xl font-black text-indigo-700" x-text="plan?.summary?.batch_bills_count ?? '{{ $plan['summary']['batch_bills_count'] ?? 0 }}'"></span>
                <span class="text-xs font-bold text-slate-500">evaluated</span>
            </div>
        </div>

        <!-- Ready Full Matches -->
        <div class="p-4 rounded-3xl bg-emerald-50/60 border border-emerald-200/80 shadow-xs flex flex-col justify-between">
            <span class="text-[11px] font-black uppercase tracking-wider text-emerald-800">Ready (Full Match)</span>
            <div class="mt-2 flex items-baseline gap-1">
                <span class="text-2xl font-black text-emerald-700" x-text="plan?.summary?.ready_bills ?? '{{ $plan['summary']['ready_bills'] ?? 0 }}'"></span>
                <span class="text-xs font-bold text-emerald-600">bills</span>
            </div>
        </div>

        <!-- Partial Matches -->
        <div class="p-4 rounded-3xl bg-amber-50/60 border border-amber-200/80 shadow-xs flex flex-col justify-between">
            <span class="text-[11px] font-black uppercase tracking-wider text-amber-800">Partial Matches</span>
            <div class="mt-2 flex items-baseline gap-1">
                <span class="text-2xl font-black text-amber-700" x-text="plan?.summary?.partial_bills ?? '{{ $plan['summary']['partial_bills'] ?? 0 }}'"></span>
                <span class="text-xs font-bold text-amber-600">bills</span>
            </div>
        </div>

        <!-- Blocked Bills -->
        <div class="p-4 rounded-3xl bg-rose-50/60 border border-rose-200/80 shadow-xs flex flex-col justify-between">
            <span class="text-[11px] font-black uppercase tracking-wider text-rose-800">Blocked Bills</span>
            <div class="mt-2 flex items-baseline gap-1">
                <span class="text-2xl font-black text-rose-700" x-text="actionableBlockedBills.length"></span>
                <span class="text-xs font-bold text-rose-600">bills</span>
            </div>
        </div>

        <!-- Matched Base Quantity -->
        <div class="p-4 rounded-3xl bg-slate-900 text-white shadow-xs flex flex-col justify-between">
            <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Matchable Base</span>
            <div class="mt-2 flex items-baseline gap-1">
                <span class="text-2xl font-black text-emerald-400" x-text="formatNumber(plan?.summary?.matched_base_qty ?? '{{ $plan['summary']['matched_base_qty'] ?? 0 }}')"></span>
                <span class="text-xs font-bold text-slate-400">KG</span>
            </div>
        </div>
    </div>

    <!-- Main Navigation Tabs -->
    <div class="border-b border-slate-200">
        <nav class="flex space-x-4 overflow-x-auto pb-1" aria-label="Tabs">
            <button type="button"
                    @click="activeSection = 'ready_bills'"
                    :class="activeSection === 'ready_bills' ? 'border-emerald-600 text-emerald-700 bg-emerald-50/50' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300'"
                    class="whitespace-nowrap py-3 px-4 border-b-2 font-black text-xs rounded-t-2xl transition flex items-center gap-2 cursor-pointer">
                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
                <span>Ready Bills (Full Match)</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-emerald-100 text-emerald-800 font-black"
                      x-text="plan?.ready_bills?.length ?? '{{ count($plan['ready_bills'] ?? []) }}'"></span>
            </button>

            <button type="button"
                    @click="activeSection = 'partial_bills'"
                    :class="activeSection === 'partial_bills' ? 'border-amber-600 text-amber-700 bg-amber-50/50' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300'"
                    class="whitespace-nowrap py-3 px-4 border-b-2 font-black text-xs rounded-t-2xl transition flex items-center gap-2 cursor-pointer">
                <i data-lucide="pie-chart" class="w-4 h-4 text-amber-600"></i>
                <span>Partial Bills</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-amber-100 text-amber-800 font-black"
                      x-text="plan?.partial_bills?.length ?? '{{ count($plan['partial_bills'] ?? []) }}'"></span>
            </button>

            <button type="button"
                    @click="activeSection = 'blocked_bills'"
                    :class="activeSection === 'blocked_bills' ? 'border-rose-600 text-rose-700 bg-rose-50/50' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300'"
                    class="whitespace-nowrap py-3 px-4 border-b-2 font-black text-xs rounded-t-2xl transition flex items-center gap-2 cursor-pointer">
                <i data-lucide="alert-octagon" class="w-4 h-4 text-rose-600"></i>
                <span>Blocked Bills</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-rose-100 text-rose-800 font-black"
                      x-text="actionableBlockedBills.length"></span>
            </button>

            <button type="button"
                    @click="activeSection = 'open_advances'"
                    :class="activeSection === 'open_advances' ? 'border-indigo-600 text-indigo-700 bg-indigo-50/50' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300'"
                    class="whitespace-nowrap py-3 px-4 border-b-2 font-black text-xs rounded-t-2xl transition flex items-center gap-2 cursor-pointer">
                <i data-lucide="clock" class="w-4 h-4 text-indigo-600"></i>
                <span>Open Advances</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-indigo-100 text-indigo-800 font-black"
                      x-text="plan?.open_advances?.length ?? '{{ count($plan['open_advances'] ?? []) }}'"></span>
            </button>

            <button type="button"
                    @click="activeSection = 'inventory_without_bills'"
                    :class="activeSection === 'inventory_without_bills' ? 'border-slate-800 text-slate-900 bg-slate-100' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300'"
                    class="whitespace-nowrap py-3 px-4 border-b-2 font-black text-xs rounded-t-2xl transition flex items-center gap-2 cursor-pointer">
                <i data-lucide="layers" class="w-4 h-4 text-slate-700"></i>
                <span>Inventory Without Bills</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-200 text-slate-800 font-black"
                      x-text="plan?.inventory_without_bills?.length ?? '{{ count($plan['inventory_without_bills'] ?? []) }}'"></span>
            </button>

            <button type="button"
                    @click="activeSection = 'run_history'"
                    :class="activeSection === 'run_history' ? 'border-slate-800 text-slate-900 bg-slate-100' : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300'"
                    class="whitespace-nowrap py-3 px-4 border-b-2 font-black text-xs rounded-t-2xl transition flex items-center gap-2 cursor-pointer">
                <i data-lucide="history" class="w-4 h-4 text-slate-700"></i>
                <span>Run History</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-200 text-slate-800 font-black">{{ $recentRuns->count() }}</span>
            </button>
        </nav>
    </div>

    <!-- SECTION 1: READY BILLS (FULL MATCH) -->
    <div x-show="activeSection === 'ready_bills'" class="space-y-4">
        <template x-if="!plan || !plan.ready_bills || plan.ready_bills.length === 0">
            <div class="rounded-3xl border border-slate-200 bg-white p-12 text-center text-slate-500">
                <i data-lucide="check-circle" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>
                <h4 class="text-sm font-black text-slate-800">No Full Match Bills in this Batch</h4>
                <p class="text-xs font-bold text-slate-500 mt-1">Check Partial Bills or Blocked Bills sections.</p>
            </div>
        </template>

        <template x-if="plan && plan.ready_bills && plan.ready_bills.length > 0">
            <div class="rounded-3xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700 border-collapse">
                        <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="p-4">
                                    <a href="{{ $getSortUrl('business_date') }}" class="inline-flex items-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'business_date' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Business Date</span>
                                        @if($sort === 'business_date')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4">
                                    <a href="{{ $getSortUrl('bill_po') }}" class="inline-flex items-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'bill_po' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Bill / PO</span>
                                        @if($sort === 'bill_po')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4">Supplier</th>
                                <th class="p-4">
                                    <a href="{{ $getSortUrl('product') }}" class="inline-flex items-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'product' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Product</span>
                                        @if($sort === 'product')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-right">
                                    <a href="{{ $getSortUrl('bill_qty') }}" class="inline-flex items-center justify-end gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'bill_qty' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Bill Qty</span>
                                        @if($sort === 'bill_qty')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-right">
                                    <a href="{{ $getSortUrl('matched_qty') }}" class="inline-flex items-center justify-end gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'matched_qty' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Matched Qty</span>
                                        @if($sort === 'matched_qty')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-center">
                                    <a href="{{ $getSortUrl('unit') }}" class="inline-flex items-center justify-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'unit' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Unit</span>
                                        @if($sort === 'unit')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-center">
                                    <a href="{{ $getSortUrl('status') }}" class="inline-flex items-center justify-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'status' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Status</span>
                                        @if($sort === 'status')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-bold">
                            <template x-for="bill in plan.ready_bills" :key="bill.goods_received_id">
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="p-4 font-black text-slate-900">
                                        <span class="inline-block px-2.5 py-1 rounded-xl bg-slate-100 text-slate-800 text-xs font-black" x-text="bill.business_date || bill.bill_date"></span>
                                    </td>
                                    <td class="p-4">
                                        <div class="space-y-0.5">
                                            <a :href="'/purchasing/grns/' + encodeURIComponent(bill.grn_number)"
                                               target="_blank"
                                               class="font-black text-emerald-700 hover:text-emerald-900 hover:underline inline-flex items-center gap-1">
                                                <span x-text="bill.grn_number"></span>
                                                <i data-lucide="external-link" class="w-3 h-3 text-slate-400"></i>
                                            </a>
                                            <div class="text-[11px] text-slate-500">
                                                <template x-if="bill.purchase_order_id">
                                                    <a :href="'/purchasing/orders/' + bill.purchase_order_id"
                                                       target="_blank"
                                                       class="hover:underline text-indigo-600 font-bold"
                                                       x-text="'PO: ' + (bill.po_number || '#' + bill.purchase_order_id)"></a>
                                                </template>
                                                <template x-if="!bill.purchase_order_id">
                                                    <span x-text="'PO: ' + (bill.po_number || '—')"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-slate-700" x-text="bill.supplier_name"></td>
                                    <td class="p-4">
                                        <div class="space-y-1">
                                            <template x-for="line in bill.lines" :key="line.item_id">
                                                <div class="text-[11px] flex items-center gap-2">
                                                    <span class="font-black text-slate-800" x-text="line.product_name"></span>
                                                    <span class="text-slate-500" x-text="line.quantity + ' ' + line.unit"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="p-4 text-right text-slate-600" x-text="formatNumber(bill.required_base_qty)"></td>
                                    <td class="p-4 text-right font-black text-emerald-700" x-text="formatNumber(bill.matched_base_qty)"></td>
                                    <td class="p-4 text-center text-slate-500" x-text="bill.unit || 'KG'"></td>
                                    <td class="p-4 text-center">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800">
                                            Full Match
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>

    <!-- SECTION 2: PARTIAL BILLS -->
    <div x-show="activeSection === 'partial_bills'" class="space-y-4">
        <template x-if="!plan || !plan.partial_bills || plan.partial_bills.length === 0">
            <div class="rounded-3xl border border-slate-200 bg-white p-12 text-center text-slate-500">
                <i data-lucide="pie-chart" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>
                <h4 class="text-sm font-black text-slate-800">No Partial Match Bills in this Batch</h4>
                <p class="text-xs font-bold text-slate-500 mt-1">All matchable bills either matched fully or are blocked.</p>
            </div>
        </template>

        <template x-if="plan && plan.partial_bills && plan.partial_bills.length > 0">
            <div class="rounded-3xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700 border-collapse">
                        <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="p-4">
                                    <a href="{{ $getSortUrl('business_date') }}" class="inline-flex items-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'business_date' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Business Date</span>
                                        @if($sort === 'business_date')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4">
                                    <a href="{{ $getSortUrl('bill_po') }}" class="inline-flex items-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'bill_po' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Bill / PO</span>
                                        @if($sort === 'bill_po')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4">Supplier</th>
                                <th class="p-4">
                                    <a href="{{ $getSortUrl('product') }}" class="inline-flex items-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'product' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Product</span>
                                        @if($sort === 'product')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-right">
                                    <a href="{{ $getSortUrl('bill_qty') }}" class="inline-flex items-center justify-end gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'bill_qty' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Bill Qty</span>
                                        @if($sort === 'bill_qty')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-right">
                                    <a href="{{ $getSortUrl('matched_qty') }}" class="inline-flex items-center justify-end gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'matched_qty' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Matched Qty</span>
                                        @if($sort === 'matched_qty')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-right">
                                    <a href="{{ $getSortUrl('remaining_qty') }}" class="inline-flex items-center justify-end gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'remaining_qty' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Remaining Qty</span>
                                        @if($sort === 'remaining_qty')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-center">
                                    <a href="{{ $getSortUrl('unit') }}" class="inline-flex items-center justify-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'unit' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Unit</span>
                                        @if($sort === 'unit')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-center">
                                    <a href="{{ $getSortUrl('status') }}" class="inline-flex items-center justify-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'status' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Status</span>
                                        @if($sort === 'status')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-bold">
                            <template x-for="bill in plan.partial_bills" :key="bill.goods_received_id">
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="p-4 font-black text-slate-900">
                                        <span class="inline-block px-2.5 py-1 rounded-xl bg-slate-100 text-slate-800 text-xs font-black" x-text="bill.business_date || bill.bill_date"></span>
                                    </td>
                                    <td class="p-4">
                                        <div class="space-y-0.5">
                                            <a :href="'/purchasing/grns/' + encodeURIComponent(bill.grn_number)"
                                               target="_blank"
                                               class="font-black text-emerald-700 hover:text-emerald-900 hover:underline inline-flex items-center gap-1">
                                                <span x-text="bill.grn_number"></span>
                                                <i data-lucide="external-link" class="w-3 h-3 text-slate-400"></i>
                                            </a>
                                            <div class="text-[11px] text-slate-500">
                                                <template x-if="bill.purchase_order_id">
                                                    <a :href="'/purchasing/orders/' + bill.purchase_order_id"
                                                       target="_blank"
                                                       class="hover:underline text-indigo-600 font-bold"
                                                       x-text="'PO: ' + (bill.po_number || '#' + bill.purchase_order_id)"></a>
                                                </template>
                                                <template x-if="!bill.purchase_order_id">
                                                    <span x-text="'PO: ' + (bill.po_number || '—')"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-slate-700" x-text="bill.supplier_name"></td>
                                    <td class="p-4">
                                        <div class="space-y-1">
                                            <template x-for="line in bill.lines" :key="line.item_id">
                                                <div class="text-[11px] flex items-center gap-2">
                                                    <span class="font-black text-slate-800" x-text="line.product_name"></span>
                                                    <span class="text-slate-500" x-text="line.quantity + ' ' + line.unit"></span>
                                                    <span class="text-emerald-700 font-black">Matched: <span x-text="formatNumber(line.matched_base_qty)"></span></span>
                                                    <span class="text-amber-700 font-black">Rem: <span x-text="formatNumber(line.remaining_unmatched_base_qty)"></span></span>
                                                </div>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="p-4 text-right text-slate-600" x-text="formatNumber(bill.required_base_qty)"></td>
                                    <td class="p-4 text-right font-black text-emerald-700" x-text="formatNumber(bill.matched_base_qty)"></td>
                                    <td class="p-4 text-right font-black text-amber-700" x-text="formatNumber(bill.remaining_base_qty)"></td>
                                    <td class="p-4 text-center text-slate-500" x-text="bill.unit || 'KG'"></td>
                                    <td class="p-4 text-center">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-amber-100 text-amber-800">
                                            Partial Match
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>

    <!-- SECTION 3: BLOCKED BILLS -->
    <div x-show="activeSection === 'blocked_bills'" class="space-y-4">
        <template x-if="actionableBlockedBills.length === 0">
            <div class="rounded-3xl border border-slate-200 bg-white p-12 text-center text-slate-500">
                <i data-lucide="check" class="w-12 h-12 text-emerald-400 mx-auto mb-3"></i>
                <h4 class="text-sm font-black text-slate-800">No Blocked Bills</h4>
                <p class="text-xs font-bold text-slate-500 mt-1">Every bill in this batch was eligible for full or partial match.</p>
            </div>
        </template>

        <template x-if="actionableBlockedBills.length > 0">
            <div class="rounded-3xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700 border-collapse">
                        <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="p-4">
                                    <a href="{{ $getSortUrl('business_date') }}" class="inline-flex items-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'business_date' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Business Date</span>
                                        @if($sort === 'business_date')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4">
                                    <a href="{{ $getSortUrl('bill_po') }}" class="inline-flex items-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'bill_po' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Bill / PO</span>
                                        @if($sort === 'bill_po')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4">Supplier</th>
                                <th class="p-4">
                                    <a href="{{ $getSortUrl('product') }}" class="inline-flex items-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'product' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Product Details</span>
                                        @if($sort === 'product')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-right">
                                    <a href="{{ $getSortUrl('bill_qty') }}" class="inline-flex items-center justify-end gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'bill_qty' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Bill Qty</span>
                                        @if($sort === 'bill_qty')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-center">
                                    <a href="{{ $getSortUrl('unit') }}" class="inline-flex items-center justify-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'unit' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Unit</span>
                                        @if($sort === 'unit')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-center">
                                    <a href="{{ $getSortUrl('status') }}" class="inline-flex items-center justify-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'status' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Blocked Reason</span>
                                        @if($sort === 'status')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-bold">
                            <template x-for="bill in actionableBlockedBills" :key="bill.goods_received_id">
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="p-4 font-black text-slate-900">
                                        <span class="inline-block px-2.5 py-1 rounded-xl bg-slate-100 text-slate-800 text-xs font-black" x-text="bill.business_date || bill.bill_date"></span>
                                    </td>
                                    <td class="p-4">
                                        <div class="space-y-0.5">
                                            <a :href="'/purchasing/grns/' + encodeURIComponent(bill.grn_number)"
                                               target="_blank"
                                               class="font-black text-rose-700 hover:text-rose-900 hover:underline inline-flex items-center gap-1">
                                                <span x-text="bill.grn_number"></span>
                                                <i data-lucide="external-link" class="w-3 h-3 text-slate-400"></i>
                                            </a>
                                            <div class="text-[11px] text-slate-500">
                                                <template x-if="bill.purchase_order_id">
                                                    <a :href="'/purchasing/orders/' + bill.purchase_order_id"
                                                       target="_blank"
                                                       class="hover:underline text-indigo-600 font-bold"
                                                       x-text="'PO: ' + (bill.po_number || '#' + bill.purchase_order_id)"></a>
                                                </template>
                                                <template x-if="!bill.purchase_order_id">
                                                    <span x-text="'PO: ' + (bill.po_number || '—')"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 text-slate-700" x-text="bill.supplier_name"></td>
                                    <td class="p-4">
                                        <div class="space-y-1">
                                            <template x-for="line in (bill.lines || []).filter(l => (l.reason || l.classification || '').toUpperCase() !== 'NO_ADVANCE')" :key="line.item_id">
                                                <div class="text-[11px] flex items-center gap-2">
                                                    <span class="font-black text-slate-800" x-text="line.product_name"></span>
                                                    <span class="text-slate-500" x-text="line.quantity + ' ' + line.unit"></span>
                                                    <span class="text-rose-700 font-black" x-text="'(' + line.reason + ')'"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="p-4 text-right text-slate-600" x-text="formatNumber(bill.required_base_qty)"></td>
                                    <td class="p-4 text-center text-slate-500" x-text="bill.unit || 'KG'"></td>
                                    <td class="p-4 text-center">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black"
                                              :class="{
                                                  'bg-rose-100 text-rose-800': bill.blocked_reason === 'UNIT_DIFFERENCE_REQUIRES_CONVERSION',
                                                  'bg-amber-100 text-amber-800': bill.blocked_reason === 'ADVANCE_EXHAUSTED',
                                                  'bg-slate-100 text-slate-800': bill.blocked_reason === 'NO_ADVANCE'
                                              }"
                                              x-text="bill.blocked_reason">
                                        </span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>

    <!-- SECTION 4: OPEN ADVANCES -->
    <div x-show="activeSection === 'open_advances'" class="space-y-4">
        <template x-if="!plan || !plan.open_advances || plan.open_advances.length === 0">
            <div class="rounded-3xl border border-slate-200 bg-white p-12 text-center text-slate-500">
                <i data-lucide="clock" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>
                <h4 class="text-sm font-black text-slate-800">No Open Advances for this Period</h4>
                <p class="text-xs font-bold text-slate-500 mt-1">All warehouse advances for this date period have already been cleared.</p>
            </div>
        </template>

        <template x-if="plan && plan.open_advances && plan.open_advances.length > 0">
            <div class="rounded-3xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700 border-collapse">
                        <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="p-4">
                                    <a href="{{ $getSortUrl('business_date') }}" class="inline-flex items-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'business_date' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Business Date</span>
                                        @if($sort === 'business_date')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4">
                                    <a href="{{ $getSortUrl('bill_po') }}" class="inline-flex items-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'bill_po' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Advance GRN</span>
                                        @if($sort === 'bill_po')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4">Age</th>
                                <th class="p-4">
                                    <a href="{{ $getSortUrl('product') }}" class="inline-flex items-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'product' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Product &amp; Items</span>
                                        @if($sort === 'product')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-right">
                                    <a href="{{ $getSortUrl('advance_qty') }}" class="inline-flex items-center justify-end gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'advance_qty' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Unbilled Base</span>
                                        @if($sort === 'advance_qty')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-bold">
                            <template x-for="adv in plan.open_advances" :key="adv.id">
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="p-4 font-black text-slate-900">
                                        <span class="inline-block px-2.5 py-1 rounded-xl bg-slate-100 text-slate-800 text-xs font-black" x-text="adv.business_date || adv.received_at"></span>
                                    </td>
                                    <td class="p-4">
                                        <a :href="'/purchasing/grns/' + encodeURIComponent(adv.grn_number)"
                                           target="_blank"
                                           class="font-black text-indigo-700 hover:text-indigo-900 hover:underline inline-flex items-center gap-1">
                                            <span x-text="adv.grn_number"></span>
                                            <i data-lucide="external-link" class="w-3 h-3 text-slate-400"></i>
                                        </a>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded-lg text-[10px] font-black"
                                              :class="adv.age_days > 7 ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700'"
                                              x-text="adv.age_days + ' days'"></span>
                                    </td>
                                    <td class="p-4">
                                        <div class="space-y-1">
                                            <template x-for="item in adv.items" :key="item.id">
                                                <div class="text-[11px] flex items-center gap-2">
                                                    <span class="font-black text-slate-800" x-text="item.product_name"></span>
                                                    <span class="text-slate-500" x-text="item.received_qty + ' ' + item.unit"></span>
                                                    <span class="text-indigo-700 font-black">(&rarr; <span x-text="formatNumber(item.remaining_base_qty)"></span> KG unbilled)</span>
                                                </div>
                                            </template>
                                        </div>
                                    </td>
                                    <td class="p-4 text-right font-black text-indigo-700" x-text="formatNumber(adv.unbilled_base_qty) + ' KG'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>

    <!-- SECTION 5: INVENTORY WITHOUT BILLS -->
    <div x-show="activeSection === 'inventory_without_bills'" class="space-y-4">
        <template x-if="!plan || !plan.inventory_without_bills || plan.inventory_without_bills.length === 0">
            <div class="rounded-3xl border border-slate-200 bg-white p-12 text-center text-slate-500">
                <i data-lucide="layers" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>
                <h4 class="text-sm font-black text-slate-800">No Unbilled Inventory</h4>
                <p class="text-xs font-bold text-slate-500 mt-1">There are no open advances awaiting bills for this warehouse.</p>
            </div>
        </template>

        <template x-if="plan && plan.inventory_without_bills && plan.inventory_without_bills.length > 0">
            <div class="rounded-3xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700 border-collapse">
                        <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="p-4">
                                    <a href="{{ $getSortUrl('business_date') }}" class="inline-flex items-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'business_date' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Business Date</span>
                                        @if($sort === 'business_date')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4">
                                    <a href="{{ $getSortUrl('product') }}" class="inline-flex items-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'product' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Product</span>
                                        @if($sort === 'product')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4">SKU</th>
                                <th class="p-4">Latest Advance GRN</th>
                                <th class="p-4 text-right">
                                    <a href="{{ $getSortUrl('bill_qty') }}" class="inline-flex items-center justify-end gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'bill_qty' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Received Qty</span>
                                        @if($sort === 'bill_qty')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-right">
                                    <a href="{{ $getSortUrl('matched_qty') }}" class="inline-flex items-center justify-end gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'matched_qty' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Matched Qty</span>
                                        @if($sort === 'matched_qty')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-right">
                                    <a href="{{ $getSortUrl('remaining_qty') }}" class="inline-flex items-center justify-end gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'remaining_qty' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Remaining Qty</span>
                                        @if($sort === 'remaining_qty')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-center">
                                    <a href="{{ $getSortUrl('unit') }}" class="inline-flex items-center justify-center gap-1 group cursor-pointer">
                                        <span class="{{ $sort === 'unit' ? 'text-emerald-700 font-black' : 'text-slate-600 font-bold group-hover:text-slate-900' }}">Unit</span>
                                        @if($sort === 'unit')
                                            <i data-lucide="{{ $direction === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3.5 h-3.5 text-emerald-600"></i>
                                        @endif
                                    </a>
                                </th>
                                <th class="p-4 text-center">Age (Days)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-bold">
                            <template x-for="row in plan.inventory_without_bills" :key="row.product_id + '_' + row.unit">
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="p-4 font-black text-slate-900">
                                        <span class="inline-block px-2.5 py-1 rounded-xl bg-slate-100 text-slate-800 text-xs font-black" x-text="row.business_date || '—'"></span>
                                    </td>
                                    <td class="p-4 font-black text-slate-900" x-text="row.product_name"></td>
                                    <td class="p-4 text-slate-500" x-text="row.product_sku"></td>
                                    <td class="p-4">
                                        <template x-if="row.advance_grn_number">
                                            <a :href="'/purchasing/grns/' + encodeURIComponent(row.advance_grn_number)"
                                               target="_blank"
                                               class="font-black text-slate-800 hover:text-indigo-600 hover:underline inline-flex items-center gap-1">
                                                <span x-text="row.advance_grn_number"></span>
                                                <i data-lucide="external-link" class="w-3 h-3 text-slate-400"></i>
                                            </a>
                                        </template>
                                        <template x-if="!row.advance_grn_number">
                                            <span class="text-slate-700 font-black" x-text="row.advance_grn_number || '—'"></span>
                                        </template>
                                    </td>
                                    <td class="p-4 text-right text-slate-600" x-text="formatNumber(row.received_qty)"></td>
                                    <td class="p-4 text-right text-emerald-700 font-black" x-text="formatNumber(row.matched_qty)"></td>
                                    <td class="p-4 text-right text-indigo-700 font-black text-sm" x-text="formatNumber(row.remaining_qty)"></td>
                                    <td class="p-4 text-center text-slate-500" x-text="row.unit"></td>
                                    <td class="p-4 text-center">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black"
                                              :class="row.age_days > 7 ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700'"
                                              x-text="row.age_days + 'd'"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>

    <!-- SECTION 6: RUN HISTORY -->
    <div x-show="activeSection === 'run_history'" class="space-y-4">
        @if($recentRuns->isEmpty())
            <div class="rounded-3xl border border-slate-200 bg-white p-12 text-center text-slate-500">
                <i data-lucide="history" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>
                <h4 class="text-sm font-black text-slate-800">No Daily Match Runs Executed</h4>
                <p class="text-xs font-bold text-slate-500 mt-1">Execute a daily match to view audit run logs here.</p>
            </div>
        @else
            <div class="rounded-3xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700 border-collapse">
                        <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="p-4">Run UUID</th>
                                <th class="p-4">Bill Date</th>
                                <th class="p-4">Requested By</th>
                                <th class="p-4 text-right">Processed Bills</th>
                                <th class="p-4 text-right">Skipped Bills</th>
                                <th class="p-4 text-right">Matched Base</th>
                                <th class="p-4 text-center">Status</th>
                                <th class="p-4">Executed At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-bold">
                            @foreach($recentRuns as $run)
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="p-4 font-mono text-[11px] text-slate-600">{{ substr($run->public_uuid ?? '', 0, 8) }}...</td>
                                    <td class="p-4 font-black text-slate-900">{{ $run->bill_date?->toDateString() }}</td>
                                    <td class="p-4 text-slate-700">{{ $run->requestedBy?->name ?? 'System' }}</td>
                                    <td class="p-4 text-right text-emerald-700 font-black">{{ $run->result_summary['processed'] ?? 0 }}</td>
                                    <td class="p-4 text-right text-amber-700 font-black">{{ $run->result_summary['skipped'] ?? 0 }}</td>
                                    <td class="p-4 text-right font-black text-slate-900">{{ number_format((float) ($run->result_summary['matched_base_qty'] ?? 0), 2) }} KG</td>
                                    <td class="p-4 text-center">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black {{ $run->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($run->status === 'partial' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">
                                            {{ strtoupper($run->status) }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-slate-500">{{ $run->completed_at ? $run->completed_at->diffForHumans() : $run->created_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <!-- EXECUTION CONFIRMATION MODAL -->
    <div x-show="confirmModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity">
        <div @click.away="closeConfirmModal()"
             class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col">
            <!-- Modal Header -->
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-emerald-50/50">
                <div>
                    <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                        <i data-lucide="sparkles" class="w-5 h-5 text-emerald-600"></i>
                        Confirm Daily Auto Match
                    </h3>
                    <p class="text-xs font-bold text-slate-500 mt-0.5">Warehouse: {{ $availableWarehouses->firstWhere('id', $selectedWarehouseId)?->name }} | Period: {{ $selectedFromDate }} to {{ $selectedToDate }}</p>
                </div>
                <button type="button" @click="closeConfirmModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-xl">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-4 text-xs font-bold text-slate-600">
                <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200/60 space-y-2">
                    <div class="flex items-center justify-between">
                        <span>Total Bills to Reconcile:</span>
                        <span class="font-black text-slate-900 text-sm" x-text="((plan?.ready_bills?.length || 0) + (plan?.partial_bills?.length || 0)) + ' bills'"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Total Matched Quantity:</span>
                        <span class="font-black text-emerald-700 text-sm" x-text="formatNumber(plan?.summary?.matched_base_qty || 0) + ' KG'"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Full Advances to Clear:</span>
                        <span class="font-black text-indigo-700 text-sm" x-text="(plan?.summary?.advances_fully_cleared || 0) + ' advances'"></span>
                    </div>
                </div>

                <p class="text-[11px] text-slate-500">
                    Executing will create non-destructive <code>AdvanceReceiveMatch</code> audit records across the selected period. Each date is processed independently with zero stock mutations.
                </p>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-2 bg-slate-50">
                <button type="button"
                        @click="closeConfirmModal()"
                        class="px-4 py-2 rounded-xl text-xs font-black text-slate-600 hover:bg-slate-200 transition">
                    Cancel
                </button>
                <button type="button"
                        @click="executeMatch()"
                        :disabled="isExecuting"
                        class="px-5 py-2 rounded-xl text-xs font-black bg-emerald-700 hover:bg-emerald-800 text-white transition shadow-sm cursor-pointer disabled:opacity-50">
                    <span x-show="!isExecuting">Execute Auto Match</span>
                    <span x-show="isExecuting">Executing Match...</span>
                </button>
            </div>
        </div>
    </div>

    <!-- EXECUTION RESULT MODAL -->
    <div x-show="resultModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity">
        <div @click.away="closeResultModal()"
             class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-emerald-50">
                <div>
                    <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                        <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
                        Daily Match Execution Completed
                    </h3>
                    <p class="text-xs font-bold text-slate-500 mt-0.5">Summary of reconciliations processed</p>
                </div>
                <button type="button" @click="closeResultModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-xl">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="p-6 space-y-4 text-xs font-bold text-slate-600">
                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200/80">
                        <span class="text-[10px] uppercase tracking-wider text-slate-400 font-black">Processed Bills</span>
                        <div class="text-xl font-black text-emerald-700 mt-1" x-text="executionResult?.summary?.processed || 0"></div>
                    </div>
                    <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200/80">
                        <span class="text-[10px] uppercase tracking-wider text-slate-400 font-black">Skipped Bills</span>
                        <div class="text-xl font-black text-amber-700 mt-1" x-text="executionResult?.summary?.skipped || 0"></div>
                    </div>
                    <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200/80">
                        <span class="text-[10px] uppercase tracking-wider text-slate-400 font-black">Matched Base</span>
                        <div class="text-xl font-black text-slate-900 mt-1" x-text="formatNumber(executionResult?.summary?.matched_base_qty || 0) + ' KG'"></div>
                    </div>
                    <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200/80">
                        <span class="text-[10px] uppercase tracking-wider text-slate-400 font-black">Advances Cleared</span>
                        <div class="text-xl font-black text-indigo-700 mt-1" x-text="executionResult?.summary?.advances_fully_cleared || 0"></div>
                    </div>
                </div>

                <template x-if="executionResult?.skipped && executionResult.skipped.length > 0">
                    <div class="space-y-2">
                        <span class="text-[11px] font-black text-amber-800">Skipped Bills Details:</span>
                        <div class="max-h-32 overflow-y-auto space-y-1 text-[11px] text-slate-600 bg-amber-50/50 p-2.5 rounded-xl border border-amber-200/60">
                            <template x-for="item in executionResult.skipped" :key="item.item_id">
                                <div class="flex items-center justify-between">
                                    <span class="font-black text-slate-800" x-text="item.grn_number || ('GRN #' + item.goods_received_id)"></span>
                                    <span class="text-amber-700 font-black" x-text="item.reason_code"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-2 bg-slate-50">
                <button type="button"
                        @click="closeResultModalAndReload()"
                        class="px-5 py-2 rounded-xl text-xs font-black bg-slate-900 text-white hover:bg-slate-800 transition">
                    Done &amp; Refresh
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function dailyAutoMatchComponent() {
    return {
        csrfToken: '{{ csrf_token() }}',
        warehouseId: {{ $selectedWarehouseId }},
        fromDate: '{{ $selectedFromDate }}',
        toDate: '{{ $selectedToDate }}',
        sort: '{{ $sort }}',
        direction: '{{ $direction }}',
        cursor: {{ $cursor ? $cursor : 'null' }},
        batchSize: {{ $batchSize }},
        previewUrl: '{{ route('admin.cashbook.auto-match.preview') }}',
        executeUrl: '{{ route('admin.cashbook.auto-match.execute') }}',
        activeSection: 'ready_bills',
        isLoading: false,
        isExecuting: false,
        confirmModalOpen: false,
        resultModalOpen: false,
        plan: @json($plan),
        executionResult: null,

        init() {
            if (window.lucide) {
                this.$nextTick(() => window.lucide.createIcons());
            }
            this.$watch('activeSection', () => {
                this.$nextTick(() => {
                    if (window.lucide) { window.lucide.createIcons(); }
                });
            });
            this.$watch('plan', () => {
                this.$nextTick(() => {
                    if (window.lucide) { window.lucide.createIcons(); }
                });
            });
        },

        get hasMatchableBills() {
            if (!this.plan) return false;
            const readyCount = this.plan.ready_bills ? this.plan.ready_bills.length : 0;
            const partialCount = this.plan.partial_bills ? this.plan.partial_bills.length : 0;
            return (readyCount + partialCount) > 0;
        },

        get actionableBlockedBills() {
            if (!this.plan || !this.plan.blocked_bills) return [];
            return this.plan.blocked_bills.filter(bill => {
                const reason = (bill.blocked_reason || bill.reason || '').toUpperCase();
                return reason !== 'NO_ADVANCE' && reason !== 'NONE';
            });
        },

        formatNumber(val) {
            const num = parseFloat(val);
            if (isNaN(num)) return '0.00';
            return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        async refreshPreview() {
            this.isLoading = true;
            try {
                const params = new URLSearchParams({
                    warehouse_id: this.warehouseId,
                    from_date: this.fromDate,
                    to_date: this.toDate,
                    sort: this.sort,
                    direction: this.direction,
                    batch_size: this.batchSize,
                });
                if (this.cursor) {
                    params.append('cursor', this.cursor);
                }

                const res = await fetch(`${this.previewUrl}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await res.json();
                if (data.status === 'success') {
                    this.plan = data.data;
                } else {
                    alert(data.message || 'Failed to refresh preview.');
                }
            } catch (err) {
                console.error(err);
                alert('Network error while refreshing preview.');
            } finally {
                this.isLoading = false;
                if (window.lucide) {
                    this.$nextTick(() => window.lucide.createIcons());
                }
            }
        },

        openConfirmModal() {
            if (!this.hasMatchableBills) return;
            this.confirmModalOpen = true;
            if (window.lucide) {
                this.$nextTick(() => window.lucide.createIcons());
            }
        },

        closeConfirmModal() {
            this.confirmModalOpen = false;
        },

        closeResultModal() {
            this.resultModalOpen = false;
        },

        closeResultModalAndReload() {
            this.resultModalOpen = false;
            window.location.reload();
        },

        generateClientUuid() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        },

        async executeMatch() {
            if (this.isExecuting || !this.plan?.plan_hash) return;
            this.isExecuting = true;

            try {
                const payload = {
                    warehouse_id: this.warehouseId,
                    from_date: this.fromDate,
                    to_date: this.toDate,
                    plan_hash: this.plan.plan_hash,
                    client_submission_id: this.generateClientUuid(),
                    batch_size: this.batchSize,
                };
                if (this.cursor) {
                    payload.cursor = this.cursor;
                }

                const res = await fetch(this.executeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (res.status === 409) {
                    this.closeConfirmModal();
                    alert('Preview has changed due to concurrent operations. Refreshing preview now.');
                    await this.refreshPreview();
                    return;
                }

                if (data.status === 'success') {
                    this.executionResult = data.data;
                    this.closeConfirmModal();
                    this.resultModalOpen = true;
                } else {
                    alert(data.message || 'Execution failed.');
                }
            } catch (err) {
                console.error(err);
                alert('Network error while executing auto match.');
            } finally {
                this.isExecuting = false;
                if (window.lucide) {
                    this.$nextTick(() => window.lucide.createIcons());
                }
            }
        }
    };
}
</script>
@endsection
