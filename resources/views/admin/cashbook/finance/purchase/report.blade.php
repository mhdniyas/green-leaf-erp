@extends('admin.cashbook.layouts.app')

@section('title', 'Purchase Report - Cashbook')
@section('header_title')
    <i data-lucide="clipboard-list" class="h-5 w-5 text-emerald-600"></i> Purchase Report
@endsection

@section('header_subtitle')
    Item-level procurement report. Category filters never count an unrelated invoice line.
@endsection

@section('header_actions')
    <a href="{{ route('admin.cashbook.finance.purchase', request()->query()) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700">Dashboard</a>
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-5">
    <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">@include('admin.cashbook.finance.purchase._filters', ['filterRoute' => route('admin.cashbook.finance.purchase.report')])</section>
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">@foreach(['Total Purchase' => $report['summary']->total_purchase, 'Cash Purchase' => $report['summary']->cash_purchase, 'Credit Purchase' => $report['summary']->credit_purchase, 'Credit Paid' => $report['summary']->credit_paid, 'Credit Outstanding' => $report['summary']->credit_outstanding] as $label => $value)<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><span class="text-[10px] font-black uppercase text-slate-400">{{ $label }}</span><strong class="mt-2 block font-mono text-xl text-slate-950">₹{{ number_format((float) $value, 2) }}</strong></div>@endforeach</section>
    @include('admin.cashbook.finance.purchase._invoice-table', ['invoices' => $report['invoices'], 'title' => 'Invoices'])
    @include('admin.cashbook.finance.purchase._summary-table', ['title' => 'Vendors', 'rows' => $report['vendors'], 'kind' => 'vendor'])
    @include('admin.cashbook.finance.purchase._summary-table', ['title' => 'Purchasers', 'rows' => $report['purchasers'], 'kind' => 'purchaser'])
    @include('admin.cashbook.finance.purchase._summary-table', ['title' => 'Categories', 'rows' => $report['categories'], 'kind' => 'category'])
</div>
@endsection
