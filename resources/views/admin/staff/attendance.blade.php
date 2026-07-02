<x-layouts.staff title="Staff Attendance">
    @php
        $statusStyles = [
            'present' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'half_day' => 'border-amber-200 bg-amber-50 text-amber-800',
            'absent' => 'border-rose-200 bg-rose-50 text-rose-800',
            'leave' => 'border-cyan-200 bg-cyan-50 text-cyan-800',
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Staff Attendance</h1>
                <p class="text-sm font-semibold text-slate-500">Daily attendance board with visible marking details, work location, and check-in timestamps.</p>
            </div>
            <form method="GET" class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white">Load</button>
            </form>
        </div>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach([
                'Present' => $attendanceRecords->where('status', 'present')->count(),
                'Half Day' => $attendanceRecords->where('status', 'half_day')->count(),
                'On Leave' => $attendanceRecords->where('status', 'leave')->count(),
                'Absent' => max(0, $employees->count() - $attendanceRecords->whereIn('status', ['present', 'half_day', 'leave'])->count()),
            ] as $label => $value)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-black text-slate-950">{{ $value }}</p>
                </article>
            @endforeach
        </section>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black text-slate-950">Attendance Board</h2>
                    <p class="text-sm font-semibold text-slate-500">{{ $selectedDate->format('d M Y') }} operational attendance list.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-right">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Visible Time</p>
                    <p class="mt-1 text-sm font-black text-slate-950">Check-in and last update shown on each card</p>
                </div>
            </div>

            <div class="mt-5 space-y-4">
                @foreach($employees as $employee)
                    @php($attendance = $attendanceRecords->get($employee->id))
                    @php($status = $attendance?->status ?? 'absent')
                    <form method="POST" action="{{ route('admin.staff.attendance.store') }}" class="rounded-[1.75rem] border border-slate-200 bg-slate-50/80 p-5">
                        @csrf
                        <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                        <input type="hidden" name="attendance_date" value="{{ $selectedDate->format('Y-m-d') }}">

                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-lg font-black text-slate-950">{{ $employee->name }}</p>
                                    <span class="rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] {{ $statusStyles[$status] ?? 'border-slate-200 bg-slate-100 text-slate-700' }}">
                                        {{ str_replace('_', ' ', $status) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm font-semibold text-slate-500">{{ $employee->employee_code }} · {{ $employee->category->name }} · {{ ucfirst($employee->staff_area) }}</p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <article class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Check-In Time</p>
                                    <p class="mt-1 text-sm font-black text-slate-950">{{ $attendance?->created_at?->format('h:i A') ?? 'Not marked' }}</p>
                                </article>
                                <article class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Updated At</p>
                                    <p class="mt-1 text-sm font-black text-slate-950">{{ $attendance?->updated_at?->format('h:i A') ?? 'No update' }}</p>
                                </article>
                                <article class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Marked By</p>
                                    <p class="mt-1 text-sm font-black text-slate-950">{{ $attendance?->markedBy?->name ?? 'Pending admin mark' }}</p>
                                </article>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3 xl:grid-cols-[1fr_1fr_1.2fr_auto]">
                            <select name="status" class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold">
                                @foreach(['present' => 'Present', 'half_day' => 'Half Day', 'absent' => 'Absent', 'leave' => 'Leave'] as $value => $label)
                                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>

                            <select name="shop_id" class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold">
                                <option value="">Worked at shop (optional)</option>
                                @foreach($shops as $shop)
                                    <option value="{{ $shop->id }}" @selected((int) $attendance?->shop_id === $shop->id)>{{ $shop->name }}</option>
                                @endforeach
                            </select>

                            <input type="text" name="notes" value="{{ $attendance?->notes }}" placeholder="Supervisor notes, travel remarks, or exceptions" class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold">

                            <button type="submit" class="rounded-xl bg-cyan-500 px-5 py-3 text-sm font-black text-slate-950">Save</button>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs font-semibold text-slate-500">
                            <p>Work location: {{ $attendance?->shop?->name ?? ($employee->defaultShop?->name ?? 'Office / unassigned') }}</p>
                            <p>Source: {{ ucfirst($attendance?->source ?? 'admin') }}</p>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.staff>
