<x-layouts.app title="Edit Supplier">

    <div class="max-w-2xl">
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Edit Supplier: {{ $supplier->name }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">Update supplier information and quality metrics.</p>
            </div>

            <form method="POST" action="{{ route('purchasing.suppliers.update', $supplier) }}" class="p-6 space-y-5">
                @csrf
                @method('PUT')

                <div class="space-y-1.5">
                    <label for="name" class="block text-sm font-medium text-gray-700">Supplier Name <span class="text-red-500">*</span></label>
                    <input id="name" name="name" type="text" required
                           value="{{ old('name', $supplier->name) }}"
                           placeholder="e.g. Green Valley Farm"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('name') border-red-300 @enderror">
                    @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label for="type" class="block text-sm font-medium text-gray-700">Supplier Type <span class="text-red-500">*</span></label>
                        <select id="type" name="type" required
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white @error('type') border-red-300 @enderror">
                            <option value="">Select type…</option>
                            <option value="Farmer" @selected(old('type', $supplier->type) === 'Farmer')>Farmer</option>
                            <option value="Market Agent" @selected(old('type', $supplier->type) === 'Market Agent')>Market Agent</option>
                            <option value="Importer" @selected(old('type', $supplier->type) === 'Importer')>Importer</option>
                            <option value="Co-operative" @selected(old('type', $supplier->type) === 'Co-operative')>Co-operative</option>
                        </select>
                        @error('type') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="payment_terms" class="block text-sm font-medium text-gray-700">Payment Terms <span class="text-red-500">*</span></label>
                        <select id="payment_terms" name="payment_terms" required
                                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 bg-white @error('payment_terms') border-red-300 @enderror">
                            <option value="">Select terms…</option>
                            <option value="COD" @selected(old('payment_terms', $supplier->payment_terms) === 'COD')>COD (Cash on Delivery)</option>
                            <option value="Net 7" @selected(old('payment_terms', $supplier->payment_terms) === 'Net 7')>Net 7</option>
                            <option value="Net 15" @selected(old('payment_terms', $supplier->payment_terms) === 'Net 15')>Net 15</option>
                            <option value="Net 30" @selected(old('payment_terms', $supplier->payment_terms) === 'Net 30')>Net 30</option>
                        </select>
                        @error('payment_terms') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label for="contact" class="block text-sm font-medium text-gray-700">Contact Details <span class="text-red-500">*</span></label>
                        <input id="contact" name="contact" type="text" required
                               value="{{ old('contact', $supplier->contact) }}"
                               placeholder="e.g. Phone, email, address"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('contact') border-red-300 @enderror">
                        @error('contact') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="quality_score" class="block text-sm font-medium text-gray-700">Quality Score (0-100)</label>
                        <input id="quality_score" name="quality_score" type="number" step="0.01" min="0" max="100"
                               value="{{ old('quality_score', $supplier->quality_score) }}"
                               placeholder="100.00"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 @error('quality_score') border-red-300 @enderror">
                        @error('quality_score') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 transition-colors">
                        Save Changes
                    </button>
                    <a href="{{ route('purchasing.suppliers.index') }}"
                       class="text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">Cancel</a>
                </div>
            </form>
        </div>
    </div>

</x-layouts.app>
