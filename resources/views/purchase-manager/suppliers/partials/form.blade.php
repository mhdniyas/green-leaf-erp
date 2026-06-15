<div class="mx-auto max-w-3xl">
    <form method="POST" action="{{ $formAction }}" class="purchase-manager-panel overflow-hidden">
        @csrf
        @if ($formMethod !== 'POST')
            @method($formMethod)
        @endif

        <div class="border-b border-slate-200 px-5 py-5">
            <h2 class="text-lg font-black text-slate-950">{{ $supplier ? 'Supplier Details' : 'New Supplier' }}</h2>
        </div>

        <div class="grid gap-5 px-5 py-5 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="name" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Vendor Name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $supplier?->name) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
            </div>
            <div>
                <label for="type" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Type</label>
                <input id="type" type="text" name="type" value="{{ old('type', $supplier?->type) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
            </div>
            <div>
                <label for="category" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Category</label>
                <select id="category" name="category" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                    <option value="own_purchase" @selected(old('category', $supplier?->category ?? 'own_purchase') === 'own_purchase')>Own Purchase</option>
                    <option value="b2b" @selected(old('category', $supplier?->category) === 'b2b')>B2B Supplier</option>
                    <option value="market" @selected(old('category', $supplier?->category) === 'market')>Market Vendor</option>
                </select>
            </div>
            <div>
                <label for="payment_terms" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Payment Terms</label>
                <input id="payment_terms" type="text" name="payment_terms" value="{{ old('payment_terms', $supplier?->payment_terms) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
            </div>
            <div>
                <label for="preferred_payment_method" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Preferred Payment Method</label>
                <input id="preferred_payment_method" type="text" name="preferred_payment_method" value="{{ old('preferred_payment_method', $supplier?->preferred_payment_method) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
            </div>
            <div class="md:col-span-2">
                <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <input id="credit_approved" type="checkbox" name="credit_approved" value="1" @checked(old('credit_approved', $supplier?->credit_approved)) class="mt-1 h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                    <span>
                        <span class="block text-sm font-black text-slate-900">Approved for purchaser credit</span>
                        <span class="mt-1 block text-xs font-semibold text-slate-500">Purchasers can only submit credit purchases for suppliers marked as approved here.</span>
                    </span>
                </label>
            </div>
            <div>
                <label for="credit_terms" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Credit Terms</label>
                <input id="credit_terms" type="text" name="credit_terms" value="{{ old('credit_terms', $supplier?->credit_terms) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
            </div>
            <div>
                <label for="location" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Location</label>
                <input id="location" type="text" name="location" value="{{ old('location', $supplier?->location) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
            </div>
            <div>
                <label for="mobile_number" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Mobile Number</label>
                <input id="mobile_number" type="text" name="mobile_number" value="{{ old('mobile_number', $supplier?->mobile_number) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
            </div>
            <div class="md:col-span-2">
                <label for="contact" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Contact Details</label>
                <textarea id="contact" name="contact" rows="4" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">{{ old('contact', $supplier?->contact) }}</textarea>
            </div>
            <div>
                <label for="quality_score" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Quality Score</label>
                <input id="quality_score" type="number" step="0.01" min="0" max="100" name="quality_score" value="{{ old('quality_score', $supplier?->quality_score) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
            </div>
            <div class="md:col-span-2">
                <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <input id="is_default_purchase" type="checkbox" name="is_default_purchase" value="1" @checked(old('is_default_purchase', $supplier?->is_default_purchase)) class="mt-1 h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                    <span>
                        <span class="block text-sm font-black text-slate-900">Default purchase supplier</span>
                        <span class="mt-1 block text-xs font-semibold text-slate-500">Use this supplier as the preselected default across purchasing boards when no product-specific supplier is already mapped.</span>
                    </span>
                </label>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 border-t border-slate-200 px-5 py-5">
            <x-purchase-manager.components.action-button type="submit" variant="primary">{{ $submitLabel }}</x-purchase-manager.components.action-button>
            <x-purchase-manager.components.action-button :href="$cancelHref" variant="secondary">Cancel</x-purchase-manager.components.action-button>
        </div>
    </form>
</div>
