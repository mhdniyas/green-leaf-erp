    @php
        $isWarehouseReceiverSortSheet = request()->routeIs('warehouse.receiver.sort-sheet.*');
        $isSegregation = ($surface ?? null) === 'segregation' || request()->routeIs('segregation.*');
        $sortSheetLayout = $isWarehouseReceiverSortSheet ? 'layouts.app' : 'layouts.admin';
        $sortSheetRouteBase = $isWarehouseReceiverSortSheet ? 'warehouse.receiver.sort-sheet' : ($isSegregation ? 'segregation' : 'sort-sheet');
        $sortSheetRoute = fn (string $name, array $params = []) => route($sortSheetRouteBase.'.'.$name, $params);
        $pageTitle = $isSegregation ? 'Selection' : 'Sort Sheet';
        $categoryFilterLabel = $isSegregation ? 'Ordered Categories' : 'Product Categories';
        $productFilterLabel = $isSegregation ? 'Ordered Products' : 'Products';
        $user = auth()->user();
        $canGenerate = $user->can('sort.sheet.generate');
        $canExport = $user->can('sort.sheet.export');
        $hasMatrix = isset($matrix) && count($matrix) > 0;
        $sortSheetShareUrl = $sortSheetShareUrl ?? null;
        $noOrders = session('noOrders', false) || (isset($noOrders) && $noOrders);
        $filters = $filters ?? [];
        $currentDate = $filters['date'] ?? date('Y-m-d');
        $currentShop = $filters['shopId'] ?? '';
        $currentCategoryIds = collect($filters['categoryIds'] ?? (isset($filters['categoryId']) && $filters['categoryId'] !== '' ? [$filters['categoryId']] : []))
            ->map(fn ($value) => (string) $value)
            ->all();
        $currentProductIds = collect($filters['productIds'] ?? [])
            ->map(fn ($value) => (string) $value)
            ->all();
        $currentPriceGroup = $filters['priceGroupId'] ?? '';
        $currentWarehouse = $filters['warehouseId'] ?? '';
        $filterParams = array_filter([
            'date' => $currentDate,
            'shop_id' => $currentShop,
            'price_group_id' => $currentPriceGroup,
            'warehouse_id' => $currentWarehouse,
        ]);
        if (! empty($currentCategoryIds)) {
            $filterParams['category_ids'] = $currentCategoryIds;
        }
        if (! empty($currentProductIds)) {
            $filterParams['product_ids'] = $currentProductIds;
        }
        $categoryPickerOptions = $categories->map(fn ($category) => [
            'id' => (string) $category->id,
            'name' => $category->name,
            'warehouse_ids' => $category->warehouses->pluck('id')->map(fn ($id) => (string) $id)->values(),
        ])->values();
        $productPickerOptions = $products->map(fn ($product) => [
            'id' => (string) $product->id,
            'sku' => (string) $product->sku,
            'name' => $product->name,
            'category_id' => (string) $product->category_id,
            'category_name' => $product->category?->name ?? optional($categories->firstWhere('id', $product->category_id))->name ?? 'Uncategorized',
        ])->values();
        $warehousePickerOptions = $warehouses->map(fn ($warehouse) => [
            'id' => (string) $warehouse->id,
            'name' => $warehouse->name,
            'code' => $warehouse->code,
            'category_ids' => $warehouse->categories->pluck('id')->map(fn ($id) => (string) $id)->values(),
        ])->values();
        $priceGroupPickerOptions = $priceGroups->map(fn ($group) => [
            'id' => (string) $group->id,
            'name' => $group->name,
        ])->values();
        $shopPickerOptions = $shops->map(fn ($shop) => [
            'id' => (string) $shop->id,
            'name' => $shop->name,
        ])->values();
    @endphp

