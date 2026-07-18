@php
    /** @var \App\Models\ShopInvoice|null $invoice */
    $invoice = $invoice ?? null;
    $context = $context ?? 'finance';
    $latestRequest = $invoice?->paymentRequests->first();
    $hasPendingRequest = $latestRequest && $latestRequest->status === 'pending';
    $fullDue = $invoice ? number_format((float) $invoice->balance_amount, 2, '.', '') : '0.00';
    $halfDue = $invoice ? number_format(round((float) $invoice->balance_amount / 2, 2), 2, '.', '') : '0.00';
    $ownedPaymentAccess = $invoice?->shop?->isOwnedAccountingEnabled() ?? false;
@endphp

<section data-payment-request-panel class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Payment Request</p>
            <h2 class="mt-2 text-xl font-black text-slate-950">Send paid amount for approval</h2>
            <p class="mt-2 text-sm text-slate-600">Any paid amount update from shop owner must be approved by accounting before the invoice payment and journal change.</p>
        </div>
        @if ($invoice)
            <div class="grid grid-cols-2 gap-3 lg:min-w-[260px]">
                <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Paid Now</p>
                    <p class="mt-1 text-sm font-black text-emerald-700">Rs. {{ number_format((float) $invoice->paid_amount, 2) }}</p>
                </div>
                <div class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-3">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Balance Due</p>
                    <p class="mt-1 text-sm font-black text-rose-700">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</p>
                </div>
            </div>
        @endif
    </div>

    @if (! $invoice)
        <div class="mt-5 rounded-[1.5rem] border border-amber-200 bg-amber-50 p-4">
            <p class="text-sm font-black text-amber-900">Invoice not generated yet.</p>
            <p class="mt-2 text-sm text-amber-800">Finish delivery review first. Payment approval options will appear after the shop invoice is ready.</p>
        </div>
    @elseif ((float) $invoice->balance_amount <= 0)
        <div class="mt-5 rounded-[1.5rem] border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-sm font-black text-emerald-800">This invoice is already fully settled.</p>
        </div>
    @elseif ($ownedPaymentAccess)
        <div class="mt-5 rounded-[1.5rem] border border-cyan-200 bg-cyan-50 p-4">
            <p class="text-sm font-black text-cyan-900">This bill is handled through the cashbook.</p>
            <p class="mt-2 text-sm font-semibold text-cyan-800">Once approved, the bill amount is automatically included as Cash Debit. Use Finance > Payments only when paying the company from the latest closing balance.</p>
            <a href="{{ route('shop-owner.finance.index', ['tab' => 'payments']) }}" class="mt-4 inline-flex h-10 items-center rounded-2xl bg-cyan-700 px-4 text-sm font-black text-white transition hover:bg-cyan-600">
                Open Payments
            </a>
        </div>
    @elseif ($hasPendingRequest)
        <div class="mt-5 rounded-[1.5rem] border border-amber-200 bg-amber-50 p-4">
            <p class="text-sm font-black text-amber-900">Payment request already waiting for approval.</p>
            <p class="mt-2 text-sm font-semibold text-amber-800">Accounting must approve or reject it before another payment request can be submitted for this invoice.</p>
        </div>
    @else
        <form method="POST" action="{{ route('shop-owner.accounting.payment-requests.store') }}" class="mt-5 space-y-4 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
            @csrf
            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
            <div class="grid gap-4 md:grid-cols-[180px_minmax(0,1fr)_minmax(0,1fr)]">
                <label class="block">
                    <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Mode</span>
                    <select name="amount_mode" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900">
                        <option value="balance_due" @selected(old('amount_mode') === 'balance_due')>Full balance</option>
                        <option value="custom" @selected(old('amount_mode') === 'custom')>Custom</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Amount</span>
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $fullDue) }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Note</span>
                    <input type="text" name="shop_note" value="{{ old('shop_note') }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900" placeholder="Payment reference or note">
                </label>
            </div>
            <button type="submit" class="inline-flex h-11 w-full items-center justify-center rounded-2xl bg-emerald-600 px-5 text-sm font-black text-white transition hover:bg-emerald-500 sm:w-auto">
                Submit Payment Request
            </button>
        </form>
    @endif
</section>
