@extends('shop-owner.layouts.app')

@section('title', 'Finance')
@section('page_title', 'Finance')
@section('page_description', 'Track paid amounts, outstanding balances, delivery deductions, and order-level finance notes.')
@php($breadcrumbs = [['label' => 'Finance']])

@section('content')
    <div class="space-y-6">
        @include('shop-owner.finance.partials.finance-summary-cards', [
            'outstandingBalance' => $outstandingBalance,
            'paidAmount' => $paidAmount,
            'shortageValue' => $shortageValue,
        ])

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">Payment History</h2>
            <div class="mt-5">
                @include('shop-owner.finance.partials.payment-history-table', ['orders' => $orders])
            </div>
        </section>
    </div>
@endsection
