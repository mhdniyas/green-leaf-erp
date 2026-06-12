@extends('shop-owner.layouts.app')

@section('title', 'Finance')
@section('page_title', 'Finance')
@section('page_description', 'Review your daily invoices, delivery deductions, and payment balances in one place.')
@php($breadcrumbs = [['label' => 'Finance']])

@section('content')
    <div class="space-y-6">
        @include('shop-owner.finance.partials.finance-summary-cards', [
            'outstandingBalance' => $outstandingBalance,
            'paidAmount' => $paidAmount,
            'shortageValue' => $shortageValue,
        ])

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">Daily Invoices</h2>
            <div class="mt-5">
                @include('shop-owner.finance.partials.payment-history-table', ['invoices' => $invoices])
            </div>
        </section>
    </div>
@endsection
