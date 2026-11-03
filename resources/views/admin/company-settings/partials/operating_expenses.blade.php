{{-- Operating Expense Settings Tab Panel --}}
<section data-settings-panel="operating-expenses" role="tabpanel" class="settings-panel hidden space-y-8">
    {{-- Error Banner for Operating Expense Settings --}}
    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-800">
            <div class="flex items-center gap-2 mb-1">
                <svg class="h-5 w-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
                <span>Please correct the errors below:</span>
            </div>
            <ul class="list-disc list-inside space-y-1 text-xs font-semibold text-rose-700 ml-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- SECTION 1: Expense Categories --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 space-y-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-2 w-2 rounded-full bg-teal-500"></span>
                    <h2 class="text-base font-black tracking-tight text-slate-950">Expense Categories</h2>
                </div>
                <p class="mt-1 text-xs font-semibold text-slate-500">
                    Manage operating expense categories, location requirements, and active status.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <input type="text"
                           id="category-search-input"
                           placeholder="Filter categories..."
                           class="h-9 w-44 sm:w-56 rounded-xl border border-slate-200 bg-slate-50 pl-8 pr-3 text-xs font-semibold text-slate-900 placeholder-slate-400 focus:border-teal-500 focus:bg-white focus:outline-none">
                    <svg class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
                <button type="button"
                        onclick="openAddCategoryModal()"
                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl bg-teal-600 px-3.5 text-xs font-black text-white shadow-sm transition hover:bg-teal-500 shrink-0">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add Category
                </button>
            </div>
        </div>

        {{-- Categories Table --}}
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full text-left text-xs" id="categories-table">
                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Category Name</th>
                        <th class="px-4 py-3">Key</th>
                        <th class="px-4 py-3">Location Mode</th>
                        <th class="px-4 py-3 text-center">Locations</th>
                        <th class="px-4 py-3 text-center">Expenses</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse ($operatingExpenseCategories as $category)
                        <tr class="hover:bg-slate-50/80 transition category-row" data-category-name="{{ strtolower($category->name) }}" data-category-key="{{ strtolower($category->key) }}">
                            <td class="px-4 py-3.5">
                                <span class="font-black text-slate-900 text-sm">{{ $category->name }}</span>
                            </td>
                            <td class="px-4 py-3.5">
                                <code class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-mono text-slate-600">{{ $category->key }}</code>
                            </td>
                            <td class="px-4 py-3.5">
                                @if ($category->sub_category_mode === 'required')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-purple-50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-purple-700 border border-purple-200/60">
                                        Required
                                    </span>
                                @elseif ($category->sub_category_mode === 'optional')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-blue-700 border border-blue-200/60">
                                        Optional
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-slate-600">
                                        None
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <a href="{{ route('admin.company-settings.edit', ['tab' => 'operating-expenses', 'category_id' => $category->id]) }}"
                                   class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-black transition {{ ($selectedExpenseCategory?->id === $category->id) ? 'bg-teal-100 text-teal-800' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                                    {{ $category->sub_categories_count }}
                                    <span class="text-[10px] text-slate-400 font-semibold">locs</span>
                                </a>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-700">
                                    {{ $category->expenses_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <form method="POST" action="{{ route('admin.company-settings.operating-expense-categories.toggle-status', $category) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            title="Click to toggle status"
                                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide transition {{ $category->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/60 hover:bg-emerald-100' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $category->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('admin.company-settings.edit', ['tab' => 'operating-expenses', 'category_id' => $category->id]) }}"
                                       class="inline-flex h-8 items-center rounded-lg border border-slate-200 bg-white px-2.5 text-[11px] font-bold text-slate-700 transition hover:bg-slate-50 {{ ($selectedExpenseCategory?->id === $category->id) ? 'border-teal-500 text-teal-700 bg-teal-50/50' : '' }}">
                                        Locations
                                    </a>
                                    <button type="button"
                                            onclick='openEditCategoryModal(@json($category))'
                                            class="inline-flex h-8 items-center rounded-lg border border-slate-200 bg-white px-2.5 text-[11px] font-bold text-slate-700 transition hover:bg-slate-50">
                                        Edit
                                    </button>
                                    @if ($category->expenses_count === 0 && $category->sub_categories_count === 0)
                                        <form method="POST"
                                              action="{{ route('admin.company-settings.operating-expense-categories.destroy', $category) }}"
                                              onsubmit="return confirm('Are you sure you want to delete category \'{{ $category->name }}\'?');"
                                              class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-rose-200 bg-white px-2 text-[11px] font-bold text-rose-600 transition hover:bg-rose-50">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-xs font-semibold text-slate-400">
                                No expense categories configured yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- SECTION 2: Locations / Routes for Selected Category --}}
    @if ($selectedExpenseCategory)
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 space-y-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-2 w-2 rounded-full bg-blue-500"></span>
                        <h2 class="text-base font-black tracking-tight text-slate-950">
                            {{ $selectedExpenseCategory->name }} — Locations / Routes
                        </h2>
                    </div>
                    <p class="mt-1 text-xs font-semibold text-slate-500">
                        Category Mode: <span class="font-bold text-slate-700 capitalize">{{ $selectedExpenseCategory->sub_category_mode }}</span> ·
                        {{ $selectedExpenseLocations->count() }} locations configured
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    {{-- Switch Parent Category Dropdown --}}
                    <div class="flex items-center gap-2">
                        <label for="switch-parent-cat" class="text-[10px] font-black uppercase tracking-wider text-slate-400">Category:</label>
                        <select id="switch-parent-cat"
                                onchange="window.location.href = this.value"
                                class="h-9 rounded-xl border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-800 focus:border-teal-500 focus:bg-white focus:outline-none">
                            @foreach ($operatingExpenseCategories as $catOption)
                                <option value="{{ route('admin.company-settings.edit', ['tab' => 'operating-expenses', 'category_id' => $catOption->id]) }}"
                                        {{ $selectedExpenseCategory->id === $catOption->id ? 'selected' : '' }}>
                                    {{ $catOption->name }} ({{ $catOption->sub_categories_count }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="button"
                            onclick="openAddLocationModal()"
                            class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-3.5 text-xs font-black text-white shadow-sm transition hover:bg-blue-500 shrink-0">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Location
                    </button>
                </div>
            </div>

            {{-- Locations Table --}}
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3">Location / Route</th>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3 text-center">Expenses</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                        @forelse ($selectedExpenseLocations as $location)
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="px-4 py-3.5">
                                    <span class="font-black text-slate-900 text-sm">{{ $location->name }}</span>
                                </td>
                                <td class="px-4 py-3.5">
                                    @if ($location->code)
                                        <code class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-mono text-slate-600">{{ $location->code }}</code>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-700">
                                        {{ $location->expenses_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <form method="POST" action="{{ route('admin.company-settings.operating-expense-sub-categories.toggle-status', $location) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                title="Click to toggle status"
                                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide transition {{ $location->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/60 hover:bg-emerald-100' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $location->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                            {{ $location->is_active ? 'Active' : 'Inactive' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button type="button"
                                                onclick='openEditLocationModal(@json($location))'
                                                class="inline-flex h-8 items-center rounded-lg border border-slate-200 bg-white px-2.5 text-[11px] font-bold text-slate-700 transition hover:bg-slate-50">
                                            Edit
                                        </button>
                                        @if ($location->expenses_count === 0)
                                            <form method="POST"
                                                  action="{{ route('admin.company-settings.operating-expense-sub-categories.destroy', $location) }}"
                                                  onsubmit="return confirm('Are you sure you want to delete location \'{{ $location->name }}\'?');"
                                                  class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex h-8 items-center rounded-lg border border-rose-200 bg-white px-2 text-[11px] font-bold text-rose-600 transition hover:bg-rose-50">
                                                    Delete
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-xs font-semibold text-slate-400">
                                    No locations or routes configured for <span class="font-bold text-slate-700">{{ $selectedExpenseCategory->name }}</span> yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>

{{-- MODAL 1: Add Category Modal --}}
<div id="add-category-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 class="text-base font-black text-slate-950">Add Operating Expense Category</h3>
            <button type="button" onclick="closeAddCategoryModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.company-settings.operating-expense-categories.store') }}" class="mt-4 space-y-4">
            @csrf
            <div>
                <label for="cat-add-name" class="block text-[11px] font-black uppercase tracking-wider text-slate-600">Category Name *</label>
                <input type="text"
                       id="cat-add-name"
                       name="name"
                       required
                       placeholder="e.g. Vehicle Rent"
                       class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
            </div>

            <div>
                <label for="cat-add-key" class="block text-[11px] font-black uppercase tracking-wider text-slate-600">Category Key</label>
                <input type="text"
                       id="cat-add-key"
                       name="key"
                       placeholder="e.g. vehicle_rent (auto-generated if empty)"
                       class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-mono font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                <p class="mt-1 text-[10px] text-slate-400 font-medium">Lowercase alphanumeric and underscores only. Immutable once created.</p>
            </div>

            <div>
                <label for="cat-add-mode" class="block text-[11px] font-black uppercase tracking-wider text-slate-600">Location Selection Mode *</label>
                <select id="cat-add-mode"
                        name="sub_category_mode"
                        required
                        class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                    <option value="none">None — No location selection</option>
                    <option value="optional">Optional — Location can be selected</option>
                    <option value="required" disabled>Required — Location must be selected (Add locations first)</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="cat-add-sort" class="block text-[11px] font-black uppercase tracking-wider text-slate-600">Sort Order</label>
                    <input type="number"
                           id="cat-add-sort"
                           name="sort_order"
                           value="0"
                           min="0"
                           class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>
                <div class="flex flex-col justify-end">
                    <label class="flex items-center gap-2 cursor-pointer pb-2.5">
                        <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                        <span class="text-xs font-bold text-slate-700">Active Category</span>
                    </label>
                </div>
            </div>

            <p class="text-[10px] text-amber-700 bg-amber-50 rounded-xl p-2.5 border border-amber-200/60 font-medium">
                Note: Custom categories default to inactive until financial reporting and purchaser workflow mappings are configured.
            </p>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeAddCategoryModal()" class="h-9 px-4 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="h-9 px-4 rounded-xl bg-teal-600 text-xs font-black text-white hover:bg-teal-500 shadow-sm">Create Category</button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL 2: Edit Category Modal --}}
<div id="edit-category-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 class="text-base font-black text-slate-950">Edit Expense Category</h3>
            <button type="button" onclick="closeEditCategoryModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form id="edit-category-form" method="POST" action="" class="mt-4 space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label for="cat-edit-name" class="block text-[11px] font-black uppercase tracking-wider text-slate-600">Category Name *</label>
                <input type="text"
                       id="cat-edit-name"
                       name="name"
                       required
                       class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-black uppercase tracking-wider text-slate-400">Category Key (Permanent)</label>
                <input type="text"
                       id="cat-edit-key"
                       disabled
                       class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-100 px-3 text-xs font-mono font-semibold text-slate-500 cursor-not-allowed">
                <p class="mt-1 text-[10px] text-slate-400 font-medium">Category keys are immutable to preserve historical and accounting records.</p>
            </div>

            <div>
                <label for="cat-edit-mode" class="block text-[11px] font-black uppercase tracking-wider text-slate-600">Location Selection Mode *</label>
                <select id="cat-edit-mode"
                        name="sub_category_mode"
                        required
                        class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                    <option value="none">None — No location selection</option>
                    <option value="optional">Optional — Location can be selected</option>
                    <option value="required">Required — Location must be selected (Requires active locations)</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="cat-edit-sort" class="block text-[11px] font-black uppercase tracking-wider text-slate-600">Sort Order</label>
                    <input type="number"
                           id="cat-edit-sort"
                           name="sort_order"
                           min="0"
                           class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>
                <div class="flex flex-col justify-end">
                    <label class="flex items-center gap-2 cursor-pointer pb-2.5">
                        <input type="checkbox" id="cat-edit-active" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                        <span class="text-xs font-bold text-slate-700">Active Category</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeEditCategoryModal()" class="h-9 px-4 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="h-9 px-4 rounded-xl bg-teal-600 text-xs font-black text-white hover:bg-teal-500 shadow-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL 3: Add Location Modal --}}
@if ($selectedExpenseCategory)
    <div id="add-location-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-black text-slate-950">Add Location / Route</h3>
                    <p class="text-[11px] font-semibold text-slate-500">Category: <span class="font-bold text-slate-800">{{ $selectedExpenseCategory->name }}</span></p>
                </div>
                <button type="button" onclick="closeAddLocationModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.company-settings.operating-expense-sub-categories.store', $selectedExpenseCategory) }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="loc-add-name" class="block text-[11px] font-black uppercase tracking-wider text-slate-600">Location / Route Name *</label>
                    <input type="text"
                           id="loc-add-name"
                           name="name"
                           required
                           placeholder="e.g. Koyambedu -> Main Warehouse"
                           class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-blue-500 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label for="loc-add-code" class="block text-[11px] font-black uppercase tracking-wider text-slate-600">Short Code (Optional)</label>
                    <input type="text"
                           id="loc-add-code"
                           name="code"
                           placeholder="e.g. LOC-01"
                           class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-mono font-semibold text-slate-900 focus:border-blue-500 focus:bg-white focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="loc-add-sort" class="block text-[11px] font-black uppercase tracking-wider text-slate-600">Sort Order</label>
                        <input type="number"
                               id="loc-add-sort"
                               name="sort_order"
                               value="0"
                               min="0"
                               class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-blue-500 focus:bg-white focus:outline-none">
                    </div>
                    <div class="flex flex-col justify-end">
                        <label class="flex items-center gap-2 cursor-pointer pb-2.5">
                            <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-xs font-bold text-slate-700">Active Location</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeAddLocationModal()" class="h-9 px-4 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="h-9 px-4 rounded-xl bg-blue-600 text-xs font-black text-white hover:bg-blue-500 shadow-sm">Add Location</button>
                </div>
            </form>
        </div>
    </div>
@endif

{{-- MODAL 4: Edit Location Modal --}}
<div id="edit-location-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 class="text-base font-black text-slate-950">Edit Location / Route</h3>
            <button type="button" onclick="closeEditLocationModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form id="edit-location-form" method="POST" action="" class="mt-4 space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label for="loc-edit-name" class="block text-[11px] font-black uppercase tracking-wider text-slate-600">Location / Route Name *</label>
                <input type="text"
                       id="loc-edit-name"
                       name="name"
                       required
                       class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-blue-500 focus:bg-white focus:outline-none">
            </div>

            <div>
                <label for="loc-edit-code" class="block text-[11px] font-black uppercase tracking-wider text-slate-600">Short Code (Optional)</label>
                <input type="text"
                       id="loc-edit-code"
                       name="code"
                       class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-mono font-semibold text-slate-900 focus:border-blue-500 focus:bg-white focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="loc-edit-sort" class="block text-[11px] font-black uppercase tracking-wider text-slate-600">Sort Order</label>
                    <input type="number"
                           id="loc-edit-sort"
                           name="sort_order"
                           min="0"
                           class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-blue-500 focus:bg-white focus:outline-none">
                </div>
                <div class="flex flex-col justify-end">
                    <label class="flex items-center gap-2 cursor-pointer pb-2.5">
                        <input type="checkbox" id="loc-edit-active" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-xs font-bold text-slate-700">Active Location</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeEditLocationModal()" class="h-9 px-4 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</button>
                <button type="submit" class="h-9 px-4 rounded-xl bg-blue-600 text-xs font-black text-white hover:bg-blue-500 shadow-sm">Save Location</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Category search filter
    const catSearchInput = document.getElementById('category-search-input');
    if (catSearchInput) {
        catSearchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            document.querySelectorAll('#categories-table .category-row').forEach(function(row) {
                const name = row.dataset.categoryName || '';
                const key = row.dataset.categoryKey || '';
                if (!query || name.includes(query) || key.includes(query)) {
                    row.classList.remove('hidden');
                } else {
                    row.classList.add('hidden');
                }
            });
        });
    }

    // Modal helpers
    function openAddCategoryModal() {
        document.getElementById('add-category-modal')?.classList.remove('hidden');
    }

    function closeAddCategoryModal() {
        document.getElementById('add-category-modal')?.classList.add('hidden');
    }

    function openEditCategoryModal(category) {
        const modal = document.getElementById('edit-category-modal');
        const form = document.getElementById('edit-category-form');
        if (!modal || !form || !category) return;

        form.action = `/admin/company-settings/operating-expense-categories/${category.id}`;
        document.getElementById('cat-edit-name').value = category.name || '';
        document.getElementById('cat-edit-key').value = category.key || '';
        document.getElementById('cat-edit-mode').value = category.sub_category_mode || 'none';
        document.getElementById('cat-edit-sort').value = category.sort_order ?? 0;
        document.getElementById('cat-edit-active').checked = Boolean(category.is_active);

        modal.classList.remove('hidden');
    }

    function closeEditCategoryModal() {
        document.getElementById('edit-category-modal')?.classList.add('hidden');
    }

    function openAddLocationModal() {
        document.getElementById('add-location-modal')?.classList.remove('hidden');
    }

    function closeAddLocationModal() {
        document.getElementById('add-location-modal')?.classList.add('hidden');
    }

    function openEditLocationModal(location) {
        const modal = document.getElementById('edit-location-modal');
        const form = document.getElementById('edit-location-form');
        if (!modal || !form || !location) return;

        form.action = `/admin/company-settings/operating-expense-sub-categories/${location.id}`;
        document.getElementById('loc-edit-name').value = location.name || '';
        document.getElementById('loc-edit-code').value = location.code || '';
        document.getElementById('loc-edit-sort').value = location.sort_order ?? 0;
        document.getElementById('loc-edit-active').checked = Boolean(location.is_active);

        modal.classList.remove('hidden');
    }

    function closeEditLocationModal() {
        document.getElementById('edit-location-modal')?.classList.add('hidden');
    }

    // Close on escape key
    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddCategoryModal();
            closeEditCategoryModal();
            closeAddLocationModal();
            closeEditLocationModal();
        }
    });
</script>

