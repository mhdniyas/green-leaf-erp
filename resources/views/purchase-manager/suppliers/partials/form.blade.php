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
                <label for="name" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Supplier Name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $supplier?->name) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
            </div>
            <div>
                <label for="type" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Type</label>
                <input id="type" type="text" name="type" value="{{ old('type', $supplier?->type) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
            </div>
            <div>
                <label for="payment_terms" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Payment Terms</label>
                <input id="payment_terms" type="text" name="payment_terms" value="{{ old('payment_terms', $supplier?->payment_terms) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
            </div>
            <div class="md:col-span-2">
                <label for="contact" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Contact Details</label>
                <textarea id="contact" name="contact" rows="4" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">{{ old('contact', $supplier?->contact) }}</textarea>
            </div>
            <div>
                <label for="quality_score" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Quality Score</label>
                <input id="quality_score" type="number" step="0.01" min="0" max="100" name="quality_score" value="{{ old('quality_score', $supplier?->quality_score) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
            </div>
        </div>

        <div class="flex flex-wrap gap-3 border-t border-slate-200 px-5 py-5">
            <x-purchase-manager.components.action-button type="submit" variant="primary">{{ $submitLabel }}</x-purchase-manager.components.action-button>
            <x-purchase-manager.components.action-button :href="$cancelHref" variant="secondary">Cancel</x-purchase-manager.components.action-button>
        </div>
    </form>
</div>
