<x-layouts.app title="Purchase Invoice">
    @php
        $lineItems = $invoice->purchaserCart?->items ?? $invoice->goodsReceived?->items ?? collect();
        $businessDate = $invoice->purchaserCart?->business_date ?? $invoice->created_at;
        $netAmount = max(0, round((float) $invoice->amount - (float) $invoice->discount_amount, 2));
        $balance = max(0, round($netAmount - (float) $invoice->paid_amount, 2));
        $backParameters = array_filter($backRouteParameters ?? []);
        $paymentModalData = [
            'number' => $invoice->invoice_number,
            'supplier' => $invoice->supplier?->name,
            'amount' => round((float) $invoice->amount, 2),
            'discountAmount' => round((float) $invoice->discount_amount, 2),
            'paidAmount' => round((float) $invoice->paid_amount, 2),
            'paymentMethod' => $invoice->payment_method ?: 'Cash',
            'paymentNote' => $invoice->payment_note,
            'paymentDetails' => $invoice->payment_details,
            'creditApproved' => (bool) $invoice->supplier?->credit_approved,
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-5xl lg:gap-4 lg:px-6 lg:py-4">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Invoice Details</p>
                    <h1 class="mt-1 text-xl font-black text-slate-950">{{ $invoice->invoice_number }}</h1>
                    <p class="mt-1 text-xs font-semibold text-slate-600">
                        {{ $invoice->supplier?->name ?: 'Vendor pending' }}
                        @if ($businessDate)
                            • {{ $businessDate->format('d M Y') }}
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route($backRouteName, $backParameters) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 px-4 text-xs font-black text-slate-700 hover:bg-slate-50">
                        Back
                    </a>
                    <a href="{{ route($billPdfRouteName, $invoice) }}" target="_blank" class="inline-flex h-10 items-center justify-center rounded-xl border border-teal-200 bg-teal-50 px-4 text-xs font-black text-teal-700 hover:bg-teal-100">
                        Open Bill
                    </a>
                    <button type="button" onclick='openShowPaymentModal(@json($paymentModalData), "{{ route($paymentUpdateRouteName, $invoice) }}")' class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950 px-4 text-xs font-black text-white hover:bg-slate-800">
                        Update Payment
                    </button>
                </div>
            </div>
        </section>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Bill Amount</p>
                <p class="mt-1 text-lg font-black text-slate-950">₹{{ number_format((float) $invoice->amount, 2) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Discount</p>
                <p class="mt-1 text-lg font-black text-slate-950">₹{{ number_format((float) $invoice->discount_amount, 2) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Paid</p>
                <p class="mt-1 text-lg font-black text-emerald-700">₹{{ number_format((float) $invoice->paid_amount, 2) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Balance</p>
                <p class="mt-1 text-lg font-black {{ $balance > 0 ? 'text-amber-700' : 'text-emerald-700' }}">₹{{ number_format($balance, 2) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Payment</p>
                <p class="mt-1 text-lg font-black text-slate-950">{{ $invoice->payment_method ?: 'Pending' }}</p>
                <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ str($invoice->payment_status ?: 'unpaid')->replace('_', ' ')->title() }}</p>
            </div>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
            <div class="grid gap-3 md:grid-cols-2">
                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Vendor</p>
                    <p class="mt-1 text-sm font-black text-slate-950">{{ $invoice->supplier?->name ?: 'Vendor pending' }}</p>
                    <p class="mt-1 text-sm font-semibold text-slate-600">{{ $invoice->supplier?->mobile_number ?: 'Mobile pending' }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Linked Cart</p>
                    <p class="mt-1 text-sm font-black text-slate-950">{{ $invoice->purchaserCart?->cart_number ?: 'Not linked' }}</p>
                    <p class="mt-1 text-sm font-semibold text-slate-600">{{ $invoice->goodsReceived?->grn_number ?: 'GRN pending' }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
            <h2 class="text-sm font-black text-slate-950">Line Items</h2>
            <div class="mt-4 space-y-2">
                @forelse ($lineItems as $item)
                    @php
                        $productName = $item->product?->name ?? $item->product_name ?? 'Item';
                        $quantity = $item->quantity ?? $item->received_qty ?? 0;
                        $unit = $item->product?->unit ?? $item->unit ?? '-';
                        $unitPrice = $item->unit_price ?? 0;
                        $lineTotal = $item->line_total ?? ((float) $quantity * (float) $unitPrice);
                    @endphp
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-black text-slate-950">{{ $productName }}</p>
                                <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ number_format((float) $quantity, 2) }} {{ $unit }} @ ₹{{ number_format((float) $unitPrice, 2) }}</p>
                            </div>
                            <p class="text-sm font-black text-slate-950">₹{{ number_format((float) $lineTotal, 2) }}</p>
                        </div>
                    </div>
                @empty
                    <p class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm font-bold text-slate-500">No line items available for this invoice.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div id="show-payment-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" onclick="if (event.target === this) closeShowPaymentModal()">
        <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <div>
                    <h3 class="text-sm font-black text-slate-950">Payment Update</h3>
                    <p id="show-payment-title" class="mt-1 text-[11px] font-semibold text-slate-500"></p>
                </div>
                <button type="button" onclick="closeShowPaymentModal()" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form id="show-payment-form" method="POST" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Total Bill</span>
                        <span id="show-payment-total" class="text-slate-900"></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Discount</span>
                        <span id="show-payment-discount" class="text-slate-900"></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Net Payable</span>
                        <span id="show-payment-net" class="text-slate-900"></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Remaining</span>
                        <span id="show-payment-balance" class="text-amber-700"></span>
                    </div>
                    <p id="show-payment-warning" class="mt-2 text-[10px] font-semibold text-amber-700"></p>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Discount</label>
                    <input id="show_discount_amount" type="number" step="0.01" min="0" name="discount_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Method</label>
                    <select id="show_payment_method" name="payment_method" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                        <option value="Cash">Cash</option>
                        <option value="Online">Online</option>
                        <option value="GPay">GPay</option>
                        <option value="Credit">Credit</option>
                    </select>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Paid Amount</label>
                    <input id="show_paid_amount" type="number" step="0.01" min="0" name="paid_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Note</label>
                    <input id="show_payment_note" type="text" name="payment_note" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Details</label>
                    <textarea id="show_payment_details" name="payment_details" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none"></textarea>
                </div>

                <button type="submit" class="h-10 w-full rounded-xl bg-teal-600 text-xs font-black text-white hover:bg-teal-500">Save Payment Update</button>
            </form>
        </div>
    </div>

    <script>
        let showPaymentAmount = 0;
        let showPaymentDiscount = 0;
        let showPaymentCreditApproved = false;

        function openShowPaymentModal(invoice, actionUrl) {
            showPaymentAmount = Number(invoice.amount || 0);
            showPaymentDiscount = Number(invoice.discountAmount || 0);
            showPaymentCreditApproved = Boolean(invoice.creditApproved);

            document.getElementById('show-payment-form').action = actionUrl;
            document.getElementById('show-payment-title').textContent = `${invoice.number} • ${invoice.supplier ?? 'Supplier pending'}`;
            document.getElementById('show-payment-total').textContent = `₹${Number(invoice.amount || 0).toFixed(2)}`;
            document.getElementById('show_discount_amount').value = Number(invoice.discountAmount || 0).toFixed(2);
            document.getElementById('show_payment_method').value = invoice.paymentMethod || 'Cash';
            document.getElementById('show_paid_amount').value = Number(invoice.paidAmount || 0).toFixed(2);
            document.getElementById('show_payment_note').value = invoice.paymentNote || '';
            document.getElementById('show_payment_details').value = invoice.paymentDetails || '';

            updateShowPaymentStatus();
            document.getElementById('show-payment-modal').classList.remove('hidden');
            document.getElementById('show-payment-modal').classList.add('flex');
        }

        function closeShowPaymentModal() {
            document.getElementById('show-payment-modal').classList.add('hidden');
            document.getElementById('show-payment-modal').classList.remove('flex');
        }

        function updateShowPaymentStatus() {
            const method = document.getElementById('show_payment_method').value;
            const discountAmount = Math.max(0, Number(document.getElementById('show_discount_amount').value || 0));
            const paidAmount = Number(document.getElementById('show_paid_amount').value || 0);
            const netAmount = Math.max(0, showPaymentAmount - discountAmount);
            const balance = Math.max(0, netAmount - paidAmount);
            const balanceNode = document.getElementById('show-payment-balance');
            const discountNode = document.getElementById('show-payment-discount');
            const netNode = document.getElementById('show-payment-net');
            const warningNode = document.getElementById('show-payment-warning');

            discountNode.textContent = `₹${discountAmount.toFixed(2)}`;
            netNode.textContent = `₹${netAmount.toFixed(2)}`;
            balanceNode.textContent = `₹${balance.toFixed(2)}`;
            balanceNode.className = balance > 0 ? 'text-amber-700' : 'text-emerald-700';

            if (method === 'Credit') {
                warningNode.textContent = showPaymentCreditApproved
                    ? 'Credit selected. Payment will stay pending until it is cleared in full.'
                    : 'Credit selected but supplier credit is not approved yet.';
                return;
            }

            warningNode.textContent = balance > 0
                ? 'Payment is not done fully. Remaining balance will stay pending.'
                : 'Full payment entered. This purchase will be marked received.';
        }

        document.getElementById('show_discount_amount')?.addEventListener('input', updateShowPaymentStatus);
        document.getElementById('show_payment_method')?.addEventListener('change', updateShowPaymentStatus);
        document.getElementById('show_paid_amount')?.addEventListener('input', updateShowPaymentStatus);
    </script>
</x-layouts.app>
