@props([
    'containerId' => 'purchaser-load-more-container',
    'hasMore' => false,
    'currentPage' => 1,
    'loadedCount' => 0,
    'totalCount' => 0,
    'pageParam' => 'page',
    'targetSelector' => '',
    'renderType' => 'row',
])

<div
    id="{{ $containerId }}"
    class="mt-4 flex flex-col items-center justify-center gap-2 {{ $hasMore ? '' : 'hidden' }}"
    data-load-more-container
    data-has-more="{{ $hasMore ? 'true' : 'false' }}"
    data-current-page="{{ $currentPage }}"
    data-loaded-count="{{ $loadedCount }}"
    data-total-count="{{ $totalCount }}"
    data-page-param="{{ $pageParam }}"
    data-target-selector="{{ $targetSelector }}"
    data-render-type="{{ $renderType }}"
>
    <p class="text-xs font-semibold text-slate-500">
        Showing <span data-load-more-loaded>{{ $loadedCount }}</span> of <span data-load-more-total>{{ $totalCount }}</span> records
    </p>

    <div data-load-more-skeleton class="hidden w-full space-y-2 py-2">
        <div class="h-10 w-full animate-pulse rounded-xl bg-slate-200"></div>
        <div class="h-10 w-full animate-pulse rounded-xl bg-slate-100"></div>
    </div>

    <div data-load-more-error class="hidden items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-700">
        <span>Failed to load more records.</span>
        <button type="button" data-load-more-retry class="underline hover:text-rose-900">Retry</button>
    </div>

    <button
        type="button"
        data-load-more-btn
        class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-black text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
    >
        <svg data-load-more-spinner class="hidden h-4 w-4 animate-spin text-teal-600" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        <span data-load-more-label>Load More</span>
    </button>
</div>

@once
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-load-more-container]').forEach((container) => {
        let currentPage = parseInt(container.dataset.currentPage || '1', 10);
        let hasMore = container.dataset.hasMore === 'true';
        let loadedCount = parseInt(container.dataset.loadedCount || '0', 10);
        let isLoading = false;
        let requestGen = 0;

        const btn = container.querySelector('[data-load-more-btn]');
        const spinner = container.querySelector('[data-load-more-spinner]');
        const label = container.querySelector('[data-load-more-label]');
        const skeleton = container.querySelector('[data-load-more-skeleton]');
        const errorBox = container.querySelector('[data-load-more-error]');
        const retryBtn = container.querySelector('[data-load-more-retry]');
        const loadedEl = container.querySelector('[data-load-more-loaded]');
        const totalEl = container.querySelector('[data-load-more-total]');
        const pageParam = container.dataset.pageParam || 'page';
        const targetSelector = container.dataset.targetSelector;

        const triggerLoad = async () => {
            if (isLoading || !hasMore) return;
            isLoading = true;
            const myGen = ++requestGen;
            btn.disabled = true;
            spinner.classList.remove('hidden');
            label.textContent = 'Loading...';
            skeleton.classList.remove('hidden');
            errorBox.classList.add('hidden');
            errorBox.classList.remove('flex');

            try {
                const url = new URL(window.location.href);
                url.searchParams.set(pageParam, String(currentPage + 1));

                const res = await fetch(url.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const json = await res.json();
                if (myGen !== requestGen) return;

                const items = Array.isArray(json.data) ? json.data : [];
                const target = targetSelector ? document.querySelector(targetSelector) : null;
                if (target && items.length > 0) {
                    const frag = document.createDocumentFragment();
                    items.forEach((item) => {
                        const tr = document.createElement('tr');
                        tr.className = 'border-t border-slate-100 hover:bg-slate-50/70';
                        const title = item.order_number || item.invoice_number || item.cart_number || item.name || ('#' + (item.id || ''));
                        const sub = item.shop_name || item.supplier_name || item.category_name || item.date_label || '';
                        const link = item.show_url || item.payment_route || '#';
                        tr.innerHTML = `<td class="px-4 py-3 text-sm font-bold text-slate-800">${title}</td><td class="px-4 py-3 text-xs text-slate-600">${sub}</td><td class="px-4 py-3 text-right"><a href="${link}" class="text-xs font-black text-teal-700 hover:text-teal-900">Open</a></td>`;
                        frag.appendChild(tr);
                    });
                    target.appendChild(frag);
                }

                currentPage = json.pagination?.current_page ?? (currentPage + 1);
                hasMore = Boolean(json.pagination?.has_more);
                loadedCount += items.length;
                if (loadedEl) loadedEl.textContent = String(loadedCount);
                if (totalEl && json.pagination?.total_records !== undefined) {
                    totalEl.textContent = String(json.pagination.total_records);
                }
                if (!hasMore) {
                    container.classList.add('hidden');
                }
            } catch (err) {
                if (myGen === requestGen) {
                    errorBox.classList.remove('hidden');
                    errorBox.classList.add('flex');
                }
            } finally {
                if (myGen === requestGen) {
                    isLoading = false;
                    btn.disabled = false;
                    spinner.classList.add('hidden');
                    label.textContent = 'Load More';
                    skeleton.classList.add('hidden');
                }
            }
        };

        btn?.addEventListener('click', triggerLoad);
        retryBtn?.addEventListener('click', triggerLoad);
    });
});
</script>
@endonce
