@php($produceType = ($filters['warehouse_code'] ?? null) === 'VEG-WH' ? 'vegetables' : (($filters['warehouse_code'] ?? null) === 'FRT-WH' ? 'fruits' : ($filters['produce_type'] ?? 'all')))

@component('admin.cashbook.finance.purchase.partials._period-filter', [
    'action' => route($filterRoute),
    'filters' => $filters,
    'extraParams' => array_filter([
        'produce_type' => $produceType !== 'all' ? $produceType : null,
        'sort' => ($filters['sort'] ?? 'code') !== 'code' ? $filters['sort'] : null,
        'search' => $filters['search'] ?? null,
        'category_id' => $filters['category_id'] ?? null,
        'product_id' => $filters['product_id'] ?? null,
        'purchaser_id' => $filters['purchaser_id'] ?? null,
        'vendor_id' => $filters['vendor_id'] ?? null,
        'grade' => $filters['grade'] ?? null,
    ], fn ($v) => $v !== null && $v !== ''),
])
    <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 items-end">
        <div>
            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Produce</label>
            <select name="produce_type" onchange="this.form.submit()" class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                <option value="all" @selected($produceType === 'all')>All Produce</option>
                <option value="vegetables" @selected($produceType === 'vegetables')>Vegetables</option>
                <option value="fruits" @selected($produceType === 'fruits')>Fruits</option>
            </select>
        </div>

        <div>
            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Sort By</label>
            <select name="sort" onchange="this.form.submit()" class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                <option value="code" @selected(($filters['sort'] ?? 'code') === 'code')>Code</option>
                <option value="category" @selected(($filters['sort'] ?? 'code') === 'category')>Category</option>
            </select>
        </div>

        <div>
            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Category</label>
            <select name="category_id" onchange="this.form.submit()" class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">All Categories</option>
                @foreach($options['categories'] as $option)
                    <option value="{{ $option->id }}" @selected(($filters['category_id'] ?? null) === (int) $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Product</label>
            <select name="product_id" onchange="this.form.submit()" class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">All Products</option>
                @foreach($options['products'] as $option)
                    <option value="{{ $option->id }}" @selected(($filters['product_id'] ?? null) === (int) $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Purchaser</label>
            <select name="purchaser_id" onchange="this.form.submit()" class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">All Purchasers</option>
                @foreach($options['purchasers'] as $option)
                    <option value="{{ $option->id }}" @selected(($filters['purchaser_id'] ?? null) === (int) $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Search</label>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Code or name..."
                   class="mt-1 block w-full rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
        </div>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-3 border-t border-slate-100">
        <div class="flex items-center gap-2 flex-wrap">
            <button type="submit" class="inline-flex min-h-9 items-center gap-1.5 rounded-xl bg-emerald-700 px-4 text-xs font-black text-white hover:bg-emerald-800 shadow-xs cursor-pointer">
                <i data-lucide="filter" class="h-3.5 w-3.5"></i> Apply
            </button>
            <a href="{{ route($filterRoute) }}" class="inline-flex min-h-9 items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 transition" title="Reset filters">
                <i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i>
            </a>

            @if(!empty($filters['search']))
                <a href="{{ route($filterRoute, request()->except('search', 'page')) }}" class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100">
                    <span>Clear: "{{ $filters['search'] }}"</span>
                    <i data-lucide="x" class="h-3.5 w-3.5"></i>
                </a>
            @endif
        </div>

        <details class="text-xs font-bold text-slate-600 cursor-pointer" @if($filters['vendor_id'] || $filters['grade']) open @endif>
            <summary class="hover:text-slate-900">More (Vendor, Grade)</summary>
            <div class="mt-2 flex items-center gap-2">
                <select name="vendor_id" onchange="this.form.submit()" class="rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">All Vendors</option>
                    @foreach($options['vendors'] as $option)
                        <option value="{{ $option->id }}" @selected(($filters['vendor_id'] ?? null) === (int) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
                <select name="grade" onchange="this.form.submit()" class="rounded-xl border-slate-300 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">All Grades</option>
                    <option value="A" @selected(($filters['grade'] ?? null) === 'A')>Grade A</option>
                    <option value="B" @selected(($filters['grade'] ?? null) === 'B')>Grade B</option>
                </select>
            </div>
        </details>
    </div>
@endcomponent

