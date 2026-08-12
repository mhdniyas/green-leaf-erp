@php
/** @var \App\Models\StockBatch $batch */
$statusColors = ['pending' => 'bg-amber-100 text-amber-700', 'sorted' => 'bg-green-100 text-green-700', 'closed' => 'bg-gray-100 text-gray-600'];
$statusColor = $statusColors[$batch->status->value] ?? 'bg-gray-100 text-gray-600';
@endphp

<x-layouts.inventory title="Batch: {{ $batch->reference }}">

    <x-slot:actions>
        @if($batch->canBeSorted())
        <a href="{{ route('inventory.batches.sort', $batch) }}"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
            Sort Batch →
        </a>
        @endif
    </x-slot:actions>

    <div class="max-w-3xl space-y-5">

        {{-- Header card --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-4">
                    @if($batch->product?->image)
                        <img src="{{ $batch->product->getImageUrl() }}" class="w-16 h-16 rounded-2xl object-cover border border-gray-100 shrink-0" alt="{{ $batch->product->name }}">
                    @endif
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Batch Reference</p>
                        <h2 class="text-2xl font-bold font-mono text-gray-900">{{ $batch->reference }}</h2>
                        <p class="text-sm text-gray-600 mt-1">{{ $batch->product?->name }} · Received {{ $batch->received_at?->format('d M Y') }}</p>
                        <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase {{ ($batch->purchase_grade ?? 'A') === 'B' ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' }}">Grade {{ $batch->purchase_grade ?? 'A' }}</span>
                    </div>
                </div>
                <span class="inline-flex text-sm font-semibold px-3 py-1.5 rounded-xl {{ $statusColor }}">{{ $batch->status->label() }}</span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 mt-6 pt-6 border-t border-gray-100">
                <div>
                    <p class="text-xs text-gray-500">Total Qty</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($batch->total_kg, 1) }} <span class="text-xs font-normal text-gray-500">kg</span></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Cost / kg</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">INR {{ number_format($batch->cost_per_kg, 4) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Transport</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">INR {{ number_format($batch->transport_cost, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Landed Cost</p>
                    <p class="text-xl font-bold text-brand-700 mt-1">INR {{ number_format($batch->total_landed_cost, 2) }}</p>
                </div>
            </div>

            @if($batch->notes)
            <p class="mt-4 text-sm text-gray-500 border-t border-gray-100 pt-4">{{ $batch->notes }}</p>
            @endif
        </div>

        {{-- Stock Movements --}}
        @if($batch->stockMovements->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">Stock Movements (Sorted)</h3>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Grade</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Quantity</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Cost/Unit</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Total Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($batch->stockMovements as $mv)
                    <tr>
                        <td class="px-6 py-3">
                            @php
                                $gc = ['A' => 'bg-green-100 text-green-700', 'B' => 'bg-blue-100 text-blue-700', 'C' => 'bg-amber-100 text-amber-700'];
                                $c  = $gc[$mv->grade?->value] ?? 'bg-gray-100 text-gray-600';
                            @endphp
                            <span class="inline-flex text-xs font-bold px-2 py-0.5 rounded-lg {{ $c }}">Grade {{ $mv->grade?->value }}</span>
                        </td>
                        <td class="px-6 py-3 text-right font-semibold">{{ number_format($mv->quantity, 3) }} kg</td>
                        <td class="px-6 py-3 text-right text-gray-600">INR {{ number_format($mv->cost_per_unit, 4) }}</td>
                        <td class="px-6 py-3 text-right font-semibold text-gray-900">INR {{ number_format($mv->total_value, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Wastage entries --}}
        @if($batch->wastageEntries->isNotEmpty())
        <div class="bg-white rounded-2xl border border-red-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-red-100 bg-red-50">
                <h3 class="text-sm font-semibold text-red-800">Wastage Recorded</h3>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-red-100">
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Grade</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Reason</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Quantity</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($batch->wastageEntries as $we)
                    <tr>
                        <td class="px-6 py-3"><span class="text-xs font-bold text-red-600 bg-red-100 px-2 py-0.5 rounded-lg">Damage</span></td>
                        <td class="px-6 py-3 text-gray-600">{{ $we->reason?->label() }}</td>
                        <td class="px-6 py-3 text-right font-semibold">{{ number_format($we->quantity, 3) }} kg</td>
                        <td class="px-6 py-3 text-right text-red-700 font-semibold">INR {{ number_format($we->total_cost, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Sort CTA if not yet sorted --}}
        @if($batch->canBeSorted())
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859M12 3v8.25m0 0l-3-3m3 3l3-3" />
                </svg>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-amber-800">This batch needs sorting</p>
                <p class="text-xs text-amber-700 mt-0.5">Assign grade quantities to update stock levels automatically.</p>
            </div>
            <a href="{{ route('inventory.batches.sort', $batch) }}"
               class="shrink-0 inline-flex items-center gap-1.5 text-sm font-semibold text-amber-800 bg-amber-100 border border-amber-300 px-4 py-2 rounded-xl hover:bg-amber-200 transition-colors">
                Sort Now →
            </a>
        </div>
        @endif

    </div>

</x-layouts.inventory>
