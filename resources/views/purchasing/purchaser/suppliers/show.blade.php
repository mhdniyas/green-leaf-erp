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

        @php
            $summaryCards = collect([
                [
                    'label' => 'Credit',
                    'value' => $supplier->credit_approved ? 'Approved' : 'Not approved',
                    'tone' => $supplier->credit_approved ? 'text-emerald-700' : 'text-amber-700',
                ],
                [
                    'label' => 'Open Issues',
                    'value' => $pendingInvoices->count(),
                    'tone' => 'text-slate-950',
                ],
                [
                    'label' => 'Recent Purchases',
                    'value' => $supplier->purchaserCarts->count(),
                    'tone' => 'text-slate-950',
                ],
                [
                    'label' => 'Credit Terms',
                    'value' => $supplier->credit_terms ?: 'Cash',
                    'tone' => 'text-slate-950',
                ],
                [
                    'label' => 'History Total',
                    'value' => '₹'.number_format((float) $historyTotals['total_amount'], 2),
                    'tone' => 'text-slate-950',
                ],
                [
                    'label' => 'Discount Total',
                    'value' => '₹'.number_format((float) $historyTotals['discount_amount'], 2),
                    'tone' => 'text-slate-950',
                ],
                [
                    'label' => 'Paid Total',
                    'value' => '₹'.number_format((float) $historyTotals['paid_amount'], 2),
                    'tone' => 'text-emerald-700',
                ],
                [
                    'label' => 'Pending Total',
                    'value' => '₹'.number_format((float) $historyTotals['pending_amount'], 2),
                    'tone' => 'text-amber-700',
                ],
                [
                    'label' => 'Items Bought',
                    'value' => $historyTotals['item_count'],
                    'tone' => 'text-slate-950',
                ],
            ]);
            $lastCardStretches = $summaryCards->count() % 3 === 1;
        @endphp

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
            @foreach ($summaryCards as $card)
                <div @class([
                    'rounded-2xl border border-slate-200 bg-white p-4 shadow-sm',
                    'sm:col-span-3 xl:col-span-1' => $lastCardStretches && $loop->last,
                ])>
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-1 text-lg font-black {{ $card['tone'] }}">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-black text-slate-950">Vendor history</h2>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Grouped by business date so you can understand the vendor relationship after a few months. Open any date to review bills, items, receipt notes, and follow-up.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black text-slate-700">{{ $supplier->purchaserCarts->count() }}</span>
            </div>
            <div class="mt-4 space-y-3 lg:hidden">
                @forelse ($vendorHistory as $day)
                    <details class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3" @if($loop->first) open @endif>
                        <summary class="cursor-pointer list-none">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-black text-slate-950">{{ $day['date_label'] }}</h3>
                                    <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ $day['record_count'] }} {{ \Illuminate\Support\Str::plural('bill', $day['record_count']) }} • {{ $day['item_count'] }} items</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[11px] font-black text-slate-950">₹{{ number_format((float) $day['total_amount'], 2) }}</p>
                                    <p class="mt-1 text-[10px] font-bold {{ $day['balance_amount'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                                        {{ $day['balance_amount'] > 0 ? 'Pending' : 'Settled' }}
                                    </p>
                                </div>
                            </div>
                        </summary>

                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-white px-3 py-2">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Paid</p>
                                <p class="mt-1 text-[11px] font-black text-emerald-700">₹{{ number_format((float) $day['paid_amount'], 2) }}</p>
                            </div>
                            <div class="rounded-xl bg-white px-3 py-2">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Pending</p>
                                <p class="mt-1 text-[11px] font-black text-amber-700">₹{{ number_format((float) $day['balance_amount'], 2) }}</p>
                            </div>
                        </div>

                        <div class="mt-3 space-y-2">
                            @foreach ($day['entries'] as $entry)
                                <article class="rounded-2xl border {{ $entry['is_operationally_unresolved'] ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-black text-slate-950">{{ $entry['invoice_number'] ?: 'Bill pending' }}</p>
                                            <p class="mt-1 text-[11px] font-semibold text-slate-600">{{ $entry['cart_number'] }} • {{ $entry['status_label'] }} • {{ $entry['payment_status'] }}</p>
                                            <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ implode(' • ', $entry['item_summary']) }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-black text-slate-950">₹{{ number_format((float) $entry['amount'], 2) }}</p>
                                            @if ($entry['is_payment_pending'] && $entry['payment_modal'] && $entry['payment_route'])
                                                <button
                                                    type="button"
                                                    onclick='openVendorHistoryPaymentModal(@json($entry['payment_modal']), "{{ $entry['payment_route'] }}")'
                                                    class="mt-2 inline-flex h-8 items-center rounded-lg bg-slate-950 px-3 text-[10px] font-black text-white hover:bg-slate-800"
                                                >
                                                    Update Payment
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <p class="rounded-2xl bg-slate-50 px-4 py-8 text-center text-sm font-bold text-slate-500">No vendor history found yet.</p>
                @endforelse

                @if ($vendorHistory->isNotEmpty())
                    <div class="rounded-2xl border border-slate-200 bg-white p-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">History Total</p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Bills</p>
                                <p class="mt-1 text-[11px] font-black text-slate-950">{{ $historyTotals['record_count'] }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Items</p>
                                <p class="mt-1 text-[11px] font-black text-slate-950">{{ $historyTotals['item_count'] }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Total</p>
                                <p class="mt-1 text-[11px] font-black text-slate-950">₹{{ number_format((float) $historyTotals['total_amount'], 2) }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Paid</p>
                                <p class="mt-1 text-[11px] font-black text-emerald-700">₹{{ number_format((float) $historyTotals['paid_amount'], 2) }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2 sm:col-span-2">
                                <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Pending</p>
                                <p class="mt-1 text-[11px] font-black text-amber-700">₹{{ number_format((float) $historyTotals['pending_amount'], 2) }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            @if ($vendorHistory->isNotEmpty())
                <div class="mt-4 hidden overflow-hidden rounded-2xl border border-slate-200 lg:block">
                    <div class="grid grid-cols-[140px_120px_120px_120px_120px_120px] gap-0 bg-slate-950 px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-300">
                        <div>Date</div>
                        <div>Bills</div>
                        <div>Items</div>
                        <div>Total</div>
                        <div>Paid</div>
                        <div>Pending</div>
                    </div>
                    @foreach ($vendorHistory as $day)
                        <details class="border-t border-slate-200 bg-white" @if($loop->first) open @endif>
                            <summary class="grid cursor-pointer list-none grid-cols-[140px_120px_120px_120px_120px_120px] items-center gap-0 px-4 py-3 text-sm">
                                <div class="font-black text-slate-950">{{ $day['date_label'] }}</div>
                                <div class="font-black text-slate-700">{{ $day['record_count'] }}</div>
                                <div class="font-black text-slate-700">{{ $day['item_count'] }}</div>
                                <div class="font-black text-slate-950">₹{{ number_format((float) $day['total_amount'], 2) }}</div>
                                <div class="font-black text-emerald-700">₹{{ number_format((float) $day['paid_amount'], 2) }}</div>
                                <div class="font-black {{ $day['balance_amount'] > 0 ? 'text-amber-700' : 'text-slate-950' }}">₹{{ number_format((float) $day['balance_amount'], 2) }}</div>
                            </summary>

                            <div class="border-t border-slate-100 bg-slate-50 px-4 py-4">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <p class="text-xs font-semibold text-slate-500">Open this date to understand what was bought, what was paid, and what still needs follow-up.</p>
                                    <span class="rounded-full bg-white px-3 py-1 text-[10px] font-black text-slate-700">{{ $day['completed_count'] }} completed • {{ $day['pending_count'] }} pending</span>
                                </div>
                                <div class="space-y-3">
                                    @foreach ($day['entries'] as $entry)
                                        <article class="rounded-2xl border {{ $entry['is_operationally_unresolved'] ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50' }} p-4">
                                            <div class="grid grid-cols-[minmax(0,1.7fr)_140px_120px_120px] items-start gap-4">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <p class="truncate text-sm font-black text-slate-950">{{ $entry['invoice_number'] ?: 'Bill pending' }}</p>
                                                        <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] {{ $entry['status_tone'] }}">
                                                            {{ $entry['status_label'] }}
                                                        </span>
                                                    </div>
                                                    <p class="mt-1 text-[11px] font-semibold text-slate-600">{{ $entry['cart_number'] }} • {{ $entry['payment_status'] }} • {{ $entry['payment_method'] }}</p>
                                                    <div class="mt-2 rounded-xl border border-white/70 bg-white/80 px-3 py-2">
                                                        <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Items Bought</p>
                                                        <p class="mt-1 text-[11px] font-semibold text-slate-700">{{ implode(' • ', $entry['item_summary']) }}</p>
                                                    </div>
                                                    @if (filled($entry['receipt_notes']))
                                                        <div class="mt-2 rounded-xl border border-white/70 bg-white/80 px-3 py-2">
                                                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Receipt Notes</p>
                                                            <p class="mt-1 whitespace-pre-line text-[11px] font-semibold text-slate-700">{{ $entry['receipt_notes'] }}</p>
                                                        </div>
                                                    @endif
                                                    @if (filled($entry['discrepancy_summary']))
                                                        <div class="mt-2 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2">
                                                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-blue-700">Delivery Discrepancy</p>
                                                            <p class="mt-1 whitespace-pre-line text-[11px] font-semibold text-slate-700">{{ $entry['discrepancy_summary'] }}</p>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Amount</p>
                                                    <p class="mt-1 text-sm font-black text-slate-950">₹{{ number_format((float) $entry['amount'], 2) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Paid</p>
                                                    <p class="mt-1 text-sm font-black text-emerald-700">₹{{ number_format((float) $entry['paid_amount'], 2) }}</p>
                                                </div>
                                                <div class="flex flex-col items-start">
                                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Pending</p>
                                                    <p class="mt-1 text-sm font-black {{ $entry['balance_amount'] > 0 ? 'text-amber-700' : 'text-slate-950' }}">₹{{ number_format((float) $entry['balance_amount'], 2) }}</p>
                                                    @if ($entry['is_payment_pending'] && $entry['payment_modal'] && $entry['payment_route'])
                                                        <button
                                                            type="button"
                                                            onclick='openVendorHistoryPaymentModal(@json($entry['payment_modal']), "{{ $entry['payment_route'] }}")'
                                                            class="mt-3 inline-flex h-8 items-center rounded-lg bg-slate-950 px-3 text-[10px] font-black text-white hover:bg-slate-800"
                                                        >
                                                            Update Payment
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        </details>
                    @endforeach
                    <div class="grid grid-cols-[140px_120px_120px_120px_120px_120px] items-center gap-0 border-t border-slate-200 bg-slate-100 px-4 py-3 text-sm">
                        <div class="font-black text-slate-950">Total</div>
                        <div class="font-black text-slate-700">{{ $historyTotals['record_count'] }}</div>
                        <div class="font-black text-slate-700">{{ $historyTotals['item_count'] }}</div>
                        <div class="font-black text-slate-950">₹{{ number_format((float) $historyTotals['total_amount'], 2) }}</div>
                        <div class="font-black text-emerald-700">₹{{ number_format((float) $historyTotals['paid_amount'], 2) }}</div>
                        <div class="font-black {{ $historyTotals['pending_amount'] > 0 ? 'text-amber-700' : 'text-slate-950' }}">₹{{ number_format((float) $historyTotals['pending_amount'], 2) }}</div>
                    </div>
                </div>
            @endif
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
                <input type="hidden" name="payment_paid_by" value="purchaser">

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
                        <span>Already Paid</span>
                        <span id="vendor-history-payment-current-paid" class="text-slate-900"></span>
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
                    <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Add Payment Now</label>
                    <input id="vendor_history_additional_paid_amount" type="number" step="0.01" min="0" name="additional_paid_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                    <p class="mt-1 text-[10px] font-semibold text-slate-500">Enter only the extra amount received now.</p>
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
        let vendorHistoryCurrentPaidAmount = 0;

        function openVendorHistoryPaymentModal(invoice, actionUrl) {
            vendorHistoryPaymentAmount = Number(invoice.amount || 0);
            vendorHistoryCreditApproved = Boolean(invoice.creditApproved);
            vendorHistoryCurrentPaidAmount = Number(invoice.paidAmount || 0);

            document.getElementById('vendor-history-payment-form').action = actionUrl;
            document.getElementById('vendor-history-payment-title').textContent = `${invoice.number} • ${invoice.supplier ?? 'Supplier pending'}`;
            document.getElementById('vendor-history-payment-total').textContent = `₹${Number(invoice.amount || 0).toFixed(2)}`;
            document.getElementById('vendor_history_discount_amount').value = Number(invoice.discountAmount || 0).toFixed(2);
            document.getElementById('vendor_history_payment_method').value = invoice.paymentMethod || 'Cash';
            document.getElementById('vendor-history-payment-current-paid').textContent = `₹${vendorHistoryCurrentPaidAmount.toFixed(2)}`;
            document.getElementById('vendor_history_additional_paid_amount').value = '';
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
            const additionalPaidAmount = Math.max(0, Number(document.getElementById('vendor_history_additional_paid_amount').value || 0));
            const paidAmount = vendorHistoryCurrentPaidAmount + additionalPaidAmount;
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
                : 'Full purchaser payment entered. Purchaser balance will be reduced.';
        }

        document.getElementById('vendor_history_discount_amount')?.addEventListener('input', updateVendorHistoryPaymentStatus);
        document.getElementById('vendor_history_payment_method')?.addEventListener('change', updateVendorHistoryPaymentStatus);
        document.getElementById('vendor_history_additional_paid_amount')?.addEventListener('input', updateVendorHistoryPaymentStatus);
    </script>
</x-layouts.app>
