<x-layouts.app title="Purchaser Bill">
    @php
        $billDate = \Illuminate\Support\Carbon::parse($date);
        $discountAmount = (float) old('discount_amount', $cart->discount_amount);
        $payableTotal = max(0, (float) $subtotal - $discountAmount);
        $paidAmount = (float) old('paid_amount', $cart->paid_amount);
        $balanceAmount = max(0, $payableTotal - $paidAmount);
        $defaultBillNumber = old('bill_number', $cart->bill_number);
        $supplier = $cart->supplier;
        $companyName = $companyDetails['name'] ?? 'Green Leaf';
        $companyLines = collect([
            $companyDetails['address'] ?? null,
            $companyDetails['phone'] ?? null ? 'Phone: '.$companyDetails['phone'] : null,
            $companyDetails['email'] ?? null ? 'Email: '.$companyDetails['email'] : null,
        ])->filter()->values();
    @endphp

    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 sm:px-2 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm print:hidden">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.18em] text-slate-500">Stage 4</p>
                    <h1 class="mt-0.5 text-lg font-black text-slate-950">Bill invoice</h1>
                    <p class="mt-0.5 text-xs font-semibold text-slate-600">{{ $cart->cart_number }} • {{ $supplier?->name ?: 'Draft Cart' }} • {{ $billDate->format('d M Y') }}</p>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <a href="{{ route('purchaser.cart', ['date' => $date, 'cart' => $cart->id]) }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 px-3 text-xs font-black text-slate-700 hover:bg-slate-50">Back to Cart</a>
                    <a href="{{ route('purchaser.history', ['date' => $date]) }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 px-3 text-xs font-black text-slate-700 hover:bg-slate-50">History</a>
                </div>
            </div>
        </section>

        @if ($supplier && ! $supplier->credit_approved && ! $supplier->credit_approval_requested_at)
            <form id="bill-credit-request-form" action="{{ route('purchasing.suppliers.credit-request', $supplier) }}" method="POST" class="hidden">
                @csrf
                <input type="hidden" name="credit_approval_note" value="Requested from purchaser bill {{ $cart->cart_number }} for {{ $billDate->format('d M Y') }}.">
            </form>
        @endif

        <form action="{{ route('purchaser.carts.submit') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="business_date" value="{{ $date }}">
            <input type="hidden" name="cart_id" value="{{ $cart->id }}">
            <input type="hidden" name="supplier_id" value="{{ $cart->supplier_id }}">
            <input type="hidden" name="return_to" value="vendors">

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="relative mx-auto min-h-[46rem] max-w-[36rem] bg-white px-3 py-5 text-slate-950 sm:px-6 sm:py-7">
                    <div id="bill-status-ribbon" class="absolute -left-11 top-7 w-40 -rotate-45 bg-amber-500 py-1 text-center text-xs font-black uppercase tracking-[0.14em] text-white shadow-sm">
                        {{ old('payment_method', $cart->payment_method ?: 'Cash') === 'Credit' || $balanceAmount > 0 ? 'Unpaid' : 'Paid' }}
                    </div>

                    <header class="border-b border-dashed border-slate-400 pb-3 text-center">
                        <h2 class="text-xl font-black uppercase tracking-wide text-slate-950">Bill Invoice</h2>
                        <p class="mt-2 text-base font-black uppercase leading-tight text-slate-950">{{ $companyName }}</p>
                        @foreach ($companyLines as $line)
                            <p class="mt-0.5 text-[11px] font-semibold leading-tight text-slate-700">{{ $line }}</p>
                        @endforeach
                    </header>

                    <div class="grid grid-cols-1 gap-3 border-b border-dashed border-slate-400 py-3 text-[11px] font-bold text-slate-800 sm:grid-cols-2">
                        <div class="min-w-0">
                            <label for="bill_number" class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">Bill No</label>
                            <input id="bill_number" type="text" name="bill_number" value="{{ $defaultBillNumber }}" placeholder="Pending" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-black text-slate-950 focus:bg-white focus:outline-none">
                            <p class="mt-1">Cart: {{ $cart->cart_number }}</p>
                        </div>
                        <div class="sm:text-right">
                            <p>Date: {{ $billDate->format('d M Y') }}</p>
                            <p class="mt-1">Source: {{ $cart->purchaseSourceLabel() }}</p>
                        </div>
                    </div>

                    <div class="border-b border-dashed border-slate-400 py-3 text-[11px] text-slate-700">
                        <p class="font-black uppercase tracking-[0.12em] text-slate-500">Vendor</p>
                        <p class="mt-1 text-sm font-black text-slate-950">{{ $supplier?->name ?: 'Supplier pending' }}</p>
                        <p class="mt-0.5 font-semibold">{{ $supplier?->mobile_number ?: $supplier?->contact ?: 'Mobile pending' }}{{ $supplier?->location ? ' • '.$supplier->location : '' }}</p>
                        @if ($supplier?->payment_terms)
                            <p class="mt-0.5 font-semibold">Terms: {{ $supplier->payment_terms }}</p>
                        @endif
                    </div>

                    <div class="overflow-x-auto border-b border-dashed border-slate-400 py-3">
                        <table class="w-full table-fixed text-left text-[11px]">
                            <thead class="border-b border-dashed border-slate-400 text-[10px] font-black uppercase text-slate-950">
                                <tr>
                                    <th class="w-7 py-1 pr-1">SN</th>
                                    <th class="py-1 pr-2">Item</th>
                                    <th class="w-12 py-1 pr-1 text-right">Qty</th>
                                    <th class="w-16 py-1 pr-1 text-right">Price</th>
                                    <th class="w-20 py-1 text-right">Amt</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cart->items as $item)
                                    @php
                                        $vendorPriceHint = $vendorPriceHints[$item->product_id] ?? 0;
                                    @endphp
                                    <tr class="align-top">
                                        <td class="py-2 pr-1 font-bold">{{ $loop->iteration }}</td>
                                        <td class="py-2 pr-2">
                                            <p class="font-black text-slate-950">{{ $item->product->name }}</p>
                                            <p class="mt-0.5 text-[10px] font-semibold text-slate-500">{{ $item->product->unit }}{{ $vendorPriceHint > 0 ? ' • Prev Rs. '.number_format((float) $vendorPriceHint, 2) : '' }}</p>
                                        </td>
                                        <td class="py-2 pr-1 text-right font-bold">{{ number_format((float) $item->quantity, 2) }}</td>
                                        <td class="py-2 pr-1 text-right">
                                            <input id="price-{{ $item->id }}" type="number" step="0.01" min="0.01" name="items[{{ $item->id }}][unit_price]" value="{{ old("items.{$item->id}.unit_price", number_format((float) $item->unit_price, 2, '.', '')) }}" class="bill-price h-7 w-14 rounded-md border border-slate-200 bg-slate-50 px-1 text-right text-[11px] font-bold text-slate-950 focus:bg-white focus:outline-none" data-quantity="{{ number_format((float) $item->quantity, 3, '.', '') }}">
                                        </td>
                                        <td class="py-2 text-right font-black text-slate-950">
                                            <span class="bill-line-total">Rs. {{ number_format((float) $item->line_total, 2) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="ml-auto w-full max-w-full border-b border-dashed border-slate-400 py-3 text-[11px] font-bold text-slate-800 sm:max-w-[20rem]">
                        <div class="flex items-center justify-between">
                            <span>Subtotal</span>
                            <span id="bill-subtotal" data-base-subtotal="{{ number_format($subtotal, 2, '.', '') }}">Rs. {{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between gap-3">
                            <label for="discount_amount">Discount</label>
                            <input id="discount_amount" type="number" step="0.01" min="0" name="discount_amount" value="{{ old('discount_amount', number_format((float) $cart->discount_amount, 2, '.', '')) }}" class="h-8 w-28 rounded-md border border-slate-200 bg-slate-50 px-2 text-right text-xs font-black text-slate-950 focus:bg-white focus:outline-none">
                        </div>
                        <div class="mt-1.5 flex items-center justify-between text-sm font-black text-slate-950">
                            <span>TOTAL</span>
                            <span id="bill-total">Rs. {{ number_format($payableTotal, 2) }}</span>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between gap-3 text-[11px] text-emerald-700">
                            <label for="paid_amount">Paid</label>
                            <input id="paid_amount" type="number" step="0.01" min="0" name="paid_amount" value="{{ old('paid_amount', number_format((float) $cart->paid_amount, 2, '.', '')) }}" class="h-8 w-28 rounded-md border border-emerald-200 bg-emerald-50 px-2 text-right text-xs font-black text-emerald-800 focus:bg-white focus:outline-none">
                        </div>
                        <div class="mt-1.5 flex items-center justify-between text-[11px] text-amber-700">
                            <span>Balance</span>
                            <span id="bill-balance-preview">Rs. {{ number_format($balanceAmount, 2) }}</span>
                        </div>
                    </div>

                    <div class="border-b border-dashed border-slate-400 py-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Payment Method</p>
                            <div class="mt-1 grid grid-cols-2 gap-1.5">
                                @foreach (['Cash' => 'Paid', 'GPay' => 'UPI', 'Online' => 'Transfer', 'Credit' => 'Later'] as $method => $caption)
                                    <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-md border border-slate-200 bg-slate-50 p-2 hover:bg-slate-100">
                                        <input type="radio" name="payment_method" value="{{ $method }}" @checked(old('payment_method', $cart->payment_method ?: 'Cash') === $method) class="h-3.5 w-3.5 border-slate-300 text-teal-600 focus:ring-teal-500">
                                        <span class="min-w-0">
                                            <span class="block text-[10px] font-black leading-none text-slate-900">{{ $method }}</span>
                                            <span class="mt-0.5 block truncate text-[8px] font-semibold leading-none text-slate-500">{{ $caption }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <p id="credit-payment-note" class="mt-2 hidden text-[10px] font-semibold text-amber-700">Supplier credit keeps paid amount at zero until purchaser or company settles it.</p>
                    </div>

                    <footer class="pt-3 text-center">
                        <p class="text-xs font-black text-slate-800">Thank You</p>
                    </footer>
                </div>
            </section>

            <section class="mx-auto w-full max-w-[36rem] rounded-2xl border border-slate-200 bg-white p-3 shadow-sm print:hidden">
                <div class="grid gap-2 sm:grid-cols-2">
                    <div>
                        <label for="payment_note" class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Payment Note</label>
                        <input id="payment_note" type="text" name="payment_note" value="{{ old('payment_note', $cart->payment_note) }}" class="mt-1 h-9 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                    </div>
                    <div>
                        <label for="payment_details" class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Reference / Settlement Details</label>
                        <input id="payment_details" type="text" name="payment_details" value="{{ old('payment_details', $cart->payment_details) }}" class="mt-1 h-9 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="notes" class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">Notes</label>
                        <textarea id="notes" name="notes" rows="2" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">{{ old('notes', $cart->notes) }}</textarea>
                    </div>
                </div>
            </section>

            <div class="sticky bottom-20 z-20 rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-lg backdrop-blur print:hidden sm:static sm:mx-auto sm:w-full sm:max-w-[36rem]">
                <div class="flex items-center gap-2">
                    <a href="{{ route('purchaser.history', ['date' => $date]) }}" class="inline-flex h-11 w-24 items-center justify-center rounded-xl border border-slate-200 px-3 text-xs font-black text-slate-700 hover:bg-slate-50">History</a>
                    <button type="submit" class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-teal-600 px-4 text-xs font-black text-white shadow-sm hover:bg-teal-500">
                        Submit Purchase
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        (() => {
            const priceInputs = Array.from(document.querySelectorAll('.bill-price'));
            const subtotalNode = document.getElementById('bill-subtotal');
            const totalNode = document.getElementById('bill-total');
            const totalSideNode = document.getElementById('bill-total-side');
            const discountNode = document.getElementById('discount_amount');
            const discountPreviewNode = document.getElementById('bill-discount-preview');
            const paidNode = document.getElementById('paid_amount');
            const paidPreviewNode = document.getElementById('bill-paid-preview');
            const balancePreviewNode = document.getElementById('bill-balance-preview');
            const creditNoteNode = document.getElementById('credit-payment-note');
            const statusRibbonNode = document.getElementById('bill-status-ribbon');
            const billNumberNode = document.getElementById('bill_number');
            const billNumberPreviewNode = document.getElementById('bill-number-preview');
            const paymentMethodNodes = Array.from(document.querySelectorAll('input[name="payment_method"]'));

            if (! priceInputs.length || ! subtotalNode || ! totalNode || ! discountNode) {
                return;
            }

            const formatCurrency = (value) => `Rs. ${Number(value).toFixed(2)}`;

            const selectedPaymentMethod = () => paymentMethodNodes.find((node) => node.checked)?.value ?? 'Cash';

            const recalculate = () => {
                let subtotal = 0;

                priceInputs.forEach((input) => {
                    const quantity = Number(input.dataset.quantity || 0);
                    const unitPrice = Number(input.value || 0);
                    const lineTotal = quantity * unitPrice;
                    subtotal += lineTotal;

                    const row = input.closest('tr');
                    const lineTotalNode = row ? row.querySelector('.bill-line-total') : null;
                    if (lineTotalNode) {
                        lineTotalNode.textContent = formatCurrency(lineTotal);
                    }
                });

                const discount = Math.max(0, Number(discountNode.value || 0));
                const paid = Math.max(0, Number(paidNode?.value || 0));
                const total = Math.max(0, subtotal - discount);
                const balance = Math.max(0, total - paid);
                const isCredit = selectedPaymentMethod() === 'Credit';

                subtotalNode.textContent = formatCurrency(subtotal);
                totalNode.textContent = formatCurrency(total);
                if (totalSideNode) {
                    totalSideNode.textContent = formatCurrency(total);
                }
                if (discountPreviewNode) {
                    discountPreviewNode.textContent = formatCurrency(discount);
                }
                if (paidPreviewNode) {
                    paidPreviewNode.textContent = formatCurrency(isCredit ? 0 : paid);
                }
                if (balancePreviewNode) {
                    balancePreviewNode.textContent = formatCurrency(isCredit ? total : balance);
                }
                if (statusRibbonNode) {
                    const isPaid = ! isCredit && balance <= 0;
                    statusRibbonNode.textContent = isPaid ? 'Paid' : 'Unpaid';
                    statusRibbonNode.classList.toggle('bg-emerald-600', isPaid);
                    statusRibbonNode.classList.toggle('bg-amber-500', ! isPaid);
                }
            };

            priceInputs.forEach((input) => input.addEventListener('input', recalculate));
            discountNode.addEventListener('input', recalculate);
            paidNode?.addEventListener('input', recalculate);
            billNumberNode?.addEventListener('input', () => {
                if (billNumberPreviewNode) {
                    billNumberPreviewNode.textContent = billNumberNode.value || 'Pending';
                }
            });
            paymentMethodNodes.forEach((input) => {
                input.addEventListener('change', () => {
                    const isCredit = selectedPaymentMethod() === 'Credit';

                    if (paidNode instanceof HTMLInputElement) {
                        if (isCredit) {
                            paidNode.value = '0.00';
                        }

                        paidNode.readOnly = isCredit;
                        paidNode.classList.toggle('bg-amber-50', isCredit);
                    }

                    creditNoteNode?.classList.toggle('hidden', ! isCredit);
                    recalculate();
                });
            });
            paymentMethodNodes.find((node) => node.checked)?.dispatchEvent(new Event('change'));
            recalculate();
        })();
    </script>
</x-layouts.app>
