<x-layouts.accounting title="Finance V2 Dashboard">
    @php
        $dateParam = $date->format('Y-m-d');
        $sectionHref = fn (string $section): string => route('admin.finance-v2.green-leaf.section', ['section' => $section, 'date' => $dateParam]);
        $companyPosition = $company_position ?? [];
        $alerts = $alerts ?? [];
        $clientSummaries = $client_summaries ?? collect();
        $pendingCount = (int) ($pending_payments->count() ?? 0);
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-6 text-white sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">Finance V2</p>
                        <h1 class="mt-2 text-3xl font-black tracking-tight">Company Dashboard</h1>
                        <p class="mt-2 text-sm font-semibold text-slate-300">{{ $month_start->format('d M Y') }} – {{ $month_end->format('d M Y') }}</p>
                        @if(!empty($companyPosition['net_client_position_message']))
                            <p class="mt-3 text-sm font-black text-emerald-300">{{ $companyPosition['net_client_position_message'] }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.finance-v2.payments.index', ['date' => $dateParam]) }}" class="inline-flex h-11 items-center rounded-[1rem] border border-white/20 bg-white/10 px-5 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-white/15">
                            Payments
                        </a>
                        <a href="{{ route('admin.finance-v2.reports', ['date' => $dateParam]) }}" class="inline-flex h-11 items-center rounded-[1rem] bg-orange-500 px-5 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-orange-600">
                            Open Reports
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Company Position</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">Financial overview</h2>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Company Cash and Bank', 'value' => $companyPosition['company_cash_bank'] ?? 0])
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Direct Bills Payable', 'value' => $companyPosition['direct_bills_payable'] ?? 0, 'href' => $sectionHref('purchase')])
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Total Shop Receivable', 'value' => $companyPosition['total_shop_receivable'] ?? 0, 'href' => route('admin.finance-v2.clients.index', ['date' => $dateParam])])
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Company Payable to Shops', 'value' => $companyPosition['total_company_payable'] ?? 0, 'href' => route('admin.finance-v2.company-payables.index', ['date' => $dateParam])])
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Pending Company Requests', 'value' => (float) ($companyPosition['pending_company_expense_requests'] ?? 0), 'href' => route('admin.finance-v2.company-payables.index', ['date' => $dateParam])])
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Net Client Position', 'value' => $companyPosition['net_client_position'] ?? 0, 'hint' => $companyPosition['net_client_position_message'] ?? ''])
            </div>
        </section>

        @if(collect($alerts)->filter(fn ($count) => (int) $count > 0)->isNotEmpty())
            <section class="rounded-[1.6rem] border border-amber-200 bg-amber-50 p-4 shadow-sm sm:p-5">
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-amber-700">Attention Required</p>
                <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach([
                        'new_company_expense_requests' => 'New company expense requests',
                        'company_requests_over_7_days' => 'Requests pending more than 7 days',
                        'company_requests_over_14_days' => 'Requests pending more than 14 days',
                        'company_requests_over_30_days' => 'Requests pending more than 30 days',
                        'shop_payments_awaiting_approval' => 'Shop payments awaiting approval',
                        'unallocated_shop_payments' => 'Unallocated shop payments',
                        'purchase_invoices_overdue' => 'Purchase invoices overdue',
                    ] as $key => $label)
                        @if((int) ($alerts[$key] ?? 0) > 0)
                            <div class="rounded-[1rem] border border-amber-200 bg-white px-4 py-3 text-sm font-bold text-slate-800">
                                {{ $label }}: {{ (int) $alerts[$key] }}
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        <section class="space-y-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Green Leaf</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">Company account totals</h2>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Purchase', 'value' => $green_leaf['purchase_total'], 'hint' => 'Paid Rs. '.number_format($green_leaf['purchase_paid'], 2).' · Pending Rs. '.number_format($green_leaf['purchase_pending'], 2), 'href' => $sectionHref('purchase')])
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Expense', 'value' => $green_leaf['expense_total'], 'hint' => 'Company operating expenses', 'href' => $sectionHref('expense')])
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Salary', 'value' => $green_leaf['salary_total'], 'hint' => 'Company and shop salary paid', 'href' => $sectionHref('salary')])
                @include('admin.finance-v2.partials.metric-card', ['label' => 'Company Balance', 'value' => $green_leaf['balance'], 'hint' => 'Received Rs. '.number_format($green_leaf['total_received'], 2).' · Outflow Rs. '.number_format($green_leaf['total_paid'], 2), 'href' => $sectionHref('balance')])
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Clients</p>
                        <h3 class="mt-1 text-xl font-black text-slate-950">Client portfolio</h3>
                        <p class="mt-1 text-sm font-semibold text-slate-500">{{ $clientSummaries->count() }} active clients</p>
                    </div>
                    <a href="{{ route('admin.finance-v2.clients.index', ['date' => $dateParam]) }}" class="inline-flex h-10 items-center rounded-[1rem] border border-slate-200 px-4 text-xs font-black uppercase tracking-[0.16em] text-slate-700 hover:bg-slate-50">View all</a>
                </div>
                <div class="mt-5 space-y-2">
                    @forelse($clientSummaries as $row)
                        @php
                            $client = $row['client'];
                            $summary = $row['summary'];
                        @endphp
                        <a href="{{ route('admin.finance-v2.clients.show', ['client' => $client, 'date' => $dateParam]) }}" class="flex items-center justify-between gap-4 rounded-[1rem] border border-slate-200 bg-slate-50 px-4 py-3 transition hover:border-slate-300 hover:bg-white">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black text-slate-950">{{ $client->name }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ number_format((int) $summary['shop_count']) }} shops · Received Rs. {{ number_format((float) $summary['received'], 2) }}</p>
                            </div>
                            <p class="shrink-0 text-sm font-black text-slate-950">Rs. {{ number_format((float) $summary['balance'], 2) }}</p>
                        </a>
                    @empty
                        <div class="rounded-[1rem] border border-dashed border-slate-300 py-8 text-center text-sm font-bold text-slate-500">No active clients.</div>
                    @endforelse
                </div>
            </article>

            <article class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Payment Queue</p>
                        <h3 class="mt-1 text-xl font-black text-slate-950">Awaiting approval</h3>
                    </div>
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-black text-amber-700">{{ $pendingCount }}</span>
                </div>
                <div class="mt-5 divide-y divide-slate-100">
                    @forelse($pending_payments as $row)
                        <div class="flex items-center justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black text-slate-950">{{ $row['shop']?->name ?? 'Shop' }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['method'] }} · {{ $row['date'] }}</p>
                            </div>
                            <p class="shrink-0 text-sm font-black text-slate-950">Rs. {{ number_format((float) $row['amount'], 2) }}</p>
                        </div>
                    @empty
                        <div class="rounded-[1rem] border border-dashed border-slate-300 py-8 text-center text-sm font-bold text-slate-500">No pending payments.</div>
                    @endforelse
                </div>
                @if($pendingCount > 0)
                    <a href="{{ route('admin.finance-v2.payments.index', ['date' => $dateParam]) }}" class="mt-4 inline-flex text-xs font-black uppercase tracking-[0.14em] text-orange-600 hover:underline">Review all payments</a>
                @endif
            </article>
        </section>

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Period Snapshot</p>
                    <h3 class="mt-1 text-xl font-black text-slate-950">Daily received, paid, salary, expense and balance</h3>
                </div>
                <a href="{{ route('admin.finance-v2.reports', ['date' => $dateParam]) }}" class="text-xs font-black uppercase tracking-[0.14em] text-orange-600 hover:underline">Full report</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-slate-50">
                        <tr>
                            @foreach(['Date', 'Total Received', 'Total Paid', 'Salary', 'Expense', 'Balance'] as $heading)
                                <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400 {{ $loop->first ? '' : 'text-right' }}">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($report_rows->take(7) as $row)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3 text-sm font-black text-slate-950">{{ $row['label'] }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-emerald-700">Rs. {{ number_format((float) $row['total_received'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['total_paid'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['salary'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $row['expense'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-black text-slate-950">Rs. {{ number_format((float) $row['balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No daily rows for this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.accounting>
