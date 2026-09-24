<!-- Reusable Sales Report Breakdown Modal -->
<div id="sales-report-breakdown-modal"
     class="fixed inset-0 z-50 hidden flex-col justify-end md:justify-center items-center bg-slate-950/60 backdrop-blur-xs p-0 sm:p-4 transition-opacity"
     role="dialog"
     aria-modal="true"
     aria-labelledby="report-modal-title">

    <!-- Modal Backdrop Overlay Click Listener -->
    <div class="fixed inset-0 -z-10" onclick="closeReportBreakdownModal()"></div>

    <div class="w-full md:max-w-2xl rounded-t-3xl md:rounded-3xl border border-slate-200 bg-white shadow-2xl flex flex-col max-h-[85vh] md:max-h-[80vh] overflow-hidden animate-in fade-in slide-in-from-bottom-4 duration-200">
        <!-- Sticky Modal Header -->
        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-slate-900 px-5 py-4 text-white">
            <div>
                <h3 id="report-modal-title" class="text-base font-black tracking-tight text-white uppercase">Breakdown</h3>
                <p id="report-modal-subtitle" class="text-xs font-semibold text-emerald-400 mt-0.5">Period</p>
            </div>
            <button type="button"
                    onclick="closeReportBreakdownModal()"
                    class="rounded-xl p-2 text-slate-400 hover:bg-slate-800 hover:text-white transition cursor-pointer"
                    aria-label="Close modal">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Scrollable Modal Content -->
        <div id="report-modal-content" class="p-5 overflow-y-auto space-y-4 font-sans text-slate-800">
            <!-- Dynamic Content Injected via Vanilla JS -->
        </div>

        <!-- Sticky Modal Footer -->
        <div class="sticky bottom-0 z-10 flex items-center justify-between border-t border-slate-100 bg-slate-50 px-5 py-3.5">
            <div id="report-modal-footer-info" class="text-xs font-bold text-slate-500">
                Single Source of Truth &bull; Cashbook Engine
            </div>
            <button type="button"
                    onclick="closeReportBreakdownModal()"
                    class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-800 transition shadow-2xs cursor-pointer">
                Close
            </button>
        </div>
    </div>
</div>

