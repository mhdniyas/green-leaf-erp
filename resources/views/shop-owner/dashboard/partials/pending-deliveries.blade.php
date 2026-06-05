<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between gap-4">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Dispatch Follow-up</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">Pending Deliveries</h2>
        </div>
        @include('shop-owner.components.action-button', ['href' => route('shop-owner.deliveries.index'), 'label' => 'All Deliveries', 'classes' => 'border border-slate-200 bg-white text-slate-800'])
    </div>

    <div class="mt-5 space-y-3">
        @forelse ($pendingDeliveries as $order)
            <a href="{{ route('shop-owner.deliveries.show', $order->order_number) }}" class="block rounded-3xl border border-slate-200 bg-slate-50 p-4 transition hover:border-slate-300 hover:bg-white">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-black text-slate-900">{{ $order->order_number }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $order->business_date->format('d M Y') }} · {{ $order->items->count() }} items</p>
                    </div>
                    @include('shop-owner.deliveries.partials.delivery-status-badge', ['order' => $order])
                </div>
            </a>
        @empty
            @include('shop-owner.components.empty-state', ['title' => 'Nothing pending', 'description' => 'All allocated orders have either been received or no dispatch is waiting on this shop.'])
        @endforelse
    </div>
</section>
