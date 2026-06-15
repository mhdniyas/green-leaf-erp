<div class="space-y-3">
    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm lg:rounded-[2rem]">
        <h2 class="text-sm font-black text-slate-950">Bills generated</h2>
        <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ $invoices->total() }} invoices for {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</p>
    </div>

    @if ($invoices->isEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-12 text-center shadow-sm lg:rounded-[2rem]">
            <p class="text-sm font-bold text-slate-900">No bills generated for this date.</p>
            <p class="mt-1 text-xs font-semibold text-slate-500">Bills appear here after a cart is processed.</p>
        </div>
    @else
        <div class="grid gap-3">
            @foreach ($invoices as $invoice)
                @php
                    $balance = max(0, round((float) $invoice->amount - (float) $invoice->paid_amount, 2));
                    $supplier = $invoice->supplier;
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
                        'supplier' => $supplier?->name,
                        'amount' => round((float) $invoice->amount, 2),
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
                                    {{ str($invoice->payment_status ?: 'unpaid')->replace('_', ' ')->title() }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm font-black text-slate-950">{{ $supplier?->name ?: 'Supplier pending' }}</p>
                            <p class="mt-1 text-[11px] font-semibold text-slate-500">
                                {{ $invoice->purchaserCart?->cart_number ?: 'Cart pending' }} • {{ $invoice->created_at->format('d M Y') }}
                            </p>
                        </div>
                        <button type="button" onclick='openCreditModal(@json($supplierData))' class="text-[11px] font-black text-teal-700 hover:text-teal-600">
                            Credit
                        </button>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Amount</p>
                            <p class="mt-1 text-sm font-black text-slate-950">₹{{ number_format((float) $invoice->amount, 2) }}</p>
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
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                <div class="flex items-center justify-between text-[11px] font-bold text-slate-600">
                    <span>Total Bill</span>
                    <span id="payment-modal-total" class="text-slate-900"></span>
                </div>
                <div class="mt-2 flex items-center justify-between text-[11px] font-bold text-slate-600">
                    <span>Remaining</span>
                    <span id="payment-modal-balance" class="text-amber-700"></span>
                </div>
                <p id="payment-modal-warning" class="mt-2 text-[10px] font-semibold text-amber-700"></p>
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
                <label class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">Paid Amount</label>
                <input id="paid_amount" type="number" step="0.01" min="0" name="paid_amount" class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
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

    function openPaymentModal(invoice, actionUrl) {
        currentInvoiceAmount = Number(invoice.amount || 0);
        currentCreditApproved = Boolean(invoice.creditApproved);

        document.getElementById('payment-update-form').action = actionUrl;
        document.getElementById('payment-modal-invoice').textContent = `${invoice.number} • ${invoice.supplier ?? 'Supplier pending'}`;
        document.getElementById('payment-modal-total').textContent = `₹${Number(invoice.amount || 0).toFixed(2)}`;
        document.getElementById('payment_method').value = invoice.paymentMethod || 'Cash';
        document.getElementById('paid_amount').value = Number(invoice.paidAmount || 0).toFixed(2);
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
        const paidAmount = Number(document.getElementById('paid_amount').value || 0);
        const balance = Math.max(0, currentInvoiceAmount - paidAmount);
        const balanceNode = document.getElementById('payment-modal-balance');
        const warningNode = document.getElementById('payment-modal-warning');

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
    document.getElementById('paid_amount')?.addEventListener('input', updatePaymentModalStatus);

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
