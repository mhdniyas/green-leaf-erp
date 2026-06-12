<x-layouts.app title="Approved Requisitions Board">
    <div class="mx-auto px-4 py-8">
        @php
            $hasPendingApprovedUpdates = collect($shopUpdateMeta ?? [])->contains(fn (array $meta) => $meta['has_update_request']);
        @endphp
        <div class="mb-6">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-cyan-700">Purchasing</p>
            <h1 class="mt-1 text-3xl font-black tracking-tight text-slate-950">Approved Requisitions Board</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600">View, edit, and export finalized daily allocations across all shops.</p>
        </div>

        <div class="mb-8 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-end">
            <div class="flex flex-wrap items-center gap-4">
                <form action="{{ route('requisitions.approved_board') }}" method="GET" class="flex items-center gap-3 bg-white px-4 py-2 border border-slate-200 rounded-xl shadow-sm">
                    <label for="date-select" class="text-xs font-bold text-slate-400 uppercase tracking-wider">Delivery Date:</label>
                    <input type="date" id="date-select" name="date" value="{{ $date }}" onchange="this.form.submit()" class="text-xs font-bold text-slate-700 border-0 focus:outline-none focus:ring-0 p-0 cursor-pointer">
                </form>

                {{-- CSV Export --}}
                <button type="button" onclick="exportApprovedBoardCsv()" class="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-4 py-3 rounded-xl transition-all border border-slate-200 shadow-sm cursor-pointer">
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Export CSV
                </button>

                {{-- PDF Export --}}
                <button type="button" onclick="exportApprovedBoardPdf()" class="inline-flex items-center gap-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold px-4 py-3 rounded-xl transition-all border border-emerald-100 shadow-sm cursor-pointer">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.844l-.24.03a.75.75 0 11-.24-1.48l.24-.03a.75.75 0 11.24 1.48zM15 10.5a.75.75 0 01.75-.75h.008a.75.75 0 01.75.75v.008a.75.75 0 01-.75.75h-.008a.75.75 0 01-.75-.75V10.5zm-6-1.5a.75.75 0 01.75-.75h.008a.75.75 0 01.75.75v.008a.75.75 0 01-.75.75h-.008a.75.75 0 01-.75-.75V9zm6 3a.75.75 0 01.75-.75h.008a.75.75 0 01.75.75v.008a.75.75 0 01-.75.75h-.008a.75.75 0 01-.75-.75v-.008zm-6 3a.75.75 0 01.75-.75h.008a.75.75 0 01.75.75v.008a.75.75 0 01-.75.75h-.008a.75.75 0 01-.75-.75v-.008z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582" /></svg>
                    Print PDF
                </button>

                @if($approvedBoardSynced && ! $hasPendingApprovedUpdates)
                    <a href="{{ route('purchasing.orders.index') }}" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-5 py-3 rounded-xl transition-all shadow-md hover:shadow-lg">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272" /></svg>
                        Continue in Purchase Orders
                    </a>
                @elseif($approvedBoardSynced && $hasPendingApprovedUpdates)
                    <button type="submit" form="board-form" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-5 py-3 rounded-xl transition-all cursor-pointer focus:outline-none shadow-md hover:shadow-lg border-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        Apply Pending Updates
                    </button>
                @else
                    <button type="submit" form="board-form" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-5 py-3 rounded-xl transition-all cursor-pointer focus:outline-none shadow-md hover:shadow-lg border-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        Save & Release Allocations
                    </button>
                @endif
            </div>
        </div>

        {{-- Alerts --}}
        @if(session('success'))
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold px-4 py-3.5 rounded-2xl flex items-center gap-2.5">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 text-xs font-semibold px-4 py-3.5 rounded-2xl flex items-center gap-2.5">
                <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                {{ session('error') }}
            </div>
        @endif

        @if($approvedBoardSynced && ! $hasPendingApprovedUpdates)
            <div class="mb-6 bg-blue-50 border border-blue-200 text-blue-900 text-xs font-semibold px-5 py-4 rounded-2xl flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    <div>
                        <p class="font-black uppercase tracking-wider">Purchase Orders generated</p>
                        <p class="mt-1 text-blue-700">This approved board is locked to prevent duplicate PO generation. Continue buying and price updates from the linked purchase orders.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($existingPos as $po)
                        <a href="{{ route('purchasing.orders.show', $po) }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-[11px] font-black text-blue-700 border border-blue-200 hover:bg-blue-100 transition-colors">
                            {{ $po->po_number }}
                        </a>
                    @endforeach
                </div>
            </div>
        @elseif($approvedBoardSynced && $hasPendingApprovedUpdates)
            <div class="mb-6 rounded-2xl border border-indigo-200 bg-indigo-50 px-5 py-4 text-indigo-900">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-indigo-700">Purchase Orders Generated, Revisions Pending</p>
                <p class="mt-1 text-sm font-semibold">Purchase orders already exist for this date, but approved-order updates are still allowed because goods receipt has not started on the linked PO lines. Review the highlighted changes and apply them from this board.</p>
            </div>
        @endif

        @if($approvedBoardSynced && count($approvedProductIds ?? []) > $poBackedApprovedProductCount)
            <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-950">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-amber-700">PO Selection Mismatch</p>
                <p class="mt-1 text-sm font-semibold">{{ $poBackedApprovedProductCount }} of {{ count($approvedProductIds ?? []) }} approved items are currently linked to generated purchase orders for this date. Unchecked rows below were approved on the board but not included in the generated PO set.</p>
            </div>
        @endif

        @php
            $shopsWithUpdates = collect($shopUpdateMeta ?? [])->filter(fn (array $meta) => $meta['has_update_request']);
        @endphp

        @if($shopsWithUpdates->isNotEmpty())
            <div class="mb-6 rounded-3xl border border-indigo-200 bg-indigo-50 px-5 py-4 text-indigo-900 shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-700">Updated Shop Requests Pending</p>
                        <p class="mt-1 text-sm font-semibold">Approved allocations changed after shop-owner updates. Click a highlighted shop column to review the note and adjust approvals before generating purchase orders.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($shops as $shop)
                            @php
                                $shopMeta = $shopUpdateMeta[$shop->id] ?? null;
                            @endphp
                            @if($shopMeta && $shopMeta['has_update_request'])
                                <button type="button" onclick="focusShopColumn({{ $shop->id }})" class="rounded-full border border-indigo-200 bg-white px-3 py-1.5 text-[11px] font-black text-indigo-700 transition hover:bg-indigo-100">
                                    {{ $shop->name }} · Update #{{ $shopMeta['revision_no'] ?? 2 }} · {{ $shopMeta['changed_items_count'] }} changes
                                </button>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div id="shop-update-panel" class="mb-6 hidden rounded-3xl border border-cyan-200 bg-cyan-50 px-5 py-4 text-cyan-950 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-cyan-700">Selected Shop Update</p>
            <h3 id="shop-update-title" class="mt-1 text-base font-black"></h3>
            <p id="shop-update-meta" class="mt-1 text-sm font-semibold text-cyan-800"></p>
            <p id="shop-update-reason" class="mt-2 rounded-2xl bg-white/80 px-4 py-3 text-sm text-slate-700"></p>
        </div>

        <!-- Supplier Warning Modal Removed -->

        {{-- Filters & Search --}}
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-4 mb-6 flex flex-col md:flex-row items-center gap-4">
            <div class="relative flex-1 w-full">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
                <input type="text" id="board-search" oninput="filterBoardRows()" placeholder="Fuzzy search products by name or SKU..." class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2.5 focus:bg-white focus:outline-none focus:border-slate-300 transition-all">
            </div>
            


            <div class="flex items-center gap-3 self-stretch md:self-auto shrink-0 bg-white px-3 py-1.5 border border-slate-200 rounded-xl shadow-sm">
                <label for="filter-produce" class="text-xs font-bold text-slate-400 uppercase tracking-wider select-none">Produce:</label>
                <select id="filter-produce" onchange="filterBoardRows()" class="text-xs font-bold text-slate-700 border-0 focus:outline-none focus:ring-0 p-0 cursor-pointer">
                    <option value="all">All</option>
                    <option value="veg">Veg</option>
                    <option value="fruit">Fruit</option>
                </select>
            </div>
            <!-- Supplier bulk selection removed -->

            <div class="flex items-center gap-4 self-stretch md:self-auto shrink-0 pl-2">
                <label class="flex items-center gap-2 text-xs font-bold text-slate-600 cursor-pointer select-none">
                    <input type="checkbox" id="filter-has-orders" checked onchange="filterBoardRows()" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer">
                    Show only items with orders
                </label>
            </div>

            <div class="flex gap-2 self-stretch md:self-auto shrink-0">
                <button type="button" onclick="clearSearch()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-all border-0 cursor-pointer">
                    Clear Filter
                </button>
            </div>
        </div>

        {{-- Form Wrap --}}
        <form id="board-form" method="POST" action="{{ route('requisitions.approved_board.save') }}">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">
            <fieldset @disabled($approvedBoardSynced && ! $hasPendingApprovedUpdates) class="{{ $approvedBoardSynced && ! $hasPendingApprovedUpdates ? 'opacity-75' : '' }}">
            
            {{-- Approved Requisitions Allocations Section --}}
            <div class="bg-white border border-slate-200 rounded-3xl shadow-sm overflow-hidden mb-8" id="allocations-card">
                <div class="px-6 py-4 bg-slate-50/50 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3h.75v3m3 0v-3h.75v3" /></svg>
                        Approved Requisitions Allocations
                    </h3>
                    <span class="text-[9px] font-black text-slate-500 bg-slate-200 px-2 py-0.5 rounded-full" id="allocations-count">0 items</span>
                </div>
                <div class="overflow-x-auto max-w-full">
                    <table class="w-full text-left border-collapse" id="matrix-table-allocations">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-4 px-4 text-center sticky left-0 bg-slate-50 z-20 w-[50px]">SL NO</th>
                                <th class="py-4 px-2 text-center sticky left-[50px] bg-slate-50 z-20 w-[50px] border-r border-slate-200">
                                    <input type="checkbox" id="select-all-allocations" checked onclick="toggleSelectAll(this.checked)" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer">
                                </th>
                                <th class="py-4 px-4 sticky left-[100px] bg-slate-50 z-20 min-w-[180px] border-r border-slate-200">Item</th>


                                @foreach($shops as $shop)
                                    @php
                                        $shopMeta = $shopUpdateMeta[$shop->id] ?? null;
                                        $shopPoMeta = $shopPoStatusMeta[$shop->id] ?? ['status' => 'none', 'label' => 'No PO', 'po_count' => 0];
                                    @endphp
                                    <th @class([
                                        'py-4 px-3 text-center min-w-[90px] border-r font-extrabold uppercase tracking-widest text-[9px] transition-colors',
                                        'border-slate-100 text-slate-700 hover:bg-slate-100/50' => ! ($shopMeta['has_update_request'] ?? false),
                                        'border-indigo-200 bg-indigo-50 text-indigo-900 hover:bg-indigo-100/80' => $shopMeta['has_update_request'] ?? false,
                                    ])>
                                        <button type="button" class="mx-auto flex flex-col items-center gap-1" onclick="focusShopColumn({{ $shop->id }})">
                                            <span>{{ str_replace([' HYPERMARKET', ' SUPERMARKET', ' STORE', ' SHOP'], '', strtoupper($shop->name)) }}</span>
                                            @if($shopMeta && $shopMeta['has_update_request'])
                                                <span class="rounded-full bg-indigo-600 px-2 py-0.5 text-[8px] font-black text-white">Update #{{ $shopMeta['revision_no'] ?? 2 }}</span>
                                            @endif
                                            <span class="rounded-full px-2 py-0.5 text-[8px] font-black {{ $shopPoMeta['status'] === 'grn_locked' ? 'bg-rose-100 text-rose-700' : ($shopPoMeta['status'] === 'created' ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-600') }}">{{ $shopPoMeta['label'] }}</span>
                                        </button>
                                    </th>
                                @endforeach
                                <th class="py-4 px-4 text-right min-w-[100px] bg-slate-50 font-black text-slate-900 tracking-wider">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700" id="tbody-allocations">
                            @php $slNo = 1; @endphp
                            @foreach($products as $product)
                                @php
                                    $rowTotal = 0;
                                    foreach ($shops as $shop) {
                                        $qtyData = $matrix[$product->id][$shop->id] ?? null;
                                        if ($qtyData) {
                                            $rowTotal += $qtyData['display_qty'] ?? ($qtyData['approved_qty'] !== null ? $qtyData['approved_qty'] : $qtyData['requested_qty']);
                                        }
                                    }
                                    $selectedSupplierId = $productSupplierMap[$product->id] ?? null;
                                @endphp
                                <tr class="hover:bg-slate-50/10 transition-colors product-row" 
                                    data-product-id="{{ $product->id }}"
                                    data-sku="{{ strtolower($product->sku) }}" 
                                    data-name="{{ strtolower($product->name) }}"
                                    data-category="{{ strtolower($product->category ? $product->category->name : '') }}"
                                    data-row-total="{{ $rowTotal }}"
                                    data-fulfillment-type="{{ $productFulfillmentTypes[$product->id] ?? 'warehouse' }}">
                                    
                                    {{-- SL NO --}}
                                    <td class="py-3 px-4 text-center font-bold text-slate-400 sticky left-0 bg-white z-10 row-sl-no">
                                        {{ $slNo++ }}
                                    </td>
                                    
                                    {{-- Checkbox --}}
                                    <td class="py-3 px-2 text-center sticky left-[50px] bg-white z-10 border-r border-slate-200">
                                        <input type="checkbox" name="selected_products[]" value="{{ $product->id }}" @checked(! $approvedBoardSynced || in_array($product->id, $poBackedProductIds ?? [], true)) class="product-select-checkbox rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer">
                                    </td>
                                    
                                    {{-- Product Name & SKU --}}
                                    <td class="py-3 px-4 sticky left-[100px] bg-white z-10 border-r border-slate-200 font-bold text-slate-900">
                                        <div class="truncate max-w-[180px]">{{ $product->name }}</div>
                                        <span class="block text-[9px] text-slate-400 font-bold tracking-wider mt-0.5">{{ $product->sku }}</span>
                                    </td>


                                    
                                    {{-- Shops Inputs --}}
                                    @foreach($shops as $shop)
                                        @php
                                            $qtyData = $matrix[$product->id][$shop->id] ?? null;
                                            $val = null;
                                            $isAdjusted = false;
                                            if ($qtyData) {
                                                $val = $qtyData['display_qty'] ?? ($qtyData['approved_qty'] ?? $qtyData['requested_qty']);
                                                $comparisonBaseline = $qtyData['previous_approved_qty'] ?? $qtyData['approved_qty'] ?? $qtyData['requested_qty'];
                                                $isAdjusted = abs((float) $val - (float) $comparisonBaseline) > 0.0001;
                                            }
                                        @endphp
                                        <td @class([
                                            'py-2 px-2 text-center border-r transition-colors',
                                            'border-slate-100 hover:bg-slate-50/5 hover:z-20' => ! ($shopUpdateMeta[$shop->id]['has_update_request'] ?? false) && ! ($qtyData['needs_attention'] ?? false),
                                            'border-indigo-100 bg-indigo-50/60 hover:bg-indigo-100/80 hover:z-20' => ($shopUpdateMeta[$shop->id]['has_update_request'] ?? false) || ($qtyData['needs_attention'] ?? false),
                                        ]) data-shop-column-cell="{{ $shop->id }}">
                                            <div class="relative inline-flex items-center">
                                                <input type="number" 
                                                       step="0.01" 
                                                       min="0" 
                                                       name="quantities[{{ $product->id }}][{{ $shop->id }}]" 
                                                       value="{{ $val > 0 ? $val : '' }}"
                                                       data-shop-id="{{ $shop->id }}"
                                                       data-product-id="{{ $product->id }}"
                                                       oninput="onCellChange(this)"
                                                       @class([
                                                           'w-16 rounded-lg border text-center py-1 text-xs font-black transition-all focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500',
                                                           'border-slate-200 text-slate-800 focus:bg-white bg-slate-50/30' => !$isAdjusted,
                                                           'border-amber-300 text-amber-900 bg-amber-50/50 focus:bg-white' => $isAdjusted,
                                                           'ring-1 ring-indigo-300 border-indigo-300 bg-indigo-50 text-indigo-900' => $qtyData['needs_attention'] ?? false,
                                                       ])
                                                       placeholder="-">
                                            </div>
                                        </td>
                                    @endforeach

                                    {{-- Row Total --}}
                                    <td class="py-3 px-4 text-right font-black text-slate-900 bg-slate-50/20 text-xs" id="row-total-{{ $product->id }}">
                                        {{ $rowTotal > 0 ? number_format($rowTotal, 2) : '0.00' }} <span class="text-[10px] text-slate-400 font-semibold">{{ $product->unit }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-100 border-t border-slate-200 text-xs font-bold text-slate-800 uppercase">
                                <td class="py-4 px-4 text-center sticky left-0 bg-slate-100 z-20" colspan="4">
                                    Total (kg)
                                </td>
                                @foreach($shops as $shop)
                                    <td class="py-4 px-3 text-center border-r border-slate-200/50 font-black text-slate-900 text-xs" id="col-total-{{ $shop->id }}">
                                        0.00
                                    </td>
                                @endforeach
                                <td class="py-4 px-4 text-right font-black text-slate-950 text-sm bg-slate-100/80" id="grand-total">
                                    0.00
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Floating controls --}}
            <div class="flex items-center justify-end gap-3 pb-8">
                <a href="{{ route('dashboard') }}" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-all border-0 cursor-pointer">
                    Cancel & Return
                </a>
                @if($approvedBoardSynced && ! $hasPendingApprovedUpdates)
                    @foreach($existingPos as $po)
                        <a href="{{ route('purchasing.orders.show', $po) }}" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-5 py-3 rounded-xl transition-all shadow-md hover:shadow-lg">
                            {{ $po->po_number }}
                        </a>
                    @endforeach
                @elseif($approvedBoardSynced && $hasPendingApprovedUpdates)
                    <button type="submit" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-5 py-3 rounded-xl transition-all cursor-pointer focus:outline-none shadow-md hover:shadow-lg border-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        Apply Pending Updates
                    </button>
                @else
                    <button type="submit" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-5 py-3 rounded-xl transition-all cursor-pointer focus:outline-none shadow-md hover:shadow-lg border-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        Save & Release Allocations
                    </button>
                @endif
            </div>
            </fieldset>
        </form>

    @push('scripts')
    <script>
        // Smooth scroll to section
        function scrollToSection(id, toBottom = false) {
            const el = document.getElementById(id);
            if (el) {
                if (toBottom) {
                    window.scrollTo({
                        top: el.offsetTop + el.offsetHeight - window.innerHeight + 150,
                        behavior: 'smooth'
                    });
                } else {
                    const yOffset = -70; // Header bar height offset
                    const y = el.getBoundingClientRect().top + window.pageYOffset + yOffset;
                    window.scrollTo({ top: y, behavior: 'smooth' });
                }
            }
        }

        // Dynamically calculate column totals and grand total on load
        document.addEventListener('DOMContentLoaded', () => {
            calculateColumnTotals();
            filterBoardRows();
        });

        // Filter product rows by search query, order presence, and produce type
        function filterBoardRows() {
            const query = document.getElementById('board-search').value.toLowerCase().trim();
            const hideZero = document.getElementById('filter-has-orders').checked;
            const produceFilter = document.getElementById('filter-produce').value;
            const rows = document.querySelectorAll('.product-row');
            
            rows.forEach(row => {
                const name = row.getAttribute('data-name');
                const sku = row.getAttribute('data-sku');
                const category = row.getAttribute('data-category');
                const rowTotal = parseFloat(row.getAttribute('data-row-total')) || 0;

                const matchesQuery = !query || name.includes(query) || sku.includes(query) || category.includes(query);
                const matchesZeroFilter = !hideZero || rowTotal > 0;
                const matchesProduce = produceFilter === 'all'
                    || (produceFilter === 'veg' && ['supply', 'veg', 'hal', 'leaf', 'english', 'kolkata', 'banana', 'onion', 'c'].includes(category))
                    || (produceFilter === 'fruit' && ['frut', 'fruit'].includes(category));

                if (matchesQuery && matchesZeroFilter && matchesProduce) {
                    row.classList.remove('hidden');
                } else {
                    row.classList.add('hidden');
                }
            });

            // Update row SL numbers and counts
            updateSerialNumbersAndCounts();
        }

        function updateSerialNumbersAndCounts() {
            let visibleCount = 0;
            document.querySelectorAll('#tbody-allocations tr.product-row').forEach((row) => {
                if (! row.classList.contains('hidden')) {
                    row.querySelector('.row-sl-no').textContent = ++visibleCount;
                }
            });
            document.getElementById('allocations-count').textContent = `${visibleCount} items`;
        }

        // Clear filter
        function clearSearch() {
            document.getElementById('board-search').value = '';
            document.getElementById('filter-has-orders').checked = true;
            document.getElementById('filter-produce').value = 'all';
            document.getElementById('bulk-supplier').value = '';
            filterBoardRows();
        }

        function buildApprovedBoardExportUrl(baseUrl, type = 'both') {
            const params = new URLSearchParams();
            params.set('date', @json($date));
            params.set('search', document.getElementById('board-search').value.trim());
            params.set('produce', document.getElementById('filter-produce').value);
            params.set('has_orders', document.getElementById('filter-has-orders').checked ? '1' : '0');
            params.set('type', type);

            document.querySelectorAll('.product-row').forEach((row) => {
                if (!row.classList.contains('hidden')) {
                    params.append('ordered_product_ids[]', row.getAttribute('data-product-id'));
                }
            });

            return `${baseUrl}?${params.toString()}`;
        }

        function exportApprovedBoardCsv() {
            window.location.href = buildApprovedBoardExportUrl(@json(route('requisitions.approved_board.export.csv')));
        }

        function exportApprovedBoardPdf() {
            window.open(buildApprovedBoardExportUrl(@json(route('requisitions.approved_board.export.pdf')), 'both'), '_blank', 'noopener');
        }



        // Action when a cell value changes
        function onCellChange(input) {
            const productId = input.getAttribute('data-product-id');
            const row = input.closest('tr');
            
            // Highlight adjustment
            if (input.value > 0) {
                input.className = "w-16 rounded-lg border text-center py-1 text-xs font-black transition-all focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 border-amber-300 text-amber-900 bg-amber-50/50 focus:bg-white";
            } else {
                input.className = "w-16 rounded-lg border text-center py-1 text-xs font-black transition-all focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 border-slate-200 text-slate-800 focus:bg-white bg-slate-50/30";
            }

            // Recalculate row total
            let rowTotal = 0;
            row.querySelectorAll('input[type="number"]').forEach(inp => {
                const val = parseFloat(inp.value) || 0;
                rowTotal += val;
            });

            // Update row total attribute
            row.setAttribute('data-row-total', rowTotal);

            // Update row total cell
            const totalCell = document.getElementById(`row-total-${productId}`);
            if (totalCell) {
                const unit = totalCell.querySelector('span')?.outerHTML || '';
                totalCell.innerHTML = `${rowTotal.toFixed(2)} ${unit}`;
            }

            // Recalculate column totals and grand total
            calculateColumnTotals();

            // Re-apply filters
            filterBoardRows();
        }

        // Recalculate all column totals and grand total
        function calculateColumnTotals() {
            const shops = @json($shops);
            
            let grandTotal = 0;
            shops.forEach(shop => {
                let shopTotal = 0;
                document.querySelectorAll('#tbody-allocations input[data-shop-id="' + shop.id + '"]').forEach(inp => {
                    const val = parseFloat(inp.value) || 0;
                    shopTotal += val;
                });
                
                const colCell = document.getElementById(`col-total-${shop.id}`);
                if (colCell) {
                    colCell.textContent = shopTotal.toFixed(2);
                }
                grandTotal += shopTotal;
            });
            
            const grandTotalCell = document.getElementById('grand-total');
            if (grandTotalCell) {
                grandTotalCell.textContent = grandTotal.toFixed(2);
            }
        }

        // Toggle all checkboxes in tbody
        function toggleSelectAll(checked) {
            const checkboxes = document.querySelectorAll('#tbody-allocations .product-select-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = checked;
            });
        }

        function focusShopColumn(shopId) {
            document.querySelectorAll('[data-shop-column-cell]').forEach((cell) => {
                cell.classList.remove('ring-2', 'ring-cyan-400', 'ring-inset');
            });

            document.querySelectorAll(`[data-shop-column-cell="${shopId}"]`).forEach((cell) => {
                cell.classList.add('ring-2', 'ring-cyan-400', 'ring-inset');
            });

            const panel = document.getElementById('shop-update-panel');
            const shopTitle = document.getElementById('shop-update-title');
            const shopMeta = document.getElementById('shop-update-meta');
            const shopReason = document.getElementById('shop-update-reason');
            const updates = @json($shopUpdateMeta);
            const shops = @json($shops->map(fn ($shop) => ['id' => $shop->id, 'name' => $shop->name])->values());
            const selectedShop = shops.find((shop) => shop.id === shopId);
            const selectedUpdate = updates[shopId];

            if (panel && shopTitle && shopMeta && shopReason && selectedShop && selectedUpdate && selectedUpdate.has_update_request) {
                panel.classList.remove('hidden');
                shopTitle.textContent = selectedShop.name;
                shopMeta.textContent = `Update #${selectedUpdate.revision_no} • ${selectedUpdate.changed_items_count} item updates requested • ${selectedUpdate.order_number}`;
                shopReason.textContent = selectedUpdate.update_reason || 'Shop owner requested an update.';
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            const firstInput = document.querySelector(`input[data-shop-id="${shopId}"]`);
            if (firstInput) {
                firstInput.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
            }
        }
    </script>
    @endpush
</x-layouts.app>
