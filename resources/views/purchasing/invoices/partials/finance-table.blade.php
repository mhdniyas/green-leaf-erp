<div class="space-y-3">
    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm lg:rounded-[2rem]">
        <h2 class="text-sm font-black text-slate-950">Bills generated</h2>
        <p class="mt-1 text-[11px] font-semibold text-slate-500">
            {{ $invoices->total() }}
            {{ $selectedTab === 'old' ? 'older invoices before' : 'invoices for' }}
            {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}
        </p>
    </div>

    @if ($invoices->isEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-12 text-center shadow-sm lg:rounded-[2rem]">
            <p class="text-sm font-bold text-slate-900">{{ $selectedTab === 'old' ? 'No older bills found.' : 'No bills generated for this date.' }}</p>
            <p class="mt-1 text-xs font-semibold text-slate-500">
                {{ $selectedTab === 'old' ? 'Older purchaser invoices will show here for payment follow-up.' : 'Bills appear here after a cart is processed.' }}
            </p>
        </div>
    @else
        <div class="grid gap-3">
            @foreach ($invoices as $invoice)
                @php
                    $balance = max(0, round(((float) $invoice->amount - (float) $invoice->discount_amount) - (float) $invoice->paid_amount, 2));
                    $supplier = $invoice->supplier;
                    $paymentStatusKey = (string) ($invoice->payment_status ?: 'unpaid');
                    $statusLabel = match ($paymentStatusKey) {
                        'partial' => 'Partially Paid',
                        'credit_pending_approval' => 'Credit Pending Approval',
                        default => str($paymentStatusKey)->replace('_', ' ')->title()->toString(),
                    };
                    $paymentHistoryAt = $paymentStatusKey === 'paid'
                        ? ($invoice->purchaserCart?->payment_made_at ?? $invoice->updated_at)
                        : $invoice->updated_at;
                    $paymentHistoryLabel = match ($paymentStatusKey) {
                        'partial' => 'Partially paid on',
                        'paid' => 'Paid on',
                        'credit_pending_approval' => 'Credit updated on',
                        default => 'Payment updated on',
                    };
                    $supplierData = $supplier ? [
                        'id' => $supplier->id,
                        'name' => $supplier->name,
                        'mobile' => $supplier->mobile_number,
                        'creditApproved' => (bool) $supplier->credit_approved,
                        'creditRequestedAt' => optional($supplier->credit_approval_requested_at)?->format('d M Y h:i A'),
                        'creditRequestedBy' => $supplier->creditApprovalRequestedBy?->name,
                        'creditApprovedAt' => optional($supplier->credit_approved_at)?->format('d M Y h:i A'),
                        'creditApprovedBy' => $supplier->creditApprovedBy?->name,
                        'creditNote' => $supplier->credit_approval_note,
                        'creditTerms' => $supplier->credit_terms,
                        'requestUrl' => $canManageSuppliers ? route('purchasing.suppliers.credit-request', $supplier) : null,
                        'approveUrl' => $financeAudience !== 'purchaser' ? route('purchasing.suppliers.credit-approve', $supplier) : null,
                        'canRequest' => $canManageSuppliers && ! $supplier->credit_approval_requested_at && ! $supplier->credit_approved,
                        'canApprove' => $financeAudience !== 'purchaser' && ! $supplier->credit_approved,
                    ] : null;
                    $invoiceData = [
                        'id' => $invoice->id,
                        'number' => $invoice->invoice_number,
                        'billNumber' => $invoice->purchaserCart?->bill_number,
                        'supplier' => $supplier?->name,
                        'amount' => round((float) $invoice->amount, 2),
                        'discountAmount' => round((float) $invoice->discount_amount, 2),
                        'paidAmount' => round((float) $invoice->paid_amount, 2),
                        'balance' => $balance,
                        'paymentMethod' => $invoice->payment_method ?: 'Cash',
                        'paymentNote' => $invoice->payment_note,
                        'paymentDetails' => $invoice->payment_details,
                        'creditApproved' => (bool) ($supplier?->credit_approved),
                    ];
                    $statusTone = $balance > 0 || $invoice->payment_method === 'Credit'
                        ? 'border-amber-200 bg-amber-50 text-amber-700'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-700';
                    $viewRouteName = $financeAudience === 'purchaser' ? 'purchaser.invoices.show' : 'purchasing.invoices.show';
                @endphp

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-mono text-sm font-black text-teal-700">{{ $invoice->invoice_number }}</p>
                                <span class="rounded-full border px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] {{ $statusTone }}">
                                    {{ $statusLabel }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm font-black text-slate-950">{{ $supplier?->name ?: 'Supplier pending' }}</p>
                            <p class="mt-1 text-[11px] font-semibold text-slate-500">
                                {{ $invoice->purchaserCart?->cart_number ?: 'Cart pending' }} • Business day {{ optional($invoice->purchaserCart?->business_date)->format('d M Y') ?? $invoice->created_at?->format('d M Y') }}
                            </p>
                            @if ($selectedTab === 'old' && $paymentHistoryAt)
                                <p class="mt-1 text-[11px] font-semibold text-slate-500">
                                    {{ $paymentHistoryLabel }} {{ $paymentHistoryAt->format('d M Y h:i A') }}
                                </p>
                            @endif
                        </div>
                        <button type="button" onclick='openCreditModal(@json($supplierData))' class="text-[11px] font-black text-teal-700 hover:text-teal-600">
                            Credit
                        </button>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-5">
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Amount</p>
                            <p class="mt-1 text-sm font-black text-slate-950">₹{{ number_format((float) $invoice->amount, 2) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Discount</p>
                            <p class="mt-1 text-sm font-black text-slate-950">₹{{ number_format((float) $invoice->discount_amount, 2) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Paid</p>
                            <p class="mt-1 text-sm font-black text-slate-950">₹{{ number_format((float) $invoice->paid_amount, 2) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Balance</p>
                            <p class="mt-1 text-sm font-black {{ $balance > 0 ? 'text-amber-700' : 'text-emerald-700' }}">₹{{ number_format($balance, 2) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Mobile</p>
                            <p class="mt-1 truncate text-sm font-black text-slate-950">{{ $supplier?->mobile_number ?: 'Pending' }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route($viewRouteName, $invoice) }}" class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 px-3 text-[11px] font-black text-slate-700 hover:bg-slate-50">
                            View
                        </a>
                        <a href="{{ route($billPdfRouteName, $invoice) }}" target="_blank" class="inline-flex h-9 items-center justify-center rounded-xl border border-teal-200 bg-teal-50 px-3 text-[11px] font-black text-teal-700 hover:bg-teal-100">
                            Open Bill
                        </a>
                        <button type="button" onclick='openPaymentModal(@json($invoiceData), "{{ route($paymentUpdateRouteName, $invoice) }}")' class="inline-flex h-9 items-center justify-center rounded-xl bg-slate-950 px-3 text-[11px] font-black text-white hover:bg-slate-800">
                            Update Payment
                        </button>
                        @if ($supplier && $canManageSuppliers && ! $supplier->credit_approved)
                            @if (! $supplier->credit_approval_requested_at)
                                <form action="{{ route('purchasing.suppliers.credit-request', $supplier) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-3 text-[11px] font-black text-amber-700 hover:bg-amber-100">
                                        Request Credit
                                    </button>
                                </form>
                            @endif
                            @if ($financeAudience !== 'purchaser')
                                <form action="{{ route('purchasing.suppliers.credit-approve', $supplier) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-[11px] font-black text-emerald-700 hover:bg-emerald-100">
                                        Approve Credit
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if ($invoices->hasPages())
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm lg:rounded-[2rem]">
                {{ $invoices->withQueryString()->links() }}
            </div>
        @endif
    @endif
</div>

<div id="payment-update-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" onclick="if (event.target === this) closePaymentModal()">
    <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <div>
                <h3 class="text-sm font-black text-slate-950">Payment Update</h3>
                <p id="payment-modal-invoice" class="mt-1 text-[11px] font-semibold text-slate-500"></p>
            </div>
            <button type="button" onclick="closePaymentModal()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form id="payment-update-form" method="POST" class="mt-4 space-y-3">
            @csrf
            @method('PATCH')
            <input type="hidden" name="tab" value="{{ $selectedTab }}">
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                <div class="flex items-center justify-between text-[11px] font-bold text-slate-600">
                    <span>Total Bill</span>
                    <span id="payment-modal-total" class="text-slate-900"></span>
                </div>
                <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                    <span>Discount</span>
                    <span id="payment-modal-discount" class="text-slate-900"></span>
                </div>
                <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                    <span>Net Payable</span>
                    <span id="payment-modal-net" class="text-slate-900"></span>
                </div>
                <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                    <span>Already Paid</span>
                    <span id="payment-modal-current-paid" class="text-slate-900"></span>
                </div>
                <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                    <span>Remaining</span>
                    <span id="payment-modal-balance" class="text-amber-700"></span>
                </div>
                <p id="payment-modal-warning" class="mt-2 text-[10px] font-semibold text-amber-700"></p>

                <div class="mt-3 flex items-center justify-between border-t border-slate-200/60 pt-2">
                    <button type="button" onclick="updatePaymentModalStatus()" class="inline-flex h-8 w-full items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-[11px] font-black text-slate-800 shadow-xs hover:bg-slate-50">
                        <svg class="h-3.5 w-3.5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span>Recheck & Recalculate</span>
                    </button>
                </div>
            </div>

            <div>
                <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Discount</label>
                <input id="discount_amount" type="number" step="0.01" min="0" name="discount_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
            </div>

            <div>
                <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Bill Number</label>
                <input id="bill_number" type="text" name="bill_number" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
            </div>

            <div>
                <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Method</label>
                <select id="payment_method" name="payment_method" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                    <option value="Cash">Cash</option>
                    <option value="Online">Online</option>
                    <option value="GPay">GPay</option>
                    <option value="Credit">Credit</option>
                </select>
            </div>

            <div>
                <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Add Payment Now</label>
                <input id="additional_paid_amount" type="number" step="0.01" min="0" name="additional_paid_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
                <p class="mt-1 text-[10px] font-semibold text-slate-500">Add only the new amount collected now. Total paid updates automatically.</p>
            </div>

            <div>
                <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Note</label>
                <input id="payment_note" type="text" name="payment_note" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
            </div>

            <div>
                <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Payment Details</label>
                <textarea id="payment_details" name="payment_details" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none"></textarea>
            </div>

            <button type="submit" class="h-10 w-full rounded-xl bg-teal-600 text-xs font-black text-white hover:bg-teal-500">Save Payment Update</button>
        </form>
    </div>
</div>

<div id="credit-info-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" onclick="if (event.target === this) closeCreditModal()">
    <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <div>
                <h3 class="text-sm font-black text-slate-950" id="credit-modal-name"></h3>
                <p class="mt-1 text-[11px] font-semibold text-slate-500">Credit approval details</p>
            </div>
            <button type="button" onclick="closeCreditModal()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div class="mt-4 space-y-3 text-[11px] font-semibold text-slate-600">
            <p id="credit-modal-status" class="font-black text-slate-900"></p>
            <p id="credit-modal-mobile"></p>
            <p id="credit-modal-requested"></p>
            <p id="credit-modal-approved"></p>
            <p id="credit-modal-terms"></p>
            <p id="credit-modal-note" class="whitespace-pre-line"></p>
        </div>
        <div id="credit-modal-actions" class="mt-4 flex flex-wrap gap-2"></div>
    </div>
</div>

<script>
    let currentInvoiceAmount = 0;
    let currentCreditApproved = false;
    let currentInvoicePaidAmount = 0;

    function openPaymentModal(invoice, actionUrl) {
        currentInvoiceAmount = Number(invoice.amount || 0);
        currentCreditApproved = Boolean(invoice.creditApproved);
        currentInvoicePaidAmount = Number(invoice.paidAmount || 0);

        document.getElementById('payment-update-form').action = actionUrl;
        document.getElementById('payment-modal-invoice').textContent = `${invoice.number} • ${invoice.supplier ?? 'Supplier pending'}`;
        document.getElementById('payment-modal-total').textContent = `₹${Number(invoice.amount || 0).toFixed(2)}`;
        document.getElementById('discount_amount').value = Number(invoice.discountAmount || 0).toFixed(2);
        document.getElementById('bill_number').value = invoice.billNumber || '';
        document.getElementById('payment_method').value = invoice.paymentMethod || 'Cash';
        document.getElementById('payment-modal-current-paid').textContent = `₹${currentInvoicePaidAmount.toFixed(2)}`;
        document.getElementById('additional_paid_amount').value = '';
        document.getElementById('payment_note').value = invoice.paymentNote || '';
        document.getElementById('payment_details').value = invoice.paymentDetails || '';

        updatePaymentModalStatus();
        document.getElementById('payment-update-modal').classList.remove('hidden');
        document.getElementById('payment-update-modal').classList.add('flex');
    }

    function closePaymentModal() {
        document.getElementById('payment-update-modal').classList.add('hidden');
        document.getElementById('payment-update-modal').classList.remove('flex');
    }

    function updatePaymentModalStatus() {
        const method = document.getElementById('payment_method').value;
        const discountAmount = Math.max(0, Number(document.getElementById('discount_amount').value || 0));
        const additionalPaidAmount = Math.max(0, Number(document.getElementById('additional_paid_amount').value || 0));
        const paidAmount = currentInvoicePaidAmount + additionalPaidAmount;
        const netAmount = Math.max(0, currentInvoiceAmount - discountAmount);
        const balance = Math.max(0, netAmount - paidAmount);
        const balanceNode = document.getElementById('payment-modal-balance');
        const discountNode = document.getElementById('payment-modal-discount');
        const netNode = document.getElementById('payment-modal-net');
        const warningNode = document.getElementById('payment-modal-warning');

        discountNode.textContent = `₹${discountAmount.toFixed(2)}`;
        netNode.textContent = `₹${netAmount.toFixed(2)}`;
        balanceNode.textContent = `₹${balance.toFixed(2)}`;
        balanceNode.className = balance > 0 ? 'text-amber-700' : 'text-emerald-700';

        if (method === 'Credit') {
            warningNode.textContent = currentCreditApproved
                ? 'Credit selected. Payment will stay pending until it is cleared in full.'
                : 'Credit selected but supplier credit is not approved yet.';
            return;
        }

        warningNode.textContent = balance > 0
            ? 'Payment is not done fully. Remaining balance will stay pending.'
            : 'Full payment entered. This purchase will be marked received.';
    }

    document.getElementById('payment_method')?.addEventListener('change', updatePaymentModalStatus);
    document.getElementById('discount_amount')?.addEventListener('input', updatePaymentModalStatus);
    document.getElementById('additional_paid_amount')?.addEventListener('input', updatePaymentModalStatus);

    function openCreditModal(supplier) {
        if (!supplier) {
            return;
        }

        document.getElementById('credit-modal-name').textContent = supplier.name;
        document.getElementById('credit-modal-status').textContent = supplier.creditApproved ? 'Credit Approved' : (supplier.creditRequestedAt ? 'Credit Approval Requested' : 'Credit Not Approved');
        document.getElementById('credit-modal-mobile').textContent = supplier.mobile ? `Mobile: ${supplier.mobile}` : '';
        document.getElementById('credit-modal-requested').textContent = supplier.creditRequestedAt ? `Requested: ${supplier.creditRequestedAt}${supplier.creditRequestedBy ? ` by ${supplier.creditRequestedBy}` : ''}` : '';
        document.getElementById('credit-modal-approved').textContent = supplier.creditApprovedAt ? `Approved: ${supplier.creditApprovedAt}${supplier.creditApprovedBy ? ` by ${supplier.creditApprovedBy}` : ''}` : '';
        document.getElementById('credit-modal-terms').textContent = supplier.creditTerms ? `Credit Terms: ${supplier.creditTerms}` : '';
        document.getElementById('credit-modal-note').textContent = supplier.creditNote ? `Note: ${supplier.creditNote}` : '';
        document.getElementById('credit-modal-actions').innerHTML = buildCreditModalActions(supplier);
        document.getElementById('credit-info-modal').classList.remove('hidden');
        document.getElementById('credit-info-modal').classList.add('flex');
    }

    function closeCreditModal() {
        document.getElementById('credit-info-modal').classList.add('hidden');
        document.getElementById('credit-info-modal').classList.remove('flex');
    }

    function buildCreditModalActions(supplier) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const actions = [];

        if (supplier.canRequest && supplier.requestUrl) {
            actions.push(`
                <form action="${supplier.requestUrl}" method="POST">
                    <input type="hidden" name="_token" value="${csrfToken}">
                    <button type="submit" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[10px] font-black text-amber-700 hover:bg-amber-100">
                        Request Credit
                    </button>
                </form>
            `);
        }

        if (supplier.canApprove && supplier.approveUrl) {
            actions.push(`
                <form action="${supplier.approveUrl}" method="POST">
                    <input type="hidden" name="_token" value="${csrfToken}">
                    <button type="submit" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-[10px] font-black text-emerald-700 hover:bg-emerald-100">
                        Approve Credit
                    </button>
                </form>
            `);
        }

        return actions.join('');
    }
</script>
