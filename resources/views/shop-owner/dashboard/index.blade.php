@extends('shop-owner.layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Shop Dashboard')
@section('page_description', 'A focused view of today’s delivery, tomorrow’s cart, and your outstanding finance.')
@php($breadcrumbs = [['label' => 'Dashboard']])

@section('page_actions')
    <div class="flex flex-wrap gap-1.5 sm:gap-2">
        @include('shop-owner.components.action-button', ['href' => route('shop-owner.accounting.index', ['tab' => $isOwnedAccountingShop ? 'cashbook' : 'bills']), 'label' => $isOwnedAccountingShop ? 'Open Cashbook' : 'Open Bills', 'classes' => 'border border-slate-200 bg-white text-slate-800'])
        @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.create'), 'label' => 'Open Marketplace', 'classes' => 'bg-emerald-600 text-white'])
    </div>
@endsection

@section('content')
    <div class="space-y-3 sm:space-y-4">
        @include('shop-owner.dashboard.partials.stats-cards', ['stats' => $stats, 'isOwnedAccountingShop' => $isOwnedAccountingShop])

        <div class="grid gap-3 sm:gap-4 lg:grid-cols-2">
            @include('shop-owner.dashboard.partials.today-orders', ['todayOrder' => $todayOrder, 'tomorrowOrder' => $tomorrowOrder])
            @include('shop-owner.dashboard.partials.pending-deliveries', ['pendingDeliveries' => $pendingDeliveries])
        </div>

        @include('shop-owner.dashboard.partials.finance-summary', [
            'financeSummary' => $financeSummary,
            'recentInvoices' => $recentInvoices,
            'businessDate' => $businessDate,
            'isOwnedAccountingShop' => $isOwnedAccountingShop,
        ])
    </div>
@endsection
