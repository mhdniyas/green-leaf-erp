<x-layouts.app title="Bulk Purchase" :show-mobile-nav="false">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_16px_36px_rgba(15,23,42,0.18)] lg:rounded-[2rem]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(45,212,191,0.28),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#134e4a_100%)] px-4 py-4 sm:px-5 lg:px-4 lg:py-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-teal-200 sm:text-[11px] sm:tracking-[0.22em]">Purchaser Flow</p>
                        <h1 class="mt-1 text-xl font-black tracking-tight sm:mt-2 sm:text-2xl">Bulk Purchase (Step 1)</h1>
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
        </div>

        <form action="{{ route('purchaser.bulk-buy.details') }}" method="GET" id="bulk-buy-form" class="pb-24">
            <input type="hidden" name="date" value="{{ $date }}">
            
            {{-- Professional Tabs switcher --}}
            <div class="mb-4 flex rounded-xl bg-slate-100 p-1 lg:rounded-2xl">
                <button type="button" onclick="switchTab('pending')" id="tab-btn-pending" class="flex-1 rounded-lg py-2.5 text-center text-xs font-black uppercase tracking-wider transition-all bg-white text-slate-900 shadow-xs focus:outline-none">
                    Pending ({{ $dailySummary->filter(fn($s) => $s['remaining_qty'] > 0)->count() }})
                </button>
                <button type="button" onclick="switchTab('fulfilled')" id="tab-btn-fulfilled" class="flex-1 rounded-lg py-2.5 text-center text-xs font-black uppercase tracking-wider transition-all text-slate-600 hover:bg-white/50 focus:outline-none">
                    Fulfilled ({{ $dailySummary->filter(fn($s) => $s['remaining_qty'] <= 0)->count() }})
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
                @foreach ($dailySummary->filter(fn($s) => $s['remaining_qty'] > 0) as $summary)
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
                @foreach ($dailySummary->filter(fn($s) => $s['remaining_qty'] <= 0) as $summary)
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

                {{-- Add-on Products --}}
                @foreach ($addOnProducts as $product)
                    <label class="product-item block relative min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:bg-slate-50 cursor-pointer"
                           data-tab="addons"
                           data-name="{{ $product->name }}"
                           data-sku="{{ $product->sku }}"
                           data-category="{{ $product->category?->name }}"
                           data-frequent="false">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center shrink-0">
                                <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" class="product-checkbox h-5 w-5 rounded border-slate-300 text-teal-600 focus:ring-teal-500 cursor-pointer">
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="min-w-0 break-words font-black text-slate-900 text-sm">{{ $product->name }}</h3>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-500">{{ $product->category?->name ?: 'Other' }}</span>
                                </div>
                                <div class="mt-2 flex items-center gap-4 text-xs font-semibold text-slate-500">
                                    <span>Unit: {{ $product->unit }}</span>
                                    <span class="rounded-full bg-teal-50 px-2 py-0.5 text-[8px] font-black uppercase tracking-wider text-teal-700">Add-on</span>
                                </div>
                            </div>
                        </div>
                    </label>
                @endforeach
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
    </div>    <script>
        let activeTab = 'pending';

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

            // Re-apply filters with the new active tab
            if (window.filterItems) {
                window.filterItems();
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('search-input');
            const filterSelect = document.getElementById('filter-select');
            const items = document.querySelectorAll('.product-item');
            const checkboxes = document.querySelectorAll('.product-checkbox');
            const selectionCount = document.getElementById('selection-count');
            const nextBtn = document.getElementById('next-btn');
            const noResultsMsg = document.getElementById('no-results-msg');
            const selectAllCheckbox = document.getElementById('select-all-checkbox');

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

            window.filterItems = function() {
                const query = searchInput.value.toLowerCase().trim();
                const category = filterSelect.value;
                let visibleCount = 0;

                items.forEach(item => {
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
                        matchFilter = itemCategory === category;
                    }

                    const matchTab = itemTab === activeTab;

                    if (matchSearch && matchFilter && matchTab) {
                        item.classList.remove('hidden');
                        visibleCount++;
                    } else {
                        item.classList.add('hidden');
                    }
                });

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
            }

            function updateSelectionCount() {
                const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
                selectionCount.textContent = `${checkedCount} item${checkedCount !== 1 ? 's' : ''} selected`;
                nextBtn.disabled = checkedCount === 0;

                // Update Select All checkbox state based on visible items
                const visibleCheckboxes = Array.from(items)
                    .filter(item => !item.classList.contains('hidden'))
                    .map(item => item.querySelector('.product-checkbox'))
                    .filter(Boolean);

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
                items.forEach(item => {
                    if (!item.classList.contains('hidden')) {
                        const cb = item.querySelector('.product-checkbox');
                        if (cb) {
                            cb.checked = isChecked;
                        }
                    }
                });
                updateSelectionCount();
            });

            searchInput.addEventListener('input', window.filterItems);

            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateSelectionCount);
            });

            // Make whole card toggle the checkbox on click unless clicking checkbox itself
            items.forEach(item => {
                item.addEventListener('click', (e) => {
                    if (e.target.closest('input[type="checkbox"]') || e.target.closest('a') || e.target.closest('button')) {
                        return;
                    }
                    const cb = item.querySelector('.product-checkbox');
                    if (cb) {
                        cb.checked = !cb.checked;
                        cb.dispatchEvent(new Event('change'));
                    }
                });
            });

            updateSelectionCount();
            window.filterItems();
        });
    </script>
</x-layouts.app>
