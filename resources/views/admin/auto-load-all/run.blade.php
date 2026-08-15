<x-layouts.admin title="Load All Now">
    <div class="mx-auto max-w-4xl space-y-6">

        {{-- Header --}}
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Warehouse Operations</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Load All Now</h1>
                    <p class="mt-2 max-w-2xl text-sm font-semibold leading-6 text-slate-500">
                        Manually trigger Auto Load All for a specific date and shops. Each order is processed one by one with a configured delay to prevent database overload.
                    </p>
                </div>
                <a href="{{ route('admin.company-settings.edit') }}"
                   class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-700 transition hover:bg-slate-50">
                    Back to Settings
                </a>
            </div>
        </section>

        {{-- Configuration form --}}
        <section id="config-section" class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm space-y-5">
            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Step 1 — Select Date & Shops</p>

            {{-- Date --}}
            <div>
                <label for="run-date" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Business Date</label>
                <input
                    id="run-date"
                    type="date"
                    value="{{ $operationalDate }}"
                    class="mt-2 h-11 w-56 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900 focus:border-violet-500 focus:bg-white focus:outline-none"
                >
            </div>

            {{-- Shops --}}
            <div>
                <div class="flex items-center justify-between">
                    <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Shops</label>
                    <div class="flex gap-2">
                        <button type="button" id="select-all-shops"
                            class="text-xs font-black text-violet-600 hover:text-violet-800 transition">
                            Select All
                        </button>
                        <span class="text-slate-300">|</span>
                        <button type="button" id="deselect-all-shops"
                            class="text-xs font-black text-slate-500 hover:text-slate-700 transition">
                            Clear
                        </button>
                    </div>
                </div>

                <div class="mt-2 grid grid-cols-1 gap-1.5 sm:grid-cols-2 lg:grid-cols-3 max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-3" id="shop-list">
                    @foreach($shops as $shop)
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2.5 hover:bg-white transition border border-transparent hover:border-slate-200">
                            <input
                                type="checkbox"
                                class="shop-checkbox h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                                value="{{ $shop->id }}"
                                data-name="{{ $shop->name }}"
                                checked
                            >
                            <span class="min-w-0">
                                <span class="block truncate text-xs font-black text-slate-900">{{ $shop->name }}</span>
                                <span class="block text-[10px] font-bold text-slate-400">{{ $shop->code }}{{ $shop->warehouse_tag ? ' · '.$shop->warehouse_tag : '' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-1 text-xs font-semibold text-slate-400"><span id="selected-count">{{ $shops->count() }}</span> of {{ $shops->count() }} shops selected</p>
            </div>

            {{-- Delay --}}
            <div class="flex items-center gap-3">
                <div>
                    <label for="delay-seconds" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Delay Between Orders</label>
                    <div class="mt-2 flex items-center gap-2">
                        <input
                            id="delay-seconds"
                            type="number"
                            min="1"
                            max="60"
                            value="{{ $delaySeconds }}"
                            class="h-10 w-20 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-bold text-slate-900 focus:border-violet-500 focus:bg-white focus:outline-none"
                        >
                        <span class="text-xs font-semibold text-slate-400">seconds</span>
                    </div>
                </div>
            </div>

            {{-- Start button --}}
            <div class="pt-2">
                <button id="start-btn" type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-6 py-3 text-sm font-black text-white shadow-sm transition hover:bg-violet-500 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                    </svg>
                    Start Load All
                </button>
            </div>
        </section>

        {{-- Progress panel (hidden until run starts) --}}
        <section id="progress-section" class="hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-sm overflow-hidden">

            {{-- Progress header --}}
            <div class="border-b border-slate-100 px-5 py-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <span id="status-dot" class="inline-flex h-2.5 w-2.5 rounded-full bg-violet-500 animate-pulse shrink-0"></span>
                    <span id="status-label" class="text-sm font-black text-slate-900">Initialising…</span>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <span id="counter-label" class="text-xs font-black text-slate-500">0 / 0</span>
                    <button id="stop-btn" type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-black text-rose-600 border border-rose-200 hover:bg-rose-100 transition">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                        Stop
                    </button>
                </div>
            </div>

            {{-- Progress bar --}}
            <div class="h-1.5 bg-slate-100">
                <div id="progress-bar" class="h-full bg-violet-500 transition-all duration-300 ease-out" style="width:0%"></div>
            </div>

            {{-- Summary chips --}}
            <div class="flex items-center gap-2 px-5 py-3 bg-slate-50 border-b border-slate-100 text-[11px] font-black">
                <span id="chip-loaded"  class="rounded-full bg-emerald-100 text-emerald-700 px-2.5 py-1">0 Loaded</span>
                <span id="chip-skipped" class="rounded-full bg-amber-100 text-amber-700 px-2.5 py-1">0 Skipped</span>
                <span id="chip-failed"  class="rounded-full bg-rose-100 text-rose-700 px-2.5 py-1">0 Failed</span>
            </div>

            {{-- Scrollable log --}}
            <div id="log" class="p-4 space-y-2 max-h-96 overflow-y-auto text-xs"></div>

            {{-- Run Again footer --}}
            <div id="run-again-footer" class="hidden border-t border-slate-100 p-4">
                <button id="run-again-btn" type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-black text-white hover:bg-slate-700 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                    Run Again
                </button>
            </div>
        </section>

    </div>

    <script>
    (() => {
        const API_BASE = '/admin/auto-load-all/api';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        // ── UI refs ────────────────────────────────────────────────────────
        const startBtn       = document.getElementById('start-btn');
        const stopBtn        = document.getElementById('stop-btn');
        const runAgainBtn    = document.getElementById('run-again-btn');
        const configSection  = document.getElementById('config-section');
        const progressSection= document.getElementById('progress-section');
        const statusDot      = document.getElementById('status-dot');
        const statusLabel    = document.getElementById('status-label');
        const counterLabel   = document.getElementById('counter-label');
        const progressBar    = document.getElementById('progress-bar');
        const log            = document.getElementById('log');
        const chipLoaded     = document.getElementById('chip-loaded');
        const chipSkipped    = document.getElementById('chip-skipped');
        const chipFailed     = document.getElementById('chip-failed');
        const runAgainFooter = document.getElementById('run-again-footer');
        const selectedCount  = document.getElementById('selected-count');
        const selectAllBtn   = document.getElementById('select-all-shops');
        const deselectAllBtn = document.getElementById('deselect-all-shops');

        let cancelled = false;
        let loaded = 0, skipped = 0, failed = 0;

        // ── Shop select helpers ────────────────────────────────────────────
        function updateSelectedCount() {
            const n = document.querySelectorAll('.shop-checkbox:checked').length;
            selectedCount.textContent = n;
        }
        document.querySelectorAll('.shop-checkbox').forEach(cb => cb.addEventListener('change', updateSelectedCount));
        selectAllBtn.addEventListener('click', () => {
            document.querySelectorAll('.shop-checkbox').forEach(cb => cb.checked = true);
            updateSelectedCount();
        });
        deselectAllBtn.addEventListener('click', () => {
            document.querySelectorAll('.shop-checkbox').forEach(cb => cb.checked = false);
            updateSelectedCount();
        });

        // ── Log helpers ────────────────────────────────────────────────────
        function appendLog(icon, bgClass, borderClass, textClass, title, subtitle, orderNum) {
            const el = document.createElement('div');
            el.className = `flex items-start gap-2.5 rounded-xl border px-3 py-2.5 ${bgClass} ${borderClass}`;
            el.innerHTML = `
                <span class="mt-0.5 text-base">${icon}</span>
                <span class="min-w-0 flex-1">
                    <span class="block text-xs font-black ${textClass}">${title}</span>
                    ${subtitle ? `<span class="block text-[11px] font-semibold text-slate-500 mt-0.5">${subtitle}</span>` : ''}
                </span>
                ${orderNum ? `<span class="shrink-0 font-mono text-[10px] font-bold text-slate-400">${orderNum}</span>` : ''}
            `;
            log.appendChild(el);
            log.scrollTop = log.scrollHeight;
        }

        function logProcessing(displayName, orderNum) {
            appendLog('⟳', 'bg-violet-50', 'border-violet-200', 'text-violet-700', `Processing: ${displayName}`, null, orderNum);
        }
        function logLoaded(displayName, orderNum) {
            appendLog('✅', 'bg-emerald-50', 'border-emerald-200', 'text-emerald-800', displayName, 'Loaded successfully', orderNum);
        }
        function logSkipped(displayName, reason, orderNum) {
            appendLog('⏭', 'bg-amber-50', 'border-amber-200', 'text-amber-800', displayName, reason ?? 'Skipped', orderNum);
        }
        function logFailed(displayName, reason, orderNum) {
            appendLog('❌', 'bg-rose-50', 'border-rose-200', 'text-rose-800', displayName, reason ?? 'Failed', orderNum);
        }

        function updateChips() {
            chipLoaded.textContent  = `${loaded} Loaded`;
            chipSkipped.textContent = `${skipped} Skipped`;
            chipFailed.textContent  = `${failed} Failed`;
        }

        // ── API helpers ────────────────────────────────────────────────────
        async function apiGet(path, params = {}) {
            const url = new URL(API_BASE + path, window.location.origin);
            Object.entries(params).forEach(([k, v]) => {
                if (v !== undefined && v !== null && v !== '') {
                    if (Array.isArray(v)) v.forEach(i => url.searchParams.append(k + '[]', i));
                    else url.searchParams.set(k, v);
                }
            });
            const res = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => null);
            if (!res.ok) {
                const msg = data?.message || `HTTP ${res.status} ${res.statusText}`;
                return { success: false, message: msg };
            }
            return data;
        }

        async function apiPost(path, body = {}) {
            const res = await fetch(API_BASE + path, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => null);
            if (!res.ok) {
                const msg = data?.message || `HTTP ${res.status} ${res.statusText}`;
                return { success: false, message: msg };
            }
            return data;
        }

        function delay(seconds) {
            return new Promise(resolve => setTimeout(resolve, seconds * 1000));
        }

        // ── Main run logic ─────────────────────────────────────────────────
        async function run() {
            cancelled = false;
            loaded = 0; skipped = 0; failed = 0;
            log.innerHTML = '';
            updateChips();
            runAgainFooter.classList.add('hidden');

            const date         = document.getElementById('run-date').value;
            const delaySeconds = parseInt(document.getElementById('delay-seconds').value, 10) || 3;
            const shopIds      = [...document.querySelectorAll('.shop-checkbox:checked')].map(cb => cb.value);

            if (!date) { alert('Please select a date.'); return; }
            if (shopIds.length === 0) { alert('Please select at least one shop.'); return; }

            // Show progress panel
            configSection.classList.add('hidden');
            progressSection.classList.remove('hidden');
            statusDot.className = 'inline-flex h-2.5 w-2.5 rounded-full bg-violet-500 animate-pulse shrink-0';
            statusLabel.textContent = 'Fetching orders…';
            counterLabel.textContent = '0 / ?';

            // ── 1. Fetch manifest ──────────────────────────────────────────
            let manifest;
            try {
                manifest = await apiGet('/manifest', { date, source: 'all' });
            } catch (e) {
                statusLabel.textContent = 'Failed to fetch orders.';
                logFailed('Manifest', e.message ?? 'Network error', '');
                statusDot.className = 'inline-flex h-2.5 w-2.5 rounded-full bg-rose-500 shrink-0';
                runAgainFooter.classList.remove('hidden');
                return;
            }

            if (!manifest.success) {
                statusLabel.textContent = 'Manifest error.';
                logFailed('Manifest', manifest.message ?? 'Could not fetch orders.', '');
                statusDot.className = 'inline-flex h-2.5 w-2.5 rounded-full bg-rose-500 shrink-0';
                runAgainFooter.classList.remove('hidden');
                return;
            }

            const shopIdSet = new Set(shopIds.map(Number));

            // Filter: selected shops, not already in a terminal state, not fully loaded
            const eligible = (manifest.orders ?? []).filter(order => {
                const shopId   = order.shop?.id;
                const status   = order.delivery_status ?? '';
                const total    = parseInt(order.total_count ?? 0, 10);
                const loaded_c = parseInt(order.loaded_count ?? 0, 10);
                const terminal = ['in_transit','delivered','partially_delivered','delivery_issue'].includes(status);
                return shopIdSet.has(shopId) && !terminal && total > 0 && loaded_c < total;
            });

            const total = eligible.length;

            if (total === 0) {
                statusLabel.textContent = 'No eligible orders found.';
                counterLabel.textContent = '0 / 0';
                progressBar.style.width = '100%';
                progressBar.classList.replace('bg-violet-500', 'bg-emerald-500');
                statusDot.className = 'inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500 shrink-0';
                appendLog('ℹ️', 'bg-slate-50', 'border-slate-200', 'text-slate-600',
                    'No eligible orders', `No pending orders for ${date} in the selected shops.`, '');
                runAgainFooter.classList.remove('hidden');
                return;
            }

            statusLabel.textContent = 'Processing…';
            counterLabel.textContent = `0 / ${total}`;

            // ── 2. Process each order ──────────────────────────────────────
            for (let i = 0; i < eligible.length; i++) {
                if (cancelled) break;

                const order       = eligible[i];
                const orderNum    = order.order_number ?? '';
                const displayName = order.display_name ?? orderNum;

                counterLabel.textContent = `${i} / ${total}`;
                progressBar.style.width = `${Math.round((i / total) * 100)}%`;
                logProcessing(displayName, orderNum);

                // 2a. Fetch detail
                let detail;
                try {
                    detail = await apiGet(`/orders/${orderNum}`);
                } catch (e) {
                    failed++;
                    logFailed(displayName, 'Network error fetching details.', orderNum);
                    updateChips();
                    if (i < eligible.length - 1) await delay(delaySeconds);
                    continue;
                }

                if (cancelled) break;

                if (!detail.success || !detail.product_groups) {
                    failed++;
                    logFailed(displayName, detail.message ?? 'Could not load order details.', orderNum);
                    updateChips();
                    if (i < eligible.length - 1) await delay(delaySeconds);
                    continue;
                }

                // 2b. Build load-all payload from product_groups
                const items        = {};
                const itemUnitQtys = {};
                let   hasAnything  = false;

                for (const pg of detail.product_groups) {
                    const balance = parseFloat(pg.total_balance ?? 0);
                    if (balance <= 0.001) continue;  // already fully loaded
                    const pid = pg.product_id;
                    items[pid] = parseFloat(pg.total_approved);
                    if (pg.has_secondary_unit && pg.default_loaded_order_unit_qty != null) {
                        itemUnitQtys[pid] = parseFloat(pg.default_loaded_order_unit_qty);
                    }
                    hasAnything = true;
                }

                if (!hasAnything) {
                    skipped++;
                    logSkipped(displayName, 'All items already loaded or no balance.', orderNum);
                    updateChips();
                    if (i < eligible.length - 1) await delay(delaySeconds);
                    continue;
                }

                // 2c. Save
                let saveResult;
                try {
                    saveResult = await apiPost(`/orders/${orderNum}/save`, {
                        items,
                        item_unit_qtys: itemUnitQtys,
                    });
                } catch (e) {
                    failed++;
                    logFailed(displayName, 'Network error saving loadout.', orderNum);
                    updateChips();
                    if (i < eligible.length - 1) await delay(delaySeconds);
                    continue;
                }

                if (cancelled) break;

                if (saveResult.success) {
                    loaded++;
                    logLoaded(displayName, orderNum);
                } else {
                    failed++;
                    logFailed(displayName, saveResult.message ?? 'Save failed.', orderNum);
                }

                updateChips();

                // ── Wait between orders ────────────────────────────────────
                if (i < eligible.length - 1 && !cancelled) {
                    await delay(delaySeconds);
                }
            }

            // ── 3. Finish ──────────────────────────────────────────────────
            const wasStopped = cancelled;
            counterLabel.textContent = `${loaded + skipped + failed} / ${total}`;
            progressBar.style.width  = '100%';

            if (wasStopped) {
                statusLabel.textContent = 'Stopped.';
                statusDot.className = 'inline-flex h-2.5 w-2.5 rounded-full bg-amber-500 shrink-0';
                progressBar.classList.replace('bg-violet-500', 'bg-amber-500');
                appendLog('⛔', 'bg-amber-50', 'border-amber-200', 'text-amber-800',
                    'Stopped by user', `${loaded} loaded · ${skipped} skipped · ${failed} failed`, '');
            } else {
                statusLabel.textContent = 'Done!';
                statusDot.className = 'inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500 shrink-0';
                progressBar.classList.replace('bg-violet-500', 'bg-emerald-500');
                appendLog('🎉', 'bg-emerald-50', 'border-emerald-200', 'text-emerald-800',
                    'Auto Load All Complete',
                    `${loaded} loaded · ${skipped} skipped · ${failed} failed`, '');
            }

            stopBtn.disabled = true;
            runAgainFooter.classList.remove('hidden');
        }

        // ── Button listeners ───────────────────────────────────────────────
        startBtn.addEventListener('click', run);

        stopBtn.addEventListener('click', () => {
            cancelled = true;
            stopBtn.disabled = true;
            statusLabel.textContent = 'Stopping…';
        });

        runAgainBtn.addEventListener('click', () => {
            cancelled = false;
            progressSection.classList.add('hidden');
            configSection.classList.remove('hidden');
            progressBar.className = 'h-full bg-violet-500 transition-all duration-300 ease-out';
            progressBar.style.width = '0%';
            stopBtn.disabled = false;
        });
    })();
    </script>
</x-layouts.admin>
