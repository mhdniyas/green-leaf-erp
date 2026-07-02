<x-layouts.accounting title="Vendor Reports">
    @php
        $previousDate = $date->copy()->subDay()->format('Y-m-d');
        $nextDate = $date->copy()->addDay()->format('Y-m-d');
        $todayDate = today()->toDateString();
        $summary = $report['summary'];
        $vendorRows = $report['vendor_rows'];
        $invoices = $report['invoices'];
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-[linear-gradient(135deg,_#0f172a,_#134e4a_55%,_#115e59)] text-white shadow-[0_30px_90px_rgba(15,23,42,0.18)]">
            <div class="flex flex-col gap-6 px-5 py-6 lg:flex-row lg:items-end lg:justify-between lg:px-7">
                <div class="max-w-3xl">
                    <p class="text-[11px] font-black uppercase tracking-[0.28em] text-teal-100">Vendor Reports</p>
                    <h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Accounting-owned vendor cash flow workspace.</h2>
                    <p class="mt-3 max-w-2xl text-sm font-semibold leading-6 text-slate-200">Supplier balances and invoice cash movement stay inside accounting now. No jump back to the old purchasing page.</p>
                </div>

                <form method="GET" action="{{ route('admin.accounting.vendor-reports') }}" class="flex flex-wrap items-center gap-2 rounded-[1.5rem] border border-white/15 bg-white/10 p-2 backdrop-blur">
                    <a href="{{ route('admin.accounting.vendor-reports', ['date' => $previousDate]) }}" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-white transition hover:bg-white/20" title="Previous day">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </a>
                    <label class="min-w-[12rem] rounded-2xl border border-white/15 bg-white px-4 py-2 text-slate-900 shadow-sm">
                        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Business Date</span>
                        <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" onchange="this.form.submit()" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black text-slate-900 focus:outline-none focus:ring-0">
                    </label>
                    @if($date->format('Y-m-d') !== $todayDate)
                        <a href="{{ route('admin.accounting.vendor-reports', ['date' => $todayDate]) }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-white px-4 text-xs font-black uppercase tracking-[0.18em] text-slate-950 transition hover:bg-slate-100">
                            Today
                        </a>
                    @endif
                    <a href="{{ route('admin.accounting.vendor-reports', ['date' => $nextDate]) }}" class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-white transition hover:bg-white/20" title="Next day">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </form>
            </div>

            <div class="grid gap-4 border-t border-white/10 px-5 py-5 md:grid-cols-2 xl:grid-cols-4 lg:px-7">
                <article class="rounded-[1.5rem] border border-white/10 bg-white/8 p-5 backdrop-blur">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-teal-100/80">Suppliers</p>
                    <p class="mt-3 text-3xl font-black tracking-tight text-white">{{ number_format($summary['count']) }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-200">{{ number_format($summary['invoice_count']) }} invoice(s)</p>
                </article>
                <article class="rounded-[1.5rem] border border-white/10 bg-white/8 p-5 backdrop-blur">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-teal-100/80">Credit</p>
                    <p class="mt-3 text-3xl font-black tracking-tight text-white">Rs. {{ number_format($summary['total_amount'], 2) }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-200">Total vendor billing</p>
                </article>
                <article class="rounded-[1.5rem] border border-white/10 bg-white/8 p-5 backdrop-blur">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-teal-100/80">Debit</p>
                    <p class="mt-3 text-3xl font-black tracking-tight text-white">Rs. {{ number_format($summary['paid_amount'], 2) }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-200">Cash paid to suppliers</p>
                </article>
                <article class="rounded-[1.5rem] border border-white/10 bg-white/8 p-5 backdrop-blur">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-teal-100/80">Balance</p>
                    <p class="mt-3 text-3xl font-black tracking-tight text-white">Rs. {{ number_format($summary['outstanding_amount'], 2) }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-200">Outstanding vendor due</p>
                </article>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <article class="rounded-[1.9rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="border-b border-slate-100 pb-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Vendor Cash Flow</p>
                    <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Vendor ledger by supplier</h3>
                </div>

                <div class="mt-5 overflow-x-auto rounded-[1.5rem] border border-slate-200">
                    <table class="min-w-full text-left">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Vendor</th>
                                <th class="px-4 py-3 text-right">Credit</th>
                                <th class="px-4 py-3 text-right">Debit</th>
                                <th class="px-4 py-3 text-right">Balance</th>
                                <th class="px-4 py-3 text-right">Bills</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse($vendorRows as $row)
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-black text-slate-950">{{ $row['vendor']?->name ?? 'Vendor pending' }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['status'] }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['total_amount'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-emerald-700">Rs. {{ number_format($row['paid_amount'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black {{ $row['outstanding_amount'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">Rs. {{ number_format($row['outstanding_amount'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-600">{{ number_format($row['invoice_count']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-sm font-bold text-slate-500">
                                        No vendor rows are available for {{ $date->format('d M Y') }}.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="rounded-[1.9rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="border-b border-slate-100 pb-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Vendor Invoices</p>
                    <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Invoice list for {{ $date->format('d M Y') }}</h3>
                </div>

                <div class="mt-5 overflow-x-auto rounded-[1.5rem] border border-slate-200">
                    <table class="min-w-full text-left">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Invoice</th>
                                <th class="px-4 py-3">Vendor</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                                <th class="px-4 py-3 text-right">Paid</th>
                                <th class="px-4 py-3 text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse($invoices as $invoice)
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-black text-slate-950">{{ $invoice->invoice_number ?: 'Invoice pending' }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $invoice->created_at?->format('d M Y h:i A') }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-black text-slate-950">{{ $invoice->supplier?->name ?? 'Vendor pending' }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $invoice->payment_status ?: 'Payment pending' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $invoice->amount, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-emerald-700">Rs. {{ number_format((float) $invoice->paid_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black {{ ((float) $invoice->amount - (float) $invoice->paid_amount) > 0 ? 'text-amber-700' : 'text-emerald-700' }}">Rs. {{ number_format(max(0, (float) $invoice->amount - (float) $invoice->paid_amount), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-sm font-bold text-slate-500">
                                        No vendor invoices are available for {{ $date->format('d M Y') }}.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </div>
</x-layouts.accounting>
