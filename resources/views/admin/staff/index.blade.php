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
