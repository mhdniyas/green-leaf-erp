<x-layouts.purchaser-v2 title="Daily Demand" :date="$date" :grade="$grade">
    <div class="mx-auto max-w-5xl space-y-3.5 sm:space-y-5">
        <!-- Header & Quick Totals Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-sm">
            <div>
                <span class="text-[10px] sm:text-xs font-extrabold uppercase tracking-wider text-emerald-700">Daily Demand</span>
                <h2 class="text-base sm:text-xl font-black text-slate-900">Products for {{ $date }}</h2>
                <p class="text-[11px] text-slate-500">First 20 items loaded. Search and pagination run on-demand server-side.</p>
            </div>
            <div class="flex items-center gap-2 self-start sm:self-auto">
                <div class="rounded-xl border border-slate-200/80 bg-slate-50 px-3 py-1.5 text-center">
                    <span class="text-[9px] uppercase font-bold text-slate-400">Total Products</span>
                    <p id="metricTotalProducts" class="text-sm font-black text-slate-900">{{ number_format($metrics['total_intended_products']) }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50 px-3 py-1.5 text-center">
                    <span class="text-[9px] uppercase font-bold text-emerald-700">Total Needed</span>
                    <p id="metricTotalApproved" class="text-sm font-black text-emerald-700">{{ number_format($metrics['total_approved_qty'], 1) }}</p>
                </div>
            </div>
        </div>

        <!-- Sticky Search & Filter Bar (Mobile-first PWA sticky) -->
        <div class="sticky top-14 md:top-16 z-20 space-y-2.5 rounded-2xl border border-slate-200/80 bg-white/95 p-3 backdrop-blur-md shadow-sm">
            <!-- Search Input -->
            <div class="relative w-full">
                <input type="text"
                       id="searchInput"
                       placeholder="Search product name or SKU..."
                       value="{{ $search }}"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-2.5 pl-10 text-xs text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                <svg class="absolute left-3.5 top-3 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <div id="searchSpinner" class="hidden absolute right-3.5 top-3">
                    <div class="h-4 w-4 animate-spin rounded-full border-2 border-emerald-600 border-t-transparent"></div>
                </div>
            </div>

            <!-- Filter Chips -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 scrollbar-none">
                <button type="button" data-status="all"
                        class="status-chip shrink-0 rounded-xl px-3 py-1.5 text-xs font-bold transition active:scale-95 {{ $statusFilter === 'all' ? 'bg-slate-900 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    All Items
                </button>
                <button type="button" data-status="pending"
                        class="status-chip shrink-0 rounded-xl px-3 py-1.5 text-xs font-bold transition active:scale-95 {{ $statusFilter === 'pending' ? 'bg-amber-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    Pending Only
                </button>
                <button type="button" data-status="fulfilled"
                        class="status-chip shrink-0 rounded-xl px-3 py-1.5 text-xs font-bold transition active:scale-95 {{ $statusFilter === 'fulfilled' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    Fulfilled Only
                </button>
            </div>
        </div>

        <!-- Products List / Cards Container -->
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
            <!-- Desktop Table View (Hidden on mobile) -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="border-b border-slate-100 bg-slate-50 text-[11px] uppercase tracking-wider text-slate-400 font-bold">
                        <tr>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3 text-right">Intended</th>
                            <th class="px-4 py-3 text-right">Purchased</th>
                            <th class="px-4 py-3 text-right">Remaining</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="desktopTableBody" class="divide-y divide-slate-100 font-medium">
                        @forelse ($initialItems as $item)
                            <tr class="product-row transition hover:bg-slate-50/70" data-id="{{ $item['product_id'] }}">
                                <td class="px-5 py-3.5">
                                    <div class="font-extrabold text-slate-900">{{ $item['name'] }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">SKU: {{ $item['sku'] }} &bull; Unit: {{ strtoupper($item['unit']) }}</div>
                                </td>
                                <td class="px-4 py-3.5 text-slate-500">
                                    {{ $item['category_name'] }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono font-bold text-slate-900">
                                    {{ number_format($item['intended_qty'], 1) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono font-bold text-emerald-600">
                                    {{ number_format($item['purchased_qty'], 1) }}
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono font-black {{ $item['remaining_qty'] > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                                    {{ number_format($item['remaining_qty'], 1) }}
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    @if ($item['is_fulfilled'])
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200">
                                            Fulfilled
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-200">
                                            Pending
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button"
                                                class="btn-view-detail rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 active:scale-95"
                                                data-id="{{ $item['product_id'] }}">
                                            Demand
                                        </button>
                                        <a href="{{ $item['buy_url'] }}"
                                           class="rounded-xl bg-emerald-600 px-3.5 py-1.5 text-[11px] font-black text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700 active:scale-95">
                                            Buy
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-400">
                                    No intended products found for this selection.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card Feed (PWA Native Feel) -->
            <div id="mobileCardContainer" class="md:hidden divide-y divide-slate-100">
                @forelse ($initialItems as $item)
                    <div class="product-card p-3.5 transition active:bg-slate-50/80" data-id="{{ $item['product_id'] }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="text-xs font-black text-slate-900 truncate">{{ $item['name'] }}</h3>
                                <p class="text-[10px] text-slate-400 font-mono">{{ $item['category_name'] }} &bull; SKU: {{ $item['sku'] }}</p>
                            </div>
                            <div>
                                @if ($item['is_fulfilled'])
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-bold text-emerald-700 border border-emerald-200">
                                        Fulfilled
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-bold text-amber-700 border border-amber-200">
                                        Pending
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Quantity Row -->
                        <div class="mt-2.5 grid grid-cols-3 gap-1.5 rounded-xl bg-slate-50 border border-slate-100 p-2 text-center text-[10px]">
                            <div>
                                <span class="text-slate-400 font-bold">Needed</span>
                                <p class="font-mono font-black text-slate-800 text-xs">{{ number_format($item['intended_qty'], 1) }}</p>
                            </div>
                            <div>
                                <span class="text-slate-400 font-bold">Bought</span>
                                <p class="font-mono font-black text-emerald-600 text-xs">{{ number_format($item['purchased_qty'], 1) }}</p>
                            </div>
                            <div>
                                <span class="text-slate-400 font-bold">Remaining</span>
                                <p class="font-mono font-black text-xs {{ $item['remaining_qty'] > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                                    {{ number_format($item['remaining_qty'], 1) }}
                                </p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mt-2.5 flex items-center justify-between gap-2">
                            <button type="button"
                                    class="btn-view-detail flex-1 rounded-xl border border-slate-200 bg-white py-2 text-[11px] font-bold text-slate-700 shadow-sm transition active:scale-95 text-center"
                                    data-id="{{ $item['product_id'] }}">
                                View Demand
                            </button>
                            <a href="{{ $item['buy_url'] }}"
                               class="flex-1 rounded-xl bg-emerald-600 py-2 text-[11px] font-black text-white shadow-sm shadow-emerald-600/20 text-center transition active:scale-95">
                                Buy Product
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-xs text-slate-400">
                        No intended products found for this selection.
                    </div>
                @endforelse
            </div>

            <!-- Load More Pagination Bar -->
            <div id="paginationContainer" class="flex items-center justify-between border-t border-slate-100 bg-slate-50/60 px-4 py-3 text-xs text-slate-500">
                <span id="pageInfo">Showing <span id="currentLoadedCount">{{ count($initialItems) }}</span> of {{ $pagination['total_count'] }} products</span>
                <button type="button" id="btnLoadMore"
                        class="{{ $pagination['has_more'] ? '' : 'hidden' }} rounded-xl bg-white border border-slate-200 px-4 py-2 font-bold text-slate-800 shadow-sm transition active:scale-95 hover:bg-slate-50">
                    Load More
                </button>
            </div>
        </div>
    </div>

    <!-- On-Demand Product Demand Bottom Sheet / Modal (PWA Native Sheet) -->
    <div id="detailModal" class="hidden fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/40 backdrop-blur-sm transition-opacity">
        <div class="relative w-full max-w-xl max-h-[88vh] flex flex-col rounded-t-3xl sm:rounded-3xl border border-slate-200 bg-white p-5 shadow-2xl overflow-hidden animate-slide-up">
            <!-- iOS Grab Handle on mobile -->
            <div class="sm:hidden w-12 h-1.5 bg-slate-300 rounded-full mx-auto mb-3 shrink-0"></div>

            <!-- Close Button -->
            <button type="button" id="btnCloseModal" class="absolute right-4 top-4 rounded-full p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 active:scale-95">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <!-- Loading Spinner State -->
            <div id="modalLoading" class="flex flex-col items-center justify-center py-12">
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-emerald-600 border-t-transparent"></div>
                <p class="mt-3 text-xs font-semibold text-slate-500">Fetching live shop demand...</p>
            </div>

            <!-- Sheet Content Body -->
            <div id="modalBody" class="hidden flex-1 overflow-y-auto space-y-4 pr-1">
                <div>
                    <span id="modalCategory" class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">Category</span>
                    <h3 id="modalProductName" class="text-base sm:text-lg font-black text-slate-900">Product Name</h3>
                    <p id="modalSkuUnit" class="text-[11px] text-slate-400 font-mono">SKU &bull; Unit</p>
                </div>

                <!-- KPI Mini Cards -->
                <div class="grid grid-cols-3 gap-2 rounded-2xl bg-slate-50 border border-slate-100 p-2.5 text-center">
                    <div>
                        <span class="text-[9px] uppercase font-bold text-slate-400">Intended</span>
                        <p id="modalIntendedQty" class="text-sm font-black text-slate-900">0.0</p>
                    </div>
                    <div>
                        <span class="text-[9px] uppercase font-bold text-emerald-700">Purchased</span>
                        <p id="modalPurchasedQty" class="text-sm font-black text-emerald-600">0.0</p>
                    </div>
                    <div>
                        <span class="text-[9px] uppercase font-bold text-amber-700">Remaining</span>
                        <p id="modalRemainingQty" class="text-sm font-black text-amber-600">0.0</p>
                    </div>
                </div>

                <!-- Shop Demand Breakdown -->
                <div>
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-500 mb-2">Shop Order Demand</h4>
                    <div class="max-h-44 overflow-y-auto rounded-xl border border-slate-100 bg-slate-50 divide-y divide-slate-200/60 text-xs">
                        <div id="modalShopsList" class="p-1.5 space-y-1"></div>
                    </div>
                </div>

                <!-- Purchaser Carts Breakdown -->
                <div>
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-500 mb-2">Existing Purchases Today</h4>
                    <div class="max-h-36 overflow-y-auto rounded-xl border border-slate-100 bg-slate-50 divide-y divide-slate-200/60 text-xs">
                        <div id="modalCartsList" class="p-1.5 space-y-1"></div>
                    </div>
                </div>

                <!-- Bottom CTA -->
                <div class="pt-2">
                    <a id="modalBuyLink" href="#" class="block w-full text-center rounded-xl bg-emerald-600 py-3 text-xs font-black text-white shadow-sm shadow-emerald-600/20 active:scale-95 transition">
                        Proceed to Buy in V2
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Vanilla JS Touch Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const date = '{{ $date }}';
            const grade = '{{ $grade }}';
            let currentPage = 1;
            let currentStatus = '{{ $statusFilter }}';
            let activeAbortController = null;
            let debounceTimer = null;

            const searchInput = document.getElementById('searchInput');
            const searchSpinner = document.getElementById('searchSpinner');
            const desktopTableBody = document.getElementById('desktopTableBody');
            const mobileCardContainer = document.getElementById('mobileCardContainer');
            const btnLoadMore = document.getElementById('btnLoadMore');
            const pageInfo = document.getElementById('pageInfo');
            const currentLoadedCount = document.getElementById('currentLoadedCount');
            const statusChips = document.querySelectorAll('.status-chip');

            // Detail modal elements
            const detailModal = document.getElementById('detailModal');
            const btnCloseModal = document.getElementById('btnCloseModal');
            const modalLoading = document.getElementById('modalLoading');
            const modalBody = document.getElementById('modalBody');
            const modalProductName = document.getElementById('modalProductName');
            const modalSkuUnit = document.getElementById('modalSkuUnit');
            const modalCategory = document.getElementById('modalCategory');
            const modalIntendedQty = document.getElementById('modalIntendedQty');
            const modalPurchasedQty = document.getElementById('modalPurchasedQty');
            const modalRemainingQty = document.getElementById('modalRemainingQty');
            const modalShopsList = document.getElementById('modalShopsList');
            const modalCartsList = document.getElementById('modalCartsList');
            const modalBuyLink = document.getElementById('modalBuyLink');

            // 1. Debounced Search
            searchInput.addEventListener('input', (e) => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    currentPage = 1;
                    fetchProducts(true);
                }, 300);
            });

            // 2. Status Chips
            statusChips.forEach(chip => {
                chip.addEventListener('click', () => {
                    statusChips.forEach(c => {
                        c.className = 'status-chip shrink-0 rounded-xl px-3 py-1.5 text-xs font-bold transition active:scale-95 bg-slate-100 text-slate-600 hover:bg-slate-200';
                    });
                    const status = chip.getAttribute('data-status');
                    chip.className = status === 'pending'
                        ? 'status-chip shrink-0 rounded-xl px-3 py-1.5 text-xs font-bold transition active:scale-95 bg-amber-600 text-white shadow-sm'
                        : (status === 'fulfilled'
                            ? 'status-chip shrink-0 rounded-xl px-3 py-1.5 text-xs font-bold transition active:scale-95 bg-emerald-600 text-white shadow-sm'
                            : 'status-chip shrink-0 rounded-xl px-3 py-1.5 text-xs font-bold transition active:scale-95 bg-slate-900 text-white shadow-sm');
                    currentStatus = status;
                    currentPage = 1;
                    fetchProducts(true);
                });
            });

            // 3. Load More
            btnLoadMore.addEventListener('click', () => {
                currentPage++;
                fetchProducts(false);
            });

            // 4. Fetch Products
            async function fetchProducts(resetList = false) {
                if (activeAbortController) {
                    activeAbortController.abort();
                }
                activeAbortController = new AbortController();

                searchSpinner.classList.remove('hidden');

                const query = searchInput.value.trim();
                const params = new URLSearchParams({
                    date: date,
                    grade: grade,
                    q: query,
                    status: currentStatus,
                    page: currentPage,
                    per_page: 20
                });

                try {
                    const res = await fetch(`/purchaser-v2/daily/products?${params.toString()}`, {
                        signal: activeAbortController.signal,
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const json = await res.json();
                    searchSpinner.classList.add('hidden');

                    if (json.status === 'success') {
                        renderItems(json.data, resetList);
                        updatePagination(json.pagination);
                    }
                } catch (err) {
                    if (err.name !== 'AbortError') {
                        searchSpinner.classList.add('hidden');
                        console.error('Fetch error:', err);
                    }
                }
            }

            // 5. Render Rows & Mobile Cards
            function renderItems(items, resetList) {
                if (resetList) {
                    desktopTableBody.innerHTML = '';
                    mobileCardContainer.innerHTML = '';
                }

                if (items.length === 0 && resetList) {
                    const emptyMsg = `
                        <tr><td colspan="7" class="px-5 py-10 text-center text-xs text-slate-400">
                            No intended products found for the selected criteria.
                        </td></tr>`;
                    desktopTableBody.innerHTML = emptyMsg;
                    mobileCardContainer.innerHTML = `<div class="p-8 text-center text-xs text-slate-400">No products found.</div>`;
                    return;
                }

                items.forEach(item => {
                    const statusBadge = item.is_fulfilled
                        ? `<span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200">Fulfilled</span>`
                        : `<span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-200">Pending</span>`;

                    const remainingClass = item.remaining_qty > 0 ? 'text-amber-600' : 'text-slate-400';

                    // 1. Desktop Table Row
                    const tr = document.createElement('tr');
                    tr.className = 'product-row transition hover:bg-slate-50/70';
                    tr.setAttribute('data-id', item.product_id);
                    tr.innerHTML = `
                        <td class="px-5 py-3.5">
                            <div class="font-extrabold text-slate-900">${escapeHtml(item.name)}</div>
                            <div class="text-[10px] text-slate-400 font-mono">SKU: ${escapeHtml(item.sku)} &bull; Unit: ${escapeHtml(item.unit.toUpperCase())}</div>
                        </td>
                        <td class="px-4 py-3.5 text-slate-500">${escapeHtml(item.category_name)}</td>
                        <td class="px-4 py-3.5 text-right font-mono font-bold text-slate-900">${item.intended_qty.toFixed(1)}</td>
                        <td class="px-4 py-3.5 text-right font-mono font-bold text-emerald-600">${item.purchased_qty.toFixed(1)}</td>
                        <td class="px-4 py-3.5 text-right font-mono font-black ${remainingClass}">${item.remaining_qty.toFixed(1)}</td>
                        <td class="px-4 py-3.5 text-center">${statusBadge}</td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button"
                                        class="btn-view-detail rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 active:scale-95"
                                        data-id="${item.product_id}">
                                    Demand
                                </button>
                                <a href="${item.buy_url}"
                                   class="rounded-xl bg-emerald-600 px-3.5 py-1.5 text-[11px] font-black text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700 active:scale-95">
                                    Buy
                                </a>
                            </div>
                        </td>
                    `;
                    desktopTableBody.appendChild(tr);

                    // 2. Mobile Card Feed
                    const card = document.createElement('div');
                    card.className = 'product-card p-3.5 transition active:bg-slate-50/80';
                    card.setAttribute('data-id', item.product_id);
                    card.innerHTML = `
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="text-xs font-black text-slate-900 truncate">${escapeHtml(item.name)}</h3>
                                <p class="text-[10px] text-slate-400 font-mono">${escapeHtml(item.category_name)} &bull; SKU: ${escapeHtml(item.sku)}</p>
                            </div>
                            <div>${statusBadge}</div>
                        </div>
                        <div class="mt-2.5 grid grid-cols-3 gap-1.5 rounded-xl bg-slate-50 border border-slate-100 p-2 text-center text-[10px]">
                            <div><span class="text-slate-400 font-bold">Needed</span><p class="font-mono font-black text-slate-800 text-xs">${item.intended_qty.toFixed(1)}</p></div>
                            <div><span class="text-slate-400 font-bold">Bought</span><p class="font-mono font-black text-emerald-600 text-xs">${item.purchased_qty.toFixed(1)}</p></div>
                            <div><span class="text-slate-400 font-bold">Remaining</span><p class="font-mono font-black text-xs ${remainingClass}">${item.remaining_qty.toFixed(1)}</p></div>
                        </div>
                        <div class="mt-2.5 flex items-center justify-between gap-2">
                            <button type="button" class="btn-view-detail flex-1 rounded-xl border border-slate-200 bg-white py-2 text-[11px] font-bold text-slate-700 shadow-sm transition active:scale-95 text-center" data-id="${item.product_id}">
                                View Demand
                            </button>
                            <a href="${item.buy_url}" class="flex-1 rounded-xl bg-emerald-600 py-2 text-[11px] font-black text-white shadow-sm shadow-emerald-600/20 text-center transition active:scale-95">
                                Buy Product
                            </a>
                        </div>
                    `;
                    mobileCardContainer.appendChild(card);
                });

                attachDetailEvents();
            }

            function updatePagination(pagination) {
                const totalLoaded = desktopTableBody.querySelectorAll('.product-row').length;
                currentLoadedCount.textContent = totalLoaded;
                pageInfo.innerHTML = `Showing <span id="currentLoadedCount">${totalLoaded}</span> of ${pagination.total_count} products`;

                if (pagination.has_more) {
                    btnLoadMore.classList.remove('hidden');
                } else {
                    btnLoadMore.classList.add('hidden');
                }
            }

            // 6. View Demand Detail Handler
            function attachDetailEvents() {
                document.querySelectorAll('.btn-view-detail').forEach(btn => {
                    btn.onclick = async () => {
                        const productId = btn.getAttribute('data-id');
                        openDetailModal(productId);
                    };
                });
            }

            async function openDetailModal(productId) {
                detailModal.classList.remove('hidden');
                modalLoading.classList.remove('hidden');
                modalBody.classList.add('hidden');

                try {
                    const res = await fetch(`/purchaser-v2/daily/products/${productId}/detail?date=${date}&grade=${grade}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const json = await res.json();
                    modalLoading.classList.add('hidden');
                    modalBody.classList.remove('hidden');

                    if (json.status === 'success') {
                        const d = json.data;
                        modalCategory.textContent = d.product.category;
                        modalProductName.textContent = d.product.name;
                        modalSkuUnit.textContent = `SKU: ${d.product.sku} • Unit: ${d.product.unit.toUpperCase()}`;
                        modalIntendedQty.textContent = d.summary.intended_qty.toFixed(1);
                        modalPurchasedQty.textContent = d.summary.purchased_qty.toFixed(1);
                        modalRemainingQty.textContent = d.summary.remaining_qty.toFixed(1);
                        modalBuyLink.href = `/purchaser-v2/buy?product_id=${d.product.id}&date=${date}&grade=${grade}`;

                        // Render shops
                        if (d.shops.length === 0) {
                            modalShopsList.innerHTML = `<div class="p-2 text-slate-400 text-center text-[11px]">No individual shop demands found.</div>`;
                        } else {
                            modalShopsList.innerHTML = d.shops.map(s => `
                                <div class="flex items-center justify-between p-2 rounded-lg hover:bg-white transition">
                                    <div>
                                        <span class="font-bold text-slate-900">${escapeHtml(s.shop_name)}</span>
                                        <span class="text-[10px] text-slate-400 ml-1 font-mono">(${escapeHtml(s.order_number)})</span>
                                    </div>
                                    <div class="font-mono font-black text-emerald-700">
                                        ${s.approved_qty.toFixed(1)} ${escapeHtml(s.unit)}
                                    </div>
                                </div>
                            `).join('');
                        }

                        // Render carts
                        if (d.carts.length === 0) {
                            modalCartsList.innerHTML = `<div class="p-2 text-slate-400 text-center text-[11px]">No carts created for this item today.</div>`;
                        } else {
                            modalCartsList.innerHTML = d.carts.map(c => `
                                <div class="flex items-center justify-between p-2 rounded-lg hover:bg-white transition">
                                    <div>
                                        <span class="font-bold text-slate-900">${escapeHtml(c.supplier_name)}</span>
                                        <span class="text-[10px] text-slate-400 ml-1">Cart: ${escapeHtml(c.cart_number)} (${escapeHtml(c.cart_status)})</span>
                                    </div>
                                    <div class="font-mono text-slate-700">
                                        ${c.quantity.toFixed(1)} &bull; ₹${c.unit_price.toFixed(2)}
                                    </div>
                                </div>
                            `).join('');
                        }
                    }
                } catch (err) {
                    modalLoading.innerHTML = `<p class="text-xs font-bold text-rose-600">Failed to load details.</p>`;
                }
            }

            btnCloseModal.addEventListener('click', () => {
                detailModal.classList.add('hidden');
            });

            detailModal.addEventListener('click', (e) => {
                if (e.target === detailModal) {
                    detailModal.classList.add('hidden');
                }
            });

            function escapeHtml(str) {
                const div = document.createElement('div');
                div.textContent = str || '';
                return div.innerHTML;
            }

            attachDetailEvents();
        });
    </script>
</x-layouts.purchaser-v2>
