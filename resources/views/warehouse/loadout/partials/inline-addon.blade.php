@php
    $addonProductCount = (int) $addonProductsByCategory->sum(fn ($category) => $category->products->count());
    $oldAddonProductId = (int) old('product_id', 0);
    $selectedAddonProduct = $addonProductsByCategory
        ->flatMap(fn ($category) => $category->products)
        ->firstWhere('id', $oldAddonProductId);
    $selectedAddonLabel = $selectedAddonProduct
        ? '[ADDON] '.($selectedAddonProduct->sku ? '#'.$selectedAddonProduct->sku.' - ' : '').$selectedAddonProduct->name.' ('.strtoupper($selectedAddonProduct->unit ?: 'KG').')'
        : 'Select addon product';
@endphp

<section class="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-3 shadow-sm">
    <button type="button"
            id="toggle-inline-addon"
            class="flex w-full items-center justify-between rounded-xl border border-indigo-200 bg-white px-3 py-2 text-left text-xs font-black uppercase tracking-[0.14em] text-indigo-700 transition-colors hover:bg-indigo-50 cursor-pointer">
        <span>Addon Products</span>
        <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] text-indigo-700">{{ $addonProductCount }}</span>
    </button>

    <div id="inline-addon-panel" class="mt-3 hidden">
        <p class="mb-2 text-[11px] font-semibold text-slate-600">
            Priority: Search and update current shop-order rows first. Use addon only when product is not in this order.
        </p>

        <form method="POST"
              action="{{ route('warehouse.loadout.addon.store', $shopOrder) }}"
              class="space-y-3 rounded-xl border border-indigo-100 bg-white p-3">
            @csrf

            <div>
                <label for="inline-addon-combobox-trigger" class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Select Product</label>
                <input id="inline-addon-product-id" name="product_id" type="hidden" value="{{ $oldAddonProductId > 0 ? $oldAddonProductId : '' }}" required>

                <div id="inline-addon-combobox" class="relative">
                    <button id="inline-addon-combobox-trigger"
                            type="button"
                            class="flex h-11 w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 text-left text-sm font-black text-slate-800 transition-colors hover:bg-white focus:border-indigo-500 focus:bg-white focus:outline-none">
                        <span id="inline-addon-selected-label" class="truncate">{{ $selectedAddonLabel }}</span>
                        <svg class="h-4 w-4 shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div id="inline-addon-combobox-panel" class="absolute z-40 mt-2 hidden w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                        <div class="border-b border-slate-100 p-2">
                            <input id="inline-addon-combobox-search"
                                   type="search"
                                   autocomplete="off"
                                   placeholder="Search addon product"
                                   class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none">
                        </div>

                        <div id="inline-addon-options" class="max-h-72 overflow-y-auto p-2">
                            @foreach($addonProductsByCategory as $category)
                                <div class="inline-addon-category-group mb-2">
                                    <p class="px-2 pb-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">{{ $category->name }}</p>
                                    <div class="space-y-1">
                                        @foreach($category->products as $product)
                                            @php
                                                $optionLabel = '[ADDON] '.($product->sku ? '#'.$product->sku.' - ' : '').$product->name.' ('.strtoupper($product->unit ?: 'KG').')';
                                                $searchText = strtolower(trim(($product->sku ? $product->sku.' ' : '').$product->name.' '.$category->name));
                                            @endphp
                                            <button type="button"
                                                    class="inline-addon-option flex w-full items-center rounded-lg px-2 py-2 text-left text-sm font-semibold text-slate-800 transition-colors hover:bg-indigo-50 hover:text-indigo-700"
                                                    data-value="{{ $product->id }}"
                                                    data-label="{{ $optionLabel }}"
                                                    data-search="{{ $searchText }}">
                                                <span class="truncate">{{ $optionLabel }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <p id="inline-addon-combobox-empty" class="hidden border-t border-slate-100 px-3 py-2 text-xs font-semibold text-amber-700">
                            No product matches this search.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-[1fr_auto] sm:items-end">
                <div>
                    <label for="inline-addon-qty" class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Approved Quantity</label>
                    <input id="inline-addon-qty"
                           name="quantity"
                           type="number"
                           min="0.01"
                           step="any"
                           required
                           class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-black text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none"
                           placeholder="0.00">
                </div>
                <button type="submit"
                        class="inline-flex h-11 items-center justify-center rounded-lg bg-indigo-600 px-4 text-xs font-black uppercase tracking-wider text-white transition-colors hover:bg-indigo-700 border-none cursor-pointer">
                    Add Addon
                </button>
            </div>
        </form>
    </div>
</section>
