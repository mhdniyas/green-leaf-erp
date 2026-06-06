<div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">{{ isset($isDraft) && $isDraft ? 'Draft Summary' : 'Order Summary' }}</p>
    @if (isset($isDraft) && $isDraft)
        @php
            $draftItems = isset($order) && $order ? (int) $order->items->count() : (int) optional($yesterdayOrder?->items)->count();
            $draftQuantity = isset($order) && $order ? (float) $order->items->sum('requested_qty') : (float) optional($yesterdayOrder?->items)->sum('requested_qty');
        @endphp
        @if (isset($order) && $order)
            <p class="mt-3 text-lg font-black text-slate-900">{{ $order->order_number }}</p>
        @else
            <p class="mt-3 text-sm text-slate-600">Use frequent products, custom lists, or search to build tomorrow’s demand quickly.</p>
        @endif
        <p class="mt-2 text-lg font-black text-slate-900">
            <span data-order-total-items>{{ $draftItems }}</span> items ·
            <span data-order-total-qty>{{ number_format($draftQuantity, 2, '.', '') }}</span> total qty
        </p>
        <p class="mt-2 text-sm font-bold text-cyan-700">Estimated value · INR <span data-order-total-value>0.00</span></p>
        @if (isset($order) && $order)
            <div class="mt-4 flex flex-wrap gap-2">
                @include('shop-owner.orders.partials.order-status-badge', ['order' => $order])
                @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.show', $order->order_number), 'label' => 'Open', 'classes' => 'bg-slate-900 text-white'])
            </div>
        @endif
    @elseif (isset($order) && $order)
        <p class="mt-3 text-lg font-black text-slate-900">{{ $order->order_number }}</p>
        <p class="mt-1 text-sm text-slate-600">{{ $order->items->count() }} items · {{ number_format((float) $order->items->sum('requested_qty'), 2) }} total qty</p>
        <div class="mt-4 flex flex-wrap gap-2">
            @include('shop-owner.orders.partials.order-status-badge', ['order' => $order])
            @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.show', $order->order_number), 'label' => 'Open', 'classes' => 'bg-slate-900 text-white'])
        </div>
    @else
        @php
            $estimatedQuantity = (float) optional($yesterdayOrder?->items)->sum('requested_qty');
            $estimatedItems = (int) optional($yesterdayOrder?->items)->count();
        @endphp
        <p class="mt-3 text-sm text-slate-600">Use yesterday’s order as the starting point for tomorrow’s demand.</p>
        <p class="mt-2 text-lg font-black text-slate-900">{{ $estimatedItems }} items · {{ number_format($estimatedQuantity, 2) }} suggested qty</p>
        <p class="mt-2 text-sm font-bold text-cyan-700">Estimated value · INR <span data-order-total-value>0.00</span></p>
    @endif
</div>
