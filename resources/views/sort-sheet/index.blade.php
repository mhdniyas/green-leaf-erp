    @php
        $isWarehouseReceiverSortSheet = request()->routeIs('warehouse.receiver.sort-sheet.*');
        $surface = $surface ?? (match(true) {
            request()->routeIs('*.shop-wise-portrait*') => 'portrait',
            request()->routeIs('*.shop-wise-wide*') => 'wide',
            request()->routeIs('*.grid*') => 'grid',
            request()->routeIs('segregation.*') => 'segregation',
            default => 'sort-sheet',
        });
        $isSegregation = in_array($surface, ['segregation', 'portrait', 'wide', 'grid'], true) || request()->routeIs('segregation.*');
        $sortSheetLayout = $isWarehouseReceiverSortSheet ? 'layouts.app' : 'layouts.printing';
        $sortSheetRouteBase = $isWarehouseReceiverSortSheet ? 'warehouse.receiver.sort-sheet' : ($isSegregation ? 'segregation' : 'sort-sheet');
        $sortSheetRoute = fn (string $name, array $params = []) => route($sortSheetRouteBase.'.'.$name, $params);
        $segregationMatrixPrintRoute = fn (array $params = []) => $isSegregation
            ? $sortSheetRoute('matrix-print', $params)
            : $sortSheetRoute('segregation.matrix-print', $params);
        $segregationGridPrintRoute = fn (array $params = []) => $isSegregation
            ? $sortSheetRoute('grid-print', $params)
            : $sortSheetRoute('segregation.grid-print', $params);

        $pageTitle = match($surface) {
            'portrait' => 'Shop Wise Portrait',
            'wide' => 'Shop Wise Wide',
            'grid' => 'Segregate Grid',
            'segregation' => 'Selection',
            default => 'Sort Sheet',
        };
        $pageSubtitle = match($surface) {
            'portrait' => 'Generate shop-wise portrait sorting matrices from approved orders.',
            'wide' => 'Generate shop-wise wide/landscape sorting matrices from approved orders.',
            'grid' => 'Generate segregated grid card views from approved orders.',
            'segregation' => 'Generate selection prints from approved shop orders.',
            default => 'Generate sort sheet prints from approved shop orders only.',
        };
        $generateButtonLabel = match($surface) {
            'portrait' => 'Generate Shop Wise Portrait',
            'wide' => 'Generate Shop Wise Wide',
            'grid' => 'Generate Segregate Grid',
            'segregation' => 'Generate Selection',
            default => 'Generate Sort Sheet',
        };
        $formActionUrl = match($surface) {
            'portrait' => route('segregation.shop-wise-portrait.generate'),
            'wide' => route('segregation.shop-wise-wide.generate'),
            'grid' => route('segregation.grid.generate'),
            'segregation' => route('segregation.generate'),
            default => route('sort-sheet.generate'),
        };
        $resetUrl = match($surface) {
            'portrait' => route('segregation.shop-wise-portrait'),
            'wide' => route('segregation.shop-wise-wide'),
            'grid' => route('segregation.grid'),
            'segregation' => route('segregation.index'),
            default => route('sort-sheet.index'),
        };

        $categoryFilterLabel = $isSegregation ? 'Ordered Categories' : 'Product Categories';
        $productFilterLabel = $isSegregation ? 'Ordered Products' : 'Products';
        $user = auth()->user();
        $canGenerate = $user->can('sort.sheet.generate');
        $canExport = $user->can('sort.sheet.export');
        $hasMatrix = isset($matrix) && count($matrix) > 0;
        $sortSheetShareUrl = $sortSheetShareUrl ?? null;
        $noOrders = session('noOrders', false) || (isset($noOrders) && $noOrders);
        $filters = $filters ?? [];
        $currentDate = $filters['date'] ?? ($defaultDate ?? date('Y-m-d'));
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
            'separate_category_pages' => ! empty($filters['separateCategoryPages']) ? 1 : null,
        ]);
        if (! empty($currentCategoryIds)) {
            $filterParams['category_ids'] = $currentCategoryIds;
        }
        if (! empty($currentProductIds)) {
            $filterParams['product_ids'] = $currentProductIds;
        }
        if (! empty($filters['pageBreakCategoryIds'])) {
            $filterParams['page_break_category_ids'] = $filters['pageBreakCategoryIds'];
        }
        $shopWiseWidePrintParams = array_merge($filterParams, ['orientation' => 'landscape']);
        $shopWisePortraitPrintParams = array_merge($filterParams, ['orientation' => 'portrait']);
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
            <div class="flex flex-wrap items-center gap-3">
                {{-- Subsection: Export & Share --}}
                <div class="flex flex-wrap items-center gap-1.5 rounded-2xl border border-slate-200 bg-slate-50/90 p-1.5 shadow-sm">
                    <span class="px-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Export & Share</span>
                    @if($sortSheetShareUrl && in_array($surface, ['sort-sheet', 'segregation'], true))
                    <a href="{{ $sortSheetShareUrl }}"
                       target="_blank"
                       rel="noopener"
                       id="share-whatsapp-btn"
                       class="inline-flex items-center gap-2 rounded-xl bg-green-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-green-700 transition-all shadow-sm">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                            <path d="M11.998 2.166C6.525 2.166 2.09 6.6 2.09 12.073c0 1.742.455 3.378 1.25 4.793L2 22l5.292-1.387c1.36.74 2.912 1.162 4.566 1.162 5.472 0 9.908-4.433 9.908-9.905 0-5.474-4.436-9.704-9.768-9.704z"/>
                        </svg>
                        WhatsApp
                    </a>
                    @endif
                    @if($canExport)
                    <a href="{{ $sortSheetRoute('export.excel', $filterParams) }}"
                       id="export-excel-btn"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition-all shadow-sm">
                        Excel
                    </a>
                    @if($surface === 'sort-sheet')
                    <a href="{{ $sortSheetRoute('export.pdf', $filterParams) }}"
                       id="export-pdf-btn"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-red-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-red-700 transition-all shadow-sm">
                        PDF
                    </a>
                    @endif
                    @endif
                </div>

                {{-- Subsection: Print Action --}}
                @if($canExport)
                <div class="flex flex-wrap items-center gap-1.5 rounded-2xl border border-slate-200 bg-slate-50/90 p-1.5 shadow-sm">
                    <span class="px-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Print</span>
                    @if($surface === 'sort-sheet')
                    <a href="{{ $sortSheetRoute('print', $filterParams) }}"
                       id="print-sort-sheet-btn"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all shadow-sm">
                        Print Sort Sheet
                    </a>
                    @elseif($surface === 'segregation')
                    <a href="{{ $sortSheetRoute('print', $filterParams) }}"
                       id="print-selection-btn"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-bold text-white hover:bg-slate-800 transition-all shadow-sm">
                        Print Selection
                    </a>
                    @elseif($surface === 'portrait')
                    <a href="{{ $segregationMatrixPrintRoute($shopWisePortraitPrintParams) }}"
                       id="print-portrait-btn"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-cyan-700 px-3.5 py-2 text-xs font-bold text-white hover:bg-cyan-800 transition-all shadow-sm">
                        Print Portrait
                    </a>
                    @elseif($surface === 'wide')
                    <a href="{{ $segregationMatrixPrintRoute($shopWiseWidePrintParams) }}"
                       id="print-wide-btn"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-sky-700 px-3.5 py-2 text-xs font-bold text-white hover:bg-sky-800 transition-all shadow-sm">
                        Print Wide
                    </a>
                    @elseif($surface === 'grid')
                    <a href="{{ $segregationGridPrintRoute($filterParams) }}"
                       id="print-grid-btn"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-700 px-3.5 py-2 text-xs font-bold text-white hover:bg-indigo-800 transition-all shadow-sm">
                        Print Grid
                    </a>
                    @endif
                </div>
                @endif
            </div>
        @else
            <div class="flex flex-wrap items-center gap-1.5 rounded-2xl border border-slate-200 bg-slate-50/90 p-1.5 shadow-sm">
                <span class="px-2 text-[10px] font-black uppercase tracking-wider text-slate-400">Print Formats</span>
                <a href="{{ route('sort-sheet.index', array_filter(['date' => $currentDate])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all {{ $surface === 'sort-sheet' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-200/60' }}">
                    Sort Sheet
                </a>
                <a href="{{ route('segregation.index', array_filter(['date' => $currentDate])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all {{ $surface === 'segregation' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-200/60' }}">
                    Selection
                </a>
                <a href="{{ route('segregation.shop-wise-portrait', array_filter(['date' => $currentDate])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all {{ $surface === 'portrait' ? 'bg-cyan-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-200/60' }}">
                    Shop Wise Portrait
                </a>
                <a href="{{ route('segregation.shop-wise-wide', array_filter(['date' => $currentDate])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all {{ $surface === 'wide' ? 'bg-sky-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-200/60' }}">
                    Shop Wise Wide
                </a>
                <a href="{{ route('segregation.grid', array_filter(['date' => $currentDate])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all {{ $surface === 'grid' ? 'bg-indigo-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-200/60' }}">
                    Segregate Grid
                </a>
            </div>
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
                        {{ $pageSubtitle }}
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

        {{-- Filters & Presets --}}
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">

            {{-- Custom Order Presets Bar (1-Click Generator) --}}
            <div class="mb-4 pb-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-600 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                        </svg>
                        Daily Custom Presets:
                    </span>
                    @forelse($presets as $preset)
                        <div class="inline-flex items-center gap-1.5 rounded-xl bg-amber-50 border border-amber-200/80 px-3 py-1.5 text-xs font-bold text-amber-900 shadow-sm transition hover:bg-amber-100 group">
                            <button type="button" class="preset-apply-btn flex items-center gap-1.5 hover:text-amber-700" data-preset='@json($preset)'>
                                <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                </svg>
                                <span>{{ $preset->name }}</span>
                            </button>
                            <form method="POST" action="{{ route('sort-sheet.presets.destroy', $preset) }}" class="inline ml-1" onsubmit="return confirm('Delete preset \'{{ $preset->name }}\'?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-0.5 text-amber-400 hover:text-red-600 transition" title="Delete preset">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    @empty
                        <span class="text-xs text-slate-400 font-medium">No saved custom order presets yet.</span>
                    @endforelse

                    @if(isset($presetBatches) && $presetBatches->isNotEmpty())
                    <span class="text-[10px] font-black uppercase tracking-wider text-purple-600 flex items-center gap-1 ml-2 border-l border-slate-200 pl-2">
                        <svg class="w-3.5 h-3.5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
                        </svg>
                        Saved Batches:
                    </span>
                    @foreach($presetBatches as $batch)
                        <div class="inline-flex items-center gap-1.5 rounded-xl bg-purple-50 border border-purple-200 px-3 py-1.5 text-xs font-bold text-purple-900 shadow-sm transition hover:bg-purple-100 group">
                            <a href="{{ route('sort-sheet.presets.batch-print', ['batch_id' => $batch->uuid, 'date' => $currentDate]) }}" target="_blank"
                               class="flex items-center gap-1.5 hover:text-purple-700">
                                <svg class="w-3.5 h-3.5 text-purple-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
                                </svg>
                                <span>Batch: {{ $batch->name }}</span>
                            </a>
                            <form method="POST" action="{{ route('sort-sheet.presets.batches.destroy', $batch) }}" class="inline ml-1" onsubmit="return confirm('Delete batch \'{{ $batch->name }}\'?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-0.5 text-purple-400 hover:text-red-600 transition" title="Delete batch">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    @endforeach
                    @endif
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('sort-sheet.presets.index') }}"
                       class="px-3.5 py-1.5 rounded-xl border border-amber-300 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
                        </svg>
                        Batch Print Presets
                    </a>
                    <button type="button" id="open-save-preset-modal-btn"
                            class="px-3.5 py-1.5 rounded-xl border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-900 text-xs font-bold transition flex items-center gap-1.5 shrink-0 shadow-sm">
                        <svg class="w-3.5 h-3.5 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Save Selection as Custom Order
                    </button>
                </div>
            </div>

            <form method="GET" action="{{ $formActionUrl }}" id="sort-sheet-filter-form"
                  class="space-y-4">

                {{-- Row 1: Primary Date & Location Filters --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
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
                </div>

                {{-- Row 2: Product & Category Selection Filters --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-end pt-1">
                    {{-- Product Categories --}}
                    <div class="relative" data-picker-root="categories">
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
                        <div id="category-print-order-bar" class="mt-2 hidden flex flex-wrap items-center gap-1.5 text-[10px] font-bold text-slate-600"></div>
                    </div>

                    {{-- Products --}}
                    <div class="relative" data-picker-root="products">
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
                </div>

                {{-- Row 3: Custom Print Options & Action Buttons Bar --}}
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pt-3 border-t border-slate-100">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Print Format:</span>
                        <a href="{{ route('sort-sheet.index', array_filter(['date' => $currentDate])) }}"
                           class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all {{ $surface === 'sort-sheet' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                            Sort Sheet
                        </a>
                        <a href="{{ route('segregation.index', array_filter(['date' => $currentDate])) }}"
                           class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all {{ $surface === 'segregation' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                            Selection
                        </a>
                        <a href="{{ route('segregation.shop-wise-portrait', array_filter(['date' => $currentDate])) }}"
                           class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all {{ $surface === 'portrait' ? 'bg-cyan-700 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                            Shop Wise Portrait
                        </a>
                        <a href="{{ route('segregation.shop-wise-wide', array_filter(['date' => $currentDate])) }}"
                           class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all {{ $surface === 'wide' ? 'bg-sky-700 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                            Shop Wise Wide
                        </a>
                        <a href="{{ route('segregation.grid', array_filter(['date' => $currentDate])) }}"
                           class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all {{ $surface === 'grid' ? 'bg-indigo-700 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                            Segregate Grid
                        </a>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer bg-slate-50 border border-slate-200 px-3 py-2.5 rounded-xl hover:bg-slate-100 transition">
                            <input type="checkbox" name="separate_category_pages" value="1" id="separate-category-pages-checkbox"
                                   {{ !empty($filters['separateCategoryPages']) ? 'checked' : '' }}
                                   class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>Separate Category Pages (Print)</span>
                        </label>

                        @if($hasMatrix || $noOrders)
                        <a href="{{ $resetUrl }}"
                           class="min-h-11 px-5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all flex items-center justify-center">
                            Reset Filters
                        </a>
                        @endif
                        @if($canGenerate)
                        <button type="submit" id="generate-btn"
                                class="min-h-11 rounded-xl bg-emerald-600 hover:bg-emerald-700 px-6 text-xs font-bold text-white transition-all shadow-md flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z" />
                            </svg>
                            {{ $generateButtonLabel }}
                        </button>
                        @endif
                    </div>
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
                    @if($sortSheetShareUrl)
                    <a href="{{ $sortSheetShareUrl }}"
                       target="_blank"
                       rel="noopener"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-green-600 px-3.5 py-2 text-[10px] font-bold text-white hover:bg-green-700 transition-all shadow-sm">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                            <path d="M11.998 2.166C6.525 2.166 2.09 6.6 2.09 12.073c0 1.742.455 3.378 1.25 4.793L2 22l5.292-1.387c1.36.74 2.912 1.162 4.566 1.162 5.472 0 9.908-4.433 9.908-9.905 0-5.474-4.436-9.704-9.768-9.704z"/>
                        </svg>
                        WhatsApp
                    </a>
                    @endif
                    @if($canExport)
                    <a href="{{ $sortSheetRoute('export.excel', $filterParams) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-[10px] font-bold text-white hover:bg-emerald-700 transition-all shadow-sm">
                        Excel
                    </a>
                    @if($surface === 'sort-sheet')
                    <a href="{{ $sortSheetRoute('export.pdf', $filterParams) }}"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-red-600 px-3.5 py-2 text-[10px] font-bold text-white hover:bg-red-700 transition-all shadow-sm">
                        PDF
                    </a>
                    <a href="{{ $sortSheetRoute('print', $filterParams) }}"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-[10px] font-bold text-slate-700 hover:bg-slate-50 transition-all shadow-sm">
                        Print Sort Sheet
                    </a>
                    @elseif($surface === 'segregation')
                    <a href="{{ $sortSheetRoute('print', $filterParams) }}"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3.5 py-2 text-[10px] font-bold text-white hover:bg-slate-800 transition-all shadow-sm">
                        Print Selection
                    </a>
                    @elseif($surface === 'portrait')
                    <a href="{{ $segregationMatrixPrintRoute($shopWisePortraitPrintParams) }}"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-cyan-700 px-3.5 py-2 text-[10px] font-bold text-white hover:bg-cyan-800 transition-all shadow-sm">
                        Print Portrait
                    </a>
                    @elseif($surface === 'wide')
                    <a href="{{ $segregationMatrixPrintRoute($shopWiseWidePrintParams) }}"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-sky-700 px-3.5 py-2 text-[10px] font-bold text-white hover:bg-sky-800 transition-all shadow-sm">
                        Print Wide
                    </a>
                    @elseif($surface === 'grid')
                    <a href="{{ $segregationGridPrintRoute($filterParams) }}"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-700 px-3.5 py-2 text-[10px] font-bold text-white hover:bg-indigo-800 transition-all shadow-sm">
                        Print Grid
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
                            <th class="py-3 px-4 text-center w-16 border-r border-slate-700">Code</th>
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
                        @php
                            $rowIdx = 0;
                            $currentCategoryName = null;
                        @endphp
                        @foreach($matrix as $productId => $shopQtys)
                        @php
                            $meta = $productMeta[$productId];
                            $catName = $meta['category_name'] ?? 'General';
                            $total = array_sum($shopQtys);
                            $isEven = $rowIdx % 2 === 0;
                            $rowIdx++;
                        @endphp
                        @if($currentCategoryName !== $catName)
                        @php $currentCategoryName = $catName; @endphp
                        <tr class="bg-slate-800 border-y-2 border-slate-900">
                            <td colspan="{{ 4 + $filteredShops->count() }}" class="py-2.5 px-4 bg-slate-900 text-white font-black text-xs tracking-wider uppercase flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-emerald-400 shrink-0 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                                </svg>
                                <span>Category: {{ $catName }}</span>
                            </td>
                        </tr>
                        <tr class="bg-slate-200 text-slate-800 text-[10px] font-black uppercase tracking-wider border-b border-slate-300">
                            <th class="py-2 px-3 text-center border-r border-slate-300">SKU</th>
                            <th class="py-2 px-4 text-left border-r border-slate-300">Product Name</th>
                            @foreach($filteredShops as $shop)
                            <th class="py-2 px-2 text-center border-r border-slate-300">
                                <span class="block text-[10px] font-black">{{ $shop->name }}</span>
                                @if($shop->warehouse_tag)
                                <span class="block text-[8px] text-slate-500 font-semibold">{{ $shop->warehouse_tag }}</span>
                                @endif
                            </th>
                            @endforeach
                            <th class="py-2 px-3 text-center border-r border-slate-300 bg-emerald-100 text-emerald-900">Total</th>
                            <th class="py-2 px-3 text-center">Unit</th>
                        </tr>
                        @endif
                        <tr class="{{ $isEven ? 'bg-slate-50/40' : 'bg-white' }} hover:bg-emerald-50/30 transition-colors group">
                            <td class="py-2.5 px-3 text-center text-slate-700 font-mono text-xs font-bold border-r border-slate-100 whitespace-nowrap">
                                {{ $meta['sku'] }}
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
       {{-- Save Custom Order Preset Modal --}}
    <div id="save-preset-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                    </svg>
                    Save Custom Order Preset
                </h3>
                <button type="button" id="close-preset-modal-btn" class="p-1 text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form method="POST" action="{{ route('sort-sheet.presets.store') }}" id="save-preset-form" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Preset Name *</label>
                    <input type="text" name="name" placeholder="e.g. Daily Priority Vegetables" required
                           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>
                <input type="hidden" name="surface" value="{{ $surface }}">
                <input type="hidden" name="warehouse_id" id="preset-warehouse-id">
                <input type="hidden" name="price_group_id" id="preset-price-group-id">
                <input type="hidden" name="shop_id" id="preset-shop-id">
                <div id="preset-category-inputs"></div>
                <div id="preset-product-inputs"></div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" id="cancel-preset-modal-btn" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-xs font-bold text-white shadow-md">Save Custom Order</button>
                </div>
            </form>
        </div>
    </div>
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

    let selectedPageBreakCategoryIds = [];

    const syncHiddenInputs = () => {
        categoryInputs.replaceChildren(...Array.from(selectedCategoryIds).map((id) => hiddenInput('category_ids[]', id)));
        productInputs.replaceChildren(...Array.from(selectedProductIds).map((id) => hiddenInput('product_ids[]', id)));
        warehouseInput.value = selectedWarehouseId;
        priceGroupInput.value = selectedPriceGroupId;
        shopInput.value = selectedShopId;

        const existingPageBreakInputs = form.querySelectorAll('input[name="page_break_category_ids[]"]');
        existingPageBreakInputs.forEach((el) => el.remove());
        (selectedPageBreakCategoryIds || []).forEach((id) => {
            form.appendChild(hiddenInput('page_break_category_ids[]', id));
        });
    };

    const categoryPrintOrderBar = document.getElementById('category-print-order-bar');

    const updateLabels = () => {
        const selectedCatArray = Array.from(selectedCategoryIds);
        if (selectedCatArray.length === 0) {
            categoryLabel.textContent = allCategoryLabel;
            if (categoryPrintOrderBar) {
                categoryPrintOrderBar.classList.add('hidden');
                categoryPrintOrderBar.replaceChildren();
            }
        } else {
            const selectedCatNames = selectedCatArray
                .map((id) => categories.find((c) => c.id === id)?.name)
                .filter(Boolean);

            categoryLabel.textContent = `${selectedCatArray.length} categories selected`;

            if (categoryPrintOrderBar) {
                categoryPrintOrderBar.classList.remove('hidden');
                const titleSpan = document.createElement('span');
                titleSpan.className = 'font-black uppercase tracking-wider text-slate-400 text-[9px] mr-1';
                titleSpan.textContent = 'Print Order:';

                const pills = selectedCatNames.map((name, idx) => {
                    const pill = document.createElement('span');
                    pill.className = 'inline-flex items-center gap-1 rounded-lg bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-900 border border-emerald-200';
                    pill.innerHTML = `<span class="text-[9px] font-black text-emerald-600">#${idx + 1}</span> ${name}`;
                    return pill;
                });

                categoryPrintOrderBar.replaceChildren(titleSpan, ...pills);
            }
        }

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

    const checkMark = (selected, orderIdx = 0) => {
        const mark = document.createElement('span');
        if (selected && orderIdx > 0) {
            mark.className = 'flex min-w-8 h-5 px-1.5 shrink-0 items-center justify-center rounded-md border border-emerald-600 bg-emerald-600 text-white text-[10px] font-black gap-0.5';
            mark.textContent = `#${orderIdx} ✓`;
        } else {
            mark.className = 'flex h-5 w-5 shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white text-white text-[10px] font-black';
            mark.textContent = '';
        }
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
        const selectedCatArray = Array.from(selectedCategoryIds);

        const rows = availableCategories()
            .filter((category) => category.name.toLowerCase().includes(query))
            .map((category) => {
                const selected = selectedCategoryIds.has(category.id);
                const orderIdx = selected ? selectedCatArray.indexOf(category.id) + 1 : 0;
                const button = document.createElement('button');
                button.type = 'button';
                button.className = rowClasses(selected);
                button.dataset.categoryId = category.id;
                const label = document.createElement('span');
                label.textContent = category.name;
                button.append(label, checkMark(selected, orderIdx));
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

    // Apply Preset Listener (1-Click Generator)
    document.querySelectorAll('.preset-apply-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            try {
                const preset = JSON.parse(btn.dataset.preset);
                selectedWarehouseId = preset.warehouse_id ? String(preset.warehouse_id) : '';
                selectedPriceGroupId = preset.price_group_id ? String(preset.price_group_id) : '';
                selectedShopId = preset.shop_id ? String(preset.shop_id) : '';

                selectedCategoryIds.clear();
                (preset.category_ids || []).forEach((id) => selectedCategoryIds.add(id));

                selectedProductIds.clear();
                (preset.product_ids || []).forEach((id) => selectedProductIds.add(id));

                const sepCheck = document.getElementById('separate-category-pages-checkbox');
                if (sepCheck) {
                    sepCheck.checked = Boolean(preset.separate_category_pages);
                }

                selectedPageBreakCategoryIds = (preset.page_break_category_ids || []).map(String);

                renderAll();
                form.submit();
            } catch (err) {
                console.error('Error applying preset:', err);
            }
        });
    });

    // Save Preset Modal logic
    const openPresetModalBtn = document.getElementById('open-save-preset-modal-btn');
    const savePresetModal = document.getElementById('save-preset-modal');
    const closePresetModalBtn = document.getElementById('close-preset-modal-btn');
    const cancelPresetModalBtn = document.getElementById('cancel-preset-modal-btn');

    if (openPresetModalBtn && savePresetModal) {
        openPresetModalBtn.addEventListener('click', () => {
            document.getElementById('preset-warehouse-id').value = selectedWarehouseId;
            document.getElementById('preset-price-group-id').value = selectedPriceGroupId;
            document.getElementById('preset-shop-id').value = selectedShopId;

            const categoryInputsContainer = document.getElementById('preset-category-inputs');
            categoryInputsContainer.replaceChildren(
                ...Array.from(selectedCategoryIds).map((id) => hiddenInput('category_ids[]', id))
            );

            const productInputsContainer = document.getElementById('preset-product-inputs');
            productInputsContainer.replaceChildren(
                ...Array.from(selectedProductIds).map((id) => hiddenInput('product_ids[]', id))
            );

            savePresetModal.classList.remove('hidden');
        });

        [closePresetModalBtn, cancelPresetModalBtn].forEach((btn) => {
            if (btn) {
                btn.addEventListener('click', () => savePresetModal.classList.add('hidden'));
            }
        });
    }

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
