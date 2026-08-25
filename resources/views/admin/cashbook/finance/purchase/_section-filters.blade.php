@php
    $selectedProductFilter = $filters['product_filter'] ?? null;
    $sectionRoute = 'admin.cashbook.finance.purchase.'.$section;
@endphp
@if($section === 'invoices')
    @php
        $periodBaseQuery = request()->except(['period', 'start_date', 'end_date', 'page']);
        $chipQuery = request()->except('page');
    @endphp
    <form method="GET" action="{{ route($sectionRoute) }}" class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-3 xl:flex-row xl:flex-wrap xl:items-end">
            @include('admin.cashbook.finance.purchase._period-controls', [
                'periodRoute' => $sectionRoute,
                'periodBaseQuery' => $periodBaseQuery,
            ])

            <label class="text-[10px] font-black uppercase text-slate-500 xl:w-48">Product Filter<select name="product_filter" onchange="this.form.submit()" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold"><option value="">All Products</option>@foreach($productFilters as $filter)<option value="{{ $filter->uuid }}" @selected($selectedProductFilter === $filter->uuid)>{{ $filter->name }}</option>@endforeach</select></label>

            <label class="min-w-0 flex-1 text-[10px] font-black uppercase text-slate-500 xl:min-w-72">Search<span class="mt-1 flex"><input name="search" value="{{ $filters['search'] }}" placeholder="Search invoice, vendor, purchaser, product..." class="min-h-10 min-w-0 flex-1 rounded-l-lg border border-r-0 border-slate-300 px-3 text-xs font-bold"><button type="submit" class="inline-flex min-h-10 w-10 shrink-0 items-center justify-center rounded-r-lg border border-emerald-700 bg-emerald-700 text-white" title="Search" aria-label="Search"><i data-lucide="search" class="h-4 w-4"></i></button></span></label>
        </div>

        <details class="mt-3 border-t border-slate-100 pt-3" @if($filters['purchaser_id'] || $filters['vendor_id'] || $filters['payment'] !== 'all' || $filters['category_ids'] || $filters['grade']) open @endif>
            <summary class="cursor-pointer text-xs font-black text-emerald-700">More Filters</summary>
            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                <label class="text-[10px] font-black uppercase text-slate-500">Purchaser<select name="purchaser_id" onchange="this.form.submit()" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs"><option value="">All Purchasers</option>@foreach($options['purchasers'] as $option)<option value="{{ $option->id }}" @selected($filters['purchaser_id'] === $option->id)>{{ $option->label }}</option>@endforeach</select></label>
                <label class="text-[10px] font-black uppercase text-slate-500">Vendor<select name="vendor_id" onchange="this.form.submit()" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs"><option value="">All Vendors</option>@foreach($options['vendors'] as $option)<option value="{{ $option->id }}" @selected($filters['vendor_id'] === $option->id)>{{ $option->label }}</option>@endforeach</select></label>
                <label class="text-[10px] font-black uppercase text-slate-500">Payment<select name="payment" onchange="this.form.submit()" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs"><option value="all">All</option><option value="cash" @selected($filters['payment'] === 'cash')>Cash</option><option value="credit" @selected($filters['payment'] === 'credit')>Credit</option></select></label>
                <label class="text-[10px] font-black uppercase text-slate-500">Category<select name="category_id" onchange="this.form.submit()" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs"><option value="">All Categories</option>@foreach($options['categories'] as $option)<option value="{{ $option->id }}" @selected(in_array($option->id, $filters['category_ids'], true))>{{ $option->label }}</option>@endforeach</select></label>
                <label class="text-[10px] font-black uppercase text-slate-500">Grade<select name="grade" onchange="this.form.submit()" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs"><option value="">All Grades</option><option value="A" @selected($filters['grade'] === 'A')>A</option><option value="B" @selected($filters['grade'] === 'B')>B</option></select></label>
            </div>
        </details>

        @if($filters['product_filter'] || $filters['purchaser_id'] || $filters['vendor_id'] || $filters['payment'] !== 'all' || $filters['category_ids'] || $filters['grade'])
            <div class="mt-3 flex flex-wrap gap-1.5 border-t border-slate-100 pt-3 text-[10px] font-black text-slate-700">
                @if($filters['product_filter'])<a href="{{ route($sectionRoute, \Illuminate\Support\Arr::except($chipQuery, 'product_filter')) }}" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1">Filter: {{ $productFilters->firstWhere('uuid', $filters['product_filter'])?->name }}<i data-lucide="x" class="h-3 w-3"></i></a>@endif
                @if($filters['purchaser_id'])<a href="{{ route($sectionRoute, \Illuminate\Support\Arr::except($chipQuery, 'purchaser_id')) }}" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1">Purchaser: {{ $options['purchasers']->firstWhere('id', $filters['purchaser_id'])?->label }}<i data-lucide="x" class="h-3 w-3"></i></a>@endif
                @if($filters['vendor_id'])<a href="{{ route($sectionRoute, \Illuminate\Support\Arr::except($chipQuery, 'vendor_id')) }}" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1">Vendor: {{ $options['vendors']->firstWhere('id', $filters['vendor_id'])?->label }}<i data-lucide="x" class="h-3 w-3"></i></a>@endif
                @if($filters['payment'] !== 'all')<a href="{{ route($sectionRoute, \Illuminate\Support\Arr::except($chipQuery, 'payment')) }}" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1">Payment: {{ ucfirst($filters['payment']) }}<i data-lucide="x" class="h-3 w-3"></i></a>@endif
                @foreach($filters['category_ids'] as $categoryId)<a href="{{ route($sectionRoute, \Illuminate\Support\Arr::except($chipQuery, ['category_id', 'category_ids'])) }}" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1">Category: {{ $options['categories']->firstWhere('id', $categoryId)?->label }}<i data-lucide="x" class="h-3 w-3"></i></a>@endforeach
                @if($filters['grade'])<a href="{{ route($sectionRoute, \Illuminate\Support\Arr::except($chipQuery, 'grade')) }}" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1">Grade: {{ $filters['grade'] }}<i data-lucide="x" class="h-3 w-3"></i></a>@endif
            </div>
        @endif
    </form>
