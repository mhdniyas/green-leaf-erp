@php
    $currentDate = $filters['date'] ?? date('Y-m-d');
    $currentShopIds = collect($filters['shop_ids'] ?? [])->map(fn($v) => (string)$v)->all();
    $currentCategoryIds = collect($filters['category_ids'] ?? [])->map(fn($v) => (string)$v)->all();
    $currentProductIds = collect($filters['product_ids'] ?? [])->map(fn($v) => (string)$v)->all();
    $currentWarehouse = $filters['warehouse_id'] ? (string)$filters['warehouse_id'] : '';
    $currentPriceGroup = $filters['price_group_id'] ? (string)$filters['price_group_id'] : '';
    $currentCodeSort = (bool)($filters['code_sort'] ?? true);
    $currentShowCode = (bool)($filters['show_code'] ?? true);
    $currentShowCategoryTitles = (bool)($filters['show_category_titles'] ?? true);
    $currentSkipEmptyShops = (bool)($filters['skip_empty_shops'] ?? true);
    $selectedGrades = (array)($filters['grades'] ?? ['A']);
    $hasMatrix = !empty($matrix);

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
    if (!empty($filters['selected_preset'])) {
        $printQueryParams['preset_id'] = $filters['selected_preset'];
    }
    $printQueryParams['grades_filter'] = '1';
    $printUrl = route('print.sort-sheet.print', $printQueryParams);
@endphp

