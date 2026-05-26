<x-layouts.app title="Approved Requisitions Board">
    <div class="mx-auto px-4 py-8">
        {{-- Header Section --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 mb-8">
            <div>
                <a href="{{ route('dashboard') }}" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 flex items-center gap-1.5 transition-colors mb-2">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    Back to Control Center
                </a>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                    Approved Requisitions Board
                </h1>
                <p class="text-xs text-slate-500 mt-1">View, edit, and export finalized daily allocations across all shops</p>
            </div>

            <div class="flex flex-wrap items-center gap-4">
                {{-- Date selection form --}}
                <form action="{{ route('requisitions.approved_board') }}" method="GET" class="flex items-center gap-3 bg-white px-4 py-2 border border-slate-200 rounded-xl shadow-sm">
                    <label for="date-select" class="text-xs font-bold text-slate-400 uppercase tracking-wider">Delivery Date:</label>
                    <input type="date" id="date-select" name="date" value="{{ $date }}" onchange="this.form.submit()" class="text-xs font-bold text-slate-700 border-0 focus:outline-none focus:ring-0 p-0 cursor-pointer">
                </form>

                {{-- CSV Export --}}
                <a href="{{ route('requisitions.approved_board.export.csv', ['date' => $date]) }}" class="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-4 py-3 rounded-xl transition-all border border-slate-200 shadow-sm">
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    Export CSV
                </a>

                {{-- PDF Export --}}
                <a href="{{ route('requisitions.approved_board.export.pdf', ['date' => $date]) }}" target="_blank" class="inline-flex items-center gap-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold px-4 py-3 rounded-xl transition-all border border-emerald-100 shadow-sm">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.844l-.24.03a.75.75 0 11-.24-1.48l.24-.03a.75.75 0 11.24 1.48zM15 10.5a.75.75 0 01.75-.75h.008a.75.75 0 01.75.75v.008a.75.75 0 01-.75.75h-.008a.75.75 0 01-.75-.75V10.5zm-6-1.5a.75.75 0 01.75-.75h.008a.75.75 0 01.75.75v.008a.75.75 0 01-.75.75h-.008a.75.75 0 01-.75-.75V9zm6 3a.75.75 0 01.75-.75h.008a.75.75 0 01.75.75v.008a.75.75 0 01-.75.75h-.008a.75.75 0 01-.75-.75v-.008zm-6 3a.75.75 0 01.75-.75h.008a.75.75 0 01.75.75v.008a.75.75 0 01-.75.75h-.008a.75.75 0 01-.75-.75v-.008z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582" /></svg>
                    Print PDF
                </a>

                {{-- Save Button --}}
                <button type="submit" form="board-form" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-5 py-3 rounded-xl transition-all cursor-pointer focus:outline-none shadow-md hover:shadow-lg border-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    Save & Update
                </button>
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

            <div class="flex items-center gap-4 self-stretch md:self-auto shrink-0 pl-2">
                <label class="flex items-center gap-2 text-xs font-bold text-slate-600 cursor-pointer select-none">
                    <input type="checkbox" id="filter-has-orders" onchange="filterBoardRows()" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer">
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

            {{-- Table Wrapper --}}
            <div class="bg-white border border-slate-200 rounded-3xl shadow-sm overflow-hidden mb-6">
                <div class="overflow-x-auto max-w-full">
                    <table class="w-full text-left border-collapse" id="matrix-table">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-4 px-4 text-center sticky left-0 bg-slate-50 z-20 w-[60px]">SL NO</th>
                                <th class="py-4 px-4 sticky left-[60px] bg-slate-50 z-20 min-w-[200px] border-r border-slate-200">Item</th>
                                <th class="py-4 px-3 text-center min-w-[120px] border-r border-slate-200 text-slate-700 uppercase font-black text-[9px] tracking-wider bg-slate-50 z-20">Fulfillment</th>
                                @foreach($shops as $shop)
                                    <th class="py-4 px-3 text-center min-w-[90px] border-r border-slate-100 font-extrabold text-slate-700 uppercase tracking-widest text-[9px] hover:bg-slate-100/50 transition-colors">
                                        {{ str_replace([' HYPERMARKET', ' SUPERMARKET', ' STORE', ' SHOP'], '', strtoupper($shop->name)) }}
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
                                            $rowTotal += $qtyData['approved_qty'] !== null ? $qtyData['approved_qty'] : $qtyData['requested_qty'];
                                        }
                                    }
                                @endphp
                                <tr class="hover:bg-slate-50/10 transition-colors product-row" 
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

                                    {{-- Fulfillment Select --}}
                                    <td class="py-3 px-3 border-r border-slate-200 text-center">
                                        <select name="fulfillment_types[{{ $product->id }}]" onchange="onFulfillmentTypeChange(this)" class="text-[10px] bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 focus:outline-none focus:ring-1 focus:ring-emerald-500 font-black cursor-pointer text-slate-700">
                                            <option value="warehouse" {{ ($productFulfillmentTypes[$product->id] ?? 'warehouse') === 'warehouse' ? 'selected' : '' }}>Warehouse (Bulk)</option>
                                            <option value="selection" {{ ($productFulfillmentTypes[$product->id] ?? 'warehouse') === 'selection' ? 'selected' : '' }}>Selection (Packet)</option>
                                        </select>
                                    </td>
                                    
                                    {{-- Shops Inputs --}}
                                    @foreach($shops as $shop)
                                        @php
                                            $qtyData = $matrix[$product->id][$shop->id] ?? null;
                                            $val = null;
                                            $isAdjusted = false;
                                            if ($qtyData) {
                                                if ($qtyData['approved_qty'] !== null) {
                                                    $val = $qtyData['approved_qty'];
                                                    $isAdjusted = $qtyData['approved_qty'] !== $qtyData['requested_qty'];
                                                } else {
                                                    $val = $qtyData['requested_qty'];
                                                }
                                            }
                                        @endphp
                                        <td class="py-2 px-2 text-center border-r border-slate-100 hover:bg-slate-50/5 hover:z-20 transition-colors">
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
                <button type="submit" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-5 py-3 rounded-xl transition-all cursor-pointer focus:outline-none shadow-md hover:shadow-lg border-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    Save & Update Approved Board
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        // Dynamically calculate column totals and grand total on load
        document.addEventListener('DOMContentLoaded', () => {
            calculateColumnTotals();
        });

        // Filter product rows by search query, order presence, and fulfillment type
        function filterBoardRows() {
            const query = document.getElementById('board-search').value.toLowerCase().trim();
            const hideZero = document.getElementById('filter-has-orders').checked;
            const fulfillmentFilter = document.getElementById('filter-fulfillment').value;
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

                if (matchesQuery && matchesZeroFilter && matchesFulfillment) {
                    row.classList.remove('hidden');
                } else {
                    row.classList.add('hidden');
                }
            });
        }

        // Clear filter
        function clearSearch() {
            document.getElementById('board-search').value = '';
            document.getElementById('filter-has-orders').checked = false;
            document.getElementById('filter-fulfillment').value = 'all';
            filterBoardRows();
        }

        // Action when fulfillment type changes on a row
        function onFulfillmentTypeChange(select) {
            const row = select.closest('tr');
            row.setAttribute('data-fulfillment-type', select.value);
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
    </script>
    @endpush
</x-layouts.app>
