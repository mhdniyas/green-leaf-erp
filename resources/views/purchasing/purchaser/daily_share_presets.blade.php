<x-layouts.app title="Daily Share Presets">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 py-3 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')

        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_16px_36px_rgba(15,23,42,0.18)] lg:rounded-[2rem]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(45,212,191,0.28),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#14532d_100%)] px-4 py-5 lg:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-200">Grade {{ $purchaseGrade }} Product Presets</p>
                        <h1 class="mt-1 text-xl font-black tracking-tight">Daily Share Presets</h1>
                        <p class="mt-1 text-sm font-medium text-slate-300">Create named product groups from all active products and load them in one click.</p>
                    </div>
                    <a href="{{ route('purchaser.daily.share', ['date' => $date, 'purchase_grade' => $purchaseGrade, 'share_mode' => 'tag']) }}" class="inline-flex min-h-9 items-center justify-center rounded-xl border border-white/15 bg-white/10 px-4 text-sm font-black text-white hover:bg-white/20 transition-colors">
                        ← Back To Daily Share
                    </a>
                </div>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                <div class="border-b border-slate-100 px-4 py-3">
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Create Or Update Preset</p>
                </div>

                <form action="{{ route('purchaser.daily.share.presets.store') }}" method="POST" class="space-y-4 px-4 py-4">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date }}">
                    <input type="hidden" name="purchase_grade" value="{{ $purchaseGrade }}">

                    <div>
                        <label for="preset-name" class="mb-1 block text-xs font-black text-slate-700">Preset Name</label>
                        <input id="preset-name" name="name" type="text" required maxlength="80" placeholder="Example: Morning Veg Core"
                            class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none"
                            value="{{ old('name') }}">
                        <p class="mt-1 text-[10px] font-semibold text-slate-400">Using an existing name will update that preset.</p>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Select Products</p>
                            <span id="preset-selected-count" class="text-[10px] font-semibold text-slate-400">0 selected</span>
                        </div>

                        <div class="relative mb-2">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            <input type="search" id="preset-product-search" placeholder="Search product..."
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                        </div>

                        <div class="mb-2 flex flex-wrap gap-2">
                            <button type="button" id="preset-select-visible" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 px-3 text-[11px] font-black text-slate-700 hover:bg-slate-50">Select Visible</button>
                            <button type="button" id="preset-clear-all" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 px-3 text-[11px] font-black text-slate-700 hover:bg-slate-50">Clear All</button>
                        </div>

                        <div id="preset-product-list" class="max-h-80 space-y-1 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-2">
                            @forelse ($availableProducts as $product)
                                <label
                                    data-preset-product-item
                                    data-search="{{ $product['search_index'] }}"
                                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-transparent bg-white px-3 py-2 transition hover:border-slate-200"
                                >
                                    <input type="checkbox" name="product_ids[]" value="{{ $product['product_id'] }}"
                                        class="h-4 w-4 shrink-0 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <div class="min-w-0 flex-1 flex items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-black text-slate-900">{{ $product['product_name'] }}</p>
                                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $product['category_name'] ?: 'No Tag' }}</p>
                                        </div>
                                        <p class="shrink-0 text-xs font-black text-emerald-700">{{ number_format($product['remaining_qty'], 2) }} {{ $product['unit'] }}</p>
                                    </div>
                                </label>
                            @empty
                                <p class="px-3 py-4 text-center text-sm font-semibold text-slate-400">No active products available.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-3">
                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-950 px-5 text-xs font-black text-white hover:bg-slate-800 transition-colors">
                            Save Preset And Load In Share
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                <div class="border-b border-slate-100 px-4 py-3">
                    <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Existing Presets</p>
                </div>

                <div class="space-y-2 px-4 py-4">
                    @forelse ($sharePresets as $preset)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-black text-slate-900">{{ $preset->name }}</p>
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ count($preset->product_ids ?? []) }} products</p>
                                </div>
                                <a href="{{ route('purchaser.daily.share', ['date' => $date, 'purchase_grade' => $purchaseGrade, 'share_mode' => 'tag', 'preset_id' => $preset->id]) }}"
                                    class="inline-flex h-8 items-center justify-center rounded-lg bg-emerald-600 px-3 text-[11px] font-black text-white hover:bg-emerald-500 transition-colors">
                                    Load Items
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center">
                            <p class="text-sm font-black text-slate-400">No presets yet</p>
                            <p class="mt-1 text-xs font-semibold text-slate-400">Create one from the left panel.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('preset-product-search');
        const countEl = document.getElementById('preset-selected-count');
        const productItems = Array.from(document.querySelectorAll('[data-preset-product-item]'));
        const checkboxes = Array.from(document.querySelectorAll('input[name="product_ids[]"]'));
        const selectVisibleButton = document.getElementById('preset-select-visible');
        const clearAllButton = document.getElementById('preset-clear-all');

        const updateCount = () => {
            const count = checkboxes.filter(cb => cb.checked).length;
            if (countEl) {
                countEl.textContent = count + ' selected';
            }
        };

        const updateItemState = (item, checked) => {
            item.classList.toggle('border-emerald-500', checked);
            item.classList.toggle('bg-emerald-50', checked);
            item.classList.toggle('border-transparent', !checked);
            item.classList.toggle('bg-white', !checked);
        };

        const filterItems = () => {
            const query = (searchInput?.value ?? '').trim().toLowerCase();
            productItems.forEach(item => {
                const match = query === '' || (item.dataset.search ?? '').includes(query);
                item.classList.toggle('hidden', !match);
            });
        };

        checkboxes.forEach(cb => {
            cb.addEventListener('change', () => {
                const item = cb.closest('[data-preset-product-item]');
                if (item) {
                    updateItemState(item, cb.checked);
                }
                updateCount();
            });
        });

        selectVisibleButton?.addEventListener('click', () => {
            productItems.forEach(item => {
                if (item.classList.contains('hidden')) {
                    return;
                }
                const cb = item.querySelector('input[type="checkbox"]');
                if (!cb) {
                    return;
                }
                cb.checked = true;
                updateItemState(item, true);
            });
            updateCount();
        });

        clearAllButton?.addEventListener('click', () => {
            checkboxes.forEach(cb => {
                cb.checked = false;
                const item = cb.closest('[data-preset-product-item]');
                if (item) {
                    updateItemState(item, false);
                }
            });
            updateCount();
        });

        searchInput?.addEventListener('input', filterItems);

        updateCount();
        filterItems();
    });
    </script>
</x-layouts.app>
