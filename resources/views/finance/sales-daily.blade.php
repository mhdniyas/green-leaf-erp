<x-layouts.app title="Sales Daily Report">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="bg-linear-to-r from-slate-950 via-sky-950 to-cyan-900 px-6 py-7 text-white">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-[11px] font-black uppercase tracking-[0.28em] text-sky-100/80">Sales Daily View</p>
                        <h1 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">Sales report for {{ $date->format('d M Y') }}</h1>
                        <p class="mt-3 text-sm font-semibold leading-6 text-slate-200">Single-table daily sales reporting with a clean filter for pending or settled invoices.</p>
                    </div>
                    <form method="GET" action="{{ route('finance.sales-daily') }}" class="flex flex-wrap items-end gap-2 rounded-[1.5rem] border border-white/10 bg-white/10 p-3 backdrop-blur">
                        <label class="min-w-[12rem]">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-200">Business Date</span>
                            <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" class="mt-2 h-10 w-full rounded-2xl border border-white/20 bg-white px-4 text-sm font-black text-slate-950 focus:outline-none">
                        </label>
                        <label class="min-w-[11rem]">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-200">Filter</span>
                            <select name="status" class="mt-2 h-10 w-full rounded-2xl border border-white/20 bg-white px-4 text-sm font-black text-slate-950 focus:outline-none">
                                <option value="all" @selected(($statusFilter ?? 'all') === 'all')>All</option>
                                <option value="pending" @selected(($statusFilter ?? 'all') === 'pending')>Pending</option>
                                <option value="settled" @selected(($statusFilter ?? 'all') === 'settled')>Settled</option>
                            </select>
                        </label>
                        <button type="submit" class="inline-flex h-10 items-center rounded-2xl bg-white px-5 text-xs font-black uppercase tracking-[0.18em] text-slate-950 transition hover:bg-sky-50">
                            Apply
                        </button>
                        <a href="{{ route('finance.index', ['start_date' => $date->format('Y-m-d'), 'end_date' => $date->format('Y-m-d')]) }}" class="inline-flex h-10 items-center rounded-2xl border border-white/20 px-5 text-xs font-black uppercase tracking-[0.18em] text-white transition hover:bg-white/10">
                            Back
                        </a>
                    </form>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-4">
            <div class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Shops</p>
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
            <div class="rounded-[1.5rem] border border-rose-200 bg-rose-50 p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-rose-700">Balance</p>
                <p class="mt-2 text-3xl font-black text-rose-900">Rs. {{ number_format($report['summary']['outstanding_amount'], 2) }}</p>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[1.1fr_1.3fr]">
            <article class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                    <h2 class="text-lg font-black text-slate-950">Shop Summary</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Shop</th>
                                <th class="px-4 py-3 text-right">Credit</th>
                                <th class="px-4 py-3 text-right">Debit</th>
                                <th class="px-4 py-3 text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse ($report['shop_rows'] as $row)
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-black text-slate-950">{{ $row['shop']?->name ?? 'Shop pending' }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['invoice_count'] }} invoice(s)</p>
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['total_amount'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['paid_amount'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black {{ $row['outstanding_amount'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">Rs. {{ number_format($row['outstanding_amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center font-bold text-slate-500">No shop summary rows.</td>
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
                                <th class="px-4 py-3">Shop</th>
                                <th class="px-4 py-3 text-right">Credit</th>
                                <th class="px-4 py-3 text-right">Debit</th>
                                <th class="px-4 py-3 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse ($report['invoices'] as $invoice)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-black text-slate-950">{{ $invoice->invoice_number }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ \Illuminate\Support\Carbon::parse((string) $invoice->business_date)->format('d M Y') }}</p>
                                </td>
                                    <td class="px-4 py-3 font-semibold text-slate-700">{{ $invoice->shop?->name ?? 'Shop pending' }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $invoice->paid_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="inline-flex rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ (float) $invoice->balance_amount > 0 ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">
                                            {{ (float) $invoice->balance_amount > 0 ? 'Pending' : 'Settled' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center font-bold text-slate-500">No sales invoice detail rows for the selected filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </div>
</x-layouts.app>
