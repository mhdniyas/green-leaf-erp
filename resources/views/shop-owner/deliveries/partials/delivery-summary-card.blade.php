@php
    $sortedItems = $order->items->sortBy(
        fn ($item) => \App\Models\Product::sortableSku((string) ($item->product?->sku ?? ''))
    );
    $displayDeliveredQuantity = $order->hasPendingDeliveryReview()
        ? (float) $order->items->sum('shop_reported_received_qty')
        : (float) $order->items->sum('delivered_qty');
    $isClientShop = (bool) $order->shop?->client_id;
@endphp

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:rounded-[2rem]">
    <div class="bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_38%),linear-gradient(135deg,_#ffffff,_#f8fafc)] px-3 py-3.5 sm:px-6 sm:py-6">
        <div class="flex flex-col gap-2.5 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500 sm:text-[11px] sm:tracking-[0.18em]">Delivery Ref</p>
                <h2 class="mt-0.5 break-all text-base font-black leading-tight text-slate-950 sm:mt-1 sm:text-2xl">{{ $order->order_number }}</h2>
                <p class="mt-1 text-[11px] font-semibold leading-tight text-slate-600 sm:mt-2 sm:text-sm">{{ $order->business_date->format('d M Y') }} · {{ $order->shop->name }}</p>
            </div>
            <div class="flex flex-wrap gap-1.5 sm:gap-2">
                <span class="rounded-full {{ $isClientShop ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-700' }} px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.1em] shadow-sm sm:px-3 sm:py-1 sm:text-xs sm:tracking-[0.16em]">
                    {{ $isClientShop ? 'Client: '.($order->shop?->client?->name ?? 'Aishwarya Veg') : 'Direct Sales' }}
                </span>
                @include('shop-owner.deliveries.partials.delivery-status-badge', ['order' => $order])
                <span class="rounded-full bg-white px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.1em] text-slate-700 shadow-sm sm:px-3 sm:py-1 sm:text-xs sm:tracking-[0.16em]">
                    {{ $order->items->count() }} Items
                </span>
            </div>
        </div>

        <div class="mt-3 grid grid-cols-2 gap-2 sm:mt-5 sm:gap-3 xl:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-2.5 sm:rounded-[1.5rem] sm:p-4">
                <p class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[10px] sm:tracking-[0.16em]">Approved Qty</p>
                <p class="mt-1 text-lg font-black text-slate-950 sm:mt-2 sm:text-2xl">{{ number_format((float) $order->items->sum('approved_qty'), 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-2.5 sm:rounded-[1.5rem] sm:p-4">
                <p class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[10px] sm:tracking-[0.16em]">Delivered Qty</p>
                <p class="mt-1 text-lg font-black text-slate-950 sm:mt-2 sm:text-2xl">{{ number_format($displayDeliveredQuantity, 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-2.5 sm:rounded-[1.5rem] sm:p-4">
                <p class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[10px] sm:tracking-[0.16em]">Invoice Total</p>
                <p class="mt-1 text-lg font-black text-slate-950 sm:mt-2 sm:text-2xl">Rs. {{ number_format((float) ($order->invoice?->final_total ?? 0), 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-2.5 sm:rounded-[1.5rem] sm:p-4">
                <p class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[10px] sm:tracking-[0.16em]">Warehouse Stage</p>
                <p class="mt-1 text-xs font-black leading-tight text-slate-950 sm:mt-2 sm:text-base">{{ $order->warehouseWorkflowLabel() }}</p>
            </div>
        </div>
    </div>

    <div class="border-t border-slate-100 px-3 py-3 sm:px-6 sm:py-5">
        <div class="flex items-center justify-between gap-2 sm:gap-3">
            <div>
                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500 sm:text-[11px] sm:tracking-[0.18em]">Product Progress</p>
                <p class="mt-0.5 text-[11px] text-slate-600 sm:mt-1 sm:text-sm">Loaded stock to confirmed delivered quantity.</p>
            </div>
        </div>

        <div class="mt-2.5 grid gap-2 sm:mt-4 sm:gap-3 md:grid-cols-2">
            @foreach ($sortedItems as $item)
                <article class="rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-2 sm:rounded-[1.5rem] sm:px-4 sm:py-3">
                    <div class="flex items-start justify-between gap-2 sm:gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-black leading-tight text-slate-950 sm:text-base">{{ $item->product->name }}</p>
                            <p class="mt-0.5 text-[9px] font-bold tracking-[0.1em] text-slate-500 sm:mt-1 sm:text-[11px] sm:tracking-[0.14em]">Code {{ $item->product->sku }} · {{ strtoupper($item->unit) }}</p>
                        </div>
                        @include('shop-owner.components.status-badge', ['label' => $item->warehouseWorkflowLabel(), 'tone' => $item->warehouseWorkflowTone()])
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
