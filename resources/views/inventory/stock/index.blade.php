<x-layouts.app title="Stock Levels">

    <x-slot:actions>
        <a href="{{ route('inventory.batches.create') }}"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
            + Receive Batch
        </a>
    </x-slot:actions>

    {{-- Grade legend --}}
    <div class="flex flex-wrap gap-3 mb-6">
        @foreach(['A' => ['label' => 'Grade A — Premium', 'color' => 'bg-green-100 text-green-700 border-green-200'], 'B' => ['label' => 'Grade B — Standard', 'color' => 'bg-blue-100 text-blue-700 border-blue-200'], 'C' => ['label' => 'Grade C — Economy', 'color' => 'bg-amber-100 text-amber-700 border-amber-200'], 'D' => ['label' => 'Grade D — Damage', 'color' => 'bg-red-100 text-red-600 border-red-200']] as $grade => $info)
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
        <p class="text-sm font-semibold text-gray-900">No stock yet</p>
        <p class="text-xs text-gray-500 mt-1">Receive a batch and sort it to see stock levels here.</p>
        <a href="{{ route('inventory.batches.create') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm text-brand-600 font-medium hover:underline">
            + Receive First Batch
        </a>
    </div>
    @else

    {{--
        Results are plain stdClass objects (from toBase()->get()), so:
        - $entry->grade  is a raw string ('A', 'B', 'C', 'D') — safe as array key
        - $entry->product_name is a string joined from the products table
        - Collection::sum() with a closure works on stdClass
    --}}
    @php
        $byProduct = $stock->groupBy('product_id');

        $gradeColors = [
            'A' => 'bg-green-50 text-green-700',
            'B' => 'bg-blue-50 text-blue-700',
            'C' => 'bg-amber-50 text-amber-700',
            'D' => 'bg-red-50 text-red-600',
        ];
    @endphp

    <div class="space-y-3">
        @foreach($byProduct as $productId => $grades)
        @php
            $firstEntry = $grades->first();
            $productName = $firstEntry->product_name;
            $productImage = $firstEntry->product_image;
            $totalStock  = $grades->sum(fn ($e) => (float) $e->current_stock);
        @endphp
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-50">
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
                    <p class="text-sm font-semibold text-gray-900">{{ $productName }}</p>
                </div>
                <p class="text-sm font-bold text-gray-900">
                    {{ number_format($totalStock, 1) }} <span class="text-xs font-normal text-gray-500">kg total</span>
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-{{ min($grades->count(), 4) }} divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
                @foreach($grades as $entry)
                @php
                    $color = $gradeColors[$entry->grade] ?? 'bg-gray-50 text-gray-700';
                @endphp
                <div class="px-6 py-4 flex items-center justify-between">
                    <span class="inline-flex items-center text-xs font-bold px-2.5 py-1 rounded-lg {{ $color }}">
                        Grade {{ $entry->grade }}
                    </span>
                    <span class="text-lg font-bold text-gray-900">
                        {{ number_format((float) $entry->current_stock, 1) }}
                        <span class="text-xs font-normal text-gray-500">kg</span>
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    @endif

</x-layouts.app>
