<x-layouts.app title="Pay Multiple Bills">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-3xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')

        {{-- Page Header --}}
        <section class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs lg:rounded-2xl">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-teal-100 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-teal-700">Bulk Payment</span>
                    <h1 class="text-base font-black text-slate-950">{{ $supplier->name }}</h1>
                </div>
                <p class="mt-0.5 text-[11px] font-semibold text-slate-500">
                    {{ $supplier->mobile_number ?: 'Mobile pending' }}{{ $supplier->location ? ' • ' . $supplier->location : '' }}
                </p>
            </div>

            <div class="mt-2.5 flex flex-wrap items-center gap-2">
                <a href="{{ route('purchaser.suppliers.show', ['supplier' => $supplier, 'date' => $date]) }}" class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-black text-slate-700 hover:bg-white shrink-0">
                    ← Back to Vendor
                </a>
            </div>
        </section>

        {{-- Payment Form --}}
        <form method="POST" action="{{ route('purchaser.suppliers.bulk-payment', $supplier) }}" class="space-y-3">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">

            {{-- Bill Selection --}}
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs lg:rounded-2xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-black text-slate-950">Select Bills to Pay</h2>
                    <p class="text-[10px] font-bold text-slate-500">{{ $pendingBills->count() }} pending bills</p>
                </div>

                <div class="mt-3 space-y-2 max-h-96 overflow-y-auto">
                    @foreach ($pendingBills as $bill)
                        <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 cursor-pointer hover:bg-teal-50 hover:border-teal-300 transition-colors" data-bill-id="{{ $bill['id'] }}">
                            <input
                                type="checkbox"
                                name="bill_ids[]"
                                value="{{ $bill['id'] }}"
                                class="bulk-bill-checkbox mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                data-pending="{{ $bill['pending'] }}"
                                onchange="updateBulkPaymentSummary()"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="font-mono text-xs font-black text-slate-950">{{ $bill['invoice_number'] }}</p>
                                        <p class="text-[10px] font-semibold text-slate-500">{{ $bill['cart_number'] }} • {{ $bill['date'] }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[10px] font-bold text-slate-600">Bill Amount</p>
                                        <p class="text-xs font-black text-slate-950">₹{{ number_format($bill['amount'], 2) }}</p>
                                    </div>
                                </div>
                                <div class="mt-2 grid grid-cols-2 gap-2 text-[10px] font-bold">
                                    <div>
                                        <span class="text-slate-600">Paid:</span>
                                        <span class="text-emerald-700">₹{{ number_format($bill['paid'], 2) }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-600">Pending:</span>
                                        <span class="text-amber-700">₹{{ number_format($bill['pending'], 2) }}</span>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <label class="text-[9px] font-black uppercase tracking-wider text-slate-400">Discount for this bill (optional)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="{{ $bill['pending'] }}"
                                        name="discount_allocations[{{ $bill['id'] }}]"
                                        class="bulk-bill-discount mt-1 h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:outline-none"
                                        data-bill-id="{{ $bill['id'] }}"
                                        placeholder="0.00"
                                        onchange="updateBulkPaymentSummary()"
                                    />
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </section>

            {{-- Payment Details --}}
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs lg:rounded-2xl">
                <h2 class="text-sm font-black text-slate-950">Payment Details</h2>

                <div class="mt-3 space-y-3">
                    {{-- Payment Method --}}
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Payment Method</label>
                        <select name="payment_method" required class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                            <option value="Cash">Cash</option>
                            <option value="Online">Online</option>
                            <option value="GPay">GPay</option>
                            <option value="Credit">Credit</option>
                        </select>
                    </div>

                    {{-- Amount Paying --}}
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Total Amount Paying</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="amount_paid"
                            id="bulk-payment-amount"
                            required
                            onchange="updateBulkPaymentSummary()"
                            class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none"
                            placeholder="0.00"
                        />
                    </div>

                    {{-- Payment Note --}}
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Notes (optional)</label>
                        <textarea
                            name="payment_note"
                            rows="2"
                            class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none"
                            placeholder="Payment details..."
                        ></textarea>
                    </div>
                </div>
            </section>

            {{-- Summary Card --}}
            <section class="rounded-xl border border-teal-200 bg-gradient-to-br from-teal-50 to-emerald-50 p-4 shadow-xs lg:rounded-2xl">
                <p class="text-[9px] font-black uppercase tracking-wider text-teal-800">Payment Summary</p>
                <div class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between font-bold text-slate-700">
                        <span>Selected Bills</span>
                        <span id="bulk-summary-count">0</span>
                    </div>
                    <div class="flex justify-between font-bold text-slate-700">
                        <span>Total Pending</span>
                        <span id="bulk-summary-pending">₹0.00</span>
                    </div>
                    <div class="flex justify-between font-bold text-teal-700">
                        <span>Total Discount</span>
                        <span id="bulk-summary-discount">₹0.00</span>
                    </div>
                    <div class="flex justify-between font-bold text-emerald-700">
                        <span>Amount Paying</span>
                        <span id="bulk-summary-paying">₹0.00</span>
                    </div>
                    <div class="flex justify-between border-t border-teal-300 pt-2 font-black text-slate-950">
                        <span>Remaining</span>
                        <span id="bulk-summary-remaining" class="text-amber-700">₹0.00</span>
                    </div>
                </div>
                <p id="bulk-summary-warning" class="mt-3 text-[11px] font-semibold text-amber-700"></p>
            </section>

            {{-- Submit Button --}}
            <section class="sticky bottom-0 z-10 flex gap-2">
                <button
                    type="submit"
                    class="flex-1 inline-flex h-11 items-center justify-center rounded-xl bg-gradient-to-r from-teal-600 to-emerald-600 text-sm font-black text-white hover:from-teal-500 hover:to-emerald-500 shadow-md"
                >
                    Process Payment
                </button>
                <a
                    href="{{ route('purchaser.suppliers.show', ['supplier' => $supplier, 'date' => $date]) }}"
                    class="inline-flex h-11 items-center justify-center rounded-xl border-2 border-slate-200 bg-white px-6 text-sm font-black text-slate-700 hover:bg-slate-50"
                >
                    Cancel
                </a>
            </section>
        </form>
    </div>

    <script>
        function updateBulkPaymentSummary() {
            const checkboxes = document.querySelectorAll('.bulk-bill-checkbox:checked');
            const paymentAmount = Number(document.getElementById('bulk-payment-amount')?.value || 0);

            let totalPending = 0;
            let totalDiscount = 0;

            checkboxes.forEach(checkbox => {
                const billId = checkbox.value;
                const pending = Number(checkbox.dataset.pending || 0);
                const discountInput = document.querySelector(`.bulk-bill-discount[data-bill-id="${billId}"]`);
                const discount = Number(discountInput?.value || 0);

                totalPending += pending;
                totalDiscount += discount;
            });

            const netPending = Math.max(0, totalPending - totalDiscount);
            const remaining = Math.max(0, netPending - paymentAmount);

            // Update summary
            document.getElementById('bulk-summary-count').textContent = checkboxes.length;
            document.getElementById('bulk-summary-pending').textContent = `₹${totalPending.toFixed(2)}`;
            document.getElementById('bulk-summary-discount').textContent = `₹${totalDiscount.toFixed(2)}`;
            document.getElementById('bulk-summary-paying').textContent = `₹${paymentAmount.toFixed(2)}`;
            document.getElementById('bulk-summary-remaining').textContent = `₹${remaining.toFixed(2)}`;

            // Update remaining color
            const remainingEl = document.getElementById('bulk-summary-remaining');
            if (remainingEl) {
                remainingEl.className = remaining > 0 ? 'text-amber-700' : 'text-emerald-700';
            }

            // Update warning
            const warningEl = document.getElementById('bulk-summary-warning');
            if (warningEl) {
                if (checkboxes.length === 0) {
                    warningEl.textContent = 'Please select at least one bill to pay.';
                } else if (paymentAmount === 0) {
                    warningEl.textContent = 'Please enter the payment amount.';
                } else if (remaining > 0) {
                    warningEl.textContent = `Partial payment. ₹${remaining.toFixed(2)} will remain pending across selected bills.`;
                } else if (paymentAmount > netPending) {
                    warningEl.textContent = 'Payment amount exceeds total pending. Excess will be ignored.';
                } else {
                    warningEl.innerHTML = '<svg class="inline h-3.5 w-3.5 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Full payment covers all selected bills.';
                    warningEl.className = 'mt-3 text-[11px] font-semibold text-emerald-700';
                }
            }
        }

        // Validate on submit
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('.bulk-bill-checkbox:checked');
            const paymentAmount = Number(document.getElementById('bulk-payment-amount')?.value || 0);

            if (checkboxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one bill to pay.');
                return;
            }

            if (paymentAmount <= 0) {
                e.preventDefault();
                alert('Please enter a valid payment amount.');
                return;
            }
        });

        // Initialize summary on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateBulkPaymentSummary();
        });
    </script>
</x-layouts.app>
