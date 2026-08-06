<x-layouts.app title="Loadout — {{ $shopOrder->loadoutDisplayName() }}">
    <style>
        /* Remove browser default spinner arrows on number inputs */
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield;
        }
    </style>

    <div id="loadout-page-top" class="mx-auto flex w-full max-w-xl min-w-0 flex-col gap-4 py-3 pb-56 lg:px-4 lg:py-4 lg:pb-32">

        {{-- Header --}}
        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_12px_28px_rgba(15,23,42,0.16)]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(99,102,241,0.25),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#312e81_100%)] px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <a href="{{ route('warehouse.loadout.index') }}"
                           class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white hover:bg-white/20 transition-all border border-white/10 text-decoration-none">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </a>
                        <div>
                            <h1 class="text-base font-black tracking-tight text-white">{{ $shopOrder->loadoutDisplayName() }}</h1>
                            <p class="text-[9px] font-semibold text-indigo-300">
                                Order: <span class="font-mono">{{ $shopOrder->order_number }}</span>
                                &middot; {{ $shopOrder->business_date->format('d M Y') }}
                            </p>
                        </div>
                    </div>
                    @php
                        $statusLabels = [
                            'pending_delivery'   => ['Waiting for Loadout', 'bg-amber-400/20 text-amber-300 border-amber-400/30'],
                            'ready_for_dispatch' => ['Ready for Delivery',  'bg-emerald-400/20 text-emerald-300 border-emerald-400/30'],
                            'in_transit'         => ['Out for Delivery',    'bg-indigo-400/20 text-indigo-300 border-indigo-400/30'],
                            'delivered'          => ['Delivered',           'bg-slate-400/20 text-slate-300 border-slate-400/30'],
                        ];
                        [$statusText, $statusClass] = $statusLabels[$shopOrder->delivery_status] ?? ['Unknown', 'bg-slate-400/20 text-slate-300'];
                    @endphp
                    <span class="rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-wider {{ $statusClass }}">
                        {{ $statusText }}
                    </span>
                </div>
            </div>
        </section>

        @php
            $remainingProductCount = collect($productGroups)->filter(fn (array $group): bool => (float) $group['total_balance'] > 0.001)->count();
            $hasAnyDualMeasurement = collect($productGroups)->contains(
                fn (array $group): bool => (bool) ($group['use_dual_measurement_inputs'] ?? false)
            );
            $mobileTotalRows = (int) $shopOrder->items->count();
            $mobileLoadedRows = (int) $shopOrder->items->where('sorting_status', 'loaded')->count();
            $mobileAddonRows = (int) $shopOrder->items->filter(
                fn ($item): bool => str_contains((string) ($item->notes ?? ''), 'Addon item added from warehouse loadout.')
            )->count();
        @endphp

        @if($canEdit && $hasDuplicates)
            <section class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-amber-700">Merge Required</p>
                        <h2 class="mt-1 text-sm font-black text-amber-900">Duplicate product rows detected in this loadout.</h2>
                        <p class="mt-1 text-xs font-semibold text-amber-800">Merge duplicates before moving to delivery so billing always stays one product per invoice line.</p>
                    </div>
                    <form action="{{ route('warehouse.loadout.merge-duplicates.all', $shopOrder) }}"
                          method="POST"
                          class="loadout-confirm-form"
                          data-confirm-title="Merge all duplicates"
                          data-confirm-message="Merge all duplicate product rows now? This only normalizes rows and does not change stock movements."
                          data-confirm-button="Merge All">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-xl bg-amber-600 px-4 py-2 text-[11px] font-black uppercase tracking-wider text-white hover:bg-amber-700 border-none cursor-pointer">
                            Merge All Duplicates
                        </button>
                    </form>
                </div>

                <div class="mt-3 space-y-2">
                    @foreach($mergeCandidates as $candidate)
                        <div class="flex flex-col gap-2 rounded-xl border border-amber-200 bg-white px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-xs font-semibold text-slate-700">
                                <span class="font-black text-slate-900">{{ $candidate['product_name'] }}</span>
                                <span class="ml-1 text-[11px] text-amber-700">({{ $candidate['row_count'] }} rows)</span>
                            </div>
                            <form action="{{ route('warehouse.loadout.merge-duplicates', $shopOrder) }}"
                                  method="POST"
                                  class="loadout-confirm-form"
                                  data-confirm-title="Merge duplicate rows"
                                  data-confirm-message="Merge duplicate rows for {{ $candidate['product_name'] }}?"
                                  data-confirm-button="Merge Product">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $candidate['product_id'] }}">
                                <button type="submit"
                                        class="inline-flex items-center justify-center rounded-lg border border-amber-300 bg-amber-100 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-amber-800 hover:bg-amber-200 border-none cursor-pointer">
                                    Merge This Product
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if($canEdit || $canMoveToDelivery || $canMoveToPartialDelivery)
            <div class="grid gap-2 {{ ($canMoveToDelivery || $canMoveToPartialDelivery) && $canEdit ? 'grid-cols-2' : 'grid-cols-1' }}">
                @if($canMoveToDelivery)
                    <form action="{{ route('warehouse.loadout.move-to-delivery', $shopOrder) }}"
                          method="POST"
                          class="loadout-confirm-form"
                          data-confirm-title="Move to Delivery"
                          data-confirm-message="Move this order to delivery? This will reduce inventory for all loaded items."
                          data-confirm-button="Move to Delivery">
                        @csrf
                        <button type="submit"
                                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-xs font-black uppercase tracking-[0.14em] text-white shadow-sm transition-all hover:bg-emerald-700 active:scale-[0.98] border-none cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.25h11.25m0 0-3.75-3.75m3.75 3.75-3.75 3.75M14.25 15.75H21m0 0-3.75-3.75M21 15.75l-3.75 3.75" />
                            </svg>
                            Delivery
                        </button>
                    </form>
                @endif

                @if($canMoveToPartialDelivery)
                    <form action="{{ route('warehouse.loadout.move-to-partial-delivery', $shopOrder) }}"
                          method="POST"
                          class="loadout-confirm-form"
                          data-confirm-title="Move to Partial Delivery"
                          data-confirm-message="This order still has {{ $remainingProductCount }} product line(s) not fully loaded. Move it to delivery as a partial delivery?"
                          data-confirm-button="Move to Partial Delivery">
                        @csrf
                        <button type="submit"
                                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-amber-500 px-4 py-3 text-xs font-black uppercase tracking-[0.14em] text-white shadow-sm transition-all hover:bg-amber-600 active:scale-[0.98] border-none cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.34 4.94 2.94 17.76A1.5 1.5 0 0 0 4.24 20h15.52a1.5 1.5 0 0 0 1.3-2.24L13.66 4.94a1.5 1.5 0 0 0-2.6 0Z" />
                            </svg>
                            Delivery
                        </button>
                    </form>
                @endif

                @if($canMoveToLoadout)
                    <form action="{{ route('warehouse.loadout.move-to-loadout', $shopOrder) }}"
                          method="POST"
                          class="loadout-confirm-form"
                          data-confirm-title="Re-open Loadout"
                          data-confirm-message="Re-open this order for loadout quantity corrections?"
                          data-confirm-button="Re-open Loadout">
                        @csrf
                        <button type="submit"
                                class="flex w-full items-center justify-center gap-2 rounded-2xl bg-amber-600 px-4 py-3 text-xs font-black uppercase tracking-[0.14em] text-white shadow-sm transition-all hover:bg-amber-700 active:scale-[0.98] border-none cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                            Reopen
                        </button>
                    </form>
                @endif
            </div>
        @endif

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 space-y-1">
                @foreach($errors->all() as $error)
                    <p class="text-xs font-semibold text-rose-700">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @if(!empty($unpricedProductNames))
            <section class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-amber-700">Pricing Issue</p>
                        <h2 class="mt-1 text-sm font-black text-amber-900">{{ count($unpricedProductNames) }} item(s) have no approved selling price</h2>
                        <p class="mt-1 text-xs font-semibold text-amber-800">{{ implode(', ', $unpricedProductNames) }}</p>
                        <p class="mt-1 text-[11px] text-amber-700">These items are blocking invoice generation. Remove them to proceed with delivery.</p>
                    </div>
                    <form action="{{ route('warehouse.loadout.remove-unpriced-items', $shopOrder) }}"
                          method="POST"
                          class="loadout-confirm-form"
                          data-confirm-title="Remove unpriced items"
                          data-confirm-message="Remove {{ count($unpricedProductNames) }} item(s) with no approved selling price from this order? This cannot be undone."
                          data-confirm-button="Remove Items">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-xl bg-amber-600 px-4 py-2 text-[11px] font-black uppercase tracking-wider text-white hover:bg-amber-700 border-none cursor-pointer whitespace-nowrap">
                            Remove Unpriced Items
                        </button>
                    </form>
                </div>
            </section>
        @endif

        @php
            $loadoutProductCategories = collect($productGroups)
                ->map(fn (array $group): string => (string) ($group['product']->category?->name ?? 'Other'))
                ->filter()
                ->unique()
                ->sort()
                ->values();
        @endphp

        <section class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
            <div class="grid gap-1.5 sm:grid-cols-[1fr_140px_130px]">
                <div>
                    <label for="loadout-product-search" class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Find Product</label>
                    <input id="loadout-product-search" type="search" placeholder="Search product, SKU, category..." class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none">
                </div>

                {{-- Custom Tailwind Category Select --}}
                <div class="relative">
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Category</label>
                    <input type="hidden" id="loadout-product-category" value="">
                    <button type="button" id="category-select-trigger" class="flex h-9 w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-[11px] font-black text-slate-700 hover:border-indigo-500 hover:bg-white focus:outline-none cursor-pointer">
                        <span id="category-select-label" class="truncate">All Categories</span>
                        <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div id="category-select-panel" class="absolute left-0 right-0 top-full z-40 mt-1 max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg hidden space-y-0.5">
                        <button type="button" data-value="" data-label="All Categories" class="category-select-option flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-[11px] font-bold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer border-none bg-transparent">
                            <span>All Categories</span>
                        </button>
                        @foreach($loadoutProductCategories as $categoryName)
                            <button type="button" data-value="{{ strtolower($categoryName) }}" data-label="{{ $categoryName }}" class="category-select-option flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer border-none bg-transparent">
                                <span>{{ $categoryName }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Custom Tailwind Status Select --}}
                <div class="relative">
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Status</label>
                    <input type="hidden" id="loadout-product-status" value="">
                    <button type="button" id="status-select-trigger" class="flex h-9 w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-[11px] font-black text-slate-700 hover:border-indigo-500 hover:bg-white focus:outline-none cursor-pointer">
                        <span id="status-select-label" class="truncate">All Status</span>
                        <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div id="status-select-panel" class="absolute left-0 right-0 top-full z-40 mt-1 rounded-xl border border-slate-200 bg-white p-1 shadow-lg hidden space-y-0.5">
                        <button type="button" data-value="" data-label="All Status" class="status-select-option flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-[11px] font-bold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer border-none bg-transparent">
                            <span>All Status</span>
                        </button>
                        <button type="button" data-value="pending" data-label="Pending" class="status-select-option flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer border-none bg-transparent">
                            <span>Pending</span>
                        </button>
                        <button type="button" data-value="partial" data-label="Partial" class="status-select-option flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer border-none bg-transparent">
                            <span>Partial</span>
                        </button>
                        <button type="button" data-value="loaded" data-label="Loaded" class="status-select-option flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer border-none bg-transparent">
                            <span>Loaded</span>
                        </button>
                    </div>
                </div>
            </div>
            <p id="loadout-product-filter-count" class="mt-1.5 text-[10px] font-bold text-slate-500">{{ collect($productGroups)->count() }} product(s)</p>
        </section>

        @if($canEdit)
            @include('warehouse.loadout.partials.inline-addon', [
                'shopOrder' => $shopOrder,
                'addonProductsByCategory' => $addonProductsByCategory,
            ])
        @endif

        @if($canEdit)
            {{-- ─── LOADOUT FORM ─── --}}
            <form id="loadout-form"
                  action="{{ route('warehouse.loadout.save', $shopOrder) }}"
                  method="POST"
                class="space-y-2.5">
                @csrf

                {{-- Top action bar: Load All & Dual-row expand/collapse --}}
                <div class="flex flex-wrap items-center justify-between gap-1.5 px-0.5">
                    <h2 class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Items Checklist</h2>
                    <div class="flex items-center gap-1.5">
                        @if($hasAnyDualMeasurement)
                            <button type="button"
                                    id="toggle-all-cards-btn"
                                    onclick="toggleExpandAllCards()"
                                    class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 px-2.5 text-[9px] font-black uppercase tracking-wider text-slate-700 cursor-pointer transition-colors shadow-2xs">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                </svg>
                                <span id="toggle-all-cards-text">Collapse Dual</span>
                            </button>
                        @endif
                        <button type="button"
                                onclick="clearAllLoadout()"
                                class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-[9px] font-black uppercase tracking-wider text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 cursor-pointer">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                            <span>Clear All</span>
                        </button>
                        
                        {{-- Show ONCE Load All Full Button (Hidden if already 100% full) --}}
                        <button type="button"
                                id="load-all-full-btn"
                                onclick="loadAllFull()"
                                class="inline-flex h-8 items-center gap-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-2.5 text-[9px] font-black uppercase tracking-wider text-white border-none cursor-pointer transition-colors shadow-2xs">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Load All Full</span>
                        </button>
                    </div>
                </div>

                {{-- Manual Save Notice Bar --}}
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-2xs flex items-center justify-between gap-2">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Manual Save Mode</p>
                        <p class="mt-0.5 text-[11px] font-semibold text-slate-700">Update quantities, then click <strong>"Save Loadout"</strong> to save with confirmation popup.</p>
                    </div>
                    <button type="button"
                            onclick="confirmSaveLoadout()"
                            class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-xl bg-teal-600 px-3.5 py-2 text-[11px] font-black uppercase tracking-wider text-white hover:bg-teal-700 transition-all border-none cursor-pointer shadow-xs">
                        <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        <span>Save Loadout</span>
                    </button>
                </div>

                {{-- Loadout & Addon counts --}}
                <div class="flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-2xs text-[11px] font-black uppercase tracking-[0.08em] text-slate-700">
                    <div class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1">
                        <span>Loadout</span>
                        <span id="mobile-loadout-count" class="text-slate-900">{{ $mobileLoadedRows }}/{{ $mobileTotalRows }}</span>
                        <svg id="mobile-loadout-saved" class="hidden h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-indigo-700">
                        <span>Addon</span>
                        <span id="mobile-addon-count" class="text-indigo-900">{{ $mobileAddonRows }}</span>
                        <svg id="mobile-addon-saved" class="hidden h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="px-0.5 text-[10px] font-semibold text-slate-500">
                        Single-measure items stay open. Dual-measure items can be expanded.
                    </p>
                    <div id="loadout-action-feedback"
                        class="hidden rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2 text-[11px] font-semibold text-cyan-800 shadow-2xs">
                    </div>
                </div>

                {{-- Product rows (grouped) --}}
                <div class="space-y-2" id="loadout-product-list">
                    @foreach($productGroups as $group)
                        @php
                            $isFullyLoaded = $group['is_fully_loaded'];
                            $isPartial = $group['is_partially_loaded'];
                            $loadoutRowStatus = $isFullyLoaded ? 'loaded' : ($isPartial ? 'partial' : 'pending');
                            $loadoutCategoryName = $group['product']->category?->name ?? 'Other';
                            $approved = $group['total_approved'];
                            $loaded = $group['total_loaded'];
                            $loadedItem = $group['items']->firstWhere('sorting_status', 'loaded');
                            $loadedActualWeight = (float) ($loadedItem?->actual_weight ?? 0);
                            $balance = $group['total_balance'];
                            $available = $group['available_stock'];
                            $measurementCount = (int) ($group['measurement_count'] ?? 1);
                            $firstItem = $group['items'][0] ?? null;
                            $hasSecondaryUnit = (bool) ($group['has_secondary_unit'] ?? false);
                            $requestedUnitRaw = strtoupper((string) ($firstItem->requested_unit ?? ''));
                            $baseUnitRaw = strtoupper((string) ($group['unit'] ?? ''));
                            $useDualMeasurementInputs = (bool) ($group['use_dual_measurement_inputs'] ?? false)
                                && $measurementCount > 1
                                && $requestedUnitRaw !== ''
                                && $requestedUnitRaw !== $baseUnitRaw;
                            $requestedUnitName = $hasSecondaryUnit ? strtoupper($firstItem->requested_unit_label ?: $firstItem->requested_unit) : strtoupper($group['unit']);
                            $orderedUnitQty = $hasSecondaryUnit ? (float) ($group['requested_unit_total'] ?? $approved) : (float) $approved;
                            $loadedUnitQty = $hasSecondaryUnit ? (float) ($group['loaded_order_unit_qty'] ?? 0.0) : (float) $loaded;
                            $orderedUnitLabel = $hasSecondaryUnit
                                ? number_format($orderedUnitQty, 2, '.', '').' '.$requestedUnitName
                                : number_format($approved, 2).' '.strtoupper($group['unit']);
                            $isItemNotAvailable = $firstItem && ($firstItem->sorting_status === 'not_available' || $firstItem->loadout_discrepancy_type === 'not_available');
                            
                            $modalData = [
                                'id' => $group['product_id'],
                                'sl' => $loop->iteration,
                                'sku' => $group['product']->sku ?: $group['product_id'],
                                'name' => $group['product']->name,
                                'unit' => strtoupper($group['unit']),
                                'ordered' => number_format($approved, 2, '.', ''),
                                'loaded' => number_format($loaded, 2, '.', ''),
                                'available' => number_format($available, 2, '.', ''),
                                'is_not_available' => $isItemNotAvailable,
                            ];
                        @endphp

                        <div class="loadout-product-row overflow-hidden rounded-2xl border bg-white shadow-xs transition
                            {{ $isFullyLoaded ? 'border-emerald-200 bg-emerald-50/20' : ($isPartial ? 'border-amber-200 bg-amber-50/10' : 'border-slate-200') }}"
                             data-search="{{ strtolower(trim(($group['product']->name ?? '').' '.($group['product']->sku ?? '').' '.$loadoutCategoryName)) }}"
                             data-category="{{ strtolower($loadoutCategoryName) }}"
                             data-status="{{ $loadoutRowStatus }}">

                            @if($useDualMeasurementInputs)
                                {{-- Collapsible Card Header for Dual Measurement --}}
                                <button type="button"
                                        onclick="toggleProductCard({{ $group['product_id'] }})"
                                        class="flex w-full items-start justify-between gap-2 p-3 text-left border-none bg-transparent transition-colors cursor-pointer hover:bg-slate-50/60">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="truncate text-sm font-black text-slate-900">
                                            <span class="mr-1 inline-block rounded-md bg-indigo-100 px-1.5 py-0.5 text-[10px] font-black text-indigo-700">SL {{ $loop->iteration }}</span>
                                            <span class="inline-block rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-black text-slate-700 mr-1">#{{ $group['product']->sku ?: $group['product_id'] }}</span>
                                            {{ $group['product']->name }}
                                        </h3>

                                        <div class="mt-0.5 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-[10px] font-semibold text-slate-500">
                                            <span>Ordered: <span class="font-black text-slate-900">{{ $orderedUnitLabel }}</span></span>
                                            @if($hasSecondaryUnit)
                                                <span>Est.: <span class="font-bold text-slate-600">{{ number_format($approved, 2) }} {{ strtoupper($group['unit']) }}</span></span>
                                            @endif
                                            @if($loaded > 0)
                                                <span>Loaded: <span class="font-black text-emerald-700">{{ $hasSecondaryUnit ? number_format($loadedUnitQty, 2, '.', '').' '.$requestedUnitName.($loadedActualWeight > 0 ? ' ('.number_format($loadedActualWeight, 2).' '.strtoupper($group['unit']).')' : '') : number_format($loaded, 2).' '.strtoupper($group['unit']) }}</span></span>
                                            @endif
                                            @if($loaded > $approved)
                                                <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 border border-amber-200">
                                                    Excess: <span class="font-black">{{ number_format($loaded - $approved, 2) }} {{ strtoupper($group['unit']) }}</span>
                                                </span>
                                            @endif
                                            <span class="inline-flex items-center gap-1 rounded-md bg-sky-50 px-1.5 py-0.5 text-[10px] font-bold text-sky-800 border border-sky-200">
                                                Info Stock: <span class="font-black">{{ number_format($available, 2) }} {{ strtoupper($group['unit']) }}</span>
                                            </span>
                                            @if($isItemNotAvailable)
                                                <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-rose-700">Not Available ✕</span>
                                            @elseif($isFullyLoaded)
                                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-emerald-700">Loaded ✓</span>
                                            @elseif($isPartial)
                                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-amber-700">Partial</span>
                                            @else
                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-slate-600">Pending</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-1.5 mt-0.5 shrink-0">
                                        <svg id="arrow-{{ $group['product_id'] }}" class="h-4 w-4 text-slate-400 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </div>
                                </button>

                                {{-- Hidden fields for status & note --}}
                                <input type="hidden" id="status-field-{{ $group['product_id'] }}" name="item_status[{{ $group['product_id'] }}]" value="{{ $isItemNotAvailable ? 'not_available' : 'loaded' }}">
                                <input type="hidden" id="note-field-{{ $group['product_id'] }}" name="item_notes[{{ $group['product_id'] }}]" value="{{ $firstItem->loadout_discrepancy_note ?? '' }}">

                                {{-- Collapsible Body --}}
                                <div id="card-body-{{ $group['product_id'] }}" class="product-card-body collapsible-body hidden border-t border-slate-100 p-3 pt-2 bg-slate-50/40">
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        {{-- Loaded Secondary Unit Count Stepper --}}
                                        <div class="flex items-center justify-between gap-2 rounded-lg bg-white border border-slate-200 p-2">
                                            <span class="text-[11px] font-black text-slate-700">Loaded {{ $requestedUnitName }}:</span>
                                            <div class="flex items-center gap-1">
                                                <button type="button" onclick="stepUnitQty({{ $group['product_id'] }}, -1)" class="flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 cursor-pointer border-none font-bold text-sm">-</button>
                                                <input type="number" id="unit-qty-{{ $group['product_id'] }}" name="item_unit_qtys[{{ $group['product_id'] }}]" value="{{ number_format($loadedUnitQty, 2, '.', '') }}" min="0" step="any" inputmode="decimal" data-approved-unit="{{ number_format($orderedUnitQty, 2, '.', '') }}" class="h-7 w-14 rounded-md border border-slate-200 bg-white text-center text-[11px] font-black text-slate-900 focus:outline-none" {{ $isItemNotAvailable ? 'readonly' : '' }}>
                                                <button type="button" onclick="stepUnitQty({{ $group['product_id'] }}, 1)" class="flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 cursor-pointer border-none font-bold text-sm">+</button>
                                            </div>
                                        </div>

                                        {{-- Loaded Actual Weight Input --}}
                                        <div class="flex items-center justify-between gap-2 rounded-lg bg-white border border-slate-200 p-2">
                                            <span class="text-[11px] font-black text-slate-700">Loaded Weight ({{ strtoupper($group['unit']) }}):</span>
                                            <div class="flex items-center gap-1">
                                                <input type="number" id="qty-{{ $group['product_id'] }}" name="item_quantities[{{ $group['product_id'] }}]" value="{{ number_format($loadedActualWeight > 0 ? $loadedActualWeight : $loaded, 2, '.', '') }}" min="0" step="any" inputmode="decimal" data-approved="{{ number_format($approved, 2, '.', '') }}" data-available="{{ number_format($available, 2, '.', '') }}" data-product="{{ $group['product']->name }}" class="qty-input h-7 w-20 rounded-md border border-slate-200 bg-white text-right text-[11px] font-black text-slate-900 focus:outline-none px-1.5" {{ $isItemNotAvailable ? 'readonly' : '' }}>
                                                <button type="button" id="full-btn-{{ $group['product_id'] }}" onclick="setFullQuantity({{ $group['product_id'] }})" class="h-7 rounded-md border border-emerald-300 bg-emerald-50 px-2 text-[10px] font-black uppercase text-emerald-800 hover:bg-emerald-100 cursor-pointer border-none">Full</button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Quick Actions --}}
                                    <div class="mt-2 flex items-center justify-between border-t border-slate-200/60 pt-2 text-[11px]">
                                        <div class="flex items-center gap-2">
                                            <button type="button" onclick="markProductNotAvailable({{ $group['product_id'] }})" class="text-[10px] font-bold text-rose-600 hover:underline border-none bg-transparent cursor-pointer">Mark Not Available</button>
                                            <button type="button" onclick="markProductAvailable({{ $group['product_id'] }})" class="text-[10px] font-bold text-emerald-600 hover:underline border-none bg-transparent cursor-pointer">Reset</button>
                                        </div>
                                        <span id="row-status-text-{{ $group['product_id'] }}" class="text-[10px] font-bold text-slate-500"></span>
                                    </div>
                                </div>
                            @else
                                {{-- Single-Measure Product Body (MATCHES ORIGINAL CLEAN SINGLE-ROW DESIGN) --}}
                                <div class="p-2 sm:p-2.5">
                                    <div class="flex items-center justify-between gap-1.5 min-w-0">
                                        {{-- Product info inline in 1 row (Click opens modal) --}}
                                        <div class="flex items-center gap-1.5 min-w-0 flex-1 cursor-pointer" onclick="openSingleQtyModal({{ json_encode($modalData) }})">
                                            <span class="rounded-md bg-indigo-100 px-1.5 py-0.5 text-[9px] font-black text-indigo-700 shrink-0">SL {{ $loop->iteration }}</span>
                                            <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-black text-slate-700 shrink-0">#{{ $group['product']->sku ?: $group['product_id'] }}</span>
                                            
                                            <h3 class="truncate text-xs font-black text-slate-900 shrink-0 max-w-[110px] sm:max-w-xs" title="{{ $group['product']->name }}">{{ $group['product']->name }}</h3>

                                            <div class="flex items-center gap-1.5 text-[9px] font-semibold text-slate-500 shrink-0">
                                                <span>Ord: <span class="font-black text-slate-900">{{ number_format($approved, 2, '.', '') }}</span></span>
                                                <span class="text-sky-700">Stk: <span class="font-bold">{{ number_format($available, 2, '.', '') }}</span></span>
                                            </div>

                                            @if($isItemNotAvailable)
                                                <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-rose-700 shrink-0">Not Avail ✕</span>
                                            @elseif($isFullyLoaded)
                                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-emerald-700 shrink-0">LOADED ✓</span>
                                            @elseif($isPartial)
                                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-amber-700 shrink-0">Partial</span>
                                            @else
                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-slate-600 shrink-0">Pending</span>
                                            @endif
                                        </div>

                                        {{-- Inline Stepper & N/A button --}}
                                        <div class="flex items-center gap-1 shrink-0">
                                            <input type="hidden" id="status-field-{{ $group['product_id'] }}" name="item_status[{{ $group['product_id'] }}]" value="{{ $isItemNotAvailable ? 'not_available' : 'loaded' }}">
                                            <input type="hidden" id="note-field-{{ $group['product_id'] }}" name="item_notes[{{ $group['product_id'] }}]" value="{{ $firstItem->loadout_discrepancy_note ?? '' }}">

                                            <button type="button" onclick="stepQuantity({{ $group['product_id'] }}, -1)" class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 cursor-pointer border-none font-bold text-sm shadow-2xs">-</button>

                                            <div class="relative flex items-center">
                                                <input type="number" id="qty-{{ $group['product_id'] }}" name="item_quantities[{{ $group['product_id'] }}]" value="{{ number_format($loaded, 2, '.', '') }}" min="0" step="any" inputmode="decimal" data-approved="{{ number_format($approved, 2, '.', '') }}" data-available="{{ number_format($available, 2, '.', '') }}" data-product="{{ $group['product']->name }}" class="qty-input h-7 w-16 rounded-md border border-slate-200 px-1 text-center text-xs font-black focus:outline-none {{ $isItemNotAvailable ? 'bg-rose-50 text-rose-600 line-through' : 'bg-white text-slate-900' }}" {{ $isItemNotAvailable ? 'readonly' : '' }}>
                                                <span class="pointer-events-none absolute right-1 text-[8px] font-extrabold text-slate-400 uppercase">{{ strtoupper($group['unit']) }}</span>
                                            </div>

                                            <button type="button" onclick="stepQuantity({{ $group['product_id'] }}, 1)" class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 cursor-pointer border-none font-bold text-sm shadow-2xs">+</button>

                                            <button type="button" id="not-avail-btn-{{ $group['product_id'] }}" onclick="markProductNotAvailable({{ $group['product_id'] }})" class="h-7 rounded-md border border-rose-200 bg-rose-50 px-2 text-[9px] font-black uppercase text-rose-700 hover:bg-rose-100 cursor-pointer border-none shadow-2xs">
                                                N/A
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </form>
        @endif

        {{-- Floating Sticky Save Loadout Bar --}}
        @if($canEdit)
            <div class="sticky bottom-3 z-30 mx-auto w-full max-w-xl px-1">
                <button type="button"
                        id="floating-save-btn"
                        onclick="confirmSaveLoadout()"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-teal-600 px-5 py-3.5 text-xs font-black uppercase tracking-[0.14em] text-white shadow-xl hover:bg-teal-700 active:scale-98 transition-all border-none cursor-pointer">
                    <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    <span>SAVE LOADOUT</span>
                </button>
            </div>
        @endif

    </div>

    {{-- Product Quantity Modal Popup (Matches Screenshot 2) --}}
    <div id="single-qty-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs hidden">
        <div class="w-full max-w-sm overflow-hidden rounded-3xl bg-white p-5 shadow-2xl transition-all">
            {{-- Modal Header --}}
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                    <span id="modal-sl-badge" class="rounded-md bg-indigo-100 px-2 py-0.5 text-xs font-black text-indigo-700">SL 1</span>
                    <span id="modal-sku-badge" class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-black text-slate-700">#1</span>
                </div>
                <button type="button" onclick="closeSingleQtyModal()" class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-700 transition-colors border-none cursor-pointer">
                    ✕
                </button>
            </div>

            <h3 id="modal-product-name" class="mt-2 text-lg font-black text-slate-900">Product Name</h3>

            {{-- Stats Card (ORDERED / LOADED / STOCK) --}}
            <div class="mt-4 rounded-2xl bg-slate-50 p-3.5 border border-slate-100 grid grid-cols-3 text-center gap-2">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">ORDERED</p>
                    <p id="modal-stat-ordered" class="mt-0.5 text-xs font-black text-slate-900">0.00 KG</p>
                </div>
                <div>
                    <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">LOADED</p>
                    <p id="modal-stat-loaded" class="mt-0.5 text-xs font-black text-emerald-600">0.00 KG</p>
                </div>
                <div>
                    <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">STOCK</p>
                    <p id="modal-stat-stock" class="mt-0.5 text-xs font-black text-sky-600">0.00 KG</p>
                </div>
            </div>

            {{-- Loaded Quantity Stepper --}}
            <div class="mt-4">
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400">LOADED QUANTITY</label>
                <div class="mt-1.5 flex items-center gap-2">
                    <button type="button" onclick="modalStepQty(-1)" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 hover:bg-slate-200 text-xl font-black border-none cursor-pointer">-</button>
                    
                    <div class="relative flex-1">
                        <input type="number" id="modal-qty-input" step="any" min="0" class="h-12 w-full rounded-2xl border-2 border-indigo-600 bg-white text-center text-lg font-black text-slate-900 focus:outline-none pr-8">
                        <span id="modal-unit-label" class="pointer-events-none absolute right-3 top-3.5 text-xs font-black text-slate-400 uppercase">KG</span>
                    </div>

                    <button type="button" onclick="modalStepQty(1)" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 hover:bg-slate-200 text-xl font-black border-none cursor-pointer">+</button>
                </div>
            </div>

            {{-- Quick Action Buttons --}}
            <div class="mt-4 grid grid-cols-3 gap-2">
                <button type="button" onclick="modalSetFull()" class="rounded-xl border border-emerald-200 bg-emerald-50 py-2.5 text-xs font-black text-emerald-700 hover:bg-emerald-100 transition-colors border-none cursor-pointer">
                    Full Qty
                </button>
                <button type="button" onclick="modalSetZero()" class="rounded-xl border border-slate-200 bg-slate-100 py-2.5 text-xs font-black text-slate-700 hover:bg-slate-200 transition-colors border-none cursor-pointer">
                    Clear (0)
                </button>
                <button type="button" onclick="modalToggleNotAvail()" class="rounded-xl border border-rose-200 bg-rose-50 py-2.5 text-xs font-black text-rose-700 hover:bg-rose-100 transition-colors border-none cursor-pointer">
                    Not Avail
                </button>
            </div>

            {{-- Primary SAVE & APPLY Button --}}
            <button type="button" onclick="saveSingleQtyModal()" class="mt-4 w-full rounded-2xl bg-indigo-600 py-3.5 text-xs font-black uppercase tracking-wider text-white shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all border-none cursor-pointer">
                SAVE & APPLY
            </button>
        </div>
    </div>

    @push('scripts')
    <script>
        const shopOrderId = '{{ $shopOrder->id }}';
        let currentModalProductId = null;

        function openSingleQtyModal(data) {
            currentModalProductId = data.id;

            const modal = document.getElementById('single-qty-modal');
            const slBadge = document.getElementById('modal-sl-badge');
            const skuBadge = document.getElementById('modal-sku-badge');
            const nameEl = document.getElementById('modal-product-name');
            const orderedEl = document.getElementById('modal-stat-ordered');
            const loadedEl = document.getElementById('modal-stat-loaded');
            const stockEl = document.getElementById('modal-stat-stock');
            const qtyInput = document.getElementById('modal-qty-input');
            const unitLabel = document.getElementById('modal-unit-label');

            if (slBadge) slBadge.textContent = 'SL ' + data.sl;
            if (skuBadge) skuBadge.textContent = '#' + data.sku;
            if (nameEl) nameEl.textContent = data.name;
            if (orderedEl) orderedEl.textContent = data.ordered + ' ' + data.unit;
            if (loadedEl) loadedEl.textContent = data.loaded + ' ' + data.unit;
            if (stockEl) stockEl.textContent = data.available + ' ' + data.unit;
            if (unitLabel) unitLabel.textContent = data.unit;

            const targetInput = document.getElementById('qty-' + data.id);
            if (targetInput && qtyInput) {
                qtyInput.value = targetInput.value;
                qtyInput.dataset.approved = targetInput.dataset.approved;
            }

            if (modal) modal.classList.remove('hidden');
        }

        function closeSingleQtyModal() {
            const modal = document.getElementById('single-qty-modal');
            if (modal) modal.classList.add('hidden');
            currentModalProductId = null;
        }

        function modalStepQty(step) {
            const qtyInput = document.getElementById('modal-qty-input');
            if (!qtyInput) return;
            let val = parseFloat(qtyInput.value) || 0;
            val = Math.max(0, val + step);
            qtyInput.value = val.toFixed(2);
        }

        function modalSetFull() {
            const qtyInput = document.getElementById('modal-qty-input');
            if (!qtyInput) return;
            const approved = qtyInput.dataset.approved || '0.00';
            qtyInput.value = parseFloat(approved).toFixed(2);
        }

        function modalSetZero() {
            const qtyInput = document.getElementById('modal-qty-input');
            if (!qtyInput) return;
            qtyInput.value = '0.00';
        }

        function modalToggleNotAvail() {
            if (!currentModalProductId) return;
            markProductNotAvailable(currentModalProductId);
            closeSingleQtyModal();
        }

        function saveSingleQtyModal() {
            if (!currentModalProductId) return;
            const modalQtyInput = document.getElementById('modal-qty-input');
            const targetInput = document.getElementById('qty-' + currentModalProductId);

            if (targetInput && modalQtyInput) {
                markProductAvailable(currentModalProductId);
                targetInput.value = parseFloat(modalQtyInput.value || 0).toFixed(2);
                targetInput.dispatchEvent(new Event('change'));
                pulseInput(targetInput);
            }

            closeSingleQtyModal();
        }

        function checkLoadAllButtonVisibility() {
            const btn = document.getElementById('load-all-full-btn');
            if (!btn) return;

            const qtyInputs = Array.from(document.querySelectorAll('.qty-input'));
            const totalRows = qtyInputs.length;
            if (totalRows === 0) return;

            const isUsed = localStorage.getItem('load_all_used_' + shopOrderId) === 'true';
            
            // Check if all products are loaded (entered >= approved or > 0)
            const loadedRows = qtyInputs.filter((input) => {
                const productId = input.id.replace('qty-', '');
                const statusField = document.getElementById('status-field-' + productId);
                const isNotAvailable = statusField && statusField.value === 'not_available';
                const qty = parseFloat(input.value) || 0;
                return !isNotAvailable && qty > 0.0001;
            }).length;

            const allLoaded = loadedRows === totalRows;

            if (isUsed || allLoaded) {
                btn.style.display = 'none';
            } else {
                btn.style.display = 'inline-flex';
            }
        }

        function toggleExpandAllCards() {
            const bodies = document.querySelectorAll('.collapsible-body');
            const textSpan = document.getElementById('toggle-all-cards-text');
            const isExpanding = textSpan ? textSpan.textContent.includes('Expand') : false;

            bodies.forEach(function (body) {
                const productId = body.id.replace('card-body-', '');
                const arrow = document.getElementById('arrow-' + productId);

                if (isExpanding) {
                    body.classList.remove('hidden');
                    if (arrow) arrow.style.transform = 'rotate(180deg)';
                } else {
                    body.classList.add('hidden');
                    if (arrow) arrow.style.transform = 'rotate(0deg)';
                }
            });

            if (textSpan) {
                textSpan.textContent = isExpanding ? 'Collapse Dual' : 'Expand Dual';
            }
        }

        function toggleProductCard(productId) {
            const body = document.getElementById('card-body-' + productId);
            const arrow = document.getElementById('arrow-' + productId);

            if (!body) return;

            const isHidden = body.classList.contains('hidden');
            if (isHidden) {
                body.classList.remove('hidden');
                if (arrow) arrow.style.transform = 'rotate(180deg)';
            } else {
                body.classList.add('hidden');
                if (arrow) arrow.style.transform = 'rotate(0deg)';
            }
        }

        function formatLoadoutQty(val) {
            const num = parseFloat(val);
            if (isNaN(num)) return '0.00';
            return num.toFixed(2);
        }

        function pulseInput(el) {
            if (!el) return;
            el.style.backgroundColor = '#ecfdf5';
            setTimeout(function () {
                el.style.backgroundColor = '';
            }, 500);
        }

        function updateFullButtonState(productId, enteredQty, approvedQty) {
            const fullBtn = document.getElementById('full-btn-' + productId);
            if (!fullBtn) return;

            const isFull = Math.abs(enteredQty - approvedQty) < 0.001 && approvedQty > 0;
            if (isFull) {
                fullBtn.classList.remove('border-emerald-300', 'bg-emerald-50', 'text-emerald-800');
                fullBtn.classList.add('border-emerald-600', 'bg-emerald-600', 'text-white');
                fullBtn.textContent = 'Full ✓';
            } else {
                fullBtn.classList.remove('border-emerald-600', 'bg-emerald-600', 'text-white');
                fullBtn.classList.add('border-emerald-300', 'bg-emerald-50', 'text-emerald-800');
                fullBtn.textContent = 'Full';
            }
        }

        function setRowStatus(productId, text) {
            const statusEl = document.getElementById('row-status-text-' + productId);
            if (statusEl) {
                statusEl.textContent = text;
            }
        }

        function markProductNotAvailable(productId) {
            const statusField = document.getElementById('status-field-' + productId);
            const noteField = document.getElementById('note-field-' + productId);
            const qtyInput = document.getElementById('qty-' + productId);
            const unitQtyInput = document.getElementById('unit-qty-' + productId);

            if (statusField) statusField.value = 'not_available';
            if (noteField) noteField.value = 'Marked not available during loadout.';

            if (qtyInput) {
                qtyInput.value = '0.00';
                qtyInput.readOnly = true;
                pulseInput(qtyInput);
            }

            if (unitQtyInput) {
                unitQtyInput.value = '0.00';
                unitQtyInput.readOnly = true;
                pulseInput(unitQtyInput);
            }

            setRowStatus(productId, 'Marked Not Available');
            checkLoadAllButtonVisibility();
        }

        function markProductAvailable(productId) {
            const statusField = document.getElementById('status-field-' + productId);
            const noteField = document.getElementById('note-field-' + productId);
            const qtyInput = document.getElementById('qty-' + productId);
            const unitQtyInput = document.getElementById('unit-qty-' + productId);

            if (statusField) statusField.value = 'loaded';
            if (noteField) noteField.value = '';

            if (qtyInput) {
                qtyInput.readOnly = false;
            }

            if (unitQtyInput) {
                unitQtyInput.readOnly = false;
            }

            setRowStatus(productId, 'Reset to available');
            checkLoadAllButtonVisibility();
        }

        function setFullQuantity(productId) {
            const qtyInput = document.getElementById('qty-' + productId);
            if (!qtyInput) return;

            markProductAvailable(productId);
            const approved = qtyInput.dataset.approved;
            qtyInput.value = approved;
            qtyInput.dispatchEvent(new Event('change'));
            pulseInput(qtyInput);

            const unitQtyInput = document.getElementById('unit-qty-' + productId);
            if (unitQtyInput) {
                unitQtyInput.value = unitQtyInput.dataset.approvedUnit || '0.00';
                unitQtyInput.dispatchEvent(new Event('change'));
                pulseInput(unitQtyInput);
            }
            checkLoadAllButtonVisibility();
        }

        function stepQuantity(productId, step) {
            const qtyInput = document.getElementById('qty-' + productId);
            if (!qtyInput || qtyInput.readOnly) return;

            markProductAvailable(productId);
            let current = parseFloat(qtyInput.value) || 0;
            current = Math.max(0, current + step);
            qtyInput.value = current.toFixed(2);
            qtyInput.dispatchEvent(new Event('change'));
            pulseInput(qtyInput);
            checkLoadAllButtonVisibility();
        }

        function stepUnitQty(productId, step) {
            const unitInput = document.getElementById('unit-qty-' + productId);
            if (!unitInput || unitInput.readOnly) return;

            markProductAvailable(productId);
            let current = parseFloat(unitInput.value) || 0;
            current = Math.max(0, current + step);
            unitInput.value = current.toFixed(2);
            unitInput.dispatchEvent(new Event('change'));
            pulseInput(unitInput);
            checkLoadAllButtonVisibility();
        }

        {{-- SHOW ONCE: Load All Full Button with Confirmation --}}
        function loadAllFull() {
            if (localStorage.getItem('load_all_used_' + shopOrderId) === 'true') {
                window.showAppConfirm({
                    title: 'Action Already Used',
                    message: 'Load All Full has already been performed for this order and cannot be clicked again.',
                    confirmLabel: 'OK',
                    tone: 'info',
                });
                return;
            }

            window.showAppConfirm({
                title: 'Load All Full',
                message: 'Are you sure you want to load all items to 100% full approved quantity? This action can only be done ONCE per order to prevent accidental clicks.',
                confirmLabel: 'Load All Full',
                cancelLabel: 'Cancel',
                tone: 'success',
                onConfirm: function () {
                    let changedCount = 0;
                    document.querySelectorAll('.qty-input').forEach(function (input) {
                        const productId = input.id.replace('qty-', '');
                        markProductAvailable(productId);

                        const approved = formatLoadoutQty(input.dataset.approved);
                        const unitInput = document.getElementById('unit-qty-' + productId);

                        input.value = approved;
                        input.dispatchEvent(new Event('change'));
                        pulseInput(input);

                        if (unitInput) {
                            unitInput.value = formatLoadoutQty(unitInput.dataset.approvedUnit);
                            unitInput.dispatchEvent(new Event('change'));
                            pulseInput(unitInput);
                        }
                        changedCount++;
                    });

                    // Hide Load All Full button so it can never be clicked again
                    localStorage.setItem('load_all_used_' + shopOrderId, 'true');
                    checkLoadAllButtonVisibility();

                    showLoadoutFeedback('Loaded all products to full quantity. Click "Save Loadout" to save changes.', 'success');
                }
            });
        }

        function clearAllLoadout() {
            window.showAppConfirm({
                title: 'Clear All Quantities',
                message: 'Are you sure you want to reset all product quantities to 0.00?',
                confirmLabel: 'Clear All',
                cancelLabel: 'Cancel',
                tone: 'danger',
                onConfirm: function () {
                    document.querySelectorAll('.qty-input').forEach(function (input) {
                        const productId = input.id.replace('qty-', '');
                        const unitInput = document.getElementById('unit-qty-' + productId);

                        markProductAvailable(productId);
                        input.value = '0.00';
                        input.dispatchEvent(new Event('change'));
                        pulseInput(input);

                        if (unitInput) {
                            unitInput.value = '0.00';
                            unitInput.dispatchEvent(new Event('change'));
                            pulseInput(unitInput);
                        }
                    });

                    // Reset single-use state when Clear All is confirmed
                    localStorage.removeItem('load_all_used_' + shopOrderId);
                    checkLoadAllButtonVisibility();

                    showLoadoutFeedback('All quantities cleared to 0.00.', 'info');
                }
            });
        }

        function showLoadoutFeedback(text, type) {
            const feedbackEl = document.getElementById('loadout-action-feedback');
            if (!feedbackEl) return;

            feedbackEl.classList.remove('hidden', 'border-rose-200', 'bg-rose-50', 'text-rose-800', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-800', 'border-cyan-200', 'bg-cyan-50', 'text-cyan-800');
            if (type === 'success') {
                feedbackEl.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-800');
            } else if (type === 'info') {
                feedbackEl.classList.add('border-cyan-200', 'bg-cyan-50', 'text-cyan-800');
            } else {
                feedbackEl.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-800');
            }
            feedbackEl.textContent = text;
            setTimeout(function () { feedbackEl.classList.add('hidden'); }, 3500);
        }

        {{-- MANUAL SAVE ONLY: Confirm Popup Modal before Saving --}}
        function confirmSaveLoadout() {
            const loadoutForm = document.getElementById('loadout-form');
            if (!loadoutForm) return;

            window.showAppConfirm({
                title: 'Save Loadout Quantities',
                message: 'Are you sure you want to save current loadout quantities and update warehouse stock?',
                confirmLabel: 'Save Loadout',
                cancelLabel: 'Cancel',
                tone: 'primary',
                onConfirm: function () {
                    executeLoadoutSave();
                }
            });
        }

        async function executeLoadoutSave() {
            const loadoutForm = document.getElementById('loadout-form');
            const feedbackEl = document.getElementById('loadout-action-feedback');
            const floatingBtn = document.getElementById('floating-save-btn');
            
            if (!loadoutForm) return;

            if (floatingBtn) {
                floatingBtn.disabled = true;
                floatingBtn.innerHTML = '<span>SAVING...</span>';
            }

            if (feedbackEl) {
                feedbackEl.classList.remove('hidden', 'border-rose-200', 'bg-rose-50', 'text-rose-800', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-800');
                feedbackEl.classList.add('border-cyan-200', 'bg-cyan-50', 'text-cyan-800');
                feedbackEl.textContent = 'Saving loadout quantities & updating stock...';
            }

            try {
                const formData = new FormData(loadoutForm);
                const response = await fetch(loadoutForm.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });

                const data = await response.json().catch(() => ({}));

                if (response.ok || response.status === 200) {
                    if (feedbackEl) {
                        feedbackEl.classList.remove('border-cyan-200', 'bg-cyan-50', 'text-cyan-800');
                        feedbackEl.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-800');
                        const duplicateCount = Number(data.duplicate_count || 0);
                        feedbackEl.textContent = duplicateCount > 0
                            ? 'Loadout saved. Duplicate rows still exist for ' + duplicateCount + ' product(s). Merge them from top warning panel.'
                            : 'Loadout saved & stock updated successfully! ✓';
                        setTimeout(() => { feedbackEl.classList.add('hidden'); }, 3500);
                    }

                    if (floatingBtn) {
                        floatingBtn.disabled = false;
                        floatingBtn.innerHTML = '<span>SAVED ✓</span>';
                        setTimeout(() => {
                            floatingBtn.innerHTML = '<svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg><span>SAVE LOADOUT</span>';
                        }, 2000);
                    }
                } else {
                    if (feedbackEl) {
                        feedbackEl.classList.remove('border-cyan-200', 'bg-cyan-50', 'text-cyan-800');
                        feedbackEl.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-800');
                        feedbackEl.textContent = data.message || 'Error saving loadout. Please check inputs.';
                    }
                    if (floatingBtn) {
                        floatingBtn.disabled = false;
                        floatingBtn.innerHTML = '<span>SAVE LOADOUT</span>';
                    }
                }
            } catch (err) {
                if (feedbackEl) {
                    feedbackEl.classList.remove('border-cyan-200', 'bg-cyan-50', 'text-cyan-800');
                    feedbackEl.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-800');
                    feedbackEl.textContent = 'Network error while saving loadout.';
                }
                if (floatingBtn) {
                    floatingBtn.disabled = false;
                    floatingBtn.innerHTML = '<span>SAVE LOADOUT</span>';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            checkLoadAllButtonVisibility();

            const productSearch = document.getElementById('loadout-product-search');
            const productCategory = document.getElementById('loadout-product-category');
            const productStatus = document.getElementById('loadout-product-status');
            const productCount = document.getElementById('loadout-product-filter-count');
            const productRows = Array.from(document.querySelectorAll('.loadout-product-row'));
            const productEmpty = document.getElementById('loadout-product-empty');

            const mobileLoadoutCount = document.getElementById('mobile-loadout-count');
            const mobileLoadoutSaved = document.getElementById('mobile-loadout-saved');
            const mobileAddonCount = document.getElementById('mobile-addon-count');
            const mobileAddonSaved = document.getElementById('mobile-addon-saved');

            const inlineAddonToggle = document.getElementById('inline-addon-toggle-btn');
            const inlineAddonPanel = document.getElementById('inline-addon-panel');
            const inlineAddonCombobox = document.getElementById('inline-addon-combobox');
            const inlineAddonTrigger = document.getElementById('inline-addon-trigger');
            const inlineAddonDropdown = document.getElementById('inline-addon-dropdown');
            const inlineAddonSearch = document.getElementById('inline-addon-search');
            const inlineAddonHiddenInput = document.getElementById('inline-addon-product-id');
            const inlineAddonSelectedLabel = document.getElementById('inline-addon-selected-label');
            const inlineAddonOptions = Array.from(document.querySelectorAll('.inline-addon-option'));
            const inlineAddonGroups = Array.from(document.querySelectorAll('.inline-addon-category-group'));
            const inlineAddonEmpty = document.getElementById('inline-addon-combobox-empty');

            function updateMobileTopInfo() {
                const qtyInputs = Array.from(document.querySelectorAll('.qty-input'));
                const totalRows = qtyInputs.length;
                const loadedRows = qtyInputs.filter((input) => {
                    const productId = input.id.replace('qty-', '');
                    const statusField = document.getElementById('status-field-' + productId);
                    const isNotAvailable = statusField && statusField.value === 'not_available';
                    const qty = parseFloat(input.value) || 0;

                    return !isNotAvailable && qty > 0.0001;
                }).length;

                if (mobileLoadoutCount) {
                    mobileLoadoutCount.textContent = loadedRows + '/' + totalRows;
                }

                if (mobileAddonCount) {
                    mobileAddonCount.textContent = mobileAddonCount.textContent || '0';
                }

                checkLoadAllButtonVisibility();
            }

            function markMobileSavedState() {
                mobileLoadoutSaved?.classList.remove('hidden');
                mobileAddonSaved?.classList.remove('hidden');
            }

            function filterLoadoutProducts() {
                const query = (productSearch?.value || '').trim().toLowerCase();
                const category = productCategory?.value || '';
                const status = productStatus?.value || '';
                let visibleCount = 0;

                productRows.forEach(function (row) {
                    const matchesSearch = query === '' || (row.dataset.search || '').includes(query);
                    const matchesCategory = category === '' || row.dataset.category === category;
                    const matchesStatus = status === '' || row.dataset.status === status;

                    if (matchesSearch && matchesCategory && matchesStatus) {
                        row.classList.remove('hidden');
                        visibleCount++;
                    } else {
                        row.classList.add('hidden');
                    }
                });

                if (productEmpty) {
                    productEmpty.classList.toggle('hidden', visibleCount > 0);
                }

                if (productCount) {
                    productCount.textContent = visibleCount + ' of ' + productRows.length + ' product(s)';
                }
            }

            productSearch?.addEventListener('input', filterLoadoutProducts);
            productCategory?.addEventListener('change', filterLoadoutProducts);
            productStatus?.addEventListener('change', filterLoadoutProducts);

            // Custom Tailwind Category & Status Select Handlers
            const categoryTrigger = document.getElementById('category-select-trigger');
            const categoryPanel = document.getElementById('category-select-panel');
            const categoryLabel = document.getElementById('category-select-label');
            const categoryHidden = document.getElementById('loadout-product-category');
            const categoryOptions = document.querySelectorAll('.category-select-option');

            const statusTrigger = document.getElementById('status-select-trigger');
            const statusPanel = document.getElementById('status-select-panel');
            const statusLabel = document.getElementById('status-select-label');
            const statusHidden = document.getElementById('loadout-product-status');
            const statusOptions = document.querySelectorAll('.status-select-option');

            function toggleCategoryDropdown(show) {
                if (!categoryPanel) return;
                const willShow = show !== undefined ? show : categoryPanel.classList.contains('hidden');
                categoryPanel.classList.toggle('hidden', !willShow);
                if (willShow && statusPanel) statusPanel.classList.add('hidden');
            }

            function toggleStatusDropdown(show) {
                if (!statusPanel) return;
                const willShow = show !== undefined ? show : statusPanel.classList.contains('hidden');
                statusPanel.classList.toggle('hidden', !willShow);
                if (willShow && categoryPanel) categoryPanel.classList.add('hidden');
            }

            if (categoryTrigger) {
                categoryTrigger.addEventListener('click', function (e) {
                    e.stopPropagation();
                    toggleCategoryDropdown();
                });
            }

            if (statusTrigger) {
                statusTrigger.addEventListener('click', function (e) {
                    e.stopPropagation();
                    toggleStatusDropdown();
                });
            }

            categoryOptions.forEach(function (opt) {
                opt.addEventListener('click', function () {
                    const val = opt.dataset.value;
                    const label = opt.dataset.label;
                    if (categoryHidden) categoryHidden.value = val;
                    if (categoryLabel) categoryLabel.textContent = label;
                    toggleCategoryDropdown(false);
                    filterLoadoutProducts();
                });
            });

            statusOptions.forEach(function (opt) {
                opt.addEventListener('click', function () {
                    const val = opt.dataset.value;
                    const label = opt.dataset.label;
                    if (statusHidden) statusHidden.value = val;
                    if (statusLabel) statusLabel.textContent = label;
                    toggleStatusDropdown(false);
                    filterLoadoutProducts();
                });
            });

            document.addEventListener('click', function (e) {
                if (categoryPanel && !categoryPanel.contains(e.target) && categoryTrigger && !categoryTrigger.contains(e.target)) {
                    toggleCategoryDropdown(false);
                }
                if (statusPanel && !statusPanel.contains(e.target) && statusTrigger && !statusTrigger.contains(e.target)) {
                    toggleStatusDropdown(false);
                }
            });

            filterLoadoutProducts();

            if (inlineAddonToggle && inlineAddonPanel) {
                inlineAddonToggle.addEventListener('click', function () {
                    inlineAddonPanel.classList.toggle('hidden');
                });
            }

            if (
                inlineAddonCombobox
                && inlineAddonTrigger
                && inlineAddonDropdown
                && inlineAddonSearch
                && inlineAddonHiddenInput
                && inlineAddonSelectedLabel
                && inlineAddonEmpty
            ) {
                const normalize = (value) => (value || '').toString().trim().toLowerCase();

                const closeInlineAddonDropdown = () => {
                    inlineAddonDropdown.classList.add('hidden');
                    inlineAddonTrigger.classList.remove('border-indigo-500', 'bg-white');
                };

                const openInlineAddonDropdown = () => {
                    inlineAddonDropdown.classList.remove('hidden');
                    inlineAddonTrigger.classList.add('border-indigo-500', 'bg-white');
                    inlineAddonSearch.focus();
                    filterInlineAddonOptions();
                };

                const selectInlineAddonOption = (option) => {
                    inlineAddonHiddenInput.value = option.dataset.value || '';
                    inlineAddonSelectedLabel.textContent = option.dataset.label || 'Select addon product';

                    inlineAddonOptions.forEach((item) => item.classList.remove('bg-indigo-100', 'text-indigo-800'));
                    option.classList.add('bg-indigo-100', 'text-indigo-800');

                    closeInlineAddonDropdown();
                };

                function filterInlineAddonOptions() {
                    const query = normalize(inlineAddonSearch.value);
                    let visibleCount = 0;

                    inlineAddonGroups.forEach((group) => {
                        const groupOptions = inlineAddonOptions.filter((option) => option.closest('.inline-addon-category-group') === group);
                        let groupVisible = 0;

                        groupOptions.forEach((option) => {
                            const searchText = normalize(option.dataset.search);
                            const visible = query === '' || searchText.includes(query);
                            option.classList.toggle('hidden', !visible);
                            if (visible) {
                                visibleCount++;
                                groupVisible++;
                            }
                        });

                        group.classList.toggle('hidden', groupVisible === 0);
                    });

                    inlineAddonEmpty.classList.toggle('hidden', visibleCount > 0);
                }

                inlineAddonTrigger.addEventListener('click', () => {
                    if (inlineAddonDropdown.classList.contains('hidden')) {
                        openInlineAddonDropdown();
                    } else {
                        closeInlineAddonDropdown();
                    }
                });

                inlineAddonSearch.addEventListener('input', filterInlineAddonOptions);
                inlineAddonSearch.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeInlineAddonDropdown();
                    }
                });

                inlineAddonOptions.forEach((option) => {
                    option.addEventListener('click', () => selectInlineAddonOption(option));
                });

                document.addEventListener('click', (event) => {
                    if (!inlineAddonCombobox.contains(event.target)) {
                        closeInlineAddonDropdown();
                    }
                });

                const initialValue = (inlineAddonHiddenInput.value || '').toString();
                if (initialValue) {
                    const initialOption = inlineAddonOptions.find((option) => (option.dataset.value || '') === initialValue);
                    if (initialOption) {
                        inlineAddonSelectedLabel.textContent = initialOption.dataset.label || inlineAddonSelectedLabel.textContent;
                        initialOption.classList.add('bg-indigo-100', 'text-indigo-800');
                    }
                }
            }

            document.querySelectorAll('.qty-input').forEach(function (input) {
                const approved = parseFloat(input.dataset.approved);
                const entered = parseFloat(input.value) || 0;
                const productId = input.id.replace('qty-', '');
                updateFullButtonState(productId, entered, approved);
            });

            document.querySelectorAll('.loadout-confirm-form').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (form.dataset.appConfirmBypass === 'true') {
                        form.dataset.appConfirmBypass = 'false';
                        return;
                    }
                    event.preventDefault();
                    window.showAppConfirm({
                        title: form.dataset.confirmTitle || 'Confirm',
                        message: form.dataset.confirmMessage || 'Are you sure?',
                        confirmLabel: form.dataset.confirmButton || 'Confirm',
                        cancelLabel: 'Cancel',
                        tone: 'danger',
                        onConfirm: function () {
                            form.dataset.appConfirmBypass = 'true';
                            HTMLFormElement.prototype.submit.call(form);
                        },
                    });
                });
            });

            // Inline validation: warn if entered qty > available (NO AUTOSAVE)
            document.querySelectorAll('.qty-input').forEach(function (input) {
                const onQtyChanged = function () {
                    const approved = parseFloat(input.dataset.approved);
                    const available = parseFloat(input.dataset.available);
                    const entered = parseFloat(input.value) || 0;
                    const productId = input.id.replace('qty-', '');
                    const normalizedEntered = parseFloat(input.value) || 0;
                    updateFullButtonState(productId, normalizedEntered, approved);

                    if (normalizedEntered > available) {
                        input.style.borderColor = '#f59e0b';
                        input.title = 'Notice: exceeds available stock (' + available.toFixed(2) + ')';
                        setRowStatus(productId, 'Exceeds available stock (' + available.toFixed(2) + ')');
                    } else {
                        input.style.borderColor = '';
                        input.title = '';
                        if (normalizedEntered >= approved - 0.001 && approved > 0) {
                            setRowStatus(productId, 'Ready at full quantity');
                        } else if (normalizedEntered > 0) {
                            setRowStatus(productId, 'Custom quantity set');
                        } else {
                            setRowStatus(productId, 'No quantity selected');
                        }
                    }

                    updateMobileTopInfo();
                };

                input.addEventListener('input', onQtyChanged);
                input.addEventListener('change', onQtyChanged);
            });

            document.querySelectorAll('[id^="unit-qty-"]').forEach(function (input) {
                const onUnitQtyChanged = function () {
                    updateMobileTopInfo();
                };

                input.addEventListener('input', onUnitQtyChanged);
                input.addEventListener('change', onUnitQtyChanged);
            });

            updateMobileTopInfo();
        });
    </script>
    @endpush
</x-layouts.app>
