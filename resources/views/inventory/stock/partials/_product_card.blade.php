@php
    $firstEntry = $grades->first();
    $productName = $firstEntry->product_name;
    $productImage = $firstEntry->product_image;
    $productRouteKey = $firstEntry->product_route_key;
    $productUnit = $firstEntry->product_unit ?: 'kg';
    $totalStock = $grades->sum(fn ($e) => (float) $e->current_stock);
    $sortedStock = (float) $grades->reject(fn ($e) => in_array($e->grade, ['Unsorted', 'U'], true))->sum('current_stock');
    $unsortedStock = (float) $grades->filter(fn ($e) => in_array($e->grade, ['Unsorted', 'U'], true))->sum('current_stock');
    $bufferQty = (float) ($firstEntry->buffer_qty ?? 0);
    $isBelowBuffer = $bufferQty > 0 && $totalStock < $bufferQty;
    $isNegative = $totalStock < -0.001;
    $showActions = $allowInventoryActions ?? true;
    $cardLayout = $layout ?? 'card';
@endphp
@if($cardLayout === 'modal')
<div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
    {{-- Left Column: Compact Image, Total Stock, Buffer, Sorted/Mixed, Grades --}}
    <div class="lg:col-span-6 space-y-4">
        <div class="flex flex-col sm:flex-row gap-4 items-start rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 p-3.5">
            <div class="relative h-28 w-28 shrink-0 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden flex items-center justify-center">
                @if($productImage)
                    <img src="{{ asset('storage/' . $productImage) }}" loading="lazy" class="w-full h-full object-cover" alt="{{ $productName }}">
                @else
                    <svg class="w-9 h-9 stroke-[1.5] text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                    </svg>
                @endif
                @if($isNegative)
                    <span class="absolute top-1.5 left-1.5 px-1.5 py-0.5 rounded bg-rose-600/95 text-white text-[9px] font-black uppercase tracking-wider">
                        Negative Stock
                    </span>
                @elseif($isBelowBuffer)
                    <span class="absolute top-1.5 left-1.5 px-1.5 py-0.5 rounded bg-amber-500/95 text-white text-[9px] font-black uppercase tracking-wider">
                        Below Buffer
                    </span>
                @endif
            </div>

            <div class="min-w-0 flex-1 space-y-2">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Current Total Stock</p>
                    <div class="mt-0.5 flex items-baseline gap-1.5">
                        <span class="font-mono text-2xl font-black {{ $isNegative ? 'text-rose-600 dark:text-rose-400' : ($isBelowBuffer ? 'text-amber-600 dark:text-amber-400' : 'text-slate-950 dark:text-white') }}">
                            {{ number_format($totalStock, 2) }}
                        </span>
                        <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $productUnit }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-slate-200/80 dark:border-slate-700 text-xs">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Buffer Target</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200 font-mono text-[11px]">
                        {{ $bufferQty > 0 ? number_format($bufferQty, 2) . ' ' . $productUnit : 'Not set' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-2xl {{ $sortedStock < -0.001 ? 'bg-rose-50 border border-rose-200 dark:bg-rose-950/40 dark:border-rose-800' : 'bg-emerald-50 border border-emerald-200 dark:bg-emerald-950/40 dark:border-emerald-800' }} px-3.5 py-2.5 text-center">
                <p class="text-[10px] font-black uppercase tracking-wider {{ $sortedStock < -0.001 ? 'text-rose-700 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400' }}">Sorted</p>
                <p class="mt-0.5 font-mono text-sm font-black {{ $sortedStock < -0.001 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-950 dark:text-emerald-200' }}">{{ number_format($sortedStock, 2) }} <span class="text-[10px]">{{ $productUnit }}</span></p>
            </div>
            <div class="rounded-2xl {{ $unsortedStock < -0.001 ? 'bg-rose-50 border border-rose-200 dark:bg-rose-950/40 dark:border-rose-800' : 'bg-slate-100 border border-slate-200 dark:bg-slate-800 dark:border-slate-700' }} px-3.5 py-2.5 text-center">
                <p class="text-[10px] font-black uppercase tracking-wider {{ $unsortedStock < -0.001 ? 'text-rose-700 dark:text-rose-400' : 'text-slate-600 dark:text-slate-400' }}">Mixed</p>
                <p class="mt-0.5 font-mono text-sm font-black {{ $unsortedStock < -0.001 ? 'text-rose-700 dark:text-rose-300' : 'text-slate-900 dark:text-slate-100' }}">{{ number_format($unsortedStock, 2) }} <span class="text-[10px]">{{ $productUnit }}</span></p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-3.5 space-y-2">
            <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-wider text-slate-400">
                <span>Grades</span>
                <span>Stock Qty</span>
            </div>
            <div class="space-y-1.5">
                @foreach($grades as $entry)
                @php
                    $entryQty = (float) $entry->current_stock;
                    $color = match($entry->grade) {
                        'A' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
                        'B' => 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800',
                        'C' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800',
                        'D' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800',
                        default => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
                    };
                    $gradeLabel = in_array($entry->grade, ['Unsorted', 'U', 'MIXED'], true) ? 'Mixed / Unsegregated' : 'Grade ' . $entry->grade;
                @endphp
                <div class="flex items-center justify-between px-3 py-2 rounded-xl border text-xs {{ $color }}">
                    <span class="text-[11px] font-extrabold uppercase tracking-tight">{{ $gradeLabel }}</span>
                    <span class="font-mono font-black text-xs {{ $entryQty < -0.001 ? 'text-rose-600 dark:text-rose-400' : '' }}">{{ number_format($entryQty, 2) }} <span class="text-[10px] font-bold opacity-75 font-sans">{{ $productUnit }}</span></span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Right Column: Independently Scrollable Shop Allocations --}}
    <div class="lg:col-span-6 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 flex flex-col">
        <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-wider text-slate-400 pb-3 border-b border-slate-100 dark:border-slate-800 shrink-0">
            <span>Shop Allocations</span>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-mono">{{ $prodAllocations->count() }}</span>
        </div>

        @if($prodAllocations->isEmpty())
            <p class="text-xs font-medium text-slate-400 italic py-8 text-center">No dispatches today</p>
        @else
            <div class="mt-3 max-h-72 lg:max-h-[58vh] overflow-y-auto custom-scrollbar space-y-2 pr-1">
                @foreach($prodAllocations as $allocItem)
                    @php
                        $statusBadge = match($allocItem->sorting_status) {
                            'loaded' => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800',
                            'allocated' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
                            default => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800',
                        };
                    @endphp
                    <div class="flex items-center justify-between gap-2 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700 text-xs">
                        <div class="min-w-0 flex-1">
                            <p class="font-extrabold text-slate-800 dark:text-slate-100 truncate" title="{{ $allocItem->order->shop ? $allocItem->order->shop->name : 'N/A' }}">
                                {{ $allocItem->order->shop ? $allocItem->order->shop->name : 'N/A' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="font-mono font-black text-slate-900 dark:text-slate-100">{{ number_format((float) $allocItem->approved_qty, 2) }} <span class="text-[10px] font-bold text-slate-400">{{ $allocItem->unit }}</span></span>
                            <span class="inline-flex items-center text-[9px] font-black uppercase px-2 py-0.5 rounded-md border {{ $statusBadge }}">
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
@else
<div class="group bg-white dark:bg-slate-900 rounded-2xl border border-gray-200 dark:border-slate-800 shadow-xs hover:shadow-md hover:border-brand-300 transition-all duration-200 flex flex-col justify-between overflow-hidden h-full">

    {{-- Square Image Header --}}
    <div class="relative aspect-square w-full bg-slate-50 dark:bg-slate-800/60 overflow-hidden flex items-center justify-center p-3 border-b border-gray-100 dark:border-slate-800">
        @if($productImage)
            <img src="{{ asset('storage/' . $productImage) }}" class="w-full h-full object-cover rounded-xl group-hover:scale-105 transition-transform duration-300" alt="{{ $productName }}">
        @else
            <div class="w-full h-full rounded-xl bg-gradient-to-br from-slate-100 to-slate-200/70 dark:from-slate-800 dark:to-slate-700/70 flex flex-col items-center justify-center text-slate-400 group-hover:scale-105 transition-transform duration-300">
                <svg class="w-10 h-10 stroke-[1.5] text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                </svg>
            </div>
        @endif

        @if($showActions)
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
        @endif

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
            <span class="text-[10px] font-bold text-slate-300">{{ $productUnit }}</span>
        </div>

        @if($showActions)
            @can('inventory.stock.adjust')
                <button
                    type="button"
                    onclick="openStockAdjustmentModal('{{ $productRouteKey }}', @js($productName), {{ number_format($totalStock, 3, '.', '') }}, {{ $selectedWarehouseId ? $selectedWarehouseId : 'null' }}, @js($productUnit))"
                    class="absolute bottom-2.5 left-2.5 rounded-xl bg-white/95 px-2.5 py-1 text-[10px] font-black text-slate-700 shadow-sm ring-1 ring-slate-900/10 backdrop-blur-sm transition hover:bg-brand-600 hover:text-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                >
                    Update Qty
                </button>
            @endcan
        @endif
    </div>

    {{-- Card Body --}}
    <div class="p-4 flex-1 flex flex-col justify-between space-y-3 bg-white dark:bg-slate-900">
        <div>
            <h3 class="text-sm font-black text-slate-900 dark:text-slate-100 group-hover:text-brand-600 transition-colors line-clamp-1" title="{{ $productName }}">
                {{ $productName }}
            </h3>

            <div class="mt-1 flex items-center justify-between text-xs">
                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Buffer Target</span>
                <span class="font-bold text-slate-700 dark:text-slate-200 font-mono text-[11px]">
                    {{ $bufferQty > 0 ? number_format($bufferQty, 2) . ' ' . $productUnit : 'Not set' }}
                </span>
            </div>
        </div>

        {{-- Grade Breakdown --}}
        <div class="pt-2.5 border-t border-gray-100 dark:border-slate-800">
            <div class="mb-2 grid grid-cols-2 gap-2">
                <div class="rounded-lg {{ $sortedStock < -0.001 ? 'bg-rose-50 dark:bg-rose-950/40' : 'bg-emerald-50 dark:bg-emerald-950/40' }} px-2.5 py-2 text-center">
                    <p class="text-[9px] font-black uppercase tracking-wider {{ $sortedStock < -0.001 ? 'text-rose-700 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400' }}">Sorted</p>
                    <p class="mt-0.5 font-mono text-xs font-black {{ $sortedStock < -0.001 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-950 dark:text-emerald-200' }}">{{ number_format($sortedStock, 2) }} <span class="text-[9px]">{{ $productUnit }}</span></p>
                </div>
                <div class="rounded-lg {{ $unsortedStock < -0.001 ? 'bg-rose-50 dark:bg-rose-950/40' : 'bg-slate-100 dark:bg-slate-800' }} px-2.5 py-2 text-center">
                    <p class="text-[9px] font-black uppercase tracking-wider {{ $unsortedStock < -0.001 ? 'text-rose-700 dark:text-rose-400' : 'text-slate-600 dark:text-slate-400' }}">Mixed</p>
                    <p class="mt-0.5 font-mono text-xs font-black {{ $unsortedStock < -0.001 ? 'text-rose-700 dark:text-rose-300' : 'text-slate-900 dark:text-slate-100' }}">{{ number_format($unsortedStock, 2) }} <span class="text-[9px]">{{ $productUnit }}</span></p>
                </div>
            </div>
            <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">
                <span>Grades</span>
                <span>Stock Qty</span>
            </div>
            <div class="space-y-1">
                @foreach($grades as $entry)
                @php
                    $entryQty = (float) $entry->current_stock;
                    $color = match($entry->grade) {
                        'A' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
                        'B' => 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800',
                        'C' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800',
                        'D' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800',
                        default => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
                    };
                    $gradeLabel = in_array($entry->grade, ['Unsorted', 'U', 'MIXED'], true) ? 'Mixed / Unsegregated' : 'Grade ' . $entry->grade;
                @endphp
                <div class="flex items-center justify-between px-2 py-1 rounded-lg border text-xs {{ $color }}">
                    <span class="text-[10px] font-extrabold uppercase tracking-tight">{{ $gradeLabel }}</span>
                    <div class="flex items-center gap-1.5">
                        <span class="font-mono font-black text-[11px] {{ $entryQty < -0.001 ? 'text-rose-600 dark:text-rose-400' : '' }}">{{ number_format($entryQty, 2) }} <span class="text-[9px] font-bold opacity-75 font-sans">{{ $productUnit }}</span></span>
                        @if($showActions)
                            @can('inventory.stock.adjust')
                                <button
                                    type="button"
                                    onclick="event.stopPropagation(); openStockAdjustmentModal('{{ $productRouteKey }}', @js($productName), {{ number_format($entryQty, 3, '.', '') }}, {{ $selectedWarehouseId ? $selectedWarehouseId : 'null' }}, @js($productUnit), '{{ $entry->grade }}')"
                                    class="rounded px-1 text-[9px] font-bold text-slate-500 hover:bg-white hover:text-slate-900 transition shadow-2xs"
                                    title="Update {{ $gradeLabel }} Qty"
                                >
                                    Edit
                                </button>
                            @endcan
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Shop Allocations --}}
        <div class="pt-2.5 border-t border-gray-100 dark:border-slate-800">
            <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">
                <span>Shop Allocations</span>
                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-mono">{{ $prodAllocations->count() }}</span>
            </div>
            @if($prodAllocations->isEmpty())
                <p class="text-[11px] font-medium text-slate-400 italic py-1">No dispatches today</p>
            @else
                <div class="max-h-24 overflow-y-auto space-y-1.5 pr-1 scrollbar-thin scrollbar-thumb-slate-200">
                    @foreach($prodAllocations as $allocItem)
                        @php
                            $statusBadge = match($allocItem->sorting_status) {
                                'loaded' => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800',
                                'allocated' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
                                default => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800',
                            };
                        @endphp
                        <div class="flex items-center justify-between gap-1.5 p-1.5 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700 text-[11px]">
                            <div class="min-w-0 flex-1">
                                <p class="font-extrabold text-slate-700 dark:text-slate-200 truncate" title="{{ $allocItem->order->shop ? $allocItem->order->shop->name : 'N/A' }}">
                                    {{ $allocItem->order->shop ? $allocItem->order->shop->name : 'N/A' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <span class="font-mono font-black text-slate-800 dark:text-slate-100">{{ number_format((float) $allocItem->approved_qty, 2) }}</span>
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
@endif
