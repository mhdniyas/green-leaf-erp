@extends('admin.cashbook.layouts.app')

@php
    $labels = ['purchasers' => 'Purchasers', 'vendors' => 'Vendors', 'categories' => 'Categories', 'invoices' => 'Invoices'];
    $questions = [
        'purchasers' => 'Purchaser Procurement & Funding Status',
        'vendors' => 'Supplier Directory & Purchases Breakdown',
        'categories' => 'Product Categories & Volume Distribution',
        'invoices' => 'Purchase Invoices Ledger & Settlement Tracking',
    ];
    $activePurchaseTab = $section;
    $summary = $sectionData['summary'];
    $sectionRoute = 'admin.cashbook.finance.purchase.'.$section;
    $cardQuery = request()->except(['page', 'payment']);
    $sectionCardUrl = fn (string $payment = 'all'): string => route($sectionRoute, array_merge($cardQuery, ['payment' => $payment])).'#purchase-results';

    $cards = match($section) {
        'purchasers' => [
            ['Total Purchase', $summary->total_purchase, true, $sectionCardUrl(), 'emerald'],
            ['Cash Purchase', $summary->cash_purchase, true, $sectionCardUrl('cash'), 'teal'],
            ['Credit Purchase', $summary->credit_purchase, true, $sectionCardUrl('credit'), 'indigo'],
            ['Period Funding', $sectionData['rowSummary']->funding ?? 0, true, $sectionCardUrl(), 'sky'],
            ['Current Advance', $sectionData['rowSummary']->balance ?? 0, true, $sectionCardUrl(), 'purple'],
        ],
        'vendors' => [
            ['Total Vendors', $summary->vendor_count, false, $sectionCardUrl(), 'slate'],
            ['Total Purchase', $summary->total_purchase, true, $sectionCardUrl(), 'emerald'],
            ['Cash', $summary->cash_purchase, true, $sectionCardUrl('cash'), 'teal'],
            ['Credit', $summary->credit_purchase, true, $sectionCardUrl('credit'), 'indigo'],
            ['Outstanding', $summary->credit_outstanding, true, route('admin.cashbook.finance.vendor-credit'), 'rose'],
        ],
        'categories' => [
            ['Total Purchase', $summary->total_purchase, true, $sectionCardUrl(), 'emerald'],
            ['Categories', $summary->category_count, false, $sectionCardUrl(), 'indigo'],
            ['Vendors', $summary->vendor_count, false, route('admin.cashbook.finance.purchase.vendors', $cardQuery), 'teal'],
            ['Purchasers', $summary->purchaser_count, false, route('admin.cashbook.finance.purchase.purchasers', $cardQuery), 'sky'],
        ],
        default => [
            ['Total Purchase', $summary->total_purchase, true, $sectionCardUrl(), 'emerald'],
            ['Invoices', $summary->invoice_count, false, $sectionCardUrl(), 'slate'],
            ['Cash', $summary->cash_purchase, true, $sectionCardUrl('cash'), 'teal'],
            ['Credit', $summary->credit_purchase, true, $sectionCardUrl('credit'), 'indigo'],
            ['Outstanding', $summary->credit_outstanding, true, route('admin.cashbook.finance.vendor-credit'), 'rose'],
        ],
    };
@endphp

@section('title', $labels[$section].' — Purchase Finance')
@section('header_title')
    <i data-lucide="shopping-basket" class="h-5 w-5 text-emerald-600"></i> Purchase Finance
@endsection

@section('header_subtitle')
    {{ $labels[$section] }} &bull; Detailed Procurement Ledger
