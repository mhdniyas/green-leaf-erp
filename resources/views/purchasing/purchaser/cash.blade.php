<x-layouts.app title="Purchaser Cash">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Purchaser Cash</p>
                    <h1 class="mt-1 text-xl font-black text-slate-950">Cash in & out</h1>
                    <p class="mt-1 text-xs font-semibold text-slate-600">Read-only purchaser ledger for cash given in and invoice out flow.</p>
                </div>
                <a href="{{ route('purchaser.daily') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-xs font-black text-slate-700 hover:bg-white">
                    Back To Daily
                </a>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Cash In</p>
                <p class="mt-2 text-2xl font-black text-emerald-950">₹{{ number_format($totalIn, 2) }}</p>
                <p class="mt-1 text-xs font-semibold text-slate-500">Funds received from Green Leaf</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">Cash Out</p>
                <p class="mt-2 text-2xl font-black text-rose-950">₹{{ number_format($totalOut, 2) }}</p>
                <p class="mt-1 text-xs font-semibold text-slate-500">Purchases submitted against invoices</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Balance</p>
                <p class="mt-2 text-2xl font-black {{ $balance >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">₹{{ number_format($balance, 2) }}</p>
                <p class="mt-1 text-xs font-semibold text-slate-500">Current purchaser cash balance</p>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-black text-slate-950">Cash ledger</h2>
                    <p class="mt-1 text-xs font-semibold text-slate-500">All in/out entries recorded for this purchaser account.</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black text-slate-700">{{ $credits->count() }}</span>
            </div>

            <div class="mt-4 space-y-3 lg:hidden">
                @forelse ($credits as $credit)
                    <article class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-black text-slate-950">{{ $credit->business_date?->format('d M Y') }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] {{ $credit->type === 'in' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                        {{ strtoupper($credit->type) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-[11px] font-semibold text-slate-600">{{ $credit->description }}</p>
                                <p class="mt-1 text-[11px] font-semibold text-slate-500">{{ $credit->creator?->name ?: 'System' }}</p>
                                @if ($credit->purchaseInvoice)
                                    <a href="{{ route('purchaser.invoices.show', $credit->purchaseInvoice) }}" class="mt-2 inline-flex items-center gap-1 text-[11px] font-black text-cyan-700 hover:underline">
                                        Invoice: {{ $credit->purchaseInvoice->invoice_number }}
                                    </a>
                                @endif
                            </div>
                            <p class="text-sm font-black {{ $credit->type === 'in' ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $credit->type === 'in' ? '+' : '-' }}₹{{ number_format((float) $credit->amount, 2) }}
                            </p>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm font-bold text-slate-500">
                        No purchaser cash entries recorded yet.
                    </div>
                @endforelse
            </div>

            <div class="mt-4 hidden overflow-hidden rounded-2xl border border-slate-200 lg:block">
                <div class="grid grid-cols-[140px_90px_minmax(0,1.5fr)_120px_160px] gap-0 bg-slate-950 px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-300">
                    <div>Date</div>
                    <div>Type</div>
                    <div>Description</div>
                    <div class="text-right">Amount</div>
                    <div class="text-right">Created By</div>
                </div>
                @forelse ($credits as $credit)
                    <div class="grid grid-cols-[140px_90px_minmax(0,1.5fr)_120px_160px] items-center gap-0 border-t border-slate-200 bg-white px-4 py-3 text-sm">
                        <div class="font-black text-slate-950">{{ $credit->business_date?->format('d M Y') }}</div>
                        <div>
                            <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] {{ $credit->type === 'in' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                {{ strtoupper($credit->type) }}
                            </span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-700">{{ $credit->description }}</p>
                            @if ($credit->purchaseInvoice)
                                <a href="{{ route('purchaser.invoices.show', $credit->purchaseInvoice) }}" class="mt-1 inline-flex items-center gap-1 text-[11px] font-black text-cyan-700 hover:underline">
                                    Invoice: {{ $credit->purchaseInvoice->invoice_number }}
                                </a>
                            @endif
                        </div>
                        <div class="text-right font-black {{ $credit->type === 'in' ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ $credit->type === 'in' ? '+' : '-' }}₹{{ number_format((float) $credit->amount, 2) }}
                        </div>
                        <div class="text-right font-semibold text-slate-500">{{ $credit->creator?->name ?: 'System' }}</div>
                    </div>
                @empty
                    <div class="bg-white px-4 py-12 text-center text-sm font-bold text-slate-500">
                        No purchaser cash entries recorded yet.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
