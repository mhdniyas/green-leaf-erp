<x-layouts.accounting title="Category Wise Report">
    @php
        $startDateValue = $startDate->toDateString();
        $endDateValue = $endDate->toDateString();
        $reportSections = [
            'income' => [
                'title' => 'Income Categories',
                'rows' => $incomeRows,
                'total' => $summary['income_total'],
                'tone' => 'emerald',
                'empty' => 'No income categories found for this period.',
            ],
            'expense' => [
                'title' => 'Expense Categories',
                'rows' => $expenseRows,
                'total' => $summary['expense_total'],
                'tone' => 'rose',
                'empty' => 'No expense categories found for this period.',
            ],
        ];
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-5 border-b border-slate-200 bg-slate-950 px-4 py-5 text-white sm:px-6 lg:flex-row lg:items-end lg:justify-between lg:px-7">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-emerald-300">Owned Shop Cashbook</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight sm:text-4xl">Category Wise Report</h1>
                    <p class="mt-3 text-sm font-semibold text-slate-300">{{ $startDate->format('d M Y') }} to {{ $endDate->format('d M Y') }}</p>
                </div>

                <form method="GET" action="{{ route('admin.accounting.clients.category-report') }}" class="grid gap-3 rounded-xl border border-white/10 bg-white/10 p-3 sm:grid-cols-[minmax(0,11rem)_minmax(0,11rem)_auto]">
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

            <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4 lg:p-6">
                <article class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Income Total</p>
                    <p class="mt-3 text-2xl font-black text-emerald-950">Rs. {{ number_format($summary['income_total'], 2) }}</p>
                    <p class="mt-1 text-xs font-bold text-emerald-700">{{ number_format($incomeRows->count()) }} category row(s)</p>
                </article>
                <article class="rounded-lg border border-rose-200 bg-rose-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">Expense Total</p>
                    <p class="mt-3 text-2xl font-black text-rose-950">Rs. {{ number_format($summary['expense_total'], 2) }}</p>
                    <p class="mt-1 text-xs font-bold text-rose-700">{{ number_format($expenseRows->count()) }} category row(s)</p>
                </article>
                <article class="rounded-lg border border-sky-200 bg-sky-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-sky-700">Net</p>
                    <p class="mt-3 text-2xl font-black {{ $summary['net_total'] >= 0 ? 'text-sky-950' : 'text-rose-800' }}">Rs. {{ number_format($summary['net_total'], 2) }}</p>
                    <p class="mt-1 text-xs font-bold text-sky-700">Income minus expense</p>
                </article>
                <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Scope</p>
                    <p class="mt-3 text-2xl font-black text-slate-950">{{ number_format($summary['shop_count']) }}</p>
                    <p class="mt-1 text-xs font-bold text-slate-500">Owned shop(s), submitted/recheck/approved only</p>
                </article>
            </div>
        </section>

        @foreach ($reportSections as $section)
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h2 class="text-xl font-black text-slate-950">{{ $section['title'] }}</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Grouped by category name across owned shops.</p>
                    </div>
                    <p @class([
                        'text-lg font-black',
                        'text-emerald-700' => $section['tone'] === 'emerald',
                        'text-rose-700' => $section['tone'] === 'rose',
                    ])>
                        Rs. {{ number_format($section['total'], 2) }}
                    </p>
                </div>

                <div class="space-y-3 p-4 md:hidden">
                    @forelse ($section['rows'] as $row)
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black text-slate-950">{{ $row['category_name'] }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['purpose'] ? str($row['purpose'])->replace('_', ' ')->title() : 'General' }}</p>
                                </div>
                                <p class="text-right text-sm font-black text-slate-950">Rs. {{ number_format($row['total_amount'], 2) }}</p>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-2 text-xs font-bold">
                                <p class="rounded-lg bg-white p-3 text-slate-600">Approved<br><span class="text-emerald-700">Rs. {{ number_format($row['approved_amount'], 2) }}</span></p>
                                <p class="rounded-lg bg-white p-3 text-slate-600">Submitted<br><span class="text-amber-700">Rs. {{ number_format($row['submitted_amount'], 2) }}</span></p>
                                <p class="rounded-lg bg-white p-3 text-slate-600">Recheck<br><span class="text-rose-700">Rs. {{ number_format($row['recheck_amount'], 2) }}</span></p>
                                <p class="rounded-lg bg-white p-3 text-slate-600">Activity<br><span class="text-slate-950">{{ number_format($row['shop_count']) }} shops / {{ number_format($row['line_count']) }} lines</span></p>
                            </div>
                        </article>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-bold text-slate-500">{{ $section['empty'] }}</p>
                    @endforelse
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.14em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Purpose</th>
                                <th class="px-4 py-3 text-right">Approved</th>
                                <th class="px-4 py-3 text-right">Submitted</th>
                                <th class="px-4 py-3 text-right">Recheck</th>
                                <th class="px-4 py-3 text-right">Shops</th>
                                <th class="px-4 py-3 text-right">Lines</th>
                                <th class="px-4 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($section['rows'] as $row)
                                <tr>
                                    <td class="px-4 py-4 font-black text-slate-950">{{ $row['category_name'] }}</td>
                                    <td class="px-4 py-4 font-semibold text-slate-500">{{ $row['purpose'] ? str($row['purpose'])->replace('_', ' ')->title() : 'General' }}</td>
                                    <td class="px-4 py-4 text-right font-black text-emerald-700">Rs. {{ number_format($row['approved_amount'], 2) }}</td>
                                    <td class="px-4 py-4 text-right font-black text-amber-700">Rs. {{ number_format($row['submitted_amount'], 2) }}</td>
                                    <td class="px-4 py-4 text-right font-black text-rose-700">Rs. {{ number_format($row['recheck_amount'], 2) }}</td>
                                    <td class="px-4 py-4 text-right font-semibold text-slate-600">{{ number_format($row['shop_count']) }}</td>
                                    <td class="px-4 py-4 text-right font-semibold text-slate-600">{{ number_format($row['line_count']) }}</td>
                                    <td class="px-4 py-4 text-right font-black text-slate-950">Rs. {{ number_format($row['total_amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-12 text-center text-sm font-bold text-slate-500">{{ $section['empty'] }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.accounting>
