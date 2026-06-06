@php
    $allProductsForOrder = collect();

    foreach ($productsByCategory as $category) {
        foreach ($category->products as $product) {
            $existingItem = $tomorrowOrder?->items->firstWhere('product_id', $product->id);
            $yesterdayItem = $yesterdayOrder?->items->firstWhere('product_id', $product->id);
            $currentQuantity = old("items.{$product->sku}", $existingItem?->requested_qty ?? '');
            $suggestedQuantity = (float) ($yesterdayItem?->requested_qty ?? 0);

            $allProductsForOrder->push([
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->unit,
                'category' => $category->name,
                'current_qty' => $currentQuantity,
                'suggested_qty' => $suggestedQuantity,
                'yesterday_qty' => $suggestedQuantity,
                'price' => (float) ($product->effective_price ?? $product->base_price ?? 0),
            ]);
        }
    }
@endphp

<div class="space-y-6">
    <div>
        <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Quick Order</p>
        <h3 class="mt-1 text-lg font-black text-slate-950">Fast row order entry</h3>
        <p class="mt-2 text-sm text-slate-600">Keep the current order as rows. Search and add the next product from the next row below.</p>
    </div>

    @if ($frequentProducts->isNotEmpty())
        <div class="mt-2">
            <div class="flex items-center justify-between gap-3">
                <h4 class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Frequently Ordered</h4>
                <span class="text-[10px] font-semibold text-slate-400">Tap to add with suggested qty</span>
            </div>
            <div class="mt-3 grid gap-2 grid-cols-2 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($frequentProducts as $frequentProduct)
                    @php($product = $frequentProduct['product'])
                    <button
                        type="button"
                        data-add-product="{{ $product->id }}"
                        class="rounded-xl border border-slate-200 bg-white p-3 text-left transition hover:border-emerald-300 hover:bg-emerald-50 active:scale-[0.98]"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-bold text-slate-900 text-xs truncate">{{ $product->name }}</p>
                                <p class="mt-0.5 text-[10px] text-slate-400 truncate">{{ $product->sku }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <span class="rounded-lg bg-cyan-50 px-2 py-0.5 text-[10px] font-black text-cyan-700">INR {{ number_format((float) ($product->effective_price ?? $product->base_price ?? 0), 2) }}</span>
                                <p class="mt-1 text-[10px] font-black text-emerald-700">{{ number_format((float) $frequentProduct['last_quantity'], 2) }} {{ $product->unit }}</p>
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3">
            <div>
                <p class="text-sm font-black text-slate-950">Current Order List</p>
                <p class="mt-0.5 text-xs text-slate-500">Selected products stay here as quick order rows.</p>
            </div>
            <span data-selected-count-badge class="inline-flex self-start sm:self-center rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-700">
                0 items selected
            </span>
        </div>

        <div data-empty-selection class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 px-4 py-6 text-center text-xs text-slate-400">
            Search a product, tap a frequent item, or load a custom list to start the order.
        </div>

        <div class="mt-4 hidden border-b border-slate-200 pb-2 text-[10px] md:text-[11px] font-black uppercase tracking-[0.16em] text-slate-500 grid grid-cols-[minmax(0,1.4fr)_90px_150px_90px_36px] gap-2 sm:grid-cols-[minmax(0,1.5fr)_100px_170px_110px_48px]" data-selected-table-head>
            <span>Product</span>
            <span class="text-right">Price</span>
            <span class="text-right">Qty</span>
            <span class="text-right">Total</span>
            <span></span>
        </div>
        <div data-selected-products class="mt-2 space-y-1"></div>

        <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50/50 p-3 sm:p-4">
            <div class="md:grid md:grid-cols-[minmax(0,1.6fr)_140px_120px_88px] md:items-center md:gap-3">
                <div class="md:col-span-1">
                    <label for="order-product-search" class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Add Next Product</label>
                    <input
                        id="order-product-search"
                        type="search"
                        data-product-search
                        placeholder="Search product or SKU"
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    >
                </div>
                <div class="mt-2 text-xs text-slate-400 md:col-span-3 md:mt-0 md:text-right">
                    Search here, add product, enter qty, then continue to the next row.
                </div>
            </div>

            <div data-search-results-wrap class="mt-3 hidden bg-transparent">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2">
                    <h4 class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Search Results</h4>
                    <button type="button" data-clear-search class="text-xs font-bold text-slate-400 hover:text-slate-600">Clear</button>
                </div>
                <div data-search-results class="mt-1 divide-y divide-slate-100"></div>
                <p data-search-empty class="mt-3 hidden text-xs text-slate-400 italic">No matching products found.</p>
            </div>
        </div>
    </div>

    <details data-full-catalog class="mt-4">
        <summary class="cursor-pointer list-none text-sm font-black text-slate-900 flex items-center justify-between border border-slate-100 rounded-xl px-4 py-3 bg-white hover:bg-slate-50/50 transition shadow-sm">
            <span>
                Browse Full Catalog
                <span class="block mt-0.5 text-[10px] font-semibold text-slate-400">Open only when you need more products</span>
            </span>
            <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </summary>

        <div class="mt-4 border border-slate-100 rounded-2xl bg-white px-4 py-2 shadow-sm">
            <div data-product-catalog class="divide-y divide-slate-100">
                @foreach ($allProductsForOrder as $productData)
                    <article
                        data-product-row
                        data-product-id="{{ $productData['id'] }}"
                        data-search-text="{{ \Illuminate\Support\Str::lower($productData['name'].' '.$productData['sku'].' '.$productData['category']) }}"
                        class="flex items-center justify-between py-2.5"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-slate-900 text-xs sm:text-sm truncate">{{ $productData['name'] }}</p>
                            <p class="text-[10px] text-slate-500 truncate">{{ $productData['sku'] }} · {{ $productData['category'] }} · <span class="uppercase">{{ $productData['unit'] }}</span></p>
                        </div>
                        <div class="flex items-center gap-4 shrink-0 pl-3">
                            <div class="text-right">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Price</p>
                                <p class="text-xs font-bold text-cyan-700">INR {{ number_format((float) $productData['price'], 2) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Sug.</p>
                                <p class="text-xs font-bold text-slate-700">{{ number_format((float) $productData['suggested_qty'], 2) }}</p>
                            </div>
                            <button type="button" data-add-product="{{ $productData['id'] }}" class="rounded-lg bg-emerald-600 hover:bg-emerald-700 active:scale-95 transition text-xs font-black text-white px-3 py-1.5 shadow-sm">
                                Add
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </details>

    <div class="hidden" aria-hidden="true">
        @foreach ($allProductsForOrder as $productData)
            <input
                type="number"
                step="0.01"
                min="0"
                name="items[{{ $productData['sku'] }}]"
                value="{{ $productData['current_qty'] }}"
                data-order-qty
                data-master-qty
                data-product-id="{{ $productData['id'] }}"
                data-effective-price="{{ number_format((float) $productData['price'], 2, '.', '') }}"
            >
        @endforeach
    </div>

    <script id="shop-owner-product-catalog" type="application/json">
        {!! $allProductsForOrder->values()->toJson() !!}
    </script>
</div>
