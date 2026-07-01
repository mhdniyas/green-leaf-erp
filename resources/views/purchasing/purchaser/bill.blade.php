<x-layouts.app title="Purchaser Bill">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 sm:px-2 lg:max-w-5xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.18em] text-slate-500">Stage 4</p>
                    <h1 class="mt-0.5 text-lg font-black text-slate-950">Bill processing</h1>
                    <p class="mt-0.5 text-xs font-semibold text-slate-600">{{ $cart->cart_number }} • {{ $cart->supplier?->name ?: 'Draft Cart' }} • {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</p>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <a href="{{ route('purchaser.cart', ['date' => $date, 'cart' => $cart->id]) }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 px-3 text-xs font-black text-slate-700 hover:bg-slate-50">Back to Cart</a>
                    <a href="{{ route('purchaser.history', ['date' => $date]) }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 px-3 text-xs font-black text-slate-700 hover:bg-slate-50">History</a>
                </div>
            </div>
        </section>

        <form action="{{ route('purchaser.carts.submit') }}" method="POST" class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_20rem]">
            @csrf
            <input type="hidden" name="business_date" value="{{ $date }}">
            <input type="hidden" name="cart_id" value="{{ $cart->id }}">
            <input type="hidden" name="supplier_id" value="{{ $cart->supplier_id }}">
            <input type="hidden" name="return_to" value="vendors">

            <div class="space-y-2">
                <section class="space-y-1.5">
                    @foreach ($cart->items as $item)
                        <article class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white p-2.5 shadow-sm">
                            <div class="flex min-w-0 flex-col gap-2 rounded-lg bg-slate-50 p-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                                <div class="min-w-0 flex-1 flex items-center justify-between gap-2 sm:block pb-1 border-b border-slate-100/60 sm:pb-0 sm:border-0">
                                    <p class="font-black text-slate-900 truncate text-[11px]">{{ $item->product->name }}</p>
                                    <p class="text-[9px] font-semibold text-slate-500 mt-0.5">{{ number_format((float) $item->quantity, 2) }} {{ $item->product->unit }}</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 justify-between sm:justify-end min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="text-[10px] text-slate-400 font-bold">@</span>
                                        <div class="flex flex-col items-center gap-0.5">
                                            @php
                                                $vendorPriceHint = $vendorPriceHints[$item->product_id] ?? 0;
                                            @endphp
                                            <input id="price-{{ $item->id }}" type="number" step="0.01" min="0" name="items[{{ $item->id }}][unit_price]" value="{{ old("items.{$item->id}.unit_price", number_format((float) $item->unit_price, 2, '.', '')) }}" class="bill-price h-7 w-14 text-center text-[10px] font-semibold border border-slate-200 rounded-md bg-white focus:outline-none shrink-0" data-quantity="{{ number_format((float) $item->quantity, 3, '.', '') }}">
                                            @if ($vendorPriceHint > 0)
                                                <span class="text-[8px] font-bold text-amber-700">Prev ₹{{ number_format((float) $vendorPriceHint, 2) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <span class="text-[10px] font-black text-slate-700 bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded-md shrink-0">
                                        <span class="bill-line-total">₹ {{ number_format((float) $item->line_total, 2) }}</span>
                                    </span>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </section>
            </div>

            <aside class="space-y-3">
                <section class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <div class="flex items-center justify-between text-xs font-semibold text-slate-600">
                            <span>Subtotal</span>
                            <span id="bill-subtotal" class="font-bold text-slate-900" data-base-subtotal="{{ number_format($subtotal, 2, '.', '') }}">₹ {{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between gap-3">
                            <label for="discount_amount" class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500 shrink-0">Discount</label>
                            <input id="discount_amount" type="number" step="0.01" min="0" name="discount_amount" value="{{ old('discount_amount', number_format((float) $cart->discount_amount, 2, '.', '')) }}" class="h-8 w-24 rounded-md border border-slate-200 bg-white px-2 text-right text-xs font-semibold text-slate-900 focus:outline-none">
                        </div>
                        <div class="mt-2.5 border-t border-slate-200/60 pt-2 flex items-center justify-between text-sm font-black text-slate-950">
                            <span>Total Payable</span>
                            <span id="bill-total">₹ {{ number_format(max(0, $subtotal - (float) $cart->discount_amount), 2) }}</span>
                        </div>
                    </div>

                    <div class="mt-3 space-y-2">
                        <div>
                            <label for="bill_number" class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Bill / Invoice Number</label>
                            <input id="bill_number" type="text" name="bill_number" value="{{ old('bill_number', $cart->bill_number) }}" class="mt-1 h-8 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Payment Method</p>
                            <div class="mt-1 grid grid-cols-3 gap-1.5">
                                @foreach (['Cash' => 'Paid', 'GPay' => 'UPI', 'Online' => 'Transfer', 'Credit' => 'Later'] as $method => $caption)
                                    <label class="flex cursor-pointer items-center gap-1 rounded-md border border-slate-200 bg-slate-50 p-1.5 hover:bg-slate-100">
                                        <input type="radio" name="payment_method" value="{{ $method }}" @checked(old('payment_method', $cart->payment_method ?: 'Cash') === $method) class="h-3.5 w-3.5 border-slate-300 text-teal-600 focus:ring-teal-500">
                                        <span class="min-w-0">
                                            <span class="block text-[10px] font-black text-slate-900 leading-none">{{ $method }}</span>
                                            <span class="block text-[8px] font-semibold text-slate-500 leading-none mt-0.5 truncate">{{ $caption }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @if ($cart->supplier && ! $cart->supplier->credit_approved)
                                <p class="mt-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-2 text-[10px] font-semibold text-amber-800">Credit is blocked for this supplier until approval.</p>
                            @endif
                        </div>
                        <div>
                            <label for="paid_amount" class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Amount Paid Now</label>
                            <input id="paid_amount" type="number" step="0.01" min="0" name="paid_amount" value="{{ old('paid_amount', number_format((float) $cart->paid_amount, 2, '.', '')) }}" class="mt-1 h-8 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                        </div>
                        <div>
                            <label for="payment_note" class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Payment Note</label>
                            <input id="payment_note" type="text" name="payment_note" value="{{ old('payment_note', $cart->payment_note) }}" class="mt-1 h-8 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                        </div>
                        <div>
                            <label for="payment_details" class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Reference / Settlement Details</label>
                            <textarea id="payment_details" name="payment_details" rows="2" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">{{ old('payment_details', $cart->payment_details) }}</textarea>
                        </div>
                        <div>
                            <label for="notes" class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Notes</label>
                            <textarea id="notes" name="notes" rows="2" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">{{ old('notes', $cart->notes) }}</textarea>
                        </div>
                    </div>

                    <button type="submit" class="mt-3 inline-flex h-9 w-full items-center justify-center rounded-lg bg-teal-600 px-4 text-xs font-black text-white hover:bg-teal-500 shadow-sm">
                        Submit Purchase
                    </button>
                </section>
            </aside>
        </form>
    </div>

    <script>
        (() => {
            const priceInputs = Array.from(document.querySelectorAll('.bill-price'));
            const subtotalNode = document.getElementById('bill-subtotal');
            const totalNode = document.getElementById('bill-total');
            const discountNode = document.getElementById('discount_amount');

            if (! priceInputs.length || ! subtotalNode || ! totalNode || ! discountNode) {
                return;
            }

            const formatCurrency = (value) => `₹ ${Number(value).toFixed(2)}`;

            const recalculate = () => {
                let subtotal = 0;

                priceInputs.forEach((input) => {
                    const quantity = Number(input.dataset.quantity || 0);
                    const unitPrice = Number(input.value || 0);
                    const lineTotal = quantity * unitPrice;
                    subtotal += lineTotal;

                    const card = input.closest('article');
                    const lineTotalNode = card ? card.querySelector('.bill-line-total') : null;
                    if (lineTotalNode) {
                        lineTotalNode.textContent = formatCurrency(lineTotal);
                    }
                });

                const discount = Number(discountNode.value || 0);
                subtotalNode.textContent = formatCurrency(subtotal);
                totalNode.textContent = formatCurrency(Math.max(0, subtotal - discount));
            };

            priceInputs.forEach((input) => input.addEventListener('input', recalculate));
            discountNode.addEventListener('input', recalculate);
            recalculate();
        })();
    </script>
</x-layouts.app>
