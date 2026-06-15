@extends('shop-owner.layouts.app')

@section('title', 'Cart Details')
@section('page_title', 'Cart Details')
@section('page_description', 'View requested quantities, approval status, delivery progress, and finance notes for this daily cart.')
@php($breadcrumbs = [['label' => 'Cart', 'url' => route('shop-owner.orders.index')], ['label' => $order->order_number]])

@section('page_actions')
    <div class="flex flex-wrap gap-2">
        @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.history'), 'label' => 'Approval History', 'classes' => 'border border-slate-200 bg-white text-slate-800'])
        <?php
            $isTomorrowOrder = $order->business_date->isTomorrow();
            $purchaseOrdersLockedForTomorrow = $order->linkedPurchaseOrdersHaveGoodsReceived();
            $canRequestUpdate = $isTomorrowOrder && 
                                !$order->canEditDirectly() && 
                                (in_array($order->state, ['submitted', 'update_requested'], true) || 
                                 ($order->state === 'approved' && !$purchaseOrdersLockedForTomorrow));
        ?>
        @if ($canRequestUpdate)
            @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.create'), 'label' => 'Request Items', 'classes' => 'bg-amber-600 text-white hover:bg-amber-700'])
        @endif
    </div>
@endsection

@section('content')
    <div class="space-y-6">
        @include('shop-owner.orders.partials.order-tabs')
        @include('shop-owner.orders.partials.order-summary-card', ['order' => $order])

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">Cart Items</h2>
            <div class="mt-5">
                @include('shop-owner.orders.partials.order-items-table', ['order' => $order])
            </div>
        </section>
    </div>
@endsection
