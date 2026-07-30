<x-layouts.app title="Daily Share Summary">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 py-3 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')

        {{-- Header --}}
        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_16px_36px_rgba(15,23,42,0.18)] lg:rounded-[2rem]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(45,212,191,0.28),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#14532d_100%)] px-4 py-5 lg:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-200">WhatsApp Share</p>
                        <h1 class="mt-1 text-xl font-black tracking-tight">Daily Share Summary</h1>
                        <p class="mt-1 text-sm font-medium text-slate-300">Filter and send demand to WhatsApp.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('purchaser.daily', ['date' => $date]) }}" class="inline-flex min-h-9 items-center justify-center rounded-xl border border-white/15 bg-white/10 px-4 text-sm font-black text-white">
                            ← Daily
                        </a>
                        <a href="{{ $shareUrl }}" target="_blank" rel="noopener" class="inline-flex min-h-9 items-center justify-center rounded-xl bg-emerald-500 px-4 text-sm font-black text-slate-950">
                            Share on WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
            {{-- Filter Panel --}}
            <section class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                <form action="{{ route('purchaser.daily.share') }}" method="GET" id="share-form">
                    <input type="hidden" name="date" value="{{ $date }}">

                    {{-- Mode + Date Header --}}
                    <div class="border-b border-slate-100 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                        {{-- Pill Tabs --}}
                        <div class="flex items-center gap-1 rounded-xl bg-slate-100 p-1" id="daily-share-mode-group">
                            @foreach ([
                                'all'     => 'All Products',
                                'tag'     => 'By Category',
                                'product' => 'Single Item',
                            ] as $modeValue => $modeLabel)
                                <label class="cursor-pointer" data-share-mode-card="{{ $modeValue }}">
                                    <input type="radio" name="share_mode" value="{{ $modeValue }}" class="sr-only" {{ $shareMode === $modeValue ? 'checked' : '' }}>
                                    <span class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-black transition-all
                                        {{ $shareMode === $modeValue
                                            ? 'bg-white text-slate-950 shadow-sm'
                                            : 'text-slate-500 hover:text-slate-700' }}">
                                        {{ $modeLabel }}
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        {{-- Date picker --}}
                        <input type="date" name="date" value="{{ $date }}"
                            class="h-9 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                    </div>

                    {{-- ===== ALL MODE ===== --}}
                    <div data-share-section="all" class="{{ $shareMode !== 'all' ? 'hidden' : '' }} px-4 py-4">
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                            All {{ $availableProducts->count() }} products will be included in the share.
                        </div>
                    </div>

                    {{-- ===== TAG / CATEGORY MODE ===== --}}
                    <div data-share-section="tag" class="{{ $shareMode !== 'tag' ? 'hidden' : '' }} px-4 py-4 space-y-4">

                        {{-- Category pill multi-select --}}
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500 mb-2">Filter by Category <span class="font-semibold normal-case text-slate-400">(select multiple)</span></p>
                            <div class="flex flex-wrap gap-1.5" data-multi-tag-filter-group>
                                @forelse ($availableTags as $tag)
                                    <label class="cursor-pointer" data-tag-label="{{ strtolower($tag) }}">
                                        <input type="checkbox" name="tags[]" value="{{ $tag }}" class="sr-only"
                                            {{ in_array($tag, $selectedTags, true) ? 'checked' : '' }}>
                                        <span class="tag-pill inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold transition-all
                                            {{ in_array($tag, $selectedTags, true)
                                                ? 'border-emerald-500 bg-emerald-100 text-emerald-800'
                                                : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-300' }}">
                                            {{ $tag }}
                                        </span>
                                    </label>
                                @empty
                                    <p class="text-sm font-semibold text-slate-400">No categories available for this date.</p>
                                @endforelse
                            </div>
                            @if ($availableTags->isNotEmpty())
                                <p class="mt-2 text-[10px] font-semibold text-slate-400">Leave all unchecked to share all categories.</p>
                            @endif
                        </div>

                        {{-- Product search + checkboxes --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Or Pick Specific Products</p>
                                <span class="text-[10px] font-semibold text-slate-400" id="selected-count">
                                    {{ count($selectedProductIds) > 0 ? count($selectedProductIds).' selected' : '' }}
                                </span>
                            </div>
                            <div class="relative mb-2">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                <input type="search" data-multi-product-search placeholder="Search products..."
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-3 py-2 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                            </div>
                            <div class="max-h-64 space-y-1 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-2" data-multi-product-list>
                                @forelse ($availableProducts as $product)
                                    <label
                                        data-multi-product-item
                                        data-category="{{ strtolower($product['category_name'] ?: 'no tag') }}"
                                        data-search="{{ $product['search_index'] }}"
                                        class="flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2 transition
                                            {{ in_array($product['product_id'], $selectedProductIds, true)
                                                ? 'border-emerald-500 bg-emerald-50'
                                                : 'border-transparent bg-white hover:border-slate-200' }}"
                                    >
                                        <input type="checkbox" name="product_ids[]" value="{{ $product['product_id'] }}"
                                            class="h-4 w-4 shrink-0 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                            {{ in_array($product['product_id'], $selectedProductIds, true) ? 'checked' : '' }}>
                                        <div class="min-w-0 flex-1 flex items-center justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="truncate text-xs font-black text-slate-900">{{ $product['product_name'] }}</p>
                                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $product['category_name'] ?: 'No Tag' }}</p>
                                            </div>
                                            <p class="shrink-0 text-xs font-black text-emerald-700">{{ number_format($product['remaining_qty'], 2) }} {{ $product['unit'] }}</p>
                                        </div>
                                    </label>
                                @empty
                                    <p class="px-3 py-4 text-center text-sm font-semibold text-slate-400">No products for this date.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- ===== SINGLE PRODUCT MODE ===== --}}
                    <div data-share-section="product" class="{{ $shareMode !== 'product' ? 'hidden' : '' }} px-4 py-4">
                        <div class="relative" data-single-product-picker>
                            <input type="hidden" name="product_id" value="{{ $selectedProductId }}" id="share-product-id">
                            <button type="button" data-single-product-trigger
                                class="flex min-h-12 w-full items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-left transition hover:border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                                <span class="min-w-0" data-single-product-label>
                                    @php $selectedSingleProduct = collect($availableProducts)->firstWhere('product_id', $selectedProductId); @endphp
                                    @if ($selectedSingleProduct)
                                        <span class="block truncate text-sm font-black text-slate-900">{{ $selectedSingleProduct['product_name'] }}</span>
                                        <span class="block text-[10px] font-bold uppercase text-slate-400">{{ $selectedSingleProduct['category_name'] ?: 'No Tag' }}</span>
                                    @else
                                        <span class="block text-sm font-black text-slate-400">Select one product…</span>
                                    @endif
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                            </button>

                            <div data-single-product-panel class="absolute left-0 right-0 z-30 mt-1 hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                                <div class="border-b border-slate-100 p-2">
                                    <input type="search" data-single-product-search placeholder="Search product..."
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                                </div>
                                <div class="max-h-64 overflow-y-auto p-1.5">
                                    @foreach ($availableProducts as $product)
                                        <button type="button"
                                            data-single-product-option
                                            data-value="{{ $product['product_id'] }}"
                                            data-search="{{ $product['search_index'] }}"
                                            data-product-name="{{ $product['product_name'] }}"
                                            data-category-name="{{ $product['category_name'] ?: 'No Tag' }}"
                                            class="flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2 text-left transition
                                                {{ $selectedProductId === $product['product_id'] ? 'bg-emerald-50 text-emerald-700' : 'text-slate-700 hover:bg-slate-50' }}">
                                            <span class="min-w-0">
                                                <span class="block truncate text-xs font-black">{{ $product['product_name'] }}</span>
                                                <span class="block text-[10px] font-bold uppercase text-slate-400">{{ $product['category_name'] ?: 'No Tag' }}</span>
                                            </span>
                                            <span class="shrink-0 text-xs font-black text-emerald-700">{{ number_format($product['remaining_qty'], 2) }} {{ $product['unit'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Action buttons --}}
                    <div class="border-t border-slate-100 px-4 py-3 flex items-center gap-2">
                        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-xl bg-slate-950 px-5 text-xs font-black text-white hover:bg-slate-800 transition-colors">
                            Apply Filter
                        </button>
                        <a href="{{ route('purchaser.daily.share', ['date' => $date, 'share_mode' => 'all']) }}"
                            class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 px-4 text-xs font-black text-slate-700 hover:bg-slate-50 transition-colors">
                            Reset
                        </a>
                        @if ($shareSummary->isNotEmpty())
                            <a href="{{ $shareUrl }}" target="_blank" rel="noopener"
                                class="ml-auto inline-flex h-9 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-xs font-black text-white hover:bg-emerald-500 transition-colors">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M11.998 2.166C6.525 2.166 2.09 6.6 2.09 12.073c0 1.742.455 3.378 1.25 4.793L2 22l5.292-1.387c1.36.74 2.912 1.162 4.566 1.162 5.472 0 9.908-4.433 9.908-9.905 0-5.474-4.436-9.704-9.768-9.704z"/></svg>
                                Share Now
                            </a>
                        @endif
                    </div>
                </form>
            </section>

            {{-- Preview Panel --}}
            <section class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
                <div class="border-b border-slate-100 px-4 py-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Preview</p>
                        <p class="mt-0.5 text-xs font-semibold text-slate-600">
                            {{ $shareSummary->count() }} {{ Str::plural('product', $shareSummary->count()) }} · {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}
                        </p>
                    </div>
                    @if ($shareSummary->isNotEmpty())
                        <a href="{{ $shareUrl }}" target="_blank" rel="noopener"
                            class="inline-flex h-8 items-center justify-center rounded-xl bg-emerald-600 px-3 text-xs font-black text-white hover:bg-emerald-500">
                            WhatsApp
                        </a>
                    @endif
                </div>

                {{-- Share text preview --}}
                <div class="px-4 py-3">
                    @if ($shareSummary->isEmpty())
                        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center">
                            <p class="text-sm font-black text-slate-400">No products match</p>
                            <p class="mt-1 text-xs font-semibold text-slate-400">Adjust the filter and apply.</p>
                        </div>
                    @else
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <pre class="whitespace-pre-wrap break-words font-mono text-[11px] leading-5 text-slate-700">{{ $sharePreviewText }}</pre>
                        </div>

                        <div class="mt-3 space-y-1.5">
                            @foreach ($shareSummary as $summary)
                                <div class="flex items-center justify-between gap-2 rounded-xl border border-slate-100 bg-white px-3 py-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-black text-slate-900">{{ $summary['product_name'] }}</p>
                                        <p class="text-[10px] font-bold uppercase text-slate-400">{{ $summary['category_name'] ?: 'No Tag' }}</p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="text-xs font-black text-emerald-700">{{ number_format((float) $summary['remaining_qty'], 2) }} {{ $summary['unit'] }}</p>
                                        @php
                                            $directCount = collect($summary['shop_details'])->where('is_direct_purchase', true)->count();
                                            $shopCount = count($summary['shop_details']) - $directCount;
                                        @endphp
                                        <p class="text-[10px] text-slate-400">{{ $directCount > 0 ? 'Direct + ' : '' }}{{ $shopCount }} shops</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // ── Mode pill tabs ──────────────────────────────────────────────
        const modeInputs = Array.from(document.querySelectorAll('input[name="share_mode"]'));
        const modeCards  = Array.from(document.querySelectorAll('[data-share-mode-card]'));
        const sections   = Array.from(document.querySelectorAll('[data-share-section]'));

        const updateModeUI = () => {
            const selected = modeInputs.find(i => i.checked)?.value ?? 'all';
            modeCards.forEach(card => {
                const span = card.querySelector('span');
                const isActive = card.dataset.shareModeCard === selected;
                span?.classList.toggle('bg-white', isActive);
                span?.classList.toggle('shadow-sm', isActive);
                span?.classList.toggle('text-slate-950', isActive);
                span?.classList.toggle('text-slate-500', !isActive);
            });
            sections.forEach(s => {
                s.classList.toggle('hidden', s.dataset.shareSection !== selected);
            });
            // Enable/disable inputs
            document.querySelectorAll('input[name="product_ids[]"]').forEach(i => { i.disabled = selected !== 'tag'; });
            document.querySelectorAll('input[name="tags[]"]').forEach(i => { i.disabled = selected !== 'tag'; });
            const pid = document.getElementById('share-product-id');
            if (pid) pid.disabled = selected !== 'product';
        };

        modeCards.forEach(card => {
            card.addEventListener('click', () => {
                const radio = card.querySelector('input[type="radio"]');
                if (radio) { radio.checked = true; updateModeUI(); }
            });
        });
        updateModeUI();

        // ── Category tag pills multi-select ────────────────────────────
        document.querySelectorAll('[data-multi-tag-filter-group] label').forEach(label => {
            label.addEventListener('click', () => {
                const cb = label.querySelector('input[type="checkbox"]');
                if (!cb) return;
                // toggle is handled by browser; update pill style after
                requestAnimationFrame(() => {
                    const pill = label.querySelector('.tag-pill');
                    if (cb.checked) {
                        pill?.classList.replace('border-slate-200', 'border-emerald-500');
                        pill?.classList.replace('bg-slate-50', 'bg-emerald-100');
                        pill?.classList.replace('text-slate-600', 'text-emerald-800');
                    } else {
                        pill?.classList.replace('border-emerald-500', 'border-slate-200');
                        pill?.classList.replace('bg-emerald-100', 'bg-slate-50');
                        pill?.classList.replace('text-emerald-800', 'text-slate-600');
                    }
                    filterProductsByActiveTags();
                });
            });
        });

        const filterProductsByActiveTags = () => {
            const activeTags = Array.from(document.querySelectorAll('[data-multi-tag-filter-group] input:checked'))
                .map(cb => cb.value.toLowerCase());
            const search = (document.querySelector('[data-multi-product-search]')?.value ?? '').trim().toLowerCase();

            document.querySelectorAll('[data-multi-product-item]').forEach(item => {
                const cat  = item.dataset.category ?? '';
                const srch = item.dataset.search ?? '';
                const matchTag  = activeTags.length === 0 || activeTags.includes(cat);
                const matchSrch = search === '' || srch.includes(search);
                item.classList.toggle('hidden', !(matchTag && matchSrch));
            });

            const count = document.querySelectorAll('input[name="product_ids[]"]:checked').length;
            const el = document.getElementById('selected-count');
            if (el) el.textContent = count > 0 ? count + ' selected' : '';
        };

        // ── Product search in multi-mode ───────────────────────────────
        document.querySelector('[data-multi-product-search]')?.addEventListener('input', filterProductsByActiveTags);

        // Checkbox visual toggle
        document.querySelectorAll('[data-multi-product-item]').forEach(item => {
            const cb = item.querySelector('input[type="checkbox"]');
            cb?.addEventListener('change', () => {
                item.classList.toggle('border-emerald-500', cb.checked);
                item.classList.toggle('bg-emerald-50', cb.checked);
                item.classList.toggle('border-transparent', !cb.checked);
                item.classList.toggle('bg-white', !cb.checked);
                const count = document.querySelectorAll('input[name="product_ids[]"]:checked').length;
                const el = document.getElementById('selected-count');
                if (el) el.textContent = count > 0 ? count + ' selected' : '';
            });
        });

        // ── Single product picker ──────────────────────────────────────
        const singleInput  = document.getElementById('share-product-id');
        const singlePanel  = document.querySelector('[data-single-product-panel]');
        const singleLabel  = document.querySelector('[data-single-product-label]');
        const singleSearch = document.querySelector('[data-single-product-search]');

        document.querySelector('[data-single-product-trigger]')?.addEventListener('click', () => {
            singlePanel?.classList.toggle('hidden');
            if (!singlePanel?.classList.contains('hidden')) singleSearch?.focus();
        });

        singleSearch?.addEventListener('input', () => {
            const val = singleSearch.value.trim().toLowerCase();
            document.querySelectorAll('[data-single-product-option]').forEach(opt => {
                opt.classList.toggle('hidden', val !== '' && !(opt.dataset.search ?? '').includes(val));
            });
        });

        document.querySelectorAll('[data-single-product-option]').forEach(opt => {
            opt.addEventListener('click', () => {
                if (singleInput) singleInput.value = opt.dataset.value ?? '0';
                if (singleLabel) singleLabel.innerHTML = `
                    <span class="block truncate text-sm font-black text-slate-900">${opt.dataset.productName}</span>
                    <span class="block text-[10px] font-bold uppercase text-slate-400">${opt.dataset.categoryName}</span>
                `;
                document.querySelectorAll('[data-single-product-option]').forEach(o => {
                    o.classList.toggle('bg-emerald-50', o === opt);
                    o.classList.toggle('text-emerald-700', o === opt);
                    o.classList.toggle('text-slate-700', o !== opt);
                });
                singlePanel?.classList.add('hidden');
            });
        });

        document.addEventListener('click', e => {
            if (!e.target.closest('[data-single-product-picker]')) singlePanel?.classList.add('hidden');
        });

        // Run initial filter
        filterProductsByActiveTags();
    });
    </script>
</x-layouts.app>
