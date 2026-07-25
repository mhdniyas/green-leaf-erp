    @php
        $isWarehouseReceiverSortSheet = request()->routeIs('warehouse.receiver.sort-sheet.*');
        $sortSheetLayout = $isWarehouseReceiverSortSheet ? 'layouts.app' : 'layouts.admin';
        $sortSheetRouteBase = $isWarehouseReceiverSortSheet ? 'warehouse.receiver.sort-sheet' : 'sort-sheet';
        $sortSheetRoute = fn (string $name, array $params = []) => route($sortSheetRouteBase.'.'.$name, $params);
        $user = auth()->user();
        $canGenerate = $user->can('sort.sheet.generate');
        $canExport = $user->can('sort.sheet.export');
        $hasMatrix = isset($matrix) && count($matrix) > 0;
        $noOrders = session('noOrders', false) || (isset($noOrders) && $noOrders);
        $filters = $filters ?? [];
        $currentDate = $filters['date'] ?? date('Y-m-d');
        $currentShop = $filters['shopId'] ?? '';
        $currentCategory = $filters['categoryId'] ?? '';
        $currentPriceGroup = $filters['priceGroupId'] ?? '';
    @endphp

<x-dynamic-component :component="$sortSheetLayout" title="Sort Sheet">
    <x-slot:actions>
        @if($canExport && $hasMatrix)
        <a href="{{ $sortSheetRoute('export.excel', array_filter(['date' => $currentDate, 'shop_id' => $currentShop, 'category_id' => $currentCategory, 'price_group_id' => $currentPriceGroup])) }}"
           id="export-excel-btn"
           class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-emerald-700 transition-all shadow-md hover:shadow-lg">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Export Excel
        </a>
        <a href="{{ $sortSheetRoute('export.pdf', array_filter(['date' => $currentDate, 'shop_id' => $currentShop, 'category_id' => $currentCategory, 'price_group_id' => $currentPriceGroup])) }}"
           id="export-pdf-btn"
           target="_blank"
           class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-red-700 transition-all shadow-md hover:shadow-lg">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            Export PDF
        </a>
        <a href="{{ $sortSheetRoute('print', array_filter(['date' => $currentDate, 'shop_id' => $currentShop, 'category_id' => $currentCategory, 'price_group_id' => $currentPriceGroup])) }}"
           id="print-btn"
           target="_blank"
           class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
            </svg>
            Print
        </a>
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
                        Sort Sheet
                    </h1>
                    <p class="text-xs text-slate-500 mt-1 ml-[52px]">
                        Generate a product-wise sorting sheet from approved shop orders only.
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
                  class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">

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

                {{-- Product Category --}}
                <div>
                    <label for="filter-category" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                        Product Category
                    </label>
                    <select id="filter-category" name="category_id"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ $currentCategory == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Shop Price Group (shop category) --}}
                <div>
                    <label for="filter-price-group" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                        Shop Category
                    </label>
                    <select id="filter-price-group" name="price_group_id"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                        <option value="">All Shop Categories</option>
                        @foreach($priceGroups as $pg)
                            <option value="{{ $pg->id }}" {{ $currentPriceGroup == $pg->id ? 'selected' : '' }}>
                                {{ $pg->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Individual Shop --}}
                <div>
                    <label for="filter-shop" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                        Shop
                    </label>
                    <select id="filter-shop" name="shop_id"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                        <option value="">All Shops</option>
                        @foreach($shops as $shop)
                            <option value="{{ $shop->id }}" {{ $currentShop == $shop->id ? 'selected' : '' }}>
                                {{ $shop->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Buttons --}}
                <div class="flex gap-2">
                    @if($canGenerate)
                    <button type="submit" id="generate-btn"
                            class="flex-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 px-4 py-2.5 text-xs font-bold text-white transition-all shadow-md flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z" />
                        </svg>
                        Generate
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
            <p class="text-xs text-amber-600 mt-2">Only orders with status <span class="font-bold">Approved</span> are included in the Sort Sheet.</p>
        </div>
        @endif

        {{-- Sort Sheet Table --}}
        @if($hasMatrix)
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            {{-- Table Header --}}
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-black text-slate-900 tracking-tight">
                        Sort Sheet — {{ \Carbon\Carbon::parse($currentDate)->format('d M Y') }}
                    </h2>
                    <p class="text-[10px] text-slate-500 mt-0.5">
                        {{ count($matrix) }} products · {{ $filteredShops->count() }} shops · Approved quantities only
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @if($canExport)
                    <a href="{{ $sortSheetRoute('export.excel', array_filter(['date' => $currentDate, 'shop_id' => $currentShop, 'category_id' => $currentCategory, 'price_group_id' => $currentPriceGroup])) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-[10px] font-bold text-white hover:bg-emerald-700 transition-all shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Excel
                    </a>
                    <a href="{{ $sortSheetRoute('export.pdf', array_filter(['date' => $currentDate, 'shop_id' => $currentShop, 'category_id' => $currentCategory, 'price_group_id' => $currentPriceGroup])) }}"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-red-600 px-3.5 py-2 text-[10px] font-bold text-white hover:bg-red-700 transition-all shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        PDF
                    </a>
                    <a href="{{ $sortSheetRoute('print', array_filter(['date' => $currentDate, 'shop_id' => $currentShop, 'category_id' => $currentCategory, 'price_group_id' => $currentPriceGroup])) }}"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-[10px] font-bold text-slate-700 hover:bg-slate-50 transition-all shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
                        </svg>
                        Print
                    </a>
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
                Select a date and apply filters, then click <strong>Generate Sort Sheet</strong> to build the product-wise sorting matrix from approved shop orders.
            </p>
            @if(!$canGenerate)
            <p class="text-xs text-slate-400 mt-3 italic">Your role allows viewing and exporting. Ask your manager to generate the sheet first.</p>
            @endif
        </div>
        @endif
    </div>
</x-dynamic-component>
