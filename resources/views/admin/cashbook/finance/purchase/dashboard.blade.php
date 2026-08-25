@extends('admin.cashbook.layouts.app')

@php
    $produceType = $filters['warehouse_code'] === 'VEG-WH' ? 'vegetables' : ($filters['warehouse_code'] === 'FRT-WH' ? 'fruits' : 'all');
    $activePurchaseTab = 'overview';
    $purchaseContext = ['period' => $filters['period'], 'produce_type' => $produceType];
    if (in_array($filters['period'], ['custom', 'between', 'range'], true)) {
        $purchaseContext += ['start_date' => $filters['start_date'], 'end_date' => $filters['end_date']];
    }
@endphp

@section('title', 'Purchase - Cashbook')
@section('header_title')
    <i data-lucide="shopping-basket" class="h-5 w-5 text-emerald-600"></i> Purchase
@endsection

@section('header_subtitle')
    Procurement overview
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-5">
    @include('admin.cashbook.finance.purchase._nav')
    @include('admin.cashbook.finance.purchase._dashboard-tabs')

    <section class="flex flex-col gap-4 border-b border-slate-200 pb-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Purchase</p>
            <h1 class="mt-1 text-2xl font-black text-slate-950">What are we purchasing right now?</h1>
            <p class="mt-1 text-sm font-semibold text-slate-500">{{ $filters['start_date'] }} to {{ $filters['end_date'] }}</p>
        </div>

        <form method="GET" action="{{ route('admin.cashbook.finance.purchase') }}" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end xl:justify-end">
            @include('admin.cashbook.finance.purchase._period-controls', [
                'periodRoute' => 'admin.cashbook.finance.purchase',
                'periodBaseQuery' => ['produce_type' => $produceType],
            ])

            <select name="produce_type" onchange="this.form.submit()" class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                <option value="all" @selected($produceType === 'all')>All produce</option>
                <option value="vegetables" @selected($produceType === 'vegetables')>Vegetables</option>
                <option value="fruits" @selected($produceType === 'fruits')>Fruits</option>
            </select>
        </form>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Total Purchase', $dashboard['summary']->total_purchase, []],
            ['Cash Purchase', $dashboard['summary']->cash_purchase, ['payment' => 'cash']],
            ['Credit Purchase', $dashboard['summary']->credit_purchase, ['payment' => 'credit']],
            ['Credit Outstanding', $dashboard['summary']->credit_outstanding, ['payment' => 'credit']],
        ] as [$label, $value, $extra])
            <a href="{{ route('admin.cashbook.finance.purchase.invoices', $purchaseContext + $extra) }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm hover:border-emerald-300">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">{{ $label }}</span>
                <strong class="mt-2 block font-mono text-xl text-slate-950">₹{{ number_format((float) $value, 2) }}</strong>
            </a>
        @endforeach
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Purchasers', $dashboard['summary']->purchaser_count, 'admin.cashbook.finance.purchase.purchasers'],
            ['Vendors', $dashboard['summary']->vendor_count, 'admin.cashbook.finance.purchase.vendors'],
            ['Invoices', $dashboard['summary']->invoice_count, 'admin.cashbook.finance.purchase.invoices'],
            ['Categories', $dashboard['summary']->category_count, 'admin.cashbook.finance.purchase.categories'],
        ] as [$label, $value, $routeName])
            <a href="{{ route($routeName, $purchaseContext) }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm hover:border-emerald-300">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">{{ $label }}</span>
                <strong class="mt-2 block font-mono text-xl text-slate-950">{{ number_format((int) $value) }}</strong>
            </a>
        @endforeach
    </section>

    @if((int) $dashboard['summary']->invoice_count === 0)
        <section class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center shadow-sm">
            <h2 class="font-black text-slate-900">No purchases recorded for this period</h2>
            <p class="mt-1 text-sm text-slate-500">This dashboard uses the purchase business date.</p>
            <a href="{{ route('admin.cashbook.finance.purchase', ['period' => 'month', 'produce_type' => $produceType]) }}" class="mt-4 inline-flex rounded-lg bg-emerald-700 px-4 py-2 text-xs font-black text-white">View This Month</a>
        </section>
    @endif

    @if($dashboard['unsupportedLegacyCount'] > 0)
        <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs font-semibold text-amber-900">{{ $dashboard['unsupportedLegacyCount'] }} legacy invoice(s) have no reliable cart-item link and are excluded from category reporting.</p>
    @endif

    <section class="grid gap-5 xl:grid-cols-2">
        <div id="purchasers" class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 p-4">
                <h2 class="font-black text-slate-950">Purchasers Overview</h2>
                <a href="{{ route('admin.cashbook.finance.purchase.purchasers', $purchaseContext) }}" class="text-xs font-black text-emerald-700 hover:text-emerald-900">View All Purchasers</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($dashboard['purchasers'] as $purchaser)
                    <div class="grid gap-2 p-4 text-xs sm:grid-cols-[minmax(0,1.3fr)_repeat(4,minmax(0,1fr))] sm:items-center">
                        <div class="font-bold"><a class="text-emerald-700 hover:underline" href="{{ route('admin.cashbook.finance.purchase.purchasers.show', ['purchaser' => $purchaser->purchaser_public_uuid] + $purchaseContext) }}">{{ $purchaser->purchaser_name ?? 'Unassigned' }}</a></div>
                        <div><span class="text-slate-400 sm:hidden">Total </span><strong class="font-mono">₹{{ number_format((float) $purchaser->total_purchase, 2) }}</strong></div>
                        <div><span class="text-slate-400 sm:hidden">Cash </span><span class="font-mono">₹{{ number_format((float) $purchaser->cash_purchase, 2) }}</span></div>
                        <div><span class="text-slate-400 sm:hidden">Credit </span><span class="font-mono">₹{{ number_format((float) $purchaser->credit_purchase, 2) }}</span></div>
                        <div><span class="text-slate-400 sm:hidden">Invoices </span><span class="font-mono">{{ number_format((int) $purchaser->invoice_count) }}</span></div>
                    </div>
                @empty
                    <p class="p-5 text-center text-sm font-semibold text-slate-400">No purchaser activity.</p>
                @endforelse
            </div>
        </div>

        <div id="vendors" class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 p-4">
                <h2 class="font-black text-slate-950">Vendors Overview</h2>
                <a href="{{ route('admin.cashbook.finance.purchase.vendors', $purchaseContext) }}" class="text-xs font-black text-emerald-700 hover:text-emerald-900">View All Vendors</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($dashboard['vendors'] as $vendor)
                    @php($tags = $vendor->category_tags ? array_map(fn ($tag) => explode('|', $tag, 2), array_filter(explode(',', $vendor->category_tags))) : [])
                    <div class="space-y-3 p-4 text-xs">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="font-bold"><a class="text-emerald-700 hover:underline" href="{{ route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $vendor->supplier_public_uuid] + $purchaseContext) }}">{{ $vendor->supplier_name ?? 'Unassigned' }}</a></div>
                                @if($tags !== [])
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach(array_slice($tags, 0, 3) as [$categoryId, $categoryName])
                                            <a href="{{ route('admin.cashbook.finance.purchase.categories', $purchaseContext + ['vendor_id' => $vendor->supplier_id, 'search' => $categoryName]) }}" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 hover:bg-emerald-50">{{ $categoryName ?: 'Uncategorised' }}</a>
                                        @endforeach
                                        @if(count($tags) > 3)
                                            <span class="rounded-full bg-slate-900 px-2 py-0.5 text-[10px] font-bold text-white">+{{ count($tags) - 3 }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <strong class="font-mono text-slate-950">₹{{ number_format((float) $vendor->total_purchase, 2) }}</strong>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-slate-500">
                            <span>Cash <strong class="block font-mono text-slate-900">₹{{ number_format((float) $vendor->cash_purchase, 2) }}</strong></span>
                            <span>Credit <strong class="block font-mono text-slate-900">₹{{ number_format((float) $vendor->credit_purchase, 2) }}</strong></span>
                            <span>Outstanding <strong class="block font-mono text-slate-900">₹{{ number_format((float) $vendor->outstanding, 2) }}</strong></span>
                        </div>
                    </div>
                @empty
                    <p class="p-5 text-center text-sm font-semibold text-slate-400">No vendor activity.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section id="categories" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 p-4">
            <h2 class="font-black text-slate-950">Category Overview</h2>
            <a href="{{ route('admin.cashbook.finance.purchase.categories', $purchaseContext) }}" class="text-xs font-black text-emerald-700 hover:text-emerald-900">View All Categories</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[36rem] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase text-slate-500">
                    <tr><th class="p-3">Category</th><th class="p-3 text-right">Purchase Value</th><th class="p-3 text-right">Invoices</th><th class="p-3 text-right">Vendors</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($dashboard['categories'] as $category)
                        <tr>
                            <td class="p-3 font-bold"><a class="text-emerald-700 hover:underline" href="{{ route('admin.cashbook.finance.purchase.categories.show', ['category' => $category->category_id] + $purchaseContext) }}">{{ $category->category_name ?? 'Uncategorised' }}</a></td>
                            <td class="p-3 text-right font-mono font-bold">₹{{ number_format((float) $category->total_purchase, 2) }}</td>
                            <td class="p-3 text-right font-mono">{{ number_format((int) $category->invoice_count) }}</td>
                            <td class="p-3 text-right font-mono">{{ number_format((int) $category->vendor_count) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-5 text-center text-slate-400">No category activity.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section id="invoices" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 p-4">
            <h2 class="font-black text-slate-950">Recent Purchases</h2>
            <a href="{{ route('admin.cashbook.finance.purchase.invoices', $purchaseContext) }}" class="text-xs font-black text-emerald-700 hover:text-emerald-900">View All Invoices</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[48rem] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase text-slate-500">
                    <tr><th class="p-3">Date</th><th class="p-3">Invoice</th><th class="p-3">Vendor</th><th class="p-3">Purchaser</th><th class="p-3">Produce Type</th><th class="p-3">Cash / Credit</th><th class="p-3 text-right">Amount</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($dashboard['recentPurchases'] as $invoice)
                        <tr>
                            <td class="p-3 font-mono">{{ $invoice->business_date }}</td>
                            <td class="p-3 font-bold"><a class="text-emerald-700 hover:underline" href="{{ route('purchasing.invoices.show', $invoice->invoice_public_uuid) }}">{{ $invoice->invoice_number ?: 'Invoice' }}</a></td>
                            <td class="p-3">{{ $invoice->supplier_name }}</td>
                            <td class="p-3">{{ $invoice->purchaser_name }}</td>
                            <td class="p-3">{{ $invoice->produce_types }}</td>
                            <td class="p-3 uppercase">{{ $invoice->payment_class }}</td>
                            <td class="p-3 text-right font-mono font-bold">₹{{ number_format((float) $invoice->total_purchase, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-5 text-center text-slate-400">No recent purchase invoices.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