@endsection

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-16">
    <!-- TOP NAVIGATION & SUBSECTIONS -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        @include('admin.cashbook.finance.purchase._nav')
        @include('admin.cashbook.finance.purchase._dashboard-tabs')
    </div>

    <!-- SECTION HEADER -->
    <header class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    <span class="text-xs font-black uppercase tracking-wider text-emerald-800">{{ $labels[$section] }}</span>
                </div>
                <h1 class="mt-1 text-xl sm:text-2xl font-black text-slate-950 tracking-tight">{{ $questions[$section] }}</h1>
                <p class="mt-1 text-xs font-bold text-slate-500 flex items-center gap-1.5">
                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                    Period: <strong class="text-slate-900">{{ $filters['start_date'] }}</strong> to <strong class="text-slate-900">{{ $filters['end_date'] }}</strong>
                </p>
            </div>
            @if($section === 'vendors')
                <a href="{{ route('admin.cashbook.finance.vendor-credit') }}"
                   class="inline-flex items-center gap-1.5 rounded-2xl bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wider text-white shadow-xs hover:bg-emerald-800 transition">
                    <i data-lucide="truck" class="w-4 h-4"></i>
                    <span>Vendor Credit Payments</span>
                </a>
            @endif
        </div>

        @include('admin.cashbook.finance.purchase._section-filters')
    </header>

    <!-- SECTION SUMMARY CARDS -->
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-{{ count($cards) }}">
        @foreach($cards as [$label, $value, $money, $cardUrl, $color])
            @php
                $bgClass = match($color) {
                    'emerald' => 'border-emerald-100 bg-emerald-50/40 hover:bg-emerald-50/70 text-emerald-950',
                    'rose' => 'border-rose-100 bg-rose-50/40 hover:bg-rose-50/70 text-rose-950',
                    'indigo' => 'border-indigo-100 bg-indigo-50/40 hover:bg-indigo-50/70 text-indigo-950',
                    'teal' => 'border-teal-100 bg-teal-50/40 hover:bg-teal-50/70 text-teal-950',
                    'sky' => 'border-sky-100 bg-sky-50/40 hover:bg-sky-50/70 text-sky-950',
                    'purple' => 'border-purple-100 bg-purple-50/40 hover:bg-purple-50/70 text-purple-950',
                    default => 'border-slate-200 bg-slate-50/40 hover:bg-slate-50/70 text-slate-950',
                };
            @endphp
            <a href="{{ $cardUrl }}"
               class="rounded-2xl border p-4 shadow-xs transition {{ $bgClass }} flex flex-col justify-between group">
                <span class="flex items-center justify-between gap-2 text-[10px] font-black uppercase tracking-wider text-slate-500">
                    <span>{{ $label }}</span>
                    <i data-lucide="arrow-up-right" class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-700"></i>
                </span>
                <strong class="mt-2 block font-mono text-xl sm:text-2xl font-black tabular-nums tracking-tight">
                    {{ $money ? '₹'.number_format((float) $value, 2) : number_format((int) $value) }}
                </strong>
            </a>
        @endforeach
    </section>

    <!-- DATA TABLE -->
    @if($section === 'invoices' && $sectionData['rows']->isEmpty())
        <section class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center shadow-xs">
            <p class="font-black text-slate-900">No purchase invoices match the selected filters.</p>
            <a href="{{ route('admin.cashbook.finance.purchase.invoices') }}"
               class="mt-4 inline-flex rounded-xl bg-emerald-700 px-4 py-2 text-xs font-black uppercase tracking-wider text-white shadow-xs hover:bg-emerald-800 transition">
                Clear Filters
            </a>
        </section>
    @else
        <section id="purchase-results" class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4">
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[58rem] text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 rounded-xl">
                        @if($section === 'purchasers')
                            <tr>
                                <th class="p-3">Purchaser</th>
                                <th class="p-3 text-right">Total Purchase</th>
                                <th class="p-3 text-right">Cash</th>
                                <th class="p-3 text-right">Credit</th>
                                <th class="p-3 text-right">Period Funding</th>
                                <th class="p-3 text-right">Period Used</th>
                                <th class="p-3 text-right">Transactions</th>
                                <th class="p-3 text-right">Current Advance</th>
                                <th class="p-3 text-right">Invoices</th>
                                <th class="p-3 text-center">Action</th>
                            </tr>
                        @endif
                        @if($section === 'vendors')
                            <tr>
                                <th class="p-3">Vendor</th>
                                <th class="p-3">Categories</th>
                                <th class="p-3 text-right">Invoices</th>
                                <th class="p-3 text-right">Cash</th>
                                <th class="p-3 text-right">Credit</th>
                                <th class="p-3 text-right">Outstanding</th>
                                <th class="p-3 text-right">Total Purchase</th>
                                <th class="p-3 text-center">Action</th>
                            </tr>
                        @endif
                        @if($section === 'categories')
                            <tr>
                                <th class="p-3">Category</th>
                                <th class="p-3 text-right">Purchase Value</th>
                                <th class="p-3 text-right">Cash</th>
                                <th class="p-3 text-right">Credit</th>
                                <th class="p-3 text-right">Vendors</th>
                                <th class="p-3 text-right">Purchasers</th>
                                <th class="p-3 text-right">Invoices</th>
                            </tr>
                        @endif
                        @if($section === 'invoices')
                            <tr>
                                <th class="p-3">Date</th>
                                <th class="p-3">Invoice</th>
                                <th class="p-3">Vendor</th>
                                <th class="p-3">Purchaser</th>
                                <th class="p-3">Categories</th>
                                <th class="p-3 text-center">Payment</th>
                                <th class="p-3 text-right">Amount</th>
                                <th class="p-3 text-right">Paid</th>
                                <th class="p-3 text-right">Outstanding</th>
                                <th class="p-3 text-center">Action</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($sectionData['rows'] as $row)
                            @include('admin.cashbook.finance.purchase._section-row', ['mobile' => false])
                        @empty
                            <tr>
                                <td colspan="10" class="p-8 text-center text-xs font-semibold text-slate-400">No matching purchase data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Mobile View -->
            <div class="divide-y divide-slate-100 md:hidden">
                @forelse($sectionData['rows'] as $row)
                    @include('admin.cashbook.finance.purchase._section-row', ['mobile' => true])
                @empty
                    <p class="p-8 text-center text-xs font-semibold text-slate-400">No matching purchase data.</p>
                @endforelse
            </div>

            @if($sectionData['rows']->hasPages())
                <div class="border-t border-slate-100 pt-4">
                    {{ $sectionData['rows']->links() }}
                </div>
            @endif
        </section>
    @endif
</div>
@endsection
