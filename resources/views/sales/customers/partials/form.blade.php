@php
    $customer ??= null;
    $types = ['Retailer', 'Wholesaler', 'Restaurant', 'Supermarket'];
    $paymentTerms = ['COD', 'Net 7', 'Net 15', 'Net 30'];
    $selectedType = old('type', $customer?->type ?? 'Retailer');
    $selectedPaymentTerms = old('payment_terms', $customer?->payment_terms ?? 'COD');
    $isActive = (bool) old('is_active', $customer?->is_active ?? true);
@endphp

<form method="POST" action="{{ $formAction }}" class="mx-auto max-w-5xl space-y-5">
    @csrf
    @if($formMethod !== 'POST')
        @method($formMethod)
    @endif

    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Customer Details</h2>
            <p class="mt-1 text-xs text-gray-500">Maintain external sales customers used for invoices and payment follow-up.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
            <div>
                <label for="name" class="mb-1.5 block text-xs font-semibold text-gray-700">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name', $customer?->name) }}" required
                       class="w-full rounded-lg border @error('name') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="type" class="mb-1.5 block text-xs font-semibold text-gray-700">Type <span class="text-red-500">*</span></label>
                <select name="type" id="type" required
                        class="w-full rounded-lg border @error('type') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    @foreach($types as $type)
                        <option value="{{ $type }}" @selected($selectedType === $type)>{{ $type }}</option>
                    @endforeach
                </select>
                @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="contact" class="mb-1.5 block text-xs font-semibold text-gray-700">Contact <span class="text-red-500">*</span></label>
                <input type="text" name="contact" id="contact" value="{{ old('contact', $customer?->contact) }}" required
                       class="w-full rounded-lg border @error('contact') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                @error('contact') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-xs font-semibold text-gray-700">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email', $customer?->email) }}"
                       class="w-full rounded-lg border @error('email') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="payment_terms" class="mb-1.5 block text-xs font-semibold text-gray-700">Payment Terms <span class="text-red-500">*</span></label>
                <select name="payment_terms" id="payment_terms" required
                        class="w-full rounded-lg border @error('payment_terms') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                    @foreach($paymentTerms as $term)
                        <option value="{{ $term }}" @selected($selectedPaymentTerms === $term)>{{ $term }}</option>
                    @endforeach
                </select>
                @error('payment_terms') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="credit_limit" class="mb-1.5 block text-xs font-semibold text-gray-700">Credit Limit <span class="text-red-500">*</span></label>
                <input type="number" name="credit_limit" id="credit_limit" value="{{ old('credit_limit', $customer?->credit_limit ?? '0.00') }}" required min="0" step="0.01"
                       class="w-full rounded-lg border @error('credit_limit') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">
                @error('credit_limit') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label for="address" class="mb-1.5 block text-xs font-semibold text-gray-700">Address</label>
                <textarea name="address" id="address" rows="3"
                          class="w-full rounded-lg border @error('address') border-red-400 @else border-gray-200 @enderror bg-white px-3 py-2 text-sm text-gray-800 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-100">{{ old('address', $customer?->address) }}</textarea>
                @error('address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 md:col-span-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked($isActive)
                       class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                <span class="text-sm font-semibold text-gray-800">Customer is active</span>
            </label>
        </div>
    </div>

    <div class="flex flex-col-reverse gap-3 pt-1 sm:flex-row sm:justify-end">
        <a href="{{ $cancelHref }}" class="inline-flex justify-center rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">Cancel</a>
        <button type="submit" class="inline-flex justify-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-700">
            {{ $submitLabel }}
        </button>
    </div>
</form>
