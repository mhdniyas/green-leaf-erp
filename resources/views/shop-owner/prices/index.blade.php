@extends('shop-owner.layouts.app')

@section('title', 'Daily Price Board')
@section('page_title', 'Daily Price Board')
@section('page_description', 'Review effective product prices for the selected business date and add items directly into tomorrow\'s order.')
@php
    $breadcrumbs = [
        ['label' => 'Daily Price Board'],
    ];
@endphp

@section('page_actions')
    @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.create'), 'label' => 'Go To Order Page', 'classes' => 'bg-emerald-600 text-white'])
@endsection

@section('content')
    @php
        $priceBoardProducts = $products->map(function ($product) use ($dailyPrices, $priceDate) {
            $dailyPrice = $dailyPrices[$product->id] ?? null;
            $effectivePrice = $dailyPrice !== null ? (float) $dailyPrice : (float) $product->base_price;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => strtoupper($product->unit),
                'category' => $product->category?->name,
                'base_price' => (float) $product->base_price,
                'daily_price' => $dailyPrice !== null ? (float) $dailyPrice : null,
                'effective_price' => $effectivePrice,
                'order_count' => (int) ($product->order_count ?? 0),
                'last_order_quantity' => (float) ($product->last_order_quantity ?? 0),
                'total_ordered_quantity' => (float) ($product->total_ordered_quantity ?? 0),
                'order_url' => route('shop-owner.orders.create', ['product' => $product->id, 'price_date' => $priceDate->toDateString()]),
            ];
        });
    @endphp

    <div class="space-y-6">
        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <form method="GET" action="{{ route('shop-owner.prices.index') }}" class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Price Date</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">{{ $priceDate->format('d F Y') }}</h2>
                    <p class="mt-2 text-sm text-slate-600">Use these live prices while building tomorrow's order.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div>
                        <label for="date" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Business Date</label>
                        <input id="date" type="date" name="date" value="{{ $priceDate->toDateString() }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-slate-900 px-5 py-3 text-sm font-black text-white">
                        Load Prices
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Find Products Fast</p>
                    <h3 class="mt-1 text-lg font-black text-slate-950">Search and sort before you add</h3>
                    <p class="mt-2 text-sm text-slate-600">Search is pinned at the top, and you can sort the board by frequently ordered items, name, or effective price.</p>
                </div>
                <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_220px] lg:min-w-[560px]">
                    <div>
                        <label for="price-board-search" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Search Products</label>
                        <input id="price-board-search" type="search" data-price-board-search placeholder="Search by name, SKU, or category" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="price-board-sort" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Sort By</label>
                        <select id="price-board-sort" data-price-board-sort class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                            <option value="frequent">Frequently Ordered</option>
                            <option value="name">Product Name</option>
                            <option value="price_low">Price: Low to High</option>
                            <option value="price_high">Price: High to Low</option>
                        </select>
                    </div>
                </div>
            </div>

            @if ($frequentProducts->isNotEmpty())
                <div class="mt-5 border-t border-slate-100 pt-5">
                    <div class="flex items-center justify-between gap-3">
                        <h4 class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Frequently Ordered Items</h4>
                        <span class="text-[10px] font-semibold text-slate-400">Based on recent shop orders</span>
                    </div>
                    <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        @foreach ($frequentProducts->take(4) as $frequentProduct)
                            @php
                                $product = $frequentProduct['product'];
                                $effectivePrice = (float) ($dailyPrices[$product->id] ?? $product->base_price);
                            @endphp
                            <button
                                type="button"
                                data-open-price-board-modal
                                data-product-id="{{ $product->id }}"
                                class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 text-left transition hover:border-emerald-300 hover:bg-emerald-50"
                            >
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">Ordered {{ $frequentProduct['order_count'] }} times</p>
                                <h5 class="mt-2 text-sm font-black text-slate-950">{{ $product->name }}</h5>
                                <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ $product->sku }} · {{ strtoupper($product->unit) }}</p>
                                <div class="mt-3 flex items-center justify-between gap-3">
                                    <span class="text-sm font-black text-cyan-700">INR {{ number_format($effectivePrice, 2) }}</span>
                                    <span class="text-[11px] font-bold text-slate-500">Last Qty {{ number_format((float) $frequentProduct['last_quantity'], 2) }}</span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-200 px-6 py-5">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-lg font-black text-slate-950">Effective Product Prices</h3>
                        <p class="mt-1 text-sm text-slate-500">Daily price overrides base price only when it is set for this date.</p>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Product</th>
                            <th class="px-5 py-4">SKU</th>
                            <th class="px-5 py-4 text-right">Base Price</th>
                            <th class="px-5 py-4 text-right">Daily Price</th>
                            <th class="px-5 py-4 text-right">Effective</th>
                            <th class="px-5 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm" data-price-board-body>
                        @foreach ($products as $product)
                            @php
                                $dailyPrice = $dailyPrices[$product->id] ?? null;
                                $effectivePrice = $dailyPrice !== null ? (float) $dailyPrice : (float) $product->base_price;
                            @endphp
                            <tr
                                data-price-board-row
                                data-product-id="{{ $product->id }}"
                                data-search-text="{{ \Illuminate\Support\Str::lower(trim($product->name.' '.$product->sku.' '.($product->category?->name ?? ''))) }}"
                                data-name="{{ \Illuminate\Support\Str::lower($product->name) }}"
                                data-effective-price="{{ number_format($effectivePrice, 2, '.', '') }}"
                                data-order-count="{{ (int) ($product->order_count ?? 0) }}"
                                data-last-quantity="{{ number_format((float) ($product->last_order_quantity ?? 0), 2, '.', '') }}"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-bold text-slate-950">{{ $product->name }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-400">{{ $product->category?->name }} · {{ strtoupper($product->unit) }}</p>
                                </td>
                                <td class="px-5 py-4 font-semibold text-slate-500">{{ $product->sku }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-slate-700">INR {{ number_format((float) $product->base_price, 2) }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-slate-700">{{ $dailyPrice !== null ? 'INR '.number_format((float) $dailyPrice, 2) : 'Base Price' }}</td>
                                <td class="px-5 py-4 text-right font-black text-slate-950">INR {{ number_format($effectivePrice, 2) }}</td>
                                <td class="px-5 py-4 text-right">
                                    <button
                                        type="button"
                                        data-open-price-board-modal
                                        data-product-id="{{ $product->id }}"
                                        class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white shadow-sm hover:bg-emerald-700"
                                    >
                                        Add To Tomorrow Order
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div id="price-board-add-modal" class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-slate-950/60" data-close-price-board-modal></div>
        <div class="relative flex min-h-full items-end justify-center p-4 sm:items-center">
            <div class="w-full max-w-lg rounded-[2rem] bg-white shadow-2xl">
                <div class="border-b border-slate-100 px-6 py-5">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Add To Tomorrow Order</p>
                    <h3 class="mt-2 text-xl font-black text-slate-950" data-modal-product-name>Select product</h3>
                    <p class="mt-2 text-sm text-slate-500">Review the daily price, set a starting quantity, and add it to the draft order without leaving this page.</p>
                </div>
                <div class="space-y-5 px-6 py-5">
                    <div class="grid gap-3 rounded-2xl bg-slate-50 p-4 sm:grid-cols-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">SKU</p>
                            <p class="mt-1 text-sm font-bold text-slate-900" data-modal-product-sku>-</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Effective Price</p>
                            <p class="mt-1 text-sm font-bold text-cyan-700" data-modal-product-price>-</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Recent Use</p>
                            <p class="mt-1 text-sm font-bold text-slate-900" data-modal-product-frequency>-</p>
                        </div>
                    </div>

                    <div>
                        <label for="price-board-modal-qty" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Starting Quantity</label>
                        <div class="mt-2 flex items-center gap-2">
                            <button type="button" data-price-board-qty-step="-1" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-lg font-black text-slate-700">-</button>
                            <input id="price-board-modal-qty" type="number" min="0.01" step="0.01" value="1.00" data-modal-product-qty class="h-11 flex-1 rounded-2xl border border-slate-200 bg-white px-4 text-center text-base font-black text-slate-900 focus:border-emerald-500 focus:outline-none">
                            <button type="button" data-price-board-qty-step="1" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-lg font-black text-slate-700">+</button>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Add multiple items here first, then open the order page when the draft is ready.</p>
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-3 border-t border-slate-100 px-6 py-5 sm:flex-row sm:justify-end">
                    <button type="button" data-close-price-board-modal class="rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-700">Cancel</button>
                    <button type="button" data-modal-add-draft class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-black text-white shadow-sm hover:bg-emerald-700">
                        Add To Draft Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="price-board-toast" class="pointer-events-none fixed right-4 top-24 z-50 hidden max-w-sm rounded-2xl bg-slate-950 px-4 py-3 text-sm font-bold text-white shadow-2xl">
        <span data-price-board-toast-message>Item added.</span>
    </div>

    <script id="shop-owner-price-board-data" type="application/json">
        {!! $priceBoardProducts->values()->toJson() !!}
    </script>
@endsection
