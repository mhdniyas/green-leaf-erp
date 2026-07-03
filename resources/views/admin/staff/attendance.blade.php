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
        <div>
            <h1 class="text-2xl font-black text-slate-950">Staff Attendance</h1>
            <p class="text-sm font-semibold text-slate-500">Daily attendance board with visible marking details, updater visibility, and scoped filters.</p>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-black text-slate-950">Attendance Filters</h2>
                    <p class="text-sm font-semibold text-slate-500">Filter by date, shop, office or shop staff, and payroll category.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-right">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Visible Time</p>
                    <p class="mt-1 text-sm font-black text-slate-950">Check-in and updater shown on each card</p>
                </div>
            </div>

            <form method="GET" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-[1.2fr_1fr_1fr_1fr_1fr_auto]">
                <input type="search" name="search" value="{{ $search }}" placeholder="Search employee name, code, phone" class="rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold">

                <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold">

                <select name="shop_id" class="rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold">
                    <option value="">All Owned Shops and Office</option>
                    @foreach($shops as $shop)
                        <option value="{{ $shop->id }}" @selected($selectedShopId === $shop->id)>{{ $shop->name }}</option>
                    @endforeach
                </select>

                <select name="staff_area" class="rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold">
                    <option value="">All Staff Areas</option>
                    <option value="office" @selected($selectedStaffArea === 'office')>Office Staff</option>
                    <option value="shop" @selected($selectedStaffArea === 'shop')>Shop Staff</option>
                </select>

                <select name="category" class="rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->code }}" @selected($selectedCategory?->code === $category->code)>{{ $category->name }}</option>
                    @endforeach
                </select>

                <button type="submit" class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white">Apply</button>
            </form>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    'present' => ['label' => 'Present', 'value' => $statusCounts['present']],
                    'half_day' => ['label' => 'Half Day', 'value' => $statusCounts['half_day']],
                    'leave' => ['label' => 'On Leave', 'value' => $statusCounts['leave']],
                    'absent' => ['label' => 'Absent', 'value' => $statusCounts['absent']],
                ] as $statusKey => $item)
                    @php($statusQuery = array_merge(request()->query(), ['status' => $selectedStatus === $statusKey ? null : $statusKey]))
                    <a href="{{ route('admin.staff.attendance', array_filter($statusQuery, fn ($value) => $value !== null && $value !== '')) }}" class="block rounded-2xl border p-4 transition {{ $selectedStatus === $statusKey ? 'border-cyan-300 bg-cyan-50' : 'border-slate-200 bg-slate-50 hover:border-slate-300 hover:bg-white' }}">
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-400">{{ $item['label'] }}</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">{{ $item['value'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black text-slate-950">Attendance Board</h2>
                    <p class="text-sm font-semibold text-slate-500">{{ $selectedDate->format('d M Y') }} operational attendance list.</p>
                </div>
            </div>

            <div class="mt-5 space-y-4">
                @if($employees->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm font-semibold text-slate-500">
                        No employees matched the current attendance filters.
                    </div>
                @endif
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
                                    <a href="{{ route('admin.staff.show', $employee) }}" class="text-lg font-black text-slate-950 underline-offset-4 hover:text-cyan-700 hover:underline">{{ $employee->name }}</a>
                                    <span class="rounded-full border px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] {{ $statusStyles[$status] ?? 'border-slate-200 bg-slate-100 text-slate-700' }}">
                                        {{ str_replace('_', ' ', $status) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm font-semibold text-slate-500">{{ $employee->employee_code }} · {{ $employee->category->name }} · {{ ucfirst($employee->staff_area) }}</p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <article class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Check-In Time</p>
                                    <p class="mt-1 text-sm font-black text-slate-950">{{ $attendance?->marked_at?->format('h:i A') ?? 'Not marked' }}</p>
                                </article>
                                <article class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Updated At</p>
                                    <p class="mt-1 text-sm font-black text-slate-950">{{ $attendance?->updated_at?->format('h:i A') ?? 'No update' }}</p>
                                </article>
                                <article class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Updated By</p>
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
                            <p>Updated by {{ $attendance?->markedBy?->name ?? 'Pending admin mark' }} · Source: {{ ucfirst($attendance?->source ?? 'admin') }}</p>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.staff>
