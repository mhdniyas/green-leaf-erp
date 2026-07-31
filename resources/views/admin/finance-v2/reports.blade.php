<x-layouts.accounting title="Finance V2 Reports">
    @php
        $dateParam = $date->format('Y-m-d');
        $companyPosition = $company_position ?? [];
        $payableAgeing = $payable_ageing ?? ['0_7' => 0, '8_14' => 0, '15_30' => 0, '31_60' => 0, 'above_60' => 0];
        $dailyRows = $daily_rows ?? collect();
        $shopRows = $shop_rows ?? collect();
        $directRows = $direct_rows ?? collect();
        $clients = $clients ?? collect();

        $dailyTotals = [
            'total_received' => round((float) $dailyRows->sum('total_received'), 2),
            'total_paid' => round((float) $dailyRows->sum('total_paid'), 2),
            'salary' => round((float) $dailyRows->sum('salary'), 2),
            'expense' => round((float) $dailyRows->sum('expense'), 2),
            'balance' => round((float) $dailyRows->sum('balance'), 2),
        ];
        $shopTotals = [
            'opening_balance' => round((float) $shopRows->sum('opening_balance'), 2),
            'bills' => round((float) $shopRows->sum('bills'), 2),
            'expense' => round((float) $shopRows->sum('expense'), 2),
            'salary' => round((float) $shopRows->sum('salary'), 2),
            'received' => round((float) $shopRows->sum('received'), 2),
            'credit' => round((float) $shopRows->sum('credit'), 2),
            'closing_balance' => round((float) $shopRows->sum('closing_balance'), 2),
        ];
        $directTotals = [
            'opening_balance' => round((float) $directRows->sum('opening_balance'), 2),
            'bills' => round((float) $directRows->sum('bills'), 2),
            'expense' => round((float) $directRows->sum('expense'), 2),
            'salary' => round((float) $directRows->sum('salary'), 2),
            'received' => round((float) $directRows->sum('received'), 2),
            'credit' => round((float) $directRows->sum('credit'), 2),
            'closing_balance' => round((float) $directRows->sum('closing_balance'), 2),
        ];
        $ageingTotal = round(collect($payableAgeing)->sum(), 2);
        $sections = [
            ['id' => 'report-summary', 'label' => '1. Executive Summary'],
            ['id' => 'report-daily', 'label' => '2. Daily Movement'],
            ['id' => 'report-ageing', 'label' => '3. Payable Ageing'],
            ['id' => 'report-shops', 'label' => '4. Shop Balance Report'],
        ];
        if ($directRows->isNotEmpty()) {
            $sections[] = ['id' => 'report-direct', 'label' => '5. Direct Sales Shops'];
        }
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5 print:max-w-none print:space-y-8">
        <div class="print:hidden">
            @include('admin.finance-v2.partials.nav')
        </div>

        {{-- Report cover / header --}}
        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm print:rounded-none print:border-0 print:shadow-none">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-6 text-white sm:px-8 sm:py-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">Green Leaf Traders · Finance V2</p>
                        <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Finance V2 Reports</h1>
                        <p class="mt-3 max-w-2xl text-sm font-semibold text-slate-300">
                            Formal period report for company cash movement, client shop balances, and company payable ageing.
                        </p>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <span class="inline-flex rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em]">
                                Period {{ $month_start->format('d M Y') }} – {{ $date->format('d M Y') }}
                            </span>
                            <span class="inline-flex rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em]">
                                {{ $clients->count() }} clients · {{ $shopRows->count() }} shops
                            </span>
                            <span class="inline-flex rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em]">
                                Generated {{ now()->format('d M Y H:i') }}
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 print:hidden">
                        <a href="{{ route('admin.finance-v2.dashboard', ['date' => $dateParam]) }}" class="inline-flex h-11 items-center rounded-[1rem] border border-white/20 bg-white/10 px-5 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-white/15">
                            Dashboard
                        </a>
                        <button type="button" onclick="window.print()" class="inline-flex h-11 items-center rounded-[1rem] bg-orange-500 px-5 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-orange-600">
                            Print Report
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-0 lg:grid-cols-[0.85fr_1.15fr]">
                <aside class="border-b border-slate-200 bg-slate-50 p-5 sm:p-6 lg:border-b-0 lg:border-r print:hidden">
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Report Contents</p>
                    <nav class="mt-4 space-y-2">
                        @foreach($sections as $section)
                            <a href="#{{ $section['id'] }}" class="flex items-center justify-between rounded-[1rem] border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-800 transition hover:border-slate-300 hover:bg-slate-50">
                                <span>{{ $section['label'] }}</span>
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                            </a>
                        @endforeach
                    </nav>
                </aside>

                <div id="report-summary" class="p-5 sm:p-6">
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">1. Executive Summary</p>
                    <h2 class="mt-2 text-2xl font-black text-slate-950">Company position at a glance</h2>
                    @if(!empty($companyPosition['net_client_position_message']))
                        <p class="mt-3 rounded-[1rem] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-black text-emerald-900">
                            {{ $companyPosition['net_client_position_message'] }}
                        </p>
                    @endif

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-[1.1rem] border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Company Cash / Bank</p>
                            <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format((float) ($companyPosition['company_cash_bank'] ?? 0), 2) }}</p>
                        </div>
                        <div class="rounded-[1.1rem] border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Direct Bills Payable</p>
                            <p class="mt-2 text-2xl font-black text-amber-700">Rs. {{ number_format((float) ($companyPosition['direct_bills_payable'] ?? 0), 2) }}</p>
                        </div>
                        <div class="rounded-[1.1rem] border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Shop Receivable</p>
                            <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format((float) ($companyPosition['total_shop_receivable'] ?? 0), 2) }}</p>
                        </div>
                        <div class="rounded-[1.1rem] border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Company Payable to Shops</p>
                            <p class="mt-2 text-2xl font-black text-violet-700">Rs. {{ number_format((float) ($companyPosition['total_company_payable'] ?? 0), 2) }}</p>
                        </div>
                        <div class="rounded-[1.1rem] border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Pending Company Requests</p>
                            <p class="mt-2 text-2xl font-black text-orange-600">{{ number_format((int) ($companyPosition['pending_company_expense_requests'] ?? 0)) }}</p>
                        </div>
                        <div class="rounded-[1.1rem] border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Net Client Position</p>
                            <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format((float) ($companyPosition['net_client_position'] ?? 0), 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Daily movement report --}}
        <section id="report-daily" class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm print:break-inside-avoid print:rounded-none print:shadow-none">
            <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Report 02</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Daily Movement Report</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Total received, paid, salary, expense and balance by day</p>
                </div>
                <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">{{ $dailyRows->count() }} days</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-white">
                        <tr>
                            @foreach(['Date', 'Total Received', 'Total Paid', 'Salary', 'Expense', 'Balance'] as $heading)
                                <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400 {{ $loop->first ? '' : 'text-right' }}">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($dailyRows as $row)
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
                                <td colspan="6" class="px-4 py-12 text-center text-sm font-bold text-slate-500">No report rows found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($dailyRows->isNotEmpty())
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                            <tr>
                                <td class="px-4 py-3 text-xs font-black uppercase tracking-[0.16em] text-slate-500">Period Total</td>
                                <td class="px-4 py-3 text-right text-sm font-black text-emerald-700">Rs. {{ number_format($dailyTotals['total_received'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-black text-slate-950">Rs. {{ number_format($dailyTotals['total_paid'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-black text-slate-950">Rs. {{ number_format($dailyTotals['salary'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-black text-slate-950">Rs. {{ number_format($dailyTotals['expense'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-black text-slate-950">Rs. {{ number_format($dailyTotals['balance'], 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>

        {{-- Payable ageing --}}
        <section id="report-ageing" class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm print:break-inside-avoid print:rounded-none print:shadow-none">
            <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Report 03</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Company Payable Ageing Report</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Outstanding company payables grouped by age bucket</p>
                </div>
                <div class="rounded-[1rem] border border-violet-200 bg-violet-50 px-4 py-2 text-right">
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-violet-600">Total Ageing</p>
                    <p class="text-lg font-black text-violet-900">Rs. {{ number_format($ageingTotal, 2) }}</p>
                </div>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-5 sm:p-6">
                @foreach([
                    '0_7' => '0–7 days',
                    '8_14' => '8–14 days',
                    '15_30' => '15–30 days',
                    '31_60' => '31–60 days',
                    'above_60' => 'Above 60 days',
                ] as $key => $label)
                    @php
                        $bucket = (float) ($payableAgeing[$key] ?? 0);
                    @endphp
                    <div class="rounded-[1.15rem] border {{ $bucket > 0 ? 'border-violet-200 bg-violet-50' : 'border-slate-200 bg-slate-50' }} p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">{{ $label }}</p>
                        <p class="mt-2 text-xl font-black {{ $bucket > 0 ? 'text-violet-900' : 'text-slate-950' }}">Rs. {{ number_format($bucket, 2) }}</p>
                        @if($ageingTotal > 0)
                            <p class="mt-1 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">{{ number_format(($bucket / $ageingTotal) * 100, 1) }}% of total</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Shop balance report --}}
        <section id="report-shops" class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm print:break-inside-avoid print:rounded-none print:shadow-none">
            <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Report 04</p>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Shop Balance Report</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">All client shop opening, bills, expense, salary, received, credit and closing</p>
                </div>
                <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">{{ $shopRows->count() }} shops</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-white">
                        <tr>
                            @foreach(['Shop', 'Opening', 'Bills', 'Expense', 'Salary', 'Received', 'Credit', 'Closing'] as $heading)
                                <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400 {{ $loop->first ? '' : 'text-right' }}">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($shopRows as $row)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-black text-slate-950">{{ $row['shop']->name }}</p>
                                    <p class="mt-0.5 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">{{ $row['shop']->client?->name ?? 'Client shop' }}</p>
                                </td>
                                @foreach(['opening_balance', 'bills', 'expense', 'salary', 'received', 'credit', 'closing_balance'] as $field)
                                    <td class="px-4 py-3 text-right text-sm font-black {{ $field === 'closing_balance' ? 'text-slate-950' : ($field === 'received' ? 'text-emerald-700' : 'text-slate-700') }}">
                                        Rs. {{ number_format((float) $row[$field], 2) }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center text-sm font-bold text-slate-500">No client shops found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($shopRows->isNotEmpty())
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                            <tr>
                                <td class="px-4 py-3 text-xs font-black uppercase tracking-[0.16em] text-slate-500">All Shops Total</td>
                                @foreach(['opening_balance', 'bills', 'expense', 'salary', 'received', 'credit', 'closing_balance'] as $field)
                                    <td class="px-4 py-3 text-right text-sm font-black text-slate-950">Rs. {{ number_format($shopTotals[$field], 2) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>

        @if($directRows->isNotEmpty())
            <section id="report-direct" class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm print:break-inside-avoid print:rounded-none print:shadow-none">
                <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Report 05</p>
                        <h2 class="mt-1 text-xl font-black text-slate-950">Direct Sales Shop Report</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Accounting-enabled shops without a client link</p>
                    </div>
                    <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">{{ $directRows->count() }} shops</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-left">
                        <thead class="bg-white">
                            <tr>
                                @foreach(['Shop', 'Opening', 'Bills', 'Expense', 'Salary', 'Received', 'Credit', 'Closing'] as $heading)
                                    <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400 {{ $loop->first ? '' : 'text-right' }}">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($directRows as $row)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="px-4 py-3 text-sm font-black text-slate-950">{{ $row['shop']->name }}</td>
                                    @foreach(['opening_balance', 'bills', 'expense', 'salary', 'received', 'credit', 'closing_balance'] as $field)
                                        <td class="px-4 py-3 text-right text-sm font-black text-slate-700">Rs. {{ number_format((float) $row[$field], 2) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                            <tr>
                                <td class="px-4 py-3 text-xs font-black uppercase tracking-[0.16em] text-slate-500">Direct Total</td>
                                @foreach(['opening_balance', 'bills', 'expense', 'salary', 'received', 'credit', 'closing_balance'] as $field)
                                    <td class="px-4 py-3 text-right text-sm font-black text-slate-950">Rs. {{ number_format($directTotals[$field], 2) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        @endif

        <section class="rounded-[1.6rem] border border-dashed border-slate-300 bg-slate-50 px-5 py-4 text-center print:rounded-none">
            <p class="text-xs font-bold text-slate-500">End of Finance V2 Reports · {{ $month_start->format('d M Y') }} to {{ $date->format('d M Y') }} · Confidential — Green Leaf Traders</p>
        </section>
    </div>
</x-layouts.accounting>
