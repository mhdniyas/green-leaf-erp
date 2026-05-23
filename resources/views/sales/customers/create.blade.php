<x-layouts.app title="Add Customer">

    <x-slot:actions>
        <a href="{{ route('sales.customers.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
            ← Back to Customers
        </a>
    </x-slot:actions>

    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">New Customer</h2>
            </div>

            <form method="POST" action="{{ route('sales.customers.store') }}" class="p-6 space-y-5">
                @csrf

                {{-- Name --}}
                <div>
                    <label for="name" class="block text-xs font-semibold text-gray-700 mb-1.5">Customer Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                           class="w-full rounded-lg border @error('name') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Type + Contact --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="type" class="block text-xs font-semibold text-gray-700 mb-1.5">Customer Type <span class="text-red-500">*</span></label>
                        <select name="type" id="type" required class="w-full rounded-lg border @error('type') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none">
                            <option value="">Select type…</option>
                            @foreach(['Retailer','Wholesaler','Restaurant','Supermarket'] as $t)
                                <option value="{{ $t }}" @selected(old('type') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                        @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="payment_terms" class="block text-xs font-semibold text-gray-700 mb-1.5">Payment Terms <span class="text-red-500">*</span></label>
                        <select name="payment_terms" id="payment_terms" required class="w-full rounded-lg border @error('payment_terms') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none">
                            @foreach(['COD','Net 7','Net 15','Net 30'] as $term)
                                <option value="{{ $term }}" @selected(old('payment_terms', 'COD') === $term)>{{ $term }}</option>
                            @endforeach
                        </select>
                        @error('payment_terms') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Contact --}}
                <div>
                    <label for="contact" class="block text-xs font-semibold text-gray-700 mb-1.5">Contact Details <span class="text-red-500">*</span></label>
                    <input type="text" name="contact" id="contact" value="{{ old('contact') }}" required
                           placeholder="Name (phone number)"
                           class="w-full rounded-lg border @error('contact') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    @error('contact') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Email + Credit Limit --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="block text-xs font-semibold text-gray-700 mb-1.5">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}"
                               class="w-full rounded-lg border @error('email') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="credit_limit" class="block text-xs font-semibold text-gray-700 mb-1.5">Credit Limit (INR)</label>
                        <input type="number" name="credit_limit" id="credit_limit" value="{{ old('credit_limit', 0) }}" min="0" step="100"
                               class="w-full rounded-lg border @error('credit_limit') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        @error('credit_limit') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Address --}}
                <div>
                    <label for="address" class="block text-xs font-semibold text-gray-700 mb-1.5">Address</label>
                    <textarea name="address" id="address" rows="2"
                              class="w-full rounded-lg border @error('address') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100 resize-none">{{ old('address') }}</textarea>
                    @error('address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('sales.customers.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Cancel</a>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
                        Create Customer
                    </button>
                </div>
            </form>
        </div>
    </div>

</x-layouts.app>
