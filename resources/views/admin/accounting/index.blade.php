<x-layouts.accounting title="Accounting Dashboard">
    @php
        $prevDate = $date->copy()->subDay()->format('Y-m-d');
        $nextDate = $date->copy()->addDay()->format('Y-m-d');
        $todayDate = today()->toDateString();
        $salesSummary = $finance['sales']['summary'];
        $vendorSummary = $finance['vendor']['summary'];
        $purchaserBalance = round((float) $purchaserCashRows->sum('balance'), 2);
        $purchaserTodayFlow = round((float) $purchaserCashRows->sum('today_balance'), 2);
        $cashFlowSummary = $cashFlowReport['summary'];
        $cashFlowTab = request()->string('cash_tab')->toString();
        $cashFlowTab = in_array($cashFlowTab, ['journal', 'daily-balance', 'cash-paid', 'cash-received'], true) ? $cashFlowTab : 'journal';
        $summaryCards = [
            ['label' => 'Shop Sales', 'value' => 'Rs. '.number_format($salesSummary['total_amount'], 2), 'hint' => number_format($salesSummary['invoice_count']).' invoice(s) for '.$date->format('d M Y')],
            ['label' => 'Sales Collection', 'value' => 'Rs. '.number_format($salesSummary['paid_amount'], 2), 'hint' => 'Pending Rs. '.number_format($salesSummary['outstanding_amount'], 2)],
            ['label' => 'Vendor Payments', 'value' => 'Rs. '.number_format($vendorSummary['paid_amount'], 2), 'hint' => 'Vendor due Rs. '.number_format($vendorSummary['outstanding_amount'], 2)],
            ['label' => 'Purchaser Balance', 'value' => 'Rs. '.number_format($purchaserBalance, 2), 'hint' => 'Today movement Rs. '.number_format($purchaserTodayFlow, 2)],
        ];
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        <section class="space-y-4 rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Accounting Dashboard</p>
                    <p class="mt-1 text-sm font-semibold text-slate-600">Daily accounting snapshot for sales, vendors, purchasers, and owned shops.</p>
                </div>

                <form method="GET" action="{{ route('admin.accounting.index') }}" class="flex flex-wrap items-center gap-2 rounded-[1.2rem] border border-slate-200 bg-slate-50 p-2">
                    <a href="{{ route('admin.accounting.index', ['date' => $prevDate]) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[1rem] border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-100" title="Previous day">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </a>
                    <label class="min-w-[11rem] rounded-[1rem] border border-slate-200 bg-white px-3 py-2 text-slate-900 shadow-sm">
                        <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Business Date</span>
                        <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" onchange="this.form.submit()" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black text-slate-900 focus:outline-none focus:ring-0">
                    </label>
                    @if($date->format('Y-m-d') !== $todayDate)
                        <a href="{{ route('admin.accounting.index', ['date' => $todayDate]) }}" class="inline-flex h-10 items-center justify-center rounded-[1rem] border border-slate-200 bg-white px-3 text-[10px] font-black uppercase tracking-[0.16em] text-slate-950 transition hover:bg-slate-100">
                            Today
                        </a>
                    @endif
                    <a href="{{ route('admin.accounting.index', ['date' => $nextDate]) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[1rem] border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-100" title="Next day">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </form>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach($summaryCards as $card)
                    <article class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">{{ $card['label'] }}</p>
                        <p class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ $card['value'] }}</p>
                        <p class="mt-1.5 text-xs font-semibold text-slate-500">{{ $card['hint'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="rounded-[1.9rem] border border-slate-200 bg-white p-5 shadow-sm" data-cash-flow-tabs data-cash-flow-active-tab="{{ $cashFlowTab }}">
            <div class="flex flex-col gap-4 border-b border-slate-100 pb-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="max-w-3xl">
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Cash Flow Report</p>
                    <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Combined purchaser and owned shop cash journal</h3>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Monthly view for {{ $cashFlowReport['month_label'] }} with one journal, running balance, and purchaser-wise paid and received details.</p>
                </div>

                <div class="flex flex-col gap-3 xl:items-end">
                    <div class="flex flex-wrap gap-2 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-2" role="tablist" aria-label="Cash flow views">
                        @foreach([
                            'journal' => 'Journal',
                            'daily-balance' => 'Daily Balance',
                            'cash-paid' => 'Purchaser Paid',
                            'cash-received' => 'Purchaser Receive',
                        ] as $tabKey => $tabLabel)
                            <button
                                type="button"
                                role="tab"
                                aria-selected="{{ $cashFlowTab === $tabKey ? 'true' : 'false' }}"
                                data-cash-flow-tab-button="{{ $tabKey }}"
                                class="inline-flex h-11 items-center rounded-2xl px-4 text-xs font-black uppercase tracking-[0.16em] transition {{ $cashFlowTab === $tabKey ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-700 hover:bg-white' }}"
                            >
                                {{ $tabLabel }}
                            </button>
                        @endforeach
                    </div>

                    <div class="rounded-[1.3rem] border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Report Period</p>
                        <p class="mt-1 text-sm font-black text-slate-950">{{ \Illuminate\Support\Carbon::parse($cashFlowReport['start_date'])->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($cashFlowReport['end_date'])->format('d M Y') }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-[1.35rem] border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Opening Balance</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($cashFlowSummary['opening_balance'], 2) }}</p>
                </article>
                <article class="rounded-[1.35rem] border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Total In</p>
                    <p class="mt-2 text-2xl font-black text-emerald-800">Rs. {{ number_format($cashFlowSummary['total_in'], 2) }}</p>
                </article>
                <article class="rounded-[1.35rem] border border-amber-200 bg-amber-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Total Out</p>
                    <p class="mt-2 text-2xl font-black text-amber-800">Rs. {{ number_format($cashFlowSummary['total_out'], 2) }}</p>
                </article>
                <article class="rounded-[1.35rem] border border-cyan-200 bg-cyan-50 p-4">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-cyan-700">Closing Balance</p>
                    <p class="mt-2 text-2xl font-black text-cyan-900">Rs. {{ number_format($cashFlowSummary['closing_balance'], 2) }}</p>
                </article>
            </div>

            <article class="mt-5 overflow-hidden rounded-[1.5rem] border border-slate-200">
                <section data-cash-flow-tab-panel="journal" @class(['hidden' => $cashFlowTab !== 'journal'])>
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Journal</p>
                        <p class="mt-1 text-sm font-black text-slate-950">Purchaser credits and owned shop approved entries</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left">
                            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3 whitespace-nowrap">Date</th>
                                    <th class="px-4 py-3 text-right whitespace-nowrap">Amount</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Debit / Credit</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Journal</th>
                                    <th class="px-4 py-3 min-w-[15rem]">Remarks</th>
                                    <th class="px-4 py-3 whitespace-nowrap">Category</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @forelse($cashFlowReport['journal_rows'] as $row)
                                    <tr>
                                        <td class="px-4 py-3 font-black text-slate-950 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d-M') }}</td>
                                        <td class="px-4 py-3 text-right font-black text-slate-950 whitespace-nowrap">Rs. {{ number_format((float) $row['amount'], 2) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $row['direction'] === 'IN' ? 'border border-emerald-200 bg-emerald-50 text-emerald-700' : 'border border-amber-200 bg-amber-50 text-amber-700' }}">
                                                {{ $row['direction'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <p class="font-black text-slate-950">{{ $row['journal'] }}</p>
                                            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ str_replace('_', ' ', $row['source']) }}</p>
                                        </td>
                                        <td class="px-4 py-3 text-sm font-semibold text-slate-600 min-w-[15rem]">
                                            @if($row['remarks'])
                                                @if(strlen($row['remarks']) > 60)
                                                    <div data-remarks-container>
                                                        <span data-remarks-short class="block line-clamp-1 text-slate-600">{{ $row['remarks'] }}</span>
                                                        <span data-remarks-full class="hidden text-slate-600">{{ $row['remarks'] }}</span>
                                                        <button type="button" onclick="toggleRemarks(this)" class="mt-1 block text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700 hover:text-emerald-800 focus:outline-none">
                                                            Show More
                                                        </button>
                                                    </div>
                                                @else
                                                    {{ $row['remarks'] }}
                                                @endif
                                            @else
                                                <span class="text-slate-400 italic">No remarks</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm font-black text-slate-700 whitespace-nowrap">{{ $row['category'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-10 text-center text-sm font-bold text-slate-500">
                                            No cash flow rows are available for {{ $cashFlowReport['month_label'] }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section data-cash-flow-tab-panel="daily-balance" @class(['hidden' => $cashFlowTab !== 'daily-balance'])>
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Daily Balance</p>
                        <p class="mt-1 text-sm font-black text-slate-950">Running cash position for the month</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left">
                            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3 text-right">In</th>
                                    <th class="px-4 py-3 text-right">Out</th>
                                    <th class="px-4 py-3 text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @foreach($cashFlowReport['daily_rows'] as $row)
                                    <tr>
                                        <td class="px-4 py-3 font-black text-slate-950">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d-M') }}</td>
                                        <td class="px-4 py-3 text-right font-black text-emerald-700">Rs. {{ number_format((float) $row['in_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black text-amber-700">Rs. {{ number_format((float) $row['out_amount'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-black {{ (float) $row['balance'] >= 0 ? 'text-cyan-900' : 'text-rose-700' }}">Rs. {{ number_format((float) $row['balance'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section data-cash-flow-tab-panel="cash-paid" @class(['hidden' => $cashFlowTab !== 'cash-paid'])>
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Purchaser Paid</p>
                        <p class="mt-1 text-sm font-black text-slate-950">Purchaser Paid</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left">
                            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3 whitespace-nowrap">Date</th>
                                    @foreach($cashFlowReport['purchaser_columns'] as $column)
                                        <th class="px-4 py-3 text-right whitespace-nowrap">{{ $column['label'] }}</th>
                                    @endforeach
                                    <th class="px-4 py-3 text-right whitespace-nowrap">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @foreach($cashFlowReport['paid_rows'] as $row)
                                    <tr>
                                        <td class="px-4 py-3 font-black text-slate-950 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d-M') }}</td>
                                        @foreach($cashFlowReport['purchaser_columns'] as $column)
                                            <td class="px-4 py-3 text-right font-black text-slate-700 whitespace-nowrap">Rs. {{ number_format((float) ($row['amounts'][$column['id']] ?? 0), 2) }}</td>
                                        @endforeach
                                        <td class="px-4 py-3 text-right font-black text-slate-950 whitespace-nowrap">Rs. {{ number_format((float) $row['total'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section data-cash-flow-tab-panel="cash-received" @class(['hidden' => $cashFlowTab !== 'cash-received'])>
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Purchaser Receive</p>
                        <p class="mt-1 text-sm font-black text-slate-950">Purchaser Receive</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left">
                            <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                                <tr>
                                    <th class="px-4 py-3 whitespace-nowrap">Date</th>
                                    @foreach($cashFlowReport['purchaser_columns'] as $column)
                                        <th class="px-4 py-3 text-right whitespace-nowrap">{{ $column['label'] }}</th>
                                    @endforeach
                                    <th class="px-4 py-3 text-right whitespace-nowrap">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @foreach($cashFlowReport['received_rows'] as $row)
                                    <tr>
                                        <td class="px-4 py-3 font-black text-slate-950 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d-M') }}</td>
                                        @foreach($cashFlowReport['purchaser_columns'] as $column)
                                            <td class="px-4 py-3 text-right font-black text-slate-700 whitespace-nowrap">Rs. {{ number_format((float) ($row['amounts'][$column['id']] ?? 0), 2) }}</td>
                                        @endforeach
                                        <td class="px-4 py-3 text-right font-black text-slate-950 whitespace-nowrap">Rs. {{ number_format((float) $row['total'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </article>
        </section>

        <section class="grid gap-5 2xl:grid-cols-[1.55fr_0.95fr]">
            <article class="rounded-[1.9rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 border-b border-slate-100 pb-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Purchasing Dashboard</p>
                        <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Purchasing workflow moved out of admin</h3>
                        <p class="mt-2 text-sm font-semibold leading-6 text-slate-600">Approve shop orders, daily price board, shop invoices, purchase orders, goods receipts, and supplier bills now live in the dedicated purchasing dashboard.</p>
                    </div>

                    <a href="{{ route('purchasing.dashboard') }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-950 px-4 text-xs font-black uppercase tracking-[0.18em] text-white transition hover:bg-slate-800">
                        Open Purchasing Dashboard
                    </a>
                </div>

                <div class="mt-5 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5">
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach([
                            'Approve Shop Orders',
                            'Approved Board',
                            'Daily Price Board',
                            'Shop Price Categories',
                            'Shop Daily Invoices',
                            'Purchase Orders',
                            'Goods Receipts',
                            'Supplier Bills',
                        ] as $purchasingArea)
                            <div class="rounded-[1.2rem] border border-slate-200 bg-white px-4 py-3 text-sm font-black text-slate-700">
                                {{ $purchasingArea }}
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-4 text-sm font-semibold leading-6 text-slate-600">
                        Admin accounting no longer generates or manages purchasing records from this screen.
                    </p>
                </div>
            </article>

            <article class="rounded-[1.9rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Owned Shop Accounting</p>
                        <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Settlement side panel</h3>
                    </div>
                    <a href="{{ route('admin.accounting.owned-shops.index') }}" class="inline-flex h-10 items-center rounded-2xl border border-slate-200 px-4 text-xs font-black uppercase tracking-[0.16em] text-slate-700 transition hover:bg-slate-50">
                        Open Shops
                    </a>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-[1.35rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Eligible Shops</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">{{ number_format($ownedMetrics['eligible_shop_count']) }}</p>
                    </div>
                    <div class="rounded-[1.35rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Net Position</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($ownedMetrics['net_amount'], 2) }}</p>
                    </div>
                    <div class="rounded-[1.35rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Pending Review</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">{{ number_format($ownedMetrics['pending_review_count']) }}</p>
                    </div>
                    <div class="rounded-[1.35rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Rechecks</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">{{ number_format($ownedMetrics['recheck_count']) }}</p>
                    </div>
                </div>

                <div class="mt-5 overflow-hidden rounded-[1.5rem] border border-slate-200">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Tracked Shops</p>
                            <p class="mt-1 text-sm font-black text-slate-950">Recent accounting-enabled shops</p>
                        </div>
                        <a href="{{ route('admin.accounting.purchasers.index') }}" class="inline-flex h-9 items-center rounded-xl border border-emerald-200 bg-white px-3 text-[11px] font-black uppercase tracking-[0.16em] text-emerald-700 transition hover:bg-emerald-50">
                            Purchasers
                        </a>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse($eligibleShops as $shop)
                            <a href="{{ route('admin.accounting.owned-shops.show', $shop) }}" class="flex items-center justify-between gap-3 px-4 py-3 transition hover:bg-slate-50">
                                <div>
                                    <p class="font-black text-slate-950">{{ $shop->name }}</p>
                                    <p class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $shop->code }} • {{ $shop->accounting_mode }}</p>
                                </div>
                                <span class="text-xs font-black text-cyan-700">Open</span>
                            </a>
                        @empty
                            <div class="px-4 py-8 text-center text-sm font-bold text-slate-500">
                                No owned or partnership shops are enabled yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </article>
        </section>

        <section class="rounded-[1.9rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Purchaser Cash Flow</p>
                    <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Purchaser accounts and day movement</h3>
                    <p class="mt-2 text-sm font-semibold text-slate-600">This keeps cash given, invoice outflow, and current balance in one place for admin review.</p>
                </div>
                <a href="{{ route('admin.accounting.purchasers.index') }}" class="inline-flex h-10 items-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-emerald-700 transition hover:bg-emerald-100">
                    Open Purchaser Ledgers
                </a>
            </div>

            <div class="mt-5 overflow-x-auto rounded-[1.5rem] border border-slate-200">
                <table class="min-w-full text-left">
                    <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                        <tr>
                            <th class="px-4 py-3">Purchaser</th>
                            <th class="px-4 py-3 text-right">Total In</th>
                            <th class="px-4 py-3 text-right">Total Out</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                            <th class="px-4 py-3 text-right">Today In</th>
                            <th class="px-4 py-3 text-right">Today Out</th>
                            <th class="px-4 py-3 text-right">Today Net</th>
                            <th class="px-4 py-3 text-right">Txn</th>
                            <th class="px-4 py-3 text-right">Ledger</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @forelse($purchaserCashRows as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-black text-slate-950">{{ $row['purchaser']->name }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $row['purchaser']->email }}</p>
                                </td>
                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['total_in'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['total_out'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-black {{ $row['balance'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Rs. {{ number_format($row['balance'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-emerald-700">Rs. {{ number_format($row['today_in'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-slate-950">Rs. {{ number_format($row['today_out'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-black {{ $row['today_balance'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Rs. {{ number_format($row['today_balance'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-slate-600">{{ number_format($row['transaction_count']) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.accounting.purchasers.show', $row['purchaser']) }}" class="inline-flex h-8 items-center rounded-xl border border-slate-200 px-3 text-xs font-black text-slate-700 transition hover:bg-slate-50">
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-sm font-bold text-slate-500">
                                    No purchaser accounts are available.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        function toggleRemarks(button) {
            const container = button.closest('[data-remarks-container]');
            const shortSpan = container.querySelector('[data-remarks-short]');
            const fullSpan = container.querySelector('[data-remarks-full]');
            
            if (fullSpan.classList.contains('hidden')) {
                fullSpan.classList.remove('hidden');
                shortSpan.classList.add('hidden');
                button.textContent = 'Show Less';
            } else {
                fullSpan.classList.add('hidden');
                shortSpan.classList.remove('hidden');
                button.textContent = 'Show More';
            }
        }
    </script>
    <script src="{{ asset('js/accounting-cash-flow-tabs.js') }}" defer></script>
</x-layouts.accounting>
