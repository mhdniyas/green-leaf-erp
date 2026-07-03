<x-layouts.staff title="Staff Categories">
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <h1 class="text-2xl font-black text-slate-950">Category Rules</h1>
            <p class="text-sm font-semibold text-slate-500">Manage payroll categories, paid leave limits, and salary calculation weights.</p>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap gap-2">
                <a href="#create-category" class="rounded-full bg-slate-950 px-4 py-2 text-sm font-black text-white">Add Payroll Category</a>
                @foreach($allCategories as $category)
                    <a href="#category-{{ $category->code }}" class="rounded-full bg-slate-100 px-4 py-2 text-sm font-black text-slate-700">
                        {{ $category->name }}
                    </a>
                @endforeach
            </div>
            <p class="mt-4 text-sm font-semibold text-slate-500">Only set the salary, monthly paid leaves, and how much staff should be paid for half day, unpaid leave, and absent day.</p>
        </section>

        <section id="create-category" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black text-slate-950">Add Payroll Category</h2>
            <p class="mt-1 text-sm font-semibold text-slate-500">Create a simple monthly salary rule for one staff category. Present day and approved paid leave always count as full pay.</p>

            <form method="POST" action="{{ route('admin.staff.categories.store') }}" class="mt-5 grid gap-4 xl:grid-cols-4">
                @csrf
                <label class="block">
                    <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Category Name</span>
                    <input type="text" name="name" placeholder="Category name" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                </label>
                <label class="block">
                    <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Category Code</span>
                    <input type="text" name="code" placeholder="Code" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                </label>
                <label class="block">
                    <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Staff Area</span>
                    <select name="staff_area" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                        <option value="office">Office</option>
                        <option value="shop">Shop</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Monthly Salary</span>
                    <input type="number" step="0.01" name="default_monthly_salary" placeholder="Monthly salary" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                </label>
                <label class="block">
                    <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Paid Leaves Per Month</span>
                    <input type="number" name="monthly_paid_leave_limit" min="0" max="31" placeholder="Paid leaves allowed per month" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                </label>
                <label class="block">
                    <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Half Day Pay %</span>
                    <input type="number" min="0" max="100" name="half_day_salary_percent" value="50" placeholder="Half day pay %" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                </label>
                <label class="block">
                    <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Unpaid Leave Pay %</span>
                    <input type="number" min="0" max="100" name="unpaid_leave_salary_percent" value="0" placeholder="Unpaid leave pay %" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                </label>
                <label class="block">
                    <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Absent Day Pay %</span>
                    <input type="number" min="0" max="100" name="absent_day_salary_percent" value="0" placeholder="Absent day pay %" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                </label>
                <input type="hidden" name="present_day_weight" value="1">
                <input type="hidden" name="paid_leave_weight" value="1">
                <label class="block xl:col-span-3">
                    <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Notes</span>
                    <input type="text" name="notes" placeholder="Notes (optional)" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm">
                </label>
                <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-600">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300">
                    Active category
                </label>
                <button type="submit" class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-black text-white xl:col-span-4">Create Category</button>
            </form>
        </section>

        <section class="space-y-5">
            <div>
                <h2 class="text-xl font-black text-slate-950">Rule Directory</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">Each category shows the monthly payroll setup in business language instead of raw calculation weights.</p>
            </div>

            @foreach($allCategories as $category)
                <form id="category-{{ $category->code }}" method="POST" action="{{ route('admin.staff.categories.update', $category) }}" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    @csrf
                    @method('PUT')
                    @php($isCoreCategory = $category->isCoreCategory())

                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-black text-slate-950">{{ $category->name }}</h3>
                            <p class="mt-1 text-sm font-semibold text-slate-500">
                                {{ $category->monthly_paid_leave_limit }} paid leave day(s) per month. Half day pays {{ (int) round((float) $category->half_day_weight * 100) }}%. Unpaid leave pays {{ (int) round((float) $category->excess_leave_weight * 100) }}%. Absent day pays {{ (int) round((float) $category->absent_day_weight * 100) }}%.
                                {{ $isCoreCategory ? 'This is a permanent staff category.' : '' }}
                            </p>
                        </div>
                        <span class="rounded-full {{ $isCoreCategory ? 'bg-cyan-50 text-cyan-700' : 'bg-slate-100 text-slate-700' }} px-3 py-1 text-xs font-black uppercase tracking-[0.16em]">
                            {{ $isCoreCategory ? 'Core Category' : 'Custom Rule' }}
                        </span>
                    </div>

                    <div class="mt-5 grid gap-4 xl:grid-cols-4">
                        <label class="block">
                            <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Category Name</span>
                            <input type="text" name="name" value="{{ $category->name }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm {{ $isCoreCategory ? 'bg-slate-100 text-slate-500' : '' }}" @readonly($isCoreCategory) required>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Category Code</span>
                            <input type="text" name="code" value="{{ $category->code }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm {{ $isCoreCategory ? 'bg-slate-100 text-slate-500' : '' }}" @readonly($isCoreCategory) required>
                        </label>
                        @if($isCoreCategory)
                            <input type="hidden" name="staff_area" value="{{ $category->staff_area }}">
                        @endif
                        <label class="block">
                            <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Staff Area</span>
                            <select name="{{ $isCoreCategory ? '' : 'staff_area' }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm {{ $isCoreCategory ? 'bg-slate-100 text-slate-500' : '' }}" @disabled($isCoreCategory) required>
                                <option value="office" @selected($category->staff_area === 'office')>Office</option>
                                <option value="shop" @selected($category->staff_area === 'shop')>Shop</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Monthly Salary</span>
                            <input type="number" step="0.01" name="default_monthly_salary" value="{{ (float) $category->default_monthly_salary }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Paid Leaves Per Month</span>
                            <input type="number" name="monthly_paid_leave_limit" min="0" max="31" value="{{ $category->monthly_paid_leave_limit }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Half Day Pay %</span>
                            <input type="number" min="0" max="100" name="half_day_salary_percent" value="{{ (int) round((float) $category->half_day_weight * 100) }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Unpaid Leave Pay %</span>
                            <input type="number" min="0" max="100" name="unpaid_leave_salary_percent" value="{{ (int) round((float) $category->excess_leave_weight * 100) }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                        </label>
                        <label class="block">
                            <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Absent Day Pay %</span>
                            <input type="number" min="0" max="100" name="absent_day_salary_percent" value="{{ (int) round((float) $category->absent_day_weight * 100) }}" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm" required>
                        </label>
                        <input type="hidden" name="present_day_weight" value="1">
                        <input type="hidden" name="paid_leave_weight" value="1">
                        <label class="block xl:col-span-3">
                            <span class="mb-2 block text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Notes</span>
                            <input type="text" name="notes" value="{{ $category->notes }}" placeholder="Notes" class="w-full rounded-xl border border-slate-200 px-3 py-3 text-sm">
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-600">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="rounded border-slate-300">
                            Active
                        </label>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <button type="submit" class="rounded-xl bg-cyan-500 px-5 py-3 text-sm font-black text-slate-950">Update Rule</button>
                    </div>
                </form>
            @endforeach
        </section>
    </div>
</x-layouts.staff>
