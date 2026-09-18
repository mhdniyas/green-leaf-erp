@php
    $selectedProductFilter = $filters['product_filter'] ?? null;
    $sectionRoute = 'admin.cashbook.finance.purchase.'.$section;
    $chipQuery = request()->except('page');
    $categoryFirstId = !empty($filters['category_ids']) ? $filters['category_ids'][0] : null;

    $extraParams = array_filter([
        'product_filter' => $selectedProductFilter,
        'purchaser_id' => $filters['purchaser_id'] ?? null,
        'vendor_id' => $filters['vendor_id'] ?? null,
        'payment' => ($filters['payment'] ?? 'all') !== 'all' ? $filters['payment'] : null,
        'category_id' => $categoryFirstId,
        'grade' => $filters['grade'] ?? null,
        'search' => $filters['search'] ?? null,
    ], fn ($v) => $v !== null && $v !== '');
@endphp

@component('admin.cashbook.finance.purchase.partials._period-filter', [
    'action' => route($sectionRoute),
    'filters' => $filters,
    'extraParams' => $extraParams,
])
    <div class="flex flex-col gap-3 xl:flex-row xl:flex-wrap xl:items-end">
        <label class="text-[10px] font-black uppercase text-slate-500 xl:w-48">
            Product Filter
            <select name="product_filter" onchange="this.form.submit()" class="mt-1 min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden">
                <option value="">All Products</option>
                @foreach($productFilters as $filter)
                    <option value="{{ $filter->uuid }}" @selected($selectedProductFilter === $filter->uuid)>{{ $filter->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="min-w-0 flex-1 text-[10px] font-black uppercase text-slate-500 xl:min-w-72">
            Search
            <span class="mt-1 flex">
                <input name="search" value="{{ $filters['search'] }}" placeholder="{{ $section === 'invoices' ? 'Search invoice, vendor, purchaser, product...' : 'Search '.$section.'...' }}" class="min-h-10 min-w-0 flex-1 rounded-l-xl border border-r-0 border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden">
                <button type="submit" class="inline-flex min-h-10 w-10 shrink-0 items-center justify-center rounded-r-xl border border-emerald-700 bg-emerald-700 text-white hover:bg-emerald-800 transition cursor-pointer" title="Search" aria-label="Search">
                    <i data-lucide="search" class="h-4 w-4"></i>
                </button>
            </span>
        </label>
    </div>

    <details class="mt-3 border-t border-slate-200/60 pt-3" @if($filters['purchaser_id'] || $filters['vendor_id'] || $filters['payment'] !== 'all' || $filters['category_ids'] || $filters['grade']) open @endif>
        <summary class="cursor-pointer text-xs font-black text-emerald-700 hover:text-emerald-900 transition flex items-center gap-1">
            <span>More Filters</span>
            <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
        </summary>
        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            @if($section !== 'purchasers')
                <label class="text-[10px] font-black uppercase text-slate-500">Purchaser
                    <select name="purchaser_id" onchange="this.form.submit()" class="mt-1 min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        <option value="">All Purchasers</option>
                        @foreach($options['purchasers'] as $option)
                            <option value="{{ $option->id }}" @selected($filters['purchaser_id'] === $option->id)>{{ $option->label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @if($section !== 'vendors')
                <label class="text-[10px] font-black uppercase text-slate-500">Vendor
                    <select name="vendor_id" onchange="this.form.submit()" class="mt-1 min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        <option value="">All Vendors</option>
                        @foreach($options['vendors'] as $option)
                            <option value="{{ $option->id }}" @selected($filters['vendor_id'] === $option->id)>{{ $option->label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="text-[10px] font-black uppercase text-slate-500">Payment
                <select name="payment" onchange="this.form.submit()" class="mt-1 min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <option value="all">All</option>
                    <option value="cash" @selected($filters['payment'] === 'cash')>Cash</option>
                    <option value="credit" @selected($filters['payment'] === 'credit')>Credit</option>
                </select>
            </label>

            @if($section !== 'categories')
                <label class="text-[10px] font-black uppercase text-slate-500">Category
                    <select name="category_id" onchange="this.form.submit()" class="mt-1 min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        <option value="">All Categories</option>
                        @foreach($options['categories'] as $option)
                            <option value="{{ $option->id }}" @selected(in_array($option->id, $filters['category_ids'], true))>{{ $option->label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="text-[10px] font-black uppercase text-slate-500">Grade
                <select name="grade" onchange="this.form.submit()" class="mt-1 min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                    <option value="">All Grades</option>
                    <option value="A" @selected($filters['grade'] === 'A')>A</option>
                    <option value="B" @selected($filters['grade'] === 'B')>B</option>
                </select>
            </label>
        </div>
    </details>

    @if($filters['product_filter'] || $filters['purchaser_id'] || $filters['vendor_id'] || $filters['payment'] !== 'all' || $filters['category_ids'] || $filters['grade'])
        <div class="mt-3 flex flex-wrap gap-1.5 border-t border-slate-200/60 pt-3 text-[10px] font-black text-slate-700">
            @if($filters['product_filter'])
                <a href="{{ route($sectionRoute, \Illuminate\Support\Arr::except($chipQuery, 'product_filter')) }}" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 transition">
                    Filter: {{ $productFilters->firstWhere('uuid', $filters['product_filter'])?->name }}
                    <i data-lucide="x" class="h-3 w-3"></i>
                </a>
            @endif
            @if($filters['purchaser_id'])
                <a href="{{ route($sectionRoute, \Illuminate\Support\Arr::except($chipQuery, 'purchaser_id')) }}" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 transition">
                    Purchaser: {{ $options['purchasers']->firstWhere('id', $filters['purchaser_id'])?->label }}
                    <i data-lucide="x" class="h-3 w-3"></i>
                </a>
            @endif
            @if($filters['vendor_id'])
                <a href="{{ route($sectionRoute, \Illuminate\Support\Arr::except($chipQuery, 'vendor_id')) }}" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 transition">
                    Vendor: {{ $options['vendors']->firstWhere('id', $filters['vendor_id'])?->label }}
                    <i data-lucide="x" class="h-3 w-3"></i>
                </a>
            @endif
            @if($filters['payment'] !== 'all')
                <a href="{{ route($sectionRoute, \Illuminate\Support\Arr::except($chipQuery, 'payment')) }}" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 transition">
                    Payment: {{ ucfirst($filters['payment']) }}
                    <i data-lucide="x" class="h-3 w-3"></i>
                </a>
            @endif
            @foreach($filters['category_ids'] as $categoryId)
                <a href="{{ route($sectionRoute, \Illuminate\Support\Arr::except($chipQuery, ['category_id', 'category_ids'])) }}" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 transition">
                    Category: {{ $options['categories']->firstWhere('id', $categoryId)?->label }}
                    <i data-lucide="x" class="h-3 w-3"></i>
                </a>
            @endforeach
            @if($filters['grade'])
                <a href="{{ route($sectionRoute, \Illuminate\Support\Arr::except($chipQuery, 'grade')) }}" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 transition">
                    Grade: {{ $filters['grade'] }}
                    <i data-lucide="x" class="h-3 w-3"></i>
                </a>
            @endif
        </div>
    @endif
@endcomponent
