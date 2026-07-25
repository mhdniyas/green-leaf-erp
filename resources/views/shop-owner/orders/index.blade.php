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
    <div class="space-y-6">
        @include('shop-owner.orders.partials.order-tabs')

        @include('shop-owner.partials.date-range-filter', [
            'action' => route('shop-owner.orders.index'),
            'startDate' => $filterStartDate,
            'endDate' => $filterEndDate,
            'clearUrl' => route('shop-owner.orders.index'),
        ])

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Current Workflow</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Tomorrow Cart Snapshot</h2>
                </div>
            </div>

            <div class="mt-5">
                @if ($tomorrowOrder)
                    @include('shop-owner.orders.partials.order-summary-card', ['order' => $tomorrowOrder])
                @else
                    @include('shop-owner.components.empty-state', ['title' => 'No pending tomorrow cart', 'description' => 'Start in the marketplace and add products for the next business day.', 'actionLabel' => 'Open Marketplace', 'actionUrl' => route('shop-owner.orders.create')])
                @endif
            </div>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">Approval History</h2>
            <div class="mt-5">
                @include('shop-owner.orders.partials.order-history-table', ['orders' => $orders])
            </div>
        </section>
    </div>
@endsection
