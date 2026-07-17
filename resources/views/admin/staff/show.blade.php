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
                <span class="mt-3 inline-flex rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] {{ $employee->employment_status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                    {{ $employee->employment_status }}
                </span>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <form method="GET" class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                    <input type="month" name="month" value="{{ $selectedMonth->format('Y-m') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white">Load Month</button>
                </form>
                @if(auth()->user()?->hasRole('admin'))
                    <form method="POST" action="{{ route('admin.staff.employment-status.update', $employee) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="employment_status" value="{{ $employee->employment_status === 'active' ? 'inactive' : 'active' }}">
                        <button type="submit" class="rounded-xl bg-slate-100 px-4 py-3 text-sm font-black text-slate-700">
                            {{ $employee->employment_status === 'active' ? 'Deactivate Staff' : 'Reactivate Staff' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            @foreach([
                'Salary' => $employee->salary_type === 'daily_wage'
                    ? 'Daily Rs. '.number_format((float) $employee->daily_wage, 2)
                    : 'Rs. '.number_format((float) $employee->monthly_salary, 2),
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

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            @php
                $monthlyPayrollPaid = $monthlyPayrollItem?->paidAmount() ?? 0;
                $monthlyOfficePaid = $monthlyPayrollItem?->officePaidAmount() ?? 0;
                $monthlyShopPaid = $monthlyPayrollItem?->shopPaidAmount() ?? 0;
                $monthlyPayrollRemaining = $monthlyPayrollItem?->remainingAmount() ?? 0;
            @endphp
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-950">Salary payments</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">{{ $selectedMonth->format('F Y') }} salary payment status and recent payment journal details.</p>
                </div>
                <a href="{{ route('admin.staff.payments.index', ['payroll_month' => $selectedMonth->format('Y-m')]) }}" class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-black text-white">Open Payments</a>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-5">
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Payroll amount</p>
                    <p class="mt-2 text-xl font-black text-slate-950">Rs. {{ number_format((float) ($monthlyPayrollItem?->final_amount ?? 0), 2) }}</p>
                </article>
                <article class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Paid Total</p>
                    <p class="mt-2 text-xl font-black text-emerald-900">Rs. {{ number_format($monthlyPayrollPaid, 2) }}</p>
                </article>
                <article class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-cyan-700">Office Paid</p>
                    <p class="mt-2 text-xl font-black text-cyan-900">Rs. {{ number_format($monthlyOfficePaid, 2) }}</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Shop Paid</p>
                    <p class="mt-2 text-xl font-black text-slate-950">Rs. {{ number_format($monthlyShopPaid, 2) }}</p>
                </article>
                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-amber-700">Remaining</p>
                    <p class="mt-2 text-xl font-black text-amber-900">Rs. {{ number_format($monthlyPayrollRemaining, 2) }}</p>
                </article>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-3 py-3">Office Date</th>
                                <th class="px-3 py-3">Method</th>
                                <th class="px-3 py-3 text-right">Amount</th>
                                <th class="px-3 py-3">Journal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($recentPayrollPayments as $payment)
                                <tr>
                                    <td class="px-3 py-3 font-bold text-slate-900">{{ $payment->paid_on->format('d M Y') }}</td>
                                    <td class="px-3 py-3 capitalize">Office {{ $payment->payment_method }}</td>
                                    <td class="px-3 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $payment->amount, 2) }}</td>
                                    <td class="px-3 py-3 text-sm font-semibold text-cyan-700">{{ $payment->journalEntry?->reference ?? 'Pending journal' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-6 text-center text-sm font-semibold text-slate-500">No office salary payments recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-3 py-3">Shop Date</th>
                                <th class="px-3 py-3">Shop</th>
                                <th class="px-3 py-3">Type</th>
                                <th class="px-3 py-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($recentShopStaffPayments as $payment)
                                <tr>
                                    <td class="px-3 py-3 font-bold text-slate-900">{{ $payment->paid_on->format('d M Y') }}</td>
                                    <td class="px-3 py-3 font-semibold text-slate-600">{{ $payment->shop?->name }}</td>
                                    <td class="px-3 py-3 capitalize">{{ $payment->payment_type }} / {{ str($payment->fund_source)->replace('_', ' ')->headline() }}</td>
                                    <td class="px-3 py-3 text-right font-black text-slate-950">Rs. {{ number_format((float) $payment->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-6 text-center text-sm font-semibold text-slate-500">No shop salary or advance payments recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h3 class="text-sm font-black text-slate-950">Advance Details</h3>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    @forelse($employeeAdvanceRequests as $advanceRequest)
                        <div class="rounded-xl border border-slate-200 bg-white p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black text-slate-900">{{ $advanceRequest->shop?->name }}</p>
                                    <p class="text-xs font-semibold text-slate-500">{{ $advanceRequest->requested_on->format('d M Y') }} · {{ str($advanceRequest->fund_source)->replace('_', ' ')->headline() }}</p>
                                </div>
                                <span class="rounded-full border px-2 py-1 text-[10px] font-black uppercase {{ $advanceRequest->status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($advanceRequest->status === 'rejected' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-amber-200 bg-amber-50 text-amber-700') }}">{{ $advanceRequest->status }}</span>
                            </div>
                            <p class="mt-2 text-sm font-bold text-slate-700">Requested Rs. {{ number_format((float) $advanceRequest->requested_amount, 2) }} · Eligible Rs. {{ number_format((float) $advanceRequest->eligible_amount, 2) }}</p>
                            @if($advanceRequest->review_note)
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $advanceRequest->review_note }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm font-semibold text-slate-500">No advance requests recorded for this employee.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-xl font-black text-slate-950">Leave available</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Current available days for this staff member. Carry-over is shown separately with a <span class="font-black text-cyan-700">+</span> so it is easy to understand.</p>
                </div>
                <span class="text-xs font-bold text-slate-400">As of {{ today()->format('d M Y') }}</span>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @forelse($leaveBalances as $leaveBalance)
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-black text-slate-950">{{ $leaveBalance['leave_type']->name }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $leaveBalance['leave_type']->is_paid ? 'Paid leave' : 'Unpaid leave' }}</p>
                            </div>
                            <span class="rounded-full bg-white px-2 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Days</span>
                        </div>
                        <p class="mt-4 text-3xl font-black text-slate-950">{{ number_format($leaveBalance['available'], 2) }}</p>
                        <p class="mt-1 text-xs font-bold text-slate-500">Available now</p>
                        @if($leaveBalance['carry_forward_allowed'])
                            <div class="mt-4 rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2">
                                <p class="text-sm font-black text-cyan-800">+ up to {{ number_format($leaveBalance['carry_forward_limit'], 2) }} days</p>
                                <p class="mt-1 text-xs font-semibold text-cyan-700">Carried over from the previous period</p>
                                @if($leaveBalance['carry_forward_expiry_months'] !== null)
                                    <p class="mt-1 text-[11px] font-bold text-cyan-700">Expires after {{ $leaveBalance['carry_forward_expiry_months'] }} month(s)</p>
                                @endif
                            </div>
                        @else
                            <p class="mt-4 text-xs font-semibold text-slate-400">No carry-over configured</p>
                        @endif
                    </article>
                @empty
                    <p class="text-sm font-semibold text-slate-500">No leave types are configured for this staff member.</p>
                @endforelse
            </div>
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
                                    <p>{{ $attendance->source ? str($attendance->source)->headline() : 'Source pending' }} · {{ $attendance->markedBy?->name ?? 'System' }}</p>
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
                        <select name="salary_type" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                            <option value="monthly" @selected($employee->salary_type === 'monthly')>Monthly salary</option>
                            <option value="daily_wage" @selected($employee->salary_type === 'daily_wage')>Daily wage</option>
                        </select>
                        <input type="number" step="0.01" name="daily_wage" value="{{ (float) $employee->daily_wage }}" placeholder="Daily wage" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
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
                                <th class="pb-3">SL No</th>
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
                                    <td class="py-3 font-black text-slate-500">{{ $loop->iteration }}</td>
                                    <td class="py-3 font-bold text-slate-900">{{ $attendance->attendance_date->format('d M Y') }}</td>
                                    <td class="py-3 capitalize">{{ str_replace('_', ' ', $attendance->status) }}</td>
                                    <td class="py-3">{{ $attendance->shop?->name ?? 'Admin desk' }}</td>
                                    <td class="py-3">{{ $attendance->marked_at?->format('d M, h:i A') ?? 'Pending time' }}</td>
                                    <td class="py-3">{{ $attendance->markedBy?->name ?? 'System' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-4 text-sm font-semibold text-slate-500">No attendance entries for this month.</td>
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
                            <th class="pb-3">SL No</th>
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
                                <td class="py-3 font-black text-slate-500">{{ $loop->iteration }}</td>
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
                                <td colspan="6" class="py-4 text-sm font-semibold text-slate-500">No leave requests recorded for this staff member.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.staff>
