<div x-data="{
        showModal: false,
        loadingDetails: false,
        isSubmitting: false,
        errorMessage: null,
        bill: null,
        details: null,
        form: {
            payment_amount: '',
            payment_date: '{{ today('Asia/Kolkata')->toDateString() }}',
            payment_method: 'Bank',
            company_account_id: '',
            payment_note: ''
        },
        open(data) {
            this.bill = data;
            this.details = null;
            this.errorMessage = null;
            this.form.payment_amount = (parseFloat(data.outstanding) || 0).toFixed(2);
            this.form.payment_date = '{{ today('Asia/Kolkata')->toDateString() }}';
            this.form.payment_method = 'Bank';
            this.form.company_account_id = '';
            this.form.payment_note = '';
            this.showModal = true;
            this.fetchDetails(data.invoice_public_uuid);
        },
        close() {
            if (this.isSubmitting) return;
            this.showModal = false;
            this.errorMessage = null;
        },
        async fetchDetails(uuid) {
            if (!uuid) return;
            this.loadingDetails = true;
            try {
                const res = await fetch(`/admin/cashbook/finance/vendor-credit/${uuid}/details`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const json = await res.json();
                    this.details = json;
                    if (json.outstanding !== undefined) {
                        this.form.payment_amount = parseFloat(json.outstanding).toFixed(2);
                    }
                }
            } catch (err) {
                console.error('Error fetching bill details', err);
            } finally {
                this.loadingDetails = false;
            }
        },
        async submit() {
            if (!this.bill) return;
            const amt = parseFloat(this.form.payment_amount);
            const maxDue = this.details ? parseFloat(this.details.outstanding) : parseFloat(this.bill.outstanding);
            if (isNaN(amt) || amt <= 0) {
                this.errorMessage = 'Please enter a valid payment amount greater than zero.';
                return;
            }
            if (amt > maxDue + 0.009) {
                this.errorMessage = `Payment amount cannot exceed the outstanding balance of Rs. ${maxDue.toFixed(2)}.`;
                return;
            }

            this.isSubmitting = true;
            this.errorMessage = null;

            try {
                const res = await fetch(this.bill.pay_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        _token: '{{ csrf_token() }}',
                        payment_amount: this.form.payment_amount,
                        payment_date: this.form.payment_date,
                        payment_method: this.form.payment_method,
                        company_account_id: this.form.company_account_id || null,
                        payment_note: this.form.payment_note || null
                    })
                });

                const data = await res.json();

                if (res.ok && data.success) {
                    sessionStorage.setItem('purchase_payment_flash_success', data.message || 'Payment recorded successfully.');
                    window.location.reload();
                } else {
                    if (data.errors) {
                        this.errorMessage = Object.values(data.errors).flat().join(' ');
                    } else {
                        this.errorMessage = data.message || 'Payment recording failed. Please verify and try again.';
                    }
                    this.isSubmitting = false;
                }
            } catch (err) {
                this.errorMessage = 'A network or server error occurred. Please try again.';
                this.isSubmitting = false;
            }
        }
     }"
     @open-payment-modal.window="open($event.detail)"
     x-cloak>

    {{-- Backdrop & Modal Container --}}
    <div x-show="showModal"
         class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/65 backdrop-blur-xs overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">

        <div @click.away="close()"
             class="w-full max-w-2xl rounded-3xl bg-white shadow-2xl border border-slate-200/90 overflow-hidden my-6">

            {{-- Modal Header --}}
            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-5 py-4">
                <div class="space-y-0.5">
                    <div class="flex items-center gap-2">
                        <span class="rounded bg-emerald-100 text-emerald-900 border border-emerald-300 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider">
                            GREEN LEAF
                        </span>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">
                            PURCHASE BILL PAYMENT &bull; SINGLE BILL SETTLEMENT
                        </h3>
                    </div>
                    <p class="text-xs font-bold text-slate-500">
                        Recording company payment for purchase bill <span class="font-mono text-slate-900" x-text="bill ? bill.invoice_number : ''"></span>
                    </p>
                </div>
                <button type="button"
                        @click="close()"
                        :disabled="isSubmitting"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-200/80 hover:text-slate-700 transition cursor-pointer">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-5 sm:p-6 space-y-5 max-h-[calc(85vh-130px)] overflow-y-auto">
                {{-- Bill Meta Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50/90 p-3.5 rounded-2xl border border-slate-200/70 text-xs">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Bill Number</span>
                        <span class="font-mono font-bold text-slate-900 truncate block mt-0.5" x-text="bill ? bill.invoice_number : '—'"></span>
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Vendor</span>
                        <span class="font-bold text-slate-900 truncate block mt-0.5" x-text="bill ? bill.supplier_name : '—'"></span>
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Purchaser</span>
                        <span class="font-semibold text-slate-800 truncate block mt-0.5" x-text="bill ? (bill.purchaser_name || '—') : '—'"></span>
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Purchase Date</span>
                        <span class="font-semibold text-slate-800 truncate block mt-0.5" x-text="bill ? (bill.business_date || '—') : '—'"></span>
                    </div>
                </div>

                {{-- Payment Summary Table --}}
                <div class="space-y-1.5">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Payment Summary</span>
                    <div class="rounded-2xl border border-slate-200/80 bg-white overflow-hidden shadow-2xs">
                        <table class="w-full text-xs">
                            <tbody class="divide-y divide-slate-100">
                                <tr>
                                    <td class="px-4 py-2 text-slate-600 font-semibold">Purchase Total</td>
                                    <td class="px-4 py-2 text-right font-mono font-bold text-slate-900 tabular-nums">
                                        Rs. <span x-text="details ? Number(details.amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : (bill ? Number(bill.gross_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00')"></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2 text-slate-600 font-semibold">Discount</td>
                                    <td class="px-4 py-2 text-right font-mono font-bold text-slate-700 tabular-nums">
                                        Rs. <span x-text="details ? Number(details.discount_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : (bill ? Number(bill.discount_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00')"></span>
                                    </td>
                                </tr>
                                <tr class="bg-slate-50/70 font-bold">
                                    <td class="px-4 py-2 text-slate-900 uppercase text-[11px]">Net Payable</td>
                                    <td class="px-4 py-2 text-right font-mono text-slate-900 tabular-nums">
                                        Rs. <span x-text="details ? Number(details.net_payable).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : (bill ? Number(bill.net_payable).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00')"></span>
                                    </td>
                                </tr>
                                <tr class="bg-emerald-50/60 text-emerald-900 font-bold">
                                    <td class="px-4 py-2 flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        <span>Already Paid</span>
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono text-emerald-700 tabular-nums font-black">
                                        Rs. <span x-text="details ? Number(details.paid_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : (bill ? Number(bill.paid_amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00')"></span>
                                    </td>
                                </tr>
                                <tr class="bg-amber-50/80 text-amber-950 font-black">
                                    <td class="px-4 py-2.5 uppercase text-[11px] tracking-wider text-rose-900">Credit Balance</td>
                                    <td class="px-4 py-2.5 text-right font-mono text-rose-700 text-sm tabular-nums">
                                        Rs. <span x-text="details ? Number(details.outstanding).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : (bill ? Number(bill.outstanding).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '0.00')"></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Existing Payment History (if any) --}}
                <div class="space-y-1.5" x-show="details && details.payments && details.payments.length > 0">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Existing Payment History</span>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 overflow-hidden">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-100 text-[10px] font-black uppercase text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="px-3 py-1.5">Date</th>
                                    <th class="px-3 py-1.5">Method</th>
                                    <th class="px-3 py-1.5 text-right">Amount</th>
                                    <th class="px-3 py-1.5">Recorded By</th>
                                    <th class="px-3 py-1.5">Note</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/60 bg-white">
                                <template x-for="p in (details ? details.payments : [])" :key="p.id">
                                    <tr class="text-[11px]">
                                        <td class="px-3 py-1.5 font-semibold text-slate-600" x-text="p.date || '—'"></td>
                                        <td class="px-3 py-1.5 font-bold text-slate-800" x-text="p.payment_method || '—'"></td>
                                        <td class="px-3 py-1.5 text-right font-mono font-bold text-emerald-700 tabular-nums" x-text="'Rs. ' + Number(p.amount).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></td>
                                        <td class="px-3 py-1.5 text-slate-600 font-medium" x-text="p.created_by || 'Admin'"></td>
                                        <td class="px-3 py-1.5 text-slate-500 truncate max-w-xs" x-text="p.note || '—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Error Alert Banner --}}
                <div x-show="errorMessage" x-cloak
                     class="rounded-xl bg-rose-50 border border-rose-300 p-3 flex items-start gap-2.5 text-xs text-rose-900 font-bold">
                    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0 mt-0.5"></i>
                    <span x-text="errorMessage"></span>
                </div>

                {{-- Payment Update Form --}}
                <form @submit.prevent="submit()" class="space-y-4 pt-1 border-t border-slate-100">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Payment Amount --}}
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-bold text-slate-700">Payment Amount (Rs.) <span class="text-rose-600">*</span></label>
                                <button type="button"
                                        @click="form.payment_amount = details ? details.outstanding.toFixed(2) : bill.outstanding.toFixed(2)"
                                        class="text-[11px] font-extrabold text-emerald-700 hover:text-emerald-800 hover:underline cursor-pointer">
                                    Pay Full Due
                                </button>
                            </div>
                            <input type="number"
                                   step="0.01"
                                   min="0.01"
                                   :max="details ? details.outstanding : (bill ? bill.outstanding : 999999)"
                                   x-model="form.payment_amount"
                                   required
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-sm font-bold text-slate-900 focus:border-emerald-600 focus:outline-hidden focus:ring-1 focus:ring-emerald-600">
                            <span class="text-[10px] text-slate-500 font-semibold block mt-1">
                                Enter exact or partial payment. Max allowed: Rs. <span class="font-mono text-slate-800" x-text="details ? details.outstanding.toFixed(2) : (bill ? bill.outstanding.toFixed(2) : '0.00')"></span>
                            </span>
                        </div>

                        {{-- Payment Date --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Payment Date <span class="text-rose-600">*</span></label>
                            <input type="date"
                                   x-model="form.payment_date"
                                   required
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:outline-hidden focus:ring-1 focus:ring-emerald-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Payment Method --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Payment Method <span class="text-rose-600">*</span></label>
                            <select x-model="form.payment_method"
                                    required
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden focus:ring-1 focus:ring-emerald-600">
                                <option value="Bank">Bank Transfer / NEFT / RTGS</option>
                                <option value="Online">Online / Net Banking</option>
                                <option value="GPay">UPI / GPay</option>
                                <option value="Cash">Cash in Hand</option>
                            </select>
                        </div>

                        {{-- Company Account (Optional) --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Company Account (Optional)</label>
                            <select x-model="form.company_account_id"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden focus:ring-1 focus:ring-emerald-600">
                                <option value="">Select Account (Default)</option>
                                @if(!empty($companyAccounts))
                                    @foreach($companyAccounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->name }} ({{ strtoupper($acc->account_type ?? 'ACCOUNT') }})</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    </div>

                    {{-- Payment Reference or Remarks --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Payment Reference / Remarks</label>
                        <input type="text"
                               x-model="form.payment_note"
                               placeholder="Cheque #, UTR, transaction ref, remarks..."
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-800 focus:border-emerald-600 focus:outline-hidden focus:ring-1 focus:ring-emerald-600">
                    </div>

                    {{-- Buttons --}}
                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                        <button type="button"
                                @click="close()"
                                :disabled="isSubmitting"
                                class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="isSubmitting"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white px-5 py-2 text-xs font-black uppercase tracking-wider shadow-sm transition cursor-pointer disabled:opacity-50">
                            <template x-if="isSubmitting">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    <span>Saving Payment...</span>
                                </span>
                            </template>
                            <template x-if="!isSubmitting">
                                <span class="inline-flex items-center gap-1.5">
                                    <i data-lucide="check" class="w-4 h-4"></i>
                                    <span>Save Payment</span>
                                </span>
                            </template>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

