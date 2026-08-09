<section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs sm:p-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-[9px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Dispatch Follow-up</p>
            <h2 class="mt-0.5 text-base font-black text-slate-950 sm:text-lg">Pending Deliveries</h2>
        </div>
        <a href="{{ route('shop-owner.deliveries.index') }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 text-[10px] font-bold text-slate-800 hover:bg-slate-50 transition-colors">
            All Deliveries
        </a>
    </div>

    <div class="mt-3 space-y-1.5">
        @forelse ($pendingDeliveries as $order)
            @php
                $tone = $order->warehouseWorkflowTone();
                $badgeClass = match($tone) {
                    'success' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'warning' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'danger' => 'bg-red-50 text-red-700 border-red-200',
                    'info' => 'bg-blue-50 text-blue-700 border-blue-200',
                    default => 'bg-slate-100 text-slate-700 border-slate-200',
                };
            @endphp
            <article class="flex items-center justify-between gap-1.5 rounded-lg border border-slate-200 bg-white p-2 shadow-xs transition hover:border-slate-300">
                <div class="min-w-0 flex-1 flex items-center gap-1.5">
                    <span class="text-[11px] font-black text-slate-900 whitespace-nowrap">{{ $order->business_date->format('d M') }}</span>
                    <span class="truncate font-mono text-[9px] font-semibold text-slate-400 max-w-[65px] sm:max-w-none">{{ $order->order_number }}</span>
                    <span class="hidden text-[10px] text-slate-500 sm:inline">· {{ $order->items->count() }} items</span>
                </div>

                <div class="shrink-0">
                    <span class="inline-flex items-center rounded-full border px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wider whitespace-nowrap {{ $badgeClass }}">
                        {{ $order->warehouseWorkflowLabel() }}
                    </span>
                </div>

                <div class="shrink-0">
                    <a href="{{ route('shop-owner.deliveries.show', $order->order_number) }}" class="inline-flex items-center justify-center rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 hover:bg-emerald-100 transition-colors">
                        Open &rarr;
                    </a>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-center">
                <p class="text-xs font-bold text-slate-500">No pending deliveries dispatch waiting.</p>
            </div>
        @endforelse
    </div>
</section>
