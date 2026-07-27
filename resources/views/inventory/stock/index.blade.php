<x-layouts.inventory title="Stock Levels">

    <x-slot:actions>
        <div class="flex items-center gap-2">
            <a href="{{ route('inventory.stock.index', ['date' => \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d')]) }}" class="p-2 bg-white rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors shadow-xs" title="Previous Day">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
            </a>
            <form id="date-form" method="GET" action="{{ route('inventory.stock.index') }}" class="flex items-center gap-2">
                <input id="date-select" type="date" name="date" value="{{ $date }}" onchange="document.getElementById('date-form').submit();"
                       class="border border-gray-200 rounded-xl px-3 py-1.5 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white shadow-xs">
            </form>
            <a href="{{ route('inventory.stock.index', ['date' => \Carbon\Carbon::parse($date)->addDay()->format('Y-m-d')]) }}" class="p-2 bg-white rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors shadow-xs" title="Next Day">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            </a>
            <a href="{{ route('inventory.stock.index', ['date' => \Carbon\Carbon::today()->format('Y-m-d')]) }}" class="px-3 py-1.5 bg-brand-50 text-brand-700 border border-brand-200 rounded-xl text-xs font-bold hover:bg-brand-100 transition-colors shadow-xs">
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
        $byProduct = $stock->groupBy('product_id');

        $gradeColors = [
            'A' => 'bg-green-50 text-green-700 border border-green-200',
            'B' => 'bg-blue-50 text-blue-700 border border-blue-200',
            'C' => 'bg-amber-50 text-amber-700 border border-amber-200',
            'D' => 'bg-red-50 text-red-600 border border-red-200',
            'Unsorted' => 'bg-slate-100 text-slate-700 border border-slate-200',
        ];
    @endphp

    <div class="space-y-4">
        @foreach($byProduct as $productId => $grades)
        @php
            $firstEntry = $grades->first();
            $productName = $firstEntry->product_name;
            $productImage = $firstEntry->product_image;
            $totalStock  = $grades->sum(fn ($e) => (float) $e->current_stock);
            $bufferQty = (float) ($firstEntry->buffer_qty ?? 0);
            $isBelowBuffer = $bufferQty > 0 && $totalStock < $bufferQty;
            $isNegative = $totalStock < -0.001;
        @endphp
        <div class="bg-white rounded-3xl border border-gray-200 overflow-hidden shadow-sm flex flex-col justify-between">
            
            {{-- Card Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-slate-50/50">
                <div class="flex items-center gap-3">
                    @if($productImage)
                        <img src="{{ asset('storage/' . $productImage) }}" class="w-8 h-8 rounded-lg object-cover shrink-0" alt="{{ $productName }}">
                    @else
                        <div class="w-8 h-8 rounded-lg bg-brand-100 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                            </svg>
                        </div>
                    @endif
                    <p class="text-sm font-black text-slate-800 tracking-tight">{{ $productName }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-black {{ $isNegative ? 'text-rose-700' : ($isBelowBuffer ? 'text-amber-700' : 'text-slate-800') }}">
                        {{ number_format($totalStock, 2) }} <span class="text-xs font-bold text-slate-400">kg total stock</span>
                    </p>
                    @if($bufferQty > 0)
                        <p class="mt-1 text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Buffer {{ number_format($bufferQty, 2) }} kg</p>
                    @endif
                </div>
            </div>

            {{-- Card Body: Grade Breakdown --}}
            <div class="grid grid-cols-1 sm:grid-cols-{{ min($grades->count(), 5) }} divide-y sm:divide-y-0 sm:divide-x divide-gray-100 bg-white border-b border-gray-100">
                @foreach($grades as $entry)
                @php
                    $color = $gradeColors[$entry->grade] ?? 'bg-gray-50 text-gray-700 border border-gray-200';
                @endphp
                <div class="px-6 py-4 flex items-center justify-between">
                    <span class="inline-flex items-center text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg {{ $color }}">
                        {{ $entry->grade === 'Unsorted' ? 'Unsorted / Pending' : 'Grade ' . $entry->grade }}
                    </span>
                    <span class="text-lg font-black text-slate-800 font-mono">
                        {{ number_format((float) $entry->current_stock, 2) }}
                        <span class="text-xs font-bold text-slate-400 font-sans">kg</span>
                    </span>
                </div>
                @endforeach
            </div>

            {{-- Card Footer: Daily Shop Dispatches & Allocations --}}
            @php
                $prodAllocations = ($allocations->get($productId) ?? collect())->sortBy('order.shop.name');
            @endphp
            <div class="px-6 py-4 bg-slate-50/20">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-wider">
                        Today's Shop Allocations & Load Info ({{ \Carbon\Carbon::parse($date)->format('M d, Y') }})
                    </h4>
                    <span class="text-[9px] font-bold text-slate-400">
                        {{ $prodAllocations->count() }} dispatches
                    </span>
                </div>
                @if($prodAllocations->isEmpty())
                    <p class="text-xs text-slate-400 font-semibold italic">No shop allocations recorded on this date.</p>
                @else
                    <div class="flex flex-wrap gap-2.5">
                        @foreach($prodAllocations as $allocItem)
                            @php
                                $statusBadge = match($allocItem->sorting_status) {
                                    'loaded' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                    'allocated' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    default => 'bg-amber-50 text-amber-700 border-amber-200',
                                };
                            @endphp
                            <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200/60 px-3 py-1 bg-white text-xs font-bold shadow-xs">
                                <span class="text-slate-600 font-extrabold">{{ $allocItem->order->shop ? $allocItem->order->shop->name : 'N/A' }}</span>
                                <span class="text-slate-800 font-mono font-black">{{ number_format((float) $allocItem->approved_qty, 2) }} <span class="text-[10px] text-slate-400 font-sans font-bold">{{ $allocItem->unit }}</span></span>
                                <span class="inline-flex items-center text-[9px] font-black uppercase px-1.5 py-0.5 rounded-md border {{ $statusBadge }}">
                                    @if($allocItem->sorting_status === 'loaded')
                                        🚚 Loaded
                                    @elseif($allocItem->sorting_status === 'allocated')
                                        ✓ Allocated
                                    @else
                                        ⌛ Pending
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
        @endforeach
    </div>
    @endif

</x-layouts.inventory>
