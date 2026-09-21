<x-layouts.purchaser-v2 title="Purchaser V2 &bull; Buy Workspace">
    <div class="space-y-4 sm:space-y-6">

        <!-- Top Header & Context Bar -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('purchaser-v2.daily', ['date' => $date, 'purchase_grade' => $grade]) }}"
                   class="flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-900 active:scale-95">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-black tracking-tight text-slate-900 sm:text-2xl">Buy Workspace</h1>
                        <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-black text-emerald-700 border border-emerald-200">
                            Grade {{ $grade }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 font-medium">Select multiple items, configure quantities & prices, and draft cart.</p>
                </div>
            </div>

            <!-- Date & Cart Indicator -->
            <div class="flex items-center gap-2 self-start sm:self-auto">
                <span class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm">
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    {{ \Illuminate\Support\Carbon::parse($date)->format('D, d M Y') }}
                </span>
                @if ($draftCartsCount > 0)
                    <a href="{{ route('purchaser-v2.cart.index', ['date' => $date, 'grade' => $grade]) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-800 shadow-sm transition hover:bg-amber-100">
                        <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span>{{ $draftCartsCount }} Draft {{ $draftCartsCount === 1 ? 'Cart' : 'Carts' }}</span>
                    </a>
                @endif
            </div>
        </div>

        <!-- Main Workspace Grid: Selection Source & Selected Items -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- Left Panel: Intended Demand & Add-on Search (5 Cols on desktop) -->
            <div class="lg:col-span-5 space-y-4">
                <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                    
                    <!-- Toggle Bar: Intended vs Add-on -->
                    <div class="flex rounded-2xl bg-slate-100 p-1 mb-4">
                        <button type="button" id="tabBtnIntended"
                                class="flex-1 rounded-xl py-2 text-xs font-black transition shadow-sm bg-white text-slate-900">
                            Intended Demand ({{ count($pendingIntendedProducts) }})
                        </button>
                        <button type="button" id="tabBtnAddon"
                                class="flex-1 rounded-xl py-2 text-xs font-black transition text-slate-500 hover:text-slate-900">
                            Add-on Search
                        </button>
                    </div>

                    <!-- Panel 1: Intended Demand Checklist -->
                    <div id="intendedPanel" class="space-y-3">
                        <div class="flex items-center justify-between px-1">
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Pending Approved Items</span>
                            <button type="button" id="btnSelectAllIntended" class="text-[11px] font-bold text-emerald-600 hover:text-emerald-700">
                                Select All
                            </button>
                        </div>

                        <div class="max-h-[420px] overflow-y-auto divide-y divide-slate-100 pr-1 space-y-1">
                            @forelse ($pendingIntendedProducts as $item)
                                <label class="flex items-center justify-between p-2.5 rounded-2xl transition hover:bg-slate-50 cursor-pointer group">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <input type="checkbox"
                                               class="intended-checkbox h-4 w-4 rounded-lg border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                               value="{{ $item['product_id'] }}"
                                               @checked(in_array($item['product_id'], $preselectedIds, true))>
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-slate-900 truncate group-hover:text-emerald-600 transition">{{ $item['name'] }}</p>
                                            <p class="text-[10px] text-slate-400 font-mono">{{ $item['category_name'] }} &bull; SKU: {{ $item['sku'] }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right pl-2 shrink-0">
                                        <span class="inline-flex items-center rounded-lg bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-200/60 font-mono">
                                            {{ number_format($item['remaining_qty'], 1) }} {{ $item['unit'] }} left
                                        </span>
                                    </div>
                                </label>
                            @empty
                                <div class="p-6 text-center text-xs text-slate-400 font-medium">
                                    No pending approved demand remaining for today.
                                </div>
                            @endforelse
                        </div>

                        <button type="button" id="btnAddCheckedToIntended"
                                class="w-full rounded-2xl bg-slate-900 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-slate-800 active:scale-95">
                            Add Selected to Buy Workspace
                        </button>
                    </div>

                    <!-- Panel 2: Add-on Live Product Search -->
                    <div id="addonPanel" class="space-y-3 hidden">
                        <div class="relative">
                            <input type="text" id="addonSearchInput"
                                   placeholder="Type product name or SKU..."
                                   class="h-10 w-full rounded-2xl border border-slate-200 bg-slate-50 pl-10 pr-4 text-xs font-bold text-slate-900 placeholder:text-slate-400 focus:border-emerald-600 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <div id="addonSearchSpinner" class="hidden absolute inset-y-0 right-0 flex items-center pr-3.5">
                                <svg class="animate-spin h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                </svg>
                            </div>
                        </div>

                        <div id="addonSearchResults" class="max-h-[360px] overflow-y-auto divide-y divide-slate-100 pr-1 space-y-1">
                            <div class="p-6 text-center text-xs text-slate-400">
                                Search for any active product to add as non-intended / extra purchase.
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Right Panel: Selected Workspace Grid (7 Cols on desktop) -->
            <div class="lg:col-span-7 space-y-4">
                <form id="cartStoreForm" method="POST" action="{{ route('purchaser-v2.cart.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="business_date" value="{{ $date }}">
                    <input type="hidden" name="purchase_grade" value="{{ $grade }}">
                    <input type="hidden" name="submission_key" value="{{ uniqid('v2_buy_', true) }}">

                    <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <div>
                                <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Selected To Buy</h2>
                                <p class="text-[11px] text-slate-400 font-medium">Configure quantities and unit prices</p>
                            </div>
                            <span id="selectedCountBadge" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-700 font-mono">
                                {{ count($selectedProducts) }} items
                            </span>
                        </div>

                        <!-- Workspace Item Cards / Rows Container -->
                        <div id="selectedItemsContainer" class="space-y-3">
                            @forelse ($selectedProducts as $idx => $p)
                                <div class="workspace-item rounded-2xl border border-slate-200/80 bg-slate-50/50 p-3.5 space-y-3 transition hover:border-slate-300"
                                     data-product-id="{{ $p['product_id'] }}">
                                    
                                    <input type="hidden" name="items[{{ $p['product_id'] }}][product_id]" value="{{ $p['product_id'] }}">

                                    <!-- Top Row: Product Title, Badges, Remove Button -->
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <h3 class="text-xs font-black text-slate-900 truncate">{{ $p['name'] }}</h3>
                                                @if ($p['is_intended'])
                                                    <span class="rounded-md bg-sky-50 px-1.5 py-0.5 text-[9px] font-bold text-sky-700 border border-sky-200">
                                                        Intended
                                                    </span>
                                                @else
                                                    <span class="rounded-md bg-purple-50 px-1.5 py-0.5 text-[9px] font-bold text-purple-700 border border-purple-200">
                                                        Add-on
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="text-[10px] text-slate-400 font-mono">{{ $p['category_name'] }} &bull; SKU: {{ $p['sku'] }}</p>
                                        </div>

                                        <button type="button" class="btn-remove-item text-slate-400 hover:text-rose-600 transition p-1" title="Remove from workspace">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>

                                    <!-- Middle Row: Demand Stats (if intended) -->
                                    @if ($p['is_intended'])
                                        <div class="flex items-center gap-2 text-[10px] font-mono text-slate-500 bg-white rounded-xl p-2 border border-slate-100">
                                            <span>Needed: <strong class="text-slate-900">{{ number_format($p['approved_qty'], 1) }}</strong></span>
                                            <span>&bull;</span>
                                            <span>Bought: <strong class="text-emerald-600">{{ number_format($p['submitted_qty'], 1) }}</strong></span>
                                            <span>&bull;</span>
                                            <span>Remaining: <strong class="text-amber-600">{{ number_format($p['remaining_qty'], 1) }}</strong> {{ $p['base_unit'] }}</span>
                                        </div>
                                    @endif

                                    <!-- Bottom Row: Input Controls (Qty, Unit, Unit Price, Line Total) -->
                                    <div class="grid grid-cols-12 gap-2 items-end">
                                        <!-- Qty Input -->
                                        <div class="col-span-4">
                                            <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Buy Qty</label>
                                            <input type="number" step="any" min="0.01"
                                                   name="items[{{ $p['product_id'] }}][quantity]"
                                                   value="{{ $p['default_purchase_qty'] > 0 ? $p['default_purchase_qty'] : 1 }}"
                                                   class="input-qty h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-900 text-right focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 font-mono"
                                                   required>
                                        </div>

                                        <!-- Unit Dropdown -->
                                        <div class="col-span-3">
                                            <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Unit</label>
                                            <select name="items[{{ $p['product_id'] }}][unit]"
                                                    class="select-unit h-9 w-full rounded-xl border border-slate-200 bg-white px-2 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 font-mono">
                                                @foreach ($p['orderable_units'] as $u)
                                                    <option value="{{ $u['unit'] }}"
                                                            data-conversion="{{ $u['conversion_to_base'] }}"
                                                            @selected($u['is_base'])>
                                                        {{ $u['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" name="items[{{ $p['product_id'] }}][conversion_to_base]" class="input-conversion" value="1.0">
                                        </div>

                                        <!-- Price Input -->
                                        <div class="col-span-3">
                                            <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Price (₹)</label>
                                            <input type="number" step="0.01" min="0"
                                                   name="items[{{ $p['product_id'] }}][unit_price]"
                                                   value="{{ $p['unit_price'] > 0 ? $p['unit_price'] : '' }}"
                                                   placeholder="0.00"
                                                   class="input-price h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-900 text-right focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 font-mono"
                                                   required>
                                        </div>

                                        <!-- Line Total -->
                                        <div class="col-span-2 text-right">
                                            <span class="block text-[9px] font-black uppercase text-slate-400 mb-1">Total</span>
                                            <span class="line-total-display text-xs font-black text-slate-900 font-mono block py-1.5 truncate">
                                                ₹0.00
                                            </span>
                                        </div>
                                    </div>

                                </div>
                            @empty
                                <div id="emptyWorkspaceMessage" class="p-12 text-center text-slate-400 space-y-2">
                                    <svg class="mx-auto h-8 w-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                    </svg>
                                    <p class="text-xs font-bold text-slate-600">No products selected yet</p>
                                    <p class="text-[11px] text-slate-400">Select items from the Intended list or search Add-on products.</p>
                                </div>
                            @endforelse
                        </div>

                        <!-- Sticky Summary & Submit Action Footer -->
                        <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-50/50 p-4 rounded-2xl">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Estimated Total</p>
                                <div class="flex items-baseline gap-2">
                                    <span id="grandTotalDisplay" class="text-lg font-black text-slate-900 font-mono">₹0.00</span>
                                    <span id="totalItemsDisplay" class="text-xs text-slate-500 font-mono font-medium">0 items</span>
                                </div>
                            </div>

                            <button type="submit" id="btnSubmitCart"
                                    class="rounded-2xl bg-emerald-600 px-6 py-3 text-xs font-black text-white shadow-sm shadow-emerald-600/30 transition hover:bg-emerald-700 active:scale-95 flex items-center justify-center gap-2">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <span>Add To Draft Cart</span>
                            </button>
                        </div>

                    </div>
                </form>
            </div>

        </div>

    </div>

    <!-- Client-Side Vanilla JS Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabBtnIntended = document.getElementById('tabBtnIntended');
            const tabBtnAddon = document.getElementById('tabBtnAddon');
            const intendedPanel = document.getElementById('intendedPanel');
            const addonPanel = document.getElementById('addonPanel');
            const addonSearchInput = document.getElementById('addonSearchInput');
            const addonSearchResults = document.getElementById('addonSearchResults');
            const addonSearchSpinner = document.getElementById('addonSearchSpinner');
            const selectedItemsContainer = document.getElementById('selectedItemsContainer');
            const emptyWorkspaceMessage = document.getElementById('emptyWorkspaceMessage');
            const selectedCountBadge = document.getElementById('selectedCountBadge');
            const grandTotalDisplay = document.getElementById('grandTotalDisplay');
            const totalItemsDisplay = document.getElementById('totalItemsDisplay');
            const btnSelectAllIntended = document.getElementById('btnSelectAllIntended');
            const btnAddCheckedToIntended = document.getElementById('btnAddCheckedToIntended');

            let searchAbortController = null;
            let searchDebounceTimer = null;

            // Tab Switching
            tabBtnIntended.addEventListener('click', () => {
                tabBtnIntended.className = 'flex-1 rounded-xl py-2 text-xs font-black transition shadow-sm bg-white text-slate-900';
                tabBtnAddon.className = 'flex-1 rounded-xl py-2 text-xs font-black transition text-slate-500 hover:text-slate-900';
                intendedPanel.classList.remove('hidden');
                addonPanel.classList.add('hidden');
            });

            tabBtnAddon.addEventListener('click', () => {
                tabBtnAddon.className = 'flex-1 rounded-xl py-2 text-xs font-black transition shadow-sm bg-white text-slate-900';
                tabBtnIntended.className = 'flex-1 rounded-xl py-2 text-xs font-black transition text-slate-500 hover:text-slate-900';
                addonPanel.classList.remove('hidden');
                intendedPanel.classList.add('hidden');
                addonSearchInput.focus();
            });

            // Select All Intended Checkboxes
            btnSelectAllIntended.addEventListener('click', () => {
                const checkboxes = document.querySelectorAll('.intended-checkbox');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                checkboxes.forEach(cb => cb.checked = !allChecked);
                btnSelectAllIntended.textContent = allChecked ? 'Select All' : 'Deselect All';
            });

            // Add Checked Intended Items to Workspace
            btnAddCheckedToIntended.addEventListener('click', async () => {
                const checkedIds = Array.from(document.querySelectorAll('.intended-checkbox:checked'))
                    .map(cb => parseInt(cb.value, 10))
                    .filter(id => !document.querySelector(`.workspace-item[data-product-id="${id}"]`));

                if (checkedIds.length === 0) return;

                await fetchAndAddProducts(checkedIds);
            });

            // Add-on Search Debounce with AbortController
            addonSearchInput.addEventListener('input', (e) => {
                const query = e.target.value.trim();
                clearTimeout(searchDebounceTimer);

                if (query.length < 2) {
                    addonSearchResults.innerHTML = '<div class="p-6 text-center text-xs text-slate-400">Type at least 2 characters to search...</div>';
                    return;
                }

                searchDebounceTimer = setTimeout(async () => {
                    if (searchAbortController) {
                        searchAbortController.abort();
                    }
                    searchAbortController = new AbortController();
                    addonSearchSpinner.classList.remove('hidden');

                    try {
                        const res = await fetch(`{{ route('purchaser-v2.products.search') }}?q=${encodeURIComponent(query)}`, {
                            headers: { 'Accept': 'application/json' },
                            signal: searchAbortController.signal,
                        });
                        const json = await res.json();
                        addonSearchSpinner.classList.add('hidden');

                        if (json.status === 'success' && json.data.length > 0) {
                            addonSearchResults.innerHTML = json.data.map(p => `
                                <div class="flex items-center justify-between p-2.5 rounded-2xl transition hover:bg-slate-50 cursor-pointer group btn-add-addon-item"
                                     data-id="${p.id}">
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-slate-900 truncate group-hover:text-emerald-600 transition">${p.name}</p>
                                        <p class="text-[10px] text-slate-400 font-mono">${p.category_name} &bull; SKU: ${p.sku}</p>
                                    </div>
                                    <button type="button" class="rounded-xl bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700 border border-emerald-200 hover:bg-emerald-100">
                                        + Add
                                    </button>
                                </div>
                            `).join('');

                            // Attach click handlers to add items
                            document.querySelectorAll('.btn-add-addon-item').forEach(el => {
                                el.addEventListener('click', async () => {
                                    const id = parseInt(el.dataset.id, 10);
                                    await fetchAndAddProducts([id]);
                                    addonSearchInput.value = '';
                                    addonSearchResults.innerHTML = '<div class="p-6 text-center text-xs text-slate-400">Search for any active product to add as non-intended / extra purchase.</div>';
                                });
                            });
                        } else {
                            addonSearchResults.innerHTML = '<div class="p-6 text-center text-xs text-slate-400">No matching products found.</div>';
                        }
                    } catch (err) {
                        if (err.name !== 'AbortError') {
                            addonSearchSpinner.classList.add('hidden');
                            addonSearchResults.innerHTML = '<div class="p-6 text-center text-xs text-rose-500">Error searching products.</div>';
                        }
                    }
                }, 300);
            });

            // Fetch product details for selected IDs and inject cards into workspace
            async function fetchAndAddProducts(productIds) {
                if (productIds.length === 0) return;

                const params = new URLSearchParams();
                productIds.forEach(id => params.append('product_ids[]', id));
                params.append('date', '{{ $date }}');
                params.append('grade', '{{ $grade }}');

                try {
                    const res = await fetch(`{{ route('purchaser-v2.buy.product-details') }}?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const json = await res.json();

                    if (json.status === 'success' && json.data.length > 0) {
                        if (emptyWorkspaceMessage) {
                            emptyWorkspaceMessage.remove();
                        }

                        json.data.forEach(p => {
                            if (document.querySelector(`.workspace-item[data-product-id="${p.product_id}"]`)) {
                                return;
                            }

                            const card = document.createElement('div');
                            card.className = 'workspace-item rounded-2xl border border-slate-200/80 bg-slate-50/50 p-3.5 space-y-3 transition hover:border-slate-300';
                            card.dataset.productId = p.product_id;

                            const unitsOptions = p.orderable_units.map(u => `
                                <option value="${u.unit}" data-conversion="${u.conversion_to_base}" ${u.is_base ? 'selected' : ''}>
                                    ${u.label}
                                </option>
                            `).join('');

                            const demandBadge = p.is_intended ? `
                                <div class="flex items-center gap-2 text-[10px] font-mono text-slate-500 bg-white rounded-xl p-2 border border-slate-100">
                                    <span>Needed: <strong class="text-slate-900">${p.approved_qty.toFixed(1)}</strong></span>
                                    <span>&bull;</span>
                                    <span>Bought: <strong class="text-emerald-600">${p.submitted_qty.toFixed(1)}</strong></span>
                                    <span>&bull;</span>
                                    <span>Remaining: <strong class="text-amber-600">${p.remaining_qty.toFixed(1)}</strong> ${p.base_unit}</span>
                                </div>
                            ` : '';

                            card.innerHTML = `
                                <input type="hidden" name="items[${p.product_id}][product_id]" value="${p.product_id}">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <h3 class="text-xs font-black text-slate-900 truncate">${p.name}</h3>
                                            <span class="rounded-md ${p.is_intended ? 'bg-sky-50 text-sky-700 border-sky-200' : 'bg-purple-50 text-purple-700 border-purple-200'} px-1.5 py-0.5 text-[9px] font-bold border">
                                                ${p.is_intended ? 'Intended' : 'Add-on'}
                                            </span>
                                        </div>
                                        <p class="text-[10px] text-slate-400 font-mono">${p.category_name} &bull; SKU: ${p.sku}</p>
                                    </div>
                                    <button type="button" class="btn-remove-item text-slate-400 hover:text-rose-600 transition p-1" title="Remove from workspace">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>

                                ${demandBadge}

                                <div class="grid grid-cols-12 gap-2 items-end">
                                    <div class="col-span-4">
                                        <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Buy Qty</label>
                                        <input type="number" step="any" min="0.01"
                                               name="items[${p.product_id}][quantity]"
                                               value="${p.default_purchase_qty > 0 ? p.default_purchase_qty : 1}"
                                               class="input-qty h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-900 text-right focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 font-mono"
                                               required>
                                    </div>
                                    <div class="col-span-3">
                                        <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Unit</label>
                                        <select name="items[${p.product_id}][unit]"
                                                class="select-unit h-9 w-full rounded-xl border border-slate-200 bg-white px-2 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 font-mono">
                                            ${unitsOptions}
                                        </select>
                                        <input type="hidden" name="items[${p.product_id}][conversion_to_base]" class="input-conversion" value="1.0">
                                    </div>
                                    <div class="col-span-3">
                                        <label class="block text-[9px] font-black uppercase text-slate-400 mb-1">Price (₹)</label>
                                        <input type="number" step="0.01" min="0"
                                               name="items[${p.product_id}][unit_price]"
                                               value="${p.unit_price > 0 ? p.unit_price : ''}"
                                               placeholder="0.00"
                                               class="input-price h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-900 text-right focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 font-mono"
                                               required>
                                    </div>
                                    <div class="col-span-2 text-right">
                                        <span class="block text-[9px] font-black uppercase text-slate-400 mb-1">Total</span>
                                        <span class="line-total-display text-xs font-black text-slate-900 font-mono block py-1.5 truncate">
                                            ₹0.00
                                        </span>
                                    </div>
                                </div>
                            `;

                            selectedItemsContainer.appendChild(card);
                            attachItemListeners(card);
                        });

                        recalculateTotals();
                    }
                } catch (err) {
                    console.error('Error fetching product details:', err);
                }
            }

            // Attach dynamic listeners to an item card
            function attachItemListeners(card) {
                const qtyInput = card.querySelector('.input-qty');
                const unitSelect = card.querySelector('.select-unit');
                const conversionInput = card.querySelector('.input-conversion');
                const priceInput = card.querySelector('.input-price');
                const removeBtn = card.querySelector('.btn-remove-item');

                unitSelect.addEventListener('change', () => {
                    const selectedOpt = unitSelect.options[unitSelect.selectedIndex];
                    const conversion = parseFloat(selectedOpt.dataset.conversion || '1.0');
                    conversionInput.value = conversion;
                    recalculateTotals();
                });

                qtyInput.addEventListener('input', recalculateTotals);
                priceInput.addEventListener('input', recalculateTotals);

                removeBtn.addEventListener('click', () => {
                    const productId = card.dataset.productId;
                    card.remove();

                    // Uncheck in intended checklist if present
                    const cb = document.querySelector(`.intended-checkbox[value="${productId}"]`);
                    if (cb) cb.checked = false;

                    recalculateTotals();
                });
            }

            // Recalculate Live Totals across all workspace items
            function recalculateTotals() {
                const cards = document.querySelectorAll('.workspace-item');
                let grandTotal = 0;
                let totalItems = cards.length;

                cards.forEach(card => {
                    const qty = parseFloat(card.querySelector('.input-qty')?.value || '0');
                    const conversion = parseFloat(card.querySelector('.input-conversion')?.value || '1.0');
                    const price = parseFloat(card.querySelector('.input-price')?.value || '0');
                    const lineDisplay = card.querySelector('.line-total-display');

                    const lineTotal = (qty * conversion) * price;
                    if (lineDisplay) {
                        lineDisplay.textContent = '₹' + lineTotal.toFixed(2);
                    }
                    grandTotal += lineTotal;
                });

                selectedCountBadge.textContent = `${totalItems} items`;
                totalItemsDisplay.textContent = `${totalItems} items`;
                grandTotalDisplay.textContent = '₹' + grandTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            // Attach listeners to initial rendered cards
            document.querySelectorAll('.workspace-item').forEach(card => attachItemListeners(card));
            recalculateTotals();
        });
    </script>
</x-layouts.purchaser-v2>
