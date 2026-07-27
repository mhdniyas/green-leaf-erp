<x-layouts.staff title="Staff Employees">
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Employees</h1>
                <p class="text-sm font-semibold text-slate-500">CRUD all staff records and switch quickly between category tabs.</p>
            </div>
            <div class="flex flex-wrap items-start gap-3">
                <a href="{{ route('admin.staff.assignments.index', ['date' => $selectedDate->format('Y-m-d')]) }}" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white">Assign Employees</a>
                <details class="max-w-md rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <summary class="cursor-pointer list-none text-sm font-black text-slate-700">What Re-Sync Linked Users does</summary>
                    <div class="mt-3 space-y-2 text-sm font-semibold text-slate-500">
                        <p>Checks all login users and makes sure each one has a linked staff record.</p>
                        <p>Refreshes linked staff details like category, staff area, name, email, and user connection using the current role mapping.</p>
                        <p>Keeps attendance, leave, and payroll history. Custom salary is preserved if HR already changed it manually.</p>
                        <p>Use this after role changes, seed updates, or when linked staff records need to be realigned.</p>
                    </div>
                </details>
                <form method="POST" action="{{ route('admin.staff.sync-users') }}">
                    @csrf
                    <button type="submit" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700">Re-Sync Linked Users</button>
                </form>
            </div>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap gap-2">
                @php
                    $selectedCategoryCode = $selectedCategory?->code;
                @endphp
                <a href="{{ route('admin.staff.employees.index', request()->except('category', 'page')) }}" class="rounded-full px-4 py-2 text-sm font-black {{ $selectedCategoryCode === null ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700' }}">
                    All Categories ({{ $employees->total() }})
                </a>
                @foreach($categoryTabs as $tab)
                    <a href="{{ route('admin.staff.employees.index', array_merge(request()->except('page'), ['category' => $tab['code']])) }}" class="rounded-full px-4 py-2 text-sm font-black {{ $selectedCategoryCode === $tab['code'] ? 'bg-cyan-500 text-slate-950' : 'bg-slate-100 text-slate-700' }}">
                        {{ $tab['name'] }} ({{ $tab['count'] }})
                    </a>
                @endforeach
            </div>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black text-slate-950">Staff CRUD</h2>
                    <p class="text-sm font-semibold text-slate-500">Search by name, code, phone, or email. Linked user roles and client shops are shown inline.</p>
                </div>
                <form method="GET" class="flex flex-wrap gap-2">
                    @if($selectedCategoryCode !== null)
                        <input type="hidden" name="category" value="{{ $selectedCategoryCode }}">
                    @endif
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search name, code, phone" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <input type="date" name="date" value="{{ $selectedDate->format('Y-m-d') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <select name="staff_area" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option value="">All Areas</option>
                        <option value="office" @selected(request('staff_area') === 'office')>Office</option>
                        <option value="shop" @selected(request('staff_area') === 'shop')>Shop</option>
                    </select>
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white">Filter</button>
                </form>
            </div>

            <div class="mt-5 overflow-x-auto">
                @php($employeeSerial = ($employees->currentPage() - 1) * $employees->perPage())
                <table class="min-w-full text-left text-sm">
                    <thead class="text-slate-500">
                            <tr>
                                <th class="pb-3">SL No</th>
                                <th class="pb-3">Name</th>
                                <th class="pb-3">Code</th>
                                <th class="pb-3">Area</th>
                                <th class="pb-3">Category</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3">Access</th>
                                <th class="pb-3">Salary</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        @foreach($employees as $employee)
                            <tr>
                                <td class="py-3 font-black text-slate-500">{{ $employeeSerial + $loop->iteration }}</td>
                                <td class="py-3">
                                    <a href="{{ route('admin.staff.show', $employee) }}" class="font-bold text-slate-900 underline-offset-4 hover:text-cyan-700 hover:underline">{{ $employee->name }}</a>
                                    <p class="text-xs font-semibold text-slate-500">{{ $employee->phone ?: ($employee->user?->email ?? 'No contact') }}</p>
                                    </td>
                                    <td class="py-3">{{ $employee->employee_code }}</td>
                                    <td class="py-3 capitalize">{{ $employee->staff_area }}</td>
                                    <td class="py-3">{{ $employee->category->name }}</td>
                                    <td class="py-3">
                                        <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-[0.12em] {{ $employee->employment_status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                                            {{ $employee->employment_status }}
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        @if($employee->user)
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($employee->user->roles as $role)
                                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700">{{ $role->name }}</span>
                                                @endforeach
                                            </div>
                                            @if($employee->user->ownedShopAssignments->isNotEmpty())
                                                <p class="mt-1 text-xs font-semibold text-slate-500">
                                                    Owns: {{ $employee->user->ownedShopAssignments->pluck('shop.name')->implode(', ') }}
                                                </p>
                                            @endif
                                        @else
                                            <span class="text-xs font-semibold text-slate-400">No linked user</span>
                                        @endif
                                    </td>
                                    <td class="py-3">
                                        <p class="font-bold text-slate-900">Rs. {{ number_format((float) $employee->monthly_salary, 2) }}</p>
                                        @if($employee->salary_type === 'daily_wage')
                                            <p class="text-xs font-semibold text-cyan-700">Daily Rs. {{ number_format((float) $employee->daily_wage, 2) }}</p>
                                        @else
                                            <p class="text-xs font-semibold text-slate-500">Monthly</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $employees->withQueryString()->links() }}</div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">Add Employee</h2>
            <form method="POST" action="{{ route('admin.staff.store') }}" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @csrf
                <input type="text" name="name" placeholder="Full name" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                <input type="text" name="employee_code" placeholder="Employee code" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                <select name="employee_category_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <option value="">Select category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected($selectedCategoryCode === $category->code)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select name="staff_area" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <option value="office">Office</option>
                    <option value="shop">Shop</option>
                </select>
                <select name="default_shop_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <option value="">Default shop (optional)</option>
                    @foreach($shops as $shop)
                        <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                    @endforeach
                </select>
                <select name="user_id" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <option value="">Linked user (optional)</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}{{ $user->roles->isNotEmpty() ? ' · '.$user->roles->pluck('name')->implode(', ') : '' }}</option>
                    @endforeach
                </select>
                <input type="number" step="0.01" name="monthly_salary" placeholder="Monthly salary" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                <select name="salary_type" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <option value="monthly">Monthly salary</option>
                    <option value="daily_wage">Daily wage</option>
                </select>
                <input type="number" step="0.01" name="daily_wage" placeholder="Daily wage" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <input type="text" name="phone" placeholder="Phone" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <input type="date" name="joined_on" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <input type="hidden" name="employment_status" value="active">
                <div class="xl:col-span-3">
                    <button type="submit" class="rounded-xl bg-cyan-500 px-4 py-2 text-sm font-black text-slate-950">Create Employee</button>
                </div>
            </form>
        </section>
    </div>
</x-layouts.staff>