<x-dynamic-component :component="$sortSheetLayout" :title="$pageTitle">
    <x-slot:actions>
        @if($hasMatrix)
            @if(!$isSegregation)
                @if($sortSheetShareUrl)
                <a href="{{ $sortSheetShareUrl }}"
                   target="_blank"
                   rel="noopener"
                   id="share-whatsapp-btn"
                   class="inline-flex items-center gap-2 rounded-xl bg-green-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-green-700 transition-all shadow-md hover:shadow-lg">
                    WhatsApp
                </a>
                @endif
                @if($canExport)
                <a href="{{ $sortSheetRoute('export.excel', $filterParams) }}"
                   id="export-excel-btn"
                   class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-emerald-700 transition-all shadow-md hover:shadow-lg">
                    Excel
                </a>
                <a href="{{ $sortSheetRoute('export.pdf', $filterParams) }}"
                   id="export-pdf-btn"
                   target="_blank"
                   class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-red-700 transition-all shadow-md hover:shadow-lg">
                    PDF
                </a>
                <a href="{{ $sortSheetRoute('print', $filterParams) }}"
                   id="print-btn"
                   target="_blank"
                   class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all shadow-sm">
                    Print
                </a>
                @endif
            @else
                @if($sortSheetShareUrl)
                <a href="{{ $sortSheetShareUrl }}"
                   target="_blank"
                   rel="noopener"
                   id="selection-share-whatsapp-btn"
                   class="inline-flex items-center gap-2 rounded-xl bg-green-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-green-700 transition-all shadow-md hover:shadow-lg">
                    WhatsApp
                </a>
                @endif
                @if($canExport)
                <a href="{{ $sortSheetRoute('export.excel', $filterParams) }}"
                   id="selection-export-excel-btn"
                   class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-emerald-700 transition-all shadow-md hover:shadow-lg">
                    Excel
                </a>
                <a href="{{ $sortSheetRoute('print', $filterParams) }}"
                   id="segregation-print-btn"
                   target="_blank"
                   class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 transition-all shadow-md hover:shadow-lg">
                    Print Selection
                </a>
                @endif
            @endif
        @endif
    </x-slot:actions>

    <div class="max-w-[1600px] mx-auto space-y-6">

        {{-- Page Header --}}
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0112 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M13.125 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M20.625 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5M12 14.625v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 14.625c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 1.5v-1.5m0 0c0-.621.504-1.125 1.125-1.125m0 0h7.5" />
                            </svg>
                        </div>
                        {{ $pageTitle }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-1 ml-[52px]">
                        Generate {{ strtolower($pageTitle) }} prints from approved shop orders only.
                    </p>
                </div>
                @if($hasMatrix)
                <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 rounded-2xl px-4 py-2.5">
                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                    <span class="text-xs font-bold text-emerald-800">{{ count($matrix) }} products · {{ isset($filteredShops) ? $filteredShops->count() : 0 }} shops</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Filters --}}
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
            <form method="GET" action="{{ $sortSheetRoute('generate') }}" id="sort-sheet-filter-form"
                  class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">

                {{-- Date --}}
                <div>
                    <label for="filter-date" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                        Date <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        id="filter-date"
                        name="date"
                        value="{{ $currentDate }}"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white"
                    >
                </div>

                {{-- Warehouse --}}
                <div class="relative" data-picker-root="warehouses">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                        Warehouse
                    </label>
                    <button type="button" id="warehouse-picker-trigger"
                            class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <span id="warehouse-picker-label">All Warehouses</span>
                        <span class="text-slate-400">▾</span>
                    </button>
                    <div id="warehouse-picker-panel" class="absolute left-0 right-0 top-full z-30 mt-2 hidden max-h-72 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-xl"></div>
                    <input type="hidden" name="warehouse_id" id="warehouse-hidden-input" value="{{ $currentWarehouse }}">
                </div>

                {{-- Product Categories --}}
                <div class="relative lg:col-span-2" data-picker-root="categories">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                        {{ $categoryFilterLabel }}
                    </label>
                    <button type="button"
                            id="category-picker-trigger"
                            class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <span id="category-picker-label">All Categories</span>
                        <span class="text-slate-400">▾</span>
                    </button>
                    <div id="category-picker-panel" class="absolute left-0 right-0 top-full z-30 mt-2 hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-xl">
                        <input type="search" id="category-picker-search" placeholder="Search categories"
                               class="h-9 w-full rounded-xl border border-slate-200 px-3 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <div class="mt-3 flex gap-2">
                            <button type="button" id="category-select-all" class="rounded-lg border border-slate-200 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-slate-700 hover:bg-slate-50">Select all</button>
                            <button type="button" id="category-clear" class="rounded-lg border border-slate-200 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-slate-700 hover:bg-slate-50">Clear</button>
                        </div>
                        <div id="category-picker-list" class="mt-3 max-h-64 space-y-1 overflow-y-auto"></div>
                    </div>
                    <div id="category-hidden-inputs"></div>
                </div>

                {{-- Products --}}
                <div class="relative lg:col-span-2" data-picker-root="products">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                        {{ $productFilterLabel }}
                    </label>
                    <button type="button"
                            id="product-picker-trigger"
                            class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <span id="product-picker-label">All Products</span>
                        <span class="text-slate-400">▾</span>
                    </button>
                    <div id="product-picker-panel" class="absolute left-0 right-0 top-full z-30 mt-2 hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-xl lg:w-[520px]">
                        <input type="search" id="product-picker-search" placeholder="Search item code or name"
                               class="h-9 w-full rounded-xl border border-slate-200 px-3 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <div class="mt-3 flex gap-2">
                            <button type="button" id="product-select-visible" class="rounded-lg border border-slate-200 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-slate-700 hover:bg-slate-50">Select visible</button>
                            <button type="button" id="product-clear" class="rounded-lg border border-slate-200 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-slate-700 hover:bg-slate-50">Clear products</button>
                        </div>
                        <div id="product-picker-list" class="mt-3 max-h-80 space-y-3 overflow-y-auto"></div>
                    </div>
                    <div id="product-hidden-inputs"></div>
                </div>

                {{-- Shop Price Group (shop category) --}}
                <div class="relative" data-picker-root="price-groups">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                        Shop Category
                    </label>
                    <button type="button" id="price-group-picker-trigger"
                            class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <span id="price-group-picker-label">All Shop Categories</span>
                        <span class="text-slate-400">▾</span>
                    </button>
                    <div id="price-group-picker-panel" class="absolute left-0 right-0 top-full z-30 mt-2 hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-xl"></div>
                    <input type="hidden" name="price_group_id" id="price-group-hidden-input" value="{{ $currentPriceGroup }}">
                </div>

                {{-- Individual Shop --}}
                <div class="relative" data-picker-root="shops">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                        Shop
                    </label>
                    <button type="button" id="shop-picker-trigger"
                            class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <span id="shop-picker-label">All Shops</span>
                        <span class="text-slate-400">▾</span>
                    </button>
                    <div id="shop-picker-panel" class="absolute left-0 right-0 top-full z-30 mt-2 hidden max-h-72 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-xl"></div>
                    <input type="hidden" name="shop_id" id="shop-hidden-input" value="{{ $currentShop }}">
                </div>

                {{-- Buttons --}}
                <div class="flex gap-2">
                    @if($canGenerate)
                    <button type="submit" id="generate-btn"
                            class="flex-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2.5 text-xs font-bold text-white transition-all shadow-md flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z" />
                        </svg>
                        {{ $isSegregation ? 'Generate Selection' : 'Generate' }}
                    </button>
                    @endif
                    @if($hasMatrix || $noOrders)
                    <a href="{{ $sortSheetRoute('index') }}"
                       class="rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all flex items-center justify-center">
                        Reset
                    </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- No Orders Message --}}
        @if($noOrders)
        <div class="bg-amber-50 border border-amber-200 rounded-3xl p-8 text-center shadow-sm">
            <div class="w-14 h-14 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
            <h3 class="text-base font-black text-amber-900">No Approved Shop Orders Found</h3>
            <p class="text-sm text-amber-700 mt-1">No approved shop orders exist for <strong>{{ \Carbon\Carbon::parse($currentDate)->format('d M Y') }}</strong> with the selected filters.</p>
            <p class="text-xs text-amber-600 mt-2">Only orders with status <span class="font-bold">Approved</span> are included.</p>
        </div>
        @endif

        {{-- Generated Table --}}
        @if($hasMatrix)
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            {{-- Table Header --}}
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-black text-slate-900 tracking-tight">
                        {{ $pageTitle }} — {{ \Carbon\Carbon::parse($currentDate)->format('d M Y') }}
                    </h2>
                    <p class="text-[10px] text-slate-500 mt-0.5">
                        {{ count($matrix) }} products · {{ $filteredShops->count() }} shops · Approved quantities only
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @if(!$isSegregation)
                        @if($sortSheetShareUrl)
                        <a href="{{ $sortSheetShareUrl }}"
                           target="_blank"
                           rel="noopener"
                           class="inline-flex items-center gap-1.5 rounded-xl bg-green-600 px-3.5 py-2 text-[10px] font-bold text-white hover:bg-green-700 transition-all shadow-sm">
                            WhatsApp
                        </a>
                        @endif
                        @if($canExport)
                        <a href="{{ $sortSheetRoute('export.excel', $filterParams) }}"
                           class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-[10px] font-bold text-white hover:bg-emerald-700 transition-all shadow-sm">
                            Excel
                        </a>
                        <a href="{{ $sortSheetRoute('export.pdf', $filterParams) }}"
                           target="_blank"
                           class="inline-flex items-center gap-1.5 rounded-xl bg-red-600 px-3.5 py-2 text-[10px] font-bold text-white hover:bg-red-700 transition-all shadow-sm">
                            PDF
                        </a>
                        <a href="{{ $sortSheetRoute('print', $filterParams) }}"
                           target="_blank"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-[10px] font-bold text-slate-700 hover:bg-slate-50 transition-all shadow-sm">
                            Print
                        </a>
                        @endif
                    @else
                        @if($sortSheetShareUrl)
                        <a href="{{ $sortSheetShareUrl }}"
                           target="_blank"
                           rel="noopener"
                           class="inline-flex items-center gap-1.5 rounded-xl bg-green-600 px-3.5 py-2 text-[10px] font-bold text-white hover:bg-green-700 transition-all shadow-sm">
                            WhatsApp
                        </a>
                        @endif
                        @if($canExport)
                        <a href="{{ $sortSheetRoute('export.excel', $filterParams) }}"
                           class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-[10px] font-bold text-white hover:bg-emerald-700 transition-all shadow-sm">
                            Excel
                        </a>
                        <a href="{{ $sortSheetRoute('print', $filterParams) }}"
                           target="_blank"
                           class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3.5 py-2 text-[10px] font-bold text-white hover:bg-slate-800 transition-all shadow-sm">
                            Print Selection
                        </a>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Scrollable Table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[600px]" id="sort-sheet-table">
                    <thead>
                        <tr class="bg-slate-800 text-white text-[10px] font-bold uppercase tracking-wider sticky top-0 z-10">
                            <th class="py-3 px-4 text-center w-12 border-r border-slate-700">SL</th>
                            <th class="py-3 px-4 min-w-[160px] border-r border-slate-700">Item</th>
                            @foreach($filteredShops as $shop)
                            <th class="py-2.5 px-3 text-center border-r border-slate-700 whitespace-nowrap min-w-[70px]">
                                <span class="block text-[10px] font-bold leading-tight">{{ $shop->name }}</span>
                                @if($shop->warehouse_tag)
                                <span class="mt-1 inline-block bg-emerald-500/20 text-emerald-200 text-[9px] font-black rounded px-1.5 py-0.5 tracking-widest leading-none">
                                    {{ $shop->warehouse_tag }}
                                </span>
                                @endif
                            </th>
                            @endforeach
                            <th class="py-3 px-3 text-center border-r border-slate-700 bg-slate-700 min-w-[70px]">Total</th>
                            <th class="py-3 px-3 text-center min-w-[50px]">Unit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        @php $sl = 1; @endphp
                        @foreach($matrix as $productId => $shopQtys)
                        @php
                            $meta = $productMeta[$productId];
                            $total = array_sum($shopQtys);
                            $isEven = $sl % 2 === 0;
                        @endphp
                        <tr class="{{ $isEven ? 'bg-slate-50/40' : 'bg-white' }} hover:bg-emerald-50/30 transition-colors group">
                            <td class="py-2.5 px-4 text-center text-slate-500 font-mono text-[10px] border-r border-slate-100">
                                {{ $sl++ }}
                            </td>
                            <td class="py-2.5 px-4 font-semibold text-slate-900 border-r border-slate-100">
                                {{ $meta['name'] }}
                                <span class="block text-[9px] text-slate-400 font-normal">{{ $meta['category_name'] }}</span>
                            </td>
                            @foreach($filteredShops as $shop)
                            @php $qty = $shopQtys[$shop->id] ?? 0; @endphp
                            <td class="py-2.5 px-3 text-center border-r border-slate-100 font-mono
                                       {{ $qty > 0 ? 'text-slate-900 font-bold' : 'text-slate-300' }}">
                                {{ $qty > 0 ? ($qty == intval($qty) ? intval($qty) : number_format($qty, 2)) : '—' }}
                            </td>
                            @endforeach
                            <td class="py-2.5 px-3 text-center border-r border-slate-100 font-black text-emerald-700 bg-emerald-50/50">
                                {{ $total == intval($total) ? intval($total) : number_format($total, 2) }}
                            </td>
                            <td class="py-2.5 px-3 text-center text-slate-500 text-[10px] font-semibold uppercase tracking-wide">
                                {{ $meta['unit'] }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    {{-- Totals Row --}}
                    <tfoot>
                        <tr class="bg-slate-800 text-white text-xs font-bold border-t-2 border-slate-600">
                            <td class="py-3 px-4 text-center border-r border-slate-700" colspan="2">
                                Grand Total ({{ count($matrix) }} items)
                            </td>
                            @foreach($filteredShops as $shop)
                            @php
                                $colTotal = collect($matrix)->sum(fn($shopQtys) => $shopQtys[$shop->id] ?? 0);
                            @endphp
                            <td class="py-3 px-3 text-center border-r border-slate-700 font-black font-mono">
                                {{ $colTotal > 0 ? ($colTotal == intval($colTotal) ? intval($colTotal) : number_format($colTotal, 2)) : '—' }}
                            </td>
                            @endforeach
                            @php $grandTotal = collect($matrix)->sum(fn($shopQtys) => array_sum($shopQtys)); @endphp
                            <td class="py-3 px-3 text-center border-r border-slate-700 font-black font-mono text-cyan-300">
                                {{ $grandTotal == intval($grandTotal) ? intval($grandTotal) : number_format($grandTotal, 2) }}
                            </td>
                            <td class="py-3 px-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @elseif(!$noOrders)
        {{-- Initial Empty State --}}
        <div class="bg-white rounded-3xl border border-dashed border-slate-200 p-16 text-center shadow-sm">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0112 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5" />
                </svg>
            </div>
            <h3 class="text-base font-black text-slate-900">Ready to Generate</h3>
            <p class="text-sm text-slate-500 mt-1 max-w-sm mx-auto">
                Select a date and apply filters, then click <strong>Generate</strong> to build the product-wise matrix from approved shop orders.
            </p>
            @if(!$canGenerate)
            <p class="text-xs text-slate-400 mt-3 italic">Your role allows viewing and exporting. Ask your manager to generate the sheet first.</p>
            @endif
        </div>
        @endif
    </div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const categories = @json($categoryPickerOptions);
    const products = @json($productPickerOptions);
    const warehouses = @json($warehousePickerOptions);
    const priceGroups = @json($priceGroupPickerOptions);
    const shops = @json($shopPickerOptions);
    const allCategoryLabel = @json($isSegregation ? 'All Ordered Categories' : 'All Categories');
    const allProductLabel = @json($isSegregation ? 'All Ordered Products' : 'All Products');

    const selectedCategoryIds = new Set(@json($currentCategoryIds));
    const selectedProductIds = new Set(@json($currentProductIds));
    let selectedWarehouseId = @json((string) $currentWarehouse);
    let selectedPriceGroupId = @json((string) $currentPriceGroup);
    let selectedShopId = @json((string) $currentShop);

    const categoryTrigger = document.getElementById('category-picker-trigger');
    const categoryPanel = document.getElementById('category-picker-panel');
    const categorySearch = document.getElementById('category-picker-search');
    const categoryList = document.getElementById('category-picker-list');
    const categoryLabel = document.getElementById('category-picker-label');
    const categoryInputs = document.getElementById('category-hidden-inputs');

    const productTrigger = document.getElementById('product-picker-trigger');
    const productPanel = document.getElementById('product-picker-panel');
    const productSearch = document.getElementById('product-picker-search');
    const productList = document.getElementById('product-picker-list');
    const productLabel = document.getElementById('product-picker-label');
    const productInputs = document.getElementById('product-hidden-inputs');

    const warehouseTrigger = document.getElementById('warehouse-picker-trigger');
    const warehousePanel = document.getElementById('warehouse-picker-panel');
    const warehouseLabel = document.getElementById('warehouse-picker-label');
    const warehouseInput = document.getElementById('warehouse-hidden-input');

    const priceGroupTrigger = document.getElementById('price-group-picker-trigger');
    const priceGroupPanel = document.getElementById('price-group-picker-panel');
    const priceGroupLabel = document.getElementById('price-group-picker-label');
    const priceGroupInput = document.getElementById('price-group-hidden-input');

    const shopTrigger = document.getElementById('shop-picker-trigger');
    const shopPanel = document.getElementById('shop-picker-panel');
    const shopLabel = document.getElementById('shop-picker-label');
    const shopInput = document.getElementById('shop-hidden-input');

    const form = document.getElementById('sort-sheet-filter-form');

    const closeOtherPanels = (openPanel) => {
        [categoryPanel, productPanel, warehousePanel, priceGroupPanel, shopPanel].forEach((panel) => {
            if (panel !== openPanel) {
                panel.classList.add('hidden');
            }
        });
    };

    const hiddenInput = (name, value) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        return input;
    };

    const syncHiddenInputs = () => {
        categoryInputs.replaceChildren(...Array.from(selectedCategoryIds).map((id) => hiddenInput('category_ids[]', id)));
        productInputs.replaceChildren(...Array.from(selectedProductIds).map((id) => hiddenInput('product_ids[]', id)));
        warehouseInput.value = selectedWarehouseId;
        priceGroupInput.value = selectedPriceGroupId;
        shopInput.value = selectedShopId;
    };

    const updateLabels = () => {
        categoryLabel.textContent = selectedCategoryIds.size === 0
            ? allCategoryLabel
            : `${selectedCategoryIds.size} categories selected`;
        productLabel.textContent = selectedProductIds.size === 0
            ? allProductLabel
            : `${selectedProductIds.size} products selected`;
        const warehouse = warehouses.find((item) => item.id === selectedWarehouseId);
        warehouseLabel.textContent = warehouse ? `${warehouse.name} (${warehouse.code})` : 'All Warehouses';
        priceGroupLabel.textContent = priceGroups.find((group) => group.id === selectedPriceGroupId)?.name || 'All Shop Categories';
        shopLabel.textContent = shops.find((shop) => shop.id === selectedShopId)?.name || 'All Shops';
    };

    const rowClasses = (selected) => [
        'flex', 'w-full', 'items-center', 'justify-between', 'gap-3', 'rounded-xl', 'border', 'px-3', 'py-2',
        'text-left', 'text-xs', 'font-semibold', 'transition',
        selected ? 'border-emerald-500' : 'border-slate-200',
        selected ? 'bg-emerald-50' : 'bg-white',
        selected ? 'text-emerald-900' : 'text-slate-700',
        'hover:bg-slate-50',
    ].join(' ');

    const checkMark = (selected) => {
        const mark = document.createElement('span');
        mark.className = [
            'flex', 'h-5', 'w-5', 'shrink-0', 'items-center', 'justify-center', 'rounded-md', 'border', 'text-[10px]', 'font-black',
            selected ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-300 bg-white text-white',
        ].join(' ');
        mark.textContent = selected ? '✓' : '';
        return mark;
    };

    const warehouseCategoryIds = () => {
        if (selectedWarehouseId === '') {
            return null;
        }

        return warehouses.find((warehouse) => warehouse.id === selectedWarehouseId)?.category_ids || [];
    };

    const availableCategories = () => {
        const warehouseLimitedCategoryIds = warehouseCategoryIds();
        if (warehouseLimitedCategoryIds === null) {
            return categories;
        }

        return categories.filter((category) => warehouseLimitedCategoryIds.includes(category.id));
    };

    const renderCategories = () => {
        const query = categorySearch.value.trim().toLowerCase();
        const rows = availableCategories()
            .filter((category) => category.name.toLowerCase().includes(query))
            .map((category) => {
                const selected = selectedCategoryIds.has(category.id);
                const button = document.createElement('button');
                button.type = 'button';
                button.className = rowClasses(selected);
                button.dataset.categoryId = category.id;
                const label = document.createElement('span');
                label.textContent = category.name;
                button.append(label, checkMark(selected));
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    if (selectedCategoryIds.has(category.id)) {
                        selectedCategoryIds.delete(category.id);
                    } else {
                        selectedCategoryIds.add(category.id);
                    }
                    renderAll();
                    categoryPanel.classList.remove('hidden');
                });
                return button;
            });

        if (rows.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'rounded-xl border border-dashed border-slate-200 p-4 text-center text-xs font-semibold text-slate-500';
            empty.textContent = 'No ordered categories match the current date and shop filters.';
            categoryList.replaceChildren(empty);
            return;
        }

        categoryList.replaceChildren(...rows);
    };

    const visibleProducts = () => {
        const warehouseLimitedCategoryIds = warehouseCategoryIds();
        const warehouseLimited = warehouseLimitedCategoryIds === null
            ? products
            : products.filter((product) => warehouseLimitedCategoryIds.includes(product.category_id));
        const categoryLimited = selectedCategoryIds.size === 0
            ? warehouseLimited
            : warehouseLimited.filter((product) => selectedCategoryIds.has(product.category_id));
        const query = productSearch.value.trim().toLowerCase();

        return categoryLimited.filter((product) => {
            const text = `${product.sku} ${product.name} ${product.category_name}`.toLowerCase();
            return text.includes(query);
        });
    };

    const pruneProductSelections = () => {
        const warehouseLimitedCategoryIds = warehouseCategoryIds();

        selectedProductIds.forEach((productId) => {
            const product = products.find((item) => item.id === productId);
            if (! product) {
                selectedProductIds.delete(productId);
                return;
            }
            if (warehouseLimitedCategoryIds !== null && ! warehouseLimitedCategoryIds.includes(product.category_id)) {
                selectedProductIds.delete(productId);
                return;
            }
            if (selectedCategoryIds.size > 0 && !selectedCategoryIds.has(product.category_id)) {
                selectedProductIds.delete(productId);
            }
        });
    };

    const pruneCategorySelections = () => {
        const warehouseLimitedCategoryIds = warehouseCategoryIds();
        if (warehouseLimitedCategoryIds === null) {
            return;
        }

        selectedCategoryIds.forEach((categoryId) => {
            if (! warehouseLimitedCategoryIds.includes(categoryId)) {
                selectedCategoryIds.delete(categoryId);
            }
        });
    };

    const renderProducts = () => {
        const grouped = new Map();
        visibleProducts().forEach((product) => {
            if (! grouped.has(product.category_name)) {
                grouped.set(product.category_name, []);
            }
            grouped.get(product.category_name).push(product);
        });

        const sections = [];
        grouped.forEach((groupProducts, categoryName) => {
            const section = document.createElement('section');
            const heading = document.createElement('div');
            heading.className = 'sticky top-0 z-10 bg-white py-1 text-[10px] font-black uppercase tracking-wider text-slate-500';
            heading.textContent = categoryName;
            const rows = document.createElement('div');
            rows.className = 'space-y-1';

            groupProducts.forEach((product) => {
                const selected = selectedProductIds.has(product.id);
                const button = document.createElement('button');
                button.type = 'button';
                button.className = rowClasses(selected);
                button.dataset.productId = product.id;
                const text = document.createElement('span');
                text.textContent = `${product.sku} · ${product.name}`;
                button.append(text, checkMark(selected));
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    if (selectedProductIds.has(product.id)) {
                        selectedProductIds.delete(product.id);
                    } else {
                        selectedProductIds.add(product.id);
                    }
                    renderAll();
                    productPanel.classList.remove('hidden');
                });
                rows.append(button);
            });

            section.append(heading, rows);
            sections.push(section);
        });

        if (sections.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'rounded-xl border border-dashed border-slate-200 p-4 text-center text-xs font-semibold text-slate-500';
            empty.textContent = 'No products match the current filters.';
            productList.replaceChildren(empty);
            return;
        }

        productList.replaceChildren(...sections);
    };

    const renderSinglePicker = (panel, items, selectedId, allLabel, onSelect) => {
        const allButton = document.createElement('button');
        allButton.type = 'button';
        allButton.className = rowClasses(selectedId === '');
        allButton.append(document.createTextNode(allLabel), checkMark(selectedId === ''));
        allButton.addEventListener('click', () => {
            onSelect('');
            panel.classList.add('hidden');
            renderAll();
        });

        const rows = items.map((item) => {
            const selected = item.id === selectedId;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = rowClasses(selected);
            button.append(document.createTextNode(item.name), checkMark(selected));
            button.addEventListener('click', () => {
                onSelect(item.id);
                panel.classList.add('hidden');
                renderAll();
            });
            return button;
        });

        panel.replaceChildren(allButton, ...rows);
    };

    const renderAll = () => {
        pruneCategorySelections();
        pruneProductSelections();
        renderCategories();
        renderProducts();
        renderSinglePicker(warehousePanel, warehouses.map((warehouse) => ({
            id: warehouse.id,
            name: `${warehouse.name} (${warehouse.code})`,
        })), selectedWarehouseId, 'All Warehouses', (id) => selectedWarehouseId = id);
        renderSinglePicker(priceGroupPanel, priceGroups, selectedPriceGroupId, 'All Shop Categories', (id) => selectedPriceGroupId = id);
        renderSinglePicker(shopPanel, shops, selectedShopId, 'All Shops', (id) => selectedShopId = id);
        updateLabels();
        syncHiddenInputs();
    };

    categoryTrigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        categoryPanel.classList.toggle('hidden');
        closeOtherPanels(categoryPanel);
        categorySearch.focus();
    });
    productTrigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        productPanel.classList.toggle('hidden');
        closeOtherPanels(productPanel);
        productSearch.focus();
    });
    warehouseTrigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        warehousePanel.classList.toggle('hidden');
        closeOtherPanels(warehousePanel);
    });
    priceGroupTrigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        priceGroupPanel.classList.toggle('hidden');
        closeOtherPanels(priceGroupPanel);
    });
    shopTrigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        shopPanel.classList.toggle('hidden');
        closeOtherPanels(shopPanel);
    });

    categoryPanel.addEventListener('click', (event) => event.stopPropagation());
    productPanel.addEventListener('click', (event) => event.stopPropagation());
    warehousePanel.addEventListener('click', (event) => event.stopPropagation());
    priceGroupPanel.addEventListener('click', (event) => event.stopPropagation());
    shopPanel.addEventListener('click', (event) => event.stopPropagation());

    document.getElementById('category-select-all').addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        availableCategories().forEach((category) => selectedCategoryIds.add(category.id));
        renderAll();
        categoryPanel.classList.remove('hidden');
    });
    document.getElementById('category-clear').addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        selectedCategoryIds.clear();
        renderAll();
        categoryPanel.classList.remove('hidden');
    });
    document.getElementById('product-select-visible').addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        visibleProducts().forEach((product) => selectedProductIds.add(product.id));
        renderAll();
        productPanel.classList.remove('hidden');
    });
    document.getElementById('product-clear').addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        selectedProductIds.clear();
        renderAll();
        productPanel.classList.remove('hidden');
    });

    categorySearch.addEventListener('input', renderCategories);
    productSearch.addEventListener('input', renderProducts);
    form.addEventListener('submit', syncHiddenInputs);

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-picker-root]')) {
            categoryPanel.classList.add('hidden');
            productPanel.classList.add('hidden');
            warehousePanel.classList.add('hidden');
            priceGroupPanel.classList.add('hidden');
            shopPanel.classList.add('hidden');
        }
    });

    renderAll();
});
</script>
@endpush

</x-dynamic-component>
