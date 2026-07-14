@extends('shop-owner.layouts.app')

@section('title', 'Delivery Details')
@section('page_title', 'Delivery Details')
@section('page_description', 'Review allocation, update received details, and track damages or shortages.')
@php($breadcrumbs = [['label' => 'Deliveries', 'url' => route('shop-owner.deliveries.index')], ['label' => $order->order_number]])

@section('content')
    <div class="space-y-6">
        @include('shop-owner.deliveries.partials.delivery-summary-card', ['order' => $order])
        @include('shop-owner.finance.partials.payment-request-panel', ['invoice' => $order->invoice, 'context' => 'delivery'])

        <div class="grid gap-6 xl:grid-cols-2">
            @include('shop-owner.deliveries.partials.received-update-form', ['order' => $order])
            @include('shop-owner.deliveries.partials.damaged-missing-form', ['order' => $order])
        </div>
    </div>
@endsection
