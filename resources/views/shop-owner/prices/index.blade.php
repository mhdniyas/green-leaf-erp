@extends('shop-owner.layouts.app')

@section('title', 'Daily Price Board')
@section('page_title', 'Daily Price Board')
@section('page_description', 'Review current selling prices and add items to the draft order.')
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
        $priceBoardProducts = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => strtoupper($product->unit),
                'category' => $product->category?->name,
                'effective_price' => (float) ($product->effective_price ?? 0),
                'order_count' => (int) ($product->order_count ?? 0),
                'last_order_quantity' => (float) ($product->last_order_quantity ?? 0),
                'total_ordered_quantity' => (float) ($product->total_ordered_quantity ?? 0),
                'order_url' => route('shop-owner.orders.create', ['product' => $product->id]),
            ];
        });
    @endphp

    <div class="space-y-6">
        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Current Price Group</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">{{ $priceGroup->display_name }}</h2>
                    <p class="mt-2 text-sm text-slate-600">These are final selling prices for your shop.</p>
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
                        <h3 class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Frequently Ordered Items</h3>
                        <span class="text-[10px] font-semibold text-slate-400">Based on recent shop orders</span>
                    </div>
                    <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        @foreach ($frequentProducts->take(4) as $frequentProduct)
                            @php
                                $product = $products->firstWhere('id', $frequentProduct['product']->id) ?? $frequentProduct['product'];
                            @endphp
                            <button
                                type="button"
                                data-open-price-board-modal
                                data-product-id="{{ $product->id }}"
                                class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 text-left transition hover:border-emerald-300 hover:bg-emerald-50"
                            >
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">Ordered {{ $frequentProduct['order_count'] }} times</p>
                                <h4 class="mt-2 text-sm font-black text-slate-950">{{ $product->name }}</h4>
                                <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ $product->sku }} · {{ strtoupper($product->unit) }}</p>
                                <div class="mt-3 flex items-center justify-between gap-3">
                                    <span class="text-sm font-black text-cyan-700">INR {{ number_format((float) ($product->effective_price ?? 0), 2) }}</span>
                                    <span class="text-[11px] font-bold text-slate-500">Last Qty {{ number_format((float) $frequentProduct['last_quantity'], 2) }}</span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <h3 class="text-lg font-black text-slate-950">Final Selling Prices</h3>
                <p class="mt-1 text-sm text-slate-500">Internal purchase costs are not shown here.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Product</th>
                            <th class="px-5 py-4">Availability</th>
                            <th class="px-5 py-4 text-right">Selling Price</th>
                            <th class="px-5 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm" data-price-board-body>
                        @foreach ($products as $product)
                                <tr
                                    data-price-board-row
                                    data-product-id="{{ $product->id }}"
                                    data-search-text="{{ \Illuminate\Support\Str::lower(trim($product->name.' '.$product->sku.' '.($product->category?->name ?? ''))) }}"
                                    data-name="{{ \Illuminate\Support\Str::lower($product->name) }}"
                                    data-effective-price="{{ number_format((float) ($product->effective_price ?? 0), 2, '.', '') }}"
                                    data-order-count="{{ (int) ($product->order_count ?? 0) }}"
                                    data-last-quantity="{{ number_format((float) ($product->last_order_quantity ?? 0), 2, '.', '') }}"
                                >
                                    <td class="px-5 py-4">
                                        <p class="font-bold text-slate-950">{{ $product->name }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-400">{{ $product->sku }} · {{ strtoupper($product->unit) }} · {{ $product->category?->name }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span @class([
                                            'rounded-full px-3 py-1 text-xs font-black',
                                            'bg-emerald-50 text-emerald-700' => ($product->availability_label ?? 'On request') === 'Available',
                                            'bg-amber-50 text-amber-700' => ($product->availability_label ?? 'On request') !== 'Available',
                                        ])>{{ $product->availability_label ?? 'On request' }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-right font-black text-slate-950">INR {{ number_format((float) ($product->effective_price ?? 0), 2) }}</td>
                                    <td class="px-5 py-4 text-right">
                                        <button
                                            type="button"
                                            data-open-price-board-modal
                                            data-product-id="{{ $product->id }}"
                                            class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white shadow-sm hover:bg-emerald-700"
                                        >
                                            Add To Draft
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
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Add To Draft Order</p>
                    <h3 class="mt-2 text-xl font-black text-slate-950" data-modal-product-name>Select product</h3>
                </div>
                <div class="space-y-5 px-6 py-5">
                    <div class="grid gap-3 rounded-2xl bg-slate-50 p-4 sm:grid-cols-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">SKU</p>
                            <p class="mt-1 text-sm font-bold text-slate-900" data-modal-product-sku>-</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Selling Price</p>
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
