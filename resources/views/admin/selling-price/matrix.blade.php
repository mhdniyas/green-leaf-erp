<x-layouts.admin title="Product Selling Price Matrix">
    <div class="mx-auto max-w-7xl space-y-6">
        {{-- Header Section --}}
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Administration</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Selling Price Management</h1>
                    <p class="mt-2 max-w-2xl text-sm font-semibold leading-6 text-slate-500">
                        Configure global retail markups, categories, product-level matrices, and shop pricing rules.
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('admin.overview') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 transition hover:bg-slate-50">
                        Back to Admin
                    </a>
                </div>
            </div>
        </section>

        {{-- Flash Notifications --}}
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800">
                {{ session('error') }}
            </div>
        @endif

        {{-- Module Container --}}
        <div class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-sm">
            {{-- Navigation Tabs --}}
            <div class="flex gap-1 overflow-x-auto border-b border-slate-200 bg-slate-50 p-2" role="tablist" aria-label="Selling price management sections">
                {{-- Tab 1: General Pricing --}}
                <a href="{{ route('admin.selling-price.index') }}"
                   role="tab"
                   aria-selected="false"
                   class="inline-flex items-center gap-2 shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white hover:text-slate-900">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                    </svg>
                    General Pricing
                </a>

                {{-- Tab 2: Price Categories --}}
                <a href="{{ route('admin.selling-price.categories.index') }}"
                   role="tab"
                   aria-selected="false"
                   class="inline-flex items-center gap-2 shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white hover:text-slate-900">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                    </svg>
                    <span>Price Categories</span>
                </a>

                {{-- Tab 3: Product Matrix (Active) --}}
                <a href="{{ route('admin.selling-price.matrix.index') }}"
                   role="tab"
                   aria-selected="true"
                   class="inline-flex items-center gap-2 shrink-0 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                    </svg>
                    <span>Product Matrix</span>
                </a>

                {{-- Tab 4: Shop Pricing --}}
                <a href="{{ route('admin.selling-price.shop-pricing.index') }}"
                   role="tab"
                   aria-selected="false"
                   class="inline-flex items-center gap-2 shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white hover:text-slate-900">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                    </svg>
                    <span>Shop Pricing</span>
                </a>
            </div>

            {{-- Main Content Section --}}
            <div class="p-5 sm:p-6 space-y-6">
                {{-- Search & Filters Form --}}
                <form method="GET" action="{{ route('admin.selling-price.matrix.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5 items-end">
                    {{-- Search Input --}}
                    <div class="lg:col-span-2">
                        <label for="search" class="block text-xs font-bold text-slate-700">Search Products</label>
                        <div class="relative mt-1">
                            <input type="text"
                                   id="search"
                                   name="search"
                                   value="{{ $search }}"
                                   placeholder="Search by product name, SKU, or ID..."
                                   class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                            <svg class="h-4 w-4 text-slate-400 absolute left-3 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Category Filter --}}
                    <div>
                        <label for="category_id" class="block text-xs font-bold text-slate-700">Selling Price Category</label>
                        <select id="category_id"
                                name="category_id"
                                class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                            <option value="all" @selected($categoryId === 'all')>All Categories</option>
                            <option value="unassigned" @selected($categoryId === 'unassigned')>Unassigned (Global Fallback)</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" @selected((string)$categoryId === (string)$cat->id)>
                                    {{ $cat->name }} ({{ $cat->markup_percentage }}%)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Pricing Mode Filter --}}
                    <div>
                        <label for="pricing_mode" class="block text-xs font-bold text-slate-700">Pricing Mode</label>
                        <select id="pricing_mode"
                                name="pricing_mode"
                                class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                            <option value="all" @selected($pricingMode === 'all')>All Pricing Modes</option>
                            <option value="inherited" @selected($pricingMode === 'inherited')>Inherited Only</option>
                            <option value="custom_percentage" @selected($pricingMode === 'custom_percentage')>Custom % Only</option>
                            <option value="fixed_price" @selected($pricingMode === 'fixed_price')>Fixed Retail ₹ Only</option>
                        </select>
                    </div>

                    {{-- Shop Context Preview Selector & Actions --}}
                    <div>
                        <label for="shop_id" class="block text-xs font-bold text-slate-700" title="Select a shop to preview wholesale supply prices">
                            Preview Shop Context
                        </label>
                        <div class="mt-1 flex items-center gap-2">
                            <select id="shop_id"
                                    name="shop_id"
                                    class="h-10 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                                <option value="">No Shop (General)</option>
                                @foreach ($shops as $s)
                                    <option value="{{ $s->id }}" @selected($shopId === $s->id)>
                                        {{ $s->code }} — {{ $s->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="h-10 rounded-xl bg-slate-900 px-3.5 text-xs font-bold text-white hover:bg-slate-800 shrink-0">
                                Filter
                            </button>
                            @if ($search !== '' || $categoryId !== 'all' || $pricingMode !== 'all' || $shopId !== null)
                                <a href="{{ route('admin.selling-price.matrix.index') }}" class="h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-500 hover:bg-slate-50 shrink-0" title="Reset Filters">
                                    Reset
                                </a>
                            @endif
                        </div>
                    </div>
                </form>

                {{-- Bulk Action Floating Toolbar (Appears when checkboxes selected) --}}
                <div id="bulkActionBar" class="hidden rounded-2xl border border-teal-200 bg-teal-50/90 p-3.5 text-xs shadow-xs transition duration-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-2 font-black text-teal-950">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-teal-600 text-xs font-black text-white" id="bulkSelectedBadge">0</span>
                            <span id="bulkSelectedText">0 products selected</span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            {{-- Bulk Category Dropdown & Button --}}
                            <div class="flex items-center gap-1.5">
                                <select id="bulkCategoryId" class="h-9 rounded-xl border border-teal-300 bg-white px-2.5 text-xs font-semibold text-slate-900">
                                    <option value="">Assign Category...</option>
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->markup_percentage }}%)</option>
                                    @endforeach
                                </select>
                                <button type="button" onclick="submitBulkCategory()" class="h-9 rounded-xl bg-teal-700 px-3 text-xs font-bold text-white hover:bg-teal-800">
                                    Assign
                                </button>
                            </div>

                            {{-- Bulk Percentage Input & Button --}}
                            <div class="flex items-center gap-1.5">
                                <input type="number" id="bulkPercentageInput" step="0.01" min="0" max="500" placeholder="Markup %" class="h-9 w-24 rounded-xl border border-teal-300 bg-white px-2.5 text-xs font-bold text-slate-900">
                                <button type="button" onclick="submitBulkPercentage()" class="h-9 rounded-xl bg-teal-700 px-3 text-xs font-bold text-white hover:bg-teal-800">
                                    Set %
                                </button>
                            </div>

                            {{-- Bulk Reset Button --}}
                            <button type="button" onclick="submitBulkReset()" class="h-9 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50">
                                Reset to Inherited
                            </button>

                            <button type="button" onclick="clearAllSelections()" class="text-xs font-bold text-teal-800 hover:underline px-2">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Notice for Shop Context --}}
                @if ($selectedShop)
                    <div class="rounded-2xl border border-blue-200/80 bg-blue-50/70 p-3.5 flex items-center justify-between text-xs text-blue-900">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex h-2 w-2 rounded-full bg-blue-600"></span>
                            <span>Previewing supply prices and retail calculations for shop: <strong class="font-bold text-blue-950">{{ $selectedShop->name }} ({{ $selectedShop->code }})</strong></span>
                        </div>
                        <span class="text-[11px] font-semibold text-blue-700">Today: {{ today()->format('d M Y') }}</span>
                    </div>
                @endif

                {{-- Products Matrix Table --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-slate-100 bg-slate-50/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="w-10 px-3 py-3 text-center">
                                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this.checked)" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                                    </th>
                                    <th class="w-12 px-3 py-3 text-center">SL</th>
                                    <th class="px-4 py-3">Product</th>
                                    <th class="px-4 py-3">Price Category</th>
                                    <th class="px-4 py-3">Pricing Mode</th>
                                    <th class="px-4 py-3">Effective Rule</th>
                                    <th class="px-4 py-3 text-right">Supply Price</th>
                                    <th class="px-4 py-3 text-right">Retail Price</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                                @forelse ($resolvedRows as $index => $row)
                                    @php
                                        $product = $row['product'];
                                        $res = $row['resolution'];
                                        $config = $product->sellingPriceConfig;
                                    @endphp
                                    <tr class="transition hover:bg-slate-50/60" id="row-product-{{ $product->id }}">
                                        {{-- Checkbox --}}
                                        <td class="px-3 py-3.5 text-center">
                                            <input type="checkbox"
                                                   value="{{ $product->id }}"
                                                   class="product-row-checkbox rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                                   onchange="onProductRowCheckboxChange()">
                                        </td>

                                        {{-- SL No --}}
                                        <td class="px-3 py-3.5 text-center text-[11px] font-bold text-slate-400">
                                            {{ $products->firstItem() + $index }}
                                        </td>

                                        {{-- Product Info --}}
                                        <td class="px-4 py-3.5">
                                            <div class="font-black text-slate-900 text-sm">{{ $product->name }}</div>
                                            <div class="text-[11px] text-slate-400 font-medium mt-0.5">
                                                {{ $product->sku }} · {{ $product->unit ?: 'kg' }} · <span class="text-slate-500 font-semibold">{{ $product->category?->name ?? 'General' }}</span>
                                            </div>
                                        </td>

                                        {{-- Price Category --}}
                                        <td class="px-4 py-3.5">
                                            @if ($product->sellingPriceCategory)
                                                <div class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">
                                                    <span class="h-1.5 w-1.5 rounded-full {{ $product->sellingPriceCategory->is_active ? 'bg-teal-500' : 'bg-slate-400' }}"></span>
                                                    <span>{{ $product->sellingPriceCategory->name }}</span>
                                                    <span class="text-[10px] text-slate-400">({{ $product->sellingPriceCategory->markup_percentage }}%)</span>
                                                </div>
                                                @if (! $product->sellingPriceCategory->is_active)
                                                    <span class="block text-[10px] font-medium text-slate-400 mt-0.5">Inactive (Uses Global)</span>
                                                @endif
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-slate-50 border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-500">
                                                    Global Default ({{ $globalPercentage }}%)
                                                </span>
                                            @endif
                                        </td>

                                        {{-- Pricing Mode --}}
                                        <td class="px-4 py-3.5">
                                            @if ($res['is_fixed'])
                                                <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-200 px-2.5 py-1 text-[11px] font-black text-indigo-700">
                                                    Fixed ₹
                                                </span>
                                            @elseif ($res['source'] === 'product_custom')
                                                <span class="inline-flex items-center gap-1 rounded-full bg-teal-50 border border-teal-200 px-2.5 py-1 text-[11px] font-black text-teal-800">
                                                    Custom %
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600">
                                                    Inherited
                                                </span>
                                            @endif
                                        </td>

                                        {{-- Effective Rule --}}
                                        <td class="px-4 py-3.5">
                                            <div class="font-bold text-slate-900 text-xs">
                                                {{ $res['source_label'] }}
                                            </div>
                                            @if ($res['is_override'])
                                                <div class="text-[10px] font-medium text-amber-600">Product Override Active</div>
                                            @endif
                                        </td>

                                        {{-- Supply Price --}}
                                        <td class="px-4 py-3.5 text-right font-bold text-slate-900">
                                            @if ($res['supply_price'] !== null)
                                                ₹{{ number_format($res['supply_price'], 2) }}
                                            @else
                                                <span class="text-slate-400 font-semibold" title="Select a shop above to preview supply price">—</span>
                                            @endif
                                        </td>

                                        {{-- Retail Price --}}
                                        <td class="px-4 py-3.5 text-right">
                                            @if ($res['retail_price'] !== null)
                                                <div class="font-black text-slate-950 text-sm">
                                                    ₹{{ number_format($res['retail_price'], 2) }}
                                                </div>
                                                @if ($res['warning'])
                                                    <div class="text-[10px] font-bold text-rose-600 flex items-center justify-end gap-1 mt-0.5" title="{{ $res['warning'] }}">
                                                        <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                        <span>Below Supply</span>
                                                    </div>
                                                @endif
                                            @else
                                                <span class="text-slate-400 font-semibold" title="Select a shop above to preview retail price">—</span>
                                            @endif
                                        </td>

                                        {{-- Actions --}}
                                        <td class="px-4 py-3.5 text-right">
                                            <button type="button"
                                                    onclick="openEditProductModal({{ json_encode([
                                                        'id' => $product->id,
                                                        'name' => $product->name,
                                                        'sku' => $product->sku,
                                                        'unit' => $product->unit ?: 'kg',
                                                        'category_id' => $product->selling_price_category_id,
                                                        'category_name' => $product->sellingPriceCategory?->name ?? 'Unassigned',
                                                        'category_markup' => $product->sellingPriceCategory?->markup_percentage ?? $globalPercentage,
                                                        'category_is_active' => (bool) ($product->sellingPriceCategory?->is_active ?? true),
                                                        'pricing_mode' => $config?->pricing_mode ?? 'inherited',
                                                        'markup_percentage' => $config?->markup_percentage,
                                                        'fixed_price' => $config?->fixed_price,
                                                        'supply_price' => $res['supply_price'],
                                                        'global_percentage' => $globalPercentage,
                                                    ]) }})"
                                                    class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50 hover:text-slate-900 shadow-2xs">
                                                <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                                </svg>
                                                Edit Rule
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="py-12 text-center">
                                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                                                </svg>
                                            </div>
                                            <h3 class="mt-3 text-sm font-bold text-slate-800">No Products Found</h3>
                                            <p class="mt-1 text-xs text-slate-500">Try adjusting your search criteria or category filter.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Server-side Pagination --}}
                    @if ($products->hasPages())
                        <div class="border-t border-slate-100 bg-slate-50/50 p-4">
                            {{ $products->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Edit Product Pricing Rule --}}
    <div id="editProductModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-xs flex items-center justify-center">
        <div class="w-full max-w-xl rounded-3xl bg-white p-6 shadow-2xl space-y-5">
            {{-- Modal Header --}}
            <div class="flex items-start justify-between border-b border-slate-100 pb-3">
                <div>
                    <span class="rounded-md bg-teal-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-teal-700">Product Pricing Rule</span>
                    <h3 id="epTitle" class="mt-1 text-lg font-black text-slate-950"></h3>
                    <p id="epSkuUnit" class="text-xs font-semibold text-slate-400 mt-0.5"></p>
                </div>
                <button type="button" onclick="closeEditProductModal()" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="editProductForm" method="POST" action="" class="space-y-4">
                @csrf
                @method('PATCH')

                {{-- Price Category Dropdown --}}
                <div>
                    <label for="epCategorySelect" class="block text-xs font-bold text-slate-700">Selling Price Category</label>
                    <select id="epCategorySelect"
                            name="selling_price_category_id"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                        <option value="">Unassigned (Global Default: {{ $globalPercentage }}%)</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->markup_percentage }}%)</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-slate-400 font-medium">Changing category sets the base for inherited pricing. Custom product overrides take higher precedence.</p>
                </div>

                {{-- Pricing Mode Selector --}}
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">General Pricing Mode</label>
                    <div class="grid grid-cols-3 gap-2">
                        <label class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-200 cursor-pointer text-center transition hover:bg-slate-50 has-checked:border-teal-500 has-checked:bg-teal-50/50 has-checked:text-teal-900">
                            <input type="radio" name="pricing_mode" value="inherited" class="sr-only" onchange="onModeChange('inherited')">
                            <span class="text-xs font-black">Inherited</span>
                            <span class="text-[10px] text-slate-400 mt-0.5">Category / Global</span>
                        </label>
                        <label class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-200 cursor-pointer text-center transition hover:bg-slate-50 has-checked:border-teal-500 has-checked:bg-teal-50/50 has-checked:text-teal-900">
                            <input type="radio" name="pricing_mode" value="custom_percentage" class="sr-only" onchange="onModeChange('custom_percentage')">
                            <span class="text-xs font-black">Custom %</span>
                            <span class="text-[10px] text-slate-400 mt-0.5">Product Markup</span>
                        </label>
                        <label class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-200 cursor-pointer text-center transition hover:bg-slate-50 has-checked:border-teal-500 has-checked:bg-teal-50/50 has-checked:text-teal-900">
                            <input type="radio" name="pricing_mode" value="fixed_price" class="sr-only" onchange="onModeChange('fixed_price')">
                            <span class="text-xs font-black">Fixed Retail ₹</span>
                            <span class="text-[10px] text-slate-400 mt-0.5">Manual Amount</span>
                        </label>
                    </div>
                </div>

                {{-- Input: Custom Markup Percentage --}}
                <div id="epCustomPercentageSection" class="hidden space-y-1">
                    <label for="epMarkupInput" class="block text-xs font-bold text-slate-700">Custom Markup Percentage (%)</label>
                    <div class="relative">
                        <input type="number"
                               id="epMarkupInput"
                               name="markup_percentage"
                               step="0.01"
                               min="0"
                               max="500"
                               placeholder="e.g. 35.00"
                               class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 pr-8 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-black text-slate-400">%</span>
                    </div>
                    <p class="text-[11px] text-slate-400 font-semibold">Accepted range: 0% – 500%</p>
                </div>

                {{-- Input: Manual Fixed Retail Price --}}
                <div id="epFixedPriceSection" class="hidden space-y-1">
                    <label for="epFixedPriceInput" class="block text-xs font-bold text-slate-700">Manual Fixed Retail Price (₹)</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs font-black text-slate-400">₹</span>
                        <input type="number"
                               id="epFixedPriceInput"
                               name="fixed_price"
                               step="0.01"
                               min="0"
                               placeholder="e.g. 45.00"
                               class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-7 pr-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                    </div>
                    <p class="text-[11px] text-slate-400 font-semibold">Recommended retail price remains constant regardless of supply price shifts.</p>
                </div>

                {{-- Live Preview Box --}}
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5 text-xs space-y-1.5">
                    <div class="flex items-center justify-between text-slate-600 font-semibold">
                        <span id="epPreviewSupplyLabel">Supply Price Basis</span>
                        <span id="epPreviewSupplyVal" class="font-bold text-slate-900">₹100.00 (Example)</span>
                    </div>
                    <div class="flex items-center justify-between text-teal-700 font-bold">
                        <span>Effective Pricing Rule</span>
                        <span id="epPreviewRuleVal"></span>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-200 pt-1.5 font-black text-slate-900">
                        <span>Recommended Min Retail Price</span>
                        <span id="epPreviewRetailVal" class="text-sm font-black text-teal-800"></span>
                    </div>
                    <div id="epPreviewWarning" class="hidden mt-1 rounded-lg bg-rose-50 border border-rose-200 p-2 text-[11px] font-bold text-rose-700"></div>
                </div>

                {{-- Form Actions Bar --}}
                <div class="flex items-center justify-between border-t border-slate-100 pt-3">
                    <button type="button" onclick="resetToInheritedInModal()" class="text-xs font-bold text-slate-500 hover:text-slate-800 hover:underline">
                        Reset to Inherited
                    </button>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="closeEditProductModal()" class="rounded-xl px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100">Cancel</button>
                        <button type="submit" class="rounded-xl bg-teal-600 px-5 py-2 text-xs font-black text-white hover:bg-teal-500 shadow-sm">Save Pricing Rule</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Frontend JavaScript --}}
    <script>
        let currentModalProduct = null;
        const categoriesMap = @json($categories->keyBy('id'));
        const globalFallbackPct = {{ $globalPercentage }};

        // Checkbox Selection Logic
        function onProductRowCheckboxChange() {
            const checkedBoxes = document.querySelectorAll('.product-row-checkbox:checked');
            const count = checkedBoxes.length;
            const bar = document.getElementById('bulkActionBar');
            const badge = document.getElementById('bulkSelectedBadge');
            const text = document.getElementById('bulkSelectedText');

            badge.innerText = count;
            text.innerText = count === 1 ? '1 product selected' : `${count} products selected`;

            if (count > 0) {
                bar.classList.remove('hidden');
            } else {
                bar.classList.add('hidden');
            }
        }

        function toggleSelectAll(checked) {
            document.querySelectorAll('.product-row-checkbox').forEach(cb => {
                cb.checked = checked;
            });
            onProductRowCheckboxChange();
        }

        function clearAllSelections() {
            document.getElementById('selectAllCheckbox').checked = false;
            toggleSelectAll(false);
        }

        function getSelectedProductIds() {
            const ids = [];
            document.querySelectorAll('.product-row-checkbox:checked').forEach(cb => {
                ids.push(parseInt(cb.value));
            });
            return ids;
        }

        // Bulk Actions
        function submitBulkCategory() {
            const catId = document.getElementById('bulkCategoryId').value;
            const ids = getSelectedProductIds();
            if (ids.length === 0) return alert('Select at least one product.');
            if (!catId) return alert('Select a category to assign.');

            if (!confirm(`Assign category to ${ids.length} selected product(s)?`)) return;

            fetch("{{ route('admin.selling-price.matrix.bulk-category') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ product_ids: ids, selling_price_category_id: parseInt(catId) })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error updating category.');
                }
            });
        }

        function submitBulkPercentage() {
            const pct = document.getElementById('bulkPercentageInput').value;
            const ids = getSelectedProductIds();
            if (ids.length === 0) return alert('Select at least one product.');
            if (pct === '' || isNaN(pct) || parseFloat(pct) < 0 || parseFloat(pct) > 500) {
                return alert('Please enter a valid percentage between 0% and 500%.');
            }

            if (!confirm(`Set custom markup of ${pct}% for ${ids.length} selected product(s)?`)) return;

            fetch("{{ route('admin.selling-price.matrix.bulk-percentage') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ product_ids: ids, markup_percentage: parseFloat(pct) })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error updating markup percentage.');
                }
            });
        }

        function submitBulkReset() {
            const ids = getSelectedProductIds();
            if (ids.length === 0) return alert('Select at least one product.');
            if (!confirm(`Reset ${ids.length} product(s) to inherited pricing? Product-level overrides will be cleared.`)) return;

            fetch("{{ route('admin.selling-price.matrix.bulk-reset') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ product_ids: ids })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.reload();
                } else {
                    alert(data.message || 'Error resetting pricing.');
                }
            });
        }

        // Single Product Edit Modal
        function openEditProductModal(prod) {
            currentModalProduct = prod;

            document.getElementById('epTitle').innerText = prod.name;
            document.getElementById('epSkuUnit').innerText = `${prod.sku} · Unit: ${prod.unit}`;
            document.getElementById('epCategorySelect').value = prod.category_id || '';

            const form = document.getElementById('editProductForm');
            form.action = `{{ url('admin/selling-price/matrix/products') }}/${prod.id}`;

            document.querySelectorAll('input[name="pricing_mode"]').forEach(radio => {
                radio.checked = (radio.value === prod.pricing_mode);
            });

            document.getElementById('epMarkupInput').value = prod.markup_percentage !== null ? prod.markup_percentage : '';
            document.getElementById('epFixedPriceInput').value = prod.fixed_price !== null ? prod.fixed_price : '';

            onModeChange(prod.pricing_mode);
            updateModalPreview();

            document.getElementById('editProductModal').classList.remove('hidden');
        }

        function closeEditProductModal() {
            document.getElementById('editProductModal').classList.add('hidden');
            currentModalProduct = null;
        }

        function onModeChange(mode) {
            const pctSec = document.getElementById('epCustomPercentageSection');
            const fixedSec = document.getElementById('epFixedPriceSection');

            pctSec.classList.toggle('hidden', mode !== 'custom_percentage');
            fixedSec.classList.toggle('hidden', mode !== 'fixed_price');

            updateModalPreview();
        }

        function updateModalPreview() {
            if (!currentModalProduct) return;

            const modeRadio = document.querySelector('input[name="pricing_mode"]:checked');
            const mode = modeRadio ? modeRadio.value : 'inherited';

            const catSelect = document.getElementById('epCategorySelect');
            const selectedCatId = catSelect.value ? parseInt(catSelect.value) : null;
            const categoryObj = selectedCatId ? categoriesMap[selectedCatId] : null;

            const supplyPriceBasis = currentModalProduct.supply_price !== null
                ? currentModalProduct.supply_price
                : 100.00;

            const isIllustrative = currentModalProduct.supply_price === null;
            document.getElementById('epPreviewSupplyLabel').innerText = isIllustrative
                ? 'Illustrative Supply Price'
                : 'Selected Shop Supply Price';
            document.getElementById('epPreviewSupplyVal').innerText = isIllustrative
                ? '₹100.00 (Example)'
                : '₹' + supplyPriceBasis.toFixed(2);

            let retailPrice = 0;
            let ruleLabel = '';
            let warningText = '';

            if (mode === 'fixed_price') {
                const fixedVal = parseFloat(document.getElementById('epFixedPriceInput').value) || 0;
                retailPrice = fixedVal;
                ruleLabel = `Fixed Retail: ₹${fixedVal.toFixed(2)}`;

                if (!isIllustrative && fixedVal < supplyPriceBasis) {
                    warningText = `Warning: Fixed price (₹${fixedVal.toFixed(2)}) is below the current supply price (₹${supplyPriceBasis.toFixed(2)}).`;
                }
            } else if (mode === 'custom_percentage') {
                const pctVal = parseFloat(document.getElementById('epMarkupInput').value) || 0;
                retailPrice = supplyPriceBasis * (1 + (pctVal / 100));
                ruleLabel = `Custom Markup (${pctVal}%)`;
            } else {
                // Inherited
                let pct = globalFallbackPct;
                if (categoryObj && categoryObj.is_active) {
                    pct = categoryObj.markup_percentage;
                    ruleLabel = `Category: ${categoryObj.name} (${pct}%)`;
                } else if (categoryObj && !categoryObj.is_active) {
                    ruleLabel = `Global Default (${pct}%) — Category Inactive`;
                } else {
                    ruleLabel = `Global Default (${pct}%)`;
                }
                retailPrice = supplyPriceBasis * (1 + (pct / 100));
            }

            document.getElementById('epPreviewRuleVal').innerText = ruleLabel;
            document.getElementById('epPreviewRetailVal').innerText = '₹' + (Math.round(retailPrice * 100) / 100).toFixed(2);

            const warnEl = document.getElementById('epPreviewWarning');
            if (warningText) {
                warnEl.innerText = warningText;
                warnEl.classList.remove('hidden');
            } else {
                warnEl.classList.add('hidden');
            }
        }

        document.getElementById('epCategorySelect').addEventListener('change', updateModalPreview);
        document.getElementById('epMarkupInput').addEventListener('input', updateModalPreview);
        document.getElementById('epFixedPriceInput').addEventListener('input', updateModalPreview);

        function resetToInheritedInModal() {
            document.querySelectorAll('input[name="pricing_mode"]').forEach(radio => {
                radio.checked = (radio.value === 'inherited');
            });
            document.getElementById('epMarkupInput').value = '';
            document.getElementById('epFixedPriceInput').value = '';
            onModeChange('inherited');
        }
    </script>
</x-layouts.admin>

