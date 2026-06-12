@extends('shop-owner.layouts.app')

@php
    $isUpdateRequest = $tomorrowOrder && 
                        !$tomorrowOrder->canEditDirectly() && 
                        (in_array($tomorrowOrder->state, ['submitted', 'update_requested'], true) || 
                         ($tomorrowOrder->state === 'approved' && !$purchaseOrdersLockedForTomorrow));
@endphp

@section('title', $isUpdateRequest ? 'Request Order Update' : 'Create Order')
@section('page_title', $isUpdateRequest ? 'Request Order Update' : 'Create Tomorrow Order')
@section('page_description', $isUpdateRequest ? 'Request modifications to your submitted tomorrow’s order. Changes require manager approval.' : 'Build tomorrow’s shop order with suggested quantities from the previous business day.')
@php($breadcrumbs = [['label' => 'Daily Orders', 'url' => route('shop-owner.orders.index')], ['label' => $isUpdateRequest ? 'Request Update' : 'Create']])

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
