@php
    $allProductsForOrder = collect();
    $defaultCategory = 'all';
    $effectiveAllowedGrades = $allowedProductGrades ?? ['A'];
    $primaryGrade = $defaultProductGrade ?? ($orderedProductGrades[0] ?? $effectiveAllowedGrades[0] ?? 'A');
    $isPrimaryNonA = $primaryGrade !== 'A';
    $orderedGradesList = $orderedProductGrades ?? $effectiveAllowedGrades;
    $additionalGrades = array_values(array_filter($orderedGradesList, fn ($g) => $g !== $primaryGrade));
    $hasMultipleGrades = count($additionalGrades) > 0;
    $multiGradeOrderingEnabled = (bool) ($multiGradeOrderingEnabled ?? false);

    foreach ($productsByCategory as $category) {
        foreach ($category->products as $product) {
            $yesterdayItem = $yesterdayOrder?->items->first(function ($item) use ($product, $primaryGrade): bool {
                if ((int) $item->product_id !== (int) $product->id) {
                    return false;
                }
                $grade = $item->product_grade instanceof \App\Enums\Inventory\ProductGrade
                    ? $item->product_grade->value
                    : (string) ($item->product_grade ?? 'A');
                return $grade === $primaryGrade;
            });
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
            $existingItem = $tomorrowOrder?->items->first(function ($item) use ($product, $primaryGrade): bool {
                if ((int) $item->product_id !== (int) $product->id) {
                    return false;
                }
                $grade = $item->product_grade instanceof \App\Enums\Inventory\ProductGrade
                    ? $item->product_grade->value
                    : (string) ($item->product_grade ?? 'A');
                return $grade === $primaryGrade;
            });
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

    $prioritizeSelectedProducts = (isset($isUpdateRequest) && $isUpdateRequest)
        || ($orderFormMode ?? 'shop-owner') === 'admin-shop-order';

    $allProductsForOrder = $allProductsForOrder
        ->sort(function (array $left, array $right) use ($prioritizeSelectedProducts): int {
            if ($prioritizeSelectedProducts && $left['is_selected'] !== $right['is_selected']) {
                return $left['is_selected'] ? -1 : 1;
            }

            return strcmp($left['sku_sort_value'], $right['sku_sort_value']);
        })
        ->values();

    $productCardsForOrder = $allProductsForOrder;
    $rowSelectionLabel = $rowSelectionLabel ?? 'Selected for cart';
    $canCancelSubmittedOrder = ($orderFormMode ?? 'shop-owner') === 'shop-owner'
        && $tomorrowOrder
        && $tomorrowOrder->state !== 'approved'
        && ! $tomorrowOrder->isFinanciallyLocked()
        && ! $tomorrowOrder->is_delivered;
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
                <div class="shop-order-row-grid grid min-w-0 grid-cols-[1.75rem_minmax(0,1fr)_3.35rem_3.55rem] items-center gap-1 sm:grid-cols-[2rem_minmax(0,1fr)_4rem_4.5rem] sm:gap-1.5">
                    <div class="shop-order-row-code flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-[10px] font-black text-slate-600 sm:h-8 sm:w-8 sm:text-[11px]">
                        {{ $productData['sku'] }}
                    </div>
                    <div class="shop-order-row-title min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <h4 class="shop-order-row-name text-[12px] font-black leading-3.5 text-slate-950 sm:text-[13px] sm:leading-4" title="{{ $productData['name'] }}">{{ $productData['name'] }}</h4>
                            @if($isPrimaryNonA)
                                <span class="rounded bg-purple-100 px-1 py-0.5 text-[9px] font-black text-purple-700">Grade {{ $primaryGrade }}</span>
                            @endif
                        </div>
                        <p class="truncate text-[10px] font-semibold leading-3 text-slate-500/85 sm:text-[11px]">{{ $productData['category'] }}</p>
                    </div>
                    @if(count($productData['order_units']) > 1)
                        <div class="shop-order-row-unit relative" data-inline-unit-picker data-product-id="{{ $productData['id'] }}" data-line-key="{{ $productData['line_key'] }}">
                            <input type="hidden" name="item_units[{{ $productData['line_key'] }}]" value="{{ $productData['current_unit'] }}" data-inline-unit data-product-id="{{ $productData['id'] }}" data-line-key="{{ $productData['line_key'] }}">
                            <input type="hidden" name="item_measures[{{ $productData['line_key'] }}]" value="{{ $productData['current_measure_uuid'] }}" data-inline-measure data-product-id="{{ $productData['id'] }}" data-line-key="{{ $productData['line_key'] }}">
                            <button type="button" data-unit-picker-trigger class="flex h-8 w-full items-center justify-center gap-0.5 rounded-lg border border-slate-200 bg-slate-50 px-1 text-[9px] font-black uppercase text-slate-800 shadow-sm transition hover:border-emerald-300 hover:bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 sm:justify-between sm:gap-1 sm:px-2 sm:text-[10px]" aria-haspopup="listbox" aria-expanded="false" aria-label="Unit for {{ $productData['name'] }}">
                                <span data-unit-picker-label class="truncate">{{ $productData['current_unit_label'] }}</span>
                                <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                            <div data-unit-picker-menu class="absolute left-0 top-[calc(100%+0.25rem)] z-50 hidden min-w-28 overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-xl shadow-slate-900/15" role="listbox">
                                @foreach($productData['order_units'] as $unit)
                                    @php
                                        $measureValue = $unit['public_uuid'] ?: $unit['unit'];
                                    @endphp
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
                        <div class="shop-order-row-unit flex h-8 items-center justify-center truncate rounded-lg border border-slate-200 bg-slate-50 px-1 text-[9px] font-black uppercase text-slate-800 sm:px-1.5 sm:text-[10px]">
                            {{ $productData['current_unit_label'] }}
                        </div>
                        <input type="hidden" name="item_units[{{ $productData['line_key'] }}]" value="{{ $productData['current_unit'] }}" data-inline-unit data-product-id="{{ $productData['id'] }}" data-line-key="{{ $productData['line_key'] }}">
                        <input type="hidden" name="item_measures[{{ $productData['line_key'] }}]" value="{{ $productData['current_measure_uuid'] }}" data-inline-measure data-product-id="{{ $productData['id'] }}" data-line-key="{{ $productData['line_key'] }}">
                    @endif
                    @if($isPrimaryNonA)
                        <input type="hidden" name="item_grades[{{ $productData['line_key'] }}]" value="{{ $primaryGrade }}" data-inline-grade data-product-id="{{ $productData['id'] }}" data-line-key="{{ $productData['line_key'] }}">
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
                        class="shop-order-row-qty h-8 w-full rounded-lg border border-slate-200 bg-white px-1.5 text-right text-sm font-black text-slate-950 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 sm:px-2"
                        placeholder="0"
                        aria-label="Quantity for {{ $productData['name'] }}"
                    >
                </div>

                @php
                    $subGradeData = [];
                    $extraFilledCount = 0;
                    if ($hasMultipleGrades) {
                        foreach ($additionalGrades as $subGrade) {
                            $subGradeKey = $productData['line_key'] . '|' . $subGrade;
                            $subExistingItem = $tomorrowOrder?->items->first(function ($item) use ($productData, $subGrade): bool {
                                if ((int) $item->product_id !== (int) $productData['id']) {
                                    return false;
                                }
                                $grade = $item->product_grade instanceof \App\Enums\Inventory\ProductGrade
                                    ? $item->product_grade->value
                                    : (string) ($item->product_grade ?? 'A');
                                return $grade === $subGrade;
                            });
                            $subCurrentQty = old("items.{$subGradeKey}", $subExistingItem?->requested_unit_quantity ?? $subExistingItem?->requested_qty ?? '');
                            $qtyNum = (float) $subCurrentQty;
                            if ($qtyNum > 0) {
                                $extraFilledCount++;
                            }
                            $subGradeData[] = [
                                'grade' => $subGrade,
                                'key' => $subGradeKey,
                                'qty' => $subCurrentQty,
                                'numeric_qty' => $qtyNum,
                            ];
                        }
                    }
                    $hasExistingExtraQty = $extraFilledCount > 0;
                    $gradesLabel = implode(' / ', $additionalGrades);
                @endphp

                @if($hasMultipleGrades && $multiGradeOrderingEnabled)
                    <div x-data="{
                        expanded: {{ $hasExistingExtraQty ? 'true' : 'false' }},
                        extraCount: {{ $extraFilledCount }},
                        updateExtraCount() {
                            let count = 0;
                            $el.querySelectorAll('input[data-sub-grade-qty]').forEach(el => {
                                if (parseFloat(el.value) > 0) count++;
                            });
                            this.extraCount = count;
                        }
                    }" class="mt-2 border-t border-slate-100 pt-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <button type="button"
                                    @click="expanded = !expanded"
                                    class="inline-flex items-center gap-1.5 text-xs font-bold text-purple-700 hover:text-purple-900 transition py-1 cursor-pointer">
                                <svg class="h-3.5 w-3.5 transition-transform duration-150" :class="{ 'rotate-90': expanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>
                                <span x-text="expanded ? 'Hide Other Grades' : '+ Other Grades ({{ $gradesLabel }})'">+ Other Grades ({{ $gradesLabel }})</span>
                            </button>

                            <span x-show="extraCount > 0"
                                  x-cloak
                                  class="inline-flex items-center gap-1 rounded-full bg-purple-50 border border-purple-200 px-2 py-0.5 text-[10px] font-bold text-purple-700">
                                <svg class="h-3 w-3 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span x-text="'✓ ' + extraCount + (extraCount === 1 ? ' extra grade' : ' extra grades')">
                                    ✓ {{ $extraFilledCount }} {{ $extraFilledCount === 1 ? 'extra grade' : 'extra grades' }}
                                </span>
                            </span>
                        </div>

                        <!-- Keep inputs in DOM at all times so values are not lost when collapsed -->
                        <div x-show="expanded"
                             x-transition:enter="ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="mt-1.5 space-y-1.5">
                            @foreach($subGradeData as $sub)
                                <div class="flex items-center justify-between gap-2 rounded-lg bg-slate-50/80 px-2 py-1 text-xs">
                                    <span class="inline-flex items-center gap-1 font-bold text-slate-700">
                                        <span class="rounded bg-purple-100 px-1 py-0.5 text-[9px] font-black text-purple-700">Grade {{ $sub['grade'] }}</span>
                                        <span class="text-[10px] text-slate-500">Qty ({{ $productData['current_unit_label'] }})</span>
                                    </span>
                                    <input type="hidden" name="item_units[{{ $sub['key'] }}]" value="{{ $productData['current_unit'] }}">
                                    <input type="hidden" name="item_measures[{{ $sub['key'] }}]" value="{{ $productData['current_measure_uuid'] }}">
                                    <input type="hidden" name="item_grades[{{ $sub['key'] }}]" value="{{ $sub['grade'] }}">
                                    <input
                                        type="number"
                                        inputmode="decimal"
                                        step="0.01"
                                        min="0"
                                        name="items[{{ $sub['key'] }}]"
                                        value="{{ $sub['qty'] }}"
                                        data-order-qty
                                        data-master-qty
                                        data-inline-qty
                                        data-sub-grade-qty
                                        data-product-id="{{ $productData['id'] }}"
                                        data-line-key="{{ $sub['key'] }}"
                                        @input="updateExtraCount()"
                                        class="shop-order-row-qty h-7 w-20 rounded border border-slate-200 bg-white px-1.5 text-right text-xs font-bold text-slate-900 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500"
                                        placeholder="0"
                                        aria-label="Grade {{ $sub['grade'] }} quantity for {{ $productData['name'] }}"
                                    >
                                </div>
                            @endforeach
                        </div>
                    </div>
                @elseif($hasMultipleGrades && ! $multiGradeOrderingEnabled && $hasExistingExtraQty)
                    <!-- Data Loss Prevention: Preserved Extra Grades from previous order/draft -->
                    <div class="mt-2 rounded-xl border border-amber-200 bg-amber-50/80 p-2.5 text-xs text-amber-900">
                        <div class="flex items-start gap-1.5">
                            <svg class="h-4 w-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div class="flex-1">
                                <p class="font-bold text-amber-900 text-[11px] uppercase tracking-wide">
                                    Preserved Extra Grades
                                </p>
                                <p class="mt-0.5 text-[11px] text-amber-800 leading-snug">
                                    Draft contains quantities ordered while multi-grade ordering was enabled. You can keep, adjust, or set to 0:
                                </p>
                            </div>
                        </div>
                        <div class="mt-2 space-y-1.5">
                            @foreach($subGradeData as $sub)
                                @if($sub['numeric_qty'] > 0)
                                    <div class="flex items-center justify-between gap-2 rounded-lg bg-white/90 p-1.5 border border-amber-200/80 text-xs">
                                        <span class="inline-flex items-center gap-1 font-bold text-slate-700">
                                            <span class="rounded bg-amber-100 px-1 py-0.5 text-[9px] font-black text-amber-800">Grade {{ $sub['grade'] }}</span>
                                            <span class="text-[10px] text-slate-500">Qty ({{ $productData['current_unit_label'] }})</span>
                                        </span>
                                        <input type="hidden" name="item_units[{{ $sub['key'] }}]" value="{{ $productData['current_unit'] }}">
                                        <input type="hidden" name="item_measures[{{ $sub['key'] }}]" value="{{ $productData['current_measure_uuid'] }}">
                                        <input type="hidden" name="item_grades[{{ $sub['key'] }}]" value="{{ $sub['grade'] }}">
                                        <input
                                            type="number"
                                            inputmode="decimal"
                                            step="0.01"
                                            min="0"
                                            name="items[{{ $sub['key'] }}]"
                                            value="{{ $sub['qty'] }}"
                                            data-order-qty
                                            data-master-qty
                                            data-inline-qty
                                            data-product-id="{{ $productData['id'] }}"
                                            data-line-key="{{ $sub['key'] }}"
                                            class="shop-order-row-qty h-7 w-20 rounded border border-amber-300 bg-white px-1.5 text-right text-xs font-bold text-slate-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                            placeholder="0"
                                            aria-label="Grade {{ $sub['grade'] }} preserved quantity for {{ $productData['name'] }}"
                                        >
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                <span data-row-selection-label class="{{ (float) $productData['current_qty'] > 0 ? '' : 'hidden' }} sr-only">{{ $rowSelectionLabel }}</span>
                <p data-unit-conversion-info class="mt-2 hidden rounded-lg border border-emerald-100 bg-emerald-50 px-2 py-1 text-[11px] font-black uppercase tracking-[0.08em] text-emerald-700"></p>
                <p data-row-error class="mt-2 hidden text-[11px] font-bold text-rose-700"></p>
            </article>
        @endforeach
    </div>

    <div id="draft-cart-bar" class="fixed inset-x-0 bottom-0 z-40 hidden border-t border-slate-800 bg-slate-950 px-4 py-3 text-white shadow-[0_-14px_30px_rgba(15,23,42,0.22)] pb-[max(env(safe-area-inset-bottom),0.75rem)] sm:bottom-4 sm:left-1/2 sm:max-w-xl sm:-translate-x-1/2 sm:rounded-2xl sm:border">
        <div class="mx-auto flex max-w-xl items-center gap-3">
            <div class="min-w-0 flex-1">
                <p id="draft-cart-summary" class="truncate text-sm font-black">0 selected</p>
                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-emerald-300">{{ $stickySubmitHint ?? 'Draft only' }}</p>
            </div>
            <button type="button" id="draft-cart-clear" class="rounded-xl border border-white/15 px-3 py-2 text-[11px] font-black uppercase tracking-[0.12em] text-white transition hover:bg-white/10">
                Clear
            </button>
            @if ($canCancelSubmittedOrder)
                <button type="button" id="submitted-order-cancel" data-clear-order-action="{{ route('shop-owner.orders.clear') }}" class="rounded-xl border border-rose-300/50 px-3 py-2 text-[11px] font-black uppercase tracking-[0.12em] text-rose-100 transition hover:bg-rose-500/20">
                    Cancel Order
                </button>
            @endif
            <button type="button" id="draft-cart-submit" class="rounded-xl bg-emerald-500 px-3 py-2 text-[11px] font-black uppercase tracking-[0.12em] text-slate-950 transition hover:bg-emerald-400">
                {{ $stickySubmitLabel ?? 'Add To Cart' }}
            </button>
        </div>
    </div>

    <style>
        .shop-order-row-name {
            display: -webkit-box;
            overflow: hidden;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            line-clamp: 2;
        }

        #shop-owner-order-form.shop-order-layout-two-row [data-product-card] {
            padding: 0.65rem;
        }

        #shop-owner-order-form.shop-order-layout-two-row .shop-order-row-grid {
            grid-template-columns: 4.25rem minmax(0, 1fr);
            gap: 0.5rem;
        }

        #shop-owner-order-form.shop-order-layout-two-row .shop-order-row-unit {
            grid-column: 1 / 2;
            width: 100%;
        }

        #shop-owner-order-form.shop-order-layout-two-row .shop-order-row-qty {
            grid-column: 2 / -1;
        }

        @media (min-width: 640px) {
            #shop-owner-order-form.shop-order-layout-two-row .shop-order-row-grid {
                grid-template-columns: 2.25rem minmax(0, 1fr) 4rem 4.5rem;
            }

            #shop-owner-order-form.shop-order-layout-two-row .shop-order-row-unit {
                grid-column: 3 / 4;
            }

            #shop-owner-order-form.shop-order-layout-two-row .shop-order-row-qty {
                grid-column: 4 / 5;
            }

        }

        @media (max-width: 374px) {
            #shop-owner-order-form:not(.shop-order-layout-two-row) .shop-order-row-grid {
                grid-template-columns: 1.75rem minmax(0, 1fr) 3.35rem 3.55rem;
            }

            #shop-owner-order-form:not(.shop-order-layout-two-row) .shop-order-row-code,
            #shop-owner-order-form:not(.shop-order-layout-two-row) .shop-order-row-title {
                grid-row: 1;
            }

            #shop-owner-order-form:not(.shop-order-layout-two-row) .shop-order-row-title {
                grid-column: 2 / -1;
            }

            #shop-owner-order-form:not(.shop-order-layout-two-row) .shop-order-row-unit {
                grid-column: 3 / 4;
                grid-row: 2;
            }

            #shop-owner-order-form:not(.shop-order-layout-two-row) .shop-order-row-qty {
                grid-column: 4 / 5;
                grid-row: 2;
            }
        }
    </style>

    <!-- Cart Review Drawer Backdrop -->
    <div id="cart-review-backdrop" class="fixed inset-0 z-[80] bg-slate-950/60 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300"></div>

    <!-- Cart Review Drawer -->
    <div id="cart-review-drawer" class="fixed inset-x-0 bottom-0 z-[90] flex max-h-[85vh] flex-col overflow-hidden rounded-t-[2.5rem] border-t border-slate-200 bg-white shadow-2xl transition-transform duration-300 ease-out transform translate-y-full hidden sm:bottom-6 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:w-full sm:max-w-xl sm:rounded-3xl sm:border">
        <div class="mx-auto my-3 h-1.5 w-16 shrink-0 rounded-full bg-slate-200"></div>

        <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-6 py-3">
            <div>
                <h3 class="text-base font-black text-slate-900">{{ ($orderFormMode ?? '') === 'admin-direct-purchase' ? 'Direct Purchase Review' : (isset($isUpdateRequest) && $isUpdateRequest ? 'Item Request Review' : 'Cart Review') }}</h3>
                <p id="review-items-count" class="mt-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">0 items selected</p>
            </div>
            <button type="button" id="cart-review-close" class="rounded-full bg-slate-100 p-2 text-slate-500 hover:bg-slate-200 transition">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
            <!-- Backend validation error banner -->
            <div id="cart-review-error-banner" class="hidden rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-bold text-rose-800"></div>

            <!-- Review Items List -->
            <div id="review-items-list" class="divide-y divide-slate-100 space-y-0.5"></div>


        </div>

        <div class="shrink-0 border-t border-slate-150 bg-slate-50 p-4">
            <div class="flex gap-3">
                <button type="button" id="review-add-more-btn" class="flex-1 rounded-2xl border border-slate-200 bg-white py-3 text-xs font-black uppercase tracking-wider text-slate-700 hover:bg-slate-50 active:scale-95 transition duration-150">
                    Add Items
                </button>
                <button type="button" id="place-order-submit-btn" data-default-label="{{ ($orderFormMode ?? '') === 'admin-direct-purchase' ? 'Create Direct Purchase' : (isset($isUpdateRequest) && $isUpdateRequest ? 'Submit Item Request' : 'Place Order') }}" class="flex-[2] rounded-2xl {{ isset($isUpdateRequest) && $isUpdateRequest ? 'bg-amber-600 hover:bg-amber-700' : 'bg-emerald-600 hover:bg-emerald-700' }} py-3 text-xs font-black uppercase tracking-wider text-white shadow-md active:scale-95 transition duration-150">
                    {{ ($orderFormMode ?? '') === 'admin-direct-purchase' ? 'Create Direct Purchase' : (isset($isUpdateRequest) && $isUpdateRequest ? 'Submit Item Request' : 'Place Order') }}
                </button>
            </div>
        </div>
    </div>

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
