@php
    $allProductsForOrder = collect();
    $defaultCategory = 'all';

    foreach ($productsByCategory as $category) {
        foreach ($category->products as $product) {
            $existingItem = $tomorrowOrder?->items->firstWhere('product_id', $product->id);
            $yesterdayItem = $yesterdayOrder?->items->firstWhere('product_id', $product->id);
            $currentUnit = old("item_units.{$product->sku}", $existingItem?->requested_unit ?? $product->unit);
            $currentUnitQuantity = old("items.{$product->sku}", $existingItem?->requested_unit_quantity ?? $existingItem?->requested_qty ?? '');
            $orderUnits = $product->relationLoaded('orderUnits')
                ? $product->orderUnits->where('is_orderable', true)->values()
                : collect();

            if ($orderUnits->isEmpty()) {
                $orderUnits = collect([(object) [
                    'unit' => $product->unit,
                    'label' => strtoupper((string) $product->unit),
                    'conversion_to_base' => 1,
                    'is_base' => true,
                    'is_orderable' => true,
                ]]);
            }

            $selectedUnitRow = $orderUnits->firstWhere('unit', $currentUnit) ?? $orderUnits->first();
            $displayQuantity = $selectedUnitRow && $existingItem
                ? old("items.{$product->sku}", (float) ($existingItem->requested_unit_quantity ?? $existingItem->requested_qty))
                : $currentUnitQuantity;
            $suggestedQuantity = (float) ($yesterdayItem?->requested_qty ?? 0);
            $isSelected = (float) $displayQuantity > 0;
            $isFrequent = $frequentProducts->contains(fn ($item) => (int) $item['product']->id === (int) $product->id);

            $allProductsForOrder->push([
                'id' => $product->id,
                'sku' => $product->sku,
                'sku_sort_value' => $product->sku_sort_value,
                'name' => $product->name,
                'unit' => $product->unit,
                'current_unit' => $selectedUnitRow?->unit ?? $product->unit,
                'order_units' => $orderUnits->map(fn ($unit) => [
                    'unit' => $unit->unit,
                    'label' => $unit->label ?: strtoupper((string) $unit->unit),
                    'conversion_to_base' => (float) $unit->conversion_to_base,
                    'is_base' => (bool) $unit->is_base,
                ])->values()->all(),
                'category' => $category->name,
                'current_qty' => $displayQuantity,
                'suggested_qty' => $suggestedQuantity,
                'yesterday_qty' => $suggestedQuantity,
                'is_selected' => $isSelected,
                'is_frequent' => $isFrequent,
            ]);
        }
    }

    $allProductsForOrder = $allProductsForOrder
        ->sort(function (array $left, array $right): int {
            if ($left['is_selected'] !== $right['is_selected']) {
                return $left['is_selected'] ? -1 : 1;
            }

            if ($left['is_frequent'] !== $right['is_frequent']) {
                return $left['is_frequent'] ? -1 : 1;
            }

            return strcmp($left['sku_sort_value'], $right['sku_sort_value']);
        })
        ->values();
@endphp

