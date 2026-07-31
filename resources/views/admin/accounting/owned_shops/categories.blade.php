<x-layouts.accounting :title="$shop->name.' Categories'">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Ledger Categories</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ $shop->name }}</h1>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Create new income and expense categories here and review all available ledger category options for this shop.</p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('admin.accounting.loans', ['shop' => $shop->code]) }}" class="inline-flex h-11 items-center rounded-2xl bg-emerald-600 px-4 text-sm font-black text-white transition hover:bg-emerald-500">
                        Petty section
                    </a>
                    <a href="{{ route('admin.accounting.owned-shops.show', ['shop' => $shop->code, 'tab' => 'cashbook']) }}" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 bg-white px-4 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                        Back to Shop
                    </a>
                </div>
            </div>
        </section>

        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Create Category</p>
            <h2 class="mt-2 text-xl font-black text-slate-950">Add new ledger category</h2>

            @php
                $purposeLabels = [
                    'custom' => 'Normal Category',
                    'sales_cash' => 'Cash Sales',
                    'sales_non_cash' => 'Online / Non-Cash Sales',
                    'shop_cash_credit' => 'Cash Credit To Shop',
                    'cash_sent_company' => 'Cash Sent To Company',
                    'staff_salary' => 'Staff Salary',
                    'staff_advance' => 'Staff Salary Advance',
                ];
            @endphp

            <form method="POST" action="{{ route('admin.accounting.owned-shops.categories.store', $shop) }}" class="mt-5 grid gap-4 xl:grid-cols-6">
                @csrf
                <label class="block">
                    <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Scope</span>
                    <select name="scope" class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                        <option value="global" @selected(old('scope') === 'global')>Global</option>
                        <option value="shop" @selected(old('scope') === 'shop')>{{ $shop->name }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Type</span>
                    <select name="type" class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                        <option value="income" @selected(old('type') === 'income')>Income</option>
                        <option value="expense" @selected(old('type') === 'expense')>Expense</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Purpose</span>
                    <select name="purpose" class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                        @foreach($purposeLabels as $purpose => $label)
                            <option value="{{ $purpose }}" @selected(old('purpose', 'custom') === $purpose)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block xl:col-span-2">
                    <span class="mb-2 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Name</span>
                    <div class="flex gap-3">
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="Category name" class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900">
                        <button type="submit" class="inline-flex h-11 shrink-0 items-center justify-center rounded-2xl bg-slate-950 px-5 text-sm font-black text-white transition hover:bg-slate-800">
                            Create
                        </button>
                    </div>
                </label>
                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-700">
                    <input type="checkbox" name="cash_effect" value="1" @checked(old('cash_effect', true)) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <span>Changes daily cash balance</span>
                </label>
            </form>
        </section>

        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Manage Categories</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950">Edit or delete ledger categories</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-600">Used categories stay locked for deletion to protect submitted receipt history.</p>
                </div>
                <span class="inline-flex h-9 items-center rounded-2xl bg-slate-100 px-4 text-xs font-black uppercase tracking-[0.16em] text-slate-600">
                    {{ $globalCategories->count() + $shopCategories->count() }} categories
                </span>
            </div>

            @php($allCategories = $globalCategories->concat($shopCategories)->sortBy([['shop_id', 'asc'], ['type', 'asc'], ['name', 'asc']])->values())

            <div class="mt-5 space-y-3 lg:hidden">
                @forelse($allCategories as $category)
                    <article class="rounded-[1.25rem] border border-slate-200 bg-slate-50 p-4">
                        <form method="POST" action="{{ route('admin.accounting.owned-shops.categories.update', ['shop' => $shop, 'category' => $category]) }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label>
                                    <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Scope</span>
                                    <select name="scope" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-900">
                                        <option value="global" @selected($category->shop_id === null)>Global</option>
                                        <option value="shop" @selected($category->shop_id !== null)>{{ $shop->name }}</option>
                                    </select>
                                </label>
                                <label>
                                    <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Type</span>
                                    <select name="type" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-900">
                                        <option value="income" @selected($category->type === 'income')>Income</option>
                                        <option value="expense" @selected($category->type === 'expense')>Expense</option>
                                    </select>
                                </label>
                            </div>
                            <label>
                                <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Name</span>
                                <input type="text" name="name" value="{{ $category->name }}" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-900">
                            </label>
                            <label>
                                <span class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Purpose</span>
                                <select name="purpose" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-900">
                                    @foreach($purposeLabels as $purpose => $label)
                                        <option value="{{ $purpose }}" @selected($category->purpose === $purpose)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-600">
                                    <input type="checkbox" name="cash_effect" value="1" @checked($category->cash_effect) class="h-4 w-4 rounded border-slate-300 text-emerald-600">
                                    Cash
                                </label>
                                <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-600">
                                    <input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="h-4 w-4 rounded border-slate-300 text-emerald-600">
                                    Active
                                </label>
                                <span class="inline-flex items-center rounded-xl bg-white px-3 py-2 text-xs font-black text-slate-500">{{ number_format($category->entry_lines_count) }} used</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-slate-950 px-4 text-sm font-black text-white">Update</button>
                                @if ((int) $category->entry_lines_count === 0)
                                    <button type="submit" form="delete-category-mobile-{{ $category->id }}" class="inline-flex h-10 items-center rounded-xl bg-rose-600 px-4 text-sm font-black text-white">Delete</button>
                                @else
                                    <span class="inline-flex h-10 items-center rounded-xl bg-slate-200 px-4 text-sm font-black text-slate-500">Cannot delete</span>
                                @endif
                            </div>
                        </form>
                        @if ((int) $category->entry_lines_count === 0)
                            <form id="delete-category-mobile-{{ $category->id }}" method="POST" action="{{ route('admin.accounting.owned-shops.categories.destroy', ['shop' => $shop, 'category' => $category]) }}" onsubmit="return confirm('Delete this unused category?')">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm font-bold text-slate-500">No categories yet.</p>
                @endforelse
            </div>

            <div class="mt-5 hidden overflow-x-auto rounded-[1.25rem] border border-slate-200 lg:block">
                <table class="min-w-full table-fixed text-left text-sm">
                    <thead class="bg-slate-950 text-[10px] font-black uppercase tracking-[0.16em] text-slate-200">
                        <tr>
                            <th class="px-3 py-3">Scope</th>
                            <th class="px-3 py-3">Type</th>
                            <th class="px-3 py-3">Name</th>
                            <th class="px-3 py-3">Purpose</th>
                            <th class="px-3 py-3">Flags</th>
                            <th class="px-3 py-3 text-right">Used</th>
                            <th class="px-3 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($allCategories as $category)
                            <tr class="align-top">
                                <td class="px-3 py-3">
                                    <select name="scope" form="update-category-{{ $category->id }}" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900">
                                        <option value="global" @selected($category->shop_id === null)>Global</option>
                                        <option value="shop" @selected($category->shop_id !== null)>Shop</option>
                                    </select>
                                </td>
                                <td class="px-3 py-3">
                                    <select name="type" form="update-category-{{ $category->id }}" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900">
                                        <option value="income" @selected($category->type === 'income')>Income</option>
                                        <option value="expense" @selected($category->type === 'expense')>Expense</option>
                                    </select>
                                </td>
                                <td class="px-3 py-3">
                                    <input type="text" name="name" form="update-category-{{ $category->id }}" value="{{ $category->name }}" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900">
                                </td>
                                <td class="px-3 py-3">
                                    <select name="purpose" form="update-category-{{ $category->id }}" class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900">
                                        @foreach($purposeLabels as $purpose => $label)
                                            <option value="{{ $purpose }}" @selected($category->purpose === $purpose)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <label class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">
                                            <input type="checkbox" name="cash_effect" form="update-category-{{ $category->id }}" value="1" @checked($category->cash_effect) class="h-4 w-4 rounded border-slate-300 text-emerald-600">
                                            Cash
                                        </label>
                                        <label class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">
                                            <input type="checkbox" name="is_active" form="update-category-{{ $category->id }}" value="1" @checked($category->is_active) class="h-4 w-4 rounded border-slate-300 text-emerald-600">
                                            Active
                                        </label>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right font-black text-slate-700">{{ number_format($category->entry_lines_count) }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex justify-end gap-2">
                                        <form id="update-category-{{ $category->id }}" method="POST" action="{{ route('admin.accounting.owned-shops.categories.update', ['shop' => $shop, 'category' => $category]) }}" class="hidden">
                                            @csrf
                                            @method('PATCH')
                                        </form>
                                        <button type="submit" form="update-category-{{ $category->id }}" class="inline-flex h-10 items-center rounded-xl bg-slate-950 px-3 text-xs font-black text-white">Update</button>
                                        @if ((int) $category->entry_lines_count === 0)
                                            <form method="POST" action="{{ route('admin.accounting.owned-shops.categories.destroy', ['shop' => $shop, 'category' => $category]) }}" onsubmit="return confirm('Delete this unused category?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-rose-600 px-3 text-xs font-black text-white">Delete</button>
                                            </form>
                                        @else
                                            <span class="inline-flex h-10 items-center rounded-xl bg-slate-100 px-3 text-xs font-black text-slate-500">Used</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No categories yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.accounting>
