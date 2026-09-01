@props([
    'mode' => 'range', // 'range', 'month', 'as_of', 'link'
    'startField' => 'start_date',
    'endField' => 'end_date',
    'monthField' => 'month',
    'dateField' => 'date',
    'class' => '',
    'label' => null,
    'size' => 'md', // 'xs', 'sm', 'md'
    'href' => null,
    'timeframe' => 'custom',
])

@php
    $prevDate = now()->startOfMonth()->subDay();
    $prevStart = $prevDate->copy()->startOfMonth()->toDateString();
    $prevEnd = $prevDate->copy()->endOfMonth()->toDateString();
    $prevMonthYm = $prevDate->format('Y-m');
    $prevMonthName = $prevDate->format('F');
    $buttonLabel = $label ?? $prevMonthName;

    $baseClasses = match ($size) {
        'xs' => 'inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-extrabold text-slate-700 hover:bg-emerald-50 hover:text-emerald-800 hover:border-emerald-300 transition shadow-2xs whitespace-nowrap cursor-pointer',
        'sm' => 'inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-extrabold text-slate-700 hover:bg-emerald-50 hover:text-emerald-800 hover:border-emerald-300 transition shadow-2xs whitespace-nowrap cursor-pointer',
        default => 'inline-flex items-center justify-center gap-1.5 min-h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-extrabold text-slate-700 hover:bg-emerald-50 hover:text-emerald-800 hover:border-emerald-300 transition shadow-2xs whitespace-nowrap cursor-pointer',
    };
@endphp

@if ($mode === 'range')
    <button
        type="button"
        title="Filter by previous month: {{ $prevMonthName }} ({{ $prevStart }} to {{ $prevEnd }})"
        onclick="(function(btn) {
            const form = btn.closest('form');
            if (!form) return;
            const startInput = form.querySelector('input[name=&quot;{{ $startField }}&quot;]');
            const endInput = form.querySelector('input[name=&quot;{{ $endField }}&quot;]');
            if (startInput) startInput.value = '{{ $prevStart }}';
            if (endInput) endInput.value = '{{ $prevEnd }}';
            const pageInput = form.querySelector('input[name=&quot;page&quot;]');
            if (pageInput) pageInput.value = '1';
            const timeframeInput = form.querySelector('input[name=&quot;timeframe&quot;]');
            if (timeframeInput) timeframeInput.value = '{{ $timeframe }}';
            form.submit();
        })(this)"
        class="{{ $baseClasses }} {{ $class }}"
    >
        <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
        </svg>
        <span>{{ $buttonLabel }}</span>
    </button>
@elseif ($mode === 'month')
    <button
        type="button"
        title="Filter by previous month: {{ $prevMonthName }} ({{ $prevMonthYm }})"
        onclick="(function(btn) {
            const form = btn.closest('form');
            if (!form) return;
            const monthInput = form.querySelector('input[name=&quot;{{ $monthField }}&quot;]');
            if (monthInput) monthInput.value = '{{ $prevMonthYm }}';
            const pageInput = form.querySelector('input[name=&quot;page&quot;]');
            if (pageInput) pageInput.value = '1';
            form.submit();
        })(this)"
        class="{{ $baseClasses }} {{ $class }}"
    >
        <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
        </svg>
        <span>{{ $buttonLabel }}</span>
    </button>
@elseif ($mode === 'as_of')
    <button
        type="button"
        title="Filter as of end of previous month ({{ $prevEnd }})"
        onclick="(function(btn) {
            const form = btn.closest('form');
            if (!form) return;
            const dateInput = form.querySelector('input[name=&quot;{{ $dateField }}&quot;]');
            if (dateInput) dateInput.value = '{{ $prevEnd }}';
            const pageInput = form.querySelector('input[name=&quot;page&quot;]');
            if (pageInput) pageInput.value = '1';
            form.submit();
        })(this)"
        class="{{ $baseClasses }} {{ $class }}"
    >
        <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
        </svg>
        <span>{{ $buttonLabel }}</span>
    </button>
@elseif ($mode === 'link')
    @php
        $targetUrl = $href ?: request()->fullUrlWithQuery([
            $startField => $prevStart,
            $endField => $prevEnd,
            'timeframe' => $timeframe,
            'page' => 1,
        ]);
    @endphp
    <a
        href="{{ $targetUrl }}"
        title="Filter by previous month: {{ $prevMonthName }} ({{ $prevStart }} to {{ $prevEnd }})"
        class="{{ $baseClasses }} {{ $class }}"
    >
        <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
        </svg>
        <span>{{ $buttonLabel }}</span>
    </a>
@endif
