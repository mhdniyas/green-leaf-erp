@extends('shop-owner.layouts.app')

@section('title', 'Delivery Details')
@section('page_title', 'Delivery Details')
@section('page_description', 'Review allocation, update received details, and track damages or shortages.')
@php($breadcrumbs = [['label' => 'Deliveries', 'url' => route('shop-owner.deliveries.index')], ['label' => $order->order_number]])

@section('content')
    <div class="space-y-3 sm:space-y-6">
        @include('shop-owner.deliveries.partials.delivery-summary-card', ['order' => $order])

        @include('shop-owner.deliveries.partials.received-update-form', [
            'order' => $order,
            'deliveryEligibility' => $deliveryEligibility ?? null,
            'deliveryPriceReadiness' => $deliveryPriceReadiness ?? null,
        ])
    </div>
@endsection
