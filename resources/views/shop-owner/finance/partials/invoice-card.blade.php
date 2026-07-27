<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Invoice Reference</p>
            <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $invoice->invoice_number }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ $invoice->business_date->format('d F Y') }} · {{ $invoice->shop?->name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('shop-owner.finance.pdf', $invoice) }}" target="_blank" class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.14em] text-slate-700 transition hover:border-emerald-200 hover:text-emerald-700">
                Print / PDF
            </a>
            @include('shop-owner.finance.partials.payment-status-badge', ['invoice' => $invoice])
            @include('shop-owner.components.status-badge', [
                'label' => $invoice->delivery_status === 'awaiting_review' ? 'Awaiting Admin Review' : str($invoice->delivery_status)->replace('_', ' ')->title(),
                'tone' => in_array($invoice->delivery_status, ['received_full', 'approved_after_discrepancy'], true) ? 'success' : (in_array($invoice->delivery_status, ['received_with_discrepancy', 'awaiting_review'], true) ? 'warning' : 'neutral'),
            ])
        </div>
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-5">
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Subtotal</p>
            <p class="mt-2 text-2xl font-black text-slate-900">Rs. {{ number_format((float) $invoice->subtotal, 2) }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Shortage</p>
            <p class="mt-2 text-2xl font-black text-amber-600">Rs. {{ number_format((float) $invoice->shortage_total, 2) }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Excess</p>
            <p class="mt-2 text-2xl font-black text-cyan-700">Rs. {{ number_format((float) $invoice->excess_total, 2) }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Discount</p>
            <p class="mt-2 text-2xl font-black text-indigo-700">Rs. {{ number_format((float) $invoice->discount_total, 2) }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Final Total</p>
            <p class="mt-2 text-2xl font-black text-emerald-700">Rs. {{ number_format((float) $invoice->final_total, 2) }}</p>
        </div>
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Paid Amount</p>
            <p class="mt-2 text-2xl font-black text-emerald-700">Rs. {{ number_format((float) $invoice->paid_amount, 2) }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Balance</p>
            <p class="mt-2 text-2xl font-black text-red-600">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</p>
        </div>
    </div>
</section>
