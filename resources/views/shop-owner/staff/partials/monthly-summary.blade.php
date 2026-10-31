{{-- MONTHLY ATTENDANCE SUMMARY REPORT --}}
@php
    $years = range((int) today()->format('Y') - 4, (int) today()->format('Y') + 1);
    $monthNames = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];
@endphp

<div class="space-y-3.5">
    {{-- Top Section: Header & Month Quick-Navigation --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xs">
        <div>
            <div class="flex items-center gap-2">
                <h2 class="text-base font-black text-slate-950 sm:text-lg">{{ $calendarMonth->format('F Y') }} Attendance Summary</h2>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-800 border border-emerald-200">Monthly Report</span>
            </div>
            <p class="text-xs font-semibold text-slate-500">Employee-wise monthly attendance summary for {{ $selectedShop?->name ?? 'assigned shop' }}.</p>
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
            <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'tab' => 'monthly-summary', 'month' => $prevMonth->format('Y-m'), 'search' => $search, 'category' => $categoryCode]) }}"
               class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-xs hover:bg-slate-50 hover:text-slate-950 transition"
               title="Previous month ({{ $prevMonth->format('M Y') }})" aria-label="Previous month">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
            </a>

            <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'tab' => 'monthly-summary', 'month' => today()->format('Y-m'), 'search' => $search, 'category' => $categoryCode]) }}"
               class="inline-flex h-9 items-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-700 shadow-xs hover:bg-slate-50 transition">
                Current Month
            </a>

            <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'tab' => 'monthly-summary', 'month' => $nextMonth->format('Y-m'), 'search' => $search, 'category' => $categoryCode]) }}"
               class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-xs hover:bg-slate-50 hover:text-slate-950 transition"
               title="Next month ({{ $nextMonth->format('M Y') }})" aria-label="Next month">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </a>
        </div>
    </div>

    {{-- Filter Bar: Shop, Month, Year, Search, Category --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs">
        <form method="GET" action="{{ route('shop-owner.staff.index') }}" class="flex flex-wrap items-end gap-2.5">
            <input type="hidden" name="tab" value="monthly-summary">

            {{-- Shop Selector (authorized shops only) --}}
            <div class="w-full sm:w-auto min-w-44">
                <label for="filter-shop-select" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Shop</label>
                <select id="filter-shop-select" name="shop" class="h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
                    @foreach($shops as $shopOption)
                        <option value="{{ $shopOption->code }}" @selected($selectedShop?->code === $shopOption->code)>{{ $shopOption->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Month Selector --}}
            <div class="w-1/2 sm:w-auto min-w-32">
                <label for="filter-month-select" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Month</label>
                <select id="filter-month-select" name="month" class="h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
                    @foreach($monthNames as $mNum => $mName)
                        <option value="{{ sprintf('%02d', $mNum) }}" @selected($selectedMonthNum === $mNum)>{{ $mName }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Year Selector --}}
            <div class="w-1/2 sm:w-auto min-w-28">
                <label for="filter-year-select" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Year</label>
                <select id="filter-year-select" name="year" class="h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
                    @foreach($years as $yr)
                        <option value="{{ $yr }}" @selected($selectedYear === $yr)>{{ $yr }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Search Employee --}}
            <div class="w-full sm:w-auto flex-1 min-w-40">
                <label for="filter-search-input" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Search Employee</label>
                <input id="filter-search-input" type="search" name="search" value="{{ $search }}" placeholder="Search name, code..."
                       class="h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 placeholder:text-slate-400 focus:border-emerald-600 focus:ring-emerald-600">
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-1.5 pt-1">
                <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-slate-950 px-4 text-xs font-bold text-white shadow-xs hover:bg-slate-800 transition cursor-pointer">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" /></svg>
                    <span>Filter</span>
                </button>
                @if($search || $categoryCode || $selectedMonthNum !== (int) today()->format('n') || $selectedYear !== (int) today()->format('Y'))
                    <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'tab' => 'monthly-summary', 'month' => today()->format('Y-m')]) }}"
                       class="inline-flex h-9 items-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 shadow-xs hover:bg-slate-50 transition">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Monthly Summary Cards (5 Key Metrics) --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 sm:gap-2.5">
        {{-- Card 1: Total Employees --}}
        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs">
            <div class="flex items-center gap-1.5">
                <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                </div>
                <span class="text-[9px] font-black uppercase tracking-wider text-slate-500 truncate">Total Employees</span>
            </div>
            <p class="mt-2 text-2xl font-black text-slate-950 leading-none">{{ $monthlySummaryTotals['total_employees'] }}</p>
            <p class="mt-1 text-[10px] font-semibold text-slate-400">Assigned to shop</p>
        </div>

        {{-- Card 2: Total Present --}}
        <div class="rounded-xl border border-emerald-200 bg-white p-3 shadow-xs">
            <div class="flex items-center gap-1.5">
                <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
                <span class="text-[9px] font-black uppercase tracking-wider text-emerald-700 truncate">Total Present</span>
            </div>
            <p class="mt-2 text-2xl font-black text-emerald-700 leading-none">{{ $monthlySummaryTotals['present'] }}</p>
            <p class="mt-1 text-[10px] font-semibold text-emerald-600/80">Full day records</p>
        </div>

        {{-- Card 3: Total Absent --}}
        <div class="rounded-xl border border-rose-200 bg-white p-3 shadow-xs">
            <div class="flex items-center gap-1.5">
                <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-rose-50 text-rose-700 border border-rose-200">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </div>
                <span class="text-[9px] font-black uppercase tracking-wider text-rose-700 truncate">Total Absent</span>
            </div>
            <p class="mt-2 text-2xl font-black text-rose-700 leading-none">{{ $monthlySummaryTotals['absent'] }}</p>
            <p class="mt-1 text-[10px] font-semibold text-rose-600/80">Marked absent</p>
        </div>

        {{-- Card 4: Total Leave --}}
        <div class="rounded-xl border border-cyan-200 bg-white p-3 shadow-xs">
            <div class="flex items-center gap-1.5">
                <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-cyan-50 text-cyan-800 border border-cyan-200">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                </div>
                <span class="text-[9px] font-black uppercase tracking-wider text-cyan-800 truncate">Total Leave</span>
            </div>
            <p class="mt-2 text-2xl font-black text-cyan-800 leading-none">{{ $monthlySummaryTotals['leave'] }}</p>
            <p class="mt-1 text-[10px] font-semibold text-cyan-700/80">Leave records</p>
        </div>

        {{-- Card 5: Other Attendance --}}
        <div class="col-span-2 sm:col-span-1 rounded-xl border border-amber-200 bg-white p-3 shadow-xs">
            <div class="flex items-center gap-1.5">
                <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-amber-50 text-amber-800 border border-amber-200">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <span class="text-[9px] font-black uppercase tracking-wider text-amber-800 truncate">Other Attendance</span>
            </div>
            <p class="mt-2 text-2xl font-black text-amber-800 leading-none">{{ $monthlySummaryTotals['total_other_attendance'] }}</p>
            <p class="mt-1 text-[10px] font-semibold text-amber-700/80">
                {{ $monthlySummaryTotals['half_day'] }} Half Day{{ $monthlySummaryTotals['half_day'] === 1 ? '' : 's' }}@if($monthlySummaryTotals['other'] > 0), {{ $monthlySummaryTotals['other'] }} other @endif
            </p>
        </div>
    </div>

    {{-- Employee-wise Monthly Attendance Summary Table --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-3.5 py-2.5 bg-slate-50/50">
            <div>
                <h3 class="text-xs sm:text-sm font-black text-slate-950">Employee Attendance Summary — {{ $calendarMonth->format('F Y') }}</h3>
                <p class="text-[11px] font-semibold text-slate-500">Click any row to view complete day-by-day attendance details for that employee.</p>
            </div>
            <span class="rounded-full bg-slate-100 border border-slate-200 px-2.5 py-0.5 text-[10px] font-bold text-slate-600">
                {{ $monthlySummaryRows->count() }} {{ $monthlySummaryRows->count() === 1 ? 'Employee' : 'Employees' }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-600">
                        <th scope="col" class="py-2.5 px-3 sm:px-4">Employee Name</th>
                        <th scope="col" class="py-2.5 px-3 text-right">Present</th>
                        <th scope="col" class="py-2.5 px-3 text-right">Absent</th>
                        <th scope="col" class="py-2.5 px-3 text-right">Leave</th>
                        <th scope="col" class="py-2.5 px-3 text-right">Half Day</th>
                        <th scope="col" class="py-2.5 px-3 text-right">Other</th>
                        <th scope="col" class="py-2.5 px-3 text-center w-24">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($monthlySummaryRows as $row)
                        @php
                            $emp = $row['employee'];
                            $summaryJson = json_encode([
                                'employee_name' => $emp->name,
                                'employee_code' => $emp->employee_code,
                                'category' => $row['category_name'],
                                'shop_name' => $selectedShop?->name ?? 'Shop Staff',
                                'month_label' => $calendarMonth->format('F Y'),
                                'present' => $row['present'],
                                'absent' => $row['absent'],
                                'leave' => $row['leave'],
                                'half_day' => $row['half_day'],
                                'other' => $row['other'],
                                'total_marked' => $row['total_marked'],
                                'records' => $row['record_details'],
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
                        @endphp
                        <tr class="group hover:bg-slate-50/80 transition cursor-pointer js-summary-row"
                            data-summary="{{ $summaryJson }}"
                            title="Click to view {{ $emp->name }} monthly attendance details">
                            <td class="py-2.5 px-3 sm:px-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-900 text-[10px] font-bold text-white uppercase">
                                        {{ substr($emp->name, 0, 2) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-950 truncate leading-tight group-hover:text-emerald-700 transition">{{ $emp->name }}</p>
                                        <p class="text-[10px] font-semibold text-slate-400 truncate leading-tight">{{ $emp->employee_code }} · {{ $row['category_name'] }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-2.5 px-3 text-right font-black text-emerald-700">
                                <span class="inline-block rounded-md px-1.5 py-0.5 {{ $row['present'] > 0 ? 'bg-emerald-50 text-emerald-800' : 'text-slate-400' }}">
                                    {{ $row['present'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-black text-rose-700">
                                <span class="inline-block rounded-md px-1.5 py-0.5 {{ $row['absent'] > 0 ? 'bg-rose-50 text-rose-800' : 'text-slate-400' }}">
                                    {{ $row['absent'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-black text-cyan-800">
                                <span class="inline-block rounded-md px-1.5 py-0.5 {{ $row['leave'] > 0 ? 'bg-cyan-50 text-cyan-900' : 'text-slate-400' }}">
                                    {{ $row['leave'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-black text-amber-700">
                                <span class="inline-block rounded-md px-1.5 py-0.5 {{ $row['half_day'] > 0 ? 'bg-amber-50 text-amber-900' : 'text-slate-400' }}">
                                    {{ $row['half_day'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-black text-slate-700">
                                <span class="inline-block rounded-md px-1.5 py-0.5 {{ $row['other'] > 0 ? 'bg-slate-100 text-slate-800' : 'text-slate-400' }}">
                                    {{ $row['other'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-center">
                                <button type="button"
                                        class="js-btn-open-details inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-bold text-slate-700 shadow-xs hover:bg-slate-100 hover:text-emerald-700 transition cursor-pointer"
                                        aria-label="View attendance details for {{ $emp->name }}">
                                    <span>Details</span>
                                    <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-xs font-semibold text-slate-400">
                                No staff attendance records found for this shop in {{ $calendarMonth->format('F Y') }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($monthlySummaryRows->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-slate-300 bg-slate-50/90 text-xs font-black text-slate-950">
                            <th scope="row" class="py-3 px-3 sm:px-4 uppercase tracking-wider text-[11px]">
                                Total
                            </th>
                            <td class="py-3 px-3 text-right text-emerald-800 font-black">
                                <span class="rounded bg-emerald-100 px-1.5 py-0.5">{{ $monthlySummaryTotals['present'] }}</span>
                            </td>
                            <td class="py-3 px-3 text-right text-rose-800 font-black">
                                <span class="rounded bg-rose-100 px-1.5 py-0.5">{{ $monthlySummaryTotals['absent'] }}</span>
                            </td>
                            <td class="py-3 px-3 text-right text-cyan-900 font-black">
                                <span class="rounded bg-cyan-100 px-1.5 py-0.5">{{ $monthlySummaryTotals['leave'] }}</span>
                            </td>
                            <td class="py-3 px-3 text-right text-amber-900 font-black">
                                <span class="rounded bg-amber-100 px-1.5 py-0.5">{{ $monthlySummaryTotals['half_day'] }}</span>
                            </td>
                            <td class="py-3 px-3 text-right text-slate-900 font-black">
                                <span class="rounded bg-slate-200 px-1.5 py-0.5">{{ $monthlySummaryTotals['other'] }}</span>
                            </td>
                            <td class="py-3 px-3 text-center text-[10px] font-bold text-slate-400">
                                Overall
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>

    {{-- Employee Monthly Attendance Details Modal --}}
    <div id="monthly-summary-details-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="modal-employee-title">
        <div class="relative w-full max-w-lg mx-auto rounded-2xl bg-white p-4 sm:p-5 shadow-2xl border border-slate-200 space-y-3.5 z-10 text-xs">
            {{-- Modal Header --}}
            <div class="flex items-start justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div id="modal-employee-initials" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-900 text-sm font-bold text-white uppercase">
                        EM
                    </div>
                    <div>
                        <h3 id="modal-employee-title" class="text-sm sm:text-base font-black text-slate-950 leading-tight">Employee Details</h3>
                        <p id="modal-employee-meta" class="text-[11px] font-semibold text-slate-400 leading-tight">Staff information</p>
                    </div>
                </div>
                <button type="button" id="btn-close-summary-modal" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer" aria-label="Close modal">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>

            {{-- Mini Status Metric Pills --}}
            <div class="grid grid-cols-5 gap-1.5 text-center">
                <div class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-1.5">
                    <span class="block text-[8px] font-black uppercase text-emerald-800">Present</span>
                    <span id="modal-count-present" class="text-xs font-black text-emerald-800">0</span>
                </div>
                <div class="rounded-lg border border-rose-200 bg-rose-50/60 p-1.5">
                    <span class="block text-[8px] font-black uppercase text-rose-800">Absent</span>
                    <span id="modal-count-absent" class="text-xs font-black text-rose-800">0</span>
                </div>
                <div class="rounded-lg border border-cyan-200 bg-cyan-50/60 p-1.5">
                    <span class="block text-[8px] font-black uppercase text-cyan-800">Leave</span>
                    <span id="modal-count-leave" class="text-xs font-black text-cyan-800">0</span>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-1.5">
                    <span class="block text-[8px] font-black uppercase text-amber-800">Half Day</span>
                    <span id="modal-count-halfday" class="text-xs font-black text-amber-800">0</span>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-1.5">
                    <span class="block text-[8px] font-black uppercase text-slate-600">Other</span>
                    <span id="modal-count-other" class="text-xs font-black text-slate-700">0</span>
                </div>
            </div>

            {{-- Month Attendance Records List --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <h4 class="text-[11px] font-black uppercase tracking-wider text-slate-700">Recorded Dates (<span id="modal-record-count">0</span>)</h4>
                    <span class="text-[10px] font-semibold text-slate-400">Read-Only History</span>
                </div>

                <div id="modal-records-container" class="max-h-60 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100">
                    {{-- Dynamically populated by JavaScript --}}
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="flex items-center justify-end border-t border-slate-100 pt-3">
                <button type="button" id="btn-close-summary-modal-footer" class="rounded-xl border border-slate-200 bg-slate-100 px-4 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 transition cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Inline Vanilla JavaScript for Summary Details Modal --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('monthly-summary-details-modal');
        const btnClose = document.getElementById('btn-close-summary-modal');
        const btnCloseFooter = document.getElementById('btn-close-summary-modal-footer');
        const modalTitle = document.getElementById('modal-employee-title');
        const modalMeta = document.getElementById('modal-employee-meta');
        const modalInitials = document.getElementById('modal-employee-initials');
        const countPresent = document.getElementById('modal-count-present');
        const countAbsent = document.getElementById('modal-count-absent');
        const countLeave = document.getElementById('modal-count-leave');
        const countHalfDay = document.getElementById('modal-count-halfday');
        const countOther = document.getElementById('modal-count-other');
        const recordCount = document.getElementById('modal-record-count');
        const recordsContainer = document.getElementById('modal-records-container');

        const statusBadges = {
            present: '<span class="inline-flex items-center rounded-md bg-emerald-100 border border-emerald-200 px-1.5 py-0.5 text-[9px] font-bold text-emerald-800">Present</span>',
            absent: '<span class="inline-flex items-center rounded-md bg-rose-100 border border-rose-200 px-1.5 py-0.5 text-[9px] font-bold text-rose-800">Absent</span>',
            half_day: '<span class="inline-flex items-center rounded-md bg-amber-100 border border-amber-200 px-1.5 py-0.5 text-[9px] font-bold text-amber-800">Half Day</span>',
            leave: '<span class="inline-flex items-center rounded-md bg-cyan-100 border border-cyan-200 px-1.5 py-0.5 text-[9px] font-bold text-cyan-800">Leave</span>'
        };

        function openModalWithData(data) {
            if (!modal) return;

            modalTitle.textContent = data.employee_name;
            modalMeta.textContent = (data.employee_code || '') + ' · ' + (data.category || 'Staff') + ' · ' + (data.shop_name || '') + ' (' + data.month_label + ')';
            modalInitials.textContent = (data.employee_name ? data.employee_name.substring(0, 2) : 'EM').toUpperCase();

            countPresent.textContent = data.present || 0;
            countAbsent.textContent = data.absent || 0;
            countLeave.textContent = data.leave || 0;
            countHalfDay.textContent = data.half_day || 0;
            countOther.textContent = data.other || 0;

            const records = data.records || [];
            recordCount.textContent = records.length;

            if (records.length === 0) {
                recordsContainer.innerHTML = '<div class="p-4 text-center text-xs font-semibold text-slate-400">No attendance records recorded for this employee in ' + data.month_label + '.</div>';
            } else {
                let html = '';
                records.forEach(function (rec) {
                    const badge = statusBadges[rec.status] || '<span class="inline-flex items-center rounded-md bg-slate-100 border border-slate-200 px-1.5 py-0.5 text-[9px] font-bold text-slate-800">' + (rec.status_label || rec.status) + '</span>';
                    const notesHtml = rec.notes ? '<p class="mt-0.5 text-[10px] text-slate-500 italic">Notes: ' + escapeHtml(rec.notes) + '</p>' : '';
                    html += '<div class="p-2 sm:p-2.5 flex items-center justify-between gap-2 hover:bg-slate-50 transition">' +
                        '<div class="min-w-0">' +
                            '<div class="flex items-center gap-1.5">' +
                                '<span class="text-[11px] font-bold text-slate-900">' + rec.date + '</span>' +
                                '<span class="text-[10px] font-semibold text-slate-400">(' + rec.day + ')</span>' +
                            '</div>' +
                            '<div class="text-[10px] font-semibold text-slate-400">' +
                                'Marked at ' + rec.marked_at + ' by ' + escapeHtml(rec.marked_by) + ' (' + rec.source + ')' +
                            '</div>' +
                            notesHtml +
                        '</div>' +
                        '<div class="shrink-0">' + badge + '</div>' +
                    '</div>';
                });
                recordsContainer.innerHTML = html;
            }

            modal.classList.remove('hidden');
        }

        function closeModal() {
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        document.querySelectorAll('.js-summary-row').forEach(function (row) {
            row.addEventListener('click', function (e) {
                const raw = this.getAttribute('data-summary');
                if (!raw) return;
                try {
                    const data = JSON.parse(raw);
                    openModalWithData(data);
                } catch (err) {
                    console.error('Failed to parse employee summary data', err);
                }
            });
        });

        if (btnClose) btnClose.addEventListener('click', closeModal);
        if (btnCloseFooter) btnCloseFooter.addEventListener('click', closeModal);

        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    closeModal();
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });
    });
</script>
