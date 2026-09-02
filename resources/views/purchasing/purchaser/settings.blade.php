<x-layouts.app title="Purchaser Settings">
    <div class="mx-auto max-w-4xl space-y-6 pb-24">
        {{-- Header --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs sm:p-6 lg:rounded-[2rem]">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-teal-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-teal-800">Purchaser Preferences</span>
                        <span class="text-xs font-semibold text-slate-500">Account: {{ $user->name }}</span>
                    </div>
                    <h1 class="mt-2 text-xl font-black tracking-tight text-slate-950 sm:text-2xl lg:text-3xl">Order Category Selection</h1>
                    <p class="mt-1 text-xs font-semibold text-slate-500 sm:text-sm">Customize which categories are displayed on your dashboard, daily demand, bulk buy, and add-ons screens.</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <a href="{{ route('purchaser.daily', ['date' => $date]) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-xs font-black text-slate-700 transition hover:bg-slate-100">
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800 shadow-xs">
                ✓ {{ session('status') }}
            </div>
        @endif

        {{-- Form card --}}
        <form action="{{ route('purchaser.settings.update') }}" method="POST" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6 lg:rounded-[2rem]">
            @csrf
            
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                <div>
                    <h2 class="text-base font-black text-slate-900">Assigned Categories</h2>
                    <p class="text-xs font-semibold text-slate-500">Uncheck all categories to display ALL items by default.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="selectAllCategories(true)" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-100">
                        Select All
                    </button>
                    <button type="button" onclick="selectAllCategories(false)" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-100">
                        Clear Selection
                    </button>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($categories as $category)
                    @php
                        $isChecked = in_array((int) $category->id, $assignedCategoryIds, true);
                    @endphp
                    <label class="group relative flex cursor-pointer items-center gap-3.5 rounded-xl border border-slate-200 p-3.5 transition-all hover:border-teal-500 hover:bg-teal-50/20 has-checked:border-teal-600 has-checked:bg-teal-50/50">
                        <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" {{ $isChecked ? 'checked' : '' }} class="category-checkbox h-4 w-4 rounded-md border-slate-300 text-teal-600 focus:ring-teal-500">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-black text-slate-900 group-hover:text-teal-900">{{ $category->name }}</p>
                            @if ($category->description)
                                <p class="truncate text-[11px] font-semibold text-slate-500">{{ $category->description }}</p>
                            @endif
                        </div>
                    </label>
                @endforeach
            </div>

            {{-- Vendor / Supplier Visibility Section --}}
            <div class="mt-8 border-t border-slate-100 pt-6">
                <div class="mb-4">
                    <h2 class="text-base font-black text-slate-900">Vendor / Supplier Visibility</h2>
                    <p class="text-xs font-semibold text-slate-500">Controls which suppliers this purchaser can view and select throughout purchaser workflows.</p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="group relative flex cursor-pointer items-start gap-3.5 rounded-xl border border-slate-200 p-4 transition-all hover:border-teal-500 hover:bg-teal-50/20 has-checked:border-teal-600 has-checked:bg-teal-50/50">
                        <input type="radio" name="vendor_visibility" value="all" {{ ($vendorVisibility ?? 'all') === 'all' ? 'checked' : '' }} class="mt-0.5 h-4 w-4 border-slate-300 text-teal-600 focus:ring-teal-500">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-black text-slate-900 group-hover:text-teal-900">Show all vendors</p>
                            <p class="mt-0.5 text-[11px] font-semibold text-slate-500">Purchaser can see every active vendor/supplier in the system.</p>
                        </div>
                    </label>

                    <label class="group relative flex cursor-pointer items-start gap-3.5 rounded-xl border border-slate-200 p-4 transition-all hover:border-teal-500 hover:bg-teal-50/20 has-checked:border-teal-600 has-checked:bg-teal-50/50">
                        <input type="radio" name="vendor_visibility" value="related" {{ ($vendorVisibility ?? 'all') === 'related' ? 'checked' : '' }} class="mt-0.5 h-4 w-4 border-slate-300 text-teal-600 focus:ring-teal-500">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-black text-slate-900 group-hover:text-teal-900">Show only related suppliers</p>
                            <p class="mt-0.5 text-[11px] font-semibold text-slate-500">Restrict view to suppliers assigned via category products or purchase history.</p>
                        </div>
                    </label>
                </div>
            </div>

            <div class="mt-8 flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                <a href="{{ route('purchaser.daily', ['date' => $date]) }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 px-5 text-xs font-black text-slate-700 hover:bg-slate-50">
                    Cancel
                </a>
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-teal-600 px-6 text-xs font-black uppercase tracking-wider text-white shadow-sm transition hover:bg-teal-700 focus:outline-none">
                    Save Preferences
                </button>
            </div>
        </form>
    </div>

    <script>
        function selectAllCategories(state) {
            document.querySelectorAll('.category-checkbox').forEach(cb => {
                cb.checked = state;
            });
        }
    </script>
</x-layouts.app>
