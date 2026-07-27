@php
    $sortedItems = $order->items->sortBy(
        fn ($item) => \App\Models\Product::sortableSku((string) ($item->product?->sku ?? ''))
    );
    $displayDeliveredQuantity = $order->hasPendingDeliveryReview()
        ? (float) $order->items->sum('shop_reported_received_qty')
        : (float) $order->items->sum('delivered_qty');
    $isClientShop = (bool) $order->shop?->client_id;
@endphp

<section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
    <div class="bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.18),_transparent_38%),linear-gradient(135deg,_#ffffff,_#f8fafc)] px-5 py-6 sm:px-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Delivery Ref</p>
                <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $order->order_number }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ $order->business_date->format('d F Y') }} · {{ $order->shop->name }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="rounded-full {{ $isClientShop ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-700' }} px-3 py-1 text-xs font-black uppercase tracking-[0.16em] shadow-sm">
                    {{ $isClientShop ? 'Client: '.($order->shop?->client?->name ?? 'Aishwarya Veg') : 'Direct Sales' }}
                </span>
                @include('shop-owner.deliveries.partials.delivery-status-badge', ['order' => $order])
                <span class="rounded-full bg-white px-3 py-1 text-xs font-black uppercase tracking-[0.16em] text-slate-700 shadow-sm">
                    {{ $order->items->count() }} Items
                </span>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Approved Qty</p>
                <p class="mt-2 text-2xl font-black text-slate-950">{{ number_format((float) $order->items->sum('approved_qty'), 2) }}</p>
            </div>
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Delivered Qty</p>
                <p class="mt-2 text-2xl font-black text-slate-950">{{ number_format($displayDeliveredQuantity, 2) }}</p>
            </div>
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Shortage Value</p>
                <p class="mt-2 text-2xl font-black text-red-600">Rs. {{ number_format((float) $order->total_shortage_value, 2) }}</p>
            </div>
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-4">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Warehouse Stage</p>
                <p class="mt-2 text-base font-black text-slate-950">{{ $order->warehouseWorkflowLabel() }}</p>
            </div>
        </div>
    </div>

    <div class="border-t border-slate-100 px-5 py-5 sm:px-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Product Progress</p>
                <p class="mt-1 text-sm text-slate-600">Each product moves from loaded stock to confirmed delivered quantity.</p>
            </div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-2">
            @foreach ($sortedItems as $item)
                <article class="rounded-[1.5rem] border border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-black text-slate-950">{{ $item->product->name }}</p>
                            <p class="mt-1 text-[11px] font-bold tracking-[0.14em] text-slate-500">Code {{ $item->product->sku }} · {{ strtoupper($item->unit) }}</p>
                        </div>
                        @include('shop-owner.components.status-badge', ['label' => $item->warehouseWorkflowLabel(), 'tone' => $item->warehouseWorkflowTone()])
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
