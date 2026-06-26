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
                <div class="relative w-full md:w-64 shrink-0">
                    <select id="filter-select" class="w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-4 pr-10 py-3.5 text-xs font-black text-slate-700 focus:border-teal-500 focus:bg-white focus:outline-none lg:rounded-2xl lg:pl-5">
                        @foreach ($quickFilters as $filter)
                            <option value="{{ $filter }}" {{ $filter === 'All' ? 'selected' : '' }}>
                                {{ $filter }}
                            </option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-500">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ route('purchaser.bulk-buy.details') }}" method="GET" id="bulk-buy-form" class="pb-24">
            <input type="hidden" name="date" value="{{ $date }}">
            
            <div class="space-y-3" id="product-list">
                @forelse ($dailySummary as $summary)
                    <label class="product-item block relative min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:bg-slate-50 cursor-pointer"
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
                                    <span class="{{ $summary['remaining_qty'] > 0 ? 'text-teal-600 font-bold' : 'text-slate-400' }}">Left: {{ number_format($summary['remaining_qty'], 1) }}</span>
                                </div>
                            </div>
                        </div>
                    </label>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-3 py-10 text-center text-sm font-bold text-slate-500 lg:rounded-[2rem] lg:px-4 lg:py-12">
                        No demand for this date.
                    </div>
                @endforelse
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
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('search-input');
            const filterSelect = document.getElementById('filter-select');
            const items = document.querySelectorAll('.product-item');
            const checkboxes = document.querySelectorAll('.product-checkbox');
            const selectionCount = document.getElementById('selection-count');
            const nextBtn = document.getElementById('next-btn');
            const noResultsMsg = document.getElementById('no-results-msg');

            function filterItems() {
                const query = searchInput.value.toLowerCase().trim();
                const category = filterSelect.value;
                let visibleCount = 0;

                items.forEach(item => {
                    const name = item.dataset.name.toLowerCase();
                    const sku = item.dataset.sku.toLowerCase();
                    const itemCategory = item.dataset.category;
                    const isFrequent = item.dataset.frequent === 'true';

                    const matchSearch = name.includes(query) || sku.includes(query);
                    let matchFilter = false;

                    if (category === 'All') {
                        matchFilter = true;
                    } else if (category === 'Frequent') {
                        matchFilter = isFrequent;
                    } else {
                        matchFilter = itemCategory === category;
                    }

                    if (matchSearch && matchFilter) {
                        item.classList.remove('hidden');
                        visibleCount++;
                    } else {
                        item.classList.add('hidden');
                    }
                });

                if (visibleCount === 0 && items.length > 0) {
                    noResultsMsg.classList.remove('hidden');
                } else {
                    noResultsMsg.classList.add('hidden');
                }
            }

            function updateSelectionCount() {
                const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
                selectionCount.textContent = `${checkedCount} item${checkedCount !== 1 ? 's' : ''} selected`;
                nextBtn.disabled = checkedCount === 0;
            }

            searchInput.addEventListener('input', filterItems);
            filterSelect.addEventListener('change', filterItems);

            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateSelectionCount);
            });

            // Make whole card toggle the checkbox on click unless clicking checkbox itself
            items.forEach(item => {
                item.addEventListener('click', (e) => {
                    if (e.target.closest('input[type="checkbox"]')) {
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
            filterItems();
        });
    </script>
</x-layouts.app>
