<x-layouts.app title="Vendor Daily Report">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="bg-linear-to-r from-slate-950 via-teal-950 to-emerald-900 px-6 py-7 text-white">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-[11px] font-black uppercase tracking-[0.28em] text-teal-100/80">Vendor Daily View</p>
                        <h1 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">Vendor report for {{ $date->format('d M Y') }}</h1>
                        <p class="mt-3 text-sm font-semibold leading-6 text-slate-200">Daily credit, debit, vendor due balance, and invoice-level transaction detail.</p>
                    </div>
                    <form method="GET" action="{{ route('finance.vendor-daily') }}" class="flex flex-wrap items-end gap-2 rounded-[1.5rem] border border-white/10 bg-white/10 p-3 backdrop-blur">
                        <label class="min-w-[12rem]">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-200">Business Date</span>
                            <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" class="mt-2 h-10 w-full rounded-2xl border border-white/20 bg-white px-4 text-sm font-black text-slate-950 focus:outline-none">
                        </label>
                        <button type="submit" class="inline-flex h-10 items-center rounded-2xl bg-white px-5 text-xs font-black uppercase tracking-[0.18em] text-slate-950 transition hover:bg-teal-50">
                            Apply
                        </button>
                        <a href="{{ route('finance.index', ['start_date' => $date->format('Y-m-d'), 'end_date' => $date->format('Y-m-d')]) }}" class="inline-flex h-10 items-center rounded-2xl border border-white/20 px-5 text-xs font-black uppercase tracking-[0.18em] text-white transition hover:bg-white/10">
                            Back
                        </a>
                    </form>
                </div>
            </div>
        </section>

        <section class="flex flex-wrap items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-slate-200 shadow-xs print:hidden">
            <x-export-toolbar
                excel-url="{{ route('finance.vendor-daily.excel', ['date' => $date->format('Y-m-d')]) }}"
                pdf-url="{{ route('finance.vendor-daily.pdf', ['date' => $date->format('Y-m-d')]) }}"
                title="Vendor Daily Report ({{ $date->format('d M Y') }})"
                align="between"
            />
        </section>

        <section class="grid gap-4 md:grid-cols-4">
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Vendors</p>
                <p class="mt-2 text-3xl font-black text-slate-950">{{ number_format($report['summary']['count']) }}</p>
            </div>
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Credit</p>
                <p class="mt-2 text-3xl font-black text-slate-950">Rs. {{ number_format($report['summary']['total_amount'], 2) }}</p>
            </div>
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Debit</p>
                <p class="mt-2 text-3xl font-black text-slate-950">Rs. {{ number_format($report['summary']['paid_amount'], 2) }}</p>
            </div>
            <div class="rounded-[1.5rem] border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-amber-700">Balance</p>
                <p class="mt-2 text-3xl font-black text-amber-900">Rs. {{ number_format($report['summary']['outstanding_amount'], 2) }}</p>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[1.1fr_1.3fr]">
            <article class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                    <h2 class="text-lg font-black text-slate-950">Vendor Summary</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Vendor</th>
                                <th class="px-4 py-3 text-right">Credit</th>
                                <th class="px-4 py-3 text-right">Debit</th>
                                <th class="px-4 py-3 text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse ($report['vendor_rows'] as $row)
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-black text-slate-950">{{ $row['vendor']?->name ?? 'Vendor pending' }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['invoice_count'] }} bill(s)</p>
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['total_amount'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['paid_amount'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black {{ $row['outstanding_amount'] > 0 ? 'text-amber-700' : 'text-emerald-700' }}">Rs. {{ number_format($row['outstanding_amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center font-bold text-slate-500">No vendor summary rows.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                    <h2 class="text-lg font-black text-slate-950">Invoice Transactions</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Invoice</th>
                                <th class="px-4 py-3">Vendor</th>
                                <th class="px-4 py-3 text-right">Credit</th>
                                <th class="px-4 py-3 text-right">Debit</th>
                                <th class="px-4 py-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse ($report['invoices'] as $invoice)
                                @php
                                    $balance = max(0, round((float) $invoice->amount - (float) $invoice->paid_amount, 2));
                                @endphp
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-black text-slate-950">{{ $invoice->invoice_number }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $invoice->created_at->format('d M Y h:i A') }}</p>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-slate-700">{{ $invoice->supplier?->name ?? 'Vendor pending' }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $invoice->amount, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $invoice->paid_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="inline-flex rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $balance > 0 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                                            {{ $balance > 0 ? 'Due' : 'Settled' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center font-bold text-slate-500">No vendor invoice detail rows.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </div>
</x-layouts.app>
