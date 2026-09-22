<x-layouts.app title="Bulk Purchase">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 pb-24 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4 lg:pb-6">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="overflow-hidden rounded-2xl bg-slate-955 text-white shadow-[0_16px_36px_rgba(15,23,42,0.18)] lg:rounded-[2rem]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(45,212,191,0.28),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#134e4a_100%)] px-4 py-4 sm:px-5 lg:px-4 lg:py-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-teal-200 sm:text-[11px] sm:tracking-[0.22em]">Grade {{ $purchaseGrade }} Purchaser Flow</p>
                        <h1 class="mt-1 text-xl font-black tracking-tight sm:mt-2 sm:text-2xl">{{ $purchaseGrade === 'B' ? 'B Grade ' : '' }}Bulk Purchase (Step 1)</h1>
                        <p class="mt-2 max-w-2xl text-sm font-medium text-slate-200">Select multiple products you want to buy, then proceed to enter quantities and prices.</p>
                    </div>
                    <div class="shrink-0">
                        <span class="rounded-xl bg-white/10 px-3.5 py-2 text-sm font-bold text-white block lg:rounded-2xl">
                            {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        {{-- Filter bar --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
            <div class="flex flex-col gap-3">
                <div class="relative custom-select-container w-full">
                    <button type="button" class="custom-select-trigger flex h-11 w-full items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 text-left text-xs font-black text-slate-700 focus:border-teal-500 focus:bg-white focus:outline-none lg:rounded-2xl lg:px-5">
                        <span class="custom-select-label truncate">Filter: All</span>
                        <svg class="h-3.5 w-3.5 shrink-0 text-slate-500 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <input type="hidden" id="filter-select" value="All">
                    <div class="custom-select-options hidden absolute right-0 left-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg lg:rounded-2xl">
                        @foreach ($quickFilters as $filter)
                            <button type="button" data-value="{{ $filter }}" class="custom-select-option flex w-full items-center justify-between px-4 py-2 text-left text-xs font-black text-slate-700 hover:bg-slate-100">
                                <span>{{ $filter }}</span>
                                <span class="checkmark {{ $filter === 'All' ? '' : 'hidden' }} text-teal-600">✓</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Horizontal Category Pills --}}
                <div class="-mx-1 flex snap-x snap-mandatory gap-1.5 overflow-x-auto px-1 pb-1">
                    <button type="button" data-category-pill="All" onclick="selectCategoryPill('All', this)" class="category-pill snap-start shrink-0 rounded-full bg-teal-600 px-3.5 py-1.5 text-[11px] font-black uppercase tracking-[0.16em] text-white shadow-xs transition">
                        All
                    </button>
                    <button type="button" data-category-pill="Frequent" onclick="selectCategoryPill('Frequent', this)" class="category-pill snap-start shrink-0 rounded-full bg-slate-100 px-3.5 py-1.5 text-[11px] font-black uppercase tracking-[0.16em] text-slate-600 transition hover:bg-slate-200">
                        Frequent
                    </button>
                    @foreach ($quickFilters as $filter)
                        @if (!in_array($filter, ['All', 'Frequent']))
                            <button type="button" data-category-pill="{{ $filter }}" onclick="selectCategoryPill('{{ $filter }}', this)" class="category-pill snap-start shrink-0 rounded-full bg-slate-100 px-3.5 py-1.5 text-[11px] font-black uppercase tracking-[0.16em] text-slate-600 transition hover:bg-slate-200">
                                {{ $filter }}
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        <form action="{{ route('purchaser.bulk-buy.details') }}" method="GET" id="bulk-buy-form" class="pb-24">
            <input type="hidden" name="date" value="{{ $date }}">
            <input type="hidden" name="purchase_grade" value="{{ $purchaseGrade }}">
            
            {{-- Professional Tabs switcher --}}
            <div class="mb-3 flex rounded-xl bg-slate-100 p-1 lg:rounded-2xl">
                <button type="button" onclick="switchTab('pending')" id="tab-btn-pending" class="flex-1 rounded-lg py-2.5 text-center text-xs font-black uppercase tracking-wider transition-all bg-white text-slate-900 shadow-xs focus:outline-none">
                    Pending ({{ $pendingSummary->count() }})
                </button>
                <button type="button" onclick="switchTab('fulfilled')" id="tab-btn-fulfilled" class="flex-1 rounded-lg py-2.5 text-center text-xs font-black uppercase tracking-wider transition-all text-slate-600 hover:bg-white/50 focus:outline-none">
                    Fulfilled ({{ $fulfilledCount }})
                </button>
                <button type="button" onclick="switchTab('addons')" id="tab-btn-addons" class="flex-1 rounded-lg py-2.5 text-center text-xs font-black uppercase tracking-wider transition-all text-slate-600 hover:bg-white/50 focus:outline-none">
                    Add-ons
                </button>
            </div>

            {{-- Search product input --}}
            <div class="mb-3 relative">
                <input id="search-input" type="search" placeholder="Search product..." class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-sm focus:border-teal-500 focus:bg-white focus:outline-none lg:rounded-2xl lg:px-4 lg:py-3">
            </div>

            <div class="space-y-3" id="product-list">
                {{-- Pending Demand Items --}}
                <div id="pending-container" class="space-y-3">
                    @foreach ($pendingSummary as $summary)
                        <label class="product-item block relative min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:bg-slate-50 cursor-pointer"
                               data-tab="pending"
                               data-name="{{ $summary['product_name'] }}"
                               data-sku="{{ $summary['sku'] }}"
                               data-category="{{ $summary['category_name'] }}"
                               data-frequent="{{ $summary['is_frequent'] ? 'true' : 'false' }}">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center shrink-0">
                                    <input type="checkbox" name="product_ids[]" value="{{ $summary['product_id'] }}" class="product-checkbox h-5 w-5 rounded border-slate-300 text-teal-600 focus:ring-teal-500 cursor-pointer">
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="min-w-0 break-words font-black text-slate-900 text-sm">{{ $summary['product_name'] }}</h3>
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-500">{{ $summary['category_name'] ?: 'Other' }}</span>
                                        @if (! empty($summary['is_addon']))
                                            <span class="rounded-full bg-teal-50 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-teal-700 font-bold border border-teal-200">ADD-ON</span>
                                        @endif
                                        @if ($summary['draft_qty'] > 0)
                                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-black text-amber-700">In Cart: {{ number_format($summary['draft_qty'], 1) }} {{ $summary['unit'] }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-2 flex items-center gap-4 text-xs font-semibold text-slate-500">
                                        <span>Need: {{ number_format($summary['total_approved_qty'], 1) }} {{ $summary['unit'] }}</span>
                                        <span>Bought: {{ number_format($summary['bought_qty'], 1) }}</span>
                                        <span class="text-teal-600 font-bold">Left: {{ number_format($summary['remaining_qty'], 1) }}</span>
                                    </div>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>

                {{-- Fulfilled Demand Container (Lazy Loaded) --}}
                <div id="fulfilled-container" class="space-y-3 hidden"></div>

                {{-- Add-ons Product Picker Container (Lazy Loaded) --}}
                <div id="addons-container" class="space-y-3 hidden">
                    <div id="addons-list" class="space-y-3"></div>
                    
                    {{-- Skeleton / Loading indicator --}}
                    <div id="addons-loading" class="hidden py-8 text-center">
                        <div class="inline-flex items-center gap-2 text-sm font-bold text-slate-500">
                            <svg class="h-5 w-5 animate-spin text-teal-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>Loading products...</span>
                        </div>
                    </div>

                    {{-- Load More button --}}
                    <div id="addons-load-more-wrap" class="hidden pt-3 text-center">
                        <button type="button" id="addons-load-more-btn" onclick="loadMoreAddons()" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-6 text-xs font-black uppercase tracking-wider text-slate-700 shadow-xs transition hover:bg-slate-50 hover:border-slate-300">
                            <span>Load More Products</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="no-results-msg" class="hidden rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-sm font-bold text-slate-500 lg:rounded-[2rem] lg:px-4 lg:py-12">
                No products match the selected filters.
            </div>

            {{-- Sticky/Fixed bottom bar --}}
            <div id="bulk-buy-bottom-wrapper" class="hidden lg:block fixed inset-x-3 bottom-[max(env(safe-area-inset-bottom),0.75rem)] z-50 bg-white/95 backdrop-blur-md border border-slate-200 p-4 shadow-[0_8px_30px_rgba(0,0,0,0.12)] rounded-2xl lg:sticky lg:inset-x-auto lg:bottom-4 lg:z-40 lg:mt-6 lg:shadow-[0_8px_30px_rgba(0,0,0,0.08)] transition-all duration-200">
                {{-- Standard Bulk Buy Selection Bar (for Pending & Fulfilled) --}}
                <div id="bulk-buy-bottom-bar" class="mx-auto flex max-w-full items-center justify-between gap-3 lg:max-w-6xl">
                    <div>
                        <p class="text-xs font-black text-slate-500 uppercase">Selection</p>
                        <p class="text-sm font-black text-slate-900" id="selection-count">0 items selected</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('purchaser.daily', ['date' => $date]) }}" class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-100 px-4 text-sm font-black text-slate-700 transition hover:bg-slate-200">
                            Cancel
                        </a>
                        <button type="submit" id="next-btn" disabled class="inline-flex h-11 items-center justify-center rounded-xl bg-teal-600 px-5 text-sm font-black text-white transition hover:bg-teal-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            Next
                        </button>
                    </div>
                </div>

                {{-- Add-ons Picker Action Bar (for Add-ons Tab) --}}
                <div id="addons-bottom-bar" class="hidden mx-auto flex max-w-full items-center justify-between gap-3 lg:max-w-6xl">
                    <p class="text-2xl font-black text-slate-900" id="addons-selection-count">0</p>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="clearAddonSelections()" class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-100 px-4 text-sm font-black text-slate-700 transition hover:bg-slate-200">
                            Clear
                        </button>
                        <button type="button" id="add-to-demand-btn" onclick="submitAddonsToDemand()" disabled class="inline-flex h-11 items-center justify-center gap-1.5 rounded-xl bg-teal-600 px-5 text-sm font-black text-white shadow-xs transition hover:bg-teal-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Demand
                        </button>
                        <button type="button" id="add-to-cart-btn" onclick="submitAddonsToCart()" disabled class="inline-flex h-11 items-center justify-center gap-1.5 rounded-xl bg-indigo-600 px-5 text-sm font-black text-white shadow-xs transition hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.849-7.083a49.477 49.477 0 0 0-16.364-1.81 49.83 49.83 0 0 0-3.048.307m1.394 7.583L7.5 14.25" />
                            </svg>
                            Cart
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        let activeTab = 'pending';
        const selectedProductIds = new Set();
        const selectedAddons = new Map(); // productId => { product, qty }
        let addonsCurrentPage = 1;
        let addonsHasMore = false;
        let addonsLoading = false;
        let addonsLoadedOnce = false;

        function syncMobileBuyNavigation() {
            const hasSelection = selectedProductIds.size > 0 || selectedAddons.size > 0;
            const wrapper = document.getElementById('bulk-buy-bottom-wrapper');
            if (wrapper) {
                if (hasSelection) {
                    wrapper.classList.remove('hidden');
                } else {
                    wrapper.classList.add('hidden');
                }
            }

            window.dispatchEvent(new CustomEvent('purchaser:buy-selection-change', {
                detail: {
                    hasSelection: hasSelection,
                },
            }));
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function switchTab(tab) {
            activeTab = tab;

            const tabs = ['pending', 'fulfilled', 'addons'];
            tabs.forEach(t => {
                const btn = document.getElementById(`tab-btn-${t}`);
                if (btn) {
                    if (t === tab) {
                        btn.classList.add('bg-white', 'text-slate-900', 'shadow-xs');
                        btn.classList.remove('text-slate-600', 'hover:bg-white/50');
                    } else {
                        btn.classList.remove('bg-white', 'text-slate-900', 'shadow-xs');
                        btn.classList.add('text-slate-600', 'hover:bg-white/50');
                    }
                }
            });

            const pendingContainer = document.getElementById('pending-container');
            const fulfilledContainer = document.getElementById('fulfilled-container');
            const addonsContainer = document.getElementById('addons-container');
            const bulkBuyBottomBar = document.getElementById('bulk-buy-bottom-bar');
            const addonsBottomBar = document.getElementById('addons-bottom-bar');

            if (tab === 'pending') {
                if (pendingContainer) pendingContainer.classList.remove('hidden');
                if (fulfilledContainer) fulfilledContainer.classList.add('hidden');
                if (addonsContainer) addonsContainer.classList.add('hidden');
                if (bulkBuyBottomBar) bulkBuyBottomBar.classList.remove('hidden');
                if (addonsBottomBar) addonsBottomBar.classList.add('hidden');
            } else if (tab === 'fulfilled') {
                if (pendingContainer) pendingContainer.classList.add('hidden');
                if (fulfilledContainer) fulfilledContainer.classList.remove('hidden');
                if (addonsContainer) addonsContainer.classList.add('hidden');
                if (bulkBuyBottomBar) bulkBuyBottomBar.classList.remove('hidden');
                if (addonsBottomBar) addonsBottomBar.classList.add('hidden');

                if (fulfilledContainer && fulfilledContainer.dataset.loaded !== 'true') {
                    window.showLoader?.();
                    fetch(`{{ route('purchaser.bulk-buy.tabs.fulfilled') }}?date={{ $date }}&purchase_grade={{ $purchaseGrade }}`)
                        .then(response => response.text())
                        .then(html => {
                            fulfilledContainer.innerHTML = html;
                            fulfilledContainer.dataset.loaded = 'true';
                            window.filterItems?.();
                        })
                        .finally(() => {
                            window.hideLoader?.();
                        });
                }
            } else if (tab === 'addons') {
                if (pendingContainer) pendingContainer.classList.add('hidden');
                if (fulfilledContainer) fulfilledContainer.classList.add('hidden');
                if (addonsContainer) addonsContainer.classList.remove('hidden');
                if (bulkBuyBottomBar) bulkBuyBottomBar.classList.add('hidden');
                if (addonsBottomBar) addonsBottomBar.classList.remove('hidden');

                if (!addonsLoadedOnce) {
                    fetchAddons(1, true);
                }
            }

            if (window.filterItems) {
                window.filterItems();
            }
        }

        async function fetchAddons(page = 1, reset = false) {
            if (addonsLoading) return;
            addonsLoading = true;

            const searchInput = document.getElementById('search-input');
            const filterSelect = document.getElementById('filter-select');
            const loadingIndicator = document.getElementById('addons-loading');
            const loadMoreWrap = document.getElementById('addons-load-more-wrap');
            const addonsList = document.getElementById('addons-list');
            const noResultsMsg = document.getElementById('no-results-msg');

            if (loadingIndicator) loadingIndicator.classList.remove('hidden');
            if (reset && addonsList) {
                addonsList.innerHTML = '';
            }

            const query = searchInput ? searchInput.value.trim() : '';
            const category = filterSelect ? filterSelect.value : 'All';

            const params = new URLSearchParams({
                page: page,
                limit: 24,
                purchase_grade: '{{ $purchaseGrade }}',
            });

            if (query) {
                params.append('q', query);
            }
            if (category && category !== 'All') {
                params.append('category', category);
            }

            try {
                const response = await fetch(`{{ route('purchaser.bulk-buy.product-search') }}?${params.toString()}`);
                const result = await response.json();
                
                const products = Array.isArray(result) ? result : (result.data || []);
                addonsCurrentPage = result.current_page || page;
                addonsHasMore = Boolean(result.has_more);
                addonsLoadedOnce = true;

                if (reset && products.length === 0) {
                    if (noResultsMsg) {
                        noResultsMsg.classList.remove('hidden');
                        noResultsMsg.textContent = 'No add-on products match the selected filters.';
                    }
                } else {
                    if (noResultsMsg && activeTab === 'addons') {
                        noResultsMsg.classList.add('hidden');
                    }
                    products.forEach(product => {
                        const card = createAddonPickerCard(product);
                        addonsList.appendChild(card);
                    });
                }

                if (loadMoreWrap) {
                    if (addonsHasMore) {
                        loadMoreWrap.classList.remove('hidden');
                    } else {
                        loadMoreWrap.classList.add('hidden');
                    }
                }
            } catch (err) {
                console.error('Failed to load add-on products:', err);
            } finally {
                addonsLoading = false;
                if (loadingIndicator) loadingIndicator.classList.add('hidden');
            }
        }

        function loadMoreAddons() {
            if (!addonsHasMore || addonsLoading) return;
            fetchAddons(addonsCurrentPage + 1, false);
        }

        function createAddonPickerCard(product) {
            const isSelected = selectedAddons.has(product.id);
            const selectedData = selectedAddons.get(product.id);
            const qtyVal = isSelected ? selectedData.qty : '';

            const wrapper = document.createElement('div');
            wrapper.className = `product-item addon-picker-card block relative min-w-0 overflow-hidden rounded-2xl border ${isSelected ? 'border-teal-500 bg-teal-50/20 ring-1 ring-teal-500' : 'border-slate-200 bg-white'} p-3.5 shadow-sm transition hover:border-teal-200`;
            wrapper.dataset.productId = product.id;

            wrapper.innerHTML = `
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <input type="checkbox" class="addon-checkbox h-5 w-5 rounded border-slate-300 text-teal-600 focus:ring-teal-500 cursor-pointer" ${isSelected ? 'checked' : ''}>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-black text-slate-900 text-sm truncate">${escapeHtml(product.name)}</h3>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-500">${escapeHtml(product.category_name || 'Other')}</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-bold text-slate-500">${escapeHtml(product.sku)}</span>
                            </div>
                            <p class="text-xs font-semibold text-slate-500 mt-0.5">Unit: <span class="font-bold text-slate-700">${escapeHtml(product.unit)}</span></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <div class="relative flex items-center">
                            <input type="number" step="any" min="0.1" placeholder="Qty" value="${qtyVal}" class="addon-qty-input w-24 h-10 rounded-xl border border-slate-200 bg-white px-3 text-right text-sm font-black text-slate-900 shadow-xs focus:border-teal-500 focus:ring-1 focus:ring-teal-500 focus:outline-none">
                            <span class="ml-1.5 text-xs font-bold text-slate-500 uppercase">${escapeHtml(product.unit)}</span>
                        </div>
                    </div>
                </div>
            `;

            const checkbox = wrapper.querySelector('.addon-checkbox');
            const qtyInput = wrapper.querySelector('.addon-qty-input');

            function syncSelectionState() {
                const num = parseFloat(qtyInput.value);
                if (checkbox.checked) {
                    const finalQty = !isNaN(num) && num > 0 ? num : 1;
                    if (isNaN(num) || num <= 0) {
                        qtyInput.value = finalQty;
                    }
                    selectedAddons.set(product.id, { product, qty: finalQty });
                    wrapper.className = 'product-item addon-picker-card block relative min-w-0 overflow-hidden rounded-2xl border border-teal-500 bg-teal-50/20 ring-1 ring-teal-500 p-3.5 shadow-sm transition hover:border-teal-200';
                } else {
                    selectedAddons.delete(product.id);
                    wrapper.className = 'product-item addon-picker-card block relative min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:border-teal-200';
                }
                updateAddonsSelectionCount();
            }

            checkbox.addEventListener('change', () => {
                syncSelectionState();
                if (checkbox.checked) {
                    qtyInput.focus();
                    qtyInput.select();
                }
            });

            qtyInput.addEventListener('input', () => {
                const num = parseFloat(qtyInput.value);
                if (!isNaN(num) && num > 0) {
                    checkbox.checked = true;
                    selectedAddons.set(product.id, { product, qty: num });
                    wrapper.className = 'product-item addon-picker-card block relative min-w-0 overflow-hidden rounded-2xl border border-teal-500 bg-teal-50/20 ring-1 ring-teal-500 p-3.5 shadow-sm transition hover:border-teal-200';
                } else {
                    checkbox.checked = false;
                    selectedAddons.delete(product.id);
                    wrapper.className = 'product-item addon-picker-card block relative min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:border-teal-200';
                }
                updateAddonsSelectionCount();
            });

            return wrapper;
        }

        function updateAddonsSelectionCount() {
            const count = selectedAddons.size;
            const countEl = document.getElementById('addons-selection-count');
            const demandBtn = document.getElementById('add-to-demand-btn');
            const cartBtn = document.getElementById('add-to-cart-btn');

            if (countEl) {
                countEl.textContent = count;
            }
            if (demandBtn) {
                demandBtn.disabled = count === 0;
            }
            if (cartBtn) {
                cartBtn.disabled = count === 0;
            }

            syncMobileBuyNavigation();
        }

        function clearAddonSelections() {
            selectedAddons.clear();
            document.querySelectorAll('.addon-picker-card').forEach(card => {
                const cb = card.querySelector('.addon-checkbox');
                const qi = card.querySelector('.addon-qty-input');
                if (cb) cb.checked = false;
                if (qi) qi.value = '';
                card.className = 'product-item addon-picker-card block relative min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:border-teal-200';
            });
            updateAddonsSelectionCount();
        }

        async function submitAddonsToDemand() {
            if (selectedAddons.size === 0) return;

            const items = Array.from(selectedAddons.values()).map(entry => ({
                product_id: entry.product.id,
                quantity: entry.qty,
            }));

            window.showLoader?.();

            try {
                const response = await fetch('{{ route('purchaser.bulk-buy.add-ons.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        date: '{{ $date }}',
                        purchase_grade: '{{ $purchaseGrade }}',
                        items: items,
                    }),
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    if (result.redirect_url) {
                        window.location.href = result.redirect_url;
                    } else {
                        window.location.reload();
                    }
                } else {
                    window.hideLoader?.();
                    alert(result.message || 'Failed to add items to demand. Please try again.');
                }
            } catch (err) {
                window.hideLoader?.();
                console.error('Error adding direct purchase add-ons:', err);
                alert('A network error occurred while adding to demand.');
            }
        }

        async function submitAddonsToCart() {
            if (selectedAddons.size === 0) return;

            const items = Array.from(selectedAddons.values()).map(entry => ({
                product_id: entry.product.id,
                quantity: entry.qty,
            }));

            window.showLoader?.();

            try {
                const response = await fetch('{{ route('purchaser.bulk-buy.add-ons-to-cart.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        date: '{{ $date }}',
                        purchase_grade: '{{ $purchaseGrade }}',
                        items: items,
                    }),
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    if (result.redirect_url) {
                        window.location.href = result.redirect_url;
                    } else {
                        window.location.reload();
                    }
                } else {
                    window.hideLoader?.();
                    alert(result.message || 'Failed to add items to cart. Please try again.');
                }
            } catch (err) {
                window.hideLoader?.();
                console.error('Error adding add-ons to cart:', err);
                alert('A network error occurred while adding to cart.');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('search-input');
            const filterSelect = document.getElementById('filter-select');
            const staticItems = document.querySelectorAll('.product-item[data-tab="pending"]');
            const selectionCount = document.getElementById('selection-count');
            const nextBtn = document.getElementById('next-btn');
            const noResultsMsg = document.getElementById('no-results-msg');

            // Initialize selectedProductIds from any pre-checked checkboxes in Pending
            document.querySelectorAll('#pending-container .product-checkbox:checked').forEach(cb => {
                selectedProductIds.add(Number(cb.value));
            });

            // Handle Custom Dropdown clicks
            document.addEventListener('click', (e) => {
                const trigger = e.target.closest('.custom-select-trigger');
                if (trigger) {
                    const container = trigger.closest('.custom-select-container');
                    const optionsList = container.querySelector('.custom-select-options');
                    const arrow = trigger.querySelector('svg');
                    
                    document.querySelectorAll('.custom-select-options').forEach(el => {
                        if (el !== optionsList) {
                            el.classList.add('hidden');
                            const otherTrigger = el.closest('.custom-select-container')?.querySelector('.custom-select-trigger svg');
                            if (otherTrigger) otherTrigger.classList.remove('rotate-180');
                        }
                    });
                    
                    optionsList.classList.toggle('hidden');
                    if (arrow) arrow.classList.toggle('rotate-180');
                    return;
                }

                const option = e.target.closest('.custom-select-option');
                if (option) {
                    const container = option.closest('.custom-select-container');
                    const input = container.querySelector('#filter-select');
                    const label = container.querySelector('.custom-select-label');
                    const optionsList = container.querySelector('.custom-select-options');
                    
                    const val = option.getAttribute('data-value');
                    
                    input.value = val;
                    label.textContent = `Filter: ${val}`;
                    
                    container.querySelectorAll('.custom-select-option').forEach(opt => {
                        const check = opt.querySelector('.checkmark');
                        if (opt === option) {
                            check.classList.remove('hidden');
                        } else {
                            check.classList.add('hidden');
                        }
                    });
                    
                    optionsList.classList.add('hidden');
                    const arrow = container.querySelector('.custom-select-trigger svg');
                    if (arrow) arrow.classList.remove('rotate-180');
                    
                    if (window.filterItems) {
                        window.filterItems();
                    }
                    if (window.updatePillStyles) {
                        window.updatePillStyles(val);
                    }
                    return;
                }

                if (!e.target.closest('.custom-select-container')) {
                    document.querySelectorAll('.custom-select-options').forEach(el => {
                        el.classList.add('hidden');
                        const container = el.closest('.custom-select-container');
                        const arrow = container?.querySelector('.custom-select-trigger svg');
                        if (arrow) arrow.classList.remove('rotate-180');
                    });
                }
            });

            window.updatePillStyles = function(selectedCategory) {
                document.querySelectorAll('.category-pill').forEach(pill => {
                    const pillCat = pill.getAttribute('data-category-pill');
                    if (pillCat && pillCat.toLowerCase() === (selectedCategory || '').toLowerCase()) {
                        pill.className = 'category-pill snap-start shrink-0 rounded-full bg-teal-600 px-3.5 py-1.5 text-[11px] font-black uppercase tracking-[0.16em] text-white shadow-xs transition';
                    } else {
                        pill.className = 'category-pill snap-start shrink-0 rounded-full bg-slate-100 px-3.5 py-1.5 text-[11px] font-black uppercase tracking-[0.16em] text-slate-600 transition hover:bg-slate-200';
                    }
                });
            };

            window.selectCategoryPill = function(category, btn) {
                const filterSelectInput = document.getElementById('filter-select');
                if (filterSelectInput) {
                    filterSelectInput.value = category;
                }
                const label = document.querySelector('.custom-select-label');
                if (label) {
                    label.textContent = `Filter: ${category}`;
                }
                window.updatePillStyles(category);
                if (window.filterItems) {
                    window.filterItems();
                }
            };

            window.filterItems = function() {
                if (activeTab === 'addons') {
                    fetchAddons(1, true);
                    return;
                }

                const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
                const category = filterSelect ? filterSelect.value : 'All';
                let visibleCount = 0;

                const targetItems = activeTab === 'fulfilled' 
                    ? document.querySelectorAll('#fulfilled-container .product-item') 
                    : staticItems;

                targetItems.forEach(item => {
                    const name = (item.dataset.name || '').toLowerCase();
                    const sku = (item.dataset.sku || '').toLowerCase();
                    const itemCategory = item.dataset.category || '';
                    const isFrequent = item.dataset.frequent === 'true';

                    const matchSearch = name.includes(query) || sku.includes(query);
                    let matchFilter = false;

                    if (category === 'All') {
                        matchFilter = true;
                    } else if (category === 'Frequent') {
                        matchFilter = isFrequent;
                    } else {
                        matchFilter = itemCategory.toLowerCase() === category.toLowerCase();
                    }

                    if (matchSearch && matchFilter) {
                        item.classList.remove('hidden');
                        visibleCount++;
                    } else {
                        item.classList.add('hidden');
                    }
                });

                if (noResultsMsg) {
                    if (visibleCount === 0) {
                        noResultsMsg.classList.remove('hidden');
                        if (query === '' && category === 'All') {
                            noResultsMsg.textContent = activeTab === 'fulfilled' 
                                ? 'No fulfilled products for this date.' 
                                : 'No pending products for this date.';
                        } else {
                            noResultsMsg.textContent = 'No products match the selected filters.';
                        }
                    } else {
                        noResultsMsg.classList.add('hidden');
                    }
                }

                updateSelectionCount();
            };

            function updateSelectionCount() {
                const checkedCount = selectedProductIds.size;
                if (selectionCount) {
                    selectionCount.textContent = `${checkedCount} item${checkedCount !== 1 ? 's' : ''} selected`;
                }
                if (nextBtn) {
                    nextBtn.disabled = checkedCount === 0;
                }

                syncMobileBuyNavigation();
            }

            // Bind change events on static checkboxes
            staticItems.forEach(item => {
                const cb = item.querySelector('.product-checkbox');
                if (cb) {
                    cb.addEventListener('change', () => {
                        if (cb.checked) {
                            selectedProductIds.add(Number(cb.value));
                        } else {
                            selectedProductIds.delete(Number(cb.value));
                        }
                        updateSelectionCount();
                    });
                }

                item.addEventListener('click', (e) => {
                    if (e.target.closest('input[type="checkbox"]') || e.target.closest('a') || e.target.closest('button')) {
                        return;
                    }
                    if (cb) {
                        cb.checked = !cb.checked;
                        cb.dispatchEvent(new Event('change'));
                    }
                });
            });

            let searchTimer;
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(() => {
                        window.filterItems();
                    }, 300);
                });
            }

            updateSelectionCount();
            updateAddonsSelectionCount();
            window.filterItems();
        });
    </script>
</x-layouts.app>
