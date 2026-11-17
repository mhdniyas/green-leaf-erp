<x-layouts.app title="Bulk Purchase">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 pb-24 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4 lg:pb-6">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="overflow-hidden rounded-2xl bg-slate-955 text-white shadow-[0_16px_36px_rgba(15,23,42,0.18)] lg:rounded-[2rem]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(45,212,191,0.28),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#134e4a_100%)] px-4 py-4 sm:px-5 lg:px-4 lg:py-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-teal-200 sm:text-[11px] sm:tracking-[0.22em]">{{ $purchaseGrade === 'ALL' ? 'All Grades' : 'Grade ' . $purchaseGrade }} Purchaser Flow</p>
                        <h1 class="mt-1 text-xl font-black tracking-tight sm:mt-2 sm:text-2xl">{{ $purchaseGrade === 'B' ? 'B Grade ' : ($purchaseGrade === 'C' ? 'C Grade ' : ($purchaseGrade === 'A' ? 'A Grade ' : '')) }}Bulk Purchase (Step 1)</h1>
                        <p class="mt-2 max-w-2xl text-sm font-medium text-slate-200">Select multiple products you want to buy, or purchase individual grades directly.</p>
                    </div>
                    <div class="shrink-0 flex items-center gap-2">
                        <form method="GET" action="{{ route('purchaser.bulk-buy') }}" id="bulk-buy-date-form" class="flex items-center gap-2">
                            @if ($purchaseGrade !== 'ALL')
                                <input type="hidden" name="purchase_grade" value="{{ $purchaseGrade }}">
                            @endif
                            <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="rounded-xl border border-white/20 bg-white/10 px-3.5 py-2 text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-teal-400 cursor-pointer lg:rounded-2xl">
                        </form>
                    </div>
                </div>
            </div>
        </section>

        {{-- Grade Filter Navigation --}}
        <div class="flex items-center gap-2 overflow-x-auto pb-0.5" id="grade-nav-container">
            <a href="{{ route('purchaser.bulk-buy', ['date' => $date, 'purchase_grade' => 'ALL']) }}"
               data-grade="ALL"
               data-active-class="bg-teal-600 text-white shadow-xs"
               data-inactive-class="bg-white text-slate-700 border border-slate-200 hover:bg-slate-50"
               class="grade-nav-btn shrink-0 rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wider transition-all {{ $purchaseGrade === 'ALL' ? 'bg-teal-600 text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' }}">
                All Grades
            </a>
            <a href="{{ route('purchaser.bulk-buy', ['date' => $date, 'purchase_grade' => 'A']) }}"
               data-grade="A"
               data-active-class="bg-emerald-600 text-white shadow-xs"
               data-inactive-class="bg-white text-slate-700 border border-slate-200 hover:bg-slate-50"
               class="grade-nav-btn shrink-0 rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wider transition-all {{ $purchaseGrade === 'A' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' }}">
                Grade A
            </a>
            <a href="{{ route('purchaser.bulk-buy', ['date' => $date, 'purchase_grade' => 'B']) }}"
               data-grade="B"
               data-active-class="bg-amber-600 text-white shadow-xs"
               data-inactive-class="bg-white text-slate-700 border border-slate-200 hover:bg-slate-50"
               class="grade-nav-btn shrink-0 rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wider transition-all {{ $purchaseGrade === 'B' ? 'bg-amber-600 text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' }}">
                Grade B
            </a>
            <a href="{{ route('purchaser.bulk-buy', ['date' => $date, 'purchase_grade' => 'C']) }}"
               data-grade="C"
               data-active-class="bg-indigo-600 text-white shadow-xs"
               data-inactive-class="bg-white text-slate-700 border border-slate-200 hover:bg-slate-50"
               class="grade-nav-btn shrink-0 rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wider transition-all {{ $purchaseGrade === 'C' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50' }}">
                Grade C
            </a>
        </div>

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

        <form action="{{ $purchaseGrade === 'ALL' ? route('purchaser.carts.bulk-store') : route('purchaser.bulk-buy.details') }}"
              method="{{ $purchaseGrade === 'ALL' ? 'POST' : 'GET' }}"
              id="bulk-buy-form"
              class="pb-24">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <input type="hidden" name="business_date" value="{{ $date }}">
            <input type="hidden" name="date" value="{{ $date }}">
            <input type="hidden" id="bulk-buy-purchase-grade" name="purchase_grade" value="{{ $purchaseGrade }}">
            <input type="hidden" id="bulk-buy-submission-key" name="submission_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

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
                    @include('purchasing.purchaser.partials.bulk_pending_items')
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
                        <div class="flex items-center gap-2 {{ $purchaseGrade === 'ALL' ? '' : 'hidden' }}" id="inline-totals-container">
                            <span class="text-sm font-black text-slate-900" id="inline-selected-lines">0 lines</span>
                            <span class="text-xs text-slate-400">•</span>
                            <span class="text-sm font-black text-teal-700" id="inline-total-amount">₹0.00</span>
                        </div>
                        <p class="text-sm font-black text-slate-900 {{ $purchaseGrade === 'ALL' ? 'hidden' : '' }}" id="selection-count">0 items selected</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div id="bottom-cart-select-container" class="relative {{ $purchaseGrade === 'ALL' ? '' : 'hidden' }}">
                            <select name="cart_id" id="bottom-cart-select" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 shadow-xs focus:border-teal-500 focus:outline-none">
                                <option value="">+ New Draft Cart</option>
                            </select>
                        </div>
                        <a href="{{ route('purchaser.daily', ['date' => $date]) }}" class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-100 px-4 text-sm font-black text-slate-700 transition hover:bg-slate-200">
                            Cancel
                        </a>
                        <button type="submit" id="next-btn" disabled class="inline-flex h-11 items-center justify-center rounded-xl bg-teal-600 px-5 text-sm font-black text-white transition hover:bg-teal-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ $purchaseGrade === 'ALL' ? 'Add Selected to Cart' : 'Next' }}
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
            let currentPurchaseGrade = '{{ $purchaseGrade }}';
            const currentDate = '{{ $date }}';
            const currentUserId = '{{ auth()->id() ?? 'guest' }}';

            function getAutosaveKey() {
                return `bulk_buy_autosave_${currentUserId}_${currentDate}`;
            }
            const selectedProductIds = new Set();
            const selectedAddons = new Map(); // productId => { product, qty }
            let addonsCurrentPage = 1;
            let addonsHasMore = false;
            let addonsLoading = false;
            let addonsLoadedOnce = false;
            let inlineEnteredLines = 0;

            // Progressive Loading Caches & State
            const gradeCache = {};
            const l2GradeCache = {};
            const stateByGrade = { 'ALL': {}, 'A': {}, 'B': {}, 'C': {} };

            // Initialize current grade cache from server-rendered HTML
            const initialPendingContainer = document.getElementById('pending-container');
            if (initialPendingContainer) {
                gradeCache[currentPurchaseGrade] = initialPendingContainer.innerHTML;
            }

            function syncMobileBuyNavigation() {
                const hasSelection = selectedProductIds.size > 0 || selectedAddons.size > 0 || inlineEnteredLines > 0;
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
                        fetch(`{{ route('purchaser.bulk-buy.tabs.fulfilled') }}?date=${encodeURIComponent(currentDate)}&purchase_grade=${encodeURIComponent(currentPurchaseGrade)}`)
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

            // Grade Switching with State Preservation & Caching
            function saveCurrentGradeInputs(grade) {
                if (!stateByGrade[grade]) stateByGrade[grade] = {};
                const container = document.getElementById('pending-container');
                if (!container) return;

                const checked = [];
                container.querySelectorAll('input.product-checkbox:checked').forEach(cb => {
                    checked.push(Number(cb.value));
                });
                stateByGrade[grade].checkedProductIds = checked;

                const inlines = {};
                container.querySelectorAll('.inline-qty-input, .inline-rate-input').forEach(input => {
                    if (input.value !== '') {
                        const key = `${input.dataset.productId}_${input.dataset.grade}_${input.classList.contains('inline-qty-input') ? 'qty' : 'rate'}`;
                        inlines[key] = input.value;
                    }
                });
                stateByGrade[grade].inlineValues = inlines;

                const bottomCartSelect = document.getElementById('bottom-cart-select');
                if (bottomCartSelect) {
                    stateByGrade[grade].selectedCartId = bottomCartSelect.value;
                }

                try {
                    localStorage.setItem(getAutosaveKey(), JSON.stringify(stateByGrade));
                } catch (e) {}
            }

            function restoreGradeInputs(grade) {
                const container = document.getElementById('pending-container');
                if (!container || !stateByGrade[grade]) return;

                const saved = stateByGrade[grade];
                if (saved.checkedProductIds) {
                    container.querySelectorAll('input.product-checkbox').forEach(cb => {
                        const isChecked = saved.checkedProductIds.includes(Number(cb.value));
                        cb.checked = isChecked;
                        if (isChecked) {
                            selectedProductIds.add(Number(cb.value));
                        } else {
                            selectedProductIds.delete(Number(cb.value));
                        }
                    });
                }

                if (saved.inlineValues) {
                    container.querySelectorAll('.inline-qty-input, .inline-rate-input').forEach(input => {
                        const key = `${input.dataset.productId}_${input.dataset.grade}_${input.classList.contains('inline-qty-input') ? 'qty' : 'rate'}`;
                        if (saved.inlineValues[key] !== undefined) {
                            input.value = saved.inlineValues[key];
                        }
                    });
                }

                const bottomCartSelect = document.getElementById('bottom-cart-select');
                if (bottomCartSelect && saved.selectedCartId !== undefined) {
                    bottomCartSelect.value = saved.selectedCartId;
                }

                if (grade === 'ALL') {
                    window.updateInlineBulkTotals?.();
                }
                updateSelectionCount();
            }

            async function switchGrade(targetGrade) {
                if (targetGrade === currentPurchaseGrade) return;

                saveCurrentGradeInputs(currentPurchaseGrade);

                // Update Grade Nav Button Styles
                document.querySelectorAll('.grade-nav-btn').forEach(btn => {
                    const btnGrade = btn.getAttribute('data-grade');
                    const activeClass = btn.getAttribute('data-active-class');
                    const inactiveClass = btn.getAttribute('data-inactive-class');

                    if (btnGrade === targetGrade) {
                        btn.className = `grade-nav-btn shrink-0 rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wider transition-all ${activeClass}`;
                    } else {
                        btn.className = `grade-nav-btn shrink-0 rounded-xl px-4 py-2 text-xs font-black uppercase tracking-wider transition-all ${inactiveClass}`;
                    }
                });

                const pendingContainer = document.getElementById('pending-container');

                if (gradeCache[targetGrade]) {
                    if (pendingContainer) {
                        pendingContainer.innerHTML = gradeCache[targetGrade];
                    }
                    onGradeSwapped(targetGrade);
                } else {
                    if (pendingContainer) {
                        pendingContainer.style.opacity = '0.5';
                    }
                    try {
                        const url = `{{ route('purchaser.bulk-buy') }}?date=${encodeURIComponent(currentDate)}&purchase_grade=${encodeURIComponent(targetGrade)}&partial=1`;
                        const response = await fetch(url, {
                            headers: {
                                'Accept': 'text/html',
                                'X-Partial': 'pending',
                            }
                        });
                        if (!response.ok) throw new Error('Failed to load grade partial');
                        const html = await response.text();
                        gradeCache[targetGrade] = html;
                        if (pendingContainer) {
                            pendingContainer.innerHTML = html;
                        }
                        onGradeSwapped(targetGrade);
                    } catch (err) {
                        console.error('Error switching grade:', err);
                        window.location.href = `{{ route('purchaser.bulk-buy') }}?date=${encodeURIComponent(currentDate)}&purchase_grade=${encodeURIComponent(targetGrade)}`;
                        return;
                    } finally {
                        if (pendingContainer) {
                            pendingContainer.style.opacity = '1';
                        }
                    }
                }
            }

            function onGradeSwapped(targetGrade) {
                currentPurchaseGrade = targetGrade;

                const bulkBuyForm = document.getElementById('bulk-buy-form');
                const gradeInput = document.getElementById('bulk-buy-purchase-grade');
                const bottomCartContainer = document.getElementById('bottom-cart-select-container');
                const totalsContainer = document.getElementById('inline-totals-container');
                const selectionCount = document.getElementById('selection-count');
                const nextBtn = document.getElementById('next-btn');

                if (gradeInput) gradeInput.value = targetGrade;

                if (targetGrade === 'ALL') {
                    if (bulkBuyForm) {
                        bulkBuyForm.action = '{{ route('purchaser.carts.bulk-store') }}';
                        bulkBuyForm.method = 'POST';
                    }
                    if (bottomCartContainer) bottomCartContainer.classList.remove('hidden');
                    if (totalsContainer) totalsContainer.classList.remove('hidden');
                    if (selectionCount) selectionCount.classList.add('hidden');
                    if (nextBtn) nextBtn.textContent = 'Add Selected to Cart';
                } else {
                    if (bulkBuyForm) {
                        bulkBuyForm.action = '{{ route('purchaser.bulk-buy.details') }}';
                        bulkBuyForm.method = 'GET';
                    }
                    if (bottomCartContainer) bottomCartContainer.classList.add('hidden');
                    if (totalsContainer) totalsContainer.classList.add('hidden');
                    if (selectionCount) selectionCount.classList.remove('hidden');
                    if (nextBtn) nextBtn.textContent = 'Next';
                }

                // Reset fulfilled container for on-demand reload if tab switched
                const fulfilledContainer = document.getElementById('fulfilled-container');
                if (fulfilledContainer) {
                    fulfilledContainer.dataset.loaded = 'false';
                    fulfilledContainer.innerHTML = '';
                }
                addonsLoadedOnce = false;

                const newUrl = `{{ route('purchaser.bulk-buy') }}?date=${encodeURIComponent(currentDate)}&purchase_grade=${encodeURIComponent(targetGrade)}`;
                window.history.pushState({ grade: targetGrade }, '', newUrl);

                bindPendingItemListeners();
                restoreGradeInputs(targetGrade);

                if (window.filterItems) window.filterItems();
                fetchL2Secondary(targetGrade);
            }

            // L2 Secondary Loading
            async function fetchL2Secondary(grade) {
                if (l2GradeCache[grade]) {
                    applyL2Data(l2GradeCache[grade]);
                    return;
                }

                try {
                    const response = await fetch(`{{ route('purchaser.bulk-buy.secondary') }}?date=${encodeURIComponent(currentDate)}&purchase_grade=${encodeURIComponent(grade)}`, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    if (!response.ok) return;
                    const data = await response.json();
                    l2GradeCache[grade] = data;
                    applyL2Data(data);
                } catch (err) {
                    console.error('Failed to load L2 secondary data:', err);
                }
            }

            function applyL2Data(data) {
                if (!data) return;

                if (data.price_hints) {
                    document.querySelectorAll('.inline-rate-input').forEach(input => {
                        const pId = input.dataset.productId;
                        if (pId && data.price_hints[pId] !== undefined) {
                            const hint = Number(data.price_hints[pId]);
                            input.dataset.priceHint = hint;
                            if (!input.placeholder || input.placeholder === '0.00' || input.placeholder === '0') {
                                input.placeholder = hint > 0 ? hint.toFixed(2) : '0.00';
                            }
                        }
                    });
                }

                if (data.draft_carts) {
                    const bottomSelect = document.getElementById('bottom-cart-select');
                    const modalSelect = document.getElementById('add-to-cart-cart-id');

                    if (bottomSelect) {
                        const selectedVal = bottomSelect.value;
                        bottomSelect.innerHTML = '<option value="">+ New Draft Cart</option>';
                        data.draft_carts.forEach(cart => {
                            const opt = document.createElement('option');
                            opt.value = cart.id;
                            opt.textContent = `${cart.cart_number} ${cart.supplier_name ? '(' + cart.supplier_name + ')' : ''} (${cart.items_count} items)`;
                            bottomSelect.appendChild(opt);
                        });
                        bottomSelect.value = selectedVal;
                    }

                    if (modalSelect) {
                        const selectedVal = modalSelect.value;
                        modalSelect.innerHTML = '<option value="">New Cart (Automatic)</option>';
                        data.draft_carts.forEach(cart => {
                            const opt = document.createElement('option');
                            opt.value = cart.id;
                            opt.setAttribute('data-grade', cart.purchase_grade);
                            opt.textContent = `${cart.cart_number} (${cart.purchase_grade === 'MIXED' ? 'Mixed Lot' : 'Grade ' + cart.purchase_grade})${cart.supplier_name ? ' - ' + cart.supplier_name : ''}`;
                            modalSelect.appendChild(opt);
                        });
                        modalSelect.value = selectedVal;
                    }
                }
            }

            function bindPendingItemListeners() {
                const container = document.getElementById('pending-container');
                if (!container) return;

                container.querySelectorAll('.product-item[data-tab="pending"]').forEach(item => {
                    const cb = item.querySelector('.product-checkbox');
                    if (cb) {
                        cb.onchange = () => {
                            if (cb.checked) {
                                selectedProductIds.add(Number(cb.value));
                            } else {
                                selectedProductIds.delete(Number(cb.value));
                            }
                            updateSelectionCount();
                            saveCurrentGradeInputs(currentPurchaseGrade);
                        };
                    }

                    item.onclick = (e) => {
                        if (e.target.closest('input') || e.target.closest('a') || e.target.closest('button') || e.target.closest('table')) {
                            return;
                        }
                        if (cb) {
                            cb.checked = !cb.checked;
                            cb.dispatchEvent(new Event('change'));
                        }
                    };
                });

                container.querySelectorAll('.inline-qty-input, .inline-rate-input').forEach(input => {
                    input.oninput = () => {
                        window.updateInlineBulkTotals?.();
                        saveCurrentGradeInputs(currentPurchaseGrade);
                    };
                });
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
                    purchase_grade: currentPurchaseGrade,
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
                            date: currentDate,
                            purchase_grade: currentPurchaseGrade,
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
                            date: currentDate,
                            purchase_grade: currentPurchaseGrade,
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
                const selectionCount = document.getElementById('selection-count');
                const nextBtn = document.getElementById('next-btn');
                const noResultsMsg = document.getElementById('no-results-msg');

                // Intercept Grade Nav links for SPA progressive switching
                document.querySelectorAll('.grade-nav-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const targetGrade = btn.getAttribute('data-grade');
                        if (targetGrade) {
                            switchGrade(targetGrade);
                        }
                    });
                });

                // Listen for browser back/forward buttons
                window.addEventListener('popstate', (e) => {
                    const params = new URLSearchParams(window.location.search);
                    const grade = params.get('purchase_grade') || 'A';
                    switchGrade(grade);
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
                        : document.querySelectorAll('#pending-container .product-item[data-tab="pending"]');

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
                        if (inlineEnteredLines > 0) {
                            nextBtn.disabled = false;
                        } else {
                            nextBtn.disabled = checkedCount === 0;
                        }
                    }

                    syncMobileBuyNavigation();
                }

                window.updateInlineBulkTotals = function() {
                    let totalQty = 0;
                    let totalAmount = 0;
                    let linesCount = 0;
                    const activeProductIds = new Set();

                    document.querySelectorAll('.inline-qty-input').forEach(qtyInput => {
                        const qty = parseFloat(qtyInput.value) || 0;
                        const productId = qtyInput.dataset.productId;
                        const grade = qtyInput.dataset.grade;
                        const rateInput = document.querySelector(`.inline-rate-input[data-product-id="${productId}"][data-grade="${grade}"]`);
                        const totalSpan = document.querySelector(`.inline-line-total[data-product-id="${productId}"][data-grade="${grade}"]`);

                        const rate = parseFloat(rateInput ? rateInput.value : 0) || 0;
                        const lineTotal = qty * rate;

                        if (totalSpan) {
                            totalSpan.textContent = '₹' + lineTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }

                        if (qty > 0) {
                            linesCount++;
                            totalQty += qty;
                            totalAmount += lineTotal;
                            activeProductIds.add(Number(productId));
                        }
                    });

                    inlineEnteredLines = linesCount;

                    document.querySelectorAll('#pending-container .product-checkbox').forEach(cb => {
                        const pId = Number(cb.value);
                        if (activeProductIds.has(pId) && !cb.checked) {
                            cb.checked = true;
                            selectedProductIds.add(pId);
                        }
                    });

                    const linesEl = document.getElementById('inline-selected-lines');
                    if (linesEl) {
                        linesEl.textContent = `${linesCount} line${linesCount === 1 ? '' : 's'}`;
                    }
                    const totalAmountEl = document.getElementById('inline-total-amount');
                    if (totalAmountEl) {
                        totalAmountEl.textContent = '₹' + totalAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }

                    updateSelectionCount();
                };

                // Double submission prevention & cross-grade input preservation
                const bulkBuyForm = document.getElementById('bulk-buy-form');
                if (bulkBuyForm) {
                    bulkBuyForm.addEventListener('submit', (e) => {
                        if (e.defaultPrevented) {
                            return;
                        }
                        if (bulkBuyForm.checkValidity && !bulkBuyForm.checkValidity()) {
                            return;
                        }

                        saveCurrentGradeInputs(currentPurchaseGrade);

                        // If submitting on ALL, append any inline edits from other grades
                        if (currentPurchaseGrade === 'ALL') {
                            Object.keys(stateByGrade).forEach(g => {
                                if (g !== 'ALL' && stateByGrade[g].inlineValues) {
                                    const inlines = stateByGrade[g].inlineValues;
                                    Object.keys(inlines).forEach(k => {
                                        const parts = k.split('_');
                                        if (parts.length === 3) {
                                            const [pId, itemGrade, type] = parts;
                                            const fieldName = type === 'qty' ? 'quantity' : 'unit_price';
                                            const hidden = document.createElement('input');
                                            hidden.type = 'hidden';
                                            hidden.name = `items[${pId}][${itemGrade}][${fieldName}]`;
                                            hidden.value = inlines[k];
                                            bulkBuyForm.appendChild(hidden);
                                        }
                                    });
                                }
                            });
                        }

                        // Clear autosave draft for current user and business date upon successful submit invocation
                        try {
                            localStorage.removeItem(getAutosaveKey());
                        } catch (e) {}

                        if (nextBtn) {
                            nextBtn.disabled = true;
                            nextBtn.innerHTML = '<svg class="inline h-4 w-4 animate-spin mr-1" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Adding...';
                        }
                    });
                }

                let searchTimer;
                if (searchInput) {
                    searchInput.addEventListener('input', () => {
                        clearTimeout(searchTimer);
                        searchTimer = setTimeout(() => {
                            window.filterItems();
                        }, 300);
                    });
                }

                // Initial binding
                bindPendingItemListeners();

                // Restore autosaved state if available
                try {
                    const savedData = localStorage.getItem(getAutosaveKey());
                    if (savedData) {
                        const parsed = JSON.parse(savedData);
                        if (parsed && typeof parsed === 'object') {
                            Object.assign(stateByGrade, parsed);
                            restoreGradeInputs(currentPurchaseGrade);
                        }
                    }
                } catch (e) {}

                // Initial L2 fetch
                fetchL2Secondary(currentPurchaseGrade);

                updateSelectionCount();
                updateAddonsSelectionCount();
                window.filterItems();
            });

                // Add to Cart Modal Helpers
        let currentProductUnit = 'kg';
        let currentModalBasis = 'kg';
        let currentPurchaseGrade = 'A';

        function openAddToCartModal(productId, productName, productUnit, remainingQty, draftQty, step, draftPurchasers, purchaseSource = 'shop_order', purchaseGrade = 'A') {
            document.getElementById('add-to-cart-product-id').value = productId;
            document.getElementById('add-to-cart-purchase-source').value = purchaseSource;
            document.getElementById('add-to-cart-purchase-grade').value = purchaseGrade;
            currentPurchaseGrade = purchaseGrade || 'A';

            let displayName = productName;
            if (purchaseGrade) {
                if (purchaseGrade === 'MIXED') {
                    displayName += ' (Mixed Lot)';
                } else {
                    displayName += ` (Grade ${purchaseGrade})`;
                }
            }
            document.getElementById('add-to-cart-product-name').textContent = displayName;
            currentProductUnit = productUnit;

            // Filter cart options by grade
            const cartSelect = document.getElementById('add-to-cart-cart-id');
            if (cartSelect) {
                Array.from(cartSelect.options).forEach(opt => {
                    if (!opt.value) {
                        opt.hidden = false;
                    } else {
                        opt.hidden = opt.getAttribute('data-grade') !== purchaseGrade;
                    }
                });
                cartSelect.value = '';
            }

            const basisContainer = document.getElementById('modal-basis-container');
            if (productUnit === 'kg') {
                basisContainer.classList.remove('hidden');
            } else {
                basisContainer.classList.add('hidden');
            }

            const inCartLabel = document.getElementById('add-to-cart-in-cart-label');
            if (parseFloat(draftQty) > 0) {
                let labelText = `${parseFloat(draftQty).toFixed(2)} ${productUnit} in cart`;
                if (draftPurchasers) {
                    labelText += ` (by ${draftPurchasers})`;
                }
                inCartLabel.textContent = `(${labelText})`;
                inCartLabel.classList.remove('hidden');
            } else {
                inCartLabel.classList.add('hidden');
            }

            const defaultQty = Math.max(0, parseFloat(remainingQty) - parseFloat(draftQty || 0));
            const qtyInput = document.getElementById('add-to-cart-quantity');
            qtyInput.value = defaultQty > 0 ? defaultQty.toFixed(2) : parseFloat(step || 1).toFixed(2);
            document.getElementById('add-to-cart-price').value = '';

            setModalBasis('kg');

            document.getElementById('add-to-cart-modal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => {
                const targetFocus = currentPurchaseGrade === 'MIXED' ? document.getElementById('add-to-cart-price') : qtyInput;
                if (targetFocus) targetFocus.focus();
            }, 50);
        }

        function closeAddToCartModal() {
            document.getElementById('add-to-cart-modal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        function setModalBasis(basis) {
            currentModalBasis = basis;
            const btnKg = document.getElementById('modal-basis-kg-btn');
            const btnBox = document.getElementById('modal-basis-box-btn');
            const qtyLabel = document.getElementById('add-to-cart-qty-label');
            const priceLabel = document.getElementById('add-to-cart-price-label');
            const calcLabel = document.getElementById('add-to-cart-calc-label');
            const convContainer = document.getElementById('modal-conversion-container');
            const qtyInput = document.getElementById('add-to-cart-quantity');
            const priceInput = document.getElementById('add-to-cart-price');

            const isMixed = currentPurchaseGrade === 'MIXED';

            if (basis === 'box') {
                btnKg.className = 'rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all text-slate-600 hover:bg-slate-50 cursor-pointer';
                btnBox.className = 'rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all bg-white text-slate-955 shadow-xs cursor-pointer';
                qtyLabel.textContent = 'Quantity (Boxes)';
                convContainer.classList.remove('hidden');
                qtyInput.step = 'any';
                qtyInput.min = '0.01';

                if (isMixed) {
                    priceLabel.textContent = 'Total Amount (₹)';
                    priceInput.placeholder = 'e.g. 3000';
                    if (calcLabel) calcLabel.textContent = 'Calculated Rate (Per Box / kg)';
                } else {
                    priceLabel.textContent = 'Price (Per Box)';
                    priceInput.placeholder = 'Price';
                    if (calcLabel) calcLabel.textContent = 'Calculated Total';
                }
            } else {
                btnBox.className = 'rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all text-slate-600 hover:bg-slate-50 cursor-pointer';
                btnKg.className = 'rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all bg-white text-slate-955 shadow-xs cursor-pointer';
                qtyLabel.textContent = `Quantity (${currentProductUnit})`;
                convContainer.classList.add('hidden');
                qtyInput.step = 'any';
                qtyInput.min = '0.01';

                if (isMixed) {
                    priceLabel.textContent = 'Total Amount (₹)';
                    priceInput.placeholder = 'e.g. 3000';
                    if (calcLabel) calcLabel.textContent = `Calculated Price (Per ${currentProductUnit})`;
                } else {
                    priceLabel.textContent = `Price (Per ${currentProductUnit})`;
                    priceInput.placeholder = 'Price';
                    if (calcLabel) calcLabel.textContent = 'Calculated Total';
                }
            }
            updateModalTotalPrice();
        }

        function updateModalTotalPrice() {
            const qtyInput = document.getElementById('add-to-cart-quantity');
            const priceInput = document.getElementById('add-to-cart-price');
            const convInput = document.getElementById('add-to-cart-conversion');
            const display = document.getElementById('add-to-cart-total-display');

            if (!display) return;

            const isMixed = currentPurchaseGrade === 'MIXED';

            if (isMixed) {
                const totalAmount = parseFloat(priceInput?.value) || 0;
                const qty = parseFloat(qtyInput?.value) || 0;

                if (currentModalBasis === 'box') {
                    const boxes = qty;
                    const kgPerBox = parseFloat(convInput?.value) || 1;
                    const totalKg = boxes * kgPerBox;

                    const ratePerBox = boxes > 0 ? (totalAmount / boxes) : 0;
                    const ratePerKg = totalKg > 0 ? (totalAmount / totalKg) : 0;

                    if (totalAmount > 0 && boxes > 0) {
                        display.textContent = `₹ ${ratePerBox.toFixed(2)} / box  (₹ ${ratePerKg.toFixed(2)} / kg)`;
                    } else {
                        display.textContent = '₹ 0.00 / kg';
                    }
                } else {
                    const ratePerUnit = qty > 0 ? (totalAmount / qty) : 0;
                    if (totalAmount > 0 && qty > 0) {
                        display.textContent = `₹ ${ratePerUnit.toFixed(2)} / ${currentProductUnit}`;
                    } else {
                        display.textContent = `₹ 0.00 / ${currentProductUnit}`;
                    }
                }
            } else {
                const quantity = parseFloat(qtyInput?.value) || 0;
                const price = parseFloat(priceInput?.value) || 0;
                const total = quantity * price;

                display.textContent = '₹ ' + total.toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const qtyEl = document.getElementById('add-to-cart-quantity');
            const priceEl = document.getElementById('add-to-cart-price');
            const convEl = document.getElementById('add-to-cart-conversion');
            if (qtyEl) qtyEl.addEventListener('input', updateModalTotalPrice);
            if (priceEl) priceEl.addEventListener('input', updateModalTotalPrice);
            if (convEl) convEl.addEventListener('input', updateModalTotalPrice);

            const modalForm = document.querySelector('#add-to-cart-modal form');
            if (modalForm) {
                modalForm.addEventListener('submit', (e) => {
                    const totalEl = document.getElementById('add-to-cart-total-amount');
                    const isMixed = currentPurchaseGrade === 'MIXED';

                    if (isMixed) {
                        const totalAmount = parseFloat(priceEl?.value) || 0;
                        if (totalEl) {
                            totalEl.value = totalAmount > 0 ? totalAmount.toFixed(2) : '';
                        }

                        if (currentProductUnit === 'kg' && currentModalBasis === 'box') {
                            const boxes = parseFloat(qtyEl?.value) || 0;
                            const kgPerBox = parseFloat(convEl?.value) || 1;
                            const totalKg = boxes * kgPerBox;
                            const ratePerKg = totalKg > 0 ? (totalAmount / totalKg) : 0;

                            if (qtyEl) qtyEl.value = totalKg.toFixed(3);
                            if (priceEl) priceEl.value = ratePerKg.toFixed(4);
                        } else {
                            const qty = parseFloat(qtyEl?.value) || 0;
                            const ratePerUnit = qty > 0 ? (totalAmount / qty) : 0;

                            if (priceEl) priceEl.value = ratePerUnit.toFixed(4);
                        }
                    } else {
                        if (totalEl) totalEl.value = '';

                        if (currentProductUnit === 'kg' && currentModalBasis === 'box') {
                            const boxes = parseFloat(qtyEl?.value) || 0;
                            const kgPerBox = parseFloat(convEl?.value) || 1;
                            const pricePerBox = parseFloat(priceEl?.value) || 0;

                            const submitQty = boxes * kgPerBox;
                            const submitPrice = kgPerBox > 0 ? (pricePerBox / kgPerBox) : 0;

                            if (qtyEl) qtyEl.value = submitQty.toFixed(3);
                            if (priceEl) priceEl.value = submitPrice.toFixed(4);
                        }
                    }
                });
            }
        });
    </script>

    {{-- Add to Cart Modal --}}
    <div id="add-to-cart-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden" onclick="if(event.target === this) closeAddToCartModal()">
        <div class="relative w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-xl flex flex-col">
            {{-- Header --}}
            <div class="flex items-center justify-between pb-2 border-b border-slate-100 mb-3">
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-black text-slate-950 truncate" id="add-to-cart-product-name">Product Name</h3>
                    <p class="text-[10px] font-semibold text-slate-500 mt-0.5 flex flex-wrap items-center gap-1">
                        <span>Add to draft cart</span>
                        <span id="add-to-cart-in-cart-label" class="text-amber-600 font-bold hidden"></span>
                    </p>
                </div>
                <button type="button" onclick="closeAddToCartModal()" class="text-slate-400 hover:text-slate-600 focus:outline-none transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form action="{{ route('purchaser.cart-items.store') }}" method="POST" class="space-y-3">
                @csrf
                <input type="hidden" name="business_date" value="{{ $date }}">
                <input type="hidden" id="add-to-cart-product-id" name="product_id" value="">
                <input type="hidden" id="add-to-cart-purchase-source" name="purchase_source" value="shop_order">
                <input type="hidden" id="add-to-cart-total-amount" name="total_amount" value="">
                <input type="hidden" name="return_to" value="bulk-buy">
                <input type="hidden" name="return_grade" value="{{ $purchaseGrade }}">
                <input id="add-to-cart-purchase-grade" type="hidden" name="purchase_grade" value="{{ $purchaseGrade === 'ALL' ? 'A' : $purchaseGrade }}">

                {{-- Target Cart / Vendor Selector --}}
                <div class="space-y-1">
                    <label for="add-to-cart-cart-id" class="block text-[9px] font-black uppercase tracking-wider text-slate-500">
                        Target Cart / Vendor
                    </label>
                    <select name="cart_id" id="add-to-cart-cart-id" class="w-full h-8 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-955 focus:border-teal-500 focus:bg-white focus:outline-none">
                        <option value="">New Cart (Automatic)</option>
                        @foreach ($draftCarts as $cart)
                            <option value="{{ $cart->id }}" data-grade="{{ $cart->purchase_grade }}">
                                {{ $cart->cart_number }} ({{ $cart->purchase_grade === 'MIXED' ? 'Mixed Lot' : 'Grade ' . $cart->purchase_grade }}){{ $cart->supplier ? ' - ' . $cart->supplier->name : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Price Basis Selector --}}
                <div id="modal-basis-container" class="space-y-1 hidden">
                    <label class="block text-[9px] font-black uppercase tracking-wider text-slate-500">Price Basis</label>
                    <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-0.5 w-max">
                        <button type="button" id="modal-basis-kg-btn" onclick="setModalBasis('kg')" class="rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all bg-white text-slate-955 shadow-xs">
                            Per Kg
                        </button>
                        <button type="button" id="modal-basis-box-btn" onclick="setModalBasis('box')" class="rounded-md px-2.5 py-1 text-[9px] font-black uppercase transition-all text-slate-600 hover:bg-slate-50">
                            Per Box
                        </button>
                    </div>
                </div>

                {{-- Quantity input --}}
                <div class="space-y-1">
                    <label for="add-to-cart-quantity" class="block text-[9px] font-black uppercase tracking-wider text-slate-500">
                        <span id="add-to-cart-qty-label">Quantity (kg)</span> <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" step="0.01" min="0.01" id="add-to-cart-quantity" name="quantity" required class="w-full h-8 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-955 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>

                {{-- Box Conversion input --}}
                <div id="modal-conversion-container" class="space-y-1 hidden">
                    <label for="add-to-cart-conversion" class="block text-[9px] font-black uppercase tracking-wider text-slate-500">
                        kg/Box <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" step="0.1" min="0.1" id="add-to-cart-conversion" value="15" class="w-full h-8 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-955 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>

                {{-- Price input --}}
                <div class="space-y-1">
                    <label for="add-to-cart-price" class="block text-[9px] font-black uppercase tracking-wider text-slate-500">
                        <span id="add-to-cart-price-label">Price (Per kg)</span>
                    </label>
                    <input type="number" step="0.01" min="0.01" id="add-to-cart-price" name="unit_price" class="w-full h-8 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-955 focus:border-teal-500 focus:bg-white focus:outline-none" placeholder="Price">
                </div>

                {{-- Calculated Total --}}
                <div class="space-y-1">
                    <label class="block text-[9px] font-black uppercase tracking-wider text-slate-500">
                        <span id="add-to-cart-calc-label">Calculated Total</span>
                    </label>
                    <div id="add-to-cart-total-display" class="flex h-8 w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-100 px-2.5 text-xs font-bold text-slate-700">
                        ₹ 0.00
                    </div>
                </div>

                <div class="pt-1">
                    <button type="submit" class="w-full h-9 rounded-lg bg-teal-600 text-xs font-black text-white hover:bg-teal-500 shadow-sm transition-colors cursor-pointer">
                        Add to Cart
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
