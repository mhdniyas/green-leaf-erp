<x-layouts.app title="Discount Bill Prices">
    @php
        $billDate = $invoice->business_date;
        $invoiceTotal = (float) ($invoice->final_total ?: $invoice->subtotal);
        $oldSelectedItems = collect(old('selected_items', []))->map(fn ($id) => (string) $id)->all();
    @endphp

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-4 pb-28 lg:pb-4">
        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-900">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.18em] text-slate-500">Discount From Total</p>
                    <h1 class="mt-0.5 text-lg font-black text-slate-950">{{ $invoice->shop?->name ?? 'Unknown shop' }}</h1>
                    <p class="mt-0.5 text-xs font-semibold text-slate-600">{{ $invoice->invoice_number }} - {{ $billDate->format('d M Y') }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('purchaser.bill-prices.show', $invoice) }}" class="inline-flex h-9 flex-1 items-center justify-center rounded-xl border border-slate-200 px-3 text-xs font-black text-slate-700 transition hover:bg-slate-50 sm:flex-none">Special Prices</a>
                    <a href="{{ route('purchaser.bill-prices.index', ['date' => $billDate->toDateString()]) }}" class="inline-flex h-9 flex-1 items-center justify-center rounded-xl bg-slate-950 px-3 text-xs font-black text-white transition hover:bg-slate-800 sm:flex-none">Bills</a>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
            <form method="GET" action="{{ route('purchaser.bill-prices.discount', $invoice) }}" class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                @if($categoryId !== null)
                    <input type="hidden" name="category_id" value="{{ $categoryId }}">
                @endif
                <input type="search" name="search" value="{{ $search }}" placeholder="Search code, item, category..." class="h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 outline-none focus:border-lime-400 focus:bg-white">
                <div class="flex gap-2">
                    <button class="h-10 flex-1 rounded-xl bg-slate-950 px-4 text-xs font-black text-white transition hover:bg-slate-900 sm:flex-none">Search</button>
                    @if($search !== '')
                        <a href="{{ route('purchaser.bill-prices.discount', array_filter(['invoice' => $invoice, 'category_id' => $categoryId], fn ($value) => $value !== null && $value !== '')) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-black text-slate-600 transition hover:bg-slate-100">Clear</a>
                    @endif
                </div>
            </form>

            <p class="mt-3 mb-2 text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">Category</p>
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1">
                <a href="{{ route('purchaser.bill-prices.discount', array_filter(['invoice' => $invoice, 'search' => $search !== '' ? $search : null], fn ($value) => $value !== null && $value !== '')) }}" class="shrink-0 rounded-full border px-3 py-1.5 text-[11px] font-black uppercase tracking-wider transition {{ $categoryId === null ? 'border-slate-950 bg-slate-950 text-white shadow-sm' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}">All</a>
                @foreach($categories as $category)
                    <a href="{{ route('purchaser.bill-prices.discount', array_filter(['invoice' => $invoice, 'category_id' => $category->id, 'search' => $search !== '' ? $search : null], fn ($value) => $value !== null && $value !== '')) }}" class="shrink-0 rounded-full border px-3 py-1.5 text-[11px] font-black uppercase tracking-wider transition {{ (string) $categoryId === (string) $category->id ? 'border-teal-600 bg-teal-600 text-white shadow-sm' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}">{{ $category->name }}</a>
                @endforeach
            </div>
        </section>

        <form method="POST" action="{{ route('purchaser.bill-prices.discount.apply', $invoice) }}" data-discount-form class="contents">
            @csrf
            <input type="hidden" name="category_id" value="{{ $categoryId }}">
            <input type="hidden" name="search" value="{{ $search }}">

            <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Bill Total</p>
                        <p class="mt-1 text-sm font-black text-slate-950">Rs. {{ number_format($invoiceTotal, 2) }}</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Visible</p>
                        <p class="mt-1 text-sm font-black text-slate-950">Rs. {{ number_format((float) $visibleTotal, 2) }}</p>
                    </div>
                    <div class="rounded-xl bg-teal-50 p-3">
                        <p class="text-[9px] font-black uppercase tracking-wider text-teal-600">Selected</p>
                        <p class="mt-1 text-sm font-black text-teal-950" data-selected-total>Rs. 0.00</p>
                    </div>
                    <div class="rounded-xl bg-amber-50 p-3">
                        <p class="text-[9px] font-black uppercase tracking-wider text-amber-600">After Discount</p>
                        <p class="mt-1 text-sm font-black text-amber-950" data-after-discount>Rs. 0.00</p>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <div>
                        <label for="discount_amount" class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">Discount</label>
                        <input id="discount_amount" type="number" step="0.01" min="0.01" name="discount_amount" value="{{ old('discount_amount') }}" class="mt-1 h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-black text-slate-950 outline-none focus:border-lime-400 focus:bg-white" placeholder="Enter amount">
                    </div>
                    <button type="button" data-select-visible class="h-11 self-end rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 transition hover:bg-slate-50">Select Visible</button>
                </div>
            </section>

            <section class="space-y-3">
                @forelse($items as $item)
                    @php
                        $quantity = (float) ($item->delivered_price_quantity ?: $item->price_quantity);
                        $lineTotal = (float) ($item->final_line_total ?: $item->line_subtotal);
                        $isChecked = in_array((string) $item->id, $oldSelectedItems, true);
                    @endphp
                    <label class="block rounded-2xl border border-slate-200 bg-white p-3 shadow-sm transition has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" name="selected_items[]" value="{{ $item->id }}" data-discount-item data-line-total="{{ number_format($lineTotal, 2, '.', '') }}" class="mt-1 h-5 w-5 rounded border-slate-300 text-teal-600 focus:ring-teal-500" @checked($isChecked)>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-950">{{ $item->product?->name ?? $item->product_name }}</p>
                                        <p class="mt-0.5 text-[11px] font-semibold text-slate-500">Code {{ $item->product?->sku ?: 'NA' }} - {{ $item->product?->category?->name ?? 'No Category' }}</p>
                                    </div>
                                    <p class="shrink-0 text-sm font-black text-slate-950">Rs. {{ number_format($lineTotal, 2) }}</p>
                                </div>

                                <div class="mt-3 grid grid-cols-3 gap-2 text-[11px] font-bold text-slate-600">
                                    <div>
                                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Qty</p>
                                        <p class="mt-0.5 text-slate-950">{{ number_format($quantity, 2) }} {{ $item->price_unit ?: $item->unit }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Bill Price</p>
                                        <p class="mt-0.5 text-slate-950">Rs. {{ number_format((float) $item->unit_price, 2) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Line</p>
                                        <p class="mt-0.5 text-slate-950">Rs. {{ number_format($lineTotal, 2) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </label>
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-xs font-bold text-slate-400">
                        No items found for {{ $selectedCategory?->name ?? 'this category' }}@if($search !== '') matching "{{ $search }}"@endif.
                    </div>
                @endforelse
            </section>

            @if($items->isNotEmpty())
                <div class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 p-3 shadow-[0_-8px_24px_rgba(15,23,42,0.08)] backdrop-blur lg:sticky lg:bottom-0 lg:rounded-2xl lg:border lg:shadow-sm">
                    <div class="mx-auto grid max-w-4xl grid-cols-[minmax(0,1fr)_auto] items-center gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-500"><span data-selected-count>0</span> selected</p>
                            <p class="mt-0.5 truncate text-xs font-bold text-slate-700" data-discount-help>Select products and enter discount.</p>
                        </div>
                        <button data-apply-discount class="h-11 rounded-xl bg-teal-600 px-4 text-xs font-black text-white shadow-sm transition hover:bg-teal-500 disabled:cursor-not-allowed disabled:bg-slate-300" disabled>
                            Apply Discount
                        </button>
                    </div>
                </div>
            @endif
        </form>
    </div>

    <script>
        (() => {
            const form = document.querySelector('[data-discount-form]');
            if (!form) return;

            const items = Array.from(document.querySelectorAll('[data-discount-item]'));
            const discountInput = document.getElementById('discount_amount');
            const selectedTotalNode = document.querySelector('[data-selected-total]');
            const afterDiscountNode = document.querySelector('[data-after-discount]');
            const selectedCountNode = document.querySelector('[data-selected-count]');
            const helpNode = document.querySelector('[data-discount-help]');
            const applyButton = document.querySelector('[data-apply-discount]');
            const selectVisibleButton = document.querySelector('[data-select-visible]');

            function money(amount) {
                return `Rs. ${amount.toFixed(2)}`;
            }

            function updateSummary() {
                const selectedItems = items.filter((item) => item.checked);
                const selectedTotal = selectedItems.reduce((total, item) => total + (Number(item.dataset.lineTotal || 0) || 0), 0);
                const discount = Number(discountInput?.value || 0) || 0;
                const afterDiscount = Math.max(0, selectedTotal - discount);

                selectedTotalNode.textContent = money(selectedTotal);
                afterDiscountNode.textContent = money(afterDiscount);
                selectedCountNode.textContent = selectedItems.length;

                const isValid = selectedItems.length > 0 && discount > 0 && discount < selectedTotal;
                applyButton.disabled = !isValid;

                if (selectedItems.length === 0) {
                    helpNode.textContent = 'Select products and enter discount.';
                } else if (discount <= 0) {
                    helpNode.textContent = 'Enter discount amount.';
                } else if (discount >= selectedTotal) {
                    helpNode.textContent = 'Discount must be less than selected total.';
                } else {
                    helpNode.textContent = `${money(discount)} will be shared across selected products.`;
                }
            }

            items.forEach((item) => item.addEventListener('change', updateSummary));
            discountInput?.addEventListener('input', updateSummary);
            selectVisibleButton?.addEventListener('click', () => {
                const shouldSelect = items.some((item) => !item.checked);
                items.forEach((item) => item.checked = shouldSelect);
                updateSummary();
            });

            updateSummary();
        })();
    </script>
</x-layouts.app>
