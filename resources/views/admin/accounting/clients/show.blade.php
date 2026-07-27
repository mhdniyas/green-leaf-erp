<x-layouts.accounting title="{{ $client->name }} Daily Report">
    @php
        $startDateValue = $startDate->toDateString();
        $endDateValue = $endDate->toDateString();
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-5 border-b border-slate-200 bg-slate-950 px-5 py-6 text-white lg:flex-row lg:items-end lg:justify-between lg:px-7">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-emerald-300">Client Daily Report</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">{{ $client->name }} Daily Report</h1>
                    <p class="mt-3 text-sm font-semibold text-slate-300">{{ $startDate->format('d M Y') }} to {{ $endDate->format('d M Y') }}</p>
                </div>

                <form method="GET" action="{{ route('admin.accounting.clients.show', $client) }}" class="grid gap-3 rounded-xl border border-white/10 bg-white/10 p-3 sm:grid-cols-[minmax(0,11rem)_minmax(0,11rem)_auto]">
                    <label>
                        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-300">From</span>
                        <input type="date" name="start_date" value="{{ $startDateValue }}" class="mt-2 h-11 w-full rounded-lg border border-white/10 bg-white px-3 text-sm font-black text-slate-950">
                    </label>
                    <label>
                        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-slate-300">To</span>
                        <input type="date" name="end_date" value="{{ $endDateValue }}" class="mt-2 h-11 w-full rounded-lg border border-white/10 bg-white px-3 text-sm font-black text-slate-950">
                    </label>
                    <button type="submit" class="mt-5 inline-flex h-11 items-center justify-center rounded-lg bg-white px-5 text-xs font-black uppercase tracking-[0.16em] text-slate-950 transition hover:bg-emerald-50">
                        Apply
                    </button>
                </form>
            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-5 lg:p-7">
                <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Shops</p>
                    <p class="mt-3 text-2xl font-black text-slate-950">{{ number_format($summary['shop_count']) }}</p>
                    <p class="mt-1 text-xs font-bold text-slate-500">{{ number_format($summary['invoice_count']) }} invoice(s)</p>
                </article>
                <article class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Invoice Collected</p>
                    <p class="mt-3 text-2xl font-black text-emerald-900">Rs. {{ number_format($summary['invoice_collected'], 2) }}</p>
                    <p class="mt-1 text-xs font-bold text-emerald-700">Only shop invoice collections</p>
                </article>
                <article class="rounded-lg border border-rose-200 bg-rose-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">Invoice Pending</p>
                    <p class="mt-3 text-2xl font-black text-rose-900">Rs. {{ number_format($summary['invoice_pending'], 2) }}</p>
                    <p class="mt-1 text-xs font-bold text-rose-700">Balance to collect</p>
                </article>
                <article class="rounded-lg border border-cyan-200 bg-cyan-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">Loan Given</p>
                    <p class="mt-3 text-2xl font-black text-cyan-950">Rs. {{ number_format($summary['loan_given'], 2) }}</p>
                    <p class="mt-1 text-xs font-bold text-cyan-700">A + B category total</p>
                </article>
                <article class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Total Expense</p>
                    <p class="mt-3 text-2xl font-black text-amber-950">Rs. {{ number_format($summary['expense_total'], 2) }}</p>
                    <p class="mt-1 text-xs font-bold text-amber-700">From shop daily entries</p>
                </article>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Shop Daily Table</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">Daily collection, expense, loan, and balance</h2>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($clientOptions as $clientOption)
                        <a href="{{ route('admin.accounting.clients.show', ['client' => $clientOption, 'start_date' => $startDateValue, 'end_date' => $endDateValue]) }}" @class([
                            'rounded-lg border px-3 py-2 text-xs font-black uppercase tracking-[0.12em] transition',
                            'border-emerald-200 bg-emerald-50 text-emerald-800' => $clientOption->id === $client->id,
                            'border-slate-200 bg-white text-slate-700 hover:bg-slate-100' => $clientOption->id !== $client->id,
                        ])>
                            {{ $clientOption->name }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.14em] text-slate-200">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Shop</th>
                            <th class="px-4 py-3 text-right">Invoice Collected</th>
                            <th class="px-4 py-3 text-right">Invoice Pending</th>
                            <th class="px-4 py-3 text-right">Total Expense</th>
                            <th class="px-4 py-3 text-right">{{ $loanCategoryLabels['primary'] }}</th>
                            <th class="px-4 py-3 text-right">{{ $loanCategoryLabels['salary_advance'] }}</th>
                            <th class="px-4 py-3 text-right">Loan Total</th>
                            <th class="px-4 py-3 text-right">Opening Balance</th>
                            <th class="px-4 py-3 text-right">Closing Balance</th>
                            <th class="px-4 py-3 text-right">Open</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($dailyRows as $row)
                            <tr class="align-top transition hover:bg-slate-50">
                                <td class="px-4 py-4 font-black text-slate-950">{{ $row['date']->format('d M Y') }}</td>
                                <td class="px-4 py-4">
                                    <p class="font-black text-slate-950">{{ $row['shop']->name }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['shop']->code }}</p>
                                </td>
                                <td class="px-4 py-4 text-right font-black text-emerald-700">Rs. {{ number_format($row['invoice_collected'], 2) }}</td>
                                <td class="px-4 py-4 text-right font-black {{ $row['invoice_pending'] > 0 ? 'text-rose-700' : 'text-slate-500' }}">Rs. {{ number_format($row['invoice_pending'], 2) }}</td>
                                <td class="px-4 py-4 text-right font-black text-slate-950">Rs. {{ number_format($row['expense_total'], 2) }}</td>
                                <td class="px-4 py-4 text-right font-black text-cyan-800">Rs. {{ number_format($row['loan_primary'], 2) }}</td>
                                <td class="px-4 py-4 text-right font-black text-cyan-800">Rs. {{ number_format($row['loan_salary_advance'], 2) }}</td>
                                <td class="px-4 py-4 text-right font-black text-cyan-950">Rs. {{ number_format($row['loan_total'], 2) }}</td>
                                <td class="px-4 py-4 text-right font-black text-slate-700">Rs. {{ number_format($row['opening_balance'], 2) }}</td>
                                <td class="px-4 py-4 text-right font-black text-slate-950">Rs. {{ number_format($row['closing_balance'], 2) }}</td>
                                <td class="px-4 py-4 text-right">
                                    <a href="{{ route('admin.accounting.owned-shops.show', ['shop' => $row['shop'], 'date' => $row['date']->toDateString()]) }}" class="inline-flex h-9 items-center rounded-lg border border-slate-200 px-3 text-xs font-black uppercase tracking-[0.12em] text-slate-700 transition hover:bg-slate-100">
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-12 text-center text-sm font-bold text-slate-500">No shop daily activity found for this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.accounting>