<x-dynamic-component component="layouts.printing" title="Sort Sheet V1">
    <x-slot:actions>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Actions:</span>
            <a href="{{ $printUrl }}" target="_blank"
               class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition-all shadow-sm">
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
                </svg>
                <span>Print Sort Sheet</span>
            </a>
            <a href="{{ route('print.sort-sheet') }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all shadow-sm">
                Reset
            </a>
        </div>
    </x-slot:actions>

    <div class="max-w-[1600px] mx-auto space-y-6">

        {{-- Page Header Card --}}
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
            {{-- Printing V1 Sub-navigation Pills --}}
            <div class="flex items-center gap-2 mb-5 pb-5 border-b border-slate-100 flex-wrap">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 mr-2">Printing V1:</span>
                <a href="{{ route('print.sort-sheet', array_filter(['date' => $currentDate])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all bg-emerald-600 text-white shadow-sm">
                    Sort Sheet
                </a>
                <a href="{{ route('print.selection', array_filter(['date' => $currentDate])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all bg-slate-100 text-slate-700 hover:bg-slate-200">
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
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 9V4.5h10.5V9M6 17.25h12A2.25 2.25 0 0 0 20.25 15v-3A2.25 2.25 0 0 0 18 9.75H6A2.25 2.25 0 0 0 3.75 12v3A2.25 2.25 0 0 0 6 17.25Zm1.5 0v2.25h9V17.25" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Sort Sheet V1</h1>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-200">
                                Integrated Desk
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            Generate and preview shop-wise sorting matrices from approved orders with comprehensive on-page filters.
                        </p>
                    </div>
                </div>

                @if($hasMatrix)
                <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 rounded-2xl px-4 py-2.5">
                    <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
                    <div class="text-right">
                        <p class="text-xs font-black text-emerald-950">{{ count($matrix) }} products · {{ $filteredShops->count() }} shops</p>
                        <p class="text-[11px] font-bold text-emerald-700">{{ number_format($grandTotal, 2) }} kg total</p>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Filters Section (Shared V1 Component) --}}
        @include('print-v1.components.filters', [
            'formAction' => route('print.sort-sheet'),
            'resetUrl' => route('print.sort-sheet'),
            'submitLabel' => 'Apply Filters & Preview',
        ])

        {{-- Live Report Preview Area --}}
        @if($hasMatrix)
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            {{-- Preview Subheader --}}
            <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
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
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ $printUrl }}" target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
                        </svg>
                        <span>Print Document</span>
                    </a>
                </div>
            </div>

            {{-- Responsive Matrix Table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[700px]" id="v1-sort-sheet-table">
                    <thead>
                        <tr class="bg-slate-900 text-white text-[10px] font-bold uppercase tracking-wider sticky top-0 z-10">
                            @if($currentShowCode)
                            <th class="py-3 px-3 text-center w-16 border-r border-slate-800">SKU</th>
                            @endif
                            <th class="py-3 px-4 min-w-[180px] border-r border-slate-800">Item</th>
                            @foreach($filteredShops as $shop)
                            <th class="py-2.5 px-3 text-center border-r border-slate-800 whitespace-nowrap min-w-[70px]">
                                <span class="block text-[10px] font-bold leading-tight">{{ $shop->name }}</span>
                                @if($shop->warehouse_tag)
                                <span class="mt-0.5 inline-block bg-emerald-500/20 text-emerald-300 text-[8px] font-black rounded px-1.5 py-0.5 tracking-wider leading-none">
                                    {{ $shop->warehouse_tag }}
                                </span>
                                @endif
                            </th>
                            @endforeach
                            <th class="py-3 px-3 text-center border-r border-slate-800 bg-slate-800 min-w-[75px]">Total</th>
                            <th class="py-3 px-3 text-center min-w-[50px]">Unit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        @php
                            $rowIdx = 0;
                            $currentCategoryName = null;
                            $isMultipleGrades = count($selectedGrades) > 1;
                        @endphp
                        @foreach($matrix as $rowKey => $shopQtys)
                        @php
                            $meta = $productMeta[$rowKey];
                            $catName = $meta['category_name'] ?? 'General';
                            $rowTotal = array_sum($shopQtys);
                            $isEven = $rowIdx % 2 === 0;
                            $rowIdx++;
                        @endphp

                        {{-- Category Section Separator --}}
                        @if($currentShowCategoryTitles && $currentCategoryName !== $catName)
                        @php $currentCategoryName = $catName; @endphp
                        <tr class="bg-slate-800 border-y-2 border-slate-900">
                            <td colspan="{{ ($currentShowCode ? 3 : 2) + $filteredShops->count() }}"
                                class="py-2 px-4 bg-slate-900 text-white font-black text-xs tracking-wider uppercase">
                                <span class="inline-flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                                    </svg>
                                    <span>Category: {{ $catName }}</span>
                                </span>
                            </td>
                        </tr>
                        @endif

                        {{-- Product Data Row --}}
                        <tr class="{{ $isEven ? 'bg-slate-50/50' : 'bg-white' }} hover:bg-indigo-50/40 transition-colors">
                            @if($currentShowCode)
                            <td class="py-2.5 px-3 text-center text-slate-700 font-mono text-xs font-bold border-r border-slate-100 whitespace-nowrap">
                                {{ $meta['sku'] }}
                            </td>
                            @endif
                            <td class="py-2.5 px-4 font-semibold text-slate-900 border-r border-slate-100">
                                <span>{{ $meta['display_name'] }}</span>
                                @if($isMultipleGrades)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider ml-1.5
                                        {{ $meta['grade'] === 'B' ? 'bg-amber-100 text-amber-800 border border-amber-200' : ($meta['grade'] === 'C' ? 'bg-purple-100 text-purple-800 border border-purple-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200') }}">
                                        Grade {{ $meta['grade'] }}
                                    </span>
                                @endif
                                <span class="block text-[9px] text-slate-400 font-normal">{{ $meta['category_name'] }}</span>
                            </td>
                            @foreach($filteredShops as $shop)
                            @php $qty = $shopQtys[$shop->id] ?? 0.0; @endphp
                            <td class="py-2.5 px-3 text-center border-r border-slate-100 font-mono {{ $qty > 0 ? 'text-slate-900 font-bold' : 'text-slate-300' }}">
                                {{ $qty > 0 ? ($qty == intval($qty) ? intval($qty) : number_format($qty, 2)) : '—' }}
                            </td>
                            @endforeach
                            <td class="py-2.5 px-3 text-center border-r border-slate-100 font-black text-indigo-700 bg-indigo-50/40 font-mono">
                                {{ $rowTotal == intval($rowTotal) ? intval($rowTotal) : number_format($rowTotal, 2) }}
                            </td>
                            <td class="py-2.5 px-3 text-center text-slate-500 text-[10px] font-bold uppercase tracking-wide">
                                {{ $meta['unit'] }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    {{-- Grand Totals Footer --}}
                    <tfoot>
                        <tr class="bg-slate-900 text-white text-xs font-bold border-t-2 border-slate-700">
                            <td class="py-3 px-4 text-center border-r border-slate-800" colspan="{{ $currentShowCode ? 2 : 1 }}">
                                Grand Total ({{ count($matrix) }} rows)
                            </td>
                            @foreach($filteredShops as $shop)
                            @php $colTotal = $shopTotals[$shop->id] ?? 0.0; @endphp
                            <td class="py-3 px-3 text-center border-r border-slate-800 font-black font-mono">
                                {{ $colTotal > 0 ? ($colTotal == intval($colTotal) ? intval($colTotal) : number_format($colTotal, 2)) : '—' }}
                            </td>
                            @endforeach
                            <td class="py-3 px-3 text-center border-r border-slate-800 font-black font-mono text-cyan-300 bg-slate-800">
                                {{ $grandTotal == intval($grandTotal) ? intval($grandTotal) : number_format($grandTotal, 2) }}
                            </td>
                            <td class="py-3 px-3 text-center text-[10px] text-slate-400">KG</td>
                        </tr>
                    </tfoot>
                </table>
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
            <h3 class="text-base font-black text-slate-900">No Orders Found</h3>
            <p class="text-xs text-slate-500 max-w-md mx-auto">
                No approved shop orders match the selected date ({{ \Carbon\Carbon::parse($currentDate)->format('d M Y') }}) and filter criteria. Adjust the date or filters above and click Apply Filters.
            </p>
        </div>
        @endif

    </div>
</x-dynamic-component>
