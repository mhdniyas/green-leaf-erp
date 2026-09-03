@extends('shop-owner.layouts.app')

@section('title', 'Staff')
@section('page_title', 'Client Shop Staff')
@section('page_description', 'Handle attendance, advances, salary, leave, and staff history for client shops.')

@php
    $breadcrumbs = [['label' => 'Staff']];
    $tabs = [
        'staff' => 'Staff',
        'attendance' => 'Attendance',
        'salary' => 'Salary',
    ];
    $statusStyles = [
        'present' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'half_day' => 'border-amber-200 bg-amber-50 text-amber-800',
        'leave' => 'border-cyan-200 bg-cyan-50 text-cyan-800',
        'absent' => 'border-rose-200 bg-rose-50 text-rose-800',
    ];

    $activeStaffCount = $employees->count();
    $presentTodayCount = $attendanceRecords->filter(fn($a) => in_array($a->status, ['present', 'half_day'], true))->count();
    $pendingCount = isset($pendingEmployees) ? $pendingEmployees->where('verification_status', 'pending')->count() : 0;
@endphp

@section('content')
    <div class="mx-auto w-full max-w-5xl space-y-3" data-staff-advance-options='@json($advanceOptions)' data-staff-salary-options='@json($salaryOptions)'>
        
        <!-- COMPACT PAGE HEADER -->
        <header class="flex items-center justify-between gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-xs">
            <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                    <h1 class="text-base font-black text-slate-950 sm:text-lg">Staff</h1>
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">{{ $selectedShop?->name }}</span>
                </div>
                <p class="text-[11px] font-semibold text-slate-400 truncate">{{ $selectedDate->format('d M Y') }} · Client Shop HR</p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('shop-owner.staff.create', ['shop' => $selectedShop?->code]) }}" class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-emerald-600 px-3 text-xs font-black text-white shadow-xs hover:bg-emerald-700 active:scale-95 transition">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>+ Add Staff</span>
                </a>
            </div>
        </header>

        <!-- 3 SUMMARY TILES IN ONE ROW ON MOBILE -->
        <div class="grid grid-cols-3 gap-2">
            <!-- TILE 1: ACTIVE STAFF -->
            <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
                <div class="flex items-center gap-1">
                    <svg class="h-3.5 w-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                    <span class="text-[9px] font-black uppercase tracking-wider text-slate-500 truncate">Staff</span>
                </div>
                <p class="mt-1 text-lg font-black text-slate-950 leading-none">{{ $activeStaffCount }}</p>
            </div>

            <!-- TILE 2: PRESENT TODAY -->
            <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
                <div class="flex items-center gap-1">
                    <svg class="h-3.5 w-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-[9px] font-black uppercase tracking-wider text-slate-500 truncate">Present</span>
                </div>
                <p class="mt-1 text-lg font-black text-emerald-700 leading-none">{{ $presentTodayCount }}</p>
            </div>

            <!-- TILE 3: PENDING HR -->
            <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
                <div class="flex items-center gap-1">
                    <svg class="h-3.5 w-3.5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-[9px] font-black uppercase tracking-wider text-slate-500 truncate">Pending</span>
                </div>
                <p class="mt-1 text-lg font-black text-amber-700 leading-none">{{ $pendingCount }}</p>
            </div>
        </div>

        <!-- 3 MAIN TABS IN ONE ROW ON MOBILE -->
        @if(!$shops->isEmpty())
            <div class="space-y-1.5">
                <nav class="grid grid-cols-3 gap-1 rounded-xl border border-slate-200 bg-slate-100 p-1">
                    <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'staff']) }}"
                       class="flex items-center justify-center gap-1 rounded-lg py-2 text-center text-xs font-black transition {{ in_array($selectedTab, ['staff', 'attendance'], true) && $selectedTab === 'staff' ? 'bg-slate-950 text-white shadow-xs' : 'text-slate-700 hover:bg-white' }}">
                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                        <span>Staff</span>
                    </a>
                    <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'attendance']) }}"
                       class="flex items-center justify-center gap-1 rounded-lg py-2 text-center text-xs font-black transition {{ $selectedTab === 'attendance' ? 'bg-slate-950 text-white shadow-xs' : 'text-slate-700 hover:bg-white' }}">
                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Attendance</span>
                    </a>
                    <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'salary']) }}"
                       class="flex items-center justify-center gap-1 rounded-lg py-2 text-center text-xs font-black transition {{ $selectedTab === 'salary' ? 'bg-slate-950 text-white shadow-xs' : 'text-slate-700 hover:bg-white' }}">
                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v8m-4-4h8" />
                        </svg>
                        <span>Salary</span>
                    </a>
                </nav>

                <!-- SECONDARY LINKS (ADVANCE, LEAVE, HISTORY) -->
                <div class="flex items-center justify-center gap-2 text-[11px] font-bold text-slate-500">
                    <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'advance']) }}" class="{{ $selectedTab === 'advance' ? 'text-emerald-600 font-black underline' : 'hover:text-slate-900' }}">Advance</a>
                    <span>·</span>
                    <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'leave']) }}" class="{{ $selectedTab === 'leave' ? 'text-emerald-600 font-black underline' : 'hover:text-slate-900' }}">Leave</a>
                    <span>·</span>
                    <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'history']) }}" class="{{ $selectedTab === 'history' ? 'text-emerald-600 font-black underline' : 'hover:text-slate-900' }}">History</a>
                </div>
            </div>
        @endif

        @if(in_array($selectedTab, ['advance', 'salary', 'leave', 'history'], true))
            @include('shop-owner.partials.date-range-filter', [
                'action' => route('shop-owner.staff.index'),
                'hidden' => [
                    'shop' => $selectedShop?->code,
                    'date' => $selectedDate->format('Y-m-d'),
                    'tab' => $selectedTab,
                ],
                'startDate' => $filterStartDate,
                'endDate' => $filterEndDate,
                'clearUrl' => route('shop-owner.staff.index', [
                    'shop' => $selectedShop?->code,
                    'date' => $selectedDate->format('Y-m-d'),
                    'tab' => $selectedTab,
                ]),
            ])
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-800">{{ $errors->first() }}</div>
        @endif

        <!-- TAB 1: STAFF DIRECTORY LIST (COMPACT ROWS) -->
        @if($selectedTab === 'staff')
            <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Active Shop Staff ({{ $employees->count() }})</h2>
                    <form method="GET" class="flex items-center gap-1.5">
                        <input type="hidden" name="tab" value="staff">
                        <input type="hidden" name="shop" value="{{ $selectedShop?->code }}">
                        <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="h-8 rounded-lg border border-slate-200 px-2 text-xs font-bold" onchange="this.form.submit()">
                    </form>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($employees as $employee)
                        @php($attendance = $attendanceRecords->get($employee->id))
                        @php($status = $attendance?->status ?? 'absent')
                        <div class="flex items-center justify-between py-2.5 gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                @if($employee->photo_url)
                                    <img src="{{ $employee->photo_url }}" class="h-9 w-9 rounded-full object-cover border border-slate-200 shrink-0" alt="{{ $employee->name }}">
                                @else
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-900 font-bold text-white text-xs shrink-0">
                                        {{ Illuminate\Support\Str::upper(substr($employee->name, 0, 2)) }}
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 truncate">
                                        <p class="text-xs font-black text-slate-950 truncate">{{ $employee->name }}</p>
                                        <span class="rounded px-1.5 py-0.5 text-[9px] font-black uppercase border shrink-0 {{ $statusStyles[$status] ?? 'border-slate-200 bg-slate-100 text-slate-600' }}">
                                            {{ $status === 'present' ? '✓ Present' : str_replace('_', ' ', ucfirst((string) $status)) }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] font-semibold text-slate-400 truncate">
                                        {{ $employee->employee_code }} · Primary: {{ $employee->phone ?: 'N/A' }} · Emergency: {{ $employee->alternate_phone ?: 'N/A' }}
                                    </p>
                                </div>
                            </div>

                            <div class="text-right shrink-0">
                                <span class="text-xs font-black text-slate-900">
                                    ₹{{ number_format((float) ($employee->salary_type === 'daily_wage' ? $employee->daily_wage : $employee->monthly_salary), 0) }}
                                </span>
                                <p class="text-[10px] font-semibold text-slate-400 capitalize">{{ $employee->salary_type }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs font-semibold text-slate-400">
                            No active shop staff registered for this shop.
                        </div>
                    @endforelse
                </div>
            </section>
        @endif

        <!-- TAB 2: ATTENDANCE (FAST ONE-TAP CHECK-IN & REASON MODAL) -->
        @if($selectedTab === 'attendance')
            <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-2" x-data="{ openReasonModal: false, targetForm: null, targetStatus: '', targetLabel: '', reasonInput: '' }">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Quick Check-In</h2>
                        <p class="text-[11px] font-semibold text-slate-400">{{ $selectedDate->format('d M Y') }}</p>
                    </div>

                    <div>
                        @if($isAttendanceOpen)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black text-emerald-800 border border-emerald-200">
                                <span>🕙</span> Attendance open · until {{ $cutoffFormatted }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-black text-amber-900 border border-amber-200">
                                <span>🔒</span> Attendance closed · {{ $cutoffFormatted }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($employees as $employee)
                        @php($attendance = $attendanceRecords->get($employee->id))
                        @php($isMarked = $attendance !== null)
                        @php($status = $attendance?->status)
                        @php($selectedStatus = $attendance?->status ?? 'present')

                        <form method="POST" 
                              action="{{ route('shop-owner.staff.attendance.store') }}" 
                              class="py-2.5 space-y-1.5" 
                              data-owned-shop-attendance-form 
                              id="attendance-form-emp-{{ $employee->id }}"
                              x-data="{ isMarked: {{ $isMarked ? 'true' : 'false' }}, editing: {{ $isMarked ? 'false' : 'true' }} }"
                              @attendance-saved.window="if ($event.target === $el) { isMarked = true; editing = false; }">
                            @csrf
                            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                            <input type="hidden" name="attendance_date" value="{{ $selectedDate->format('Y-m-d') }}">
                            <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
                            <input type="hidden" name="notes" value="{{ $attendance?->notes }}" data-attendance-notes-input>

                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    @if($employee->photo_url)
                                        <img src="{{ $employee->photo_url }}" class="h-9 w-9 rounded-full object-cover border border-slate-200 shrink-0" alt="{{ $employee->name }}">
                                    @else
                                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-900 font-bold text-white text-xs shrink-0">
                                            {{ Illuminate\Support\Str::upper(substr($employee->name, 0, 2)) }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="text-xs font-black text-slate-950 truncate">{{ $employee->name }}</p>
                                        <p class="text-[10px] font-semibold text-slate-400 truncate">
                                            {{ $employee->employee_code }}
                                        </p>
                                    </div>
                                </div>

                                <div class="text-right shrink-0">
                                    <template x-if="isMarked && !editing">
                                        <div class="flex flex-col items-end gap-0.5">
                                            <div class="flex items-center gap-1">
                                                <span class="rounded px-2 py-0.5 text-[10px] font-black uppercase border inline-block {{ $statusStyles[$status] ?? 'border-slate-200 bg-slate-100 text-slate-600' }}" data-attendance-status-badge>
                                                    {{ $status === 'present' ? '✓ Present' : str_replace('_', ' ', ucfirst((string) $status)) }}
                                                </span>
                                                <span class="text-[10px] font-extrabold text-slate-700" data-attendance-time>
                                                    @if($attendance?->marked_at)
                                                        · {{ $attendance->marked_at->timezone('Asia/Kolkata')->format('g:i A') }}
                                                    @endif
                                                </span>
                                                @if($isAttendanceOpen)
                                                    <button type="button" @click="editing = true" class="text-[10px] font-black text-cyan-700 hover:underline cursor-pointer ml-1">[Change]</button>
                                                @endif
                                            </div>
                                            <p class="text-[10px] font-semibold text-slate-500 truncate max-w-[170px] {{ $attendance?->notes ? '' : 'hidden' }}" data-attendance-reason title="{{ $attendance?->notes }}">
                                                @if($attendance?->notes)
                                                    Reason: {{ $attendance->notes }}
                                                @endif
                                            </p>
                                        </div>
                                    </template>
                                    <template x-if="!isMarked && !editing">
                                        <span class="rounded px-2 py-0.5 text-[10px] font-black uppercase border border-slate-200 bg-slate-100 text-slate-500 inline-block">
                                            Not Marked
                                        </span>
                                    </template>
                                </div>
                            </div>

                            <!-- COMPACT 4 ACTION BUTTONS GRID -->
                            @if($isAttendanceOpen)
                                <div x-show="editing || !isMarked" class="grid grid-cols-4 gap-1.5 pt-0.5">
                                    <button type="button" 
                                            onclick="submitAttendanceStatus(this.form, 'present', '', this)"
                                            class="h-8 rounded-lg border text-center flex items-center justify-center text-[11px] font-extrabold transition cursor-pointer border-slate-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-600 hover:text-white">
                                        ✓ Present
                                    </button>

                                    <button type="button" 
                                            @click="targetForm = document.getElementById('attendance-form-emp-{{ $employee->id }}'); targetStatus = 'half_day'; targetLabel = 'Half Day'; reasonInput = '{{ e($attendance?->notes ?? '') }}'; openReasonModal = true"
                                            class="h-8 rounded-lg border text-center flex items-center justify-center text-[11px] font-extrabold transition cursor-pointer border-slate-200 bg-amber-50 text-amber-800 hover:bg-amber-500 hover:text-white">
                                        ◐ Half
                                    </button>

                                    <button type="button" 
                                            @click="targetForm = document.getElementById('attendance-form-emp-{{ $employee->id }}'); targetStatus = 'leave'; targetLabel = 'Leave'; reasonInput = '{{ e($attendance?->notes ?? '') }}'; openReasonModal = true"
                                            class="h-8 rounded-lg border text-center flex items-center justify-center text-[11px] font-extrabold transition cursor-pointer border-slate-200 bg-cyan-50 text-cyan-800 hover:bg-cyan-600 hover:text-white">
                                        L Leave
                                    </button>

                                    <button type="button" 
                                            @click="targetForm = document.getElementById('attendance-form-emp-{{ $employee->id }}'); targetStatus = 'absent'; targetLabel = 'Absent'; reasonInput = '{{ e($attendance?->notes ?? '') }}'; openReasonModal = true"
                                            class="h-8 rounded-lg border text-center flex items-center justify-center text-[11px] font-extrabold transition cursor-pointer border-slate-200 bg-rose-50 text-rose-800 hover:bg-rose-600 hover:text-white">
                                        × Absent
                                    </button>
                                </div>
                            @else
                                <div x-show="!isMarked" class="rounded-lg border border-slate-200 bg-slate-50 p-2 text-center text-[11px] font-bold text-slate-500">
                                    Not marked · Marking closed at {{ $cutoffFormatted }}. Contact HR for corrections.
                                </div>
                            @endif
                        </form>
                    @empty
                        <div class="py-6 text-center text-xs font-semibold text-slate-400">
                            No shop staff available for check-in.
                        </div>
                    @endforelse
                </div>

                <!-- COMPACT REASON MODAL -->
                <div x-show="openReasonModal" x-cloak style="display: none;" 
                     class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" @click="openReasonModal = false"></div>
                    <div class="relative w-full max-w-sm rounded-2xl bg-white p-4 shadow-xl border border-slate-200 space-y-3" @click.stop>
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <h3 class="text-xs font-black text-slate-900 uppercase">Reason for <span x-text="targetLabel" class="text-emerald-700"></span></h3>
                            <button type="button" @click="openReasonModal = false" class="text-slate-400 hover:text-slate-700 text-xs font-bold cursor-pointer">✕</button>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">Reason *</label>
                            <textarea x-model="reasonInput" rows="2" placeholder="e.g. Medical appointment / Family function / No show" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-semibold focus:border-emerald-600 focus:ring-emerald-600" required></textarea>
                        </div>
                        <div class="flex justify-end gap-2 pt-1">
                            <button type="button" @click="openReasonModal = false" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 cursor-pointer">Cancel</button>
                            <button type="button" 
                                    @click="if (reasonInput.trim().length >= 3) { submitAttendanceStatus(targetForm, targetStatus, reasonInput.trim()); openReasonModal = false; } else { window.showAppAlert?.('Please enter a reason (minimum 3 characters)'); }" 
                                    class="rounded-xl bg-emerald-600 px-4 py-1.5 text-xs font-black text-white hover:bg-emerald-700 cursor-pointer">
                                Save
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- PENDING HR APPROVAL COMPACT LIST -->
            <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Pending & Rejected Submissions</h2>
                    @if(isset($pendingEmployees) && $pendingEmployees->count() > 0)
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-900">
                            {{ $pendingEmployees->count() }}
                        </span>
                    @endif
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($pendingEmployees ?? [] as $pendingEmp)
                        <div class="py-2.5 space-y-1.5" x-data="{ expanded: false }">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    @if($pendingEmp->photo_url)
                                        <img src="{{ $pendingEmp->photo_url }}" class="h-9 w-9 rounded-full object-cover border border-slate-200 shrink-0" alt="{{ $pendingEmp->name }}">
                                    @else
                                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-900 font-bold text-white text-xs shrink-0">
                                            {{ Illuminate\Support\Str::upper(substr($pendingEmp->name, 0, 2)) }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="text-xs font-black text-slate-950 truncate">{{ $pendingEmp->name }}</p>
                                        <p class="text-[10px] font-semibold text-slate-400 truncate">{{ $pendingEmp->employee_code }} · {{ $pendingEmp->category?->name ?? 'Staff' }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1.5 shrink-0">
                                    <span class="rounded px-2 py-0.5 text-[9px] font-black uppercase border {{ $pendingEmp->verification_status === 'pending' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-rose-200 bg-rose-50 text-rose-800' }}">
                                        {{ $pendingEmp->verification_status === 'pending' ? 'Pending HR Approval' : 'Rejected' }}
                                    </span>
                                    <a href="{{ route('shop-owner.staff.employees.edit-submission', $pendingEmp) }}" class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-extrabold text-slate-700 hover:bg-slate-100">
                                        Edit
                                    </a>
                                </div>
                            </div>

                            @if($pendingEmp->verification_status === 'rejected' && $pendingEmp->rejection_reason)
                                <div class="rounded-lg border border-rose-200 bg-rose-50 p-2 text-[11px] text-rose-800 font-medium">
                                    <span class="font-bold">Reason:</span> {{ $pendingEmp->rejection_reason }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="py-4 text-center text-xs font-semibold text-slate-400">
                            No pending or rejected staff submissions.
                        </div>
                    @endforelse
                </div>
            </section>
        @endif

        <!-- TAB 3: SALARY TAB (COMPACT CASHBOOK STYLE) -->
        @if($selectedTab === 'salary')
            <section class="grid gap-3 sm:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-3">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Pay Staff Salary</h2>
                    <form method="POST" action="{{ route('shop-owner.staff.salary-payments.store') }}" class="space-y-2.5">
                        @csrf
                        <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
                        
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Date</label>
                            <input type="date" name="paid_on" value="{{ $selectedDate->format('Y-m-d') }}" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-900" required>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Employee</label>
                            <div class="relative">
                                <select name="employee_id" class="h-9 w-full appearance-none rounded-lg border border-slate-200 bg-white pl-3 pr-8 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 cursor-pointer" data-salary-employee required>
                                    <option value="">Select employee</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->employee_code }}</option>
                                    @endforeach
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-500">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-2 text-xs font-semibold text-emerald-900" data-salary-summary>
                            Select an employee to see salary balance.
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Amount (₹)</label>
                            <input type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                        </div>

                        <input type="hidden" name="fund_source" value="petty_cash">

                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Note</label>
                            <input type="text" name="notes" placeholder="Note / description" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
                        </div>

                        <button type="submit" class="h-10 w-full rounded-xl bg-emerald-600 text-xs font-black text-white hover:bg-emerald-700 transition">
                            Pay Salary
                        </button>
                    </form>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-3">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Recent Salary Payments</h2>
                    <div class="divide-y divide-slate-100">
                        @forelse($recentPayrollPayments as $payment)
                            <div class="py-2 flex items-center justify-between text-xs">
                                <div>
                                    <p class="font-black text-slate-950">{{ $payment->employee?->name }}</p>
                                    <p class="text-[10px] font-semibold text-slate-400">{{ $payment->paid_on->format('d M') }} · {{ str($payment->payment_type)->headline() }}</p>
                                </div>
                                <p class="font-black text-slate-950">₹{{ number_format((float) $payment->amount, 2) }}</p>
                            </div>
                        @empty
                            <p class="py-4 text-center text-xs font-semibold text-slate-400">No recent salary payments.</p>
                        @endforelse
                    </div>
                </article>
            </section>
        @endif

        <!-- ADVANCE TAB -->
        @if($selectedTab === 'advance')
            <section class="grid gap-3 sm:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-3">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Request Advance</h2>
                    <form method="POST" action="{{ route('shop-owner.staff.advance-requests.store') }}" class="space-y-2.5">
                        @csrf
                        <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
                        <input type="date" name="requested_on" value="{{ $selectedDate->format('Y-m-d') }}" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                        
                        <div class="relative">
                            <select name="employee_id" class="h-9 w-full appearance-none rounded-lg border border-slate-200 bg-white pl-3 pr-8 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 cursor-pointer" data-advance-employee required>
                                <option value="">Select employee</option>
                                @foreach($advanceEmployees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->employee_code }}</option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-500">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>

                        <div class="rounded-lg border border-cyan-200 bg-cyan-50 p-2 text-xs font-semibold text-cyan-900" data-advance-summary>
                            Select an employee to see available advance.
                        </div>
                        <input type="number" step="0.01" min="0.01" name="amount" placeholder="Advance amount" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" data-advance-amount required>
                        <p class="hidden rounded-lg border px-2.5 py-1.5 text-xs font-black" data-advance-decision></p>
                        <input type="hidden" name="fund_source" value="petty_cash">
                        <textarea name="request_note" rows="2" placeholder="Reason / note" class="w-full rounded-lg border border-slate-200 p-2 text-xs font-semibold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600"></textarea>
                        <button type="submit" class="h-10 w-full rounded-xl bg-cyan-600 text-xs font-black text-white hover:bg-cyan-700 transition">Submit Advance</button>
                    </form>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-3">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Advance Requests</h2>
                    <div class="divide-y divide-slate-100">
                        @forelse($advanceRequests as $advanceRequest)
                            <div class="py-2 flex items-center justify-between text-xs">
                                <div>
                                    <p class="font-black text-slate-950">{{ $advanceRequest->employee?->name }}</p>
                                    <p class="text-[10px] font-semibold text-slate-400">Requested {{ $advanceRequest->requested_on->format('d M') }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-black text-slate-950">₹{{ number_format((float) $advanceRequest->requested_amount, 2) }}</p>
                                    <span class="rounded px-1.5 py-0.5 text-[9px] font-black uppercase border {{ $advanceRequest->status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($advanceRequest->status === 'rejected' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">{{ $advanceRequest->status }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="py-4 text-center text-xs font-semibold text-slate-400">No advance requests yet.</p>
                        @endforelse
                    </div>
                </article>
            </section>
        @endif

        <!-- LEAVE TAB -->
        @if($selectedTab === 'leave')
            <section class="grid gap-3 sm:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-3">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Request Leave</h2>
                    <form method="POST" action="{{ route('shop-owner.staff.leave-requests.store') }}" class="space-y-2.5">
                        @csrf
                        <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
                        
                        <div class="relative">
                            <select name="employee_id" class="h-9 w-full appearance-none rounded-lg border border-slate-200 bg-white pl-3 pr-8 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 cursor-pointer" required>
                                <option value="">Select employee</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((int) old('employee_id') === $employee->id)>{{ $employee->name }}</option>
                                @endforeach
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-500">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" name="start_date" class="h-9 w-full rounded-lg border border-slate-200 px-2 text-xs font-bold" required>
                            <input type="date" name="end_date" class="h-9 w-full rounded-lg border border-slate-200 px-2 text-xs font-bold" required>
                        </div>
                        <textarea name="reason" rows="2" class="w-full rounded-lg border border-slate-200 p-2 text-xs font-semibold" placeholder="Reason for leave" required>{{ old('reason') }}</textarea>
                        <button type="submit" class="h-10 w-full rounded-xl bg-slate-950 text-xs font-black text-white hover:bg-slate-800 transition">Submit Leave Request</button>
                    </form>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-3">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Recent Leave Updates</h2>
                    <div class="divide-y divide-slate-100">
                        @forelse($leaveRequests as $leaveRequest)
                            <div class="py-2 flex items-center justify-between text-xs">
                                <div>
                                    <p class="font-black text-slate-950">{{ $leaveRequest->employee->name }}</p>
                                    <p class="text-[10px] font-semibold text-slate-400">{{ $leaveRequest->start_date->format('d M') }} to {{ $leaveRequest->end_date->format('d M') }}</p>
                                </div>
                                <span class="rounded px-1.5 py-0.5 text-[9px] font-black uppercase border {{ $leaveRequest->status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($leaveRequest->status === 'rejected' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">{{ $leaveRequest->status }}</span>
                            </div>
                        @empty
                            <p class="py-4 text-center text-xs font-semibold text-slate-400">No leave requests yet.</p>
                        @endforelse
                    </div>
                </article>
            </section>
        @endif

        <!-- HISTORY TAB -->
        @if($selectedTab === 'history')
            <section class="grid gap-3 sm:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-3">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Payment History</h2>
                    <div class="divide-y divide-slate-100">
                        @forelse($recentPayrollPayments as $payment)
                            <div class="py-2 flex items-center justify-between text-xs">
                                <div>
                                    <p class="font-black text-slate-950">{{ $payment->employee?->name }}</p>
                                    <p class="text-[10px] font-semibold text-slate-400">{{ $payment->paid_on->format('d M Y') }} · {{ str($payment->payment_type)->headline() }}</p>
                                </div>
                                <p class="font-black text-slate-950">₹{{ number_format((float) $payment->amount, 2) }}</p>
                            </div>
                        @empty
                            <p class="py-4 text-center text-xs font-semibold text-slate-400">No staff payments recorded.</p>
                        @endforelse
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-3">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-500">Recent Requests</h2>
                    <div class="divide-y divide-slate-100">
                        @foreach($advanceRequests as $advanceRequest)
                            <div class="py-2 flex items-center justify-between text-xs">
                                <div>
                                    <p class="font-black text-slate-950">{{ $advanceRequest->employee?->name }}</p>
                                    <p class="text-[10px] font-semibold text-slate-400">Advance · ₹{{ number_format((float) $advanceRequest->requested_amount, 2) }}</p>
                                </div>
                                <span class="rounded px-1.5 py-0.5 text-[9px] font-black uppercase border {{ $advanceRequest->status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">{{ $advanceRequest->status }}</span>
                            </div>
                        @endforeach
                        @foreach($leaveRequests as $leaveRequest)
                            <div class="py-2 flex items-center justify-between text-xs">
                                <div>
                                    <p class="font-black text-slate-950">{{ $leaveRequest->employee?->name }}</p>
                                    <p class="text-[10px] font-semibold text-slate-400">Leave · {{ $leaveRequest->start_date->format('d M') }} to {{ $leaveRequest->end_date->format('d M') }}</p>
                                </div>
                                <span class="rounded px-1.5 py-0.5 text-[9px] font-black uppercase border {{ $leaveRequest->status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">{{ $leaveRequest->status }}</span>
                            </div>
                        @endforeach
                        @if($advanceRequests->isEmpty() && $leaveRequests->isEmpty())
                            <p class="py-4 text-center text-xs font-semibold text-slate-400">No recent requests.</p>
                        @endif
                    </div>
                </article>
            </section>
        @endif
    </div>

    @push('scripts')
        <script>
            (() => {
                const root = document.querySelector('[data-staff-advance-options]');
                if (!root) return;
                const money = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const advanceOptions = JSON.parse(root.dataset.staffAdvanceOptions || '{}');
                const salaryOptions = JSON.parse(root.dataset.staffSalaryOptions || '{}');
                const advanceEmployee = document.querySelector('[data-advance-employee]');
                const advanceAmount = document.querySelector('[data-advance-amount]');
                const advanceSummary = document.querySelector('[data-advance-summary]');
                const advanceDecision = document.querySelector('[data-advance-decision]');
                const salaryEmployee = document.querySelector('[data-salary-employee]');
                const salarySummary = document.querySelector('[data-salary-summary]');

                const renderAdvance = () => {
                    if (!advanceEmployee || !advanceSummary || !advanceDecision) return;
                    const option = advanceOptions[advanceEmployee.value];
                    if (!option) {
                        advanceSummary.textContent = 'Select an employee to see available advance.';
                        advanceDecision.classList.add('hidden');
                        return;
                    }
                    advanceSummary.innerHTML = `Available ₹${money.format(option.available_amount)}<br>Already taken ₹${money.format(option.already_advanced_amount)} · Earned ₹${money.format(option.earned_amount)} · ${option.present_days} present days<br>${option.rule_label}`;
                    const amount = Number(advanceAmount?.value || 0);
                    if (amount <= 0) {
                        advanceDecision.classList.add('hidden');
                        return;
                    }
                    const needsApproval = amount > Number(option.available_amount || 0);
                    advanceDecision.textContent = needsApproval ? 'Needs HR approval (above available advance).' : 'Available now (auto-approved).';
                    advanceDecision.className = `rounded-lg border px-2.5 py-1.5 text-xs font-black ${needsApproval ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`;
                };

                const renderSalary = () => {
                    if (!salaryEmployee || !salarySummary) return;
                    const option = salaryOptions[salaryEmployee.value];
                    if (!option) {
                        salarySummary.textContent = 'Select an employee to see salary balance.';
                        return;
                    }
                    const remaining = option.remaining_amount === null ? 'Payroll pending' : `Remaining ₹${money.format(option.remaining_amount)}`;
                    salarySummary.innerHTML = `Salary ₹${money.format(option.salary_amount)}<br>Paid ₹${money.format(option.paid_amount)} · ${remaining}`;
                };

                advanceEmployee?.addEventListener('change', renderAdvance);
                advanceAmount?.addEventListener('input', renderAdvance);
                salaryEmployee?.addEventListener('change', renderSalary);
                renderAdvance();
                renderSalary();
            })();
        </script>
    @endpush
@endsection
