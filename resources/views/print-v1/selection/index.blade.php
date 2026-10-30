@php
    $layout = 'layouts.printing';
    $title = 'Selection V1';
    $currentDate = $date ?? ($filters['date'] ?? date('Y-m-d'));
    $currentShowCode = (bool)($filters['show_code'] ?? true);
    $currentShowCategoryTitles = (bool)($filters['show_category_titles'] ?? true);
    $currentCodeSort = (bool)($filters['code_sort'] ?? true);
    $currentSkipEmptyShops = (bool)($filters['skip_empty_shops'] ?? true);
    $currentWarehouse = !empty($filters['warehouse_id']) ? (string)$filters['warehouse_id'] : '';
    $currentPriceGroup = !empty($filters['price_group_id']) ? (string)$filters['price_group_id'] : '';
    $selectedGrades = (array)($filters['grades'] ?? ['A']);
    $isMultipleGrades = count($selectedGrades) > 1;

    $currentShopIds = collect($filters['shop_ids'] ?? [])->map(fn($v) => (string)$v)->all();
    $currentCategoryIds = collect($filters['category_ids'] ?? [])->map(fn($v) => (string)$v)->all();
    $currentProductIds = collect($filters['product_ids'] ?? [])->map(fn($v) => (string)$v)->all();

    // Build print query URL with exact current filters
    $printQueryParams = array_filter([
        'date' => $currentDate,
        'warehouse_id' => $currentWarehouse ?: null,
        'price_group_id' => $currentPriceGroup ?: null,
        'code_sort' => $currentCodeSort ? '1' : '0',
        'sku_sort' => $currentCodeSort ? '1' : '0',
        'show_code' => $currentShowCode ? '1' : '0',
        'show_category_titles' => $currentShowCategoryTitles ? '1' : '0',
        'show_category_headers' => $currentShowCategoryTitles ? '1' : '0',
        'skip_empty_shops' => $currentSkipEmptyShops ? '1' : '0',
    ], fn ($v) => $v !== null && $v !== '');

    if (!empty($selectedGrades)) {
        $printQueryParams['grades'] = $selectedGrades;
    }
    if (!empty($currentCategoryIds)) {
        $printQueryParams['category_ids'] = $currentCategoryIds;
    }
    if (!empty($currentProductIds)) {
        $printQueryParams['product_ids'] = $currentProductIds;
    }
    if (!empty($currentShopIds)) {
        $printQueryParams['shop_ids'] = $currentShopIds;
    }
    $printQueryParams['grades_filter'] = '1';

    $printUrl = route('print.selection.print', $printQueryParams);
@endphp

