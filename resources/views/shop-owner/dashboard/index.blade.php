@extends('shop-owner.layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Shop Dashboard')
@section('page_description', 'A focused view of today’s delivery, tomorrow’s cart, and your outstanding finance.')
@php($breadcrumbs = [['label' => 'Dashboard']])

@section('page_actions')
    <div class="flex flex-wrap gap-2">
        <button
            type="button"
            data-pwa-install-button
            hidden
            class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-white px-4 py-2.5 text-sm font-bold text-emerald-700 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50"
        >
            <svg class="h-4 w-4" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14" />
            </svg>
            <span data-pwa-install-label>Install App</span>
        </button>
        @include('shop-owner.components.action-button', ['href' => route('shop-owner.accounting.index', ['tab' => $isOwnedAccountingShop ? 'cashbook' : 'bills']), 'label' => $isOwnedAccountingShop ? 'Open Cashbook' : 'Open Bills', 'classes' => 'border border-slate-200 bg-white text-slate-800'])
        @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.create'), 'label' => 'Open Marketplace', 'classes' => 'bg-emerald-600 text-white'])
    </div>
@endsection

@section('content')
    <div class="space-y-6">
        @include('shop-owner.dashboard.partials.stats-cards', ['stats' => $stats, 'isOwnedAccountingShop' => $isOwnedAccountingShop])

        <div class="grid gap-6 xl:grid-cols-[1.3fr_1fr]">
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
