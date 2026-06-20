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

        @php($latestResolvedRevision = $order->latestResolvedRevision)
        @php($latestDecisionNote = $latestResolvedRevision?->manager_note ?: $order->manager_note ?: $order->update_reason)
        @php($latestDecisionSummary = match (true) {
            $latestResolvedRevision?->status === 'rejected' => 'Update request rejected. Try again in tomorrow\'s order.',
            $latestResolvedRevision?->status === 'blocked' => 'Update request could not be applied because purchasing was already locked.',
            $latestResolvedRevision?->status === 'applied' && ! $latestResolvedRevision->isFullyAccepted() => $latestResolvedRevision->acceptedItemsCount().' of '.$latestResolvedRevision->items->count().' item changes accepted. Update request solved.',
            $latestResolvedRevision?->status === 'applied' => 'All requested changes were accepted.',
            $order->reviewed_at && $order->state === 'rejected' => ((float) $order->items->sum('approved_qty') > 0 ? 'Request partially accepted after review.' : 'Request rejected after review.'),
            default => null,
        })

        @if ($latestDecisionNote || $latestDecisionSummary)
            <section class="rounded-[2rem] border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-amber-700">Purchase Manager Note</p>
                @if ($latestDecisionSummary)
                    <p class="mt-3 text-sm font-bold leading-6 text-amber-900">{{ $latestDecisionSummary }}</p>
                @endif
                @if ($latestDecisionNote)
                    <p class="mt-2 text-sm font-semibold leading-6 text-amber-900">{{ $latestDecisionNote }}</p>
                @endif
            </section>
        @endif

        <section class="rounded-[2rem] border border-slate-200/80 bg-slate-50/70 p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-black text-slate-950">Approval History</h2>
                <a href="{{ route('shop-owner.orders.history') }}" class="text-sm font-bold text-emerald-700 hover:text-emerald-900">Open all</a>
            </div>
            <div class="mt-4">
                @include('requisitions.partials.review-history', ['order' => $order])
            </div>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">Cart Items</h2>
            <div class="mt-5">
                @include('shop-owner.orders.partials.order-items-table', ['order' => $order])
            </div>
        </section>
    </div>
@endsection
