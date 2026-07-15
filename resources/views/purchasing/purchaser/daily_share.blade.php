<x-layouts.app title="Daily Share Summary">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 py-3 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')

        <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_16px_36px_rgba(15,23,42,0.18)] lg:rounded-[2rem]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(45,212,191,0.28),_transparent_36%),linear-gradient(135deg,_#0f172a_0%,_#111827_55%,_#14532d_100%)] px-4 py-5 lg:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-200">WhatsApp Share</p>
                        <h1 class="mt-1 text-2xl font-black tracking-tight">Daily share summary</h1>
                        <p class="mt-2 max-w-2xl text-sm font-medium text-slate-200">Open a clean share page, filter the demand list, and send only the products you want to WhatsApp.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('purchaser.daily', ['date' => $date]) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/15 bg-white/10 px-4 text-sm font-black text-white">
                            Back to Daily
                        </a>
                        <a href="{{ $shareUrl }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-500 px-4 text-sm font-black text-slate-950">
                            Share to WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
                <form action="{{ route('purchaser.daily.share') }}" method="GET" class="space-y-5">
                    <input type="hidden" name="date" value="{{ $date }}">

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Filter Mode</p>
                            <p class="mt-1 text-sm font-semibold text-slate-600">Choose all products, multiple products, or one product.</p>
                        </div>
                        <label class="block">
                            <span class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Business Date</span>
                            <input type="date" name="date" value="{{ $date }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none sm:w-52">
                        </label>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3" id="daily-share-mode-group">
                        @foreach ([
                            'all' => ['title' => 'Share All', 'desc' => 'Send the full daily summary.'],
                            'tag' => ['title' => 'Multiple Products', 'desc' => 'Search by tag and choose many products.'],
                            'product' => ['title' => 'Single Product', 'desc' => 'Send one product only.'],
                        ] as $modeValue => $modeMeta)
                            <label data-share-mode-card="{{ $modeValue }}" class="cursor-pointer rounded-2xl border px-4 py-4 transition {{ $shareMode === $modeValue ? 'border-emerald-500 bg-emerald-50 shadow-sm' : 'border-slate-200 bg-slate-50 hover:border-slate-300' }}">
                                <input type="radio" name="share_mode" value="{{ $modeValue }}" class="sr-only" {{ $shareMode === $modeValue ? 'checked' : '' }}>
                                <p class="text-sm font-black text-slate-900">{{ $modeMeta['title'] }}</p>
                                <p class="mt-1 text-xs font-semibold text-slate-600">{{ $modeMeta['desc'] }}</p>
                            </label>
                        @endforeach
                    </div>

                    <div data-share-section="tag" class="{{ $shareMode === 'tag' ? '' : 'hidden' }}">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Multiple Products</p>
                                <p class="mt-1 text-sm font-semibold text-slate-600">Filter by tag, search product name, then select what to share.</p>
                            </div>
                            <label class="block sm:w-72">
                                <span class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Search Product</span>
                                <input type="search" data-multi-product-search placeholder="Search by product or tag..." class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                            </label>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2" data-multi-tag-filter-group>
                            <button type="button" data-multi-tag-filter="all" class="rounded-full border border-emerald-500 bg-emerald-50 px-3 py-2 text-sm font-black text-emerald-700">
                                All Tags
                            </button>
                            @forelse ($availableTags as $tag)
                                <button type="button" data-multi-tag-filter="{{ strtolower($tag) }}" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-700">
                                    {{ $tag }}
                                </button>
                            @empty
                                <p class="text-sm font-semibold text-slate-500">No tags available for this date.</p>
                            @endforelse
                        </div>

                        <div class="mt-4 max-h-80 space-y-2 overflow-y-auto rounded-2xl border border-slate-200 bg-slate-50 p-3" data-multi-product-list>
                            @forelse ($availableProducts as $product)
                                <label
                                    data-multi-product-item
                                    data-category="{{ strtolower($product['category_name'] ?: 'no tag') }}"
                                    data-search="{{ $product['search_index'] }}"
                                    class="flex cursor-pointer items-start gap-3 rounded-2xl border px-3 py-3 transition {{ in_array($product['product_id'], $selectedProductIds, true) ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 bg-white hover:border-slate-300' }}"
                                >
                                    <input type="checkbox" name="product_ids[]" value="{{ $product['product_id'] }}" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" {{ in_array($product['product_id'], $selectedProductIds, true) ? 'checked' : '' }}>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-black text-slate-900">{{ $product['product_name'] }}</p>
                                                <p class="mt-1 text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">{{ $product['category_name'] ?: 'No Tag' }}</p>
                                            </div>
                                            <p class="text-sm font-black text-emerald-700">{{ number_format($product['remaining_qty'], 2) }} {{ $product['unit'] }}</p>
                                        </div>
                                    </div>
                                </label>
                            @empty
                                <p class="text-sm font-semibold text-slate-500">No products available for this date.</p>
                            @endforelse
                        </div>
                    </div>

                    <div data-share-section="product" class="{{ $shareMode === 'product' ? '' : 'hidden' }}">
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Single Product</p>
                                <p class="mt-1 text-sm font-semibold text-slate-600">Use the searchable dropdown and choose one product fast.</p>
                            </div>

                            <div class="relative" data-single-product-picker>
                                <input type="hidden" name="product_id" value="{{ $selectedProductId }}" id="share-product-id">
                                <button type="button" data-single-product-trigger class="flex min-h-14 w-full items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-left transition hover:border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                                    <span class="min-w-0" data-single-product-label>
                                        @php
                                            $selectedSingleProduct = collect($availableProducts)->firstWhere('product_id', $selectedProductId);
                                        @endphp
                                        @if ($selectedSingleProduct)
                                            <span class="block truncate text-sm font-black text-slate-900">{{ $selectedSingleProduct['product_name'] }}</span>
                                            <span class="mt-1 block text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">{{ $selectedSingleProduct['category_name'] ?: 'No Tag' }}</span>
                                        @else
                                            <span class="block text-sm font-black text-slate-500">Select one product</span>
                                        @endif
                                    </span>
                                    <svg class="h-5 w-5 shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <div data-single-product-panel class="absolute left-0 right-0 z-30 mt-2 hidden overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_24px_48px_rgba(15,23,42,0.16)]">
                                    <div class="border-b border-slate-100 p-3">
                                        <input type="search" data-single-product-search placeholder="Search by product or tag..." class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                                    </div>
                                    <div class="max-h-72 overflow-y-auto p-2">
                                        @foreach ($availableProducts as $product)
                                            <button
                                                type="button"
                                                data-single-product-option
                                                data-value="{{ $product['product_id'] }}"
                                                data-category="{{ strtolower($product['category_name'] ?: 'no tag') }}"
                                                data-search="{{ $product['search_index'] }}"
                                                data-product-name="{{ $product['product_name'] }}"
                                                data-category-name="{{ $product['category_name'] ?: 'No Tag' }}"
                                                class="flex w-full items-start justify-between gap-3 rounded-2xl px-3 py-3 text-left transition {{ $selectedProductId === $product['product_id'] ? 'bg-emerald-50 text-emerald-700' : 'text-slate-700 hover:bg-slate-50' }}"
                                            >
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-black">{{ $product['product_name'] }}</span>
                                                    <span class="mt-1 block text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">{{ $product['category_name'] ?: 'No Tag' }}</span>
                                                </span>
                                                <span class="shrink-0 text-sm font-black">{{ number_format($product['remaining_qty'], 2) }} {{ $product['unit'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row">
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-black text-white">
                            Apply Filter
                        </button>
                        <a href="{{ route('purchaser.daily.share', ['date' => $date, 'share_mode' => 'all']) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-4 text-sm font-black text-slate-700">
                            Reset
                        </a>
                    </div>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:rounded-[2rem] lg:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Preview</p>
                        <p class="mt-1 text-sm font-semibold text-slate-600">{{ $shareSummary->count() }} products ready for {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</p>
                    </div>
                    <a href="{{ $shareUrl }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-4 text-sm font-black text-white">
                        WhatsApp
                    </a>
                </div>

                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    @if ($shareSummary->isEmpty())
                        <p class="text-sm font-semibold text-slate-500">No products match the selected share filter.</p>
                    @else
                        <pre class="whitespace-pre-wrap break-words font-mono text-xs leading-6 text-slate-700">{{ $sharePreviewText }}</pre>
                    @endif
                </div>

                @if ($shareSummary->isNotEmpty())
                    <div class="mt-4 space-y-3">
                        @foreach ($shareSummary as $summary)
                            <div class="rounded-2xl border border-slate-200 px-4 py-3">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="text-sm font-black text-slate-900">{{ $summary['product_name'] }}</p>
                                        <p class="mt-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ $summary['category_name'] ?: 'No Tag' }}</p>
                                    </div>
                                    <div class="text-left sm:text-right">
                                        <p class="text-sm font-black text-emerald-700">{{ number_format((float) $summary['remaining_qty'], 2) }} {{ $summary['unit'] }}</p>
                                        @php
                                            $directPurchaseCount = collect($summary['shop_details'])->where('is_direct_purchase', true)->count();
                                            $shopDemandCount = count($summary['shop_details']) - $directPurchaseCount;
                                        @endphp
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $directPurchaseCount > 0 ? 'Direct + ' : '' }}{{ $shopDemandCount }} shops</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modeInputs = Array.from(document.querySelectorAll('input[name="share_mode"]'));
            const modeCards = Array.from(document.querySelectorAll('[data-share-mode-card]'));
            const sections = Array.from(document.querySelectorAll('[data-share-section]'));

            const multiProductInputs = Array.from(document.querySelectorAll('input[name="product_ids[]"]'));
            const multiProductSearch = document.querySelector('[data-multi-product-search]');
            const multiTagButtons = Array.from(document.querySelectorAll('[data-multi-tag-filter]'));
            const multiProductItems = Array.from(document.querySelectorAll('[data-multi-product-item]'));

            const singleProductInput = document.getElementById('share-product-id');
            const singleTrigger = document.querySelector('[data-single-product-trigger]');
            const singleLabel = document.querySelector('[data-single-product-label]');
            const singlePanel = document.querySelector('[data-single-product-panel]');
            const singleSearch = document.querySelector('[data-single-product-search]');
            const singleOptions = Array.from(document.querySelectorAll('[data-single-product-option]'));

            let activeMultiTag = 'all';

            const updateModeState = () => {
                const selectedMode = modeInputs.find((input) => input.checked)?.value ?? 'all';

                modeCards.forEach((card) => {
                    const isActive = card.getAttribute('data-share-mode-card') === selectedMode;
                    card.classList.toggle('border-emerald-500', isActive);
                    card.classList.toggle('bg-emerald-50', isActive);
                    card.classList.toggle('shadow-sm', isActive);
                    card.classList.toggle('border-slate-200', ! isActive);
                    card.classList.toggle('bg-slate-50', ! isActive);
                    card.classList.toggle('hover:border-slate-300', ! isActive);
                });

                sections.forEach((section) => {
                    const isActive = section.getAttribute('data-share-section') === selectedMode;
                    section.classList.toggle('hidden', ! isActive);
                });

                multiProductInputs.forEach((input) => {
                    input.disabled = selectedMode !== 'tag';
                });

                if (singleProductInput instanceof HTMLInputElement) {
                    singleProductInput.disabled = selectedMode !== 'product';
                }
            };

            const updateMultiProductFilter = () => {
                const searchValue = (multiProductSearch instanceof HTMLInputElement ? multiProductSearch.value : '').trim().toLowerCase();

                multiProductItems.forEach((item) => {
                    const matchesTag = activeMultiTag === 'all' || item.getAttribute('data-category') === activeMultiTag;
                    const matchesSearch = searchValue === '' || (item.getAttribute('data-search') ?? '').includes(searchValue);
                    item.classList.toggle('hidden', !(matchesTag && matchesSearch));
                });
            };

            const updateSingleProductFilter = () => {
                const searchValue = (singleSearch instanceof HTMLInputElement ? singleSearch.value : '').trim().toLowerCase();

                singleOptions.forEach((option) => {
                    const matchesSearch = searchValue === '' || (option.getAttribute('data-search') ?? '').includes(searchValue);
                    option.classList.toggle('hidden', !matchesSearch);
                });
            };

            multiProductInputs.forEach((input) => {
                input.addEventListener('change', () => {
                    const card = input.closest('[data-multi-product-item]');
                    const checked = input.checked;
                    card?.classList.toggle('border-emerald-500', checked);
                    card?.classList.toggle('bg-emerald-50', checked);
                    card?.classList.toggle('border-slate-200', !checked);
                    card?.classList.toggle('bg-white', !checked);
                });
            });

            if (multiProductSearch instanceof HTMLInputElement) {
                multiProductSearch.addEventListener('input', updateMultiProductFilter);
            }

            multiTagButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    activeMultiTag = button.getAttribute('data-multi-tag-filter') ?? 'all';

                    multiTagButtons.forEach((tagButton) => {
                        const isActive = tagButton === button;
                        tagButton.classList.toggle('border-emerald-500', isActive);
                        tagButton.classList.toggle('bg-emerald-50', isActive);
                        tagButton.classList.toggle('text-emerald-700', isActive);
                        tagButton.classList.toggle('border-slate-200', !isActive);
                        tagButton.classList.toggle('bg-white', !isActive);
                        tagButton.classList.toggle('text-slate-700', !isActive);
                    });

                    updateMultiProductFilter();
                });
            });

            if (singleTrigger && singlePanel) {
                singleTrigger.addEventListener('click', () => {
                    singlePanel.classList.toggle('hidden');
                    if (!singlePanel.classList.contains('hidden') && singleSearch instanceof HTMLInputElement) {
                        singleSearch.focus();
                    }
                });
            }

            if (singleSearch instanceof HTMLInputElement) {
                singleSearch.addEventListener('input', updateSingleProductFilter);
            }

            singleOptions.forEach((option) => {
                option.addEventListener('click', () => {
                    const value = option.getAttribute('data-value') ?? '0';
                    const productName = option.getAttribute('data-product-name') ?? 'Select one product';
                    const categoryName = option.getAttribute('data-category-name') ?? 'No Tag';

                    if (singleProductInput instanceof HTMLInputElement) {
                        singleProductInput.value = value;
                    }

                    if (singleLabel) {
                        singleLabel.innerHTML = `
                            <span class="block truncate text-sm font-black text-slate-900">${productName}</span>
                            <span class="mt-1 block text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">${categoryName}</span>
                        `;
                    }

                    singleOptions.forEach((item) => {
                        const isActive = item === option;
                        item.classList.toggle('bg-emerald-50', isActive);
                        item.classList.toggle('text-emerald-700', isActive);
                        item.classList.toggle('text-slate-700', !isActive);
                    });

                    singlePanel?.classList.add('hidden');
                });
            });

            document.addEventListener('click', (event) => {
                if (!event.target.closest('[data-single-product-picker]')) {
                    singlePanel?.classList.add('hidden');
                }
            });

            modeInputs.forEach((input) => {
                input.addEventListener('change', updateModeState);
            });

            updateModeState();
            updateMultiProductFilter();
            updateSingleProductFilter();
        });
    </script>
</x-layouts.app>
