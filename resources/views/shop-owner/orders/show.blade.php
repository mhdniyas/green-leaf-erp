@extends('shop-owner.layouts.app')

@section('title', 'Order Details')
@section('page_title', 'Order Details')
@section('page_description', 'View requested quantities, approval status, delivery progress, and finance notes for this order.')
@php($breadcrumbs = [['label' => 'Daily Orders', 'url' => route('shop-owner.orders.index')], ['label' => $order->order_number]])

@section('page_actions')
    @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.history'), 'label' => 'Order History', 'classes' => 'border border-slate-200 bg-white text-slate-800'])
@endsection

@section('content')
    <div class="space-y-6">
        @include('shop-owner.orders.partials.order-tabs')
        @include('shop-owner.orders.partials.order-summary-card', ['order' => $order])

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">Order Items</h2>
            <div class="mt-5">
                @include('shop-owner.orders.partials.order-items-table', ['order' => $order])
            </div>
        </section>
    </div>
@endsection
