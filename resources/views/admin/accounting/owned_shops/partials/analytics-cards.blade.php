@php
    $dailyRows = $analytics['daily_summaries']->take(6)->reverse()->values();
	    $dailyGraphMax = max(1, (float) $dailyRows->flatMap(fn ($summary) => [
	        abs((float) $summary['billed']),
	        abs((float) $summary['paid']),
	        abs((float) ($summary['cash_credit'] ?? 0)),
	        abs((float) ($summary['cash_debit'] ?? 0)),
	        abs((float) ($summary['closing_balance'] ?? 0)),
	        abs((float) ($summary['staff_salary'] ?? 0)),
	        abs((float) ($summary['staff_advance'] ?? 0)),
	    ])->max());
    $dailyAxisMax = $dailyRows->count() > 0 ? $dailyGraphMax : 1;
    $incomeTotal = max(1, (float) $analytics['cards']['income']);
    $expenseTotal = max(1, (float) $analytics['cards']['expense']);
    $staffTotal = (float) $analytics['cards']['staff_salary'] + (float) $analytics['cards']['staff_advance'];
    $cashFlow = (float) $analytics['cards']['cash_flow'];
    $collectionRate = (float) $analytics['cards']['total_billed'] > 0
        ? round(((float) $analytics['cards']['total_paid'] / (float) $analytics['cards']['total_billed']) * 100)
        : 0;
    $balanceRate = (float) $analytics['cards']['total_billed'] > 0
        ? max(0, 100 - $collectionRate)
        : 0;
	    $donutSegments = [
	        ['label' => 'Cash Debit', 'amount' => (float) ($analytics['cards']['cash_debit'] ?? 0), 'class' => 'bg-rose-500 text-rose-700'],
	        ['label' => 'Staff', 'amount' => $staffTotal, 'class' => 'bg-amber-400 text-amber-700'],
	        ['label' => 'Loan Movement', 'amount' => abs((float) ($analytics['cards']['shop_cash_movement'] ?? 0)), 'class' => 'bg-emerald-500 text-emerald-700'],
	    ];
    $donutTotal = max(1, collect($donutSegments)->sum('amount'));
    $primaryCards = [
        ['label' => 'Billed', 'value' => (float) $analytics['cards']['total_billed'], 'tone' => 'text-slate-950', 'caption' => 'Generated invoices'],
        ['label' => 'Collected', 'value' => (float) $analytics['cards']['total_paid'], 'tone' => 'text-emerald-700', 'caption' => $collectionRate.'% collection'],
        ['label' => 'Balance', 'value' => (float) $analytics['cards']['total_balance'], 'tone' => 'text-rose-700', 'caption' => $balanceRate.'% pending'],
	        ['label' => 'Closing Balance', 'value' => (float) ($analytics['cards']['closing_balance'] ?? $pettyCashBalance), 'tone' => (float) ($analytics['cards']['closing_balance'] ?? $pettyCashBalance) >= 0 ? 'text-emerald-700' : 'text-rose-700', 'caption' => 'Latest receipt balance'],
	    ];
	    $secondaryCards = [
	        ['label' => 'Cash Credit', 'value' => (float) ($analytics['cards']['cash_credit'] ?? 0), 'tone' => 'text-emerald-700'],
	        ['label' => 'Cash Debit', 'value' => (float) ($analytics['cards']['cash_debit'] ?? 0), 'tone' => 'text-rose-700'],
	        ['label' => 'Staff Salary', 'value' => (float) $analytics['cards']['staff_salary'], 'tone' => 'text-amber-700'],
	        ['label' => 'Staff Advance', 'value' => (float) $analytics['cards']['staff_advance'], 'tone' => 'text-amber-700'],
	        ['label' => 'Loan Movement', 'value' => (float) ($analytics['cards']['shop_cash_movement'] ?? 0), 'tone' => (float) ($analytics['cards']['shop_cash_movement'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700'],
	        ['label' => 'Receipt Balance', 'value' => $cashFlow, 'tone' => $cashFlow >= 0 ? 'text-cyan-700' : 'text-rose-700'],
	    ];
@endphp

<section id="owned-shop-summary" class="rounded-3xl border border-slate-200 bg-[#f7f8fa] p-3 shadow-sm sm:p-4">
    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-[0_10px_30px_rgba(15,23,42,0.04)] sm:px-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase text-slate-400">Client dashboard</p>
                <h2 class="mt-1 text-2xl font-semibold text-slate-950">Sales and financial insights</h2>
            </div>
            <div class="inline-flex w-fit items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500">
                <span>{{ $startDate->format('d M Y') }}</span>
                <span class="h-px w-4 bg-slate-300"></span>
                <span>{{ $endDate->format('d M Y') }}</span>
            </div>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($primaryCards as $card)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_12px_26px_rgba(15,23,42,0.05)]">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-500">{{ $card['label'] }}</p>
                            <p class="mt-2 text-2xl font-semibold tracking-tight {{ $card['tone'] }} tabular-nums">
                                Rs. {{ number_format($card['value'], 2) }}
                            </p>
                        </div>
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-50 text-slate-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 15l3-3 3 2 4-6" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-3 text-xs font-medium text-slate-400">{{ $card['caption'] }}</p>
                </article>
            @endforeach
        </div>

        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            @foreach ($secondaryCards as $card)
                <article class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-xs font-semibold text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-2 text-lg font-semibold tracking-tight {{ $card['tone'] }} tabular-nums">Rs. {{ number_format($card['value'], 2) }}</p>
                </article>
            @endforeach
        </div>

        <div class="mt-4 grid gap-4 xl:grid-cols-[1.15fr_1fr_0.9fr]">
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Sales Revenue</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 tabular-nums">Rs. {{ number_format((float) $analytics['cards']['total_paid'], 2) }}</p>
                        <p class="mt-2 text-sm font-medium text-slate-500">
                            Collected from Rs. {{ number_format((float) $analytics['cards']['total_billed'], 2) }} billed.
                        </p>
                    </div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">{{ $collectionRate }}%</span>
                </div>

                <div class="mt-6 h-48">
                    @if ($dailyRows->isNotEmpty())
                        <svg viewBox="0 0 360 190" class="h-full w-full overflow-visible" role="img" aria-label="Daily billed and collected chart">
                            <line x1="34" y1="160" x2="346" y2="160" stroke="#e2e8f0" stroke-width="1" />
                            <line x1="34" y1="112" x2="346" y2="112" stroke="#f1f5f9" stroke-width="1" />
                            <line x1="34" y1="64" x2="346" y2="64" stroke="#f1f5f9" stroke-width="1" />
                            @foreach ($dailyRows as $index => $summary)
                                @php
                                    $x = 52 + ($index * 52);
                                    $billedHeight = min(128, max(3, ((float) $summary['billed'] / $dailyAxisMax) * 128));
                                    $paidHeight = min(128, max(3, ((float) $summary['paid'] / $dailyAxisMax) * 128));
                                @endphp
                                <rect x="{{ $x }}" y="{{ 160 - $billedHeight }}" width="14" height="{{ $billedHeight }}" rx="4" fill="#d9dee7" />
                                <rect x="{{ $x + 18 }}" y="{{ 160 - $paidHeight }}" width="14" height="{{ $paidHeight }}" rx="4" fill="#34c7a1" />
                                <text x="{{ $x + 16 }}" y="182" text-anchor="middle" font-size="10" fill="#64748b">{{ \Illuminate\Support\Carbon::parse($summary['date'])->format('d') }}</text>
                            @endforeach
                        </svg>
                    @else
                        <div class="flex h-full items-center justify-center rounded-2xl border border-dashed border-slate-200 text-sm font-semibold text-slate-400">
                            No sales chart data
                        </div>
                    @endif
                </div>

                <div class="mt-3 flex flex-wrap gap-4 text-xs font-semibold text-slate-500">
                    <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-slate-300"></span>Billed</span>
                    <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-emerald-400"></span>Collected</span>
                </div>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
                <div class="flex items-start justify-between gap-4">
                    <div>
	                        <p class="text-sm font-semibold text-slate-500">Daily Receipt Balance</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight {{ $cashFlow >= 0 ? 'text-slate-950' : 'text-rose-700' }} tabular-nums">
                            Rs. {{ number_format($cashFlow, 2) }}
                        </p>
                        <p class="mt-2 text-sm font-medium text-slate-500">
	                            Tracks opening balance, cash credit, cash debit, and closing balance from receipts.
                        </p>
                    </div>
                    <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" />
                    </svg>
                </div>

                <div class="mt-7 space-y-4">
                    @foreach ($dailyRows as $summary)
                        @php
	                            $rowFlow = (float) ($summary['closing_balance'] ?? 0);
	                            $rowWidth = min(100, (abs($rowFlow) / $dailyGraphMax) * 100);
                        @endphp
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-2 text-xs font-semibold text-slate-500">
                                <span>{{ \Illuminate\Support\Carbon::parse($summary['date'])->format('d M') }}</span>
	                                <span class="{{ $rowFlow >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">Closing Rs. {{ number_format($rowFlow, 0) }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-slate-100">
                                <div class="h-2 rounded-full {{ $rowFlow >= 0 ? 'bg-emerald-400' : 'bg-rose-500' }}" style="width: {{ $rowWidth }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Cost Segmentation</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 tabular-nums">Rs. {{ number_format($donutTotal, 2) }}</p>
                    </div>
                    <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" />
                    </svg>
                </div>

                <div class="mt-6 flex items-center justify-center">
                    <div class="relative flex h-36 w-36 items-center justify-center rounded-full" style="background: conic-gradient(#fb7185 0 40%, #facc15 40% 70%, #34d399 70% 100%);">
                        <div class="flex h-24 w-24 flex-col items-center justify-center rounded-full bg-white text-center shadow-inner">
                            <span class="text-[11px] font-semibold text-slate-400">Total</span>
                            <span class="mt-1 text-lg font-semibold text-slate-950">Rs. {{ number_format($donutTotal, 0) }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 space-y-3">
                    @foreach ($donutSegments as $segment)
                        @php
                            $segmentPercent = round(((float) $segment['amount'] / $donutTotal) * 100);
                        @endphp
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="inline-flex min-w-0 items-center gap-2 font-medium text-slate-500">
                                <span class="h-2.5 w-2.5 rounded-full {{ explode(' ', $segment['class'])[0] }}"></span>
                                <span class="truncate">{{ $segment['label'] }}</span>
                            </span>
                            <span class="font-semibold {{ explode(' ', $segment['class'])[1] }}">{{ $segmentPercent }}%</span>
                        </div>
                    @endforeach
                </div>
            </article>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.05)]">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-500">Income by Category</p>
                    <p class="text-sm font-semibold text-slate-950">Rs. {{ number_format((float) $analytics['cards']['income'], 2) }}</p>
                </div>
                <div class="mt-5 space-y-4">
                    @forelse($analytics['income_breakdown']->take(6) as $row)
                        @php $width = min(100, ((float) $row['amount'] / $incomeTotal) * 100); @endphp
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-3 text-xs font-semibold text-slate-500">
                                <span class="truncate">{{ $row['label'] }}</span>
                                <span>Rs. {{ number_format((float) $row['amount'], 2) }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-slate-100">
                                <div class="h-2 rounded-full bg-emerald-400" style="width: {{ $width }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-200 p-5 text-center text-sm font-semibold text-slate-400">No income categories in this period.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.05)]">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-500">Expenses by Category</p>
                    <p class="text-sm font-semibold text-slate-950">Rs. {{ number_format((float) $analytics['cards']['expense'], 2) }}</p>
                </div>
                <div class="mt-5 space-y-4">
                    @forelse($analytics['expense_breakdown']->take(6) as $row)
                        @php $width = min(100, ((float) $row['amount'] / $expenseTotal) * 100); @endphp
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-3 text-xs font-semibold text-slate-500">
                                <span class="truncate">{{ $row['label'] }}</span>
                                <span>Rs. {{ number_format((float) $row['amount'], 2) }}</span>
                            </div>
                            <div class="h-2 rounded-full bg-slate-100">
                                <div class="h-2 rounded-full bg-rose-400" style="width: {{ $width }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-200 p-5 text-center text-sm font-semibold text-slate-400">No expense categories in this period.</p>
                    @endforelse
                </div>
            </article>
        </div>
    </div>
</section>
