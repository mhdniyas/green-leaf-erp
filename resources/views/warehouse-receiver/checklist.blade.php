<x-layouts.app title="Warehouse Receive Checklist">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        
        {{-- Hero Header Section --}}
        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_12px_28px_rgba(15,23,42,0.16)] lg:rounded-[2rem] lg:shadow-[0_20px_48px_rgba(15,23,42,0.22)]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(99,102,241,0.25),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#312e81_100%)] px-4 py-4 sm:px-5 lg:px-6 lg:py-6">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-indigo-300 sm:text-[11px] sm:tracking-[0.22em]">Warehouse Flow</p>
                        <h1 id="wr-page-heading" class="mt-1 text-lg font-black tracking-tight sm:text-[1.75rem]">Receive Checklist</h1>
                        <p class="mt-1.5 max-w-xl text-xs font-medium leading-5 text-slate-200 sm:text-sm">Manage physical goods receipt, track inventory status, and process dispatch loadouts.</p>
                    </div>
                    <form action="{{ route('warehouse.receiver.checklist') }}" method="GET" class="w-full md:w-auto">
                        <label for="business-date" class="text-[11px] font-black uppercase tracking-[0.16em] text-indigo-100">Business Date</label>
                        <input id="business-date" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="mt-1.5 h-12 w-full rounded-xl border border-white/10 bg-white/10 px-3 text-sm font-bold text-white outline-none ring-0 md:w-48 lg:rounded-2xl lg:px-4 cursor-pointer">
                    </form>
                </div>

                {{-- Stats Grid for context --}}
            </div>
        </section>

        {{-- Tab Panels --}}
        <div class="mt-2">
            
            {{-- TAB: Receive (Pending) --}}
            <div id="tab-pending" class="wr-tab active space-y-4">
                @php
                    $receiveFiltersActive = filled($receiveSearch) || ($receiveSource ?? 'all') !== 'all' || filled($receiveCategoryId);
                @endphp
                <form action="{{ route('warehouse.receiver.checklist') }}" method="GET" class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
                    <input type="hidden" name="date" value="{{ $date }}">
                    <input type="hidden" name="tab" value="pending">
                    <div class="grid gap-2 md:grid-cols-[1fr_180px_220px_auto] md:items-end">
                        <div>
                            <label for="receive-search" class="mb-1 block text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">Search Delivery</label>
                            <input id="receive-search" type="search" name="receive_search" value="{{ $receiveSearch }}" placeholder="Product, vendor, GRN, purchaser, order..." class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none">
                        </div>
                        <div class="relative">
                            <label for="receive-source" class="mb-1 block text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">Source</label>
                            <select id="receive-source" name="receive_source" class="w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 py-3 pl-3 pr-9 text-xs font-black text-slate-700 focus:border-indigo-500 focus:bg-white focus:outline-none">
                                <option value="all" @selected(($receiveSource ?? 'all') === 'all')>All Sources</option>
                                <option value="vendor" @selected(($receiveSource ?? 'all') === 'vendor')>Vendor Sheets</option>
                                <option value="direct" @selected(($receiveSource ?? 'all') === 'direct')>Direct Purchases</option>
                                <option value="batch" @selected(($receiveSource ?? 'all') === 'batch')>Pending Batches</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 top-5 flex items-center pr-3 text-slate-400">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                        <div class="relative">
                            <label for="receive-category" class="mb-1 block text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">Category</label>
                            <select id="receive-category" name="receive_category_id" class="w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 py-3 pl-3 pr-9 text-xs font-black text-slate-700 focus:border-indigo-500 focus:bg-white focus:outline-none">
                                <option value="">All Categories</option>
                                @foreach($receiveCategories as $category)
                                    <option value="{{ $category->id }}" @selected((int) $receiveCategoryId === (int) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 top-5 flex items-center pr-3 text-slate-400">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            @if($receiveFiltersActive)
                                <a href="{{ route('warehouse.receiver.checklist', ['date' => $date, 'tab' => 'pending']) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-3 text-xs font-black text-slate-600 transition-colors hover:bg-slate-50">Clear</a>
                            @endif
                            <button type="submit" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-indigo-600 px-4 text-xs font-black text-white transition-colors hover:bg-indigo-700 md:flex-none">Search</button>
                        </div>
                    </div>
                </form>

                @if($pendingGrns->isEmpty() && $pendingBatches->isEmpty() && $pendingDirectPurchaseOrders->isEmpty())
                    <div class="rounded-3xl border border-slate-200 bg-white p-10 text-center shadow-sm lg:rounded-[2rem]">
                        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 border border-emerald-100">
                            <svg class="h-7 w-7 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">{{ $receiveFiltersActive ? 'No matching deliveries' : 'All Clear!' }}</h3>
                        <p class="mt-1 text-xs text-slate-500">
                            @if($receiveFiltersActive)
                                No pending warehouse deliveries match these filters for {{ $date }}.
                            @else
                                No pending sheets or batches for {{ $date }}.<br>All stock is in inventory.
                            @endif
                        </p>
                    </div>
                @else
                    {{-- Pending GRNs / Vendor Sheets --}}
                    @if(!$pendingGrns->isEmpty())
                        <div class="space-y-3">
                            <h3 class="text-xs font-black uppercase tracking-[0.14em] text-slate-500 pl-1">Pending Vendor Sheets</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach($pendingGrns as $grn)
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex items-center justify-between gap-3 transition hover:border-slate-300">
                                        <div class="min-w-0 flex-1">
                                            <span class="inline-flex items-center rounded-full bg-indigo-50 border border-indigo-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-indigo-700">
                                                {{ $grn->purchaseOrder?->supplier?->name ?? 'Vendor' }}
                                            </span>
                                            <h4 class="text-sm font-black text-slate-900 mt-1.5">{{ $grn->grn_number }}</h4>
                                            <p class="text-[10px] text-slate-400 font-medium">Purchased by: {{ $grn->purchaseOrder?->purchaserCart?->user?->name ?? 'Purchaser' }}</p>
                                            <p class="text-[10px] text-slate-500 font-bold mt-1">
                                                Items: {{ $grn->items->count() }} · {{ number_format((float) $grn->items->sum('received_qty'), 2) }} kg
                                            </p>
                                        </div>
                                        <a href="{{ route('warehouse.receiver.receive-grn', $grn) }}" class="shrink-0 inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl px-3.5 py-2.5 text-xs font-black shadow-sm transition-colors text-decoration-none border-none">
                                            <span>Open Sheet</span>
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                            </svg>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(!$pendingDirectPurchaseOrders->isEmpty())
                        <div class="space-y-3 mt-4">
                            <h3 class="text-xs font-black uppercase tracking-[0.14em] text-slate-500 pl-1">Pending Direct Purchases</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach($pendingDirectPurchaseOrders as $order)
                                    <div class="rounded-2xl border border-emerald-200 bg-white p-4 shadow-sm">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-700">
                                                    Direct Purchase
                                                </span>
                                                <h4 class="mt-1.5 truncate text-sm font-black text-slate-900">{{ $order->order_number }}</h4>
                                                <p class="mt-1 text-[10px] font-bold text-slate-500">
                                                    {{ $order->items->count() }} item(s) · {{ number_format((float) $order->items->sum(fn ($item) => $item->approved_qty > 0 ? $item->approved_qty : $item->requested_qty), 2) }} kg
                                                </p>
                                            </div>
                                        </div>

                                        <div class="mt-3 space-y-1.5 rounded-xl bg-slate-50 p-3">
                                            @foreach($order->items->take(4) as $item)
                                                <div class="flex items-center justify-between gap-3 text-[11px] font-bold text-slate-600">
                                                    <span class="truncate">{{ $item->product?->name ?? 'Product #'.$item->product_id }}</span>
                                                    <span class="shrink-0 text-slate-900">{{ number_format((float) ($item->approved_qty > 0 ? $item->approved_qty : $item->requested_qty), 2) }} {{ $item->unit }}</span>
                                                </div>
                                            @endforeach
                                        </div>

                                        <form action="{{ route('warehouse.receiver.direct-purchase.receive', $order) }}" method="POST" class="warehouse-confirm-form mt-3 flex items-center gap-2 border-t border-dashed border-slate-100 pt-3"
                                              data-confirm-title="Receive direct purchase"
                                              data-confirm-message="Receive {{ $order->order_number }} directly into warehouse inventory?"
                                              data-confirm-button="Receive">
                                            @csrf
                                            <div class="relative min-w-0 flex-1">
                                                <select name="warehouse_id" required class="w-full appearance-none rounded-xl border border-slate-200 bg-white py-2.5 pl-3 pr-9 text-xs font-semibold text-slate-900 shadow-sm transition-all hover:bg-slate-50/50 focus:border-indigo-500 focus:outline-none cursor-pointer">
                                                    @foreach($warehouses as $wh)
                                                        <option value="{{ $wh->id }}">
                                                            {{ $wh->name }} ({{ $wh->code }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </div>
                                            </div>
                                            <button type="submit" class="shrink-0 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-black text-white shadow-sm transition-colors hover:bg-emerald-700 border-none cursor-pointer">
                                                Receive
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Simple Pending Batches --}}
                    @if(!$pendingBatches->isEmpty())
                        <div class="space-y-3 mt-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pl-1">
                                <h3 class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Pending Batches</h3>
                                
                                {{-- Confirm All Form --}}
                                <form action="{{ route('warehouse.receiver.confirm-all') }}" method="POST" class="warehouse-confirm-form w-full sm:w-auto"
                                    data-confirm-title="Confirm all batches"
                                    data-confirm-message="Confirm ALL {{ $pendingBatches->count() }} batch(es) as received? This will move them into active inventory."
                                    data-confirm-button="Confirm all">
                                    @csrf
                                    <input type="hidden" name="date" value="{{ $date }}">
                                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white rounded-xl px-4 py-2.5 text-xs font-black shadow-md transition-all active:scale-98 border-none cursor-pointer">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                        </svg>
                                        Confirm All {{ $pendingBatches->count() }} into Inventory
                                    </button>
                                </form>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach($pendingBatches as $batch)
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex flex-col gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 border border-amber-100">
                                                <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h4 class="truncate text-sm font-black text-slate-950">{{ $batch->product->name }}</h4>
                                                <div class="flex items-center gap-2 mt-0.5">
                                                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded-md">
                                                        {{ number_format((float) $batch->total_kg, 2) }} {{ $batch->product->unit }}
                                                    </span>
                                                    <span class="text-[10px] text-slate-400 font-mono">{{ $batch->reference }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <form action="{{ route('warehouse.receiver.confirm', $batch) }}" method="POST" class="flex items-center gap-2 pt-3 border-t border-dashed border-slate-100">
                                            @csrf
                                                                          <div class="flex-1 min-w-0 relative">
                                                <select name="warehouse_id" required class="w-full appearance-none rounded-xl border border-slate-200 bg-white pl-3 pr-9 py-2.5 text-xs font-semibold text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none cursor-pointer transition-all hover:bg-slate-50/50">
                                                    @foreach($warehouses as $wh)
                                                        <option value="{{ $wh->id }}" @selected(old('warehouse_id', $batch->product->default_warehouse_id) == $wh->id)>
                                                            {{ $wh->name }} ({{ $wh->code }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </div>
                                            </div>
                                            <button type="submit" class="shrink-0 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 text-xs font-black shadow-sm transition-colors border-none cursor-pointer">
                                                ✓ Received
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            {{-- TAB: Inventory --}}
            <div id="tab-inventory" class="wr-tab hidden space-y-4">
                <form action="{{ route('warehouse.receiver.checklist') }}" method="GET" class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                    <input type="hidden" name="date" value="{{ $date }}">
                    <input type="hidden" name="tab" value="inventory">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <div class="relative w-full sm:max-w-xs">
                            <label for="inventory-warehouse-filter" class="mb-1 block text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">Warehouse</label>
                            <select id="inventory-warehouse-filter" name="warehouse_id" onchange="this.form.submit()" class="w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-9 py-2.5 text-xs font-semibold text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none cursor-pointer shadow-sm hover:bg-slate-100/50 transition-colors">
                                <option value="">All Warehouses</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected($selectedWarehouseId === $warehouse->id)>
                                        {{ $warehouse->name }} ({{ $warehouse->code }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 top-5 flex items-center pr-3 text-slate-400">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                        @if($selectedWarehouseId)
                            <a href="{{ route('warehouse.receiver.checklist', ['date' => $date, 'tab' => 'inventory']) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-black text-slate-600 transition-colors hover:bg-slate-50">
                                Clear Filter
                            </a>
                        @endif
                    </div>
                </form>
                
                {{-- Search & Category Filter --}}
                <div class="flex flex-col gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <input type="search" id="inventory-search" oninput="filterInventory()" placeholder="Search product..." class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none">
                    </div>
                    <div class="relative w-full sm:w-48 shrink-0">
                        <select id="inventory-category-filter" onchange="filterInventory()" class="w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-9 py-2.5 text-xs font-semibold text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none cursor-pointer shadow-sm hover:bg-slate-100/50 transition-colors">
                            <option value="">All Categories</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- Sub-tab Switcher --}}
                <div class="flex rounded-2xl bg-slate-200/60 p-1 max-w-sm">
                    <button type="button" onclick="switchInvSubTab('in')" id="subtab-in" class="flex-1 rounded-xl py-2 text-center text-xs font-black transition-all bg-slate-950 text-white shadow-sm border-none cursor-pointer">IN (+)</button>
                    <button type="button" onclick="switchInvSubTab('out')" id="subtab-out" class="flex-1 rounded-xl py-2 text-center text-xs font-black transition-all text-slate-500 hover:text-slate-900 border-none cursor-pointer">OUT (-)</button>
                    <button type="button" onclick="switchInvSubTab('stock')" id="subtab-stock" class="flex-1 rounded-xl py-2 text-center text-xs font-black transition-all text-slate-500 hover:text-slate-900 border-none cursor-pointer">STOCK</button>
                </div>

                {{-- IN Sub-tab Panel --}}
                <div id="inv-subtab-in" class="inv-subtab-panel space-y-3">
                    @if($inflows->isEmpty())
                        <div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                            <p class="text-xs text-slate-500">No recent inflows logged.</p>
                        </div>
                    @else
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <table class="w-full border-collapse text-left text-xs table-fixed">
                                <thead>
                                    <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                        <th class="py-3 px-4 w-2/3">Product</th>
                                        <th class="py-3 px-4 text-right w-1/3">Quantity</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                                    @foreach($inflows as $mov)
                                        <tr class="inv-inflow-row hover:bg-slate-50/80 transition-colors"
                                            data-product-name="{{ strtolower($mov->product_name) }}"
                                            data-category="{{ strtolower($mov->category_name ?? 'Other') }}"
                                            data-category-display="{{ $mov->category_name ?? 'Other' }}">
                                            <td class="py-3 px-4">
                                                <div class="font-bold text-slate-900 truncate">{{ $mov->product_name }}</div>
                                                @if($mov->reference)
                                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5 truncate">Ref: {{ $mov->reference }}</div>
                                                @endif
                                            </td>
                                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                                <div class="inline-flex items-center gap-1.5 justify-end">
                                                    <span class="font-black text-emerald-600 text-sm">
                                                        +{{ number_format((float) $mov->quantity, 2) }} <span class="text-[10px] text-slate-400 font-medium">{{ $mov->unit }}</span>
                                                    </span>
                                                    <svg class="h-4 w-4 text-slate-400 hover:text-slate-600 cursor-pointer shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" title="Time: {{ $mov->time_formatted }}">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- OUT Sub-tab Panel --}}
                <div id="inv-subtab-out" class="inv-subtab-panel hidden space-y-3">
                    @if($outMovements->isEmpty())
                        <div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                            <p class="text-xs text-slate-500">No recent outflows logged.</p>
                        </div>
                    @else
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <table class="w-full border-collapse text-left text-xs table-fixed">
                                <thead>
                                    <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                        <th class="py-3 px-4 w-2/3">Product</th>
                                        <th class="py-3 px-4 text-right w-1/3">Quantity</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                                    @foreach($outMovements as $mov)
                                        <tr class="inv-outflow-row hover:bg-slate-50/80 transition-colors"
                                            data-product-name="{{ strtolower($mov->product->name) }}"
                                            data-category="{{ strtolower($mov->product->category->name ?? 'Other') }}"
                                            data-category-display="{{ $mov->product->category->name ?? 'Other' }}">
                                            <td class="py-3 px-4">
                                                <div class="font-bold text-slate-900 truncate">{{ $mov->product->name }}</div>
                                                <div class="text-[10px] text-rose-600/80 font-bold uppercase tracking-wider mt-0.5 truncate">{{ $mov->type->label() }}</div>
                                            </td>
                                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                                <div class="inline-flex items-center gap-1.5 justify-end">
                                                    <span class="font-black text-rose-600 text-sm">
                                                        -{{ number_format((float) $mov->quantity, 2) }} <span class="text-[10px] text-slate-400 font-medium">{{ $mov->product->unit }}</span>
                                                    </span>
                                                    <svg class="h-4 w-4 text-slate-400 hover:text-slate-600 cursor-pointer shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" title="Time: {{ $mov->created_at->format('H:i') }}">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- STOCK Sub-tab Panel --}}
                <div id="inv-subtab-stock" class="inv-subtab-panel hidden space-y-3">
                    @if($stockLevels->isEmpty())
                        <div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                            <p class="text-xs text-slate-500">No stock currently in inventory.</p>
                        </div>
                    @else
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <table class="w-full border-collapse text-left text-xs table-fixed">
                                <thead>
                                    <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                        <th class="py-3 px-4 w-12 shrink-0"></th>
                                        <th class="py-3 px-4 w-2/3">Product</th>
                                        <th class="py-3 px-4 text-right w-1/3">Current Stock</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                                    @foreach($stockLevels as $level)
                                        <tr class="inv-stock-row hover:bg-slate-50/80 transition-colors"
                                            data-product-name="{{ strtolower($level->product_name) }}"
                                            data-category="{{ strtolower($level->category_name ?? 'Other') }}"
                                            data-category-display="{{ $level->category_name ?? 'Other' }}">
                                            <td class="py-3 px-4 w-12">
                                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 border border-indigo-100 overflow-hidden">
                                                    @if(!empty($level->product_image))
                                                        <img src="{{ $level->product_image }}" class="h-full w-full object-cover" alt="{{ $level->product_name }}">
                                                    @else
                                                        <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                                        </svg>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="font-bold text-slate-900 truncate">{{ $level->product_name }}</div>
                                                @if(!empty($level->product_sku))
                                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5 truncate">Code: {{ $level->product_sku }}</div>
                                                @endif
                                            </td>
                                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                                <div class="inline-flex items-center gap-1.5 justify-end">
                                                    <span class="font-black text-slate-900 text-sm">
                                                        {{ number_format((float) $level->current_stock, 2) }} <span class="text-[10px] text-slate-400 font-medium">kg</span>
                                                    </span>
                                                    @if($level->latest_activity)
                                                        <svg class="h-4 w-4 text-slate-400 hover:text-slate-600 cursor-pointer shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" title="Latest Update: {{ \Carbon\Carbon::parse($level->latest_activity)->format('Y-m-d H:i') }}">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- TAB: Loadout --}}
            <div id="tab-loadout" class="wr-tab hidden space-y-4">
                @if($approvedOrders->isEmpty())
                    <div class="rounded-3xl border border-slate-200 bg-white p-10 text-center shadow-sm">
                        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-slate-50 border border-slate-200">
                            <svg class="h-7 w-7 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 011 1v9M17 16h2a1 1 0 001-1v-4a1 1 0 00-.3-.7l-3-3a1 1 0 00-.7-.3h-2m4 9H9m0-9h8" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">No Approved Orders</h3>
                        <p class="mt-1 text-xs text-slate-500">There are no approved orders to loadout for {{ $date }}.</p>
                    </div>
                @else
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach($approvedOrders as $order)
                            @php
                                $color = match($order->loading_status) {
                                    'Loaded' => 'emerald',
                                    'Partially Loaded' => 'amber',
                                    default => 'slate',
                                };
                            @endphp
                            <a href="{{ route('warehouse.loadout.show', $order) }}" class="block text-decoration-none">
                                <div class="rounded-2xl border border-slate-200 bg-white p-4 flex items-center justify-between gap-3 shadow-sm hover:border-indigo-200 transition-colors">
                                    <div class="min-w-0">
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-500 mb-1.5">
                                            {{ $order->shop->warehouse_tag ?? 'NO TAG' }}
                                        </span>
                                        <h4 class="truncate text-sm font-black text-slate-900">{{ $order->loadoutDisplayName() }}</h4>
                                        <p class="text-[10px] text-slate-400 font-medium">Order: <span class="font-mono">{{ $order->order_number }}</span></p>
                                        <p class="text-[10px] text-slate-500 font-bold mt-1.5">
                                            Progress: {{ $order->loaded_items_count }} / {{ $order->total_items_count }} items loaded
                                        </p>
                                    </div>
                                    <div class="text-right shrink-0 flex flex-col items-end gap-2">
                                        <span class="rounded-full bg-{{ $color }}-50 border border-{{ $color }}-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-{{ $color }}-700">
                                            {{ $order->loading_status }}
                                        </span>
                                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                        </svg>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- TAB: Delivery (Confirmed) --}}
            <div id="tab-confirmed" class="wr-tab hidden space-y-4">
                {{-- Sub-tab Switcher --}}
                <div class="flex rounded-2xl bg-slate-200/60 p-1 max-w-xs">
                    <button type="button" onclick="switchDeliverySubTab('orders')" id="del-subtab-btn-orders" class="flex-1 rounded-xl py-2 text-center text-xs font-black transition-all bg-slate-950 text-white shadow-sm border-none cursor-pointer">
                        Orders
                    </button>
                    <button type="button" onclick="switchDeliverySubTab('items')" id="del-subtab-btn-items" class="flex-1 rounded-xl py-2 text-center text-xs font-black transition-all text-slate-500 hover:text-slate-900 border-none cursor-pointer">
                        Items List
                    </button>
                </div>

                {{-- Sub-tab: Orders --}}
                <div id="del-subtab-orders" class="del-subtab-panel space-y-3">
                    @if($shopOrders->isEmpty())
                        <div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                            <p class="text-xs text-slate-500">No shop orders for {{ $date }}.</p>
                        </div>
                    @else
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach($shopOrders as $order)
                                @php
                                    $fulfillmentColor = 'rose';
                                    $fulfillmentIcon = '✗';
                                    if ($order->fulfillment_percentage === 100) {
                                        $fulfillmentColor = 'emerald';
                                        $fulfillmentIcon = '✓';
                                    } elseif ($order->fulfillment_percentage > 0) {
                                        $fulfillmentColor = 'amber';
                                        $fulfillmentIcon = '⚠';
                                    }

                                    $loadingColor = match($order->loading_status) {
                                        'Loaded' => 'emerald',
                                        'Partially Loaded' => 'amber',
                                        default => 'slate',
                                    };
                                @endphp
                                
                                {{-- Hidden template container for this order's items (used by JS to copy into items list) --}}
                                <div id="order-items-template-{{ $order->id }}" class="hidden">
                                    <div class="mb-4 rounded-2xl bg-slate-50 border border-slate-200 p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-slate-500 mb-1">
                                                    {{ $order->shop->warehouse_tag ?? 'NO TAG' }}
                                                </span>
                                                <h4 class="text-sm font-black text-slate-900">{{ $order->loadoutDisplayName() }}</h4>
                                                <p class="text-[10px] text-slate-400 font-medium">Order: <span class="font-mono">{{ $order->order_number }}</span></p>
                                            </div>
                                            <div class="text-right">
                                                <span class="inline-flex items-center gap-1 rounded-full bg-{{ $fulfillmentColor }}-50 border border-{{ $fulfillmentColor }}-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-{{ $fulfillmentColor }}-700">
                                                    {{ $fulfillmentIcon }} {{ $order->fulfillment_percentage }}% Stock Available
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-2 mb-6">
                                        @foreach($order->items as $item)
                                            @php
                                                $isLoaded = $item->sorting_status === 'loaded';
                                                $appQty = $item->approved_qty > 0 ? (float)$item->approved_qty : (float)$item->requested_qty;
                                                $lodQty = (float)$item->loaded_qty;
                                            @endphp
                                            <div class="rounded-xl border border-slate-200 bg-white p-3 flex items-center justify-between gap-3 shadow-xs">
                                                <div class="min-w-0">
                                                    <h5 class="truncate text-xs font-black text-slate-900">{{ $item->product->name }}</h5>
                                                    <p class="text-[10px] font-bold text-slate-500 mt-0.5">
                                                        Required: <span class="text-indigo-600 font-black">{{ number_format($appQty, 2) }}</span> {{ $item->unit }}
                                                        @if($isLoaded)
                                                            · Loaded: <span class="text-emerald-600 font-black">{{ number_format($lodQty, 2) }}</span> {{ $item->unit }}
                                                        @endif
                                                    </p>
                                                </div>
                                                <div class="shrink-0">
                                                    @if($isLoaded)
                                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-emerald-700">
                                                            Loaded ✓
                                                        </span>
                                                    @else
                                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-amber-700">
                                                            Pending
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- Deliver Button form --}}
                                    @if($order->delivery_status === 'pending_delivery' || $order->delivery_status === 'in_transit')
                                        @if($order->loaded_items_count === $order->total_items_count)
                                            <form action="{{ route('warehouse.receiver.loadout.order.dispatch', $order) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl py-3 text-xs font-black uppercase tracking-wider shadow-md transition-all active:scale-98 flex items-center justify-center gap-2 border-none cursor-pointer">
                                                    Move to Delivery
                                                </button>
                                            </form>
                                        @else
                                            <button type="button" onclick="confirmPartialDispatch({{ $order->id }}, '{{ $order->order_number }}')" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl py-3 text-xs font-black uppercase tracking-wider shadow-md transition-all active:scale-98 flex items-center justify-center gap-2 border-none cursor-pointer">
                                                Move to Delivery (Partial)
                                            </button>
                                            <form id="partial-dispatch-form-{{ $order->id }}" action="{{ route('warehouse.receiver.loadout.order.dispatch-partial', $order) }}" method="POST" class="hidden">
                                                @csrf
                                            </form>
                                        @endif
                                    @else
                                        <div class="text-center py-3 bg-slate-100 rounded-xl">
                                            <span class="inline-flex items-center rounded-full bg-indigo-50 border border-indigo-100 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-indigo-700">
                                                Status: Moved to Delivery
                                            </span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Clickable order card --}}
                                <div class="rounded-2xl border border-slate-200 bg-white p-4 flex flex-col gap-3 shadow-sm hover:border-indigo-200 transition-colors cursor-pointer"
                                     onclick="selectOrder({{ $order->id }})">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-slate-500 mb-1">
                                                {{ $order->shop->warehouse_tag ?? 'NO TAG' }}
                                            </span>
                                            <h4 class="truncate text-sm font-black text-slate-900">{{ $order->loadoutDisplayName() }}</h4>
                                            <p class="text-[10px] text-slate-400 font-medium">Order: <span class="font-mono">{{ $order->order_number }}</span></p>
                                        </div>
                                        <div class="text-right shrink-0 flex flex-col items-end gap-1.5">
                                            <span class="rounded-full bg-{{ $loadingColor }}-50 border border-{{ $loadingColor }}-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-{{ $loadingColor }}-700">
                                                {{ $order->loading_status }}
                                            </span>
                                            @if($order->delivery_status === 'in_transit')
                                                <span class="rounded-full bg-indigo-50 border border-indigo-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-indigo-700">
                                                    In Transit
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center justify-between pt-2.5 border-t border-dashed border-slate-100">
                                        <span class="text-[10px] font-bold text-slate-500">
                                            Items: {{ $order->loaded_items_count }} / {{ $order->total_items_count }} loaded
                                        </span>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-{{ $fulfillmentColor }}-50 border border-{{ $fulfillmentColor }}-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-{{ $fulfillmentColor }}-700">
                                            {{ $fulfillmentIcon }} {{ $order->fulfillment_percentage }}% Stock
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Sub-tab: Items --}}
                <div id="del-subtab-items" class="del-subtab-panel hidden space-y-3">
                    @if($shopOrders->isEmpty())
                        <div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                            <p class="text-xs text-slate-500">No shop orders for {{ $date }}.</p>
                        </div>
                    @else
                        @foreach($shopOrders as $order)
                            @php
                                $loadingColor = match($order->loading_status) {
                                    'Loaded' => 'emerald',
                                    'Partially Loaded' => 'amber',
                                    default => 'slate',
                                };
                            @endphp
                            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden mb-3">
                                <!-- Collapsible Header -->
                                <button type="button" 
                                        onclick="toggleShopCollapse({{ $order->id }})" 
                                        class="w-full flex items-center justify-between gap-3 bg-slate-50 px-4 py-3.5 text-left border-none cursor-pointer hover:bg-slate-100 transition-colors">
                                    <div class="min-w-0">
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-slate-500 mb-1">
                                            {{ $order->shop->warehouse_tag ?? 'NO TAG' }}
                                        </span>
                                        <h4 class="truncate text-sm font-black text-slate-900">{{ $order->loadoutDisplayName() }}</h4>
                                        <p class="text-[10px] text-slate-400 font-medium font-mono">Order: {{ $order->order_number }}</p>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <span class="rounded-full bg-{{ $loadingColor }}-50 border border-{{ $loadingColor }}-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-{{ $loadingColor }}-700">
                                            {{ $order->loading_status }} ({{ $order->loaded_items_count }} / {{ $order->total_items_count }})
                                        </span>
                                        <svg id="collapse-chevron-{{ $order->id }}" class="h-4 w-4 text-slate-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                </button>

                                <!-- Collapsible Content -->
                                <div id="collapse-content-{{ $order->id }}" class="hidden border-t border-slate-100 p-4 space-y-4">
                                    <!-- Table similar to loadout manifest -->
                                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                                        <table class="w-full min-w-[500px] border-collapse text-left text-[11px]">
                                            <thead>
                                                <tr class="border-b border-slate-200 bg-slate-50 text-[9px] font-black uppercase tracking-wider text-slate-400">
                                                    <th class="py-2.5 px-3">Product</th>
                                                    <th class="py-2.5 px-3 text-center">Grade</th>
                                                    <th class="py-2.5 px-3 text-right">Qty</th>
                                                    <th class="py-2.5 px-3 text-right">Discrepancy</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                                                @php $hasLoadedItems = false; @endphp
                                                @foreach($order->items as $item)
                                                    @if($item->sorting_status === 'loaded')
                                                        @php $hasLoadedItems = true; @endphp
                                                        <tr>
                                                            <td class="py-2.5 px-3 text-slate-900 font-black">{{ $item->product->name }}</td>
                                                            <td class="py-2.5 px-3 text-center">
                                                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 text-[9px] font-black text-slate-500">
                                                                    {{ $item->product_grade ?? 'A' }}
                                                                </span>
                                                            </td>
                                                            <td class="py-2.5 px-3 text-right text-slate-900 font-black">
                                                                {{ number_format((float)$item->loaded_qty, 2) }} <span class="text-[9px] text-slate-400 font-medium">{{ $item->unit }}</span>
                                                            </td>
                                                            <td class="py-2.5 px-3 text-right">
                                                                @if($item->loadout_discrepancy_type && $item->loadout_discrepancy_type !== 'none')
                                                                    <span class="text-rose-600 font-black">{{ $item->loadout_discrepancy_note }}</span>
                                                                @else
                                                                    <span class="text-slate-400 font-medium">-</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                                @if(!$hasLoadedItems)
                                                    <tr>
                                                        <td colspan="4" class="py-4 text-center text-slate-400 font-medium">No items have been loaded for this order yet.</td>
                                                    </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Action Button / Status -->
                                    <div class="flex justify-end pt-2">
                                        @if($order->delivery_status === 'ready_for_dispatch')
                                            <form action="{{ route('warehouse.receiver.loadout.order.ship', $order) }}" method="POST" class="warehouse-confirm-form w-full sm:w-auto"
                                                  data-confirm-title="Mark Out for Delivery"
                                                  data-confirm-message="Are you sure you want to mark this order as OUT FOR DELIVERY? It will become visible on the driver dashboard."
                                                  data-confirm-button="Mark Out">
                                                @csrf
                                                <button type="submit" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl px-5 py-2.5 text-xs font-black uppercase tracking-wider shadow-sm transition-all active:scale-98 border-none cursor-pointer">
                                                    Mark Out for Delivery
                                                </button>
                                            </form>
                                        @elseif($order->delivery_status === 'in_transit')
                                            <div class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 border border-indigo-100 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-indigo-700">
                                                <span class="h-1.5 w-1.5 rounded-full bg-indigo-600 animate-pulse"></span>
                                                Status: Out for Delivery (In Transit)
                                            </div>
                                        @elseif($order->delivery_status === 'delivered')
                                            <div class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-100 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-700">
                                                Status: Delivered
                                            </div>
                                        @else
                                            <div class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 border border-slate-200 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-500">
                                                Status: {{ ucfirst(str_replace('_', ' ', $order->delivery_status)) }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

        </div>

    </div>

    @push('scripts')
    <script>
        document.querySelectorAll('.warehouse-confirm-form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.appConfirmBypass === 'true') {
                    form.dataset.appConfirmBypass = 'false';
                    return;
                }

                event.preventDefault();

                window.showAppConfirm({
                    title: form.dataset.confirmTitle || 'Confirm action',
                    message: form.dataset.confirmMessage || 'Are you sure you want to continue?',
                    confirmLabel: form.dataset.confirmButton || 'Confirm',
                    cancelLabel: 'Cancel',
                    tone: 'danger',
                    onConfirm: () => {
                        form.dataset.appConfirmBypass = 'true';
                        HTMLFormElement.prototype.submit.call(form);
                    },
                });
            });
        });

        function switchTab(name) {
            document.querySelectorAll('.wr-tab').forEach(t => t.classList.add('hidden'));
            document.querySelectorAll('.wr-nav-btn').forEach(b => {
                b.classList.remove('active', 'bg-slate-950', 'text-white', 'shadow-sm', 'border-none');
                b.classList.add('bg-white', 'hover:bg-slate-50', 'text-slate-600', 'border', 'border-slate-200');
            });
            document.getElementById('tab-' + name)?.classList.remove('hidden');
            const activeBtn = document.getElementById('nav-' + name);
            if (activeBtn) {
                activeBtn.classList.add('active', 'bg-slate-950', 'text-white', 'shadow-sm');
                activeBtn.classList.remove('bg-white', 'hover:bg-slate-50', 'text-slate-600', 'border', 'border-slate-200');
            }

            // Update heading dynamically
            const headings = {
                'pending': 'Receive Checklist',
                'inventory': 'Inventory Status',
                'loadout': 'Loadout Checklist',
                'confirmed': 'Delivery Status'
            };
            const headingElement = document.getElementById('wr-page-heading');
            if (headingElement && headings[name]) {
                headingElement.textContent = headings[name];
            }
        }

        function switchInvSubTab(subName) {
            document.querySelectorAll('.inv-subtab-panel').forEach(p => p.classList.add('hidden'));
            document.querySelectorAll('[id^="subtab-"]').forEach(btn => {
                btn.classList.remove('bg-slate-950', 'text-white', 'shadow-sm');
                btn.classList.add('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200');
            });
            document.getElementById('inv-subtab-' + subName)?.classList.remove('hidden');
            const activeBtn = document.getElementById('subtab-' + subName);
            if (activeBtn) {
                activeBtn.classList.remove('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200');
                activeBtn.classList.add('bg-slate-950', 'text-white', 'shadow-sm');
            }
        }

        function switchDeliverySubTab(subName) {
            document.querySelectorAll('.del-subtab-panel').forEach(p => p.classList.add('hidden'));
            document.querySelectorAll('[id^="del-subtab-btn-"]').forEach(btn => {
                btn.classList.remove('bg-slate-950', 'text-white', 'shadow-sm');
                btn.classList.add('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200');
            });
            document.getElementById('del-subtab-' + subName)?.classList.remove('hidden');
            const activeBtn = document.getElementById('del-subtab-btn-' + subName);
            if (activeBtn) {
                activeBtn.classList.remove('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200');
                activeBtn.classList.add('bg-slate-950', 'text-white', 'shadow-sm');
            }
        }

        function toggleShopCollapse(orderId) {
            const content = document.getElementById('collapse-content-' + orderId);
            const chevron = document.getElementById('collapse-chevron-' + orderId);
            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                chevron.classList.add('rotate-180');
            } else {
                content.classList.add('hidden');
                chevron.classList.remove('rotate-180');
            }
        }

        function selectOrder(orderId) {
            const template = document.getElementById('order-items-template-' + orderId);
            const activeContainer = document.getElementById('selected-order-items-container');
            if (template && activeContainer) {
                activeContainer.innerHTML = template.innerHTML;
            }
            switchDeliverySubTab('items');
        }

        function confirmPartialDispatch(orderId, orderNum) {
            window.showAppConfirm({
                title: 'Partial Delivery Confirmation',
                message: 'Are you sure you want to dispatch Order ' + orderNum + ' as a partial delivery? All remaining items will be marked as not loaded.',
                confirmLabel: 'Yes, Dispatch Partial',
                cancelLabel: 'Cancel',
                tone: 'danger',
                onConfirm: () => {
                    const form = document.getElementById('partial-dispatch-form-' + orderId);
                    if (form) {
                        form.submit();
                    }
                }
            });
        }

        function populateInventoryCategories() {
            const categories = new Set();
            document.querySelectorAll('[data-category-display]').forEach(card => {
                const catName = card.getAttribute('data-category-display');
                if (catName) {
                    categories.add(catName);
                }
            });

            const filterSelect = document.getElementById('inventory-category-filter');
            if (filterSelect) {
                filterSelect.innerHTML = '<option value="">All Categories</option>';
                Array.from(categories).sort().forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat.toLowerCase();
                    option.textContent = cat;
                    filterSelect.appendChild(option);
                });
            }
        }

        function filterInventory() {
            const searchQuery = document.getElementById('inventory-search')?.value.toLowerCase().trim() || '';
            const selectedCategory = document.getElementById('inventory-category-filter')?.value || '';

            const selectors = ['.inv-inflow-row', '.inv-outflow-row', '.inv-stock-row'];
            selectors.forEach(selector => {
                document.querySelectorAll(selector).forEach(card => {
                    const name = card.getAttribute('data-product-name') || '';
                    const category = card.getAttribute('data-category') || '';
                    const matchesSearch = searchQuery === '' || name.includes(searchQuery);
                    const matchesCategory = selectedCategory === '' || category === selectedCategory;

                    if (matchesSearch && matchesCategory) {
                        card.classList.remove('hidden');
                    } else {
                        card.classList.add('hidden');
                    }
                });
            });
        }

        // Switch tab on load if present in query parameter
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab');
        if (activeTab && ['pending', 'inventory', 'loadout', 'confirmed'].includes(activeTab)) {
            switchTab(activeTab);
        }

        // Populate categories on load
        populateInventoryCategories();
    </script>
    @endpush
</x-layouts.app>
