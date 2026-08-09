@php
    $approvedDisplayCutoff = today()->setTime(12, 0);
    $showTodayOrder = $todayOrder
        && (
            $todayOrder->state !== 'approved'
            || now()->lt($approvedDisplayCutoff)
            || ($todayOrder->is_allocation_completed && ! $todayOrder->is_delivered)
        );
@endphp

<section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs sm:p-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-[9px] font-black uppercase tracking-wider text-emerald-700 sm:text-[10px]">Operations</p>
            <h2 class="mt-0.5 text-base font-black text-slate-950 sm:text-lg">Today and Tomorrow</h2>
        </div>
        <a href="{{ route('shop-owner.orders.index') }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 text-[10px] font-bold text-slate-800 hover:bg-slate-50 transition-colors">
            Open Cart
        </a>
    </div>

    <div class="mt-3 grid gap-2.5 md:grid-cols-2">
        {{-- Today's Delivery Card --}}
        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-2.5 sm:p-3">
            <p class="text-[9px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Today’s Delivery</p>
            @if ($showTodayOrder)
                <div class="mt-2 flex items-center justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-black text-slate-900 truncate">{{ $todayOrder->order_number }}</span>
                            <span class="text-[10px] font-semibold text-slate-500">· {{ $todayOrder->items->count() }} items</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        @include('shop-owner.orders.partials.order-status-badge', ['order' => $todayOrder])
                        @if ($todayOrder->is_allocation_completed && ! $todayOrder->is_delivered)
                            <a href="{{ route('shop-owner.deliveries.show', $todayOrder->order_number) }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-2.5 py-1 text-xs font-bold text-white hover:bg-indigo-700 transition-colors">
                                Verify &rarr;
                            </a>
                        @endif
                    </div>
                </div>
            @elseif ($todayOrder && $todayOrder->state === 'approved')
                <div class="mt-2 text-xs font-semibold text-slate-500">
                    Today’s approved order is hidden after 12:00 PM. Check tomorrow’s cart.
                </div>
            @else
                <div class="mt-2 text-xs font-semibold text-slate-500">
                    No shop delivery assigned for today.
                </div>
            @endif
        </div>

        {{-- Tomorrow's Cart Card --}}
        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-2.5 sm:p-3">
            <p class="text-[9px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Tomorrow’s Cart</p>
            @if ($tomorrowOrder)
                <div class="mt-2 flex items-center justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-black text-slate-900 truncate">{{ $tomorrowOrder->order_number }}</span>
                            <span class="text-[10px] font-semibold text-slate-500">· {{ $tomorrowOrder->items->count() }} items</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        @include('shop-owner.orders.partials.order-status-badge', ['order' => $tomorrowOrder])
                        <a href="{{ route('shop-owner.orders.show', $tomorrowOrder->order_number) }}" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-2.5 py-1 text-xs font-bold text-white hover:bg-slate-800 transition-colors">
                            Open &rarr;
                        </a>
                    </div>
                </div>
            @else
                <div class="mt-2 flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold text-slate-500">No cart submitted yet for tomorrow.</span>
                    <a href="{{ route('shop-owner.orders.create') }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-bold text-white hover:bg-emerald-700 transition-colors shrink-0">
                        Order &rarr;
                    </a>
                </div>
            @endif
        </div>
    </div>
</section>
