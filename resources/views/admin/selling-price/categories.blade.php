<x-layouts.admin title="Selling Price Categories">
    <div class="mx-auto max-w-5xl space-y-6">
        {{-- Header Section --}}
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Administration</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Selling Price Management</h1>
                    <p class="mt-2 max-w-2xl text-sm font-semibold leading-6 text-slate-500">
                        Configure global retail markups, categories, matrices, and shop pricing rules.
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('admin.overview') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 transition hover:bg-slate-50">
                        Back to Admin
                    </a>
                </div>
            </div>
        </section>

        {{-- Flash Notifications --}}
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-800">
                {{ session('error') }}
            </div>
        @endif

        {{-- Module Container --}}
        <div class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-sm">
            {{-- Navigation Tabs --}}
            <div class="flex gap-1 overflow-x-auto border-b border-slate-200 bg-slate-50 p-2" role="tablist" aria-label="Selling price management sections">
                {{-- Tab 1: General Pricing --}}
                <a href="{{ route('admin.selling-price.index') }}"
                   role="tab"
                   aria-selected="false"
                   class="inline-flex items-center gap-2 shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white hover:text-slate-900">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
                    </svg>
                    General Pricing
                </a>

                {{-- Tab 2: Price Categories (Active) --}}
                <a href="{{ route('admin.selling-price.categories.index') }}"
                   role="tab"
                   aria-selected="true"
                   class="inline-flex items-center gap-2 shrink-0 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                    </svg>
                    <span>Price Categories</span>
                </a>

                {{-- Tab 3: Product Matrix --}}
                <a href="{{ route('admin.selling-price.matrix.index') }}"
                   role="tab"
                   aria-selected="false"
                   class="inline-flex items-center gap-2 shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white hover:text-slate-900">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                    </svg>
                    <span>Product Matrix</span>
                </a>

                {{-- Tab 4: Shop Pricing --}}
                <a href="{{ route('admin.selling-price.shop-pricing.index') }}"
                   role="tab"
                   aria-selected="false"
                   class="inline-flex items-center gap-2 shrink-0 rounded-xl px-4 py-2.5 text-xs font-black text-slate-600 transition hover:bg-white hover:text-slate-900">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                    </svg>
                    <span>Shop Pricing</span>
                </a>
            </div>

            {{-- Categories Content Section --}}
            <div class="p-5 sm:p-6 space-y-6">
                {{-- Quick Metrics Cards --}}
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Categories</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ $categories->count() }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Assigned Products</p>
                        <p class="mt-1 text-2xl font-black text-teal-700">{{ $totalActiveProductsCount - $unassignedProductsCount }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Unassigned Products</p>
                        <p class="mt-1 text-2xl font-black text-slate-700">{{ $unassignedProductsCount }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Global Fallback</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ $sellingPriceDefaultPercentage }}%</p>
                    </div>
                </div>

                {{-- Action Bar --}}
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Selling Price Categories</h2>
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">
                            Products assigned to an active category will receive its markup percentage. Unassigned products use the global {{ $sellingPriceDefaultPercentage }}% fallback.
                        </p>
                    </div>
                    <button type="button"
                            onclick="openAddCategoryModal()"
                            class="inline-flex h-10 items-center justify-center gap-2 shrink-0 rounded-xl bg-teal-600 px-4 text-xs font-black text-white shadow-sm transition hover:bg-teal-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Category
                    </button>
                </div>

                {{-- Categories Table --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-100 bg-slate-50/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Markup %</th>
                                <th class="px-4 py-3">Assigned Products</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                            @forelse ($categories as $cat)
                                <tr class="transition hover:bg-slate-50/50">
                                    <td class="px-4 py-3.5">
                                        <div class="font-black text-slate-900 text-sm">{{ $cat->name }}</div>
                                        <div class="text-[11px] text-slate-400 font-medium mt-0.5">
                                            Updated {{ $cat->updated_at->diffForHumans() }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-bold text-teal-700 text-sm">
                                            {{ $cat->markup_percentage }}%
                                        </div>
                                        <div class="text-[10px] text-slate-400 font-semibold">
                                            ₹100 → ₹{{ number_format(round(100 * (1 + ($cat->markup_percentage / 100)), 2), 2) }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-teal-500"></span>
                                            {{ $cat->products_count }} {{ Str::plural('product', $cat->products_count) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        @if ($cat->is_active)
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700 border border-emerald-200">
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-500 border border-slate-200" title="Products fall back to global {{ $sellingPriceDefaultPercentage }}%">
                                                Inactive
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            {{-- Manage Products Button --}}
                                            <button type="button"
                                                    onclick="openManageProductsModal({{ $cat->id }}, @js($cat->name), {{ $cat->markup_percentage }}, {{ $cat->is_active ? 'true' : 'false' }})"
                                                    class="inline-flex h-8 items-center justify-center gap-1 rounded-lg bg-teal-50 px-2.5 text-xs font-bold text-teal-700 transition hover:bg-teal-100">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                                                </svg>
                                                Manage Products
                                            </button>

                                            {{-- Edit Category Button --}}
                                            <button type="button"
                                                    onclick="openEditCategoryModal({{ $cat->id }}, @js($cat->name), {{ $cat->markup_percentage }}, {{ $cat->is_active ? 'true' : 'false' }})"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                                                    title="Edit Category">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                                </svg>
                                            </button>

                                            {{-- Toggle Status Form --}}
                                            <form method="POST" action="{{ route('admin.selling-price.categories.toggle-status', $cat) }}" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        class="inline-flex h-8 px-2 items-center justify-center rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
                                                        title="{{ $cat->is_active ? 'Deactivate Category' : 'Activate Category' }}">
                                                    {{ $cat->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>

                                            {{-- Delete Button (Protected) --}}
                                            <form method="POST" action="{{ route('admin.selling-price.categories.destroy', $cat) }}"
                                                  onsubmit="return confirm('Are you sure you want to delete category {{ addslashes($cat->name) }}?')"
                                                  class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 {{ $cat->products_count > 0 ? 'opacity-40 cursor-not-allowed' : '' }}"
                                                        title="{{ $cat->products_count > 0 ? 'Cannot delete category with assigned products' : 'Delete Category' }}"
                                                        {{ $cat->products_count > 0 ? 'disabled' : '' }}>
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-12 text-center">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                                            </svg>
                                        </div>
                                        <h3 class="mt-3 text-sm font-bold text-slate-800">No Selling Price Categories Yet</h3>
                                        <p class="mt-1 text-xs text-slate-500">Create your first category to group products with specific retail markup percentages.</p>
                                        <button type="button"
                                                onclick="openAddCategoryModal()"
                                                class="mt-4 inline-flex h-9 items-center justify-center rounded-xl bg-teal-600 px-4 text-xs font-bold text-white shadow-xs transition hover:bg-teal-500">
                                            Add First Category
                                        </button>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal 1: Add Category Modal --}}
    <div id="addCategoryModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-xs flex items-center justify-center">
        <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-black text-slate-900">Add Selling Price Category</h3>
                <button type="button" onclick="closeAddCategoryModal()" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.selling-price.categories.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="create_name" class="block text-xs font-bold text-slate-700">Category Name</label>
                    <input type="text"
                           id="create_name"
                           name="name"
                           required
                           placeholder="e.g. Premium Veg, Low Margin"
                           class="mt-1 h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label for="create_markup" class="block text-xs font-bold text-slate-700">Markup Percentage (%)</label>
                    <div class="relative mt-1">
                        <input type="number"
                               id="create_markup"
                               name="markup_percentage"
                               step="0.01"
                               min="0"
                               max="500"
                               required
                               placeholder="e.g. 30"
                               value="25.00"
                               class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 pr-8 text-sm font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-black text-slate-400">%</span>
                    </div>
                    <p class="mt-1 text-[11px] font-semibold text-slate-400">Accepted range: 0% – 500%</p>
                </div>

                {{-- Illustrative Preview Box --}}
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5 text-xs space-y-1">
                    <div class="flex items-center justify-between text-slate-600 font-semibold">
                        <span>Illustrative Supply Price</span>
                        <span class="font-bold text-slate-900">₹100.00</span>
                    </div>
                    <div class="flex items-center justify-between text-teal-700 font-bold">
                        <span>Markup Applied</span>
                        <span id="create_preview_markup">25%</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-200 pt-1.5 font-black text-slate-900">
                        <span>Recommended Retail Price</span>
                        <span id="create_preview_retail" class="text-teal-800">₹125.00</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="create_is_active" name="is_active" value="1" checked class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    <label for="create_is_active" class="text-xs font-bold text-slate-700">Active (applies markup to assigned products)</label>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                    <button type="button" onclick="closeAddCategoryModal()" class="rounded-xl px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100">Cancel</button>
                    <button type="submit" class="rounded-xl bg-teal-600 px-5 py-2 text-xs font-black text-white hover:bg-teal-500 shadow-sm">Create Category</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal 2: Edit Category Modal --}}
    <div id="editCategoryModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-xs flex items-center justify-center">
        <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-black text-slate-900">Edit Selling Price Category</h3>
                <button type="button" onclick="closeEditCategoryModal()" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="editCategoryForm" method="POST" action="" class="space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label for="edit_name" class="block text-xs font-bold text-slate-700">Category Name</label>
                    <input type="text"
                           id="edit_name"
                           name="name"
                           required
                           class="mt-1 h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label for="edit_markup" class="block text-xs font-bold text-slate-700">Markup Percentage (%)</label>
                    <div class="relative mt-1">
                        <input type="number"
                               id="edit_markup"
                               name="markup_percentage"
                               step="0.01"
                               min="0"
                               max="500"
                               required
                               class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 pr-8 text-sm font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-black text-slate-400">%</span>
                    </div>
                </div>

                {{-- Illustrative Preview Box --}}
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5 text-xs space-y-1">
                    <div class="flex items-center justify-between text-slate-600 font-semibold">
                        <span>Illustrative Supply Price</span>
                        <span class="font-bold text-slate-900">₹100.00</span>
                    </div>
                    <div class="flex items-center justify-between text-teal-700 font-bold">
                        <span>Markup Applied</span>
                        <span id="edit_preview_markup">0%</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-200 pt-1.5 font-black text-slate-900">
                        <span>Recommended Retail Price</span>
                        <span id="edit_preview_retail" class="text-teal-800">₹100.00</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="edit_is_active" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    <label for="edit_is_active" class="text-xs font-bold text-slate-700">Active</label>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                    <button type="button" onclick="closeEditCategoryModal()" class="rounded-xl px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100">Cancel</button>
                    <button type="submit" class="rounded-xl bg-teal-600 px-5 py-2 text-xs font-black text-white hover:bg-teal-500 shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal 3: Manage Products Slide-over / Modal --}}
    <div id="manageProductsModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-xs flex items-center justify-center">
        <div class="w-full max-w-4xl rounded-3xl bg-white p-6 shadow-2xl space-y-5 max-h-[90vh] flex flex-col">
            {{-- Modal Header --}}
            <div class="flex items-start justify-between border-b border-slate-100 pb-4 shrink-0">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-md bg-teal-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-teal-700">Category Products</span>
                        <span id="mpCategoryActiveBadge" class="rounded-md px-2 py-0.5 text-[10px] font-bold"></span>
                    </div>
                    <h3 id="mpCategoryTitle" class="mt-1 text-xl font-black text-slate-900"></h3>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">
                        Markup: <span id="mpCategoryMarkup" class="font-bold text-teal-700"></span> · Illustrative: ₹100 → <span id="mpCategoryRetail" class="font-bold text-slate-900"></span>
                    </p>
                </div>
                <button type="button" onclick="closeManageProductsModal()" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Manage Panes Container --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 flex-1 overflow-hidden min-h-[380px]">
                {{-- Pane 1: Currently Assigned Products --}}
                <div class="flex flex-col border border-slate-200 rounded-2xl p-4 bg-slate-50/50 overflow-hidden">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 shrink-0">
                        <div class="font-black text-xs text-slate-900 flex items-center gap-1.5">
                            <span>Currently Assigned</span>
                            <span id="mpAssignedCountBadge" class="rounded-full bg-teal-100 text-teal-800 text-[10px] px-2 py-0.5 font-bold">0</span>
                        </div>
                        <button type="button" onclick="reloadAssignedProducts()" class="text-[11px] font-bold text-teal-700 hover:underline">Refresh</button>
                    </div>

                    <div id="assignedProductsList" class="flex-1 overflow-y-auto divide-y divide-slate-100 pr-1 mt-2">
                        <div class="p-8 text-center text-xs text-slate-400">Loading products...</div>
                    </div>
                </div>

                {{-- Pane 2: Search & Add Products --}}
                <div class="flex flex-col border border-slate-200 rounded-2xl p-4 bg-white overflow-hidden">
                    <div class="pb-3 border-b border-slate-100 shrink-0 space-y-2">
                        <div class="font-black text-xs text-slate-900">Add Products to this Category</div>
                        <div class="relative">
                            <input type="text"
                                   id="productSearchInput"
                                   placeholder="Search by product name or SKU..."
                                   class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 pl-8 pr-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                            <svg class="h-4 w-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Search Results List --}}
                    <div id="searchResultsList" class="flex-1 overflow-y-auto divide-y divide-slate-100 pr-1 mt-2">
                        <div class="p-8 text-center text-xs text-slate-400">Type above to search products...</div>
                    </div>

                    {{-- Reassignment Conflict Warning & Action Bar --}}
                    <div class="pt-3 border-t border-slate-100 shrink-0 space-y-2 mt-2">
                        <div id="reassignWarningBox" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-2.5 text-xs text-amber-900 space-y-1.5">
                            <div class="font-bold flex items-center gap-1.5 text-amber-950">
                                <svg class="h-4 w-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span>Reassignment Notice</span>
                            </div>
                            <p class="text-[11px] text-amber-800">One or more selected products currently belong to another category. Moving them here will update their category assignment.</p>
                            <label class="flex items-center gap-1.5 font-bold cursor-pointer text-[11px]">
                                <input type="checkbox" id="confirmReassignCheckbox" class="rounded border-amber-300 text-teal-600 focus:ring-teal-500">
                                <span>I confirm moving these products to this category</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-between">
                            <span id="selectedCountText" class="text-xs font-bold text-slate-500">0 selected</span>
                            <button type="button"
                                    id="assignSelectedBtn"
                                    onclick="submitProductAssignment()"
                                    disabled
                                    class="h-9 rounded-xl bg-teal-600 px-4 text-xs font-black text-white shadow-xs transition hover:bg-teal-500 disabled:opacity-40 disabled:cursor-not-allowed">
                                Assign Selected
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Frontend JavaScript --}}
    <script>
        let currentCategoryId = null;
        let selectedProductIds = new Set();
        let searchResultsData = [];
        let searchDebounceTimer = null;

        // Preview Helper
        function calculateRetail(markup) {
            const num = parseFloat(markup);
            if (isNaN(num) || num < 0) return '100.00';
            const price = 100 * (1 + (num / 100));
            return (Math.round(price * 100) / 100).toFixed(2);
        }

        // Add Modal
        function openAddCategoryModal() {
            document.getElementById('addCategoryModal').classList.remove('hidden');
            const markupInput = document.getElementById('create_markup');
            markupInput.oninput = () => {
                const val = markupInput.value;
                document.getElementById('create_preview_markup').innerText = (val || 0) + '%';
                document.getElementById('create_preview_retail').innerText = '₹' + calculateRetail(val);
            };
        }
        function closeAddCategoryModal() {
            document.getElementById('addCategoryModal').classList.add('hidden');
        }

        // Edit Modal
        function openEditCategoryModal(id, name, markup, isActive) {
            const modal = document.getElementById('editCategoryModal');
            document.getElementById('edit_name').value = name;
            const markupInput = document.getElementById('edit_markup');
            markupInput.value = markup;
            document.getElementById('edit_is_active').checked = isActive;

            const form = document.getElementById('editCategoryForm');
            form.action = "{{ url('admin/selling-price/categories') }}/" + id;

            document.getElementById('edit_preview_markup').innerText = markup + '%';
            document.getElementById('edit_preview_retail').innerText = '₹' + calculateRetail(markup);

            markupInput.oninput = () => {
                const val = markupInput.value;
                document.getElementById('edit_preview_markup').innerText = (val || 0) + '%';
                document.getElementById('edit_preview_retail').innerText = '₹' + calculateRetail(val);
            };

            modal.classList.remove('hidden');
        }
        function closeEditCategoryModal() {
            document.getElementById('editCategoryModal').classList.add('hidden');
        }

        // Manage Products Modal
        function openManageProductsModal(id, name, markup, isActive) {
            currentCategoryId = id;
            selectedProductIds.clear();

            document.getElementById('mpCategoryTitle').innerText = name;
            document.getElementById('mpCategoryMarkup').innerText = markup + '%';
            document.getElementById('mpCategoryRetail').innerText = '₹' + calculateRetail(markup);

            const activeBadge = document.getElementById('mpCategoryActiveBadge');
            if (isActive) {
                activeBadge.className = 'rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 text-[10px] font-bold';
                activeBadge.innerText = 'Active Category';
            } else {
                activeBadge.className = 'rounded-md bg-slate-100 text-slate-600 border border-slate-200 px-2 py-0.5 text-[10px] font-bold';
                activeBadge.innerText = 'Inactive (Uses Global Fallback)';
            }

            document.getElementById('manageProductsModal').classList.remove('hidden');
            reloadAssignedProducts();
            doProductSearch('');
        }
        function closeManageProductsModal() {
            document.getElementById('manageProductsModal').classList.add('hidden');
            currentCategoryId = null;
            selectedProductIds.clear();
        }

        // Load Assigned Products
        function reloadAssignedProducts() {
            if (!currentCategoryId) return;
            const listEl = document.getElementById('assignedProductsList');
            listEl.innerHTML = '<div class="p-6 text-center text-xs text-slate-400">Loading assigned products...</div>';

            fetch(`{{ url('admin/selling-price/categories') }}/${currentCategoryId}/products`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                const products = data.products || [];
                document.getElementById('mpAssignedCountBadge').innerText = products.length;

                if (products.length === 0) {
                    listEl.innerHTML = '<div class="p-8 text-center text-xs text-slate-400 font-semibold">No products assigned yet. Use the search on the right to assign products.</div>';
                    return;
                }

                let html = '';
                products.forEach(p => {
                    html += `
                        <div class="flex items-center justify-between py-2 text-xs">
                            <div class="min-w-0 pr-2">
                                <div class="font-bold text-slate-900 truncate">${escapeHtml(p.name)}</div>
                                <div class="text-[10px] text-slate-400 font-medium">${escapeHtml(p.sku)} · ${escapeHtml(p.category_name)}</div>
                            </div>
                            <button type="button"
                                    onclick="removeProductFromCategory(${p.id}, '${escapeHtml(p.name)}')"
                                    class="shrink-0 text-[11px] font-bold text-rose-600 hover:text-rose-800 hover:underline px-2 py-1">
                                Remove
                            </button>
                        </div>
                    `;
                });
                listEl.innerHTML = html;
            })
            .catch(() => {
                listEl.innerHTML = '<div class="p-4 text-center text-xs text-rose-500">Failed to load assigned products.</div>';
            });
        }

        // Remove Single Product Assignment
        function removeProductFromCategory(productId, productName) {
            if (!currentCategoryId) return;
            if (!confirm(`Remove "${productName}" from this selling price category? It will revert to the global default markup.`)) return;

            fetch(`{{ url('admin/selling-price/categories') }}/${currentCategoryId}/remove-product`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ product_id: productId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    reloadAssignedProducts();
                    doProductSearch(document.getElementById('productSearchInput').value);
                } else {
                    alert(data.message || 'Error removing product.');
                }
            });
        }

        // Search Products to Assign
        document.getElementById('productSearchInput').addEventListener('input', function (e) {
            clearTimeout(searchDebounceTimer);
            const q = e.target.value.trim();
            searchDebounceTimer = setTimeout(() => doProductSearch(q), 250);
        });

        function doProductSearch(q) {
            if (!currentCategoryId) return;
            const resultsEl = document.getElementById('searchResultsList');

            fetch(`{{ url('admin/selling-price/categories') }}/${currentCategoryId}/search-products?q=${encodeURIComponent(q)}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                searchResultsData = data.data || [];
                renderSearchResults();
            });
        }

        function renderSearchResults() {
            const resultsEl = document.getElementById('searchResultsList');
            if (searchResultsData.length === 0) {
                resultsEl.innerHTML = '<div class="p-8 text-center text-xs text-slate-400 font-semibold">No active products match search query.</div>';
                updateSelectedCountUI();
                return;
            }

            let html = '';
            searchResultsData.forEach(p => {
                const isChecked = selectedProductIds.has(p.id);
                let badge = '';

                if (p.is_assigned_to_current) {
                    badge = `<span class="rounded bg-teal-50 text-teal-700 px-1.5 py-0.5 text-[9px] font-bold">Already Assigned</span>`;
                } else if (p.is_assigned_to_other) {
                    badge = `<span class="rounded bg-amber-50 text-amber-700 border border-amber-200 px-1.5 py-0.5 text-[9px] font-bold">In: ${escapeHtml(p.current_category_name || 'Other')}</span>`;
                } else {
                    badge = `<span class="rounded bg-slate-100 text-slate-500 px-1.5 py-0.5 text-[9px] font-semibold">Unassigned</span>`;
                }

                html += `
                    <label class="flex items-center gap-2.5 py-2 text-xs cursor-pointer hover:bg-slate-50 px-1 rounded-lg ${p.is_assigned_to_current ? 'opacity-50 cursor-default' : ''}">
                        <input type="checkbox"
                               value="${p.id}"
                               ${p.is_assigned_to_current ? 'disabled' : ''}
                               ${isChecked ? 'checked' : ''}
                               onchange="toggleProductSelection(${p.id}, this.checked)"
                               class="rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-bold text-slate-900 truncate">${escapeHtml(p.name)}</span>
                                ${badge}
                            </div>
                            <div class="text-[10px] text-slate-400 font-medium">${escapeHtml(p.sku)} · ${escapeHtml(p.category_name)}</div>
                        </div>
                    </label>
                `;
            });
            resultsEl.innerHTML = html;
            updateSelectedCountUI();
        }

        function toggleProductSelection(id, checked) {
            if (checked) {
                selectedProductIds.add(id);
            } else {
                selectedProductIds.delete(id);
            }
            updateSelectedCountUI();
        }

        function updateSelectedCountUI() {
            const count = selectedProductIds.size;
            document.getElementById('selectedCountText').innerText = `${count} selected`;
            document.getElementById('assignSelectedBtn').disabled = count === 0;

            // Check if any selected item is currently in ANOTHER category
            let hasConflicts = false;
            searchResultsData.forEach(p => {
                if (selectedProductIds.has(p.id) && p.is_assigned_to_other) {
                    hasConflicts = true;
                }
            });

            const warningBox = document.getElementById('reassignWarningBox');
            if (hasConflicts) {
                warningBox.classList.remove('hidden');
            } else {
                warningBox.classList.add('hidden');
                document.getElementById('confirmReassignCheckbox').checked = false;
            }
        }

        function submitProductAssignment() {
            if (selectedProductIds.size === 0 || !currentCategoryId) return;

            const confirmCheckbox = document.getElementById('confirmReassignCheckbox');
            const warningBox = document.getElementById('reassignWarningBox');

            if (!warningBox.classList.contains('hidden') && !confirmCheckbox.checked) {
                alert('Please confirm moving the selected products from their existing categories.');
                return;
            }

            const btn = document.getElementById('assignSelectedBtn');
            btn.disabled = true;
            btn.innerText = 'Assigning...';

            fetch(`{{ url('admin/selling-price/categories') }}/${currentCategoryId}/assign-products`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    product_ids: Array.from(selectedProductIds),
                    confirm_reassign: confirmCheckbox.checked
                })
            })
            .then(res => res.json())
            .then(data => {
                btn.innerText = 'Assign Selected';
                if (data.status === 'success') {
                    selectedProductIds.clear();
                    reloadAssignedProducts();
                    doProductSearch(document.getElementById('productSearchInput').value);
                } else if (data.status === 'requires_confirmation') {
                    warningBox.classList.remove('hidden');
                    confirmCheckbox.checked = false;
                    alert(data.message || 'Confirmation required before reassigning.');
                } else {
                    alert(data.message || 'Error assigning products.');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerText = 'Assign Selected';
                alert('An error occurred during assignment.');
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }
    </script>
</x-layouts.admin>

