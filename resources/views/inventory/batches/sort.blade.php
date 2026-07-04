@php
/** @var \App\Models\StockBatch $batch */
$grades = [
    ['value' => 'A', 'label' => 'Grade A — Premium', 'color' => 'border-green-300 bg-green-50', 'badge' => 'bg-green-100 text-green-700'],
    ['value' => 'B', 'label' => 'Grade B — Standard', 'color' => 'border-blue-300 bg-blue-50',  'badge' => 'bg-blue-100 text-blue-700'],
    ['value' => 'C', 'label' => 'Grade C — Economy',  'color' => 'border-amber-300 bg-amber-50', 'badge' => 'bg-amber-100 text-amber-700'],
    ['value' => 'D', 'label' => 'Damage — Write-off',  'color' => 'border-red-300 bg-red-50',    'badge' => 'bg-red-100 text-red-600'],
];
@endphp

<x-layouts.inventory title="Sort Batch: {{ $batch->reference }}">

    <div class="max-w-2xl">

        {{-- Batch info card --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-5 flex gap-5 items-center">
            @if($batch->product?->image)
                <img src="{{ $batch->product->getImageUrl() }}" class="w-16 h-16 rounded-xl object-cover border border-gray-100 shrink-0" alt="{{ $batch->product->name }}">
            @endif
            <div class="flex-1 grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                    <p class="text-xs text-gray-500">Reference</p>
                    <p class="text-sm font-mono font-bold text-gray-900 mt-0.5">{{ $batch->reference }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Product</p>
                    <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $batch->product?->name }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Received</p>
                    <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $batch->received_at?->format('d M Y') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Total Quantity</p>
                    <p class="text-2xl font-bold text-brand-700 mt-0.5">{{ number_format($batch->total_kg, 1) }} <span class="text-sm font-normal text-gray-500">kg</span></p>
                </div>
            </div>
        </div>

        {{-- Validation errors --}}
        @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-5">
            @foreach($errors->all() as $err)
            <p class="text-red-700 text-sm">{{ $err }}</p>
            @endforeach
        </div>
        @endif

        {{-- Sorting form --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Enter Grade Breakdown</h2>
                <p class="text-xs text-gray-500 mt-0.5">All quantities must add up to <strong>{{ number_format($batch->total_kg, 3) }} kg</strong>. Leave unused grades as 0.</p>
            </div>

            <form id="sorting-form" method="POST" action="{{ route('inventory.batches.sort.process', $batch) }}" class="p-6 space-y-4">
                @csrf

                @foreach($grades as $i => $grade)
                <div class="flex items-center gap-4 p-4 rounded-xl border {{ $grade['color'] }}">
                    <div class="flex-1">
                        <span class="inline-flex items-center text-xs font-bold px-2.5 py-1 rounded-lg {{ $grade['badge'] }}">
                            {{ $grade['label'] }}
                        </span>
                    </div>
                    <input type="hidden" name="grades[{{ $i }}][grade]" value="{{ $grade['value'] }}">
                    <div class="flex items-center gap-2 w-40">
                        <input
                            type="number"
                            id="grade_{{ $grade['value'] }}"
                            name="grades[{{ $i }}][quantity]"
                            step="0.001"
                            min="0"
                            value="{{ old("grades.{$i}.quantity", '0') }}"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-right font-mono focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white"
                            oninput="updateTotal()"
                        >
                        <span class="text-xs text-gray-500 shrink-0">kg</span>
                    </div>
                </div>
                @endforeach

                {{-- Running total --}}
                <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <span class="text-sm text-gray-600">Total Entered</span>
                    <div class="flex items-center gap-2">
                        <span id="total-display" class="text-lg font-bold text-gray-900">0.000</span>
                        <span class="text-xs text-gray-500">/ {{ number_format($batch->total_kg, 3) }} kg</span>
                        <span id="total-badge" class="hidden text-xs font-medium px-2 py-0.5 rounded-full"></span>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="space-y-1.5">
                    <label for="sort_notes" class="block text-xs font-medium text-gray-600">Notes (optional)</label>
                    <textarea id="sort_notes" name="notes" rows="2"
                              placeholder="Any observations during sorting…"
                              class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 resize-none">{{ old('notes') }}</textarea>
                </div>

                <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                    <button type="submit" id="submit-sort-btn"
                            class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        Confirm Sort & Update Stock
                    </button>
                    <a href="{{ route('inventory.batches.show', $batch) }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const batchTotal = {{ (float) $batch->total_kg }};

        function updateTotal() {
            let sum = 0;
            document.querySelectorAll('[name^="grades"][name$="[quantity]"]').forEach(input => {
                sum += parseFloat(input.value) || 0;
            });

            const display = document.getElementById('total-display');
            const badge   = document.getElementById('total-badge');
            display.textContent = sum.toFixed(3);

            const diff = Math.abs(sum - batchTotal);
            if (diff <= 0.01) {
                display.classList.remove('text-red-600');
                display.classList.add('text-green-700');
                badge.className = 'text-xs font-medium px-2 py-0.5 rounded-full bg-green-100 text-green-700';
                badge.textContent = '✓ Match';
                badge.classList.remove('hidden');
            } else if (sum > 0) {
                display.classList.remove('text-green-700');
                display.classList.add('text-red-600');
                badge.className = 'text-xs font-medium px-2 py-0.5 rounded-full bg-red-100 text-red-600';
                badge.textContent = sum > batchTotal ? '↑ Over' : '↓ Under';
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
                display.classList.remove('text-green-700', 'text-red-600');
                display.classList.add('text-gray-900');
            }
        }
    </script>

</x-layouts.inventory>
