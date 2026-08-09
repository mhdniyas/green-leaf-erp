@extends('shop-owner.layouts.app')

@section('title', 'Cart')
@section('page_title', 'Cart')
@section('page_description', 'Review tomorrow’s cart, open the marketplace, and track approval history.')
@php($breadcrumbs = [['label' => 'Cart']])

@if (!$tomorrowOrder)
    @section('page_actions')
        @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.create'), 'label' => 'Open Marketplace', 'classes' => 'bg-emerald-600 text-white hidden sm:inline-flex'])
    @endsection
@endif

@section('content')
    <div class="space-y-3 sm:space-y-4">
        @include('shop-owner.orders.partials.order-tabs')

        @include('shop-owner.partials.date-range-filter', [
            'action' => route('shop-owner.orders.index'),
            'startDate' => $filterStartDate,
            'endDate' => $filterEndDate,
            'clearUrl' => route('shop-owner.orders.index'),
        ])

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs sm:p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Current Workflow</p>
                    <h2 class="mt-0.5 text-base font-black text-slate-950 sm:text-lg">Tomorrow Cart Snapshot</h2>
                </div>
            </div>

            <div class="mt-3">
                @if ($tomorrowOrder)
                    @include('shop-owner.orders.partials.order-summary-card', ['order' => $tomorrowOrder])
                @else
                    @include('shop-owner.components.empty-state', ['title' => 'No pending tomorrow cart', 'description' => 'Start in the marketplace and add products for the next business day.', 'actionLabel' => 'Open Marketplace', 'actionUrl' => route('shop-owner.orders.create')])
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs sm:p-4">
            <h2 class="text-base font-black text-slate-950 sm:text-lg">Approval History</h2>
            <div class="mt-3">
                @include('shop-owner.orders.partials.order-history-table', ['orders' => $orders])
            </div>
        </section>
    </div>
@endsection
