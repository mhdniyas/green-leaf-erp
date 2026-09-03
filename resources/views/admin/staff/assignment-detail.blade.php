<x-layouts.staff title="Employee Details - {{ $employee->name }}">
    <div class="mx-auto max-w-7xl space-y-6">
        <!-- BACK LINK & TOP BAR -->
        <div class="flex items-center justify-between">
            <a href="{{ route('admin.staff.assignments.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-slate-950">
                <span>←</span> Back to Staff Allocations
            </a>
            <button type="button" id="btn-open-manage-modal" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-800 cursor-pointer">
                Manage Assignment
            </button>
        </div>

        <!-- EMPLOYEE HEADER CARD -->
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    @if($employee->photo_url)
                        <img src="{{ $employee->photo_url }}" class="h-16 w-16 rounded-2xl object-cover border border-slate-200 shrink-0" alt="{{ $employee->name }}">
                    @else
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-900 text-xl font-black text-white shrink-0">
                            {{ Illuminate\Support\Str::upper(substr($employee->name, 0, 2)) }}
                        </div>
                    @endif

                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="text-xl font-black text-slate-950">{{ $employee->name }}</h1>
                            <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-black uppercase text-emerald-800">
                                {{ $employee->employment_status }}
                            </span>
                        </div>
                        <p class="text-xs font-bold text-slate-400 mt-0.5">
                            {{ $employee->employee_code }} · {{ $employee->category?->name ?? 'No Category' }} · Joined {{ $employee->joined_on?->format('d M Y') ?? 'N/A' }}
                        </p>
                    </div>
                </div>

                <!-- DETAILS GRID -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 border-t md:border-t-0 border-slate-100 pt-3 md:pt-0">
                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-3">
                        <p class="text-[10px] font-black uppercase text-slate-400">Current Shop</p>
                        <p class="mt-1 text-xs font-black text-slate-900 truncate">{{ $employee->defaultShop?->name ?? 'Unallocated' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-3">
                        <p class="text-[10px] font-black uppercase text-slate-400">Primary Phone</p>
                        <p class="mt-1 text-xs font-bold text-slate-900 truncate">{{ $employee->phone ?: 'N/A' }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-3">
                        <p class="text-[10px] font-black uppercase text-slate-400">Emergency Contact</p>
                        <p class="mt-1 text-xs font-bold text-slate-900 truncate">{{ $employee->alternate_phone ?: 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- MONTH CALENDAR SECTION -->
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-black uppercase tracking-wider text-slate-900">Attendance & Placement Calendar</h2>

                <!-- MONTH NAV CONTROLS -->
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.staff.assignments.show', [$employee, 'month' => $prevMonth->format('Y-m')]) }}" 
                       class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        ← {{ $prevMonth->format('M Y') }}
                    </a>
                    <span class="text-xs font-black text-slate-950 px-2">{{ $selectedMonth->format('F Y') }}</span>
                    <a href="{{ route('admin.staff.assignments.show', [$employee, 'month' => $nextMonth->format('Y-m')]) }}" 
                       class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        {{ $nextMonth->format('M Y') }} →
                    </a>
                </div>
            </div>

            <!-- CALENDAR GRID -->
            <div class="grid grid-cols-7 gap-1.5 text-center">
                <!-- DAYS HEADER -->
                @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
                    <div class="py-2 text-[11px] font-black uppercase text-slate-400">{{ $dayName }}</div>
                @endforeach

                <!-- OFFSET BLANK DAYS BEFORE START OF MONTH -->
                @php($startDayOfWeek = ($selectedMonth->dayOfWeekIso - 1))
                @for($i = 0; $i < $startDayOfWeek; $i++)
                    <div class="min-h-16 rounded-xl border border-slate-50 bg-slate-50/50"></div>
                @endfor

                <!-- DAY CELLS -->
                @foreach($calendarDays as $day)
                    @php($att = $day['attendance'])
                    @php($status = $att?->status)
                    @php($shopName = $day['assigned_shop']?->name ?? 'Default')

                    <button type="button" 
                            class="js-calendar-day-btn min-h-16 rounded-2xl border border-slate-200 p-2 text-left transition hover:border-emerald-500 hover:bg-emerald-50/30 flex flex-col justify-between cursor-pointer"
                            data-date="{{ $day['date']->format('d F Y') }}"
                            data-shop="{{ e($shopName) }}"
                            data-status="{{ $status ? ($status === 'present' ? '✓ Present' : str_replace('_', ' ', ucfirst($status))) : 'Not Marked' }}"
                            data-marked-at="{{ $att?->marked_at ? $att->marked_at->timezone('Asia/Kolkata')->format('g:i A') : '—' }}"
                            data-marked-by="{{ e($att?->markedBy?->name ?? 'System') }}"
                            data-notes="{{ e($att?->notes ?? '') }}">
                        <div class="flex items-center justify-between w-full">
                            <span class="text-xs font-black text-slate-900">{{ $day['day_number'] }}</span>
                            @if($status)
                                <span class="rounded px-1.5 py-0.5 text-[9px] font-black uppercase border {{ match($status) { 'present' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'half_day' => 'border-amber-200 bg-amber-50 text-amber-800', 'leave' => 'border-cyan-200 bg-cyan-50 text-cyan-800', 'absent' => 'border-rose-200 bg-rose-50 text-rose-800', default => 'border-slate-200 bg-slate-100 text-slate-600' } }}">
                                    {{ match($status) { 'present' => 'P', 'half_day' => '½', 'leave' => 'L', 'absent' => 'A', default => '?' } }}
                                </span>
                            @endif
                        </div>
                        <p class="text-[9px] font-bold text-slate-400 truncate mt-1" title="{{ $shopName }}">{{ $shopName }}</p>
                    </button>
                @endforeach
            </div>
        </section>

        <!-- DAY DETAILS MODAL -->
        <div id="calendar-day-details-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div id="calendar-day-details-backdrop" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl border border-slate-200 space-y-3 z-10">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <h3 id="day-modal-date" class="text-xs font-black text-slate-900 uppercase">Selected Date</h3>
                    <button type="button" id="btn-close-day-modal" class="text-slate-400 hover:text-slate-700 text-xs font-bold cursor-pointer">✕</button>
                </div>

                <div class="space-y-2 text-xs">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Assigned Shop</p>
                        <p id="day-modal-shop" class="font-black text-slate-900">—</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Attendance</p>
                            <p id="day-modal-status" class="font-bold text-slate-900">—</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Marked Time</p>
                            <p id="day-modal-marked-at" class="font-bold text-slate-900">—</p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Marked By</p>
                        <p id="day-modal-marked-by" class="font-semibold text-slate-700">—</p>
                    </div>

                    <div id="day-modal-notes-container" class="hidden rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Reason / Note</p>
                        <p id="day-modal-notes" class="font-semibold text-slate-700 whitespace-pre-line"></p>
                    </div>
                </div>

                <div class="pt-2 text-right">
                    <button type="button" id="btn-cancel-day-modal" class="rounded-xl bg-slate-900 px-4 py-1.5 text-xs font-bold text-white cursor-pointer">Close</button>
                </div>
            </div>
        </div>

        <!-- REUSED ASSIGNMENT MODAL -->
        <div id="detail-assignment-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div id="detail-assignment-modal-backdrop" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl border border-slate-200 space-y-4 z-10">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 uppercase">Manage Shop Placement</h3>
                        <p class="text-xs font-semibold text-slate-400">Reassign {{ $employee->name }} to a client shop</p>
                    </div>
                    <button type="button" id="btn-close-detail-assign-modal" class="text-slate-400 hover:text-slate-700 text-sm font-bold cursor-pointer">✕</button>
                </div>

                <form method="POST" action="{{ route('admin.staff.shop-assignments.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Assign to Shop *</label>
                        <select name="shop_id" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                            <option value="">-- Select Target Shop --</option>
                            @foreach($shops as $shopOpt)
                                <option value="{{ $shopOpt->id }}" @selected($employee->default_shop_id === $shopOpt->id)>{{ $shopOpt->name }} ({{ $shopOpt->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Effective Date *</label>
                        <input type="date" name="effective_from" value="{{ today()->format('Y-m-d') }}" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Notes / Remarks (optional)</label>
                        <input type="text" name="notes" placeholder="e.g. Placement update" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-semibold text-slate-900">
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" id="btn-cancel-detail-assign-modal" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 cursor-pointer">Cancel</button>
                        <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2 text-xs font-black text-white hover:bg-emerald-700 cursor-pointer">Save Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Assignment Modal Elements
            const assignModal = document.getElementById('detail-assignment-modal');
            const assignBackdrop = document.getElementById('detail-assignment-modal-backdrop');
            const openAssignBtn = document.getElementById('btn-open-manage-modal');
            const closeAssignBtn = document.getElementById('btn-close-detail-assign-modal');
            const cancelAssignBtn = document.getElementById('btn-cancel-detail-assign-modal');

            if (openAssignBtn) {
                openAssignBtn.addEventListener('click', function () {
                    if (assignModal) assignModal.classList.remove('hidden');
                });
            }

            function closeAssignModal() {
                if (assignModal) assignModal.classList.add('hidden');
            }

            if (closeAssignBtn) closeAssignBtn.addEventListener('click', closeAssignModal);
            if (cancelAssignBtn) cancelAssignBtn.addEventListener('click', closeAssignModal);
            if (assignBackdrop) assignBackdrop.addEventListener('click', closeAssignModal);

            // Day Details Modal Elements
            const dayModal = document.getElementById('calendar-day-details-modal');
            const dayBackdrop = document.getElementById('calendar-day-details-backdrop');
            const closeDayBtn = document.getElementById('btn-close-day-modal');
            const cancelDayBtn = document.getElementById('btn-cancel-day-modal');
            const elDate = document.getElementById('day-modal-date');
            const elShop = document.getElementById('day-modal-shop');
            const elStatus = document.getElementById('day-modal-status');
            const elMarkedAt = document.getElementById('day-modal-marked-at');
            const elMarkedBy = document.getElementById('day-modal-marked-by');
            const elNotesContainer = document.getElementById('day-modal-notes-container');
            const elNotes = document.getElementById('day-modal-notes');

            document.querySelectorAll('.js-calendar-day-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (elDate) elDate.textContent = button.getAttribute('data-date') || 'Selected Date';
                    if (elShop) elShop.textContent = button.getAttribute('data-shop') || '—';
                    if (elStatus) elStatus.textContent = button.getAttribute('data-status') || '—';
                    if (elMarkedAt) elMarkedAt.textContent = button.getAttribute('data-marked-at') || '—';
                    if (elMarkedBy) elMarkedBy.textContent = button.getAttribute('data-marked-by') || '—';

                    const notes = button.getAttribute('data-notes') || '';
                    if (notes && elNotesContainer && elNotes) {
                        elNotes.textContent = notes;
                        elNotesContainer.classList.remove('hidden');
                    } else if (elNotesContainer) {
                        elNotesContainer.classList.add('hidden');
                    }

                    if (dayModal) dayModal.classList.remove('hidden');
                });
            });

            function closeDayModal() {
                if (dayModal) dayModal.classList.add('hidden');
            }

            if (closeDayBtn) closeDayBtn.addEventListener('click', closeDayModal);
            if (cancelDayBtn) cancelDayBtn.addEventListener('click', closeDayModal);
            if (dayBackdrop) dayBackdrop.addEventListener('click', closeDayModal);
        });
    </script>
</x-layouts.staff>
