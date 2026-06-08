<x-layouts.app title="Consolidated Requisitions Board">
    <div class="mx-auto px-4 py-8">
        <div class="mb-6">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-cyan-700">Purchasing</p>
            <h1 class="mt-1 text-3xl font-black tracking-tight text-slate-950">Consolidated Requisitions Board</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600">Review, adjust, and bulk approve requisitions across all shops.</p>
        </div>

        <div class="mb-8 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-end">
            <div class="flex flex-wrap items-center gap-4">
                <form action="{{ route('requisitions.board') }}" method="GET" class="flex items-center gap-3 bg-white px-4 py-2 border border-slate-200 rounded-xl shadow-sm">
                    <label for="date-select" class="text-xs font-bold text-slate-400 uppercase tracking-wider">Delivery Date:</label>
                    <input type="date" id="date-select" name="date" value="{{ $date }}" onchange="this.form.submit()" class="text-xs font-bold text-slate-700 border-0 focus:outline-none focus:ring-0 p-0 cursor-pointer">
                </form>

                <button type="button" onclick="exportBoardCsv()" class="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-4 py-3 rounded-xl transition-all border border-slate-200 shadow-sm cursor-pointer">
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Export CSV
                </button>

                <button type="button" onclick="exportBoardPdf()" class="inline-flex items-center gap-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold px-4 py-3 rounded-xl transition-all border border-emerald-100 shadow-sm cursor-pointer">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-8.25A3.375 3.375 0 004.5 11.625v2.625m15 0v3.375A2.625 2.625 0 0116.875 20.25H7.125A2.625 2.625 0 014.5 17.625V14.25m15 0h-15M15 6V3.75A1.125 1.125 0 0013.875 2.625h-3.75A1.125 1.125 0 009 3.75V6" /></svg>
                    Print PDF
                </button>

                @if($boardFullyApproved)
                    <button type="submit" form="board-form" class="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-4 py-3 rounded-xl transition-all cursor-pointer border border-slate-200 shadow-sm">
                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" /></svg>
                        Save Board Changes
                    </button>
                    <a href="{{ route('requisitions.approved_board', ['date' => $date]) }}" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-5 py-3 rounded-xl transition-all shadow-md hover:shadow-lg">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        Continue to Approved Board
                    </a>
                @else
                    <button type="submit" form="board-form" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-5 py-3 rounded-xl transition-all cursor-pointer focus:outline-none shadow-md hover:shadow-lg border-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                        Save & Approve All
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

        @php
            $shopsWithUpdates = collect($shopUpdateMeta ?? [])->filter(fn (array $meta) => $meta['has_update_request']);
        @endphp

        @if($shopsWithUpdates->isNotEmpty())
            <div class="mb-6 rounded-3xl border border-indigo-200 bg-indigo-50 px-5 py-4 text-indigo-900 shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-indigo-700">Shop Owner Updates Waiting</p>
                        <p class="mt-1 text-sm font-semibold">One or more shops changed their request after cutoff. Click the highlighted shop column to review the note and update quantities before moving to the approved board.</p>
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

        {{-- Filters & Search --}}
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-4 mb-6 flex flex-col md:flex-row items-center gap-4">
            <div class="relative flex-1 w-full">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
                <input type="text" id="board-search" oninput="filterBoardRows()" placeholder="Fuzzy search products by name or SKU..." class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2.5 focus:bg-white focus:outline-none focus:border-slate-300 transition-all">
            </div>
            
            <div class="flex items-center gap-3 self-stretch md:self-auto shrink-0 bg-white px-3 py-1.5 border border-slate-200 rounded-xl shadow-sm">
                <label for="filter-fulfillment" class="text-xs font-bold text-slate-400 uppercase tracking-wider select-none">Fulfillment:</label>
                <select id="filter-fulfillment" onchange="filterBoardRows()" class="text-xs font-bold text-slate-700 border-0 focus:outline-none focus:ring-0 p-0 cursor-pointer">
                    <option value="all">All Items</option>
                    <option value="warehouse">Warehouse (Bulk)</option>
                    <option value="selection">Selection (Packet)</option>
                </select>
            </div>

            <div class="flex items-center gap-3 self-stretch md:self-auto shrink-0 bg-white px-3 py-1.5 border border-slate-200 rounded-xl shadow-sm">
                <label for="filter-produce" class="text-xs font-bold text-slate-400 uppercase tracking-wider select-none">Produce:</label>
                <select id="filter-produce" onchange="filterBoardRows()" class="text-xs font-bold text-slate-700 border-0 focus:outline-none focus:ring-0 p-0 cursor-pointer">
                    <option value="all">All</option>
                    <option value="veg">Veg</option>
                    <option value="fruit">Fruit</option>
                </select>
            </div>

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
        <form id="board-form" method="POST" action="{{ route('requisitions.board.save') }}">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">

            {{-- Table Wrapper --}}
            <div class="bg-white border border-slate-200 rounded-3xl shadow-sm overflow-hidden mb-6">
                <div class="overflow-x-auto max-w-full">
                    <table class="w-full text-left border-collapse" id="matrix-table">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-4 px-4 text-center sticky left-0 bg-slate-50 z-20 w-[60px]">SL NO</th>
                                <th class="py-4 px-4 sticky left-[60px] bg-slate-50 z-20 min-w-[200px] border-r border-slate-200">Item</th>
                                <th class="py-4 px-3 text-center min-w-[140px] border-r border-slate-200 text-slate-700 uppercase font-black text-[9px] tracking-wider bg-slate-50 z-20">Fulfillment</th>
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
                                        <button
                                            type="button"
                                            class="mx-auto flex flex-col items-center gap-1"
                                            onclick="focusShopColumn({{ $shop->id }})"
                                        >
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
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @foreach($products as $index => $product)
                                @php
                                    $rowTotal = 0;
                                    foreach ($shops as $shop) {
                                        $qtyData = $matrix[$product->id][$shop->id] ?? null;
                                        if ($qtyData) {
                                            $rowTotal += $qtyData['display_qty'] ?? ($qtyData['approved_qty'] !== null ? $qtyData['approved_qty'] : $qtyData['requested_qty']);
                                        }
                                    }
                                @endphp
                                <tr class="hover:bg-slate-50/10 transition-colors product-row" 
                                    data-product-id="{{ $product->id }}"
                                    data-sku="{{ strtolower($product->sku) }}" 
                                    data-name="{{ strtolower($product->name) }}"
                                    data-category="{{ strtolower($product->category ? $product->category->name : '') }}"
                                    data-row-total="{{ $rowTotal }}"
                                    data-fulfillment-type="{{ $productFulfillmentTypes[$product->id] ?? 'warehouse' }}">
                                    
                                    {{-- SL NO --}}
                                    <td class="py-3 px-4 text-center font-bold text-slate-400 sticky left-0 bg-white z-10">
                                        {{ $index + 1 }}
                                    </td>
                                    
                                    {{-- Product Name & SKU --}}
                                    <td class="py-3 px-4 sticky left-[60px] bg-white z-10 border-r border-slate-200 font-bold text-slate-900">
                                        <div class="truncate max-w-[200px]">{{ $product->name }}</div>
                                        <span class="block text-[9px] text-slate-400 font-bold tracking-wider mt-0.5">{{ $product->sku }}</span>
                                    </td>

                                    {{-- Fulfillment Toggle --}}
                                    <td class="py-3 px-3 border-r border-slate-200 text-center">
                                        <div class="inline-flex rounded-lg p-0.5 bg-slate-100 border border-slate-200">
                                            <label class="cursor-pointer">
                                                <input type="radio" name="fulfillment_types[{{ $product->id }}]" value="warehouse" @checked(($productFulfillmentTypes[$product->id] ?? 'warehouse') === 'warehouse') onchange="onFulfillmentRadioChange(this)" class="sr-only peer">
                                                <span class="inline-block px-2.5 py-1 rounded-md text-[10px] font-bold text-slate-500 peer-checked:bg-white peer-checked:text-slate-800 peer-checked:shadow-sm transition-all select-none">
                                                    Warehouse
                                                </span>
                                            </label>
                                            <label class="cursor-pointer">
                                                <input type="radio" name="fulfillment_types[{{ $product->id }}]" value="selection" @checked(($productFulfillmentTypes[$product->id] ?? 'warehouse') === 'selection') onchange="onFulfillmentRadioChange(this)" class="sr-only peer">
                                                <span class="inline-block px-2.5 py-1 rounded-md text-[10px] font-bold text-slate-500 peer-checked:bg-white peer-checked:text-slate-800 peer-checked:shadow-sm transition-all select-none">
                                                    Selection
                                                </span>
                                            </label>
                                        </div>
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
                        
                        {{-- Bottom Total Row --}}
                        <tfoot>
                            <tr class="bg-slate-100 border-t border-slate-200 text-xs font-bold text-slate-800 uppercase">
                                <td class="py-4 px-4 text-center sticky left-0 bg-slate-100 z-20" colspan="3">
                                    Grand Total (kg)
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
            @if($boardFullyApproved)
                <button type="submit" class="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-4 py-3 rounded-xl transition-all cursor-pointer border border-slate-200 shadow-sm">
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" /></svg>
                    Save Board Changes
                </button>
                <a href="{{ route('requisitions.approved_board', ['date' => $date]) }}" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-5 py-3 rounded-xl transition-all shadow-md hover:shadow-lg">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                    Continue to Approved Board
                </a>
            @else
                <button type="submit" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-5 py-3 rounded-xl transition-all cursor-pointer focus:outline-none shadow-md hover:shadow-lg border-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    Save Requisitions & Approve All
                </button>
            @endif
        </div>
        </form>
    </div>

    @push('scripts')
    <script>
        // Dynamically calculate column totals and grand total on load
        document.addEventListener('DOMContentLoaded', () => {
            calculateColumnTotals();
            filterBoardRows();
        });

        // Filter product rows by search query, order presence, and fulfillment type
        function filterBoardRows() {
            const query = document.getElementById('board-search').value.toLowerCase().trim();
            const hideZero = document.getElementById('filter-has-orders').checked;
            const fulfillmentFilter = document.getElementById('filter-fulfillment').value;
            const produceFilter = document.getElementById('filter-produce').value;
            const rows = document.querySelectorAll('.product-row');
            
            rows.forEach(row => {
                const name = row.getAttribute('data-name');
                const sku = row.getAttribute('data-sku');
                const category = row.getAttribute('data-category');
                const rowTotal = parseFloat(row.getAttribute('data-row-total')) || 0;
                const type = row.getAttribute('data-fulfillment-type') || 'warehouse';

                const matchesQuery = !query || name.includes(query) || sku.includes(query) || category.includes(query);
                const matchesZeroFilter = !hideZero || rowTotal > 0;
                const matchesFulfillment = fulfillmentFilter === 'all' || type === fulfillmentFilter;
                const matchesProduce = produceFilter === 'all'
                    || (produceFilter === 'veg' && ['supply', 'veg', 'hal', 'leaf', 'english', 'kolkata', 'banana', 'onion', 'c'].includes(category))
                    || (produceFilter === 'fruit' && ['frut', 'fruit'].includes(category));

                if (matchesQuery && matchesZeroFilter && matchesFulfillment && matchesProduce) {
                    row.classList.remove('hidden');
                } else {
                    row.classList.add('hidden');
                }
            });
        }

        // Clear filter
        function clearSearch() {
            document.getElementById('board-search').value = '';
            document.getElementById('filter-has-orders').checked = true;
            document.getElementById('filter-fulfillment').value = 'all';
            document.getElementById('filter-produce').value = 'all';
            filterBoardRows();
        }

        function buildBoardExportUrl(baseUrl) {
            const params = new URLSearchParams();
            params.set('date', @json($date));
            params.set('search', document.getElementById('board-search').value.trim());
            params.set('fulfillment', document.getElementById('filter-fulfillment').value);
            params.set('produce', document.getElementById('filter-produce').value);
            params.set('has_orders', document.getElementById('filter-has-orders').checked ? '1' : '0');

            document.querySelectorAll('.product-row').forEach((row) => {
                if (!row.classList.contains('hidden')) {
                    params.append('ordered_product_ids[]', row.getAttribute('data-product-id'));
                }
            });

            return `${baseUrl}?${params.toString()}`;
        }

        function exportBoardCsv() {
            window.location.href = buildBoardExportUrl(@json(route('requisitions.board.export.csv')));
        }

        function exportBoardPdf() {
            window.open(buildBoardExportUrl(@json(route('requisitions.board.export.pdf'))), '_blank', 'noopener');
        }

        // Action when fulfillment type changes on a row
        function onFulfillmentRadioChange(radio) {
            const row = radio.closest('tr');
            row.setAttribute('data-fulfillment-type', radio.value);
            filterBoardRows();
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
            const table = document.getElementById('matrix-table');
            const shops = @json($shops);
            let grandTotal = 0;

            shops.forEach(shop => {
                let shopTotal = 0;
                table.querySelectorAll(`input[data-shop-id="${shop.id}"]`).forEach(inp => {
                    const val = parseFloat(inp.value) || 0;
                    shopTotal += val;
                });
                
                const colCell = document.getElementById(`col-total-${shop.id}`);
                if (colCell) {
                    colCell.textContent = shopTotal.toFixed(2);
                }
                grandTotal += shopTotal;
            });

            const grandCell = document.getElementById('grand-total');
            if (grandCell) {
                grandCell.textContent = grandTotal.toFixed(2);
            }
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
