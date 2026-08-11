<x-layouts.inventory title="Stock Levels">

    <x-slot:actions>
        <div class="flex items-center gap-2">
            <a href="{{ route('inventory.stock.index', array_merge(request()->except(['page', 'date']), ['date' => \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d')])) }}" class="p-2 bg-white rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors shadow-xs" title="Previous Day">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
            </a>
            <form id="date-form" method="GET" action="{{ route('inventory.stock.index') }}" class="flex items-center gap-2">
                @if($search !== '') <input type="hidden" name="search" value="{{ $search }}"> @endif
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <input id="date-select" type="date" name="date" value="{{ $date }}" onchange="document.getElementById('date-form').submit();"
                       class="border border-gray-200 rounded-xl px-3 py-1.5 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white shadow-xs">
            </form>
            <a href="{{ route('inventory.stock.index', array_merge(request()->except(['page', 'date']), ['date' => \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d')])) }}" class="p-2 bg-white rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors shadow-xs" title="Next Day">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            </a>
            <a href="{{ route('inventory.stock.index', array_merge(request()->except(['page', 'date']), ['date' => \Carbon\Carbon::today()->format('Y-m-d')])) }}" class="px-3 py-1.5 bg-brand-50 text-brand-700 border border-brand-200 rounded-xl text-xs font-bold hover:bg-brand-100 transition-colors shadow-xs">
                Today
            </a>
            <a href="{{ route('inventory.batches.create') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-700 transition-colors shadow-sm">
                + Receive Batch
            </a>
            <a href="{{ route('inventory.daily-close.index', ['date' => $date]) }}"
               class="inline-flex items-center gap-2 rounded-xl bg-slate-950 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800 transition-colors shadow-sm">
                Daily Close
            </a>
        </div>
    </x-slot:actions>

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <a href="{{ route('inventory.daily-close.index', ['date' => $date]) }}" class="rounded-3xl border border-rose-200 bg-rose-50 p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-rose-700">Negative Stock</p>
            <p class="mt-2 text-3xl font-black text-rose-950">{{ $negativeProductCount }}</p>
            <p class="mt-1 text-xs font-bold text-rose-700">products need discrepancy notes or replenishment.</p>
        </a>
        <a href="{{ route('inventory.daily-close.index', ['date' => $date]) }}" class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-amber-700">Below Buffer</p>
            <p class="mt-2 text-3xl font-black text-amber-950">{{ $belowBufferProductCount }}</p>
            <p class="mt-1 text-xs font-bold text-amber-700">products are below configured buffer level.</p>
        </a>
        <a href="{{ route('inventory.daily-close.index', ['date' => $date]) }}" class="rounded-3xl border border-cyan-200 bg-cyan-50 p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-cyan-700">Carryover Enabled</p>
            <p class="mt-2 text-3xl font-black text-cyan-950">{{ $carryoverProductCount }}</p>
            <p class="mt-1 text-xs font-bold text-cyan-700">products can be retained during daily close.</p>
        </a>
    </div>

    @if($showAdjustmentTotals)
    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">Counted wastage</p>
            <p class="mt-1 text-2xl font-black text-rose-950">{{ number_format((float) $adjustmentTotals->wastage_qty, 3) }} <span class="text-sm">kg</span></p>
        </div>
        <div class="rounded-2xl border border-cyan-200 bg-cyan-50 px-5 py-4 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">Old stock added</p>
            <p class="mt-1 text-2xl font-black text-cyan-950">{{ number_format((float) $adjustmentTotals->old_stock_qty, 3) }} <span class="text-sm">kg</span></p>
        </div>
    </div>
    @endif

    @php
        $stockTabQuery = request()->except(['page', 'warehouse_id', 'category_id']);
    @endphp
    <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Warehouse</p>
        <div class="mt-2 flex gap-2 overflow-x-auto pb-1">
            <a href="{{ route('inventory.stock.index', array_merge($stockTabQuery, ['warehouse_id' => null, 'category_id' => $selectedCategoryId])) }}" @class([
                'shrink-0 rounded-xl px-3 py-2 text-xs font-black transition',
                'bg-slate-950 text-white' => ! $selectedWarehouseId,
                'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' => $selectedWarehouseId,
            ])>All warehouses</a>
            @foreach($warehouses as $warehouse)
                <a href="{{ route('inventory.stock.index', array_merge($stockTabQuery, ['warehouse_id' => $warehouse->id, 'category_id' => $selectedCategoryId])) }}" @class([
                    'shrink-0 rounded-xl px-3 py-2 text-xs font-black transition',
                    'bg-brand-600 text-white shadow-sm' => $selectedWarehouseId === $warehouse->id,
                    'border border-slate-200 bg-white text-slate-600 hover:border-brand-200 hover:bg-brand-50' => $selectedWarehouseId !== $warehouse->id,
                ])>{{ $warehouse->name }} <span class="opacity-70">{{ $warehouse->code }}</span></a>
            @endforeach
        </div>
    </div>

    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Product category</p>
        <div class="mt-2 flex gap-2 overflow-x-auto pb-1">
            <a href="{{ route('inventory.stock.index', array_merge($stockTabQuery, ['warehouse_id' => $selectedWarehouseId, 'category_id' => null])) }}" @class([
                'shrink-0 rounded-xl px-3 py-2 text-xs font-black transition',
                'bg-slate-950 text-white' => ! $selectedCategoryId,
                'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' => $selectedCategoryId,
            ])>All categories</a>
            @foreach($categories as $category)
                <a href="{{ route('inventory.stock.index', array_merge($stockTabQuery, ['warehouse_id' => $selectedWarehouseId, 'category_id' => $category->id])) }}" @class([
                    'shrink-0 rounded-xl px-3 py-2 text-xs font-black transition',
                    'bg-emerald-600 text-white shadow-sm' => $selectedCategoryId === $category->id,
                    'border border-slate-200 bg-white text-slate-600 hover:border-emerald-200 hover:bg-emerald-50' => $selectedCategoryId !== $category->id,
                ])>{{ $category->name }}</a>
            @endforeach
        </div>
    </div>

    <form method="GET" action="{{ route('inventory.stock.index') }}" class="mb-6 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs sm:flex-row sm:items-end">
        <input type="hidden" name="date" value="{{ $date }}">
        @if($selectedWarehouseId) <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}"> @endif
        @if($selectedCategoryId) <input type="hidden" name="category_id" value="{{ $selectedCategoryId }}"> @endif
        <label class="block flex-1">
            <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Search products</span>
            <input type="search" name="search" value="{{ $search }}" placeholder="Name, SKU or category" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
        </label>
        <label class="block sm:w-48">
            <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Sort by</span>
            <select name="sort" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-900 focus:border-brand-500 focus:outline-none">
                <option value="sku_asc" @selected($sort === 'sku_asc')>SKU: low to high</option>
                <option value="name_asc" @selected($sort === 'name_asc')>Product name: A–Z</option>
                <option value="name_desc" @selected($sort === 'name_desc')>Product name: Z–A</option>
                <option value="stock_high" @selected($sort === 'stock_high')>Stock: high to low</option>
                <option value="stock_low" @selected($sort === 'stock_low')>Stock: low to high</option>
                <option value="below_buffer" @selected($sort === 'below_buffer')>Below buffer first</option>
            </select>
        </label>
        <label class="block sm:w-28">
            <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Per page</span>
            <select name="per_page" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-900 focus:border-brand-500 focus:outline-none">
                @foreach([12, 24, 48] as $option)
                    <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="h-10 rounded-xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">Apply</button>
    </form>

    {{-- Grade legend --}}
    <div class="flex flex-wrap gap-3 mb-6 bg-white border border-gray-200 rounded-2xl p-4 shadow-xs">
        @foreach([
            'A' => ['label' => 'Grade A — Premium', 'color' => 'bg-green-100 text-green-700 border-green-200'],
            'B' => ['label' => 'Grade B — Standard', 'color' => 'bg-blue-100 text-blue-700 border-blue-200'],
            'C' => ['label' => 'Grade C — Economy', 'color' => 'bg-amber-100 text-amber-700 border-amber-200'],
            'D' => ['label' => 'Grade D — Damage', 'color' => 'bg-red-100 text-red-600 border-red-200'],
            'Unsorted' => ['label' => 'Unsorted / Pending', 'color' => 'bg-slate-100 text-slate-700 border-slate-200']
        ] as $grade => $info)
        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-full border {{ $info['color'] }}">
            <span class="w-2 h-2 rounded-full bg-current opacity-60"></span>
            {{ $info['label'] }}
        </span>
        @endforeach
    </div>

    @if($stock->isEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 py-20 text-center">
        <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
            </svg>
        </div>
        <p class="text-sm font-semibold text-gray-900">No stock levels found</p>
        <p class="text-xs text-gray-500 mt-1">Receive stock batches or adjust your date filter for {{ \Carbon\Carbon::parse($date)->format('d M Y') }}.</p>
    </div>
    @else

    @php
        $byProduct = $stock->getCollection();

        $gradeColors = [
            'A' => 'bg-green-50 text-green-700 border border-green-200',
            'B' => 'bg-blue-50 text-blue-700 border border-blue-200',
            'C' => 'bg-amber-50 text-amber-700 border border-amber-200',
            'D' => 'bg-red-50 text-red-600 border border-red-200',
            'Unsorted' => 'bg-slate-100 text-slate-700 border border-slate-200',
        ];
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
        @foreach($byProduct as $productId => $grades)
        @php
            $firstEntry = $grades->first();
            $productName = $firstEntry->product_name;
            $productImage = $firstEntry->product_image;
            $productRouteKey = $firstEntry->product_route_key;
            $totalStock  = $grades->sum(fn ($e) => (float) $e->current_stock);
            $bufferQty = (float) ($firstEntry->buffer_qty ?? 0);
            $isBelowBuffer = $bufferQty > 0 && $totalStock < $bufferQty;
            $isNegative = $totalStock < -0.001;
            $prodAllocations = ($allocations->get($productId) ?? collect())->sortBy('order.shop.name');
        @endphp
        <div class="group bg-white rounded-2xl border border-gray-200 shadow-xs hover:shadow-md hover:border-brand-300 transition-all duration-200 flex flex-col justify-between overflow-hidden h-full">
            
            {{-- Square Image Header --}}
            <div class="relative aspect-square w-full bg-slate-50 overflow-hidden flex items-center justify-center p-3 border-b border-gray-100">
                @if($productImage)
                    <img src="{{ asset('storage/' . $productImage) }}" class="w-full h-full object-cover rounded-xl group-hover:scale-105 transition-transform duration-300" alt="{{ $productName }}">
                @else
                    <div class="w-full h-full rounded-xl bg-gradient-to-br from-slate-100 to-slate-200/70 flex flex-col items-center justify-center text-slate-400 group-hover:scale-105 transition-transform duration-300">
                        <svg class="w-10 h-10 stroke-[1.5] text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                        </svg>
                    </div>
                @endif

                @can('inventory.product.update')
                    <a
                        href="{{ route('inventory.products.edit', ['product' => $productRouteKey]) }}#product-image-upload"
                        class="absolute top-2.5 right-2.5 inline-flex items-center gap-1.5 rounded-lg bg-white/95 px-2 py-1.5 text-[10px] font-black text-slate-700 shadow-sm ring-1 ring-slate-900/10 backdrop-blur-sm transition hover:bg-brand-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                        title="Change {{ $productName }} image"
                        aria-label="Change {{ $productName }} image"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V12m0 0V7.5m0 4.5h4.5M12 12H7.5m12.75 0a8.25 8.25 0 1 1-16.5 0 8.25 8.25 0 0 1 16.5 0Z" />
                        </svg>
                        Image
                    </a>
                @endcan

                {{-- Status Badges Overlay --}}
                @if($isNegative)
                    <span class="absolute top-2.5 left-2.5 px-2 py-0.5 rounded-lg bg-rose-600/90 text-white text-[10px] font-black uppercase tracking-wider backdrop-blur-xs shadow-xs">
                        Negative Stock
                    </span>
                @elseif($isBelowBuffer)
                    <span class="absolute top-2.5 left-2.5 px-2 py-0.5 rounded-lg bg-amber-500/90 text-white text-[10px] font-black uppercase tracking-wider backdrop-blur-xs shadow-xs">
                        Below Buffer
                    </span>
                @endif

                {{-- Total Stock Pill Overlay --}}
                <div class="absolute bottom-2.5 right-2.5 px-2.5 py-1 rounded-xl bg-slate-950/85 backdrop-blur-md text-white shadow-xs">
                    <span class="text-xs font-black font-mono {{ $isNegative ? 'text-rose-400' : ($isBelowBuffer ? 'text-amber-300' : 'text-white') }}">
                        {{ number_format($totalStock, 2) }}
                    </span>
                    <span class="text-[10px] font-bold text-slate-300">kg</span>
                </div>

                @can('inventory.stock.adjust')
                    @if(! $selectedWarehouseId)
                    <button
                        type="button"
                        onclick="openStockAdjustmentModal('{{ $productRouteKey }}', @js($productName), {{ number_format($totalStock, 3, '.', '') }})"
                        class="absolute bottom-2.5 left-2.5 rounded-xl bg-white/95 px-2.5 py-1 text-[10px] font-black text-slate-700 shadow-sm ring-1 ring-slate-900/10 backdrop-blur-sm transition hover:bg-brand-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                    >
                        Update Qty
                    </button>
                    @endif
                @endcan
            </div>

            {{-- Card Body --}}
            <div class="p-4 flex-1 flex flex-col justify-between space-y-3 bg-white">
                <div>
                    <h3 class="text-sm font-black text-slate-900 group-hover:text-brand-600 transition-colors line-clamp-1" title="{{ $productName }}">
                        {{ $productName }}
                    </h3>
                    
                    <div class="mt-1 flex items-center justify-between text-xs">
                        <span class="text-[11px] font-semibold text-slate-500">Buffer Target</span>
                        <span class="font-bold text-slate-700 font-mono text-[11px]">
                            {{ $bufferQty > 0 ? number_format($bufferQty, 2) . ' kg' : 'Not set' }}
                        </span>
                    </div>
                </div>

                {{-- Grade Breakdown --}}
                <div class="pt-2.5 border-t border-gray-100">
                    <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">
                        <span>Grades</span>
                        <span>Stock Qty</span>
                    </div>
                    <div class="space-y-1">
                        @foreach($grades as $entry)
                        @php
                            $color = match($entry->grade) {
                                'A' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'B' => 'bg-sky-50 text-sky-700 border-sky-200',
                                'C' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'D' => 'bg-rose-50 text-rose-700 border-rose-200',
                                default => 'bg-slate-100 text-slate-700 border-slate-200',
                            };
                            $gradeLabel = $entry->grade === 'Unsorted' ? 'Unsorted' : 'Grade ' . $entry->grade;
                        @endphp
                        <div class="flex items-center justify-between px-2 py-1 rounded-lg border text-xs {{ $color }}">
                            <span class="text-[10px] font-extrabold uppercase tracking-tight">{{ $gradeLabel }}</span>
                            <span class="font-mono font-black text-[11px]">{{ number_format((float) $entry->current_stock, 2) }} <span class="text-[9px] font-bold opacity-75 font-sans">kg</span></span>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Shop Allocations --}}
                <div class="pt-2.5 border-t border-gray-100">
                    <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">
                        <span>Shop Allocations</span>
                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-600 font-mono">{{ $prodAllocations->count() }}</span>
                    </div>
                    @if($prodAllocations->isEmpty())
                        <p class="text-[11px] font-medium text-slate-400 italic py-1">No dispatches today</p>
                    @else
                        <div class="max-h-24 overflow-y-auto space-y-1.5 pr-1 scrollbar-thin scrollbar-thumb-slate-200">
                            @foreach($prodAllocations as $allocItem)
                                @php
                                    $statusBadge = match($allocItem->sorting_status) {
                                        'loaded' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                        'allocated' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        default => 'bg-amber-50 text-amber-700 border-amber-200',
                                    };
                                @endphp
                                <div class="flex items-center justify-between gap-1.5 p-1.5 rounded-lg bg-slate-50 border border-slate-100 text-[11px]">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-extrabold text-slate-700 truncate" title="{{ $allocItem->order->shop ? $allocItem->order->shop->name : 'N/A' }}">
                                            {{ $allocItem->order->shop ? $allocItem->order->shop->name : 'N/A' }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0">
                                        <span class="font-mono font-black text-slate-800">{{ number_format((float) $allocItem->approved_qty, 2) }}</span>
                                        <span class="text-[9px] font-bold text-slate-400">{{ $allocItem->unit }}</span>
                                        <span class="inline-flex items-center text-[8px] font-black uppercase px-1 py-0.5 rounded border {{ $statusBadge }}">
                                            @if($allocItem->sorting_status === 'loaded')
                                                Loaded
                                            @elseif($allocItem->sorting_status === 'allocated')
                                                Allocated
                                            @else
                                                Pending
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>
        @endforeach
    </div>
    @if($stock->hasPages())
        <div class="mt-6">{{ $stock->withQueryString()->links() }}</div>
    @endif
    @endif

    <div id="stock-adjustment-modal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="stock-adjustment-title">
            <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 p-5">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-brand-700">Physical stock count</p>
                        <h2 id="stock-adjustment-title" class="mt-1 text-lg font-black text-slate-950"></h2>
                    </div>
                    <button type="button" onclick="closeStockAdjustmentModal()" class="grid h-9 w-9 place-items-center rounded-xl text-xl font-black text-slate-400 hover:bg-slate-100 hover:text-slate-900" aria-label="Close">×</button>
                </div>
                <form id="stock-adjustment-form" method="POST" action="" class="space-y-4 p-5">
                    @csrf
                    <input type="hidden" name="system_qty" id="stock-adjustment-system-qty">
                    <input type="hidden" name="business_date" value="{{ $date }}">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <div class="flex items-center justify-between text-xs font-bold text-slate-500"><span>System quantity</span><span id="stock-adjustment-system-label" class="font-mono text-base font-black text-slate-950"></span></div>
                        <label for="stock-adjustment-counted-qty" class="mt-4 block text-xs font-black text-slate-700">Physical quantity counted (kg)</label>
                        <input id="stock-adjustment-counted-qty" name="counted_qty" type="number" min="0" step="0.001" required oninput="updateStockAdjustmentPreview()" class="mt-1.5 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-right text-sm font-black text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                    </div>
                    <div id="stock-adjustment-preview" class="hidden rounded-2xl border p-3 text-xs font-bold"></div>
                    <div>
                        <label for="stock-adjustment-notes" class="block text-xs font-black text-slate-700">Reason / note</label>
                        <textarea id="stock-adjustment-notes" name="notes" rows="3" required maxlength="1000" placeholder="Explain the physical count difference" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"></textarea>
                    </div>
                    <p class="text-[11px] font-semibold text-slate-500">More stock is recorded as <strong>Old Stock</strong>. Less stock is recorded as <strong>Wastage</strong>.</p>
                    <div class="flex gap-3 pt-1"><button type="button" onclick="closeStockAdjustmentModal()" class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-black text-slate-700 hover:bg-slate-50">Cancel</button><button type="submit" class="flex-1 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-black text-white hover:bg-slate-800">Save adjustment</button></div>
                </form>
            </div>
        </div>
    <script>
            const stockAdjustmentRoute = @json(route('inventory.stock.adjustments.store', ['product' => 'PRODUCT_KEY']));
            function openStockAdjustmentModal(productKey, productName, systemQty) {
                document.getElementById('stock-adjustment-title').textContent = productName;
                document.getElementById('stock-adjustment-system-qty').value = systemQty.toFixed(3);
                document.getElementById('stock-adjustment-system-label').textContent = systemQty.toFixed(3) + ' kg';
                document.getElementById('stock-adjustment-counted-qty').value = systemQty.toFixed(3);
                document.getElementById('stock-adjustment-notes').value = '';
                document.getElementById('stock-adjustment-form').action = stockAdjustmentRoute.replace('PRODUCT_KEY', productKey);
                updateStockAdjustmentPreview();
                document.getElementById('stock-adjustment-modal').classList.remove('hidden');
                document.getElementById('stock-adjustment-modal').classList.add('flex');
                document.getElementById('stock-adjustment-counted-qty').focus();
            }
            function closeStockAdjustmentModal() { const modal = document.getElementById('stock-adjustment-modal'); modal.classList.add('hidden'); modal.classList.remove('flex'); }
            function updateStockAdjustmentPreview() {
                const systemQty = Number(document.getElementById('stock-adjustment-system-qty').value);
                const countedQty = Number(document.getElementById('stock-adjustment-counted-qty').value);
                const preview = document.getElementById('stock-adjustment-preview');
                if (!Number.isFinite(countedQty)) { preview.classList.add('hidden'); return; }
                const difference = countedQty - systemQty;
                if (Math.abs(difference) < 0.001) { preview.classList.remove('hidden'); preview.className = 'rounded-2xl border border-slate-200 bg-slate-50 p-3 text-xs font-bold text-slate-600'; preview.textContent = 'No adjustment needed — physical and system quantities match.'; return; }
                const isExcess = difference > 0;
                preview.classList.remove('hidden'); preview.className = 'rounded-2xl border p-3 text-xs font-bold ' + (isExcess ? 'border-cyan-200 bg-cyan-50 text-cyan-800' : 'border-rose-200 bg-rose-50 text-rose-800');
                preview.textContent = (isExcess ? 'Old Stock: +' : 'Wastage: ') + difference.toFixed(3) + ' kg';
            }
    </script>

</x-layouts.inventory>
