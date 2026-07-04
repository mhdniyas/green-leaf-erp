@extends('shop-owner.layouts.app')

@section('title', 'Staff')
@section('page_title', 'Staff Attendance')
@section('page_description', 'Mark today attendance for selected owned shops, keep a reusable employee list, and submit leave details for HR review.')
@php
    $breadcrumbs = [['label' => 'Staff']];
@endphp

@section('content')
    @php
        $statusStyles = [
            'present' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'half_day' => 'border-amber-200 bg-amber-50 text-amber-800',
            'leave' => 'border-cyan-200 bg-cyan-50 text-cyan-800',
            'absent' => 'border-rose-200 bg-rose-50 text-rose-800',
        ];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Owned Shop Staff</h1>
                <p class="text-sm font-semibold text-slate-500">Keep a quick staff list per owned shop, mark check-in attendance, and capture leave reasons for HR.</p>
            </div>

            <form method="GET" class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                <select name="shop" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" onchange="this.form.submit()">
                    @foreach($shops as $shop)
                        <option value="{{ $shop->code }}" @selected($selectedShop?->id === $shop->id)>{{ $shop->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white">Load</button>
            </form>
        </div>

        @if($shops->isEmpty())
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
                No owned shop is assigned to this account yet. Staff attendance and leave requests require an owned shop assignment.
            </div>
        @else
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    'Quick Staff' => $employees->count(),
                    'Present Today' => $attendanceRecords->where('status', 'present')->count(),
                    'Half Day' => $attendanceRecords->where('status', 'half_day')->count(),
                    'Pending Leaves' => $leaveRequests->where('status', 'pending')->count(),
                ] as $label => $value)
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">{{ $label }}</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">{{ $value }}</p>
                    </article>
                @endforeach
            </section>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800">{{ $errors->first() }}</div>
        @endif

        @if($ownerEmployee && $selectedShop)
            @php($ownerStatus = $ownerAttendance?->status)
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">Owner Check-In</p>
                        <h2 class="mt-2 text-xl font-black text-slate-950">{{ auth()->user()->name }}</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">{{ $selectedShop->name }} · {{ $selectedDate->format('d M Y') }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] {{ $statusStyles[$ownerStatus] ?? 'border-slate-200 bg-slate-100 text-slate-700' }}">
                            {{ $ownerStatus === 'present' ? 'checked in' : ($ownerStatus ? str_replace('_', ' ', $ownerStatus) : 'not checked in') }}
                        </span>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Time</p>
                            <p class="mt-1 text-sm font-black text-slate-950">{{ $ownerAttendance?->marked_at?->format('h:i A') ?? 'Pending' }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <div>
                        <p class="text-sm font-black text-slate-950">Mark today attendance for yourself.</p>
                        <p class="mt-1 text-sm font-semibold text-slate-500">No checkout needed. This records your owner check-in for the selected owned shop.</p>
                    </div>

                    @if($selectedDate->isToday())
                        <form method="POST" action="{{ route('shop-owner.staff.attendance.store') }}">
                            @csrf
                            <input type="hidden" name="employee_id" value="{{ $ownerEmployee->id }}">
                            <input type="hidden" name="attendance_date" value="{{ $selectedDate->format('Y-m-d') }}">
                            <input type="hidden" name="shop_id" value="{{ $selectedShop->id }}">
                            <input type="hidden" name="status" value="present">
                            <button type="submit" class="rounded-xl bg-slate-950 px-5 py-3 text-sm font-black text-white">
                                {{ $ownerAttendance ? 'Refresh Check-In' : 'Check In Now' }}
                            </button>
                        </form>
                    @else
                        <p class="text-sm font-semibold text-slate-500">Owner check-in can only be marked for today.</p>
                    @endif
                </div>
            </section>
        @endif

        <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-black text-slate-950">Quick Attendance List</h2>
                        <p class="text-sm font-semibold text-slate-500">
                            {{ $selectedShop?->name ?? 'Select shop' }} · {{ $selectedDate->format('d M Y') }}
                        </p>
                    </div>
                </div>

                @if($employees->isEmpty())
                    <div class="mt-5 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm font-semibold text-slate-500">
                        No quick staff added for this shop yet. Search and add shop employees from the panel on the right.
                    </div>
                @else
                    <div class="mt-5 space-y-4">
                        @foreach($employees as $employee)
                            @php($attendance = $attendanceRecords->get($employee->id))
                            @php($status = $attendance?->status ?? 'absent')
                            <form method="POST" action="{{ route('shop-owner.staff.attendance.store') }}" class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-5">
                                @csrf
                                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                                <input type="hidden" name="attendance_date" value="{{ $selectedDate->format('Y-m-d') }}">
                                <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">

                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-lg font-black text-slate-950">{{ $employee->name }}</p>
                                            <span class="rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] {{ $statusStyles[$status] ?? 'border-slate-200 bg-slate-100 text-slate-700' }}">
                                                {{ $status === 'present' ? 'full day' : str_replace('_', ' ', $status) }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-sm font-semibold text-slate-500">{{ $employee->employee_code }} · {{ $employee->category->name }}</p>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <article class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Check In</p>
                                            <p class="mt-1 text-sm font-black text-slate-950">{{ $attendance?->marked_at?->format('h:i A') ?? 'Not marked' }}</p>
                                        </article>
                                        <article class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Leave Status</p>
                                            <p class="mt-1 text-sm font-black text-slate-950">
                                                @if($status === 'leave')
                                                    {{ ucfirst($leaveRequests->firstWhere('employee_id', $employee->id)?->status ?? 'pending') }}
                                                @else
                                                    n/a
                                                @endif
                                            </p>
                                        </article>
                                    </div>
                                </div>

                                <div class="mt-5 grid gap-3 xl:grid-cols-[1fr_1.2fr_1.4fr_auto]">
                                    <select name="status" class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold">
                                        <option value="present" @selected($status === 'present')>Full Day</option>
                                        <option value="half_day" @selected($status === 'half_day')>Half Day</option>
                                        <option value="leave" @selected($status === 'leave')>Leave</option>
                                        <option value="absent" @selected($status === 'absent')>Absent</option>
                                    </select>

                                    <input type="text" name="leave_reason" value="{{ $status === 'leave' ? ($attendance?->notes ?? '') : '' }}" placeholder="Leave reason if on leave" class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold">

                                    <input type="text" name="notes" value="{{ $status !== 'leave' ? ($attendance?->notes ?? '') : '' }}" placeholder="Notes for full day / half day / absent" class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold">

                                    <button type="submit" class="rounded-xl bg-emerald-500 px-5 py-3 text-sm font-black text-white">Save</button>
                                </div>
                            </form>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-black text-slate-950">Add Shop Employees</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Search all shop staff and add them to this shop&apos;s quick attendance list.</p>

                    <form method="GET" class="mt-5 flex flex-wrap gap-2">
                        <input type="hidden" name="shop" value="{{ $selectedShop?->code }}">
                        <input type="search" name="employee_search" value="{{ $employeeSearch }}" placeholder="Search employee name, code, phone" class="flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white">Search</button>
                    </form>

                    <div class="mt-5 space-y-3">
                        @forelse($searchResults as $employee)
                            <form method="POST" action="{{ route('shop-owner.staff.employees.store') }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                @csrf
                                <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
                                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                                <div>
                                    <p class="font-black text-slate-950">{{ $employee->name }}</p>
                                    <p class="text-xs font-semibold text-slate-500">{{ $employee->employee_code }} · {{ $employee->category->name }}</p>
                                </div>
                                <button type="submit" class="rounded-xl bg-cyan-500 px-4 py-2 text-sm font-black text-slate-950">Add to Shop</button>
                            </form>
                        @empty
                            <p class="text-sm font-semibold text-slate-500">{{ $employeeSearch === '' ? 'Search to add more employees to this shop list.' : 'No matching shop employees found.' }}</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-black text-slate-950">Request Leave</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Submit leave for shop staff only. Office and other HR-managed staff leave must be handled from HR.</p>

                    <form method="POST" action="{{ route('shop-owner.staff.leave-requests.store') }}" class="mt-5 space-y-3">
                        @csrf
                        <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
                        <select name="employee_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                            <option value="">Select employee</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((int) old('employee_id') === $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                        <input type="date" name="start_date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <input type="date" name="end_date" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <textarea name="reason" rows="3" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Reason" required>{{ old('reason') }}</textarea>
                        <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white">Submit Leave Request</button>
                    </form>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-black text-slate-950">Recent Leave Updates</h2>
                    <div class="mt-5 space-y-3">
                        @forelse($leaveRequests as $leaveRequest)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-black text-slate-900">{{ $leaveRequest->employee->name }}</p>
                                        <p class="text-xs font-semibold text-slate-500">{{ $leaveRequest->start_date->format('d M') }} to {{ $leaveRequest->end_date->format('d M') }} · {{ $leaveRequest->submittedForShop?->name }}</p>
                                    </div>
                                    <span class="rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] {{ $leaveRequest->status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : ($leaveRequest->status === 'rejected' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-amber-200 bg-amber-50 text-amber-800') }}">
                                        {{ $leaveRequest->status }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm font-semibold text-slate-600">{{ $leaveRequest->reason }}</p>
                            </div>
                        @empty
                            <p class="text-sm font-semibold text-slate-500">No leave requests yet for this owned shop.</p>
                        @endforelse
                    </div>
                </section>
            </section>
        </div>
    </div>
@endsection
