<x-layouts.app title="Edit Bill Prices">
    @php
        $billDate = $invoice->business_date;
        $invoiceTotal = (float) ($invoice->final_total ?: $invoice->subtotal);
        $detailQuery = array_filter([
            'search' => $search !== '' ? $search : null,
            'category_id' => $categoryId,
        ], fn ($value) => $value !== null && $value !== '');
    @endphp

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-4">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-900">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-900">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div id="special-price-save-info" class="hidden rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-900"></div>

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm print:hidden">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.18em] text-slate-500">Special Price Edit</p>
                    <h1 class="mt-0.5 text-lg font-black text-slate-950">{{ $invoice->shop?->name ?? 'Unknown shop' }}</h1>
                    <p class="mt-0.5 text-xs font-semibold text-slate-600">{{ $invoice->invoice_number }} • {{ $billDate->format('d M Y') }}</p>
                    <p class="mt-1 text-[11px] font-bold text-slate-500">Items sorted by product code.</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('purchaser.bill-prices.discount', $invoice) }}" class="inline-flex h-9 flex-1 items-center justify-center rounded-xl bg-teal-600 px-3 text-xs font-black text-white transition hover:bg-teal-500 sm:flex-none">Discount From Total</a>
                    <a href="{{ route('purchaser.bill-prices.index', ['date' => $billDate->toDateString()]) }}" class="inline-flex h-9 flex-1 items-center justify-center rounded-xl border border-slate-200 px-3 text-xs font-black text-slate-700 transition hover:bg-slate-50 sm:flex-none">Back to Bills</a>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="relative mx-auto max-w-[40rem] bg-white px-3 py-5 text-slate-950 sm:px-6 sm:py-7">
                <header class="border-b border-dashed border-slate-400 pb-3 text-center">
                    <h2 class="text-xl font-black uppercase tracking-wide text-slate-950">Bill Invoice</h2>
                    <p class="mt-2 text-base font-black uppercase leading-tight text-slate-950">{{ $invoice->shop?->name ?? 'Unknown shop' }}</p>
                    @if($invoice->shop?->code)
                        <p class="mt-0.5 text-[11px] font-semibold leading-tight text-slate-700">Shop Code: {{ $invoice->shop->code }}</p>
                    @endif
                </header>

                <div class="grid grid-cols-1 gap-3 border-b border-dashed border-slate-400 py-3 text-[11px] font-bold text-slate-800 sm:grid-cols-2">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">Invoice No</p>
                        <p class="mt-0.5 font-mono text-xs font-black text-slate-950">{{ $invoice->invoice_number }}</p>
                    </div>
                    <div class="sm:text-right">
                        <p>Date: {{ $billDate->format('d M Y') }}</p>
                        <p class="mt-1">Status: {{ ucfirst((string) $invoice->status) }}</p>
                    </div>
                </div>

                <div class="sticky top-0 z-10 border-b border-dashed border-slate-400 bg-white py-3 print:static">
                    <form method="GET" action="{{ route('purchaser.bill-prices.show', $invoice) }}" class="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                        @if($categoryId !== null)
                            <input type="hidden" name="category_id" value="{{ $categoryId }}">
                        @endif
                        <input type="search" name="search" value="{{ $search }}" placeholder="Search code, item, category..." class="h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 outline-none focus:border-lime-400 focus:bg-white">
                        <div class="flex gap-2">
                            <button class="h-10 flex-1 rounded-xl bg-slate-950 px-4 text-xs font-black text-white transition hover:bg-slate-900 sm:flex-none">Search</button>
                            @if($search !== '')
                                <a href="{{ route('purchaser.bill-prices.show', array_filter(['invoice' => $invoice, 'category_id' => $categoryId], fn ($value) => $value !== null && $value !== '')) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-black text-slate-600 transition hover:bg-slate-100">Clear</a>
                            @endif
                        </div>
                    </form>

                    <p class="mb-2 text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">Category</p>
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1">
                        <a href="{{ route('purchaser.bill-prices.show', array_filter(['invoice' => $invoice, 'search' => $search !== '' ? $search : null], fn ($value) => $value !== null && $value !== '')) }}" class="shrink-0 rounded-full border px-3 py-1.5 text-[11px] font-black uppercase tracking-wider transition {{ $categoryId === null ? 'border-slate-950 bg-slate-950 text-white shadow-sm' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}">All</a>
                        @foreach($categories as $category)
                            <a href="{{ route('purchaser.bill-prices.show', array_filter(['invoice' => $invoice, 'category_id' => $category->id, 'search' => $search !== '' ? $search : null], fn ($value) => $value !== null && $value !== '')) }}" class="shrink-0 rounded-full border px-3 py-1.5 text-[11px] font-black uppercase tracking-wider transition {{ (string) $categoryId === (string) $category->id ? 'border-teal-600 bg-teal-600 text-white shadow-sm' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}">{{ $category->name }}</a>
                        @endforeach
                    </div>
                </div>

                <form method="POST" action="{{ route('purchaser.bill-prices.invoice-prices.update', $invoice) }}" data-special-price-form>
                    @csrf
                    <input type="hidden" name="category_id" value="{{ $categoryId }}">
                    <input type="hidden" name="search" value="{{ $search }}">

                    <div class="border-b border-dashed border-slate-400 py-3">
                        <div class="space-y-3">
                            @forelse($items as $item)
                                @php
                                    $specialPrice = $specialPrices->get($item->product_id);
                                    $quantity = (float) ($item->delivered_price_quantity ?: $item->price_quantity);
                                    $lineTotal = (float) ($item->final_line_total ?: $item->line_subtotal);
                                @endphp
                                <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-black text-slate-950">{{ $item->product?->name ?? $item->product_name }}</p>
                                            <p class="mt-0.5 text-[11px] font-semibold text-slate-500">Code {{ $item->product?->sku ?: 'NA' }} • {{ $item->product?->category?->name ?? 'No Category' }} • Qty {{ number_format($quantity, 2) }} {{ $item->price_unit ?: $item->unit }}</p>
                                        </div>
                                        <p class="shrink-0 text-xs font-black text-slate-950">₹{{ number_format($lineTotal, 2) }}</p>
                                    </div>

                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <div>
                                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Bill Price</p>
                                            <p class="mt-1 text-sm font-black text-slate-950">₹{{ number_format((float) $item->unit_price, 2) }}</p>
                                        </div>
                                        <div>
                                            <label class="text-[9px] font-black uppercase tracking-wider text-slate-400">Special Price</label>
                                            <input type="hidden" name="prices[{{ $item->id }}][product_id]" value="{{ $item->product_id }}">
                                            <input type="hidden" name="prices[{{ $item->id }}][price_unit]" value="{{ $item->price_unit ?: $item->unit }}">
                                            <input type="hidden" name="prices[{{ $item->id }}][reason]" value="Bill special price edit">
                                            <input type="number" step="0.01" min="0.01" name="prices[{{ $item->id }}][selling_price]" value="{{ $specialPrice ? number_format((float) $specialPrice->selling_price, 2, '.', '') : '' }}" class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-right text-sm font-black text-slate-950 outline-none focus:border-lime-400" placeholder="0.00" data-special-price-input data-item-id="{{ $item->id }}" data-product-id="{{ $item->product_id }}" data-product-name="{{ $item->product?->name ?? $item->product_name }}" data-price-unit="{{ $item->price_unit ?: $item->unit }}">
                                        </div>
                                    </div>

                                    <p id="special-status-{{ $item->id }}" class="mt-2 text-right text-[10px] font-black uppercase tracking-wider {{ $specialPrice?->status === 'approved' ? 'text-emerald-600' : ($specialPrice ? 'text-amber-600' : 'text-slate-400') }}">
                                        @if($specialPrice)
                                            {{ ucfirst($specialPrice->status) }} special price
                                            @if($specialPrice->approvedBy)
                                                by {{ $specialPrice->approvedBy->name }}
                                            @elseif($specialPrice->createdBy)
                                                by {{ $specialPrice->createdBy->name }}
                                            @endif
                                            @if($specialPrice->approved_at)
                                                · {{ $specialPrice->approved_at->format('g:i A') }}
                                            @endif
                                        @else
                                            No special price
                                        @endif
                                    </p>
                                </article>
                            @empty
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 text-center text-xs font-bold text-slate-400">
                                    No items found for {{ $selectedCategory?->name ?? 'this category' }}@if($search !== '') matching "{{ $search }}"@endif.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="ml-auto w-full max-w-full border-b border-dashed border-slate-400 py-3 text-[11px] font-bold text-slate-800 sm:max-w-[20rem]">
                        <div class="flex items-center justify-between">
                            <span>Visible Total</span>
                            <span>₹{{ number_format((float) $visibleTotal, 2) }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-sm font-black text-slate-950">
                            <span>Total Bill</span>
                            <span>₹{{ number_format($invoiceTotal, 2) }}</span>
                        </div>
                    </div>

                    @if($items->isNotEmpty())
                        <div class="sticky bottom-20 bg-white/95 pt-3 backdrop-blur print:hidden lg:bottom-0">
                            <button class="h-12 w-full rounded-xl bg-teal-600 text-sm font-black text-white shadow-sm transition hover:bg-teal-500 active:scale-[0.99]">
                                Save Special Prices
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </section>
    </div>

    <script>
        (() => {
            const form = document.querySelector('[data-special-price-form]');
            if (!form) return;

            const csrfToken = '{{ csrf_token() }}';
            const categoryId = '{{ $categoryId }}';
            const saveInfo = document.getElementById('special-price-save-info');
            const timers = {};

            function statusClass(tone) {
                if (tone === 'success') return 'mt-2 text-right text-[10px] font-black uppercase tracking-wider text-emerald-600';
                if (tone === 'error') return 'mt-2 text-right text-[10px] font-black uppercase tracking-wider text-rose-600';
                if (tone === 'saving') return 'mt-2 text-right text-[10px] font-black uppercase tracking-wider text-amber-600';
                return 'mt-2 text-right text-[10px] font-black uppercase tracking-wider text-slate-400';
            }

            function setItemStatus(itemId, message, tone = 'muted') {
                const statusEl = document.getElementById(`special-status-${itemId}`);
                if (!statusEl) return;
                statusEl.className = statusClass(tone);
                statusEl.textContent = message;
            }

            function showSaveInfo(message) {
                if (!saveInfo || !message) return;
                saveInfo.textContent = message;
                saveInfo.classList.remove('hidden');
                setTimeout(() => saveInfo.classList.add('hidden'), 3200);
            }

            function highlightInput(input) {
                input.classList.remove('border-slate-200');
                input.classList.add('border-emerald-400', 'bg-emerald-50');
                setTimeout(() => {
                    input.classList.add('border-slate-200');
                    input.classList.remove('border-emerald-400', 'bg-emerald-50');
                }, 1400);
            }

            function saveSpecialPrice(input) {
                const itemId = input.dataset.itemId;
                const priceValue = Number(input.value || 0);

                if (!itemId || !Number.isFinite(priceValue) || priceValue <= 0) {
                    return;
                }

                setItemStatus(itemId, 'Saving special price...', 'saving');

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        category_id: categoryId || null,
                        prices: {
                            [itemId]: {
                                product_id: input.dataset.productId,
                                selling_price: priceValue,
                                price_unit: input.dataset.priceUnit,
                                reason: 'Bill special price edit',
                            },
                        },
                    }),
                })
                    .then((response) => {
                        if (!response.ok) throw new Error('Save failed');
                        return response.json();
                    })
                    .then((data) => {
                        if (!data.success) throw new Error('Save failed');

                        const updater = data.updated_by_name || 'Purchaser';
                        const time = data.updated_time || '';
                        const productName = input.dataset.productName || 'Item';
                        const statusText = `Approved special price by ${updater}${time ? ' · ' + time : ''}`;

                        setItemStatus(itemId, statusText, 'success');
                        showSaveInfo(`${productName} updated as special price by ${updater}.`);
                        highlightInput(input);
                    })
                    .catch(() => {
                        setItemStatus(itemId, 'Special price update failed', 'error');
                    });
            }

            document.querySelectorAll('[data-special-price-input]').forEach((input) => {
                input.addEventListener('input', () => {
                    const itemId = input.dataset.itemId;
                    if (timers[itemId]) clearTimeout(timers[itemId]);

                    const priceValue = Number(input.value || 0);
                    if (!Number.isFinite(priceValue) || priceValue <= 0) {
                        setItemStatus(itemId, 'Enter a special price', 'muted');
                        return;
                    }

                    timers[itemId] = setTimeout(() => saveSpecialPrice(input), 650);
                });
            });
        })();
    </script>
</x-layouts.app>
