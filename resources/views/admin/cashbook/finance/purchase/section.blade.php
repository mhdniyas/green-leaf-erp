@extends('admin.cashbook.layouts.app')
@php
    $labels = ['purchasers' => 'Purchasers', 'vendors' => 'Vendors', 'categories' => 'Categories', 'invoices' => 'Invoices'];
    $questions = ['purchasers' => 'How is each purchaser purchasing?', 'vendors' => 'Who are we buying from?', 'categories' => 'What are we buying?', 'invoices' => 'Show me the actual purchase records.'];
    $activePurchaseTab = $section;
    $summary = $sectionData['summary'];
    $sectionRoute = 'admin.cashbook.finance.purchase.'.$section;
    $cardQuery = request()->except(['page', 'payment']);
    $sectionCardUrl = fn (string $payment = 'all'): string => route($sectionRoute, array_merge($cardQuery, ['payment' => $payment])).'#purchase-results';
    $cards = match($section) {
        'purchasers' => [['Total Purchase', $summary->total_purchase, true, $sectionCardUrl()], ['Cash Purchase', $summary->cash_purchase, true, $sectionCardUrl('cash')], ['Credit Purchase', $summary->credit_purchase, true, $sectionCardUrl('credit')], ['Period Funding', $sectionData['rowSummary']->funding ?? 0, true, $sectionCardUrl()], ['Current Advance', $sectionData['rowSummary']->balance ?? 0, true, $sectionCardUrl()]],
        'vendors' => [['Total Vendors', $summary->vendor_count, false, $sectionCardUrl()], ['Total Purchase', $summary->total_purchase, true, $sectionCardUrl()], ['Cash', $summary->cash_purchase, true, $sectionCardUrl('cash')], ['Credit', $summary->credit_purchase, true, $sectionCardUrl('credit')], ['Outstanding', $summary->credit_outstanding, true, route('admin.cashbook.finance.vendor-credit')]],
        'categories' => [['Total Purchase', $summary->total_purchase, true, $sectionCardUrl()], ['Categories', $summary->category_count, false, $sectionCardUrl()], ['Vendors', $summary->vendor_count, false, route('admin.cashbook.finance.purchase.vendors', $cardQuery)], ['Purchasers', $summary->purchaser_count, false, route('admin.cashbook.finance.purchase.purchasers', $cardQuery)]],
        default => [['Total Purchase', $summary->total_purchase, true, $sectionCardUrl()], ['Invoices', $summary->invoice_count, false, $sectionCardUrl()], ['Cash', $summary->cash_purchase, true, $sectionCardUrl('cash')], ['Credit', $summary->credit_purchase, true, $sectionCardUrl('credit')], ['Outstanding', $summary->credit_outstanding, true, route('admin.cashbook.finance.vendor-credit')]],
    };
@endphp
@section('title', $labels[$section].' - Purchase')
@section('header_title')
    <i data-lucide="shopping-basket" class="h-5 w-5 text-emerald-600"></i> Purchase
@endsection
@section('header_subtitle')
    {{ $labels[$section] }} investigation
@endsection
@section('content')
<div class="mx-auto max-w-[96rem] space-y-5">
    @include('admin.cashbook.finance.purchase._nav')
    @include('admin.cashbook.finance.purchase._dashboard-tabs')
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-[10px] font-black uppercase text-emerald-700">{{ $labels[$section] }}</p><h1 class="mt-1 text-2xl font-black text-slate-950">{{ $questions[$section] }}</h1><p class="mt-1 text-xs font-bold text-slate-500">{{ $filters['start_date'] }} to {{ $filters['end_date'] }}</p></div>@if($section === 'vendors')<a href="{{ route('admin.cashbook.finance.vendor-credit') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg bg-emerald-700 px-3 text-xs font-black text-white"><i data-lucide="truck" class="h-4 w-4"></i> Vendor Credit Payments</a>@endif</header>
    @include('admin.cashbook.finance.purchase._section-filters')
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">@foreach($cards as [$label, $value, $money, $cardUrl])<a href="{{ $cardUrl }}" class="group rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-600"><span class="flex items-center justify-between gap-2 text-[10px] font-black uppercase text-slate-400"><span>{{ $label }}</span><i data-lucide="arrow-up-right" class="h-3.5 w-3.5 text-slate-300 group-hover:text-emerald-600"></i></span><strong class="mt-2 block font-mono text-lg text-slate-950">{{ $money ? '₹'.number_format((float) $value, 2) : number_format((int) $value) }}</strong></a>@endforeach</section>
    @if($section === 'invoices' && $sectionData['rows']->isEmpty())
        <section class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center shadow-sm">
            <p class="font-black text-slate-900">No purchase invoices match the selected filters.</p>
            <a href="{{ route('admin.cashbook.finance.purchase.invoices') }}" class="mt-4 inline-flex rounded-lg bg-emerald-700 px-4 py-2 text-xs font-black text-white">Clear Filters</a>
        </section>
    @else
    <section id="purchase-results" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="hidden overflow-x-auto md:block"><table class="w-full min-w-[58rem] text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase text-slate-500">
            @if($section === 'purchasers')<tr><th class="p-3">Purchaser</th><th class="p-3 text-right">Total Purchase</th><th class="p-3 text-right">Cash</th><th class="p-3 text-right">Credit</th><th class="p-3 text-right">Period Funding</th><th class="p-3 text-right">Period Used</th><th class="p-3 text-right">Transactions</th><th class="p-3 text-right">Current Advance</th><th class="p-3 text-right">Invoices</th><th class="p-3">Action</th></tr>@endif
            @if($section === 'vendors')<tr><th class="p-3">Vendor</th><th class="p-3">Categories</th><th class="p-3 text-right">Invoices</th><th class="p-3 text-right">Cash</th><th class="p-3 text-right">Credit</th><th class="p-3 text-right">Outstanding</th><th class="p-3 text-right">Total Purchase</th><th class="p-3">Action</th></tr>@endif
            @if($section === 'categories')<tr><th class="p-3">Category</th><th class="p-3 text-right">Purchase Value</th><th class="p-3 text-right">Cash</th><th class="p-3 text-right">Credit</th><th class="p-3 text-right">Vendors</th><th class="p-3 text-right">Purchasers</th><th class="p-3 text-right">Invoices</th></tr>@endif
            @if($section === 'invoices')<tr><th class="p-3">Date</th><th class="p-3">Invoice</th><th class="p-3">Vendor</th><th class="p-3">Purchaser</th><th class="p-3">Categories</th><th class="p-3">Payment</th><th class="p-3 text-right">Amount</th><th class="p-3 text-right">Paid</th><th class="p-3 text-right">Outstanding</th><th class="p-3">Action</th></tr>@endif
        </thead><tbody class="divide-y divide-slate-100">@forelse($sectionData['rows'] as $row)@include('admin.cashbook.finance.purchase._section-row', ['mobile' => false])@empty<tr><td colspan="10" class="p-8 text-center text-slate-400">No matching purchase data.</td></tr>@endforelse</tbody></table></div>
        <div class="divide-y divide-slate-100 md:hidden">@forelse($sectionData['rows'] as $row)@include('admin.cashbook.finance.purchase._section-row', ['mobile' => true])@empty<p class="p-8 text-center text-sm text-slate-400">No matching purchase data.</p>@endforelse</div>
        @if($sectionData['rows']->hasPages())<div class="border-t border-slate-200 p-4">{{ $sectionData['rows']->links() }}</div>@endif
    </section>
    @endif
</div>
@endsection
