@props([
    'formAction' => route('print.sort-sheet'),
    'resetUrl' => route('print.sort-sheet'),
    'submitLabel' => 'Apply Filters & Preview',
])

@php
    $currentShopIds = collect($filters['shop_ids'] ?? [])->map(fn($v) => (string)$v)->all();
    $currentCategoryIds = collect($filters['category_ids'] ?? [])->map(fn($v) => (string)$v)->all();
    $currentProductIds = collect($filters['product_ids'] ?? [])->map(fn($v) => (string)$v)->all();
    $currentWarehouse = !empty($filters['warehouse_id']) ? (string)$filters['warehouse_id'] : '';
    $currentPriceGroup = !empty($filters['price_group_id']) ? (string)$filters['price_group_id'] : '';
    $currentCodeSort = (bool)($filters['code_sort'] ?? true);
    $currentShowCode = (bool)($filters['show_code'] ?? true);
    $currentShowCategoryTitles = (bool)($filters['show_category_titles'] ?? true);
    $currentSkipEmptyShops = (bool)($filters['skip_empty_shops'] ?? true);
    $selectedGrades = (array)($filters['grades'] ?? ['A']);
    $currentDate = $date ?? ($filters['date'] ?? date('Y-m-d'));
    $presets = $presets ?? collect();
    $selectedPreset = $filters['selected_preset'] ?? ($filters['preset_id'] ?? request('preset_id', request('preset', '')));
@endphp

