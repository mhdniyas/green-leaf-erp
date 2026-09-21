@php
    $currentMode = $period['period_mode'] ?? 'month';
    $reportType = $reportType ?? 'overview';
@endphp

<div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5" x-data="{ mode: '{{ $currentMode }}' }">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <!-- MODE SELECTOR & PREV/NEXT -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Period Nav Tabs -->
            <div class="inline-flex rounded-xl bg-slate-100 p-1 text-xs font-bold text-slate-600">
                <button type="button" @click="mode = 'month'" :class="mode === 'month' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'" class="rounded-lg px-3 py-1.5 transition">
                    Month
                </button>
                <button type="button" @click="mode = 'day'" :class="mode === 'day' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'" class="rounded-lg px-3 py-1.5 transition">
                    Day
                </button>
                <button type="button" @click="mode = 'custom'" :class="mode === 'custom' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'" class="rounded-lg px-3 py-1.5 transition">
                    Custom Range
                </button>
            </div>

            <!-- Previous Period Button -->
            <a href="{{ route($currentRoute, array_merge(request()->query(), $period['previous_period'])) }}"
               class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50"
               title="Previous Period">
                <i data-lucide="chevron-left" class="h-4 w-4"></i>
                <span class="hidden sm:inline">Prev</span>
            </a>

            <!-- Current Period Display Badge -->
            <span class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-100 bg-indigo-50/80 px-3 py-1.5 text-xs font-extrabold text-indigo-900">
                <i data-lucide="calendar" class="h-3.5 w-3.5 text-indigo-600"></i>
                {{ $period['period_label'] }}
            </span>

            <!-- Next Period Button -->
            <a href="{{ route($currentRoute, array_merge(request()->query(), $period['next_period'])) }}"
               class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50"
               title="Next Period">
                <span class="hidden sm:inline">Next</span>
                <i data-lucide="chevron-right" class="h-4 w-4"></i>
            </a>
        </div>

        <!-- FORM & EXPORT ACTIONS -->
        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Filter Form -->
            <form method="GET" action="{{ route($currentRoute) }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="period_mode" :value="mode">

                <!-- Month Picker -->
                <div x-show="mode === 'month'" class="flex items-center gap-1.5">
                    <input type="month" name="month" value="{{ $period['month'] }}"
                           class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-2xs focus:border-indigo-500 focus:outline-hidden focus:ring-1 focus:ring-indigo-500">
                </div>

                <!-- Single Day Picker -->
                <div x-show="mode === 'day'" class="flex items-center gap-1.5" x-cloak>
                    <input type="date" name="date" value="{{ $period['date'] }}"
                           class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-2xs focus:border-indigo-500 focus:outline-hidden focus:ring-1 focus:ring-indigo-500">
                </div>

                <!-- Custom Date Range Pickers -->
                <div x-show="mode === 'custom'" class="flex items-center gap-1.5" x-cloak>
                    <input type="date" name="from" value="{{ $period['from'] }}"
                           class="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 shadow-2xs focus:border-indigo-500 focus:outline-hidden focus:ring-1 focus:ring-indigo-500">
                    <span class="text-xs font-bold text-slate-400">to</span>
                    <input type="date" name="to" value="{{ $period['to'] }}"
                           class="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 shadow-2xs focus:border-indigo-500 focus:outline-hidden focus:ring-1 focus:ring-indigo-500">
                </div>

                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-700">
                    <i data-lucide="filter" class="h-3.5 w-3.5"></i>
                    Apply
                </button>
            </form>

            <!-- Export Dropdown -->
            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = !open" @click.outside="open = false"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-2xs transition hover:bg-slate-50">
                    <i data-lucide="download" class="h-3.5 w-3.5 text-slate-500"></i>
                    Export
                    <i data-lucide="chevron-down" class="h-3 w-3 text-slate-400"></i>
                </button>

                <div x-show="open" x-cloak
                     class="absolute right-0 z-30 mt-1.5 w-44 rounded-xl border border-slate-100 bg-white py-1.5 shadow-xl ring-1 ring-slate-900/5">
                    <a href="{{ route('admin.cashbook.monthly-report.export.csv', array_merge(['report' => $reportType], request()->query())) }}"
                       class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-indigo-600">
                        <i data-lucide="file-spreadsheet" class="h-3.5 w-3.5 text-emerald-600"></i>
                        Export CSV
                    </a>
                    <a href="{{ route('admin.cashbook.monthly-report.export.excel', array_merge(['report' => $reportType], request()->query())) }}"
                       class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-indigo-600">
                        <i data-lucide="table" class="h-3.5 w-3.5 text-blue-600"></i>
                        Export Excel (XLSX)
                    </a>
                    <a href="{{ route('admin.cashbook.monthly-report.export.pdf', array_merge(['report' => $reportType, 'download' => 1], request()->query())) }}"
                       class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-indigo-600">
                        <i data-lucide="file-text" class="h-3.5 w-3.5 text-rose-600"></i>
                        Download PDF
                    </a>
                    <a href="{{ route('admin.cashbook.monthly-report.export.pdf', array_merge(['report' => $reportType], request()->query())) }}" target="_blank"
                       class="flex items-center gap-2 border-t border-slate-100 px-3.5 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-indigo-600">
                        <i data-lucide="printer" class="h-3.5 w-3.5 text-slate-500"></i>
                        Print View
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
