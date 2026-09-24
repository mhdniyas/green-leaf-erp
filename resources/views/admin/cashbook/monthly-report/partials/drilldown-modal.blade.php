<!-- FINANCIAL DRILLDOWN SLIDE-OVER DRAWER (VANILLA JS) -->
<div id="financial-drilldown-modal" class="fixed inset-0 z-50 hidden" aria-modal="true" role="dialog">
    <!-- BACKDROP -->
    <div id="financial-drilldown-backdrop"
         onclick="window.closeReportDrilldown()"
         class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs opacity-0 transition-opacity duration-300 ease-out"></div>

    <!-- SLIDE-OVER DRAWER -->
    <div id="financial-drilldown-drawer"
         class="fixed inset-y-0 right-0 flex max-w-full pl-10 transform translate-x-full transition-transform duration-300 ease-out">
        <div class="w-screen max-w-2xl bg-white shadow-2xl flex flex-col h-full">
            <!-- DRAWER HEADER -->
            <div class="border-b border-slate-200 bg-slate-50/80 px-6 py-4 flex items-center justify-between shrink-0">
                <div>
                    <h3 id="drilldown-modal-title" class="text-sm font-extrabold text-slate-900">Breakdown</h3>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">
                        <span id="drilldown-modal-subtitle">Authorized Financial Audit Breakdown</span>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total</span>
                        <div id="drilldown-modal-total" class="font-mono text-sm font-black text-slate-900">₹0.00</div>
                    </div>
                    <button type="button" onclick="window.closeReportDrilldown()" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-200/60 hover:text-slate-700 transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- DRAWER BODY -->
            <div class="flex-1 overflow-y-auto p-6">
                <!-- LOADING STATE -->
                <div id="drilldown-loading" class="flex flex-col items-center justify-center py-20 text-slate-400">
                    <svg class="h-8 w-8 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="mt-2 text-xs font-semibold">Resolving financial source records...</span>
                </div>

                <!-- EMPTY STATE -->
                <div id="drilldown-empty" class="hidden flex flex-col items-center justify-center py-20 text-slate-400">
                    <svg class="h-8 w-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <span class="mt-2 text-xs font-semibold">No detailed source transactions for this selection.</span>
                </div>

                <!-- ROWS LIST -->
                <div id="drilldown-rows-container" class="hidden space-y-2.5"></div>
            </div>

            <!-- DRAWER FOOTER -->
            <div class="border-t border-slate-100 bg-slate-50 px-6 py-3 flex items-center justify-between text-xs text-slate-500 shrink-0">
                <span id="drilldown-footer-count">0 item(s) reconciled</span>
                <button type="button" onclick="window.closeReportDrilldown()" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 font-bold text-slate-700 hover:bg-slate-100">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('financial-drilldown-modal');
        const backdrop = document.getElementById('financial-drilldown-backdrop');
        const drawer = document.getElementById('financial-drilldown-drawer');
        const titleEl = document.getElementById('drilldown-modal-title');
        const subtitleEl = document.getElementById('drilldown-modal-subtitle');
        const totalEl = document.getElementById('drilldown-modal-total');
        const loadingEl = document.getElementById('drilldown-loading');
        const emptyEl = document.getElementById('drilldown-empty');
        const rowsContainer = document.getElementById('drilldown-rows-container');
        const footerCountEl = document.getElementById('drilldown-footer-count');

        window.openReportDrilldown = function (metricName, metricTitle, optDate, optPurchaserId, optShopId, optClientId) {
            if (!modal) return;

            modal.classList.remove('hidden');
            setTimeout(() => {
                backdrop.classList.remove('opacity-0');
                backdrop.classList.add('opacity-100');
                drawer.classList.remove('translate-x-full');
                drawer.classList.add('translate-x-0');
            }, 10);

            titleEl.textContent = metricTitle || metricName || 'Breakdown';
            subtitleEl.textContent = (optDate ? ('Date: ' + optDate + ' · ') : '') + 'Authorized Financial Audit Breakdown';
            totalEl.textContent = '₹0.00';
            footerCountEl.textContent = '0 item(s) reconciled';

            loadingEl.classList.remove('hidden');
            emptyEl.classList.add('hidden');
            rowsContainer.classList.add('hidden');
            rowsContainer.innerHTML = '';

            let params = new URLSearchParams(window.location.search);
            params.set('metric', metricName);
            if (optDate) params.set('date', optDate);
            if (optPurchaserId) params.set('purchaser_id', optPurchaserId);
            if (optShopId) params.set('shop_id', optShopId);
            if (optClientId) params.set('client_id', optClientId);

            fetch('{{ route('admin.cashbook.monthly-report.drilldown') }}?' + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                loadingEl.classList.add('hidden');
                titleEl.textContent = data.metric_label || metricTitle || metricName;
                const formattedTotal = Number(data.total || 0).toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                totalEl.textContent = '₹' + formattedTotal;

                const rows = data.rows || [];
                footerCountEl.textContent = rows.length + ' item(s) reconciled';

                if (rows.length === 0) {
                    emptyEl.classList.remove('hidden');
                    rowsContainer.classList.add('hidden');
                } else {
                    emptyEl.classList.add('hidden');
                    rowsContainer.classList.remove('hidden');

                    let html = '';
                    rows.forEach((r, idx) => {
                        const amt = Number(r.amount || r.out_amount || r.in_amount || 0).toLocaleString('en-IN', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        const source = r.source_type || r.type || 'Transaction';
                        const ref = r.reference || ('#' + (r.id || idx));
                        const date = r.business_date || r.date || '';
                        const entity = r.entity_name || r.shop_name || r.client_name || r.purchaser_name || '';
                        const desc = r.description || r.notes || r.original_category || '';
                        const sourceTag = r.funding_source ? ('Source: ' + r.funding_source) : (r.bucket_label || '');

                        html += `
                            <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 transition hover:bg-white hover:border-indigo-100 hover:shadow-xs">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="rounded-md bg-indigo-50 px-2 py-0.5 text-[10px] font-black text-indigo-700">${source}</span>
                                            <span class="font-mono text-xs font-bold text-slate-800">${ref}</span>
                                            <span class="text-[11px] font-medium text-slate-400">${date}</span>
                                        </div>
                                        <div class="mt-1 text-xs font-extrabold text-slate-900">${entity}</div>
                                        <div class="mt-0.5 text-xs text-slate-500">${desc}</div>
                                    </div>
                                    <div class="text-right whitespace-nowrap">
                                        <div class="font-mono text-xs font-black text-slate-900">₹${amt}</div>
                                        <span class="text-[10px] font-semibold text-slate-400">${sourceTag}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    rowsContainer.innerHTML = html;
                }
            })
            .catch(err => {
                console.error('Drilldown fetch error:', err);
                loadingEl.classList.add('hidden');
                emptyEl.classList.remove('hidden');
            });
        };

        window.closeReportDrilldown = function () {
            if (!modal) return;
            backdrop.classList.remove('opacity-100');
            backdrop.classList.add('opacity-0');
            drawer.classList.remove('translate-x-0');
            drawer.classList.add('translate-x-full');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        };

        // Listen for CustomEvent on window (for Alpine $dispatch compatibility)
        window.addEventListener('open-drilldown', function (e) {
            const d = e.detail || {};
            window.openReportDrilldown(d.metric, d.title, d.date, d.purchaserId, d.shopId, d.clientId);
        });

        // ESC key to close
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                window.closeReportDrilldown();
            }
        });
    })();
</script>
