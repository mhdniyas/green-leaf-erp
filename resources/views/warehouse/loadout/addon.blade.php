<x-layouts.app title="Add Product — {{ $shopOrder->loadoutDisplayName() }}">
    @php
        $totalCategories = $productsByCategory->count();
        $totalProducts = (int) $productsByCategory->sum(fn ($category) => $category->products->count());
        $oldProductId = (int) old('product_id', 0);
        $selectedProduct = $productsByCategory
            ->flatMap(fn ($category) => $category->products)
            ->firstWhere('id', $oldProductId);
        $selectedProductLabel = $selectedProduct
            ? (($selectedProduct->sku ? '#'.$selectedProduct->sku.' - ' : '').$selectedProduct->name.' ('.strtoupper($selectedProduct->unit ?: 'KG').')')
            : 'Select product';
    @endphp

    <div class="mx-auto flex w-full max-w-xl min-w-0 flex-col gap-4 py-3 pb-20 lg:px-4 lg:py-4">
        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_12px_28px_rgba(15,23,42,0.16)]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(99,102,241,0.25),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#312e81_100%)] px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <a href="{{ route('warehouse.loadout.show', $shopOrder) }}"
                           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white hover:bg-white/20 transition-all border border-white/10 text-decoration-none">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </a>
                        <div>
                            <h1 class="text-base font-black tracking-tight text-white">Add Product</h1>
                            <p class="text-[9px] font-semibold text-indigo-300">
                                {{ $shopOrder->loadoutDisplayName() }} &middot; {{ $shopOrder->business_date->format('d M Y') }}
                            </p>
                        </div>
                    </div>
                    <span class="rounded-full border border-indigo-300/30 bg-indigo-400/20 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-indigo-200">
                        Addon
                    </span>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-2">
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Categories</p>
                <p class="mt-1 text-lg font-black text-slate-900">{{ number_format($totalCategories) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Products</p>
                <p class="mt-1 text-lg font-black text-slate-900">{{ number_format($totalProducts) }}</p>
            </div>
        </section>

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 space-y-1">
                @foreach($errors->all() as $error)
                    <p class="text-xs font-semibold text-rose-700">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST"
              action="{{ route('warehouse.loadout.addon.store', $shopOrder) }}"
              class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            @csrf

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="sm:col-start-2">
                    <label for="category-filter" class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Category</label>
                    <select id="category-filter"
                            class="h-12 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-black text-slate-800 focus:border-indigo-500 focus:bg-white focus:outline-none">
                        <option value="">All categories</option>
                        @foreach($productsByCategory as $category)
                            <option value="{{ strtolower($category->name) }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="product-combobox-trigger" class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Product</label>
                <input id="product_id" name="product_id" type="hidden" value="{{ $oldProductId > 0 ? $oldProductId : '' }}" required>

                <div id="product-combobox" class="relative">
                    <button id="product-combobox-trigger"
                            type="button"
                            class="flex h-12 w-full items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 text-left text-sm font-black text-slate-800 transition-colors hover:bg-white focus:border-indigo-500 focus:bg-white focus:outline-none">
                        <span id="product-selected-label" class="truncate">{{ $selectedProductLabel }}</span>
                        <svg class="h-4 w-4 shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div id="product-dropdown" class="absolute z-30 mt-2 hidden w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                        <div class="border-b border-slate-100 p-2">
                            <input id="product-search-dropdown"
                                   type="text"
                                   autocomplete="off"
                                   class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none"
                                   placeholder="Search name or SKU...">
                        </div>

                        <div id="product-options" class="max-h-72 overflow-y-auto p-2">
                            @foreach($productsByCategory as $category)
                                <div class="product-category-group mb-2" data-category="{{ strtolower($category->name) }}">
                                    <p class="px-2 pb-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">{{ $category->name }}</p>
                                    <div class="space-y-1">
                                        @foreach($category->products as $product)
                                            @php
                                                $productLabel = ($product->sku ? '#'.$product->sku.' - ' : '').$product->name.' ('.strtoupper($product->unit ?: 'KG').')';
                                                $productSearch = strtolower(trim(($product->sku ? $product->sku.' ' : '').$product->name.' '.($product->unit ?: 'kg').' '.$category->name));
                                                $baseUnit = strtoupper($product->unit ?: 'KG');
                                                $measurementPayload = $product->orderUnits
                                                    ->map(function ($unit) use ($baseUnit) {
                                                        $unitLabel = strtoupper($unit->label ?: $unit->unit);
                                                        $conversion = (float) ($unit->conversion_to_base ?? 0);

                                                        return [
                                                            'label' => $unitLabel,
                                                            'is_base' => (bool) $unit->is_base,
                                                            'conversion' => $conversion,
                                                            'base_unit' => $baseUnit,
                                                        ];
                                                    })
                                                    ->values();
                                            @endphp
                                            <button type="button"
                                                    class="product-option flex w-full items-center justify-between rounded-lg px-2 py-2 text-left text-sm font-semibold text-slate-800 transition-colors hover:bg-indigo-50 hover:text-indigo-700"
                                                    data-value="{{ $product->id }}"
                                                    data-label="{{ $productLabel }}"
                                                    data-category="{{ strtolower($category->name) }}"
                                                    data-search="{{ $productSearch }}"
                                                    data-base-unit="{{ $baseUnit }}"
                                                    data-measurements='@json($measurementPayload)'>
                                                <span class="truncate">{{ $productLabel }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <p id="product-filter-note" class="mt-1 hidden text-xs font-semibold text-amber-700">No product matches your search and category filter.</p>
            </div>

            <div>
                <label for="quantity" class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Approved Quantity</label>
                <input id="quantity"
                       name="quantity"
                       type="number"
                       min="0.01"
                       step="any"
                       inputmode="decimal"
                       value="{{ old('quantity') }}"
                       required
                       class="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-black text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none"
                       placeholder="Enter quantity">

                <div id="measurement-panel" class="mt-2 rounded-xl border border-indigo-100 bg-indigo-50/60 px-3 py-2">
                    <p class="text-[10px] font-black uppercase tracking-[0.12em] text-indigo-700">Available Measurements</p>
                    <p id="measurement-base" class="mt-1 text-xs font-bold text-slate-700">Select a product to view units and conversions.</p>
                    <ul id="measurement-list" class="mt-1 space-y-1 text-xs font-semibold text-slate-700"></ul>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <a href="{{ route('warehouse.loadout.show', $shopOrder) }}"
                   class="inline-flex h-11 flex-1 items-center justify-center rounded-xl border border-slate-200 px-4 text-xs font-black uppercase tracking-wider text-slate-600 transition-colors hover:bg-slate-50 text-decoration-none">
                    Cancel
                </a>
                <button type="submit"
                        class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-indigo-600 px-4 text-xs font-black uppercase tracking-wider text-white transition-colors hover:bg-indigo-700 border-none cursor-pointer">
                    Add to Order
                </button>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const combobox = document.getElementById('product-combobox');
            const trigger = document.getElementById('product-combobox-trigger');
            const dropdown = document.getElementById('product-dropdown');
            const searchInput = document.getElementById('product-search-dropdown');
            const categoryFilter = document.getElementById('category-filter');
            const hiddenInput = document.getElementById('product_id');
            const selectedLabel = document.getElementById('product-selected-label');
            const options = Array.from(document.querySelectorAll('.product-option'));
            const groups = Array.from(document.querySelectorAll('.product-category-group'));
            const emptyNote = document.getElementById('product-filter-note');
            const measurementBase = document.getElementById('measurement-base');
            const measurementList = document.getElementById('measurement-list');

            if (!combobox || !trigger || !dropdown || !searchInput || !categoryFilter || !hiddenInput || !selectedLabel || !emptyNote || !measurementBase || !measurementList) {
                return;
            }

            const normalize = (value) => (value || '').toString().trim().toLowerCase();
            const escapeHtml = (value) => (value || '').toString().replace(/[&<>'"]/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[char]));

            const openDropdown = () => {
                dropdown.classList.remove('hidden');
                trigger.classList.add('border-indigo-500', 'bg-white');
                searchInput.focus();
            };

            const closeDropdown = () => {
                dropdown.classList.add('hidden');
                trigger.classList.remove('border-indigo-500', 'bg-white');
            };

            const filterProducts = () => {
                const query = normalize(searchInput.value);
                const selectedCategory = normalize(categoryFilter.value);

                let visibleCount = 0;

                groups.forEach((group) => {
                    const groupOptions = options.filter((option) => option.closest('.product-category-group') === group);
                    let groupVisible = 0;

                    groupOptions.forEach((option) => {
                        const optionCategory = normalize(option.dataset.category);
                        const searchText = normalize(option.dataset.search);
                        const visible = (!selectedCategory || optionCategory === selectedCategory)
                            && (!query || searchText.includes(query));

                        option.classList.toggle('hidden', !visible);
                        if (visible) {
                            groupVisible++;
                            visibleCount++;
                        }
                    });

                    group.classList.toggle('hidden', groupVisible === 0);
                });

                emptyNote.classList.toggle('hidden', visibleCount > 0);
            };

            const setSelectedProduct = (option) => {
                hiddenInput.value = option.dataset.value || '';
                selectedLabel.textContent = option.dataset.label || 'Select product';
                options.forEach((item) => item.classList.remove('bg-indigo-100', 'text-indigo-800'));
                option.classList.add('bg-indigo-100', 'text-indigo-800');
                renderMeasurements(option);
                closeDropdown();
            };

            const renderMeasurements = (option) => {
                const baseUnit = (option?.dataset.baseUnit || 'KG').toUpperCase();
                let measurements = [];

                try {
                    measurements = JSON.parse(option?.dataset.measurements || '[]');
                } catch (error) {
                    measurements = [];
                }

                measurementBase.textContent = `Base unit: ${baseUnit}`;

                if (!Array.isArray(measurements) || measurements.length === 0) {
                    measurementList.innerHTML = '<li>No measurement conversion configured.</li>';
                    return;
                }

                measurementList.innerHTML = measurements
                    .map((unit) => {
                        const label = escapeHtml((unit?.label || '').toUpperCase());
                        const conversion = Number(unit?.conversion || 0);
                        const unitBase = escapeHtml((unit?.base_unit || baseUnit).toUpperCase());

                        if (unit?.is_base) {
                            return `<li>${label}: base measurement</li>`;
                        }

                        if (conversion > 0) {
                            return `<li>1 ${label} = ${conversion.toFixed(4)} ${unitBase}</li>`;
                        }

                        return `<li>${label}: conversion missing</li>`;
                    })
                    .join('');
            };

            const applyInitialSelection = () => {
                const currentValue = (hiddenInput.value || '').toString();
                if (!currentValue) {
                    return;
                }

                const currentOption = options.find((option) => (option.dataset.value || '') === currentValue);
                if (!currentOption) {
                    hiddenInput.value = '';
                    selectedLabel.textContent = 'Select product';
                    measurementBase.textContent = 'Select a product to view units and conversions.';
                    measurementList.innerHTML = '';
                    return;
                }

                selectedLabel.textContent = currentOption.dataset.label || selectedLabel.textContent;
                currentOption.classList.add('bg-indigo-100', 'text-indigo-800');
                renderMeasurements(currentOption);
            };

            trigger.addEventListener('click', () => {
                if (dropdown.classList.contains('hidden')) {
                    openDropdown();
                } else {
                    closeDropdown();
                }
            });

            options.forEach((option) => {
                option.addEventListener('click', () => setSelectedProduct(option));
            });

            document.addEventListener('click', (event) => {
                if (!combobox.contains(event.target)) {
                    closeDropdown();
                }
            });

            searchInput.addEventListener('input', filterProducts);
            categoryFilter.addEventListener('change', filterProducts);
            searchInput.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeDropdown();
                }
            });

            applyInitialSelection();
            filterProducts();
        })();
    </script>
</x-layouts.app>
