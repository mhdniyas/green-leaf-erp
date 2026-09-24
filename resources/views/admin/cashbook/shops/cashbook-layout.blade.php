@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop').' — Shop Cashbook UI Layout')

@section('content')
@php
    $currentShopSlugOrId = $currentShop->slug ?: $currentShop->shop_id;
@endphp

<div class="mx-auto max-w-5xl space-y-6 pb-24" id="layout-app">

    <!-- 1. HEADER & ACTIONS -->
    <header class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-950 tracking-tight uppercase">
                        {{ $currentShop->name ?: 'Shop #'.$currentShop->shop_id }}
                    </h1>
                    <span class="inline-flex items-center rounded-full bg-emerald-100 text-emerald-800 px-3 py-0.5 text-xs font-black uppercase tracking-wider">
                        Shop Cashbook Layout
                    </span>
                    @if($isCustomLayout)
                        <span class="inline-flex items-center rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider">
                            Custom Layout Active
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-600 border border-slate-200 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider">
                            Default Layout
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-xs font-semibold text-slate-500">
                    Customize the visual arrangement, sub-header nesting, and display names for the Shop Owner Daily Cashbook.
                </p>
            </div>

            <!-- Action Toolbar -->
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.cashbook.shop.financial-ledger', $currentShopSlugOrId) }}"
                   class="inline-flex items-center gap-1.5 rounded-2xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs font-black uppercase tracking-wider text-slate-700 shadow-2xs hover:bg-slate-50 transition cursor-pointer">
                    <span class="font-bold">&larr;</span>
                    <span>Financial Ledger</span>
                </a>

                <button type="button"
                        onclick="openShopOwnerPreview()"
                        class="inline-flex items-center gap-1.5 rounded-2xl border border-sky-300 bg-sky-50 px-3.5 py-2.5 text-xs font-black uppercase tracking-wider text-sky-900 shadow-2xs hover:bg-sky-100 active:scale-95 transition cursor-pointer">
                    <i data-lucide="eye" class="w-4 h-4 text-sky-700 shrink-0"></i>
                    <span>Preview Shop View</span>
                </button>

                <button type="button"
                        id="reset-layout-btn"
                        onclick="resetLayout()"
                        class="inline-flex items-center gap-1.5 rounded-2xl border border-slate-300 bg-white px-3.5 py-2.5 text-xs font-black uppercase tracking-wider text-slate-700 shadow-2xs hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 active:scale-95 transition cursor-pointer">
                    <i data-lucide="rotate-ccw" class="w-4 h-4 text-slate-500 shrink-0"></i>
                    <span>Reset Layout</span>
                </button>

                <button type="button"
                        id="save-layout-btn"
                        onclick="saveLayout()"
                        class="inline-flex items-center gap-2 rounded-2xl border border-emerald-600 bg-emerald-600 px-5 py-2.5 text-xs font-black uppercase tracking-wider text-white shadow-md hover:bg-emerald-700 active:scale-95 transition cursor-pointer">
                    <i data-lucide="save" class="w-4 h-4 text-white shrink-0"></i>
                    <span id="save-btn-label">Save Layout</span>
                </button>
            </div>
        </div>

        <!-- Explanatory Guide Box -->
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-3.5 flex items-start gap-3">
            <i data-lucide="info" class="w-4 h-4 text-emerald-700 shrink-0 mt-0.5"></i>
            <div class="text-xs text-emerald-950 space-y-1">
                <p class="font-bold">Visual Layout Editor Rules:</p>
                <ul class="list-disc list-inside text-emerald-900/90 text-[11px] space-y-0.5">
                    <li><strong>Display Names:</strong> Changing a display name only changes what the Shop Owner sees. Original Cashbook names are never modified.</li>
                    <li><strong>Nesting Structure:</strong> Maximum hierarchy is <code class="bg-white/80 px-1 py-0.5 rounded font-bold">Header → Sub-Header → Items</code>. Drag a header into a sub-header zone or click "Nest under...".</li>
                    <li><strong>Safety:</strong> No accounting balances, ledgers, transactions, or category settings are modified.</li>
                </ul>
            </div>
        </div>
    </header>

    <!-- 2. ROOT HEADERS DRAGGABLE CONTAINER -->
    <div id="root-headers-list" class="space-y-5">
        @forelse($resolvedHeaders as $h)
            <div class="root-header-card rounded-2xl border border-slate-200/90 bg-white shadow-xs transition duration-150 select-none overflow-hidden"
                 data-header-id="{{ $h['id'] }}"
                 data-source-id="{{ $h['source_id'] ?? '' }}"
                 data-original-name="{{ $h['original_name'] }}"
                 data-type="{{ $h['type'] }}"
                 data-product-tagging="{{ !empty($h['product_tagging_enabled']) ? '1' : '0' }}"
                 data-show-both-sides="{{ !empty($h['show_both_sides']) ? '1' : '0' }}">

                <!-- Header Top Bar -->
                <div class="p-4 bg-slate-50/90 border-b border-slate-200/80 flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <span class="root-drag-handle cursor-grab active:cursor-grabbing font-mono text-lg font-black text-slate-400 hover:text-slate-800 p-1.5 rounded-lg hover:bg-slate-200/60 transition shrink-0" title="Drag to reorder root header">
                            ⋮⋮
                        </span>

                        <div class="min-w-0 flex-1 space-y-1">
                            <!-- Badges and Original Name info -->
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-wider {{ $h['type'] === 'income' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $h['type'] }}
                                </span>
                                @if(!empty($h['product_tagging_enabled']))
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 text-[9px] font-bold">
                                        Products Tagged
                                    </span>
                                @endif
                                <div class="text-[11px] font-semibold text-slate-500 flex items-center gap-1">
                                    <span>Original Header:</span>
                                    <strong class="font-mono text-slate-800 bg-white px-1.5 py-0.5 rounded border border-slate-200">{{ $h['original_name'] }}</strong>
                                </div>
                            </div>

                            <!-- Display Name Field with Reset Name Button -->
                            <div class="flex items-center gap-2 pt-1">
                                <div class="relative max-w-sm flex-1">
                                    <label class="sr-only">Display Name</label>
                                    <input type="text"
                                           class="display-name-input w-full h-8 px-3 rounded-lg border border-slate-300 bg-white text-xs font-black text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none transition shadow-2xs"
                                           value="{{ $h['custom_display_name'] ?? $h['display_name'] }}"
                                           placeholder="{{ $h['original_name'] }}"
                                           data-original="{{ $h['original_name'] }}"
                                           oninput="handleDisplayNameChange(this)">
                                </div>
                                <button type="button"
                                        onclick="resetDisplayName(this)"
                                        class="reset-name-btn h-8 px-2.5 rounded-lg border border-slate-200 bg-white text-[11px] font-bold text-slate-600 hover:text-slate-900 hover:bg-slate-100 shadow-2xs transition cursor-pointer shrink-0"
                                        title="Reset to original name: {{ $h['original_name'] }}">
                                    Reset Name
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Controls (Nest Helper Button) -->
                    <div class="flex items-center gap-2 shrink-0 self-end md:self-center">
                        <button type="button"
                                onclick="promptNestUnder(this)"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl border border-slate-200 bg-white text-[11px] font-bold text-slate-700 hover:bg-slate-100 shadow-2xs transition cursor-pointer">
                            <i data-lucide="corner-down-right" class="w-3.5 h-3.5 text-slate-500"></i>
                            <span>Nest under...</span>
                        </button>
                    </div>
                </div>

                <!-- Header Content Area (Sub-Headers + Direct Items) -->
                <div class="p-4 space-y-4 bg-slate-50/40">

                    <!-- SUB-HEADERS CONTAINER -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-[11px] font-black uppercase tracking-wider text-slate-400">
                            <span>Sub-Headers (Nested under this Header)</span>
                            <span class="text-[10px] font-normal text-slate-400">Drag headers here to nest</span>
                        </div>

                        <div class="sub-headers-list space-y-3 min-h-[42px] p-2.5 rounded-xl border-2 border-dashed border-slate-200 bg-white/70"
                             data-parent-header-id="{{ $h['id'] }}">
                            @forelse($h['sub_headers'] as $sub)
                                <div class="sub-header-card rounded-xl border border-indigo-100 bg-indigo-50/30 p-3 shadow-2xs space-y-3 transition"
                                     data-sub-header-id="{{ $sub['id'] }}"
                                     data-source-id="{{ $sub['source_id'] ?? '' }}"
                                     data-original-name="{{ $sub['original_name'] }}"
                                     data-type="{{ $sub['type'] }}"
                                     data-product-tagging="{{ !empty($sub['product_tagging_enabled']) ? '1' : '0' }}">
                                    
                                    <!-- Sub-Header Top Line -->
                                    <div class="flex items-center justify-between gap-2 flex-wrap">
                                        <div class="flex items-center gap-2 flex-1 min-w-0">
                                            <span class="sub-drag-handle cursor-grab active:cursor-grabbing font-mono text-sm font-black text-indigo-400 hover:text-indigo-800 p-1 rounded hover:bg-indigo-100 transition shrink-0" title="Drag sub-header">
                                                ⋮⋮
                                            </span>
                                            <span class="inline-flex items-center rounded-full bg-indigo-100 text-indigo-800 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider">
                                                Sub-Header
                                            </span>
                                            <div class="text-[11px] font-medium text-slate-500 truncate">
                                                Original: <strong class="font-mono text-slate-800 bg-white px-1.5 py-0.5 rounded border border-slate-200">{{ $sub['original_name'] }}</strong>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <button type="button"
                                                    onclick="moveSubHeaderToRoot(this)"
                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-slate-200 bg-white text-[10px] font-bold text-slate-700 hover:bg-slate-100 shadow-2xs transition cursor-pointer"
                                                    title="Move this sub-header back to root level">
                                                <i data-lucide="arrow-up-left" class="w-3 h-3 text-slate-500"></i>
                                                <span>Move to Root</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Sub-Header Display Name Input -->
                                    <div class="flex items-center gap-2">
                                        <div class="relative max-w-xs flex-1">
                                            <input type="text"
                                                   class="display-name-input w-full h-7 px-2.5 rounded-md border border-slate-300 bg-white text-xs font-bold text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none transition"
                                                   value="{{ $sub['custom_display_name'] ?? $sub['display_name'] }}"
                                                   placeholder="{{ $sub['original_name'] }}"
                                                   data-original="{{ $sub['original_name'] }}"
                                                   oninput="handleDisplayNameChange(this)">
                                        </div>
                                        <button type="button"
                                                onclick="resetDisplayName(this)"
                                                class="reset-name-btn h-7 px-2 rounded-md border border-slate-200 bg-white text-[10px] font-bold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition cursor-pointer shrink-0">
                                            Reset Name
                                        </button>
                                    </div>

                                    <!-- Sub-Header Items List -->
                                    <div class="space-y-1">
                                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 pl-1">Items in {{ $sub['display_name'] }}</div>
                                        <div class="items-list space-y-1.5 p-2 bg-white/90 rounded-lg border border-indigo-100 min-h-[36px]"
                                             data-container-type="sub-header"
                                             data-sub-header-id="{{ $sub['id'] }}">
                                            @php
                                                $subHasItems = !empty($sub['settings']) && $sub['settings']->isNotEmpty();
                                                $subHasProducts = !empty($sub['products']) && count($sub['products']) > 0;
                                            @endphp
                                            @if($subHasItems)
                                                @foreach($sub['settings'] as $setting)
                                                    <div class="item-row flex items-center justify-between gap-2 p-2 bg-slate-50 rounded-md border border-slate-200/80 hover:border-indigo-300 transition"
                                                         data-item-type="setting"
                                                         data-setting-id="{{ $setting->id }}">
                                                        <div class="flex items-center gap-2 min-w-0 flex-1">
                                                            <span class="item-drag-handle cursor-grab active:cursor-grabbing font-mono text-xs font-black text-slate-400 hover:text-slate-800 p-0.5 rounded hover:bg-slate-200 transition shrink-0" title="Drag item">
                                                                ⋮⋮
                                                            </span>
                                                            <div class="min-w-0 flex-1">
                                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                                    <span class="text-xs font-bold text-slate-900 truncate">{{ $setting->displayName() }}</span>
                                                                    @if($setting->entryType?->code)
                                                                        <span class="font-mono text-[9px] text-slate-500 bg-white border border-slate-200 px-1 py-0.5 rounded">{{ $setting->entryType->code }}</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                        @if($setting->is_vendor_purchase)
                                                            <span class="text-[9px] font-extrabold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded">Vendor Purchase</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            @endif

                                            @if($subHasProducts)
                                                @foreach($sub['products'] as $prod)
                                                    <div class="item-row product-item-row flex items-center justify-between gap-2 p-2 bg-emerald-50/40 rounded-md border border-emerald-200/80 hover:border-emerald-400 transition"
                                                         data-item-type="product"
                                                         data-product-id="{{ $prod['id'] }}">
                                                        <div class="flex items-center gap-2 min-w-0 flex-1">
                                                            <span class="item-drag-handle cursor-grab active:cursor-grabbing font-mono text-xs font-black text-emerald-500 hover:text-emerald-800 p-0.5 rounded hover:bg-emerald-100 transition shrink-0" title="Drag product">
                                                                ⋮⋮
                                                            </span>
                                                            <div class="min-w-0 flex-1">
                                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                                    <span class="text-xs font-bold text-slate-900 truncate">{{ $prod['name'] }}</span>
                                                                    <span class="font-mono text-[9px] text-emerald-800 bg-white border border-emerald-200 px-1 py-0.5 rounded font-semibold">
                                                                        {{ $prod['sku'] ? $prod['sku'] : '#'.$prod['id'] }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <span class="text-[9px] font-extrabold text-emerald-700 bg-emerald-100/70 border border-emerald-200 px-1.5 py-0.5 rounded">Product</span>
                                                    </div>
                                                @endforeach
                                            @endif

                                            @if(!$subHasItems && !$subHasProducts)
                                                <div class="py-2 text-center text-[11px] font-medium text-slate-400 italic empty-placeholder">
                                                    No items in this sub-header. Drag items here.
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                </div>
                            @empty
                                <div class="py-2.5 text-center text-[11px] font-medium text-slate-400 italic empty-sub-placeholder">
                                    No sub-headers nested here. Drag another header here or click "Nest under...".
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <!-- DIRECT ITEMS CONTAINER -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-[11px] font-black uppercase tracking-wider text-slate-400">
                            <span>Direct Items in {{ $h['display_name'] }}</span>
                            <span class="text-[10px] font-normal text-slate-400">Drag items to reorder or move</span>
                        </div>

                        <div class="items-list space-y-1.5 p-2.5 bg-white rounded-xl border border-slate-200 min-h-[44px]"
                             data-container-type="root-header"
                             data-header-id="{{ $h['id'] }}">
                            @php
                                $rootHasItems = !empty($h['settings']) && $h['settings']->isNotEmpty();
                                $rootHasProducts = !empty($h['products']) && count($h['products']) > 0;
                            @endphp
                            @if($rootHasItems)
                                @foreach($h['settings'] as $setting)
                                    <div class="item-row flex items-center justify-between gap-3 p-2.5 bg-slate-50/80 rounded-lg border border-slate-200/80 hover:border-emerald-300 transition"
                                         data-item-type="setting"
                                         data-setting-id="{{ $setting->id }}">
                                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                            <span class="item-drag-handle cursor-grab active:cursor-grabbing font-mono text-sm font-black text-slate-400 hover:text-slate-800 p-0.5 rounded hover:bg-slate-200 transition shrink-0" title="Drag item">
                                                ⋮⋮
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="text-xs font-bold text-slate-900 truncate">{{ $setting->displayName() }}</span>
                                                    @if($setting->entryType?->code)
                                                        <span class="font-mono text-[9px] text-slate-500 bg-white border border-slate-200 px-1 py-0.5 rounded">{{ $setting->entryType->code }}</span>
                                                    @endif
                                                </div>
                                                @if($setting->companyAccount)
                                                    <span class="text-[10px] text-slate-400 font-medium block truncate mt-0.5">{{ $setting->companyAccount->name }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="shrink-0 flex items-center gap-1">
                                            @if($setting->is_vendor_purchase)
                                                <span class="text-[9px] font-extrabold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded">Vendor Purchase</span>
                                            @endif
                                            @if($setting->is_readonly)
                                                <span class="text-[9px] font-extrabold text-slate-500 bg-slate-100 rounded px-1.5 py-0.5">Readonly</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            @endif

                            @if($rootHasProducts)
                                @foreach($h['products'] as $prod)
                                    <div class="item-row product-item-row flex items-center justify-between gap-3 p-2.5 bg-emerald-50/40 rounded-lg border border-emerald-200/80 hover:border-emerald-400 transition"
                                         data-item-type="product"
                                         data-product-id="{{ $prod['id'] }}">
                                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                            <span class="item-drag-handle cursor-grab active:cursor-grabbing font-mono text-sm font-black text-emerald-500 hover:text-emerald-800 p-0.5 rounded hover:bg-emerald-100 transition shrink-0" title="Drag product">
                                                ⋮⋮
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="text-xs font-bold text-slate-900 truncate">{{ $prod['name'] }}</span>
                                                    <span class="font-mono text-[9px] text-emerald-800 bg-white border border-emerald-200 px-1 py-0.5 rounded font-semibold">
                                                        {{ $prod['sku'] ? $prod['sku'] : '#'.$prod['id'] }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="shrink-0 flex items-center gap-1">
                                            <span class="text-[9px] font-extrabold text-emerald-700 bg-emerald-100/70 border border-emerald-200 px-1.5 py-0.5 rounded">Product</span>
                                        </div>
                                    </div>
                                @endforeach
                            @endif

                            @if(!$rootHasItems && !$rootHasProducts)
                                <div class="py-2.5 text-center text-[11px] font-medium text-slate-400 italic empty-placeholder">
                                    No direct items in this header. Drag items here.
                                </div>
                            @endif
                        </div>
                    </div>

                </div>

            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-8 text-center space-y-2">
                <i data-lucide="layout-grid" class="h-8 w-8 text-slate-300 mx-auto"></i>
                <h3 class="text-sm font-bold text-slate-700">No Cashbook Headers Configured</h3>
                <p class="text-xs text-slate-400">Configure headers and categories in Cashbook Settings first.</p>
            </div>
        @endforelse
    </div>

    <!-- Bottom Save Toolbar -->
    <div class="flex items-center justify-between pt-4 border-t border-slate-200">
        <a href="{{ route('admin.cashbook.shop.financial-ledger', $currentShopSlugOrId) }}"
           class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-slate-800 transition">
            &larr; Back to Financial Ledger
        </a>

        <div class="flex items-center gap-2">
            <button type="button"
                    onclick="openShopOwnerPreview()"
                    class="inline-flex items-center gap-1.5 rounded-2xl border border-sky-300 bg-sky-50 px-4 py-2.5 text-xs font-black uppercase tracking-wider text-sky-900 shadow-2xs hover:bg-sky-100 transition cursor-pointer">
                <i data-lucide="eye" class="w-4 h-4 text-sky-700 shrink-0"></i>
                <span>Preview View</span>
            </button>

            <button type="button"
                    onclick="saveLayout()"
                    class="inline-flex items-center gap-2 rounded-2xl border border-emerald-600 bg-emerald-600 px-6 py-2.5 text-xs font-black uppercase tracking-wider text-white shadow-md hover:bg-emerald-700 active:scale-95 transition cursor-pointer">
                <i data-lucide="save" class="w-4 h-4 text-white shrink-0"></i>
                <span>Save Layout</span>
            </button>
        </div>
    </div>

</div>

{{-- 3. PREVIEW MODAL (EXACT SHOP OWNER VIEW) --}}
<div id="shop-owner-preview-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs hidden p-3 sm:p-6 transition-all duration-200">
    <div class="w-full max-w-3xl rounded-3xl bg-slate-100 shadow-2xl border border-slate-200/80 flex flex-col max-h-[90vh] overflow-hidden">
        <!-- Preview Modal Header -->
        <div class="p-4 sm:p-5 bg-white border-b border-slate-200/80 flex items-center justify-between gap-3 shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-black">
                    <i data-lucide="smartphone" class="w-4 h-4"></i>
                </div>
                <div>
                    <h2 class="text-sm sm:text-base font-black uppercase tracking-tight text-slate-950">
                        {{ $currentShop->name ?: 'Shop' }} — Shop Owner View Preview
                    </h2>
                    <p class="text-[11px] font-semibold text-slate-500">
                        This is the exact layout the Shop Owner sees on their daily cashbook.
                    </p>
                </div>
            </div>
            <button type="button"
                    onclick="closeShopOwnerPreview()"
                    class="h-8 w-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-900 flex items-center justify-center transition cursor-pointer">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- Preview Modal Body (Clean Shop Owner Cards) -->
        <div class="p-4 sm:p-6 space-y-4 overflow-y-auto flex-1 bg-slate-100" id="preview-cards-container">
            <!-- Rendered dynamically by openShopOwnerPreview() -->
        </div>

        <!-- Preview Modal Footer -->
        <div class="p-3.5 bg-white border-t border-slate-200 flex items-center justify-between shrink-0">
            <span class="text-[11px] font-bold text-slate-400">Preview Mode &bull; No original names or drag handles shown</span>
            <button type="button"
                    onclick="closeShopOwnerPreview()"
                    class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-black uppercase tracking-wider hover:bg-slate-800 transition cursor-pointer">
                Close Preview
            </button>
        </div>
    </div>
</div>

{{-- 4. NEST SELECTION MODAL --}}
<div id="nest-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 backdrop-blur-2xs hidden p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl border border-slate-200 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-sm font-black text-slate-900 uppercase">Nest Under Header</h3>
            <button type="button" onclick="closeNestModal()" class="text-slate-400 hover:text-slate-700">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <p class="text-xs text-slate-600">
            Select a target parent header to nest <strong id="nest-source-name" class="text-slate-900"></strong> under as a sub-header:
        </p>
        <div id="nest-targets-list" class="space-y-2 max-h-60 overflow-y-auto pr-1">
            <!-- Rendered dynamically -->
        </div>
        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <button type="button" onclick="closeNestModal()" class="px-3 py-1.5 text-xs font-bold text-slate-600 hover:text-slate-900">
                Cancel
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
    let sortableInstances = [];
    let currentNestingSourceEl = null;

    document.addEventListener('DOMContentLoaded', () => {
        initSortable();
        if (window.lucide) lucide.createIcons();
    });

    function initSortable() {
        // Destroy any existing instances
        sortableInstances.forEach(inst => {
            if (inst && inst.destroy) inst.destroy();
        });
        sortableInstances = [];

        if (typeof Sortable === 'undefined') return;

        // 1. Root Headers Sortable (Reordering root cards)
        const rootContainer = document.getElementById('root-headers-list');
        if (rootContainer) {
            const rootSortable = Sortable.create(rootContainer, {
                group: {
                    name: 'root-headers',
                    put: false, // sub-headers are moved to root via button or specific drop
                },
                animation: 200,
                handle: '.root-drag-handle',
                ghostClass: 'opacity-40',
                chosenClass: 'bg-emerald-50/60',
                dragClass: 'shadow-2xl',
            });
            sortableInstances.push(rootSortable);
        }

        // 2. Sub-Headers Sortable (Reorder sub-headers inside parent & drag across parents)
        document.querySelectorAll('.sub-headers-list').forEach(container => {
            const subSortable = Sortable.create(container, {
                group: 'sub-headers-group',
                animation: 200,
                handle: '.sub-drag-handle',
                ghostClass: 'opacity-40',
                chosenClass: 'bg-indigo-100',
                dragClass: 'shadow-xl',
                onAdd: (evt) => updateSubHeaderPlaceholders(evt.to, evt.from),
                onRemove: (evt) => updateSubHeaderPlaceholders(evt.to, evt.from),
            });
            sortableInstances.push(subSortable);
        });

        // 3. Items Sortable (Reorder items & move items between root and sub-headers)
        document.querySelectorAll('.items-list').forEach(container => {
            const itemSortable = Sortable.create(container, {
                group: 'cashbook-items',
                animation: 200,
                handle: '.item-drag-handle',
                ghostClass: 'opacity-40',
                chosenClass: 'bg-emerald-50',
                dragClass: 'shadow-lg',
                onAdd: (evt) => updateItemPlaceholders(evt.to, evt.from),
                onRemove: (evt) => updateItemPlaceholders(evt.to, evt.from),
            });
            sortableInstances.push(itemSortable);
        });
    }

    function updateSubHeaderPlaceholders(toEl, fromEl) {
        [toEl, fromEl].forEach(el => {
            if (!el) return;
            const subs = el.querySelectorAll('.sub-header-card');
            const placeholder = el.querySelector('.empty-sub-placeholder');
            if (subs.length === 0) {
                if (!placeholder) {
                    const ph = document.createElement('div');
                    ph.className = 'py-2.5 text-center text-[11px] font-medium text-slate-400 italic empty-sub-placeholder';
                    ph.textContent = 'No sub-headers nested here. Drag another header here or click "Nest under...".';
                    el.appendChild(ph);
                } else {
                    placeholder.style.display = 'block';
                }
            } else if (placeholder) {
                placeholder.style.display = 'none';
            }
        });
    }

    function updateItemPlaceholders(toEl, fromEl) {
        [toEl, fromEl].forEach(el => {
            if (!el) return;
            const items = el.querySelectorAll('.item-row');
            const placeholder = el.querySelector('.empty-placeholder');
            if (items.length === 0) {
                if (!placeholder) {
                    const ph = document.createElement('div');
                    ph.className = 'py-2.5 text-center text-[11px] font-medium text-slate-400 italic empty-placeholder';
                    ph.textContent = 'No items in this section. Drag items here.';
                    el.appendChild(ph);
                } else {
                    placeholder.style.display = 'block';
                }
            } else if (placeholder) {
                placeholder.style.display = 'none';
            }
        });
    }

    // Display Name Editing
    function handleDisplayNameChange(input) {
        // Visual indicator if modified from original
        const orig = input.dataset.original || '';
        if (input.value.trim() !== '' && input.value.trim() !== orig) {
            input.classList.add('border-emerald-400', 'bg-emerald-50/30');
        } else {
            input.classList.remove('border-emerald-400', 'bg-emerald-50/30');
        }
    }

    function resetDisplayName(btn) {
        const parent = btn.closest('.flex');
        const input = parent.querySelector('.display-name-input');
        if (input) {
            const orig = input.dataset.original || '';
            input.value = orig;
            input.classList.remove('border-emerald-400', 'bg-emerald-50/30');
        }
    }

    // Move Sub-Header back to Root
    function moveSubHeaderToRoot(btn) {
        const subCard = btn.closest('.sub-header-card');
        if (!subCard) return;

        const subId = subCard.dataset.subHeaderId;
        const sourceId = subCard.dataset.sourceId || '';
        const originalName = subCard.dataset.originalName || 'Header';
        const type = subCard.dataset.type || 'income';
        const productTagging = subCard.dataset.productTagging === '1';
        const displayNameInput = subCard.querySelector('.display-name-input');
        const currentDisplayName = displayNameInput ? displayNameInput.value : originalName;

        // Collect existing items in this sub-header
        const items = Array.from(subCard.querySelectorAll('.items-list .item-row'));

        // Build a Root Header Card DOM Element
        const newRootCard = document.createElement('div');
        newRootCard.className = 'root-header-card rounded-2xl border border-slate-200/90 bg-white shadow-xs transition duration-150 select-none overflow-hidden';
        newRootCard.dataset.headerId = subId;
        newRootCard.dataset.sourceId = sourceId;
        newRootCard.dataset.originalName = originalName;
        newRootCard.dataset.type = type;
        newRootCard.dataset.productTagging = productTagging ? '1' : '0';
        newRootCard.dataset.showBothSides = '0';

        const typeBadge = type === 'income' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800';

        newRootCard.innerHTML = `
            <div class="p-4 bg-slate-50/90 border-b border-slate-200/80 flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <span class="root-drag-handle cursor-grab active:cursor-grabbing font-mono text-lg font-black text-slate-400 hover:text-slate-800 p-1.5 rounded-lg hover:bg-slate-200/60 transition shrink-0" title="Drag to reorder root header">
                        ⋮⋮
                    </span>
                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-wider ${typeBadge}">
                                ${escapeHtml(type)}
                            </span>
                            ${productTagging ? '<span class="inline-flex items-center rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 text-[9px] font-bold">Products Tagged</span>' : ''}
                            <div class="text-[11px] font-semibold text-slate-500 flex items-center gap-1">
                                <span>Original Header:</span>
                                <strong class="font-mono text-slate-800 bg-white px-1.5 py-0.5 rounded border border-slate-200">${escapeHtml(originalName)}</strong>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 pt-1">
                            <div class="relative max-w-sm flex-1">
                                <input type="text"
                                       class="display-name-input w-full h-8 px-3 rounded-lg border border-slate-300 bg-white text-xs font-black text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none transition shadow-2xs"
                                       value="${escapeHtml(currentDisplayName)}"
                                       placeholder="${escapeHtml(originalName)}"
                                       data-original="${escapeHtml(originalName)}"
                                       oninput="handleDisplayNameChange(this)">
                            </div>
                            <button type="button"
                                    onclick="resetDisplayName(this)"
                                    class="reset-name-btn h-8 px-2.5 rounded-lg border border-slate-200 bg-white text-[11px] font-bold text-slate-600 hover:text-slate-900 hover:bg-slate-100 shadow-2xs transition cursor-pointer shrink-0">
                                Reset Name
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0 self-end md:self-center">
                    <button type="button"
                            onclick="promptNestUnder(this)"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl border border-slate-200 bg-white text-[11px] font-bold text-slate-700 hover:bg-slate-100 shadow-2xs transition cursor-pointer">
                        <i data-lucide="corner-down-right" class="w-3.5 h-3.5 text-slate-500"></i>
                        <span>Nest under...</span>
                    </button>
                </div>
            </div>
            <div class="p-4 space-y-4 bg-slate-50/40">
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-[11px] font-black uppercase tracking-wider text-slate-400">
                        <span>Sub-Headers (Nested under this Header)</span>
                        <span class="text-[10px] font-normal text-slate-400">Drag headers here to nest</span>
                    </div>
                    <div class="sub-headers-list space-y-3 min-h-[42px] p-2.5 rounded-xl border-2 border-dashed border-slate-200 bg-white/70"
                         data-parent-header-id="${escapeHtml(subId)}">
                        <div class="py-2.5 text-center text-[11px] font-medium text-slate-400 italic empty-sub-placeholder">
                            No sub-headers nested here. Drag another header here or click "Nest under...".
                        </div>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-[11px] font-black uppercase tracking-wider text-slate-400">
                        <span>Direct Items in ${escapeHtml(currentDisplayName)}</span>
                        <span class="text-[10px] font-normal text-slate-400">Drag items to reorder or move</span>
                    </div>
                    <div class="items-list space-y-1.5 p-2.5 bg-white rounded-xl border border-slate-200 min-h-[44px]"
                         data-container-type="root-header"
                         data-header-id="${escapeHtml(subId)}">
                    </div>
                </div>
            </div>
        `;

        const rootList = document.getElementById('root-headers-list');
        const parentSubList = subCard.closest('.sub-headers-list');

        // Move item elements into new root direct items container
        const newItemsContainer = newRootCard.querySelector('.items-list');
        items.forEach(it => newItemsContainer.appendChild(it));
        updateItemPlaceholders(newItemsContainer, null);

        // Remove sub-card and append new root card
        subCard.remove();
        if (parentSubList) {
            updateSubHeaderPlaceholders(parentSubList, null);
        }
        rootList.appendChild(newRootCard);

        initSortable();
        if (window.lucide) lucide.createIcons();
    }

    // Nest a Root Header under another Root Header
    function promptNestUnder(btn) {
        const rootCard = btn.closest('.root-header-card');
        if (!rootCard) return;

        currentNestingSourceEl = rootCard;
        const sourceHeaderId = rootCard.dataset.headerId;
        const sourceName = rootCard.querySelector('.display-name-input')?.value || rootCard.dataset.originalName || 'Header';

        document.getElementById('nest-source-name').textContent = sourceName;
        const targetsContainer = document.getElementById('nest-targets-list');
        targetsContainer.innerHTML = '';

        const allRootCards = document.querySelectorAll('#root-headers-list > .root-header-card');
        let count = 0;

        allRootCards.forEach(card => {
            const targetId = card.dataset.headerId;
            if (targetId === sourceHeaderId) return; // cannot nest under itself

            count++;
            const targetName = card.querySelector('.display-name-input')?.value || card.dataset.originalName || 'Target';
            const targetType = card.dataset.type || 'income';

            const btnEl = document.createElement('button');
            btnEl.type = 'button';
            btnEl.className = 'w-full p-3 text-left rounded-xl border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/50 flex items-center justify-between transition cursor-pointer';
            btnEl.innerHTML = `
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[9px] font-black uppercase ${targetType === 'income' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}">
                        ${escapeHtml(targetType)}
                    </span>
                    <span class="text-xs font-bold text-slate-900">${escapeHtml(targetName)}</span>
                </div>
                <span class="text-[11px] font-bold text-indigo-600">Nest Here &rarr;</span>
            `;
            btnEl.onclick = () => executeNestUnder(card);
            targetsContainer.appendChild(btnEl);
        });

        if (count === 0) {
            targetsContainer.innerHTML = '<div class="py-4 text-center text-xs text-slate-400 italic">No other root headers available to nest under.</div>';
        }

        document.getElementById('nest-modal').classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    function closeNestModal() {
        document.getElementById('nest-modal').classList.add('hidden');
        currentNestingSourceEl = null;
    }

    function executeNestUnder(targetRootCard) {
        if (!currentNestingSourceEl || !targetRootCard) return;

        const sourceCard = currentNestingSourceEl;
        const subId = sourceCard.dataset.headerId;
        const sourceId = sourceCard.dataset.sourceId || '';
        const originalName = sourceCard.dataset.originalName || 'Header';
        const type = sourceCard.dataset.type || 'income';
        const productTagging = sourceCard.dataset.productTagging === '1';
        const currentDisplayName = sourceCard.querySelector('.display-name-input')?.value || originalName;

        // Collect direct items from source card + any items in its sub-headers
        const items = Array.from(sourceCard.querySelectorAll('.items-list .item-row'));

        // Build Sub-Header card DOM Element
        const subCard = document.createElement('div');
        subCard.className = 'sub-header-card rounded-xl border border-indigo-100 bg-indigo-50/30 p-3 shadow-2xs space-y-3 transition';
        subCard.dataset.subHeaderId = subId;
        subCard.dataset.sourceId = sourceId;
        subCard.dataset.originalName = originalName;
        subCard.dataset.type = type;
        subCard.dataset.productTagging = productTagging ? '1' : '0';

        subCard.innerHTML = `
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <span class="sub-drag-handle cursor-grab active:cursor-grabbing font-mono text-sm font-black text-indigo-400 hover:text-indigo-800 p-1 rounded hover:bg-indigo-100 transition shrink-0" title="Drag sub-header">
                        ⋮⋮
                    </span>
                    <span class="inline-flex items-center rounded-full bg-indigo-100 text-indigo-800 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider">
                        Sub-Header
                    </span>
                    <div class="text-[11px] font-medium text-slate-500 truncate">
                        Original: <strong class="font-mono text-slate-800 bg-white px-1.5 py-0.5 rounded border border-slate-200">${escapeHtml(originalName)}</strong>
                    </div>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <button type="button"
                            onclick="moveSubHeaderToRoot(this)"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-slate-200 bg-white text-[10px] font-bold text-slate-700 hover:bg-slate-100 shadow-2xs transition cursor-pointer"
                            title="Move this sub-header back to root level">
                        <i data-lucide="arrow-up-left" class="w-3 h-3 text-slate-500"></i>
                        <span>Move to Root</span>
                    </button>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="relative max-w-xs flex-1">
                    <input type="text"
                           class="display-name-input w-full h-7 px-2.5 rounded-md border border-slate-300 bg-white text-xs font-bold text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none transition"
                           value="${escapeHtml(currentDisplayName)}"
                           placeholder="${escapeHtml(originalName)}"
                           data-original="${escapeHtml(originalName)}"
                           oninput="handleDisplayNameChange(this)">
                </div>
                <button type="button"
                        onclick="resetDisplayName(this)"
                        class="reset-name-btn h-7 px-2 rounded-md border border-slate-200 bg-white text-[10px] font-bold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition cursor-pointer shrink-0">
                    Reset Name
                </button>
            </div>
            <div class="space-y-1">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 pl-1">Items in ${escapeHtml(currentDisplayName)}</div>
                <div class="items-list space-y-1.5 p-2 bg-white/90 rounded-lg border border-indigo-100 min-h-[36px]"
                     data-container-type="sub-header"
                     data-sub-header-id="${escapeHtml(subId)}">
                </div>
            </div>
        `;

        const targetSubList = targetRootCard.querySelector('.sub-headers-list');
        const targetItemsContainer = subCard.querySelector('.items-list');

        items.forEach(it => targetItemsContainer.appendChild(it));
        updateItemPlaceholders(targetItemsContainer, null);

        targetSubList.appendChild(subCard);
        updateSubHeaderPlaceholders(targetSubList, null);

        sourceCard.remove();
        closeNestModal();

        initSortable();
        if (window.lucide) lucide.createIcons();
    }

    // SHOP OWNER PREVIEW
    let previewSubHeaderCollapseState = {};

    function togglePreviewSubHeader(subId, event) {
        if (event) event.stopPropagation();
        previewSubHeaderCollapseState[subId] = !previewSubHeaderCollapseState[subId];
        openShopOwnerPreview();
    }

    function openShopOwnerPreview() {
        const modal = document.getElementById('shop-owner-preview-modal');
        const container = document.getElementById('preview-cards-container');
        if (!modal || !container) return;

        const tree = buildLayoutPayload();
        if (tree.length === 0) {
            container.innerHTML = '<div class="p-8 text-center text-sm font-semibold text-slate-500">No cashbook sections to display.</div>';
        } else {
            container.innerHTML = tree.map(h => {
                const isIncome = (h.type || '').toLowerCase() === 'income';
                const headerClass = isIncome ? 'text-emerald-700' : 'text-rose-700 font-black';

                // Direct items HTML inside preview
                const directItemsHtml = (h.items || []).map(item => `
                    <div class="flex items-start justify-between gap-2 py-1">
                        <div class="min-w-0 flex-1">
                            <span class="text-xs font-bold text-slate-800 leading-tight block truncate">${escapeHtml(item.name)}</span>
                            ${item.is_product ? '<span class="text-[9px] text-emerald-600 font-bold block">Product</span>' : ''}
                        </div>
                        <span class="font-mono text-xs font-black text-slate-400">₹0.00</span>
                    </div>
                `).join('');

                // Sub-headers HTML inside preview (Accordion rows inside card)
                const subHeadersHtml = (h.sub_headers || []).map(sub => {
                    const isExpanded = !!previewSubHeaderCollapseState[sub.id];
                    const subItemsHtml = (sub.items || []).map(item => `
                        <div class="flex items-start justify-between gap-2 py-1 pl-2">
                            <div class="min-w-0 flex-1">
                                <span class="text-xs font-semibold text-slate-700 leading-tight block truncate">${escapeHtml(item.name)}</span>
                                ${item.is_product ? '<span class="text-[9px] text-emerald-600 font-bold block">Product</span>' : ''}
                            </div>
                            <span class="font-mono text-xs font-bold text-slate-400">₹0.00</span>
                        </div>
                    `).join('');

                    return `
                        <div class="border-t border-slate-100/90 pt-1.5 mt-1.5">
                            <div class="flex items-center justify-between gap-2 py-1 cursor-pointer hover:bg-slate-50/90 rounded-lg px-1 transition select-none"
                                 onclick="togglePreviewSubHeader('${sub.id}', event)">
                                <div class="min-w-0 flex-1 flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-xs text-[10px] font-black ${isExpanded ? 'bg-slate-200 text-slate-900 border border-slate-300' : 'bg-slate-100 text-slate-700 border border-slate-200'} shrink-0">
                                        ${isExpanded ? '−' : '+'}
                                    </span>
                                    <span class="text-xs font-black uppercase text-slate-900 tracking-tight truncate">${escapeHtml(sub.display_name)}</span>
                                </div>
                                <span class="font-mono text-xs font-bold text-slate-400">₹0.00</span>
                            </div>
                            ${isExpanded ? `
                                <div class="pl-3 pr-1 pt-1 pb-1 space-y-0.5 border-l-2 border-slate-200/80 ml-2 mt-1 divide-y divide-slate-50">
                                    ${subItemsHtml || '<div class="py-1 pl-2 text-[11px] text-slate-400 italic">No items</div>'}
                                </div>
                            ` : ''}
                        </div>
                    `;
                }).join('');

                return `
                    <div class="rounded-xl sm:rounded-2xl border border-slate-200 bg-white p-3.5 sm:p-4 shadow-xs space-y-2 select-none">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span class="h-2 w-2 rounded-full ${isIncome ? 'bg-emerald-500' : 'bg-rose-500'} shrink-0"></span>
                                <span class="text-xs sm:text-sm font-black uppercase text-slate-900 truncate">${escapeHtml(h.display_name)}</span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="font-mono text-xs sm:text-sm font-black ${headerClass}">₹0.00</span>
                                <span class="inline-flex items-center text-[10px] font-bold text-slate-400">
                                    <span>Edit</span>
                                    <i data-lucide="chevron-right" class="h-3 w-3 ml-0.5"></i>
                                </span>
                            </div>
                        </div>

                        <div class="space-y-0.5 divide-y divide-slate-50">
                            ${directItemsHtml}
                            ${subHeadersHtml}
                            ${(!directItemsHtml && !subHeadersHtml) ? '<div class="py-1 text-[11px] text-slate-400 italic">No items configured</div>' : ''}
                        </div>

                        <div class="flex items-center justify-between border-t border-slate-100 pt-1.5 text-[11px] font-bold text-slate-500">
                            <span>Total ${escapeHtml(h.display_name)}</span>
                            <span class="font-mono font-bold ${headerClass}">₹0.00</span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        modal.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    function closeShopOwnerPreview() {
        document.getElementById('shop-owner-preview-modal').classList.add('hidden');
    }

    // BUILD PAYLOAD
    function buildLayoutPayload() {
        const rootCards = document.querySelectorAll('#root-headers-list > .root-header-card');
        const headers = [];

        rootCards.forEach(card => {
            const headerId = card.dataset.headerId;
            const sourceId = card.dataset.sourceId ? parseInt(card.dataset.sourceId) : null;
            const originalName = card.dataset.originalName || '';
            const type = card.dataset.type || 'income';
            const productTagging = card.dataset.productTagging === '1';
            const showBothSides = card.dataset.showBothSides === '1';

            const nameInput = card.querySelector(':scope > .p-4 .display-name-input');
            const customDisplayName = nameInput ? nameInput.value.trim() : null;
            const displayName = customDisplayName || originalName;

            // Direct Items (Settings & Products)
            const settingIds = [];
            const productIds = [];
            const directItemObjs = [];
            card.querySelectorAll(':scope > .p-4 > .space-y-2 > .items-list > .item-row').forEach(row => {
                const sId = parseInt(row.dataset.settingId);
                const pId = parseInt(row.dataset.productId);
                const itemName = row.querySelector('.text-slate-900')?.textContent?.trim() || 'Item';

                if (sId) {
                    settingIds.push(sId);
                    directItemObjs.push({ id: sId, name: itemName, is_product: false });
                } else if (pId) {
                    productIds.push(pId);
                    directItemObjs.push({ id: pId, name: itemName, is_product: true });
                }
            });

            // Sub-Headers
            const subHeaders = [];
            card.querySelectorAll(':scope > .p-4 > .space-y-2 > .sub-headers-list > .sub-header-card').forEach(subCard => {
                const subId = subCard.dataset.subHeaderId;
                const subSourceId = subCard.dataset.sourceId ? parseInt(subCard.dataset.sourceId) : null;
                const subOrigName = subCard.dataset.originalName || '';
                const subType = subCard.dataset.type || 'income';
                const subProductTagging = subCard.dataset.productTagging === '1';

                const subNameInput = subCard.querySelector('.display-name-input');
                const subCustomDisplayName = subNameInput ? subNameInput.value.trim() : null;
                const subDisplayName = subCustomDisplayName || subOrigName;

                const subSettingIds = [];
                const subProductIds = [];
                const subItemObjs = [];
                subCard.querySelectorAll('.items-list > .item-row').forEach(row => {
                    const sId = parseInt(row.dataset.settingId);
                    const pId = parseInt(row.dataset.productId);
                    const itemName = row.querySelector('.text-slate-900')?.textContent?.trim() || 'Item';

                    if (sId) {
                        subSettingIds.push(sId);
                        subItemObjs.push({ id: sId, name: itemName, is_product: false });
                    } else if (pId) {
                        subProductIds.push(pId);
                        subItemObjs.push({ id: pId, name: itemName, is_product: true });
                    }
                });

                subHeaders.push({
                    id: subId,
                    source_id: subSourceId,
                    original_name: subOrigName,
                    display_name: subDisplayName,
                    custom_display_name: (subCustomDisplayName && subCustomDisplayName !== subOrigName) ? subCustomDisplayName : null,
                    type: subType,
                    product_tagging_enabled: subProductTagging,
                    setting_ids: subSettingIds,
                    product_ids: subProductIds,
                    items: subItemObjs,
                });
            });

            headers.push({
                id: headerId,
                source_id: sourceId,
                original_name: originalName,
                display_name: displayName,
                custom_display_name: (customDisplayName && customDisplayName !== originalName) ? customDisplayName : null,
                type: type,
                product_tagging_enabled: productTagging,
                show_both_sides: showBothSides,
                setting_ids: settingIds,
                product_ids: productIds,
                items: directItemObjs,
                sub_headers: subHeaders,
            });
        });

        return headers;
    }

    // SAVE LAYOUT
    async function saveLayout() {
        const saveBtn = document.getElementById('save-layout-btn');
        const saveLabel = document.getElementById('save-btn-label');
        if (saveBtn && saveBtn.disabled) return;

        if (saveBtn) saveBtn.disabled = true;
        if (saveLabel) saveLabel.textContent = 'Saving...';

        const headersPayload = buildLayoutPayload();

        try {
            const response = await fetch('{{ route('admin.cashbook.shop.cashbook-layout.save', $currentShopSlugOrId) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ headers: headersPayload })
            });

            const data = await response.json();
            if (response.ok && data.success) {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Shop Cashbook layout updated successfully.', 'success');
                } else {
                    alert(data.message || 'Shop Cashbook layout updated successfully.');
                }
            } else {
                alert(data.message || 'Error saving layout.');
            }
        } catch (err) {
            alert('An unexpected error occurred: ' + err.message);
        } finally {
            if (saveBtn) saveBtn.disabled = false;
            if (saveLabel) saveLabel.textContent = 'Save Layout';
        }
    }

    // RESET LAYOUT
    async function resetLayout() {
        if (!confirm('Are you sure you want to reset the visual layout to default? All custom display names and sub-header arrangements will revert to default.')) {
            return;
        }

        const resetBtn = document.getElementById('reset-layout-btn');
        if (resetBtn) resetBtn.disabled = true;

        try {
            const response = await fetch('{{ route('admin.cashbook.shop.cashbook-layout.reset', $currentShopSlugOrId) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({})
            });

            const data = await response.json();
            if (response.ok && data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Error resetting layout.');
                if (resetBtn) resetBtn.disabled = false;
            }
        } catch (err) {
            alert('An unexpected error occurred: ' + err.message);
            if (resetBtn) resetBtn.disabled = false;
        }
    }

    function escapeHtml(str) {
        if (typeof str !== 'string') return '';
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>
@endpush
@endsection
