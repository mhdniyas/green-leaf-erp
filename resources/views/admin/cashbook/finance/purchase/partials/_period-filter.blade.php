@php
    $periodMode = $filters['period_mode'] ?? $filters['period'] ?? 'month';
    if (in_array($periodMode, ['today', 'yesterday', 'day'], true)) {
        $periodMode = 'day';
    } elseif (in_array($periodMode, ['custom', 'between', 'range', 'week'], true)) {
        $periodMode = 'custom';
    } else {
        $periodMode = 'month';
    }

    $rawMonth = (string) ($filters['month'] ?? '');
    if (preg_match('/^\d{4}-\d{2}$/', $rawMonth)) {
        $month = $rawMonth;
    } elseif (!empty($filters['date'])) {
        $month = \Illuminate\Support\Carbon::parse((string) $filters['date'])->format('Y-m');
    } elseif (!empty($filters['start_date'])) {
        $month = \Illuminate\Support\Carbon::parse((string) $filters['start_date'])->format('Y-m');
    } elseif (!empty($filters['date_from'])) {
        $month = \Illuminate\Support\Carbon::parse((string) $filters['date_from'])->format('Y-m');
    } elseif (!empty($filters['date_b'])) {
        $month = \Illuminate\Support\Carbon::parse((string) $filters['date_b'])->format('Y-m');
    } else {
        $month = today('Asia/Kolkata')->format('Y-m');
    }

    $monthCarbon = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month);
    $monthStart = $filters['month_start'] ?? $monthCarbon->copy()->startOfMonth()->toDateString();
    $monthEnd = $filters['month_end'] ?? $monthCarbon->copy()->endOfMonth()->toDateString();
    $prevMonth = $filters['prev_month'] ?? $monthCarbon->copy()->subMonth()->format('Y-m');
    $nextMonth = $filters['next_month'] ?? $monthCarbon->copy()->addMonth()->format('Y-m');
    $monthTitle = $filters['month_title'] ?? $monthCarbon->format('F Y');

    $dateValue = $filters['date'] ?? $filters['day'] ?? $filters['start_date'] ?? $filters['date_from'] ?? $filters['date_b'] ?? $monthStart;
    $fromValue = $filters['from'] ?? $filters['start_date'] ?? $filters['date_from'] ?? $filters['date_a'] ?? $monthStart;
    $toValue = $filters['to'] ?? $filters['end_date'] ?? $filters['date_to'] ?? $filters['date_b'] ?? $monthEnd;

    $preservedParams = $extraParams ?? [];
    // Strip date-specific keys so month navigation cleanly resets to monthly view
    unset(
        $preservedParams['month'],
        $preservedParams['period_mode'],
        $preservedParams['date'],
        $preservedParams['day'],
        $preservedParams['from'],
        $preservedParams['to'],
        $preservedParams['start_date'],
        $preservedParams['end_date'],
        $preservedParams['date_from'],
        $preservedParams['date_to'],
        $preservedParams['date_a'],
        $preservedParams['date_b'],
        $preservedParams['period'],
        $preservedParams['quick_filter'],
        $preservedParams['chip'],
        $preservedParams['page']
    );

    $prevMonthQuery = array_filter(array_merge($preservedParams, [
        'month' => $prevMonth,
        'period_mode' => 'month',
    ]), fn ($v) => $v !== null && $v !== '');

    $nextMonthQuery = array_filter(array_merge($preservedParams, [
        'month' => $nextMonth,
        'period_mode' => 'month',
    ]), fn ($v) => $v !== null && $v !== '');

    $actionUrl = $action ?? url()->current();
    $resetUrl = $resetRoute ?? $actionUrl;
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-3 sm:p-4 shadow-xs" x-data="{ activeMode: '{{ $periodMode }}' }">
    <form method="GET" action="{{ $actionUrl }}" class="flex flex-col gap-3">
        <input type="hidden" name="month" value="{{ $month }}">
        <input type="hidden" name="period_mode" :value="activeMode">

        <!-- Top Period Bar -->
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <!-- Mode Switcher Buttons: Month | Day | Custom -->
            <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl w-fit flex-wrap border border-slate-200/70">
                <button type="submit"
                        @click="activeMode = 'month'"
                        name="period_mode"
                        value="month"
                        class="px-3 py-1.5 rounded-lg text-xs font-black transition cursor-pointer {{ $periodMode === 'month' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    Month
                </button>

                <button type="button"
                        @click="activeMode = 'day'"
                        class="px-3 py-1.5 rounded-lg text-xs font-black transition cursor-pointer {{ $periodMode === 'day' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    Day
                </button>

                <button type="button"
                        @click="activeMode = 'custom'"
                        class="px-3 py-1.5 rounded-lg text-xs font-black transition cursor-pointer {{ $periodMode === 'custom' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    Custom
                </button>
            </div>

            <!-- Month Navigator & Mode Controls -->
            <div class="flex flex-wrap items-center gap-2.5">
                <!-- Month Navigator -->
                <div class="flex items-center gap-1 bg-slate-50 border border-slate-200 rounded-xl p-0.5">
                    <a href="{{ $actionUrl }}?{{ http_build_query($prevMonthQuery) }}"
                       class="p-1.5 rounded-lg hover:bg-white text-slate-700 transition" title="Previous Month">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </a>
                    <span class="px-2.5 py-1 text-xs font-black text-slate-900 uppercase tracking-tight whitespace-nowrap">
                        {{ $monthTitle }}
                    </span>
                    <a href="{{ $actionUrl }}?{{ http_build_query($nextMonthQuery) }}"
                       class="p-1.5 rounded-lg hover:bg-white text-slate-700 transition" title="Next Month">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </a>
                </div>

                <!-- Day Mode Input -->
                <div x-show="activeMode === 'day'" class="flex flex-wrap items-center gap-1.5" style="{{ $periodMode === 'day' ? '' : 'display: none;' }}">
                    <label for="period_day_picker" class="text-xs font-bold text-slate-600 sr-only">Date:</label>
                    <input type="date"
                           id="period_day_picker"
                           name="date"
                           value="{{ $dateValue }}"
                           class="rounded-xl border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden max-w-[145px]">
                    <button type="submit" class="rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-black text-white hover:bg-slate-800 transition cursor-pointer">
                        Apply Day
                    </button>
                </div>

                <!-- Custom Range Mode Inputs -->
                <div x-show="activeMode === 'custom'" class="flex flex-wrap items-center gap-1.5" style="{{ $periodMode === 'custom' ? '' : 'display: none;' }}">
                    <div class="flex items-center gap-1">
                        <label for="period_from_picker" class="text-[11px] font-bold text-slate-500">From:</label>
                        <input type="date"
                               id="period_from_picker"
                               name="from"
                               value="{{ $fromValue }}"
                               class="rounded-xl border border-slate-300 bg-white px-2 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden max-w-[130px]">
                    </div>
                    <div class="flex items-center gap-1">
                        <label for="period_to_picker" class="text-[11px] font-bold text-slate-500">To:</label>
                        <input type="date"
                               id="period_to_picker"
                               name="to"
                               value="{{ $toValue }}"
                               class="rounded-xl border border-slate-300 bg-white px-2 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-600 focus:outline-hidden max-w-[130px]">
                    </div>
                    <button type="submit" class="rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-black text-white hover:bg-slate-800 transition cursor-pointer">
                        Apply Range
                    </button>
                </div>

                <!-- Quick Reset / Clear -->
                <a href="{{ $resetUrl }}" class="p-1.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition" title="Reset to Current Month">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                </a>
            </div>
        </div>

        <!-- Extra Report Specific Filters Slot -->
        @if(isset($slot) && trim($slot) !== '')
            <div class="border-t border-slate-100 pt-3">
                {{ $slot }}
            </div>
        @endif
    </form>
</div>
