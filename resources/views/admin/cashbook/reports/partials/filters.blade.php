<section id="report-filters" class="white-card rounded-3xl p-4 sm:p-5">
    <form method="GET" action="{{ route('admin.cashbook.reports') }}" class="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
        <input type="hidden" name="tab" id="filter-tab-input" value="{{ request('tab', 'summary') }}">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Preset</span>
                <select name="timeframe" id="filter-timeframe" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-800">
                    @foreach(['daily' => 'Today / Date', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'custom' => 'Between Dates'] as $value => $label)
                        <option value="{{ $value }}" @selected($timeframe === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Report Date</span>
                <input type="date" name="date" id="filter-date" value="{{ $selectedDate }}" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-800">
            </label>
            <label class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">From</span>
                <input type="date" name="start_date" id="filter-start-date" value="{{ $startDate }}" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-800">
            </label>
            <label class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">To</span>
                <input type="date" name="end_date" id="filter-end-date" value="{{ $endDate }}" class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-800">
            </label>
        </div>
        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-slate-950 px-5 text-xs font-black uppercase tracking-[0.14em] text-white hover:bg-slate-800">
            Apply
        </button>
    </form>
</section>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const timeframeEl = document.getElementById('filter-timeframe');
        const dateEl = document.getElementById('filter-date');
        const startDateEl = document.getElementById('filter-start-date');
        const endDateEl = document.getElementById('filter-end-date');

        function onDateRangeChange() {
            if (startDateEl.value && endDateEl.value) {
                if (startDateEl.value !== endDateEl.value) {
                    timeframeEl.value = 'custom';
                }
            }
        }

        if (startDateEl && endDateEl) {
            startDateEl.addEventListener('change', onDateRangeChange);
            endDateEl.addEventListener('change', onDateRangeChange);
        }

        if (timeframeEl && dateEl) {
            timeframeEl.addEventListener('change', () => {
                const mode = timeframeEl.value;
                const d = dateEl.value ? new Date(dateEl.value) : new Date();
                if (mode === 'daily') {
                    startDateEl.value = dateEl.value;
                    endDateEl.value = dateEl.value;
                } else if (mode === 'weekly') {
                    const day = d.getDay();
                    const diffToMon = d.getDate() - day + (day === 0 ? -6 : 1);
                    const mon = new Date(d.setDate(diffToMon));
                    startDateEl.value = mon.toISOString().split('T')[0];
                    endDateEl.value = dateEl.value;
                } else if (mode === 'monthly') {
                    const firstDay = new Date(d.getFullYear(), d.getMonth(), 1);
                    startDateEl.value = firstDay.toISOString().split('T')[0];
                    endDateEl.value = dateEl.value;
                }
            });
        }
    });
</script>
