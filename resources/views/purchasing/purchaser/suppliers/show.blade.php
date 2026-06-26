<x-layouts.app title="Purchaser Vendor Details">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-5xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Vendor Detail</p>
                    <h1 class="mt-1 text-xl font-black text-slate-950">{{ $supplier->name }}</h1>
                    <p class="mt-1 text-xs font-semibold text-slate-600">{{ $supplier->mobile_number ?: 'Mobile pending' }}{{ $supplier->location ? ' • '.$supplier->location : '' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('purchaser.suppliers', ['date' => $date]) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 px-4 text-xs font-black text-slate-700 hover:bg-slate-50">Back</a>
                    <a href="{{ route('purchaser.finance', ['date' => $date]) }}" class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950 px-4 text-xs font-black text-white hover:bg-slate-800">Finance Desk</a>
                </div>
            </div>
        </section>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Credit</p>
                <p class="mt-1 text-lg font-black {{ $supplier->credit_approved ? 'text-emerald-700' : 'text-amber-700' }}">{{ $supplier->credit_approved ? 'Approved' : 'Not approved' }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Pending Bills</p>
                <p class="mt-1 text-lg font-black text-slate-950">{{ $pendingInvoices->count() }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Recent Purchases</p>
                <p class="mt-1 text-lg font-black text-slate-950">{{ $supplier->purchaserCarts->count() }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Credit Terms</p>
                <p class="mt-1 text-lg font-black text-slate-950">{{ $supplier->credit_terms ?: 'Cash' }}</p>
            </div>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-black text-slate-950">Vendor history</h2>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Grouped by business date. Only pending payment entries can be updated here.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black text-slate-700">{{ $supplier->purchaserCarts->count() }}</span>
            </div>
            <div class="mt-4 space-y-4">
                @forelse ($vendorHistory as $historyDate => $entries)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3 lg:p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-black text-slate-950">{{ $entries->first()['date_label'] }}</h3>
                            <span class="rounded-full bg-white px-3 py-1 text-[10px] font-black text-slate-600">{{ $entries->count() }} {{ \Illuminate\Support\Str::plural('record', $entries->count()) }}</span>
                        </div>

                        <div class="mt-3 space-y-2">
                            @foreach ($entries as $entry)
                                <article class="rounded-2xl border {{ $entry['is_payment_pending'] ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} p-3">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="truncate text-sm font-black text-slate-950">{{ $entry['invoice_number'] ?: 'Bill pending' }}</p>
                                                <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] {{ $entry['is_payment_pending'] ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                                    {{ $entry['is_payment_pending'] ? 'Payment Pending' : 'Completed' }}
                                                </span>
                                            </div>
                                            <p class="mt-1 text-[11px] font-semibold text-slate-600">
                                                {{ $entry['cart_number'] }}
                                                @if ($entry['updated_label'])
                                                    • Updated {{ $entry['updated_label'] }}
                                                @endif
                                            </p>
                                            <p class="mt-1 text-[11px] font-semibold text-slate-500">
                                                {{ $entry['payment_status'] }} • {{ $entry['payment_method'] }}
                                            </p>
                                        </div>

                                        <div class="flex shrink-0 flex-col items-end gap-2">
                                            <p class="text-sm font-black text-slate-950">₹{{ number_format((float) $entry['amount'], 2) }}</p>
                                            @if ($entry['is_payment_pending'] && $entry['payment_modal'] && $entry['payment_route'])
                                                <button
                                                    type="button"
                                                    onclick='openVendorHistoryPaymentModal(@json($entry['payment_modal']), "{{ $entry['payment_route'] }}")'
                                                    class="inline-flex h-8 items-center rounded-lg bg-slate-950 px-3 text-[10px] font-black text-white hover:bg-slate-800"
                                                >
                                                    Update Payment
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    @if (filled($entry['receipt_notes']))
                                        <div class="mt-3 rounded-xl border border-white/70 bg-white/80 px-3 py-2">
                                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Receipt Notes</p>
                                            <p class="mt-1 whitespace-pre-line text-[11px] font-semibold text-slate-700">{{ $entry['receipt_notes'] }}</p>
                                        </div>
                                    @endif

                                    @if (filled($entry['discrepancy_summary']))
                                        <div class="mt-3 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2">
                                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-blue-700">Delivery Discrepancy</p>
                                            <p class="mt-1 whitespace-pre-line text-[11px] font-semibold text-slate-700">{{ $entry['discrepancy_summary'] }}</p>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="rounded-2xl bg-slate-50 px-4 py-8 text-center text-sm font-bold text-slate-500">No vendor history found yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div id="vendor-history-payment-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" onclick="if (event.target === this) closeVendorHistoryPaymentModal()">
        <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <div>
                    <h3 class="text-sm font-black text-slate-950">Payment Update</h3>
                    <p id="vendor-history-payment-title" class="mt-1 text-[11px] font-semibold text-slate-500"></p>
                </div>
                <button type="button" onclick="closeVendorHistoryPaymentModal()" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form id="vendor-history-payment-form" method="POST" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <input type="hidden" name="return_to" value="supplier_detail">
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Total Bill</span>
                        <span id="vendor-history-payment-total" class="text-slate-900"></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Discount</span>
                        <span id="vendor-history-payment-discount" class="text-slate-900"></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Net Payable</span>
                        <span id="vendor-history-payment-net" class="text-slate-900"></span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                        <span>Remaining</span>
                        <span id="vendor-history-payment-balance" class="text-amber-700"></span>
                    </div>
                    <p id="vendor-history-payment-warning" class="mt-2 text-[10px] font-semibold text-amber-700"></p>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Discount</label>
                    <input id="vendor_history_discount_amount" type="number" step="0.01" min="0" name="discount_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Method</label>
                    <select id="vendor_history_payment_method" name="payment_method" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                        <option value="Cash">Cash</option>
                        <option value="Online">Online</option>
                        <option value="GPay">GPay</option>
                        <option value="Credit">Credit</option>
                    </select>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Paid Amount</label>
                    <input id="vendor_history_paid_amount" type="number" step="0.01" min="0" name="paid_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Note</label>
                    <input id="vendor_history_payment_note" type="text" name="payment_note" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Details</label>
                    <textarea id="vendor_history_payment_details" name="payment_details" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none"></textarea>
                </div>

                <button type="submit" class="h-10 w-full rounded-xl bg-teal-600 text-xs font-black text-white hover:bg-teal-500">Save Payment Update</button>
            </form>
        </div>
    </div>

    <script>
        let vendorHistoryPaymentAmount = 0;
        let vendorHistoryCreditApproved = false;

        function openVendorHistoryPaymentModal(invoice, actionUrl) {
            vendorHistoryPaymentAmount = Number(invoice.amount || 0);
            vendorHistoryCreditApproved = Boolean(invoice.creditApproved);

            document.getElementById('vendor-history-payment-form').action = actionUrl;
            document.getElementById('vendor-history-payment-title').textContent = `${invoice.number} • ${invoice.supplier ?? 'Supplier pending'}`;
            document.getElementById('vendor-history-payment-total').textContent = `₹${Number(invoice.amount || 0).toFixed(2)}`;
            document.getElementById('vendor_history_discount_amount').value = Number(invoice.discountAmount || 0).toFixed(2);
            document.getElementById('vendor_history_payment_method').value = invoice.paymentMethod || 'Cash';
            document.getElementById('vendor_history_paid_amount').value = Number(invoice.paidAmount || 0).toFixed(2);
            document.getElementById('vendor_history_payment_note').value = invoice.paymentNote || '';
            document.getElementById('vendor_history_payment_details').value = invoice.paymentDetails || '';

            updateVendorHistoryPaymentStatus();
            document.getElementById('vendor-history-payment-modal').classList.remove('hidden');
            document.getElementById('vendor-history-payment-modal').classList.add('flex');
        }

        function closeVendorHistoryPaymentModal() {
            document.getElementById('vendor-history-payment-modal').classList.add('hidden');
            document.getElementById('vendor-history-payment-modal').classList.remove('flex');
        }

        function updateVendorHistoryPaymentStatus() {
            const method = document.getElementById('vendor_history_payment_method').value;
            const discountAmount = Math.max(0, Number(document.getElementById('vendor_history_discount_amount').value || 0));
            const paidAmount = Number(document.getElementById('vendor_history_paid_amount').value || 0);
            const netAmount = Math.max(0, vendorHistoryPaymentAmount - discountAmount);
            const balance = Math.max(0, netAmount - paidAmount);
            const warningNode = document.getElementById('vendor-history-payment-warning');
            const balanceNode = document.getElementById('vendor-history-payment-balance');

            document.getElementById('vendor-history-payment-discount').textContent = `₹${discountAmount.toFixed(2)}`;
            document.getElementById('vendor-history-payment-net').textContent = `₹${netAmount.toFixed(2)}`;
            balanceNode.textContent = `₹${balance.toFixed(2)}`;
            balanceNode.className = balance > 0 ? 'text-amber-700' : 'text-emerald-700';

            if (method === 'Credit') {
                warningNode.textContent = vendorHistoryCreditApproved
                    ? 'Credit selected. Payment will stay pending until it is cleared in full.'
                    : 'Credit selected but supplier credit is not approved yet.';
                return;
            }

            warningNode.textContent = balance > 0
                ? 'Payment is not done fully. Remaining balance will stay pending.'
                : 'Full payment entered. This purchase will be marked completed.';
        }

        document.getElementById('vendor_history_discount_amount')?.addEventListener('input', updateVendorHistoryPaymentStatus);
        document.getElementById('vendor_history_payment_method')?.addEventListener('change', updateVendorHistoryPaymentStatus);
        document.getElementById('vendor_history_paid_amount')?.addEventListener('input', updateVendorHistoryPaymentStatus);
    </script>
</x-layouts.app>
