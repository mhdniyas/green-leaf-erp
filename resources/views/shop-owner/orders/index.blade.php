@extends('shop-owner.layouts.app')

@section('title', 'Daily Orders')
@section('page_title', 'Daily Orders')
@section('page_description', 'Manage tomorrow’s order, open existing submissions, and move quickly into history.')
@php($breadcrumbs = [['label' => 'Daily Orders']])

@section('page_actions')
    @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.create'), 'label' => 'Create Order', 'classes' => 'bg-emerald-600 text-white'])
@endsection

@section('content')
    <div class="space-y-6">
        @include('shop-owner.orders.partials.order-tabs')

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Current Workflow</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Tomorrow Order Snapshot</h2>
                </div>
            </div>

            <div class="mt-5">
                @if ($tomorrowOrder)
                    @include('shop-owner.orders.partials.order-summary-card', ['order' => $tomorrowOrder])
                @else
                    @include('shop-owner.components.empty-state', ['title' => 'No pending tomorrow order', 'description' => 'Start a new daily order for the next business day.', 'actionLabel' => 'Create Order', 'actionUrl' => route('shop-owner.orders.create')])
                @endif
            </div>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">Recent Orders</h2>
            <div class="mt-5">
                @include('shop-owner.orders.partials.order-history-table', ['orders' => $orders])
            </div>
        </section>
    </div>
@endsection
