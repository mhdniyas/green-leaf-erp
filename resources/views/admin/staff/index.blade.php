<x-layouts.staff title="Staff Dashboard">
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Staff Management</h1>
                <p class="text-sm font-semibold text-slate-500">Overview of employee count, attendance, leave, and payroll activity.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.staff.employees.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">Employees</a>
                <a href="{{ route('admin.staff.attendance') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">Attendance</a>
                <a href="{{ route('admin.staff.leaves.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">Leave Queue</a>
                <a href="{{ route('admin.staff.payroll.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">Payroll</a>
            </div>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black text-slate-950">Dashboard Date</h2>
                    <p class="text-sm font-semibold text-slate-500">Shop cards and attendance details below follow this selected date.</p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="block">
                        <span class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Business Date</span>
                        <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="mt-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold">
                    </label>
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white">Load Date</button>
                </div>
            </form>
        </section>

        <section class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
            @foreach([
                'Total Employees' => $stats['total_employees'],
                'Office Staff' => $stats['office_staff'],
                'Shop Staff' => $stats['shop_staff'],
                'Present Today' => $stats['present_today'],
                'Leave Today' => $stats['leave_today'],
                'Pending Leaves' => $stats['pending_leave_requests'],
            ] as $label => $value)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">{{ $value }}</p>
                </article>
            @endforeach
        </section>

        <section class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black text-slate-950">Owned Shop Coverage</h2>
                    <p class="text-sm font-semibold text-slate-500">Shop cards show employee attendance details for {{ $selectedDate->format('d M Y') }}.</p>
                </div>
                <a href="{{ route('admin.staff.attendance', ['date' => $selectedDate->format('Y-m-d')]) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">Open Full Attendance Board</a>
            </div>

            <div class="grid gap-5 xl:grid-cols-2">
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-black text-slate-950">Office Desk</h3>
                            <p class="text-sm font-semibold text-slate-500">Admin-updated office attendance for the selected date.</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-700">{{ $officeRecords->count() }} entry{{ $officeRecords->count() === 1 ? '' : 'ies' }}</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($officeRecords as $attendance)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <a href="{{ route('admin.staff.show', $attendance->employee) }}" class="text-sm font-black text-slate-950 underline-offset-4 hover:text-cyan-700 hover:underline">{{ $attendance->employee->name }}</a>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $attendance->employee->category->name }} · {{ strtoupper($attendance->employee->employee_code) }}</p>
                                    </div>
                                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-black uppercase text-slate-700">{{ str_replace('_', ' ', $attendance->status) }}</span>
                                </div>
                                <p class="mt-3 text-xs font-semibold text-slate-500">Marked by {{ $attendance->markedBy?->name ?? 'System' }} · {{ $attendance->marked_at?->format('h:i A') ?? 'Time pending' }}</p>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm font-semibold text-slate-500">
                                No office attendance entries recorded for this date.
                            </div>
                        @endforelse
                    </div>
                </article>

                @foreach($shopCards as $shopCard)
                    <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-black text-slate-950">{{ $shopCard['shop']->name }}</h3>
                                <p class="text-sm font-semibold text-slate-500">Owned shop attendance entries for {{ $selectedDate->format('d M Y') }}.</p>
                            </div>
                            <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-black text-cyan-700">{{ $shopCard['records']->count() }} staff</span>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-3">
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-3">
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Present</p>
                                <p class="mt-1 text-xl font-black text-slate-950">{{ $shopCard['present_count'] }}</p>
                            </div>
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-3">
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-amber-700">Half Day</p>
                                <p class="mt-1 text-xl font-black text-slate-950">{{ $shopCard['half_day_count'] }}</p>
                            </div>
                            <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-3">
                                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-cyan-700">Leave</p>
                                <p class="mt-1 text-xl font-black text-slate-950">{{ $shopCard['leave_count'] }}</p>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3">
                            @forelse($shopCard['records'] as $attendance)
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <a href="{{ route('admin.staff.show', $attendance->employee) }}" class="text-sm font-black text-slate-950 underline-offset-4 hover:text-cyan-700 hover:underline">{{ $attendance->employee->name }}</a>
                                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $attendance->employee->category->name }} · {{ strtoupper($attendance->employee->employee_code) }}</p>
                                        </div>
                                        <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-black uppercase text-slate-700">{{ str_replace('_', ' ', $attendance->status) }}</span>
                                    </div>
                                    <p class="mt-3 text-xs font-semibold text-slate-500">Marked by {{ $attendance->markedBy?->name ?? 'System' }} · {{ $attendance->marked_at?->format('h:i A') ?? 'Time pending' }}</p>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm font-semibold text-slate-500">
                                    No staff attendance recorded for this owned shop on the selected date.
                                </div>
                            @endforelse
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.05fr_0.95fr]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-black text-slate-950">Today Attendance Snapshot</h2>
                        <p class="text-sm font-semibold text-slate-500">Quick view of the current day before opening the full boards.</p>
                    </div>
                    <a href="{{ route('admin.staff.attendance', ['date' => $selectedDate->format('Y-m-d')]) }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white">Open Attendance</a>
                </div>

                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-slate-500">
                            <tr>
                                <th class="pb-3">Employee</th>
                                <th class="pb-3">Category</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3">Marked By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($attendanceRecords as $attendance)
                                <tr>
                                    <td class="py-3 font-bold text-slate-900">{{ $attendance->employee->name }}</td>
                                    <td class="py-3">{{ $attendance->employee->category->name }}</td>
                                    <td class="py-3 capitalize">{{ str_replace('_', ' ', $attendance->status) }}</td>
                                    <td class="py-3">{{ $attendance->markedBy?->name ?? 'System' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-sm font-semibold text-slate-500">No attendance entries recorded for {{ $selectedDate->format('d M Y') }}.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-6">
                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-black text-slate-950">Employees Section</h2>
                    <p class="mt-2 text-sm font-semibold text-slate-500">Use the dedicated employees section to create, edit, and open staff profiles by category tab.</p>
                    <a href="{{ route('admin.staff.employees.index') }}" class="mt-5 inline-flex rounded-xl bg-cyan-500 px-4 py-2 text-sm font-black text-slate-950">Open Employee CRUD</a>
                </article>

                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-black text-slate-950">Payroll Rules</h2>
                    <p class="mt-2 text-sm font-semibold text-slate-500">Category-based leave limits and salary weights are now under the Categories section in the staff sidebar.</p>
                    <a href="{{ route('admin.staff.categories.index') }}" class="mt-5 inline-flex rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white">Open Categories</a>
                </article>
            </div>
        </section>
    </div>
</x-layouts.staff>
