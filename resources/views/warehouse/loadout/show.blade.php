<x-layouts.app title="Loadout — {{ $shopOrder->loadoutDisplayName() }}">
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
        @endphp

        @if($canEdit || $canMoveToDelivery || $canMoveToPartialDelivery)
            <div class="grid gap-2 {{ ($canMoveToDelivery || $canMoveToPartialDelivery) && $canEdit ? 'grid-cols-2' : 'grid-cols-1' }}">
                @if($canEdit)
                    <button type="submit"
                            form="loadout-form"
                            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-4 py-3 text-xs font-black uppercase tracking-[0.14em] text-white shadow-sm transition-all hover:bg-indigo-700 active:scale-[0.98] border-none cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12.75 10.5 18 19 6" />
                        </svg>
                        Save Loadout
                    </button>
                @endif

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
                            Out for Delivery
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
                            Partial Delivery
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

        @php
            $loadoutProductCategories = collect($productGroups)
                ->map(fn (array $group): string => (string) ($group['product']->category?->name ?? 'Other'))
                ->filter()
                ->unique()
                ->sort()
                ->values();
        @endphp

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
            <div class="grid gap-2 sm:grid-cols-[1fr_150px_140px]">
                <div>
                    <label for="loadout-product-search" class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Find Product</label>
                    <input id="loadout-product-search" type="search" placeholder="Search product, SKU, category..." class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none">
                </div>
                <div class="relative">
                    <label for="loadout-product-category" class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Category</label>
                    <select id="loadout-product-category" class="h-11 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-9 text-xs font-black text-slate-700 focus:border-indigo-500 focus:bg-white focus:outline-none">
                        <option value="">All Categories</option>
                        @foreach($loadoutProductCategories as $categoryName)
                            <option value="{{ strtolower($categoryName) }}">{{ $categoryName }}</option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 top-5 flex items-center pr-3 text-slate-400">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
                <div class="relative">
                    <label for="loadout-product-status" class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Status</label>
                    <select id="loadout-product-status" class="h-11 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-9 text-xs font-black text-slate-700 focus:border-indigo-500 focus:bg-white focus:outline-none">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="partial">Partial</option>
                        <option value="loaded">Loaded</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 top-5 flex items-center pr-3 text-slate-400">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
            </div>
            <p id="loadout-product-filter-count" class="mt-2 text-[11px] font-bold text-slate-500">{{ collect($productGroups)->count() }} product(s)</p>
        </section>

        @if($canEdit)
            {{-- ─── LOADOUT FORM ─── --}}
            <form id="loadout-form"
                  action="{{ route('warehouse.loadout.save', $shopOrder) }}"
                  method="POST"
                  class="space-y-3">
                @csrf

                {{-- Top action bar: Load All --}}
                <div class="flex items-center justify-between gap-3 px-1">
                    <h2 class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Items Checklist</h2>
                    <button type="button"
                            onclick="loadAllFull()"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white border-none cursor-pointer transition-colors shadow-sm">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Load All Full Qty
                    </button>
                </div>
                <p class="px-1 text-[11px] font-semibold text-slate-500">
                    Start from <span class="font-black text-slate-700">0.00</span>, tap <span class="font-black text-indigo-600">FULL</span> for the approved quantity, or enter a custom quantity like 4.00.
                </p>
                <p class="px-1 text-[11px] font-semibold text-emerald-700">
                    Saving loadout updates inventory stock immediately.
                </p>
                <div id="loadout-action-feedback"
                     class="hidden rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-xs font-semibold text-cyan-800 shadow-sm">
                </div>

                {{-- Product rows (grouped) --}}
                <div class="space-y-2.5" id="loadout-product-list">
                    @foreach($productGroups as $group)
                        @php
                            $isFullyLoaded = $group['is_fully_loaded'];
                            $isPartial = $group['is_partially_loaded'];
                            $loadoutRowStatus = $isFullyLoaded ? 'loaded' : ($isPartial ? 'partial' : 'pending');
                            $loadoutCategoryName = $group['product']->category?->name ?? 'Other';
                            $approved = $group['total_approved'];
                            $loaded = $group['total_loaded'];
                            $balance = $group['total_balance'];
                            $available = $group['available_stock'];
                            $stockShort = $available < $approved;
                        @endphp

                        <div class="loadout-product-row rounded-2xl border bg-white p-4 shadow-sm transition
                            {{ $isFullyLoaded ? 'border-emerald-200 bg-emerald-50/20' : ($isPartial ? 'border-amber-200 bg-amber-50/10' : 'border-slate-200') }}"
                             data-search="{{ strtolower(trim(($group['product']->name ?? '').' '.($group['product']->sku ?? '').' '.$loadoutCategoryName)) }}"
                             data-category="{{ strtolower($loadoutCategoryName) }}"
                             data-status="{{ $loadoutRowStatus }}">

                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <h3 class="truncate text-sm font-black text-slate-900">{{ $group['product']->name }}</h3>
                                    @if (! empty($group['items']))
                                        @php
                                            $loadoutMeasures = collect($group['items'])->map(fn ($item) => $item->requestedMeasureBreakdownLabel())->filter()->unique();
                                        @endphp
                                        @if ($loadoutMeasures->isNotEmpty())
                                            <p class="mt-0.5 text-[10px] font-black uppercase tracking-[0.1em] text-emerald-700">
                                                {{ $loadoutMeasures->implode(' · ') }}
                                            </p>
                                        @endif
                                    @endif
                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] font-semibold text-slate-500">
                                        <span>Approved: <span class="font-black text-slate-700">{{ number_format($approved, 2) }} {{ $group['unit'] }}</span></span>
                                        <span class="{{ $stockShort ? 'text-rose-600 font-black' : '' }}">
                                            Available: <span class="font-black">{{ number_format($available, 2) }} {{ $group['unit'] }}</span>
                                        </span>
                                    </div>
                                    @if($balance > 0.001 && ! $isFullyLoaded)
                                        <p class="mt-0.5 text-[10px] font-bold text-amber-600">
                                            Balance: {{ number_format($balance, 2) }} {{ $group['unit'] }} remaining
                                        </p>
                                    @endif
                                </div>

                                @if($isFullyLoaded)
                                    <span class="mt-1 shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-700">Loaded ✓</span>
                                @elseif($isPartial)
                                    <span class="mt-1 shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-amber-700">Partial</span>
                                @endif
                            </div>

                            {{-- Qty input row --}}
                            <div class="mt-3 flex items-center gap-2">
                                {{-- Stepper minus --}}
                                <button type="button"
                                        onclick="stepQty({{ $group['product_id'] }}, -1)"
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-slate-600 hover:bg-slate-200 cursor-pointer transition-colors border-none">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 12H6" />
                                    </svg>
                                </button>

                                <input type="number"
                                       id="qty-{{ $group['product_id'] }}"
                                       name="items[{{ $group['product_id'] }}]"
                                       value="{{ number_format($loaded, 2, '.', '') }}"
                                       min="0"
                                       max="{{ $approved }}"
                                       step="0.01"
                                       inputmode="decimal"
                                       data-approved="{{ $approved }}"
                                       data-available="{{ $available }}"
                                       data-product="{{ $group['product']->name }}"
                                       data-unit="{{ $group['unit'] }}"
                                       class="qty-input flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-center text-sm font-black text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400"
                                       required>

                                {{-- Stepper plus --}}
                                <button type="button"
                                        onclick="stepQty({{ $group['product_id'] }}, 1)"
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 cursor-pointer transition-colors border-none">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </button>

                                {{-- Load Full Qty --}}
                                <button type="button"
                                        id="full-btn-{{ $group['product_id'] }}"
                                        onclick="handleFullAction({{ $group['product_id'] }})"
                                        class="shrink-0 rounded-xl border border-indigo-200 bg-indigo-50 px-2.5 py-2 text-[10px] font-black uppercase tracking-wider text-indigo-600 hover:bg-indigo-100 cursor-pointer transition-colors border-none">
                                    {{ $loaded >= ($approved - 0.001) && $approved > 0 ? 'Save' : 'Full' }}
                                </button>
                            </div>
                            <div class="mt-2 flex items-center gap-2">
                                <button type="button"
                                        id="save-qty-btn-{{ $group['product_id'] }}"
                                        onclick="submitSpecificQty({{ $group['product_id'] }})"
                                        class="{{ $loaded > 0.001 && $loaded < ($approved - 0.001) ? '' : 'hidden' }} rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-cyan-700 transition-colors hover:bg-cyan-100 cursor-pointer border-none">
                                    Save Qty
                                </button>
                            </div>
                            <p id="status-{{ $group['product_id'] }}" class="mt-2 hidden text-[10px] font-black uppercase tracking-[0.12em] text-cyan-700"></p>
                        </div>
                    @endforeach
                </div>
            </form>

            <div id="loadout-product-empty" class="hidden rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm font-bold text-slate-500">
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
                         data-search="{{ strtolower(trim(($group['product']->name ?? '').' '.($group['product']->sku ?? '').' '.$loadoutCategoryName)) }}"
                         data-category="{{ strtolower($loadoutCategoryName) }}"
                         data-status="{{ $loadoutRowStatus }}">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-black text-slate-900">{{ $group['product']->name }}</p>
                                @if (! empty($group['items']))
                                    @php
                                        $loadoutMeasures = collect($group['items'])->map(fn ($item) => $item->requestedMeasureBreakdownLabel())->filter()->unique();
                                    @endphp
                                    @if ($loadoutMeasures->isNotEmpty())
                                        <p class="mt-0.5 text-[10px] font-black uppercase tracking-[0.1em] text-emerald-700">
                                            {{ $loadoutMeasures->implode(' · ') }}
                                        </p>
                                    @endif
                                @endif
                                <p class="mt-0.5 text-[11px] font-semibold text-slate-500">
                                    Loaded: <span class="font-black text-slate-800">{{ number_format($group['total_loaded'], 2) }} {{ $group['unit'] }}</span>
                                    @if($group['total_balance'] > 0)
                                        &middot; Balance: <span class="text-amber-600 font-black">{{ number_format($group['total_balance'], 2) }} {{ $group['unit'] }}</span>
                                    @endif
                                </p>
                            </div>
                            @if($group['is_fully_loaded'])
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-700">Loaded ✓</span>
                            @else
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-amber-700">Partial</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div id="loadout-product-empty" class="hidden rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm font-bold text-slate-500">
                No products match these filters.
            </div>

            @php
                $deliveryDiscrepancyItems = $shopOrder->items->filter(
                    fn ($item) => (float) ($item->shortage_qty ?? 0) > 0.001 || filled($item->delivery_discrepancy_note)
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

    @if($canMoveToLoadout || $shopOrder->delivery_status === 'delivered' || $canEdit)
    {{-- ─── STICKY BOTTOM ACTION BAR ─── --}}
    <div class="fixed inset-x-0 bottom-[5.5rem] z-50 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-[0_-8px_24px_rgba(15,23,42,0.10)] backdrop-blur-sm lg:bottom-0"
         id="sticky-bar">
        <div class="mx-auto flex max-w-xl flex-col gap-2">

            @if($canMoveToLoadout)
                {{-- Status: in_transit → allow return to loadout only --}}
                <div class="mb-3 text-center">
                    <p class="text-[10px] font-semibold text-indigo-600">Order is out for delivery</p>
                </div>
                <div class="flex gap-2">
                    <form action="{{ route('warehouse.loadout.move-to-loadout', $shopOrder) }}"
                          method="POST"
                          class="loadout-confirm-form w-full"
                          data-confirm-title="Move Back to Loadout"
                          data-confirm-message="Move this order back to loadout so you can update the loaded quantities?"
                          data-confirm-button="Move to Loadout">
                        @csrf
                        <button type="submit"
                                class="w-full rounded-xl border border-slate-200 bg-slate-100 py-3 text-xs font-black uppercase tracking-wider text-slate-700 transition-all hover:bg-slate-200 active:scale-[0.98] border-none cursor-pointer">
                            Move to Loadout
                        </button>
                    </form>
                </div>

            @elseif($shopOrder->delivery_status === 'delivered')
                {{-- Delivered --}}
                <div class="flex items-center justify-center gap-2 py-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 border border-emerald-200 px-4 py-2 text-xs font-black uppercase tracking-wider text-emerald-700">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Delivered
                    </span>
                </div>

            @elseif($canEdit)
                @if($canMoveToDelivery)
                    {{-- Has loaded items → show both Save Changes and Move to Delivery --}}
                    <div class="text-center">
                        <p class="text-[9px] font-semibold text-emerald-700">Inventory already updated from saved loadout. Move to Delivery only changes the delivery status.</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit"
                                form="loadout-form"
                                class="flex-1 rounded-xl border border-slate-200 bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 text-xs font-black uppercase tracking-wider border-none cursor-pointer transition-all active:scale-[0.98]">
                            Save Changes
                        </button>
                        <form action="{{ route('warehouse.loadout.move-to-delivery', $shopOrder) }}"
                              method="POST"
                              id="move-delivery-form"
                              class="loadout-confirm-form flex-1"
                              data-confirm-title="Move to Delivery"
                              data-confirm-message="Move this order to delivery? This will reduce inventory for all loaded items."
                              data-confirm-button="Move to Delivery">
                            @csrf
                            <button type="submit"
                                    class="w-full rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white py-3 text-xs font-black uppercase tracking-wider shadow border-none cursor-pointer transition-all active:scale-[0.98]">
                                    Move to Delivery
                            </button>
                        </form>
                    </div>
                @elseif($canMoveToPartialDelivery)
                    <div class="text-center">
                        <p class="text-[9px] font-semibold text-amber-700">{{ $remainingProductCount }} product line(s) still have balance. Inventory is already updated for loaded items; use partial delivery to continue with the current load.</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit"
                                form="loadout-form"
                                class="flex-1 rounded-xl border border-slate-200 bg-slate-100 hover:bg-slate-200 text-slate-700 py-3 text-xs font-black uppercase tracking-wider border-none cursor-pointer transition-all active:scale-[0.98]">
                            Save Changes
                        </button>
                        <form action="{{ route('warehouse.loadout.move-to-partial-delivery', $shopOrder) }}"
                              method="POST"
                              id="move-partial-delivery-form"
                              class="loadout-confirm-form flex-1"
                              data-confirm-title="Move to Partial Delivery"
                              data-confirm-message="This order still has {{ $remainingProductCount }} product line(s) not fully loaded. Move it to delivery as a partial delivery?"
                              data-confirm-button="Move to Partial Delivery">
                            @csrf
                            <button type="submit"
                                    class="w-full rounded-xl bg-amber-500 hover:bg-amber-600 text-white py-3 text-xs font-black uppercase tracking-wider shadow border-none cursor-pointer transition-all active:scale-[0.98]">
                                Partial Delivery
                            </button>
                        </form>
                    </div>
                @else
                    {{-- No items loaded yet → Save Loadout only --}}
                    <button type="submit"
                            form="loadout-form"
                            class="w-full rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white py-3 text-xs font-black uppercase tracking-wider shadow border-none cursor-pointer transition-all active:scale-[0.98]">
                        Save Loadout
                    </button>
                @endif
            @endif

        </div>
    </div>
    @endif

    @push('scripts')
    <script>
        const loadoutForm = document.getElementById('loadout-form');
        const loadoutActionFeedback = document.getElementById('loadout-action-feedback');

        function formatLoadoutQty(value) {
            return (parseFloat(value) || 0).toFixed(2);
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
                const approved = formatLoadoutQty(input.dataset.approved);
                const previous = formatLoadoutQty(input.value);
                const productName = input.dataset.product;
                const unit = input.dataset.unit;

                input.value = approved;
                input.dispatchEvent(new Event('change'));
                pulseInput(input);
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
            const changedItems = [];
            const alreadyFullItems = [];

            document.querySelectorAll('.qty-input').forEach(function (input) {
                const approved = formatLoadoutQty(input.dataset.approved);
                const current = formatLoadoutQty(input.value);
                const productName = input.dataset.product;
                const unit = input.dataset.unit;

                input.value = approved;
                input.dispatchEvent(new Event('change'));
                pulseInput(input);

                if (current === approved) {
                    alreadyFullItems.push(productName + ' (' + approved + ' ' + unit + ')');
                } else {
                    changedItems.push(productName + ' (' + approved + ' ' + unit + ')');
                }
            });

            const changedSummary = changedItems.length > 0
                ? 'Set to full qty: ' + changedItems.join(', ') + '.'
                : 'All items were already at full approved quantity.';
            const alreadyFullSummary = alreadyFullItems.length > 0 && changedItems.length > 0
                ? ' Already full: ' + alreadyFullItems.join(', ') + '.'
                : '';

            showLoadoutFeedback(changedSummary + alreadyFullSummary, changedItems.length > 0 ? 'success' : 'info');

            window.showAppConfirm({
                title: changedItems.length > 0 ? 'Full quantities applied' : 'All quantities already full',
                message: changedSummary + alreadyFullSummary + ' Submit these quantities now?',
                confirmLabel: changedItems.length > 0 ? 'Save Loadout' : 'Submit Anyway',
                cancelLabel: 'Keep Editing',
                tone: changedItems.length > 0 ? 'success' : 'info',
                onConfirm: function () {
                    if (loadoutForm) {
                        loadoutForm.submit();
                    }
                },
            });
        }

        // Increment/decrement a qty input by step (in 0.5 units)
        function stepQty(productId, direction) {
            const input = document.getElementById('qty-' + productId);
            if (!input) return;
            const step = 0.5;
            const approved = parseFloat(input.dataset.approved);
            let current = parseFloat(input.value) || 0;
            current = Math.max(0, Math.min(approved, current + direction * step));
            input.value = current.toFixed(2);
            input.dispatchEvent(new Event('change'));
        }

        // Confirm modal for forms
        document.addEventListener('DOMContentLoaded', function () {
            const productSearch = document.getElementById('loadout-product-search');
            const productCategory = document.getElementById('loadout-product-category');
            const productStatus = document.getElementById('loadout-product-status');
            const productRows = Array.from(document.querySelectorAll('.loadout-product-row'));
            const productEmpty = document.getElementById('loadout-product-empty');
            const productCount = document.getElementById('loadout-product-filter-count');

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
            filterLoadoutProducts();

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
                input.addEventListener('change', function () {
                    const approved = parseFloat(input.dataset.approved);
                    const available = parseFloat(input.dataset.available);
                    const entered = parseFloat(input.value) || 0;
                    const productName = input.dataset.product;
                    const productId = input.id.replace('qty-', '');

                    if (entered > approved) {
                        input.value = approved.toFixed(2);
                        alert('Loaded quantity cannot exceed approved qty (' + approved + ') for ' + productName + '.');
                    }

                    const normalizedEntered = parseFloat(input.value) || 0;
                    updateFullButtonState(productId, normalizedEntered, approved);
                    updateSaveQtyButtonState(productId, normalizedEntered, approved);

                    if (normalizedEntered > available) {
                        input.style.borderColor = '#f43f5e';
                        input.title = 'Warning: exceeds available stock (' + available + ')';
                        setRowStatus(productId, 'Above available stock');
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
                });
            });
        });
    </script>
    @endpush
</x-layouts.app>