{{-- Filters Section --}}
<div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
    {{-- Custom Order Presets Bar (1-Click Generator & Batch Print) --}}
    <div class="mb-4 pb-4 border-b border-slate-100 flex flex-col xl:flex-row xl:items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-[10px] font-black uppercase tracking-wider text-amber-600 flex items-center gap-1 shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                </svg>
                Daily Custom Presets:
            </span>

            @forelse($presets as $preset)
                @php
                    $isActivePreset = ((string)$selectedPreset === (string)$preset->uuid || (string)$selectedPreset === (string)$preset->id);
                @endphp
                <div class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold shadow-2xs transition group {{ $isActivePreset ? 'bg-amber-100 border-amber-400 text-amber-950 ring-2 ring-amber-400' : 'bg-amber-50 border-amber-200/80 text-amber-900 hover:bg-amber-100' }}">
                    <button type="button" class="preset-apply-btn flex items-center gap-1.5 hover:text-amber-700 text-left" data-preset='@json($preset)' title="Click to apply preset {{ $preset->name }}">
                        <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                        </svg>
                        <span>{{ $preset->name }}</span>
                    </button>
                    @if(auth()->check() && (int)$preset->user_id === (int)auth()->id())
                    <form method="POST" action="{{ route('sort-sheet.presets.destroy', $preset) }}" class="inline ml-1" onsubmit="return confirm('Delete preset \'{{ $preset->name }}\'?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-0.5 text-amber-400 hover:text-red-600 transition" title="Delete preset">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </form>
                    @endif
                </div>
            @empty
                <span class="text-xs text-slate-400 font-medium">No saved custom order presets yet.</span>
            @endforelse

            @if(isset($presetBatches) && $presetBatches->isNotEmpty())
            <span class="text-[10px] font-black uppercase tracking-wider text-purple-600 flex items-center gap-1 ml-2 border-l border-slate-200 pl-2 shrink-0">
                <svg class="w-3.5 h-3.5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
                </svg>
                Saved Batches:
            </span>
            @foreach($presetBatches as $batch)
                <div class="inline-flex items-center gap-1.5 rounded-xl bg-purple-50 border border-purple-200 px-3 py-1.5 text-xs font-bold text-purple-900 shadow-2xs transition hover:bg-purple-100 group">
                    <a href="{{ route('sort-sheet.presets.batch-print', ['batch_id' => $batch->uuid, 'date' => $currentDate]) }}" target="_blank"
                       class="flex items-center gap-1.5 hover:text-purple-700">
                        <svg class="w-3.5 h-3.5 text-purple-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
                        </svg>
                        <span>Batch: {{ $batch->name }}</span>
                    </a>
                </div>
            @endforeach
            @endif
        </div>

        <div class="flex items-center gap-2 shrink-0">
            {{-- Quick Preset Dropdown --}}
            <div class="relative inline-flex items-center">
                <select id="v1-preset-select" name="preset_id" form="v1-filter-form"
                        class="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 shadow-2xs">
                    <option value="">-- Select Preset --</option>
                    @foreach($presets as $preset)
                        <option value="{{ $preset->uuid }}"
                                {{ (string)$selectedPreset === (string)$preset->uuid || (string)$selectedPreset === (string)$preset->id ? 'selected' : '' }}
                                data-preset='@json($preset)'>
                            {{ $preset->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <a href="{{ route('sort-sheet.presets.index') }}" target="_blank"
               class="px-3.5 py-1.5 rounded-xl border border-amber-300 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231a1.125 1.125 0 01-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656" />
                </svg>
                <span>Batch Print Presets</span>
            </a>

            <a href="{{ route('sort-sheet.presets.index') }}" target="_blank"
               class="px-3.5 py-1.5 rounded-xl border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-900 text-xs font-bold transition flex items-center gap-1.5 shadow-2xs">
                <svg class="w-3.5 h-3.5 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                </svg>
                <span>Manage Presets</span>
            </a>
        </div>
    </div>

    {{-- Main Filter Form --}}
    <form method="GET" action="{{ $formAction }}" id="v1-filter-form" class="space-y-5">
        <input type="hidden" name="grades_filter" value="1">
        <input type="hidden" name="preset_id" id="v1-selected-preset-input" value="{{ $selectedPreset }}">

        {{-- Filter 4: Quality Grade Selection --}}
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3">
            <div class="flex flex-wrap items-center gap-6">
                <span class="text-xs font-black text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Quality Grade:
                </span>
                <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-bold text-slate-800 hover:text-emerald-700">
                    <input type="checkbox" name="grades[]" value="A" id="grade-filter-a"
                           {{ in_array('A', $selectedGrades, true) ? 'checked' : '' }}
                           class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <span>Grade A</span>
                </label>
                <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-bold text-slate-800 hover:text-amber-700">
                    <input type="checkbox" name="grades[]" value="B" id="grade-filter-b"
                           {{ in_array('B', $selectedGrades, true) ? 'checked' : '' }}
                           class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                    <span>Grade B</span>
                </label>
                <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-bold text-slate-800 hover:text-purple-700">
                    <input type="checkbox" name="grades[]" value="C" id="grade-filter-c"
                           {{ in_array('C', $selectedGrades, true) ? 'checked' : '' }}
                           class="rounded border-slate-300 text-purple-600 focus:ring-purple-500">
                    <span>Grade C</span>
                </label>
            </div>

            <div class="text-[11px] font-bold text-slate-400">
                Default: Grade A &middot; Strict separation (no quantity merging across grades)
            </div>
        </div>

        {{-- Row 1: Primary Date, Warehouse, Price Group, Shops --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
            {{-- Date --}}
            <div>
                <label for="filter-date" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                    Date <span class="text-red-500">*</span>
                </label>
                <input type="date" id="filter-date" name="date" value="{{ $currentDate }}"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-bold text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 bg-white shadow-2xs">
            </div>

            {{-- Warehouse --}}
            <div class="relative" data-v1-picker="warehouses">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Warehouse</label>
                <button type="button" id="warehouse-picker-trigger"
                        class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 focus:border-indigo-500 focus:outline-none">
                    <span id="warehouse-picker-label">All Warehouses</span>
                    <span class="text-slate-400">▾</span>
                </button>
                <div id="warehouse-picker-panel" class="absolute left-0 right-0 top-full z-40 mt-2 hidden max-h-72 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-xl"></div>
                <input type="hidden" name="warehouse_id" id="warehouse-hidden-input" value="{{ $currentWarehouse }}">
            </div>

            {{-- Shop Category / Price Group --}}
            <div class="relative" data-v1-picker="price-groups">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Shop Category</label>
                <button type="button" id="price-group-picker-trigger"
                        class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 focus:border-indigo-500 focus:outline-none">
                    <span id="price-group-picker-label">All Shop Categories</span>
                    <span class="text-slate-400">▾</span>
                </button>
                <div id="price-group-picker-panel" class="absolute left-0 right-0 top-full z-40 mt-2 hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-xl"></div>
                <input type="hidden" name="price_group_id" id="price-group-hidden-input" value="{{ $currentPriceGroup }}">
            </div>

            {{-- Filter 3: Shop Multi-Select Picker --}}
            <div class="relative" data-v1-picker="shops">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Shops (Filter 3)</label>
                <button type="button" id="shop-picker-trigger"
                        class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 focus:border-indigo-500 focus:outline-none">
                    <span id="shop-picker-label">All Shops</span>
                    <span class="text-slate-400">▾</span>
                </button>
                <div id="shop-picker-panel" class="absolute left-0 right-0 top-full z-40 mt-2 hidden max-h-80 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-3 shadow-xl">
                    <input type="search" id="shop-picker-search" placeholder="Search shops"
                           class="h-9 w-full rounded-xl border border-slate-200 px-3 text-xs font-semibold focus:border-indigo-500 focus:outline-none">
                    <div class="mt-2.5 flex gap-2">
                        <button type="button" id="shop-select-all" class="rounded-lg border border-slate-200 px-2.5 py-1 text-[10px] font-black uppercase text-slate-700 hover:bg-slate-50">Select all</button>
                        <button type="button" id="shop-clear" class="rounded-lg border border-slate-200 px-2.5 py-1 text-[10px] font-black uppercase text-slate-700 hover:bg-slate-50">Clear</button>
                    </div>
                    <div id="shop-picker-list" class="mt-3 max-h-56 space-y-1 overflow-y-auto"></div>
                </div>
                <div id="shop-hidden-inputs"></div>
            </div>
        </div>

        {{-- Row 2: Filter 1 (Category) & Filter 2 (Product) Selection --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-end pt-1">
            {{-- Filter 1: Product Categories --}}
            <div class="relative" data-v1-picker="categories">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Product Categories (Filter 1)</label>
                <button type="button" id="category-picker-trigger"
                        class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 focus:border-indigo-500 focus:outline-none">
                    <span id="category-picker-label">All Categories</span>
                    <span class="text-slate-400">▾</span>
                </button>
                <div id="category-picker-panel" class="absolute left-0 right-0 top-full z-40 mt-2 hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-xl">
                    <input type="search" id="category-picker-search" placeholder="Search categories"
                           class="h-9 w-full rounded-xl border border-slate-200 px-3 text-xs font-semibold focus:border-indigo-500 focus:outline-none">
                    <div class="mt-2.5 flex gap-2">
                        <button type="button" id="category-select-all" class="rounded-lg border border-slate-200 px-2.5 py-1 text-[10px] font-black uppercase text-slate-700 hover:bg-slate-50">Select all</button>
                        <button type="button" id="category-clear" class="rounded-lg border border-slate-200 px-2.5 py-1 text-[10px] font-black uppercase text-slate-700 hover:bg-slate-50">Clear</button>
                    </div>
                    <div id="category-picker-list" class="mt-3 max-h-64 space-y-1 overflow-y-auto"></div>
                </div>
                <div id="category-hidden-inputs"></div>
            </div>

            {{-- Filter 2: Products Search & Select --}}
            <div class="relative" data-v1-picker="products">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Products (Filter 2)</label>
                <button type="button" id="product-picker-trigger"
                        class="flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 focus:border-indigo-500 focus:outline-none">
                    <span id="product-picker-label">All Products</span>
                    <span class="text-slate-400">▾</span>
                </button>
                <div id="product-picker-panel" class="absolute left-0 right-0 top-full z-40 mt-2 hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-xl lg:w-[540px]">
                    <input type="search" id="product-picker-search" placeholder="Search SKU code or product name"
                           class="h-9 w-full rounded-xl border border-slate-200 px-3 text-xs font-semibold focus:border-indigo-500 focus:outline-none">
                    <div class="mt-2.5 flex gap-2">
                        <button type="button" id="product-select-visible" class="rounded-lg border border-slate-200 px-2.5 py-1 text-[10px] font-black uppercase text-slate-700 hover:bg-slate-50">Select visible</button>
                        <button type="button" id="product-clear" class="rounded-lg border border-slate-200 px-2.5 py-1 text-[10px] font-black uppercase text-slate-700 hover:bg-slate-50">Clear products</button>
                    </div>
                    <div id="product-picker-list" class="mt-3 max-h-72 space-y-1.5 overflow-y-auto"></div>
                </div>
                <div id="product-hidden-inputs"></div>
            </div>
        </div>

        {{-- Row 3: Filter 5 Options & Action Controls --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pt-4 border-t border-slate-100">
            <div class="flex flex-wrap items-center gap-3">
                {{-- Show / Hide Code --}}
                <div class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 px-2 py-1.5">
                    <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-bold text-slate-700 hover:bg-white">
                        <input type="radio" name="show_code" value="1" {{ $currentShowCode ? 'checked' : '' }}
                               class="border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span>Show Code</span>
                    </label>
                    <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-bold text-slate-700 hover:bg-white">
                        <input type="radio" name="show_code" value="0" {{ !$currentShowCode ? 'checked' : '' }}
                               class="border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span>Hide Code</span>
                    </label>
                </div>

                {{-- Code Sort --}}
                <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer bg-slate-50 border border-slate-200 px-3 py-2.5 rounded-xl hover:bg-slate-100 transition">
                    <input type="hidden" name="code_sort" value="0">
                    <input type="checkbox" name="code_sort" id="code-sort-checkbox" value="1" {{ $currentCodeSort ? 'checked' : '' }}
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <span>Code Sort</span>
                </label>

                {{-- Show Category Headers --}}
                <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer bg-slate-50 border border-slate-200 px-3 py-2.5 rounded-xl hover:bg-slate-100 transition">
                    <input type="hidden" name="show_category_titles" value="0">
                    <input type="checkbox" name="show_category_titles" id="show-category-headers-checkbox" value="1" {{ $currentShowCategoryTitles ? 'checked' : '' }}
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    <span>Show Category Headers</span>
                </label>

                {{-- Skip Empty Shops (Zero Waste) --}}
                <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer bg-slate-50 border border-slate-200 px-3 py-2.5 rounded-xl hover:bg-slate-100 transition">
                    <input type="hidden" name="skip_empty_shops" value="0">
                    <input type="checkbox" name="skip_empty_shops" value="1" {{ $currentSkipEmptyShops ? 'checked' : '' }}
                           class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <span>Skip Empty Shops</span>
                </label>
            </div>

            <div class="flex items-center gap-2.5 shrink-0">
                <a href="{{ $resetUrl }}"
                   class="min-h-11 px-4 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition-all flex items-center justify-center">
                    Reset Filters
                </a>
                <button type="submit"
                        class="min-h-11 inline-flex items-center gap-2 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black transition-all shadow-md">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                    </svg>
                    <span>{{ $submitLabel }}</span>
                </button>
            </div>
        </div>
    </form>
</div>

{{-- Interactive Multi-Select Pickers Script --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
    const categories = @json($categoryPickerOptions);
    const products = @json($productPickerOptions);
    const shops = @json($shopPickerOptions);
    const warehouses = @json($warehousePickerOptions);
    const priceGroups = @json($priceGroupPickerOptions);

    let selectedWarehouseId = '{{ $currentWarehouse }}';
    let selectedPriceGroupId = '{{ $currentPriceGroup }}';
    const selectedShopIds = new Set(@json($currentShopIds));
    const selectedCategoryIds = new Set(@json($currentCategoryIds));
    const selectedProductIds = new Set(@json($currentProductIds));

    // Elements
    const warehouseTrigger = document.getElementById('warehouse-picker-trigger');
    const warehouseLabel = document.getElementById('warehouse-picker-label');
    const warehousePanel = document.getElementById('warehouse-picker-panel');
    const warehouseInput = document.getElementById('warehouse-hidden-input');

    const priceGroupTrigger = document.getElementById('price-group-picker-trigger');
    const priceGroupLabel = document.getElementById('price-group-picker-label');
    const priceGroupPanel = document.getElementById('price-group-picker-panel');
    const priceGroupInput = document.getElementById('price-group-hidden-input');

    const shopTrigger = document.getElementById('shop-picker-trigger');
    const shopLabel = document.getElementById('shop-picker-label');
    const shopPanel = document.getElementById('shop-picker-panel');
    const shopSearch = document.getElementById('shop-picker-search');
    const shopList = document.getElementById('shop-picker-list');
    const shopSelectAll = document.getElementById('shop-select-all');
    const shopClear = document.getElementById('shop-clear');
    const shopHiddenInputs = document.getElementById('shop-hidden-inputs');

    const categoryTrigger = document.getElementById('category-picker-trigger');
    const categoryLabel = document.getElementById('category-picker-label');
    const categoryPanel = document.getElementById('category-picker-panel');
    const categorySearch = document.getElementById('category-picker-search');
    const categoryList = document.getElementById('category-picker-list');
    const categorySelectAll = document.getElementById('category-select-all');
    const categoryClear = document.getElementById('category-clear');
    const categoryHiddenInputs = document.getElementById('category-hidden-inputs');

    const productTrigger = document.getElementById('product-picker-trigger');
    const productLabel = document.getElementById('product-picker-label');
    const productPanel = document.getElementById('product-picker-panel');
    const productSearch = document.getElementById('product-picker-search');
    const productList = document.getElementById('product-picker-list');
    const productSelectVisible = document.getElementById('product-select-visible');
    const productClear = document.getElementById('product-clear');
    const productHiddenInputs = document.getElementById('product-hidden-inputs');

    const panels = [warehousePanel, priceGroupPanel, shopPanel, categoryPanel, productPanel];
    const closeAllPanels = () => panels.forEach(p => p && p.classList.add('hidden'));

    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-v1-picker]')) {
            closeAllPanels();
        }
    });

    const togglePanel = (panel) => {
        const isHidden = panel.classList.contains('hidden');
        closeAllPanels();
        if (isHidden) {
            panel.classList.remove('hidden');
        }
    };

    warehouseTrigger.addEventListener('click', (e) => { e.stopPropagation(); togglePanel(warehousePanel); });
    priceGroupTrigger.addEventListener('click', (e) => { e.stopPropagation(); togglePanel(priceGroupPanel); });
    shopTrigger.addEventListener('click', (e) => { e.stopPropagation(); togglePanel(shopPanel); });
    categoryTrigger.addEventListener('click', (e) => { e.stopPropagation(); togglePanel(categoryPanel); });
    productTrigger.addEventListener('click', (e) => { e.stopPropagation(); togglePanel(productPanel); });

    const syncLabels = () => {
        // Warehouse label
        const wh = warehouses.find(w => w.id === selectedWarehouseId);
        warehouseLabel.textContent = wh ? `${wh.name} (${wh.code})` : 'All Warehouses';
        warehouseInput.value = selectedWarehouseId;

        // Price group label
        const pg = priceGroups.find(p => p.id === selectedPriceGroupId);
        priceGroupLabel.textContent = pg ? pg.name : 'All Shop Categories';
        priceGroupInput.value = selectedPriceGroupId;

        // Shop label & inputs
        shopLabel.textContent = selectedShopIds.size === 0
            ? 'All Shops'
            : `${selectedShopIds.size} shop${selectedShopIds.size > 1 ? 's' : ''} selected`;
        shopHiddenInputs.innerHTML = '';
        selectedShopIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'shop_ids[]';
            input.value = id;
            shopHiddenInputs.appendChild(input);
        });

        // Category label & inputs
        categoryLabel.textContent = selectedCategoryIds.size === 0
            ? 'All Categories'
            : `${selectedCategoryIds.size} categor${selectedCategoryIds.size > 1 ? 'ies' : 'y'} selected`;
        categoryHiddenInputs.innerHTML = '';
        selectedCategoryIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'category_ids[]';
            input.value = id;
            categoryHiddenInputs.appendChild(input);
        });

        // Product label & inputs
        productLabel.textContent = selectedProductIds.size === 0
            ? 'All Products'
            : `${selectedProductIds.size} product${selectedProductIds.size > 1 ? 's' : ''} selected`;
        productHiddenInputs.innerHTML = '';
        selectedProductIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'product_ids[]';
            input.value = id;
            productHiddenInputs.appendChild(input);
        });
    };

    // Render warehouse panel
    const renderWarehouses = () => {
        warehousePanel.innerHTML = '';
        const allBtn = document.createElement('button');
        allBtn.type = 'button';
        allBtn.className = 'w-full text-left px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-50 transition ' +
            (selectedWarehouseId === '' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700');
        allBtn.textContent = 'All Warehouses';
        allBtn.onclick = () => {
            selectedWarehouseId = '';
            syncLabels();
            closeAllPanels();
            renderCategories();
            renderProducts();
        };
        warehousePanel.appendChild(allBtn);

        warehouses.forEach(wh => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-full text-left px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-50 transition ' +
                (selectedWarehouseId === wh.id ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700');
            btn.textContent = `${wh.name} (${wh.code})`;
            btn.onclick = () => {
                selectedWarehouseId = wh.id;
                syncLabels();
                closeAllPanels();
                renderCategories();
                renderProducts();
            };
            warehousePanel.appendChild(btn);
        });
    };

    // Render price groups panel
    const renderPriceGroups = () => {
        priceGroupPanel.innerHTML = '';
        const allBtn = document.createElement('button');
        allBtn.type = 'button';
        allBtn.className = 'w-full text-left px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-50 transition ' +
            (selectedPriceGroupId === '' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700');
        allBtn.textContent = 'All Shop Categories';
        allBtn.onclick = () => {
            selectedPriceGroupId = '';
            syncLabels();
            closeAllPanels();
        };
        priceGroupPanel.appendChild(allBtn);

        priceGroups.forEach(pg => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-full text-left px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-50 transition ' +
                (selectedPriceGroupId === pg.id ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700');
            btn.textContent = pg.name;
            btn.onclick = () => {
                selectedPriceGroupId = pg.id;
                syncLabels();
                closeAllPanels();
            };
            priceGroupPanel.appendChild(btn);
        });
    };

    // Render shop picker list
    const renderShops = () => {
        const query = (shopSearch.value || '').trim().toLowerCase();
        shopList.innerHTML = '';

        const filtered = shops.filter(s => {
            return s.name.toLowerCase().includes(query) || (s.warehouse_tag && s.warehouse_tag.toLowerCase().includes(query));
        });

        if (filtered.length === 0) {
            shopList.innerHTML = '<div class="p-3 text-center text-xs text-slate-400">No shops match search</div>';
            return;
        }

        filtered.forEach(shop => {
            const isChecked = selectedShopIds.has(shop.id);
            const label = document.createElement('label');
            label.className = 'flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-slate-50 cursor-pointer text-xs font-semibold text-slate-700';

            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = isChecked;
            cb.className = 'rounded border-slate-300 text-indigo-600 focus:ring-indigo-500';
            cb.onchange = (e) => {
                if (e.target.checked) {
                    selectedShopIds.add(shop.id);
                } else {
                    selectedShopIds.delete(shop.id);
                }
                syncLabels();
            };

            const nameSpan = document.createElement('span');
            nameSpan.className = 'truncate flex-1';
            nameSpan.textContent = shop.name;

            label.appendChild(cb);
            label.appendChild(nameSpan);

            if (shop.warehouse_tag) {
                const tag = document.createElement('span');
                tag.className = 'font-mono text-[9px] font-black bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded';
                tag.textContent = shop.warehouse_tag;
                label.appendChild(tag);
            }

            shopList.appendChild(label);
        });
    };

    shopSearch.addEventListener('input', renderShops);

    shopSelectAll.onclick = (e) => {
        e.stopPropagation();
        shops.forEach(s => selectedShopIds.add(s.id));
        syncLabels();
        renderShops();
    };

    shopClear.onclick = (e) => {
        e.stopPropagation();
        selectedShopIds.clear();
        syncLabels();
        renderShops();
    };

    // Filter categories by selected warehouse
    const getEligibleCategories = () => {
        if (!selectedWarehouseId) return categories;
        return categories.filter(c => c.warehouse_ids.length === 0 || c.warehouse_ids.includes(selectedWarehouseId));
    };

    const renderCategories = () => {
        const query = (categorySearch.value || '').trim().toLowerCase();
        categoryList.innerHTML = '';
        const eligible = getEligibleCategories();

        const filtered = eligible.filter(c => c.name.toLowerCase().includes(query));

        if (filtered.length === 0) {
            categoryList.innerHTML = '<div class="p-3 text-center text-xs text-slate-400">No categories match search</div>';
            return;
        }

        filtered.forEach(cat => {
            const isChecked = selectedCategoryIds.has(cat.id);
            const label = document.createElement('label');
            label.className = 'flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-slate-50 cursor-pointer text-xs font-semibold text-slate-700';

            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = isChecked;
            cb.className = 'rounded border-slate-300 text-indigo-600 focus:ring-indigo-500';
            cb.onchange = (e) => {
                if (e.target.checked) {
                    selectedCategoryIds.add(cat.id);
                } else {
                    selectedCategoryIds.delete(cat.id);
                }
                syncLabels();
                renderProducts();
            };

            const nameSpan = document.createElement('span');
            nameSpan.className = 'truncate flex-1';
            nameSpan.textContent = cat.name;

            label.appendChild(cb);
            label.appendChild(nameSpan);
            categoryList.appendChild(label);
        });
    };

    categorySearch.addEventListener('input', renderCategories);

    categorySelectAll.onclick = (e) => {
        e.stopPropagation();
        getEligibleCategories().forEach(c => selectedCategoryIds.add(c.id));
        syncLabels();
        renderCategories();
        renderProducts();
    };

    categoryClear.onclick = (e) => {
        e.stopPropagation();
        selectedCategoryIds.clear();
        syncLabels();
        renderCategories();
        renderProducts();
    };

    // Filter products by warehouse and selected categories
    const getEligibleProducts = () => {
        let prods = products;
        if (selectedWarehouseId) {
            prods = prods.filter(p => !p.default_warehouse_id || p.default_warehouse_id === selectedWarehouseId);
        }
        if (selectedCategoryIds.size > 0) {
            prods = prods.filter(p => selectedCategoryIds.has(p.category_id));
        }
        return prods;
    };

    const renderProducts = () => {
        const query = (productSearch.value || '').trim().toLowerCase();
        productList.innerHTML = '';
        const eligible = getEligibleProducts();

        const filtered = eligible.filter(p => {
            return p.name.toLowerCase().includes(query) || (p.sku && p.sku.toLowerCase().includes(query));
        });

        if (filtered.length === 0) {
            productList.innerHTML = '<div class="p-3 text-center text-xs text-slate-400">No products match search criteria</div>';
            return;
        }

        filtered.forEach(prod => {
            const isChecked = selectedProductIds.has(prod.id);
            const label = document.createElement('label');
            label.className = 'flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg hover:bg-slate-50 cursor-pointer text-xs font-semibold text-slate-700';

            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = isChecked;
            cb.className = 'rounded border-slate-300 text-indigo-600 focus:ring-indigo-500';
            cb.onchange = (e) => {
                if (e.target.checked) {
                    selectedProductIds.add(prod.id);
                } else {
                    selectedProductIds.delete(prod.id);
                }
                syncLabels();
            };

            const skuSpan = document.createElement('span');
            skuSpan.className = 'font-mono text-[10px] font-black text-slate-500 w-12 text-center bg-slate-100 rounded px-1 py-0.5';
            skuSpan.textContent = prod.sku || '—';

            const nameSpan = document.createElement('span');
            nameSpan.className = 'truncate flex-1 font-bold';
            nameSpan.textContent = prod.name;

            const catSpan = document.createElement('span');
            catSpan.className = 'text-[10px] text-slate-400';
            catSpan.textContent = prod.category_name;

            label.appendChild(cb);
            label.appendChild(skuSpan);
            label.appendChild(nameSpan);
            label.appendChild(catSpan);
            productList.appendChild(label);
        });
    };

    productSelectVisible.onclick = (e) => {
        e.stopPropagation();
        let candidateProducts = getEligibleProducts();
        const query = (productSearch.value || '').trim().toLowerCase();
        if (query) {
            candidateProducts = candidateProducts.filter(p => p.name.toLowerCase().includes(query) || (p.sku && p.sku.toLowerCase().includes(query)));
        }
        candidateProducts.forEach(p => selectedProductIds.add(p.id));
        syncLabels();
        renderProducts();
    };

    productClear.onclick = (e) => {
        e.stopPropagation();
        selectedProductIds.clear();
        syncLabels();
        renderProducts();
    };

    productSearch.addEventListener('input', renderProducts);

    const renderAll = () => {
        syncLabels();
        renderWarehouses();
        renderPriceGroups();
        renderShops();
        renderCategories();
        renderProducts();
    };

    // Preset 1-Click Pills & Dropdown Listener
    const presetSelect = document.getElementById('v1-preset-select');

    const applyPresetObject = (preset) => {
        try {
            selectedWarehouseId = preset.warehouse_id ? String(preset.warehouse_id) : '';
            selectedPriceGroupId = preset.price_group_id ? String(preset.price_group_id) : '';

            selectedShopIds.clear();
            if (preset.shop_id) {
                selectedShopIds.add(String(preset.shop_id));
            }

            selectedCategoryIds.clear();
            (preset.category_ids || []).forEach(id => selectedCategoryIds.add(String(id)));

            selectedProductIds.clear();
            (preset.product_ids || []).forEach(id => selectedProductIds.add(String(id)));

            if (presetSelect && preset.uuid) {
                presetSelect.value = preset.uuid;
            }

            const presetInput = document.getElementById('v1-selected-preset-input');
            if (presetInput) {
                presetInput.value = preset.uuid || preset.id || '';
            }

            renderAll();

            const form = document.getElementById('v1-filter-form');
            if (form) {
                form.submit();
            }
        } catch (err) {
            console.error('Error applying preset:', err);
        }
    };

    document.querySelectorAll('.preset-apply-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!btn.dataset.preset) return;
            try {
                const preset = JSON.parse(btn.dataset.preset);
                applyPresetObject(preset);
            } catch (err) {
                console.error('Error parsing preset from pill:', err);
            }
        });
    });

    if (presetSelect) {
        presetSelect.addEventListener('change', () => {
            const selectedOption = presetSelect.options[presetSelect.selectedIndex];
            if (!selectedOption || !selectedOption.dataset.preset) {
                const presetInput = document.getElementById('v1-selected-preset-input');
                if (presetInput) presetInput.value = '';
                const form = document.getElementById('v1-filter-form');
                if (form) form.submit();
                return;
            }
            try {
                const preset = JSON.parse(selectedOption.dataset.preset);
                applyPresetObject(preset);
            } catch (err) {
                console.error('Error applying preset from select:', err);
            }
        });
    }

    // Auto-refresh when date is changed
    const dateInput = document.getElementById('filter-date');
    if (dateInput) {
        dateInput.addEventListener('change', () => {
            const form = document.getElementById('v1-filter-form');
            if (form) {
                form.submit();
            }
        });
    }

    renderAll();
});
</script>

