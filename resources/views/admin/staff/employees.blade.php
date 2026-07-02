<x-layouts.staff title="Staff Employees">
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-950">Employees</h1>
                <p class="text-sm font-semibold text-slate-500">CRUD all staff records and switch quickly between category tabs.</p>
            </div>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap gap-2">
                @php
                    $selectedCategoryId = request()->filled('employee_category_id') ? (int) request('employee_category_id') : null;
                @endphp
                <a href="{{ route('admin.staff.employees.index', request()->except('employee_category_id', 'page')) }}" class="rounded-full px-4 py-2 text-sm font-black {{ $selectedCategoryId === null ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-700' }}">
                    All Categories ({{ $employees->total() }})
                </a>
                @foreach($categoryTabs as $tab)
                    <a href="{{ route('admin.staff.employees.index', array_merge(request()->except('page'), ['employee_category_id' => $tab['id']])) }}" class="rounded-full px-4 py-2 text-sm font-black {{ $selectedCategoryId === $tab['id'] ? 'bg-cyan-500 text-slate-950' : 'bg-slate-100 text-slate-700' }}">
                        {{ $tab['name'] }} ({{ $tab['count'] }})
                    </a>
                @endforeach
            </div>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black text-slate-950">Staff CRUD</h2>
                    <p class="text-sm font-semibold text-slate-500">Search by name, code, phone, or email.</p>
                </div>
                <form method="GET" class="flex flex-wrap gap-2">
                    @if($selectedCategoryId !== null)
                        <input type="hidden" name="employee_category_id" value="{{ $selectedCategoryId }}">
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
                <table class="min-w-full text-left text-sm">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="pb-3">Name</th>
                            <th class="pb-3">Code</th>
                            <th class="pb-3">Area</th>
                            <th class="pb-3">Category</th>
                            <th class="pb-3">Salary</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($employees as $employee)
                            <tr>
                                <td class="py-3">
                                    <a href="{{ route('admin.staff.show', $employee) }}" class="font-bold text-slate-900 underline-offset-4 hover:text-cyan-700 hover:underline">{{ $employee->name }}</a>
                                    <p class="text-xs font-semibold text-slate-500">{{ $employee->phone ?: ($employee->user?->email ?? 'No contact') }}</p>
                                </td>
                                <td class="py-3">{{ $employee->employee_code }}</td>
                                <td class="py-3 capitalize">{{ $employee->staff_area }}</td>
                                <td class="py-3">{{ $employee->category->name }}</td>
                                <td class="py-3">Rs. {{ number_format((float) $employee->monthly_salary, 2) }}</td>
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
                        <option value="{{ $category->id }}" @selected($selectedCategoryId === $category->id)>{{ $category->name }}</option>
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
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                <input type="number" step="0.01" name="monthly_salary" placeholder="Monthly salary" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
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
