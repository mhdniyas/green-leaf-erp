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
            </div>
        </section>

        {{-- Tab Navigation --}}
        <div class="flex gap-1.5 overflow-x-auto pb-1">
            <button id="nav-pending"   type="button" onclick="switchTab('pending')"   class="wr-nav-btn shrink-0 rounded-xl bg-slate-950 px-4 py-2 text-xs font-black text-white shadow-sm active">Receive</button>
            <button id="nav-inventory" type="button" onclick="switchTab('inventory')" class="wr-nav-btn shrink-0 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-600 hover:bg-slate-50">Inventory</button>
            <button id="nav-loadout"   type="button" onclick="switchTab('loadout')"   class="wr-nav-btn shrink-0 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-600 hover:bg-slate-50">Loadout</button>
            <button id="nav-confirmed" type="button" onclick="switchTab('confirmed')" class="wr-nav-btn shrink-0 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-black text-slate-600 hover:bg-slate-50">Delivery</button>
        </div>

        {{-- Tab Panels --}}
        <div class="mt-2">

            {{-- TAB: Receive (Pending) --}}
            <div id="tab-pending" class="wr-tab space-y-4">
                {{-- Filters (server-rendered, form still posts to normal route) --}}
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
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
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
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            </div>
                        </div>
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-3 text-xs font-black text-white transition-colors hover:bg-slate-700 border-none cursor-pointer">Filter</button>
                    </div>
                </form>

                {{-- Async content area --}}
                <div id="tab-pending-content">
                    @include('warehouse-receiver.partials.tab-skeleton', ['lines' => 5])
                </div>
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
                                    <option value="{{ $warehouse->id }}" @selected($selectedWarehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 top-5 flex items-center pr-3 text-slate-400">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            </div>
                        </div>
                        @if($selectedWarehouseId)
                            <a href="{{ route('warehouse.receiver.checklist', ['date' => $date, 'tab' => 'inventory']) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-black text-slate-600 transition-colors hover:bg-slate-50">Clear Filter</a>
                        @endif
                    </div>
                </form>

                {{-- Client-side search (filters rendered rows) --}}
                <div class="flex flex-col gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <input type="search" id="inventory-search" oninput="filterInventory()" placeholder="Search product..." class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none">
                    </div>
                    <div class="relative w-full sm:w-48 shrink-0">
                        <select id="inventory-category-filter" onchange="filterInventory()" class="w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-9 py-2.5 text-xs font-semibold text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none cursor-pointer shadow-sm hover:bg-slate-100/50 transition-colors">
                            <option value="">All Categories</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </div>
                    </div>
                </div>

                {{-- Sub-tab Switcher --}}
                <div class="flex rounded-2xl bg-slate-200/60 p-1 max-w-sm">
                    <button type="button" onclick="switchInvSubTab('in')"    id="subtab-in"    class="flex-1 rounded-xl py-2 text-center text-xs font-black transition-all bg-slate-950 text-white shadow-sm border-none cursor-pointer">IN (+)</button>
                    <button type="button" onclick="switchInvSubTab('out')"   id="subtab-out"   class="flex-1 rounded-xl py-2 text-center text-xs font-black transition-all text-slate-500 hover:text-slate-900 border-none cursor-pointer">OUT (-)</button>
                    <button type="button" onclick="switchInvSubTab('stock')" id="subtab-stock" class="flex-1 rounded-xl py-2 text-center text-xs font-black transition-all text-slate-500 hover:text-slate-900 border-none cursor-pointer">STOCK</button>
                </div>

                {{-- Async content area --}}
                <div id="tab-inventory-content">
                    {{-- Content injected by fetchTabContent('inventory') --}}
                </div>
            </div>

            {{-- TAB: Loadout --}}
            <div id="tab-loadout" class="wr-tab hidden space-y-4">
                <div id="tab-loadout-content">
                    {{-- Content injected by fetchTabContent('loadout') --}}
                </div>
            </div>

            {{-- TAB: Delivery (Confirmed) --}}
            <div id="tab-confirmed" class="wr-tab hidden space-y-4">
                {{-- Sub-tab Switcher --}}
                <div class="flex rounded-2xl bg-slate-200/60 p-1 max-w-xs">
                    <button type="button" onclick="switchDeliverySubTab('orders')" id="del-subtab-btn-orders" class="flex-1 rounded-xl py-2 text-center text-xs font-black transition-all bg-slate-950 text-white shadow-sm border-none cursor-pointer">Orders</button>
                    <button type="button" onclick="switchDeliverySubTab('items')"  id="del-subtab-btn-items"  class="flex-1 rounded-xl py-2 text-center text-xs font-black transition-all text-slate-500 hover:text-slate-900 border-none cursor-pointer">Items List</button>
                </div>

                {{-- Orders sub-tab --}}
                <div id="del-subtab-orders" class="del-subtab-panel space-y-3">
                    <div id="tab-confirmed-content">
                        {{-- Content injected by fetchTabContent('confirmed') --}}
                    </div>
                </div>

                {{-- Items sub-tab (JS-populated from order template) --}}
                <div id="del-subtab-items" class="del-subtab-panel hidden space-y-3">
                    <div id="selected-order-items-container">
                        <div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                            <p class="text-xs text-slate-500">Click an order card to see its item details.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        // ──────────────────────────────────────────────────────────────────────
        // Tab API fetch — lazy loading
        // ──────────────────────────────────────────────────────────────────────
        const tabLoaded  = {};
        const tabApiMap  = {
            pending:   '{{ route('warehouse.receiver.tab.pending') }}',
            inventory: '{{ route('warehouse.receiver.tab.inventory') }}',
            loadout:   '{{ route('warehouse.receiver.tab.loadout') }}',
            confirmed: '{{ route('warehouse.receiver.tab.deliveries') }}',
        };
        const tabContentEl = {
            pending:   document.getElementById('tab-pending-content'),
            inventory: document.getElementById('tab-inventory-content'),
            loadout:   document.getElementById('tab-loadout-content'),
            confirmed: document.getElementById('tab-confirmed-content'),
        };

        function getTabParams() {
            const params = new URLSearchParams(window.location.search);
            // remove tab itself from forwarded params (API handles data, not tabs)
            params.delete('tab');
            return params;
        }

        function showTabSkeleton(tabName) {
            const el = tabContentEl[tabName];
            if (!el) return;
            el.innerHTML = `<div class="space-y-3 animate-pulse">
                ${Array.from({length: 4}, () => `
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="h-3 w-2/3 rounded-full bg-slate-200 mb-2"></div>
                        <div class="h-2.5 w-1/2 rounded-full bg-slate-100"></div>
                    </div>`).join('')}
            </div>`;
        }

        function showTabError(tabName) {
            const el = tabContentEl[tabName];
            if (!el) return;
            el.innerHTML = `<div class="rounded-3xl border border-rose-200 bg-rose-50 p-8 text-center shadow-sm">
                <p class="text-xs font-bold text-rose-600">Failed to load data. <button onclick="reloadTab('${tabName}')" class="underline cursor-pointer border-none bg-transparent text-rose-600">Try again</button></p>
            </div>`;
        }

        function reloadTab(tabName) {
            tabLoaded[tabName] = false;
            fetchTabContent(tabName);
        }

        async function fetchTabContent(tabName) {
            if (tabLoaded[tabName]) return;
            tabLoaded[tabName] = true;

            showTabSkeleton(tabName);

            try {
                const url = tabApiMap[tabName] + '?' + getTabParams().toString();
                const res = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                renderTab(tabName, data);
            } catch (e) {
                tabLoaded[tabName] = false;
                showTabError(tabName);
            }
        }

        // ──────────────────────────────────────────────────────────────────────
        // Renderers — one per tab
        // ──────────────────────────────────────────────────────────────────────

        function renderTab(tabName, data) {
            switch (tabName) {
                case 'pending':   return renderPending(data);
                case 'inventory': return renderInventory(data);
                case 'loadout':   return renderLoadout(data);
                case 'confirmed': return renderConfirmed(data);
            }
        }

        // ── Pending ───────────────────────────────────────────────────────────
        function renderPending(data) {
            const el = tabContentEl.pending;
            const grns       = data.pending_grns || [];
            const batches    = data.pending_batches || [];
            const direct     = data.pending_direct_orders || [];
            const warehouses = data.warehouses || [];
            const total      = grns.length + batches.length + direct.length;

            if (total === 0) {
                el.innerHTML = `<div class="rounded-3xl border border-slate-200 bg-white p-10 text-center shadow-sm">
                    <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 border border-emerald-100">
                        <svg class="h-7 w-7 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-sm font-bold text-slate-900">All Clear!</p>
                    <p class="mt-1 text-xs text-slate-500">No pending vendor sheets or batches for ${data.date}.<br>All stock is in inventory.</p>
                </div>`;
                return;
            }

            let html = '';

            // 1. Pending GRNs (Vendor Sheets)
            if (grns.length > 0) {
                const bulkCount = grns.length;
                html += `
                <div class="space-y-3">
                    <div class="flex flex-col gap-3 pl-1 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Pending Vendor Sheets (${grns.length})</h3>
                        <form action="${escAttr(data.receive_all_grns_url)}" method="POST" class="warehouse-confirm-form w-full sm:w-auto"
                              data-confirm-title="Receive all vendor sheets"
                              data-confirm-message="Receive all ${bulkCount} pending vendor sheet(s) for ${data.date} using current received quantities and default warehouses?"
                              data-confirm-button="Receive all">
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <input type="hidden" name="date" value="${data.date}">
                            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white shadow-sm transition-colors hover:bg-emerald-700 sm:w-auto border-none cursor-pointer">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                Receive All (${bulkCount})
                            </button>
                        </form>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        ${grns.map(grn => `
                            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex items-center justify-between gap-3 transition hover:border-slate-300">
                                <div class="min-w-0 flex-1">
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 border border-indigo-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-indigo-700">
                                        ${escHtml(grn.supplier_name)}
                                    </span>
                                    <h4 class="text-sm font-black text-slate-900 mt-1.5">${escHtml(grn.grn_number)}</h4>
                                    <p class="text-[10px] text-slate-400 font-medium">Purchased by: ${escHtml(grn.purchaser_name)}</p>
                                    <p class="text-[10px] text-slate-500 font-bold mt-1">
                                        Items: ${grn.items_count} · ${(grn.total_kg || 0).toFixed(2)} kg
                                    </p>
                                </div>
                                <a href="${escAttr(grn.receive_url)}" class="shrink-0 inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl px-3.5 py-2.5 text-xs font-black shadow-sm transition-colors text-decoration-none border-none cursor-pointer">
                                    <span>Open Sheet</span>
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                </a>
                            </div>
                        `).join('')}
                    </div>
                </div>`;
            }

            // 2. Pending Direct Purchases
            if (direct.length > 0) {
                html += `
                <div class="space-y-3 mt-4">
                    <h3 class="text-xs font-black uppercase tracking-[0.14em] text-slate-500 pl-1">Pending Direct Purchases (${direct.length})</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        ${direct.map(order => `
                            <div class="rounded-2xl border border-emerald-200 bg-white p-4 shadow-sm">
                                <form action="${escAttr(order.receive_url)}" method="POST" class="space-y-3">
                                    <input type="hidden" name="_token" value="${csrfToken}">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-emerald-700">Direct Purchase</span>
                                            <h4 class="text-sm font-black text-slate-900 mt-1 font-mono">${escHtml(order.order_number)}</h4>
                                        </div>
                                    </div>
                                    <div class="space-y-2 pt-2 border-t border-slate-100">
                                        ${(order.items || []).map(item => `
                                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5 flex items-center justify-between gap-2">
                                                <div class="min-w-0">
                                                    <p class="text-xs font-bold text-slate-900 truncate">${escHtml(item.product_name || '-')}</p>
                                                    <p class="text-[10px] text-slate-500 font-semibold">${item.approved_qty.toFixed(2)} ${escHtml(item.unit || '')}</p>
                                                </div>
                                                <div class="w-36 shrink-0">
                                                    <select name="items[${item.id}][warehouse_id]" required class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] font-bold text-slate-700 shadow-sm focus:border-indigo-500 focus:outline-none cursor-pointer">
                                                        ${warehouses.map(wh => `<option value="${wh.id}" ${wh.id == item.default_warehouse_id ? 'selected' : ''}>${escHtml(wh.name)}</option>`).join('')}
                                                    </select>
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                    <button type="submit" class="w-full rounded-xl bg-emerald-600 px-3 py-2.5 text-xs font-black text-white shadow-sm transition-colors hover:bg-emerald-700 border-none cursor-pointer">
                                        Receive Direct Purchase
                                    </button>
                                </form>
                            </div>
                        `).join('')}
                    </div>
                </div>`;
            }

            // 3. Pending Batches
            if (batches.length > 0) {
                html += `
                <div class="space-y-3 mt-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pl-1">
                        <h3 class="text-xs font-black uppercase tracking-[0.14em] text-slate-500">Pending Batches (${batches.length})</h3>
                        <form action="${escAttr(data.confirm_all_batches_url)}" method="POST" class="warehouse-confirm-form w-full sm:w-auto"
                              data-confirm-title="Confirm all batches"
                              data-confirm-message="Confirm ALL ${batches.length} batch(es) as received? This will move them into active inventory."
                              data-confirm-button="Confirm all">
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <input type="hidden" name="date" value="${data.date}">
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white rounded-xl px-4 py-2.5 text-xs font-black shadow-md transition-all active:scale-98 border-none cursor-pointer">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138z" />
                                </svg>
                                Confirm All ${batches.length} into Inventory
                            </button>
                        </form>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        ${batches.map(batch => `
                            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex flex-col gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 border border-amber-100">
                                        <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="truncate text-sm font-black text-slate-950">${escHtml(batch.product_name || '-')}</h4>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded-md">
                                                ${batch.total_kg.toFixed(2)} ${escHtml(batch.unit || 'kg')}
                                            </span>
                                            <span class="text-[10px] text-slate-400 font-mono">${escHtml(batch.reference || '')}</span>
                                        </div>
                                    </div>
                                </div>
                                <form action="${escAttr(batch.confirm_url)}" method="POST" class="flex items-center gap-2 pt-3 border-t border-dashed border-slate-100">
                                    <input type="hidden" name="_token" value="${csrfToken}">
                                    <div class="flex-1 min-w-0 relative">
                                        <select name="warehouse_id" required class="w-full appearance-none rounded-xl border border-slate-200 bg-white pl-3 pr-9 py-2.5 text-xs font-semibold text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none cursor-pointer transition-all hover:bg-slate-50/50">
                                            ${warehouses.map(wh => `<option value="${wh.id}" ${wh.id == batch.default_warehouse_id ? 'selected' : ''}>${escHtml(wh.name)} (${escHtml(wh.code||'')})</option>`).join('')}
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                        </div>
                                    </div>
                                    <button type="submit" class="shrink-0 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 text-xs font-black shadow-sm transition-colors border-none cursor-pointer">
                                        ✓ Received
                                    </button>
                                </form>
                            </div>
                        `).join('')}
                    </div>
                </div>`;
            }

            el.innerHTML = `<div class="space-y-4">${html}</div>`;
            bindConfirmForms(el);
        }

        // ── Inventory ─────────────────────────────────────────────────────────
        function renderInventory(data) {
            const el = tabContentEl.inventory;

            const inflowRows = (data.inflows || []).map(m => `
                <tr class="inv-inflow-row hover:bg-slate-50/80 transition-colors"
                    data-product-name="${escAttr((m.product_name||'').toLowerCase())}"
                    data-category="${escAttr((m.category_name||'other').toLowerCase())}"
                    data-category-display="${escAttr(m.category_name||'Other')}">
                    <td class="py-3 px-4">
                        <div class="font-bold text-slate-900 truncate">${escHtml(m.product_name||'-')}</div>
                        ${m.reference ? `<div class="text-[10px] text-slate-400 font-mono mt-0.5 truncate">Ref: ${escHtml(m.reference)}</div>` : ''}
                    </td>
                    <td class="py-3 px-4 text-right whitespace-nowrap">
                        <span class="font-black text-emerald-600 text-sm">+${m.quantity.toFixed(2)} <span class="text-[10px] text-slate-400 font-medium">${escHtml(m.unit||'')}</span></span>
                        <span class="ml-1 text-[10px] text-slate-400">${escHtml(m.time_formatted||'')}</span>
                    </td>
                </tr>`).join('');

            const outflowRows = (data.outflows || []).map(m => `
                <tr class="inv-outflow-row hover:bg-slate-50/80 transition-colors"
                    data-product-name="${escAttr((m.product_name||'').toLowerCase())}"
                    data-category="${escAttr((m.category_name||'other').toLowerCase())}"
                    data-category-display="${escAttr(m.category_name||'Other')}">
                    <td class="py-3 px-4">
                        <div class="font-bold text-slate-900 truncate">${escHtml(m.product_name||'-')}</div>
                        <div class="text-[10px] text-rose-600/80 font-bold uppercase tracking-wider mt-0.5">${escHtml(m.type_label||'')}</div>
                    </td>
                    <td class="py-3 px-4 text-right whitespace-nowrap">
                        <span class="font-black text-rose-600 text-sm">-${m.quantity.toFixed(2)} <span class="text-[10px] text-slate-400 font-medium">${escHtml(m.unit||'')}</span></span>
                        <span class="ml-1 text-[10px] text-slate-400">${escHtml(m.time_formatted||'')}</span>
                    </td>
                </tr>`).join('');

            const stockRows = (data.stock_levels || []).map(l => `
                <tr class="inv-stock-row hover:bg-slate-50/80 transition-colors"
                    data-product-name="${escAttr((l.product_name||'').toLowerCase())}"
                    data-category="${escAttr((l.category_name||'other').toLowerCase())}"
                    data-category-display="${escAttr(l.category_name||'Other')}">
                    <td class="py-3 px-4 w-12">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 border border-indigo-100">
                            <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        </div>
                    </td>
                    <td class="py-3 px-4">
                        <div class="font-bold text-slate-900 truncate">${escHtml(l.product_name||'-')}</div>
                        ${l.product_sku ? `<div class="text-[10px] text-slate-400 font-mono mt-0.5">Code: ${escHtml(l.product_sku)}</div>` : ''}
                    </td>
                    <td class="py-3 px-4 text-right whitespace-nowrap">
                        <span class="font-black text-slate-900 text-sm">${l.current_stock.toFixed(2)} <span class="text-[10px] text-slate-400 font-medium">kg</span></span>
                        ${l.latest_activity ? `<div class="text-[9px] text-slate-400 mt-0.5">${escHtml(l.latest_activity)}</div>` : ''}
                    </td>
                </tr>`).join('');

            const table = (rows, emptyMsg) => rows
                ? `<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full border-collapse text-left text-xs table-fixed">
                        <thead><tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="py-3 px-4 w-12"></th><th class="py-3 px-4 w-2/3">Product</th><th class="py-3 px-4 text-right w-1/3">Stock</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">${rows}</tbody>
                    </table></div>`
                : `<div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm"><p class="text-xs text-slate-500">${emptyMsg}</p></div>`;

            const inflowTable = inflowRows
                ? `<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full border-collapse text-left text-xs table-fixed">
                        <thead><tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="py-3 px-4 w-2/3">Product</th><th class="py-3 px-4 text-right w-1/3">Quantity</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">${inflowRows}</tbody>
                    </table></div>`
                : `<div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm"><p class="text-xs text-slate-500">No recent inflows logged.</p></div>`;

            const outflowTable = outflowRows
                ? `<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full border-collapse text-left text-xs table-fixed">
                        <thead><tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="py-3 px-4 w-2/3">Product</th><th class="py-3 px-4 text-right w-1/3">Quantity</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">${outflowRows}</tbody>
                    </table></div>`
                : `<div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm"><p class="text-xs text-slate-500">No recent outflows logged.</p></div>`;

            const stockTable = stockRows
                ? `<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full border-collapse text-left text-xs table-fixed">
                        <thead><tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="py-3 px-4 w-12"></th><th class="py-3 px-4 w-2/3">Product</th><th class="py-3 px-4 text-right w-1/3">Current Stock</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">${stockRows}</tbody>
                    </table></div>`
                : `<div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm"><p class="text-xs text-slate-500">No stock currently in inventory.</p></div>`;

            el.innerHTML = `
                <div id="inv-subtab-in"    class="inv-subtab-panel space-y-3">${inflowTable}</div>
                <div id="inv-subtab-out"   class="inv-subtab-panel hidden space-y-3">${outflowTable}</div>
                <div id="inv-subtab-stock" class="inv-subtab-panel hidden space-y-3">${stockTable}</div>`;

            populateInventoryCategories();
            filterInventory();
        }

        // ── Loadout ───────────────────────────────────────────────────────────
        function renderLoadout(data) {
            const el = tabContentEl.loadout;
            const orders = data.orders || [];

            if (orders.length === 0) {
                el.innerHTML = `<div class="rounded-3xl border border-slate-200 bg-white p-10 text-center shadow-sm">
                    <h3 class="text-sm font-bold text-slate-900">No Approved Orders</h3>
                    <p class="mt-1 text-xs text-slate-500">There are no approved orders to loadout for ${data.date}.</p>
                </div>`;
                return;
            }

            const colorMap = { 'Loaded': 'emerald', 'Partially Loaded': 'amber' };
            const cards = orders.map(order => {
                const color = colorMap[order.loading_status] || 'slate';
                const tag   = order.shop?.warehouse_tag || 'NO TAG';
                const code  = order.shop?.code || '';
                const phone = order.shop?.contact_phone || '';
                const contact = order.shop?.contact_name || '';
                return `<a href="${escAttr(order.loadout_url)}" class="block text-decoration-none">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 flex items-center justify-between gap-3 shadow-sm hover:border-indigo-200 transition-colors">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap mb-1">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-slate-500">${escHtml(tag)}</span>
                                ${code ? `<span class="text-[10px] font-mono font-bold text-slate-500">(${escHtml(code)})</span>` : ''}
                            </div>
                            <h4 class="truncate text-sm font-black text-slate-900">${escHtml(order.display_name)}</h4>
                            ${phone ? `<p class="text-[11px] font-bold text-emerald-700 mt-0.5">${escHtml(phone)}${contact ? ' ('+escHtml(contact)+')' : ''}</p>` : ''}
                            <p class="text-[10px] text-slate-400 font-medium mt-0.5">Order: <span class="font-mono">${escHtml(order.order_number)}</span></p>
                            <p class="text-[10px] text-slate-500 font-bold mt-1">Progress: ${order.loaded_items_count} / ${order.total_items_count} items loaded</p>
                        </div>
                        <div class="text-right shrink-0 flex flex-col items-end gap-2">
                            <span class="rounded-full bg-${color}-50 border border-${color}-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-${color}-700">${escHtml(order.loading_status)}</span>
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                        </div>
                    </div>
                </a>`;
            }).join('');

            el.innerHTML = `<div class="grid gap-3 sm:grid-cols-2">${cards}</div>`;
        }

        // ── Deliveries ────────────────────────────────────────────────────────
        function renderConfirmed(data) {
            const el = tabContentEl.confirmed;
            const orders = data.orders || [];

            if (orders.length === 0) {
                el.innerHTML = `<div class="rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <p class="text-xs text-slate-500">No shop orders for ${data.date}.</p>
                </div>`;
                return;
            }

            const loadColorMap = { 'Loaded': 'emerald', 'Partially Loaded': 'amber' };
            const fulfillColorMap = pct => pct === 100 ? 'emerald' : pct > 0 ? 'amber' : 'rose';
            const fulfillIcon     = pct => pct === 100 ? '✓' : pct > 0 ? '⚠' : '✗';

            // Build hidden item templates + order cards
            let templates = '';
            let cards = '';

            orders.forEach(order => {
                const lColor = loadColorMap[order.loading_status] || 'slate';
                const fColor = fulfillColorMap(order.fulfillment_percentage);
                const fIcon  = fulfillIcon(order.fulfillment_percentage);
                const tag    = order.shop?.warehouse_tag || 'NO TAG';

                // Hidden template for "Items List" sub-tab
                const itemRows = (order.all_items || []).map(item => {
                    const isLoaded = item.sorting_status === 'loaded';
                    return `<div class="rounded-xl border border-slate-200 bg-white p-3 flex items-center justify-between gap-3 shadow-xs">
                        <div class="min-w-0">
                            <h5 class="truncate text-xs font-black text-slate-900">${escHtml(item.product_name||'-')}</h5>
                            <p class="text-[10px] font-bold text-slate-500 mt-0.5">Required: <span class="text-indigo-600 font-black">${item.approved_qty.toFixed(2)}</span> ${escHtml(item.unit||'')}${isLoaded ? ' · Loaded: <span class="text-emerald-600 font-black">'+item.loaded_qty.toFixed(2)+'</span> '+escHtml(item.unit||'') : ''}</p>
                        </div>
                        <div class="shrink-0"><span class="rounded-full ${isLoaded ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'} px-2 py-0.5 text-[8px] font-black uppercase tracking-wider">${isLoaded ? 'Loaded ✓' : 'Pending'}</span></div>
                    </div>`;
                }).join('');

                // Action button
                let actionBtn = '';
                if (order.delivery_status === 'pending_delivery' || order.delivery_status === 'in_transit') {
                    if (order.loaded_items_count === order.total_items_count) {
                        actionBtn = `<form action="${escAttr(order.dispatch_url)}" method="POST">
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl py-3 text-xs font-black uppercase tracking-wider shadow-md transition-all border-none cursor-pointer">Move to Delivery</button>
                        </form>`;
                    } else {
                        actionBtn = `<button type="button" onclick="partialDispatch(${order.id},'${escAttr(order.order_number)}')" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl py-3 text-xs font-black uppercase tracking-wider shadow-md transition-all border-none cursor-pointer">Move to Delivery (Partial)</button>
                        <form id="partial-dispatch-form-${order.id}" action="${escAttr(order.dispatch_partial_url)}" method="POST" class="hidden"><input type="hidden" name="_token" value="${csrfToken}"></form>`;
                    }
                } else {
                    actionBtn = `<div class="text-center py-3 bg-slate-100 rounded-xl"><span class="inline-flex items-center rounded-full bg-indigo-50 border border-indigo-100 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-indigo-700">Status: Moved to Delivery</span></div>`;
                }

                templates += `<div id="order-items-template-${order.id}" class="hidden">
                    <div class="mb-4 rounded-2xl bg-slate-50 border border-slate-200 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-slate-500 mb-1">${escHtml(tag)}</span>
                                <h4 class="text-sm font-black text-slate-900">${escHtml(order.display_name)}</h4>
                                <p class="text-[10px] text-slate-400 font-medium">Order: <span class="font-mono">${escHtml(order.order_number)}</span></p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center gap-1 rounded-full bg-${fColor}-50 border border-${fColor}-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-${fColor}-700">${fIcon} ${order.fulfillment_percentage}% Stock Available</span>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-2 mb-6">${itemRows}</div>
                    ${actionBtn}
                </div>`;

                // Clickable order card
                cards += `<div class="rounded-2xl border border-slate-200 bg-white p-4 flex flex-col gap-3 shadow-sm hover:border-indigo-200 transition-colors cursor-pointer" onclick="selectOrder(${order.id})">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-slate-500 mb-1">${escHtml(tag)}</span>
                            <h4 class="truncate text-sm font-black text-slate-900">${escHtml(order.display_name)}</h4>
                            <p class="text-[10px] text-slate-400 font-medium">Order: <span class="font-mono">${escHtml(order.order_number)}</span></p>
                        </div>
                        <div class="text-right shrink-0 flex flex-col items-end gap-1.5">
                            <span class="rounded-full bg-${lColor}-50 border border-${lColor}-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-${lColor}-700">${escHtml(order.loading_status)}</span>
                            ${order.delivery_status === 'in_transit' ? '<span class="rounded-full bg-indigo-50 border border-indigo-100 px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-indigo-700">In Transit</span>' : ''}
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-2.5 border-t border-dashed border-slate-100">
                        <span class="text-[10px] font-bold text-slate-500">Items: ${order.loaded_items_count} / ${order.total_items_count} loaded</span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-${fColor}-50 border border-${fColor}-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-${fColor}-700">${fIcon} ${order.fulfillment_percentage}% Stock</span>
                    </div>
                </div>`;
            });

            el.innerHTML = `<div class="grid gap-3 sm:grid-cols-2">${cards}</div>${templates}`;
        }

        // ──────────────────────────────────────────────────────────────────────
        // Tab & sub-tab switch
        // ──────────────────────────────────────────────────────────────────────
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

            const headings = { pending: 'Receive Checklist', inventory: 'Inventory Status', loadout: 'Loadout Checklist', confirmed: 'Delivery Status' };
            const headingEl = document.getElementById('wr-page-heading');
            if (headingEl && headings[name]) headingEl.textContent = headings[name];

            // Lazy-load tab data
            fetchTabContent(name);
        }

        function switchInvSubTab(subName) {
            document.querySelectorAll('.inv-subtab-panel').forEach(p => p.classList.add('hidden'));
            document.querySelectorAll('[id^="subtab-"]').forEach(btn => {
                btn.classList.remove('bg-slate-950', 'text-white', 'shadow-sm');
                btn.classList.add('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200');
            });
            document.getElementById('inv-subtab-' + subName)?.classList.remove('hidden');
            const activeBtn = document.getElementById('subtab-' + subName);
            if (activeBtn) { activeBtn.classList.remove('bg-slate-100','text-slate-600','hover:bg-slate-200'); activeBtn.classList.add('bg-slate-950','text-white','shadow-sm'); }
        }

        function switchDeliverySubTab(subName) {
            document.querySelectorAll('.del-subtab-panel').forEach(p => p.classList.add('hidden'));
            document.querySelectorAll('[id^="del-subtab-btn-"]').forEach(btn => {
                btn.classList.remove('bg-slate-950', 'text-white', 'shadow-sm');
                btn.classList.add('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200');
            });
            document.getElementById('del-subtab-' + subName)?.classList.remove('hidden');
            const activeBtn = document.getElementById('del-subtab-btn-' + subName);
            if (activeBtn) { activeBtn.classList.remove('bg-slate-100','text-slate-600','hover:bg-slate-200'); activeBtn.classList.add('bg-slate-950','text-white','shadow-sm'); }
        }

        // ──────────────────────────────────────────────────────────────────────
        // Interaction helpers
        // ──────────────────────────────────────────────────────────────────────
        function selectOrder(orderId) {
            const template = document.getElementById('order-items-template-' + orderId);
            const container = document.getElementById('selected-order-items-container');
            if (template && container) container.innerHTML = template.innerHTML;
            switchDeliverySubTab('items');
        }

        function partialDispatch(orderId, orderNum) {
            window.showAppConfirm({
                title: 'Partial Delivery Confirmation',
                message: 'Are you sure you want to dispatch Order ' + orderNum + ' as a partial delivery?',
                confirmLabel: 'Yes, Dispatch Partial', cancelLabel: 'Cancel', tone: 'danger',
                onConfirm: () => { const f = document.getElementById('partial-dispatch-form-' + orderId); if (f) f.submit(); }
            });
        }

        function populateInventoryCategories() {
            const categories = new Set();
            document.querySelectorAll('[data-category-display]').forEach(card => {
                const c = card.getAttribute('data-category-display'); if (c) categories.add(c);
            });
            const sel = document.getElementById('inventory-category-filter');
            if (sel) {
                sel.innerHTML = '<option value="">All Categories</option>';
                Array.from(categories).sort().forEach(cat => {
                    const opt = document.createElement('option'); opt.value = cat.toLowerCase(); opt.textContent = cat; sel.appendChild(opt);
                });
            }
        }

        function filterInventory() {
            const q   = document.getElementById('inventory-search')?.value.toLowerCase().trim() || '';
            const cat = document.getElementById('inventory-category-filter')?.value || '';
            ['.inv-inflow-row', '.inv-outflow-row', '.inv-stock-row'].forEach(sel => {
                document.querySelectorAll(sel).forEach(row => {
                    const name     = row.getAttribute('data-product-name') || '';
                    const category = row.getAttribute('data-category') || '';
                    const show = (q === '' || name.includes(q)) && (cat === '' || category === cat);
                    row.classList.toggle('hidden', !show);
                });
            });
        }

        // ──────────────────────────────────────────────────────────────────────
        // HTML escape helpers
        // ──────────────────────────────────────────────────────────────────────
        function escHtml(str) {
            return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
        }
        function escAttr(str) { return escHtml(str); }
        function bindConfirmForms(container) {
            (container || document).querySelectorAll('.warehouse-confirm-form').forEach((form) => {
                if (form.dataset.bound === 'true') return;
                form.dataset.bound = 'true';
                form.addEventListener('submit', (event) => {
                    if (form.dataset.appConfirmBypass === 'true') {
                        form.dataset.appConfirmBypass = 'false';
                        return;
                    }

                    event.preventDefault();

                    if (typeof window.showAppConfirm === 'function') {
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
                    } else {
                        form.dataset.appConfirmBypass = 'true';
                        HTMLFormElement.prototype.submit.call(form);
                    }
                });
            });
        }

        // CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        // ──────────────────────────────────────────────────────────────────────
        // Init
        // ──────────────────────────────────────────────────────────────────────
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab');
        if (activeTab && ['pending', 'inventory', 'loadout', 'confirmed'].includes(activeTab)) {
            switchTab(activeTab);
        } else {
            // Default: load pending tab
            fetchTabContent('pending');
        }
    </script>
    @endpush
</x-layouts.app>