<div class="space-y-5 pb-28">
    <div class="space-y-3">
        <div class="relative">
            <input
                id="order-product-search"
                type="search"
                data-product-search
                placeholder="Search products by name, code or category..."
                class="w-full rounded-2xl border border-slate-200 bg-white py-3.5 pl-11 pr-4 text-sm font-semibold text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 1 1-14 0 7 7 0 0 1 14 0z" />
                </svg>
            </div>
        </div>

        <div class="-mx-4 flex snap-x snap-mandatory gap-1.5 overflow-x-auto px-4 pb-1.5 sm:mx-0 sm:px-0">
            @if ($frequentProducts->isNotEmpty())
                <button type="button" data-category-pill="frequent" class="snap-start shrink-0 rounded-full bg-slate-100 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.16em] text-slate-600 transition hover:bg-slate-200">
                    Frequent
                </button>
            @endif
            <button type="button" data-category-pill="all" data-default-category="{{ $defaultCategory }}" class="snap-start shrink-0 rounded-full bg-emerald-600 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.16em] text-white shadow-sm transition">
                All
            </button>
            @foreach ($productsByCategory as $category)
                <button type="button" data-category-pill="{{ $category->name }}" class="snap-start shrink-0 rounded-full bg-slate-100 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.16em] text-slate-600 transition hover:bg-slate-200">
                    {{ $category->name }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
        <h3 id="current-list-title" class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">All Products</h3>
        <span id="list-results-count" class="rounded-lg bg-slate-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-slate-400">0 products</span>
    </div>

    <div id="no-search-results" class="hidden rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 px-4 py-8 text-center text-xs text-slate-400">
        No matching products found. Try another search.
    </div>

    <div id="product-list-container" class="space-y-1.5">
        @foreach ($allProductsForOrder as $productData)
            <article
                data-product-card
                data-product-id="{{ $productData['id'] }}"
                data-sku="{{ $productData['sku'] }}"
                data-name="{{ $productData['name'] }}"
                data-unit="{{ $productData['unit'] }}"
                data-current-unit="{{ $productData['current_unit'] }}"
                data-category="{{ $productData['category'] }}"
                data-is-frequent="{{ $productData['is_frequent'] ? 'true' : 'false' }}"
                data-search-text="{{ \Illuminate\Support\Str::lower($productData['name'].' '.$productData['sku'].' '.$productData['category']) }}"
                @class([
                    'rounded-xl border px-2.5 py-2 transition',
                    'border-emerald-200 bg-emerald-50 shadow-sm' => (float) $productData['current_qty'] > 0,
                    'border-slate-200 bg-white' => (float) $productData['current_qty'] <= 0,
                ])
            >
                <div class="grid grid-cols-[2rem_minmax(0,1fr)_4.25rem_minmax(4.5rem,5.5rem)] items-center gap-1.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-[11px] font-black text-slate-600">
                        {{ $productData['sku'] }}
                    </div>
                    <div class="min-w-0">
                        <h4 class="truncate text-[13px] font-black leading-4 text-slate-950">{{ $productData['name'] }}</h4>
                        <p class="truncate text-[11px] font-semibold leading-3 text-slate-500">{{ $productData['category'] }}</p>
                    </div>
                    @if(count($productData['order_units']) > 1)
                        <select
                            name="item_units[{{ $productData['sku'] }}]"
                            data-inline-unit
                            data-product-id="{{ $productData['id'] }}"
                            class="h-8 w-full rounded-lg border border-slate-200 bg-slate-50 px-1 text-[11px] font-black uppercase text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            aria-label="Unit for {{ $productData['name'] }}"
                        >
                            @foreach($productData['order_units'] as $unit)
                                <option value="{{ $unit['unit'] }}" @selected($unit['unit'] === $productData['current_unit'])>{{ strtoupper($unit['unit']) }}</option>
                            @endforeach
                        </select>
                    @else
                        <div class="flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-1.5 text-[11px] font-black uppercase text-slate-800">
                            {{ $productData['order_units'][0]['unit'] ?? $productData['unit'] }}
                        </div>
                        <input type="hidden" name="item_units[{{ $productData['sku'] }}]" value="{{ $productData['order_units'][0]['unit'] ?? $productData['unit'] }}" data-inline-unit data-product-id="{{ $productData['id'] }}">
                    @endif
                    <input
                        id="order-qty-{{ $productData['id'] }}"
                        type="number"
                        inputmode="decimal"
                        step="0.01"
                        min="0"
                        name="items[{{ $productData['sku'] }}]"
                        value="{{ $productData['current_qty'] }}"
                        data-order-qty
                        data-master-qty
                        data-inline-qty
                        data-product-id="{{ $productData['id'] }}"
                        class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-right text-sm font-black text-slate-950 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        placeholder="0"
                        aria-label="Quantity for {{ $productData['name'] }}"
                    >
                </div>

                <span data-row-selection-label class="{{ (float) $productData['current_qty'] > 0 ? '' : 'hidden' }} sr-only">Selected for cart</span>
                <p data-row-error class="mt-2 hidden text-[11px] font-bold text-rose-700"></p>
            </article>
        @endforeach
    </div>

    <div id="draft-cart-bar" class="fixed inset-x-0 bottom-16 z-40 hidden border-t border-slate-800 bg-slate-950 px-4 py-3 text-white shadow-[0_-14px_30px_rgba(15,23,42,0.22)] sm:bottom-4 sm:left-1/2 sm:max-w-xl sm:-translate-x-1/2 sm:rounded-2xl sm:border">
        <div class="mx-auto flex max-w-xl items-center gap-3">
            <div class="min-w-0 flex-1">
                <p id="draft-cart-summary" class="truncate text-sm font-black">0 selected</p>
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-emerald-300">Draft only</p>
            </div>
            <button type="button" id="draft-cart-clear" class="rounded-xl border border-white/15 px-3 py-2 text-[11px] font-black uppercase tracking-[0.12em] text-white transition hover:bg-white/10">
                Clear
            </button>
            <button type="button" id="draft-cart-submit" class="rounded-xl bg-emerald-500 px-3 py-2 text-[11px] font-black uppercase tracking-[0.12em] text-slate-950 transition hover:bg-emerald-400">
                Add To Cart
            </button>
        </div>
    </div>

    @if ($allowPresetSave ?? true)
        <form method="POST" action="{{ route('requisitions.presets.store') }}" data-save-preset-form class="hidden" aria-hidden="true">
            @csrf
            <input type="hidden" name="redirect_to" value="shop-owner-orders-create">
            <input type="text" name="name" data-preset-name-input>
        </form>
    @endif

    <script id="shop-owner-product-catalog" type="application/json">
        {!! $allProductsForOrder->values()->toJson() !!}
    </script>

    <script id="shop-owner-presets-data" type="application/json">
        {!! $presets->map(fn ($preset) => [
            'id' => $preset->id,
            'name' => $preset->name,
            'items' => $preset->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => (float) $item->quantity,
            ])->values()->all(),
        ])->values()->toJson() !!}
    </script>
</div>
