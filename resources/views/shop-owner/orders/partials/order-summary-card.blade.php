<div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs">
    <div class="flex items-center justify-between gap-2">
        <p class="text-[9px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">
            {{ isset($isDraft) && $isDraft ? 'Cart Summary' : 'Approval Summary' }}
        </p>
        @if (isset($order) && $order)
            @include('shop-owner.orders.partials.order-status-badge', ['order' => $order])
        @endif
    </div>

    @if (isset($isDraft) && $isDraft)
        @php
            $draftItems = isset($order) && $order ? (int) $order->items->count() : (int) optional($yesterdayOrder?->items)->count();
            $draftQuantity = isset($order) && $order ? (float) $order->items->sum('requested_qty') : (float) optional($yesterdayOrder?->items)->sum('requested_qty');
        @endphp
        <div class="mt-2 flex items-center justify-between gap-2">
            <div>
                @if (isset($order) && $order)
                    <p class="text-sm font-black text-slate-900">{{ $order->order_number }}</p>
                @endif
                <p class="text-xs font-semibold text-slate-600">
                    <span data-order-total-items>{{ $draftItems }}</span> items ·
                    <span data-order-total-qty>{{ number_format($draftQuantity, 2, '.', '') }}</span> total qty
                </p>
            </div>
            @if (isset($order) && $order)
                <a href="{{ route('shop-owner.orders.show', $order->order_number) }}" class="inline-flex items-center justify-center rounded-lg bg-slate-950 px-3 py-1 text-xs font-bold text-white hover:bg-slate-800 transition">
                    Open &rarr;
                </a>
            @endif
        </div>
    @elseif (isset($order) && $order)
        <div class="mt-2 flex items-center justify-between gap-2">
            <div>
                <p class="text-sm font-black text-slate-900">{{ $order->order_number }}</p>
                <p class="text-xs font-semibold text-slate-600">{{ $order->items->count() }} items · {{ number_format((float) $order->items->sum('requested_qty'), 2) }} total qty</p>
            </div>
            <a href="{{ route('shop-owner.orders.show', $order->order_number) }}" class="inline-flex items-center justify-center rounded-lg bg-slate-950 px-3 py-1 text-xs font-bold text-white hover:bg-slate-800 transition">
                Open &rarr;
            </a>
        </div>
    @else
        @php
            $estimatedQuantity = (float) optional($yesterdayOrder?->items)->sum('requested_qty');
            $estimatedItems = (int) optional($yesterdayOrder?->items)->count();
        @endphp
        <div class="mt-2">
            <p class="text-xs font-semibold text-slate-600">Use yesterday’s order as the starting point for tomorrow’s demand.</p>
            <p class="mt-1 text-sm font-black text-slate-900">{{ $estimatedItems }} items · {{ number_format($estimatedQuantity, 2) }} suggested qty</p>
        </div>
    @endif
</div>
