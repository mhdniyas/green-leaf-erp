<x-layouts.accounting :title="$shop->name.' Accounting'">
    @php
        $hasEntry = $entry instanceof \App\Models\ShopAccountingEntry;
        $entryAction = $hasEntry
            ? route('admin.accounting.owned-shops.entries.update', ['shop' => $shop, 'entry' => $entry])
            : route('admin.accounting.owned-shops.entries.store', $shop);
        $entryIncomeTotal = $hasEntry ? (float) $entry->lines->where('type', 'income')->sum('amount') : 0.0;
        $entryExpenseTotal = $hasEntry ? (float) $entry->lines->where('type', 'expense')->sum('amount') : 0.0;
    @endphp

    <div class="mx-auto max-w-7xl space-y-5">
        <section class="rounded-3xl border border-slate-200 bg-white px-4 py-4 shadow-[0_12px_34px_rgba(15,23,42,0.05)] sm:px-6 sm:py-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase text-slate-400">Client Accounting</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ $shop->name }}</h1>
                    <p class="mt-2 text-sm font-medium text-slate-500">{{ $shop->code }} • Client: {{ $shop->client?->name ?? 'Aishwarya Veg' }}</p>
                </div>

                <form method="GET" action="{{ route('admin.accounting.owned-shops.show', $shop) }}" class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-2">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <label class="rounded-xl bg-white px-4 py-2 text-slate-900 shadow-sm">
                        <span class="block text-[10px] font-semibold uppercase text-slate-400">Business Date</span>
                        <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" onchange="this.form.submit()" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-semibold focus:outline-none focus:ring-0">
                    </label>
                    <a href="{{ route('admin.accounting.owned-shops.index') }}" class="inline-flex h-11 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-950">
                        All Shops
                    </a>
                </form>
            </div>

        </section>

        @include('admin.accounting.owned_shops.partials.section-tabs')

        @include('admin.accounting.owned_shops.partials.analytics-cards')

        @if ($tab === 'bills')
            <div class="space-y-6">
                <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Delivery Bills</p>
                            <h2 class="mt-2 text-xl font-black text-slate-950">Daily bill table</h2>
                        </div>
                    </div>

                    <div class="mt-6 overflow-x-auto rounded-[1.25rem] border border-slate-200">
                        <table class="min-w-full text-left">
                            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Invoice Number</th>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3 text-right">Bill</th>
                                    <th class="px-4 py-3 text-right">Paid</th>
                                    <th class="px-4 py-3 text-right">Due</th>
                                    <th class="px-4 py-3 text-center">Status</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @forelse($billingInvoices as $billingInvoice)
                                    <tr>
                                        <td class="px-4 py-3 font-black text-slate-950 text-xs sm:text-sm">
                                            {{ $billingInvoice->invoice_number }}
                                        </td>
                                        <td class="px-4 py-3 text-slate-500 font-semibold text-xs sm:text-sm">
                                            {{ $billingInvoice->business_date->format('d M Y') }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-black text-slate-950 text-xs sm:text-sm">
                                            Rs. {{ number_format((float) $billingInvoice->final_total, 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-black text-emerald-700 text-xs sm:text-sm">
                                            Rs. {{ number_format((float) $billingInvoice->paid_amount, 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-black text-rose-700 text-xs sm:text-sm">
                                            Rs. {{ number_format((float) $billingInvoice->balance_amount, 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @if ((float) $billingInvoice->balance_amount <= 0)
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-black text-emerald-700 border border-emerald-200">
                                                    Settled
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-black text-rose-700 border border-rose-200">
                                                    Due
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            @if ((float) $billingInvoice->balance_amount > 0)
                                                <button type="button" 
                                                        class="daily-bill-payment-open inline-flex h-8 items-center rounded-xl bg-cyan-600 px-3 text-xs font-black text-white transition hover:bg-cyan-500" 
                                                        data-invoice-number="{{ $billingInvoice->invoice_number }}"
                                                        data-final-total="{{ (float) $billingInvoice->final_total }}"
                                                        data-paid-amount="{{ (float) $billingInvoice->paid_amount }}"
                                                        data-balance-amount="{{ (float) $billingInvoice->balance_amount }}"
                                                        data-discount-total="{{ (float) $billingInvoice->discount_total }}"
                                                        data-payment-note="{{ $billingInvoice->payment_note }}"
                                                        data-action="{{ route('admin.accounting.owned-shops.daily-bills.payment', ['shop' => $shop, 'invoice' => $billingInvoice]) }}">
                                                    Update Paid
                                                </button>
                                            @else
                                                <span class="text-xs font-bold text-slate-400">N/A</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center font-bold text-slate-500">
                                            No delivery bills found in this timeframe.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($billingInvoices->hasPages())
                        <div class="mt-5">{{ $billingInvoices->withQueryString()->links() }}</div>
                    @endif
                </article>

                <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Payment Requests</p>
                            <h2 class="mt-2 text-xl font-black text-slate-950">Approve requested payments</h2>
                        </div>
                    </div>

                    <div class="mt-6 space-y-3">
                        @forelse($paymentRequests as $paymentRequest)
                            <div class="rounded-[1.25rem] border {{ $paymentRequest->status === 'pending' ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50' }} p-4">
                                <div class="flex flex-col gap-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-black text-slate-950">{{ $paymentRequest->invoice?->invoice_number }}</p>
                                            <p class="mt-1 text-sm font-semibold text-slate-600">Requested Rs. {{ number_format((float) $paymentRequest->requested_amount, 2) }}</p>
                                            @if ($paymentRequest->shop_note)
                                                <p class="mt-2 text-sm font-semibold text-slate-700">{{ $paymentRequest->shop_note }}</p>
                                            @endif
                                        </div>
                                        <span class="rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $paymentRequest->statusTone() === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($paymentRequest->statusTone() === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">
                                            {{ $paymentRequest->statusLabel() }}
                                        </span>
                                    </div>

                                    @if ($paymentRequest->status === 'pending')
                                        <form method="POST" action="{{ route('admin.accounting.owned-shops.payment-requests.review', ['shop' => $shop, 'paymentRequest' => $paymentRequest]) }}" class="rounded-[1.1rem] border border-white/80 bg-white/80 px-4 py-3">
                                            @csrf
                                            @method('PATCH')
                                            <label class="block">
                                                <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Admin Note</span>
                                                <textarea name="admin_note" rows="2" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none" placeholder="Optional note for shop owner">{{ old('admin_note') }}</textarea>
                                            </label>
                                            <div class="mt-3 flex flex-wrap justify-end gap-2">
                                                <button type="submit" name="decision" value="reject" class="inline-flex h-10 items-center rounded-2xl bg-red-600 px-4 text-xs font-black uppercase tracking-[0.14em] text-white transition hover:bg-red-500">
                                                    Reject
                                                </button>
                                                <button type="submit" name="decision" value="approve" class="inline-flex h-10 items-center rounded-2xl bg-emerald-600 px-4 text-xs font-black uppercase tracking-[0.14em] text-white transition hover:bg-emerald-500">
                                                    Approve Paid
                                                </button>
                                            </div>
                                        </form>
                                    @elseif ($paymentRequest->admin_note)
                                        <p class="text-sm font-semibold text-slate-700">Admin note: {{ $paymentRequest->admin_note }}</p>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="rounded-[1.25rem] border border-dashed border-slate-300 px-4 py-8 text-center text-sm font-bold text-slate-500">
                                No payment requests found.
                            </div>
                        @endforelse
                    </div>

                    @if ($paymentRequests->hasPages())
                        <div class="mt-5">{{ $paymentRequests->withQueryString()->links() }}</div>
                    @endif
                </article>

                <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Daily Summary</p>
                            <h2 class="mt-2 text-xl font-black text-slate-950">Cash flow by day</h2>
                        </div>
                    </div>

                    <div class="mt-6 overflow-x-auto rounded-[1.25rem] border border-slate-200">
                        <table class="min-w-full text-left">
                            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3 text-right">Billed</th>
                                    <th class="px-4 py-3 text-right">Collected</th>
                                    <th class="px-4 py-3 text-right">Credit</th>
                                    <th class="px-4 py-3 text-right">Balance</th>
                                    <th class="px-4 py-3 text-right">Income</th>
                                    <th class="px-4 py-3 text-right">Expense</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @forelse($analytics['daily_summaries'] as $summary)
                                    <tr>
                                        <td class="px-4 py-3 font-black text-slate-950">{{ \Illuminate\Support\Carbon::parse($summary['date'])->format('d M Y') }}</td>
                                        <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $summary['billed'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black text-emerald-700">Rs. {{ number_format((float) $summary['paid'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black text-emerald-700">Rs. {{ number_format((float) $summary['credit'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black text-rose-700">Rs. {{ number_format((float) $summary['balance'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $summary['income'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $summary['expense'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center font-bold text-slate-500">No daily summary rows found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Daily Bill Payment Collection Modal -->
            <div id="daily-bill-payment-modal" class="hidden fixed inset-0 z-[70]">
                <div class="daily-bill-payment-modal-overlay absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>
                <div class="relative flex min-h-full items-center justify-center p-4">
                    <div class="w-full max-w-lg rounded-[2rem] border border-slate-200 bg-white shadow-[0_30px_80px_rgba(15,23,42,0.22)]">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Daily Bill Payment</p>
                                <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Update Collected Money</h2>
                            </div>
                            <button type="button" class="daily-bill-payment-modal-close inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <form id="daily-bill-payment-form" method="POST" action="" class="space-y-4 px-6 py-6">
                            @csrf
                            @method('PATCH')
                            
                            <div>
                                <p class="text-sm font-bold text-slate-700">Invoice Number: <span id="payment-modal-invoice-number" class="font-black text-slate-950"></span></p>
                            </div>

                            <div class="grid grid-cols-3 gap-3 rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Bill Total</p>
                                    <p id="payment-modal-final-total" class="mt-1 text-sm font-black text-slate-950">Rs. 0.00</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Current Paid</p>
                                    <p id="payment-modal-current-paid" class="mt-1 text-sm font-black text-emerald-700">Rs. 0.00</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Remaining Due</p>
                                    <p id="payment-modal-remaining-due" class="mt-1 text-sm font-black text-rose-700">Rs. 0.00</p>
                                </div>
                            </div>

                            <input type="hidden" name="discount_total" id="payment-modal-discount-total" value="0.00">

                            <div>
                                <label class="block">
                                    <span class="mb-2 block text-xs font-black uppercase tracking-[0.16em] text-slate-500">Total Paid Amount (Rs.)</span>
                                    <input type="number" step="0.01" min="0" name="paid_amount" id="payment-modal-paid-amount-input" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900 focus:border-cyan-400 focus:outline-none">
                                </label>
                                <p class="mt-1.5 text-xs text-slate-500">Enter the cumulative collected amount. The newly received balance will be recorded as an approved paid request for the shop owner.</p>
                                <button type="button" id="payment-modal-set-full-btn" class="mt-2 inline-flex h-8 items-center rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-black text-slate-700 transition hover:bg-slate-100">
                                    Set to Full
                                </button>
                            </div>

                            <div>
                                <label class="block">
                                    <span class="mb-2 block text-xs font-black uppercase tracking-[0.16em] text-slate-500">Payment Note</span>
                                    <textarea name="payment_note" id="payment-modal-payment-note-input" rows="3" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none" placeholder="e.g. Balance collected in cash."></textarea>
                                </label>
                            </div>

                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" class="daily-bill-payment-modal-close inline-flex h-11 items-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                                    Cancel
                                </button>
                                <button type="submit" class="inline-flex h-11 items-center rounded-2xl bg-cyan-600 px-5 text-sm font-black text-white transition hover:bg-cyan-500">
                                    Confirm collected money
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <script>
                (() => {
                    const dailyBillPaymentModal = document.getElementById('daily-bill-payment-modal');
                    const dailyBillPaymentForm = document.getElementById('daily-bill-payment-form');
                    const dailyBillPaymentButtons = document.querySelectorAll('.daily-bill-payment-open');
                    const modalInvoiceNumber = document.getElementById('payment-modal-invoice-number');
                    const modalFinalTotal = document.getElementById('payment-modal-final-total');
                    const modalCurrentPaid = document.getElementById('payment-modal-current-paid');
                    const modalRemainingDue = document.getElementById('payment-modal-remaining-due');
                    const modalDiscountTotal = document.getElementById('payment-modal-discount-total');
                    const modalPaidAmountInput = document.getElementById('payment-modal-paid-amount-input');
                    const modalPaymentNoteInput = document.getElementById('payment-modal-payment-note-input');
                    const modalSetFullBtn = document.getElementById('payment-modal-set-full-btn');

                    let currentInvoiceFinalTotal = 0;

                    const money = (amount) => 'Rs. ' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    const closeModal = () => {
                        dailyBillPaymentModal?.classList.add('hidden');
                        document.body.classList.remove('overflow-hidden');
                    };

                    dailyBillPaymentButtons.forEach((button) => {
                        button.addEventListener('click', () => {
                            if (!dailyBillPaymentModal || !dailyBillPaymentForm) {
                                return;
                            }

                            const finalTotal = parseFloat(button.dataset.finalTotal ?? '0');
                            const paidAmount = parseFloat(button.dataset.paidAmount ?? '0');
                            const balanceAmount = parseFloat(button.dataset.balanceAmount ?? '0');
                            const discountTotal = parseFloat(button.dataset.discountTotal ?? '0');

                            currentInvoiceFinalTotal = finalTotal;
                            dailyBillPaymentForm.action = button.dataset.action ?? '';
                            if (modalInvoiceNumber) modalInvoiceNumber.textContent = button.dataset.invoiceNumber ?? '';
                            if (modalFinalTotal) modalFinalTotal.textContent = money(finalTotal);
                            if (modalCurrentPaid) modalCurrentPaid.textContent = money(paidAmount);
                            if (modalRemainingDue) modalRemainingDue.textContent = money(balanceAmount);
                            if (modalDiscountTotal) modalDiscountTotal.value = discountTotal.toFixed(2);
                            if (modalPaidAmountInput) modalPaidAmountInput.value = finalTotal.toFixed(2);
                            if (modalPaymentNoteInput) modalPaymentNoteInput.value = button.dataset.paymentNote ?? '';

                            dailyBillPaymentModal.classList.remove('hidden');
                            document.body.classList.add('overflow-hidden');
                        });
                    });

                    modalSetFullBtn?.addEventListener('click', () => {
                        if (modalPaidAmountInput) {
                            modalPaidAmountInput.value = currentInvoiceFinalTotal.toFixed(2);
                        }
                    });
                    dailyBillPaymentModal?.querySelectorAll('.daily-bill-payment-modal-close').forEach((button) => button.addEventListener('click', closeModal));
                    dailyBillPaymentModal?.addEventListener('click', (event) => {
                        if (event.target instanceof HTMLElement && event.target.classList.contains('daily-bill-payment-modal-overlay')) {
                            closeModal();
                        }
                    });
                })();
            </script>
        @endif

        @if ($tab === 'cashbook')
            @php
                $approvalTabs = [
                    'pending' => ['label' => 'Pending Approval', 'empty' => 'No new entries waiting for approval.'],
                    'approved' => ['label' => 'Approved', 'empty' => 'No approved entries in this period.'],
                    'recheck' => ['label' => 'Recheck Required', 'empty' => 'No entries waiting for shop correction.'],
                ];
            @endphp

            <section id="owned-shop-approval-workflow" class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase text-slate-400">Admin Approval</p>
                        <h2 class="mt-1 text-xl font-semibold text-slate-950">Daily Shop Receipt workflow</h2>
                    </div>
                    <div class="flex flex-wrap gap-2 rounded-2xl bg-slate-100 p-1">
                        @foreach ($approvalTabs as $approvalKey => $approvalMeta)
                            <button type="button" data-approval-tab-trigger="{{ $approvalKey }}" aria-selected="{{ $approvalTab === $approvalKey ? 'true' : 'false' }}" class="{{ $approvalTab === $approvalKey ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-900' }} inline-flex h-10 items-center rounded-xl px-4 text-sm font-semibold transition">
                                {{ $approvalMeta['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach ($approvalTabs as $approvalKey => $approvalMeta)
                        @php
                            $workflowEntries = $approvalEntriesByTab->get($approvalKey, collect());
                            $selectedApprovalEntryIsVisible = $hasEntry && match ($approvalKey) {
                                'approved' => $entry->status === 'approved',
                                'recheck' => $entry->status === 'recheck_required',
                                default => $entry->status === 'submitted',
                            };
                            $visibleWorkflowEntries = $selectedApprovalEntryIsVisible
                                ? $workflowEntries->reject(fn ($workflowEntry) => $workflowEntry->is($entry))->values()
                                : $workflowEntries;
                        @endphp
                        <div data-approval-tab-panel="{{ $approvalKey }}" @class(['space-y-3', 'hidden' => $approvalTab !== $approvalKey])>
                            @if ($selectedApprovalEntryIsVisible)
                                @include('admin.accounting.owned_shops.partials.admin-approval')
                            @endif

                            @forelse ($visibleWorkflowEntries as $workflowEntry)
                                @php
                                    $workflowIncome = round((float) $workflowEntry->lines->where('type', 'income')->sum('amount'), 2);
                                    $workflowExpense = round((float) $workflowEntry->lines->where('type', 'expense')->sum('amount'), 2);
                                    $workflowEntryUrl = route('admin.accounting.owned-shops.show', [
                                        'shop' => $shop,
                                        'tab' => 'cashbook',
                                        'approval_tab' => $approvalKey,
                                        'date' => $workflowEntry->business_date->format('Y-m-d'),
                                        'start_date' => $startDate->format('Y-m-d'),
                                        'end_date' => $endDate->format('Y-m-d'),
                                    ]);
                                @endphp
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-emerald-200 hover:bg-emerald-50">
                                    <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="text-sm font-semibold text-slate-950">{{ $workflowEntry->business_date->format('d M Y') }}</p>
                                                <span class="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase {{ $workflowEntry->statusTone() === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($workflowEntry->statusTone() === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">{{ $workflowEntry->statusLabel() }}</span>
                                            </div>
                                            <p class="mt-2 text-sm font-medium text-slate-600">
                                                Income Rs. {{ number_format($workflowIncome, 2) }} / Expense Rs. {{ number_format($workflowExpense, 2) }}
                                            </p>
                                            @if ($workflowEntry->admin_note)
                                                <p class="mt-2 text-sm font-medium text-slate-500">{{ $workflowEntry->admin_note }}</p>
                                            @endif
                                        </div>
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center xl:justify-end">
                                            <p class="text-sm font-semibold text-slate-950">Rs. {{ number_format($workflowIncome - $workflowExpense, 2) }}</p>
                                            <div class="flex flex-wrap gap-2">
                                                <a href="{{ $workflowEntryUrl }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black uppercase tracking-[0.12em] text-slate-700 transition hover:border-slate-300 hover:text-slate-950">
                                                    View
                                                </a>
                                                @if ($workflowEntry->status === 'submitted')
                                                    <form method="POST" action="{{ route('admin.accounting.owned-shops.entries.review', ['shop' => $shop, 'entry' => $workflowEntry]) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="decision" value="approve">
                                                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-emerald-600 px-4 text-xs font-black uppercase tracking-[0.12em] text-white transition hover:bg-emerald-500">
                                                            Approve
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                @unless ($selectedApprovalEntryIsVisible)
                                    <div class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm font-semibold text-slate-500">
                                        {{ $approvalMeta['empty'] }}
                                    </div>
                                @endunless
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </section>

            <script>
                (() => {
                    const workflow = document.getElementById('owned-shop-approval-workflow');

                    if (!workflow) {
                        return;
                    }

                    const buttons = workflow.querySelectorAll('[data-approval-tab-trigger]');
                    const panels = workflow.querySelectorAll('[data-approval-tab-panel]');

                    const setTab = (tab) => {
                        buttons.forEach((button) => {
                            const isActive = button.dataset.approvalTabTrigger === tab;
                            button.classList.toggle('bg-white', isActive);
                            button.classList.toggle('text-slate-950', isActive);
                            button.classList.toggle('shadow-sm', isActive);
                            button.classList.toggle('text-slate-500', !isActive);
                            button.classList.toggle('hover:text-slate-900', !isActive);
                            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        });

                        panels.forEach((panel) => {
                            panel.classList.toggle('hidden', panel.dataset.approvalTabPanel !== tab);
                        });

                        const url = new URL(window.location.href);
                        url.searchParams.set('tab', 'cashbook');
                        url.searchParams.set('approval_tab', tab);
                        window.history.replaceState({}, '', url);
                    };

                    buttons.forEach((button) => {
                        button.addEventListener('click', () => setTab(button.dataset.approvalTabTrigger ?? 'pending'));
                    });
                })();
            </script>

        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Opening</p>
                        <p class="mt-2 whitespace-nowrap text-base font-black leading-none tracking-tight text-slate-950 tabular-nums xl:text-lg">Rs. {{ number_format($receiptSummary['opening_balance'], 2) }}</p>
                    </div>
                    <div class="rounded-[1rem] border border-emerald-200 bg-emerald-50 p-3">
                        <p class="text-[9px] font-black uppercase tracking-[0.14em] text-emerald-700">Cash Credit</p>
                        <p class="mt-2 whitespace-nowrap text-base font-black leading-none tracking-tight text-emerald-800 tabular-nums xl:text-lg">Rs. {{ number_format($receiptSummary['cash_credit'], 2) }}</p>
                    </div>
                    <div class="rounded-[1rem] border border-cyan-200 bg-cyan-50 p-3">
                        <p class="text-[9px] font-black uppercase tracking-[0.14em] text-cyan-700">Non-Cash Income</p>
                        <p class="mt-2 whitespace-nowrap text-base font-black leading-none tracking-tight text-cyan-800 tabular-nums xl:text-lg">Rs. {{ number_format($receiptSummary['non_cash_income'], 2) }}</p>
                    </div>
                    <div class="rounded-[1rem] border border-rose-200 bg-rose-50 p-3">
                        <p class="text-[9px] font-black uppercase tracking-[0.14em] text-rose-700">Cash Debit</p>
                        <p class="mt-2 whitespace-nowrap text-base font-black leading-none tracking-tight text-rose-800 tabular-nums xl:text-lg">Rs. {{ number_format($receiptSummary['cash_debit'], 2) }}</p>
                    </div>
                    <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Expected Closing</p>
                        <p class="mt-2 whitespace-nowrap text-base font-black leading-none tracking-tight text-slate-950 tabular-nums xl:text-lg">Rs. {{ number_format($receiptSummary['expected_closing'], 2) }}</p>
                    </div>
                    <div class="rounded-[1rem] border {{ ($receiptSummary['entered_closing'] ?? 0) < 0 ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50' }} p-3">
                        <p class="text-[9px] font-black uppercase tracking-[0.14em] {{ ($receiptSummary['entered_closing'] ?? 0) < 0 ? 'text-rose-700' : 'text-emerald-700' }}">Closing</p>
                        <p class="mt-2 whitespace-nowrap text-base font-black leading-none tracking-tight tabular-nums {{ ($receiptSummary['entered_closing'] ?? 0) < 0 ? 'text-rose-800' : 'text-emerald-800' }} xl:text-lg">
                            {{ $receiptSummary['entered_closing'] === null ? 'None' : 'Rs. '.number_format($receiptSummary['entered_closing'], 2) }}
                        </p>
                    </div>
                    <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-3">
                        <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500">Approval Status</p>
                        <div class="mt-2">
                            @if ($hasEntry)
                                <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $entry->statusTone() === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($entry->statusTone() === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : ($entry->statusTone() === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-slate-200 bg-slate-100 text-slate-700')) }}">
                                    {{ $entry->statusLabel() }}
                                </span>
                            @else
                                <p class="text-2xl font-black text-slate-950">None</p>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($receiptSummary['owner_funded'] > 0 || ($receiptSummary['difference'] !== null && abs((float) $receiptSummary['difference']) > 0.009))
                    <div class="mt-4 grid gap-3 lg:grid-cols-2">
                        @if ($receiptSummary['owner_funded'] > 0)
                            <div class="rounded-[1.25rem] border border-rose-200 bg-rose-50 px-4 py-4">
                                <p class="text-sm font-black text-rose-900">Owner Funded / Payable: Rs. {{ number_format($receiptSummary['owner_funded'], 2) }}</p>
                            </div>
                        @endif
                        @if ($receiptSummary['difference'] !== null && abs((float) $receiptSummary['difference']) > 0.009)
                            <div class="rounded-[1.25rem] border border-amber-200 bg-amber-50 px-4 py-4">
                                <p class="text-sm font-black text-amber-900">Closing Difference: Rs. {{ number_format((float) $receiptSummary['difference'], 2) }}</p>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <button type="button" id="daily-entry-open-modal" class="inline-flex h-11 items-center rounded-2xl bg-cyan-600 px-5 text-sm font-black text-white transition hover:bg-cyan-500">
                        {{ $hasEntry ? 'Update Daily Entry' : 'Add Daily Entry' }}
                    </button>
                </div>

                <section id="owned-shop-cash-movements" class="mt-6 grid gap-6">
                    <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Green Leaf Loan Ledger</p>
                                <h3 class="mt-2 text-lg font-black text-slate-950">Latest client-shop loan movements</h3>
                            </div>
                            @php
                                $shopCashTotal = (float) $shopCredits->sum(fn ($credit) => $credit->signedAccountingAmount());
                            @endphp
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <p class="text-sm font-black {{ $shopCashTotal >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Rs. {{ number_format($shopCashTotal, 2) }}</p>
                                <button type="button" id="shop-cash-open-modal" class="inline-flex h-10 items-center rounded-xl bg-emerald-600 px-4 text-xs font-semibold text-white transition hover:bg-emerald-500">
                                    Add loan
                                </button>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Loan Category Report</p>
                                        <p class="mt-1 text-sm font-black text-slate-950">Green Leaf loans by category</p>
                                    </div>
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-600">{{ $cashMovementCategoryTotals->count() }} active</span>
                                </div>
                                <div class="mt-3 space-y-2">
                                    @forelse($cashMovementCategoryTotals as $categoryTotal)
                                        <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 px-3 py-2">
                                            <div>
                                                <p class="text-sm font-black text-slate-950">{{ $categoryTotal['category'] }}</p>
                                                <p class="text-[11px] font-bold text-slate-500">{{ $categoryTotal['count'] }} movement{{ $categoryTotal['count'] === 1 ? '' : 's' }}</p>
                                            </div>
                                            <p class="text-sm font-black {{ $categoryTotal['amount'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Rs. {{ number_format((float) $categoryTotal['amount'], 2) }}</p>
                                        </div>
                                    @empty
                                        <p class="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-sm font-bold text-slate-500">No category movements for this period.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 overflow-x-auto rounded-[1rem] border border-slate-200 bg-white">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                    <tr>
                                        <th class="px-4 py-3">Date</th>
                                        <th class="px-4 py-3">Type</th>
                                        <th class="px-4 py-3">Category</th>
                                        <th class="px-4 py-3">Description</th>
                                        <th class="px-4 py-3 text-right">Ledger Amount</th>
                                        <th class="px-4 py-3 text-right">By</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($shopCredits as $credit)
                                        <tr>
                                            <td class="px-4 py-3 font-black text-slate-950">{{ $credit->business_date->format('d M Y') }}</td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $credit->isAccountingOut() ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                                                    {{ $credit->accountingLabel() }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 font-black text-slate-950">{{ $credit->cashMovementCategory?->name ?? 'Uncategorized' }}</td>
                                            <td class="px-4 py-3 font-semibold text-slate-600">{{ $credit->description }}</td>
                                            <td class="px-4 py-3 text-right font-black {{ $credit->isAccountingOut() ? 'text-rose-700' : 'text-emerald-700' }}">{{ $credit->isAccountingOut() ? '-' : '+' }} Rs. {{ number_format((float) $credit->amount, 2) }}</td>
                                            <td class="px-4 py-3 text-right font-semibold text-slate-500">{{ $credit->creator?->name ?? 'System' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-8 text-center font-bold text-slate-500">No Green Leaf loan movements added yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                @if ($hasEntry)
                    <div class="mt-6 space-y-4">
                        <div class="rounded-[1.25rem] border {{ $entry->status === 'recheck_required' ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-slate-50' }} p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] {{ $entry->status === 'recheck_required' ? 'text-red-700' : 'text-slate-500' }}">Review Timeline</p>
                            <div class="mt-3 grid gap-3 text-sm font-semibold text-slate-700 md:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-[0.95rem] border border-white/70 bg-white/80 px-3 py-3">
                                    <p><span class="font-black text-slate-950">Submitted by:</span> {{ $entry->submittedBy?->name ?? 'Admin entry' }}</p>
                                </div>
                                <div class="rounded-[0.95rem] border border-white/70 bg-white/80 px-3 py-3">
                                    <p><span class="font-black text-slate-950">Last updated:</span> {{ $entry->updated_at?->format('d M Y h:i A') }}</p>
                                </div>
                                @if ($entry->submitted_at)
                                    <div class="rounded-[0.95rem] border border-white/70 bg-white/80 px-3 py-3">
                                        <p><span class="font-black text-slate-950">Submitted at:</span> {{ $entry->submitted_at->format('d M Y h:i A') }}</p>
                                    </div>
                                @endif
                                @if ($entry->admin_note)
                                    <div class="rounded-[0.95rem] border border-white/70 bg-white/80 px-3 py-3 md:col-span-2 xl:col-span-1">
                                        <p><span class="font-black text-slate-950">Admin note:</span> {{ $entry->admin_note }}</p>
                                    </div>
                                @endif
                                @if ($entry->shop_reply_note)
                                    <div class="rounded-[0.95rem] border border-white/70 bg-white/80 px-3 py-3 md:col-span-2 xl:col-span-2">
                                        <p><span class="font-black text-slate-950">Shop reply:</span> {{ $entry->shop_reply_note }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                    </div>

                    <div class="mt-4 rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Submitted Items</p>
                                <h3 class="mt-1 text-lg font-black text-slate-950">Exact update from shop owner</h3>
                            </div>
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">{{ $entry->lines->count() }} manual item{{ $entry->lines->count() === 1 ? '' : 's' }}</p>
                        </div>

                        <div class="mt-4 overflow-x-auto rounded-[1.15rem] border border-slate-200 bg-white">
                            <table class="min-w-full text-left">
                                <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                    <tr>
                                        <th class="px-4 py-3">Type</th>
                                        <th class="px-4 py-3">Category</th>
                                        <th class="px-4 py-3">Notes</th>
                                        <th class="px-4 py-3">Review</th>
                                        <th class="px-4 py-3 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-sm">
                                    @foreach ($entry->lines as $line)
                                        <tr>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $line->type === 'income' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                    {{ $line->type }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 font-black text-slate-950">{{ $line->category?->name ?? 'Category removed' }}</td>
                                            <td class="px-4 py-3 text-sm font-semibold text-slate-600">{{ $line->description ?: 'No note added' }}</td>
                                            <td class="px-4 py-3">
                                                <div class="flex flex-col gap-2">
                                                    <span class="inline-flex w-fit rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $line->reviewStatusTone() === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($line->reviewStatusTone() === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">
                                                        {{ $line->reviewStatusLabel() }}
                                                    </span>
                                                    @if ($line->review_note)
                                                        <p class="text-xs font-semibold text-slate-500">{{ $line->review_note }}</p>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $line->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="bg-slate-50/80">
                                        <td colspan="4" class="px-4 py-3 font-black text-rose-900">Warehouse Delivery Invoice</td>
                                        <td class="px-4 py-3 text-right font-black text-rose-700">Shown in shop ledger totals from bill data</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-6">
                    <a href="{{ route('admin.accounting.owned-shops.categories.index', $shop) }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">
                        Open Categories Page
                    </a>
                </div>
        </section>

        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Closed Periods</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">Accounting month locks</h2>
                </div>
            </div>

            <div class="mt-6 space-y-3">
                @forelse($periodClosures as $closure)
                    <div class="flex items-center justify-between gap-3 rounded-[1.25rem] border border-slate-200 bg-slate-50 px-4 py-4">
                        <div>
                            <p class="text-sm font-black text-slate-950">{{ $closure->period_start->format('d M Y') }} to {{ $closure->period_end->format('d M Y') }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">Closed {{ $closure->closed_at->format('d M Y h:i A') }} by {{ $closure->closedBy?->name ?? 'Unknown' }}</p>
                            @if ($closure->notes)
                                <p class="mt-2 text-xs font-semibold text-slate-600">{{ $closure->notes }}</p>
                            @endif
                        </div>
                        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700">Closed</span>
                    </div>
                @empty
                    <div class="rounded-[1.25rem] border border-dashed border-slate-300 px-4 py-8 text-center text-sm font-bold text-slate-500">
                        No accounting periods have been closed yet.
                    </div>
                @endforelse
            </div>

            <form method="POST" action="{{ route('admin.accounting.owned-shops.period-closures.store', $shop) }}" class="mt-6 grid gap-3 border-t border-slate-100 pt-6 md:grid-cols-4">
                @csrf
                <input type="date" name="period_start" value="{{ $selectedDate->copy()->startOfMonth()->format('Y-m-d') }}" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                <input type="date" name="period_end" value="{{ $selectedDate->copy()->endOfMonth()->format('Y-m-d') }}" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                <input type="text" name="notes" placeholder="Close note" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                <button type="submit" class="inline-flex h-12 items-center justify-center rounded-2xl bg-emerald-600 px-4 text-sm font-black text-white transition hover:bg-emerald-500">
                    Close Month
                </button>
            </form>
        </section>

        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Recent Daily Entries</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">Latest accounting activity</h2>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto rounded-[1.25rem] border border-slate-200">
                <table class="min-w-full text-left">
                    <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Income</th>
                            <th class="px-4 py-3 text-right">Expense</th>
                            <th class="px-4 py-3 text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @forelse($recentEntries as $recentEntry)
                            @php
                                $recentIncome = (float) $recentEntry->lines->where('type', 'income')->sum('amount');
                                $recentExpense = (float) $recentEntry->lines->where('type', 'expense')->sum('amount');
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-black text-slate-950">{{ $recentEntry->business_date->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $recentEntry->statusTone() === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($recentEntry->statusTone() === 'danger' ? 'border-red-200 bg-red-50 text-red-700' : ($recentEntry->statusTone() === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-slate-200 bg-slate-100 text-slate-700')) }}">
                                        {{ $recentEntry->statusLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($recentIncome, 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($recentExpense, 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($recentIncome - $recentExpense, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center font-bold text-slate-500">No daily accounting entries recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div id="shop-cash-modal" class="hidden fixed inset-0 z-[70]">
            <div class="shop-cash-modal-overlay absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>
            <div class="relative flex min-h-full items-center justify-center p-4">
                <div class="w-full max-w-lg rounded-[2rem] border border-slate-200 bg-white shadow-[0_30px_80px_rgba(15,23,42,0.22)]">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                        <div>
	                            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-emerald-700">Green Leaf Loan</p>
	                            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Add loan</h2>
	                            <p class="mt-2 text-sm font-semibold text-slate-500">This records Green Leaf cash out and increases the client shop loan balance. Sales income stays in approved daily receipts.</p>
	                        </div>
                        <button type="button" class="shop-cash-modal-close inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4 px-6 py-6">
                        <form id="shop-cash-credit-form" method="POST" action="{{ route('admin.accounting.owned-shops.credits.store', $shop) }}">
                            @csrf
                            <input type="hidden" name="type" value="in">
                        </form>
	                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
	                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Movement Type</p>
	                            <p class="mt-1 text-sm font-black text-slate-950">Loan given to client shop</p>
	                            <p class="mt-1 text-xs font-semibold text-slate-600">Green Leaf view: cash out. Client dashboard: loan balance increases.</p>
	                        </div>
                        <label>
                            <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Amount</span>
                            <input form="shop-cash-credit-form" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                        </label>
                        <label>
                            <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Movement Date</span>
                            <input form="shop-cash-credit-form" type="date" name="business_date" value="{{ old('business_date', $selectedDate->format('Y-m-d')) }}" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                        </label>
                        <div data-shop-cash-category-dropdown class="relative">
                            <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Category</span>
                            @php
                                $selectedCashCategory = $cashMovementCategories->firstWhere('id', (int) old('shop_cash_movement_category_id', $defaultCashMovementCategory->id)) ?? $defaultCashMovementCategory;
                            @endphp
                            <input form="shop-cash-credit-form" type="hidden" name="shop_cash_movement_category_id" value="{{ old('shop_cash_movement_category_id', $selectedCashCategory->id) }}" data-shop-cash-category-input>
                            <button type="button" data-shop-cash-category-button class="flex h-11 w-full items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 text-left text-sm font-black text-slate-950 transition hover:bg-slate-50 focus:border-emerald-500 focus:outline-none">
                                <span data-shop-cash-category-label>{{ $selectedCashCategory->name }}</span>
                                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                            <div data-shop-cash-category-menu class="absolute left-0 right-0 top-[4.4rem] z-20 hidden overflow-hidden rounded-2xl border border-slate-200 bg-white py-2 shadow-xl">
                                @foreach($cashMovementCategories as $category)
                                    <button type="button" data-shop-cash-category-option data-value="{{ $category->id }}" data-label="{{ $category->name }}" class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-black text-slate-800 transition hover:bg-emerald-50 hover:text-emerald-700">
                                        <span>{{ $category->name }}</span>
                                        @if($category->is_default)
                                            <span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-emerald-700">Default</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
	                        <label>
                            <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Description</span>
	                            <input form="shop-cash-credit-form" type="text" name="description" value="{{ old('description') }}" placeholder="Loan given to client shop" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                        </label>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="shop-cash-modal-close inline-flex h-11 items-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                                Cancel
                            </button>
                            <button form="shop-cash-credit-form" type="submit" class="inline-flex h-11 items-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">
                                Record loan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="daily-entry-modal" class="hidden fixed inset-0 z-[70]">
            <div class="daily-entry-modal-overlay absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>
            <div class="relative flex min-h-full items-center justify-center p-4">
                <div class="w-full max-w-5xl rounded-[2rem] border border-slate-200 bg-white shadow-[0_30px_80px_rgba(15,23,42,0.22)]">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Daily Entry</p>
                            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ $hasEntry ? 'Update daily entry' : 'Add daily entry' }}</h2>
                            <p class="mt-2 text-sm font-semibold text-slate-600">Only category, amount, and description are required here.</p>
                        </div>
                        <button type="button" class="daily-entry-modal-close inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ $entryAction }}" class="space-y-4 px-6 py-6">
                        @csrf
                        @if($hasEntry)
                            @method('PATCH')
                        @endif
                        <input type="hidden" name="business_date" value="{{ $selectedDate->format('Y-m-d') }}">
                        <input type="hidden" name="status" value="{{ old('status', in_array($entry?->status, ['approved', 'finalized'], true) ? $entry->status : 'submitted') }}">
                        <input type="hidden" name="opening_cash" value="{{ old('opening_cash', $entry?->opening_cash ?? $suggestedOpeningBalance) }}">
                        <input type="hidden" name="closing_cash" value="{{ old('closing_cash', $entry?->closing_cash) }}">

                        <div class="space-y-3">
                            @for($index = 0; $index < max(4, $hasEntry ? $entry->lines->count() : 0); $index++)
                                @php $line = $hasEntry ? $entry->lines[$index] ?? null : null; @endphp
                                <div class="grid gap-3 rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4 md:grid-cols-[1.2fr_0.8fr_1.2fr]">
                                    <select name="lines[{{ $index }}][shop_accounting_category_id]" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                                        <option value="">Select category</option>
                                        @foreach($availableCategories as $category)
                                            <option value="{{ $category->id }}" {{ (string) old("lines.$index.shop_accounting_category_id", $line?->shop_accounting_category_id) === (string) $category->id ? 'selected' : '' }}>
                                                {{ strtoupper($category->type) }} • {{ $category->name }}{{ $category->shop_id ? ' (Shop)' : ' (Global)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="number" step="0.01" min="0.01" name="lines[{{ $index }}][amount]" value="{{ old("lines.$index.amount", $line?->amount) }}" placeholder="Amount" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                                    <input type="text" name="lines[{{ $index }}][description]" value="{{ old("lines.$index.description", $line?->description) }}" placeholder="Description" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none">
                                </div>
                            @endfor
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" class="inline-flex h-11 items-center rounded-2xl bg-cyan-600 px-5 text-sm font-black text-white transition hover:bg-cyan-500">
                                {{ $hasEntry ? 'Submit Daily Update' : 'Submit Daily Entry' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if ($hasEntry)
            <div id="line-review-modal" class="hidden fixed inset-0 z-[85]">
                <div class="line-review-modal-overlay absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>
                <div class="relative flex min-h-full items-center justify-center p-4">
                    <div class="w-full max-w-2xl rounded-[2rem] border border-slate-200 bg-white shadow-[0_30px_80px_rgba(15,23,42,0.22)]">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                            <div>
                                <p id="line-review-modal-kicker" class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Line Review</p>
                                <h2 id="line-review-modal-title" class="mt-2 text-2xl font-black tracking-tight text-slate-950">Review Item</h2>
                                <p id="line-review-modal-description" class="mt-2 text-sm font-semibold text-slate-600"></p>
                            </div>
                            <button type="button" class="line-review-modal-close inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <form method="POST" action="{{ route('admin.accounting.owned-shops.entries.review', ['shop' => $shop, 'entry' => $entry]) }}" class="space-y-4 px-6 py-6">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="decision" value="review_lines">
                            <input type="hidden" name="line_reviews[0][decision]" id="line-review-decision" value="">
                            <input type="hidden" name="line_reviews[0][review_note]" id="line-review-note-hidden" value="">
                            <input type="hidden" name="admin_note" id="line-review-admin-note-hidden" value="">
                            <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Selected Item</p>
                                <p id="line-review-modal-item" class="mt-2 text-sm font-black text-slate-950"></p>
                            </div>
                            <label class="block">
                                <span id="line-review-note-label" class="mb-2 block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Note</span>
                                <textarea id="line-review-note-input" rows="4" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-400 focus:outline-none" placeholder="Add a note if needed."></textarea>
                            </label>
                            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                                <button type="button" class="line-review-modal-close inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-800 transition hover:bg-slate-50">
                                    Cancel
                                </button>
                                <button type="submit" id="line-review-submit-button" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">
                                    Confirm
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="approve-entry-modal" class="hidden fixed inset-0 z-[80]">
                <div class="approve-entry-modal-overlay absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>
                <div class="relative flex min-h-full items-center justify-center p-4">
                    <div class="w-full max-w-3xl rounded-[2rem] border border-slate-200 bg-white shadow-[0_30px_80px_rgba(15,23,42,0.22)]">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-emerald-700">Approve Request</p>
                                <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Confirm all submitted items</h2>
                                <p class="mt-2 text-sm font-semibold text-slate-600">Review the exact update before accounting approves this day.</p>
                            </div>
                            <button type="button" class="approve-entry-modal-close inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="space-y-4 px-6 py-6">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Submitted By</p>
                                    <p class="mt-2 text-sm font-black text-slate-950">{{ $entry->submittedBy?->name ?? 'Admin entry' }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $entry->submitted_at?->format('d M Y h:i A') }}</p>
                                </div>
                                <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Day Summary</p>
                                    <p class="mt-2 text-sm font-black text-slate-950">Income Rs. {{ number_format($entryIncomeTotal, 2) }}</p>
                                    <p class="mt-1 text-sm font-black text-slate-950">Expense Rs. {{ number_format($entryExpenseTotal, 2) }}</p>
                                </div>
                            </div>

                            <div class="max-h-[22rem] overflow-y-auto rounded-[1.15rem] border border-slate-200">
                                <table class="min-w-full text-left">
                                    <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                        <tr>
                                            <th class="px-4 py-3">Type</th>
                                            <th class="px-4 py-3">Category</th>
                                            <th class="px-4 py-3">Notes</th>
                                            <th class="px-4 py-3 text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-sm">
                                        @foreach ($entry->lines as $line)
                                            <tr>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $line->type === 'income' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
                                                        {{ $line->type }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 font-black text-slate-950">{{ $line->category?->name ?? 'Category removed' }}</td>
                                                <td class="px-4 py-3 text-sm font-semibold text-slate-600">{{ $line->description ?: 'No note added' }}</td>
                                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $line->amount, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Admin Note Preview</p>
                                <p id="approve-entry-note-preview" class="mt-2 text-sm font-semibold text-slate-700">No note added.</p>
                            </div>

                            <form method="POST" action="{{ route('admin.accounting.owned-shops.entries.review', ['shop' => $shop, 'entry' => $entry]) }}" class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="decision" value="approve">
                                <input type="hidden" name="admin_note" id="approve-entry-note-hidden" value="{{ old('admin_note', $entry->admin_note) }}">
                                <button type="button" class="approve-entry-modal-close inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-800 transition hover:bg-slate-50">
                                    Cancel
                                </button>
                                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500">
                                    Confirm Approve All
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <script>
            (() => {
                const bindModal = (openId, modalId, closeSelector, forceOpen = false) => {
                    const openButton = document.getElementById(openId);
                    const modal = document.getElementById(modalId);

                    if (!modal) {
                        return;
                    }

                    const openModal = () => {
                        modal.classList.remove('hidden');
                        document.body.classList.add('overflow-hidden');
                    };

                    const closeModal = () => {
                        modal.classList.add('hidden');
                        document.body.classList.remove('overflow-hidden');
                    };

                    if (forceOpen) {
                        openModal();
                    }

                    openButton?.addEventListener('click', openModal);
                    modal.querySelectorAll(closeSelector).forEach((button) => {
                        button.addEventListener('click', closeModal);
                    });
                    modal.addEventListener('click', (event) => {
                        if (event.target === modal.querySelector(`.${modalId}-overlay`) || event.target.classList.contains(`${modalId}-overlay`)) {
                            closeModal();
                        }
                    });
                };

                bindModal('shop-cash-open-modal', 'shop-cash-modal', '.shop-cash-modal-close', {{ $errors->has('type') || $errors->has('amount') || $errors->has('description') || $errors->has('shop_cash_movement_category_id') ? 'true' : 'false' }});
                bindModal('daily-entry-open-modal', 'daily-entry-modal', '.daily-entry-modal-close', {{ $errors->has('lines') || $errors->has('business_date') ? 'true' : 'false' }});
                bindModal('approve-entry-open-modal', 'approve-entry-modal', '.approve-entry-modal-close');
                bindModal('review-details-open-modal', 'approve-entry-modal', '.approve-entry-modal-close');
                bindModal('line-review-open-missing', 'line-review-modal', '.line-review-modal-close', {{ old('decision') === 'review_lines' ? 'true' : 'false' }});

                const approvalNoteInput = document.querySelector('textarea[name="admin_note"]');
                const approveEntryOpenModal = document.getElementById('approve-entry-open-modal');
                const approveEntryNoteHidden = document.getElementById('approve-entry-note-hidden');
                const approveEntryNotePreview = document.getElementById('approve-entry-note-preview');
                const lineReviewModal = document.getElementById('line-review-modal');
                const lineReviewButtons = document.querySelectorAll('.line-review-open');
                const lineReviewTitle = document.getElementById('line-review-modal-title');
                const lineReviewDescription = document.getElementById('line-review-modal-description');
                const lineReviewItem = document.getElementById('line-review-modal-item');
                const lineReviewDecision = document.getElementById('line-review-decision');
                const lineReviewNoteHidden = document.getElementById('line-review-note-hidden');
                const lineReviewAdminNoteHidden = document.getElementById('line-review-admin-note-hidden');
                const lineReviewNoteInput = document.getElementById('line-review-note-input');
                const lineReviewNoteLabel = document.getElementById('line-review-note-label');
                const lineReviewSubmitButton = document.getElementById('line-review-submit-button');
                const shopCashCategoryDropdown = document.querySelector('[data-shop-cash-category-dropdown]');
                const shopCashCategoryButton = shopCashCategoryDropdown?.querySelector('[data-shop-cash-category-button]');
                const shopCashCategoryMenu = shopCashCategoryDropdown?.querySelector('[data-shop-cash-category-menu]');
                const shopCashCategoryInput = shopCashCategoryDropdown?.querySelector('[data-shop-cash-category-input]');
                const shopCashCategoryLabel = shopCashCategoryDropdown?.querySelector('[data-shop-cash-category-label]');

                shopCashCategoryButton?.addEventListener('click', () => {
                    shopCashCategoryMenu?.classList.toggle('hidden');
                });

                shopCashCategoryDropdown?.querySelectorAll('[data-shop-cash-category-option]').forEach((option) => {
                    option.addEventListener('click', () => {
                        if (shopCashCategoryInput instanceof HTMLInputElement) {
                            shopCashCategoryInput.value = option.dataset.value ?? '';
                        }

                        if (shopCashCategoryLabel) {
                            shopCashCategoryLabel.textContent = option.dataset.label ?? 'Loan';
                        }

                        shopCashCategoryMenu?.classList.add('hidden');
                    });
                });

                document.addEventListener('click', (event) => {
                    if (!shopCashCategoryDropdown?.contains(event.target)) {
                        shopCashCategoryMenu?.classList.add('hidden');
                    }
                });

                approveEntryOpenModal?.addEventListener('click', () => {
                    const note = approvalNoteInput instanceof HTMLTextAreaElement ? approvalNoteInput.value.trim() : '';
                    if (approveEntryNoteHidden instanceof HTMLInputElement) {
                        approveEntryNoteHidden.value = note;
                    }

                    if (approveEntryNotePreview) {
                        approveEntryNotePreview.textContent = note !== '' ? note : 'No note added.';
                    }
                });

                const openLineReviewModal = (button) => {
                    if (!lineReviewModal || !lineReviewDecision || !lineReviewItem || !lineReviewTitle || !lineReviewDescription || !lineReviewNoteInput || !lineReviewSubmitButton || !lineReviewAdminNoteHidden || !lineReviewNoteHidden || !lineReviewNoteLabel) {
                        return;
                    }

                    const lineId = button.dataset.lineId ?? '';
                    const action = button.dataset.lineAction ?? 'approve';
                    const label = button.dataset.lineLabel ?? 'Item';
                    const description = button.dataset.lineDescription ?? '';

                    lineReviewDecision.name = `line_reviews[${lineId}][decision]`;
                    lineReviewDecision.value = action;
                    lineReviewNoteHidden.name = `line_reviews[${lineId}][review_note]`;
                    lineReviewItem.textContent = label;
                    lineReviewTitle.textContent = action === 'approve' ? 'Approve This Item' : 'Send Item For Recheck';
                    lineReviewDescription.textContent = description;
                    lineReviewNoteLabel.textContent = action === 'approve' ? 'Optional Note' : 'Recheck Note';
                    lineReviewNoteInput.placeholder = action === 'approve' ? 'Add a short note if needed.' : 'Tell the shop owner what to fix.';
                    lineReviewSubmitButton.textContent = action === 'approve' ? 'Confirm Approve' : 'Confirm Recheck';
                    lineReviewSubmitButton.className = `inline-flex h-11 items-center justify-center rounded-2xl px-5 text-sm font-black text-white transition ${action === 'approve' ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-red-600 hover:bg-red-500'}`;
                    lineReviewNoteInput.value = '';
                    lineReviewModal.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');
                };

                lineReviewButtons.forEach((button) => {
                    button.addEventListener('click', () => openLineReviewModal(button));
                });

                lineReviewModal?.querySelector('form')?.addEventListener('submit', () => {
                    if (!(lineReviewNoteInput instanceof HTMLTextAreaElement) || !(lineReviewAdminNoteHidden instanceof HTMLInputElement) || !(lineReviewNoteHidden instanceof HTMLInputElement) || !(lineReviewDecision instanceof HTMLInputElement)) {
                        return;
                    }

                    const note = lineReviewNoteInput.value.trim();
                    lineReviewNoteHidden.value = note;
                    lineReviewAdminNoteHidden.value = lineReviewDecision.value === 'recheck' ? note : '';
                });

            })();
        </script>
        @endif
    </div>
</x-layouts.accounting>
