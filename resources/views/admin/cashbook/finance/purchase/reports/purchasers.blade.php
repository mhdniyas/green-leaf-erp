@extends('admin.cashbook.layouts.app')
@section('title', 'Purchaser Report - Cashbook')
@section('header_title')
    <i data-lucide="users" class="h-5 w-5 text-emerald-600"></i> Purchaser Report
@endsection
@section('header_subtitle')
    Purchaser-wise purchase and finance activity.
@endsection
@section('content')
<div class="mx-auto max-w-[96rem] space-y-5">
    @include('admin.cashbook.finance.purchase.reports._header', ['reportName' => 'Purchaser Report', 'reportDescription' => 'Purchaser-wise purchase and finance activity.'])

    @component('admin.cashbook.finance.purchase.partials._period-filter', [
        'action' => route('admin.cashbook.finance.purchase.reports.purchasers'),
        'filters' => $filters,
        'extraParams' => array_filter([
            'purchaser_id' => $filters['purchaser_id'] ?? null,
            'vendor_id' => $filters['vendor_id'] ?? null,
            'payment' => ($filters['payment'] ?? 'all') !== 'all' ? $filters['payment'] : null,
            'product_filter' => $filters['product_filter'] ?? null,
            'category_id' => !empty($filters['category_ids']) ? $filters['category_ids'][0] : null,
            'grade' => $filters['grade'] ?? null,
            'search' => $filters['search'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''),
    ])
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7 items-end">
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Product Filter</label>
                <select name="product_filter" onchange="this.form.submit()" class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">All Products</option>
                    @foreach($productFilters as $filter)
                        <option value="{{ $filter->uuid }}" @selected(($filters['product_filter'] ?? null) === $filter->uuid)>{{ $filter->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Purchaser</label>
                <select name="purchaser_id" onchange="this.form.submit()" class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">All Purchasers</option>
                    @foreach($options['purchasers'] as $purchaser)
                        <option value="{{ $purchaser->id }}" @selected(($filters['purchaser_id'] ?? null) === $purchaser->id)>{{ $purchaser->label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Vendor</label>
                <select name="vendor_id" onchange="this.form.submit()" class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">All Vendors</option>
                    @foreach($options['vendors'] as $vendor)
                        <option value="{{ $vendor->id }}" @selected(($filters['vendor_id'] ?? null) === $vendor->id)>{{ $vendor->label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Payment</label>
                <select name="payment" onchange="this.form.submit()" class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="all">All Payments</option>
                    <option value="cash" @selected(($filters['payment'] ?? 'all') === 'cash')>Cash</option>
                    <option value="credit" @selected(($filters['payment'] ?? 'all') === 'credit')>Credit</option>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Category</label>
                <select name="category_id" onchange="this.form.submit()" class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">All Categories</option>
                    @foreach($options['categories'] as $category)
                        <option value="{{ $category->id }}" @selected(in_array($category->id, $filters['category_ids'] ?? [], true))>{{ $category->label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Grade</label>
                <select name="grade" onchange="this.form.submit()" class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">All Grades</option>
                    <option value="A" @selected(($filters['grade'] ?? null) === 'A')>Grade A</option>
                    <option value="B" @selected(($filters['grade'] ?? null) === 'B')>Grade B</option>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Purchaser, invoice, vendor, product..."
                       class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500" />
            </div>
        </div>

        <div class="flex items-center gap-2 pt-3 border-t border-slate-100">
            <button type="submit" class="inline-flex min-h-9 items-center gap-1.5 rounded-xl bg-emerald-700 px-4 text-xs font-black text-white hover:bg-emerald-800 shadow-xs cursor-pointer">
                <i data-lucide="filter" class="h-3.5 w-3.5"></i> Apply
            </button>
            <a href="{{ route('admin.cashbook.finance.purchase.reports.purchasers') }}" class="inline-flex min-h-9 items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                <i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i>
            </a>
        </div>
    @endcomponent
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">@foreach(['Total Purchase' => $report['summary']->total_purchase, 'Cash Purchase' => $report['summary']->cash_purchase, 'Credit Purchase' => $report['summary']->credit_purchase, 'Credit Paid' => $report['summary']->credit_paid, 'Credit Outstanding' => $report['summary']->credit_outstanding] as $label => $value)<div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><span class="text-[10px] font-black uppercase text-slate-400">{{ $label }}</span><strong class="mt-2 block font-mono text-lg text-slate-950">₹{{ number_format((float) $value, 2) }}</strong></div>@endforeach</section>
    @include('admin.cashbook.finance.purchase._summary-table', ['title' => 'Purchasers', 'rows' => $report['purchasers'], 'kind' => 'purchaser'])
    @include('admin.cashbook.finance.purchase._invoice-table', ['invoices' => $report['invoices'], 'title' => 'Invoice and Vendor Details'])
</div>
@endsection
