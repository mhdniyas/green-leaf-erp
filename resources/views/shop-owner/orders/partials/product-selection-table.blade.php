@php
    $allProductsForOrder = collect();
    $defaultCategory = 'all';

    foreach ($productsByCategory as $category) {
        foreach ($category->products as $product) {
            $yesterdayItem = $yesterdayOrder?->items->firstWhere('product_id', $product->id);
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
                    'public_uuid' => null,
                    'id' => null,
                ]]);
            }

            $orderUnitPayloads = $orderUnits->map(fn ($unit) => [
                    'id' => $unit->id ?? null,
                    'public_uuid' => $unit->public_uuid ?? null,
                    'unit' => $unit->unit,
                    'label' => $unit->label ?: strtoupper((string) $unit->unit),
                    'conversion_to_base' => $unit->conversion_to_base !== null ? (float) $unit->conversion_to_base : null,
                    'is_base' => (bool) $unit->is_base,
                ])->values();
            $existingItem = $tomorrowOrder?->items->firstWhere('product_id', $product->id);
            $selectedUnitPayload = $orderUnitPayloads
                ->first(function (array $unitPayload) use ($existingItem): bool {
                    if (! $existingItem) {
                        return false;
                    }

                    if (! empty($unitPayload['id']) && (int) ($existingItem->requested_product_unit_id ?? 0) === (int) $unitPayload['id']) {
                        return true;
                    }

                    return (string) ($existingItem->requested_unit_label ?? '') === (string) $unitPayload['label']
                        || (string) ($existingItem->requested_unit ?? '') === (string) $unitPayload['unit'];
                })
                ?? $orderUnitPayloads->first();
            $currentUnitQuantity = old("items.{$product->sku}", $existingItem?->requested_unit_quantity ?? $existingItem?->requested_qty ?? '');
            $suggestedQuantity = (float) ($yesterdayItem?->requested_qty ?? 0);
            $isSelected = (float) $currentUnitQuantity > 0;
            $isFrequent = $frequentProducts->contains(fn ($item) => (int) $item['product']->id === (int) $product->id);

            $allProductsForOrder->push([
                'id' => $product->id,
                'line_key' => $product->sku,
                'sku' => $product->sku,
                'sku_sort_value' => $product->sku_sort_value,
                'name' => $product->name,
                'unit' => $product->unit,
                'current_unit' => $selectedUnitPayload['unit'],
                'current_unit_label' => $selectedUnitPayload['label'],
                'current_measure_uuid' => $selectedUnitPayload['public_uuid'],
                'current_conversion_to_base' => $selectedUnitPayload['conversion_to_base'],
                'order_units' => $orderUnitPayloads->all(),
                'category' => $category->name,
                'current_qty' => $currentUnitQuantity,
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

    $productCardsForOrder = $allProductsForOrder;
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

    <div class="flex flex-col gap-2 border-b border-slate-100 pb-2.5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center justify-between gap-3">
            <h3 id="current-list-title" class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">All Products</h3>
            <span id="list-results-count" class="rounded-lg bg-slate-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-slate-400">0 products</span>
        </div>
        <div class="flex h-9 w-full items-center rounded-xl bg-slate-100 p-1 sm:w-auto" role="group" aria-label="Product row layout">
            <button type="button" data-shop-order-layout-toggle data-layout="compact" class="shop-order-layout-toggle flex-1 rounded-lg px-3 py-1.5 text-[11px] font-black uppercase text-slate-600 transition sm:flex-none">
                Row
            </button>
            <button type="button" data-shop-order-layout-toggle data-layout="two-row" class="shop-order-layout-toggle flex-1 rounded-lg px-3 py-1.5 text-[11px] font-black uppercase text-slate-600 transition sm:flex-none">
                Two row
            </button>
        </div>
    </div>

    <div id="no-search-results" class="hidden rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 px-4 py-8 text-center text-xs text-slate-400">
        No matching products found. Try another search.
    </div>

    <div id="product-list-container" class="space-y-1.5">
        @foreach ($productCardsForOrder as $productData)
            <article
                data-product-card
                data-product-id="{{ $productData['id'] }}"
                data-line-key="{{ $productData['line_key'] }}"
                data-sku="{{ $productData['sku'] }}"
                data-name="{{ $productData['name'] }}"
                data-unit="{{ $productData['unit'] }}"
                data-current-unit="{{ $productData['current_unit'] }}"
                data-category="{{ $productData['category'] }}"
                data-is-frequent="{{ $productData['is_frequent'] ? 'true' : 'false' }}"
                data-search-text="{{ \Illuminate\Support\Str::lower($productData['name'].' '.$productData['sku'].' '.$productData['category'].' '.collect($productData['order_units'])->pluck('label')->implode(' ')) }}"
                @class([
                    'rounded-xl border px-2.5 py-2 transition',
                    'border-emerald-200 bg-emerald-50 shadow-sm' => (float) $productData['current_qty'] > 0,
                    'border-slate-200 bg-white' => (float) $productData['current_qty'] <= 0,
                ])
            >
                <div class="shop-order-row-grid relative grid grid-cols-[2rem_minmax(0,1fr)_4.25rem_minmax(4.5rem,5.5rem)] items-center gap-1.5 pr-7">
                    <div class="shop-order-row-code flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-[11px] font-black text-slate-600">
                        {{ $productData['sku'] }}
                    </div>
                    <div class="shop-order-row-title min-w-0">
                        <h4 class="truncate text-[13px] font-black leading-4 text-slate-950">{{ $productData['name'] }}</h4>
                        <p class="truncate text-[11px] font-semibold leading-3 text-slate-500">{{ $productData['category'] }}</p>
                    </div>
                    @if(count($productData['order_units']) > 1)
                        <div class="shop-order-row-unit relative" data-inline-unit-picker data-product-id="{{ $productData['id'] }}" data-line-key="{{ $productData['line_key'] }}">
                            <input type="hidden" name="item_units[{{ $productData['line_key'] }}]" value="{{ $productData['current_unit'] }}" data-inline-unit data-product-id="{{ $productData['id'] }}" data-line-key="{{ $productData['line_key'] }}">
                            <input type="hidden" name="item_measures[{{ $productData['line_key'] }}]" value="{{ $productData['current_measure_uuid'] }}" data-inline-measure data-product-id="{{ $productData['id'] }}" data-line-key="{{ $productData['line_key'] }}">
                            <button type="button" data-unit-picker-trigger class="flex h-8 w-full items-center justify-between gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 text-[10px] font-black uppercase text-slate-800 shadow-sm transition hover:border-emerald-300 hover:bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" aria-haspopup="listbox" aria-expanded="false" aria-label="Unit for {{ $productData['name'] }}">
                                <span data-unit-picker-label>{{ $productData['current_unit_label'] }}</span>
                                <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                            <div data-unit-picker-menu class="absolute left-0 top-[calc(100%+0.25rem)] z-50 hidden min-w-28 overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-xl shadow-slate-900/15" role="listbox">
                                @foreach($productData['order_units'] as $unit)
                                    @php($measureValue = $unit['public_uuid'] ?: $unit['unit'])
                                    <button type="button" data-unit-picker-option data-unit-value="{{ $measureValue }}" data-unit-name="{{ $unit['unit'] }}" data-unit-label="{{ $unit['label'] }}" @class([
                                        'flex h-8 w-full items-center gap-1.5 rounded-lg px-2 text-left text-[10px] font-black uppercase transition',
                                        'bg-emerald-600 text-white' => $measureValue === $productData['current_measure_uuid'] || ($productData['current_measure_uuid'] === null && $unit['unit'] === $productData['current_unit']),
                                        'text-slate-700 hover:bg-slate-100' => ! ($measureValue === $productData['current_measure_uuid'] || ($productData['current_measure_uuid'] === null && $unit['unit'] === $productData['current_unit'])),
                                    ]) role="option" aria-selected="{{ $measureValue === $productData['current_measure_uuid'] || ($productData['current_measure_uuid'] === null && $unit['unit'] === $productData['current_unit']) ? 'true' : 'false' }}">
                                        <span data-unit-picker-check class="{{ $measureValue === $productData['current_measure_uuid'] || ($productData['current_measure_uuid'] === null && $unit['unit'] === $productData['current_unit']) ? '' : 'invisible' }}">✓</span>
                                        <span>{{ $unit['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="shop-order-row-unit flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-1.5 text-[10px] font-black uppercase text-slate-800">
                            {{ $productData['current_unit_label'] }}
                        </div>
                        <input type="hidden" name="item_units[{{ $productData['line_key'] }}]" value="{{ $productData['current_unit'] }}" data-inline-unit data-product-id="{{ $productData['id'] }}" data-line-key="{{ $productData['line_key'] }}">
                        <input type="hidden" name="item_measures[{{ $productData['line_key'] }}]" value="{{ $productData['current_measure_uuid'] }}" data-inline-measure data-product-id="{{ $productData['id'] }}" data-line-key="{{ $productData['line_key'] }}">
                    @endif
                    <input
                        id="order-qty-{{ $loop->index }}"
                        type="number"
                        inputmode="decimal"
                        step="0.01"
                        min="0"
                        name="items[{{ $productData['line_key'] }}]"
                        value="{{ $productData['current_qty'] }}"
                        data-order-qty
                        data-master-qty
                        data-inline-qty
                        data-product-id="{{ $productData['id'] }}"
                        data-line-key="{{ $productData['line_key'] }}"
                        class="shop-order-row-qty h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-right text-sm font-black text-slate-950 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        placeholder="0"
                        aria-label="Quantity for {{ $productData['name'] }}"
                    >
                    <button type="button" data-add-measure-line class="shop-order-row-action absolute right-0 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md border border-slate-200 bg-white text-xs font-black text-slate-500 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700" aria-label="Add another unit for {{ $productData['name'] }}">
                        +
                    </button>
                </div>

                <span data-row-selection-label class="{{ (float) $productData['current_qty'] > 0 ? '' : 'hidden' }} sr-only">Selected for cart</span>
                <p data-unit-conversion-info class="mt-2 hidden rounded-lg border border-emerald-100 bg-emerald-50 px-2 py-1 text-[11px] font-black uppercase tracking-[0.08em] text-emerald-700"></p>
                @if(count($productData['order_units']) > 1)
                    <div data-extra-measure-lines class="mt-1.5 grid gap-1.5">
                        @foreach($productData['order_units'] as $extraUnit)
                            <div data-extra-measure-line class="hidden grid grid-cols-[2rem_minmax(0,1fr)_4.25rem_minmax(4.5rem,5.5rem)_2rem] items-center gap-1.5">
                                <div></div>
                                <div></div>
                                <div class="relative" data-inline-unit-picker data-product-id="{{ $productData['id'] }}" data-line-key="{{ $productData['sku'] }}|extra-{{ $loop->index }}">
                                    <input type="hidden" name="item_units[{{ $productData['sku'] }}|extra-{{ $loop->index }}]" value="{{ $extraUnit['unit'] }}" data-inline-unit data-product-id="{{ $productData['id'] }}" data-line-key="{{ $productData['sku'] }}|extra-{{ $loop->index }}">
                                    <input type="hidden" name="item_measures[{{ $productData['sku'] }}|extra-{{ $loop->index }}]" value="{{ $extraUnit['public_uuid'] }}" data-inline-measure data-product-id="{{ $productData['id'] }}" data-line-key="{{ $productData['sku'] }}|extra-{{ $loop->index }}">
                                    <button type="button" data-unit-picker-trigger class="flex h-8 w-full items-center justify-between gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 text-[10px] font-black uppercase text-slate-800 shadow-sm transition hover:border-emerald-300 hover:bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500" aria-haspopup="listbox" aria-expanded="false" aria-label="Extra unit for {{ $productData['name'] }}">
                                        <span data-unit-picker-label>{{ $extraUnit['label'] }}</span>
                                        <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                        </svg>
                                    </button>
                                    <div data-unit-picker-menu class="absolute left-0 top-[calc(100%+0.25rem)] z-50 hidden min-w-28 overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-xl shadow-slate-900/15" role="listbox">
                                        @foreach($productData['order_units'] as $unit)
                                            @php($measureValue = $unit['public_uuid'] ?: $unit['unit'])
                                            <button type="button" data-unit-picker-option data-unit-value="{{ $measureValue }}" data-unit-name="{{ $unit['unit'] }}" data-unit-label="{{ $unit['label'] }}" @class([
                                                'flex h-8 w-full items-center gap-1.5 rounded-lg px-2 text-left text-[10px] font-black uppercase transition',
                                                'bg-emerald-600 text-white' => $measureValue === ($extraUnit['public_uuid'] ?: $extraUnit['unit']),
                                                'text-slate-700 hover:bg-slate-100' => $measureValue !== ($extraUnit['public_uuid'] ?: $extraUnit['unit']),
                                            ]) role="option" aria-selected="{{ $measureValue === ($extraUnit['public_uuid'] ?: $extraUnit['unit']) ? 'true' : 'false' }}">
                                                <span data-unit-picker-check class="{{ $measureValue === ($extraUnit['public_uuid'] ?: $extraUnit['unit']) ? '' : 'invisible' }}">✓</span>
                                                <span>{{ $unit['label'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                                <input
                                    type="number"
                                    inputmode="decimal"
                                    step="0.01"
                                    min="0"
                                    name="items[{{ $productData['sku'] }}|extra-{{ $loop->index }}]"
                                    value=""
                                    data-order-qty
                                    data-master-qty
                                    data-inline-qty
                                    data-product-id="{{ $productData['id'] }}"
                                    data-line-key="{{ $productData['sku'] }}|extra-{{ $loop->index }}"
                                    class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-right text-sm font-black text-slate-950 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                    placeholder="0"
                                    aria-label="Extra quantity for {{ $productData['name'] }}"
                                >
                                <button type="button" data-remove-measure-line class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-sm font-black text-slate-400 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600" aria-label="Remove extra unit for {{ $productData['name'] }}">
                                    x
                                </button>
                                <p data-unit-conversion-info class="col-start-3 col-span-2 hidden rounded-lg border border-emerald-100 bg-emerald-50 px-2 py-1 text-[11px] font-black uppercase tracking-[0.08em] text-emerald-700"></p>
                            </div>
                        @endforeach
                    </div>
                @endif
                <p data-row-error class="mt-2 hidden text-[11px] font-bold text-rose-700"></p>
            </article>
        @endforeach
    </div>

    <div id="draft-cart-bar" class="fixed inset-x-0 bottom-16 z-40 hidden border-t border-slate-800 bg-slate-950 px-4 py-3 text-white shadow-[0_-14px_30px_rgba(15,23,42,0.22)] sm:bottom-4 sm:left-1/2 sm:max-w-xl sm:-translate-x-1/2 sm:rounded-2xl sm:border">
        <div class="mx-auto flex max-w-xl items-center gap-3">
            <div class="min-w-0 flex-1">
                <p id="draft-cart-summary" class="truncate text-sm font-black">0 selected</p>
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-emerald-300">{{ $stickySubmitHint ?? 'Draft only' }}</p>
            </div>
            <button type="button" id="draft-cart-clear" class="rounded-xl border border-white/15 px-3 py-2 text-[11px] font-black uppercase tracking-[0.12em] text-white transition hover:bg-white/10">
                Clear
            </button>
            <button type="button" id="draft-cart-submit" class="rounded-xl bg-emerald-500 px-3 py-2 text-[11px] font-black uppercase tracking-[0.12em] text-slate-950 transition hover:bg-emerald-400">
                {{ $stickySubmitLabel ?? 'Add To Cart' }}
            </button>
        </div>
    </div>

    <style>
        #shop-owner-order-form.shop-order-layout-two-row [data-product-card] {
            padding: 0.65rem;
        }

        #shop-owner-order-form.shop-order-layout-two-row .shop-order-row-grid {
            grid-template-columns: 4.25rem minmax(0, 1fr) 1.75rem;
            gap: 0.5rem;
            padding-right: 0;
        }

        #shop-owner-order-form.shop-order-layout-two-row .shop-order-row-unit {
            grid-column: 1 / 2;
            width: 100%;
        }

        #shop-owner-order-form.shop-order-layout-two-row .shop-order-row-qty {
            grid-column: 2 / -1;
        }

        #shop-owner-order-form.shop-order-layout-two-row .shop-order-row-action {
            position: static;
            grid-column: 3 / 4;
            grid-row: 1;
            transform: none;
            justify-self: end;
        }

        @media (min-width: 640px) {
            #shop-owner-order-form.shop-order-layout-two-row .shop-order-row-grid {
                grid-template-columns: 2.25rem minmax(0, 1fr) 5rem 6rem 2rem;
            }

            #shop-owner-order-form.shop-order-layout-two-row .shop-order-row-unit {
                grid-column: 3 / 4;
            }

            #shop-owner-order-form.shop-order-layout-two-row .shop-order-row-qty {
                grid-column: 4 / 5;
            }

            #shop-owner-order-form.shop-order-layout-two-row .shop-order-row-action {
                grid-column: 5 / 6;
            }
        }
    </style>

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
