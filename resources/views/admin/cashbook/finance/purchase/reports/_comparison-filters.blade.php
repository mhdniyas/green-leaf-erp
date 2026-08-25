@php($produceType = ($filters['warehouse_code'] ?? null) === 'VEG-WH' ? 'vegetables' : (($filters['warehouse_code'] ?? null) === 'FRT-WH' ? 'fruits' : 'all'))
@php($isChangedView = ($filters['view'] ?? 'all') === 'changed' || !empty($filters['changed_only']))
<form method="GET" action="{{ route($filterRoute) }}" class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-[10rem_10rem_9rem_9rem_1fr_auto] lg:items-end">
        <label class="text-[10px] font-black uppercase text-slate-500">Date A
            <input type="date" name="date_a" value="{{ $filters['date_a'] }}" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs font-bold">
        </label>
        <label class="text-[10px] font-black uppercase text-slate-500">Date B
            <input type="date" name="date_b" value="{{ $filters['date_b'] }}" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs font-bold">
        </label>
        <label class="text-[10px] font-black uppercase text-slate-500">Produce
            <select name="produce_type" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs font-bold">
                <option value="all" @selected($produceType === 'all')>All Produce</option>
                <option value="vegetables" @selected($produceType === 'vegetables')>Vegetables</option>
                <option value="fruits" @selected($produceType === 'fruits')>Fruits</option>
            </select>
        </label>
        @if($showPriceGroup ?? false)
            <label class="text-[10px] font-black uppercase text-slate-500">Selling Price Group
                <select name="price_group" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs font-bold">
                    @foreach(['A', 'B', 'C'] as $group)
                        <option value="{{ $group }}" @selected($filters['price_group'] === $group)>Group {{ $group }}</option>
                    @endforeach
                </select>
            </label>
        @else
            <label class="text-[10px] font-black uppercase text-slate-500">View
                <select name="view" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs font-bold">
                    <option value="all" @selected(! $isChangedView)>All</option>
                    <option value="changed" @selected($isChangedView)>Changed Only</option>
                </select>
            </label>
        @endif
        <label class="text-[10px] font-black uppercase text-slate-500">Search
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search code or name..." class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs font-medium">
        </label>
        <button type="submit" class="min-h-10 rounded-lg bg-emerald-700 px-4 text-xs font-black text-white hover:bg-emerald-800">
            Compare
        </button>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
        <a href="{{ route($filterRoute) }}" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-[10px] font-black text-slate-700 hover:bg-slate-100">
            Yesterday vs Today
        </a>
        @if(!($showPriceGroup ?? false))
            <a href="{{ route($filterRoute, array_merge(request()->except('page'), ['view' => 'all'])) }}" class="rounded-lg border px-3 py-1.5 text-[10px] font-black transition-colors {{ ! $isChangedView ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}">
                All
            </a>
            <a href="{{ route($filterRoute, array_merge(request()->except('page'), ['view' => 'changed'])) }}" class="rounded-lg border px-3 py-1.5 text-[10px] font-black transition-colors {{ $isChangedView ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}">
                Changed Only
            </a>
        @endif
        @if(!empty($filters['search']))
            <a href="{{ route($filterRoute, request()->except('search', 'page')) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-[10px] font-bold text-slate-600 hover:bg-slate-100">
                <span>Clear Search: "{{ $filters['search'] }}"</span>
                <i data-lucide="x" class="h-3 w-3"></i>
            </a>
        @endif
    </div>
    <details class="mt-3 border-t border-slate-100 pt-3" @if($filters['category_id'] || $filters['product_id'] || $filters['purchaser_id'] || $filters['vendor_id'] || $filters['grade']) open @endif>
        <summary class="cursor-pointer text-xs font-black text-emerald-700">More Filters</summary>
        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            <label class="text-[10px] font-black uppercase text-slate-500">Category
                <select name="category_id" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs">
                    <option value="">All Categories</option>
                    @foreach($options['categories'] as $option)
                        <option value="{{ $option->id }}" @selected($filters['category_id'] === (int) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-[10px] font-black uppercase text-slate-500">Product
                <select name="product_id" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs">
                    <option value="">All Products</option>
                    @foreach($options['products'] as $option)
                        <option value="{{ $option->id }}" @selected($filters['product_id'] === (int) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-[10px] font-black uppercase text-slate-500">Purchaser
                <select name="purchaser_id" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs">
                    <option value="">All Purchasers</option>
                    @foreach($options['purchasers'] as $option)
                        <option value="{{ $option->id }}" @selected($filters['purchaser_id'] === (int) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-[10px] font-black uppercase text-slate-500">Vendor
                <select name="vendor_id" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs">
                    <option value="">All Vendors</option>
                    @foreach($options['vendors'] as $option)
                        <option value="{{ $option->id }}" @selected($filters['vendor_id'] === (int) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-[10px] font-black uppercase text-slate-500">Grade
                <select name="grade" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs">
                    <option value="">All Grades</option>
                    <option value="A" @selected($filters['grade'] === 'A')>A</option>
                    <option value="B" @selected($filters['grade'] === 'B')>B</option>
                </select>
            </label>
        </div>
    </details>
</form>
