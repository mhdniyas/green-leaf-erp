<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Operations</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">Today and Tomorrow</h2>
        </div>
        @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.index'), 'label' => 'Open Cart', 'classes' => 'border border-slate-200 bg-white text-slate-800'])
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-2">
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Today’s Delivery</p>
            @if ($todayOrder)
                <p class="mt-3 text-lg font-black text-slate-900">{{ $todayOrder->order_number }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ $todayOrder->items->count() }} items</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @include('shop-owner.orders.partials.order-status-badge', ['order' => $todayOrder])
                    @if ($todayOrder->is_allocation_completed && ! $todayOrder->is_delivered)
                        @include('shop-owner.components.action-button', ['href' => route('shop-owner.deliveries.show', $todayOrder->order_number), 'label' => 'Verify Delivery', 'classes' => 'bg-indigo-600 text-white'])
                    @endif
                </div>
            @else
                @include('shop-owner.components.empty-state', ['title' => 'No delivery scheduled', 'description' => 'There is no shop delivery assigned for today.'])
            @endif
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Tomorrow’s Cart</p>
            @if ($tomorrowOrder)
                <p class="mt-3 text-lg font-black text-slate-900">{{ $tomorrowOrder->order_number }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ $tomorrowOrder->items->count() }} items, {{ number_format((float) $tomorrowOrder->items->sum('requested_qty'), 2) }} total qty</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    @include('shop-owner.orders.partials.order-status-badge', ['order' => $tomorrowOrder])
                    @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.show', $tomorrowOrder->order_number), 'label' => 'Open Cart', 'classes' => 'bg-slate-900 text-white'])
                    @php
                        $purchaseOrdersLockedForTomorrow = $tomorrowOrder->linkedPurchaseOrdersHaveGoodsReceived();
                        $canRequestUpdate = !$tomorrowOrder->canEditDirectly() && 
                                            (in_array($tomorrowOrder->state, ['submitted', 'update_requested'], true) || 
                                             ($tomorrowOrder->state === 'approved' && !$purchaseOrdersLockedForTomorrow));
                    @endphp
                    @if ($canRequestUpdate)
                        @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.create'), 'label' => 'Request Items', 'classes' => 'bg-amber-600 text-white hover:bg-amber-700'])
                    @endif
                </div>
            @else
                @include('shop-owner.components.empty-state', ['title' => 'No cart submitted', 'description' => 'Open the marketplace and build tomorrow’s cart before '.app(\App\Services\Purchasing\PurchaserBusinessDayService::class)->cutoffLabel().'.', 'actionLabel' => 'Open Marketplace', 'actionUrl' => route('shop-owner.orders.create')])
            @endif
        </div>
    </div>
</section>