<x-dynamic-component :component="$layout" :title="$title">
    <div class="max-w-[1600px] mx-auto space-y-6">

        {{-- Page Header Card with Sub-nav --}}
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
            {{-- Printing V1 Sub-navigation Pills --}}
            <div class="flex items-center gap-2 mb-5 pb-5 border-b border-slate-100 flex-wrap">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 mr-2">Printing V1:</span>
                <a href="{{ route('print.sort-sheet', array_filter(['date' => $currentDate])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all bg-slate-100 text-slate-700 hover:bg-slate-200">
                    Sort Sheet
                </a>
                <a href="{{ route('print.selection', array_filter(['date' => $currentDate])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all bg-emerald-600 text-white shadow-sm">
                    Selection
                </a>
                <a href="{{ route('print.segregation-grid', array_filter(['date' => $currentDate])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all bg-slate-100 text-slate-700 hover:bg-slate-200">
                    Segregation Grid
                </a>
                <a href="{{ route('print.shop-order', array_filter(['date' => $currentDate])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all bg-slate-100 text-slate-700 hover:bg-slate-200">
                    Shop Order
                </a>
            </div>

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center shrink-0 text-indigo-700">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15m-15 5.25h15m-15 5.25h15M8.25 4.5v15m7.5-15v15" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Selection V1</h1>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-200">
                                Integrated Desk
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            Generate and preview shop-wise selection packing lists from approved orders with comprehensive on-page filters.
                        </p>
                    </div>
                </div>

                @if($hasData)
                <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 rounded-2xl px-4 py-2.5">
                    <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
                    <div class="text-right">
                        <p class="text-xs font-black text-emerald-950">{{ $totalShopsWithOrders }} shops · {{ $totalItemsAllShops }} items</p>
                        <p class="text-[11px] font-bold text-emerald-700">{{ $formattedTotalQtyAllShops }} kg total</p>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Filters Section (Shared V1 Component) --}}
        @include('print-v1.components.filters', [
            'formAction' => route('print.selection'),
            'resetUrl' => route('print.selection'),
            'submitLabel' => 'Apply Filters & Preview Selection',
        ])

        {{-- Live Report Preview Area --}}
        @if($hasData)
        <div class="space-y-6">
            {{-- Preview Controls Subheader Card --}}
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="px-3 py-1.5 rounded-xl bg-slate-900 text-white font-black text-xs tracking-wider uppercase">
                        {{ $gradeHeading }}
                    </span>
                    <span class="text-xs font-bold text-slate-500">
                        Business Date: <span class="font-black text-slate-800">{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</span>
                    </span>
                    @if($selectedWarehouse)
                    <span class="text-xs font-bold text-slate-500">
                        &middot; Warehouse: <span class="font-black text-slate-800">{{ $selectedWarehouse->name }}</span>
                    </span>
                    @endif
                    <span class="text-xs font-bold text-slate-500">
                        &middot; <span class="font-black text-emerald-700">{{ $totalShopsWithOrders }} shops</span>
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ $printUrl }}" target="_blank"
                       id="print-selection-btn"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-5 py-2.5 text-xs font-black text-white hover:bg-slate-800 transition shadow-sm">
                        <svg class="h-4 w-4 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
                        </svg>
                        <span>Print Selection</span>
                    </a>
                </div>
            </div>

            {{-- Shop-by-Shop Selection Report Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="v1-selection-preview-grid">
                @foreach($shopSections as $section)
                @php
                    $shop = $section['shop'];
                    $items = $section['items'];
                    $currentCat = null;
                @endphp
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                    {{-- Shop Card Header --}}
                    <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            <h2 class="text-sm font-black tracking-tight text-white uppercase">{{ $shop->name }}</h2>
                            @if($shop->warehouse_tag)
                            <span class="font-mono text-[9px] font-black bg-emerald-500/20 text-emerald-300 px-2 py-0.5 rounded tracking-wider">
                                {{ $shop->warehouse_tag }}
                            </span>
                            @endif
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-mono font-black text-cyan-300">{{ $section['formatted_total_qty'] }} kg</span>
                            <span class="text-[10px] text-slate-400 block font-semibold">{{ $section['total_items'] }} items</span>
                        </div>
                    </div>

                    {{-- Items Table for Shop --}}
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-wider border-b border-slate-200">
                                    @if($currentShowCode)
                                    <th class="py-2 px-3 text-center w-16 border-r border-slate-200">Code</th>
                                    @endif
                                    <th class="py-2 px-3 border-r border-slate-200">Description</th>
                                    <th class="py-2 px-3 text-center border-r border-slate-200 w-24">Qty</th>
                                    <th class="py-2 px-2 text-center w-12">Unit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($items as $idx => $item)
                                    {{-- Category Heading Separator --}}
                                    @if($currentShowCategoryTitles && $currentCat !== $item['category_name'])
                                    @php $currentCat = $item['category_name']; @endphp
                                    <tr class="bg-slate-50 border-y border-slate-200">
                                        <td colspan="{{ ($currentShowCode ? 1 : 0) + 3 }}"
                                            class="py-1 px-3 text-[10px] font-black text-slate-600 uppercase tracking-wider bg-slate-100/70">
                                            Category: {{ $item['category_name'] }}
                                        </td>
                                    </tr>
                                    @endif

                                    <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/40' }} hover:bg-indigo-50/40 transition">
                                        @if($currentShowCode)
                                        <td class="py-2 px-3 text-center font-mono text-[11px] font-bold text-slate-600 border-r border-slate-100">
                                            {{ $item['code'] }}
                                        </td>
                                        @endif
                                        <td class="py-2 px-3 font-semibold text-slate-900 border-r border-slate-100">
                                            <span>{{ $item['name'] }}</span>
                                            @if($isMultipleGrades)
                                            <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] font-black uppercase ml-1
                                                {{ $item['grade'] === 'B' ? 'bg-amber-100 text-amber-800' : ($item['grade'] === 'C' ? 'bg-purple-100 text-purple-800' : 'bg-emerald-100 text-emerald-800') }}">
                                                Gr.{{ $item['grade'] }}
                                            </span>
                                            @endif
                                        </td>
                                        <td class="py-2 px-3 text-center font-mono font-black text-slate-900 border-r border-slate-100 bg-slate-50/50">
                                            {{ $item['formatted_qty'] }}
                                        </td>
                                        <td class="py-2 px-2 text-center text-[10px] text-slate-400 font-bold uppercase">
                                            {{ $item['unit'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="bg-slate-100/80 border-t border-slate-200 font-black text-[11px]">
                                    <td colspan="{{ $currentShowCode ? 2 : 1 }}" class="py-2 px-3 text-slate-700">
                                        Shop Total ({{ $section['total_items'] }} items)
                                    </td>
                                    <td class="py-2 px-3 text-center font-mono text-emerald-700">
                                        {{ $section['formatted_total_qty'] }}
                                    </td>
                                    <td class="py-2 px-2 text-center text-[9px] text-slate-400 uppercase">KG</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @else
        {{-- Empty State Card --}}
        <div class="bg-white rounded-3xl border border-dashed border-slate-200 p-16 text-center shadow-sm space-y-3">
            <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <h3 class="text-base font-black text-slate-900">No Approved Orders Found</h3>
            <p class="text-xs text-slate-500 max-w-md mx-auto">
                No approved shop orders match the selected date ({{ \Carbon\Carbon::parse($currentDate)->format('d M Y') }}) and filter criteria. Adjust the date or filters above and click Apply Filters & Preview Selection.
            </p>
        </div>
        @endif

    </div>
</x-dynamic-component>

