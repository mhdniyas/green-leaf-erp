<x-layouts.app title="Loadout — {{ $shopOrder->loadoutDisplayName() }}">
    <div id="loadout-page-top"
        class="mx-auto flex w-full max-w-xl min-w-0 flex-col gap-4 py-3 pb-56 lg:px-4 lg:py-4 lg:pb-32">

        {{-- Header --}}
        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_12px_28px_rgba(15,23,42,0.16)]">
            <div
                class="bg-[radial-gradient(circle_at_top_left,_rgba(99,102,241,0.25),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#312e81_100%)] px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <a href="{{ route('warehouse.loadout.index') }}"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white hover:bg-white/20 transition-all border border-white/10 text-decoration-none">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </a>
                        <div>
                            <h1 class="text-base font-black tracking-tight text-white">
                                {{ $shopOrder->loadoutDisplayName() }}
                            </h1>
                            <p class="text-[9px] font-semibold text-indigo-300">
                                Order: <span class="font-mono">{{ $shopOrder->order_number }}</span>
                                &middot; {{ $shopOrder->business_date->format('d M Y') }}
                            </p>
                        </div>
                    </div>
                    @php
                        $statusLabels = [
                            'pending_delivery' => ['Waiting for Loadout', 'bg-amber-400/20 text-amber-300 border-amber-400/30'],
                            'ready_for_dispatch' => ['Ready for Delivery', 'bg-emerald-400/20 text-emerald-300 border-emerald-400/30'],
                            'in_transit' => ['Out for Delivery', 'bg-indigo-400/20 text-indigo-300 border-indigo-400/30'],
                            'delivered' => ['Delivered', 'bg-slate-400/20 text-slate-300 border-slate-400/30'],
                        ];
                        [$statusText, $statusClass] = $statusLabels[$shopOrder->delivery_status] ?? ['Unknown', 'bg-slate-400/20 text-slate-300'];
                    @endphp
                    <div class="flex items-center gap-2">
                        <a href="{{ route('warehouse.loadout.slip', $shopOrder) }}" target="_blank"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-white hover:bg-white/20 transition-all border border-white/15 text-decoration-none shadow-sm">
                            <svg class="h-3.5 w-3.5 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                            Loadout Slip
                        </a>
                        <span
                            class="rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-wider {{ $statusClass }}">
                            {{ $statusText }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Shop Details & Contact Info Section -->
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">DESTINATION SHOP</span>
                    @if($shopOrder->shop?->code)
                        <span class="rounded-full bg-slate-100 border border-slate-200 px-2 py-0.5 text-[9px] font-black text-slate-700 font-mono">{{ $shopOrder->shop->code }}</span>
                    @endif
                    @if($shopOrder->shop?->warehouse_tag)
                        <span class="rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 px-2 py-0.5 text-[9px] font-black uppercase">{{ $shopOrder->shop->warehouse_tag }}</span>
                    @endif
                </div>
                <h2 class="text-base font-black text-slate-950 mt-1">{{ $shopOrder->shop?->name ?? 'Direct Customer' }}</h2>
                @if($shopOrder->shop?->address)
                    <p class="text-xs text-slate-500 mt-0.5">{{ $shopOrder->shop->address }}</p>
                @endif
            </div>

            @if($shopOrder->shop?->contact_phone || $shopOrder->shop?->contact_name)
                <div class="flex items-center gap-3 self-start sm:self-auto shrink-0 bg-slate-50 border border-slate-200 rounded-xl p-2.5">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Contact Person</p>
                        <p class="text-xs font-bold text-slate-800">{{ $shopOrder->shop->contact_name ?? 'N/A' }}</p>
                    </div>
                    @if($shopOrder->shop?->contact_phone)
                        <a href="tel:{{ $shopOrder->shop->contact_phone }}" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-black text-white hover:bg-emerald-500 shadow-sm transition-colors text-decoration-none border-none">
                            <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-2.828-1.41-5.183-3.765-6.593-6.593l1.293-.97c.362-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                            </svg>
                            {{ $shopOrder->shop->contact_phone }}
                        </a>
                    @endif
                </div>
            @endif
        </section>

        @php
            $remainingProductCount = collect($productGroups)->filter(fn(array $group): bool => (float) $group['total_balance'] > 0.001)->count();
            $hasAnyDualMeasurement = collect($productGroups)->contains(
                fn(array $group): bool => (bool) ($group['use_dual_measurement_inputs'] ?? false)
            );
            $mobileTotalRows = (int) $shopOrder->items->count();
            $mobileLoadedRows = (int) $shopOrder->items->where('sorting_status', 'loaded')->count();
            $mobileAddonRows = (int) $shopOrder->items->filter(
                fn($item): bool => str_contains((string) ($item->notes ?? ''), 'Addon item added from warehouse loadout.')
            )->count();
        @endphp



        @if($canEdit && $hasDuplicates)
            <section class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-amber-700">Merge Required</p>
                        <h2 class="mt-1 text-sm font-black text-amber-900">Duplicate product rows detected in this loadout.
                        </h2>
                        <p class="mt-1 text-xs font-semibold text-amber-800">Merge duplicates before moving to delivery so
                            billing always stays one product per invoice line.</p>
                    </div>
                    <form action="{{ route('warehouse.loadout.merge-duplicates.all', $shopOrder) }}" method="POST"
                        class="loadout-confirm-form" data-confirm-title="Merge all duplicates"
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
                        <div
                            class="flex flex-col gap-2 rounded-xl border border-amber-200 bg-white px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-xs font-semibold text-slate-700">
                                <span class="font-black text-slate-900">{{ $candidate['product_name'] }}</span>
                                <span class="ml-1 text-[11px] text-amber-700">({{ $candidate['row_count'] }} rows)</span>
                            </div>
                            <form action="{{ route('warehouse.loadout.merge-duplicates', $shopOrder) }}" method="POST"
                                class="loadout-confirm-form" data-confirm-title="Merge duplicate rows"
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
            <div
                class="grid gap-2 {{ ($canMoveToDelivery || $canMoveToPartialDelivery) && $canEdit ? 'grid-cols-2' : 'grid-cols-1' }}">
                @if($canMoveToDelivery)
                    <form action="{{ route('warehouse.loadout.move-to-delivery', $shopOrder) }}" method="POST"
                        class="loadout-confirm-form" data-confirm-title="Move to Delivery"
                        data-confirm-message="Move this order to delivery? This will reduce inventory for all loaded items."
                        data-confirm-button="Move to Delivery">
                        @csrf
                        <button type="submit"
                            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-xs font-black uppercase tracking-[0.14em] text-white shadow-sm transition-all hover:bg-emerald-700 active:scale-[0.98] border-none cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 8.25h11.25m0 0-3.75-3.75m3.75 3.75-3.75 3.75M14.25 15.75H21m0 0-3.75-3.75M21 15.75l-3.75 3.75" />
                            </svg>
                            Delivery
                        </button>
                    </form>
                @endif

                @if($canMoveToPartialDelivery)
                    <form action="{{ route('warehouse.loadout.move-to-partial-delivery', $shopOrder) }}" method="POST"
                        class="loadout-confirm-form" data-confirm-title="Move to Partial Delivery"
                        data-confirm-message="This order still has {{ $remainingProductCount }} product line(s) not fully loaded. Move it to delivery as a partial delivery?"
                        data-confirm-button="Move to Partial Delivery">
                        @csrf
                        <button type="submit"
                            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-amber-500 px-4 py-3 text-xs font-black uppercase tracking-[0.14em] text-white shadow-sm transition-all hover:bg-amber-600 active:scale-[0.98] border-none cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.34 4.94 2.94 17.76A1.5 1.5 0 0 0 4.24 20h15.52a1.5 1.5 0 0 0 1.3-2.24L13.66 4.94a1.5 1.5 0 0 0-2.6 0Z" />
                            </svg>
                            Delivery
                        </button>
                    </form>
                @endif

                @if($canMoveToLoadout)
                    <form action="{{ route('warehouse.loadout.move-to-loadout', $shopOrder) }}" method="POST"
                        class="loadout-confirm-form" data-confirm-title="Re-open Loadout"
                        data-confirm-message="Re-open this order for loadout quantity corrections?"
                        data-confirm-button="Re-open Loadout">
                        @csrf
                        <button type="submit"
                            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-amber-600 px-4 py-3 text-xs font-black uppercase tracking-[0.14em] text-white shadow-sm transition-all hover:bg-amber-700 active:scale-[0.98] border-none cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                            Reopen
                        </button>
                    </form>
                @endif
            </div>
        @endif

        {{-- Flash messages --}}
        @if(session('success'))
            <div
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">
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
                        <h2 class="mt-1 text-sm font-black text-amber-900">{{ count($unpricedProductNames) }} item(s) have
                            no approved selling price</h2>
                        <p class="mt-1 text-xs font-semibold text-amber-800">{{ implode(', ', $unpricedProductNames) }}</p>
                        <p class="mt-1 text-[11px] text-amber-700">These items are blocking invoice generation. Remove them
                            to proceed with delivery.</p>
                    </div>
                    <form action="{{ route('warehouse.loadout.remove-unpriced-items', $shopOrder) }}" method="POST"
                        class="loadout-confirm-form" data-confirm-title="Remove unpriced items"
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
                ->map(fn(array $group): string => (string) ($group['product']->category?->name ?? 'Other'))
                ->filter()
                ->unique()
                ->sort()
                ->values();
        @endphp

        <section class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
            <div class="grid gap-1.5 sm:grid-cols-[1fr_140px_130px]">
                <div>
                    <label for="loadout-product-search"
                        class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Find
                        Product</label>
                    <input id="loadout-product-search" type="search" placeholder="Search product, SKU, category..."
                        class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none">
                </div>

                {{-- Custom Tailwind Category Select --}}
                <div class="relative">
                    <label
                        class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Category</label>
                    <input type="hidden" id="loadout-product-category" value="">
                    <button type="button" id="category-select-trigger"
                        class="flex h-9 w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-[11px] font-black text-slate-700 hover:border-indigo-500 hover:bg-white focus:outline-none cursor-pointer">
                        <span id="category-select-label" class="truncate">All Categories</span>
                        <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div id="category-select-panel"
                        class="absolute left-0 right-0 top-full z-40 mt-1 max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-lg hidden space-y-0.5">
                        <button type="button" data-value="" data-label="All Categories"
                            class="category-select-option flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-[11px] font-bold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer border-none bg-transparent">
                            <span>All Categories</span>
                        </button>
                        @foreach($loadoutProductCategories as $categoryName)
                            <button type="button" data-value="{{ strtolower($categoryName) }}"
                                data-label="{{ $categoryName }}"
                                class="category-select-option flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer border-none bg-transparent">
                                <span>{{ $categoryName }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Custom Tailwind Status Select --}}
                <div class="relative">
                    <label
                        class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Status</label>
                    <input type="hidden" id="loadout-product-status" value="">
                    <button type="button" id="status-select-trigger"
                        class="flex h-9 w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-[11px] font-black text-slate-700 hover:border-indigo-500 hover:bg-white focus:outline-none cursor-pointer">
                        <span id="status-select-label" class="truncate">All Status</span>
                        <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div id="status-select-panel"
                        class="absolute left-0 right-0 top-full z-40 mt-1 rounded-xl border border-slate-200 bg-white p-1 shadow-lg hidden space-y-0.5">
                        <button type="button" data-value="" data-label="All Status"
                            class="status-select-option flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-[11px] font-bold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer border-none bg-transparent">
                            <span>All Status</span>
                        </button>
                        <button type="button" data-value="pending" data-label="Pending"
                            class="status-select-option flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer border-none bg-transparent">
                            <span>Pending</span>
                        </button>
                        <button type="button" data-value="partial" data-label="Partial"
                            class="status-select-option flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer border-none bg-transparent">
                            <span>Partial</span>
                        </button>
                        <button type="button" data-value="loaded" data-label="Loaded"
                            class="status-select-option flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 cursor-pointer border-none bg-transparent">
                            <span>Loaded</span>
                        </button>
                    </div>
                </div>
            </div>
            <p id="loadout-product-filter-count" class="mt-1.5 text-[10px] font-bold text-slate-500">
                {{ collect($productGroups)->count() }} product(s)
            </p>
        </section>

        @if($canEdit)
            @include('warehouse.loadout.partials.inline-addon', [
                'shopOrder' => $shopOrder,
                'addonProductsByCategory' => $addonProductsByCategory,
            ])
        @endif

        @if($canEdit)
            {{-- ─── LOADOUT FORM ─── --}}
            <form id="loadout-form" action="{{ route('warehouse.loadout.save', $shopOrder) }}" method="POST"
                class="space-y-2.5">
                @csrf

                {{-- Top action bar: Load All & Dual-row expand/collapse --}}
                <div class="flex flex-wrap items-center justify-between gap-1.5 px-0.5">
                    <h2 class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Items Checklist</h2>
                    <div class="flex items-center gap-1.5">
                        @if($hasAnyDualMeasurement)
                            <button type="button" id="toggle-all-cards-btn" onclick="toggleExpandAllCards()"
                                class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 px-2.5 text-[9px] font-black uppercase tracking-wider text-slate-700 cursor-pointer transition-colors shadow-2xs">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                </svg>
                                <span id="toggle-all-cards-text">Collapse Dual</span>
                            </button>
                        @endif
                        <button type="button" onclick="clearAllLoadout()"
                            class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-[9px] font-black uppercase tracking-wider text-slate-700 shadow-2xs transition-colors hover:bg-slate-50 cursor-pointer">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                            <span>Clear All</span>
                        </button>
                        <button type="button" onclick="loadAllFull()"
                            class="inline-flex h-8 items-center gap-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-2.5 text-[9px] font-black uppercase tracking-wider text-white border-none cursor-pointer transition-colors shadow-2xs">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Load All Full</span>
                        </button>
                    </div>
                </div>

                <div class="rounded-xl border border-indigo-100 bg-indigo-50/70 px-3 py-2">
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-indigo-700">Auto Save On</p>
                    <p class="mt-0.5 text-[11px] font-semibold text-indigo-900">Update quantity, stock saves automatically.
                    </p>
                </div>

                {{-- Loadout & Addon counts --}}
                <div
                    class="flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-2xs text-[11px] font-black uppercase tracking-[0.08em] text-slate-700">
                    <div
                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1">
                        <span>Loadout</span>
                        <span id="mobile-loadout-count"
                            class="text-slate-900">{{ $mobileLoadedRows }}/{{ $mobileTotalRows }}</span>
                        <svg id="mobile-loadout-saved" class="hidden h-3.5 w-3.5 text-emerald-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div
                        class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-indigo-700">
                        <span>Addon</span>
                        <span id="mobile-addon-count" class="text-indigo-900">{{ $mobileAddonRows }}</span>
                        <svg id="mobile-addon-saved" class="hidden h-3.5 w-3.5 text-emerald-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="px-0.5 text-[10px] font-semibold text-slate-500">
                        Single-measure items stay open. Dual-measure items can be expanded. Changes are auto-saved.
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
                                ? number_format($orderedUnitQty, 2, '.', '') . ' ' . $requestedUnitName
                                : number_format($approved, 2) . ' ' . strtoupper($group['unit']);
                            $isItemNotAvailable = $firstItem && ($firstItem->sorting_status === 'not_available' || $firstItem->loadout_discrepancy_type === 'not_available');
                        @endphp

                        <div class="loadout-product-row overflow-hidden rounded-xl border bg-white shadow-xs transition
                                            {{ $isFullyLoaded ? 'border-emerald-200 bg-emerald-50/20' : ($isPartial ? 'border-amber-200 bg-amber-50/10' : 'border-slate-200') }}"
                            data-search="{{ strtolower(trim(($group['product']->name ?? '') . ' ' . ($group['product']->sku ?? '') . ' ' . $loadoutCategoryName)) }}"
                            data-category="{{ strtolower($loadoutCategoryName) }}" data-status="{{ $loadoutRowStatus }}">

                            @if($useDualMeasurementInputs)
                                {{-- Collapsible Card Header for Dual Measurement --}}
                                <button type="button" onclick="toggleProductCard({{ $group['product_id'] }})"
                                    class="flex w-full items-start justify-between gap-2 p-3 text-left border-none bg-transparent transition-colors cursor-pointer hover:bg-slate-50/60">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="truncate text-sm font-black text-slate-900">
                                            <span
                                                class="mr-1 inline-block rounded-md bg-indigo-100 px-1.5 py-0.5 text-[10px] font-black text-indigo-700">SL
                                                {{ $loop->iteration }}</span>
                                            <span
                                                class="inline-block rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-black text-slate-700 mr-1">#{{ $group['product']->sku ?: $group['product_id'] }}</span>
                                            {{ $group['product']->name }}
                                        </h3>

                                        <div
                                            class="mt-0.5 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-[10px] font-semibold text-slate-500">
                                            <span>Ordered: <span
                                                    class="font-black text-slate-900">{{ $orderedUnitLabel }}</span></span>
                                            @if($hasSecondaryUnit)
                                                <span>Est.: <span class="font-bold text-slate-600">{{ number_format($approved, 2) }}
                                                        {{ strtoupper($group['unit']) }}</span></span>
                                            @endif
                                            @if($loaded > 0)
                                                <span>Loaded: <span
                                                        class="font-black text-emerald-700">{{ $hasSecondaryUnit ? number_format($loadedUnitQty, 2, '.', '') . ' ' . $requestedUnitName . ($loadedActualWeight > 0 ? ' (' . number_format($loadedActualWeight, 2) . ' ' . strtoupper($group['unit']) . ')' : '') : number_format($loaded, 2) . ' ' . strtoupper($group['unit']) }}</span></span>
                                            @endif
                                            @if($loaded > $approved)
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 border border-amber-200">
                                                    Excess: <span class="font-black">{{ number_format($loaded - $approved, 2) }}
                                                        {{ strtoupper($group['unit']) }}</span>
                                                </span>
                                            @endif
                                            <span
                                                class="inline-flex items-center gap-1 rounded-md bg-sky-50 px-1.5 py-0.5 text-[10px] font-bold text-sky-800 border border-sky-200">
                                                Info Stock: <span class="font-black">{{ number_format($available, 2) }}
                                                    {{ strtoupper($group['unit']) }}</span>
                                            </span>
                                            @if($isItemNotAvailable)
                                                <span
                                                    class="rounded-full bg-rose-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-rose-700">Not
                                                    Available ✕</span>
                                            @elseif($isFullyLoaded)
                                                <span
                                                    class="rounded-full bg-emerald-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-emerald-700">Loaded
                                                    ✓</span>
                                            @elseif($isPartial)
                                                <span
                                                    class="rounded-full bg-amber-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-amber-700">Partial</span>
                                            @else
                                                <span
                                                    class="rounded-full bg-slate-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-slate-600">Pending</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-1.5 mt-0.5 shrink-0">
                                        <svg id="arrow-{{ $group['product_id'] }}"
                                            class="h-4 w-4 text-slate-400 transition-transform duration-200" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </div>
                                </button>

                                {{-- Hidden fields for status & note --}}
                                <input type="hidden" id="status-field-{{ $group['product_id'] }}"
                                    name="item_status[{{ $group['product_id'] }}]"
                                    value="{{ $isItemNotAvailable ? 'not_available' : 'loaded' }}">
                                <input type="hidden" id="note-field-{{ $group['product_id'] }}"
                                    name="item_notes[{{ $group['product_id'] }}]"
                                    value="{{ $firstItem->loadout_discrepancy_note ?? '' }}">

                                {{-- Collapsible Body (COLLAPSED BY DEFAULT) --}}
                                <div id="card-body-{{ $group['product_id'] }}"
                                    class="product-card-body collapsible-body hidden border-t border-slate-100 p-3 pt-2 bg-slate-50/40">
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        {{-- 1. Loaded Secondary Unit Count Stepper --}}
                                        <div
                                            class="flex items-center justify-between gap-2 rounded-lg bg-white border border-slate-200 p-2">
                                            <span class="text-[11px] font-black text-slate-700">Loaded
                                                {{ $requestedUnitName }}:</span>
                                            <div class="flex items-center gap-1">
                                                <button type="button" onclick="stepUnitQty({{ $group['product_id'] }}, -1)"
                                                    class="flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 cursor-pointer border-none font-bold text-sm">-</button>
                                                <input type="number" id="unit-qty-{{ $group['product_id'] }}"
                                                    name="item_unit_qtys[{{ $group['product_id'] }}]"
                                                    value="{{ number_format($loadedUnitQty, 2, '.', '') }}" min="0" step="any"
                                                    inputmode="decimal"
                                                    data-approved-unit="{{ number_format($orderedUnitQty, 2, '.', '') }}"
                                                    class="h-7 w-14 rounded-md border border-slate-200 bg-white text-center text-[11px] font-black text-slate-900 focus:outline-none"
                                                    {{ $isItemNotAvailable ? 'readonly' : '' }}>
                                                <button type="button" onclick="stepUnitQty({{ $group['product_id'] }}, 1)"
                                                    class="flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-700 hover:bg-slate-100 cursor-pointer border-none font-bold text-sm">+</button>
                                            </div>
                                        </div>

                                        {{-- 2. Actual Weight Input --}}
                                        <div
                                            class="flex items-center justify-between gap-2 rounded-lg bg-white border border-slate-200 p-2">
                                            <span class="text-[11px] font-black text-slate-700">Actual Weight:</span>
                                            <div class="flex items-center gap-1.5 flex-1 max-w-[170px]">
                                                <input type="number" id="qty-{{ $group['product_id'] }}"
                                                    name="items[{{ $group['product_id'] }}]"
                                                    value="{{ number_format($loaded, 2, '.', '') }}" min="0" step="any"
                                                    inputmode="decimal" data-approved="{{ number_format($approved, 2, '.', '') }}"
                                                    data-available="{{ number_format($available, 2, '.', '') }}"
                                                    data-product="{{ $group['product']->name }}"
                                                    data-unit="{{ strtoupper($group['unit']) }}"
                                                    class="qty-input h-8 w-full rounded-md border border-slate-200 px-2 text-center text-xs font-black focus:outline-none focus:ring-2 focus:ring-indigo-400 {{ $isItemNotAvailable ? 'bg-rose-50 text-rose-600 line-through' : 'bg-white text-slate-900' }}"
                                                    {{ $isItemNotAvailable ? 'readonly' : '' }} required>
                                                <span
                                                    class="text-[11px] font-black text-slate-600">{{ strtoupper($group['unit']) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-2 flex items-center justify-end gap-2 border-t border-slate-200/80 pt-2">
                                        <button type="button" id="not-avail-btn-{{ $group['product_id'] }}"
                                            onclick="toggleNotAvailable({{ $group['product_id'] }})"
                                            class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-rose-700 transition-colors hover:bg-rose-100 cursor-pointer border-none">
                                            {{ $isItemNotAvailable ? 'Re-enable Item' : 'Not Available' }}
                                        </button>
                                    </div>
                                </div>
                            @else
                                {{-- Single Input for Direct Base Unit (Strict Single Row Mobile Layout & Modal Support) --}}
                                <div class="p-2 sm:p-2.5">
                                    {{-- Hidden fields for status & note --}}
                                    <input type="hidden" id="status-field-{{ $group['product_id'] }}"
                                        name="item_status[{{ $group['product_id'] }}]"
                                        value="{{ $isItemNotAvailable ? 'not_available' : 'loaded' }}">
                                    <input type="hidden" id="note-field-{{ $group['product_id'] }}"
                                        name="item_notes[{{ $group['product_id'] }}]"
                                        value="{{ $firstItem->loadout_discrepancy_note ?? '' }}">

                                    <div class="flex items-center justify-between gap-1.5 min-w-0">
                                        {{-- Product info inline in 1 row --}}
                                        <div class="flex items-center gap-1.5 min-w-0 flex-1 cursor-pointer"
                                            onclick="openSingleQtyModal({{ $group['product_id'] }})">
                                            <span
                                                class="rounded-md bg-indigo-100 px-1.5 py-0.5 text-[9px] font-black text-indigo-700 shrink-0">SL
                                                {{ $loop->iteration }}</span>
                                            <span
                                                class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-black text-slate-700 shrink-0">#{{ $group['product']->sku ?: $group['product_id'] }}</span>

                                            <h3 class="truncate text-xs font-black text-slate-900 shrink-0 max-w-[100px] sm:max-w-xs"
                                                title="{{ $group['product']->name }}">{{ $group['product']->name }}</h3>

                                            <div
                                                class="hidden sm:flex items-center gap-1.5 text-[9px] font-semibold text-slate-500 shrink-0">
                                                <span>Ord: <span
                                                        class="font-black text-slate-900">{{ number_format($orderedUnitQty, 2, '.', '') }}</span></span>
                                                <span class="text-sky-700">Stk: <span
                                                        class="font-bold">{{ number_format($available, 2, '.', '') }}</span></span>
                                            </div>

                                            @if($isItemNotAvailable)
                                                <span
                                                    class="rounded-full bg-rose-100 px-1.5 py-0.5 text-[8px] font-black uppercase text-rose-700 shrink-0">N/A
                                                    ✕</span>
                                            @elseif($isFullyLoaded)
                                                <span
                                                    class="rounded-full bg-emerald-100 px-1.5 py-0.5 text-[8px] font-black uppercase text-emerald-700 shrink-0">Loaded
                                                    ✓</span>
                                            @elseif($isPartial)
                                                <span
                                                    class="rounded-full bg-amber-100 px-1.5 py-0.5 text-[8px] font-black uppercase text-amber-700 shrink-0">Partial</span>
                                            @else
                                                <span
                                                    class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[8px] font-black uppercase text-slate-600 shrink-0">Pending</span>
                                            @endif
                                        </div>

                                        {{-- Controls in single row --}}
                                        <div class="flex items-center gap-1 shrink-0">
                                            <button type="button" onclick="stepQty({{ $group['product_id'] }}, -1)"
                                                class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 active:bg-slate-200 cursor-pointer transition-colors border-none font-bold text-sm">-</button>

                                            <div
                                                class="flex items-center gap-0.5 rounded-lg border border-slate-200 bg-white px-1.5 py-0.5 shadow-2xs">
                                                <input type="number" id="qty-{{ $group['product_id'] }}"
                                                    name="items[{{ $group['product_id'] }}]"
                                                    value="{{ number_format($loaded, 2, '.', '') }}" min="0" step="any"
                                                    inputmode="decimal" data-approved="{{ number_format($approved, 2, '.', '') }}"
                                                    data-available="{{ number_format($available, 2, '.', '') }}"
                                                    data-product="{{ $group['product']->name }}"
                                                    data-unit="{{ strtoupper($group['unit']) }}"
                                                    class="qty-input w-14 border-none text-center text-xs font-black focus:outline-none {{ $isItemNotAvailable ? 'bg-rose-50 text-rose-600 line-through' : 'bg-white text-slate-900' }}"
                                                    {{ $isItemNotAvailable ? 'readonly' : '' }} required>
                                                <span
                                                    class="text-[9px] font-black text-slate-500 uppercase">{{ strtoupper($group['unit']) }}</span>
                                            </div>

                                            <button type="button" onclick="stepQty({{ $group['product_id'] }}, 1)"
                                                class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white hover:bg-slate-100 active:bg-slate-200 text-slate-600 cursor-pointer transition-colors border-none font-bold text-sm">+</button>

                                            <button type="button" id="not-avail-btn-{{ $group['product_id'] }}"
                                                onclick="toggleNotAvailable({{ $group['product_id'] }})"
                                                class="shrink-0 rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-rose-700 transition-colors hover:bg-rose-100 cursor-pointer border-none">
                                                {{ $isItemNotAvailable ? 'Enable' : 'N/A' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </form>

            <div id="loadout-product-empty"
                class="hidden rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm font-bold text-slate-500">
                No products match these filters.
            </div>

        @else
            {{-- READ-ONLY: in_transit or delivered view --}}
            <div class="space-y-2.5" id="loadout-product-list">
                <h2 class="px-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Loaded Items</h2>

                @foreach($productGroups as $group)
                    @php
                        $loadoutRowStatus = $group['is_fully_loaded'] ? 'loaded' : ($group['is_partially_loaded'] ? 'partial' : 'pending');
                        $loadoutCategoryName = $group['product']->category?->name ?? 'Other';
                    @endphp
                    <div class="loadout-product-row rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                        data-search="{{ strtolower(trim(($group['product']->name ?? '') . ' ' . ($group['product']->sku ?? '') . ' ' . $loadoutCategoryName)) }}"
                        data-category="{{ strtolower($loadoutCategoryName) }}" data-status="{{ $loadoutRowStatus }}">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-black text-slate-900">
                                    <span
                                        class="mr-1.5 inline-block rounded-lg bg-indigo-100 px-1.5 py-0.5 text-xs font-black text-indigo-700">SL
                                        {{ $loop->iteration }}</span>
                                    {{ $group['product']->name }}
                                </p>
                                @if (!empty($group['items']))
                                    @php
                                        $loadoutMeasures = collect($group['items'])->map(fn($item) => $item->requestedMeasureBreakdownLabel())->filter()->unique();
                                    @endphp
                                    @if ($loadoutMeasures->isNotEmpty())
                                        <p class="mt-0.5 text-[10px] font-black uppercase tracking-[0.1em] text-emerald-700">
                                            {{ $loadoutMeasures->implode(' · ') }}
                                        </p>
                                    @endif
                                @endif
                                <p class="mt-0.5 text-[11px] font-semibold text-slate-500">
                                    Loaded: <span
                                        class="font-black text-slate-800">{{ number_format($group['total_loaded'], 2) }}
                                        {{ $group['unit'] }}</span>
                                    @if($group['total_balance'] > 0)
                                        &middot; Balance: <span
                                            class="text-amber-600 font-black">{{ number_format($group['total_balance'], 2) }}
                                            {{ $group['unit'] }}</span>
                                    @endif
                                </p>
                            </div>
                            @if($group['is_fully_loaded'])
                                <span
                                    class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-700">Loaded
                                    ✓</span>
                            @else
                                <span
                                    class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-amber-700">Partial</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div id="loadout-product-empty"
                class="hidden rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm font-bold text-slate-500">
                No products match these filters.
            </div>

            @php
                $deliveryDiscrepancyItems = $shopOrder->items->filter(
                    fn($item) => (float) ($item->shortage_qty ?? 0) > 0.001 || filled($item->delivery_discrepancy_note)
                );
            @endphp

            @if($shopOrder->delivery_notes || $deliveryDiscrepancyItems->isNotEmpty())
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                        <div>
                            <h2 class="text-sm font-black text-slate-900">Delivery Update</h2>
                            <p class="mt-1 text-[11px] font-semibold text-slate-500">
                                {{ str($shopOrder->delivery_status)->replace('_', ' ')->title() }}
                                @if($shopOrder->deliveredBy)
                                    · Updated by {{ $shopOrder->deliveredBy->name }}
                                @endif
                            </p>
                        </div>
                        @if($shopOrder->delivered_at)
                            <span class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">
                                {{ $shopOrder->delivered_at->format('d M, h:i A') }}
                            </span>
                        @endif
                    </div>

                    @if($shopOrder->delivery_notes)
                        <div class="mt-3 rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-amber-700">Delivery Note</p>
                            <p class="mt-1 text-sm font-semibold text-amber-900">{{ $shopOrder->delivery_notes }}</p>
                        </div>
                    @endif

                    @if($deliveryDiscrepancyItems->isNotEmpty())
                        <div class="mt-3 space-y-2">
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Discrepancy Details</p>
                            @foreach($deliveryDiscrepancyItems as $item)
                                <div class="rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-black text-slate-900">{{ $item->product->name }}</p>
                                        <span class="text-[10px] font-black uppercase tracking-[0.12em] text-rose-700">
                                            Short {{ number_format((float) $item->shortage_qty, 2) }} {{ $item->unit }}
                                        </span>
                                    </div>
                                    @if($item->delivery_discrepancy_note)
                                        <p class="mt-1 text-xs font-semibold text-rose-900">{{ $item->delivery_discrepancy_note }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        @endif

    </div>

    @if($shopOrder->delivery_status === 'delivered')
        {{-- ─── STICKY BOTTOM ACTION BAR ─── --}}
        <div class="fixed inset-x-0 bottom-[5.5rem] z-50 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-[0_-8px_24px_rgba(15,23,42,0.10)] backdrop-blur-sm lg:bottom-0"
            id="sticky-bar">
            <div class="mx-auto flex max-w-xl flex-col gap-2">
                <div class="flex items-center justify-center gap-2 py-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 border border-emerald-200 px-4 py-2 text-xs font-black uppercase tracking-wider text-emerald-700">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Delivered
                    </span>
                </div>

            </div>
        </div>
    @endif

    {{-- ─── SINGLE ITEM QUANTITY EDIT POPUP MODAL ─── --}}
    <div id="single-qty-modal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-xs hidden"
        onclick="if(event.target === this) closeSingleQtyModal()">
        <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-xl space-y-3.5">
            {{-- Modal Header --}}
            <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-2.5">
                <div>
                    <div class="flex items-center gap-1.5">
                        <span id="modal-sl-badge"
                            class="rounded-md bg-indigo-100 px-1.5 py-0.5 text-[10px] font-black text-indigo-700">SL
                            1</span>
                        <span id="modal-sku-badge"
                            class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-black text-slate-700">#SKU</span>
                    </div>
                    <h3 id="modal-product-name" class="mt-1 text-sm font-black text-slate-900">Product Name</h3>
                </div>
                <button type="button" onclick="closeSingleQtyModal()"
                    class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500 hover:bg-slate-100 border-none cursor-pointer text-xs font-bold">
                    ✕
                </button>
            </div>

            {{-- Product Info Stats --}}
            <div class="grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-2.5 text-center">
                <div>
                    <p class="text-[9px] font-black uppercase text-slate-400">Ordered</p>
                    <p id="modal-ordered-qty" class="text-xs font-black text-slate-900">0.00</p>
                </div>
                <div>
                    <p class="text-[9px] font-black uppercase text-slate-400">Loaded</p>
                    <p id="modal-loaded-qty" class="text-xs font-black text-emerald-700">0.00</p>
                </div>
                <div>
                    <p class="text-[9px] font-black uppercase text-slate-400">Stock</p>
                    <p id="modal-available-stock" class="text-xs font-black text-sky-700">0.00</p>
                </div>
            </div>

            {{-- Input & Stepper --}}
            <div class="space-y-1.5">
                <label for="modal-qty-input"
                    class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Loaded Quantity</label>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="stepModalQty(-1)"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-lg font-black text-slate-700 hover:bg-slate-200 active:bg-slate-300 cursor-pointer border-none">-</button>
                    <div
                        class="flex flex-1 items-center gap-1.5 rounded-xl border-2 border-indigo-500 bg-white px-3 py-2 shadow-2xs">
                        <input type="number" id="modal-qty-input" min="0" step="any" inputmode="decimal"
                            class="w-full border-none text-center text-lg font-black text-slate-900 focus:outline-none">
                        <span id="modal-unit-label" class="text-xs font-black text-slate-500 uppercase">KG</span>
                    </div>
                    <button type="button" onclick="stepModalQty(1)"
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-lg font-black text-slate-700 hover:bg-slate-200 active:bg-slate-300 cursor-pointer border-none">+</button>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="grid grid-cols-3 gap-2">
                <button type="button" onclick="setModalFullQty()"
                    class="rounded-xl border border-emerald-200 bg-emerald-50 px-2 py-2 text-xs font-black text-emerald-700 hover:bg-emerald-100 cursor-pointer border-none">
                    Full Qty
                </button>
                <button type="button" onclick="setModalZeroQty()"
                    class="rounded-xl border border-slate-200 bg-slate-50 px-2 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 cursor-pointer border-none">
                    Clear (0)
                </button>
                <button type="button" id="modal-not-avail-btn" onclick="toggleModalNotAvailable()"
                    class="rounded-xl border border-rose-200 bg-rose-50 px-2 py-2 text-xs font-black text-rose-700 hover:bg-rose-100 cursor-pointer border-none">
                    Not Avail
                </button>
            </div>

            {{-- Submit & Apply --}}
            <button type="button" onclick="saveSingleQtyModal()"
                class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase tracking-wider text-white hover:bg-indigo-700 cursor-pointer border-none shadow-sm">
                Save & Apply
            </button>
        </div>
    </div>

    {{-- ─── NOT AVAILABLE REASON TAILWIND MODAL POPUP ─── --}}
    <div id="not-available-reason-modal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-xs hidden"
        onclick="if(event.target === this) closeNotAvailableReasonModal()">
        <div class="w-full max-w-sm rounded-2xl border border-rose-200 bg-white p-4 shadow-xl space-y-3.5">
            {{-- Modal Header --}}
            <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-2.5">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Mark Not Available</h3>
                        <p id="not-avail-modal-product-name" class="text-[11px] font-semibold text-slate-500">Product
                            Name</p>
                    </div>
                </div>
                <button type="button" onclick="closeNotAvailableReasonModal()"
                    class="flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500 hover:bg-slate-100 border-none cursor-pointer text-xs font-bold">
                    ✕
                </button>
            </div>

            {{-- Reason Form --}}
            <div class="space-y-1.5">
                <label for="not-avail-reason-input"
                    class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Reason / Note
                    (Optional)</label>
                <textarea id="not-avail-reason-input" rows="2"
                    placeholder="e.g. Out of stock, Damaged quality, Vendor shortage..."
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-semibold text-slate-900 focus:border-rose-500 focus:bg-white focus:outline-none"></textarea>
            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center gap-2 pt-1">
                <button type="button" onclick="closeNotAvailableReasonModal()"
                    class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-black text-slate-700 hover:bg-slate-100 cursor-pointer border-none">
                    Cancel
                </button>
                <button type="button" onclick="confirmNotAvailableReason()"
                    class="flex-1 rounded-xl bg-rose-600 px-3 py-2.5 text-xs font-black uppercase tracking-wider text-white hover:bg-rose-700 cursor-pointer border-none shadow-sm">
                    Confirm N/A
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            const loadoutForm = document.getElementById('loadout-form');
            const loadoutActionFeedback = document.getElementById('loadout-action-feedback');
            let currentModalProductId = null;
            let pendingNotAvailableProductId = null;

            function openSingleQtyModal(productId) {
                const input = document.getElementById('qty-' + productId);
                if (!input) return;

                currentModalProductId = productId;
                const row = input.closest('.loadout-product-row');
                const slBadge = row ? (row.querySelector('.bg-indigo-100')?.textContent || '') : '';
                const skuBadge = row ? (row.querySelector('.bg-slate-100')?.textContent || '') : '';
                const productName = input.dataset.product || '';
                const unit = input.dataset.unit || '';
                const approved = parseFloat(input.dataset.approved) || 0;
                const available = parseFloat(input.dataset.available) || 0;
                const currentQty = parseFloat(input.value) || 0;
                const statusField = document.getElementById('status-field-' + productId);
                const isNotAvail = statusField && statusField.value === 'not_available';

                document.getElementById('modal-sl-badge').textContent = slBadge;
                document.getElementById('modal-sku-badge').textContent = skuBadge;
                document.getElementById('modal-product-name').textContent = productName;
                document.getElementById('modal-unit-label').textContent = unit;
                document.getElementById('modal-ordered-qty').textContent = approved.toFixed(2) + ' ' + unit;
                document.getElementById('modal-loaded-qty').textContent = currentQty.toFixed(2) + ' ' + unit;
                document.getElementById('modal-available-stock').textContent = available.toFixed(2) + ' ' + unit;

                const modalInput = document.getElementById('modal-qty-input');
                modalInput.value = currentQty.toFixed(2);
                modalInput.readOnly = isNotAvail;

                const modalNotAvailBtn = document.getElementById('modal-not-avail-btn');
                if (modalNotAvailBtn) {
                    modalNotAvailBtn.textContent = isNotAvail ? 'Enable Item' : 'Not Avail';
                }

                document.getElementById('single-qty-modal').classList.remove('hidden');
                setTimeout(() => { modalInput.focus(); modalInput.select(); }, 100);
            }

            function closeSingleQtyModal() {
                document.getElementById('single-qty-modal').classList.add('hidden');
                currentModalProductId = null;
            }

            function stepModalQty(delta) {
                const modalInput = document.getElementById('modal-qty-input');
                if (!modalInput || modalInput.readOnly) return;
                let val = parseFloat(modalInput.value) || 0;
                val = Math.max(0, val + delta * 0.5);
                modalInput.value = val.toFixed(2);
            }

            function setModalFullQty() {
                if (!currentModalProductId) return;
                const mainInput = document.getElementById('qty-' + currentModalProductId);
                if (!mainInput) return;
                const approved = parseFloat(mainInput.dataset.approved) || 0;
                const modalInput = document.getElementById('modal-qty-input');
                if (modalInput) {
                    modalInput.value = approved.toFixed(2);
                }
            }

            function setModalZeroQty() {
                const modalInput = document.getElementById('modal-qty-input');
                if (modalInput) {
                    modalInput.value = '0.00';
                }
            }

            function toggleModalNotAvailable() {
                if (!currentModalProductId) return;
                toggleNotAvailable(currentModalProductId);
                const mainInput = document.getElementById('qty-' + currentModalProductId);
                const statusField = document.getElementById('status-field-' + currentModalProductId);
                const isNotAvail = statusField && statusField.value === 'not_available';
                const modalInput = document.getElementById('modal-qty-input');
                if (modalInput && mainInput) {
                    modalInput.value = mainInput.value;
                    modalInput.readOnly = isNotAvail;
                }
                const modalNotAvailBtn = document.getElementById('modal-not-avail-btn');
                if (modalNotAvailBtn) {
                    modalNotAvailBtn.textContent = isNotAvail ? 'Enable Item' : 'Not Avail';
                }
            }

            function saveSingleQtyModal() {
                if (!currentModalProductId) return;
                const mainInput = document.getElementById('qty-' + currentModalProductId);
                const modalInput = document.getElementById('modal-qty-input');
                if (mainInput && modalInput) {
                    mainInput.value = (parseFloat(modalInput.value) || 0).toFixed(2);
                    mainInput.dispatchEvent(new Event('change'));
                }
                closeSingleQtyModal();
            }

            function toggleProductCard(productId) {
                const body = document.getElementById('card-body-' + productId);
                const arrow = document.getElementById('arrow-' + productId);
                if (!body || !body.classList.contains('collapsible-body')) return;

                const isHidden = body.classList.contains('hidden');
                body.classList.toggle('hidden', !isHidden);
                if (arrow) {
                    arrow.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
                }
            }

            function toggleExpandAllCards() {
                const bodies = document.querySelectorAll('.product-card-body.collapsible-body');
                const textSpan = document.getElementById('toggle-all-cards-text');
                if (!bodies.length) return;

                const anyHidden = Array.from(bodies).some(b => b.classList.contains('hidden'));

                bodies.forEach(body => {
                    const productId = body.id.replace('card-body-', '');
                    const arrow = document.getElementById('arrow-' + productId);

                    body.classList.toggle('hidden', !anyHidden);
                    if (arrow) {
                        arrow.style.transform = anyHidden ? 'rotate(180deg)' : 'rotate(0deg)';
                    }
                });

                if (textSpan) {
                    textSpan.textContent = anyHidden ? 'Collapse Dual' : 'Expand Dual';
                }
            }

            function formatLoadoutQty(value) {
                return (parseFloat(value) || 0).toFixed(2);
            }

            function stepUnitQty(productId, delta) {
                const input = document.getElementById('unit-qty-' + productId);
                if (!input || input.readOnly) {
                    return;
                }

                let val = parseFloat(input.value) || 0;
                val = Math.max(0, val + delta);
                input.value = val;
            }

            function showLoadoutFeedback(message, tone = 'info') {
                if (!loadoutActionFeedback) {
                    return;
                }

                const toneClasses = {
                    info: ['border-cyan-200', 'bg-cyan-50', 'text-cyan-800'],
                    success: ['border-emerald-200', 'bg-emerald-50', 'text-emerald-800'],
                    warning: ['border-amber-200', 'bg-amber-50', 'text-amber-800'],
                };

                loadoutActionFeedback.className = 'rounded-2xl border px-4 py-3 text-xs font-semibold shadow-sm';
                loadoutActionFeedback.classList.add(...(toneClasses[tone] ?? toneClasses.info));
                loadoutActionFeedback.textContent = message;
                loadoutActionFeedback.classList.remove('hidden');
            }

            function setRowStatus(productId, message) {
                const rowStatus = document.getElementById('status-' + productId);
                if (!rowStatus) {
                    return;
                }

                rowStatus.textContent = message;
                rowStatus.classList.remove('hidden');
            }

            function pulseInput(input) {
                if (!input) {
                    return;
                }

                input.classList.add('ring-2', 'ring-cyan-400', 'border-cyan-400');
                window.setTimeout(function () {
                    input.classList.remove('ring-2', 'ring-cyan-400', 'border-cyan-400');
                }, 1000);
            }

            function markProductAvailable(productId) {
                const input = document.getElementById('qty-' + productId);
                const unitInput = document.getElementById('unit-qty-' + productId);
                const statusField = document.getElementById('status-field-' + productId);
                const noteField = document.getElementById('note-field-' + productId);
                const btn = document.getElementById('not-avail-btn-' + productId);
                const isDualMeasurement = Boolean(unitInput);

                if (statusField) {
                    statusField.value = 'loaded';
                }

                if (noteField) {
                    noteField.value = '';
                }

                [input, unitInput].forEach(function (field) {
                    if (!field) {
                        return;
                    }

                    field.readOnly = false;
                    field.classList.remove('bg-rose-50', 'text-rose-600', 'line-through');
                    field.classList.add('bg-white', 'text-slate-900');
                });

                if (btn) {
                    btn.textContent = isDualMeasurement ? 'Not Available' : 'Not Avail';
                    btn.className = isDualMeasurement
                        ? 'rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-rose-700 transition-colors hover:bg-rose-100 cursor-pointer border-none'
                        : 'shrink-0 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-[10px] font-black uppercase tracking-wide text-rose-700 transition-colors hover:bg-rose-100 cursor-pointer border-none';
                }
            }

            function updateFullButtonState(productId, entered, approved) {
                const fullButton = document.getElementById('full-btn-' + productId);
                if (!fullButton) {
                    return;
                }

                if (approved > 0 && entered >= approved - 0.001) {
                    fullButton.textContent = 'Save';
                    fullButton.classList.remove('border-indigo-200', 'bg-indigo-50', 'text-indigo-600');
                    fullButton.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
                } else {
                    fullButton.textContent = 'Full';
                    fullButton.classList.remove('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
                    fullButton.classList.add('border-indigo-200', 'bg-indigo-50', 'text-indigo-600');
                }
            }

            function handleFullAction(productId) {
                const input = document.getElementById('qty-' + productId);
                if (!input) {
                    return;
                }

                const approved = parseFloat(input.dataset.approved) || 0;
                const entered = parseFloat(input.value) || 0;

                if (approved > 0 && entered >= approved - 0.001) {
                    submitSpecificQty(productId);
                    return;
                }

                setFull(productId);
            }

            function updateSaveQtyButtonState(productId, entered, approved) {
                const saveQtyButton = document.getElementById('save-qty-btn-' + productId);
                if (!saveQtyButton) {
                    return;
                }

                if (entered > 0.001 && entered < approved - 0.001) {
                    saveQtyButton.classList.remove('hidden');
                } else {
                    saveQtyButton.classList.add('hidden');
                }
            }

            function submitSpecificQty(productId) {
                const input = document.getElementById('qty-' + productId);
                if (!input || !loadoutForm) {
                    return;
                }

                const entered = formatLoadoutQty(input.value);
                const productName = input.dataset.product;
                const unit = input.dataset.unit;

                showLoadoutFeedback('Saving custom quantity for ' + productName + ': ' + entered + ' ' + unit + '.', 'success');
                setRowStatus(productId, 'Saving custom quantity');
                loadoutForm.submit();
            }

            // Set a product's qty input to its approved (full) qty
            function setFull(productId) {
                const input = document.getElementById('qty-' + productId);
                if (input) {
                    markProductAvailable(productId);
                    const approved = formatLoadoutQty(input.dataset.approved);
                    const previous = formatLoadoutQty(input.value);
                    const productName = input.dataset.product;
                    const unit = input.dataset.unit;
                    const unitInput = document.getElementById('unit-qty-' + productId);

                    input.value = approved;
                    input.dispatchEvent(new Event('change'));
                    pulseInput(input);

                    if (unitInput) {
                        unitInput.value = formatLoadoutQty(unitInput.dataset.approvedUnit);
                        unitInput.dispatchEvent(new Event('change'));
                        pulseInput(unitInput);
                    }

                    setRowStatus(productId, approved === previous ? 'Already at full quantity' : 'Full quantity applied');
                    showLoadoutFeedback(productName + ' set to ' + approved + ' ' + unit + '.', 'success');
                    window.showAppAlert({
                        title: productName,
                        message: approved === previous
                            ? productName + ' is already at the full approved quantity of ' + approved + ' ' + unit + '.'
                            : 'Set ' + productName + ' to the full approved quantity of ' + approved + ' ' + unit + '.',
                        tone: 'success',
                        confirmLabel: 'Continue',
                    });
                }
            }

            // Load all products at full approved qty
            function loadAllFull() {
                let changedCount = 0;
                let alreadyFullCount = 0;

                document.querySelectorAll('.qty-input').forEach(function (input) {
                    const productId = input.id.replace('qty-', '');
                    markProductAvailable(productId);

                    const approved = formatLoadoutQty(input.dataset.approved);
                    const current = formatLoadoutQty(input.value);
                    const unitInput = document.getElementById('unit-qty-' + productId);

                    input.value = approved;
                    input.dispatchEvent(new Event('change'));
                    pulseInput(input);

                    if (unitInput) {
                        unitInput.value = formatLoadoutQty(unitInput.dataset.approvedUnit);
                        unitInput.dispatchEvent(new Event('change'));
                        pulseInput(unitInput);
                    }

                    if (current === approved) {
                        alreadyFullCount++;
                    } else {
                        changedCount++;
                    }
                });

                const changedSummary = changedCount > 0
                    ? 'Full approved quantities applied for ' + changedCount + ' product(s).'
                    : 'All products are already at full approved quantity.';
                const alreadyFullSummary = alreadyFullCount > 0 && changedCount > 0
                    ? ' ' + alreadyFullCount + ' product(s) were already full.'
                    : '';

                showLoadoutFeedback(changedSummary + alreadyFullSummary, changedCount > 0 ? 'success' : 'info');

                window.showAppConfirm({
                    title: 'Load all products',
                    message: 'Load every product in this shop order as per the purchaser-approved quantities and save it now?',
                    confirmLabel: 'Confirm Load All',
                    cancelLabel: 'Keep Editing',
                    tone: 'success',
                    onConfirm: function () {
                        if (loadoutForm) {
                            loadoutForm.submit();
                        }
                    },
                });
            }

            function clearAllLoadout() {
                let changedCount = 0;

                document.querySelectorAll('.qty-input').forEach(function (input) {
                    const productId = input.id.replace('qty-', '');
                    const current = formatLoadoutQty(input.value);
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

                    if (current !== '0.00') {
                        changedCount++;
                    }
                });

                const changedSummary = changedCount > 0
                    ? 'Cleared loadout quantities for ' + changedCount + ' product(s).'
                    : 'All loadout quantities are already clear.';

                showLoadoutFeedback(changedSummary, changedCount > 0 ? 'warning' : 'info');

                window.showAppConfirm({
                    title: 'Clear all quantities',
                    message: 'Clear every loaded quantity in this shop order and save it now?',
                    confirmLabel: 'Confirm Clear All',
                    cancelLabel: 'Keep Editing',
                    tone: 'danger',
                    onConfirm: function () {
                        if (loadoutForm) {
                            loadoutForm.submit();
                        }
                    },
                });
            }

            // Increment/decrement a secondary unit (BOX/CRATE/BAG) qty input by 1
            function stepUnitQty(productId, direction) {
                const input = document.getElementById('unit-qty-' + productId);
                if (!input) return;
                const step = 1;
                let current = parseFloat(input.value) || 0;
                current = Math.max(0, current + direction * step);
                input.value = current.toFixed(2);
                input.dispatchEvent(new Event('change'));
            }

            // Increment/decrement a qty input by step (in 0.5 units)
            function stepQty(productId, direction) {
                const input = document.getElementById('qty-' + productId);
                if (!input) return;
                const step = 0.5;
                let current = parseFloat(input.value) || 0;
                current = Math.max(0, current + direction * step);
                input.value = current.toFixed(2);
                input.dispatchEvent(new Event('change'));
            }

            // Toggle Not Available / Out of Stock status for a product via Tailwind Modal
            function toggleNotAvailable(productId) {
                const input = document.getElementById('qty-' + productId);
                const unitInput = document.getElementById('unit-qty-' + productId);
                const statusField = document.getElementById('status-field-' + productId);
                const noteField = document.getElementById('note-field-' + productId);
                const btn = document.getElementById('not-avail-btn-' + productId);
                const isDualMeasurement = Boolean(unitInput);
                if (!input || !statusField || !btn) return;

                if (statusField.value === 'not_available') {
                    statusField.value = 'loaded';
                    if (noteField) noteField.value = '';
                    input.readOnly = false;
                    input.classList.remove('bg-rose-50', 'text-rose-600', 'line-through');
                    btn.textContent = isDualMeasurement ? 'Not Available' : 'N/A';
                    btn.className = isDualMeasurement
                        ? 'rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-rose-700 transition-colors hover:bg-rose-100 cursor-pointer border-none'
                        : 'shrink-0 rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-rose-700 transition-colors hover:bg-rose-100 cursor-pointer border-none';
                    input.dispatchEvent(new Event('change'));
                } else {
                    openNotAvailableReasonModal(productId);
                }
            }

            function openNotAvailableReasonModal(productId) {
                const input = document.getElementById('qty-' + productId);
                if (!input) return;

                pendingNotAvailableProductId = productId;
                const productName = input.dataset.product || 'Product';
                document.getElementById('not-avail-modal-product-name').textContent = productName;
                const reasonInput = document.getElementById('not-avail-reason-input');
                if (reasonInput) reasonInput.value = '';

                document.getElementById('not-available-reason-modal').classList.remove('hidden');
                setTimeout(() => { if (reasonInput) reasonInput.focus(); }, 100);
            }

            function closeNotAvailableReasonModal() {
                document.getElementById('not-available-reason-modal').classList.add('hidden');
                pendingNotAvailableProductId = null;
            }

            function confirmNotAvailableReason() {
                if (!pendingNotAvailableProductId) return;
                const productId = pendingNotAvailableProductId;

                const input = document.getElementById('qty-' + productId);
                const unitInput = document.getElementById('unit-qty-' + productId);
                const statusField = document.getElementById('status-field-' + productId);
                const noteField = document.getElementById('note-field-' + productId);
                const btn = document.getElementById('not-avail-btn-' + productId);
                const isDualMeasurement = Boolean(unitInput);
                const reasonInput = document.getElementById('not-avail-reason-input');
                const note = (reasonInput ? reasonInput.value : '').trim() || 'Marked as Not Available';

                if (input && statusField && btn) {
                    statusField.value = 'not_available';
                    if (noteField) noteField.value = note;
                    input.value = '0.00';
                    input.readOnly = true;
                    input.classList.add('bg-rose-50', 'text-rose-600', 'line-through');
                    btn.textContent = isDualMeasurement ? 'Unavailable ✕' : 'N/A ✕';
                    btn.className = isDualMeasurement
                        ? 'rounded-xl border bg-rose-600 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-white cursor-pointer transition-colors border-none'
                        : 'shrink-0 rounded-lg border bg-rose-600 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-white cursor-pointer transition-colors border-none';

                    input.dispatchEvent(new Event('change'));
                }

                closeNotAvailableReasonModal();

                if (typeof currentModalProductId !== 'undefined' && currentModalProductId === productId) {
                    const modalInput = document.getElementById('modal-qty-input');
                    if (modalInput) {
                        modalInput.value = '0.00';
                        modalInput.readOnly = true;
                    }
                    const modalNotAvailBtn = document.getElementById('modal-not-avail-btn');
                    if (modalNotAvailBtn) {
                        modalNotAvailBtn.textContent = 'Enable Item';
                    }
                }
            }

            // Confirm modal for forms
            document.addEventListener('DOMContentLoaded', function () {
                const productSearch = document.getElementById('loadout-product-search');
                const productCategory = document.getElementById('loadout-product-category');
                const productStatus = document.getElementById('loadout-product-status');
                const productRows = Array.from(document.querySelectorAll('.loadout-product-row'));
                const productEmpty = document.getElementById('loadout-product-empty');
                const productCount = document.getElementById('loadout-product-filter-count');
                const mobileLoadoutCount = document.getElementById('mobile-loadout-count');
                const mobileAddonCount = document.getElementById('mobile-addon-count');
                const mobileLoadoutSaved = document.getElementById('mobile-loadout-saved');
                const mobileAddonSaved = document.getElementById('mobile-addon-saved');

                const inlineAddonToggle = document.getElementById('toggle-inline-addon');
                const inlineAddonPanel = document.getElementById('inline-addon-panel');
                const inlineAddonCombobox = document.getElementById('inline-addon-combobox');
                const inlineAddonTrigger = document.getElementById('inline-addon-combobox-trigger');
                const inlineAddonDropdown = document.getElementById('inline-addon-combobox-panel');
                const inlineAddonSearch = document.getElementById('inline-addon-combobox-search');
                const inlineAddonHiddenInput = document.getElementById('inline-addon-product-id');
                const inlineAddonSelectedLabel = document.getElementById('inline-addon-selected-label');
                const inlineAddonOptions = Array.from(document.querySelectorAll('.inline-addon-option'));
                const inlineAddonGroups = Array.from(document.querySelectorAll('.inline-addon-category-group'));
                const inlineAddonEmpty = document.getElementById('inline-addon-combobox-empty');

                const loadoutForm = document.getElementById('loadout-form');
                let autosaveTimer = null;

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
                }

                function markMobileSavedState() {
                    mobileLoadoutSaved?.classList.remove('hidden');
                    mobileAddonSaved?.classList.remove('hidden');
                }

                function scheduleAutosave() {
                    if (!loadoutForm) {
                        return;
                    }

                    mobileLoadoutSaved?.classList.add('hidden');
                    mobileAddonSaved?.classList.add('hidden');

                    if (autosaveTimer) {
                        clearTimeout(autosaveTimer);
                    }

                    autosaveTimer = window.setTimeout(function () {
                        loadoutForm.requestSubmit();
                    }, 600);
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

                // AJAX submit for loadout form without page reload
                if (loadoutForm) {
                    loadoutForm.addEventListener('submit', async function (e) {
                        e.preventDefault();

                        const submitter = e.submitter;
                        const feedbackEl = document.getElementById('loadout-action-feedback');

                        let originalText = '';
                        if (submitter) {
                            originalText = submitter.innerHTML;
                            submitter.disabled = true;
                            submitter.innerHTML = 'Saving...';
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
                                        ? 'Loadout saved. Duplicate rows still exist for ' + duplicateCount + ' product(s). Merge them from the top warning panel.'
                                        : 'Loadout saved & stock updated successfully! ✓';
                                    setTimeout(() => { feedbackEl.classList.add('hidden'); }, 3500);
                                }

                                updateMobileTopInfo();
                                markMobileSavedState();

                                if (submitter) {
                                    submitter.disabled = false;
                                    submitter.innerHTML = 'Saved ✓';
                                    setTimeout(() => { submitter.innerHTML = originalText; }, 2000);
                                }
                            } else {
                                if (feedbackEl) {
                                    feedbackEl.classList.remove('border-cyan-200', 'bg-cyan-50', 'text-cyan-800');
                                    feedbackEl.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-800');
                                    feedbackEl.textContent = data.message || 'Error saving loadout. Please check inputs.';
                                }
                                if (submitter) {
                                    submitter.disabled = false;
                                    submitter.innerHTML = originalText;
                                }
                            }
                        } catch (err) {
                            if (feedbackEl) {
                                feedbackEl.classList.remove('border-cyan-200', 'bg-cyan-50', 'text-cyan-800');
                                feedbackEl.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-800');
                                feedbackEl.textContent = 'Network error while saving loadout.';
                            }
                            if (submitter) {
                                submitter.disabled = false;
                                submitter.innerHTML = originalText;
                            }
                        }
                    });
                }

                document.querySelectorAll('.qty-input').forEach(function (input) {
                    const approved = parseFloat(input.dataset.approved);
                    const entered = parseFloat(input.value) || 0;
                    const productId = input.id.replace('qty-', '');
                    updateFullButtonState(productId, entered, approved);
                    updateSaveQtyButtonState(productId, entered, approved);
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

                // Inline validation: warn if entered qty > available
                document.querySelectorAll('.qty-input').forEach(function (input) {
                    const onQtyChanged = function () {
                        const approved = parseFloat(input.dataset.approved);
                        const available = parseFloat(input.dataset.available);
                        const entered = parseFloat(input.value) || 0;
                        const productName = input.dataset.product;
                        const productId = input.id.replace('qty-', '');
                        const normalizedEntered = parseFloat(input.value) || 0;
                        updateFullButtonState(productId, normalizedEntered, approved);
                        updateSaveQtyButtonState(productId, normalizedEntered, approved);

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
                        scheduleAutosave();
                    };

                    input.addEventListener('input', onQtyChanged);
                    input.addEventListener('change', onQtyChanged);
                });

                document.querySelectorAll('[id^="unit-qty-"]').forEach(function (input) {
                    const onUnitQtyChanged = function () {
                        updateMobileTopInfo();
                        scheduleAutosave();
                    };

                    input.addEventListener('input', onUnitQtyChanged);
                    input.addEventListener('change', onUnitQtyChanged);
                });

                updateMobileTopInfo();
            });
        </script>
    @endpush
</x-layouts.app>