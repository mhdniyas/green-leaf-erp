@extends('shop-owner.layouts.app')

@section('title', 'Staff')
@section('page_title', 'Client Shop Staff')
@section('page_description', 'Handle attendance, advances, salary, leave, and staff history for client shops.')
@php
    $breadcrumbs = [['label' => 'Staff']];
@endphp

@section('content')
    @php
        $tabs = [
            'attendance' => 'Attendance',
            'advance' => 'Advance',
            'salary' => 'Salary',
            'leave' => 'Leave',
            'history' => 'History',
        ];
        $statusStyles = [
            'present' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'half_day' => 'border-amber-200 bg-amber-50 text-amber-800',
            'leave' => 'border-cyan-200 bg-cyan-50 text-cyan-800',
            'absent' => 'border-rose-200 bg-rose-50 text-rose-800',
        ];
    @endphp

    <div class="space-y-4" data-staff-advance-options='@json($advanceOptions)' data-staff-salary-options='@json($salaryOptions)'>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Client Shop Staff</h1>
                <p class="mt-1 text-sm font-semibold text-slate-500">Choose one workflow and keep the screen focused.</p>
            </div>

            <form method="GET" class="grid gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-[1fr_1fr_auto]">
                <input type="hidden" name="tab" value="{{ $selectedTab }}">
                <select name="shop" class="h-11 rounded-xl border border-slate-200 px-3 text-sm font-bold" onchange="this.form.submit()">
                    @foreach($shops as $shop)
                        <option value="{{ $shop->code }}" @selected($selectedShop?->id === $shop->id)>{{ $shop->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="h-11 rounded-xl border border-slate-200 px-3 text-sm font-bold">
                <button type="submit" class="h-11 rounded-xl bg-slate-950 px-4 text-sm font-black text-white">Load</button>
            </form>
        </div>

        @if($shops->isEmpty())
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
                No client shop is assigned to this account yet. Staff access requires a client shop assignment.
            </div>
        @else
            <nav class="grid grid-cols-2 gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm sm:grid-cols-5">
                @foreach($tabs as $tabKey => $tabLabel)
                    <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => $tabKey]) }}"
                       class="rounded-xl px-3 py-3 text-center text-xs font-black uppercase tracking-[0.12em] transition {{ $selectedTab === $tabKey ? 'bg-slate-950 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' }}">
                        {{ $tabLabel }}
                    </a>
                @endforeach
            </nav>
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
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800">{{ $errors->first() }}</div>
        @endif

        @if($selectedTab === 'attendance')
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    'Quick Staff' => $employees->count(),
                    'Present Today' => $attendanceRecords->where('status', 'present')->count(),
                    'Half Day' => $attendanceRecords->where('status', 'half_day')->count(),
                    'Pending Leaves' => $pendingLeaveCount,
                ] as $label => $value)
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">{{ $label }}</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">{{ $value }}</p>
                    </article>
                @endforeach
            </section>

            @if($ownerEmployee && $selectedShop)
                @php($ownerStatus = $ownerAttendance?->status)
                @php($ownerWasChanged = $ownerAttendance && $ownerAttendance->created_at && $ownerAttendance->updated_at && $ownerAttendance->updated_at->gt($ownerAttendance->created_at->copy()->addSecond()))
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Incharge Check-In</p>
                            <h2 class="mt-1 text-lg font-black text-slate-950">{{ auth()->user()->name }}</h2>
                            <p class="text-sm font-semibold text-slate-500">{{ $selectedShop->name }} · {{ $selectedDate->format('d M Y') }}</p>
                            @if($ownerAttendance)
                                <div class="mt-2 flex flex-wrap gap-2 text-xs font-black text-slate-500">
                                    <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-emerald-800">
                                        Checked in {{ ($ownerAttendance->created_at ?? $ownerAttendance->marked_at)?->format('h:i A') }}
                                    </span>
                                    @if($ownerAttendance->marked_at)
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                                            Latest mark {{ $ownerAttendance->marked_at->format('h:i A') }}
                                        </span>
                                    @endif
                                    @if($ownerWasChanged)
                                        <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-amber-800">
                                            Changed {{ $ownerAttendance->updated_at->format('h:i A') }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <span class="w-fit rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] {{ $statusStyles[$ownerStatus] ?? 'border-slate-200 bg-slate-100 text-slate-700' }}">
                            {{ $ownerStatus === 'present' ? 'checked in' : ($ownerStatus ? str_replace('_', ' ', $ownerStatus) : 'not checked in') }}
                        </span>
                    </div>
                    @if($selectedDate->isToday())
                        <form method="POST" action="{{ route('shop-owner.staff.attendance.store') }}" class="mt-4" data-owned-shop-attendance-form>
                            @csrf
                            <input type="hidden" name="employee_id" value="{{ $ownerEmployee->id }}">
                            <input type="hidden" name="attendance_date" value="{{ $selectedDate->format('Y-m-d') }}">
                            <input type="hidden" name="shop_id" value="{{ $selectedShop->id }}">
                            <input type="hidden" name="status" value="present">
                            <button type="submit" class="w-full rounded-xl bg-emerald-600 px-5 py-3 text-sm font-black text-white sm:w-auto" data-attendance-submit>
                                {{ $ownerAttendance ? 'Update Check-In' : 'Check In Now' }}
                            </button>
                        </form>
                    @endif
                </section>
            @elseif($selectedShop)
                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-bold text-amber-900 shadow-sm sm:p-5">
                    HR needs to link your user account to an employee profile before incharge check-in is available.
                </section>
            @endif

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <h2 class="text-lg font-black text-slate-950">Quick Attendance</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">{{ $selectedShop?->name ?? 'Select shop' }} · {{ $selectedDate->format('d M Y') }}</p>

                <div class="mt-4 space-y-3">
                    @forelse($employees as $employee)
                        @php($attendance = $attendanceRecords->get($employee->id))
                        @php($status = $attendance?->status ?? 'absent')
                        @php($selectedStatus = $attendance?->status ?? 'present')
                        @php($wasChanged = $attendance && $attendance->created_at && $attendance->updated_at && $attendance->updated_at->gt($attendance->created_at->copy()->addSecond()))
                        <form method="POST" action="{{ route('shop-owner.staff.attendance.store') }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4" data-owned-shop-attendance-form>
                            @csrf
                            <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                            <input type="hidden" name="attendance_date" value="{{ $selectedDate->format('Y-m-d') }}">
                            <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-black text-slate-950">{{ $employee->name }}</p>
                                        <span class="rounded-full border px-2 py-1 text-[10px] font-black uppercase {{ $statusStyles[$status] ?? 'border-slate-200 bg-slate-100 text-slate-700' }}" data-attendance-status-badge>{{ $status === 'present' ? 'checked in' : str_replace('_', ' ', $status) }}</span>
                                    </div>
                                    <p class="text-xs font-semibold text-slate-500">{{ $employee->employee_code }} · {{ $employee->category?->name }}</p>
                                    <div class="{{ $attendance ? '' : 'hidden' }} mt-2 flex flex-wrap gap-2 text-xs font-black text-slate-500" data-attendance-markers>
                                        @if($attendance)
                                            <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-emerald-800">
                                                Checked in {{ ($attendance->created_at ?? $attendance->marked_at)?->format('h:i A') }}
                                            </span>
                                            @if($attendance->marked_at)
                                                <span class="rounded-full border border-slate-200 bg-white px-3 py-1">
                                                    Latest mark {{ $attendance->marked_at->format('h:i A') }}
                                                </span>
                                            @endif
                                            @if($wasChanged)
                                                <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-amber-800">
                                                    Changed {{ $attendance->updated_at->format('h:i A') }}
                                                </span>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                                <div class="grid gap-2 sm:grid-cols-[10rem_1fr_1fr_auto]">
                                    <select name="status" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold">
                                        <option value="present" @selected($selectedStatus === 'present')>Full Day</option>
                                        <option value="half_day" @selected($selectedStatus === 'half_day')>Half Day</option>
                                        <option value="leave" @selected($selectedStatus === 'leave')>Leave</option>
                                        <option value="absent" @selected($selectedStatus === 'absent')>Absent</option>
                                    </select>
                                    <input type="text" name="leave_reason" value="{{ $status === 'leave' ? ($attendance?->notes ?? '') : '' }}" placeholder="Leave reason" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm">
                                    <input type="text" name="notes" value="{{ $status !== 'leave' ? ($attendance?->notes ?? '') : '' }}" placeholder="Notes" class="h-11 rounded-xl border border-slate-200 bg-white px-3 text-sm">
                                    <button type="submit" class="h-11 rounded-xl bg-emerald-600 px-4 text-sm font-black text-white" data-attendance-submit>{{ $attendance ? 'Update Check-In' : 'Check In' }}</button>
                                </div>
                            </div>
                        </form>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm font-semibold text-slate-500">
                            No HR-assigned shop staff for this shop and date.
                        </div>
                    @endforelse
                </div>
            </section>
        @endif

        @if($selectedTab === 'advance')
            <section class="grid gap-4 lg:grid-cols-[0.9fr_1.1fr]">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="text-lg font-black text-slate-950">Request Advance</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Current and previous staff for this client shop are available here.</p>
                    <form method="POST" action="{{ route('shop-owner.staff.advance-requests.store') }}" class="mt-4 grid gap-3">
                        @csrf
                        <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
                        <input type="date" name="requested_on" value="{{ $selectedDate->format('Y-m-d') }}" class="h-11 rounded-xl border border-slate-200 px-3 text-sm font-bold" required>
                        <select name="employee_id" class="h-11 rounded-xl border border-slate-200 px-3 text-sm font-bold" data-advance-employee required>
                            <option value="">Select employee</option>
                            @foreach($advanceEmployees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->employee_code }}</option>
                            @endforeach
                        </select>
                        <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-3 text-sm font-semibold text-cyan-900" data-advance-summary>
                            Select an employee to see available advance.
                        </div>
                        <input type="number" step="0.01" min="0.01" name="amount" placeholder="Advance amount" class="h-11 rounded-xl border border-slate-200 px-3 text-sm font-bold" data-advance-amount required>
                        <p class="hidden rounded-xl border px-3 py-2 text-xs font-black" data-advance-decision></p>
                        <input type="hidden" name="fund_source" value="petty_cash">
                        <textarea name="request_note" rows="2" placeholder="Reason / note" class="rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
                        <button type="submit" class="h-11 rounded-xl bg-cyan-500 px-4 text-sm font-black text-slate-950">Submit Advance</button>
                    </form>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="text-lg font-black text-slate-950">Advance Requests</h2>
                    <div class="mt-4 space-y-2">
                        @forelse($advanceRequests as $advanceRequest)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-black text-slate-900">{{ $advanceRequest->employee?->name }}</p>
                                        <p class="text-xs font-semibold text-slate-500">Eligible Rs. {{ number_format((float) $advanceRequest->eligible_amount, 2) }} · requested {{ $advanceRequest->requested_on->format('d M') }}</p>
                                    </div>
                                    <span class="rounded-full border px-2 py-1 text-[10px] font-black uppercase {{ $advanceRequest->status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($advanceRequest->status === 'rejected' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">{{ $advanceRequest->status }}</span>
                                </div>
                                <p class="mt-1 text-sm font-black text-slate-950">Rs. {{ number_format((float) $advanceRequest->requested_amount, 2) }}</p>
                            </div>
                        @empty
                            <p class="text-sm font-semibold text-slate-500">No advance requests yet.</p>
                        @endforelse
                    </div>
                    @if($advanceRequests->hasPages())
                        <div class="mt-4">{{ $advanceRequests->links() }}</div>
                    @endif
                </article>
            </section>
        @endif

        @if($selectedTab === 'salary')
            <section class="grid gap-4 lg:grid-cols-[0.9fr_1.1fr]">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="text-lg font-black text-slate-950">Pay Salary</h2>
                    <form method="POST" action="{{ route('shop-owner.staff.salary-payments.store') }}" class="mt-4 grid gap-3">
                        @csrf
                        <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
                        <input type="date" name="paid_on" value="{{ $selectedDate->format('Y-m-d') }}" class="h-11 rounded-xl border border-slate-200 px-3 text-sm font-bold" required>
                        <select name="employee_id" class="h-11 rounded-xl border border-slate-200 px-3 text-sm font-bold" data-salary-employee required>
                            <option value="">Select employee</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->name }} · {{ $employee->employee_code }}</option>
                            @endforeach
                        </select>
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-900" data-salary-summary>
                            Select an employee to see salary balance.
                        </div>
                        <input type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" class="h-11 rounded-xl border border-slate-200 px-3 text-sm font-bold" required>
                        <input type="hidden" name="fund_source" value="petty_cash">
                        <input type="text" name="notes" placeholder="Note" class="h-11 rounded-xl border border-slate-200 px-3 text-sm">
                        <button type="submit" class="h-11 rounded-xl bg-emerald-600 px-4 text-sm font-black text-white">Pay Salary</button>
                    </form>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="text-lg font-black text-slate-950">Recent Staff Money</h2>
                    <div class="mt-4 space-y-2">
                        @forelse($recentPayrollPayments as $payment)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-black text-slate-900">{{ $payment->employee?->name }}</p>
                                        <p class="text-xs font-semibold text-slate-500">
                                            {{ str($payment->payment_type)->headline() }}
                                            · {{ $payment->cashbookLine ? 'Cashbook expense posted' : 'Cashbook posting pending' }}
                                        </p>
                                    </div>
                                    <p class="text-sm font-black text-slate-950">Rs. {{ number_format((float) $payment->amount, 2) }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm font-semibold text-slate-500">No salary or advance payments yet.</p>
                        @endforelse
                    </div>
                    @if($recentPayrollPayments->hasPages())
                        <div class="mt-4">{{ $recentPayrollPayments->links() }}</div>
                    @endif
                </article>
            </section>
        @endif

        @if($selectedTab === 'leave')
            <section class="grid gap-4 lg:grid-cols-[0.9fr_1.1fr]">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="text-lg font-black text-slate-950">Request Leave</h2>
                    <form method="POST" action="{{ route('shop-owner.staff.leave-requests.store') }}" class="mt-4 grid gap-3">
                        @csrf
                        <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
                        <select name="employee_id" class="h-11 rounded-xl border border-slate-200 px-3 text-sm" required>
                            <option value="">Select employee</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((int) old('employee_id') === $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                        <input type="date" name="start_date" class="h-11 rounded-xl border border-slate-200 px-3 text-sm" required>
                        <input type="date" name="end_date" class="h-11 rounded-xl border border-slate-200 px-3 text-sm" required>
                        <textarea name="reason" rows="3" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Reason" required>{{ old('reason') }}</textarea>
                        <button type="submit" class="h-11 rounded-xl bg-slate-950 px-4 text-sm font-black text-white">Submit Leave Request</button>
                    </form>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="text-lg font-black text-slate-950">Recent Leave Updates</h2>
                    <div class="mt-4 space-y-3">
                        @forelse($leaveRequests as $leaveRequest)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-black text-slate-900">{{ $leaveRequest->employee->name }}</p>
                                        <p class="text-xs font-semibold text-slate-500">{{ $leaveRequest->start_date->format('d M') }} to {{ $leaveRequest->end_date->format('d M') }}</p>
                                    </div>
                                    <span class="rounded-full border px-2 py-1 text-[10px] font-black uppercase {{ $leaveRequest->status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : ($leaveRequest->status === 'rejected' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-amber-200 bg-amber-50 text-amber-800') }}">{{ $leaveRequest->status }}</span>
                                </div>
                                <p class="mt-2 text-sm font-semibold text-slate-600">{{ $leaveRequest->reason }}</p>
                            </div>
                        @empty
                            <p class="text-sm font-semibold text-slate-500">No leave requests yet for this client shop.</p>
                        @endforelse
                    </div>
                    @if($leaveRequests->hasPages())
                        <div class="mt-4">{{ $leaveRequests->links() }}</div>
                    @endif
                </article>
            </section>
        @endif

        @if($selectedTab === 'history')
            <section class="grid gap-4 lg:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="text-lg font-black text-slate-950">Salary & Advance Payments</h2>
                    <div class="mt-4 space-y-2">
                        @forelse($recentPayrollPayments as $payment)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-black text-slate-900">{{ $payment->employee?->name }}</p>
                                        <p class="text-xs font-semibold text-slate-500">{{ $payment->paid_on->format('d M Y') }} · {{ str($payment->payment_type)->headline() }}</p>
                                    </div>
                                    <p class="text-sm font-black text-slate-950">Rs. {{ number_format((float) $payment->amount, 2) }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm font-semibold text-slate-500">No staff payments yet.</p>
                        @endforelse
                    </div>
                    @if($recentPayrollPayments->hasPages())
                        <div class="mt-4">{{ $recentPayrollPayments->links() }}</div>
                    @endif
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <h2 class="text-lg font-black text-slate-950">Requests</h2>
                    <div class="mt-4 space-y-2">
                        @foreach($advanceRequests as $advanceRequest)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-sm font-black text-slate-900">{{ $advanceRequest->employee?->name }} · Advance</p>
                                <p class="text-xs font-semibold text-slate-500">Rs. {{ number_format((float) $advanceRequest->requested_amount, 2) }} · {{ $advanceRequest->status }}</p>
                            </div>
                        @endforeach
                        @foreach($leaveRequests as $leaveRequest)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-sm font-black text-slate-900">{{ $leaveRequest->employee?->name }} · Leave</p>
                                <p class="text-xs font-semibold text-slate-500">{{ $leaveRequest->start_date->format('d M') }} to {{ $leaveRequest->end_date->format('d M') }} · {{ $leaveRequest->status }}</p>
                            </div>
                        @endforeach
                        @if($advanceRequests->isEmpty() && $leaveRequests->isEmpty())
                            <p class="text-sm font-semibold text-slate-500">No requests yet.</p>
                        @endif
                    </div>
                    @if($advanceRequests->hasPages() || $leaveRequests->hasPages())
                        <div class="mt-4 space-y-3">
                            @if($advanceRequests->hasPages())
                                {{ $advanceRequests->links() }}
                            @endif
                            @if($leaveRequests->hasPages())
                                {{ $leaveRequests->links() }}
                            @endif
                        </div>
                    @endif
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
                    advanceSummary.innerHTML = `Available Rs. ${money.format(option.available_amount)}<br>Already taken Rs. ${money.format(option.already_advanced_amount)} · Earned Rs. ${money.format(option.earned_amount)} · ${option.present_days} present days<br>${option.rule_label}`;
                    const amount = Number(advanceAmount?.value || 0);
                    if (amount <= 0) {
                        advanceDecision.classList.add('hidden');
                        return;
                    }
                    const needsApproval = amount > Number(option.available_amount || 0);
                    advanceDecision.textContent = needsApproval ? 'Needs HR/admin approval because amount is above available advance.' : 'Available now. This can be auto-approved.';
                    advanceDecision.className = `rounded-xl border px-3 py-2 text-xs font-black ${needsApproval ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`;
                };

                const renderSalary = () => {
                    if (!salaryEmployee || !salarySummary) return;
                    const option = salaryOptions[salaryEmployee.value];
                    if (!option) {
                        salarySummary.textContent = 'Select an employee to see salary balance.';
                        return;
                    }
                    const remaining = option.remaining_amount === null ? 'Payroll not generated yet' : `Remaining Rs. ${money.format(option.remaining_amount)}`;
                    salarySummary.innerHTML = `Salary Rs. ${money.format(option.salary_amount)}<br>Paid Rs. ${money.format(option.paid_amount)} · ${remaining}`;
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
