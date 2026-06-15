@extends('shop-owner.layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Shop Dashboard')
@section('page_description', 'A focused view of today’s delivery, tomorrow’s cart, and your outstanding finance.')
@php($breadcrumbs = [['label' => 'Dashboard']])

@section('page_actions')
    @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.create'), 'label' => 'Open Marketplace', 'classes' => 'bg-emerald-600 text-white'])
@endsection

@section('content')
    <div class="space-y-6">
        @include('shop-owner.dashboard.partials.stats-cards', ['stats' => $stats])

        <div class="grid gap-6 xl:grid-cols-[1.3fr_1fr]">
            @include('shop-owner.dashboard.partials.today-orders', ['todayOrder' => $todayOrder, 'tomorrowOrder' => $tomorrowOrder])
            @include('shop-owner.dashboard.partials.pending-deliveries', ['pendingDeliveries' => $pendingDeliveries])
        </div>

        @include('shop-owner.dashboard.partials.finance-summary', ['financeSummary' => $financeSummary, 'recentOrders' => $recentOrders])
    </div>
@endsection
