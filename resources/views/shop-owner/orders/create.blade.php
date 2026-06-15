@extends('shop-owner.layouts.app')

@php
    $isUpdateRequest = $tomorrowOrder && 
                        !$tomorrowOrder->canEditDirectly() && 
                        (in_array($tomorrowOrder->state, ['submitted', 'update_requested'], true) || 
                         ($tomorrowOrder->state === 'approved' && !$purchaseOrdersLockedForTomorrow));
@endphp

@section('title', $isUpdateRequest ? 'Request Items' : 'Marketplace')
@section('page_title', $isUpdateRequest ? 'Request Items' : 'Marketplace')
@section('page_description', $isUpdateRequest ? 'Adjust your submitted cart and send the item request for manager approval.' : 'Browse the marketplace, add products to cart, and submit tomorrow’s daily order from the cart.')
@php($breadcrumbs = [['label' => 'Cart', 'url' => route('shop-owner.orders.index')], ['label' => $isUpdateRequest ? 'Request Items' : 'Marketplace']])

@section('content')
    <div class="space-y-6">
        @include('shop-owner.orders.partials.order-tabs')
        @include('shop-owner.orders.partials.order-form', [
            'productsByCategory' => $productsByCategory,
            'frequentProducts' => $frequentProducts,
            'tomorrowOrder' => $tomorrowOrder,
            'yesterdayOrder' => $yesterdayOrder,
            'presets' => $presets,
            'tomorrowDate' => $tomorrowDate,
            'cutoffPassed' => $cutoffPassed,
            'isUpdateRequest' => $isUpdateRequest,
        ])
    </div>
@endsection
