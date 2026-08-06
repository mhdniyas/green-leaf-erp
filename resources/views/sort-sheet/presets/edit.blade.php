<x-dynamic-component component="layouts.printing" title="Edit Custom Preset — {{ $preset->name }}">
    <div class="max-w-[1600px] mx-auto space-y-6">

        {{-- Page Header --}}
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-amber-700" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                        </svg>
                    </div>
                    Edit Preset: {{ $preset->name }}
                </h1>
                <p class="text-xs text-slate-500 mt-1 ml-[52px]">
                    Update preset settings, category print order, and custom page break rules.
                </p>
            </div>
            <a href="{{ route('sort-sheet.presets.index') }}"
               class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition flex items-center gap-2 shrink-0">
                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                Back to Presets List
            </a>
        </div>

        {{-- Edit Form Card --}}
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-6">
            <form method="POST" action="{{ route('sort-sheet.presets.update', $preset) }}" class="space-y-6" id="edit-preset-form">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Preset Name *</label>
                        <input type="text" name="name" value="{{ old('name', $preset->name) }}" required
                               class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Default Format *</label>
                        <select name="surface" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                            <option value="sort-sheet" {{ $preset->surface === 'sort-sheet' ? 'selected' : '' }}>Sort Sheet</option>
                            <option value="segregation" {{ $preset->surface === 'segregation' ? 'selected' : '' }}>Selection</option>
                            <option value="portrait" {{ $preset->surface === 'portrait' ? 'selected' : '' }}>Shop Wise Portrait</option>
                            <option value="wide" {{ $preset->surface === 'wide' ? 'selected' : '' }}>Shop Wise Wide</option>
                            <option value="grid" {{ $preset->surface === 'grid' ? 'selected' : '' }}>Segregate Grid</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Warehouse (Optional)</label>
                        <select name="warehouse_id" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                            <option value="">All Warehouses</option>
                            @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ $preset->warehouse_id == $wh->id ? 'selected' : '' }}>{{ $wh->name }} ({{ $wh->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Shop Category (Optional)</label>
                        <select name="price_group_id" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                            <option value="">All Shop Categories</option>
                            @foreach($priceGroups as $pg)
                            <option value="{{ $pg->id }}" {{ $preset->price_group_id == $pg->id ? 'selected' : '' }}>{{ $pg->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Specific Shop (Optional)</label>
                        <select name="shop_id" class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 bg-white">
                            <option value="">All Shops</option>
                            @foreach($shops as $sh)
                            <option value="{{ $sh->id }}" {{ $preset->shop_id == $sh->id ? 'selected' : '' }}>{{ $sh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Category Priority Selector --}}
                @php
                    $existingCatIds = array_map('strval', $preset->category_ids ?? []);
                @endphp
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                        Category Print Sequence (Select in Order of Priority)
                    </label>
                    <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 space-y-3">
                        <p class="text-[11px] text-slate-500 font-medium">Click categories to select/re-order. Number badges (#1, #2, #3...) show the exact print priority sequence.</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2" id="preset-category-selector">
                            @foreach($categories as $cat)
                            @php
                                $catIdStr = (string) $cat->id;
                                $seqIndex = array_search($catIdStr, $existingCatIds, true);
                                $isChecked = $seqIndex !== false;
                            @endphp
                            <div class="preset-cat-card flex items-center gap-2 p-2.5 rounded-xl border {{ $isChecked ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 bg-white' }} cursor-pointer hover:border-emerald-400 transition text-xs font-semibold select-none" data-cat-id="{{ $cat->id }}" data-cat-name="{{ $cat->name }}">
                                <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" {{ $isChecked ? 'checked' : '' }} class="preset-cat-checkbox hidden">
                                <span class="preset-cat-badge flex min-w-7 h-5 px-1 shrink-0 items-center justify-center rounded-md {{ $isChecked ? 'border border-emerald-600 bg-emerald-600 text-white' : 'border border-slate-300 bg-white text-slate-400' }} text-[10px] font-black">
                                    {{ $isChecked ? '#'.($seqIndex + 1) : '' }}
                                </span>
                                <span class="truncate pointer-events-none">{{ $cat->name }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Page Break Rules --}}
                <div>
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <label class="block text-xs font-bold text-purple-700 uppercase tracking-wider">
                            Page Break Rules (Custom Printed Page Groupings)
                        </label>
                        <div class="flex items-center gap-2">
                            <button type="button" id="select-all-breaks-btn" class="text-[11px] font-bold text-purple-700 hover:text-purple-900 bg-purple-50 hover:bg-purple-100 px-2.5 py-1 rounded-lg border border-purple-200 transition">
                                Select All Breaks
                            </button>
                            <button type="button" id="clear-all-breaks-btn" class="text-[11px] font-bold text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-2.5 py-1 rounded-lg transition">
                                Clear Breaks
                            </button>
                        </div>
                    </div>
                    <div class="p-4 rounded-2xl border border-purple-200 bg-purple-50/30 space-y-4">
                        <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-800 cursor-pointer bg-white px-4 py-2.5 rounded-xl border border-purple-200 shadow-sm hover:border-purple-400 transition">
                            <input type="checkbox" name="separate_category_pages" id="separate-cat-pages-checkbox" value="1" {{ $preset->separate_category_pages ? 'checked' : '' }} class="rounded border-slate-300 text-purple-600 focus:ring-purple-500">
                            <span>Global Rule: Start EVERY category on a new printed page (1 Category = 1 Page)</span>
                        </label>

                        <div class="pt-3 border-t border-purple-100">
                            <p class="text-[11px] font-bold text-slate-700 mb-2">Or select specific categories after which a page break occurs (e.g. #1 alone on Page 1, #2 & #3 together on Page 2...):</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                                @php $existingBreakIds = array_map('strval', $preset->page_break_category_ids ?? []); @endphp
                                @foreach($categories as $cat)
                                @php $isBreakChecked = in_array((string) $cat->id, $existingBreakIds, true); @endphp
                                <div class="page-break-card flex items-center justify-between gap-2 p-2.5 rounded-xl border {{ $isBreakChecked ? 'border-purple-500 bg-purple-50' : 'border-slate-200 bg-white' }} cursor-pointer hover:border-purple-400 transition text-xs font-semibold select-none" data-cat-id="{{ $cat->id }}">
                                    <input type="checkbox" name="page_break_category_ids[]" value="{{ $cat->id }}" {{ $isBreakChecked ? 'checked' : '' }} class="page-break-checkbox hidden">
                                    <span class="truncate pointer-events-none text-slate-700 font-medium">Break after {{ $cat->name }}</span>
                                    <span class="page-break-badge text-[10px] font-black px-1.5 py-0.5 rounded {{ $isBreakChecked ? 'bg-purple-600 text-white' : 'bg-slate-100 text-slate-400' }}">
                                        {{ $isBreakChecked ? 'Break' : 'Cont.' }}
                                    </span>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Live Printed Page Layout Preview --}}
                        <div class="pt-3 border-t border-purple-100">
                            <span class="text-[10px] font-black uppercase tracking-wider text-purple-800 block mb-1.5">Live Printed Page Breakdown Preview:</span>
                            <div id="page-breakdown-preview" class="flex flex-wrap gap-2 text-xs">
                                {{-- Dynamically rendered by JS --}}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                    <a href="{{ route('sort-sheet.presets.index') }}"
                       class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-xs font-bold text-white shadow-md transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                        Update Preset
                    </button>
                </div>
            </form>
        </div>

    </div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.preset-cat-card');
    const breakCards = document.querySelectorAll('.page-break-card');
    const globalSepCheckbox = document.getElementById('separate-cat-pages-checkbox');
    const previewContainer = document.getElementById('page-breakdown-preview');

    const selectedSequence = @json($existingCatIds);

    const updateAllCards = () => {
        cards.forEach((card) => {
            const chk = card.querySelector('.preset-cat-checkbox');
            const badge = card.querySelector('.preset-cat-badge');
            const catId = String(card.dataset.catId);
            const seqIndex = selectedSequence.indexOf(catId);

            if (chk.checked && seqIndex !== -1) {
                card.className = 'preset-cat-card flex items-center gap-2 p-2.5 rounded-xl border border-emerald-500 bg-emerald-50 cursor-pointer hover:border-emerald-600 transition text-xs font-semibold select-none';
                badge.className = 'preset-cat-badge flex min-w-7 h-5 px-1 shrink-0 items-center justify-center rounded-md border border-emerald-600 bg-emerald-600 text-white text-[10px] font-black';
                badge.textContent = `#${seqIndex + 1}`;
            } else {
                card.className = 'preset-cat-card flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-white cursor-pointer hover:border-emerald-400 transition text-xs font-semibold select-none';
                badge.className = 'preset-cat-badge flex min-w-7 h-5 px-1 shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white text-[10px] font-black text-slate-400';
                badge.textContent = '';
            }
        });

        updatePageBreakdownPreview();
    };

    const updatePageBreakdownPreview = () => {
        if (!previewContainer) return;

        const activeCatCards = selectedSequence.map((catId) => {
            const card = Array.from(cards).find((c) => String(c.dataset.catId) === String(catId));
            return card ? { id: catId, name: card.dataset.catName } : null;
        }).filter(Boolean);

        if (activeCatCards.length === 0) {
            previewContainer.innerHTML = '<span class="text-slate-400 italic text-xs">No categories selected. Click categories above to select print sequence.</span>';
            return;
        }

        const isGlobalSep = globalSepCheckbox && globalSepCheckbox.checked;

        const breakCatIds = Array.from(breakCards)
            .filter((bc) => bc.querySelector('.page-break-checkbox').checked)
            .map((bc) => String(bc.dataset.catId));

        let pages = [];
        let currentPage = [];

        activeCatCards.forEach((cat) => {
            currentPage.push(cat.name);
            if (isGlobalSep || breakCatIds.includes(String(cat.id))) {
                pages.push(currentPage);
                currentPage = [];
            }
        });

        if (currentPage.length > 0) {
            pages.push(currentPage);
        }

        previewContainer.innerHTML = pages.map((pg, idx) => `
            <div class="inline-flex items-center gap-1.5 bg-white border border-purple-200 rounded-xl px-3 py-1.5 text-xs shadow-sm">
                <span class="font-black text-purple-700">Page ${idx + 1}:</span>
                <span class="font-bold text-slate-800">${pg.join(', ')}</span>
            </div>
        `).join('');
    };

    cards.forEach((card) => {
        card.addEventListener('click', (e) => {
            e.preventDefault();
            const chk = card.querySelector('.preset-cat-checkbox');
            const catId = String(card.dataset.catId);

            chk.checked = !chk.checked;

            if (chk.checked) {
                if (!selectedSequence.includes(catId)) {
                    selectedSequence.push(catId);
                }
            } else {
                const idx = selectedSequence.indexOf(catId);
                if (idx !== -1) {
                    selectedSequence.splice(idx, 1);
                }
            }

            updateAllCards();
        });
    });

    breakCards.forEach((bcard) => {
        bcard.addEventListener('click', (e) => {
            e.preventDefault();
            const chk = bcard.querySelector('.page-break-checkbox');
            const badge = bcard.querySelector('.page-break-badge');

            chk.checked = !chk.checked;

            if (chk.checked) {
                bcard.className = 'page-break-card flex items-center justify-between gap-2 p-2.5 rounded-xl border border-purple-500 bg-purple-50 cursor-pointer hover:border-purple-600 transition text-xs font-semibold select-none';
                badge.className = 'page-break-badge text-[10px] font-black px-1.5 py-0.5 rounded bg-purple-600 text-white';
                badge.textContent = 'Break';
            } else {
                bcard.className = 'page-break-card flex items-center justify-between gap-2 p-2.5 rounded-xl border border-slate-200 bg-white cursor-pointer hover:border-purple-400 transition text-xs font-semibold select-none';
                badge.className = 'page-break-badge text-[10px] font-black px-1.5 py-0.5 rounded bg-slate-100 text-slate-400';
                badge.textContent = 'Cont.';
            }

            updatePageBreakdownPreview();
        });
    });

    if (globalSepCheckbox) {
        globalSepCheckbox.addEventListener('change', updatePageBreakdownPreview);
    }

    document.getElementById('select-all-breaks-btn')?.addEventListener('click', () => {
        breakCards.forEach((bcard) => {
            const chk = bcard.querySelector('.page-break-checkbox');
            const badge = bcard.querySelector('.page-break-badge');
            chk.checked = true;
            bcard.className = 'page-break-card flex items-center justify-between gap-2 p-2.5 rounded-xl border border-purple-500 bg-purple-50 cursor-pointer hover:border-purple-600 transition text-xs font-semibold select-none';
            badge.className = 'page-break-badge text-[10px] font-black px-1.5 py-0.5 rounded bg-purple-600 text-white';
            badge.textContent = 'Break';
        });
        updatePageBreakdownPreview();
    });

    document.getElementById('clear-all-breaks-btn')?.addEventListener('click', () => {
        breakCards.forEach((bcard) => {
            const chk = bcard.querySelector('.page-break-checkbox');
            const badge = bcard.querySelector('.page-break-badge');
            chk.checked = false;
            bcard.className = 'page-break-card flex items-center justify-between gap-2 p-2.5 rounded-xl border border-slate-200 bg-white cursor-pointer hover:border-purple-400 transition text-xs font-semibold select-none';
            badge.className = 'page-break-badge text-[10px] font-black px-1.5 py-0.5 rounded bg-slate-100 text-slate-400';
            badge.textContent = 'Cont.';
        });
        updatePageBreakdownPreview();
    });

    updateAllCards();
});
</script>
@endpush
</x-dynamic-component>
