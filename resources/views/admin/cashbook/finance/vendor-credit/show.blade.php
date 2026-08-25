@extends('admin.cashbook.layouts.app')

@section('title', $supplier->name . ' — Vendor Credit Details')

@section('header_title')
    <i data-lucide="truck" class="h-5 w-5 text-emerald-600"></i> {{ $supplier->name }}
@endsection

@section('header_subtitle')
    Credit purchase bills, settlement payments, and outstanding liability for this supplier.
@endsection

@section('header_actions')
    <div class="flex items-center gap-2"><a href="#vendor-bills" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 text-xs font-bold text-emerald-800 shadow-sm hover:bg-emerald-100"><i data-lucide="receipt" class="h-4 w-4"></i><span>View Bills</span></a><a href="{{ route('admin.cashbook.finance.vendor-credit') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50"><i data-lucide="arrow-left" class="h-4 w-4"></i><span>Back to Vendors</span></a></div>
@endsection

@section('content')
    <div class="mx-auto max-w-[96rem] space-y-6" x-data="vendorSettlement({{ Js::from($settlementCandidates) }})">
        <!-- KPI METRICS FOR THIS SUPPLIER -->
        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Credit Purchases</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-slate-950">₹{{ number_format($kpi['total_invoiced'], 2) }}</div>
                <span class="mt-1 block text-xs font-bold text-slate-500">{{ number_format($kpi['invoice_count']) }} bills total</span>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Settled</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-emerald-700">₹{{ number_format($kpi['total_paid'], 2) }}</div>
                <span class="mt-1 block text-xs font-bold text-emerald-600">Cleared payments</span>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Current Outstanding</span>
                <div class="mt-2 break-words font-mono text-2xl font-extrabold text-rose-700">₹{{ number_format($kpi['total_outstanding'], 2) }}</div>
                <span class="mt-1 block text-xs font-bold text-rose-600">Due to supplier</span>
            </div>
            <div class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">Supplier Info</span>
                <div class="mt-2 text-base font-extrabold text-slate-900 truncate">{{ $supplier->name }}</div>
                <span class="mt-1 block text-xs font-bold text-slate-500">{{ $supplier->mobile_number ?: ($supplier->contact ?: 'No phone recorded') }}</span>
            </div>
        </section>

        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div><h2 class="text-base font-extrabold text-slate-950">Simple Vendor Settlement</h2><p class="mt-0.5 text-xs font-semibold text-slate-500">Select bills, enter actual payment, review automatic allocation.</p></div>
                <span class="rounded-lg bg-sky-50 px-3 py-1.5 font-mono text-xs font-extrabold text-sky-800">Advance available: ₹{{ number_format($availableVendorAdvance, 2) }}</span>
            </div>
            <form method="POST" action="{{ route('admin.cashbook.finance.vendor-credit.settle', $supplier) }}" class="space-y-4">
                @csrf
                <div class="rounded-xl border border-slate-200 p-3"><span class="text-[10px] font-black uppercase text-slate-500">Payment Source</span><div class="mt-2 flex flex-wrap gap-4 text-xs font-bold"><label><input x-model="paymentSource" value="company" type="radio"> Company Account</label><label><input x-model="paymentSource" value="statement" type="radio"> Existing Statement Transaction</label></div></div>
                <div class="flex flex-wrap gap-2"><button type="button" @click="selectVisible()" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold">Select All Visible</button><button type="button" @click="clearSelection()" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold">Clear</button><span class="px-2 py-2 text-xs font-bold text-slate-600" x-text="`${selectedRows.length} Bills Selected`"></span></div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"><template x-for="row in rows" :key="row.id"><label class="flex min-h-16 cursor-pointer items-center gap-3 rounded-xl border p-3" :class="row.selected ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 bg-white'"><input x-model="row.selected" type="checkbox" class="h-6 w-6"><span class="min-w-0"><b class="block font-mono text-xs" x-text="row.number"></b><span class="block text-[11px] text-slate-500" x-text="row.date"></span></span><b class="ml-auto font-mono text-xs" x-text="rupees(row.outstanding)"></b></label></template></div>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl bg-slate-100 p-3"><span class="text-[10px] font-black uppercase text-slate-500">Selected Bills Total</span><div class="mt-1 font-mono text-xl font-black" x-text="rupees(selectedTotal)"></div></div>
                    <label class="text-xs font-bold text-slate-700">Actual Cash/Bank Payment<input x-model.number="cash" name="actual_payment_amount" type="number" min="0" step="0.01" required :readonly="paymentSource === 'statement'" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 font-mono"></label>
                    <label class="flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700"><input x-model="useAdvance" name="use_vendor_advance" value="1" type="checkbox" class="h-5 w-5"> Use Vendor Advance</label>
                    <label class="text-xs font-bold text-slate-700">Allocation Order<select x-model="allocationOrder" name="allocation_order" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3"><option value="oldest">Oldest First</option><option value="newest">Newest First</option></select></label>
                    <label class="text-xs font-bold text-slate-700">Payment Date<input x-model="paymentDate" name="payment_date" type="date" value="{{ now()->toDateString() }}" required :readonly="paymentSource === 'statement'" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3 font-mono"></label>
                    <label class="text-xs font-bold text-slate-700">Payment Method<select name="payment_method" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3"><option value="Bank">Bank</option><option value="Cash">Cash</option><option value="Online">Online</option><option value="GPay">GPay</option></select></label>
                    <label class="text-xs font-bold text-slate-700">Company Account<select x-model="companyAccountId" name="company_account_id" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3"><option value="">Optional: finalize Cashbook movement</option>@foreach($companyAccounts as $acc)<option value="{{ $acc->id }}">{{ $acc->name }} ({{ strtoupper($acc->account_type) }})</option>@endforeach</select></label>
                    <label class="text-xs font-bold text-slate-700">Reference<input x-model="reference" name="reference" type="text" :readonly="paymentSource === 'statement'" class="mt-1 min-h-10 w-full rounded-lg border border-slate-300 px-3"></label>
                </div>
                <div x-show="paymentSource === 'statement'" class="rounded-xl border border-sky-200 bg-sky-50 p-3"><label class="block text-xs font-bold text-slate-700">Existing OUT Statement Transaction<select x-model="statementEntryId" name="statement_entry_id" @change="selectStatement($event)" class="mt-1 min-h-11 w-full rounded-lg border border-sky-300 bg-white px-3 font-mono text-xs"><option value="">Select statement transaction</option>@foreach($statementTransactions as $statement)<option value="{{ $statement->id }}" data-account="{{ $statement->company_account_id }}" data-amount="{{ $statement->amount }}" data-date="{{ $statement->transaction_date?->toDateString() }}" data-reference="{{ $statement->reference }}">{{ $statement->companyAccount?->name }} | {{ $statement->transaction_date?->format('d M') }} | {{ $statement->reference ?: $statement->narration }} | OUT | ₹{{ number_format($statement->amount, 2) }}</option>@endforeach</select></label><p class="mt-1 text-[11px] font-semibold text-sky-800">Statement amount, date, account, and reference become authoritative.</p></div>
                <div x-show="difference > 0.01" class="rounded-xl border border-amber-200 bg-amber-50 p-3"><div class="font-mono text-lg font-black text-amber-900" x-text="`${rupees(difference)} remains`"></div><p class="mt-1 text-xs font-semibold text-amber-800">How should difference be handled?</p><label class="mr-4 text-xs font-bold"><input x-model="differenceTreatment" name="difference_treatment" value="outstanding" type="radio"> Keep as Vendor Outstanding</label><label class="text-xs font-bold"><input x-model="differenceTreatment" name="difference_treatment" value="discount" type="radio"> Apply as Settlement Discount</label></div>
                <input x-show="difference <= 0.01" type="hidden" name="difference_treatment" value="outstanding">
                <template x-for="row in selectedRows" :key="row.id"><input type="hidden" name="invoice_ids[]" :value="row.id"></template>
                <aside class="sticky bottom-3 z-10 rounded-xl bg-slate-900 p-4 text-white shadow-xl"><div class="grid gap-2 text-xs font-bold sm:grid-cols-4"><span>SELECTED BILLS <b class="block font-mono text-base" x-text="rupees(selectedTotal)"></b></span><span>VENDOR ADVANCE USED <b class="block font-mono text-base" x-text="rupees(advanceUsed)"></b></span><span>CASH/BANK PAYMENT <b class="block font-mono text-base" x-text="rupees(cash)"></b></span><span>SETTLEMENT DISCOUNT <b class="block font-mono text-base" x-text="rupees(discount)"></b></span><span>TOTAL SETTLED <b class="block font-mono text-base" x-text="rupees(totalSettled)"></b></span><span>REMAINING <b class="block font-mono text-base" x-text="rupees(remaining)"></b></span><span>NEW VENDOR ADVANCE <b class="block font-mono text-base" x-text="rupees(newAdvance)"></b></span></div></aside>
                <div><h3 class="mb-2 text-sm font-extrabold">How this payment will be applied</h3><div class="overflow-x-auto"><table class="w-full text-xs"><thead class="bg-slate-100 text-left"><tr><th class="p-2">Invoice</th><th class="p-2 text-right">Before</th><th class="p-2 text-right">Cash</th><th class="p-2 text-right">Advance</th><th class="p-2 text-right">Discount</th><th class="p-2 text-right">Remaining</th></tr></thead><tbody><template x-for="row in preview" :key="row.id"><tr class="border-b"><td class="p-2 font-mono" x-text="row.number"></td><td class="p-2 text-right font-mono" x-text="rupees(row.outstanding)"></td><td class="p-2 text-right font-mono" x-text="rupees(row.cash)"></td><td class="p-2 text-right font-mono" x-text="rupees(row.advance)"></td><td class="p-2 text-right font-mono" x-text="rupees(row.discount)"></td><td class="p-2 text-right font-mono" x-text="rupees(row.remaining)"></td></tr></template></tbody></table></div></div>
                <button type="submit" :disabled="selectedRows.length === 0" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white disabled:cursor-not-allowed disabled:bg-slate-400" x-text="`Confirm ${rupees(cash)} Vendor Payment`"></button>
            </form>
        </section>

        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
            <div class="mb-3"><h2 class="text-base font-extrabold text-slate-950">Settlement History</h2><p class="text-xs font-semibold text-slate-500">Cash paid, internal settlement amounts, and reconciliation state.</p></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead class="bg-slate-100 text-[10px] uppercase text-slate-500"><tr><th class="p-2">Date</th><th class="p-2">Reference</th><th class="p-2 text-right">Cash</th><th class="p-2 text-right">Discount</th><th class="p-2 text-right">Advance Used</th><th class="p-2 text-right">New Advance</th><th class="p-2">Reconciliation</th><th class="p-2">Finalized</th><th class="p-2">Action</th></tr></thead><tbody>@forelse($settlementHistory as $history)<tr class="border-b"><td class="p-2 font-mono">{{ $history->payment_date?->format('Y-m-d') }}</td><td class="p-2">{{ $history->reference ?: ('VENDOR-SETTLEMENT-'.$history->id) }}</td><td class="p-2 text-right font-mono">₹{{ number_format($history->actual_payment_amount, 2) }}</td><td class="p-2 text-right font-mono">₹{{ number_format($history->settlement_discount_amount, 2) }}</td><td class="p-2 text-right font-mono">₹{{ number_format($history->vendor_advance_used_amount, 2) }}</td><td class="p-2 text-right font-mono">₹{{ number_format($history->new_vendor_advance_amount, 2) }}</td><td class="p-2 uppercase">{{ $history->reconciliation_status }}</td><td class="p-2">{{ $history->is_finalized ? 'YES' : 'NO' }}</td><td class="p-2"><div class="flex flex-wrap gap-2">@unless($history->is_finalized)<a class="font-bold text-emerald-700 hover:underline" href="{{ route('admin.cashbook.finance.vendor-credit.settlements.show', $history) }}#reconcile">Reconcile Now</a>@endunless<a class="font-bold text-slate-700 hover:underline" href="{{ route('admin.cashbook.finance.vendor-credit.settlements.show', $history) }}">View Details</a></div></td></tr>@empty<tr><td colspan="9" class="p-4 text-center text-slate-400">No new settlement history.</td></tr>@endforelse</tbody></table></div>
        </section>

        <!-- FILTERS TOOLBAR -->
        <section class="white-card rounded-2xl border border-slate-200 p-4 shadow-sm sm:p-5">
            <form method="GET" action="{{ route('admin.cashbook.finance.vendor-credit.show', $supplier) }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-5 lg:items-end">
                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Payment Status</label>
                    <select name="status" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                        @foreach(['all' => 'All Invoices', 'unpaid' => 'Unpaid Only', 'partially_paid' => 'Partially Paid', 'paid' => 'Fully Paid'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">From Date</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">To Date</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Search Bill / Ref</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Invoice #, cart #, note..." class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white hover:bg-emerald-500">
                        <i data-lucide="filter" class="h-4 w-4"></i> Filter
                    </button>
                    @if($status !== 'all' || $startDate !== '' || $endDate !== '' || $search !== '')
                        <a href="{{ route('admin.cashbook.finance.vendor-credit.show', $supplier) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50" title="Clear Filters">
                            <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                        </a>
                    @endif
                </div>
            </form>
        </section>

        <!-- INVOICES TABLE -->
        <section id="vendor-bills" class="white-card rounded-2xl border border-slate-200 p-4 shadow-xl sm:p-5">
            <div class="mb-4 flex flex-col gap-2 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-extrabold text-slate-950">Purchase Invoices & Bills</h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">Individual credit purchase bills and direct settlement action.</p>
                </div>
                <span class="font-mono text-xs font-bold text-slate-400">{{ $invoices->total() }} invoices</span>
            </div>

            <!-- DESKTOP TABLE -->
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-100/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <th class="px-3 py-3">Date</th>
                            <th class="px-3 py-3">Invoice #</th>
                            <th class="px-3 py-3">Reference / Cart</th>
                            <th class="px-3 py-3 text-right">Gross Amount</th>
                            <th class="px-3 py-3 text-right">Discount</th>
                            <th class="px-3 py-3 text-right">Net Amount</th>
                            <th class="px-3 py-3 text-right">Paid Amount</th>
                            <th class="px-3 py-3 text-right">Outstanding</th>
                            <th class="px-3 py-3 text-center">Status</th>
                            <th class="px-3 py-3 text-center">Journal Link</th>
                            <th class="px-3 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($invoices as $inv)
                            @php
                                $net = max(0, (float) $inv->amount - (float) $inv->discount_amount);
                                $settled = (float) ($inv->vendor_settlement_allocations_sum_total_settled ?? $inv->paid_amount);
                                $due = max(0, $net - $settled);
                                $linkedJe = $journalEntries->get($inv->id);
                            @endphp
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-3 py-3 font-mono font-bold text-slate-700">
                                    {{ $inv->purchaserCart?->business_date?->format('Y-m-d') ?: $inv->created_at?->format('Y-m-d') }}
                                </td>
                                <td class="px-3 py-3 font-mono font-bold text-slate-900">
                                    {{ $inv->invoice_number ?: ('#'.$inv->id) }}
                                </td>
                                <td class="px-3 py-3 font-semibold text-slate-600">
                                    {{ $inv->purchaserCart?->bill_number ?: ($inv->purchaserCart?->cart_number ?: '—') }}
                                </td>
                                <td class="px-3 py-3 text-right font-mono text-slate-600">₹{{ number_format($inv->amount, 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono text-slate-500">₹{{ number_format($inv->discount_amount, 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-slate-900">₹{{ number_format($net, 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold text-emerald-700">₹{{ number_format($settled, 2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-extrabold {{ $due > 0 ? 'text-rose-700' : 'text-slate-400' }}">
                                    ₹{{ number_format($due, 2) }}
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if($due <= 0.01)
                                        <span class="rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 text-[9px] font-black uppercase">Paid</span>
                                    @elseif($settled > 0)
                                        <span class="rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-[9px] font-black uppercase">Partial</span>
                                    @else
                                        <span class="rounded-full bg-rose-100 text-rose-800 px-2 py-0.5 text-[9px] font-black uppercase">Unpaid</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if($linkedJe)
                                        <a href="{{ route('admin.cashbook.finance.journal.entry-show', $linkedJe->id) }}" class="font-mono text-[11px] font-extrabold text-emerald-700 hover:underline">
                                            {{ $linkedJe->formatted_reference }}
                                        </a>
                                    @else
                                        <span class="text-slate-400 text-[11px]">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right">
                                    @if($due > 0.01)
                                        <button type="button"
                                                @click="selectedInvoice = {{ json_encode(['id' => $inv->id, 'invoice_number' => $inv->invoice_number ?: ('#'.$inv->id), 'due' => $due, 'pay_url' => route('admin.cashbook.finance.vendor-credit.pay', $inv)]) }}; payAmount = {{ $due }}; showPayModal = true"
                                                class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-bold text-white shadow-sm hover:bg-emerald-500">
                                            <i data-lucide="credit-card" class="h-3.5 w-3.5"></i>
                                            <span>Settle</span>
                                        </button>
                                    @else
                                        <span class="text-emerald-700 text-xs font-bold">Settled</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-3 py-8 text-center text-sm font-bold text-slate-400">No credit invoices found for this supplier.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $invoices->links() }}
            </div>
        </section>

        <!-- SETTLE VENDOR CREDIT MODAL -->
        <div x-show="showPayModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" style="display: none;" x-cloak>
            <div @click.away="showPayModal = false" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">Settle Vendor Credit</h3>
                        <p class="text-xs text-slate-500 font-semibold" x-text="'Recording company payment for ' + (selectedInvoice ? selectedInvoice.invoice_number : '')"></p>
                    </div>
                    <button @click="showPayModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>

                <form :action="selectedInvoice ? selectedInvoice.pay_url : '#'" method="POST" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Amount to Pay (₹)</label>
                        <input type="number" step="0.01" min="0.01" :max="selectedInvoice ? selectedInvoice.due : 999999" x-model="payAmount" name="payment_amount" required class="min-h-11 w-full rounded-xl border border-slate-300 px-3 font-mono text-sm font-bold text-slate-900">
                        <div class="mt-1 flex items-center justify-between text-[11px] text-slate-500 font-semibold">
                            <span>Outstanding: <strong class="font-mono text-rose-700" x-text="'₹' + (selectedInvoice ? selectedInvoice.due.toFixed(2) : '0.00')"></strong></span>
                            <button type="button" @click="payAmount = selectedInvoice.due" class="text-emerald-700 hover:underline">Pay Full Due</button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Payment Method</label>
                        <select name="payment_method" required class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                            <option value="Bank">Bank Transfer / NEFT / RTGS</option>
                            <option value="Online">Online / Net Banking</option>
                            <option value="GPay">UPI / GPay</option>
                            <option value="Cash">Cash in Hand</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Company Account (Optional)</label>
                        <select name="company_account_id" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800">
                            <option value="">Select Account (Default)</option>
                            @foreach($companyAccounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }} ({{ strtoupper($acc->account_type) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Payment Note / Bank Ref</label>
                        <input type="text" name="payment_note" placeholder="Cheque #, UTR, transaction ref..." class="min-h-11 w-full rounded-xl border border-slate-300 px-3 text-xs font-semibold text-slate-800">
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="showPayModal = false" class="min-h-10 rounded-xl border border-slate-300 px-4 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-5 text-xs font-bold text-white shadow-sm hover:bg-emerald-500">
                            <i data-lucide="check" class="h-4 w-4"></i> Confirm Settlement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        function vendorSettlement(candidates) {
            return {
                showPayModal: false, selectedInvoice: null, payAmount: 0, cash: 0, paymentSource: 'company', statementEntryId: '', companyAccountId: '', paymentDate: '{{ now()->toDateString() }}', reference: '',
                useAdvance: false, differenceTreatment: 'outstanding', allocationOrder: 'oldest',
                rows: candidates.map(row => ({ ...row, selected: false })),
                rupees(value) { return `₹${Number(value || 0).toFixed(2)}` },
                get selectedRows() { return this.rows.filter(row => row.selected) },
                get selectedTotal() { return this.selectedRows.reduce((sum, row) => sum + Number(row.outstanding), 0) },
                get advanceUsed() { return this.useAdvance ? Math.min({{ $availableVendorAdvance }}, this.selectedTotal) : 0 },
                get cashForInvoices() { return Math.min(Number(this.cash || 0), Math.max(0, this.selectedTotal - this.advanceUsed)) },
                get difference() { return Math.max(0, this.selectedTotal - this.advanceUsed - this.cashForInvoices) },
                get discount() { return this.differenceTreatment === 'discount' ? this.difference : 0 },
                get totalSettled() { return this.cashForInvoices + this.advanceUsed + this.discount },
                get remaining() { return Math.max(0, this.selectedTotal - this.totalSettled) },
                get newAdvance() { return Math.max(0, Number(this.cash || 0) - this.cashForInvoices) },
                get preview() {
                    let cash = this.cashForInvoices; let advance = this.advanceUsed; let discount = this.discount;
                    let rows = this.allocationOrder === 'newest' ? [...this.selectedRows].reverse() : this.selectedRows;
                    return rows.map(row => { const paidCash = Math.min(cash, row.outstanding); cash -= paidCash; const afterCash = row.outstanding - paidCash; const paidAdvance = Math.min(advance, afterCash); advance -= paidAdvance; const afterAdvance = afterCash - paidAdvance; const paidDiscount = Math.min(discount, afterAdvance); discount -= paidDiscount; return { ...row, cash: paidCash, advance: paidAdvance, discount: paidDiscount, remaining: afterAdvance - paidDiscount }; });
                },
                selectVisible() { this.rows.forEach(row => { row.selected = true }) },
                clearSelection() { this.rows.forEach(row => { row.selected = false }) },
                selectStatement(event) { const option = event.target.options[event.target.selectedIndex]; if (!option || !option.value) return; this.cash = Number(option.dataset.amount); this.companyAccountId = option.dataset.account; this.paymentDate = option.dataset.date; this.reference = option.dataset.reference; },
            }
        }
    </script>
@endsection
