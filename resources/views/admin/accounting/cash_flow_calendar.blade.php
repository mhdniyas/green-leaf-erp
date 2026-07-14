<x-layouts.accounting title="Calendar">
    @php
        $selectedDate = \Illuminate\Support\Carbon::parse($cashFlowReport['selected_date']);
        $selectedMonth = $selectedDate->copy()->startOfMonth();
        $previousMonth = $selectedMonth->copy()->subMonth();
        $nextMonth = $selectedMonth->copy()->addMonth();
        $dailyRowsByDate = collect($cashFlowReport['daily_rows'])->keyBy('date');
        $calendarStart = $selectedMonth->copy()->startOfWeek();
        $calendarEnd = $selectedMonth->copy()->endOfMonth()->endOfWeek();
        $calendarDays = collect();

        for ($cursor = $calendarStart->copy(); $cursor->lte($calendarEnd); $cursor->addDay()) {
            $calendarDays->push($cursor->copy());
        }
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Accounting / Calendar</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Calendar</h1>
                    <p class="mt-1 text-sm font-semibold text-slate-600">Select a day to see that day journal only.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.accounting.cash-flow.calendar', ['date' => $previousMonth->toDateString()]) }}" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-lg font-black text-slate-700 transition hover:border-cyan-200 hover:bg-white" title="Previous month">
                        &lsaquo;
                    </a>
                    <form method="GET" action="{{ route('admin.accounting.cash-flow.calendar') }}" class="flex h-11 items-center rounded-xl border border-slate-200 bg-slate-50 px-2" data-accounting-calendar-month-form>
                        <input type="month" name="month" value="{{ $selectedMonth->format('Y-m') }}" class="h-8 rounded-lg border border-slate-200 bg-white px-3 text-sm font-black text-slate-950 outline-none transition focus:border-cyan-400 focus:ring-4 focus:ring-cyan-100" data-accounting-calendar-month-input aria-label="Accounting calendar month">
                        <button type="submit" class="sr-only">Apply month</button>
                    </form>
                    <a href="{{ route('admin.accounting.cash-flow.calendar', ['date' => $nextMonth->toDateString()]) }}" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-lg font-black text-slate-700 transition hover:border-cyan-200 hover:bg-white" title="Next month">
                        &rsaquo;
                    </a>
                    <a href="{{ route('admin.accounting.cash-flow.export.excel', ['date' => $selectedDate->toDateString()]) }}" class="inline-flex h-11 items-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-emerald-700 transition hover:bg-emerald-100">
                        Export Excel
                    </a>
                    <a href="{{ route('admin.accounting.cash-flow.export.pdf', ['date' => $selectedDate->toDateString()]) }}" target="_blank" class="inline-flex h-11 items-center rounded-2xl border border-cyan-200 bg-cyan-50 px-4 text-xs font-black uppercase tracking-[0.16em] text-cyan-700 transition hover:bg-cyan-100">
                        Export PDF
                    </a>
                </div>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[20rem_minmax(0,1fr)]">
            <section class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Calendar</p>
                        <h2 class="mt-1 text-base font-black text-slate-950">Daily balance</h2>
                    </div>
                    <a href="{{ route('admin.accounting.cash-flow.calendar', ['date' => today()->toDateString()]) }}" class="text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700">Today</a>
                </div>

                <div class="mt-4 max-h-[38rem] space-y-2 overflow-y-auto pr-1">
                    @foreach($cashFlowReport['daily_rows'] as $calendarRow)
                        @php
                            $calendarDate = \Illuminate\Support\Carbon::parse($calendarRow['date']);
                            $isSelected = $calendarDate->isSameDay($selectedDate);
                        @endphp
                        <a
                            href="{{ route('admin.accounting.cash-flow.calendar', ['date' => $calendarDate->toDateString()]) }}"
                            class="block rounded-[1rem] border px-3 py-3 transition {{ $isSelected ? 'border-slate-950 bg-slate-950 text-white shadow-sm' : 'border-slate-200 bg-slate-50 hover:border-cyan-200 hover:bg-white' }}"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black {{ $isSelected ? 'text-white' : 'text-slate-950' }}">{{ $calendarDate->format('d M') }}</p>
                                    <p class="mt-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $isSelected ? 'text-slate-300' : 'text-slate-400' }}">{{ $calendarDate->format('D') }}</p>
                                </div>
                                <p class="text-right text-sm font-black {{ $isSelected ? 'text-white' : ((float) $calendarRow['balance'] >= 0 ? 'text-cyan-800' : 'text-rose-700') }}">Rs. {{ number_format((float) $calendarRow['balance'], 2) }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Day Journal</p>
                    <h2 class="mt-1 text-base font-black text-slate-950">Journal details for {{ $selectedDate->format('d M Y') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-right whitespace-nowrap">Amount</th>
                                <th class="px-4 py-3 whitespace-nowrap">Debit / Credit</th>
                                <th class="px-4 py-3 whitespace-nowrap">Journal</th>
                                <th class="px-4 py-3 min-w-[15rem]">Remarks</th>
                                <th class="px-4 py-3 whitespace-nowrap">Category</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse($cashFlowReport['selected_day_rows'] as $row)
                                <tr>
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
                                    <td class="px-4 py-3 text-sm font-semibold text-slate-600 min-w-[15rem]">{{ $row['remarks'] ?: 'No remarks' }}</td>
                                    <td class="px-4 py-3 text-sm font-black text-slate-700 whitespace-nowrap">{{ $row['category'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-sm font-bold text-slate-500">
                                        No journal rows are available for {{ $selectedDate->format('d M Y') }}.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 p-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Full Month Calendar</p>
                        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $selectedMonth->format('F Y') }}</h2>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3 xl:min-w-[38rem]">
                        <article class="rounded-2xl border border-emerald-200 bg-white px-4 py-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-emerald-700">Month In</p>
                            <p class="mt-1 text-lg font-black text-slate-950">Rs. {{ number_format((float) $cashFlowReport['summary']['total_in'], 2) }}</p>
                        </article>
                        <article class="rounded-2xl border border-amber-200 bg-white px-4 py-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-amber-700">Month Out</p>
                            <p class="mt-1 text-lg font-black text-slate-950">Rs. {{ number_format((float) $cashFlowReport['summary']['total_out'], 2) }}</p>
                        </article>
                        <article class="rounded-2xl border border-cyan-200 bg-white px-4 py-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-cyan-700">Closing</p>
                            <p class="mt-1 text-lg font-black text-slate-950">Rs. {{ number_format((float) ($dailyRowsByDate->get($selectedMonth->copy()->endOfMonth()->toDateString())['balance'] ?? $cashFlowReport['summary']['closing_balance']), 2) }}</p>
                        </article>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto bg-slate-100">
                <div class="min-w-[62rem]">
                    <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-950 text-center text-[10px] font-black uppercase tracking-[0.16em] text-slate-300">
                        @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                            <div class="px-2 py-3">{{ $weekday }}</div>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-7 gap-px bg-slate-200 p-px">
                        @foreach($calendarDays as $calendarDay)
                            @php
                                $dateKey = $calendarDay->toDateString();
                                $dayRow = $dailyRowsByDate->get($dateKey);
                                $isCurrentMonth = $calendarDay->isSameMonth($selectedMonth);
                                $isSelected = $calendarDay->isSameDay($selectedDate);
                                $inAmount = (float) ($dayRow['in_amount'] ?? 0);
                                $outAmount = (float) ($dayRow['out_amount'] ?? 0);
                                $balanceAmount = (float) ($dayRow['balance'] ?? 0);
                                $hasEntry = $inAmount > 0 || $outAmount > 0;
                            @endphp
                            <a
                                href="{{ route('admin.accounting.cash-flow.calendar', ['date' => $dateKey]) }}"
                                class="group min-h-40 p-3 transition {{ $isSelected ? 'bg-slate-950 text-white shadow-inner' : ($isCurrentMonth ? 'bg-white hover:bg-cyan-50' : 'bg-slate-50 text-slate-300 hover:bg-white') }}"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="inline-flex h-8 w-8 items-center justify-center rounded-full text-sm font-black {{ $isSelected ? 'bg-white text-slate-950' : ($calendarDay->isToday() ? 'bg-cyan-500 text-slate-950' : ($isCurrentMonth ? 'bg-slate-100 text-slate-950 group-hover:bg-white' : 'bg-white text-slate-300')) }}">{{ $calendarDay->format('j') }}</p>
                                        <p class="mt-2 text-[10px] font-black uppercase tracking-[0.14em] {{ $isSelected ? 'text-slate-300' : 'text-slate-400' }}">{{ $calendarDay->format('M') }}</p>
                                    </div>
                                    <span class="rounded-full px-2 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $isSelected ? 'bg-white/10 text-white' : ($hasEntry ? 'bg-cyan-50 text-cyan-700' : 'bg-slate-100 text-slate-400') }}">{{ $hasEntry ? 'Entry' : 'Clear' }}</span>
                                </div>

                                <div class="mt-4 space-y-2 text-xs font-black">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="{{ $isSelected ? 'text-slate-300' : 'text-emerald-700' }}">In</span>
                                        <span class="{{ $isSelected ? 'text-white' : ($isCurrentMonth ? 'text-slate-950' : 'text-slate-400') }}">Rs. {{ number_format($inAmount, 2) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="{{ $isSelected ? 'text-slate-300' : 'text-amber-700' }}">Out</span>
                                        <span class="{{ $isSelected ? 'text-white' : ($isCurrentMonth ? 'text-slate-950' : 'text-slate-400') }}">Rs. {{ number_format($outAmount, 2) }}</span>
                                    </div>
                                    <div class="border-t pt-2 {{ $isSelected ? 'border-white/15' : 'border-slate-100' }}">
                                        <p class="{{ $isSelected ? 'text-slate-300' : 'text-slate-400' }}">Balance</p>
                                        <p class="mt-1 text-sm {{ $isSelected ? 'text-white' : ($balanceAmount >= 0 ? 'text-cyan-800' : 'text-rose-700') }}">Rs. {{ number_format($balanceAmount, 2) }}</p>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-layouts.accounting>
