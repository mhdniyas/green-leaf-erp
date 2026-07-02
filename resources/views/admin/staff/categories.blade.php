<x-layouts.staff title="Staff Categories">
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <h1 class="text-2xl font-black text-slate-950">Category Rules</h1>
            <p class="text-sm font-semibold text-slate-500">Manage payroll categories, paid leave limits, and salary calculation weights.</p>
        </div>

        <section class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-black text-slate-950">Add Payroll Category</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">Create a salary rule group for office staff, direct board, or other teams.</p>

                <form method="POST" action="{{ route('admin.staff.categories.store') }}" class="mt-5 grid gap-3 md:grid-cols-2">
                    @csrf
                    <input type="text" name="name" placeholder="Category name" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <input type="text" name="code" placeholder="Code" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <select name="staff_area" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                        <option value="office">Office</option>
                        <option value="shop">Shop</option>
                    </select>
                    <input type="number" step="0.01" name="default_monthly_salary" placeholder="Default salary" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <input type="number" name="monthly_paid_leave_limit" min="0" max="31" placeholder="Paid leaves / month" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <input type="number" step="0.01" min="0" max="1" name="present_day_weight" value="1" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <input type="number" step="0.01" min="0" max="1" name="half_day_weight" value="0.5" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <input type="number" step="0.01" min="0" max="1" name="paid_leave_weight" value="1" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <input type="number" step="0.01" min="0" max="1" name="excess_leave_weight" value="0" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <input type="number" step="0.01" min="0" max="1" name="absent_day_weight" value="0" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                    <input type="text" name="notes" placeholder="Notes (optional)" class="rounded-xl border border-slate-200 px-3 py-2 text-sm md:col-span-2">
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-600 md:col-span-2">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300">
                        Active category
                    </label>
                    <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-black text-white md:col-span-2">Create Category</button>
                </form>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-black text-slate-950">Existing Rules</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">These rules control how leave and absence impact payroll.</p>

                <div class="mt-5 space-y-4">
                    @foreach($allCategories as $category)
                        <form method="POST" action="{{ route('admin.staff.categories.update', $category) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            @csrf
                            @method('PUT')
                            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                <input type="text" name="name" value="{{ $category->name }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                                <input type="text" name="code" value="{{ $category->code }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                                <select name="staff_area" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                                    <option value="office" @selected($category->staff_area === 'office')>Office</option>
                                    <option value="shop" @selected($category->staff_area === 'shop')>Shop</option>
                                </select>
                                <input type="number" step="0.01" name="default_monthly_salary" value="{{ (float) $category->default_monthly_salary }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                                <input type="number" name="monthly_paid_leave_limit" min="0" max="31" value="{{ $category->monthly_paid_leave_limit }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                                <input type="number" step="0.01" min="0" max="1" name="present_day_weight" value="{{ (float) $category->present_day_weight }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                                <input type="number" step="0.01" min="0" max="1" name="half_day_weight" value="{{ (float) $category->half_day_weight }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                                <input type="number" step="0.01" min="0" max="1" name="paid_leave_weight" value="{{ (float) $category->paid_leave_weight }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                                <input type="number" step="0.01" min="0" max="1" name="excess_leave_weight" value="{{ (float) $category->excess_leave_weight }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                                <input type="number" step="0.01" min="0" max="1" name="absent_day_weight" value="{{ (float) $category->absent_day_weight }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                                <input type="text" name="notes" value="{{ $category->notes }}" placeholder="Notes" class="rounded-xl border border-slate-200 px-3 py-2 text-sm md:col-span-2 xl:col-span-1">
                                <label class="flex items-center gap-2 text-sm font-semibold text-slate-600">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="rounded border-slate-300">
                                    Active
                                </label>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                <p class="text-xs font-semibold text-slate-500">{{ $category->monthly_paid_leave_limit }} paid leave day(s) per month. Extra leave weight: {{ number_format((float) $category->excess_leave_weight, 2) }}.</p>
                                <button type="submit" class="rounded-xl bg-cyan-500 px-4 py-2 text-sm font-black text-slate-950">Update Rule</button>
                            </div>
                        </form>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</x-layouts.staff>
