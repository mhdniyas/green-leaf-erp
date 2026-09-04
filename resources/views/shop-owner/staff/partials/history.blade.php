<!-- HISTORY TAB: COMPACT MONTHLY ATTENDANCE CALENDAR -->
@php
    $prevMonth = $calendarMonth->copy()->subMonth();
    $nextMonth = $calendarMonth->copy()->addMonth();
    $daysInMonth = $calendarMonth->daysInMonth;
    $firstDayOfWeek = $calendarMonth->copy()->startOfMonth()->dayOfWeekIso; // 1 = Monday, 7 = Sunday
    $todayDate = today()->format('Y-m-d');
    $currentSelectedDate = $selectedDate->format('Y-m-d');
@endphp
<section class="grid gap-3 lg:grid-cols-12">
    <!-- COMPACT CALENDAR CARD -->
    <article class="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xs space-y-3 lg:col-span-5">
        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'tab' => 'history', 'month' => $prevMonth->format('Y-m'), 'date' => $prevMonth->copy()->startOfMonth()->format('Y-m-d')]) }}" 
               class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition"
               title="Previous month">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </a>
            <div class="text-center">
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-900">{{ $calendarMonth->format('F Y') }}</h2>
                <p class="text-[10px] font-semibold text-slate-400">Attendance History</p>
            </div>
            <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'tab' => 'history', 'month' => $nextMonth->format('Y-m'), 'date' => $nextMonth->copy()->startOfMonth()->format('Y-m-d')]) }}" 
               class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition"
               title="Next month">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5-7.5" />
                </svg>
            </a>
        </div>

        <!-- 7-COLUMN CALENDAR (Mon-Sun) -->
        <div>
            <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-black uppercase text-slate-400 pb-1.5">
                <div>Mon</div>
                <div>Tue</div>
                <div>Wed</div>
                <div>Thu</div>
                <div>Fri</div>
                <div>Sat</div>
                <div>Sun</div>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center">
                @for($i = 1; $i < $firstDayOfWeek; $i++)
                    <div class="h-8"></div>
                @endfor
                @for($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $dayDateStr = $calendarMonth->copy()->day($day)->format('Y-m-d');
                        $isSelected = $dayDateStr === $currentSelectedDate;
                        $isToday = $dayDateStr === $todayDate;
                        $hasAttendance = $historyDatesWithAttendance->contains($dayDateStr);
                    @endphp
                    <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'tab' => 'history', 'month' => $calendarMonth->format('Y-m'), 'date' => $dayDateStr]) }}"
                       class="relative flex h-8 flex-col items-center justify-center rounded-lg text-xs font-bold transition
                              {{ $isSelected ? 'bg-slate-950 text-white shadow-xs' : ($isToday ? 'border border-emerald-500 font-black text-emerald-950 bg-emerald-50/50' : 'text-slate-700 hover:bg-slate-100') }}">
                        <span>{{ $day }}</span>
                        @if($hasAttendance)
                            <span class="absolute bottom-0.5 h-1 w-1 rounded-full {{ $isSelected ? 'bg-emerald-400' : 'bg-emerald-600' }}"></span>
                        @endif
                    </a>
                @endfor
            </div>
        </div>

        <div class="flex items-center justify-center gap-3 border-t border-slate-100 pt-2 text-[10px] font-semibold text-slate-400">
            <span class="flex items-center gap-1">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span> Has Records
            </span>
            <span class="flex items-center gap-1">
                <span class="h-2 w-2 rounded-sm border border-emerald-500 bg-emerald-50"></span> Today
            </span>
            <span class="flex items-center gap-1">
                <span class="h-2 w-2 rounded-sm bg-slate-950"></span> Selected
            </span>
        </div>
    </article>

    <!-- SELECTED DAY ATTENDANCE DETAILS -->
    <article class="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xs space-y-3 lg:col-span-7">
        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
            <div>
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-500">Selected Date</h3>
                <p class="text-sm font-black text-slate-950">{{ $selectedDate->format('d M Y') }}</p>
            </div>
            @if($historyDayAttendance->isNotEmpty())
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-black text-slate-700">
                    {{ $historyDayAttendance->count() }} marked
                </span>
            @endif
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($historyDayAttendance as $att)
                @php
                    $status = $att->status;
                @endphp
                <div class="py-2.5 space-y-1">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <p class="text-xs font-black text-slate-950 truncate">{{ $att->employee?->name }}</p>
                                <span class="rounded px-1.5 py-0.5 text-[9px] font-black uppercase border shrink-0 {{ $statusStyles[$status] ?? 'border-slate-200 bg-slate-100 text-slate-600' }}">
                                    {{ $status === 'present' ? '✓ Present' : str_replace('_', ' ', ucfirst((string) $status)) }}
                                </span>
                            </div>
                            <p class="text-[10px] font-semibold text-slate-400">
                                {{ $att->employee?->employee_code }}
                                @if($att->marked_at)
                                    · Time: {{ $att->marked_at->timezone('Asia/Kolkata')->format('g:i A') }}
                                @endif
                                @if($att->markedBy)
                                    · By: {{ $att->markedBy->name }}
                                @endif
                            </p>
                        </div>
                    </div>
                    @if($att->notes)
                        <p class="rounded-lg border border-slate-100 bg-slate-50 px-2 py-1 text-[11px] font-medium text-slate-600">
                            {{ $att->notes }}
                        </p>
                    @endif
                </div>
            @empty
                <div class="py-10 text-center text-xs font-semibold text-slate-400">
                    No attendance records for this date.
                </div>
            @endforelse
        </div>
    </article>
</section>