@else
<form method="GET" action="{{ route('admin.cashbook.finance.purchase.'.$section) }}" class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-[10rem_12rem_minmax(12rem,1fr)_auto] lg:items-end">
        <label class="text-[10px] font-black uppercase text-slate-500">Period<select name="period" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold">@foreach(['today' => 'Today', 'yesterday' => 'Yesterday', 'week' => 'This Week', 'month' => 'This Month', 'custom' => 'Custom'] as $value => $label)<option value="{{ $value }}" @selected($filters['period'] === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="text-[10px] font-black uppercase text-slate-500">Product Filter<select name="product_filter" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold"><option value="">All Products</option>@foreach($productFilters as $filter)<option value="{{ $filter->uuid }}" @selected($selectedProductFilter === $filter->uuid)>{{ $filter->name }}</option>@endforeach</select></label>
        <label class="text-[10px] font-black uppercase text-slate-500">Search<input name="search" value="{{ $filters['search'] }}" placeholder="Search {{ $section }}..." class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs font-bold"></label>
        <button class="min-h-10 rounded-lg bg-emerald-700 px-4 text-xs font-black text-white">Apply</button>
    </div>
    <details class="mt-3 border-t border-slate-100 pt-3" @if($filters['purchaser_id'] || $filters['vendor_id'] || $filters['payment'] !== 'all' || $filters['category_ids'] || $filters['grade']) open @endif>
        <summary class="cursor-pointer text-xs font-black text-emerald-700">More Filters</summary>
        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            @if(in_array($section, ['vendors', 'categories', 'invoices'], true))<label class="text-[10px] font-black uppercase text-slate-500">Purchaser<select name="purchaser_id" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs"><option value="">All Purchasers</option>@foreach($options['purchasers'] as $option)<option value="{{ $option->id }}" @selected($filters['purchaser_id'] === $option->id)>{{ $option->label }}</option>@endforeach</select></label>@endif
            @if(in_array($section, ['purchasers', 'categories', 'invoices'], true))<label class="text-[10px] font-black uppercase text-slate-500">Vendor<select name="vendor_id" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs"><option value="">All Vendors</option>@foreach($options['vendors'] as $option)<option value="{{ $option->id }}" @selected($filters['vendor_id'] === $option->id)>{{ $option->label }}</option>@endforeach</select></label>@endif
            <label class="text-[10px] font-black uppercase text-slate-500">Payment<select name="payment" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs"><option value="all">All</option><option value="cash" @selected($filters['payment'] === 'cash')>Cash</option><option value="credit" @selected($filters['payment'] === 'credit')>Credit</option></select></label>
            @if($section !== 'categories')<label class="text-[10px] font-black uppercase text-slate-500">Category<select name="category_id" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs"><option value="">All Categories</option>@foreach($options['categories'] as $option)<option value="{{ $option->id }}" @selected(in_array($option->id, $filters['category_ids'], true))>{{ $option->label }}</option>@endforeach</select></label>@endif
            @if($section === 'invoices')<label class="text-[10px] font-black uppercase text-slate-500">Grade<select name="grade" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs"><option value="">All Grades</option><option value="A" @selected($filters['grade'] === 'A')>A</option><option value="B" @selected($filters['grade'] === 'B')>B</option></select></label>@endif
            <label class="text-[10px] font-black uppercase text-slate-500">From<input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs"></label>
            <label class="text-[10px] font-black uppercase text-slate-500">To<input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 text-xs"></label>
        </div>
    </details>
    @if($filters['product_filter'] || $filters['purchaser_id'] || $filters['vendor_id'] || $filters['payment'] !== 'all' || $filters['category_ids'] || $filters['grade'])
        <div class="mt-3 flex flex-wrap gap-1.5 border-t border-slate-100 pt-3 text-[10px] font-black text-slate-700">
            @if($filters['product_filter'])<span class="rounded-full bg-emerald-50 px-2 py-1">Filter: {{ $productFilters->firstWhere('uuid', $filters['product_filter'])?->name }}</span>@endif
            @if($filters['purchaser_id'])<span class="rounded-full bg-emerald-50 px-2 py-1">Purchaser: {{ $options['purchasers']->firstWhere('id', $filters['purchaser_id'])?->label }}</span>@endif
            @if($filters['vendor_id'])<span class="rounded-full bg-emerald-50 px-2 py-1">Vendor: {{ $options['vendors']->firstWhere('id', $filters['vendor_id'])?->label }}</span>@endif
            @if($filters['payment'] !== 'all')<span class="rounded-full bg-emerald-50 px-2 py-1">Payment: {{ ucfirst($filters['payment']) }}</span>@endif
            @foreach($filters['category_ids'] as $categoryId)<span class="rounded-full bg-emerald-50 px-2 py-1">Category: {{ $options['categories']->firstWhere('id', $categoryId)?->label }}</span>@endforeach
            @if($filters['grade'])<span class="rounded-full bg-emerald-50 px-2 py-1">Grade: {{ $filters['grade'] }}</span>@endif
        </div>
    @endif
</form>
@endif
