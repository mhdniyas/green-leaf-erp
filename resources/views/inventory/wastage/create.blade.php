<x-layouts.app title="Record Wastage">

    <div class="max-w-2xl">
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Record Wastage</h2>
                <p class="text-xs text-gray-500 mt-0.5">Manually record spoiled or damaged stock.</p>
            </div>

            <form method="POST" action="{{ route('inventory.wastage.store') }}" class="p-6 space-y-5">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label for="product_id" class="block text-sm font-medium text-gray-700">Product <span class="text-red-500">*</span></label>
                        <select id="product_id" name="product_id" required
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white @error('product_id') border-red-300 @enderror">
                            <option value="">Select product…</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }}</option>
                            @endforeach
                        </select>
                        @error('product_id') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="wastage_date" class="block text-sm font-medium text-gray-700">Date <span class="text-red-500">*</span></label>
                        <input id="wastage_date" name="wastage_date" type="date" required
                               value="{{ old('wastage_date', today()->toDateString()) }}"
                               max="{{ today()->toDateString() }}"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('wastage_date') border-red-300 @enderror">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div class="space-y-1.5">
                        <label for="grade" class="block text-sm font-medium text-gray-700">Grade <span class="text-red-500">*</span></label>
                        <select id="grade" name="grade" required
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white">
                            @foreach($grades as $grade)
                                <option value="{{ $grade->value }}" @selected(old('grade') === $grade->value)>Grade {{ $grade->value }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label for="quantity" class="block text-sm font-medium text-gray-700">Quantity (kg) <span class="text-red-500">*</span></label>
                        <input id="quantity" name="quantity" type="number" step="0.001" min="0.001" required
                               value="{{ old('quantity') }}"
                               placeholder="e.g. 5.500"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('quantity') border-red-300 @enderror">
                        @error('quantity') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="cost_per_kg" class="block text-sm font-medium text-gray-700">Cost / kg <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium">RM</span>
                            <input id="cost_per_kg" name="cost_per_kg" type="number" step="0.0001" min="0.01" required
                                   value="{{ old('cost_per_kg') }}"
                                   placeholder="0.0000"
                                   class="w-full border border-gray-200 rounded-xl pl-9 pr-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('cost_per_kg') border-red-300 @enderror">
                        </div>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="reason" class="block text-sm font-medium text-gray-700">Reason <span class="text-red-500">*</span></label>
                    <select id="reason" name="reason" required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white @error('reason') border-red-300 @enderror">
                        @foreach($reasons as $reason)
                            <option value="{{ $reason->value }}" @selected(old('reason') === $reason->value)>{{ $reason->label() }}</option>
                        @endforeach
                    </select>
                    @error('reason') <p class="text-red-600 text-xs">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-1.5">
                    <label for="wastage_notes" class="block text-sm font-medium text-gray-700">Notes (optional)</label>
                    <textarea id="wastage_notes" name="notes" rows="2"
                              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 resize-none">{{ old('notes') }}</textarea>
                </div>

                <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700 transition-colors">
                        Record Wastage
                    </button>
                    <a href="{{ route('inventory.wastage.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Cancel</a>
                </div>
            </form>
        </div>
    </div>

</x-layouts.app>
