<x-layouts.app title="Special Price Report">
    @php
        $currentSort = (string) ($filters['sort'] ?? 'product');
        $currentDirection = strtolower((string) ($filters['direction'] ?? 'asc'));

        $sortUrl = function (string $column, string $defaultDirection = 'asc') use ($currentSort, $currentDirection): string {
            $nextDirection = ($currentSort === $column)
                ? ($currentDirection === 'asc' ? 'desc' : 'asc')
                : $defaultDirection;

            return request()->fullUrlWithQuery([
                'sort' => $column,
                'direction' => $nextDirection,
                'page' => 1,
            ]);
        };

        $ranges = [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'week' => 'This Week',
            'month' => 'This Month',
            'custom' => 'Custom',
        ];
    @endphp

    <div class="mx-auto w-full max-w-7xl overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <header class="flex flex-wrap items-end justify-between gap-3 bg-slate-950 px-4 py-4 text-white sm:px-5">
            <div>
                <p class="text-[10px] font-black uppercase text-emerald-400">Purchaser Reports</p>
                <h1 class="mt-1 text-xl font-black">Special Price Report</h1>
                <p class="mt-1 text-xs font-semibold text-slate-400">
                    {{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d M Y') }}
                    @if ($filters['date_from'] !== $filters['date_to'])
                        – {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d M Y') }}
                    @endif
                </p>
                @if ($hasCategorySettings)
                    <p class="mt-1 text-[10px] font-black uppercase text-amber-300">Filtered by purchaser settings</p>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('purchaser.reports.special-prices.csv', request()->query()) }}" class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-2 text-xs font-bold text-slate-200 transition hover:bg-slate-800">Export CSV</a>
                <a href="{{ route('purchaser.reports.sales-summary', request()->query()) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-200 transition hover:bg-slate-800">Sales Summary</a>
                <a href="{{ route('purchaser.reports.item-summary', request()->query()) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-200 transition hover:bg-slate-800">Item Summary</a>
                <a href="{{ route('purchaser.bill-prices.index') }}" class="rounded-lg border border-emerald-500/60 bg-emerald-500 px-3 py-2 text-xs font-bold text-white transition hover:bg-emerald-600">Approval - Bill</a>
            </div>
        </header>

        {{-- Filter Bar --}}
        <section class="border-b border-slate-200 bg-white px-3 py-3 sm:px-5">
            @if ($errors->any())
                <div class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Report date range">
                @foreach ($ranges as $value => $label)
                    <a
                        href="{{ route('purchaser.reports.special-prices', array_merge(request()->except(['page', 'date_from', 'date_to', 'date']), ['range' => $value])) }}"
                        @class([
                            'shrink-0 rounded-lg border px-3 py-2 text-xs font-bold transition',
                            'border-emerald-700 bg-emerald-700 text-white' => $selectedRange === $value,
                            'border-slate-200 bg-white text-slate-600 hover:border-slate-300' => $selectedRange !== $value,
                        ])
                    >{{ $label }}</a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('purchaser.reports.special-prices') }}" class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1.2fr_1.2fr_1.5fr_auto_auto]">
                <input type="hidden" name="range" value="{{ $selectedRange }}">
                @if (!empty($filters['sort']))
                    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
                @endif
                @if (!empty($filters['direction']))
                    <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
                @endif

                <label class="text-[10px] font-black uppercase text-slate-500">
                    From
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900" @disabled($selectedRange !== 'custom')>
                </label>

                <label class="text-[10px] font-black uppercase text-slate-500">
                    To
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900" @disabled($selectedRange !== 'custom')>
                </label>

                <label class="text-[10px] font-black uppercase text-slate-500">
                    Shop
                    <select name="shop_id" class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-2 text-sm font-semibold text-slate-900">
                        <option value="">All shops</option>
                        @foreach ($shops as $shop)
                            <option value="{{ $shop->id }}" @selected($filters['shop_id'] === $shop->id)>{{ $shop->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-[10px] font-black uppercase text-slate-500">
                    Status
                    <select name="status" class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-2 text-sm font-semibold text-slate-900">
                        <option value="all" @selected($filters['status'] === 'all')>All statuses</option>
                        <option value="applied" @selected($filters['status'] === 'applied')>Applied to invoice</option>
                        <option value="not_applied" @selected($filters['status'] === 'not_applied')>Not applied (Price mismatch / Pending)</option>
                        <option value="approved" @selected($filters['status'] === 'approved')>Approved only</option>
                        <option value="draft" @selected($filters['status'] === 'draft')>Draft only</option>
                        <option value="expired" @selected($filters['status'] === 'expired')>Expired (Unused)</option>
                    </select>
                </label>

                <label class="text-[10px] font-black uppercase text-slate-500">
                    Search
                    <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Product name, SKU, or shop" class="mt-1 h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 placeholder:text-slate-400">
                </label>

                <button type="submit" class="mt-auto h-10 rounded-lg bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">Apply</button>
                <a href="{{ route('purchaser.reports.special-prices') }}" class="mt-auto inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50">Reset</a>
            </form>
        </section>

        {{-- Summary Metric Cards --}}
        <section class="grid grid-cols-2 divide-x divide-y sm:grid-cols-5 sm:divide-y-0 border-b border-slate-200">
            <div class="px-3 py-3 text-center">
                <p class="text-[10px] font-black uppercase text-slate-400">Total Specials</p>
                <p class="mt-1 text-lg font-black text-slate-950">{{ $report['summary']['total_records'] }}</p>
            </div>
            <div class="px-3 py-3 text-center">
                <p class="text-[10px] font-black uppercase text-slate-400">Distinct Shops</p>
                <p class="mt-1 text-lg font-black text-slate-950">{{ $report['summary']['distinct_shops'] }}</p>
            </div>
            <div class="px-3 py-3 text-center">
                <p class="text-[10px] font-black uppercase text-emerald-600">Applied To Invoice</p>
                <p class="mt-1 text-lg font-black text-emerald-600">{{ $report['summary']['applied_count'] }}</p>
            </div>
            <div class="px-3 py-3 text-center">
                <p class="text-[10px] font-black uppercase text-amber-600">Not Applied / Pending</p>
                <p class="mt-1 text-lg font-black text-amber-600">{{ $report['summary']['not_applied_count'] }}</p>
            </div>
            <div class="col-span-2 sm:col-span-1 px-3 py-3 text-center">
                <p class="text-[10px] font-black uppercase text-slate-400">Drafts</p>
                <p class="mt-1 text-lg font-black text-slate-600">{{ $report['summary']['draft_count'] }}</p>
            </div>
        </section>

        @if ($report['items'] === [])
            <div class="px-5 py-16 text-center text-sm font-semibold text-slate-500">
                <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <p class="mt-3 font-bold text-slate-700">No special prices found</p>
                <p class="mt-1 text-xs text-slate-400">No special prices configured for this period matching your filter criteria.</p>
            </div>
        @else
            {{-- Mobile Quick Sort Indicator --}}
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs md:hidden">
                <span class="font-semibold text-slate-500">Sort: <strong class="text-slate-800 capitalize">{{ $currentSort }} ({{ $currentDirection }})</strong></span>
                <div class="flex items-center gap-1.5">
                    <a href="{{ $sortUrl('product', 'asc') }}" class="rounded border border-slate-200 bg-white px-2 py-1 font-bold text-slate-700 shadow-xs">Product</a>
                    <a href="{{ $sortUrl('shop', 'asc') }}" class="rounded border border-slate-200 bg-white px-2 py-1 font-bold text-slate-700 shadow-xs">Shop</a>
                    <a href="{{ $sortUrl('price', 'asc') }}" class="rounded border border-slate-200 bg-white px-2 py-1 font-bold text-slate-700 shadow-xs">Price</a>
                </div>
            </div>

            {{-- Desktop Table --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-500 select-none">
                        <tr>
                            <th class="w-10 px-3 py-3 text-center">SL</th>
                            <th class="px-4 py-3">
                                <a href="{{ $sortUrl('product', 'asc') }}" class="group inline-flex items-center gap-1 font-black uppercase hover:text-slate-900 transition">
                                    <span>Product</span>
                                    @if ($currentSort === 'product' || $currentSort === 'name')
                                        <span class="text-[10px] font-black text-emerald-600">{{ $currentDirection === 'asc' ? '▲' : '▼' }}</span>
                                    @else
                                        <span class="text-[9px] text-slate-300 opacity-0 group-hover:opacity-100 transition">▲▼</span>
                                    @endif
                                </a>
                            </th>
                            <th class="px-4 py-3">
                                <a href="{{ $sortUrl('shop', 'asc') }}" class="group inline-flex items-center gap-1 font-black uppercase hover:text-slate-900 transition">
                                    <span>Shop</span>
                                    @if ($currentSort === 'shop')
                                        <span class="text-[10px] font-black text-emerald-600">{{ $currentDirection === 'asc' ? '▲' : '▼' }}</span>
                                    @else
                                        <span class="text-[9px] text-slate-300 opacity-0 group-hover:opacity-100 transition">▲▼</span>
                                    @endif
                                </a>
                            </th>
                            <th class="px-3 py-3 text-center">Grade</th>
                            <th class="px-3 py-3 text-right">Normal Price</th>
                            <th class="px-4 py-3 text-right">
                                <a href="{{ $sortUrl('price', 'asc') }}" class="group inline-flex items-center justify-end gap-1 font-black uppercase hover:text-slate-900 transition">
                                    <span>Special Price</span>
                                    @if ($currentSort === 'price' || $currentSort === 'special_price')
                                        <span class="text-[10px] font-black text-emerald-600">{{ $currentDirection === 'asc' ? '▲' : '▼' }}</span>
                                    @else
                                        <span class="text-[9px] text-slate-300 opacity-0 group-hover:opacity-100 transition">▲▼</span>
                                    @endif
                                </a>
                            </th>
                            <th class="px-3 py-3 text-right">Difference</th>
                            <th class="px-3 py-3">
                                <a href="{{ $sortUrl('date', 'desc') }}" class="group inline-flex items-center gap-1 font-black uppercase hover:text-slate-900 transition">
                                    <span>Date</span>
                                    @if ($currentSort === 'date')
                                        <span class="text-[10px] font-black text-emerald-600">{{ $currentDirection === 'asc' ? '▲' : '▼' }}</span>
                                    @else
                                        <span class="text-[9px] text-slate-300 opacity-0 group-hover:opacity-100 transition">▲▼</span>
                                    @endif
                                </a>
                            </th>
                            <th class="px-3 py-3 text-center">Status</th>
                            <th class="px-4 py-3">Updated By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($report['items'] as $item)
                            @php
                                $sl = ($report['pagination']['current_page'] - 1) * $report['pagination']['per_page'] + $loop->iteration;
                            @endphp
                            <tr class="transition hover:bg-slate-50/80">
                                <td class="w-10 px-3 py-3 text-center text-xs font-semibold text-slate-400">{{ $sl }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-950">{{ $item['product_name'] }}</div>
                                    <div class="flex items-center gap-1.5 text-xs text-slate-400">
                                        <span class="font-mono font-semibold">{{ $item['product_sku'] }}</span>
                                        <span>·</span>
                                        <span>{{ $item['category_name'] }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-slate-900">{{ $item['shop_name'] }}</div>
                                    <div class="flex items-center gap-1.5 text-xs text-slate-400">
                                        <span class="font-mono">{{ $item['shop_code'] }}</span>
                                        <span class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.2 text-[10px] font-bold text-slate-600">Group {{ $item['price_group'] }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-700">
                                        {{ $item['grade_label'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    @if ($item['normal_price'] !== null)
                                        <span class="font-semibold text-slate-700">{{ $item['normal_price_formatted'] }}</span>
                                        <span class="block text-[11px] text-slate-400 font-normal">/ {{ $item['price_unit'] }}</span>
                                    @else
                                        <span class="font-semibold text-slate-400" title="Daily price not configured for this price group/date">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-black text-slate-950">{{ $item['special_price_formatted'] }}</span>
                                    <span class="text-[11px] text-slate-400 font-normal">/ {{ $item['price_unit'] }}</span>
                                    <small class="block text-[11px] text-slate-500 font-normal">
                                        Updated by: {{ $item['updater_name'] ?? '—' }} · {{ $item['updated_at_formatted'] ?? '—' }}
                                    </small>
                                </td>
                                <td class="px-3 py-3 text-right font-bold">
                                    @if ($item['difference'] !== null)
                                        @if ($item['difference'] < 0)
                                            <span class="inline-flex items-center gap-0.5 text-emerald-600">
                                                <span>{{ $item['difference_formatted'] }}</span>
                                                <span class="text-[10px] font-normal text-emerald-500">(discount)</span>
                                            </span>
                                        @elseif ($item['difference'] > 0)
                                            <span class="inline-flex items-center gap-0.5 text-amber-600">
                                                <span>{{ $item['difference_formatted'] }}</span>
                                                <span class="text-[10px] font-normal text-amber-500">(premium)</span>
                                            </span>
                                        @else
                                            <span class="text-slate-500">₹0.00</span>
                                        @endif
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <span class="font-semibold text-slate-800">{{ $item['business_date_formatted'] }}</span>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if ($item['status_key'] === 'applied')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20" title="{{ $item['invoice_number'] ? 'Billed on invoice ' . $item['invoice_number'] : '' }}">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                            <span>Applied</span>
                                        </span>
                                    @elseif ($item['status_key'] === 'draft')
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600 ring-1 ring-inset ring-slate-500/20">
                                            Draft
                                        </span>
                                    @elseif ($item['status_key'] === 'not_applied')
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800 ring-1 ring-inset ring-amber-600/20" title="Invoice exists but item price differs (₹{{ number_format((float) $item['invoice_item_price'], 2) }})">
                                            Not Applied
                                        </span>
                                    @elseif ($item['status_key'] === 'expired')
                                        <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-600 ring-1 ring-inset ring-zinc-500/20" title="Business date has passed without invoice application">
                                            Expired
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-700 ring-1 ring-inset ring-sky-600/20" title="Approved special price configured; invoice pending">
                                            Configured
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-xs font-semibold text-slate-900">{{ $item['updater_name'] ?? '—' }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $item['updated_at_formatted'] ?? '—' }}</div>
                                    @if ($item['approved_by_name'] && $item['approved_at_formatted'])
                                        <div class="mt-0.5 text-[10px] text-slate-400" title="Approved by {{ $item['approved_by_name'] }} on {{ $item['approved_at_formatted'] }}">
                                            ✓ Approved by {{ $item['approved_by_name'] }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards --}}
            <div class="divide-y divide-slate-200 md:hidden">
                @foreach ($report['items'] as $item)
                    @php
                        $sl = ($report['pagination']['current_page'] - 1) * $report['pagination']['per_page'] + $loop->iteration;
                    @endphp
                    <article class="p-4 space-y-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-2 min-w-0">
                                <span class="inline-flex size-6 shrink-0 items-center justify-center rounded bg-slate-100 text-[10px] font-black text-slate-600">{{ $sl }}</span>
                                <div class="min-w-0">
                                    <h2 class="font-black text-slate-950">{{ $item['product_name'] }}</h2>
                                    <p class="text-xs text-slate-500">{{ $item['product_sku'] }} · {{ $item['category_name'] }}</p>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-base font-black text-slate-950">{{ $item['special_price_formatted'] }}</p>
                                <p class="text-xs text-slate-400 font-semibold">Normal: {{ $item['normal_price_formatted'] }}</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1 text-xs">
                            <div>
                                <span class="font-bold text-slate-800">{{ $item['shop_name'] }}</span>
                                <span class="text-slate-400">({{ $item['shop_code'] }})</span>
                            </div>
                            <div>
                                @if ($item['difference'] !== null)
                                    <span @class([
                                        'font-bold',
                                        'text-emerald-600' => $item['difference'] < 0,
                                        'text-amber-600' => $item['difference'] > 0,
                                        'text-slate-600' => $item['difference'] == 0,
                                    ])>Diff: {{ $item['difference_formatted'] }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1 text-[11px] text-slate-500 border-t border-slate-100">
                            <div>
                                <span>Date: <strong class="text-slate-700">{{ $item['business_date_formatted'] }}</strong></span>
                                <span class="mx-1">·</span>
                                <span>{{ $item['grade_label'] }}</span>
                            </div>
                            <div>
                                @if ($item['status_key'] === 'applied')
                                    <span class="rounded bg-emerald-50 px-2 py-0.5 font-bold text-emerald-700">Applied</span>
                                @elseif ($item['status_key'] === 'draft')
                                    <span class="rounded bg-slate-100 px-2 py-0.5 font-bold text-slate-600">Draft</span>
                                @elseif ($item['status_key'] === 'not_applied')
                                    <span class="rounded bg-amber-50 px-2 py-0.5 font-bold text-amber-800">Not Applied</span>
                                @elseif ($item['status_key'] === 'expired')
                                    <span class="rounded bg-zinc-100 px-2 py-0.5 font-semibold text-zinc-600">Expired</span>
                                @else
                                    <span class="rounded bg-sky-50 px-2 py-0.5 font-bold text-sky-700">Configured</span>
                                @endif
                            </div>
                        </div>

                        <div class="text-[11px] text-slate-400">
                            Updated by: {{ $item['updater_name'] ?? '—' }} · {{ $item['updated_at_formatted'] ?? '—' }}
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        @include('purchasing.purchaser.reports.partials.pagination', ['pagination' => $report['pagination']])
    </div>
</x-layouts.app>
