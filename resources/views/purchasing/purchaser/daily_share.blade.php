<x-layouts.app title="Daily Share Summary">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 py-3 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')

        {{-- Header --}}
        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_16px_36px_rgba(15,23,42,0.18)] lg:rounded-[2rem]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(45,212,191,0.28),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#14532d_100%)] px-4 py-5 lg:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-200">Grade {{ $purchaseGrade }} WhatsApp Share</p>
                        <h1 class="mt-1 text-xl font-black tracking-tight">{{ $purchaseGrade === 'B' ? 'B Grade ' : '' }}Daily Share Summary</h1>
                        <p class="mt-1 text-sm font-medium text-slate-300">Filter and send demand to WhatsApp.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ $purchaseGrade === 'B' ? route('purchaser.b-grade', ['date' => $date]) : route('purchaser.daily', ['date' => $date]) }}" class="inline-flex min-h-9 items-center justify-center rounded-xl border border-white/15 bg-white/10 px-4 text-sm font-black text-white hover:bg-white/20 transition-colors">
                            ← Daily
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
                    <input type="hidden" name="purchase_grade" value="{{ $purchaseGrade }}">

                    {{-- Mode + Date Header --}}
                    <div class="border-b border-slate-100 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                        {{-- Pill Tabs --}}
                        <div class="flex items-center gap-1 rounded-xl bg-slate-100 p-1" id="daily-share-mode-group">
                            @foreach ([
                                'presets' => 'Presets',
                                'custom'  => 'Custom',
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

                    {{-- ===== PRESETS MODE ===== --}}
                    <div data-share-section="presets" class="{{ $shareMode !== 'presets' ? 'hidden' : '' }} px-4 py-4 space-y-4">
                        <div>
                            <p class="mb-2 text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Preset Type</p>
                            <div class="flex flex-wrap items-center gap-1 rounded-xl bg-slate-100 p-1" id="daily-share-preset-group">
                                @foreach ([
                                    'changed' => 'Changed',
                                    'any' => 'Any',
                                    'tag' => 'By Category',
                                ] as $presetValue => $presetLabel)
                                    <label class="cursor-pointer" data-preset-mode-card="{{ $presetValue }}">
                                        <input type="radio" name="preset_mode" value="{{ $presetValue }}" class="sr-only" {{ $selectedPresetMode === $presetValue ? 'checked' : '' }}>
                                        <span class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-black transition-all
                                            {{ $selectedPresetMode === $presetValue
                                                ? 'bg-white text-slate-950 shadow-sm'
                                                : 'text-slate-500 hover:text-slate-700' }}">
                                            {{ $presetLabel }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- ===== PRESET ANY ===== --}}
                        <div data-preset-section="any" class="{{ $selectedPresetMode !== 'any' ? 'hidden' : '' }}">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                                Any product from today demand can be shared. {{ $availableProducts->count() }} products will be included.
                            </div>
                        </div>

                        {{-- ===== PRESET CHANGED ===== --}}
                        <div data-preset-section="changed" class="{{ $selectedPresetMode !== 'changed' ? 'hidden' : '' }}">
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
                                Only products still pending after cart quantities will be included.
                            </div>
                        </div>

                        {{-- ===== PRESET TAG / CATEGORY ===== --}}
                        <div data-preset-section="tag" class="{{ $selectedPresetMode !== 'tag' ? 'hidden' : '' }}">

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
                        </div>
                    </div>

                    {{-- ===== CUSTOM MODE ===== --}}
                    <div data-share-section="custom" class="{{ $shareMode !== 'custom' ? 'hidden' : '' }} px-4 py-4">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Pick Specific Products</p>
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

                    {{-- Action buttons --}}
                    <div class="border-t border-slate-100 px-4 py-3 flex flex-wrap items-center gap-2">
                        <button type="submit" class="inline-flex h-9 items-center justify-center rounded-xl bg-slate-950 px-5 text-xs font-black text-white hover:bg-slate-800 transition-colors">
                            Apply Filter
                        </button>

                        <a href="{{ $shareTotalUrl }}" target="_blank" rel="noopener"
                            class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 text-xs font-black text-white hover:bg-blue-500 transition-colors shadow-xs">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2a10 10 0 0 0-8.58 15.13L2 22l5.05-1.33A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.14-1.15l-.3-.18-3.08.81.82-3-.2-.32A8 8 0 1 1 12 20zm4.4-6c-.24-.12-1.42-.7-1.64-.78-.22-.08-.38-.12-.54.12s-.62.78-.76.94-.28.18-.52.06a6.54 6.54 0 0 1-1.93-1.19 7.22 7.22 0 0 1-1.34-1.67c-.14-.24 0-.36.12-.48s.24-.28.36-.42.16-.24.24-.4.04-.3-.02-.42c-.06-.12-.54-1.3-.74-1.78-.2-.48-.4-.42-.54-.42h-.46a.89.89 0 0 0-.64.3 2.7 2.7 0 0 0-.84 2c0 1.18.86 2.32.98 2.48s1.69 2.58 4.1 3.62c.57.25 1.02.4 1.37.51.58.18 1.1.16 1.52.1.46-.07 1.42-.58 1.62-1.14s.2-.1.14-.24c-.06-.12-.24-.2-.48-.32z"/>
                            </svg>
                            <span>Total Qty</span>
                        </a>

                        <a href="{{ $shareUrl }}" target="_blank" rel="noopener"
                            class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 text-xs font-black text-white hover:bg-emerald-500 transition-colors shadow-xs">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2a10 10 0 0 0-8.58 15.13L2 22l5.05-1.33A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.14-1.15l-.3-.18-3.08.81.82-3-.2-.32A8 8 0 1 1 12 20zm4.4-6c-.24-.12-1.42-.7-1.64-.78-.22-.08-.38-.12-.54.12s-.62.78-.76.94-.28.18-.52.06a6.54 6.54 0 0 1-1.93-1.19 7.22 7.22 0 0 1-1.34-1.67c-.14-.24 0-.36.12-.48s.24-.28.36-.42.16-.24.24-.4.04-.3-.02-.42c-.06-.12-.54-1.3-.74-1.78-.2-.48-.4-.42-.54-.42h-.46a.89.89 0 0 0-.64.3 2.7 2.7 0 0 0-.84 2c0 1.18.86 2.32.98 2.48s1.69 2.58 4.1 3.62c.57.25 1.02.4 1.37.51.58.18 1.1.16 1.52.1.46-.07 1.42-.58 1.62-1.14s.2-.1.14-.24c-.06-.12-.24-.2-.48-.32z"/>
                            </svg>
                            <span>Selection</span>
                        </a>

                        <a href="{{ route('purchaser.daily.share', ['date' => $date, 'purchase_grade' => $purchaseGrade, 'share_mode' => 'presets', 'preset_mode' => 'changed']) }}"
                            class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 px-4 text-xs font-black text-slate-700 hover:bg-slate-50 transition-colors">
                            Reset
                        </a>
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
                </div>

                {{-- Share text preview --}}
                <div class="px-4 py-3">
                    @if ($shareSummary->isEmpty())
                        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center">
                            <p class="text-sm font-black text-slate-400">No products match</p>
                            <p class="mt-1 text-xs font-semibold text-slate-400">Adjust the filter and apply.</p>
                        </div>
                    @else
                        {{-- Preview format switcher --}}
                        <div class="mb-3 flex items-center gap-1 rounded-xl bg-slate-100 p-1 w-fit">
                            <button type="button" id="tab-preview-detailed" onclick="switchPreviewFormat('detailed')"
                                class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-black transition-all bg-white text-slate-950 shadow-xs">
                                Selection Format
                            </button>
                            <button type="button" id="tab-preview-total" onclick="switchPreviewFormat('total')"
                                class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-black transition-all text-slate-500 hover:text-slate-700">
                                Total Qty Format
                            </button>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <pre id="preview-text-detailed" class="whitespace-pre-wrap break-words font-mono text-[11px] leading-5 text-slate-700">{{ $sharePreviewText }}</pre>
                            <pre id="preview-text-total" class="hidden whitespace-pre-wrap break-words font-mono text-[11px] leading-5 text-slate-700">{{ $shareTotalPreviewText }}</pre>
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
    window.switchPreviewFormat = function(format) {
        const isTotal = format === 'total';
        document.getElementById('preview-text-detailed')?.classList.toggle('hidden', isTotal);
        document.getElementById('preview-text-total')?.classList.toggle('hidden', !isTotal);

        const btnDetailed = document.getElementById('tab-preview-detailed');
        const btnTotal = document.getElementById('tab-preview-total');
        const mainShareBtn = document.getElementById('main-share-btn');

        if (mainShareBtn) {
            mainShareBtn.href = isTotal ? @json($shareTotalUrl) : @json($shareUrl);
        }

        if (btnDetailed && btnTotal) {
            btnDetailed.classList.toggle('bg-white', !isTotal);
            btnDetailed.classList.toggle('text-slate-950', !isTotal);
            btnDetailed.classList.toggle('shadow-xs', !isTotal);
            btnDetailed.classList.toggle('text-slate-500', isTotal);

            btnTotal.classList.toggle('bg-white', isTotal);
            btnTotal.classList.toggle('text-slate-950', isTotal);
            btnTotal.classList.toggle('shadow-xs', isTotal);
            btnTotal.classList.toggle('text-slate-500', !isTotal);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        const modeInputs = Array.from(document.querySelectorAll('input[name="share_mode"]'));
        const modeCards = Array.from(document.querySelectorAll('[data-share-mode-card]'));
        const shareSections = Array.from(document.querySelectorAll('[data-share-section]'));

        const presetInputs = Array.from(document.querySelectorAll('input[name="preset_mode"]'));
        const presetCards = Array.from(document.querySelectorAll('[data-preset-mode-card]'));
        const presetSections = Array.from(document.querySelectorAll('[data-preset-section]'));

        const customSearch = document.querySelector('[data-multi-product-search]');
        const customItems = Array.from(document.querySelectorAll('[data-multi-product-item]'));

        const updateSelectedCount = () => {
            const count = document.querySelectorAll('input[name="product_ids[]"]:checked').length;
            const el = document.getElementById('selected-count');
            if (el) {
                el.textContent = count > 0 ? count + ' selected' : '';
            }
        };

        const filterCustomProducts = () => {
            const search = (customSearch?.value ?? '').trim().toLowerCase();
            customItems.forEach(item => {
                const srch = item.dataset.search ?? '';
                item.classList.toggle('hidden', search !== '' && !srch.includes(search));
            });
        };

        const updatePresetUI = () => {
            const selectedPreset = presetInputs.find(i => i.checked)?.value ?? 'changed';

            presetCards.forEach(card => {
                const span = card.querySelector('span');
                const isActive = card.dataset.presetModeCard === selectedPreset;
                span?.classList.toggle('bg-white', isActive);
                span?.classList.toggle('shadow-sm', isActive);
                span?.classList.toggle('text-slate-950', isActive);
                span?.classList.toggle('text-slate-500', !isActive);
            });

            presetSections.forEach(section => {
                section.classList.toggle('hidden', section.dataset.presetSection !== selectedPreset);
            });
        };

        const updateModeUI = () => {
            const selectedMode = modeInputs.find(i => i.checked)?.value ?? 'presets';
            const selectedPreset = presetInputs.find(i => i.checked)?.value ?? 'changed';

            modeCards.forEach(card => {
                const span = card.querySelector('span');
                const isActive = card.dataset.shareModeCard === selectedMode;
                span?.classList.toggle('bg-white', isActive);
                span?.classList.toggle('shadow-sm', isActive);
                span?.classList.toggle('text-slate-950', isActive);
                span?.classList.toggle('text-slate-500', !isActive);
            });

            shareSections.forEach(section => {
                section.classList.toggle('hidden', section.dataset.shareSection !== selectedMode);
            });

            const enableTags = selectedMode === 'presets' && selectedPreset === 'tag';
            document.querySelectorAll('input[name="tags[]"]').forEach(input => {
                input.disabled = !enableTags;
            });

            const enableProducts = selectedMode === 'custom';
            document.querySelectorAll('input[name="product_ids[]"]').forEach(input => {
                input.disabled = !enableProducts;
            });
        };

        modeCards.forEach(card => {
            card.addEventListener('click', () => {
                const radio = card.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    updateModeUI();
                }
            });
        });

        presetCards.forEach(card => {
            card.addEventListener('click', () => {
                const radio = card.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    updatePresetUI();
                    updateModeUI();
                }
            });
        });

        document.querySelectorAll('[data-multi-tag-filter-group] label').forEach(label => {
            label.addEventListener('click', () => {
                const cb = label.querySelector('input[type="checkbox"]');
                if (!cb) {
                    return;
                }

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
                });
            });
        });

        customSearch?.addEventListener('input', filterCustomProducts);

        customItems.forEach(item => {
            const cb = item.querySelector('input[type="checkbox"]');
            cb?.addEventListener('change', () => {
                item.classList.toggle('border-emerald-500', cb.checked);
                item.classList.toggle('bg-emerald-50', cb.checked);
                item.classList.toggle('border-transparent', !cb.checked);
                item.classList.toggle('bg-white', !cb.checked);
                updateSelectedCount();
            });
        });

        updatePresetUI();
        updateModeUI();
        updateSelectedCount();
        filterCustomProducts();
    });
    </script>
</x-layouts.app>
