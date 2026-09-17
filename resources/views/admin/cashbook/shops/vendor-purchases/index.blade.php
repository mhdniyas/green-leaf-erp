@extends('admin.cashbook.layouts.app')

@section('title', 'Vendor Purchases — ' . $currentShop->name)

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-16">
    <!-- ── 1. HEADER & CONTROLS ────────────────────────────────────────── -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('admin.cashbook.shop.show', ['shop' => $currentShop->slug ?: $currentShop->shop_id, 'month' => $filters['month']]) }}"
                   class="inline-flex items-center gap-1 text-xs font-bold text-slate-500 hover:text-emerald-700 transition">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    <span>Back to Cashbook</span>
                </a>
                <span class="text-slate-300">&middot;</span>
                <span class="px-2 py-0.5 rounded-md bg-slate-100 text-[11px] font-black tracking-wider uppercase text-slate-600">
                    {{ $currentShop->code }}
                </span>
            </div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 uppercase flex items-center gap-2">
                <span>Vendor Purchases</span>
                <span class="text-base font-normal text-slate-400 font-mono">&mdash; {{ $currentShop->name }}</span>
            </h1>
            <p class="text-xs text-slate-500 font-medium mt-0.5">
                Detailed read-only vendor procurement report, vendor/product aggregations, and daily purchase audit.
            </p>
        </div>

        <!-- Shop Dropdown Selector -->
        <div class="flex items-center gap-2">
            <div>
                <select onchange="window.location.href='/admin/cashbook/shops/' + this.value + '/purchases/vendors?period={{ $filters['period'] }}&start_date={{ $filters['start_date'] }}&end_date={{ $filters['end_date'] }}&month={{ $filters['month'] }}'"
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

    <!-- ── 2. PERIOD CONTROLS & FILTER BAR ─────────────────────────────── -->
    <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm space-y-4">
        <!-- Quick Period Tabs -->
        @php
            $currentPeriod = $filters['period'];
            $baseRouteParams = [
                'shop' => $currentShop->slug ?: $currentShop->shop_id,
                'vendor_id' => $filters['vendor_id'],
                'category_id' => $filters['category_id'],
                'payment' => $filters['payment'],
                'search' => $filters['search'],
            ];
            $todayStr = now('Asia/Kolkata')->toDateString();
            $yesterdayStr = now('Asia/Kolkata')->subDay()->toDateString();
            $monthStart = now('Asia/Kolkata')->startOfMonth()->toDateString();
            $monthEnd = now('Asia/Kolkata')->endOfMonth()->toDateString();
        @endphp

        <form method="GET" action="{{ route('admin.cashbook.shop.purchases.vendors', ['shop' => $currentShop->slug ?: $currentShop->shop_id]) }}" class="space-y-4">
            <div class="flex items-center justify-between flex-wrap gap-3 border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2 overflow-x-auto pb-1 max-w-full">
                    <a href="{{ route('admin.cashbook.shop.purchases.vendors', array_merge($baseRouteParams, ['period' => 'today', 'start_date' => $todayStr, 'end_date' => $todayStr])) }}"
                       class="px-3.5 py-1.5 rounded-xl text-xs font-black transition {{ $currentPeriod === 'today' ? 'bg-emerald-700 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        Today
                    </a>
                    <a href="{{ route('admin.cashbook.shop.purchases.vendors', array_merge($baseRouteParams, ['period' => 'yesterday', 'start_date' => $yesterdayStr, 'end_date' => $yesterdayStr])) }}"
                       class="px-3.5 py-1.5 rounded-xl text-xs font-black transition {{ $currentPeriod === 'yesterday' ? 'bg-emerald-700 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        Yesterday
                    </a>
                    <a href="{{ route('admin.cashbook.shop.purchases.vendors', array_merge($baseRouteParams, ['period' => 'month', 'start_date' => $monthStart, 'end_date' => $monthEnd, 'month' => now('Asia/Kolkata')->format('Y-m')])) }}"
                       class="px-3.5 py-1.5 rounded-xl text-xs font-black transition {{ $currentPeriod === 'month' ? 'bg-emerald-700 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        This Month
                    </a>
                    <span class="px-3.5 py-1.5 rounded-xl text-xs font-black {{ $currentPeriod === 'custom' ? 'bg-emerald-700 text-white shadow-xs' : 'bg-slate-100 text-slate-600' }}">
                        Custom Period
                    </span>
                </div>

                <div class="text-xs font-mono text-slate-500 font-bold">
                    {{ \Illuminate\Support\Carbon::parse($filters['start_date'])->format('d M Y') }} &mdash; {{ \Illuminate\Support\Carbon::parse($filters['end_date'])->format('d M Y') }}
                </div>
            </div>

            <input type="hidden" name="period" value="custom">

            <!-- Filter Controls Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 pt-1">
                <!-- From Date -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">From Date</label>
                    <input type="date" name="start_date" value="{{ $filters['start_date'] }}"
                           class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                </div>

                <!-- To Date -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">To Date</label>
                    <input type="date" name="end_date" value="{{ $filters['end_date'] }}"
                           class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                </div>

                <!-- Vendor Filter -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Vendor</label>
                    <select name="vendor_id" class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 cursor-pointer">
                        <option value="">All Vendors</option>
                        @foreach($report['options']['vendors'] as $v)
                            <option value="{{ $v->id }}" {{ (int)$filters['vendor_id'] === (int)$v->id ? 'selected' : '' }}>
                                {{ $v->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Category Filter -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Category</label>
                    <select name="category_id" class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 cursor-pointer">
                        <option value="">All Categories</option>
                        @foreach($report['options']['categories'] as $cat)
                            <option value="{{ $cat->id }}" {{ (int)$filters['category_id'] === (int)$cat->id ? 'selected' : '' }}>
                                {{ $cat->name }} {{ $cat->header_name ? "({$cat->header_name})" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Payment Method Filter -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Payment</label>
                    <select name="payment" class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 cursor-pointer">
                        <option value="all" {{ $filters['payment'] === 'all' ? 'selected' : '' }}>All Payments</option>
                        <option value="cash" {{ $filters['payment'] === 'cash' ? 'selected' : '' }}>Cash Only</option>
                        <option value="credit" {{ $filters['payment'] === 'credit' ? 'selected' : '' }}>Credit Only</option>
                    </select>
                </div>

                <!-- Search & Actions -->
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Search</label>
                        <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Product, Vendor..."
                               class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl text-xs font-black transition shadow-xs cursor-pointer">
                        Apply
                    </button>
                    <a href="{{ route('admin.cashbook.shop.purchases.vendors', ['shop' => $currentShop->slug ?: $currentShop->shop_id]) }}"
                       title="Reset Filters"
                       class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-black transition">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- ── 3. TOP SUMMARY CARDS ────────────────────────────────────────── -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Total Purchase Card -->
        <div class="p-5 rounded-3xl bg-white border border-slate-200 shadow-sm space-y-2">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <span class="text-[11px] font-black uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
                    <i data-lucide="shopping-bag" class="w-3.5 h-3.5 text-emerald-600"></i>
                    Total Purchases
                </span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 font-mono">
                    {{ $report['summary']['invoice_count'] }} {{ Str::plural('bill', $report['summary']['invoice_count']) }}
                </span>
            </div>
            <div>
                <p class="text-3xl font-black font-mono text-slate-900">
                    ₹{{ number_format($report['summary']['total_purchase'], 2) }}
                </p>
                <p class="text-xs text-slate-500 font-medium mt-1">
                    {{ $report['summary']['vendor_count'] }} {{ Str::plural('vendor', $report['summary']['vendor_count']) }} &middot; {{ $report['summary']['product_count'] }} {{ Str::plural('product', $report['summary']['product_count']) }}
                </p>
            </div>
        </div>

        <!-- Cash Purchases Card -->
        <div class="p-5 rounded-3xl bg-white border border-slate-200 shadow-sm space-y-2">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <span class="text-[11px] font-black uppercase tracking-wider text-emerald-700 flex items-center gap-1.5">
                    <i data-lucide="banknote" class="w-3.5 h-3.5 text-emerald-600"></i>
                    Cash Purchases
                </span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 font-bold">
                    Paid from Cashbook
                </span>
            </div>
            <div>
                <p class="text-3xl font-black font-mono text-emerald-700">
                    ₹{{ number_format($report['summary']['cash_purchase'], 2) }}
                </p>
                <p class="text-xs text-slate-500 font-medium mt-1">
                    Direct cash settlement via Cashbook category
                </p>
            </div>
        </div>

        <!-- Credit Purchases Card -->
        <div class="p-5 rounded-3xl bg-white border border-slate-200 shadow-sm space-y-2">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <span class="text-[11px] font-black uppercase tracking-wider text-amber-700 flex items-center gap-1.5">
                    <i data-lucide="clock" class="w-3.5 h-3.5 text-amber-600"></i>
                    Credit Purchases
                </span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 font-bold">
                    Payable Obligations
                </span>
            </div>
            <div>
                <p class="text-3xl font-black font-mono text-amber-700">
                    ₹{{ number_format($report['summary']['credit_purchase'], 2) }}
                </p>
                <p class="text-xs text-slate-500 font-medium mt-1">
                    Recorded to Shop Vendor Payables
                </p>
            </div>
        </div>
    </div>

    <!-- ── 4. AGGREGATION TABLES: VENDOR & PRODUCT SUMMARIES ────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Vendor Summary Card -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <i data-lucide="truck" class="w-4 h-4 text-emerald-600"></i>
                    <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">
                        Vendor Summary
                    </h2>
                </div>
                <span class="text-xs font-mono font-bold text-slate-400">
                    {{ count($report['vendor_summary']) }} {{ Str::plural('Vendor', count($report['vendor_summary'])) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            <th class="pb-2">Vendor</th>
                            <th class="pb-2 text-center">Bills</th>
                            <th class="pb-2 text-right">Qty</th>
                            <th class="pb-2 text-right">Cash</th>
                            <th class="pb-2 text-right">Credit</th>
                            <th class="pb-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($report['vendor_summary'] as $v)
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-2.5 font-bold text-slate-900">
                                    {{ $v->supplier_name }}
                                </td>
                                <td class="py-2.5 text-center font-mono text-slate-500 font-bold">
                                    {{ $v->purchase_count }}
                                </td>
                                <td class="py-2.5 text-right font-mono text-slate-700 font-bold">
                                    {{ $v->qty_formatted }}
                                </td>
                                <td class="py-2.5 text-right font-mono text-emerald-700 font-bold">
                                    ₹{{ number_format($v->cash_purchase, 2) }}
                                </td>
                                <td class="py-2.5 text-right font-mono text-amber-700 font-bold">
                                    ₹{{ number_format($v->credit_purchase, 2) }}
                                </td>
                                <td class="py-2.5 text-right font-mono font-black text-slate-900">
                                    ₹{{ number_format($v->total_purchase, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-400 font-medium">
                                    No vendor purchases recorded for this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Product Summary Card -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <i data-lucide="package" class="w-4 h-4 text-emerald-600"></i>
                    <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">
                        Product Summary
                    </h2>
                </div>
                <span class="text-xs font-mono font-bold text-slate-400">
                    {{ count($report['product_summary']) }} {{ Str::plural('Product', count($report['product_summary'])) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            <th class="pb-2">Product</th>
                            <th class="pb-2 text-right">Total Qty</th>
                            <th class="pb-2 text-right">Avg Buy Rate</th>
                            <th class="pb-2 text-right">Total Purchase</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($report['product_summary'] as $p)
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-2.5 font-bold text-slate-900">
                                    {{ $p->product_name }}
                                </td>
                                <td class="py-2.5 text-right font-mono text-slate-700 font-bold">
                                    {{ $p->qty_formatted }}
                                </td>
                                <td class="py-2.5 text-right font-mono text-emerald-700 font-extrabold">
                                    {{ $p->avg_buy_formatted }}
                                </td>
                                <td class="py-2.5 text-right font-mono font-black text-slate-900">
                                    ₹{{ number_format($p->total_purchase, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-slate-400 font-medium">
                                    No product purchases recorded for this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── 5. DAILY PURCHASE DETAILS (GROUPED VIEW) ────────────────────── -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <i data-lucide="calendar" class="w-4 h-4 text-emerald-600"></i>
                <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">
                    Daily Purchase Details
                </h2>
            </div>
            <span class="text-xs font-mono font-bold text-slate-400">
                Grouped by Business Date &amp; Vendor
            </span>
        </div>

        <div class="space-y-6">
            @forelse($report['daily_details'] as $day)
                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 space-y-4">
                    <!-- Day Header Bar -->
                    <div class="flex items-center justify-between border-b border-slate-200 pb-2.5 flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <span class="p-1 rounded-lg bg-slate-200 text-slate-700">
                                <i data-lucide="calendar-days" class="w-3.5 h-3.5"></i>
                            </span>
                            <span class="text-xs font-black text-slate-900 uppercase">
                                {{ $day->formatted_date }}
                            </span>
                            <span class="text-[11px] font-bold text-slate-400">
                                ({{ $day->day_of_week }})
                            </span>
                        </div>

                        <!-- Day Totals Badges -->
                        <div class="flex items-center gap-2 text-xs font-mono">
                            <span class="text-slate-500 font-bold">
                                Day Total: <strong class="text-slate-900 font-black">₹{{ number_format($day->day_total, 2) }}</strong>
                            </span>
                            <span class="text-slate-300">&middot;</span>
                            <span class="text-emerald-700 font-bold">
                                Cash: ₹{{ number_format($day->day_cash, 2) }}
                            </span>
                            <span class="text-slate-300">&middot;</span>
                            <span class="text-amber-700 font-bold">
                                Credit: ₹{{ number_format($day->day_credit, 2) }}
                            </span>
                        </div>
                    </div>

                    <!-- Invoices on this Day -->
                    <div class="space-y-3">
                        @foreach($day->invoices as $inv)
                            <div class="bg-white rounded-xl border border-slate-200 p-3.5 space-y-3 shadow-2xs">
                                <!-- Invoice Meta Row -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs font-black text-slate-900">
                                            {{ $inv->supplier_name }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase font-mono {{ $inv->payment_method === 'Credit' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                            {{ $inv->payment_method }}
                                        </span>
                                        <!-- Category Context Badge -->
                                        @if($inv->has_category)
                                            <span class="px-2 py-0.5 rounded bg-slate-100 text-[10px] font-bold text-slate-700 flex items-center gap-1">
                                                <span>Category: {{ $inv->category_name }}</span>
                                                @if($inv->header_name)
                                                    <span class="text-slate-400">&middot;</span>
                                                    <span>{{ $inv->header_name }}</span>
                                                @endif
                                                @if($inv->settlement_name)
                                                    <span class="text-slate-400">&middot;</span>
                                                    <span class="text-indigo-700 font-semibold">{{ $inv->settlement_name }}</span>
                                                @endif
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded bg-slate-100 text-[10px] font-medium text-slate-500">
                                                Legacy Vendor Purchase
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-3 text-xs font-mono">
                                        @if($inv->bill_number)
                                            <span class="text-slate-500 text-[11px]">
                                                Bill #<strong class="text-slate-700">{{ $inv->bill_number }}</strong>
                                            </span>
                                        @endif
                                        <span class="text-slate-500 text-[11px]">
                                            Inv: <strong class="text-slate-700">{{ $inv->invoice_number }}</strong>
                                        </span>
                                        <span class="font-black text-slate-900">
                                            ₹{{ number_format($inv->net_amount, 2) }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Items List -->
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs">
                                        <thead>
                                            <tr class="text-[10px] font-black uppercase text-slate-400 border-b border-slate-100">
                                                <th class="pb-1.5">Product</th>
                                                <th class="pb-1.5 text-center">Grade</th>
                                                <th class="pb-1.5 text-right">Quantity</th>
                                                <th class="pb-1.5 text-right">Rate / Avg Buy</th>
                                                <th class="pb-1.5 text-right">Line Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-50 font-mono">
                                            @foreach($inv->items as $item)
                                                <tr>
                                                    <td class="py-1.5 font-sans font-bold text-slate-800">
                                                        {{ $item->product_name }}
                                                    </td>
                                                    <td class="py-1.5 text-center text-slate-500 font-bold">
                                                        {{ $item->grade }}
                                                    </td>
                                                    <td class="py-1.5 text-right text-slate-700 font-bold">
                                                        {{ $item->qty_formatted }}
                                                    </td>
                                                    <td class="py-1.5 text-right text-emerald-700 font-bold">
                                                        {{ $item->effective_rate_formatted }}
                                                    </td>
                                                    <td class="py-1.5 text-right font-black text-slate-900">
                                                        ₹{{ number_format($item->line_total, 2) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="py-12 text-center text-slate-400 font-medium bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
                    No purchase details found for the selected filters.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
