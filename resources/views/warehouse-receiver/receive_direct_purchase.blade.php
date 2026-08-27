<x-layouts.app title="Receive Direct Purchase">
    <div class="mx-auto flex w-full max-w-xl min-w-0 flex-col gap-4 py-3 lg:px-4 lg:py-4">
        
        {{-- Hero Header Box --}}
        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_12px_28px_rgba(15,23,42,0.16)]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.25),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#065f46_100%)] px-4 py-4 sm:px-5">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <a href="{{ route('warehouse.receiver.checklist', ['date' => $order->business_date->format('Y-m-d'), 'tab' => 'pending']) }}" class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 text-white hover:bg-white/20 transition-all border border-white/10 shadow-sm cursor-pointer text-decoration-none">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </a>
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-emerald-300 leading-none mb-1">Direct Purchase Receive</p>
                            <h1 class="text-base font-black tracking-tight text-white">{{ $order->order_number }}</h1>
                            <span class="mt-1 inline-flex rounded-full bg-emerald-400/25 px-2 py-0.5 text-[9px] font-black uppercase text-emerald-100">
                                {{ $order->delivery_status === 'pending_delivery' ? 'Pending Receive' : str_replace('_', ' ', $order->delivery_status) }}
                            </span>
                        </div>
                    </div>
                    <span class="rounded-full bg-white/10 border border-white/10 px-2.5 py-1 text-[10px] font-black text-emerald-200">
                        Direct Purchase
                    </span>
                </div>
            </div>
        </section>

        @if (session('warning'))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900 shadow-sm">
                {{ session('warning') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-900 shadow-sm">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Direct Purchase Meta Card --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <h3 class="text-xs font-black uppercase tracking-[0.14em] text-slate-500 mb-3 pl-0.5">Order Information</h3>
            <div class="grid grid-cols-2 gap-y-3 gap-x-4 text-xs">
                <div>
                    <span class="text-slate-400 font-semibold">Date</span>
                    <p class="font-bold text-slate-800 mt-0.5">{{ $order->business_date->format('d M Y') }}</p>
                </div>
                <div>
                    <span class="text-slate-400 font-semibold">Manager Note</span>
                    <p class="font-bold text-slate-800 mt-0.5">{{ $order->manager_note ?: 'Green Leaf Direct Purchase' }}</p>
                </div>
                <div>
                    <span class="text-slate-400 font-semibold">Total Items</span>
                    <p class="font-bold text-slate-800 mt-0.5">{{ $order->items->count() }} product(s)</p>
                </div>
                <div>
                    <span class="text-slate-400 font-semibold">Total Quantity</span>
                    <p class="font-bold text-slate-800 mt-0.5">
                        {{ number_format((float) $order->items->sum(fn($item) => $item->approved_qty ?: $item->requested_qty), 2) }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Receive Form --}}
        <form action="{{ route('warehouse.receiver.direct-purchase.receive', $order) }}" method="POST" id="direct-purchase-receive-form" class="space-y-4">
            @csrf

            {{-- Default Target Warehouse Selection --}}
            <div class="rounded-2xl bg-emerald-50/50 border border-emerald-100 p-4 shadow-sm">
                <label class="block text-xs font-black uppercase tracking-[0.14em] text-emerald-800 mb-2">Default Target Warehouse</label>
                <div class="relative w-full">
                    <select id="default-warehouse-select" class="w-full appearance-none rounded-xl border border-emerald-200 bg-white pl-3 pr-8 py-2.5 text-sm font-bold text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 cursor-pointer">
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">
                                {{ $wh->name }} ({{ $wh->code }})
                            </option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
                <p class="text-[10px] text-emerald-600 font-bold mt-1.5 pl-0.5">Select a default warehouse. You can override this for individual products below.</p>
            </div>

            {{-- Product List --}}
            <div class="space-y-3">
                <h3 class="text-xs font-black uppercase tracking-[0.14em] text-slate-500 pl-1">Products to Receive ({{ $order->items->count() }})</h3>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm divide-y divide-slate-100">
                    @foreach($order->items as $item)
                        @php
                            $qty = (float) ($item->approved_qty ?: $item->requested_qty);
                            $defaultWhId = $item->product?->default_warehouse_id ?? $warehouses->first()?->id;
                        @endphp
                        <div class="py-3.5 first:pt-0 last:pb-0">
                            <div class="flex items-start justify-between gap-3 min-w-0">
                                <div class="min-w-0 flex-1">
                                    <h4 class="truncate text-sm font-black text-slate-950">{{ $item->product?->name ?? 'Product' }}</h4>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-600">
                                            {{ $item->product?->category?->name ?? 'General' }}
                                        </span>
                                        @if($item->product?->sku)
                                            <span class="text-[10px] font-mono font-bold text-slate-400">SKU: {{ $item->product->sku }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="text-sm font-black text-slate-900">{{ number_format($qty, 2) }}</span>
                                    <span class="text-xs font-bold text-slate-500 ml-0.5">{{ $item->unit ?: ($item->product?->unit ?? 'kg') }}</span>
                                </div>
                            </div>

                            {{-- Target Warehouse select --}}
                            <div class="mt-3 flex items-center justify-between gap-3">
                                <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Target Warehouse</span>
                                <div class="w-48 shrink-0 relative">
                                    <select name="items[{{ $item->id }}][warehouse_id]" 
                                            required
                                            class="product-warehouse-select w-full appearance-none rounded-xl border border-slate-200 bg-white pl-2.5 pr-8 py-1.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-400 cursor-pointer"
                                            data-default-warehouse-id="{{ $defaultWhId }}"
                                            data-manual="false">
                                        @foreach($warehouses as $wh)
                                            <option value="{{ $wh->id }}" @selected(old("items.{$item->id}.warehouse_id", $defaultWhId) == $wh->id)>
                                                {{ $wh->name }} ({{ $wh->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-500">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Submit Action --}}
            <div class="pt-2">
                <button type="submit" class="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-black text-white shadow-lg shadow-emerald-600/20 transition-all hover:bg-emerald-700 hover:shadow-emerald-600/30 cursor-pointer border-none">
                    Receive Direct Purchase into Warehouse
                </button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const defaultSelect = document.getElementById('default-warehouse-select');
            const productSelects = document.querySelectorAll('.product-warehouse-select');

            if (defaultSelect && productSelects.length > 0) {
                defaultSelect.addEventListener('change', (e) => {
                    const newWhId = e.target.value;
                    productSelects.forEach(select => {
                        if (select.dataset.manual !== 'true') {
                            select.value = newWhId;
                        }
                    });
                });

                productSelects.forEach(select => {
                    select.addEventListener('change', () => {
                        select.dataset.manual = 'true';
                    });
                });
            }
        });
    </script>
</x-layouts.app>
