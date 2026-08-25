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
    @include('admin.cashbook.finance.purchase._filters', ['filterRoute' => route('admin.cashbook.finance.purchase.reports.purchasers')])
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">@foreach(['Total Purchase' => $report['summary']->total_purchase, 'Cash Purchase' => $report['summary']->cash_purchase, 'Credit Purchase' => $report['summary']->credit_purchase, 'Credit Paid' => $report['summary']->credit_paid, 'Credit Outstanding' => $report['summary']->credit_outstanding] as $label => $value)<div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"><span class="text-[10px] font-black uppercase text-slate-400">{{ $label }}</span><strong class="mt-2 block font-mono text-lg text-slate-950">₹{{ number_format((float) $value, 2) }}</strong></div>@endforeach</section>
    @include('admin.cashbook.finance.purchase._summary-table', ['title' => 'Purchasers', 'rows' => $report['purchasers'], 'kind' => 'purchaser'])
    @include('admin.cashbook.finance.purchase._invoice-table', ['invoices' => $report['invoices'], 'title' => 'Invoice and Vendor Details'])
</div>
@endsection
