<x-layouts.accounting title="Finance V2 Dashboard">
    @php
        $dateParam = $date->format('Y-m-d');
        $sectionHref = fn (string $section): string => route('admin.finance-v2.green-leaf.section', ['section' => $section, 'date' => $dateParam]);
        $clientHref = fn (string $section): string => route('admin.finance-v2.aishwarya-veg.section', ['section' => $section, 'date' => $dateParam]);
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="rounded-[1.6rem] border border-slate-200 bg-slate-50 p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Green Leaf Finance</p>
                    <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Dashboard</h2>
                    <p class="mt-2 text-sm font-semibold text-slate-600">{{ $month_start->format('d M Y') }} to {{ $month_end->format('d M Y') }}. Every total opens its split.</p>
                </div>
                <a href="{{ route('admin.finance-v2.reports', ['date' => $dateParam]) }}" class="inline-flex h-11 items-center justify-center rounded-[1rem] bg-orange-500 px-5 text-xs font-black uppercase tracking-[0.16em] text-white shadow-sm transition hover:bg-orange-600">
                    Open Reports
                </a>
            </div>
        </section>

        <section class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Main Company</p>
                    <h3 class="mt-1 text-xl font-black text-slate-950">Green Leaf Account Details</h3>
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Purchase', 'value' => $green_leaf['purchase_total'], 'hint' => 'Paid Rs. '.number_format($green_leaf['purchase_paid'], 2).' | Pending Rs. '.number_format($green_leaf['purchase_pending'], 2), 'href' => $sectionHref('purchase')])
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Expense', 'value' => $green_leaf['expense_total'], 'hint' => 'All latest company expenses', 'href' => $sectionHref('expense')])
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Salary', 'value' => $green_leaf['salary_total'], 'hint' => 'Green Leaf and shop salary paid', 'href' => $sectionHref('salary')])
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Credit / Loan', 'value' => $green_leaf['loan_total'], 'hint' => 'Given to shops with details', 'href' => $sectionHref('credit-loan')])
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Company Balance', 'value' => $green_leaf['balance'], 'hint' => 'Received Rs. '.number_format($green_leaf['total_received'], 2).' | Outflow Rs. '.number_format($green_leaf['total_paid'], 2), 'href' => $sectionHref('balance')])
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1fr_0.9fr]">
            <article class="rounded-[1.6rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Client</p>
                        <h3 class="mt-1 text-xl font-black text-slate-950">Aishwarya Veg</h3>
                        <p class="mt-1 text-sm font-semibold text-slate-500">{{ number_format($client_summary['shop_count']) }} shops, all summary and split details.</p>
                    </div>
                    <a href="{{ route('admin.finance-v2.aishwarya-veg', ['date' => $dateParam]) }}" class="inline-flex h-10 items-center rounded-[1rem] border border-slate-200 px-4 text-xs font-black uppercase tracking-[0.16em] text-slate-700 hover:bg-slate-50">Open</a>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @include('admin.finance-v2.partials.metric-card', ['label' => 'Bills', 'value' => $client_summary['bills'], 'href' => $clientHref('purchase')])
                    @include('admin.finance-v2.partials.metric-card', ['label' => 'Expense', 'value' => $client_summary['expense'], 'href' => $clientHref('expense')])
                    @include('admin.finance-v2.partials.metric-card', ['label' => 'Salary', 'value' => $client_summary['salary'], 'href' => $clientHref('salary')])
                    @include('admin.finance-v2.partials.metric-card', ['label' => 'Loan', 'value' => $client_summary['loan'], 'href' => $clientHref('credit-loan')])
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Received</p>
                        <p class="mt-2 text-xl font-black text-emerald-700">Rs. {{ number_format((float) $client_summary['received'], 2) }}</p>
                    </div>
                    <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Credit</p>
                        <p class="mt-2 text-xl font-black text-cyan-700">Rs. {{ number_format((float) $client_summary['credit'], 2) }}</p>
                    </div>
                    <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Balance</p>
                        <p class="mt-2 text-xl font-black text-slate-950">Rs. {{ number_format((float) $client_summary['balance'], 2) }}</p>
                    </div>
                </div>
            </article>

            <article class="rounded-[1.6rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Approval Queue</p>
                        <h3 class="mt-1 text-xl font-black text-slate-950">Pending payment checks</h3>
                    </div>
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-black text-amber-700">{{ $pending_payments->count() }}</span>
                </div>
                <div class="mt-5 divide-y divide-slate-100">
                    @forelse($pending_payments as $row)
                        <div class="flex items-center justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black text-slate-950">{{ $row['shop']?->name ?? 'Shop' }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['method'] }} | {{ $row['date'] }}</p>
                            </div>
                            <p class="shrink-0 text-sm font-black text-slate-950">Rs. {{ number_format((float) $row['amount'], 2) }}</p>
                        </div>
                    @empty
                        <div class="rounded-[1rem] border border-dashed border-slate-300 py-8 text-center text-sm font-bold text-slate-500">No pending payments.</div>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="rounded-[1.6rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Report Page Preview</p>
                    <h3 class="mt-1 text-xl font-black text-slate-950">Total received, paid, salary, loan and balance</h3>
                </div>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-slate-50">
                        <tr>
                            @foreach(['Date', 'Total Received', 'Total Paid', 'Salary', 'Loan', 'Expense', 'Balance'] as $heading)
                                <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400 {{ $loop->first ? '' : 'text-right' }}">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($report_rows->take(7) as $row)
                            <tr>
                                <td class="px-4 py-3 text-sm font-black text-slate-950">{{ $row['label'] }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['total_received'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['total_paid'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['salary'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['loan'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['expense'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-black text-slate-950">Rs. {{ number_format((float) $row['balance'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.accounting>
