<x-layouts.accounting title="Green Leaf Summary">
    @php
        $previousDate = $date->copy()->subDay()->format('Y-m-d');
        $nextDate = $date->copy()->addDay()->format('Y-m-d');
        $todayDate = today()->toDateString();
        $daily = $report['daily'];
        $monthly = $report['monthly'];
        $dailyRows = $report['daily_rows'];
        $carryRows = $report['carry_rows'];
        $supplierDueRows = $report['supplier_due_rows'];
        $shopDueRows = $report['shop_due_rows'];
        $maxChartAmount = max(1, (float) $dailyRows->max(fn ($row) => max((float) $row['income_bills'], (float) $row['expense_bills'])));
        $chartWidth = 920;
        $chartHeight = 260;
        $chartPadX = 42;
        $chartPadY = 28;
        $chartInnerWidth = $chartWidth - ($chartPadX * 2);
        $chartInnerHeight = $chartHeight - ($chartPadY * 2);
        $chartCount = max(1, $dailyRows->count() - 1);
        $pointFor = function ($index, $amount) use ($chartPadX, $chartPadY, $chartInnerWidth, $chartInnerHeight, $chartCount, $maxChartAmount) {
            $x = $chartPadX + (($index / $chartCount) * $chartInnerWidth);
            $y = $chartPadY + ($chartInnerHeight - (((float) $amount / $maxChartAmount) * $chartInnerHeight));

            return round($x, 2).','.round($y, 2);
        };
        $incomePoints = $dailyRows->values()->map(fn ($row, $index) => $pointFor($index, $row['income_bills']))->implode(' ');
        $expensePoints = $dailyRows->values()->map(fn ($row, $index) => $pointFor($index, $row['expense_bills']))->implode(' ');
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-emerald-700">Green Leaf Analytics</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Daily and monthly income, expense, and carry-over</h1>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Supplier bills are expense. Shop invoice bills are income. Later payments and discounts are counted in the month they are recorded.</p>
                </div>

                <form method="GET" action="{{ route('admin.accounting.company-summary') }}" class="flex flex-wrap items-center gap-2 rounded-[1.2rem] border border-slate-200 bg-slate-50 p-2">
                    <a href="{{ route('admin.accounting.company-summary', ['date' => $previousDate]) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[1rem] border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-100" title="Previous day">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                    </a>
                    <label class="min-w-[11rem] rounded-[1rem] border border-slate-200 bg-white px-3 py-2 text-slate-900 shadow-sm">
                        <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Business Date</span>
                        <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" onchange="this.form.submit()" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black text-slate-900 focus:outline-none focus:ring-0">
                    </label>
                    @if($date->format('Y-m-d') !== $todayDate)
                        <a href="{{ route('admin.accounting.company-summary', ['date' => $todayDate]) }}" class="inline-flex h-10 items-center justify-center rounded-[1rem] border border-slate-200 bg-white px-3 text-[10px] font-black uppercase tracking-[0.16em] text-slate-950 transition hover:bg-slate-100">Today</a>
                    @endif
                    <a href="{{ route('admin.accounting.company-summary', ['date' => $nextDate]) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[1rem] border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-100" title="Next day">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </a>
                </form>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <article class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Daily</p>
                        <h2 class="mt-2 text-xl font-black text-slate-950">{{ $date->format('d M Y') }}</h2>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-600">{{ number_format($daily['supplier_bill_count']) }} supplier bill(s)</span>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-[1rem] border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Income Bills</p>
                        <p class="mt-2 text-2xl font-black text-emerald-950">Rs. {{ number_format($daily['income_bills'], 2) }}</p>
                        <p class="mt-1 text-xs font-bold text-emerald-800">Collected Rs. {{ number_format($daily['income_collected'], 2) }}</p>
                    </div>
                    <div class="rounded-[1rem] border border-rose-200 bg-rose-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">Supplier Expense</p>
                        <p class="mt-2 text-2xl font-black text-rose-950">Rs. {{ number_format($daily['expense_bills'], 2) }}</p>
                        <p class="mt-1 text-xs font-bold text-rose-800">Paid Rs. {{ number_format($daily['expense_paid'], 2) }}</p>
                    </div>
                    <div class="rounded-[1rem] border border-amber-200 bg-amber-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Pending Collection</p>
                        <p class="mt-2 text-xl font-black text-amber-950">Rs. {{ number_format($daily['income_pending'], 2) }}</p>
                        <p class="mt-1 text-xs font-bold text-amber-800">Discount Rs. {{ number_format($daily['income_discount'], 2) }}</p>
                    </div>
                    <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Net Cash</p>
                        <p class="mt-2 text-xl font-black {{ $daily['net_cash'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Rs. {{ number_format($daily['net_cash'], 2) }}</p>
                        <p class="mt-1 text-xs font-bold text-slate-500">Income collected minus expense paid</p>
                    </div>
                </div>
            </article>

            <article class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Monthly</p>
                        <h2 class="mt-2 text-xl font-black text-slate-950">{{ $report['month_start']->format('F Y') }}</h2>
                    </div>
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-600">{{ number_format($monthly['shop_invoice_count']) }} income bill(s)</span>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-[1rem] border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Income Bills</p>
                        <p class="mt-2 text-2xl font-black text-emerald-950">Rs. {{ number_format($monthly['income_bills'], 2) }}</p>
                        <p class="mt-1 text-xs font-bold text-emerald-800">Collected Rs. {{ number_format($monthly['income_collected'], 2) }}</p>
                    </div>
                    <div class="rounded-[1rem] border border-rose-200 bg-rose-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">Expense Bills</p>
                        <p class="mt-2 text-2xl font-black text-rose-950">Rs. {{ number_format($monthly['expense_bills'], 2) }}</p>
                        <p class="mt-1 text-xs font-bold text-rose-800">Paid Rs. {{ number_format($monthly['expense_paid'], 2) }}</p>
                    </div>
                    <div class="rounded-[1rem] border border-amber-200 bg-amber-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-amber-700">Carry Pending</p>
                        <p class="mt-2 text-xl font-black text-amber-950">Rs. {{ number_format($monthly['income_pending'] + $monthly['expense_pending'], 2) }}</p>
                        <p class="mt-1 text-xs font-bold text-amber-800">Current-month pending before carry</p>
                    </div>
                    <div class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Net Billed</p>
                        <p class="mt-2 text-xl font-black {{ $monthly['net_billed'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Rs. {{ number_format($monthly['net_billed'], 2) }}</p>
                        <p class="mt-1 text-xs font-bold text-slate-500">Income bills minus expense bills</p>
                    </div>
                </div>
            </article>
        </section>

        <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Daily Chart</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">Income bills and supplier expense bills</h2>
                </div>
                <div class="flex gap-3 text-xs font-black">
                    <span class="inline-flex items-center gap-2 text-emerald-700"><span class="h-2.5 w-2.5 rounded-full bg-emerald-600"></span>Income</span>
                    <span class="inline-flex items-center gap-2 text-rose-700"><span class="h-2.5 w-2.5 rounded-full bg-rose-600"></span>Expense</span>
                </div>
            </div>
            <div class="mt-5 overflow-x-auto rounded-[1.25rem] border border-slate-200 bg-slate-50 p-3">
                <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="h-72 min-w-[54rem] w-full" role="img" aria-label="Daily income and expense line chart">
                    <line x1="{{ $chartPadX }}" y1="{{ $chartHeight - $chartPadY }}" x2="{{ $chartWidth - $chartPadX }}" y2="{{ $chartHeight - $chartPadY }}" stroke="#cbd5e1" stroke-width="1" />
                    <line x1="{{ $chartPadX }}" y1="{{ $chartPadY }}" x2="{{ $chartPadX }}" y2="{{ $chartHeight - $chartPadY }}" stroke="#cbd5e1" stroke-width="1" />
                    @foreach($dailyRows->values() as $index => $row)
                        @if($index % 5 === 0 || $loop->last)
                            @php
                                $x = $chartPadX + (($index / max(1, $dailyRows->count() - 1)) * $chartInnerWidth);
                            @endphp
                            <text x="{{ $x }}" y="{{ $chartHeight - 6 }}" text-anchor="middle" class="fill-slate-500 text-[10px] font-bold">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d') }}</text>
                        @endif
                    @endforeach
                    <polyline points="{{ $incomePoints }}" fill="none" stroke="#059669" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                    <polyline points="{{ $expensePoints }}" fill="none" stroke="#e11d48" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                    @foreach($dailyRows->values() as $index => $row)
                        @php
                            [$incomeX, $incomeY] = explode(',', $pointFor($index, $row['income_bills']));
                            [$expenseX, $expenseY] = explode(',', $pointFor($index, $row['expense_bills']));
                        @endphp
                        <circle cx="{{ $incomeX }}" cy="{{ $incomeY }}" r="3.5" fill="#059669" />
                        <circle cx="{{ $expenseX }}" cy="{{ $expenseY }}" r="3.5" fill="#e11d48" />
                    @endforeach
                </svg>
            </div>
        </section>

        <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Carry Over</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">Monthly pending movement</h2>
                </div>
            </div>
            <div class="mt-5 overflow-x-auto rounded-[1.25rem] border border-slate-200">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                        <tr>
                            <th class="px-4 py-3">Month</th>
                            <th class="px-4 py-3 text-right">Supplier Opening</th>
                            <th class="px-4 py-3 text-right">Supplier Bills</th>
                            <th class="px-4 py-3 text-right">Paid</th>
                            <th class="px-4 py-3 text-right">Discount</th>
                            <th class="px-4 py-3 text-right">Supplier Closing</th>
                            <th class="px-4 py-3 text-right">Bills Opening</th>
                            <th class="px-4 py-3 text-right">Income Bills</th>
                            <th class="px-4 py-3 text-right">Collected</th>
                            <th class="px-4 py-3 text-right">Discount</th>
                            <th class="px-4 py-3 text-right">Bills Closing</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($carryRows as $row)
                            <tr>
                                <td class="px-4 py-3 font-black text-slate-950">{{ $row['label'] }}</td>
                                <td class="px-4 py-3 text-right font-bold text-slate-700">Rs. {{ number_format($row['supplier_opening_pending'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-slate-700">Rs. {{ number_format($row['expense_bills'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-emerald-700">Rs. {{ number_format($row['expense_paid'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-cyan-700">Rs. {{ number_format($row['expense_discount'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-rose-700">Rs. {{ number_format($row['supplier_closing_pending'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-slate-700">Rs. {{ number_format($row['shop_opening_pending'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-slate-700">Rs. {{ number_format($row['income_bills'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-emerald-700">Rs. {{ number_format($row['income_collected'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-cyan-700">Rs. {{ number_format($row['income_discount'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-black text-amber-700">Rs. {{ number_format($row['shop_closing_pending'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <article class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-xl font-black text-slate-950">Supplier Bills Pending</h2>
                <div class="mt-4 overflow-x-auto rounded-[1.25rem] border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Supplier</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3 text-right">Bill</th>
                                <th class="px-4 py-3 text-right">Pending</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($supplierDueRows->take(12) as $row)
                                <tr>
                                    <td class="px-4 py-3 font-black text-slate-950">{{ $row['party'] }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-500">{{ \Illuminate\Support\Carbon::parse($row['business_date'])->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-slate-700">Rs. {{ number_format($row['bill_amount'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-rose-700">Rs. {{ number_format($row['pending_amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center font-bold text-slate-500">No supplier pending bills.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-xl font-black text-slate-950">Invoice Bills Pending</h2>
                <div class="mt-4 overflow-x-auto rounded-[1.25rem] border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Shop</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3 text-right">Bill</th>
                                <th class="px-4 py-3 text-right">Pending</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($shopDueRows->take(12) as $row)
                                <tr>
                                    <td class="px-4 py-3 font-black text-slate-950">{{ $row['party'] }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-500">{{ \Illuminate\Support\Carbon::parse($row['business_date'])->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-slate-700">Rs. {{ number_format($row['bill_amount'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-black text-amber-700">Rs. {{ number_format($row['pending_amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center font-bold text-slate-500">No invoice pending bills.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </div>
</x-layouts.accounting>
