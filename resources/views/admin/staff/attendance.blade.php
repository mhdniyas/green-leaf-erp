<x-layouts.staff title="Staff Attendance Board">
    <div class="mx-auto max-w-7xl space-y-6">
        <!-- PAGE HEADER & DATE NAVIGATION BAR -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Staff Attendance</h1>
                <p class="text-sm font-semibold text-slate-500">Shop-wise operational attendance dashboard for quick daily marking & verification.</p>
            </div>
            
            <div class="flex flex-wrap items-center gap-2">
                <!-- DATE NAV CONTROLS -->
                <form method="GET" action="{{ route('admin.staff.attendance') }}" class="flex items-center gap-1.5 rounded-2xl border border-slate-200 bg-white p-1 shadow-xs">
                    <input type="hidden" name="search" value="{{ $search }}">
                    <input type="hidden" name="shop_id" value="{{ $selectedShopId }}">
                    <input type="hidden" name="category" value="{{ $categoryCode }}">
                    <input type="hidden" name="status" value="{{ $selectedStatus }}">

                    <a href="{{ route('admin.staff.attendance', array_merge(request()->query(), ['date' => $prevDate->format('Y-m-d')])) }}" 
                       class="rounded-xl p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-950 transition" title="Previous Day">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                    </a>

                    <a href="{{ route('admin.staff.attendance', array_merge(request()->query(), ['date' => today()->format('Y-m-d')])) }}" 
                       class="rounded-xl px-2.5 py-1 text-xs font-black text-slate-700 hover:bg-slate-100 transition">
                        Today
                    </a>

                    <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" 
                           class="rounded-xl border border-slate-200 px-2.5 py-1 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600"
                           onchange="this.form.submit()">

                    <a href="{{ route('admin.staff.attendance', array_merge(request()->query(), ['date' => $nextDate->format('Y-m-d')])) }}" 
                       class="rounded-xl p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-950 transition" title="Next Day">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </a>
                </form>

                <button type="button" 
                        class="js-open-attendance-modal inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white hover:bg-emerald-700 shadow-xs cursor-pointer"
                        data-employee-id="" 
                        data-employee-name="" 
                        data-shop-id="" 
                        data-status="present" 
                        data-notes="">
                    <span>+</span> Add Attendance
                </button>
            </div>
        </div>

        <!-- MAIN TOP SUMMARY CARDS (TOTALS ACROSS ALL SHOPS) -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-6">
            @php
                $summaryCards = [
                    'all' => ['label' => 'Total Staff', 'value' => $statusCounts['total'], 'color' => 'text-slate-950'],
                    'present' => ['label' => 'Present (P)', 'value' => $statusCounts['present'], 'color' => 'text-emerald-700'],
                    'half_day' => ['label' => 'Half Day (½)', 'value' => $statusCounts['half_day'], 'color' => 'text-amber-700'],
                    'leave' => ['label' => 'On Leave (L)', 'value' => $statusCounts['leave'], 'color' => 'text-cyan-700'],
                    'absent' => ['label' => 'Absent (A)', 'value' => $statusCounts['absent'], 'color' => 'text-rose-700'],
                    'not_marked' => ['label' => 'Not Marked (—)', 'value' => $statusCounts['not_marked'], 'color' => 'text-slate-500'],
                ];
            @endphp

            @foreach($summaryCards as $key => $card)
                @php($isActive = ($selectedStatus === $key) || ($key === 'all' && ($selectedStatus === '' || $selectedStatus === 'all')))
                <a href="{{ route('admin.staff.attendance', array_merge(request()->query(), ['status' => $key === 'all' ? null : $key])) }}" 
                   class="block rounded-2xl border p-3.5 shadow-xs transition cursor-pointer {{ $isActive ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                    <p class="text-[10px] font-black uppercase tracking-wider {{ $isActive ? 'text-slate-400' : 'text-slate-400' }}">{{ $card['label'] }}</p>
                    <p class="mt-1 text-2xl font-black {{ $isActive ? 'text-white' : $card['color'] }}">{{ $card['value'] }}</p>
                </a>
            @endforeach
        </div>

        <!-- COMPACT FILTER BAR -->
        <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs">
            <form method="GET" action="{{ route('admin.staff.attendance') }}" class="flex flex-wrap items-center justify-between gap-3">
                <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">

                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- Search Input -->
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search employee name, code..." 
                           class="h-9 w-full sm:w-56 rounded-xl border border-slate-200 px-3 text-xs font-semibold focus:border-emerald-600 focus:ring-emerald-600">

                    <!-- Shop Select -->
                    <select name="shop_id" class="h-9 rounded-xl border border-slate-200 px-3 text-xs font-semibold">
                        <option value="">-- All Shops --</option>
                        @foreach($shops as $shop)
                            <option value="{{ $shop->id }}" @selected($selectedShopId === $shop->id)>{{ $shop->name }}</option>
                        @endforeach
                    </select>

                    <!-- Category Select -->
                    <select name="category" class="h-9 rounded-xl border border-slate-200 px-3 text-xs font-semibold">
                        <option value="">-- All Categories --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->code }}" @selected($categoryCode === $cat->code)>{{ $cat->name }}</option>
                        @endforeach
                    </select>

                    <!-- Status Select -->
                    <select name="status" class="h-9 rounded-xl border border-slate-200 px-3 text-xs font-semibold">
                        <option value="">-- All Statuses --</option>
                        <option value="present" @selected($selectedStatus === 'present')>Present (P)</option>
                        <option value="half_day" @selected($selectedStatus === 'half_day')>Half Day (½)</option>
                        <option value="leave" @selected($selectedStatus === 'leave')>Leave (L)</option>
                        <option value="absent" @selected($selectedStatus === 'absent')>Absent (A)</option>
                        <option value="not_marked" @selected($selectedStatus === 'not_marked')>Not Marked (—)</option>
                    </select>

                    <button type="submit" class="h-9 rounded-xl bg-slate-950 px-4 text-xs font-bold text-white hover:bg-slate-800 cursor-pointer">Filter</button>
                    @if($search || $selectedShopId || $categoryCode || $selectedStatus)
                        <a href="{{ route('admin.staff.attendance', ['date' => $selectedDate->format('Y-m-d')]) }}" 
                           class="h-9 rounded-xl border border-slate-200 px-3 flex items-center text-xs font-bold text-slate-600 hover:bg-slate-50">Reset</a>
                    @endif
                </div>
            </form>
        </div>

        <!-- SHOP CARDS GRID / STACK (ONE-VIEW SHOP-WISE ATTENDANCE) -->
        <div class="space-y-4">
            @forelse($shopGroups as $group)
                <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                    <!-- SHOP CARD HEADER -->
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/80 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-black uppercase tracking-wider text-slate-900">{{ $group['shop_name'] }}</h2>
                            <span class="rounded-full border border-slate-200 bg-white px-2.5 py-0.5 text-[10px] font-black text-slate-700">
                                {{ $group['total'] }} Staff
                            </span>
                        </div>

                        <!-- SHOP HEADER SUMMARY PILLS -->
                        <div class="flex items-center gap-1.5 flex-wrap">
                            @if($group['present'] > 0)
                                <span class="rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-800">
                                    {{ $group['present'] }} P
                                </span>
                            @endif
                            @if($group['half_day'] > 0)
                                <span class="rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-black text-amber-800">
                                    {{ $group['half_day'] }} ½
                                </span>
                            @endif
                            @if($group['leave'] > 0)
                                <span class="rounded-md border border-cyan-200 bg-cyan-50 px-2 py-0.5 text-[10px] font-black text-cyan-800">
                                    {{ $group['leave'] }} L
                                </span>
                            @endif
                            @if($group['absent'] > 0)
                                <span class="rounded-md border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-black text-rose-800">
                                    {{ $group['absent'] }} A
                                </span>
                            @endif
                            @if($group['not_marked'] > 0)
                                <span class="rounded-md border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] font-black text-slate-500">
                                    {{ $group['not_marked'] }} —
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- EMPLOYEE ROWS IN SHOP CARD -->
                    <div class="divide-y divide-slate-100">
                        @foreach($group['employees'] as $item)
                            @php($emp = $item['employee'])
                            @php($att = $item['attendance'])
                            @php($status = $item['status'])

                            <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3 gap-3 hover:bg-slate-50/60 transition">
                                <!-- Employee Profile Info -->
                                <div class="flex items-center gap-3 min-w-0">
                                    @if($emp->photo_url)
                                        <img src="{{ $emp->photo_url }}" class="h-9 w-9 rounded-full object-cover border border-slate-200 shrink-0" alt="{{ $emp->name }}">
                                    @else
                                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-900 font-bold text-white text-xs shrink-0">
                                            {{ Illuminate\Support\Str::upper(substr($emp->name, 0, 2)) }}
                                        </div>
                                    @endif

                                    <div class="min-w-0">
                                        <a href="{{ route('admin.staff.assignments.show', $emp) }}" class="text-xs font-black text-slate-950 hover:text-emerald-700 hover:underline truncate block">
                                            {{ $emp->name }}
                                        </a>
                                        <p class="text-[10px] font-semibold text-slate-400 truncate">
                                            {{ $emp->employee_code }} · {{ $emp->category?->name ?? 'Staff' }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Status Badge, Time & Reason -->
                                <div class="flex flex-wrap sm:flex-nowrap items-center justify-between sm:justify-end gap-3 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                                    <div class="flex items-center gap-2 min-w-[140px]">
                                        <!-- ONE-LETTER / COMPACT STATUS BADGE -->
                                        @if($status === 'present')
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-xs font-black text-white" title="Present">P</span>
                                        @elseif($status === 'half_day')
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-amber-500 text-xs font-black text-white" title="Half Day">½</span>
                                        @elseif($status === 'leave')
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-cyan-600 text-xs font-black text-white" title="Leave">L</span>
                                        @elseif($status === 'absent')
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-rose-600 text-xs font-black text-white" title="Absent">A</span>
                                        @else
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-slate-200 text-xs font-black text-slate-500" title="Not Marked">—</span>
                                        @endif

                                        <div class="min-w-0">
                                            <p class="text-xs font-extrabold text-slate-900">
                                                {{ $att?->marked_at ? $att->marked_at->timezone('Asia/Kolkata')->format('g:i A') : ($status ? 'Marked' : 'Not Marked') }}
                                            </p>
                                            @if($att?->notes)
                                                <p class="text-[10px] font-semibold text-slate-500 truncate max-w-[180px]" title="{{ $att->notes }}">
                                                    {{ $att->notes }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex items-center gap-1.5">
                                        <button type="button" 
                                                class="js-open-details-modal rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-100 cursor-pointer"
                                                data-employee-id="{{ $emp->id }}"
                                                data-employee-code="{{ $emp->employee_code }}"
                                                data-employee-name="{{ e($emp->name) }}"
                                                data-employee-category="{{ e($emp->category?->name ?? '') }}"
                                                data-employee-photo="{{ $emp->photo_url }}"
                                                data-employee-phone="{{ $emp->phone }}"
                                                data-employee-emergency="{{ $emp->alternate_phone }}"
                                                data-shop-name="{{ e($group['shop_name']) }}"
                                                data-status="{{ $status ? match($status) { 'present' => 'P (Present)', 'half_day' => '½ (Half Day)', 'leave' => 'L (Leave)', 'absent' => 'A (Absent)', default => ucfirst($status) } : 'Not Marked' }}"
                                                data-marked-at="{{ $att?->marked_at ? $att->marked_at->timezone('Asia/Kolkata')->format('g:i A') : '—' }}"
                                                data-marked-by="{{ e($att?->markedBy?->name ?? '—') }}"
                                                data-source="{{ ucfirst($att?->source ?? 'admin') }}"
                                                data-notes="{{ e($att?->notes ?? '') }}"
                                                data-calendar-url="{{ route('admin.staff.assignments.show', $emp) }}">
                                            Details
                                        </button>

                                        <button type="button" 
                                                class="js-open-attendance-modal rounded-lg px-2.5 py-1 text-[11px] font-bold text-white transition cursor-pointer {{ $status ? 'bg-slate-900 hover:bg-slate-800' : 'bg-emerald-600 hover:bg-emerald-700' }}"
                                                data-employee-id="{{ $emp->id }}"
                                                data-employee-name="{{ e($emp->name) }}"
                                                data-shop-id="{{ $group['shop_id'] ?? '' }}"
                                                data-status="{{ $status ?? 'present' }}"
                                                data-notes="{{ e($att?->notes ?? '') }}">
                                            {{ $status ? 'Edit' : '+ Add' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center text-xs font-semibold text-slate-400">
                    No active employees match the selected filters on {{ $selectedDate->format('d M Y') }}.
                </div>
            @endforelse
        </div>

        <!-- REUSED ADD / EDIT ATTENDANCE MODAL -->
        <div id="admin-attendance-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div id="admin-attendance-modal-backdrop" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl border border-slate-200 space-y-4 z-10">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 uppercase">Mark Attendance</h3>
                        <p id="attendance-modal-subtitle" class="text-xs font-semibold text-slate-400">Record employee attendance</p>
                    </div>
                    <button type="button" id="btn-close-attendance-modal" class="text-slate-400 hover:text-slate-700 text-sm font-bold cursor-pointer">✕</button>
                </div>

                <form method="POST" action="{{ route('admin.staff.attendance.store') }}" class="space-y-3">
                    @csrf

                    <!-- Employee Select -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Employee *</label>
                        <select id="attendance-modal-employee-id" name="employee_id" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                            <option value="">-- Select Employee --</option>
                            @foreach($allActiveEmployees as $empOpt)
                                <option value="{{ $empOpt->id }}">{{ $empOpt->name }} ({{ $empOpt->employee_code }}) {{ $empOpt->defaultShop ? '· '.$empOpt->defaultShop->name : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Attendance Date -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Date *</label>
                        <input type="date" name="attendance_date" value="{{ $selectedDate->format('Y-m-d') }}" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                    </div>

                    <!-- Work Location / Shop -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Assigned / Worked Shop</label>
                        <select id="attendance-modal-shop-id" name="shop_id" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-semibold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
                            <option value="">Office / Default Assignment</option>
                            @foreach($shops as $shopOpt)
                                <option value="{{ $shopOpt->id }}">{{ $shopOpt->name }} ({{ $shopOpt->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Status Radio Buttons -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1.5">Status *</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-2 text-xs font-bold text-slate-800 cursor-pointer hover:bg-emerald-50/50">
                                <input type="radio" name="status" value="present" class="accent-emerald-600" checked>
                                <span>P — Present</span>
                            </label>
                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-2 text-xs font-bold text-slate-800 cursor-pointer hover:bg-amber-50/50">
                                <input type="radio" name="status" value="half_day" class="accent-amber-600">
                                <span>½ — Half Day</span>
                            </label>
                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-2 text-xs font-bold text-slate-800 cursor-pointer hover:bg-cyan-50/50">
                                <input type="radio" name="status" value="leave" class="accent-cyan-600">
                                <span>L — Leave</span>
                            </label>
                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-2 text-xs font-bold text-slate-800 cursor-pointer hover:bg-rose-50/50">
                                <input type="radio" name="status" value="absent" class="accent-rose-600">
                                <span>A — Absent</span>
                            </label>
                        </div>
                    </div>

                    <!-- Reason / Notes -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Reason / Note <span class="text-rose-500 font-normal">(Required for Half Day, Leave, Absent)</span></label>
                        <input type="text" id="attendance-modal-notes" name="notes" placeholder="e.g. Personal leave / Sick / Medical" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-semibold text-slate-900">
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" id="btn-cancel-attendance-modal" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 cursor-pointer">Cancel</button>
                        <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2 text-xs font-black text-white hover:bg-emerald-700 cursor-pointer">Save Attendance</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- EMPLOYEE DETAILS MODAL -->
        <div id="admin-attendance-details-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div id="admin-attendance-details-backdrop" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl border border-slate-200 space-y-4 z-10">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-black text-slate-900 uppercase">Attendance & Employee Details</h3>
                    <button type="button" id="btn-close-details-modal" class="text-slate-400 hover:text-slate-700 text-sm font-bold cursor-pointer">✕</button>
                </div>

                <!-- PROFILE HEADER -->
                <div class="flex items-center gap-3">
                    <div id="details-modal-avatar-container" class="shrink-0">
                        <div id="details-modal-initials" class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-900 text-sm font-bold text-white"></div>
                        <img id="details-modal-photo" src="" class="hidden h-12 w-12 rounded-full object-cover border border-slate-200" alt="">
                    </div>
                    <div class="min-w-0">
                        <h4 id="details-modal-name" class="text-base font-black text-slate-950 truncate"></h4>
                        <p id="details-modal-meta" class="text-xs font-semibold text-slate-400"></p>
                    </div>
                </div>

                <!-- DETAILS GRID -->
                <div class="space-y-2 text-xs">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Assigned Work Location for Date</p>
                        <p id="details-modal-shop" class="font-black text-slate-900">—</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Status</p>
                            <p id="details-modal-status" class="font-bold text-slate-900">—</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Marked Time</p>
                            <p id="details-modal-marked-at" class="font-bold text-slate-900">—</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Primary Phone</p>
                            <p id="details-modal-phone" class="font-semibold text-slate-800">—</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Emergency Contact</p>
                            <p id="details-modal-emergency" class="font-semibold text-slate-800">—</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Marked By & Source</p>
                        <p id="details-modal-marked-by" class="font-semibold text-slate-800">—</p>
                    </div>

                    <div id="details-modal-notes-container" class="hidden rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Reason / Note</p>
                        <p id="details-modal-notes" class="font-semibold text-slate-700 whitespace-pre-line"></p>
                    </div>
                </div>

                <!-- FOOTER ACTIONS -->
                <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                    <a id="details-modal-calendar-link" href="#" class="rounded-xl border border-slate-200 px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100">
                        View Employee Calendar
                    </a>
                    <button type="button" id="btn-cancel-details-modal" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white cursor-pointer">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PLAIN JAVASCRIPT MODAL EVENT HANDLERS -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Attendance Modal Elements
            const attModal = document.getElementById('admin-attendance-modal');
            const attBackdrop = document.getElementById('admin-attendance-modal-backdrop');
            const attCloseBtn = document.getElementById('btn-close-attendance-modal');
            const attCancelBtn = document.getElementById('btn-cancel-attendance-modal');
            const attSubtitle = document.getElementById('attendance-modal-subtitle');
            const attEmployeeSelect = document.getElementById('attendance-modal-employee-id');
            const attShopSelect = document.getElementById('attendance-modal-shop-id');
            const attNotesInput = document.getElementById('attendance-modal-notes');

            function openAttendanceModal(empId, empName, shopId, status, notes) {
                if (!attModal) return;

                if (attEmployeeSelect && empId) {
                    attEmployeeSelect.value = empId;
                } else if (attEmployeeSelect) {
                    attEmployeeSelect.value = '';
                }

                if (attShopSelect && shopId) {
                    attShopSelect.value = shopId;
                } else if (attShopSelect) {
                    attShopSelect.value = '';
                }

                if (attNotesInput) {
                    attNotesInput.value = notes || '';
                }

                const radioStatus = attModal.querySelector('input[name="status"][value="' + (status || 'present') + '"]');
                if (radioStatus) {
                    radioStatus.checked = true;
                }

                if (attSubtitle) {
                    attSubtitle.textContent = empName ? 'Mark attendance for ' + empName : 'Record employee attendance';
                }

                attModal.classList.remove('hidden');
            }

            function closeAttendanceModal() {
                if (attModal) attModal.classList.add('hidden');
            }

            document.querySelectorAll('.js-open-attendance-modal').forEach(function (button) {
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const empId = button.getAttribute('data-employee-id') || '';
                    const empName = button.getAttribute('data-employee-name') || '';
                    const shopId = button.getAttribute('data-shop-id') || '';
                    const status = button.getAttribute('data-status') || 'present';
                    const notes = button.getAttribute('data-notes') || '';

                    openAttendanceModal(empId, empName, shopId, status, notes);
                });
            });

            if (attCloseBtn) attCloseBtn.addEventListener('click', closeAttendanceModal);
            if (attCancelBtn) attCancelBtn.addEventListener('click', closeAttendanceModal);
            if (attBackdrop) attBackdrop.addEventListener('click', closeAttendanceModal);

            // Details Modal Elements
            const detModal = document.getElementById('admin-attendance-details-modal');
            const detBackdrop = document.getElementById('admin-attendance-details-backdrop');
            const detCloseBtn = document.getElementById('btn-close-details-modal');
            const detCancelBtn = document.getElementById('btn-cancel-details-modal');

            const elPhoto = document.getElementById('details-modal-photo');
            const elInitials = document.getElementById('details-modal-initials');
            const elName = document.getElementById('details-modal-name');
            const elMeta = document.getElementById('details-modal-meta');
            const elShop = document.getElementById('details-modal-shop');
            const elStatus = document.getElementById('details-modal-status');
            const elMarkedAt = document.getElementById('details-modal-marked-at');
            const elPhone = document.getElementById('details-modal-phone');
            const elEmergency = document.getElementById('details-modal-emergency');
            const elMarkedBy = document.getElementById('details-modal-marked-by');
            const elNotesContainer = document.getElementById('details-modal-notes-container');
            const elNotes = document.getElementById('details-modal-notes');
            const elCalendarLink = document.getElementById('details-modal-calendar-link');

            document.querySelectorAll('.js-open-details-modal').forEach(function (button) {
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const name = button.getAttribute('data-employee-name') || '';
                    const code = button.getAttribute('data-employee-code') || '';
                    const category = button.getAttribute('data-employee-category') || '';
                    const photo = button.getAttribute('data-employee-photo') || '';
                    const phone = button.getAttribute('data-employee-phone') || 'N/A';
                    const emergency = button.getAttribute('data-employee-emergency') || 'N/A';
                    const shop = button.getAttribute('data-shop-name') || '—';
                    const status = button.getAttribute('data-status') || '—';
                    const markedAt = button.getAttribute('data-marked-at') || '—';
                    const markedBy = button.getAttribute('data-marked-by') || '—';
                    const source = button.getAttribute('data-source') || 'admin';
                    const notes = button.getAttribute('data-notes') || '';
                    const calendarUrl = button.getAttribute('data-calendar-url') || '#';

                    if (elName) elName.textContent = name;
                    if (elMeta) elMeta.textContent = code + (category ? ' · ' + category : '');
                    if (elShop) elShop.textContent = shop;
                    if (elStatus) elStatus.textContent = status;
                    if (elMarkedAt) elMarkedAt.textContent = markedAt;
                    if (elPhone) elPhone.textContent = phone;
                    if (elEmergency) elEmergency.textContent = emergency;
                    if (elMarkedBy) elMarkedBy.textContent = markedBy + ' (Source: ' + source + ')';

                    if (photo && elPhoto && elInitials) {
                        elPhoto.src = photo;
                        elPhoto.classList.remove('hidden');
                        elInitials.classList.add('hidden');
                    } else if (elPhoto && elInitials) {
                        elInitials.textContent = (name.substr(0, 2) || 'EM').toUpperCase();
                        elInitials.classList.remove('hidden');
                        elPhoto.classList.add('hidden');
                    }

                    if (notes && elNotesContainer && elNotes) {
                        elNotes.textContent = notes;
                        elNotesContainer.classList.remove('hidden');
                    } else if (elNotesContainer) {
                        elNotesContainer.classList.add('hidden');
                    }

                    if (elCalendarLink) elCalendarLink.href = calendarUrl;

                    if (detModal) detModal.classList.remove('hidden');
                });
            });

            function closeDetailsModal() {
                if (detModal) detModal.classList.add('hidden');
            }

            if (detCloseBtn) detCloseBtn.addEventListener('click', closeDetailsModal);
            if (detCancelBtn) detCancelBtn.addEventListener('click', closeDetailsModal);
            if (detBackdrop) detBackdrop.addEventListener('click', closeDetailsModal);

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeAttendanceModal();
                    closeDetailsModal();
                }
            });
        });
    </script>
</x-layouts.staff>
