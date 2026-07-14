@extends('shop-owner.layouts.app')

@section('title', 'Invoice Details')
@section('page_title', 'Invoice Details')
@section('page_description', 'Review the generated daily invoice, confirmed delivery quantities, shortages, and payment balance.')
@php($breadcrumbs = [['label' => 'Finance', 'url' => route('shop-owner.finance.index')], ['label' => $invoice->invoice_number]])

@section('content')
    <div class="space-y-6">
        @include('shop-owner.finance.partials.invoice-card', ['invoice' => $invoice])
        @include('shop-owner.finance.partials.payment-request-panel', ['invoice' => $invoice, 'context' => 'finance'])
        @include('shop-owner.finance.partials.finance-note-form', ['invoice' => $invoice])
    </div>
@endsection
