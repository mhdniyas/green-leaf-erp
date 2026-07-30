@extends('shop-owner.layouts.app')

@section('title', 'Payments')
@section('page_title', 'Payments')
@section('page_description', 'Pay Green Leaf bills and submit Aishwarya Veg cashbook payments for approval.')
@php($breadcrumbs = [['label' => 'Payments']])

@section('page_actions')
    @include('shop-owner.components.action-button', ['href' => route('shop-owner.finance.index'), 'label' => 'Invoices', 'classes' => 'border border-slate-200 bg-white text-slate-800'])
@endsection

@section('content')
    <div class="space-y-5">
        @include('shop-owner.finance.partials.finance-summary-cards', [
            'totalBilled' => $totalBilled,
            'outstandingBalance' => $outstandingBalance,
            'paidAmount' => $paidAmount,
            'shortageValue' => $shortageValue,
            'pendingPaymentAmount' => $pendingPaymentAmount,
            'isOwnedAccountingShop' => $isOwnedAccountingShop,
            'latestClosingBalance' => $latestClosingBalance,
            'carryOver' => $carryOver,
        ])

        @include('shop-owner.partials.date-range-filter', [
            'action' => route('shop-owner.payments.index'),
            'hidden' => [],
            'startDate' => $filterStartDate,
            'endDate' => $filterEndDate,
            'clearUrl' => route('shop-owner.payments.index'),
        ])

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
    </div>
@endsection
