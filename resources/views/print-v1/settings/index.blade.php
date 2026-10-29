@extends('print-v1.layouts.app')

@section('content')
    <div x-data="{
        activeTab: '{{ $activeTab ?? 'sort-sheet' }}',
        editingPreset: null,
        presetModalOpen: false,
        openPresetModal(preset = null) {
            this.editingPreset = preset;
            this.presetModalOpen = true;
        }
    }" class="space-y-6">

        {{-- Page Title --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Printing Settings & Presets</h1>
                <p class="text-sm text-slate-500">Configure default parameters and custom presets independently for each print format.</p>
            </div>
            <a href="{{ route('print.dashboard') }}" class="px-3 py-1.5 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-2xs">
                &larr; Back to Dashboard
            </a>
        </div>

        {{-- Navigation Tabs --}}
        <div class="border-b border-slate-200">
            <nav class="flex space-x-4 overflow-x-auto pb-px" aria-label="Tabs">
                <button @click="activeTab = 'sort-sheet'"
                        :class="activeTab === 'sort-sheet' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-bold text-sm transition">
                    1. Sort Print
                </button>
                <button @click="activeTab = 'selection'"
                        :class="activeTab === 'selection' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-bold text-sm transition">
                    2. Selection
                </button>
                <button @click="activeTab = 'segregation-grid'"
                        :class="activeTab === 'segregation-grid' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-bold text-sm transition">
                    3. Segregation Grid
                </button>
                <button @click="activeTab = 'shop-wise-portrait'"
                        :class="activeTab === 'shop-wise-portrait' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-bold text-sm transition">
                    4. Shop Wise Portrait
                </button>
                <button @click="activeTab = 'shops'"
                        :class="activeTab === 'shops' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-bold text-sm transition">
                    5. Shop Configuration
                </button>
                <button @click="activeTab = 'presets'"
                        :class="activeTab === 'presets' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-bold text-sm transition flex items-center gap-1.5">
                    Custom Presets
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">{{ $presets->count() }}</span>
                </button>
                <button @click="activeTab = 'batches'"
                        :class="activeTab === 'batches' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
                        class="whitespace-nowrap py-3 px-1 border-b-2 font-bold text-sm transition flex items-center gap-1.5">
                    Batch Print
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">{{ $batches->count() }}</span>
                </button>
            </nav>
        </div>

        {{-- TAB 1: Sort Print Settings --}}
        <div x-show="activeTab === 'sort-sheet'" x-cloak class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
            <form method="POST" action="{{ route('print.settings.section.update', 'sort-sheet') }}" class="space-y-6">
                @csrf
                @method('PUT')
                <div class="border-b border-slate-100 pb-4">
                    <h3 class="text-lg font-black text-slate-900">Sort Print Configuration</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Controls the default matrix generation for Sort Print.</p>
                </div>

                {{-- Grades Selection --}}
                <div class="space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Included Product Grades</label>
                    <div class="flex flex-wrap gap-3">
                        @foreach(['A', 'B', 'C', 'U', 'DMG'] as $g)
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-300 bg-white cursor-pointer hover:bg-slate-50 text-xs font-bold text-slate-800">
                                <input type="checkbox" name="grades[]" value="{{ $g }}"
                                       {{ in_array($g, (array) ($sortSettings['grades'] ?? ['A', 'B', 'C', 'U']), true) ? 'checked' : '' }}
                                       class="rounded text-indigo-600 focus:ring-indigo-500">
                                <span>Grade {{ $g === 'U' ? 'Mixed (U)' : ($g === 'DMG' ? 'Damage (DMG)' : $g) }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-slate-500">Grade D is permanently disabled and disallowed.</p>
                </div>

                {{-- Empty Shop Handling --}}
                <div class="space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Empty Shops Filter</label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="skip_empty_shops" value="0">
                        <input type="checkbox" name="skip_empty_shops" value="1"
                               {{ (bool) ($sortSettings['skip_empty_shops'] ?? true) ? 'checked' : '' }}
                               class="rounded text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-semibold text-slate-800">Automatically skip shops with zero eligible order quantities</span>
                    </label>
                    <p class="text-[11px] text-slate-500">Prevents rendering empty columns, giving all available horizontal space to active shops.</p>
                </div>

                {{-- Warehouse Scope --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Default Warehouse Scope</label>
                        <select name="warehouse_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All Warehouses</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ ($sortSettings['warehouse_id'] ?? null) == $wh->id ? 'selected' : '' }}>
                                    {{ $wh->name }} ({{ $wh->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Shop Price Group Scope</label>
                        <select name="price_group_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All Price Groups</option>
                            @foreach($priceGroups as $pg)
                                <option value="{{ $pg->id }}" {{ ($sortSettings['price_group_id'] ?? null) == $pg->id ? 'selected' : '' }}>
                                    {{ $pg->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Category & Product Scope --}}
                @include('print-v1.components.category-product-filter', [
                    'section' => 'sort-sheet',
                    'selectedCategoryIds' => array_map('intval', (array) ($sortSettings['category_ids'] ?? [])),
                    'selectedProductIds' => array_map('intval', (array) ($sortSettings['product_ids'] ?? [])),
                    'categories' => $categoryPickerOptions,
                    'products' => $productPickerOptions,
                ])

                {{-- Sorting & Titles --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Product Ordering</label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="code_sort" value="0">
                            <input type="checkbox" name="code_sort" value="1"
                                   {{ (bool) ($sortSettings['code_sort'] ?? true) ? 'checked' : '' }}
                                   class="rounded text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-semibold text-slate-800">Sort by Item SKU Code (Alphanumeric)</span>
                        </label>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Category Headers</label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="show_category_titles" value="0">
                            <input type="checkbox" name="show_category_titles" value="1"
                                   {{ (bool) ($sortSettings['show_category_titles'] ?? true) ? 'checked' : '' }}
                                   class="rounded text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-semibold text-slate-800">Render Category Group Titles</span>
                        </label>
                    </div>
                </div>

                {{-- Admin System Default Checkbox --}}
                @if(auth()->user()?->hasRole('admin') || auth()->user()?->isMainAdmin())
                    <div class="pt-2 border-t border-slate-100">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="save_as_system_default" value="0">
                            <input type="checkbox" name="save_as_system_default" value="1" class="rounded text-indigo-600">
                            <span class="text-xs font-bold text-indigo-700 uppercase tracking-wide">Save as System Default (All Users)</span>
                        </label>
                    </div>
                @endif

                <div class="pt-4 border-t border-slate-100 flex justify-end">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-sm hover:bg-indigo-700 shadow-sm transition">
                        Save Sort Print Settings
                    </button>
                </div>
            </form>
        </div>

        {{-- TAB 2: Selection Settings --}}
        <div x-show="activeTab === 'selection'" x-cloak class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
            <form method="POST" action="{{ route('print.settings.section.update', 'selection') }}" class="space-y-6">
                @csrf
                @method('PUT')
                <div class="border-b border-slate-100 pb-4">
                    <h3 class="text-lg font-black text-slate-900">Selection List Configuration</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Controls shop-wise item picking list pagination and formatting.</p>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Included Product Grades</label>
                    <div class="flex flex-wrap gap-3">
                        @foreach(['A', 'B', 'C', 'U', 'DMG'] as $g)
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-300 bg-white cursor-pointer hover:bg-slate-50 text-xs font-bold text-slate-800">
                                <input type="checkbox" name="grades[]" value="{{ $g }}"
                                       {{ in_array($g, (array) ($selectionSettings['grades'] ?? ['A', 'B', 'C', 'U']), true) ? 'checked' : '' }}
                                       class="rounded text-indigo-600 focus:ring-indigo-500">
                                <span>Grade {{ $g === 'U' ? 'Mixed (U)' : ($g === 'DMG' ? 'Damage (DMG)' : $g) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Items Per Page Chunk</label>
                        <input type="number" name="items_per_page" value="{{ $selectionSettings['items_per_page'] ?? 36 }}" min="10" max="100"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 focus:ring-indigo-500">
                        <p class="text-[11px] text-slate-500 mt-1">Default is 36 items per page chunk for A4 Portrait.</p>
                    </div>

                    <div class="flex items-center pt-5">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="skip_empty_shops" value="0">
                            <input type="checkbox" name="skip_empty_shops" value="1"
                                   {{ (bool) ($selectionSettings['skip_empty_shops'] ?? true) ? 'checked' : '' }}
                                   class="rounded text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-semibold text-slate-800">Auto-skip empty shops</span>
                        </label>
                    </div>
                </div>

                {{-- Category & Product Scope --}}
                @include('print-v1.components.category-product-filter', [
                    'section' => 'selection',
                    'selectedCategoryIds' => array_map('intval', (array) ($selectionSettings['category_ids'] ?? [])),
                    'selectedProductIds' => array_map('intval', (array) ($selectionSettings['product_ids'] ?? [])),
                    'categories' => $categoryPickerOptions,
                    'products' => $productPickerOptions,
                ])

                @if(auth()->user()?->hasRole('admin') || auth()->user()?->isMainAdmin())
                    <div class="pt-2 border-t border-slate-100">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="save_as_system_default" value="0">
                            <input type="checkbox" name="save_as_system_default" value="1" class="rounded text-indigo-600">
                            <span class="text-xs font-bold text-indigo-700 uppercase tracking-wide">Save as System Default (All Users)</span>
                        </label>
                    </div>
                @endif

                <div class="pt-4 border-t border-slate-100 flex justify-end">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-sm hover:bg-indigo-700 shadow-sm transition">
                        Save Selection Settings
                    </button>
                </div>
            </form>
        </div>

        {{-- TAB 3: Segregation Grid Settings --}}
        <div x-show="activeTab === 'segregation-grid'" x-cloak class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
            <form method="POST" action="{{ route('print.settings.section.update', 'segregation-grid') }}" class="space-y-6">
                @csrf
                @method('PUT')
                <div class="border-b border-slate-100 pb-4">
                    <h3 class="text-lg font-black text-slate-900">Segregation Grid Configuration</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Controls card grid items per page and grade filtering.</p>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Included Product Grades</label>
                    <div class="flex flex-wrap gap-3">
                        @foreach(['A', 'B', 'C', 'U', 'DMG'] as $g)
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-300 bg-white cursor-pointer hover:bg-slate-50 text-xs font-bold text-slate-800">
                                <input type="checkbox" name="grades[]" value="{{ $g }}"
                                       {{ in_array($g, (array) ($gridSettings['grades'] ?? ['A', 'B', 'C', 'U']), true) ? 'checked' : '' }}
                                       class="rounded text-indigo-600 focus:ring-indigo-500">
                                <span>Grade {{ $g === 'U' ? 'Mixed (U)' : ($g === 'DMG' ? 'Damage (DMG)' : $g) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Items Per Grid Page</label>
                        <input type="number" name="items_per_page" value="{{ $gridSettings['items_per_page'] ?? 9 }}" min="4" max="20"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 focus:ring-indigo-500">
                        <p class="text-[11px] text-slate-500 mt-1">Default is 9 items per page for A4 Landscape.</p>
                    </div>

                    <div class="flex items-center pt-5">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="skip_empty_shops" value="0">
                            <input type="checkbox" name="skip_empty_shops" value="1"
                                   {{ (bool) ($gridSettings['skip_empty_shops'] ?? true) ? 'checked' : '' }}
                                   class="rounded text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-semibold text-slate-800">Auto-skip empty shops</span>
                        </label>
                    </div>
                </div>

                {{-- Category & Product Scope --}}
                @include('print-v1.components.category-product-filter', [
                    'section' => 'segregation-grid',
                    'selectedCategoryIds' => array_map('intval', (array) ($gridSettings['category_ids'] ?? [])),
                    'selectedProductIds' => array_map('intval', (array) ($gridSettings['product_ids'] ?? [])),
                    'categories' => $categoryPickerOptions,
                    'products' => $productPickerOptions,
                ])

                @if(auth()->user()?->hasRole('admin') || auth()->user()?->isMainAdmin())
                    <div class="pt-2 border-t border-slate-100">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="save_as_system_default" value="0">
                            <input type="checkbox" name="save_as_system_default" value="1" class="rounded text-indigo-600">
                            <span class="text-xs font-bold text-indigo-700 uppercase tracking-wide">Save as System Default (All Users)</span>
                        </label>
                    </div>
                @endif

                <div class="pt-4 border-t border-slate-100 flex justify-end">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-sm hover:bg-indigo-700 shadow-sm transition">
                        Save Segregation Grid Settings
                    </button>
                </div>
            </form>
        </div>

        {{-- TAB 4: Shop Wise Portrait Settings --}}
        <div x-show="activeTab === 'shop-wise-portrait'" x-cloak class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
            <form method="POST" action="{{ route('print.settings.section.update', 'shop-wise-portrait') }}" class="space-y-6">
                @csrf
                @method('PUT')
                <div class="border-b border-slate-100 pb-4">
                    <h3 class="text-lg font-black text-slate-900">Shop Wise Portrait Configuration</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Controls orientation and grade details for shop-wise printouts.</p>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Included Product Grades</label>
                    <div class="flex flex-wrap gap-3">
                        @foreach(['A', 'B', 'C', 'U', 'DMG'] as $g)
                            <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border border-slate-300 bg-white cursor-pointer hover:bg-slate-50 text-xs font-bold text-slate-800">
                                <input type="checkbox" name="grades[]" value="{{ $g }}"
                                       {{ in_array($g, (array) ($portraitSettings['grades'] ?? ['A', 'B', 'C', 'U']), true) ? 'checked' : '' }}
                                       class="rounded text-indigo-600 focus:ring-indigo-500">
                                <span>Grade {{ $g === 'U' ? 'Mixed (U)' : ($g === 'DMG' ? 'Damage (DMG)' : $g) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Page Orientation</label>
                        <select name="orientation" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 focus:ring-indigo-500">
                            <option value="portrait" {{ ($portraitSettings['orientation'] ?? 'portrait') === 'portrait' ? 'selected' : '' }}>Portrait (A4)</option>
                            <option value="landscape" {{ ($portraitSettings['orientation'] ?? 'portrait') === 'landscape' ? 'selected' : '' }}>Landscape (A4)</option>
                        </select>
                    </div>

                    <div class="flex items-center pt-5">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="skip_empty_shops" value="0">
                            <input type="checkbox" name="skip_empty_shops" value="1"
                                   {{ (bool) ($portraitSettings['skip_empty_shops'] ?? true) ? 'checked' : '' }}
                                   class="rounded text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-semibold text-slate-800">Auto-skip empty shops</span>
                        </label>
                    </div>
                </div>

                {{-- Category & Product Scope --}}
                @include('print-v1.components.category-product-filter', [
                    'section' => 'shop-wise-portrait',
                    'selectedCategoryIds' => array_map('intval', (array) ($portraitSettings['category_ids'] ?? [])),
                    'selectedProductIds' => array_map('intval', (array) ($portraitSettings['product_ids'] ?? [])),
                    'categories' => $categoryPickerOptions,
                    'products' => $productPickerOptions,
                ])

                @if(auth()->user()?->hasRole('admin') || auth()->user()?->isMainAdmin())
                    <div class="pt-2 border-t border-slate-100">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="save_as_system_default" value="0">
                            <input type="checkbox" name="save_as_system_default" value="1" class="rounded text-indigo-600">
                            <span class="text-xs font-bold text-indigo-700 uppercase tracking-wide">Save as System Default (All Users)</span>
                        </label>
                    </div>
                @endif

                <div class="pt-4 border-t border-slate-100 flex justify-end">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-sm hover:bg-indigo-700 shadow-sm transition">
                        Save Portrait Settings
                    </button>
                </div>
            </form>
        </div>

        {{-- TAB 5: Shop Configuration --}}
        <div x-show="activeTab === 'shops'" x-cloak class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs">
            <form method="POST" action="{{ route('print.settings.shops.update') }}" class="space-y-6">
                @csrf
                @method('PUT')
                <div class="border-b border-slate-100 pb-4">
                    <h3 class="text-lg font-black text-slate-900">Shop Printing Configuration</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Configure global shop inclusion/exclusion rules across all printing sections without affecting operational shop status.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Whitelist Inactive Shops --}}
                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Deliberately Include Inactive Shops
                        </label>
                        <p class="text-xs text-slate-500">Selected inactive shops will be checked for orders and printed without changing their operational status.</p>
                        <div class="max-h-60 overflow-y-auto border border-slate-200 rounded-xl p-3 space-y-2 bg-slate-50">
                            @php
                                $inactiveShops = $shops->filter(fn($s) => $s->status !== 'active');
                                $includedInactive = (array) ($shopSettings['included_inactive_shop_ids'] ?? []);
                            @endphp
                            @forelse($inactiveShops as $shop)
                                <label class="flex items-center gap-2 text-xs font-semibold text-slate-800 cursor-pointer">
                                    <input type="checkbox" name="included_inactive_shop_ids[]" value="{{ $shop->id }}"
                                           {{ in_array($shop->id, $includedInactive, true) ? 'checked' : '' }}
                                           class="rounded text-indigo-600">
                                    <span>{{ $shop->name }}</span>
                                    <span class="text-[10px] text-amber-700 bg-amber-50 px-1.5 py-0.2 rounded border border-amber-200 uppercase">Inactive</span>
                                </label>
                            @empty
                                <p class="text-xs text-slate-400 italic">No inactive shops currently found.</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Blacklist Active Shops --}}
                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Exclude Active Shops from Printing
                        </label>
                        <p class="text-xs text-slate-500">Ticked shops will be excluded from all printing runs even if active.</p>
                        <div class="max-h-60 overflow-y-auto border border-slate-200 rounded-xl p-3 space-y-2 bg-slate-50">
                            @php
                                $activeShops = $shops->filter(fn($s) => $s->status === 'active');
                                $excludedActive = (array) ($shopSettings['excluded_active_shop_ids'] ?? []);
                            @endphp
                            @foreach($activeShops as $shop)
                                <label class="flex items-center gap-2 text-xs font-semibold text-slate-800 cursor-pointer">
                                    <input type="checkbox" name="excluded_active_shop_ids[]" value="{{ $shop->id }}"
                                           {{ in_array($shop->id, $excludedActive, true) ? 'checked' : '' }}
                                           class="rounded text-rose-600">
                                    <span>{{ $shop->name }}</span>
                                    @if($shop->warehouse_tag)
                                        <span class="text-[10px] font-mono text-slate-500">[{{ $shop->warehouse_tag }}]</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Global Empty Shop Default --}}
                <div class="pt-2 border-t border-slate-100">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="default_skip_empty_shops" value="0">
                        <input type="checkbox" name="default_skip_empty_shops" value="1"
                               {{ (bool) ($shopSettings['default_skip_empty_shops'] ?? true) ? 'checked' : '' }}
                               class="rounded text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-semibold text-slate-800">Global Default: Automatically skip shops with zero eligible order quantities</span>
                    </label>
                    <p class="text-[11px] text-slate-500 mt-0.5">Applies across all printing formats when a section has not set an override.</p>
                </div>

                @if(auth()->user()?->hasRole('admin') || auth()->user()?->isMainAdmin())
                    <div class="pt-2 border-t border-slate-100">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="save_as_system_default" value="0">
                            <input type="checkbox" name="save_as_system_default" value="1" class="rounded text-indigo-600">
                            <span class="text-xs font-bold text-indigo-700 uppercase tracking-wide">Save as System Default (All Users)</span>
                        </label>
                    </div>
                @endif

                <div class="pt-4 border-t border-slate-100 flex justify-end">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-sm hover:bg-indigo-700 shadow-sm transition">
                        Save Shop Configuration
                    </button>
                </div>
            </form>
        </div>

        {{-- TAB 6: Presets Manager --}}
        <div x-show="activeTab === 'presets'" x-cloak class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-lg font-black text-slate-900">Custom Presets</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Create and manage persistent presets for quick printing.</p>
                </div>
                <div class="flex items-center gap-3">
                    @if($legacyPresetCount > 0)
                        <form method="POST" action="{{ route('print.settings.presets.copy-legacy') }}">
                            @csrf
                            <button type="submit" onclick="return confirm('Copy {{ $legacyPresetCount }} legacy presets into V1? Legacy records will remain completely unchanged.')"
                                    class="px-3 py-1.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-50">
                                Import {{ $legacyPresetCount }} Legacy Presets
                            </button>
                        </form>
                    @endif
                    <button type="button" @click="openPresetModal()"
                            class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 shadow-xs">
                        + New Preset
                    </button>
                </div>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($presets as $preset)
                    <div class="py-3 flex items-center justify-between">
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-slate-900">{{ $preset->name }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 uppercase">
                                    {{ str_replace('-', ' ', $preset->section) }}
                                </span>
                                @if($preset->is_default)
                                    <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Default</span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-500">
                                Grades: {{ implode(', ', $preset->settings['grades'] ?? ['A', 'B', 'C', 'U']) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('print.'.$preset->section, ['preset' => $preset->uuid]) }}" target="_blank"
                               class="px-2.5 py-1 bg-white border border-slate-300 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-50">
                                Test Print
                            </a>
                            <form method="POST" action="{{ route('print.settings.presets.destroy', $preset) }}" onsubmit="return confirm('Delete preset \'{{ $preset->name }}\'?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-2.5 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-lg">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-center py-8 text-sm text-slate-400 italic">No custom presets saved yet.</p>
                @endforelse
            </div>
        </div>

        {{-- TAB 7: Batches Manager --}}
        <div x-show="activeTab === 'batches'" x-cloak class="bg-white rounded-2xl border border-slate-200 p-6 shadow-xs space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-lg font-black text-slate-900">Batch Print Presets</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Execute multiple presets sequentially in a single print job.</p>
                </div>
            </div>

            {{-- Form to create batch --}}
            <form method="POST" action="{{ route('print.settings.batches.store') }}" class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Batch Name</label>
                        <input type="text" name="name" placeholder="e.g. Morning Warehouse Run" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Section</label>
                        <select name="section" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option value="sort-sheet">Sort Print</option>
                            <option value="selection">Selection</option>
                            <option value="segregation-grid">Segregation Grid</option>
                            <option value="shop-wise-portrait">Shop Wise Portrait</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Select Presets in Execution Order</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 max-h-48 overflow-y-auto p-2 bg-white rounded-xl border border-slate-200">
                        @foreach($presets as $preset)
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-800 cursor-pointer">
                                <input type="checkbox" name="preset_ids[]" value="{{ $preset->uuid }}" class="rounded text-indigo-600">
                                <span>{{ $preset->name }} ({{ str_replace('-', ' ', $preset->section) }})</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 text-white font-bold text-xs hover:bg-indigo-700">
                        Create Batch
                    </button>
                </div>
            </form>

            {{-- Batches List --}}
            <div class="divide-y divide-slate-100">
                @forelse($batches as $batch)
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">{{ $batch->name }}</h4>
                            <p class="text-xs text-slate-500">{{ count((array) $batch->preset_ids) }} presets linked</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('print.batch-print', ['batch_id' => $batch->uuid]) }}" target="_blank"
                               class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700">
                                Run Batch Print
                            </a>
                            <form method="POST" action="{{ route('print.settings.batches.destroy', $batch) }}" onsubmit="return confirm('Delete batch \'{{ $batch->name }}\'?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-2.5 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-lg">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-center py-6 text-xs text-slate-400 italic">No batches configured yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Preset Modal (Alpine.js) --}}
        <div x-show="presetModalOpen" x-cloak
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div @click.away="presetModalOpen = false"
                 class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <h3 class="text-base font-black text-slate-900">Create New Preset</h3>
                    <button @click="presetModalOpen = false" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>

                <form method="POST" action="{{ route('print.settings.presets.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Preset Name</label>
                        <input type="text" name="name" placeholder="e.g. Vegetables Only" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Format Section</label>
                        <select name="section" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <option value="sort-sheet">Sort Print</option>
                            <option value="selection">Selection</option>
                            <option value="segregation-grid">Segregation Grid</option>
                            <option value="shop-wise-portrait">Shop Wise Portrait</option>
                        </select>
                    </div>

                    @include('print-v1.components.category-product-filter', [
                        'section' => 'preset-modal',
                        'categoryInputName' => 'settings[category_ids][]',
                        'productInputName' => 'settings[product_ids][]',
                        'categoryFilterLabel' => 'Category Filter (Optional)',
                        'productFilterLabel' => 'Product Filter (Optional)',
                        'selectedCategoryIds' => [],
                        'selectedProductIds' => [],
                        'categories' => $categoryPickerOptions,
                        'products' => $productPickerOptions,
                    ])

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Grades</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['A', 'B', 'C', 'U', 'DMG'] as $g)
                                <label class="inline-flex items-center gap-1.5 text-xs font-semibold">
                                    <input type="checkbox" name="settings[grades][]" value="{{ $g }}" checked class="rounded text-indigo-600">
                                    <span>{{ $g }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-2 pt-2 border-t border-slate-100">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="settings[skip_empty_shops]" value="0">
                            <input type="checkbox" name="settings[skip_empty_shops]" value="1" checked class="rounded text-indigo-600">
                            <span class="text-xs font-semibold text-slate-800">Auto-skip empty shops</span>
                        </label>

                        <div class="grid grid-cols-2 gap-2 pt-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="settings[code_sort]" value="0">
                                <input type="checkbox" name="settings[code_sort]" value="1" checked class="rounded text-indigo-600">
                                <span class="text-xs font-semibold text-slate-800">Sort by SKU</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="settings[show_category_titles]" value="0">
                                <input type="checkbox" name="settings[show_category_titles]" value="1" checked class="rounded text-indigo-600">
                                <span class="text-xs font-semibold text-slate-800">Show Categories</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" @click="presetModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700">
                            Save Preset
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
@endsection
