<x-layouts.staff title="Staff Attendance Board">
    <div class="mx-auto max-w-[1600px] space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Staff Attendance</h1>
                <p class="text-sm font-semibold text-slate-500">Monthly attendance register for {{ $selectedDate->format('F Y') }}.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" action="{{ route('admin.staff.attendance') }}" class="flex items-center gap-1.5 rounded-2xl border border-slate-200 bg-white p-1 shadow-xs">
                    <input type="hidden" name="search" value="{{ $search }}">
                    <input type="hidden" name="shop_id" value="{{ $selectedShopId }}">
                    <input type="hidden" name="category" value="{{ $categoryCode }}">
                    <input type="hidden" name="status" value="{{ $selectedStatus }}">
                    <input type="hidden" name="tab" value="{{ $selectedAttendanceTab }}">

                    <a href="{{ route('admin.staff.attendance', array_merge(request()->query(), ['date' => $prevDate->format('Y-m-d')])) }}" 
                       class="rounded-xl p-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-950" title="Previous month" aria-label="Previous month">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                    </a>

                    <a href="{{ route('admin.staff.attendance', array_merge(request()->query(), ['date' => today()->format('Y-m-d')])) }}"
                       class="rounded-xl px-2.5 py-1 text-xs font-black text-slate-700 hover:bg-slate-100 transition">
                        This month
                    </a>

                    <input type="month" name="date" value="{{ $selectedDate->format('Y-m') }}"
                           class="rounded-xl border border-slate-200 px-2.5 py-1 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600"
                           onchange="this.form.submit()">

                    <a href="{{ route('admin.staff.attendance', array_merge(request()->query(), ['date' => $nextDate->format('Y-m-d')])) }}" 
                       class="rounded-xl p-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-950" title="Next month" aria-label="Next month">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </a>
                </form>

                @if($selectedAttendanceTab === 'attendance')
                <button type="button"
                        class="js-open-attendance-modal inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white hover:bg-emerald-700 shadow-xs cursor-pointer"
                        data-employee-id="" 
                        data-employee-name="" 
                        data-attendance-date="{{ $selectedDate->format('Y-m-d') }}"
                        data-shop-id="" 
                        data-status="present" 
                        data-notes="">
                    <span>+</span> Add Attendance
                </button>
                @endif
            </div>
        </div>

        <nav class="inline-flex rounded-2xl border border-slate-200 bg-white p-1 shadow-xs" aria-label="Attendance page sections">
            <a href="{{ route('admin.staff.attendance', array_merge(request()->query(), ['tab' => null])) }}"
               class="rounded-xl px-4 py-2 text-xs font-black transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 {{ $selectedAttendanceTab === 'attendance' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}"
               @if($selectedAttendanceTab === 'attendance') aria-current="page" @endif>Attendance</a>
            <a href="{{ route('admin.staff.attendance', array_merge(request()->query(), ['tab' => 'pending-payment', 'status' => null])) }}"
               class="rounded-xl px-4 py-2 text-xs font-black transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 {{ $selectedAttendanceTab === 'pending-payment' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}"
               @if($selectedAttendanceTab === 'pending-payment') aria-current="page" @endif>Pending Payment</a>
        </nav>

        @if($selectedAttendanceTab === 'attendance')
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-6">
            @php
                $summaryCards = [
                    'all' => ['label' => 'Total Staff', 'value' => $statusCounts['total'], 'color' => 'text-slate-950'],
                    'present' => ['label' => 'Present (P)', 'value' => $statusCounts['present'], 'color' => 'text-emerald-700'],
                    'half_day' => ['label' => 'Half Day (H)', 'value' => $statusCounts['half_day'], 'color' => 'text-orange-700'],
                    'leave' => ['label' => 'On Leave (L)', 'value' => $statusCounts['leave'], 'color' => 'text-slate-950'],
                    'absent' => ['label' => 'Absent (A)', 'value' => $statusCounts['absent'], 'color' => 'text-rose-700'],
                    'not_marked' => ['label' => 'Not Marked (—)', 'value' => $statusCounts['not_marked'], 'color' => 'text-slate-500'],
                ];
            @endphp

            @foreach($summaryCards as $key => $card)
                @php($isActive = ($selectedStatus === $key) || ($key === 'all' && ($selectedStatus === '' || $selectedStatus === 'all')))
                <a href="{{ route('admin.staff.attendance', array_merge(request()->query(), ['status' => $key === 'all' ? null : $key])) }}" 
                   class="block rounded-2xl border p-3.5 shadow-xs transition cursor-pointer {{ $isActive ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                    <p class="text-[10px] font-black uppercase tracking-wider {{ $isActive ? 'text-slate-400' : 'text-slate-400' }}">{{ $card['label'] }}</p>
                    <p class="mt-1 text-2xl font-black {{ $isActive ? 'text-white' : $card['color'] }}">{{ $card['value'] }}</p>
                </a>
            @endforeach
        </div>
        @endif

        <nav class="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-3 shadow-xs" aria-label="Filter attendance by shop">
            <div class="flex min-w-max items-center gap-2">
                <a href="{{ route('admin.staff.attendance', array_merge(request()->query(), ['shop_id' => null])) }}"
                   data-shop-filter="all"
                   class="inline-flex h-9 items-center gap-2 rounded-full border px-4 text-xs font-black transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 {{ $selectedShopId === null ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800' }}"
                   @if($selectedShopId === null) aria-current="page" @endif>
                    All Shops
                </a>
                @foreach($availableShops as $shop)
                    <a href="{{ route('admin.staff.attendance', array_merge(request()->query(), ['shop_id' => $shop->id])) }}"
                       data-shop-filter="{{ $shop->id }}"
                       class="inline-flex h-9 items-center gap-2 rounded-full border px-4 text-xs font-black transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 {{ $selectedShopId === $shop->id ? 'border-emerald-700 bg-emerald-700 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800' }}"
                       @if($selectedShopId === $shop->id) aria-current="page" @endif>
                        {{ $shop->name }}
                        <span class="rounded-full px-2 py-0.5 text-[10px] {{ $selectedShopId === $shop->id ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ $shopEmployeeCounts->get($shop->id) }}</span>
                    </a>
                @endforeach
            </div>
        </nav>

        <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs">
            <form method="GET" action="{{ route('admin.staff.attendance') }}" class="flex flex-wrap items-center justify-between gap-3">
                <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
                <input type="hidden" name="shop_id" value="{{ $selectedShopId }}">
                <input type="hidden" name="tab" value="{{ $selectedAttendanceTab }}">

                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search employee name, code..." 
                           class="h-9 w-full sm:w-56 rounded-xl border border-slate-200 px-3 text-xs font-semibold focus:border-emerald-600 focus:ring-emerald-600">

                    <select name="category" class="h-9 rounded-xl border border-slate-200 px-3 text-xs font-semibold">
                        <option value="">-- All Categories --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->code }}" @selected($categoryCode === $cat->code)>{{ $cat->name }}</option>
                        @endforeach
                    </select>

                    @if($selectedAttendanceTab === 'attendance')
                    <select name="status" class="h-9 rounded-xl border border-slate-200 px-3 text-xs font-semibold">
                        <option value="">-- All Statuses --</option>
                        <option value="present" @selected($selectedStatus === 'present')>Present (P)</option>
                        <option value="half_day" @selected($selectedStatus === 'half_day')>Half Day (H)</option>
                        <option value="leave" @selected($selectedStatus === 'leave')>Leave (L)</option>
                        <option value="absent" @selected($selectedStatus === 'absent')>Absent (A)</option>
                        <option value="not_marked" @selected($selectedStatus === 'not_marked')>Not Marked (—)</option>
                    </select>
                    @endif

                    <button type="submit" class="h-9 rounded-xl bg-slate-950 px-4 text-xs font-bold text-white hover:bg-slate-800 cursor-pointer">Filter</button>
                    @if($search || $selectedShopId || $categoryCode || $selectedStatus)
                        <a href="{{ route('admin.staff.attendance', ['date' => $selectedDate->format('Y-m-d'), 'tab' => $selectedAttendanceTab === 'pending-payment' ? 'pending-payment' : null]) }}"
                           class="h-9 rounded-xl border border-slate-200 px-3 flex items-center text-xs font-bold text-slate-600 hover:bg-slate-50">Reset</a>
                    @endif
                </div>
            </form>
        </div>

        @if($selectedAttendanceTab === 'attendance')
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <div>
                    <h2 class="text-sm font-black text-slate-950">{{ $selectedDate->format('F Y') }} attendance register</h2>
                    <p class="text-xs font-semibold text-slate-500">Scroll sideways to view every day. Select a cell to add or edit attendance.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-[11px] font-black" aria-label="Attendance status legend">
                    <span class="rounded-lg bg-emerald-600 px-2.5 py-1 text-white">P <span class="font-semibold">Present</span></span>
                    <span class="rounded-lg bg-rose-600 px-2.5 py-1 text-white">A <span class="font-semibold">Absent</span></span>
                    <span class="rounded-lg bg-orange-500 px-2.5 py-1 text-white">H <span class="font-semibold">Half Day</span></span>
                    <span class="rounded-lg bg-slate-950 px-2.5 py-1 text-white">L <span class="font-semibold">Leave</span></span>
                    <span class="rounded-lg bg-slate-200 px-2.5 py-1 text-slate-600">— <span class="font-semibold">Not Marked</span></span>
                </div>
            </div>

            <div class="overflow-x-auto overscroll-x-contain" tabindex="0" aria-label="Monthly attendance table, horizontally scrollable">
                <table class="w-max min-w-full border-separate border-spacing-0 text-left">
                    <thead>
                        <tr>
                            <th scope="col" class="sticky left-0 z-30 min-w-64 border-b border-r border-slate-200 bg-slate-50 px-4 py-3 text-xs font-black uppercase tracking-wider text-slate-700 shadow-[5px_0_8px_-6px_rgba(15,23,42,0.35)]">Employee</th>
                            @foreach($monthDays as $day)
                                <th scope="col" class="min-w-12 border-b border-r border-slate-200 px-1 py-2 text-center {{ $day->isSameDay($selectedDate) ? 'bg-emerald-50' : ($day->isWeekend() ? 'bg-slate-100' : 'bg-slate-50') }}">
                                    <span class="block text-[9px] font-black uppercase text-slate-400">{{ $day->format('D') }}</span>
                                    <span class="mt-0.5 block text-xs font-black {{ $day->isSameDay($selectedDate) ? 'text-emerald-700' : 'text-slate-800' }}">{{ $day->format('d') }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $employee)
                            @php($employeeAttendance = $monthlyAttendanceByEmployee->get($employee->id, collect()))
                            @php($selectedAttendance = $employeeAttendance->get($selectedDate->toDateString()))
                            <tr class="group">
                                <th scope="row" class="sticky left-0 z-20 border-b border-r border-slate-200 bg-white px-4 py-2.5 shadow-[5px_0_8px_-6px_rgba(15,23,42,0.35)] group-hover:bg-slate-50">
                                    <a href="{{ route('admin.staff.assignments.show', $employee) }}" class="block max-w-56 truncate text-xs font-black text-slate-950 hover:text-emerald-700 hover:underline">{{ $employee->name }}</a>
                                    <span class="block max-w-56 truncate text-[10px] font-semibold text-slate-400">{{ $employee->employee_code }} · {{ $employee->defaultShop?->name ?? 'UNALLOCATED STAFF' }}</span>
                                    <button type="button"
                                            class="js-open-details-modal mt-1 text-[10px] font-bold text-emerald-700 hover:underline"
                                            data-employee-code="{{ $employee->employee_code }}"
                                            data-employee-name="{{ e($employee->name) }}"
                                            data-employee-category="{{ e($employee->category?->name ?? '') }}"
                                            data-employee-photo="{{ $employee->photo_url }}"
                                            data-employee-phone="{{ $employee->phone }}"
                                            data-employee-emergency="{{ $employee->alternate_phone }}"
                                            data-shop-name="{{ e($selectedAttendance?->shop?->name ?? $employee->defaultShop?->name ?? 'UNALLOCATED STAFF') }}"
                                            data-status="{{ $selectedAttendance ? match($selectedAttendance->status) { 'present' => 'P (Present)', 'half_day' => 'H (Half Day)', 'leave' => 'L (Leave)', 'absent' => 'A (Absent)', default => ucfirst($selectedAttendance->status) } : 'Not Marked' }}"
                                            data-marked-at="{{ $selectedAttendance?->marked_at?->timezone('Asia/Kolkata')->format('g:i A') ?? '—' }}"
                                            data-marked-by="{{ e($selectedAttendance?->markedBy?->name ?? '—') }}"
                                            data-source="{{ ucfirst($selectedAttendance?->source ?? 'admin') }}"
                                            data-notes="{{ e($selectedAttendance?->notes ?? '') }}"
                                            data-calendar-url="{{ route('admin.staff.assignments.show', $employee) }}">Details</button>
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
                                    <td class="border-b border-r border-slate-200 p-1 text-center {{ $day->isSameDay($selectedDate) ? 'bg-emerald-50/60' : ($day->isWeekend() ? 'bg-slate-50' : 'bg-white') }}">
                                        <button type="button"
                                                class="js-open-attendance-modal flex h-8 w-8 items-center justify-center rounded-lg text-xs font-black transition focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 {{ $statusStyles[2] }}"
                                                title="{{ $employee->name }} — {{ $day->format('d M') }}: {{ $statusStyles[1] }}"
                                                aria-label="{{ $employee->name }}, {{ $day->format('d F Y') }}, {{ $statusStyles[1] }}"
                                                data-employee-id="{{ $employee->id }}"
                                                data-employee-name="{{ e($employee->name) }}"
                                                data-attendance-date="{{ $day->toDateString() }}"
                                                data-shop-id="{{ $attendance?->shop_id ?? $employee->default_shop_id ?? '' }}"
                                                data-status="{{ $status ?? 'present' }}"
                                                data-notes="{{ e($attendance?->notes ?? '') }}">{{ $statusStyles[0] }}</button>
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $monthDays->count() + 1 }}" class="p-12 text-center text-xs font-semibold text-slate-400">No active employees match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @else
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-4">
                <div>
                    <h2 class="text-sm font-black text-slate-950">Pending payments for {{ $selectedDate->format('F Y') }}</h2>
                    <p class="text-xs font-semibold text-slate-500">Remaining salary after all recorded salary and advance payments.</p>
                </div>
                <div class="rounded-xl bg-emerald-50 px-4 py-2 text-right">
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Total Pending Payment</p>
                    <p class="text-lg font-black text-emerald-900">₹{{ number_format($totalPendingPayment, 2) }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-black uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Employee</th>
                            <th class="px-4 py-3">Shop</th>
                            <th class="px-4 py-3 text-right">Pending Payment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($pendingPaymentRows as $row)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.staff.show', $row['employee']) }}" class="font-black text-slate-950 hover:text-emerald-700 hover:underline">{{ $row['employee']->name }}</a>
                                    <p class="text-xs font-semibold text-slate-400">{{ $row['employee']->employee_code }}</p>
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-600">{{ $row['shop']?->name ?? 'UNALLOCATED STAFF' }}</td>
                                <td class="px-4 py-3 text-right text-base font-black {{ $row['pending_amount'] > 0 ? 'text-rose-700' : 'text-emerald-700' }}">₹{{ number_format($row['pending_amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-12 text-center text-sm font-semibold text-slate-400">No employees match the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                        <tr>
                            <th colspan="2" class="px-4 py-4 text-sm font-black text-slate-950">Total Pending Payment</th>
                            <th class="px-4 py-4 text-right text-lg font-black text-rose-700">₹{{ number_format($totalPendingPayment, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif

        <!-- REUSED ADD / EDIT ATTENDANCE MODAL -->
        <div id="admin-attendance-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div id="admin-attendance-modal-backdrop" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl border border-slate-200 space-y-4 z-10">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 uppercase">Mark Attendance</h3>
                        <p id="attendance-modal-subtitle" class="text-xs font-semibold text-slate-400">Record employee attendance</p>
                    </div>
                    <button type="button" id="btn-close-attendance-modal" class="text-slate-400 hover:text-slate-700 text-sm font-bold cursor-pointer">✕</button>
                </div>

                <form method="POST" action="{{ route('admin.staff.attendance.store') }}" class="space-y-3">
                    @csrf

                    <!-- Employee Select -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Employee *</label>
                        <select id="attendance-modal-employee-id" name="employee_id" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                            <option value="">-- Select Employee --</option>
                            @foreach($allActiveEmployees as $empOpt)
                                <option value="{{ $empOpt->id }}">{{ $empOpt->name }} ({{ $empOpt->employee_code }}) {{ $empOpt->defaultShop ? '· '.$empOpt->defaultShop->name : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Attendance Date -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Date *</label>
                        <input id="attendance-modal-date" type="date" name="attendance_date" value="{{ $selectedDate->format('Y-m-d') }}" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                    </div>

                    <!-- Work Location / Shop -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Assigned / Worked Shop</label>
                        <select id="attendance-modal-shop-id" name="shop_id" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-semibold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
                            <option value="">Office / Default Assignment</option>
                            @foreach($shops as $shopOpt)
                                <option value="{{ $shopOpt->id }}">{{ $shopOpt->name }} ({{ $shopOpt->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Status Radio Buttons -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Status *</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-2 text-xs font-bold text-slate-800 cursor-pointer hover:bg-emerald-50/50">
                                <input type="radio" name="status" value="present" class="accent-emerald-600" checked>
                                <span>P — Present</span>
                            </label>
                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-2 text-xs font-bold text-slate-800 cursor-pointer hover:bg-amber-50/50">
                                <input type="radio" name="status" value="half_day" class="accent-amber-600">
                                <span>H — Half Day</span>
                            </label>
                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-2 text-xs font-bold text-slate-800 cursor-pointer hover:bg-slate-100">
                                <input type="radio" name="status" value="leave" class="accent-slate-950">
                                <span>L — Leave</span>
                            </label>
                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-2 text-xs font-bold text-slate-800 cursor-pointer hover:bg-rose-50/50">
                                <input type="radio" name="status" value="absent" class="accent-rose-600">
                                <span>A — Absent</span>
                            </label>
                        </div>
                    </div>

                    <!-- Reason / Notes -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Reason / Note <span class="text-rose-500 font-normal">(Required for Half Day, Leave, Absent)</span></label>
                        <input type="text" id="attendance-modal-notes" name="notes" placeholder="e.g. Personal leave / Sick / Medical" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-semibold text-slate-900">
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" id="btn-cancel-attendance-modal" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 cursor-pointer">Cancel</button>
                        <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2 text-xs font-black text-white hover:bg-emerald-700 cursor-pointer">Save Attendance</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- EMPLOYEE DETAILS MODAL -->
        <div id="admin-attendance-details-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div id="admin-attendance-details-backdrop" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl border border-slate-200 space-y-4 z-10">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-black text-slate-900 uppercase">Attendance & Employee Details</h3>
                    <button type="button" id="btn-close-details-modal" class="text-slate-400 hover:text-slate-700 text-sm font-bold cursor-pointer">✕</button>
                </div>

                <!-- PROFILE HEADER -->
                <div class="flex items-center gap-3">
                    <div id="details-modal-avatar-container" class="shrink-0">
                        <div id="details-modal-initials" class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-900 text-sm font-bold text-white"></div>
                        <img id="details-modal-photo" src="" class="hidden h-12 w-12 rounded-full object-cover border border-slate-200" alt="">
                    </div>
                    <div class="min-w-0">
                        <h4 id="details-modal-name" class="text-base font-black text-slate-950 truncate"></h4>
                        <p id="details-modal-meta" class="text-xs font-semibold text-slate-400"></p>
                    </div>
                </div>

                <!-- DETAILS GRID -->
                <div class="space-y-2 text-xs">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Assigned Work Location for Date</p>
                        <p id="details-modal-shop" class="font-black text-slate-900">—</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Status</p>
                            <p id="details-modal-status" class="font-bold text-slate-900">—</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Marked Time</p>
                            <p id="details-modal-marked-at" class="font-bold text-slate-900">—</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Primary Phone</p>
                            <p id="details-modal-phone" class="font-semibold text-slate-800">—</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Emergency Contact</p>
                            <p id="details-modal-emergency" class="font-semibold text-slate-800">—</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Marked By & Source</p>
                        <p id="details-modal-marked-by" class="font-semibold text-slate-800">—</p>
                    </div>

                    <div id="details-modal-notes-container" class="hidden rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Reason / Note</p>
                        <p id="details-modal-notes" class="font-semibold text-slate-700 whitespace-pre-line"></p>
                    </div>
                </div>

                <!-- FOOTER ACTIONS -->
                <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                    <a id="details-modal-calendar-link" href="#" class="rounded-xl border border-slate-200 px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100">
                        View Employee Calendar
                    </a>
                    <button type="button" id="btn-cancel-details-modal" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white cursor-pointer">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PLAIN JAVASCRIPT MODAL EVENT HANDLERS -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Attendance Modal Elements
            const attModal = document.getElementById('admin-attendance-modal');
            const attBackdrop = document.getElementById('admin-attendance-modal-backdrop');
            const attCloseBtn = document.getElementById('btn-close-attendance-modal');
            const attCancelBtn = document.getElementById('btn-cancel-attendance-modal');
            const attSubtitle = document.getElementById('attendance-modal-subtitle');
            const attEmployeeSelect = document.getElementById('attendance-modal-employee-id');
            const attDateInput = document.getElementById('attendance-modal-date');
            const attShopSelect = document.getElementById('attendance-modal-shop-id');
            const attNotesInput = document.getElementById('attendance-modal-notes');

            function openAttendanceModal(empId, empName, attendanceDate, shopId, status, notes) {
                if (!attModal) return;

                if (attEmployeeSelect && empId) {
                    attEmployeeSelect.value = empId;
                } else if (attEmployeeSelect) {
                    attEmployeeSelect.value = '';
                }

                if (attShopSelect && shopId) {
                    attShopSelect.value = shopId;
                } else if (attShopSelect) {
                    attShopSelect.value = '';
                }

                if (attNotesInput) {
                    attNotesInput.value = notes || '';
                }

                if (attDateInput && attendanceDate) {
                    attDateInput.value = attendanceDate;
                }

                const radioStatus = attModal.querySelector('input[name="status"][value="' + (status || 'present') + '"]');
                if (radioStatus) {
                    radioStatus.checked = true;
                }

                if (attSubtitle) {
                    attSubtitle.textContent = empName ? 'Mark attendance for ' + empName : 'Record employee attendance';
                }

                attModal.classList.remove('hidden');
            }

            function closeAttendanceModal() {
                if (attModal) attModal.classList.add('hidden');
            }

            document.querySelectorAll('.js-open-attendance-modal').forEach(function (button) {
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const empId = button.getAttribute('data-employee-id') || '';
                    const empName = button.getAttribute('data-employee-name') || '';
                    const attendanceDate = button.getAttribute('data-attendance-date') || '';
                    const shopId = button.getAttribute('data-shop-id') || '';
                    const status = button.getAttribute('data-status') || 'present';
                    const notes = button.getAttribute('data-notes') || '';

                    openAttendanceModal(empId, empName, attendanceDate, shopId, status, notes);
                });
            });

            if (attCloseBtn) attCloseBtn.addEventListener('click', closeAttendanceModal);
            if (attCancelBtn) attCancelBtn.addEventListener('click', closeAttendanceModal);
            if (attBackdrop) attBackdrop.addEventListener('click', closeAttendanceModal);

            // Details Modal Elements
            const detModal = document.getElementById('admin-attendance-details-modal');
            const detBackdrop = document.getElementById('admin-attendance-details-backdrop');
            const detCloseBtn = document.getElementById('btn-close-details-modal');
            const detCancelBtn = document.getElementById('btn-cancel-details-modal');

            const elPhoto = document.getElementById('details-modal-photo');
            const elInitials = document.getElementById('details-modal-initials');
            const elName = document.getElementById('details-modal-name');
            const elMeta = document.getElementById('details-modal-meta');
            const elShop = document.getElementById('details-modal-shop');
            const elStatus = document.getElementById('details-modal-status');
            const elMarkedAt = document.getElementById('details-modal-marked-at');
            const elPhone = document.getElementById('details-modal-phone');
            const elEmergency = document.getElementById('details-modal-emergency');
            const elMarkedBy = document.getElementById('details-modal-marked-by');
            const elNotesContainer = document.getElementById('details-modal-notes-container');
            const elNotes = document.getElementById('details-modal-notes');
            const elCalendarLink = document.getElementById('details-modal-calendar-link');

            document.querySelectorAll('.js-open-details-modal').forEach(function (button) {
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
                    const markedAt = button.getAttribute('data-marked-at') || '—';
                    const markedBy = button.getAttribute('data-marked-by') || '—';
                    const source = button.getAttribute('data-source') || 'admin';
                    const notes = button.getAttribute('data-notes') || '';
                    const calendarUrl = button.getAttribute('data-calendar-url') || '#';

                    if (elName) elName.textContent = name;
                    if (elMeta) elMeta.textContent = code + (category ? ' · ' + category : '');
                    if (elShop) elShop.textContent = shop;
                    if (elStatus) elStatus.textContent = status;
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

                    if (elCalendarLink) elCalendarLink.href = calendarUrl;

                    if (detModal) detModal.classList.remove('hidden');
                });
            });

            function closeDetailsModal() {
                if (detModal) detModal.classList.add('hidden');
            }

            if (detCloseBtn) detCloseBtn.addEventListener('click', closeDetailsModal);
            if (detCancelBtn) detCancelBtn.addEventListener('click', closeDetailsModal);
            if (detBackdrop) detBackdrop.addEventListener('click', closeDetailsModal);

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeAttendanceModal();
                    closeDetailsModal();
                }
            });
        });
    </script>
</x-layouts.staff>
