<x-layouts.staff title="Staff Profile">
    @php
        $statusStyles = [
            'present' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'half_day' => 'border-amber-200 bg-amber-50 text-amber-800',
            'leave' => 'border-cyan-200 bg-cyan-50 text-cyan-800',
            'absent' => 'border-rose-200 bg-rose-50 text-rose-800',
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('admin.staff.index') }}" class="text-sm font-black text-cyan-700">← Back to Staff Dashboard</a>
                <h1 class="mt-2 text-3xl font-black text-slate-950">{{ $employee->name }}</h1>
                <p class="mt-1 text-sm font-semibold text-slate-500">{{ $employee->employee_code }} · {{ $employee->category->name }} · {{ ucfirst($employee->staff_area) }} staff</p>
            </div>
            <form method="GET" class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                <input type="month" name="month" value="{{ $selectedMonth->format('Y-m') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white">Load Month</button>
            </form>
        </div>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            @foreach([
                'Salary' => 'Rs. '.number_format((float) $employee->monthly_salary, 2),
                'Present' => $monthlySummary['present'],
                'Half Day' => $monthlySummary['half_day'],
                'Leave' => $monthlySummary['leave'],
                'Absent' => $monthlySummary['absent'],
                'Paid Leave Limit' => $employee->category->monthly_paid_leave_limit,
            ] as $label => $value)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">{{ $value }}</p>
                </article>
            @endforeach
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <h2 class="text-xl font-black text-slate-950">Attendance Calendar</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">{{ $selectedMonth->format('F Y') }} attendance details and status history.</p>

                <div class="mt-5 grid grid-cols-7 gap-2 text-center text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
                    @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
                        <div>{{ $dayName }}</div>
                    @endforeach
                </div>

                <div class="mt-3 grid grid-cols-7 gap-2">
                    @foreach($calendarDays as $day)
                        @php
                            $attendance = $day['attendance'];
                            $status = $attendance?->status;
                        @endphp
                        <article class="min-h-[104px] rounded-2xl border p-3 {{ $day['is_current_month'] ? 'border-slate-200 bg-slate-50' : 'border-slate-100 bg-white text-slate-300' }}">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-black">{{ $day['date']->day }}</p>
                                @if($status)
                                    <span class="rounded-full border px-2 py-1 text-[10px] font-black uppercase tracking-[0.16em] {{ $statusStyles[$status] ?? 'border-slate-200 bg-slate-100 text-slate-700' }}">
                                        {{ str_replace('_', ' ', $status) }}
                                    </span>
                                @endif
                            </div>
                            @if($attendance)
                                <div class="mt-3 space-y-1 text-xs font-semibold text-slate-600">
                                    <p>{{ $attendance->shop?->name ?? 'Admin desk' }}</p>
                                    <p>{{ $attendance->markedBy?->name ?? 'System' }}</p>
                                    <p>{{ $attendance->marked_at?->format('h:i A') ?? 'Time pending' }}</p>
                                </div>
                            @else
                                <p class="mt-4 text-xs font-semibold text-slate-400">No entry</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-black text-slate-950">Linked User Access</h2>
                    @if($employee->user)
                        <div class="mt-4 space-y-4">
                            <div>
                                <p class="text-sm font-black text-slate-950">{{ $employee->user->name }}</p>
                                <p class="text-sm font-semibold text-slate-500">{{ $employee->user->email }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @forelse($employee->user->roles as $role)
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-slate-700">{{ $role->name }}</span>
                                @empty
                                    <span class="text-sm font-semibold text-slate-400">No explicit roles</span>
                                @endforelse
                            </div>
                            @if($employee->user->ownedShopAssignments->isNotEmpty())
                                <div>
                                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Owned Shops</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-600">{{ $employee->user->ownedShopAssignments->pluck('shop.name')->implode(', ') }}</p>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="mt-4 text-sm font-semibold text-slate-500">This staff record is not linked to a login user.</p>
                    @endif
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-black text-slate-950">Shop Coverage</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Quick List Shops</p>
                            <p class="mt-2 text-sm font-semibold text-slate-600">
                                {{ $employee->assignedShops->isNotEmpty() ? $employee->assignedShops->pluck('name')->implode(', ') : 'Not added to any owned shop quick list yet.' }}
                            </p>
                        </article>
                        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Worked Shops</p>
                            <p class="mt-2 text-sm font-semibold text-slate-600">
                                {{ $workedShops->isNotEmpty() ? $workedShops->pluck('name')->implode(', ') : 'No shop attendance history yet.' }}
                            </p>
                        </article>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-black text-slate-950">Update Staff Profile</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Salary changes here flow into future payroll runs.</p>

                    <form method="POST" action="{{ route('admin.staff.update', $employee) }}" class="mt-5 grid gap-3 md:grid-cols-2">
                        @csrf
                        @method('PUT')
                        <input type="text" name="name" value="{{ $employee->name }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <input type="text" name="employee_code" value="{{ $employee->employee_code }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <input type="text" name="phone" value="{{ $employee->phone }}" placeholder="Phone" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <input type="email" name="email" value="{{ $employee->email }}" placeholder="Email" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <select name="employee_category_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected($employee->employee_category_id === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <select name="staff_area" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                            <option value="office" @selected($employee->staff_area === 'office')>Office</option>
                            <option value="shop" @selected($employee->staff_area === 'shop')>Shop</option>
                        </select>
                        <input type="number" step="0.01" name="monthly_salary" value="{{ (float) $employee->monthly_salary }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <input type="date" name="joined_on" value="{{ $employee->joined_on?->format('Y-m-d') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <input type="hidden" name="employment_status" value="{{ $employee->employment_status }}">
                        <input type="hidden" name="user_id" value="{{ $employee->user_id }}">
                        <input type="hidden" name="default_shop_id" value="{{ $employee->default_shop_id }}">
                        <textarea name="notes" rows="3" placeholder="Notes" class="rounded-xl border border-slate-200 px-3 py-2 text-sm md:col-span-2">{{ $employee->notes }}</textarea>
                        <button type="submit" class="rounded-xl bg-cyan-500 px-4 py-2 text-sm font-black text-slate-950 md:col-span-2">Save Salary and Staff Details</button>
                    </form>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-black text-slate-950">Update Attendance</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Admin can backfill or correct attendance for any date.</p>

                    <form method="POST" action="{{ route('admin.staff.attendance.store') }}" class="mt-5 grid gap-3 md:grid-cols-2">
                        @csrf
                        <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                        <input type="hidden" name="redirect_to" value="profile">
                        <input type="date" name="attendance_date" value="{{ today()->format('Y-m-d') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <select name="status" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                            <option value="present">Present</option>
                            <option value="half_day">Half Day</option>
                            <option value="leave">Leave</option>
                            <option value="absent">Absent</option>
                        </select>
                        <select name="shop_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Marked by admin / office</option>
                            @foreach($shops as $shop)
                                <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="notes" placeholder="Notes" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white md:col-span-2">Save Attendance</button>
                    </form>
                </section>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-black text-slate-950">Recent Attendance Entries</h2>
                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-slate-500">
                            <tr>
                                <th class="pb-3">Date</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3">Shop</th>
                                <th class="pb-3">Check-In</th>
                                <th class="pb-3">Marked By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($attendanceRecords as $attendance)
                                <tr>
                                    <td class="py-3 font-bold text-slate-900">{{ $attendance->attendance_date->format('d M Y') }}</td>
                                    <td class="py-3 capitalize">{{ str_replace('_', ' ', $attendance->status) }}</td>
                                    <td class="py-3">{{ $attendance->shop?->name ?? 'Admin desk' }}</td>
                                    <td class="py-3">{{ $attendance->marked_at?->format('d M, h:i A') ?? 'Pending time' }}</td>
                                    <td class="py-3">{{ $attendance->markedBy?->name ?? 'System' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-sm font-semibold text-slate-500">No attendance entries for this month.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-black text-slate-950">Payroll and Leave Rule</h2>
                <div class="mt-4 space-y-4">
                    <article class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4">
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-cyan-700">Current Rule</p>
                        <p class="mt-2 text-lg font-black text-slate-950">{{ $employee->category->name }}</p>
                        <p class="mt-2 text-sm font-semibold text-slate-600">{{ $employee->category->monthly_paid_leave_limit }} paid leave day(s) per month. Extra leave uses salary weight {{ number_format((float) $employee->category->excess_leave_weight, 2) }}.</p>
                    </article>

                    <div class="space-y-3">
                        @forelse($payrollHistory as $history)
                            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-black text-slate-950">{{ $history->payrollRun?->period_start?->format('M Y') }}</p>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">Paid leave {{ $history->paid_leave_days }} · Excess leave {{ $history->unpaid_leave_days }} · Absent {{ $history->absent_days }}</p>
                                    </div>
                                    <p class="text-sm font-black text-slate-950">Rs. {{ number_format((float) $history->final_amount, 2) }}</p>
                                </div>
                            </article>
                        @empty
                            <p class="text-sm font-semibold text-slate-500">No payroll history yet for this employee.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black text-slate-950">Leave Request History</h2>
                    <p class="text-sm font-semibold text-slate-500">Review who submitted leave and whether shop-owner initiated requests are affecting this staff record.</p>
                </div>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="pb-3">Dates</th>
                            <th class="pb-3">Status</th>
                            <th class="pb-3">Submitted By</th>
                            <th class="pb-3">Shop</th>
                            <th class="pb-3">Type</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($leaveRequests as $leaveRequest)
                            <tr>
                                <td class="py-3 font-bold text-slate-900">{{ $leaveRequest->start_date->format('d M Y') }} to {{ $leaveRequest->end_date->format('d M Y') }}</td>
                                <td class="py-3 capitalize">{{ $leaveRequest->status }}</td>
                                <td class="py-3">
                                    {{ $leaveRequest->submittedBy?->name ?? 'Unknown' }}
                                    @if($leaveRequest->submittedBy?->roles?->isNotEmpty())
                                        <p class="text-xs font-semibold text-slate-500">{{ $leaveRequest->submittedBy->roles->pluck('name')->implode(', ') }}</p>
                                    @endif
                                </td>
                                <td class="py-3">{{ $leaveRequest->submittedForShop?->name ?? 'N/A' }}</td>
                                <td class="py-3 uppercase">{{ $leaveRequest->submission_type }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-sm font-semibold text-slate-500">No leave requests recorded for this staff member.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.staff>
