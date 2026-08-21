<x-layouts.app title="Bulk Purchase" :show-mobile-nav="false">
@php
    $mappedAddOnProducts = $addOnProducts->map(function ($product) {
        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'sku' => (string) $product->sku,
            'category_name' => (string) ($product->category?->name ?? 'Other'),
            'unit' => (string) $product->unit,
        ];
    })->values();
@endphp

<script>
    window.bulkBuyAddOnProducts = @json($mappedAddOnProducts);
</script>

    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
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

        {{-- Filter and search bar --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-3 md:flex-row md:items-center">
                    <div class="relative flex-1">
                        <input id="search-input" type="search" placeholder="Search product..." class="w-full min-w-0 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none lg:rounded-2xl lg:px-4">
                    </div>
                    <div class="relative custom-select-container w-full md:w-64 shrink-0">
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
                </div>

                {{-- Horizontal Category Pills (Matching Add-ons) --}}
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
            <div id="hidden-selected-addons-container"></div>
            
            {{-- Professional Tabs switcher --}}
            <div class="mb-4 flex rounded-xl bg-slate-100 p-1 lg:rounded-2xl">
                <button type="button" onclick="switchTab('pending')" id="tab-btn-pending" class="flex-1 rounded-lg py-2.5 text-center text-xs font-black uppercase tracking-wider transition-all bg-white text-slate-900 shadow-xs focus:outline-none">
                    Pending ({{ $pendingSummary->count() }})
                </button>
                <button type="button" onclick="switchTab('fulfilled')" id="tab-btn-fulfilled" class="flex-1 rounded-lg py-2.5 text-center text-xs font-black uppercase tracking-wider transition-all text-slate-600 hover:bg-white/50 focus:outline-none">
                    Fulfilled ({{ $fulfilledSummary->count() }})
                </button>
                <button type="button" onclick="switchTab('addons')" id="tab-btn-addons" class="flex-1 rounded-lg py-2.5 text-center text-xs font-black uppercase tracking-wider transition-all text-slate-600 hover:bg-white/50 focus:outline-none">
                    Add-ons ({{ $addOnProducts->count() }})
                </button>
            </div>

            {{-- Select All visible --}}
            <div class="mb-3 flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 shadow-sm lg:rounded-2xl">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" id="select-all-checkbox" class="h-4.5 w-4.5 rounded border-slate-300 text-teal-600 focus:ring-teal-500 cursor-pointer">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-500">Select All Visible</span>
                </label>
            </div>

            <div class="space-y-3" id="product-list">
                {{-- Pending Carts --}}
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

                {{-- Fulfilled Carts --}}
                @foreach ($fulfilledSummary as $summary)
                    <label class="product-item block relative min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:bg-slate-50 cursor-pointer"
                           data-tab="fulfilled"
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
                                    <h3 class="min-w-0 break-words font-black text-slate-900 text-sm opacity-60">{{ $summary['product_name'] }}</h3>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-500">{{ $summary['category_name'] ?: 'Other' }}</span>
                                    @if ($summary['draft_qty'] > 0)
                                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-black text-amber-700">In Cart: {{ number_format($summary['draft_qty'], 1) }} {{ $summary['unit'] }}</span>
                                    @endif
                                </div>
                                <div class="mt-2 flex items-center gap-4 text-xs font-semibold text-slate-400">
                                    <span>Need: {{ number_format($summary['total_approved_qty'], 1) }} {{ $summary['unit'] }}</span>
                                    <span>Bought: {{ number_format($summary['bought_qty'], 1) }}</span>
                                    <span>Left: {{ number_format($summary['remaining_qty'], 1) }}</span>
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-emerald-700">Fulfilled</span>
                                </div>
                            </div>
                        </div>
                    </label>
                @endforeach

                {{-- Dynamic Add-on Products Container --}}
                <div id="addons-container" class="space-y-3"></div>
            </div>
            
            <div id="no-results-msg" class="hidden rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-sm font-bold text-slate-500 lg:rounded-[2rem] lg:px-4 lg:py-12">
                No products match the selected filters.
            </div>

            {{-- Sticky bottom bar --}}
            <div class="sticky bottom-4 z-40 mt-6 bg-white/95 backdrop-blur-md border border-slate-200 p-4 shadow-[0_8px_30px_rgba(0,0,0,0.08)] rounded-2xl">
                <div class="mx-auto flex max-w-full items-center justify-between gap-3 lg:max-w-6xl">
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
            </div>
        </form>
    </div>

    <script>
        let activeTab = 'pending';
        const selectedProductIds = new Set();

        function switchTab(tab) {
            activeTab = tab;
            
            const tabs = ['pending', 'fulfilled', 'addons'];
            tabs.forEach(t => {
                const btn = document.getElementById(`tab-btn-${t}`);
                if (t === tab) {
                    btn.classList.add('bg-white', 'text-slate-900', 'shadow-xs');
                    btn.classList.remove('text-slate-600', 'hover:bg-white/50');
                } else {
                    btn.classList.remove('bg-white', 'text-slate-900', 'shadow-xs');
                    btn.classList.add('text-slate-600', 'hover:bg-white/50');
                }
            });

            if (window.filterItems) {
                window.filterItems();
            }
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

        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('search-input');
            const filterSelect = document.getElementById('filter-select');
            const staticItems = document.querySelectorAll('.product-item[data-tab="pending"], .product-item[data-tab="fulfilled"]');
            const addonsContainer = document.getElementById('addons-container');
            const hiddenAddonsContainer = document.getElementById('hidden-selected-addons-container');
            const selectionCount = document.getElementById('selection-count');
            const nextBtn = document.getElementById('next-btn');
            const noResultsMsg = document.getElementById('no-results-msg');
            const selectAllCheckbox = document.getElementById('select-all-checkbox');

            // Initialize selectedProductIds from any pre-checked checkboxes
            document.querySelectorAll('.product-checkbox:checked').forEach(cb => {
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
                            const otherTrigger = el.closest('.custom-select-container').querySelector('.custom-select-trigger svg');
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
                        const arrow = container.querySelector('.custom-select-trigger svg');
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

            function createAddOnCard(product) {
                const isChecked = selectedProductIds.has(product.id);
                const label = document.createElement('label');
                label.className = 'product-item addon-dynamic-card block relative min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:bg-slate-50 cursor-pointer';
                label.dataset.tab = 'addons';
                label.dataset.name = product.name;
                label.dataset.sku = product.sku;
                label.dataset.category = product.category_name;
                label.dataset.frequent = 'false';

                const catName = product.category_name || 'Other';
                const checkedAttr = isChecked ? 'checked' : '';

                label.innerHTML = `
                    <div class="flex items-center gap-3">
                        <div class="flex items-center shrink-0">
                            <input type="checkbox" name="product_ids[]" value="${product.id}" class="product-checkbox h-5 w-5 rounded border-slate-300 text-teal-600 focus:ring-teal-500 cursor-pointer" ${checkedAttr}>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="min-w-0 break-words font-black text-slate-900 text-sm">${escapeHtml(product.name)}</h3>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-500">${escapeHtml(catName)}</span>
                            </div>
                            <div class="mt-2 flex items-center gap-4 text-xs font-semibold text-slate-500">
                                <span>Unit: ${escapeHtml(product.unit)}</span>
                                <span class="rounded-full bg-teal-50 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-teal-700">Add-on</span>
                            </div>
                        </div>
                    </div>
                `;

                const cb = label.querySelector('.product-checkbox');
                cb.addEventListener('change', () => {
                    if (cb.checked) {
                        selectedProductIds.add(product.id);
                    } else {
                        selectedProductIds.delete(product.id);
                    }
                    updateSelectionCount();
                });

                label.addEventListener('click', (e) => {
                    if (e.target.closest('input[type="checkbox"]') || e.target.closest('a') || e.target.closest('button')) {
                        return;
                    }
                    cb.checked = !cb.checked;
                    cb.dispatchEvent(new Event('change'));
                });

                return label;
            }

            function syncHiddenAddonInputs(renderedCardIds) {
                if (!hiddenAddonsContainer) return;
                hiddenAddonsContainer.innerHTML = '';
                
                const allAddonProducts = window.bulkBuyAddOnProducts || [];
                allAddonProducts.forEach(product => {
                    if (selectedProductIds.has(product.id) && !renderedCardIds.has(product.id)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'product_ids[]';
                        input.value = product.id;
                        hiddenAddonsContainer.appendChild(input);
                    }
                });
            }

            window.filterItems = function() {
                const query = searchInput.value.toLowerCase().trim();
                const category = filterSelect.value;
                let visibleCount = 0;

                // 1. Filter Static Items (Pending & Fulfilled)
                staticItems.forEach(item => {
                    const name = item.dataset.name.toLowerCase();
                    const sku = item.dataset.sku.toLowerCase();
                    const itemCategory = item.dataset.category;
                    const isFrequent = item.dataset.frequent === 'true';
                    const itemTab = item.dataset.tab;

                    const matchSearch = name.includes(query) || sku.includes(query);
                    let matchFilter = false;

                    if (category === 'All') {
                        matchFilter = true;
                    } else if (category === 'Frequent') {
                        matchFilter = isFrequent;
                    } else {
                        matchFilter = (itemCategory || '').toLowerCase() === (category || '').toLowerCase();
                    }

                    const matchTab = itemTab === activeTab;

                    if (matchSearch && matchFilter && matchTab) {
                        item.classList.remove('hidden');
                        visibleCount++;
                    } else {
                        item.classList.add('hidden');
                    }
                });

                // 2. Filter & Render Bounded Add-ons (Max 30)
                const renderedCardIds = new Set();
                if (addonsContainer) {
                    addonsContainer.innerHTML = '';
                    if (activeTab === 'addons') {
                        const allAddons = window.bulkBuyAddOnProducts || [];
                        const matchingAddons = allAddons.filter(product => {
                            const name = product.name.toLowerCase();
                            const sku = product.sku.toLowerCase();
                            const cat = product.category_name;

                            const matchSearch = name.includes(query) || sku.includes(query);
                            let matchFilter = false;

                            if (category === 'All' || category === 'Frequent') {
                                matchFilter = true;
                            } else {
                                matchFilter = (cat || '').toLowerCase() === (category || '').toLowerCase();
                            }

                            return matchSearch && matchFilter;
                        });

                        visibleCount += matchingAddons.length;

                        // Render top 30 bounded cards
                        const boundedSlice = matchingAddons.slice(0, 30);
                        boundedSlice.forEach(product => {
                            renderedCardIds.add(product.id);
                            const card = createAddOnCard(product);
                            addonsContainer.appendChild(card);
                        });
                    }
                }

                syncHiddenAddonInputs(renderedCardIds);

                if (visibleCount === 0) {
                    noResultsMsg.classList.remove('hidden');
                    if (query === '' && category === 'All') {
                        noResultsMsg.textContent = activeTab === 'addons' 
                            ? 'No add-on products available.' 
                            : (activeTab === 'fulfilled' ? 'No fulfilled products for this date.' : 'No pending products for this date.');
                    } else {
                        noResultsMsg.textContent = 'No products match the selected filters.';
                    }
                } else {
                    noResultsMsg.classList.add('hidden');
                }

                updateSelectionCount();
            };

            function updateSelectionCount() {
                const checkedCount = selectedProductIds.size;
                selectionCount.textContent = `${checkedCount} item${checkedCount !== 1 ? 's' : ''} selected`;
                nextBtn.disabled = checkedCount === 0;

                // Update Select All checkbox state based on visible items
                const visibleCheckboxes = [];
                
                staticItems.forEach(item => {
                    if (!item.classList.contains('hidden')) {
                        const cb = item.querySelector('.product-checkbox');
                        if (cb) visibleCheckboxes.push(cb);
                    }
                });

                if (activeTab === 'addons' && addonsContainer) {
                    addonsContainer.querySelectorAll('.product-checkbox').forEach(cb => {
                        visibleCheckboxes.push(cb);
                    });
                }

                if (visibleCheckboxes.length > 0) {
                    const allChecked = visibleCheckboxes.every(cb => cb.checked);
                    selectAllCheckbox.disabled = false;
                    selectAllCheckbox.checked = allChecked;
                } else {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.disabled = true;
                }
            }

            selectAllCheckbox.addEventListener('change', () => {
                const isChecked = selectAllCheckbox.checked;
                
                staticItems.forEach(item => {
                    if (!item.classList.contains('hidden')) {
                        const cb = item.querySelector('.product-checkbox');
                        if (cb) {
                            cb.checked = isChecked;
                            if (isChecked) {
                                selectedProductIds.add(Number(cb.value));
                            } else {
                                selectedProductIds.delete(Number(cb.value));
                            }
                        }
                    }
                });

                if (activeTab === 'addons' && addonsContainer) {
                    addonsContainer.querySelectorAll('.product-checkbox').forEach(cb => {
                        cb.checked = isChecked;
                        const id = Number(cb.value);
                        if (isChecked) {
                            selectedProductIds.add(id);
                        } else {
                            selectedProductIds.delete(id);
                        }
                    });
                }

                if (window.filterItems) {
                    window.filterItems();
                }
            });

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

            searchInput.addEventListener('input', window.filterItems);

            updateSelectionCount();
            window.filterItems();
        });
    </script>
</x-layouts.app>
