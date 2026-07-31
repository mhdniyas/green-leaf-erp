<x-layouts.accounting title="Create Shop Payment">
    @php
        $dateParam = $date->format('Y-m-d');
        $contextUrlTemplate = url('/admin/finance-v2/payments/shop-context/__SHOP__').'?date='.$dateParam;
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5" data-payment-create
         data-context-url-template="{{ $contextUrlTemplate }}"
         data-date="{{ $dateParam }}">
        @include('admin.finance-v2.partials.nav')

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-6 text-white sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">Admin Entry</p>
                        <h1 class="mt-2 text-3xl font-black tracking-tight">Create shop payment</h1>
                        <p class="mt-2 text-sm font-semibold text-slate-300">Select a shop to see pending bills, credit, company payables and allocation before you create the request.</p>
                    </div>
                    <a href="{{ route('admin.finance-v2.payments.index', ['date' => $dateParam]) }}" class="inline-flex h-11 items-center rounded-[1rem] border border-white/20 bg-white/10 px-5 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-white/15">Back to Payments</a>
                </div>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
            <section class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Payment Form</p>
                <h3 class="mt-2 text-xl font-black text-slate-950">Enter payment details</h3>

                <form method="POST" action="{{ route('admin.finance-v2.payments.store') }}" class="mt-6 grid gap-4" id="shop-payment-create-form">
                    @csrf
                    <input type="hidden" name="date" value="{{ $dateParam }}">

                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Shop</span>
                        <select name="shop_id" id="payment-shop" required class="mt-2 h-12 w-full rounded-[1rem] border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-950">
                            <option value="">Select shop</option>
                            @foreach($shops as $shop)
                                <option value="{{ $shop->id }}" @selected(old('shop_id', $prefill_shop_id ?? null) == $shop->id)>
                                    {{ $shop->name }}@if($shop->client) — {{ $shop->client->name }}@endif
                                </option>
                            @endforeach
                        </select>
                        @error('shop_id')
                            <p class="mt-2 text-xs font-bold text-rose-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Amount</span>
                            <input type="number" step="0.01" min="0.01" name="requested_amount" id="payment-amount" value="{{ old('requested_amount', $prefill_requested_amount ?? null) }}" required class="mt-2 h-12 w-full rounded-[1rem] border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-950">
                            @error('requested_amount')
                                <p class="mt-2 text-xs font-bold text-rose-600">{{ $message }}</p>
                            @enderror
                        </label>
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Payment Date</span>
                            <input type="date" name="payment_date" value="{{ old('payment_date', $dateParam) }}" required class="mt-2 h-12 w-full rounded-[1rem] border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-950">
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Method</span>
                            <select name="payment_method" id="payment-method" required class="mt-2 h-12 w-full rounded-[1rem] border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-950">
                                <option value="cash" @selected(old('payment_method', $prefill_payment_method ?? 'cash') === 'cash')>Cash</option>
                                <option value="online_upi" @selected(old('payment_method', $prefill_payment_method ?? 'cash') === 'online_upi')>Bank / UPI</option>
                                <option value="cheque" @selected(old('payment_method', $prefill_payment_method ?? 'cash') === 'cheque')>Cheque</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Reference / Cheque No</span>
                            <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" class="mt-2 h-12 w-full rounded-[1rem] border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-950">
                        </label>
                    </div>

                    <div id="cheque-fields" class="grid gap-4 md:grid-cols-2 {{ old('payment_method', $prefill_payment_method ?? 'cash') === 'cheque' ? '' : 'hidden' }}">
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Cheque Bank</span>
                            <input type="text" name="cheque_bank_name" value="{{ old('cheque_bank_name') }}" class="mt-2 h-12 w-full rounded-[1rem] border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-950">
                        </label>
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Cheque Date</span>
                            <input type="date" name="cheque_date" value="{{ old('cheque_date') }}" class="mt-2 h-12 w-full rounded-[1rem] border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-950">
                        </label>
                    </div>

                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Notes</span>
                        <textarea name="shop_note" rows="3" class="mt-2 w-full rounded-[1rem] border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold text-slate-950">{{ old('shop_note') }}</textarea>
                    </label>

                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('admin.finance-v2.payments.index', ['date' => $dateParam]) }}" class="inline-flex h-11 items-center rounded-[1rem] border border-slate-200 px-5 text-xs font-black uppercase tracking-[0.16em] text-slate-700">Cancel</a>
                        <button class="inline-flex h-11 items-center rounded-[1rem] bg-orange-500 px-5 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-orange-600">Create Payment</button>
                    </div>
                </form>
            </section>

            <section class="space-y-5">
                <div id="shop-details-empty" class="rounded-[1.6rem] border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm">
                    <p class="text-sm font-black text-slate-950">No shop selected</p>
                    <p class="mt-2 text-sm font-semibold text-slate-500">Choose a shop to load pending bills, credit, payables and recent payments.</p>
                </div>

                <div id="shop-details-panel" class="hidden space-y-5">
                    <article class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Shop Context</p>
                                <h3 class="mt-2 text-xl font-black text-slate-950" data-shop-name>—</h3>
                                <p class="mt-1 text-sm font-semibold text-slate-500" data-shop-client>—</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-600" data-loading-state>Ready</span>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Pending Bills</p>
                                <p class="mt-2 text-xl font-black text-amber-700" data-pending-bills>Rs. 0.00</p>
                            </div>
                            <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Available Credit</p>
                                <p class="mt-2 text-xl font-black text-cyan-700" data-available-credit>Rs. 0.00</p>
                            </div>
                            <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Closing Balance</p>
                                <p class="mt-2 text-xl font-black text-slate-950" data-closing-balance>Rs. 0.00</p>
                            </div>
                            <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Company Payable</p>
                                <p class="mt-2 text-xl font-black text-violet-700" data-company-payable>Rs. 0.00</p>
                                <p class="mt-1 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400" data-company-payable-count>0 pending</p>
                            </div>
                        </div>

                        <div class="mt-4 rounded-[1rem] border border-emerald-200 bg-emerald-50 px-4 py-3">
                            <p class="text-sm font-black text-emerald-900" data-preview-message>Enter a payment amount to preview invoice allocation.</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700">Will apply</p>
                                    <p class="mt-1 text-lg font-black text-emerald-900" data-applied-amount>Rs. 0.00</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700">Excess credit</p>
                                    <p class="mt-1 text-lg font-black text-emerald-900" data-credit-amount>Rs. 0.00</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700">Received MTD</p>
                                    <p class="mt-1 text-lg font-black text-emerald-900" data-received-mtd>Rs. 0.00</p>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-5 py-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Pending Invoices</p>
                            <h3 class="mt-1 text-lg font-black text-slate-950">Oldest bills first</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100 text-left">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Invoice</th>
                                        <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Date</th>
                                        <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Remaining</th>
                                        <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Will Allocate</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100" data-invoice-rows>
                                    <tr>
                                        <td colspan="4" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No pending invoices.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Open Company Payables</p>
                                <h3 class="mt-1 text-lg font-black text-slate-950">Company-funded expenses for this shop</h3>
                            </div>
                            <a href="{{ route('admin.finance-v2.company-payables.index', ['date' => $dateParam]) }}" class="text-[10px] font-black uppercase tracking-[0.14em] text-orange-600 hover:underline" data-payables-link>View all</a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100 text-left">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Category</th>
                                        <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Status</th>
                                        <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Remaining</th>
                                        <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-[0.16em] text-slate-400"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100" data-payable-rows>
                                    <tr>
                                        <td colspan="4" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No open company payables for this shop.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <article class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Recent Payments</p>
                                <h3 class="mt-1 text-lg font-black text-slate-950">Latest shop payment activity</h3>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100 text-left">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Date</th>
                                        <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Method</th>
                                        <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Amount</th>
                                        <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Status</th>
                                        <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-[0.16em] text-slate-400"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100" data-recent-payments>
                                    <tr>
                                        <td colspan="5" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No recent payments.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-payment-create]');
            if (!root) return;

            const shopSelect = document.getElementById('payment-shop');
            const amountInput = document.getElementById('payment-amount');
            const methodSelect = document.getElementById('payment-method');
            const chequeFields = document.getElementById('cheque-fields');
            const emptyState = document.getElementById('shop-details-empty');
            const panel = document.getElementById('shop-details-panel');
            const loadingState = root.querySelector('[data-loading-state]');
            const urlTemplate = root.dataset.contextUrlTemplate;
            const dateParam = root.dataset.date;
            let pendingInvoices = [];
            let fetchTimer = null;

            const money = (value) => `Rs. ${Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

            const toggleCheque = () => {
                chequeFields?.classList.toggle('hidden', methodSelect.value !== 'cheque');
            };

            const setText = (selector, value) => {
                const el = root.querySelector(selector);
                if (el) el.textContent = value;
            };

            const computeAllocation = (amount) => {
                let remaining = Math.max(0, Number(amount) || 0);
                const rows = pendingInvoices.map((invoice) => {
                    const balance = Number(invoice.balance_amount) || 0;
                    const allocate = Math.min(remaining, balance);
                    remaining = Math.round((remaining - allocate) * 100) / 100;
                    return { ...invoice, allocate_amount: Math.round(allocate * 100) / 100 };
                });
                const applied = rows.reduce((sum, row) => sum + row.allocate_amount, 0);
                const credit = Math.max(0, Math.round(((Number(amount) || 0) - applied) * 100) / 100);
                return { rows, applied: Math.round(applied * 100) / 100, credit };
            };

            const renderInvoices = (amount) => {
                const tbody = root.querySelector('[data-invoice-rows]');
                if (!tbody) return;
                if (!pendingInvoices.length) {
                    tbody.innerHTML = `<tr><td colspan="4" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No pending invoices.</td></tr>`;
                    setText('[data-applied-amount]', money(0));
                    setText('[data-credit-amount]', money(Number(amount) || 0));
                    setText('[data-preview-message]', Number(amount) > 0
                        ? `No pending bills. The full amount will become shop credit (${money(amount)}).`
                        : 'Enter a payment amount to preview invoice allocation.');
                    return;
                }

                const { rows, applied, credit } = computeAllocation(amount);
                tbody.innerHTML = rows.map((row) => `
                    <tr>
                        <td class="px-4 py-3 text-sm font-black text-slate-950">${row.invoice_number || ('#' + row.id)}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-slate-600">${row.business_date || '—'}</td>
                        <td class="px-4 py-3 text-right text-sm font-black text-amber-700">${money(row.balance_amount)}</td>
                        <td class="px-4 py-3 text-right text-sm font-black text-emerald-700">${money(row.allocate_amount)}</td>
                    </tr>
                `).join('');

                setText('[data-applied-amount]', money(applied));
                setText('[data-credit-amount]', money(credit));
                if (!(Number(amount) > 0)) {
                    setText('[data-preview-message]', 'Enter a payment amount to preview invoice allocation.');
                } else if (applied > 0 && credit > 0) {
                    setText('[data-preview-message]', `This payment will cover ${money(applied)} of bills; ${money(credit)} becomes shop credit.`);
                } else if (applied > 0) {
                    setText('[data-preview-message]', `This payment will cover ${money(applied)} of pending bills.`);
                } else {
                    setText('[data-preview-message]', `No pending bills. The full amount will become shop credit (${money(credit)}).`);
                }
            };

            const renderRecent = (payments) => {
                const tbody = root.querySelector('[data-recent-payments]');
                if (!tbody) return;
                if (!payments?.length) {
                    tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No recent payments.</td></tr>`;
                    return;
                }
                tbody.innerHTML = payments.map((payment) => `
                    <tr>
                        <td class="px-4 py-3 text-sm font-semibold text-slate-600">${payment.date || '—'}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-slate-600">${payment.method || '—'}</td>
                        <td class="px-4 py-3 text-right text-sm font-black text-slate-950">${money(payment.amount)}</td>
                        <td class="px-4 py-3"><span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-600">${payment.status || '—'}</span></td>
                        <td class="px-4 py-3 text-right"><a href="${payment.url}" class="text-[10px] font-black uppercase tracking-[0.14em] text-orange-600 hover:underline">Open</a></td>
                    </tr>
                `).join('');
            };

            const renderPayables = (payables) => {
                const tbody = root.querySelector('[data-payable-rows]');
                if (!tbody) return;
                if (!payables?.length) {
                    tbody.innerHTML = `<tr><td colspan="4" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No open company payables for this shop.</td></tr>`;
                    return;
                }
                tbody.innerHTML = payables.map((payable) => {
                    const statusTone = payable.status === 'pending'
                        ? 'border-amber-200 bg-amber-50 text-amber-800'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-700';
                    return `
                        <tr>
                            <td class="px-4 py-3">
                                <p class="text-sm font-black text-slate-950">${payable.category || 'Expense'}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">${payable.business_date || '—'}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] ${statusTone}">${payable.status_label || payable.status || '—'}</span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-black text-violet-700">${money(payable.remaining)}</td>
                            <td class="px-4 py-3 text-right"><a href="${payable.url}" class="text-[10px] font-black uppercase tracking-[0.14em] text-orange-600 hover:underline">Open</a></td>
                        </tr>
                    `;
                }).join('');
            };

            const renderContext = (payload) => {
                emptyState?.classList.add('hidden');
                panel?.classList.remove('hidden');
                pendingInvoices = payload.pending_invoices || [];
                setText('[data-shop-name]', payload.shop?.name || 'Shop');
                setText('[data-shop-client]', payload.shop?.client_name || 'Direct / owned shop');
                setText('[data-pending-bills]', money(payload.summary?.pending_bills));
                setText('[data-available-credit]', money(payload.summary?.available_credit));
                setText('[data-closing-balance]', money(payload.summary?.closing_balance));
                setText('[data-company-payable]', money(payload.summary?.company_payable_remaining));
                setText('[data-company-payable-count]', `${payload.summary?.company_payable_pending_count || 0} pending`);
                setText('[data-received-mtd]', money(payload.summary?.received_mtd));
                if (payload.company_payables_url) {
                    const link = root.querySelector('[data-payables-link]');
                    if (link) link.href = payload.company_payables_url;
                }
                renderPayables(payload.company_payables || []);
                renderRecent(payload.recent_payments || []);
                renderInvoices(amountInput?.value);
            };

            const loadContext = async () => {
                const shopId = shopSelect?.value;
                if (!shopId) {
                    pendingInvoices = [];
                    panel?.classList.add('hidden');
                    emptyState?.classList.remove('hidden');
                    return;
                }

                if (loadingState) loadingState.textContent = 'Loading…';
                const amount = amountInput?.value || 0;
                let url = urlTemplate.replace('__SHOP__', shopId);
                url += (url.includes('?') ? '&' : '?') + `amount=${encodeURIComponent(amount)}`;
                if (!url.includes('date=')) {
                    url += `&date=${encodeURIComponent(dateParam)}`;
                }

                try {
                    const response = await fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) throw new Error('Failed to load shop context');
                    const payload = await response.json();
                    renderContext(payload);
                    if (loadingState) loadingState.textContent = 'Updated';
                } catch (error) {
                    if (loadingState) loadingState.textContent = 'Failed';
                    console.error(error);
                }
            };

            const scheduleLoad = () => {
                clearTimeout(fetchTimer);
                fetchTimer = setTimeout(loadContext, 200);
            };

            shopSelect?.addEventListener('change', scheduleLoad);
            amountInput?.addEventListener('input', () => {
                if (!shopSelect?.value) return;
                renderInvoices(amountInput.value);
                scheduleLoad();
            });
            methodSelect?.addEventListener('change', toggleCheque);

            toggleCheque();
            if (shopSelect?.value) {
                loadContext();
            }
        })();
    </script>
</x-layouts.accounting>
