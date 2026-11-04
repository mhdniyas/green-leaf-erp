<x-layouts.admin title="Shop Pricing — Selling Price Management">
    <div class="mx-auto max-w-7xl space-y-6 pb-24">
        {{-- Header Section --}}
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Administration</p>
                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800">Shop Overrides</span>
                    </div>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Shop-Wise Selling Price Management</h1>
                    <p class="mt-2 max-w-3xl text-sm font-semibold leading-6 text-slate-500">
                        Configure shop-specific retail markup percentages for individual shops or apply bulk area-wise pricing across multiple shops.
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button"
                            onclick="openAreaWiseModal()"
                            class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 text-xs font-black text-white shadow-xs transition hover:bg-emerald-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                        </svg>
                        <span>Area-Wise Bulk Pricing</span>
                    </button>
                    <a href="{{ route('admin.overview') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 transition hover:bg-slate-50">
                        Back to Admin
                    </a>
                </div>
            </div>
        </section>

        {{-- Flash Notification --}}
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Precedence Information Alert --}}
        <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 text-xs font-semibold text-amber-900 flex items-start gap-3">
            <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            <div>
                <span class="font-bold uppercase tracking-wider text-amber-800">Pricing Precedence Hierarchy:</span>
                <span class="font-bold text-emerald-800">1. Shop Custom %</span> &gt;
                <span class="font-bold text-indigo-800">2. Fixed Retail Price</span> &gt;
                <span class="font-bold text-blue-800">3. Product Custom %</span> &gt;
                <span class="font-bold text-slate-800">4. Category %</span> &gt;
                <span class="text-slate-600">5. Global Default ({{ number_format($globalPercentage, 1) }}%)</span>.
                <p class="mt-1 text-amber-800">
                    A shop-specific percentage takes precedence over all general product rules, including fixed retail prices, allowing precise local area pricing.
                </p>
            </div>
        </div>

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

                {{-- Tab 3: Product Matrix --}}
                <a href="{{ route('admin.selling-price.matrix.index') }}"
                   role="tab"
                   aria-selected="false"
                   class="inline-flex items-center gap-2 shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white hover:text-slate-900">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                    </svg>
                    <span>Product Matrix</span>
                </a>

                {{-- Tab 4: Shop Pricing (Active) --}}
                <a href="{{ route('admin.selling-price.shop-pricing.index') }}"
                   role="tab"
                   aria-selected="true"
                   class="inline-flex items-center gap-2 shrink-0 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                    </svg>
                    <span>Shop Pricing</span>
                </a>
            </div>

            {{-- Summary Metrics Bar --}}
            <div class="grid grid-cols-2 gap-4 border-b border-slate-200 bg-slate-50/50 p-4 sm:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Selected Shop</p>
                    <p class="mt-1 text-sm font-black text-slate-900 truncate">
                        {{ $selectedShop ? $selectedShop->name : 'No Shop Selected' }}
                    </p>
                    @if ($selectedShop)
                        <span class="text-[10px] font-semibold text-slate-500">
                            Code: {{ $selectedShop->code }} | Group: {{ strtoupper($selectedShop->priceGroup?->name ?? 'A') }}
                        </span>
                    @endif
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Products</p>
                    <p class="mt-1 text-lg font-black text-slate-900">{{ number_format($totalProductsCount) }}</p>
                    <span class="text-[10px] font-semibold text-slate-500">Active catalog items</span>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">Shop Custom Overrides</p>
                    <p class="mt-1 text-lg font-black text-emerald-700">{{ number_format($shopCustomizedCount) }}</p>
                    <span class="text-[10px] font-semibold text-emerald-600">Specific % active</span>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Inherited Pricing</p>
                    <p class="mt-1 text-lg font-black text-slate-700">{{ number_format($shopInheritedCount) }}</p>
                    <span class="text-[10px] font-semibold text-slate-500">Fixed/Category/Global</span>
                </div>
            </div>

            {{-- Filter & Context Bar --}}
            <div class="border-b border-slate-200 bg-white p-4">
                <form method="GET" action="{{ route('admin.selling-price.shop-pricing.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                        {{-- 1. Shop Selector --}}
                        <div class="sm:w-64 shrink-0">
                            <label for="shop_id" class="sr-only">Select Shop</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-emerald-600">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                                    </svg>
                                </span>
                                <select id="shop_id"
                                        name="shop_id"
                                        onchange="this.form.submit()"
                                        class="h-10 w-full rounded-xl border border-slate-300 bg-white pl-9 pr-8 text-xs font-bold text-slate-800 shadow-xs focus:border-emerald-500 focus:outline-hidden focus:ring-1 focus:ring-emerald-500">
                                    @foreach ($shops as $s)
                                        <option value="{{ $s->id }}" @selected($selectedShopId === $s->id)>
                                            {{ $s->name }} ({{ $s->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- 2. Search Input --}}
                        <div class="relative flex-1">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                            </span>
                            <input type="text"
                                   name="search"
                                   value="{{ $search }}"
                                   placeholder="Search products by name, SKU, or ID..."
                                   class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-9 pr-3 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-slate-400 focus:bg-white focus:outline-hidden" />
                        </div>

                        {{-- 3. Category Filter --}}
                        <div class="sm:w-44">
                            <label for="category_id" class="sr-only">Category Filter</label>
                            <select id="category_id"
                                    name="category_id"
                                    onchange="this.form.submit()"
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 text-xs font-semibold text-slate-700 focus:border-slate-400 focus:bg-white focus:outline-hidden">
                                <option value="all" @selected($categoryId === 'all')>All Categories</option>
                                <option value="unassigned" @selected($categoryId === 'unassigned')>Unassigned</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}" @selected($categoryId == $cat->id)>
                                        {{ $cat->name }} ({{ number_format((float) $cat->markup_percentage, 1) }}%)
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- 4. Override Status Filter --}}
                        <div class="sm:w-44">
                            <label for="override_status" class="sr-only">Override Status</label>
                            <select id="override_status"
                                    name="override_status"
                                    onchange="this.form.submit()"
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 text-xs font-semibold text-slate-700 focus:border-slate-400 focus:bg-white focus:outline-hidden">
                                <option value="all" @selected($overrideStatus === 'all')>All Products</option>
                                <option value="customized" @selected($overrideStatus === 'customized')>Shop Custom Only</option>
                                <option value="inherited" @selected($overrideStatus === 'inherited')>Inherited Only</option>
                            </select>
                        </div>

                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-900 px-4 text-xs font-black text-white hover:bg-slate-800 shrink-0">
                            Filter
                        </button>

                        @if ($search !== '' || $categoryId !== 'all' || $overrideStatus !== 'all')
                            <a href="{{ route('admin.selling-price.shop-pricing.index', ['shop_id' => $selectedShopId]) }}"
                               class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 shrink-0">
                                Clear
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Products Table --}}
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="w-10 px-4 py-3.5 text-center">
                                <input type="checkbox"
                                       id="selectAllRows"
                                       onchange="toggleSelectAll(this)"
                                       class="h-4 w-4 rounded-md border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                            </th>
                            <th class="w-14 px-3 py-3.5 text-center">SL No.</th>
                            <th class="px-4 py-3.5">Product & SKU</th>
                            <th class="px-4 py-3.5">General Rule</th>
                            <th class="px-4 py-3.5">Shop Markup</th>
                            <th class="px-4 py-3.5 text-right">Wholesale Supply Price</th>
                            <th class="px-4 py-3.5 text-right">Effective Retail Price</th>
                            <th class="w-36 px-4 py-3.5 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse ($resolvedRows as $index => $row)
                            @php
                                $product = $row['product'];
                                $resolution = $row['resolution'];
                                $general = $row['general_rule'];
                                $supplyPrice = $row['supply_price'];
                                $isShopCustom = $resolution['is_shop_override'];
                                $overrideRecord = $resolution['override_record'];
                                $slNo = $products->firstItem() + $index;
                            @endphp
                            <tr class="transition hover:bg-slate-50/70" id="product-row-{{ $product->id }}">
                                {{-- Checkbox --}}
                                <td class="px-4 py-3.5 text-center">
                                    <input type="checkbox"
                                           name="selected_products[]"
                                           value="{{ $product->id }}"
                                           data-product-id="{{ $product->id }}"
                                           data-product-name="{{ $product->name }}"
                                           onchange="updateSelectionBar()"
                                           class="row-checkbox h-4 w-4 rounded-md border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                </td>

                                {{-- SL No. --}}
                                <td class="px-3 py-3.5 text-center font-bold text-slate-400">
                                    {{ $slNo }}
                                </td>

                                {{-- Product Name & SKU --}}
                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-slate-900">{{ $product->name }}</div>
                                    <div class="mt-0.5 flex items-center gap-2 text-[11px] text-slate-500">
                                        <span class="font-mono">{{ $product->sku }}</span>
                                        <span>•</span>
                                        <span>Unit: {{ $product->unit ?: 'kg' }}</span>
                                    </div>
                                </td>

                                {{-- General Rule (Tiers 2-5) --}}
                                <td class="px-4 py-3.5">
                                    @if ($general['pricing_mode'] === 'fixed_price')
                                        <div class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 px-2 py-1 text-[11px] font-bold text-indigo-700">
                                            <span>Fixed: ₹{{ number_format((float) $general['fixed_price'], 2) }}</span>
                                        </div>
                                    @elseif ($general['pricing_mode'] === 'custom_percentage')
                                        <div class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2 py-1 text-[11px] font-bold text-blue-700">
                                            <span>Product Custom: {{ number_format((float) $general['effective_percentage'], 1) }}%</span>
                                        </div>
                                    @elseif ($general['source'] === 'category')
                                        <div class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">
                                            <span>{{ $general['source_label'] }}</span>
                                        </div>
                                    @else
                                        <div class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-500">
                                            <span>Global Default ({{ number_format((float) $general['effective_percentage'], 1) }}%)</span>
                                        </div>
                                    @endif
                                </td>

                                {{-- Shop Markup --}}
                                <td class="px-4 py-3.5">
                                    @if ($isShopCustom)
                                        <div class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-[11px] font-black text-emerald-800">
                                            <span>{{ number_format((float) $resolution['effective_percentage'], 2) }}% (Custom)</span>
                                        </div>
                                        @if ($overrideRecord && $overrideRecord->updater)
                                            <div class="text-[10px] text-slate-400 mt-0.5">
                                                by {{ $overrideRecord->updater->name }} • {{ $overrideRecord->updated_at?->diffForHumans() }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="inline-flex items-center rounded-lg bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500">
                                            Inherited
                                        </span>
                                    @endif
                                </td>

                                {{-- Supply Price --}}
                                <td class="px-4 py-3.5 text-right font-mono text-xs">
                                    @if ($supplyPrice !== null)
                                        <span class="font-bold text-slate-900">₹{{ number_format((float) $supplyPrice, 2) }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- Retail Price --}}
                                <td class="px-4 py-3.5 text-right font-mono text-xs">
                                    @if ($resolution['retail_price'] !== null)
                                        <span class="font-black text-emerald-700">₹{{ number_format((float) $resolution['retail_price'], 2) }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- Action --}}
                                <td class="px-4 py-3.5 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button type="button"
                                                onclick="openEditModal({{ json_encode([
                                                    'productId' => $product->id,
                                                    'productName' => $product->name,
                                                    'productSku' => $product->sku,
                                                    'productUnit' => $product->unit ?: 'kg',
                                                    'shopId' => $selectedShop?->id,
                                                    'shopName' => $selectedShop?->name,
                                                    'supplyPrice' => $supplyPrice,
                                                    'generalMode' => $general['pricing_mode'],
                                                    'generalLabel' => $general['source_label'],
                                                    'generalFixed' => $general['fixed_price'],
                                                    'generalPercentage' => $general['effective_percentage'],
                                                    'isShopCustom' => $isShopCustom,
                                                    'shopPercentage' => $isShopCustom ? (float) $resolution['effective_percentage'] : null,
                                                    'effectiveRetailPrice' => $resolution['retail_price'],
                                                    'updateUrl' => $selectedShop ? route('admin.selling-price.shop-pricing.update-override', ['shop' => $selectedShop->id, 'product' => $product->id]) : '',
                                                    'resetUrl' => $selectedShop ? route('admin.selling-price.shop-pricing.reset-override', ['shop' => $selectedShop->id, 'product' => $product->id]) : '',
                                                ]) }})"
                                                class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-slate-900">
                                            Edit
                                        </button>

                                        @if ($isShopCustom && $selectedShop)
                                            <form method="POST"
                                                  action="{{ route('admin.selling-price.shop-pricing.reset-override', ['shop' => $selectedShop->id, 'product' => $product->id]) }}"
                                                  onsubmit="return confirm('Reset {{ addslashes($product->name) }} to general pricing for {{ addslashes($selectedShop->name) }}?');"
                                                  class="inline-block">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        title="Reset to general pricing"
                                                        class="rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-bold text-rose-700 hover:bg-rose-100">
                                                    Reset
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-sm font-semibold text-slate-400">
                                    No products found matching the criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination Footer --}}
            @if ($products->hasPages())
                <div class="border-t border-slate-200 bg-slate-50/50 p-4">
                    {{ $products->links() }}
                </div>
            @endif
        </div>

        {{-- Floating Selection Action Bar --}}
        <div id="selectionBar"
             class="fixed bottom-6 inset-x-0 mx-auto max-w-2xl rounded-2xl border border-slate-900/10 bg-slate-900/95 p-4 text-white shadow-2xl backdrop-blur-md transition-all duration-300 translate-y-24 opacity-0 pointer-events-none z-40 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span id="selectedCountBadge" class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500 text-xs font-black text-slate-950">
                    0
                </span>
                <span class="text-xs font-bold">product(s) selected</span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button"
                        onclick="openAreaWiseModalWithSelected()"
                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl bg-emerald-500 px-3.5 text-xs font-black text-slate-950 hover:bg-emerald-400 shadow-sm">
                    Bulk Apply Markup...
                </button>
                @if ($selectedShop)
                    <button type="button"
                            onclick="confirmBulkResetCurrentShop()"
                            class="inline-flex h-9 items-center justify-center rounded-xl bg-rose-600 px-3.5 text-xs font-black text-white hover:bg-rose-500">
                        Reset for {{ $selectedShop->name }}
                    </button>
                @endif
                <button type="button"
                        onclick="clearRowSelections()"
                        class="h-9 rounded-xl border border-white/20 bg-transparent px-3 text-xs font-bold text-white hover:bg-white/10">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    {{-- Single Product Focused Edit Modal --}}
    <div id="editModal"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-xs hidden"
         role="dialog"
         aria-modal="true"
         aria-labelledby="editModalTitle">
        <div class="w-full max-w-lg overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div class="border-b border-slate-100 bg-slate-50/70 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Shop Selling Price Override</p>
                        <h2 id="editModalTitle" class="mt-1 text-lg font-black text-slate-900">Configure Shop Price</h2>
                        <p id="editModalSubtitle" class="mt-0.5 text-xs font-semibold text-slate-500"></p>
                    </div>
                    <button type="button" onclick="closeEditModal()" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <form id="editForm" method="POST" action="" class="p-6 space-y-5">
                @csrf
                {{-- Context Box --}}
                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 space-y-2 text-xs">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 font-semibold">Target Shop:</span>
                        <span id="modalShopName" class="font-bold text-slate-900"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 font-semibold">General Pricing Rule:</span>
                        <span id="modalGeneralRule" class="font-bold text-slate-800"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 font-semibold">Wholesale Supply Price:</span>
                        <span id="modalSupplyPrice" class="font-bold font-mono text-slate-900"></span>
                    </div>
                </div>

                {{-- Shop Markup Percentage Input --}}
                <div class="space-y-2">
                    <label for="modalMarkupInput" class="block text-xs font-bold text-slate-800">
                        Shop Custom Markup Percentage (%)
                    </label>
                    <div class="relative">
                        <input type="number"
                               id="modalMarkupInput"
                               name="markup_percentage"
                               step="0.01"
                               min="0"
                               max="500"
                               required
                               placeholder="e.g. 35.00"
                               oninput="updateModalPreview()"
                               class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-bold text-slate-900 shadow-xs focus:border-emerald-500 focus:outline-hidden focus:ring-1 focus:ring-emerald-500" />
                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-xs font-bold text-slate-400">%</span>
                    </div>
                    <p class="text-[11px] text-slate-500">
                        Range: 0% to 500%. Overrides any general fixed price or category percentage for this shop.
                    </p>
                </div>

                {{-- Live Calculation Preview Box --}}
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Calculated Retail Price Preview</p>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span class="text-xs font-bold text-emerald-950">Recommended Retail (MRP):</span>
                        <span id="modalRetailPreview" class="text-xl font-black font-mono text-emerald-800">₹0.00</span>
                    </div>
                    <p id="modalFormulaBreakdown" class="mt-1 text-[11px] font-semibold text-emerald-700"></p>
                </div>

                {{-- Modal Action Buttons --}}
                <div class="flex items-center justify-between pt-2">
                    <div>
                        <button type="button"
                                id="modalResetButton"
                                onclick="executeModalReset()"
                                class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-bold text-rose-700 hover:bg-rose-100 hidden">
                            Reset to General Rule
                        </button>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                onclick="closeEditModal()"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit"
                                class="rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-black text-white hover:bg-emerald-700 shadow-xs">
                            Save Override
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Area-Wise Bulk Pricing Modal --}}
    <div id="areaWiseModal"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-xs hidden"
         role="dialog"
         aria-modal="true"
         aria-labelledby="areaWiseModalTitle">
        <div class="w-full max-w-2xl overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <div class="border-b border-slate-100 bg-slate-50/70 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-600">Area-Wise Pricing</p>
                        <h2 id="areaWiseModalTitle" class="mt-1 text-lg font-black text-slate-900">Bulk Shop Pricing</h2>
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">Apply a markup percentage across multiple shops and products in one operation.</p>
                    </div>
                    <button type="button" onclick="closeAreaWiseModal()" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <form id="bulkApplyForm" method="POST" action="{{ route('admin.selling-price.shop-pricing.bulk-apply') }}" class="p-6 space-y-6">
                @csrf
                {{-- Step 1: Select Shops --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-black uppercase tracking-wider text-slate-700">
                            1. Select Target Shops (<span id="bulkShopCountBadge">0</span> selected)
                        </label>
                        <div class="flex items-center gap-2 text-[11px]">
                            <button type="button" onclick="selectAllShops(true)" class="font-bold text-emerald-600 hover:underline">Select All</button>
                            <span class="text-slate-300">•</span>
                            <button type="button" onclick="selectAllShops(false)" class="font-bold text-slate-500 hover:underline">Deselect All</button>
                        </div>
                    </div>
                    <div class="max-h-36 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/50 p-3 grid grid-cols-2 gap-2 text-xs">
                        @foreach ($shops as $s)
                            <label class="flex items-center gap-2 rounded-lg bg-white p-2 border border-slate-200/80 cursor-pointer hover:border-emerald-500">
                                <input type="checkbox"
                                       name="shop_ids[]"
                                       value="{{ $s->id }}"
                                       onchange="updateBulkPreview()"
                                       class="bulk-shop-cb h-4 w-4 rounded-md border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                <span class="font-bold text-slate-800 truncate">{{ $s->name }}</span>
                                <span class="text-[10px] text-slate-400 font-mono">({{ $s->code }})</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Step 2: Target Products --}}
                <div class="space-y-2">
                    <label class="text-xs font-black uppercase tracking-wider text-slate-700">
                        2. Target Products (<span id="bulkProductCountBadge">0</span> selected)
                    </label>
                    <div id="bulkProductContainer" class="rounded-xl border border-slate-200 bg-slate-50/50 p-3 text-xs">
                        <p id="bulkProductSelectionMsg" class="font-semibold text-slate-600"></p>
                    </div>
                    <div id="hiddenProductInputs"></div>
                </div>

                {{-- Step 3: Markup Percentage --}}
                <div class="space-y-2">
                    <label for="bulkMarkupInput" class="block text-xs font-black uppercase tracking-wider text-slate-700">
                        3. Set Markup Percentage (%)
                    </label>
                    <div class="relative">
                        <input type="number"
                               id="bulkMarkupInput"
                               name="markup_percentage"
                               step="0.01"
                               min="0"
                               max="500"
                               required
                               placeholder="e.g. 30.00"
                               oninput="updateBulkPreview()"
                               class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-bold text-slate-900 shadow-xs focus:border-emerald-500 focus:outline-hidden focus:ring-1 focus:ring-emerald-500" />
                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-xs font-bold text-slate-400">%</span>
                    </div>
                </div>

                {{-- Calculation Summary Preview --}}
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <p class="text-xs font-bold text-emerald-950">
                            Operation Summary:
                        </p>
                    </div>
                    <p id="bulkPreviewText" class="mt-1 text-xs font-semibold text-emerald-800">
                        Select at least one shop and one product to preview.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button"
                            onclick="closeAreaWiseModal()"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit"
                            id="bulkSubmitBtn"
                            disabled
                            class="rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-black text-white hover:bg-emerald-700 shadow-xs disabled:opacity-50 disabled:cursor-not-allowed">
                        Apply Bulk Pricing
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Hidden form for Bulk Reset --}}
    <form id="bulkResetForm" method="POST" action="{{ route('admin.selling-price.shop-pricing.bulk-reset') }}" class="hidden">
        @csrf
        <div id="bulkResetInputs"></div>
    </form>

    <script>
        let currentModalData = null;

        // --- Single Product Edit Modal ---
        function openEditModal(data) {
            currentModalData = data;
            const modal = document.getElementById('editModal');
            const form = document.getElementById('editForm');
            const title = document.getElementById('editModalTitle');
            const subtitle = document.getElementById('editModalSubtitle');
            const shopName = document.getElementById('modalShopName');
            const generalRule = document.getElementById('modalGeneralRule');
            const supplyPrice = document.getElementById('modalSupplyPrice');
            const markupInput = document.getElementById('modalMarkupInput');
            const resetBtn = document.getElementById('modalResetButton');

            form.action = data.updateUrl;
            title.textContent = `Configure Price for ${data.productName}`;
            subtitle.textContent = `SKU: ${data.productSku} • Unit: ${data.productUnit}`;
            shopName.textContent = data.shopName || 'None';
            generalRule.textContent = data.generalLabel;

            if (data.supplyPrice !== null && data.supplyPrice > 0) {
                supplyPrice.textContent = `₹${parseFloat(data.supplyPrice).toFixed(2)}`;
            } else {
                supplyPrice.textContent = '— (No approved price)';
            }

            if (data.isShopCustom && data.shopPercentage !== null) {
                markupInput.value = data.shopPercentage;
                resetBtn.classList.remove('hidden');
            } else {
                markupInput.value = '';
                resetBtn.classList.add('hidden');
            }

            updateModalPreview();
            modal.classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            currentModalData = null;
        }

        function updateModalPreview() {
            if (!currentModalData) return;
            const inputVal = parseFloat(document.getElementById('modalMarkupInput').value);
            const previewEl = document.getElementById('modalRetailPreview');
            const breakdownEl = document.getElementById('modalFormulaBreakdown');
            const supply = currentModalData.supplyPrice;

            if (isNaN(inputVal) || inputVal < 0) {
                previewEl.textContent = '₹0.00';
                breakdownEl.textContent = 'Enter a valid percentage (0% to 500%).';
                return;
            }

            if (supply !== null && supply > 0) {
                const retail = Math.round(supply * (1 + (inputVal / 100)) * 100) / 100;
                previewEl.textContent = `₹${retail.toFixed(2)}`;
                breakdownEl.textContent = `₹${parseFloat(supply).toFixed(2)} × (1 + ${inputVal}% / 100) = ₹${retail.toFixed(2)}`;
            } else {
                // Illustrative ₹100 basis
                const illustrativeRetail = Math.round(100 * (1 + (inputVal / 100)) * 100) / 100;
                previewEl.textContent = `₹${illustrativeRetail.toFixed(2)}*`;
                breakdownEl.textContent = `*Illustrative preview on ₹100 supply base (Actual supply price is not yet approved).`;
            }
        }

        function executeModalReset() {
            if (!currentModalData || !currentModalData.resetUrl) return;
            if (!confirm(`Reset ${currentModalData.productName} to general pricing for ${currentModalData.shopName}?`)) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = currentModalData.resetUrl;

            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);

            const method = document.createElement('input');
            method.type = 'hidden';
            method.name = '_method';
            method.value = 'DELETE';
            form.appendChild(method);

            document.body.appendChild(form);
            form.submit();
        }

        // --- Table Row Selection & Floating Bar ---
        function toggleSelectAll(master) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => cb.checked = master.checked);
            updateSelectionBar();
        }

        function getSelectedProductIds() {
            const checked = document.querySelectorAll('.row-checkbox:checked');
            return Array.from(checked).map(cb => parseInt(cb.value));
        }

        function updateSelectionBar() {
            const count = getSelectedProductIds().length;
            const bar = document.getElementById('selectionBar');
            const badge = document.getElementById('selectedCountBadge');

            badge.textContent = count;
            if (count > 0) {
                bar.classList.remove('translate-y-24', 'opacity-0', 'pointer-events-none');
            } else {
                bar.classList.add('translate-y-24', 'opacity-0', 'pointer-events-none');
                const master = document.getElementById('selectAllRows');
                if (master) master.checked = false;
            }
        }

        function clearRowSelections() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            updateSelectionBar();
        }

        // --- Area-Wise Bulk Pricing Modal ---
        function openAreaWiseModal() {
            const selectedIds = getSelectedProductIds();
            const msgEl = document.getElementById('bulkProductSelectionMsg');
            const hiddenContainer = document.getElementById('hiddenProductInputs');
            hiddenContainer.innerHTML = '';

            if (selectedIds.length > 0) {
                msgEl.innerHTML = `Applying to <strong>${selectedIds.length}</strong> product(s) selected from the table.`;
                selectedIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'product_ids[]';
                    input.value = id;
                    hiddenContainer.appendChild(input);
                });
            } else {
                // If none selected from table, default to all products on the current page
                const allPageCbs = document.querySelectorAll('.row-checkbox');
                const allPageIds = Array.from(allPageCbs).map(cb => parseInt(cb.value));
                msgEl.innerHTML = `No products checked. Defaulting to all <strong>${allPageIds.length}</strong> product(s) on this page.`;
                allPageIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'product_ids[]';
                    input.value = id;
                    hiddenContainer.appendChild(input);
                });
            }

            document.getElementById('bulkProductCountBadge').textContent = hiddenContainer.children.length;

            // Pre-check currently selected shop if available
            const selectedShopId = '{{ $selectedShopId }}';
            document.querySelectorAll('.bulk-shop-cb').forEach(cb => {
                if (selectedShopId && cb.value === selectedShopId) {
                    cb.checked = true;
                }
            });

            updateBulkPreview();
            document.getElementById('areaWiseModal').classList.remove('hidden');
        }

        function openAreaWiseModalWithSelected() {
            openAreaWiseModal();
        }

        function closeAreaWiseModal() {
            document.getElementById('areaWiseModal').classList.add('hidden');
        }

        function selectAllShops(checked) {
            document.querySelectorAll('.bulk-shop-cb').forEach(cb => cb.checked = checked);
            updateBulkPreview();
        }

        function updateBulkPreview() {
            const shopCbs = document.querySelectorAll('.bulk-shop-cb:checked');
            const shopCount = shopCbs.length;
            const productCount = document.querySelectorAll('#hiddenProductInputs input').length;
            const markup = parseFloat(document.getElementById('bulkMarkupInput').value);
            const submitBtn = document.getElementById('bulkSubmitBtn');
            const previewText = document.getElementById('bulkPreviewText');
            document.getElementById('bulkShopCountBadge').textContent = shopCount;

            const totalCombos = shopCount * productCount;

            if (shopCount === 0 || productCount === 0 || isNaN(markup) || markup < 0 || markup > 500) {
                submitBtn.disabled = true;
                if (shopCount === 0) {
                    previewText.textContent = 'Please select at least one shop.';
                } else if (isNaN(markup) || markup < 0 || markup > 500) {
                    previewText.textContent = 'Please enter a valid markup percentage between 0% and 500%.';
                }
                return;
            }

            if (totalCombos > 1000) {
                submitBtn.disabled = true;
                previewText.innerHTML = `<span class="text-rose-600 font-bold">Limit Exceeded: ${totalCombos} combinations selected. Maximum allowed is 1,000 per operation.</span>`;
                return;
            }

            submitBtn.disabled = false;
            previewText.innerHTML = `Applying <strong>${markup.toFixed(2)}%</strong> markup will create or update <strong>${totalCombos}</strong> overrides across <strong>${shopCount}</strong> shop(s) and <strong>${productCount}</strong> product(s).`;
        }

        // --- Bulk Reset for Selected Shop ---
        function confirmBulkResetCurrentShop() {
            const productIds = getSelectedProductIds();
            const shopId = '{{ $selectedShopId }}';
            const shopName = '{{ $selectedShop?->name }}';

            if (!shopId || productIds.length === 0) return;

            if (!confirm(`Are you sure you want to reset ${productIds.length} product(s) back to general pricing for ${shopName}?`)) {
                return;
            }

            const resetForm = document.getElementById('bulkResetForm');
            const container = document.getElementById('bulkResetInputs');
            container.innerHTML = '';

            const shopInput = document.createElement('input');
            shopInput.type = 'hidden';
            shopInput.name = 'shop_ids[]';
            shopInput.value = shopId;
            container.appendChild(shopInput);

            productIds.forEach(id => {
                const pInput = document.createElement('input');
                pInput.type = 'hidden';
                pInput.name = 'product_ids[]';
                pInput.value = id;
                container.appendChild(pInput);
            });

            resetForm.submit();
        }
    </script>
</x-layouts.admin>

