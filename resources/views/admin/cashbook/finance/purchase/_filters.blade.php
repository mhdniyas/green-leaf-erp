@php
    $produceType = $filters['warehouse_code'] === 'VEG-WH' ? 'vegetables' : ($filters['warehouse_code'] === 'FRT-WH' ? 'fruits' : 'all');
    $usesCustomDates = in_array($filters['period'], ['custom', 'between', 'range'], true);
    $hasMoreFilters = $filters['purchaser_id'] || $filters['vendor_id'] || $filters['payment'] !== 'all' || $filters['category_ids'] || $filters['grade'] || $usesCustomDates;
@endphp

<form method="GET" action="{{ $filterRoute }}" class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm" data-purchase-filter-form data-today="{{ now('Asia/Kolkata')->toDateString() }}">
    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-[10rem_10rem_minmax(16rem,1fr)_auto_auto] lg:items-end">
        <label class="text-[10px] font-black uppercase text-slate-500">Period
            <select name="period" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800" data-purchase-period>
                @foreach(['today' => 'Today', 'yesterday' => 'Yesterday', 'week' => 'This Week', 'month' => 'This Month', 'custom' => 'Custom'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['period'] === $value || ($value === 'custom' && $usesCustomDates))>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-[10px] font-black uppercase text-slate-500">Produce
            <select name="produce_type" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                <option value="all" @selected($produceType === 'all')>All Produce</option>
                <option value="vegetables" @selected($produceType === 'vegetables')>Vegetables</option>
                <option value="fruits" @selected($produceType === 'fruits')>Fruits</option>
            </select>
        </label>

        <label class="min-w-0 text-[10px] font-black uppercase text-slate-500">Search
            <span class="mt-1 flex">
                <input name="search" value="{{ $filters['search'] }}" placeholder="Purchaser, invoice, vendor, product..." class="min-h-10 min-w-0 flex-1 rounded-l-lg border border-r-0 border-slate-300 px-3 text-xs font-semibold normal-case">
                <button type="submit" class="inline-flex min-h-10 w-10 shrink-0 items-center justify-center rounded-r-lg border border-emerald-700 bg-emerald-700 text-white hover:bg-emerald-800" aria-label="Search" title="Search">
                    <i data-lucide="search" class="h-4 w-4"></i>
                </button>
            </span>
        </label>

        <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg bg-emerald-700 px-4 text-xs font-black text-white hover:bg-emerald-800">
            <i data-lucide="filter" class="h-4 w-4"></i> Apply
        </button>
        <a href="{{ $filterRoute }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-slate-600 hover:bg-slate-50" aria-label="Reset filters" title="Reset filters">
            <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
        </a>
    </div>

    <details class="mt-3 border-t border-slate-100 pt-3" @if($hasMoreFilters) open @endif>
        <summary class="cursor-pointer text-xs font-black text-emerald-700">More Filters</summary>
        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
            <label class="text-[10px] font-black uppercase text-slate-500">Purchaser
                <select name="purchaser_id" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <option value="">All Purchasers</option>
                    @foreach($options['purchasers'] as $purchaser)
                        <option value="{{ $purchaser->id }}" @selected($filters['purchaser_id'] === $purchaser->id)>{{ $purchaser->label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-[10px] font-black uppercase text-slate-500">Vendor
                <select name="vendor_id" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <option value="">All Vendors</option>
                    @foreach($options['vendors'] as $vendor)
                        <option value="{{ $vendor->id }}" @selected($filters['vendor_id'] === $vendor->id)>{{ $vendor->label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-[10px] font-black uppercase text-slate-500">Payment
                <select name="payment" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <option value="all">All Payments</option>
                    <option value="cash" @selected($filters['payment'] === 'cash')>Cash</option>
                    <option value="credit" @selected($filters['payment'] === 'credit')>Credit</option>
                </select>
            </label>

            <label class="text-[10px] font-black uppercase text-slate-500">Category
                <select name="category_id" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <option value="">All Categories</option>
                    @foreach($options['categories'] as $category)
                        <option value="{{ $category->id }}" @selected(in_array($category->id, $filters['category_ids'], true))>{{ $category->label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-[10px] font-black uppercase text-slate-500">Grade
                <select name="grade" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <option value="">All Grades</option>
                    <option value="A" @selected($filters['grade'] === 'A')>Grade A</option>
                    <option value="B" @selected($filters['grade'] === 'B')>Grade B</option>
                </select>
            </label>

            <label class="text-[10px] font-black uppercase text-slate-500">From
                <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-800" data-purchase-start-date>
            </label>

            <label class="text-[10px] font-black uppercase text-slate-500">To
                <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-800" data-purchase-end-date>
            </label>
        </div>
    </details>
</form>

@pushOnce('scripts', 'purchase-filter-dates')
    <script>
        document.querySelectorAll('[data-purchase-filter-form]').forEach((form) => {
            const period = form.querySelector('[data-purchase-period]');
            const startDate = form.querySelector('[data-purchase-start-date]');
            const endDate = form.querySelector('[data-purchase-end-date]');
            const today = form.dataset.today;

            if (!period || !startDate || !endDate || !today) {
                return;
            }

            const parseLocalDate = (value) => {
                const [year, month, day] = value.split('-').map(Number);

                return new Date(year, month - 1, day);
            };
            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');

                return `${year}-${month}-${day}`;
            };
            const setRange = (from, to) => {
                startDate.value = from;
                endDate.value = to;
            };

            period.addEventListener('change', () => {
                const selected = parseLocalDate(today);

                if (period.value === 'today') {
                    setRange(today, today);
                } else if (period.value === 'yesterday') {
                    selected.setDate(selected.getDate() - 1);
                    setRange(formatDate(selected), formatDate(selected));
                } else if (period.value === 'week') {
                    const day = selected.getDay() || 7;
                    selected.setDate(selected.getDate() - day + 1);
                    setRange(formatDate(selected), today);
                } else if (period.value === 'month') {
                    selected.setDate(1);
                    setRange(formatDate(selected), today);
                }
            });

            [startDate, endDate].forEach((field) => {
                field.addEventListener('change', () => {
                    period.value = 'custom';
                });
            });
        });
    </script>
@endPushOnce
