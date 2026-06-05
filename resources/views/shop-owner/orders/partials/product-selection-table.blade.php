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
            ]);
        }
    }
@endphp

<div class="space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Quick Order</p>
            <h3 class="mt-1 text-lg font-black text-slate-950">Fast row order entry</h3>
            <p class="mt-2 text-sm text-slate-600">Keep the current order as rows. Search and add the next product from the next row below.</p>
        </div>

        @if ($frequentProducts->isNotEmpty())
            <div class="mt-4">
                <div class="flex items-center justify-between gap-3">
                    <h4 class="text-sm font-black uppercase tracking-[0.18em] text-slate-700">Frequently Ordered</h4>
                    <span class="text-xs font-semibold text-slate-500">Tap to add with suggested qty</span>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($frequentProducts as $frequentProduct)
                        @php($product = $frequentProduct['product'])
                        <button
                            type="button"
                            data-add-product="{{ $product->id }}"
                            class="rounded-2xl border border-slate-200 bg-white p-4 text-left transition hover:border-emerald-300 hover:bg-emerald-50"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-bold text-slate-900">{{ $product->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $product->sku }}</p>
                                </div>
                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-700">
                                    {{ number_format((float) $frequentProduct['last_quantity'], 2) }} {{ $product->unit }}
                                </span>
                            </div>
                            <p class="mt-3 text-xs text-slate-500">{{ $frequentProduct['order_count'] }} recent orders · total {{ number_format((float) $frequentProduct['total_quantity'], 2) }} {{ $product->unit }}</p>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-5 rounded-3xl border border-slate-200 bg-white p-4 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-black text-slate-950">Current Order List</p>
                    <p class="mt-1 text-sm text-slate-600">Selected products stay here as quick order rows.</p>
                </div>
                <span data-selected-count-badge class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-[0.16em] text-slate-700">
                    0 items selected
                </span>
            </div>

            <div data-empty-selection class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                Search a product, tap a frequent item, or load a custom list to start the order.
            </div>

            <div class="mt-4 hidden border-b border-slate-200 pb-2 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500 md:grid md:grid-cols-[minmax(0,1.6fr)_140px_120px_88px] md:gap-3" data-selected-table-head>
                <span>Product</span>
                <span class="text-right">Qty</span>
                <span class="text-right">Suggested</span>
                <span class="text-right">Action</span>
            </div>
            <div data-selected-products class="mt-4 space-y-3"></div>

            <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">
                <div class="md:grid md:grid-cols-[minmax(0,1.6fr)_140px_120px_88px] md:items-center md:gap-3">
                    <div class="md:col-span-1">
                        <label for="order-product-search" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Add Next Product</label>
                        <input
                            id="order-product-search"
                            type="search"
                            data-product-search
                            placeholder="Search product or SKU"
                            class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none"
                        >
                    </div>
                    <div class="mt-3 text-sm text-slate-500 md:col-span-3 md:mt-0 md:text-right">
                        Search here, add product, enter qty, then continue to the next row.
                    </div>
                </div>

                <div data-search-results-wrap class="mt-3 hidden rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h4 class="text-sm font-black uppercase tracking-[0.18em] text-slate-700">Search Results</h4>
                        <button type="button" data-clear-search class="text-xs font-bold text-slate-500">Clear</button>
                    </div>
                    <div data-search-results class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3"></div>
                    <p data-search-empty class="mt-3 hidden text-sm text-slate-500">No matching products found.</p>
                </div>
            </div>
        </div>

        <details data-full-catalog class="mt-5 rounded-3xl border border-slate-200 bg-white p-4 sm:p-5">
            <summary class="cursor-pointer list-none text-sm font-black text-slate-900">
                Browse Full Catalog
                <span class="ml-2 text-xs font-semibold text-slate-500">Open only when you need more products</span>
            </summary>

            <div data-product-catalog class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($allProductsForOrder as $productData)
                    <article
                        data-product-row
                        data-product-id="{{ $productData['id'] }}"
                        data-search-text="{{ \Illuminate\Support\Str::lower($productData['name'].' '.$productData['sku'].' '.$productData['category']) }}"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-slate-900">{{ $productData['name'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $productData['sku'] }} · {{ $productData['category'] }}</p>
                            </div>
                            <span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-slate-600">{{ $productData['unit'] }}</span>
                        </div>
                        <div class="mt-4 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Suggested</p>
                                <p class="mt-1 text-sm font-bold text-slate-900">{{ number_format((float) $productData['suggested_qty'], 2) }} {{ $productData['unit'] }}</p>
                            </div>
                            <button type="button" data-add-product="{{ $productData['id'] }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">
                                Add
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>
        </details>
    </section>

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
            >
        @endforeach
    </div>

    <script id="shop-owner-product-catalog" type="application/json">
        {!! $allProductsForOrder->values()->toJson() !!}
    </script>
</div>
