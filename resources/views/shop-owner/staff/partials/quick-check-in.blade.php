@php
    $statusConfigMap = [
        'present' => [
            'cardClass' => 'border-emerald-200 bg-emerald-50/70 text-emerald-950',
            'svg' => '<svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
        ],
        'half_day' => [
            'cardClass' => 'border-amber-200 bg-amber-50/70 text-amber-950',
            'svg' => '<svg class="h-5 w-5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" /></svg>',
        ],
        'leave' => [
            'cardClass' => 'border-cyan-200 bg-cyan-50/70 text-cyan-950',
            'svg' => '<svg class="h-5 w-5 text-cyan-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>',
        ],
        'absent' => [
            'cardClass' => 'border-rose-200 bg-rose-50/70 text-rose-950',
            'svg' => '<svg class="h-5 w-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
        ],
    ];
@endphp

<section class="rounded-2xl border border-slate-200 bg-white p-3 sm:p-4 shadow-xs space-y-3">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
        <div>
            <h2 class="text-xs font-black uppercase tracking-wider text-slate-900 whitespace-nowrap">Quick Check-In</h2>
            <p class="text-[11px] font-semibold text-slate-400">{{ $selectedDate->format('d M Y') }}</p>
        </div>

        <div>
            @if($isAttendanceOpen)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-extrabold text-emerald-800 border border-emerald-200 shadow-2xs">
                    <svg class="h-3.5 w-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Attendance open · until {{ $cutoffFormatted }}
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-extrabold text-amber-900 border border-amber-200 shadow-2xs">
                    <svg class="h-3.5 w-3.5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Attendance closed · {{ $cutoffFormatted }}
                </span>
            @endif
        </div>
    </div>

    <div class="divide-y divide-slate-100">
        @forelse($employees as $employee)
            @php
                $attendance = $attendanceRecords->get($employee->id);
                $isMarked = $attendance !== null;
                $status = $attendance?->status;
                $statusUpper = $status ? str_replace('_', ' ', strtoupper((string) $status)) : 'NOT MARKED';
                $markedTimeStr = $attendance?->marked_at ? $attendance->marked_at->timezone('Asia/Kolkata')->format('g:i A') : '';
                $notesStr = $attendance?->notes ?? '';
                $currentCardClass = $statusConfigMap[$status]['cardClass'] ?? 'border-slate-200 bg-slate-50 text-slate-900';
                $currentSvg = $statusConfigMap[$status]['svg'] ?? '';
            @endphp

            <form method="POST" 
                  action="{{ route('shop-owner.staff.attendance.store') }}" 
                  class="py-3 space-y-2" 
                  data-owned-shop-attendance-form 
                  id="attendance-form-emp-{{ $employee->id }}"
                  data-employee-id="{{ $employee->id }}"
                  data-employee-name="{{ $employee->name }}"
                  data-is-marked="{{ $isMarked ? '1' : '0' }}"
                  data-status="{{ $status ?? '' }}"
                  data-status-label="{{ $statusUpper }}"
                  data-marked-at="{{ $markedTimeStr ? 'Marked at ' . $markedTimeStr : '' }}"
                  data-notes="{{ $notesStr }}">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <input type="hidden" name="attendance_date" value="{{ $selectedDate->format('Y-m-d') }}">
                <input type="hidden" name="shop_id" value="{{ $selectedShop?->id }}">
                <input type="hidden" name="notes" value="{{ $notesStr }}" data-attendance-notes-input>

                <!-- EMPLOYEE IDENTITY & UNMARKED BADGE HEADER -->
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        @if($employee->photo_url)
                            <img src="{{ $employee->photo_url }}" class="h-10 w-10 rounded-full object-cover border border-slate-200 shrink-0 shadow-2xs" alt="{{ $employee->name }}">
                        @else
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-900 font-bold text-white text-xs shrink-0 shadow-2xs">
                                {{ Illuminate\Support\Str::upper(substr($employee->name, 0, 2)) }}
                            </div>
                        @endif
                        <div class="min-w-0">
                            <p class="text-xs sm:text-sm font-black text-slate-950 truncate">{{ $employee->name }}</p>
                            <p class="text-[11px] font-semibold text-slate-400 truncate">
                                {{ $employee->employee_code }}
                            </p>
                        </div>
                    </div>

                    <div data-attendance-not-marked class="{{ !$isMarked ? '' : 'hidden' }} shrink-0">
                        <span class="rounded-lg px-2.5 py-1 text-[10px] font-black uppercase tracking-wider border border-slate-200 bg-slate-100 text-slate-500 inline-block shadow-2xs">
                            NOT MARKED
                        </span>
                    </div>
                </div>

                <!-- STATE 2: SAVED ATTENDANCE STATUS CARD -->
                <div data-attendance-marked-container
                     class="{{ $isMarked ? '' : 'hidden' }} mt-2 rounded-xl border p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 transition {{ $currentCardClass }}">
                    
                    <div class="flex items-start sm:items-center gap-2.5 min-w-0">
                        <!-- STATUS ICON -->
                        <div class="mt-0.5 sm:mt-0 shrink-0" data-attendance-icon-wrapper>
                            {!! $currentSvg !!}
                        </div>

                        <div class="min-w-0 space-y-0.5">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black uppercase tracking-wider" data-attendance-status-badge>
                                    {{ $statusUpper }}
                                </span>
                                <span class="text-[11px] font-bold text-slate-500" data-attendance-time>
                                    @if($markedTimeStr)
                                        Marked at {{ $markedTimeStr }}
                                    @endif
                                </span>
                            </div>
                            <p class="text-[11px] font-semibold text-slate-600 truncate max-w-xs {{ ($status !== 'present' && $notesStr) ? '' : 'hidden' }}" data-attendance-reason>
                                @if($notesStr)
                                    Reason: {{ $notesStr }}
                                @endif
                            </p>
                        </div>
                    </div>

                    @if($isAttendanceOpen)
                        <div class="shrink-0 pt-1 sm:pt-0">
                            <button type="button" 
                                    data-action="change-attendance"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-extrabold text-slate-700 shadow-2xs hover:bg-slate-100 hover:text-slate-900 transition cursor-pointer">
                                <svg class="h-3.5 w-3.5 text-slate-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                                Change
                            </button>
                        </div>
                    @endif
                </div>

                <!-- STATE 1 / CHANGE MODE: ACTION BUTTONS GRID -->
                @if($isAttendanceOpen)
                    <div data-attendance-actions-grid class="{{ $isMarked ? 'hidden' : '' }} mt-2 space-y-2">
                        
                        <div data-attendance-change-header class="{{ $isMarked ? '' : 'hidden' }} flex items-center justify-between pt-1 pb-0.5">
                            <span class="text-[11px] font-black uppercase tracking-wider text-slate-500">Change Attendance</span>
                            <button type="button" data-action="cancel-change" class="text-xs font-black text-slate-500 hover:text-slate-900 cursor-pointer">
                                Cancel
                            </button>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <!-- PRESENT -->
                            <button type="button" 
                                    data-action="mark-status"
                                    data-status="present"
                                    class="min-h-[44px] rounded-xl border p-2 text-center flex items-center justify-center gap-1.5 text-xs font-black transition cursor-pointer border-emerald-200 bg-emerald-50/80 text-emerald-900 hover:bg-emerald-600 hover:text-white disabled:opacity-50 shadow-2xs">
                                <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span data-button-label>Present</span>
                            </button>

                            <!-- HALF DAY -->
                            <button type="button" 
                                    data-action="mark-status"
                                    data-status="half_day"
                                    data-status-label="Half Day"
                                    class="min-h-[44px] rounded-xl border p-2 text-center flex items-center justify-center gap-1.5 text-xs font-black transition cursor-pointer border-amber-200 bg-amber-50/80 text-amber-900 hover:bg-amber-500 hover:text-white disabled:opacity-50 shadow-2xs">
                                <svg class="h-4 w-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                                </svg>
                                <span data-button-label>Half Day</span>
                            </button>

                            <!-- LEAVE -->
                            <button type="button" 
                                    data-action="mark-status"
                                    data-status="leave"
                                    data-status-label="Leave"
                                    class="min-h-[44px] rounded-xl border p-2 text-center flex items-center justify-center gap-1.5 text-xs font-black transition cursor-pointer border-cyan-200 bg-cyan-50/80 text-cyan-900 hover:bg-cyan-600 hover:text-white disabled:opacity-50 shadow-2xs">
                                <svg class="h-4 w-4 text-cyan-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span data-button-label>Leave</span>
                            </button>

                            <!-- ABSENT -->
                            <button type="button" 
                                    data-action="mark-status"
                                    data-status="absent"
                                    data-status-label="Absent"
                                    class="min-h-[44px] rounded-xl border p-2 text-center flex items-center justify-center gap-1.5 text-xs font-black transition cursor-pointer border-rose-200 bg-rose-50/80 text-rose-900 hover:bg-rose-600 hover:text-white disabled:opacity-50 shadow-2xs">
                                <svg class="h-4 w-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span data-button-label>Absent</span>
                            </button>
                        </div>

                        <div data-attendance-cancel-container class="{{ $isMarked ? '' : 'hidden' }} pt-1">
                            <button type="button" data-action="cancel-change" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 text-center text-xs font-extrabold text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition cursor-pointer">
                                Cancel
                            </button>
                        </div>

                        <div data-attendance-error-msg class="hidden rounded-xl border border-rose-200 bg-rose-50 p-2 text-xs font-bold text-rose-700"></div>
                    </div>
                @else
                    <div class="{{ $isMarked ? 'hidden' : '' }} mt-2 rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-center text-xs font-bold text-slate-500">
                        Not marked · Marking closed at {{ $cutoffFormatted }}. Contact HR for corrections.
                    </div>
                @endif
            </form>
        @empty
            <div class="py-6 text-center text-xs font-semibold text-slate-400">
                No shop staff available for check-in.
            </div>
        @endforelse
    </div>

    <!-- PURE JAVASCRIPT REASON MODAL -->
    <div id="attendance-reason-modal"
         class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
         tabindex="-1"
         role="dialog"
         aria-modal="true">
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" data-action="close-reason-modal"></div>
        <div class="relative w-full max-w-sm rounded-2xl bg-white p-4 shadow-xl border border-slate-200 space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h3 class="text-xs font-black text-slate-900 uppercase">Reason for <span id="attendance-modal-status-label" class="text-emerald-700"></span></h3>
                <button type="button" data-action="close-reason-modal" class="text-slate-400 hover:text-slate-700 text-xs font-bold cursor-pointer">✕</button>
            </div>
            <div>
                <p id="attendance-modal-employee-name" class="text-[11px] font-bold text-slate-500 mb-1"></p>
                <label for="attendance-modal-reason-input" class="block text-[11px] font-bold text-slate-600 mb-1">Reason *</label>
                <textarea id="attendance-modal-reason-input" rows="2" placeholder="e.g. Medical appointment / Family function / No show" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-semibold focus:border-emerald-600 focus:ring-emerald-600" required></textarea>
                <p id="attendance-modal-error" class="hidden mt-1 text-[11px] font-bold text-rose-600"></p>
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" data-action="close-reason-modal" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 cursor-pointer">Cancel</button>
                <button type="button" id="attendance-modal-save-btn" class="rounded-xl bg-emerald-600 px-4 py-1.5 text-xs font-black text-white hover:bg-emerald-700 cursor-pointer">
                    Save
                </button>
            </div>
        </div>
    </div>
</section>
