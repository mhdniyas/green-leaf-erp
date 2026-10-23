@php
/** @var \App\Models\StockBatch $batch */
$grades = [
    ['value' => 'A', 'label' => 'Grade A — Premium', 'color' => 'border-green-300 bg-green-50', 'badge' => 'bg-green-100 text-green-700'],
    ['value' => 'B', 'label' => 'Grade B — Standard', 'color' => 'border-blue-300 bg-blue-50',  'badge' => 'bg-blue-100 text-blue-700'],
    ['value' => 'C', 'label' => 'Grade C — Economy',  'color' => 'border-amber-300 bg-amber-50', 'badge' => 'bg-amber-100 text-amber-700'],
    ['value' => 'DMG', 'label' => 'Damage — Write-off',  'color' => 'border-red-300 bg-red-50',    'badge' => 'bg-red-100 text-red-600'],
];
$availableMixed = $batch->available_mixed_qty;
$originalTotal = $batch->original_total_kg;
@endphp

<x-layouts.inventory title="Sort Batch: {{ $batch->reference }}">

    <div class="max-w-3xl space-y-6">

        {{-- Batch info card --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex gap-5 items-center">
            @if($batch->product?->image)
                <img src="{{ $batch->product->getImageUrl() }}" class="w-16 h-16 rounded-xl object-cover border border-gray-100 shrink-0" alt="{{ $batch->product->name }}">
            @endif
            <div class="flex-1 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
                <div>
                    <p class="text-xs text-gray-500">Reference</p>
                    <p class="text-xs font-mono font-bold text-gray-900 mt-0.5 truncate">{{ $batch->reference }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Product</p>
                    <p class="text-xs font-semibold text-gray-900 mt-0.5 truncate">{{ $batch->product?->name }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Warehouse</p>
                    <p class="text-xs font-semibold text-gray-900 mt-0.5">{{ $batch->warehouse?->name ?? 'Default' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Original Total</p>
                    <p class="text-sm font-bold text-gray-700 mt-0.5">{{ number_format($originalTotal, 3) }} <span class="text-xs font-normal text-gray-500">kg</span></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Available Mixed</p>
                    <p class="text-sm font-bold text-brand-700 mt-0.5">{{ number_format($availableMixed, 3) }} <span class="text-xs font-normal text-gray-500">kg</span></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Received</p>
                    <p class="text-xs font-semibold text-gray-900 mt-0.5">{{ $batch->received_at?->format('d M Y') }}</p>
                </div>
            </div>
        </div>

        {{-- Validation errors --}}
        @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3">
            @foreach($errors->all() as $err)
            <p class="text-red-700 text-sm">{{ $err }}</p>
            @endforeach
        </div>
        @endif

        {{-- Success message --}}
        @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-800 text-sm font-medium">
            {{ session('success') }}
        </div>
        @endif

        {{-- Section 1: Optional Partial Segregation --}}
        <div class="bg-white rounded-2xl border border-brand-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-brand-50/60 border-b border-brand-100 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-brand-900">Optional: Partial Warehouse Segregation</h2>
                    <p class="text-xs text-brand-700 mt-0.5">Segregate a portion of available Mixed stock into Grade A, B, C, or Damage immediately without full sort.</p>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-brand-100 text-brand-800">
                    Available: {{ number_format($availableMixed, 3) }} kg
                </span>
            </div>

            @if($availableMixed > 0.001)
            <form id="segregation-form" method="POST" action="{{ route('inventory.batches.segregate', $batch) }}" class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="client_submission_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Target Grade --}}
                    <div>
                        <label for="target_grade" class="block text-xs font-semibold text-gray-700 mb-1">Target Grade</label>
                        <select id="target_grade" name="target_grade" required
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500">
                            <option value="A" {{ old('target_grade') === 'A' ? 'selected' : '' }}>Grade A — Premium</option>
                            <option value="B" {{ old('target_grade') === 'B' ? 'selected' : '' }}>Grade B — Standard</option>
                            <option value="C" {{ old('target_grade') === 'C' ? 'selected' : '' }}>Grade C — Economy</option>
                            <option value="DMG" {{ old('target_grade') === 'DMG' ? 'selected' : '' }}>Damage — Write-off</option>
                        </select>
                    </div>

                    {{-- Quantity to Segregate --}}
                    <div>
                        <label for="segregate_quantity" class="block text-xs font-semibold text-gray-700 mb-1">Quantity to Segregate (kg)</label>
                        <div class="relative">
                            <input
                                type="number"
                                id="segregate_quantity"
                                name="quantity"
                                step="0.001"
                                min="0.001"
                                max="{{ $availableMixed }}"
                                value="{{ old('quantity') }}"
                                required
                                placeholder="e.g. 30.0"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white"
                                oninput="updateSegregationPreview()"
                            >
                            <span class="absolute right-3 top-2.5 text-xs text-gray-400">kg</span>
                        </div>
                    </div>
                </div>

                {{-- Live Remaining Mixed Display --}}
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 flex items-center justify-between text-xs">
                    <span class="text-gray-600">Remaining Mixed after segregation:</span>
                    <span id="remaining-mixed-preview" class="font-mono font-bold text-gray-900 text-sm">
                        {{ number_format($availableMixed, 3) }} kg
                    </span>
                </div>

                {{-- Notes --}}
                <div>
                    <label for="segregate_notes" class="block text-xs font-medium text-gray-600 mb-1">Segregation Notes (optional)</label>
                    <input type="text" id="segregate_notes" name="notes" value="{{ old('notes') }}"
                           placeholder="Optional reason or operator notes"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500">
                </div>

                <div class="pt-2">
                    <button type="submit" id="confirm-segregate-btn"
                            class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
                        Confirm Segregation →
                    </button>
                </div>
            </form>
            @else
            <div class="p-6 text-sm text-gray-500">
                All Mixed stock in this batch has been segregated or allocated.
            </div>
            @endif
        </div>

        {{-- Existing Segregations (if any) --}}
        @if($batch->segregatedBatches->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                <h3 class="text-xs font-bold text-gray-800 uppercase tracking-wide">Already Segregated From This Batch</h3>
                <span class="text-xs text-gray-500">{{ $batch->segregatedBatches->count() }} segregated batch(es)</span>
            </div>
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-gray-500">
                        <th class="px-6 py-2.5 text-left font-semibold">Reference</th>
                        <th class="px-6 py-2.5 text-left font-semibold">Grade</th>
                        <th class="px-6 py-2.5 text-right font-semibold">Quantity</th>
                        <th class="px-6 py-2.5 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($batch->segregatedBatches as $segChild)
                    <tr>
                        <td class="px-6 py-3 font-mono font-medium text-gray-900">
                            <a href="{{ route('inventory.batches.show', $segChild) }}" class="text-brand-600 hover:underline">
                                {{ $segChild->reference }}
                            </a>
                        </td>
                        <td class="px-6 py-3">
                            <span class="inline-flex text-[11px] font-bold px-2 py-0.5 rounded-lg {{ $segChild->purchase_grade === 'A' ? 'bg-green-100 text-green-700' : ($segChild->purchase_grade === 'B' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700') }}">
                                Grade {{ $segChild->purchase_grade }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right font-semibold text-gray-900">
                            {{ number_format($segChild->total_kg, 3) }} kg
                        </td>
                        <td class="px-6 py-3 text-right">
                            <form method="POST" action="{{ route('inventory.batches.segregate.reverse', $segChild) }}" class="inline"
                                  onsubmit="return confirm('Reverse {{ number_format($segChild->total_kg, 3) }} kg of Grade {{ $segChild->purchase_grade }} back to Mixed stock?');">
                                @csrf
                                <input type="hidden" name="client_submission_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="quantity" value="{{ (float) $segChild->total_kg }}">
                                <button type="submit" class="text-red-600 hover:text-red-800 font-medium text-xs">
                                    Reverse
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Section 2: Full Batch Sorting (Existing Workflow) --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Full Sorting: Breakdown Remaining Mixed</h2>
                <p class="text-xs text-gray-500 mt-0.5">Sort the remaining Mixed quantity (<strong>{{ number_format($availableMixed, 3) }} kg</strong>) completely into grades. Leave unused grades as 0.</p>
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
                            value="{{ old("grades.{$i}.quantity", $sortAllAsGradeA && $grade['value'] === 'A' ? (float) $availableMixed : '0') }}"
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
                        <span class="text-xs text-gray-500">/ {{ number_format($availableMixed, 3) }} kg</span>
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
                            class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-black transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        Confirm Full Sort & Close Mixed Batch
                    </button>
                    <a href="{{ route('inventory.batches.show', $batch) }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const batchAvailable = {{ (float) $availableMixed }};

        function updateSegregationPreview() {
            const input = document.getElementById('segregate_quantity');
            const preview = document.getElementById('remaining-mixed-preview');
            const val = parseFloat(input.value) || 0;
            const remaining = Math.max(0, batchAvailable - val);
            preview.textContent = remaining.toFixed(3) + ' kg';
            if (val > batchAvailable) {
                preview.classList.add('text-red-600');
                preview.classList.remove('text-gray-900');
            } else {
                preview.classList.remove('text-red-600');
                preview.classList.add('text-gray-900');
            }
        }

        function updateTotal() {
            let sum = 0;
            document.querySelectorAll('[name^="grades"][name$="[quantity]"]').forEach(input => {
                sum += parseFloat(input.value) || 0;
            });

            const display = document.getElementById('total-display');
            const badge   = document.getElementById('total-badge');
            display.textContent = sum.toFixed(3);

            const diff = Math.abs(sum - batchAvailable);
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
                badge.textContent = sum > batchAvailable ? '↑ Over' : '↓ Under';
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
                display.classList.remove('text-green-700', 'text-red-600');
                display.classList.add('text-gray-900');
            }
        }

        updateTotal();
    </script>

</x-layouts.inventory>
