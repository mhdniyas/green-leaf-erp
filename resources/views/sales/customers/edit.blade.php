<x-layouts.admin title="Edit Customer">

    <x-slot:actions>
        <a href="{{ route('sales.customers.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
            ← Back to Customers
        </a>
    </x-slot:actions>

    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Edit Customer — {{ $customer->name }}</h2>
            </div>

            <form method="POST" action="{{ route('sales.customers.update', $customer) }}" class="p-6 space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="block text-xs font-semibold text-gray-700 mb-1.5">Customer Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $customer->name) }}" required
                           class="w-full rounded-lg border @error('name') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="type" class="block text-xs font-semibold text-gray-700 mb-1.5">Type <span class="text-red-500">*</span></label>
                        <select name="type" id="type" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none">
                            @foreach(['Retailer','Wholesaler','Restaurant','Supermarket'] as $t)
                                <option value="{{ $t }}" @selected(old('type', $customer->type) === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="payment_terms" class="block text-xs font-semibold text-gray-700 mb-1.5">Payment Terms <span class="text-red-500">*</span></label>
                        <select name="payment_terms" id="payment_terms" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none">
                            @foreach(['COD','Net 7','Net 15','Net 30'] as $term)
                                <option value="{{ $term }}" @selected(old('payment_terms', $customer->payment_terms) === $term)>{{ $term }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label for="contact" class="block text-xs font-semibold text-gray-700 mb-1.5">Contact Details <span class="text-red-500">*</span></label>
                    <input type="text" name="contact" id="contact" value="{{ old('contact', $customer->contact) }}" required
                           class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="block text-xs font-semibold text-gray-700 mb-1.5">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $customer->email) }}"
                               class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    </div>

                    <div>
                        <label for="credit_limit" class="block text-xs font-semibold text-gray-700 mb-1.5">Credit Limit (INR)</label>
                        <input type="number" name="credit_limit" id="credit_limit" value="{{ old('credit_limit', $customer->credit_limit) }}" min="0" step="100"
                               class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    </div>
                </div>

                <div>
                    <label for="address" class="block text-xs font-semibold text-gray-700 mb-1.5">Address</label>
                    <textarea name="address" id="address" rows="2"
                              class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100 resize-none">{{ old('address', $customer->address) }}</textarea>
                </div>

                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $customer->is_active))
                           class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    <label for="is_active" class="text-sm text-gray-700">Active customer</label>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <a href="{{ route('sales.customers.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Cancel</a>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 transition-colors shadow-sm">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

</x-layouts.admin>
