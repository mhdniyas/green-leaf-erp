<x-layouts.staff title="Assign Employees">
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Assign Employees</h1>
                <p class="text-sm font-semibold text-slate-500">Manage shop staff assignment from a shop-first view with incharge and daily attendance details.</p>
            </div>
            <form method="GET" class="flex flex-wrap gap-2">
                <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white">Refresh</button>
            </form>
        </div>

        <section class="grid gap-4 lg:grid-cols-2">
            @forelse($shops as $shop)
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-black text-slate-950">{{ $shop->name }}</h2>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $shop->code }} · {{ ucfirst((string) $shop->accounting_mode) }}</p>
                        </div>
                        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">
                            {{ $shop->assignedEmployees->count() }} assigned
                        </span>
                    </div>

                    <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-xs font-black uppercase text-slate-400">Incharge Details</p>
                        <div class="mt-2 space-y-2">
                            @forelse($shop->ownerAssignments as $ownerAssignment)
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-black text-slate-900">{{ $ownerAssignment->user?->name ?? 'Unknown incharge' }}</p>
                                        <p class="text-xs font-semibold text-slate-500">{{ $ownerAssignment->user?->email ?? 'No email' }}</p>
                                    </div>
                                    @if($ownerAssignment->user?->roles?->isNotEmpty())
                                        <span class="rounded-full bg-white px-3 py-1 text-[10px] font-black uppercase text-slate-500">
                                            {{ $ownerAssignment->user->roles->pluck('name')->implode(', ') }}
                                        </span>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm font-semibold text-rose-600">No incharge assigned to this shop.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-2xl border border-slate-100 p-3">
                            <p class="text-[10px] font-black uppercase text-slate-400">Present</p>
                            <p class="mt-1 text-xl font-black text-emerald-700">{{ $shop->today_present_count }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 p-3">
                            <p class="text-[10px] font-black uppercase text-slate-400">Half Day</p>
                            <p class="mt-1 text-xl font-black text-amber-700">{{ $shop->today_half_day_count }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 p-3">
                            <p class="text-[10px] font-black uppercase text-slate-400">Absent</p>
                            <p class="mt-1 text-xl font-black text-rose-700">{{ $shop->today_absent_count }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 p-3">
                            <p class="text-[10px] font-black uppercase text-slate-400">Leave</p>
                            <p class="mt-1 text-xl font-black text-sky-700">{{ $shop->today_leave_count }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.staff.shop-assignments.store') }}" class="mt-4 grid gap-3 rounded-2xl border border-slate-200 p-4 sm:grid-cols-2">
                        @csrf
                        <input type="hidden" name="shop_id" value="{{ $shop->id }}">
                        <div class="relative sm:col-span-2" data-employee-dropdown>
                            <input type="hidden" name="employee_id" data-employee-value>
                            <button type="button" data-employee-toggle class="flex min-h-12 w-full items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 text-left text-sm font-bold text-slate-700 shadow-sm transition hover:border-cyan-300 hover:bg-cyan-50/40">
                                <span data-employee-label>Select employee</span>
                                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </button>

                            <div data-employee-menu class="absolute left-0 right-0 top-full z-30 mt-2 hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                                <div class="border-b border-slate-100 p-3">
                                    <input type="search" data-employee-search placeholder="Search employee, code, shop" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-100">

                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1.5 text-xs font-black text-cyan-800">
                                            <input type="radio" name="employee_category_{{ $shop->id }}" value="shop" class="h-3.5 w-3.5 accent-cyan-600" data-employee-category checked>
                                            Shop Employees
                                        </label>
                                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-black text-slate-600">
                                            <input type="radio" name="employee_category_{{ $shop->id }}" value="office" class="h-3.5 w-3.5 accent-cyan-600" data-employee-category>
                                            Office
                                        </label>
                                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-black text-slate-600">
                                            <input type="radio" name="employee_category_{{ $shop->id }}" value="all" class="h-3.5 w-3.5 accent-cyan-600" data-employee-category>
                                            All
                                        </label>
                                    </div>
                                </div>

                                <div class="max-h-72 overflow-y-auto p-2" data-employee-options>
                                    @foreach($employeesForAssignment as $employee)
                                        @php
                                            $employeeLabel = $employee->name.' · '.$employee->employee_code.($employee->defaultShop ? ' · '.$employee->defaultShop->name : '');
                                        @endphp
                                        <button
                                            type="button"
                                            data-employee-option
                                            data-employee-id="{{ $employee->id }}"
                                            data-employee-label="{{ $employeeLabel }}"
                                            data-employee-area="{{ $employee->staff_area }}"
                                            data-employee-search-text="{{ \Illuminate\Support\Str::lower($employeeLabel.' '.$employee->staff_area.' '.($employee->category?->name ?? '')) }}"
                                            class="flex w-full items-start justify-between gap-3 rounded-xl px-3 py-2 text-left text-sm font-bold text-slate-700 transition hover:bg-cyan-50 hover:text-cyan-800"
                                        >
                                            <span>
                                                <span class="block">{{ $employee->name }}</span>
                                                <span class="mt-0.5 block text-xs font-semibold text-slate-500">
                                                    {{ $employee->employee_code }}{{ $employee->defaultShop ? ' · '.$employee->defaultShop->name : '' }}
                                                </span>
                                            </span>
                                            <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-500">{{ $employee->staff_area }}</span>
                                        </button>
                                    @endforeach

                                    <p data-employee-empty class="hidden px-3 py-6 text-center text-sm font-semibold text-slate-500">No employees match this search.</p>
                                </div>
                            </div>
                        </div>
                        <input type="date" name="effective_from" value="{{ $selectedDate->format('Y-m-d') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <input type="text" name="notes" placeholder="Note" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white sm:col-span-2">Assign to {{ $shop->name }}</button>
                    </form>

                    <div class="mt-4">
                        <p class="text-xs font-black uppercase text-slate-400">Current Employees</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse($shop->assignedEmployees as $employee)
                                <a href="{{ route('admin.staff.show', $employee) }}" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-black text-slate-700 hover:border-cyan-300 hover:text-cyan-700">
                                    {{ $employee->name }}
                                </a>
                            @empty
                                <span class="text-sm font-semibold text-slate-500">No employees assigned.</span>
                            @endforelse
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center">
                    <p class="text-lg font-black text-slate-950">No owned staff shops found.</p>
                    <p class="mt-2 text-sm font-semibold text-slate-500">Only active client shops with accounting enabled appear here.</p>
                </div>
            @endforelse
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black text-slate-950">Recent Active Assignments</h2>
                    <p class="text-sm font-semibold text-slate-500">Latest active shop placements across client shops.</p>
                </div>
                <a href="{{ route('admin.staff.employees.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700">Open Employees</a>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2">
                @forelse($activeAssignments as $assignment)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-black text-slate-950">{{ $assignment->employee?->name }}</p>
                                <p class="text-xs font-semibold text-slate-500">
                                    {{ $assignment->shop?->name }} · from {{ $assignment->effective_from?->format('d M Y') ?? 'not set' }}
                                </p>
                                <p class="mt-1 text-xs font-semibold text-slate-400">
                                    Assigned by {{ $assignment->assignedBy?->name ?? 'System' }}
                                </p>
                            </div>
                            <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase text-emerald-700">{{ $assignment->status }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm font-semibold text-slate-500">No active shop assignments yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    <script>
        (() => {
            const dropdowns = document.querySelectorAll('[data-employee-dropdown]');

            dropdowns.forEach((dropdown) => {
                const toggle = dropdown.querySelector('[data-employee-toggle]');
                const menu = dropdown.querySelector('[data-employee-menu]');
                const search = dropdown.querySelector('[data-employee-search]');
                const valueInput = dropdown.querySelector('[data-employee-value]');
                const label = dropdown.querySelector('[data-employee-label]');
                const options = Array.from(dropdown.querySelectorAll('[data-employee-option]'));
                const categories = Array.from(dropdown.querySelectorAll('[data-employee-category]'));
                const empty = dropdown.querySelector('[data-employee-empty]');

                const activeCategory = () => categories.find((category) => category.checked)?.value ?? 'shop';

                const setMenuOpen = (isOpen) => {
                    menu.classList.toggle('hidden', !isOpen);

                    if (isOpen) {
                        search.focus();
                    }
                };

                const filterOptions = () => {
                    const category = activeCategory();
                    const query = search.value.trim().toLowerCase();
                    let visibleCount = 0;

                    options.forEach((option) => {
                        const matchesCategory = category === 'all' || option.dataset.employeeArea === category;
                        const matchesSearch = query === '' || (option.dataset.employeeSearchText ?? '').includes(query);
                        const isVisible = matchesCategory && matchesSearch;

                        option.classList.toggle('hidden', !isVisible);

                        if (isVisible) {
                            visibleCount += 1;
                        }
                    });

                    empty.classList.toggle('hidden', visibleCount > 0);
                };

                toggle.addEventListener('click', () => {
                    setMenuOpen(menu.classList.contains('hidden'));
                    filterOptions();
                });

                search.addEventListener('input', filterOptions);
                categories.forEach((category) => category.addEventListener('change', filterOptions));

                options.forEach((option) => {
                    option.addEventListener('click', () => {
                        valueInput.value = option.dataset.employeeId ?? '';
                        label.textContent = option.dataset.employeeLabel ?? 'Select employee';
                        setMenuOpen(false);
                    });
                });

                dropdown.closest('form')?.addEventListener('submit', (event) => {
                    if (valueInput.value !== '') {
                        return;
                    }

                    event.preventDefault();
                    setMenuOpen(true);
                    filterOptions();
                    search.focus();
                });

                document.addEventListener('click', (event) => {
                    if (!dropdown.contains(event.target)) {
                        setMenuOpen(false);
                    }
                });

                filterOptions();
            });
        })();
    </script>
</x-layouts.staff>
