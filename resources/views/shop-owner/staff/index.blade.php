@extends('shop-owner.layouts.app')

@section('title', 'Staff')
@section('page_title', 'Client Shop Staff')
@section('page_description', 'Handle attendance, advances, salary, leave, and staff history for client shops.')

@php
    $breadcrumbs = [['label' => 'Staff']];
    $tabs = [
        'staff' => 'Staff',
        'attendance' => 'Attendance',
        'salary' => 'Salary & Advance',
        'history' => 'History',
        'leave' => 'Leave',
    ];
    $statusStyles = [
        'present' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'half_day' => 'border-amber-200 bg-amber-50 text-amber-800',
        'leave' => 'border-cyan-200 bg-cyan-50 text-cyan-800',
        'absent' => 'border-rose-200 bg-rose-50 text-rose-800',
    ];

    $activeStaffCount = $employees->count();
    $presentTodayCount = $attendanceRecords->filter(fn($a) => in_array($a->status, ['present', 'half_day'], true))->count();
    $pendingCount = isset($pendingEmployees) ? $pendingEmployees->where('verification_status', 'pending')->count() : 0;
@endphp

@section('content')
    <div class="mx-auto w-full max-w-5xl space-y-3" data-staff-advance-options="{{ json_encode($advanceOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}" data-staff-salary-options="{{ json_encode($salaryOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}">
        
        <!-- COMPACT PAGE HEADER -->
        <header class="flex items-center justify-between gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-xs">
            <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                    <h1 class="text-base font-black text-slate-950 sm:text-lg">Staff</h1>
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">{{ $selectedShop?->name }}</span>
                </div>
                <p class="text-[11px] font-semibold text-slate-400 truncate">{{ $selectedDate->format('d M Y') }} · Client Shop HR</p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('shop-owner.staff.create', ['shop' => $selectedShop?->code]) }}" class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-emerald-600 px-3 text-xs font-black text-white shadow-xs hover:bg-emerald-700 active:scale-95 transition">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>+ Add Staff</span>
                </a>
            </div>
        </header>

        <!-- 3 SUMMARY TILES IN ONE ROW ON MOBILE -->
        <div class="grid grid-cols-3 gap-2">
            <!-- TILE 1: ACTIVE STAFF -->
            <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
                <div class="flex items-center gap-1">
                    <svg class="h-3.5 w-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                    <span class="text-[9px] font-black uppercase tracking-wider text-slate-500 truncate">Staff</span>
                </div>
                <p class="mt-1 text-lg font-black text-slate-950 leading-none">{{ $activeStaffCount }}</p>
            </div>

            <!-- TILE 2: PRESENT TODAY -->
            <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
                <div class="flex items-center gap-1">
                    <svg class="h-3.5 w-3.5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-[9px] font-black uppercase tracking-wider text-slate-500 truncate">Present</span>
                </div>
                <p class="mt-1 text-lg font-black text-emerald-700 leading-none">{{ $presentTodayCount }}</p>
            </div>

            <!-- TILE 3: PENDING HR -->
            <div class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs">
                <div class="flex items-center gap-1">
                    <svg class="h-3.5 w-3.5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-[9px] font-black uppercase tracking-wider text-slate-500 truncate">Pending</span>
                </div>
                <p class="mt-1 text-lg font-black text-amber-700 leading-none">{{ $pendingCount }}</p>
            </div>
        </div>

        <!-- 5 MAIN TABS -->
        @if(!$shops->isEmpty())
            <nav class="grid grid-cols-2 gap-1.5 rounded-xl border border-slate-200 bg-slate-100 p-1.5 text-center sm:grid-cols-5 sm:gap-1">
                <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'staff']) }}"
                   class="flex items-center justify-center gap-1.5 rounded-lg py-2 px-2 text-xs font-black transition {{ $selectedTab === 'staff' ? 'bg-slate-950 text-white shadow-xs' : 'text-slate-700 hover:bg-white' }}">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                    <span class="truncate">Staff</span>
                </a>
                <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'attendance']) }}"
                   class="flex items-center justify-center gap-1.5 rounded-lg py-2 px-2 text-xs font-black transition {{ $selectedTab === 'attendance' ? 'bg-slate-950 text-white shadow-xs' : 'text-slate-700 hover:bg-white' }}">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="truncate">Attendance</span>
                </a>
                <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'salary']) }}"
                   class="flex items-center justify-center gap-1.5 rounded-lg py-2 px-2 text-xs font-black transition {{ in_array($selectedTab, ['salary', 'advance'], true) ? 'bg-slate-950 text-white shadow-xs' : 'text-slate-700 hover:bg-white' }}">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v.375c0 .621.504 1.125 1.125 1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-12H21.75M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                    <span class="hidden sm:inline truncate">Salary & Advance</span>
                    <span class="sm:hidden truncate">Salary</span>
                </a>
                <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'history']) }}"
                   class="flex items-center justify-center gap-1.5 rounded-lg py-2 px-2 text-xs font-black transition {{ $selectedTab === 'history' ? 'bg-slate-950 text-white shadow-xs' : 'text-slate-700 hover:bg-white' }}">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="truncate">History</span>
                </a>
                <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'leave']) }}"
                   class="col-span-2 sm:col-span-1 flex items-center justify-center gap-1.5 rounded-lg py-2 px-2 text-xs font-black transition {{ $selectedTab === 'leave' ? 'bg-slate-950 text-white shadow-xs' : 'text-slate-700 hover:bg-white' }}">
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                    <span class="truncate">Leave</span>
                </a>
            </nav>
        @endif

        @if(in_array($selectedTab, ['salary', 'advance', 'leave', 'history'], true))
            @include('shop-owner.partials.date-range-filter', [
                'action' => route('shop-owner.staff.index'),
                'hidden' => [
                    'shop' => $selectedShop?->code,
                    'date' => $selectedDate->format('Y-m-d'),
                    'tab' => $selectedTab,
                ],
                'startDate' => $filterStartDate,
                'endDate' => $filterEndDate,
                'clearUrl' => route('shop-owner.staff.index', [
                    'shop' => $selectedShop?->code,
                    'date' => $selectedDate->format('Y-m-d'),
                    'tab' => $selectedTab,
                ]),
            ])
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-3.5 shadow-sm" role="alert" tabindex="-1">
                <div class="flex items-center gap-2 text-rose-900 font-black text-xs mb-1">
                    <svg class="h-4 w-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <span>Please correct the errors below:</span>
                </div>
                <ul class="list-disc list-inside text-xs font-semibold text-rose-700 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- TAB CONTENT PARTIALS -->
        @if($selectedTab === 'staff')
            @include('shop-owner.staff.partials.staff')
        @elseif($selectedTab === 'attendance')
            @include('shop-owner.staff.partials.attendance')
        @elseif(in_array($selectedTab, ['salary', 'advance'], true))
            @include('shop-owner.staff.partials.salary')
        @elseif($selectedTab === 'leave')
            @include('shop-owner.staff.partials.leave')
        @elseif($selectedTab === 'history')
            @include('shop-owner.staff.partials.history')
        @endif

    </div>

    @push('scripts')
        <script>
            // Pure Vanilla JavaScript Controller for Attendance (Zero Alpine Dependency)
            (() => {
                const statusMap = {
                    present: {
                        label: 'PRESENT',
                        cardClass: 'border-emerald-200 bg-emerald-50/70 text-emerald-950',
                        iconSvg: '<svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
                    },
                    half_day: {
                        label: 'HALF DAY',
                        cardClass: 'border-amber-200 bg-amber-50/70 text-amber-950',
                        iconSvg: '<svg class="h-5 w-5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" /></svg>'
                    },
                    leave: {
                        label: 'LEAVE',
                        cardClass: 'border-cyan-200 bg-cyan-50/70 text-cyan-950',
                        iconSvg: '<svg class="h-5 w-5 text-cyan-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>'
                    },
                    absent: {
                        label: 'ABSENT',
                        cardClass: 'border-rose-200 bg-rose-50/70 text-rose-950',
                        iconSvg: '<svg class="h-5 w-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
                    }
                };

                const allCardClasses = [
                    'border-emerald-200', 'bg-emerald-50/70', 'text-emerald-950',
                    'border-amber-200', 'bg-amber-50/70', 'text-amber-950',
                    'border-cyan-200', 'bg-cyan-50/70', 'text-cyan-950',
                    'border-rose-200', 'bg-rose-50/70', 'text-rose-950',
                    'border-slate-200', 'bg-slate-50', 'text-slate-900'
                ];

                let activeModalForm = null;
                let activeModalStatus = '';

                const modal = document.getElementById('attendance-reason-modal');
                const modalStatusLabel = document.getElementById('attendance-modal-status-label');
                const modalEmployeeName = document.getElementById('attendance-modal-employee-name');
                const modalReasonInput = document.getElementById('attendance-modal-reason-input');
                const modalError = document.getElementById('attendance-modal-error');
                const modalSaveBtn = document.getElementById('attendance-modal-save-btn');

                function openModal(form, status, statusLabel) {
                    if (!modal) return;
                    activeModalForm = form;
                    activeModalStatus = status;

                    if (modalStatusLabel) modalStatusLabel.textContent = statusLabel || status;
                    if (modalEmployeeName) modalEmployeeName.textContent = form.dataset.employeeName ? `Staff: ${form.dataset.employeeName}` : '';
                    if (modalReasonInput) {
                        modalReasonInput.value = form.dataset.notes || '';
                    }
                    if (modalError) {
                        modalError.textContent = '';
                        modalError.classList.add('hidden');
                    }

                    modal.classList.remove('hidden');
                    setTimeout(() => modalReasonInput?.focus(), 50);
                }

                function closeModal() {
                    if (!modal) return;
                    modal.classList.add('hidden');
                    activeModalForm = null;
                    activeModalStatus = '';
                    if (modalReasonInput) modalReasonInput.value = '';
                    if (modalError) {
                        modalError.textContent = '';
                        modalError.classList.add('hidden');
                    }
                }

                if (modal) {
                    modal.querySelectorAll('[data-action="close-reason-modal"]').forEach(el => {
                        el.addEventListener('click', (e) => {
                            e.preventDefault();
                            closeModal();
                        });
                    });

                    document.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                            closeModal();
                        }
                    });

                    modalSaveBtn?.addEventListener('click', () => {
                        if (!activeModalForm || !activeModalStatus) return;
                        const reason = (modalReasonInput?.value || '').trim();

                        if (reason.length < 3) {
                            if (modalError) {
                                modalError.textContent = 'Please enter a reason (minimum 3 characters)';
                                modalError.classList.remove('hidden');
                            } else if (window.showAppAlert) {
                                window.showAppAlert('Please enter a reason (minimum 3 characters)');
                            } else {
                                alert('Please enter a reason (minimum 3 characters)');
                            }
                            modalReasonInput?.focus();
                            return;
                        }

                        const formToSubmit = activeModalForm;
                        const statusToSubmit = activeModalStatus;
                        closeModal();
                        submitAttendance(formToSubmit, statusToSubmit, reason);
                    });
                }

                async function submitAttendance(form, status, notes = '', triggerBtn = null) {
                    if (!form) return;

                    const notMarkedEl = form.querySelector('[data-attendance-not-marked]');
                    const markedContainer = form.querySelector('[data-attendance-marked-container]');
                    const actionsGrid = form.querySelector('[data-attendance-actions-grid]');
                    const changeHeader = form.querySelector('[data-attendance-change-header]');
                    const cancelContainer = form.querySelector('[data-attendance-cancel-container]');
                    const errorMsgEl = form.querySelector('[data-attendance-error-msg]');
                    const notesInput = form.querySelector('[data-attendance-notes-input]');
                    const buttons = form.querySelectorAll('button');

                    if (errorMsgEl) {
                        errorMsgEl.textContent = '';
                        errorMsgEl.classList.add('hidden');
                    }

                    buttons.forEach(b => b.disabled = true);
                    const labelEl = triggerBtn?.querySelector('[data-button-label]');
                    let originalLabel = '';
                    if (labelEl) {
                        originalLabel = labelEl.textContent;
                        labelEl.textContent = 'Saving...';
                    }

                    if (notesInput) {
                        notesInput.value = notes || '';
                    }

                    const formData = new FormData(form);
                    formData.set('status', status);
                    formData.set('notes', notes || '');
                    if (status === 'leave' && notes) {
                        formData.set('leave_reason', notes);
                    } else {
                        formData.delete('leave_reason');
                    }

                    try {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                            || form.querySelector('input[name="_token"]')?.value;

                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken || ''
                            },
                            body: formData
                        });

                        const data = await response.json();

                        if (response.ok) {
                            const wasMarked = form.dataset.isMarked === '1';
                            const rawTime = data.attendance?.marked_at || data.attendance?.checked_in_at || '';
                            const timeText = rawTime ? (wasMarked ? `Updated at ${rawTime}` : `Marked at ${rawTime}`) : '';
                            const finalNotes = status === 'present' ? '' : (data.attendance?.notes || notes || '');
                            const cfg = statusMap[status] || {
                                label: status.replace('_', ' ').toUpperCase(),
                                cardClass: 'border-slate-200 bg-slate-50 text-slate-900',
                                iconSvg: ''
                            };

                            form.dataset.isMarked = '1';
                            form.dataset.status = status;
                            form.dataset.statusLabel = cfg.label;
                            form.dataset.markedAt = timeText;
                            form.dataset.notes = finalNotes;
                            if (notesInput) notesInput.value = finalNotes;

                            if (notMarkedEl) notMarkedEl.classList.add('hidden');
                            if (actionsGrid) actionsGrid.classList.add('hidden');
                            if (changeHeader) changeHeader.classList.remove('hidden');
                            if (cancelContainer) cancelContainer.classList.remove('hidden');

                            if (markedContainer) {
                                markedContainer.classList.remove('hidden');
                                allCardClasses.forEach(c => markedContainer.classList.remove(c));
                                cfg.cardClass.split(' ').forEach(c => markedContainer.classList.add(c));
                            }

                            const iconWrapper = form.querySelector('[data-attendance-icon-wrapper]');
                            if (iconWrapper) iconWrapper.innerHTML = cfg.iconSvg;

                            const statusBadge = form.querySelector('[data-attendance-status-badge]');
                            if (statusBadge) statusBadge.textContent = cfg.label;

                            const timeEl = form.querySelector('[data-attendance-time]');
                            if (timeEl) timeEl.textContent = timeText;

                            const reasonEl = form.querySelector('[data-attendance-reason]');
                            if (reasonEl) {
                                if (status !== 'present' && finalNotes) {
                                    reasonEl.textContent = `Reason: ${finalNotes}`;
                                    reasonEl.classList.remove('hidden');
                                } else {
                                    reasonEl.textContent = '';
                                    reasonEl.classList.add('hidden');
                                }
                            }

                            if (window.showAppToast) {
                                window.showAppToast(data.message || 'Attendance updated successfully.');
                            }
                        } else {
                            const errorMsg = data.message || (data.errors ? Object.values(data.errors).flat().join('\n') : 'Unable to update attendance. Try again.');
                            if (errorMsgEl) {
                                errorMsgEl.textContent = errorMsg;
                                errorMsgEl.classList.remove('hidden');
                            } else if (window.showAppAlert) {
                                window.showAppAlert(errorMsg);
                            } else {
                                alert(errorMsg);
                            }
                            if (actionsGrid) actionsGrid.classList.remove('hidden');
                        }
                    } catch (err) {
                        console.error('Attendance submit error:', err);
                        const msg = 'Unable to update attendance. Try again.';
                        if (errorMsgEl) {
                            errorMsgEl.textContent = msg;
                            errorMsgEl.classList.remove('hidden');
                        } else if (window.showAppAlert) {
                            window.showAppAlert(msg);
                        } else {
                            alert(msg);
                        }
                        if (actionsGrid) actionsGrid.classList.remove('hidden');
                    } finally {
                        buttons.forEach(b => b.disabled = false);
                        if (labelEl && originalLabel) {
                            labelEl.textContent = originalLabel;
                        }
                    }
                }

                document.addEventListener('click', (e) => {
                    const closeBtn = e.target.closest('[data-action="close-reason-modal"]');
                    if (closeBtn) {
                        e.preventDefault();
                        closeModal();
                        return;
                    }

                    const changeBtn = e.target.closest('[data-action="change-attendance"]');
                    if (changeBtn) {
                        e.preventDefault();
                        const form = changeBtn.closest('[data-owned-shop-attendance-form]');
                        if (!form) return;
                        const markedContainer = form.querySelector('[data-attendance-marked-container]');
                        const actionsGrid = form.querySelector('[data-attendance-actions-grid]');
                        const changeHeader = form.querySelector('[data-attendance-change-header]');
                        const cancelContainer = form.querySelector('[data-attendance-cancel-container]');
                        const errorMsgEl = form.querySelector('[data-attendance-error-msg]');

                        if (markedContainer) markedContainer.classList.add('hidden');
                        if (actionsGrid) actionsGrid.classList.remove('hidden');
                        if (changeHeader) changeHeader.classList.remove('hidden');
                        if (cancelContainer) cancelContainer.classList.remove('hidden');
                        if (errorMsgEl) {
                            errorMsgEl.textContent = '';
                            errorMsgEl.classList.add('hidden');
                        }
                        return;
                    }

                    const cancelBtn = e.target.closest('[data-action="cancel-change"]');
                    if (cancelBtn) {
                        e.preventDefault();
                        const form = cancelBtn.closest('[data-owned-shop-attendance-form]');
                        if (!form) return;
                        const markedContainer = form.querySelector('[data-attendance-marked-container]');
                        const actionsGrid = form.querySelector('[data-attendance-actions-grid]');
                        const errorMsgEl = form.querySelector('[data-attendance-error-msg]');

                        if (form.dataset.isMarked === '1') {
                            if (actionsGrid) actionsGrid.classList.add('hidden');
                            if (markedContainer) markedContainer.classList.remove('hidden');
                        }
                        if (errorMsgEl) {
                            errorMsgEl.textContent = '';
                            errorMsgEl.classList.add('hidden');
                        }
                        return;
                    }

                    const statusBtn = e.target.closest('[data-action="mark-status"]');
                    if (statusBtn) {
                        e.preventDefault();
                        const form = statusBtn.closest('[data-owned-shop-attendance-form]');
                        if (!form) return;
                        const status = statusBtn.dataset.status;
                        const statusLabel = statusBtn.dataset.statusLabel || status;

                        if (status === 'present') {
                            submitAttendance(form, 'present', '', statusBtn);
                        } else {
                            openModal(form, status, statusLabel);
                        }
                        return;
                    }
                });

                window.submitAttendanceStatus = function(formOrId, status, notes = '') {
                    const form = typeof formOrId === 'string' ? document.getElementById(formOrId) : formOrId;
                    if (form) submitAttendance(form, status, notes);
                };
            })();

            @if(in_array($selectedTab, ['advance', 'salary'], true))
            (() => {
                const root = document.querySelector('[data-staff-advance-options]');
                if (!root) return;
                const money = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const escapeHtml = (str) => {
                    if (str === null || str === undefined) return '';
                    return String(str)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                };
                const advanceOptions = JSON.parse(root.dataset.staffAdvanceOptions || '{}');
                const salaryOptions = JSON.parse(root.dataset.staffSalaryOptions || '{}');
                const advanceEmployee = document.querySelector('[data-advance-employee]');
                const advanceAmount = document.querySelector('[data-advance-amount]');
                const advanceSummary = document.querySelector('[data-advance-summary]');
                const advanceDecision = document.querySelector('[data-advance-decision]');
                const advanceAmountHelper = document.getElementById('adv_amount_helper');
                const salaryEmployee = document.querySelector('[data-salary-employee]');
                const salaryAmount = document.querySelector('[data-salary-amount]');
                const salarySummary = document.querySelector('[data-salary-summary]');
                const salaryAmountHelper = document.getElementById('sal_amount_helper');

                const renderAdvance = () => {
                    if (!advanceEmployee || !advanceSummary) return;
                    const option = advanceOptions[advanceEmployee.value];
                    const submitBtn = document.getElementById('advance-submit-btn');
                    const reasonAsterisk = document.getElementById('adv_reason_asterisk');
                    const reasonInput = document.getElementById('adv_request_note');

                    if (!option) {
                        advanceSummary.innerHTML = '<div class="py-2 text-center text-xs font-bold text-slate-400">Select an employee to view accrued salary and advance limits.</div>';
                        if (advanceDecision) advanceDecision.classList.add('hidden');
                        if (advanceAmountHelper) advanceAmountHelper.textContent = '';
                        if (submitBtn) {
                            submitBtn.className = 'h-11 w-full rounded-xl bg-emerald-600 text-xs font-black uppercase tracking-wider text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition cursor-pointer';
                            submitBtn.innerHTML = '<span>Give Advance</span>';
                        }
                        if (reasonAsterisk) reasonAsterisk.classList.add('hidden');
                        return;
                    }

                    const earned = Number(option.earned_amount || 0);
                    const ceiling = Number(option.advance_ceiling || Math.round(earned * 0.5 * 100) / 100);
                    const alreadyAdvanced = Number(option.already_advanced_amount || 0);
                    const available = Number(option.available_amount || 0);

                    // Clean Advance Card
                    advanceSummary.innerHTML = `
                        <div class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-2xs space-y-2.5">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                <div>
                                    <h3 class="text-xs font-black uppercase text-slate-900">${escapeHtml(option.employee_name)}</h3>
                                    <p class="text-[10px] font-semibold text-slate-400">${escapeHtml(option.role)} · Base: ₹${money.format(option.monthly_salary)}</p>
                                </div>
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700">
                                    ${option.payable_units} Days Accrued
                                </span>
                            </div>

                            <div class="space-y-1.5 text-xs">
                                <div class="flex items-center justify-between font-bold text-slate-700">
                                    <span>Accrued Salary</span>
                                    <span class="font-mono text-slate-950 font-black">₹${money.format(earned)}</span>
                                </div>
                                <div class="flex items-center justify-between font-medium text-slate-600">
                                    <span>Manager Advance Limit (50%)</span>
                                    <span class="font-mono text-slate-800">₹${money.format(ceiling)}</span>
                                </div>
                                <div class="flex items-center justify-between font-medium text-slate-600">
                                    <span>Already Advanced</span>
                                    <span class="font-mono ${alreadyAdvanced > 0 ? 'text-rose-600 font-bold' : 'text-slate-500'}">
                                        ${alreadyAdvanced > 0 ? '−' : ''}₹${money.format(alreadyAdvanced)}
                                    </span>
                                </div>
                                <div class="border-t border-slate-100 pt-2 flex items-center justify-between text-xs font-black">
                                    <span class="text-slate-900">Available Without HR</span>
                                    <span class="font-mono text-sm ${available > 0 ? 'text-emerald-700' : 'text-slate-400'}">₹${money.format(available)}</span>
                                </div>
                            </div>
                        </div>
                    `;

                    const amount = Number(advanceAmount?.value || 0);
                    if (advanceAmountHelper) {
                        advanceAmountHelper.textContent = `Direct Limit: ₹${money.format(available)}`;
                    }

                    if (amount <= 0 || !advanceDecision) {
                        if (advanceDecision) advanceDecision.classList.add('hidden');
                        if (submitBtn) {
                            submitBtn.className = 'h-11 w-full rounded-xl bg-emerald-600 text-xs font-black uppercase tracking-wider text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition cursor-pointer';
                            submitBtn.innerHTML = '<span>Give Advance</span>';
                        }
                        if (reasonAsterisk) reasonAsterisk.classList.add('hidden');
                        if (reasonInput) reasonInput.required = false;
                        return;
                    }

                    const isAboveLimit = amount > available;
                    const overage = amount - available;

                    if (!isAboveLimit) {
                        // Within 50% limit - Direct manager payout
                        advanceDecision.className = 'rounded-xl border border-emerald-200 bg-emerald-50/80 p-3 text-xs font-bold text-emerald-900 space-y-1 block';
                        advanceDecision.innerHTML = `
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5 font-black text-emerald-800">
                                    <span>✓ Direct Advance (Instant Payout)</span>
                                </span>
                                <span class="font-mono font-black text-emerald-700">₹${money.format(amount)}</span>
                            </div>
                            <p class="text-[11px] font-medium text-emerald-700">Within manager 50% accrued ceiling. Auto-approved and recorded to shop till.</p>
                        `;
                        advanceDecision.classList.remove('hidden');

                        if (submitBtn) {
                            submitBtn.className = 'h-11 w-full rounded-xl bg-emerald-600 text-xs font-black uppercase tracking-wider text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition cursor-pointer';
                            submitBtn.innerHTML = '<span>Give Advance</span>';
                        }
                        if (reasonAsterisk) reasonAsterisk.classList.add('hidden');
                        if (reasonInput) reasonInput.required = false;
                    } else {
                        // Exceeds 50% limit - Requires HR Approval
                        advanceDecision.className = 'rounded-xl border border-amber-200 bg-amber-50/90 p-3 text-xs text-amber-950 space-y-1.5 block shadow-xs';
                        advanceDecision.innerHTML = `
                            <div class="flex items-center justify-between text-xs font-black border-b border-amber-200/70 pb-1.5 text-amber-900">
                                <span>Advance Breakdown</span>
                                <span class="rounded bg-amber-200/70 px-1.5 py-0.5 text-[10px] uppercase tracking-wide">Requires HR</span>
                            </div>
                            <div class="space-y-1 text-xs">
                                <div class="flex items-center justify-between font-bold text-slate-700">
                                    <span>Requested Advance</span>
                                    <span class="font-mono font-black text-slate-900">₹${money.format(amount)}</span>
                                </div>
                                <div class="flex items-center justify-between text-slate-600">
                                    <span>Manager Limit</span>
                                    <span class="font-mono font-bold text-slate-800">₹${money.format(available)}</span>
                                </div>
                                <div class="border-t border-amber-200/70 pt-1 flex items-center justify-between font-black text-rose-700">
                                    <span>Needs HR Approval</span>
                                    <span class="font-mono font-black text-rose-700">+₹${money.format(overage)}</span>
                                </div>
                            </div>
                        `;
                        advanceDecision.classList.remove('hidden');

                        if (submitBtn) {
                            submitBtn.className = 'h-11 w-full rounded-xl bg-amber-600 text-xs font-black uppercase tracking-wider text-white shadow-sm hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition cursor-pointer';
                            submitBtn.innerHTML = '<span>Send to HR for Approval</span>';
                        }
                        if (reasonAsterisk) reasonAsterisk.classList.remove('hidden');
                        if (reasonInput) reasonInput.required = true;
                    }
                };

                const renderSalary = () => {
                    if (!salaryEmployee || !salarySummary) return;
                    const option = salaryOptions[salaryEmployee.value];
                    if (!option) {
                        salarySummary.innerHTML = '<div class="py-2 text-center text-xs font-bold text-slate-400">Select an employee to view monthly salary balance.</div>';
                        if (salaryAmountHelper) salaryAmountHelper.textContent = '';
                        return;
                    }

                    const earned = Number(option.earned_amount || 0);
                    const advancesPaid = Number(option.already_advanced_amount || 0);
                    const previousSalaryPaid = Number(option.paid_amount || 0);
                    const salaryBalance = Number(option.remaining_amount || 0);

                    if (salaryAmountHelper) {
                        salaryAmountHelper.textContent = `Salary Balance: ₹${money.format(salaryBalance)}`;
                    }

                    // Pre-fill amount to pay with exact remaining balance
                    if (salaryAmount && (!salaryAmount.value || salaryAmount.dataset.autoFilled === 'true')) {
                        salaryAmount.value = salaryBalance > 0 ? salaryBalance.toFixed(2) : '0.00';
                        salaryAmount.dataset.autoFilled = 'true';
                    }

                    salarySummary.innerHTML = `
                        <div class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-2xs space-y-2.5">
                            <div class="flex items-start justify-between border-b border-slate-100 pb-2">
                                <div>
                                    <h3 class="text-xs font-black uppercase text-slate-900">${escapeHtml(option.employee_name)}</h3>
                                    <p class="text-[10px] font-semibold text-slate-400">${escapeHtml(option.role)} · Base: ₹${money.format(option.monthly_salary)}</p>
                                </div>
                                <span class="rounded-md bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-black text-emerald-800">
                                    ${option.payable_units} Days Payable
                                </span>
                            </div>

                            <div class="space-y-1.5 text-xs">
                                <div class="flex items-center justify-between font-bold text-slate-700">
                                    <span>Accrued Salary</span>
                                    <span class="font-mono text-slate-950 font-black">₹${money.format(earned)}</span>
                                </div>
                                <div class="flex items-center justify-between font-medium text-slate-600">
                                    <span>Advances Paid</span>
                                    <span class="font-mono ${advancesPaid > 0 ? 'text-amber-700 font-bold' : 'text-slate-500'}">
                                        ${advancesPaid > 0 ? '−' : ''}₹${money.format(advancesPaid)}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between font-medium text-slate-600">
                                    <span>Previous Salary Payments</span>
                                    <span class="font-mono ${previousSalaryPaid > 0 ? 'text-slate-700 font-bold' : 'text-slate-500'}">
                                        ${previousSalaryPaid > 0 ? '−' : ''}₹${money.format(previousSalaryPaid)}
                                    </span>
                                </div>
                                <div class="border-t border-slate-100 pt-2 flex items-center justify-between text-xs font-black">
                                    <span class="text-slate-900">Salary Balance</span>
                                    <span class="font-mono text-sm ${salaryBalance > 0 ? 'text-emerald-700' : 'text-slate-400'}">₹${money.format(salaryBalance)}</span>
                                </div>
                            </div>
                        </div>
                    `;
                };

                const setupSubmitAndUuid = (formId, btnId, uuidInputId) => {
                    const form = document.getElementById(formId);
                    const btn = document.getElementById(btnId);
                    const uuidInput = document.getElementById(uuidInputId);
                    if (!form || !btn) return;

                    form.addEventListener('submit', () => {
                        if (uuidInput && !uuidInput.value) {
                            uuidInput.value = (window.crypto && window.crypto.randomUUID)
                                ? window.crypto.randomUUID()
                                : 'req-' + Date.now() + '-' + Math.random().toString(36).substring(2, 10);
                        }
                        btn.disabled = true;
                        btn.classList.add('opacity-50', 'cursor-not-allowed');
                        btn.innerHTML = '<span class="inline-block animate-spin mr-1">↻</span> Processing..';
                    });
                };

                advanceEmployee?.addEventListener('change', renderAdvance);
                advanceAmount?.addEventListener('input', renderAdvance);
                salaryEmployee?.addEventListener('change', renderSalary);
                salaryAmount?.addEventListener('input', () => {
                    if (salaryAmount) salaryAmount.dataset.autoFilled = 'false';
                });

                setupSubmitAndUuid('advance-request-form', 'advance-submit-btn', 'adv_request_uuid');
                setupSubmitAndUuid('salary-payment-form', 'salary-submit-btn', 'sal_request_uuid');

                renderAdvance();
                renderSalary();
            })();
            @endif
        </script>
    @endpush
@endsection
