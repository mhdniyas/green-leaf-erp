@extends('shop-owner.layouts.app')

@section('title', 'Cart Details')
@section('page_title', 'Cart Details')
@section('page_description', 'View requested quantities, approval status, delivery progress, and finance notes for this daily cart.')
@php($breadcrumbs = [['label' => 'Cart', 'url' => route('shop-owner.orders.index')], ['label' => $order->order_number]])

@section('page_actions')
    <div class="flex flex-wrap gap-1.5 sm:gap-2">
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
    <div class="space-y-3 sm:space-y-4">
        @include('shop-owner.orders.partials.order-tabs')
        @include('shop-owner.orders.partials.order-summary-card', ['order' => $order])

        @php($latestResolvedRevision = $order->latestResolvedRevision)
        @php($rawDecisionNote = $latestResolvedRevision?->manager_note ?: $order->manager_note ?: $order->update_reason)
        @php($latestDecisionNote = ($rawDecisionNote && str_contains(strtolower($rawDecisionNote), 'automatically approved')) ? null : $rawDecisionNote)
        @php($latestDecisionSummary = match (true) {
            $latestResolvedRevision?->status === 'rejected' => 'Update request rejected. Try again in tomorrow\'s order.',
            $latestResolvedRevision?->status === 'blocked' => 'Update request could not be applied because purchasing was already locked.',
            $latestResolvedRevision?->status === 'applied' && ! $latestResolvedRevision->isFullyAccepted() => $latestResolvedRevision->acceptedItemsCount().' of '.$latestResolvedRevision->items->count().' item changes accepted. Update request solved.',
            $latestResolvedRevision?->status === 'applied' => 'All requested changes were accepted.',
            $order->reviewed_at && $order->state === 'rejected' => ((float) $order->items->sum('approved_qty') > 0 ? 'Request partially accepted after review.' : 'Request rejected after review.'),
            default => null,
        })

        @if ($latestDecisionNote || $latestDecisionSummary)
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-3.5 shadow-xs">
                <p class="text-[9px] font-black uppercase tracking-wider text-amber-700 sm:text-[10px]">Purchase Manager Note</p>
                @if ($latestDecisionSummary)
                    <p class="mt-1 text-xs font-bold leading-5 text-amber-900">{{ $latestDecisionSummary }}</p>
                @endif
                @if ($latestDecisionNote)
                    <p class="mt-0.5 text-xs font-semibold leading-5 text-amber-900">{{ $latestDecisionNote }}</p>
                @endif
            </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs sm:p-4">
            <h2 class="text-base font-black text-slate-950 sm:text-lg">Cart Items</h2>
            <div class="mt-3">
                @include('shop-owner.orders.partials.order-items-table', ['order' => $order])
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-3 shadow-xs sm:p-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-black text-slate-950 sm:text-lg">Approval History</h2>
                <a href="{{ route('shop-owner.orders.history') }}" class="text-xs font-bold text-emerald-700 hover:text-emerald-900">Open all &rarr;</a>
            </div>
            <div class="mt-3">
                @include('requisitions.partials.review-history', ['order' => $order])
            </div>
        </section>
    </div>
@endsection
