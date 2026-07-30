@extends('shop-owner.layouts.app')

@section('title', 'Finance')
@section('page_title', 'Finance')
@section('page_description', 'Review your daily invoices, delivery deductions, and payment balances in one place.')
@php($breadcrumbs = [['label' => 'Finance']])

@section('content')
    <div class="space-y-5">
        @include('shop-owner.finance.partials.finance-summary-cards', [
            'totalBilled' => $totalBilled,
            'outstandingBalance' => $outstandingBalance,
            'paidAmount' => $paidAmount,
            'shortageValue' => $shortageValue,
            'pendingPaymentAmount' => $pendingPaymentAmount,
            'isOwnedAccountingShop' => $isOwnedAccountingShop,
        ])

        <section class="rounded-[1.5rem] border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
            <div class="grid grid-cols-2 gap-2 rounded-[1.1rem] bg-slate-100 p-1.5 text-sm font-black text-slate-600 sm:inline-grid sm:auto-cols-fr sm:grid-flow-col">
                <a href="{{ route('shop-owner.finance.index', ['tab' => 'invoices']) }}" class="rounded-[0.9rem] px-4 py-3 text-center transition {{ $activeTab === 'invoices' ? 'bg-white text-slate-950 shadow-sm' : 'hover:bg-white/70' }}">
                    Invoices
                </a>
                <a href="{{ route('shop-owner.payments.index') }}" class="rounded-[0.9rem] px-4 py-3 text-center transition hover:bg-white/70">
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
                'invoicePaymentRequests' => $invoicePaymentRequests,
                'outstandingBalance' => $outstandingBalance,
                'pendingPaymentAmount' => $pendingPaymentAmount,
                'availableInvoicePaymentCredit' => $availableInvoicePaymentCredit,
                'isOwnedAccountingShop' => $isOwnedAccountingShop,
                'latestBalanceDate' => $latestBalanceDate,
                'latestClosingBalance' => $latestClosingBalance,
                'pendingBillApprovalSummary' => $pendingBillApprovalSummary,
            ])
        @else
            <section class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <h2 class="text-lg font-black text-slate-950 sm:text-xl">Daily Invoices</h2>
                <div class="mt-5">
                    @include('shop-owner.finance.partials.payment-history-table', ['invoices' => $invoices])
                </div>
            </section>
        @endif
    </div>
@endsection
