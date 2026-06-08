<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <h3 class="text-lg font-black text-slate-950">Damaged or Missing</h3>
    <p class="mt-2 text-sm text-slate-600">Shortage and discrepancy values are derived from the received update submitted during delivery verification.</p>

    <div class="mt-5 space-y-3">
        @foreach ($order->items as $item)
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="font-bold text-slate-900">{{ $item->product->name }}</p>
                        <div class="mt-2">
                            @include('shop-owner.components.status-badge', ['label' => $item->warehouseWorkflowLabel(), 'tone' => $item->warehouseWorkflowTone()])
                        </div>
                    </div>
                    <span class="text-sm font-black text-red-600">{{ number_format((float) ($item->shortage_qty ?? 0), 2) }} {{ $item->unit }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-600">Shortage value: Rs. {{ number_format((float) ($item->shortage_value ?? 0), 2) }}</p>
            </div>
        @endforeach
    </div>
</section>
