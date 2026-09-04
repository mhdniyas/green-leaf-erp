<!-- TAB 1: STAFF DIRECTORY LIST (COMPACT ROWS) -->
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
            @php
                $attendance = $attendanceRecords->get($employee->id);
                $status = $attendance?->status ?? 'absent';
            @endphp
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
            </div>
        @empty
            <div class="py-6 text-center text-xs font-semibold text-slate-400">
                No active shop staff registered for this shop.
            </div>
        @endforelse
    </div>
</section>
