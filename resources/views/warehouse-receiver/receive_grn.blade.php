<x-layouts.app title="Receive Vendor Sheet">
    <div class="mx-auto flex w-full max-w-xl min-w-0 flex-col gap-4 py-3 lg:px-4 lg:py-4">
        
        {{-- Hero Header Box --}}
        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_12px_28px_rgba(15,23,42,0.16)]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(99,102,241,0.25),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#312e81_100%)] px-4 py-4 sm:px-5">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <a href="{{ route('warehouse.receiver.checklist', ['date' => \Carbon\Carbon::parse($grn->received_at)->format('Y-m-d')]) }}" class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 text-white hover:bg-white/20 transition-all border border-white/10 shadow-sm cursor-pointer text-decoration-none">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </a>
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-[0.2em] text-indigo-300 leading-none mb-1">Vendor Sheet Receive</p>
                            <h1 class="text-base font-black tracking-tight text-white">{{ $grn->grn_number }}</h1>
                            <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[9px] font-black uppercase {{ ($grn->purchase_grade ?? 'A') === 'B' ? 'bg-blue-400/25 text-blue-100' : 'bg-emerald-400/25 text-emerald-100' }}">Grade {{ $grn->purchase_grade ?? 'A' }}</span>
                        </div>
                    </div>
                    <span class="rounded-full bg-white/10 border border-white/10 px-2.5 py-1 text-[10px] font-black text-indigo-200">
                        {{ $grn->purchaseOrder?->supplier?->name ?? 'Vendor' }}
                    </span>
                </div>
            </div>
        </section>

        @if (session('warning'))
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900 shadow-sm">
                {{ session('warning') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-900 shadow-sm">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- GRN Meta Card --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <h3 class="text-xs font-black uppercase tracking-[0.14em] text-slate-500 mb-3 pl-0.5">Sheet Information</h3>
            <div class="grid grid-cols-2 gap-y-3 gap-x-4 text-xs">
                <div>
                    <span class="text-slate-400 font-semibold">Purchaser</span>
                    <p class="font-bold text-slate-800 mt-0.5">{{ $grn->purchaseOrder?->purchaserCart?->user?->name ?? 'Purchaser' }}</p>
                </div>
                <div>
                    <span class="text-slate-400 font-semibold">Date</span>
                    <p class="font-bold text-slate-800 mt-0.5">{{ \Carbon\Carbon::parse($grn->received_at)->format('d M Y') }}</p>
                </div>
                <div>
                    <span class="text-slate-400 font-semibold">Total Purchase Qty</span>
                    <p class="font-bold text-slate-800 mt-0.5">
                        {{ number_format((float) $grn->items->sum(fn($item) => $item->purchaseOrderItem?->quantity ?? $item->received_qty), 2) }} kg
                    </p>
                </div>
                <div>
                    <span class="text-slate-400 font-semibold">Vendor Code</span>
                    <p class="font-mono font-bold text-indigo-600 mt-0.5">{{ $grn->purchaseOrder?->supplier?->code ?? 'N/A' }}</p>
                </div>
            </div>
        </div>

        {{-- Receive Form --}}
        <form action="{{ route('warehouse.receiver.process-receive-grn', $grn) }}" method="POST" id="grn-receive-form" class="space-y-4">
            @csrf

            {{-- Warehouse Selection --}}
            <div class="rounded-2xl bg-indigo-50/50 border border-indigo-100 p-4 shadow-sm">
                <label class="block text-xs font-black uppercase tracking-[0.14em] text-indigo-800 mb-2">Default Target Warehouse</label>
                <div class="relative w-full">
                    <select name="warehouse_id" id="default-warehouse-select" required class="w-full appearance-none rounded-xl border border-indigo-200 bg-white pl-3 pr-8 py-2.5 text-sm font-bold text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 cursor-pointer">
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">
                                {{ $wh->name }} ({{ $wh->code }})
                            </option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
                <p class="text-[10px] text-indigo-600 font-bold mt-1.5 pl-0.5">Select a default warehouse. You can override this for individual products below.</p>
            </div>

            @if($advanceSuggestions['has_advance_match'] ?? false)
                <div class="rounded-2xl bg-amber-50 border-2 border-amber-300 p-4 shadow-sm space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-white font-black text-xs">!</span>
                            <h3 class="text-xs font-black uppercase tracking-[0.14em] text-amber-950">Warehouse Advance Found</h3>
                        </div>
                        <span class="rounded-full bg-amber-200/80 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-amber-900">
                            Coverage: {{ $advanceSuggestions['overall_coverage_percentage'] }}%
                        </span>
                    </div>

                    <p class="text-xs font-semibold text-amber-800">
                        Physical stock for this bill was already received in Advance. Confirming will reconcile against open Advance stock without duplicating inventory.
                    </p>

                    <div class="space-y-2 pt-1">
                        @php $matchIndex = 0; @endphp
                        @foreach($advanceSuggestions['items'] as $itemMatch)
                            @if($itemMatch['has_advance_available'] ?? false)
                                <div class="rounded-xl bg-white border border-amber-200 p-3 text-xs">
                                    <div class="flex items-center justify-between font-black text-slate-800 mb-2">
                                        <span>{{ $itemMatch['product_name'] }}</span>
                                        <span class="text-amber-700 font-mono">{{ $itemMatch['coverage_percentage'] }}% Covered</span>
                                    </div>
                                    <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 text-[11px]">
                                        <div>
                                            <span class="text-slate-400 font-semibold block text-[9px] uppercase">Bill Qty</span>
                                            <span class="font-bold text-slate-800">{{ $itemMatch['bill_qty'] ?? $itemMatch['ordered_qty'] }} {{ $itemMatch['unit'] }}</span>
                                        </div>
                                        <div>
                                            <span class="text-slate-400 font-semibold block text-[9px] uppercase">Advance Found</span>
                                            <span class="font-bold text-slate-800">{{ $itemMatch['total_advance_available_qty'] }} {{ $itemMatch['unit'] }}</span>
                                        </div>
                                        <div>
                                            <span class="text-slate-400 font-semibold block text-[9px] uppercase">From Advance</span>
                                            <span class="font-bold text-emerald-600">{{ $itemMatch['total_proposed_match_qty'] }} {{ $itemMatch['unit'] }}</span>
                                        </div>
                                        <div>
                                            <span class="text-slate-400 font-semibold block text-[9px] uppercase">New Receive</span>
                                            <span class="font-bold {{ $itemMatch['new_receive_qty'] > 0 ? 'text-amber-600' : 'text-slate-500' }}">{{ $itemMatch['new_receive_qty'] }} {{ $itemMatch['unit'] }}</span>
                                        </div>
                                        <div>
                                            <span class="text-slate-400 font-semibold block text-[9px] uppercase">Coverage</span>
                                            <span class="font-bold text-indigo-600">{{ $itemMatch['coverage_percentage'] }}%</span>
                                        </div>
                                        <div>
                                            <span class="text-slate-400 font-semibold block text-[9px] uppercase">Remaining Adv</span>
                                            <span class="font-bold text-slate-700">{{ max(0, round($itemMatch['total_advance_available_qty'] - $itemMatch['total_proposed_match_qty'], 2)) }} {{ $itemMatch['unit'] }}</span>
                                        </div>
                                    </div>

                                    @foreach($itemMatch['suggested_matches'] as $sMatch)
                                        <input type="hidden" name="advance_matches[{{ $matchIndex }}][advance_goods_received_id]" value="{{ $sMatch['advance_goods_received_id'] }}">
                                        <input type="hidden" name="advance_matches[{{ $matchIndex }}][advance_goods_received_item_id]" value="{{ $sMatch['advance_goods_received_item_id'] }}">
                                        <input type="hidden" name="advance_matches[{{ $matchIndex }}][purchase_order_item_id]" value="{{ $itemMatch['purchase_order_item_id'] ?? '' }}">
                                        <input type="hidden" name="advance_matches[{{ $matchIndex }}][goods_received_item_id]" value="{{ $itemMatch['goods_received_item_id'] ?? '' }}">
                                        <input type="hidden" name="advance_matches[{{ $matchIndex }}][product_id]" value="{{ $itemMatch['product_id'] }}">
                                        <input type="hidden" name="advance_matches[{{ $matchIndex }}][matched_qty]" value="{{ $sMatch['matched_qty'] ?? $sMatch['proposed_match_qty'] ?? 0 }}">
                                        <input type="hidden" name="advance_matches[{{ $matchIndex }}][unit]" value="{{ $sMatch['unit'] }}">
                                        @php $matchIndex++; @endphp
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @php
                $grnCategoryNames = $groupedItems->keys()->filter()->sort()->values();
                $grnItemCount = $groupedItems->flatten(1)->count();
            @endphp

            <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                <div class="grid gap-2 sm:grid-cols-[1fr_180px]">
                    <div>
                        <label for="grn-product-search" class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Find Product</label>
                        <input id="grn-product-search" type="search" placeholder="Search product, SKU, category..." class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none">
                    </div>
                    <div class="relative">
                        <label for="grn-product-category" class="mb-1 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Category</label>
                        <select id="grn-product-category" class="h-11 w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-9 text-xs font-black text-slate-700 focus:border-indigo-500 focus:bg-white focus:outline-none">
                            <option value="">All Categories</option>
                            @foreach($grnCategoryNames as $categoryName)
                                <option value="{{ strtolower($categoryName) }}">{{ $categoryName }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 top-5 flex items-center pr-3 text-slate-400">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </div>
                <p id="grn-product-filter-count" class="mt-2 text-[11px] font-bold text-slate-500">{{ $grnItemCount }} product(s)</p>
            </section>

            {{-- Grouped Items --}}
            <div class="space-y-4" id="grn-product-list">
                @foreach($groupedItems as $categoryName => $items)
                    <div class="grn-category-card rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h2 class="text-xs font-black uppercase tracking-[0.14em] text-slate-900 border-b border-slate-100 pb-2 mb-3">
                            {{ $categoryName }}
                        </h2>
                        <div class="divide-y divide-slate-100">
                            @foreach($items as $item)
                                @php
                                    $purchasedQty = (float) ($item->purchaseOrderItem?->quantity ?? $item->received_qty);
                                    $itemSku = $item->product?->sku ?? '';
                                @endphp
                                <div class="grn-product-row py-3.5 first:pt-0 last:pb-0"
                                     data-item-id="{{ $item->id }}"
                                     data-search="{{ strtolower(trim(($item->product?->name ?? '').' '.$itemSku.' '.$categoryName)) }}"
                                     data-category="{{ strtolower($categoryName) }}">
                                    <div class="flex items-start justify-between gap-3 min-w-0">
                                        <div class="min-w-0 flex-1">
                                            <h4 class="truncate text-sm font-black text-slate-950">{{ $item->product->name }}</h4>
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-600 mt-1">
                                                Purchased: {{ number_format($purchasedQty, 2) }} kg
                                            </span>
                                        </div>
                                        <div class="shrink-0 flex items-center gap-1.5">
                                            <input type="number" 
                                                   step="0.001" 
                                                   name="items[{{ $item->id }}][received_qty]" 
                                                   value="{{ $purchasedQty }}" 
                                                   data-purchased="{{ $purchasedQty }}"
                                                   class="received-qty-input w-24 text-right rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-black text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                                                   required>
                                            <span class="text-xs text-slate-400 font-bold">kg</span>
                                        </div>
                                    </div>

                                    {{-- Warehouse override dropdown --}}
                                    <div class="mt-3 flex items-center justify-between gap-3">
                                        <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Target Warehouse</span>
                                        <div class="w-48 shrink-0 relative">
                                            <select name="items[{{ $item->id }}][warehouse_id]" 
                                                    class="product-warehouse-select w-full appearance-none rounded-xl border border-slate-200 bg-white pl-2.5 pr-8 py-1.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 cursor-pointer"
                                                    data-default-warehouse-id="{{ $item->product->default_warehouse_id }}"
                                                    data-manual="false">
                                                @foreach($warehouses as $wh)
                                                    <option value="{{ $wh->id }}" @selected(old("items.{$item->id}.warehouse_id", $item->product->default_warehouse_id) == $wh->id)>
                                                        {{ $wh->name }} ({{ $wh->code }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-500">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Discrepancy details --}}
                                    <div class="discrepancy-panel mt-3 bg-slate-50 border border-slate-100 rounded-xl p-3 hidden">
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-[9px] font-black uppercase tracking-[0.14em] text-slate-500 mb-1">Discrepancy Action</label>
                                                <div class="relative w-full">
                                                    <select name="items[{{ $item->id }}][discrepancy_type]" class="discrepancy-type-select w-full appearance-none rounded-lg border border-slate-200 bg-white pl-2 pr-6 py-1.5 text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 cursor-pointer">
                                                        <option value="none">Choose Action...</option>
                                                        <option value="wastage">Move to Wastage</option>
                                                        <option value="other">Other (Adjustment)</option>
                                                    </select>
                                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2 text-slate-500">
                                                        <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                        </svg>
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-[9px] font-black uppercase tracking-[0.14em] text-slate-500 mb-1">Variance Note</label>
                                                <input type="text" 
                                                       name="items[{{ $item->id }}][discrepancy_note]" 
                                                       placeholder="Reason for shortage..." 
                                                       class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div id="grn-product-empty" class="hidden rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm font-bold text-slate-500">
                No products match these filters.
            </div>

            {{-- Submit Form Action --}}
            <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
                <button type="submit" class="w-full {{ ($advanceSuggestions['has_advance_match'] ?? false) ? 'bg-amber-600 hover:bg-amber-700' : 'bg-indigo-600 hover:bg-indigo-700' }} text-white rounded-xl py-3.5 text-xs font-black uppercase tracking-wider shadow-md transition-colors border-none cursor-pointer">
                    {{ ($advanceSuggestions['has_advance_match'] ?? false) ? 'Confirm Match & Receive' : 'Confirm Receipt & Update Inventory' }}
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const qtyInputs = document.querySelectorAll('.received-qty-input');
            const productSearch = document.getElementById('grn-product-search');
            const productCategory = document.getElementById('grn-product-category');
            const productRows = Array.from(document.querySelectorAll('.grn-product-row'));
            const productCards = Array.from(document.querySelectorAll('.grn-category-card'));
            const productEmpty = document.getElementById('grn-product-empty');
            const productCount = document.getElementById('grn-product-filter-count');

            function filterGrnProducts() {
                const query = (productSearch?.value || '').trim().toLowerCase();
                const category = productCategory?.value || '';
                let visibleCount = 0;

                productRows.forEach(row => {
                    const matchesSearch = query === '' || (row.dataset.search || '').includes(query);
                    const matchesCategory = category === '' || row.dataset.category === category;

                    if (matchesSearch && matchesCategory) {
                        row.classList.remove('hidden');
                        visibleCount++;
                    } else {
                        row.classList.add('hidden');
                    }
                });

                productCards.forEach(card => {
                    const hasVisibleRows = Array.from(card.querySelectorAll('.grn-product-row'))
                        .some(row => !row.classList.contains('hidden'));
                    card.classList.toggle('hidden', !hasVisibleRows);
                });

                if (productEmpty) {
                    productEmpty.classList.toggle('hidden', visibleCount > 0);
                }

                if (productCount) {
                    productCount.textContent = visibleCount + ' of ' + productRows.length + ' product(s)';
                }
            }

            productSearch?.addEventListener('input', filterGrnProducts);
            productCategory?.addEventListener('change', filterGrnProducts);
            filterGrnProducts();

            qtyInputs.forEach(input => {
                input.addEventListener('input', () => {
                    const purchased = parseFloat(input.dataset.purchased);
                    const received = parseFloat(input.value) || 0;
                    const container = input.closest('[data-item-id]');
                    const panel = container.querySelector('.discrepancy-panel');
                    const select = container.querySelector('.discrepancy-type-select');

                    if (received < purchased) {
                        panel.classList.remove('hidden');
                        select.required = true;
                        if (select.value === 'none') {
                            select.value = 'wastage'; // default to wastage shortage
                        }
                    } else {
                        panel.classList.add('hidden');
                        select.required = false;
                        select.value = 'none';
                    }
                });
            });

            // Warehouse synchronization logic
            const defaultWhSelect = document.getElementById('default-warehouse-select');
            const productWhSelects = document.querySelectorAll('.product-warehouse-select');

            // Set initial values based on defaultWhSelect for selects without a product default
            productWhSelects.forEach(select => {
                const productDefault = select.dataset.defaultWarehouseId;
                if (!productDefault) {
                    select.value = defaultWhSelect.value;
                }
                
                // If user changes individual select, mark it as manual
                select.addEventListener('change', () => {
                    select.dataset.manual = 'true';
                });
            });

            // When default target warehouse changes, update all non-manually-changed selects
            defaultWhSelect.addEventListener('change', () => {
                productWhSelects.forEach(select => {
                    if (select.dataset.manual !== 'true') {
                        select.value = defaultWhSelect.value;
                    }
                });
            });

            // Form submission confirmation
            const form = document.getElementById('grn-receive-form');
            form.addEventListener('submit', (e) => {
                let hasUnresolvedDiscrepancy = false;
                qtyInputs.forEach(input => {
                    const purchased = parseFloat(input.dataset.purchased);
                    const received = parseFloat(input.value) || 0;
                    const container = input.closest('[data-item-id]');
                    const select = container.querySelector('.discrepancy-type-select');

                    if (received < purchased && select.value === 'none') {
                        hasUnresolvedDiscrepancy = true;
                    }
                });

                if (hasUnresolvedDiscrepancy) {
                    e.preventDefault();
                    alert('Please select a discrepancy action (Wastage or Other) for all items with short quantities.');
                }
            });
        });
    </script>
    @endpush
</x-layouts.app>
