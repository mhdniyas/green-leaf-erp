@extends('shop-owner.layouts.app')

@section('title', 'Finance')
@section('page_title', 'Finance')
@section('page_description', 'Review daily invoices, payment balances, and financial status in one place.')
@php($breadcrumbs = [['label' => 'Finance']])

@section('content')
    <div class="space-y-4 sm:space-y-5">
        @include('shop-owner.finance.partials.finance-summary-cards', [
            'totalBilled' => $totalBilled,
            'outstandingBalance' => $outstandingBalance,
            'paidAmount' => $paidAmount,
            'shortageValue' => $shortageValue,
            'pendingPaymentAmount' => $pendingPaymentAmount,
            'isOwnedAccountingShop' => $isOwnedAccountingShop,
            'latestClosingBalance' => $latestClosingBalance ?? 0,
            'carryOver' => $carryOver,
            'payableTotal' => $payableTotal ?? 0,
            'payableReceivedTotal' => $payableReceivedTotal ?? 0,
            'payableBalance' => $payableBalance ?? 0,
        ])

        <section class="rounded-2xl border border-slate-200 bg-white p-2.5 shadow-xs sm:p-3">
            <div class="grid grid-cols-2 gap-1.5 rounded-xl bg-slate-100 p-1 text-xs font-black text-slate-600 sm:inline-grid sm:auto-cols-fr sm:grid-flow-col">
                <a href="{{ route('shop-owner.finance.index', ['tab' => 'invoices']) }}" class="rounded-lg px-3.5 py-2 text-center transition {{ $activeTab === 'invoices' ? 'bg-white text-slate-950 shadow-xs' : 'hover:bg-white/70' }}">
                    Invoices
                </a>
                <a href="{{ route('shop-owner.payments.index') }}" class="rounded-lg px-3.5 py-2 text-center transition hover:bg-white/70">
                    Payments
                </a>
            </div>
        </section>

        @include('shop-owner.partials.date-range-filter', [
            'action' => route('shop-owner.finance.index'),
            'hidden' => ['tab' => $activeTab],
            'startDate' => $filterStartDate,
            'endDate' => $filterEndDate,
            'clearUrl' => route('shop-owner.finance.index', ['tab' => $activeTab]),
        ])

        @if ($activeTab === 'payments')
            @include('shop-owner.finance.partials.invoice-payments-tab', [
                'invoices' => $invoices,
                'payableInvoices' => $payableInvoices,
                'payableInvoiceTotal' => $payableInvoiceTotal,
                'invoicePaymentRequests' => $invoicePaymentRequests,
                'outstandingBalance' => $outstandingBalance,
                'pendingPaymentAmount' => $pendingPaymentAmount,
                'availableInvoicePaymentCredit' => $availableInvoicePaymentCredit,
                'isOwnedAccountingShop' => $isOwnedAccountingShop,
                'latestBalanceDate' => $latestBalanceDate,
                'latestClosingBalance' => $latestClosingBalance,
                'pendingBillApprovalSummary' => $pendingBillApprovalSummary,
                'payableCategories' => $payableCategories ?? collect(),
                'payableTotal' => $payableTotal ?? 0,
                'payableReceivedTotal' => $payableReceivedTotal ?? 0,
                'payableBalance' => $payableBalance ?? 0,
            ])
        @else
            <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs sm:p-4">
                <h2 class="text-base font-black text-slate-950 sm:text-lg">Daily Invoices</h2>
                <div class="mt-3">
                    @include('shop-owner.finance.partials.payment-history-table', ['invoices' => $invoices])
                </div>
            </section>
        @endif
    </div>
@endsection
