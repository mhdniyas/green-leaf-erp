{{-- ADMIN MONTHLY ATTENDANCE SUMMARY REPORT --}}
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

<div class="space-y-4">
    {{-- Top Section: Header & Month Quick-Navigation --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
        <div>
            <div class="flex items-center gap-2">
                <h2 class="text-base font-black text-slate-950 sm:text-lg">{{ $calendarMonth->format('F Y') }} Attendance Summary</h2>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-800 border border-emerald-200">Monthly Report</span>
                @if($selectedShop)
                    <span class="inline-flex items-center rounded-full bg-cyan-50 px-2 py-0.5 text-[10px] font-bold text-cyan-800 border border-cyan-200">{{ $selectedShop->name }}</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700 border border-slate-200">All Shops Aggregated</span>
                @endif
            </div>
            <p class="text-xs font-semibold text-slate-500">
                @if($selectedShop)
                    Employee-wise monthly attendance for {{ $selectedShop->name }}.
                @else
                    Comprehensive monthly attendance summary across all managed shops.
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
            <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->query(), ['tab' => 'monthly-summary', 'month' => $prevMonth->format('Y-m'), 'year' => $prevMonth->format('Y')])) }}"
               class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-xs hover:bg-slate-50 hover:text-slate-950 transition"
               title="Previous month ({{ $prevMonth->format('M Y') }})" aria-label="Previous month">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
            </a>

            <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->query(), ['tab' => 'monthly-summary', 'month' => today()->format('Y-m'), 'year' => today()->format('Y')])) }}"
               class="inline-flex h-9 items-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-700 shadow-xs hover:bg-slate-50 transition">
                Current Month
            </a>

            <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->query(), ['tab' => 'monthly-summary', 'month' => $nextMonth->format('Y-m'), 'year' => $nextMonth->format('Y')])) }}"
               class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-xs hover:bg-slate-50 hover:text-slate-950 transition"
               title="Next month ({{ $nextMonth->format('M Y') }})" aria-label="Next month">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </a>
        </div>
    </div>

    {{-- Filter Bar: Shop, Month, Year, Category, Search --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xs">
        <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-end gap-2.5">
            <input type="hidden" name="tab" value="monthly-summary">

            {{-- Shop Selector (All Shops option included) --}}
            <div class="w-full sm:w-auto min-w-44">
                <label for="admin-filter-shop-select" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Shop</label>
                <select id="admin-filter-shop-select" name="shop_id" class="h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
                    <option value="" @selected(empty($selectedShopId))>All Shops</option>
                    @foreach($shops as $shopOption)
                        <option value="{{ $shopOption->id }}" @selected((int) $selectedShopId === (int) $shopOption->id)>{{ $shopOption->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Month Selector --}}
            <div class="w-1/2 sm:w-auto min-w-32">
                <label for="admin-filter-month-select" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Month</label>
                <select id="admin-filter-month-select" name="month" class="h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
                    @foreach($monthNames as $mNum => $mName)
                        <option value="{{ sprintf('%02d', $mNum) }}" @selected($selectedMonthNum === $mNum)>{{ $mName }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Year Selector --}}
            <div class="w-1/2 sm:w-auto min-w-28">
                <label for="admin-filter-year-select" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Year</label>
                <select id="admin-filter-year-select" name="year" class="h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
                    @foreach($years as $yr)
                        <option value="{{ $yr }}" @selected($selectedYear === $yr)>{{ $yr }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Category Filter --}}
            @if(isset($categories) && $categories->isNotEmpty())
                <div class="w-full sm:w-auto min-w-36">
                    <label for="admin-filter-category-select" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Category</label>
                    <select id="admin-filter-category-select" name="category" class="h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
                        <option value="">All Categories</option>
                        @foreach($categories as $categoryOption)
                            <option value="{{ $categoryOption->code }}" @selected($categoryCode === $categoryOption->code)>{{ $categoryOption->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Search Employee --}}
            <div class="w-full sm:w-auto flex-1 min-w-40">
                <label for="admin-filter-search-input" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Search Employee</label>
                <input id="admin-filter-search-input" type="search" name="search" value="{{ $search }}" placeholder="Search name, code..."
                       class="h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 placeholder:text-slate-400 focus:border-emerald-600 focus:ring-emerald-600">
            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center gap-1.5 pt-1">
                <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-slate-950 px-4 text-xs font-bold text-white shadow-xs hover:bg-slate-800 transition cursor-pointer">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" /></svg>
                    <span>Filter</span>
                </button>
                @if($search || $categoryCode || $selectedShopId || $selectedMonthNum !== (int) today()->format('n') || $selectedYear !== (int) today()->format('Y'))
                    <a href="{{ url()->current() }}?tab=monthly-summary"
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
            <p class="mt-1 text-[10px] font-semibold text-slate-400">
                {{ $selectedShop ? 'Assigned to shop' : 'Unique staff across shops' }}
            </p>
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
            <p class="mt-1 text-[10px] font-semibold text-cyan-700/80">Approved leaves</p>
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

    {{-- Admin All-Shops Summary Table (Visible only when All Shops is selected) --}}
    @if(empty($selectedShopId) && isset($shopSummaries) && $shopSummaries->isNotEmpty())
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3 bg-slate-50/60">
                <div>
                    <h3 class="text-xs sm:text-sm font-black text-slate-950">Shop-wise Attendance Summary — {{ $calendarMonth->format('F Y') }}</h3>
                    <p class="text-[11px] font-semibold text-slate-500">Aggregate attendance performance by shop. Click "View Shop" to inspect shop staff details.</p>
                </div>
                <span class="rounded-full bg-slate-100 border border-slate-200 px-2.5 py-0.5 text-[10px] font-bold text-slate-600">
                    {{ $shopSummaries->count() }} Shops
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-600">
                            <th scope="col" class="py-2.5 px-3 sm:px-4">Shop Name</th>
                            <th scope="col" class="py-2.5 px-3 text-right">Employees</th>
                            <th scope="col" class="py-2.5 px-3 text-right">Present</th>
                            <th scope="col" class="py-2.5 px-3 text-right">Absent</th>
                            <th scope="col" class="py-2.5 px-3 text-right">Leave</th>
                            <th scope="col" class="py-2.5 px-3 text-right">Other</th>
                            <th scope="col" class="py-2.5 px-3 text-center w-28">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($shopSummaries as $shopSummary)
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-2.5 px-3 sm:px-4 font-bold text-slate-950">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-emerald-100 text-[10px] font-black text-emerald-800">
                                            {{ substr($shopSummary['shop_name'], 0, 1) }}
                                        </span>
                                        <div>
                                            <p class="leading-tight text-slate-900 font-bold">{{ $shopSummary['shop_name'] }}</p>
                                            <p class="text-[10px] font-semibold text-slate-400 leading-tight">{{ $shopSummary['shop_code'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3 text-right font-black text-slate-700">
                                    {{ $shopSummary['employee_count'] }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-black text-emerald-700">
                                    <span class="inline-block rounded-md px-1.5 py-0.5 {{ $shopSummary['present'] > 0 ? 'bg-emerald-50 text-emerald-800' : 'text-slate-400' }}">
                                        {{ $shopSummary['present'] }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-black text-rose-700">
                                    <span class="inline-block rounded-md px-1.5 py-0.5 {{ $shopSummary['absent'] > 0 ? 'bg-rose-50 text-rose-800' : 'text-slate-400' }}">
                                        {{ $shopSummary['absent'] }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-black text-cyan-800">
                                    <span class="inline-block rounded-md px-1.5 py-0.5 {{ $shopSummary['leave'] > 0 ? 'bg-cyan-50 text-cyan-800' : 'text-slate-400' }}">
                                        {{ $shopSummary['leave'] }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-black text-amber-800">
                                    <span class="inline-block rounded-md px-1.5 py-0.5 {{ $shopSummary['total_other_attendance'] > 0 ? 'bg-amber-50 text-amber-800' : 'text-slate-400' }}">
                                        {{ $shopSummary['total_other_attendance'] }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->query(), ['tab' => 'monthly-summary', 'shop_id' => $shopSummary['shop_id'], 'month' => $calendarMonth->format('Y-m'), 'year' => $calendarMonth->format('Y')])) }}"
                                       class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-bold text-slate-700 shadow-2xs hover:bg-slate-100 hover:text-slate-950 transition">
                                        <span>View Shop</span>
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-slate-300 bg-slate-100 font-black text-slate-950">
                            <td class="py-3 px-3 sm:px-4 font-black">Total</td>
                            <td class="py-3 px-3 text-right text-slate-900">{{ $shopTotals['employee_count'] ?? $monthlySummaryTotals['total_employees'] }}</td>
                            <td class="py-3 px-3 text-right text-emerald-800">{{ $shopTotals['present'] ?? $monthlySummaryTotals['present'] }}</td>
                            <td class="py-3 px-3 text-right text-rose-800">{{ $shopTotals['absent'] ?? $monthlySummaryTotals['absent'] }}</td>
                            <td class="py-3 px-3 text-right text-cyan-800">{{ $shopTotals['leave'] ?? $monthlySummaryTotals['leave'] }}</td>
                            <td class="py-3 px-3 text-right text-amber-800">{{ $shopTotals['total_other_attendance'] ?? $monthlySummaryTotals['total_other_attendance'] }}</td>
                            <td class="py-3 px-3 text-center"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    @endif

    {{-- Employee-wise Monthly Attendance Summary Table --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3 bg-slate-50/60">
            <div>
                <h3 class="text-xs sm:text-sm font-black text-slate-950">
                    Employee Attendance Details — {{ $calendarMonth->format('F Y') }}
                    @if($selectedShop)
                        <span class="text-slate-500 font-normal">({{ $selectedShop->name }})</span>
                    @endif
                </h3>
                <p class="text-[11px] font-semibold text-slate-500">Click any row to view complete day-by-day attendance details for that employee.</p>
            </div>
            <span class="rounded-full bg-slate-100 border border-slate-200 px-2.5 py-0.5 text-[10px] font-bold text-slate-600">
                {{ $monthlySummaryRows->count() }} {{ $monthlySummaryRows->count() === 1 ? 'Record' : 'Records' }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-600">
                        <th scope="col" class="py-2.5 px-3 sm:px-4">Employee</th>
                        <th scope="col" class="py-2.5 px-3">Shop</th>
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
                                'shop_name' => $row['shop_name'],
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
                        <tr class="group hover:bg-slate-50/80 transition cursor-pointer js-admin-summary-row"
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
                            <td class="py-2.5 px-3">
                                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                    {{ $row['shop_name'] }}
                                </span>
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
                                <span class="inline-block rounded-md px-1.5 py-0.5 {{ $row['leave'] > 0 ? 'bg-cyan-50 text-cyan-800' : 'text-slate-400' }}">
                                    {{ $row['leave'] }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-black text-amber-800">
                                <span class="inline-block rounded-md px-1.5 py-0.5 {{ $row['half_day'] > 0 ? 'bg-amber-50 text-amber-800' : 'text-slate-400' }}">
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
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-bold text-slate-700 shadow-2xs hover:bg-slate-100 hover:text-slate-950 transition cursor-pointer">
                                    <span>Details</span>
                                    <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 px-4 text-center">
                                <div class="mx-auto max-w-sm space-y-1">
                                    <p class="text-sm font-bold text-slate-800">No attendance summary records found</p>
                                    <p class="text-xs font-semibold text-slate-400">
                                        No active staff or attendance entries found for {{ $calendarMonth->format('F Y') }} matching your filters.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($monthlySummaryRows->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-slate-300 bg-slate-100 font-black text-slate-950">
                            <td class="py-3 px-3 sm:px-4 font-black">
                                Total ({{ $monthlySummaryRows->count() }} records)
                            </td>
                            <td class="py-3 px-3 text-slate-500 font-semibold">—</td>
                            <td class="py-3 px-3 text-right text-emerald-800">{{ $monthlySummaryTotals['present'] }}</td>
                            <td class="py-3 px-3 text-right text-rose-800">{{ $monthlySummaryTotals['absent'] }}</td>
                            <td class="py-3 px-3 text-right text-cyan-800">{{ $monthlySummaryTotals['leave'] }}</td>
                            <td class="py-3 px-3 text-right text-amber-800">{{ $monthlySummaryTotals['half_day'] }}</td>
                            <td class="py-3 px-3 text-right text-slate-800">{{ $monthlySummaryTotals['other'] }}</td>
                            <td class="py-3 px-3 text-center"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>

    {{-- Employee Monthly Attendance Details Modal --}}
    <div id="admin-summary-detail-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-3 sm:p-4 bg-slate-950/60 backdrop-blur-xs transition-opacity" role="dialog" aria-modal="true" aria-labelledby="admin-modal-employee-name">
        <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl flex flex-col max-h-[90vh]">
            {{-- Modal Header --}}
            <div class="flex items-start justify-between gap-3 border-b border-slate-200 p-4 bg-slate-50">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-950 text-sm font-black text-white uppercase" id="admin-modal-avatar">
                        EM
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-black text-slate-950" id="admin-modal-employee-name">Employee Details</h3>
                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-800 border border-emerald-200" id="admin-modal-month-badge">Month</span>
                        </div>
                        <p class="text-xs font-semibold text-slate-500" id="admin-modal-subtext">Code · Category · Shop</p>
                    </div>
                </div>
                <button type="button" id="admin-modal-close-btn" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition cursor-pointer" aria-label="Close modal">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            {{-- Summary Pills in Modal --}}
            <div class="grid grid-cols-5 gap-2 border-b border-slate-100 bg-white p-3 text-center">
                <div class="rounded-lg bg-emerald-50 p-2 border border-emerald-100">
                    <span class="text-[9px] font-black uppercase text-emerald-800 block">Present</span>
                    <span class="text-lg font-black text-emerald-700" id="admin-modal-present">0</span>
                </div>
                <div class="rounded-lg bg-rose-50 p-2 border border-rose-100">
                    <span class="text-[9px] font-black uppercase text-rose-800 block">Absent</span>
                    <span class="text-lg font-black text-rose-700" id="admin-modal-absent">0</span>
                </div>
                <div class="rounded-lg bg-cyan-50 p-2 border border-cyan-100">
                    <span class="text-[9px] font-black uppercase text-cyan-800 block">Leave</span>
                    <span class="text-lg font-black text-cyan-700" id="admin-modal-leave">0</span>
                </div>
                <div class="rounded-lg bg-amber-50 p-2 border border-amber-100">
                    <span class="text-[9px] font-black uppercase text-amber-800 block">Half Day</span>
                    <span class="text-lg font-black text-amber-700" id="admin-modal-half-day">0</span>
                </div>
                <div class="rounded-lg bg-slate-100 p-2 border border-slate-200">
                    <span class="text-[9px] font-black uppercase text-slate-700 block">Other</span>
                    <span class="text-lg font-black text-slate-800" id="admin-modal-other">0</span>
                </div>
            </div>

            {{-- Modal Body: Day-by-Day Attendance History --}}
            <div class="overflow-y-auto p-4 flex-1">
                <h4 class="text-xs font-black uppercase tracking-wider text-slate-500 mb-2.5">Attendance History</h4>
                <div id="admin-modal-records-container" class="space-y-2">
                    {{-- Dynamically populated with rows --}}
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-2.5 text-xs text-slate-500">
                <span id="admin-modal-footer-stats">0 entries recorded</span>
                <button type="button" id="admin-modal-bottom-close-btn" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('admin-summary-detail-modal');
        if (!modal) return;

        const closeBtn = document.getElementById('admin-modal-close-btn');
        const bottomCloseBtn = document.getElementById('admin-modal-bottom-close-btn');
        const modalAvatar = document.getElementById('admin-modal-avatar');
        const modalEmpName = document.getElementById('admin-modal-employee-name');
        const modalMonthBadge = document.getElementById('admin-modal-month-badge');
        const modalSubtext = document.getElementById('admin-modal-subtext');
        const modalPresent = document.getElementById('admin-modal-present');
        const modalAbsent = document.getElementById('admin-modal-absent');
        const modalLeave = document.getElementById('admin-modal-leave');
        const modalHalfDay = document.getElementById('admin-modal-half-day');
        const modalOther = document.getElementById('admin-modal-other');
        const recordsContainer = document.getElementById('admin-modal-records-container');
        const footerStats = document.getElementById('admin-modal-footer-stats');

        function openModal(data) {
            modalEmpName.textContent = data.employee_name || 'Employee';
            modalAvatar.textContent = (data.employee_name || 'EM').substring(0, 2).toUpperCase();
            modalMonthBadge.textContent = data.month_label || '';
            modalSubtext.textContent = (data.employee_code || '') + ' · ' + (data.category || 'Staff') + ' · ' + (data.shop_name || 'Shop');
            modalPresent.textContent = data.present || 0;
            modalAbsent.textContent = data.absent || 0;
            modalLeave.textContent = data.leave || 0;
            modalHalfDay.textContent = data.half_day || 0;
            modalOther.textContent = data.other || 0;

            recordsContainer.innerHTML = '';
            const records = data.records || [];

            if (records.length === 0) {
                recordsContainer.innerHTML = `
                    <div class="rounded-xl border border-dashed border-slate-200 p-6 text-center">
                        <p class="text-xs font-bold text-slate-700">No attendance entries recorded</p>
                        <p class="text-[11px] font-semibold text-slate-400 mt-1">There are no marked attendance records for this employee in ${data.month_label}.</p>
                    </div>
                `;
            } else {
                records.forEach(function (rec) {
                    let badgeClass = 'bg-slate-100 text-slate-800 border-slate-200';
                    if (rec.status === 'present') badgeClass = 'bg-emerald-50 text-emerald-800 border-emerald-200';
                    else if (rec.status === 'absent') badgeClass = 'bg-rose-50 text-rose-800 border-rose-200';
                    else if (rec.status === 'leave') badgeClass = 'bg-cyan-50 text-cyan-800 border-cyan-200';
                    else if (rec.status === 'half_day') badgeClass = 'bg-amber-50 text-amber-800 border-amber-200';

                    const rowEl = document.createElement('div');
                    rowEl.className = 'flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white p-2.5 text-xs shadow-2xs';
                    rowEl.innerHTML = `
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-[10px] font-black text-slate-700">
                                ${rec.day || '—'}
                            </span>
                            <div>
                                <p class="font-bold text-slate-900">${rec.date || '—'}</p>
                                <p class="text-[10px] font-semibold text-slate-400">
                                    Marked by ${rec.marked_by || 'System'} at ${rec.marked_at || '—'}
                                    ${rec.notes ? ' · <span class="text-slate-600 font-medium">"' + escapeHtml(rec.notes) + '"</span>' : ''}
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold ${badgeClass}">
                            ${rec.status_label || rec.status}
                        </span>
                    `;
                    recordsContainer.appendChild(rowEl);
                });
            }

            footerStats.textContent = records.length + (records.length === 1 ? ' entry' : ' entries') + ' recorded';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.querySelectorAll('.js-admin-summary-row').forEach(function (row) {
            row.addEventListener('click', function () {
                const raw = row.getAttribute('data-summary');
                if (!raw) return;
                try {
                    const data = JSON.parse(raw);
                    openModal(data);
                } catch (e) {
                    console.error('Failed to parse employee summary data', e);
                }
            });
        });

        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (bottomCloseBtn) bottomCloseBtn.addEventListener('click', closeModal);

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });
    });
</script>
