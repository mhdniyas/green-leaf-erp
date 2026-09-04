@extends('shop-owner.layouts.app')

@section('title', 'Staff')
@section('page_title', 'Client Shop Staff')
@section('page_description', 'Handle attendance, advances, salary, leave, and staff history for client shops.')

@php
    $breadcrumbs = [['label' => 'Staff']];
    $tabs = [
        'staff' => 'Staff',
        'attendance' => 'Attendance',
        'salary' => 'Salary',
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
    <div class="mx-auto w-full max-w-5xl space-y-3" data-staff-advance-options='@json($advanceOptions)' data-staff-salary-options='@json($salaryOptions)'>
        
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

        <!-- 3 MAIN TABS IN ONE ROW ON MOBILE -->
        @if(!$shops->isEmpty())
            <div class="space-y-1.5">
                <nav class="grid grid-cols-3 gap-1 rounded-xl border border-slate-200 bg-slate-100 p-1">
                    <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'staff']) }}"
                       class="flex items-center justify-center gap-1 rounded-lg py-2 text-center text-xs font-black transition {{ in_array($selectedTab, ['staff', 'attendance'], true) && $selectedTab === 'staff' ? 'bg-slate-950 text-white shadow-xs' : 'text-slate-700 hover:bg-white' }}">
                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                        <span>Staff</span>
                    </a>
                    <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'attendance']) }}"
                       class="flex items-center justify-center gap-1 rounded-lg py-2 text-center text-xs font-black transition {{ $selectedTab === 'attendance' ? 'bg-slate-950 text-white shadow-xs' : 'text-slate-700 hover:bg-white' }}">
                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Attendance</span>
                    </a>
                    <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'salary']) }}"
                       class="flex items-center justify-center gap-1 rounded-lg py-2 text-center text-xs font-black transition {{ $selectedTab === 'salary' ? 'bg-slate-950 text-white shadow-xs' : 'text-slate-700 hover:bg-white' }}">
                        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v8m-4-4h8" />
                        </svg>
                        <span>Salary</span>
                    </a>
                </nav>

                <!-- SECONDARY LINKS (ADVANCE, LEAVE, HISTORY) -->
                <div class="flex items-center justify-center gap-2 text-[11px] font-bold text-slate-500">
                    <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'advance']) }}" class="{{ $selectedTab === 'advance' ? 'text-emerald-600 font-black underline' : 'hover:text-slate-900' }}">Advance</a>
                    <span>·</span>
                    <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'leave']) }}" class="{{ $selectedTab === 'leave' ? 'text-emerald-600 font-black underline' : 'hover:text-slate-900' }}">Leave</a>
                    <span>·</span>
                    <a href="{{ route('shop-owner.staff.index', ['shop' => $selectedShop?->code, 'date' => $selectedDate->format('Y-m-d'), 'tab' => 'history']) }}" class="{{ $selectedTab === 'history' ? 'text-emerald-600 font-black underline' : 'hover:text-slate-900' }}">History</a>
                </div>
            </div>
        @endif

        @if(in_array($selectedTab, ['advance', 'salary', 'leave'], true))
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
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-800">{{ $errors->first() }}</div>
        @endif

        <!-- TAB CONTENT PARTIALS -->
        @if($selectedTab === 'staff')
            @include('shop-owner.staff.partials.staff')
        @elseif($selectedTab === 'attendance')
            @include('shop-owner.staff.partials.attendance')
        @elseif($selectedTab === 'salary')
            @include('shop-owner.staff.partials.salary')
        @elseif($selectedTab === 'advance')
            @include('shop-owner.staff.partials.advance')
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

            (() => {
                const root = document.querySelector('[data-staff-advance-options]');
                if (!root) return;
                const money = new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const advanceOptions = JSON.parse(root.dataset.staffAdvanceOptions || '{}');
                const salaryOptions = JSON.parse(root.dataset.staffSalaryOptions || '{}');
                const advanceEmployee = document.querySelector('[data-advance-employee]');
                const advanceAmount = document.querySelector('[data-advance-amount]');
                const advanceSummary = document.querySelector('[data-advance-summary]');
                const advanceDecision = document.querySelector('[data-advance-decision]');
                const salaryEmployee = document.querySelector('[data-salary-employee]');
                const salarySummary = document.querySelector('[data-salary-summary]');

                const renderAdvance = () => {
                    if (!advanceEmployee || !advanceSummary || !advanceDecision) return;
                    const option = advanceOptions[advanceEmployee.value];
                    if (!option) {
                        advanceSummary.textContent = 'Select an employee to see available advance.';
                        advanceDecision.classList.add('hidden');
                        return;
                    }
                    advanceSummary.innerHTML = `Available ₹${money.format(option.available_amount)}<br>Already taken ₹${money.format(option.already_advanced_amount)} · Earned ₹${money.format(option.earned_amount)} · ${option.present_days} present days<br>${option.rule_label}`;
                    const amount = Number(advanceAmount?.value || 0);
                    if (amount <= 0) {
                        advanceDecision.classList.add('hidden');
                        return;
                    }
                    const needsApproval = amount > Number(option.available_amount || 0);
                    advanceDecision.textContent = needsApproval ? 'Needs HR approval (above available advance).' : 'Available now (auto-approved).';
                    advanceDecision.className = `rounded-lg border px-2.5 py-1.5 text-xs font-black ${needsApproval ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`;
                };

                const renderSalary = () => {
                    if (!salaryEmployee || !salarySummary) return;
                    const option = salaryOptions[salaryEmployee.value];
                    if (!option) {
                        salarySummary.textContent = 'Select an employee to see salary balance.';
                        return;
                    }
                    const remaining = option.remaining_amount === null ? 'Payroll pending' : `Remaining ₹${money.format(option.remaining_amount)}`;
                    salarySummary.innerHTML = `Salary ₹${money.format(option.salary_amount)}<br>Paid ₹${money.format(option.paid_amount)} · ${remaining}`;
                };

                advanceEmployee?.addEventListener('change', renderAdvance);
                advanceAmount?.addEventListener('input', renderAdvance);
                salaryEmployee?.addEventListener('change', renderSalary);
                renderAdvance();
                renderSalary();
            })();
        </script>
    @endpush
@endsection
