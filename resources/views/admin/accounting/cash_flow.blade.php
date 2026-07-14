<x-layouts.accounting title="Cash Flow Report">
    @php
        $prevDate = $date->copy()->subDay()->format('Y-m-d');
        $nextDate = $date->copy()->addDay()->format('Y-m-d');
        $todayDate = today()->toDateString();
        $cashFlowTab = request()->string('cash_tab')->toString();
        $cashFlowTab = in_array($cashFlowTab, ['journal', 'day-journal', 'daily-balance', 'cash-paid', 'cash-received'], true) ? $cashFlowTab : 'journal';
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        <section class="space-y-4 rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Accounting / Cash Flow</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Cash Flow Report</h1>
                    <p class="mt-1 text-sm font-semibold text-slate-600">Combined purchaser and owned shop cash journal.</p>
                </div>

                <form method="GET" action="{{ route('admin.accounting.cash-flow') }}" class="flex flex-wrap items-center gap-2 rounded-[1.2rem] border border-slate-200 bg-slate-50 p-2">
                    <input type="hidden" name="cash_tab" value="{{ $cashFlowTab }}">
                    <a href="{{ route('admin.accounting.cash-flow', ['date' => $prevDate, 'cash_tab' => $cashFlowTab]) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[1rem] border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-100" title="Previous day">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </a>
                    <label class="min-w-[11rem] rounded-[1rem] border border-slate-200 bg-white px-3 py-2 text-slate-900 shadow-sm">
                        <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Business Date</span>
                        <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" onchange="this.form.submit()" class="mt-1 w-full border-0 bg-transparent p-0 text-sm font-black text-slate-900 focus:outline-none focus:ring-0">
                    </label>
                    @if($date->format('Y-m-d') !== $todayDate)
                        <a href="{{ route('admin.accounting.cash-flow', ['date' => $todayDate, 'cash_tab' => $cashFlowTab]) }}" class="inline-flex h-10 items-center justify-center rounded-[1rem] border border-slate-200 bg-white px-3 text-[10px] font-black uppercase tracking-[0.16em] text-slate-950 transition hover:bg-slate-100">
                            Today
                        </a>
                    @endif
                    <a href="{{ route('admin.accounting.cash-flow', ['date' => $nextDate, 'cash_tab' => $cashFlowTab]) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[1rem] border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-100" title="Next day">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </form>
            </div>
        </section>

        @include('admin.accounting.partials.cash-flow-report')
    </div>
</x-layouts.accounting>
