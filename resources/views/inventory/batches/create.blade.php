<x-layouts.inventory title="Receive Batch">

    <div class="max-w-2xl">
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Receive New Batch</h2>
                <p class="text-xs text-gray-500 mt-0.5">Record incoming goods from a supplier or farm.</p>
            </div>

            <form method="POST" action="{{ route('inventory.batches.store') }}" class="p-6 space-y-5">
                @csrf

                {{-- Product --}}
                <div class="space-y-1.5">
                    <label for="product_id" class="block text-sm font-medium text-gray-700">Product <span class="text-red-500">*</span></label>
                    <select id="product_id" name="product_id" required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white @error('product_id') border-red-300 @enderror">
                        <option value="">Select product…</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>
                                {{ $product->name }} ({{ $product->sku }})
                            </option>
                        @endforeach
                    </select>
                    @error('product_id') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    {{-- Received date --}}
                    <div class="space-y-1.5">
                        <label for="received_at" class="block text-sm font-medium text-gray-700">Received Date <span class="text-red-500">*</span></label>
                        <input id="received_at" name="received_at" type="date" required
                               value="{{ old('received_at', today()->toDateString()) }}"
                               max="{{ today()->toDateString() }}"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('received_at') border-red-300 @enderror">
                        @error('received_at') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                    </div>

                    {{-- Total KG --}}
                    <div class="space-y-1.5">
                        <label for="total_kg" class="block text-sm font-medium text-gray-700">Total Quantity (kg) <span class="text-red-500">*</span></label>
                        <input id="total_kg" name="total_kg" type="number" step="0.001" min="0.1" required
                               value="{{ old('total_kg') }}"
                               placeholder="e.g. 500.000"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('total_kg') border-red-300 @enderror">
                        @error('total_kg') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Cost breakdown --}}
                <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 space-y-4">
                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Cost Breakdown (Landed Cost)</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="space-y-1.5">
                            <label for="cost_per_kg" class="block text-xs font-medium text-gray-600">Purchase Cost / kg <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium">INR</span>
                                <input id="cost_per_kg" name="cost_per_kg" type="number" step="0.0001" min="0.01" required
                                       value="{{ old('cost_per_kg') }}"
                                       placeholder="0.0000"
                                       class="w-full border border-gray-200 rounded-xl pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white @error('cost_per_kg') border-red-300 @enderror">
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label for="transport_cost" class="block text-xs font-medium text-gray-600">Transport Cost</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium">INR</span>
                                <input id="transport_cost" name="transport_cost" type="number" step="0.01" min="0"
                                       value="{{ old('transport_cost', '0.00') }}"
                                       class="w-full border border-gray-200 rounded-xl pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white">
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label for="labour_cost" class="block text-xs font-medium text-gray-600">Labour Cost</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium">INR</span>
                                <input id="labour_cost" name="labour_cost" type="number" step="0.01" min="0"
                                       value="{{ old('labour_cost', '0.00') }}"
                                       class="w-full border border-gray-200 rounded-xl pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="space-y-1.5">
                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea id="notes" name="notes" rows="2"
                              placeholder="Supplier name, vehicle number, condition notes…"
                              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 resize-none">{{ old('notes') }}</textarea>
                </div>

                <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors">
                        Receive Batch
                    </button>
                    <a href="{{ route('inventory.batches.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Cancel</a>
                </div>
            </form>
        </div>
    </div>

</x-layouts.inventory>