<script>
    (function() {
        window.openReportBreakdownModal = function(periodLabel, breakdownData) {
            const modal = document.getElementById('sales-report-breakdown-modal');
            const titleEl = document.getElementById('report-modal-title');
            const subtitleEl = document.getElementById('report-modal-subtitle');
            const contentEl = document.getElementById('report-modal-content');

            if (!modal || !contentEl || !breakdownData) return;

            titleEl.textContent = breakdownData.title || 'REPORT BREAKDOWN';
            subtitleEl.textContent = periodLabel || '';

            if (breakdownData.is_balance) {
                // Render Balance Calculation Popup
                const sales = parseFloat(breakdownData.sales || 0);
                const rent = parseFloat(breakdownData.rent || 0);
                const purchase = parseFloat(breakdownData.purchase || 0);
                const other = parseFloat(breakdownData.other_expense || 0);
                const total = parseFloat(breakdownData.total || 0);

                contentEl.innerHTML = `
                    <div class="space-y-3 font-mono text-sm">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-2.5">
                            <div class="flex justify-between items-center text-emerald-800 font-bold">
                                <span class="font-sans">Sales</span>
                                <span>+ ₹${formatMoney(sales)}</span>
                            </div>
                            <div class="flex justify-between items-center text-rose-700 font-bold">
                                <span class="font-sans">Rent</span>
                                <span>- ₹${formatMoney(rent)}</span>
                            </div>
                            <div class="flex justify-between items-center text-amber-800 font-bold">
                                <span class="font-sans">Purchase</span>
                                <span>- ₹${formatMoney(purchase)}</span>
                            </div>
                            <div class="flex justify-between items-center text-purple-800 font-bold">
                                <span class="font-sans">Other Expenses</span>
                                <span>- ₹${formatMoney(other)}</span>
                            </div>
                            <div class="pt-3 border-t-2 border-slate-300 flex justify-between items-center font-black text-base ${total >= 0 ? 'text-blue-900' : 'text-rose-900'}">
                                <span class="font-sans uppercase">Balance</span>
                                <span>₹${formatMoney(total)}</span>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                // Render Normal Heading Breakdown Popup
                const allSources = breakdownData.sources || [];
                const grandTotal = parseFloat(breakdownData.total || 0);

                // Filter out sources that have zero total and no non-zero categories/products
                const sources = allSources.filter(source => {
                    const sourceTotal = Math.abs(parseFloat(source.total || 0));
                    const hasActiveCategories = (source.categories || []).some(cat => Math.abs(parseFloat(cat.total || 0)) > 0.001);
                    const hasActiveProducts = (source.products || []).some(prod => Math.abs(parseFloat(prod.total || 0)) > 0.001);
                    return sourceTotal > 0.001 || hasActiveCategories || hasActiveProducts;
                });

                let html = '<div class="space-y-4">';

                if (sources.length === 0) {
                    html += `
                        <div class="p-6 text-center text-slate-400 font-bold text-xs">
                            No breakdown details recorded for this item.
                        </div>
                    `;
                } else {
                    sources.forEach(source => {
                        const sourceTotal = parseFloat(source.total || 0);
                        const categories = (source.categories || []).filter(cat => Math.abs(parseFloat(cat.total || 0)) > 0.001);
                        const products = (source.products || []).filter(prod => Math.abs(parseFloat(prod.total || 0)) > 0.001);

                        html += `
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-2">
                                <div class="flex justify-between items-center font-bold text-slate-900 text-xs sm:text-sm border-b border-slate-200/80 pb-2">
                                    <span>${escapeHtml(source.name)}</span>
                                    <span class="font-mono text-slate-950">₹${formatMoney(sourceTotal)}</span>
                                </div>
                        `;

                        // Render Categories
                        if (categories.length > 0) {
                            html += '<div class="pl-3 space-y-1.5 text-xs font-medium text-slate-700 font-mono">';
                            categories.forEach(cat => {
                                const catTotal = parseFloat(cat.total || 0);
                                html += `
                                    <div class="flex justify-between items-center hover:text-slate-900">
                                        <span class="font-sans font-semibold text-slate-600">${escapeHtml(cat.name)}</span>
                                        <span>₹${formatMoney(catTotal)}</span>
                                    </div>
                                `;
                            });
                            html += '</div>';
                        }

                        // Render Products if present
                        if (products.length > 0) {
                            html += '<div class="mt-2 pt-2 border-t border-slate-200/60 pl-3 space-y-1 text-xs font-mono">';
                            html += '<div class="text-[10px] font-black uppercase text-amber-800 tracking-wider font-sans mb-1">Product Split</div>';
                            products.forEach(prod => {
                                const pTotal = parseFloat(prod.total || 0);
                                const qtyStr = prod.qty > 0 && prod.unit ? ` (${prod.qty} ${prod.unit})` : '';
                                html += `
                                    <div class="flex justify-between items-center text-slate-600 hover:text-slate-900">
                                        <span class="font-sans pl-2 border-l-2 border-amber-300">${escapeHtml(prod.name)}${escapeHtml(qtyStr)}</span>
                                        <span>₹${formatMoney(pTotal)}</span>
                                    </div>
                                `;
                            });
                            html += '</div>';
                        }

                        html += '</div>';
                    });
                }

                // Total Summary Footer Row
                html += `
                    <div class="rounded-2xl bg-slate-900 text-white p-4 flex justify-between items-center font-black font-mono text-base shadow-sm">
                        <span class="font-sans uppercase text-xs tracking-wider text-slate-300">TOTAL</span>
                        <span class="text-emerald-400">₹${formatMoney(grandTotal)}</span>
                    </div>
                `;

                html += '</div>';
                contentEl.innerHTML = html;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        };

        window.closeReportBreakdownModal = function() {
            const modal = document.getElementById('sales-report-breakdown-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.style.overflow = '';
            }
        };

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeReportBreakdownModal();
            }
        });

        function formatMoney(amount) {
            return (amount || 0).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            return String(text)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    })();
</script>
