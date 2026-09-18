<!-- HISTORY TAB: MONTHLY ATTENDANCE REGISTER & STAFF PAYMENTS -->
@php
    $todayDate = today()->format('Y-m-d');
    $currentSelectedDate = $selectedDate->format('Y-m-d');
@endphp

<div class="space-y-4">
    {{-- Top Section: Monthly Attendance Register Header & Controls --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xs">
        <div>
            <h2 class="text-base font-black text-slate-950 sm:text-lg">{{ $calendarMonth->format('F Y') }} attendance register</h2>
            <p class="text-xs font-semibold text-slate-500">Monthly attendance register for {{ $selectedShop?->name ?? 'assigned shop' }}.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('shop-owner.staff.index') }}" class="flex items-center gap-1.5 rounded-2xl border border-slate-200 bg-white p-1 shadow-xs">
                <input type="hidden" name="shop" value="{{ $selectedShop?->code }}">
                <input type="hidden" name="tab" value="history">
                <input type="hidden" name="search" value="{{ $search }}">
                <input type="hidden" name="category" value="{{ $categoryCode }}">
                <input type="hidden" name="status" value="{{ $selectedStatus }}">

                <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'tab' => 'history', 'month' => $prevMonth->format('Y-m'), 'date' => $prevMonth->copy()->startOfMonth()->format('Y-m-d'), 'search' => $search, 'category' => $categoryCode, 'status' => $selectedStatus]) }}" 
                   class="rounded-xl p-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-950" title="Previous month" aria-label="Previous month">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                </a>

                <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'tab' => 'history', 'month' => today()->format('Y-m'), 'date' => today()->format('Y-m-d'), 'search' => $search, 'category' => $categoryCode, 'status' => $selectedStatus]) }}"
                   class="rounded-xl px-2.5 py-1 text-xs font-black text-slate-700 hover:bg-slate-100 transition">
                    This month
                </a>

                <input type="month" name="month" value="{{ $calendarMonth->format('Y-m') }}"
                       class="rounded-xl border border-slate-200 px-2.5 py-1 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600"
                       onchange="this.form.submit()">

                <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'tab' => 'history', 'month' => $nextMonth->format('Y-m'), 'date' => $nextMonth->copy()->startOfMonth()->format('Y-m-d'), 'search' => $search, 'category' => $categoryCode, 'status' => $selectedStatus]) }}" 
                   class="rounded-xl p-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-950" title="Next month" aria-label="Next month">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                </a>
            </form>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs">
        <form method="GET" action="{{ route('shop-owner.staff.index') }}" class="flex flex-wrap items-center justify-between gap-3">
            <input type="hidden" name="shop" value="{{ $selectedShop?->code }}">
            <input type="hidden" name="tab" value="history">
            <input type="hidden" name="month" value="{{ $calendarMonth->format('Y-m') }}">
            <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">

            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <input type="search" name="search" value="{{ $search }}" placeholder="Search employee name, code..." 
                       class="h-9 w-full sm:w-56 rounded-xl border border-slate-200 px-3 text-xs font-semibold focus:border-emerald-600 focus:ring-emerald-600">

                <select name="category" class="h-9 rounded-xl border border-slate-200 px-3 text-xs font-semibold">
                    <option value="">-- All Categories --</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->code }}" @selected($categoryCode === $cat->code)>{{ $cat->name }}</option>
                    @endforeach
                </select>

                <select name="status" class="h-9 rounded-xl border border-slate-200 px-3 text-xs font-semibold">
                    <option value="">-- All Statuses --</option>
                    <option value="present" @selected($selectedStatus === 'present')>Present (P)</option>
                    <option value="half_day" @selected($selectedStatus === 'half_day')>Half Day (H)</option>
                    <option value="leave" @selected($selectedStatus === 'leave')>Leave (L)</option>
                    <option value="absent" @selected($selectedStatus === 'absent')>Absent (A)</option>
                    <option value="not_marked" @selected($selectedStatus === 'not_marked')>Not Marked (—)</option>
                </select>

                <button type="submit" class="h-9 rounded-xl bg-slate-950 px-4 text-xs font-bold text-white hover:bg-slate-800 cursor-pointer">Filter</button>
                @if($search || $categoryCode || $selectedStatus)
                    <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'tab' => 'history', 'month' => $calendarMonth->format('Y-m'), 'date' => $selectedDate->format('Y-m-d')]) }}"
                       class="h-9 rounded-xl border border-slate-200 px-3 flex items-center text-xs font-bold text-slate-600 hover:bg-slate-50">Reset</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Monthly Attendance Register Card --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-3.5 py-2.5">
            <div class="flex items-center gap-2">
                <div>
                    <div class="flex items-center gap-1.5">
                        <h3 class="text-xs sm:text-sm font-black text-slate-950">{{ $calendarMonth->format('F Y') }} attendance register</h3>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-bold text-slate-600 border border-slate-200">Read-Only History</span>
                    </div>
                    <p class="text-[11px] font-semibold text-slate-500">Scroll sideways to view every day. Select a cell to view record details.</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-black" aria-label="Attendance status legend">
                <span class="rounded bg-emerald-600 px-1.5 py-0.5 text-white">P <span class="font-normal text-[9px]">Present</span></span>
                <span class="rounded bg-rose-600 px-1.5 py-0.5 text-white">A <span class="font-normal text-[9px]">Absent</span></span>
                <span class="rounded bg-orange-500 px-1.5 py-0.5 text-white">H <span class="font-normal text-[9px]">Half Day</span></span>
                <span class="rounded bg-slate-950 px-1.5 py-0.5 text-white">L <span class="font-normal text-[9px]">Leave</span></span>
                <span class="rounded bg-slate-100 border border-slate-200 px-1.5 py-0.5 text-slate-600">— <span class="font-normal text-[9px]">Not Marked</span></span>
            </div>
        </div>

        <div id="history-attendance-scroll-container" class="overflow-x-auto overscroll-x-contain" tabindex="0" aria-label="Monthly attendance table, horizontally scrollable">
            <table class="w-max min-w-full border-separate border-spacing-0 text-left">
                <thead>
                    <tr>
                        <th scope="col" class="sticky left-0 z-30 min-w-36 sm:min-w-44 max-w-44 border-b border-r border-slate-200 bg-slate-50 px-2.5 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-700 shadow-[4px_0_6px_-4px_rgba(15,23,42,0.35)]">Employee</th>
                        @foreach($monthDays as $day)
                            @php($isToday = $day->isSameDay(today()))
                            @php($isSelected = $day->isSameDay($selectedDate))
                            <th scope="col"
                                @if($isToday || (!$calendarMonth->isSameMonth(today()) && $isSelected)) id="history-today-column" @endif
                                data-date="{{ $day->toDateString() }}"
                                class="min-w-6 sm:min-w-7 w-7 border-b border-r border-slate-200 px-0.5 py-1 text-center {{ $isToday ? 'bg-emerald-100/70 border-b-2 border-b-emerald-600' : ($isSelected ? 'bg-emerald-50' : ($day->isWeekend() ? 'bg-slate-100' : 'bg-slate-50')) }}">
                                <span class="block text-[8px] font-extrabold uppercase {{ $isToday ? 'text-emerald-800' : 'text-slate-400' }}">{{ substr($day->format('D'), 0, 2) }}</span>
                                <span class="mt-0.5 inline-flex items-center justify-center text-[10px] font-black {{ $isToday ? 'h-4 w-4 rounded-full bg-emerald-700 text-white shadow-xs mx-auto text-[9px]' : ($isSelected ? 'text-emerald-700' : 'text-slate-800') }}">{{ $day->format('d') }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($historyEmployees as $employee)
                        @php($employeeAttendance = $monthlyAttendanceByEmployee->get($employee->id, collect()))
                        @php($selectedAttendance = $employeeAttendance->get($selectedDate->toDateString()))
                        <tr class="group">
                            <th scope="row" class="sticky left-0 z-20 border-b border-r border-slate-200 bg-white px-2.5 py-1.5 shadow-[4px_0_6px_-4px_rgba(15,23,42,0.35)] group-hover:bg-slate-50">
                                <span class="block max-w-32 sm:max-w-40 truncate text-[11px] font-bold text-slate-950 leading-tight">{{ $employee->name }}</span>
                                <span class="block max-w-32 sm:max-w-40 truncate text-[9px] font-semibold text-slate-400 leading-tight">{{ $employee->employee_code }} · {{ $employee->defaultShop?->name ?? $selectedShop?->name ?? 'Staff' }}</span>
                                <button type="button"
                                        class="js-history-open-details text-[9px] font-bold text-emerald-700 hover:underline cursor-pointer inline-block mt-0.5"
                                        data-employee-code="{{ $employee->employee_code }}"
                                        data-employee-name="{{ e($employee->name) }}"
                                        data-employee-category="{{ e($employee->category?->name ?? '') }}"
                                        data-employee-photo="{{ $employee->photo_url }}"
                                        data-employee-phone="{{ $employee->phone }}"
                                        data-employee-emergency="{{ $employee->alternate_phone }}"
                                        data-shop-name="{{ e($selectedAttendance?->shop?->name ?? $employee->defaultShop?->name ?? $selectedShop?->name ?? 'Shop Staff') }}"
                                        data-status="{{ $selectedAttendance ? match($selectedAttendance->status) { 'present' => 'P (Present)', 'half_day' => 'H (Half Day)', 'leave' => 'L (Leave)', 'absent' => 'A (Absent)', default => ucfirst($selectedAttendance->status) } : 'Not Marked' }}"
                                        data-attendance-date="{{ $selectedDate->format('d M Y') }}"
                                        data-marked-at="{{ $selectedAttendance?->marked_at?->timezone('Asia/Kolkata')->format('g:i A') ?? '—' }}"
                                        data-marked-by="{{ e($selectedAttendance?->markedBy?->name ?? '—') }}"
                                        data-source="{{ ucfirst($selectedAttendance?->source ?? 'shop_owner') }}"
                                        data-notes="{{ e($selectedAttendance?->notes ?? '') }}">Details</button>
                            </th>
                            @foreach($monthDays as $day)
                                @php($attendance = $employeeAttendance->get($day->toDateString()))
                                @php($status = $attendance?->status)
                                @php($statusStyles = match($status) {
                                    'present' => ['P', 'Present', 'bg-emerald-600 text-white hover:bg-emerald-700'],
                                    'absent' => ['A', 'Absent', 'bg-rose-600 text-white hover:bg-rose-700'],
                                    'half_day' => ['H', 'Half Day', 'bg-orange-500 text-white hover:bg-orange-600'],
                                    'leave' => ['L', 'Leave', 'bg-slate-950 text-white hover:bg-black'],
                                    default => ['—', 'Not Marked', 'bg-slate-100 text-slate-400 hover:bg-slate-200 hover:text-slate-700'],
                                })
                                @php($isToday = $day->isSameDay(today()))
                                @php($isSelected = $day->isSameDay($selectedDate))
                                <td class="border-b border-r border-slate-200 p-0.5 text-center {{ $isToday ? 'bg-emerald-50/75' : ($isSelected ? 'bg-emerald-50/40' : ($day->isWeekend() ? 'bg-slate-50' : 'bg-white')) }}">
                                    <button type="button"
                                            class="js-history-open-details flex h-5 w-5 sm:h-5.5 sm:w-5.5 mx-auto items-center justify-center rounded text-[10px] font-black leading-none transition focus:outline-none focus:ring-1 focus:ring-emerald-500 cursor-pointer {{ $statusStyles[2] }}"
                                            title="{{ $employee->name }} — {{ $day->format('d M') }}: {{ $statusStyles[1] }} (Read-only)"
                                            aria-label="{{ $employee->name }}, {{ $day->format('d F Y') }}, {{ $statusStyles[1] }}"
                                            data-employee-code="{{ $employee->employee_code }}"
                                            data-employee-name="{{ e($employee->name) }}"
                                            data-employee-category="{{ e($employee->category?->name ?? '') }}"
                                            data-employee-photo="{{ $employee->photo_url }}"
                                            data-employee-phone="{{ $employee->phone }}"
                                            data-employee-emergency="{{ $employee->alternate_phone }}"
                                            data-shop-name="{{ e($attendance?->shop?->name ?? $employee->defaultShop?->name ?? $selectedShop?->name ?? 'Shop Staff') }}"
                                            data-status="{{ $attendance ? match($status) { 'present' => 'P (Present)', 'half_day' => 'H (Half Day)', 'leave' => 'L (Leave)', 'absent' => 'A (Absent)', default => ucfirst((string) $status) } : 'Not Marked' }}"
                                            data-attendance-date="{{ $day->format('d M Y') }}"
                                            data-marked-at="{{ $attendance?->marked_at?->timezone('Asia/Kolkata')->format('g:i A') ?? '—' }}"
                                            data-marked-by="{{ e($attendance?->markedBy?->name ?? '—') }}"
                                            data-source="{{ ucfirst($attendance?->source ?? 'shop_owner') }}"
                                            data-notes="{{ e($attendance?->notes ?? '') }}">{{ $statusStyles[0] }}</button>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $monthDays->count() + 1 }}" class="p-8 text-center text-xs font-semibold text-slate-400">No active employees match the selected filters for this shop.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- EMPLOYEE DETAILS MODAL (50% SMALLER / COMPACT) -->
    <div id="history-attendance-details-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-3" role="dialog" aria-modal="true">
        <div id="history-attendance-details-backdrop" class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs"></div>
        <div class="relative w-full max-w-xs sm:max-w-sm rounded-xl bg-white p-3.5 shadow-xl border border-slate-200 space-y-2.5 z-10 text-xs">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide">Attendance Details</h3>
                <button type="button" id="btn-close-history-details-modal" class="text-slate-400 hover:text-slate-700 text-xs font-bold cursor-pointer p-0.5">✕</button>
            </div>

            <!-- PROFILE HEADER -->
            <div class="flex items-center gap-2.5">
                <div id="history-details-avatar-container" class="shrink-0">
                    <div id="history-details-initials" class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white"></div>
                    <img id="history-details-photo" src="" class="hidden h-8 w-8 rounded-full object-cover border border-slate-200" alt="">
                </div>
                <div class="min-w-0">
                    <h4 id="history-details-name" class="text-xs font-black text-slate-950 truncate leading-tight"></h4>
                    <p id="history-details-meta" class="text-[10px] font-semibold text-slate-400 truncate leading-tight"></p>
                </div>
            </div>

            <!-- DETAILS GRID -->
            <div class="space-y-1.5 text-[11px]">
                <div class="rounded-lg border border-slate-100 bg-slate-50 px-2 py-1">
                    <p class="text-[8px] font-bold text-slate-400 uppercase">Assigned Location</p>
                    <p id="history-details-shop" class="font-bold text-slate-900 leading-tight">—</p>
                </div>

                <div class="grid grid-cols-2 gap-1.5">
                    <div class="rounded-lg border border-slate-100 bg-slate-50 px-2 py-1">
                        <p class="text-[8px] font-bold text-slate-400 uppercase">Status & Date</p>
                        <p id="history-details-status" class="font-bold text-slate-900 leading-tight">—</p>
                    </div>
                    <div class="rounded-lg border border-slate-100 bg-slate-50 px-2 py-1">
                        <p class="text-[8px] font-bold text-slate-400 uppercase">Marked Time</p>
                        <p id="history-details-marked-at" class="font-bold text-slate-900 leading-tight">—</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-1.5">
                    <div class="rounded-lg border border-slate-100 bg-slate-50 px-2 py-1">
                        <p class="text-[8px] font-bold text-slate-400 uppercase">Primary Phone</p>
                        <p id="history-details-phone" class="font-semibold text-slate-800 leading-tight truncate">—</p>
                    </div>
                    <div class="rounded-lg border border-slate-100 bg-slate-50 px-2 py-1">
                        <p class="text-[8px] font-bold text-slate-400 uppercase">Emergency Contact</p>
                        <p id="history-details-emergency" class="font-semibold text-slate-800 leading-tight truncate">—</p>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-100 bg-slate-50 px-2 py-1">
                    <p class="text-[8px] font-bold text-slate-400 uppercase">Marked By & Source</p>
                    <p id="history-details-marked-by" class="font-semibold text-slate-800 leading-tight">—</p>
                </div>

                <div id="history-details-notes-container" class="hidden rounded-lg border border-slate-100 bg-slate-50 px-2 py-1">
                    <p class="text-[8px] font-bold text-slate-400 uppercase">Reason / Note</p>
                    <p id="history-details-notes" class="font-semibold text-slate-700 whitespace-pre-line leading-tight text-[10px]"></p>
                </div>
            </div>

            <!-- FOOTER ACTIONS -->
            <div class="flex items-center justify-end pt-1.5 border-t border-slate-100">
                <button type="button" id="btn-cancel-history-details-modal" class="rounded-lg bg-slate-900 px-3 py-1 text-[11px] font-bold text-white hover:bg-slate-800 cursor-pointer">Close</button>
            </div>
        </div>
    </div>

    @if(session('sync_results'))
        @php($syncRes = session('sync_results'))
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 shadow-sm space-y-3">
            <div class="flex items-center justify-between border-b border-emerald-200/60 pb-2.5">
                <div class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-white font-black text-xs">✓</span>
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-wider text-emerald-950">Cashbook Sync Results</h3>
                        <p class="text-[11px] font-semibold text-emerald-800">Reconciled Staff History with Shop Cashbook entries.</p>
                    </div>
                </div>
                <span class="rounded-lg bg-emerald-200/70 px-2 py-1 text-[10px] font-black text-emerald-900">
                    {{ count($syncRes['created']) }} Created · {{ count($syncRes['updated']) }} Updated · {{ count($syncRes['matching']) }} Matching · {{ count($syncRes['orphans']) }} Orphans
                </span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-center text-xs font-bold">
                <div class="rounded-xl border border-emerald-200 bg-white p-2">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Created</p>
                    <p class="text-sm font-black text-emerald-700">{{ count($syncRes['created']) }}</p>
                </div>
                <div class="rounded-xl border border-blue-200 bg-white p-2">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Updated</p>
                    <p class="text-sm font-black text-blue-700">{{ count($syncRes['updated']) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-2">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Matching</p>
                    <p class="text-sm font-black text-slate-700">{{ count($syncRes['matching']) }}</p>
                </div>
                <div class="rounded-xl border border-rose-200 bg-white p-2">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Orphans</p>
                    <p class="text-sm font-black text-rose-700">{{ count($syncRes['orphans']) }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-white p-2">
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Mismatched</p>
                    <p class="text-sm font-black text-amber-700">{{ count($syncRes['mismatched']) }}</p>
                </div>
            </div>

            @if(!empty($syncRes['orphans']))
                <div class="rounded-xl border border-rose-200 bg-rose-50/50 p-3 space-y-2">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-black uppercase text-rose-900 tracking-wider">Orphan Cashbook Entries Detected</h4>
                        <span class="text-[10px] font-semibold text-rose-700">Staff payment no longer exists for these records</span>
                    </div>
                    <div class="divide-y divide-rose-100 max-h-48 overflow-y-auto">
                        @foreach($syncRes['orphans'] as $orphan)
                            <div class="py-2 flex items-center justify-between text-xs gap-2">
                                <div class="min-w-0">
                                    <p class="font-bold text-slate-900 truncate">{{ $orphan['category'] }} · ₹{{ number_format((float) $orphan['amount'], 2) }}</p>
                                    <p class="text-[10px] text-slate-500 font-medium truncate">Date: {{ $orphan['business_date'] }} · Note: {{ $orphan['notes'] }}</p>
                                </div>
                                <form action="{{ route('shop-owner.staff.delete-cashbook-orphan') }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this orphan Cashbook entry?');">
                                    @csrf
                                    <input type="hidden" name="transaction_id" value="{{ $orphan['transaction_id'] }}">
                                    <input type="hidden" name="shop" value="{{ $selectedShop?->code }}">
                                    <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
                                    <input type="hidden" name="month" value="{{ $calendarMonth->format('Y-m') }}">
                                    <button type="submit" class="rounded-lg bg-rose-600 px-2.5 py-1 text-[11px] font-black text-white hover:bg-rose-700 active:scale-95 transition cursor-pointer">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </article>
    @endif

    {{-- Bottom Row: Full Salary & Advance Payments and Advance Requests --}}
    <section class="grid gap-4 lg:grid-cols-12">
        <!-- SALARY & ADVANCE PAYMENTS HISTORY TABLE -->
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3 lg:col-span-7">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">Salary & Advance Payments</h2>
                    <p class="text-xs font-medium text-slate-500 mt-0.5">Recorded staff payouts with funding source & cashbook status.</p>
                </div>
                <div class="flex items-center gap-2">
                    <form action="{{ route('shop-owner.staff.sync-cashbook') }}" method="POST">
                        @csrf
                        <input type="hidden" name="shop" value="{{ $selectedShop?->code }}">
                        <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
                        <input type="hidden" name="month" value="{{ $calendarMonth->format('Y-m') }}">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-black text-emerald-800 shadow-2xs hover:bg-emerald-100 active:scale-95 transition cursor-pointer">
                            <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            <span>Sync with Cashbook</span>
                        </button>
                    </form>
                    <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-black text-emerald-800">
                        {{ $recentPayrollPayments->total() }} Payouts
                    </span>
                </div>
            </div>

            <div class="divide-y divide-slate-100 max-h-[400px] overflow-y-auto">
                @forelse($recentPayrollPayments as $payment)
                    <div class="py-2.5 flex items-center justify-between text-xs gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <p class="font-black text-slate-950 truncate">{{ $payment->employee?->name }}</p>
                                <span class="rounded px-1.5 py-0.2 text-[9px] font-black uppercase border {{ $payment->payment_type === 'advance' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">
                                    {{ $payment->payment_type ?? 'salary' }}
                                </span>
                            </div>
                            <p class="text-[10px] font-semibold text-slate-400 mt-0.5 truncate">
                                Paid: {{ $payment->paid_on?->format('d M Y') }} · Source: {{ str_replace('_', ' ', (string) $payment->fund_source) }}
                                @if($payment->notes) · Note: {{ $payment->notes }} @endif
                            </p>
                        </div>
                        <div class="text-right shrink-0 space-y-1">
                            <p class="font-black text-slate-950">₹{{ number_format((float) $payment->amount, 2) }}</p>
                            <span class="inline-block text-[9px] font-bold text-emerald-600">
                                ✓ Cashbook Synced
                            </span>
                            <div class="flex items-center justify-end gap-1.5 pt-0.5">
                                <button type="button" 
                                        onclick="openEditPaymentModal({{ json_encode([
                                            'id' => $payment->id,
                                            'employee_name' => $payment->employee?->name ?? 'Staff',
                                            'amount' => (float) $payment->amount,
                                            'paid_on' => $payment->paid_on?->format('Y-m-d') ?? today()->format('Y-m-d'),
                                            'payment_type' => $payment->payment_type ?? 'salary',
                                            'fund_source' => $payment->fund_source ?? 'sales',
                                            'notes' => $payment->notes ?? '',
                                            'update_url' => route('shop-owner.staff.payments.update', $payment),
                                        ]) }})"
                                        class="rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-bold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition cursor-pointer">
                                    Edit
                                </button>
                                <form action="{{ route('shop-owner.staff.payments.destroy', $payment) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this payment record? Any linked Cashbook entry will be detected during sync.');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700 hover:bg-rose-100 hover:text-rose-900 transition cursor-pointer">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs font-semibold text-slate-400">
                        No payments recorded for this shop.
                    </div>
                @endforelse
            </div>

            @if($recentPayrollPayments->hasPages())
                <div class="border-t border-slate-100 pt-2">
                    {{ $recentPayrollPayments->links() }}
                </div>
            @endif
        </article>

        <!-- HR ADVANCE REQUESTS & APPROVALS -->
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3 lg:col-span-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-800">HR Advance Requests</h2>
                    <p class="text-xs font-medium text-slate-500 mt-0.5">Advance exceptions submitted for HR review.</p>
                </div>
            </div>

            <div class="divide-y divide-slate-100 max-h-[400px] overflow-y-auto">
                @forelse($advanceRequests as $request)
                    <div class="py-2.5 flex items-center justify-between text-xs gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="font-black text-slate-950 truncate">{{ $request->employee?->name }}</p>
                            <p class="text-[10px] font-semibold text-slate-400 truncate">
                                {{ $request->requested_on?->format('d M Y') }} · Req: ₹{{ number_format((float) $request->requested_amount, 2) }}
                            </p>
                            @if($request->request_note)
                                <p class="text-[10px] font-medium text-slate-500 truncate mt-0.5">"{{ $request->request_note }}"</p>
                            @endif
                        </div>
                        <div class="text-right shrink-0">
                            @if($request->approval_status === 'approved')
                                <span class="rounded px-2 py-0.5 text-[9px] font-black uppercase border border-emerald-200 bg-emerald-50 text-emerald-800">Approved</span>
                            @elseif($request->approval_status === 'rejected')
                                <span class="rounded px-2 py-0.5 text-[9px] font-black uppercase border border-rose-200 bg-rose-50 text-rose-800">Rejected</span>
                            @else
                                <span class="rounded px-2 py-0.5 text-[9px] font-black uppercase border border-amber-200 bg-amber-50 text-amber-800">Pending HR</span>
                            @endif
                            @if($request->approved_amount)
                                <p class="text-[10px] font-black text-slate-800 mt-0.5">₹{{ number_format((float) $request->approved_amount, 2) }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-xs font-semibold text-slate-400">
                        No HR advance requests found.
                    </div>
                @endforelse
            </div>

            @if($advanceRequests->hasPages())
                <div class="border-t border-slate-100 pt-2">
                    {{ $advanceRequests->links() }}
                </div>
            @endif
        </article>
    </section>

    {{-- Edit Staff Payment Modal --}}
    <div id="edit-staff-payment-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-xl transition-all border border-slate-200 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-sm font-black uppercase tracking-wider text-slate-900">Edit Staff Payment</h3>
                    <p id="edit-payment-employee-name" class="text-xs font-semibold text-slate-500 mt-0.5"></p>
                </div>
                <button type="button" onclick="closeEditPaymentModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition cursor-pointer">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="edit-staff-payment-form" method="POST" class="space-y-3">
                @csrf
                @method('PUT')
                <input type="hidden" name="shop" value="{{ $selectedShop?->code }}">

                <div>
                    <label for="edit-payment-amount" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700">Amount (₹)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="edit-payment-amount" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-bold text-slate-900 shadow-2xs focus:border-emerald-500 focus:ring-emerald-500">
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="edit-payment-date" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700">Date</label>
                        <input type="date" name="paid_on" id="edit-payment-date" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-emerald-500 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label for="edit-payment-type" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700">Category</label>
                        <select name="payment_type" id="edit-payment-type" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="salary">Salary</option>
                            <option value="advance">Staff Advance</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="edit-payment-fund-source" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700">Fund Source</label>
                    <select name="fund_source" id="edit-payment-fund-source" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold text-slate-900 shadow-2xs focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="sales">Shop Cash / Daily Sales</option>
                        <option value="petty_cash">Petty Cash</option>
                        <option value="company">Company Account</option>
                    </select>
                </div>

                <div>
                    <label for="edit-payment-notes" class="block text-[11px] font-bold uppercase tracking-wider text-slate-700">Notes / Remarks</label>
                    <textarea name="notes" id="edit-payment-notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-xs text-slate-900 shadow-2xs focus:border-emerald-500 focus:ring-emerald-500" placeholder="Optional payment note"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                    <button type="button" onclick="closeEditPaymentModal()" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-black text-white hover:bg-emerald-700 active:scale-95 transition cursor-pointer shadow-xs">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditPaymentModal(data) {
            const modal = document.getElementById('edit-staff-payment-modal');
            const form = document.getElementById('edit-staff-payment-form');
            if (!modal || !form) return;

            document.getElementById('edit-payment-employee-name').textContent = data.employee_name;
            document.getElementById('edit-payment-amount').value = data.amount;
            document.getElementById('edit-payment-date').value = data.paid_on;
            document.getElementById('edit-payment-type').value = data.payment_type;
            document.getElementById('edit-payment-fund-source').value = (data.fund_source === 'petty' ? 'petty_cash' : data.fund_source);
            document.getElementById('edit-payment-notes').value = data.notes || '';
            form.action = data.update_url;

            modal.classList.remove('hidden');
        }

        function closeEditPaymentModal() {
            const modal = document.getElementById('edit-staff-payment-modal');
            if (modal) modal.classList.add('hidden');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const detModal = document.getElementById('history-attendance-details-modal');
            const detBackdrop = document.getElementById('history-attendance-details-backdrop');
            const detCloseBtn = document.getElementById('btn-close-history-details-modal');
            const detCancelBtn = document.getElementById('btn-cancel-history-details-modal');

            const elPhoto = document.getElementById('history-details-photo');
            const elInitials = document.getElementById('history-details-initials');
            const elName = document.getElementById('history-details-name');
            const elMeta = document.getElementById('history-details-meta');
            const elShop = document.getElementById('history-details-shop');
            const elStatus = document.getElementById('history-details-status');
            const elMarkedAt = document.getElementById('history-details-marked-at');
            const elPhone = document.getElementById('history-details-phone');
            const elEmergency = document.getElementById('history-details-emergency');
            const elMarkedBy = document.getElementById('history-details-marked-by');
            const elNotesContainer = document.getElementById('history-details-notes-container');
            const elNotes = document.getElementById('history-details-notes');

            document.querySelectorAll('.js-history-open-details').forEach(function (button) {
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const name = button.getAttribute('data-employee-name') || '';
                    const code = button.getAttribute('data-employee-code') || '';
                    const category = button.getAttribute('data-employee-category') || '';
                    const photo = button.getAttribute('data-employee-photo') || '';
                    const phone = button.getAttribute('data-employee-phone') || 'N/A';
                    const emergency = button.getAttribute('data-employee-emergency') || 'N/A';
                    const shop = button.getAttribute('data-shop-name') || '—';
                    const status = button.getAttribute('data-status') || '—';
                    const attDate = button.getAttribute('data-attendance-date') || '';
                    const markedAt = button.getAttribute('data-marked-at') || '—';
                    const markedBy = button.getAttribute('data-marked-by') || '—';
                    const source = button.getAttribute('data-source') || 'shop_owner';
                    const notes = button.getAttribute('data-notes') || '';

                    if (elName) elName.textContent = name;
                    if (elMeta) elMeta.textContent = code + (category ? ' · ' + category : '');
                    if (elShop) elShop.textContent = shop;
                    if (elStatus) elStatus.textContent = status + (attDate ? ' (' + attDate + ')' : '');
                    if (elMarkedAt) elMarkedAt.textContent = markedAt;
                    if (elPhone) elPhone.textContent = phone;
                    if (elEmergency) elEmergency.textContent = emergency;
                    if (elMarkedBy) elMarkedBy.textContent = markedBy + ' (Source: ' + source + ')';

                    if (photo && elPhoto && elInitials) {
                        elPhoto.src = photo;
                        elPhoto.classList.remove('hidden');
                        elInitials.classList.add('hidden');
                    } else if (elPhoto && elInitials) {
                        elInitials.textContent = (name.substr(0, 2) || 'EM').toUpperCase();
                        elInitials.classList.remove('hidden');
                        elPhoto.classList.add('hidden');
                    }

                    if (notes && elNotesContainer && elNotes) {
                        elNotes.textContent = notes;
                        elNotesContainer.classList.remove('hidden');
                    } else if (elNotesContainer) {
                        elNotesContainer.classList.add('hidden');
                    }

                    if (detModal) detModal.classList.remove('hidden');
                });
            });

            function closeHistoryDetailsModal() {
                if (detModal) detModal.classList.add('hidden');
            }

            if (detCloseBtn) detCloseBtn.addEventListener('click', closeHistoryDetailsModal);
            if (detCancelBtn) detCancelBtn.addEventListener('click', closeHistoryDetailsModal);
            if (detBackdrop) detBackdrop.addEventListener('click', closeHistoryDetailsModal);

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && detModal && !detModal.classList.contains('hidden')) {
                    closeHistoryDetailsModal();
                }
            });

            // Auto-scroll monthly register to today / selected date column on load
            function scrollToTodayColumn() {
                const scrollContainer = document.getElementById('history-attendance-scroll-container');
                const todayCol = document.getElementById('history-today-column');
                if (!scrollContainer || !todayCol) return;

                const stickyHeader = scrollContainer.querySelector('th.sticky');
                const stickyWidth = stickyHeader ? stickyHeader.offsetWidth : 0;
                const containerWidth = scrollContainer.clientWidth;
                const colLeft = todayCol.offsetLeft;
                const colWidth = todayCol.offsetWidth;

                const targetScroll = Math.max(0, colLeft - stickyWidth - (containerWidth - stickyWidth - colWidth) / 2);
                scrollContainer.scrollLeft = targetScroll;
            }

            scrollToTodayColumn();
            requestAnimationFrame(scrollToTodayColumn);
            setTimeout(scrollToTodayColumn, 100);
        });
    </script>
</div>
