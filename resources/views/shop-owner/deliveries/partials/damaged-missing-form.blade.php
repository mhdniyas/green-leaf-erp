@php
    $discrepancyItems = $order->items->filter(fn ($item) => (float) ($item->shortage_qty ?? 0) > 0);
@endphp

<section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
    <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4">
        <div>
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Step 3</p>
            <h3 class="mt-1 text-lg font-black text-slate-950">Shortage Summary</h3>
            <p class="mt-2 text-sm text-slate-600">This section shows product-level shortage impact that will affect the daily invoice if approved.</p>
        </div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-600">
            {{ $discrepancyItems->count() }} products
        </span>
    </div>

    @if ($discrepancyItems->isEmpty())
        <div class="mt-5 rounded-[1.75rem] border border-emerald-200 bg-emerald-50 p-5 text-center">
            <p class="text-sm font-black text-emerald-800">No shortage recorded.</p>
            <p class="mt-1 text-xs font-semibold text-emerald-700">If all items are received fully, finance will continue without discrepancy approval.</p>
        </div>
    @else
        <div class="mt-5 space-y-3">
            @foreach ($discrepancyItems as $item)
                <article class="rounded-[1.75rem] border border-amber-200 bg-amber-50/70 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h4 class="text-base font-black text-slate-950">{{ $item->product->name }}</h4>
                            <p class="mt-1 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">{{ strtoupper($item->unit) }}</p>
                        </div>
                        @include('shop-owner.components.status-badge', ['label' => $item->warehouseWorkflowLabel(), 'tone' => $item->warehouseWorkflowTone()])
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl bg-white p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Short Qty</p>
                            <p class="mt-1 text-sm font-black text-amber-800">{{ number_format((float) ($item->shortage_qty ?? 0), 2) }} {{ $item->unit }}</p>
                        </div>
                        <div class="rounded-2xl bg-white p-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Invoice Impact</p>
                            <p class="mt-1 text-sm font-black text-red-600">Rs. {{ number_format((float) ($item->shortage_value ?? 0), 2) }}</p>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
