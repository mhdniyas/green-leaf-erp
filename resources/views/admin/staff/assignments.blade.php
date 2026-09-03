<x-layouts.staff title="Staff Allocations & Assignments">
    <div class="mx-auto max-w-7xl space-y-6">
        <!-- PAGE HEADER -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Staff Allocations</h1>
                <p class="text-sm font-semibold text-slate-500">Overview of client shop placements, unallocated staff, and shop assignments.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" id="btn-assign-header" 
                        class="js-open-assignment-modal inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white hover:bg-emerald-700 shadow-xs cursor-pointer"
                        data-employee-id="" data-employee-name="" data-shop-id="">
                    <span>+</span> Assign Staff
                </button>
            </div>
        </div>

        <!-- TOP COMPACT SUMMARY CARDS -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Total Staff</p>
                <p class="mt-1 text-2xl font-black text-slate-950">{{ $totalStaffCount }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Allocated</p>
                <p class="mt-1 text-2xl font-black text-emerald-700">{{ $allocatedCount }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Unallocated</p>
                <p class="mt-1 text-2xl font-black text-amber-700">{{ $unallocatedCount }}</p>
            </div>
            <a href="{{ route('admin.staff.approvals.index') }}" class="block rounded-2xl border border-slate-200 bg-white p-4 shadow-xs hover:border-slate-300 transition">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Pending HR</p>
                <p class="mt-1 text-2xl font-black text-cyan-700">{{ $pendingCount }}</p>
            </a>
        </div>

        <!-- DYNAMIC CATEGORY TABS -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 no-scrollbar">
            <a href="{{ route('admin.staff.assignments.index', array_merge(request()->query(), ['category' => 'all'])) }}"
               class="rounded-xl px-3.5 py-2 text-xs font-bold whitespace-nowrap transition cursor-pointer {{ $categoryCode === 'all' ? 'bg-slate-950 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                All ({{ $totalStaffCount }})
            </a>
            @foreach($categoryTabs as $tab)
                <a href="{{ route('admin.staff.assignments.index', array_merge(request()->query(), ['category' => $tab['code']])) }}"
                   class="rounded-xl px-3.5 py-2 text-xs font-bold whitespace-nowrap transition cursor-pointer {{ $categoryCode === $tab['code'] ? 'bg-slate-950 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">
                    {{ $tab['name'] }} ({{ $tab['count'] }})
                </a>
            @endforeach
        </div>

        <!-- FILTERS & SEARCH ROW -->
        <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs space-y-3">
            <form method="GET" action="{{ route('admin.staff.assignments.index') }}" class="flex flex-wrap items-center justify-between gap-3">
                <input type="hidden" name="category" value="{{ $categoryCode }}">

                <!-- Allocation Toggle -->
                <div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1">
                    <a href="{{ route('admin.staff.assignments.index', array_merge(request()->query(), ['allocation' => 'all'])) }}" 
                       class="rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $allocationFilter === 'all' ? 'bg-white text-slate-950 shadow-xs' : 'text-slate-500 hover:text-slate-950' }}">
                        All Staff
                    </a>
                    <a href="{{ route('admin.staff.assignments.index', array_merge(request()->query(), ['allocation' => 'allocated'])) }}" 
                       class="rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $allocationFilter === 'allocated' ? 'bg-white text-emerald-800 shadow-xs' : 'text-slate-500 hover:text-slate-950' }}">
                        Allocated
                    </a>
                    <a href="{{ route('admin.staff.assignments.index', array_merge(request()->query(), ['allocation' => 'unallocated'])) }}" 
                       class="rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $allocationFilter === 'unallocated' ? 'bg-white text-amber-800 shadow-xs' : 'text-slate-500 hover:text-slate-950' }}">
                        Unallocated
                    </a>
                </div>

                <!-- Search & Date/Shop Filter -->
                <div class="flex flex-wrap items-center gap-2">
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search name, code, phone..." class="h-9 w-48 sm:w-64 rounded-xl border border-slate-200 px-3 text-xs font-semibold focus:border-emerald-600 focus:ring-emerald-600">

                    <!-- Optional Date + Shop View Filter -->
                    <select name="shop_id" class="h-9 rounded-xl border border-slate-200 px-3 text-xs font-semibold" onchange="this.form.submit()">
                        <option value="">-- Shop Filter --</option>
                        @foreach($shops as $s)
                            <option value="{{ $s->id }}" @selected($selectedFilterShop?->id === $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>

                    @if($selectedFilterShop)
                        <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="h-9 rounded-xl border border-slate-200 px-2 text-xs font-semibold" onchange="this.form.submit()">
                    @endif

                    <button type="submit" class="h-9 rounded-xl bg-slate-950 px-4 text-xs font-bold text-white hover:bg-slate-800 cursor-pointer">Filter</button>
                    @if($search || $allocationFilter !== 'all' || $categoryCode !== 'all' || $selectedFilterShop)
                        <a href="{{ route('admin.staff.assignments.index') }}" class="h-9 rounded-xl border border-slate-200 px-3 flex items-center text-xs font-bold text-slate-600 hover:bg-slate-50">Reset</a>
                    @endif
                </div>
            </form>

            <!-- DATE + SHOP VIEW PANEL (If Date & Shop selected) -->
            @if($selectedFilterShop)
                <div class="rounded-xl border border-cyan-200 bg-cyan-50/60 p-3 space-y-2">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-black uppercase text-cyan-950">
                            Staff Working at {{ $selectedFilterShop->name }} on {{ $selectedDate->format('d M Y') }} ({{ $dateShopStaff->count() }})
                        </h3>
                        <a href="{{ route('admin.staff.assignments.index') }}" class="text-[10px] font-extrabold text-cyan-800 hover:underline">✕ Close Shop View</a>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                        @forelse($dateShopStaff as $att)
                            <div class="rounded-lg border border-white bg-white p-2.5 shadow-2xs flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-xs font-black text-slate-900 truncate">{{ $att->employee?->name }}</p>
                                    <p class="text-[10px] font-semibold text-slate-400">{{ $att->employee?->employee_code }} · {{ $att->employee?->category?->name ?? 'Staff' }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="rounded px-1.5 py-0.5 text-[9px] font-black uppercase border inline-block {{ $att->status === 'present' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-100 text-slate-700' }}">
                                        {{ $att->status === 'present' ? '✓ Present' : str_replace('_', ' ', ucfirst($att->status)) }}
                                    </span>
                                    @if($att->marked_at)
                                        <p class="text-[9px] font-extrabold text-slate-500 mt-0.5">{{ $att->marked_at->timezone('Asia/Kolkata')->format('g:i A') }}</p>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-xs font-semibold text-slate-500 py-2 col-span-full">No attendance records recorded for this shop on {{ $selectedDate->format('d M Y') }}.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>

        <!-- STAFF DIRECTORY TABLE -->
        <section class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
            <div class="divide-y divide-slate-100">
                @forelse($employees as $emp)
                    @php($isAllocated = $emp->defaultShop !== null)
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3.5 gap-3 hover:bg-slate-50/80 transition">
                        <div class="flex items-center gap-3 min-w-0">
                            @if($emp->photo_url)
                                <img src="{{ $emp->photo_url }}" class="h-10 w-10 rounded-full object-cover border border-slate-200 shrink-0" alt="{{ $emp->name }}">
                            @else
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-900 font-bold text-white text-xs shrink-0">
                                    {{ Illuminate\Support\Str::upper(substr($emp->name, 0, 2)) }}
                                </div>
                            @endif
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <a href="{{ route('admin.staff.assignments.show', $emp) }}" class="text-sm font-black text-slate-950 hover:text-emerald-700 hover:underline truncate">
                                        {{ $emp->name }}
                                    </a>
                                    <span class="rounded px-2 py-0.5 text-[9px] font-black uppercase tracking-wider border {{ $isAllocated ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
                                        {{ $isAllocated ? 'Allocated' : 'Unallocated' }}
                                    </span>
                                </div>
                                <p class="text-xs font-semibold text-slate-400 truncate mt-0.5">
                                    {{ $emp->employee_code }} · {{ $emp->category?->name ?? 'Unassigned Category' }} · Primary: {{ $emp->phone ?: 'N/A' }} · Emergency: {{ $emp->alternate_phone ?: 'N/A' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                            <div class="text-left sm:text-right">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Current Shop</p>
                                <p class="text-xs font-black text-slate-900">{{ $emp->defaultShop?->name ?? '—' }}</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.staff.assignments.show', $emp) }}" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100">
                                    View
                                </a>

                                <button type="button" 
                                        class="js-open-assignment-modal rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800 cursor-pointer"
                                        data-employee-id="{{ $emp->id }}" 
                                        data-employee-name="{{ e($emp->name) }}" 
                                        data-shop-id="{{ $emp->default_shop_id ?? '' }}">
                                    {{ $isAllocated ? 'Manage Assignment' : 'Assign' }}
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-12 text-center text-xs font-semibold text-slate-400">
                        No employees found matching the specified filters.
                    </div>
                @endforelse
            </div>

            @if($employees->hasPages())
                <div class="border-t border-slate-100 p-3 bg-slate-50/50">
                    {{ $employees->links() }}
                </div>
            @endif
        </section>

        <!-- ASSIGNMENT MODAL (REUSING CANONICAL ROUTE admin.staff.shop-assignments.store) -->
        <div id="admin-assignment-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div id="admin-assignment-modal-backdrop" class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl border border-slate-200 space-y-4 z-10">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 uppercase">Shop Assignment</h3>
                        <p id="assignment-modal-subtitle" class="text-xs font-semibold text-slate-400">Assign employee to shop</p>
                    </div>
                    <button type="button" id="btn-close-assignment-modal" class="text-slate-400 hover:text-slate-700 text-sm font-bold cursor-pointer">✕</button>
                </div>

                <form method="POST" action="{{ route('admin.staff.shop-assignments.store') }}" class="space-y-3">
                    @csrf

                    <!-- Employee Select -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Employee *</label>
                        <select id="assignment-modal-employee-id" name="employee_id" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                            <option value="">-- Select Employee --</option>
                            @foreach($employeesForAssignment as $empOpt)
                                <option value="{{ $empOpt->id }}">{{ $empOpt->name }} ({{ $empOpt->employee_code }}) {{ $empOpt->defaultShop ? '· Current: '.$empOpt->defaultShop->name : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Target Shop Select -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Assign to Shop *</label>
                        <select id="assignment-modal-shop-id" name="shop_id" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                            <option value="">-- Select Target Shop --</option>
                            @foreach($shops as $shopOpt)
                                <option value="{{ $shopOpt->id }}">{{ $shopOpt->name }} ({{ $shopOpt->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Effective From Date -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Effective Date *</label>
                        <input type="date" name="effective_from" value="{{ today()->format('Y-m-d') }}" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-bold text-slate-900 focus:border-emerald-600 focus:ring-emerald-600" required>
                    </div>

                    <!-- Note / Remarks -->
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Notes / Remarks (optional)</label>
                        <input type="text" name="notes" placeholder="e.g. Temporary transfer / New placement" class="w-full rounded-xl border border-slate-200 p-2.5 text-xs font-semibold text-slate-900">
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" id="btn-cancel-assignment-modal" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 cursor-pointer">Cancel</button>
                        <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2 text-xs font-black text-white hover:bg-emerald-700 cursor-pointer">Save Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('admin-assignment-modal');
            const backdrop = document.getElementById('admin-assignment-modal-backdrop');
            const closeBtn = document.getElementById('btn-close-assignment-modal');
            const cancelBtn = document.getElementById('btn-cancel-assignment-modal');
            const subtitleEl = document.getElementById('assignment-modal-subtitle');
            const employeeSelect = document.getElementById('assignment-modal-employee-id');
            const shopSelect = document.getElementById('assignment-modal-shop-id');

            function openModal(employeeId, employeeName, shopId) {
                if (!modal) return;

                if (employeeSelect && employeeId) {
                    employeeSelect.value = employeeId;
                } else if (employeeSelect) {
                    employeeSelect.value = '';
                }

                if (shopSelect && shopId) {
                    shopSelect.value = shopId;
                } else if (shopSelect) {
                    shopSelect.value = '';
                }

                if (subtitleEl) {
                    subtitleEl.textContent = employeeName ? 'Assign ' + employeeName : 'Assign employee to shop';
                }

                modal.classList.remove('hidden');
            }

            function closeModal() {
                if (modal) {
                    modal.classList.add('hidden');
                }
            }

            document.querySelectorAll('.js-open-assignment-modal').forEach(function (button) {
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const empId = button.getAttribute('data-employee-id') || '';
                    const empName = button.getAttribute('data-employee-name') || '';
                    const shopId = button.getAttribute('data-shop-id') || '';

                    openModal(empId, empName, shopId);
                });
            });

            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
            if (backdrop) backdrop.addEventListener('click', closeModal);

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        });
    </script>
</x-layouts.staff>
