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
            <div class="flex items-start gap-4">
                @if($employee->photo_url)
                    <img src="{{ $employee->photo_url }}" alt="{{ $employee->name }}" class="h-20 w-20 rounded-2xl object-cover border-2 border-white shadow-md">
                @else
                    <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-slate-900 text-xl font-black text-white shadow-md">
                        {{ substr($employee->name, 0, 2) }}
                    </div>
                @endif
                <div>
                    <a href="{{ route('admin.staff.index') }}" class="text-sm font-black text-cyan-700">← Back to Staff Dashboard</a>
                    <h1 class="mt-1 text-3xl font-black text-slate-950">{{ $employee->name }}</h1>
                    <p class="mt-1 text-sm font-semibold text-slate-500">{{ $employee->employee_code }} · {{ $employee->category?->name ?? 'Unassigned Category' }} · {{ ucfirst($employee->staff_area) }} staff</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="inline-flex rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] {{ $employee->employment_status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                            {{ $employee->employment_status }}
                        </span>
                        @if($employee->defaultShop)
                            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-700">
                                Shop: {{ $employee->defaultShop->name }}
                            </span>
                        @endif
                    </div>
                </div>
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

        @if($employee->verification_status === 'pending')
            <section class="rounded-3xl border-2 border-amber-300 bg-amber-50/80 p-6 shadow-md" x-data="{
                showRejectModal: false,
                salaryType: 'monthly'
            }">
                <div class="space-y-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-amber-200/70 pb-3">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="rounded-full bg-amber-500 px-3 py-1 text-xs font-black text-white uppercase tracking-wider">Pending HR Verification</span>
                                <span class="text-xs font-semibold text-amber-800">Submitted by {{ $employee->submittedBy?->name ?? 'Shop Owner' }} on {{ $employee->created_at?->format('d M Y, h:i A') }}</span>
                            </div>
                            <h2 class="mt-2 text-xl font-black text-amber-950">Review Shop Registration Request</h2>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="rounded-lg bg-amber-200/80 px-2.5 py-1 text-xs font-black text-amber-950">
                                    Submitted From Shop: {{ $employee->defaultShop?->name ?? 'Unknown Shop' }}
                                </span>
                            </div>
                        </div>
                        <button type="button" @click="showRejectModal = true" class="rounded-2xl border border-rose-300 bg-rose-100 px-4 py-2.5 text-xs font-black text-rose-800 shadow-sm hover:bg-rose-200 active:scale-95 transition self-start sm:self-auto">
                            ✕ Reject Registration
                        </button>
                    </div>

                    <!-- HR APPROVAL FORM WITH CATEGORY AND SALARY -->
                    <form method="POST" action="{{ route('admin.staff.approve', $employee) }}" class="rounded-2xl bg-white p-4 border border-amber-200 shadow-sm space-y-3">
                        @csrf
                        <p class="text-xs font-black uppercase tracking-wider text-slate-500">Assign HR Role & Salary Configuration *</p>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Category / Designation *</label>
                                <div class="relative">
                                    <select name="employee_category_id" class="h-10 w-full appearance-none rounded-xl border border-slate-200 bg-white pl-3 pr-8 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 cursor-pointer" required>
                                        <option value="">Select Category *</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-500">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Salary Type *</label>
                                <div class="relative">
                                    <select name="salary_type" x-model="salaryType" class="h-10 w-full appearance-none rounded-xl border border-slate-200 bg-white pl-3 pr-8 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600 cursor-pointer" required>
                                        <option value="monthly">Monthly Salary</option>
                                        <option value="daily_wage">Daily Wage</option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-500">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <div x-show="salaryType === 'monthly'">
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Monthly Salary (₹) *</label>
                                <input type="number" step="0.01" min="0" name="monthly_salary" placeholder="Amount (e.g. 18000)" class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" :required="salaryType === 'monthly'" :disabled="salaryType !== 'monthly'">
                            </div>

                            <div x-show="salaryType === 'daily_wage'" x-cloak>
                                <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Daily Wage (₹) *</label>
                                <input type="number" step="0.01" min="0" name="daily_wage" placeholder="Daily wage amount" class="h-10 w-full rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" :required="salaryType === 'daily_wage'" :disabled="salaryType !== 'daily_wage'">
                            </div>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button type="submit" class="rounded-2xl bg-emerald-600 px-6 py-3 text-sm font-black text-white shadow-md hover:bg-emerald-700 active:scale-95 transition">
                                ✓ Approve & Activate Employee
                            </button>
                        </div>
                    </form>
                </div>

                <!-- REJECTION MODAL -->
                <div x-show="showRejectModal" x-cloak style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
                    <div class="flex min-h-screen items-center justify-center p-4">
                        <div class="fixed inset-0 bg-slate-950/75 transition-opacity" @click="showRejectModal = false"></div>
                        <div class="relative w-full max-w-md transform overflow-hidden rounded-3xl bg-white p-6 text-left shadow-2xl transition-all">
                            <h3 class="text-lg font-black text-slate-950">Reject Staff Registration</h3>
                            <p class="mt-1 text-xs font-semibold text-slate-500">Provide a reason for rejecting this registration request. The shop owner will see this reason.</p>
                            <form method="POST" action="{{ route('admin.staff.reject', $employee) }}" class="mt-4 space-y-4">
                                @csrf
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Rejection Reason *</label>
                                    <textarea name="rejection_reason" rows="3" placeholder="e.g. ID Front image is blurry / Salary amount mismatch" class="w-full rounded-xl border border-slate-200 p-3 text-sm font-semibold focus:border-rose-600 focus:ring-rose-600" required></textarea>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="showRejectModal = false" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700">Cancel</button>
                                    <button type="submit" class="rounded-xl bg-rose-600 px-5 py-2 text-xs font-black text-white hover:bg-rose-700">Submit Rejection</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        @elseif($employee->verification_status === 'rejected')
            <section class="rounded-3xl border border-rose-200 bg-rose-50 p-6 shadow-sm">
                <div>
                    <span class="rounded-full bg-rose-600 px-3 py-1 text-xs font-black text-white uppercase tracking-wider">Registration Rejected</span>
                    <p class="mt-2 text-sm font-bold text-rose-900">Reason: {{ $employee->rejection_reason }}</p>
                    <p class="text-xs font-semibold text-rose-700 mt-1">Reviewed by {{ $employee->reviewedBy?->name ?? 'Admin' }} on {{ $employee->reviewed_at?->format('d M Y, h:i A') }}</p>
                </div>
            </section>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            @foreach([
                'Salary' => $employee->salary_type === 'daily_wage'
                    ? 'Daily Rs. '.number_format((float) $employee->daily_wage, 2)
                    : 'Rs. '.number_format((float) $employee->monthly_salary, 2),
                'Present' => $monthlySummary['present'],
                'Half Day' => $monthlySummary['half_day'],
                'Leave' => $monthlySummary['leave'],
                'Absent' => $monthlySummary['absent'],
                'Paid Leave Limit' => $employee->category?->monthly_paid_leave_limit ?? 0,
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
                                    <td class="px-3 py-3 capitalize">
                                        {{ $payment->payment_type }}
                                        / {{ $payment->cashbookLine ? 'cashbook posted' : 'cashbook pending' }}
                                    </td>
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
                                    <p class="text-xs font-semibold text-slate-500">
                                        {{ $advanceRequest->requested_on->format('d M Y') }}
                                        · {{ $advanceRequest->shopStaffPayment?->cashbookLine ? 'cashbook posted' : 'cashbook pending' }}
                                    </p>
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
                    <h2 class="text-xl font-black text-slate-950">HR Details & ID Proof</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2 text-sm">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4" x-data="{ showFullId: false }">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Government ID Proof</p>
                            <p class="mt-1 text-xs font-bold text-slate-700">{{ $employee->formatted_id_type }}</p>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="font-mono text-base font-bold text-slate-900" x-text="showFullId ? '{{ $employee->id_number ?: 'Not provided' }}' : '{{ $employee->masked_id_number ?: 'Not provided' }}'"></span>
                                @if($employee->id_number)
                                    <button type="button" @click="showFullId = !showFullId" class="text-xs font-bold text-cyan-700 underline">
                                        <span x-text="showFullId ? 'Hide' : 'Show Full'"></span>
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Phone Numbers</p>
                            <p class="mt-2 font-bold text-slate-900">Primary: {{ $employee->phone ?: 'N/A' }}</p>
                            <p class="mt-1 font-semibold text-slate-600">Emergency: {{ $employee->alternate_phone ?: 'N/A' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 md:col-span-2">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Address</p>
                            <p class="mt-2 font-semibold text-slate-700 whitespace-pre-line">{{ $employee->address ?: 'No address recorded.' }}</p>
                        </div>
                        @if($employee->id_front_url || $employee->id_back_url)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 md:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400 mb-3">Uploaded Document Proofs</p>
                                <div class="flex flex-wrap gap-4">
                                    @if($employee->id_front_url)
                                        <div>
                                            <p class="text-xs font-bold text-slate-600 mb-1">ID Front Image</p>
                                            <a href="{{ $employee->id_front_url }}" target="_blank" class="block">
                                                <img src="{{ $employee->id_front_url }}" alt="ID Front" class="h-32 w-auto max-w-xs rounded-xl object-contain border border-slate-200 bg-white p-1 hover:opacity-90 shadow-sm">
                                            </a>
                                        </div>
                                    @endif
                                    @if($employee->id_back_url)
                                        <div>
                                            <p class="text-xs font-bold text-slate-600 mb-1">ID Back Image</p>
                                            <a href="{{ $employee->id_back_url }}" target="_blank" class="block">
                                                <img src="{{ $employee->id_back_url }}" alt="ID Back" class="h-32 w-auto max-w-xs rounded-xl object-contain border border-slate-200 bg-white p-1 hover:opacity-90 shadow-sm">
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

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
                                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Client Shops</p>
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
                                {{ $employee->assignedShops->isNotEmpty() ? $employee->assignedShops->pluck('name')->implode(', ') : 'Not added to any client shop quick list yet.' }}
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

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm" x-data="{ idType: '{{ $employee->id_type ?? 'aadhaar' }}', salaryType: '{{ $employee->salary_type ?? 'monthly' }}' }">
                    <h2 class="text-xl font-black text-slate-950">Update Staff Profile</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">Salary and detail changes here update this employee's record.</p>

                    <form method="POST" action="{{ route('admin.staff.update', $employee) }}" enctype="multipart/form-data" class="mt-5 grid gap-3 md:grid-cols-2">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Full Name *</label>
                            <input type="text" name="name" value="{{ $employee->name }}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Employee Code *</label>
                            <input type="text" name="employee_code" value="{{ $employee->employee_code }}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Primary Phone *</label>
                            <input type="text" name="phone" value="{{ $employee->phone }}" placeholder="Phone" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Emergency Contact Number *</label>
                            <input type="text" name="alternate_phone" value="{{ $employee->alternate_phone }}" placeholder="Emergency contact number" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Email</label>
                            <input type="email" name="email" value="{{ $employee->email }}" placeholder="Email" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Government ID Type *</label>
                            <select name="id_type" x-model="idType" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                                <option value="aadhaar" @selected($employee->id_type === 'aadhaar')>Aadhaar</option>
                                <option value="passport" @selected($employee->id_type === 'passport')>Passport</option>
                                <option value="driving_licence" @selected($employee->id_type === 'driving_licence')>Driving Licence</option>
                                <option value="voter_id" @selected($employee->id_type === 'voter_id')>Voter ID</option>
                                <option value="pan" @selected($employee->id_type === 'pan')>PAN</option>
                                <option value="other" @selected($employee->id_type === 'other')>Other</option>
                            </select>
                        </div>
                        <div x-show="idType === 'other'" x-cloak :class="{ 'hidden': idType !== 'other' }">
                            <label class="block text-xs font-bold text-slate-600 mb-1">Other ID Type Name *</label>
                            <input type="text" name="other_id_type" value="{{ $employee->other_id_type }}" placeholder="e.g. State Ration Card" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" :required="idType === 'other'" :disabled="idType !== 'other'">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">ID Number *</label>
                            <input type="text" name="id_number" value="{{ $employee->id_number }}" placeholder="Document ID number" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        </div>
                        <!-- PROFILE PHOTO CROP & PREVIEW CARD -->
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                            <label class="block text-xs font-bold text-slate-700">Profile Photo (Square 1:1)</label>
                            <div class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-200 bg-white min-h-[140px]">
                                <img id="photo-preview-image" src="{{ $employee->photo_url ?? '' }}" class="h-28 w-28 rounded-2xl object-cover border border-slate-300 shadow-sm {{ !empty($employee->photo_url) ? '' : 'hidden' }}" alt="Profile Preview">
                                <div id="photo-preview-placeholder" class="text-center text-xs text-slate-400 font-semibold py-4 {{ !empty($employee->photo_url) ? 'hidden' : '' }}">No photo selected</div>
                            </div>
                            <div class="flex flex-wrap items-center justify-center gap-2">
                                <label class="cursor-pointer rounded-xl bg-slate-950 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-slate-800 transition">
                                    <span>Choose Photo</span>
                                    <input type="file" id="photo_file_input" accept="image/jpeg,image/png,image/webp" onchange="handleCropImageSelect(event, 'photo')" class="hidden">
                                </label>
                                <button type="button" id="photo-remove-btn" onclick="removeSelectedEmployeeImage('photo')" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 transition {{ !empty($employee->photo_url) ? '' : 'hidden' }}">
                                    Remove
                                </button>
                            </div>
                            <input type="hidden" name="photo_data_url" id="photo_data_url" value="">
                        </div>

                        <!-- GOVERNMENT ID FRONT CROP & PREVIEW CARD -->
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                            <label class="block text-xs font-bold text-slate-700">Government ID Front (Free Crop)</label>
                            <div class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-200 bg-white min-h-[140px]">
                                <img id="id_front-preview-image" src="{{ $employee->id_front_url ?? '' }}" class="h-28 w-auto max-w-full rounded-xl object-contain border border-slate-300 shadow-sm {{ !empty($employee->id_front_url) ? '' : 'hidden' }}" alt="ID Front Preview">
                                <div id="id_front-preview-placeholder" class="text-center text-xs text-slate-400 font-semibold py-4 {{ !empty($employee->id_front_url) ? 'hidden' : '' }}">No front image selected</div>
                            </div>
                            <div class="flex flex-wrap items-center justify-center gap-2">
                                <label class="cursor-pointer rounded-xl bg-slate-950 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-slate-800 transition">
                                    <span>Choose Image</span>
                                    <input type="file" id="id_front_file_input" accept="image/jpeg,image/png,image/webp" onchange="handleCropImageSelect(event, 'id_front')" class="hidden">
                                </label>
                                <button type="button" id="id_front-remove-btn" onclick="removeSelectedEmployeeImage('id_front')" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 transition {{ !empty($employee->id_front_url) ? '' : 'hidden' }}">
                                    Remove
                                </button>
                            </div>
                            <input type="hidden" name="id_front_data_url" id="id_front_data_url" value="">
                        </div>

                        <!-- GOVERNMENT ID BACK CROP & PREVIEW CARD -->
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                            <label class="block text-xs font-bold text-slate-700">Government ID Back (Free Crop)</label>
                            <div class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-200 bg-white min-h-[140px]">
                                <img id="id_back-preview-image" src="{{ $employee->id_back_url ?? '' }}" class="h-28 w-auto max-w-full rounded-xl object-contain border border-slate-300 shadow-sm {{ !empty($employee->id_back_url) ? '' : 'hidden' }}" alt="ID Back Preview">
                                <div id="id_back-preview-placeholder" class="text-center text-xs text-slate-400 font-semibold py-4 {{ !empty($employee->id_back_url) ? 'hidden' : '' }}">No back image selected</div>
                            </div>
                            <div class="flex flex-wrap items-center justify-center gap-2">
                                <label class="cursor-pointer rounded-xl bg-slate-950 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-slate-800 transition">
                                    <span>Choose Image</span>
                                    <input type="file" id="id_back_file_input" accept="image/jpeg,image/png,image/webp" onchange="handleCropImageSelect(event, 'id_back')" class="hidden">
                                </label>
                                <button type="button" id="id_back-remove-btn" onclick="removeSelectedEmployeeImage('id_back')" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 transition {{ !empty($employee->id_back_url) ? '' : 'hidden' }}">
                                    Remove
                                </button>
                            </div>
                            <input type="hidden" name="id_back_data_url" id="id_back_data_url" value="">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Category / Designation *</label>
                            <select name="employee_category_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected($employee->employee_category_id === $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Staff Area *</label>
                            <select name="staff_area" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                                <option value="office" @selected($employee->staff_area === 'office')>Office</option>
                                <option value="shop" @selected($employee->staff_area === 'shop')>Shop</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Default Shop / Workplace</label>
                            <select name="default_shop_id" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                <option value="">No default shop (Office/General)</option>
                                @foreach($shops as $shop)
                                    <option value="{{ $shop->id }}" @selected($employee->default_shop_id === $shop->id)>{{ $shop->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Salary Type *</label>
                            <select name="salary_type" x-model="salaryType" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                                <option value="monthly" @selected($employee->salary_type === 'monthly')>Monthly salary</option>
                                <option value="daily_wage" @selected($employee->salary_type === 'daily_wage')>Daily wage</option>
                            </select>
                        </div>
                        <div x-show="salaryType === 'monthly'" :class="{ 'hidden': salaryType !== 'monthly' }">
                            <label class="block text-xs font-bold text-slate-600 mb-1">Monthly Salary *</label>
                            <input type="number" step="0.01" name="monthly_salary" value="{{ (float) $employee->monthly_salary }}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" :required="salaryType === 'monthly'" :disabled="salaryType !== 'monthly'">
                        </div>
                        <div x-show="salaryType === 'daily_wage'" x-cloak :class="{ 'hidden': salaryType !== 'daily_wage' }">
                            <label class="block text-xs font-bold text-slate-600 mb-1">Daily Wage *</label>
                            <input type="number" step="0.01" name="daily_wage" value="{{ (float) $employee->daily_wage }}" placeholder="Daily wage" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" :required="salaryType === 'daily_wage'" :disabled="salaryType !== 'daily_wage'">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Joining Date</label>
                            <input type="date" name="joined_on" value="{{ $employee->joined_on?->format('Y-m-d') }}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 mb-1">Address</label>
                            <textarea name="address" rows="2" placeholder="Residential address" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">{{ $employee->address }}</textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 mb-1">Notes</label>
                            <textarea name="notes" rows="2" placeholder="Notes" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">{{ $employee->notes }}</textarea>
                        </div>
                        <input type="hidden" name="employment_status" value="{{ $employee->employment_status }}">
                        <input type="hidden" name="user_id" value="{{ $employee->user_id }}">
                        <button type="submit" class="rounded-xl bg-cyan-500 px-6 py-2.5 text-sm font-black text-slate-950 md:col-span-2 shadow-sm hover:bg-cyan-400">Save Salary and Staff Details</button>
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
                        <p class="mt-2 text-lg font-black text-slate-950">{{ $employee->category?->name ?? 'Unassigned Category' }}</p>
                        <p class="mt-2 text-sm font-semibold text-slate-600">{{ $employee->category?->monthly_paid_leave_limit ?? 0 }} paid leave day(s) per month. Extra leave uses salary weight {{ number_format((float) ($employee->category?->excess_leave_weight ?? 0), 2) }}.</p>
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
                    <p class="text-sm font-semibold text-slate-500">Review who submitted leave and whether shop-incharge initiated requests are affecting this staff record.</p>
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

    {{-- Crop Modal (Reused from Product Crop implementation: resources/views/inventory/products/create.blade.php) --}}
    <div id="crop-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
            <div class="fixed inset-0 bg-black/60 transition-opacity" aria-hidden="true" onclick="closeCropModal()"></div>

            <div class="relative bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:max-w-lg sm:w-full border border-gray-100 flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900" id="crop-modal-title">Crop Image</h3>
                    <button type="button" onclick="closeCropModal()" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                {{-- Cropper Container --}}
                <div class="flex-1 overflow-hidden p-6 bg-gray-950 flex items-center justify-center min-h-[380px] max-h-[500px]">
                    <div style="width: 100%; max-width: 450px; height: 350px; position: relative;">
                        <img id="cropper-image" style="display: block; max-width: 100%; max-height: 100%;">
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-end gap-3">
                    <button type="button" onclick="closeCropModal()" class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="button" onclick="applyCrop()" class="px-4 py-2 text-xs font-semibold text-slate-950 bg-cyan-500 rounded-lg hover:bg-cyan-400">Apply Crop</button>
                </div>
            </div>
        </div>
    </div>

@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
@endpush

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
    <script>
        let cropper = null;
        let currentCropTarget = null;
        let selectedFile = null;

        const cropTargetConfigs = {
            photo: {
                title: 'Crop Profile Photo',
                aspectRatio: 1,
                maxWidth: 800,
                dataInputId: 'photo_data_url',
                previewImgId: 'photo-preview-image',
                placeholderId: 'photo-preview-placeholder',
                removeBtnId: 'photo-remove-btn',
                fileInputId: 'photo_file_input'
            },
            id_front: {
                title: 'Crop ID Front Document',
                aspectRatio: NaN,
                maxWidth: 1400,
                dataInputId: 'id_front_data_url',
                previewImgId: 'id_front-preview-image',
                placeholderId: 'id_front-preview-placeholder',
                removeBtnId: 'id_front-remove-btn',
                fileInputId: 'id_front_file_input'
            },
            id_back: {
                title: 'Crop ID Back Document',
                aspectRatio: NaN,
                maxWidth: 1400,
                dataInputId: 'id_back_data_url',
                previewImgId: 'id_back-preview-image',
                placeholderId: 'id_back-preview-placeholder',
                removeBtnId: 'id_back-remove-btn',
                fileInputId: 'id_back_file_input'
            }
        };

        function handleCropImageSelect(event, targetKey) {
            const files = event.target.files;
            if (!files || files.length === 0) return;

            const file = files[0];
            if (!file.type.startsWith('image/')) {
                alert('Please select a valid image file (JPG, PNG, or WEBP).');
                event.target.value = '';
                return;
            }

            currentCropTarget = targetKey;
            selectedFile = file;
            const config = cropTargetConfigs[targetKey];

            const reader = new FileReader();
            reader.onload = function (e) {
                const cropperImage = document.getElementById('cropper-image');
                document.getElementById('crop-modal-title').textContent = config ? config.title : 'Crop Image';

                document.getElementById('crop-modal').classList.remove('hidden');

                cropperImage.onload = function () {
                    if (cropper) {
                        cropper.destroy();
                        cropper = null;
                    }

                    setTimeout(() => {
                        try {
                            cropper = new Cropper(cropperImage, {
                                aspectRatio: config ? config.aspectRatio : 1,
                                viewMode: 1,
                                dragMode: 'move',
                                autoCropArea: 0.95,
                                restore: false,
                                guides: true,
                                center: true,
                                highlight: false,
                                cropBoxMovable: true,
                                cropBoxResizable: true,
                                toggleDragModeOnDblclick: false,
                            });
                        } catch (err) {
                            console.error('Failed to initialize Cropper:', err);
                        }
                    }, 100);

                    cropperImage.onload = null;
                };

                cropperImage.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        function closeCropModal() {
            document.getElementById('crop-modal').classList.add('hidden');

            if (currentCropTarget && cropTargetConfigs[currentCropTarget]) {
                const fileInput = document.getElementById(cropTargetConfigs[currentCropTarget].fileInputId);
                if (fileInput) fileInput.value = '';
            }

            if (cropper) {
                cropper.destroy();
                cropper = null;
            }

            const cropperImage = document.getElementById('cropper-image');
            if (cropperImage) {
                cropperImage.onload = null;
                cropperImage.src = '';
            }
        }

        function applyCrop() {
            if (!cropper || !currentCropTarget || !cropTargetConfigs[currentCropTarget]) {
                alert('Cropper is not initialized.');
                return;
            }

            const config = cropTargetConfigs[currentCropTarget];
            const canvasOptions = {
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            };

            if (!isNaN(config.aspectRatio) && config.aspectRatio === 1) {
                canvasOptions.width = 600;
                canvasOptions.height = 600;
            } else if (config.maxWidth) {
                canvasOptions.maxWidth = config.maxWidth;
            }

            const canvas = cropper.getCroppedCanvas(canvasOptions);

            if (!canvas) {
                alert('Could not crop image. Please try again.');
                return;
            }

            let quality = 0.85;
            let dataUrl = canvas.toDataURL('image/jpeg', quality);

            while (dataUrl.length * 0.75 > 524288 && quality > 0.35) {
                quality -= 0.10;
                dataUrl = canvas.toDataURL('image/jpeg', quality);
            }

            const dataInput = document.getElementById(config.dataInputId);
            if (dataInput) dataInput.value = dataUrl;

            const previewImg = document.getElementById(config.previewImgId);
            if (previewImg) {
                previewImg.src = dataUrl;
                previewImg.classList.remove('hidden');
            }

            const placeholder = document.getElementById(config.placeholderId);
            if (placeholder) {
                placeholder.classList.add('hidden');
            }

            const removeBtn = document.getElementById(config.removeBtnId);
            if (removeBtn) {
                removeBtn.classList.remove('hidden');
            }

            closeCropModal();
        }

        function removeSelectedEmployeeImage(targetKey) {
            const config = cropTargetConfigs[targetKey];
            if (!config) return;

            const dataInput = document.getElementById(config.dataInputId);
            if (dataInput) dataInput.value = '';

            const previewImg = document.getElementById(config.previewImgId);
            if (previewImg) {
                previewImg.src = '';
                previewImg.classList.add('hidden');
            }

            const placeholder = document.getElementById(config.placeholderId);
            if (placeholder) {
                placeholder.classList.remove('hidden');
            }

            const removeBtn = document.getElementById(config.removeBtnId);
            if (removeBtn) {
                removeBtn.classList.add('hidden');
            }

            const fileInput = document.getElementById(config.fileInputId);
            if (fileInput) fileInput.value = '';
        }
    </script>
@endpush
</x-layouts.staff>
