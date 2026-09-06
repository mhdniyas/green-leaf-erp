<script>
    let activeDayData = {};

    const settings = @json($settingsJson);
    const accounts = @json($accountsJson);
    const relations = @json($relationJson);
    const headers = @json($headersJson);
    const initialTxAmounts = @json($initialTxAmounts);
    const initialTxNotes = @json($initialTxNotes);
    const shopName = @json($shop->name);

    let isSubmitting = false;
    let activeHeaderId = null;
    let activeProductHeaderId = null;
    let productQuery = '';
    let productSearchDebounceTimer = null;
    let productRowsState = {};

    document.addEventListener('DOMContentLoaded', function () {
        // Populate initial amounts and notes from existing transactions
        settings.forEach(s => {
            if (initialTxAmounts[s.id] !== undefined) {
                activeDayData[s.id] = initialTxAmounts[s.id];
                const inputEl = document.getElementById('input-s-' + s.id);
                if (inputEl) inputEl.value = initialTxAmounts[s.id] > 0 ? initialTxAmounts[s.id] : '';
            }
            if (initialTxNotes[s.id] !== undefined) {
                activeDayData.notes = activeDayData.notes || {};
                activeDayData.notes[s.id] = initialTxNotes[s.id];
                const noteEl = document.getElementById('input-note-' + s.id);
                if (noteEl) {
                    noteEl.value = initialTxNotes[s.id];
                    const wrapper = document.getElementById('note-wrapper-' + s.id);
                    if (wrapper) wrapper.classList.remove('hidden');
                }
            }
        });

        recalculateOwnerCashbook();
        if (window.lucide) lucide.createIcons();
    });

    // NAVIGATION / VIEW TOGGLES
    function showReportView() {
        document.getElementById('cashbook-dashboard-view').classList.add('hidden');
        document.getElementById('cashbook-report-view').classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    function hideReportView() {
        document.getElementById('cashbook-report-view').classList.add('hidden');
        document.getElementById('cashbook-dashboard-view').classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    function switchReportTimeframe(mode) {
        const hiddenInput = document.getElementById('report-hidden-timeframe');
        if (hiddenInput) hiddenInput.value = mode;

        const btnDaily = document.getElementById('timeframe-btn-daily');
        const btnCustom = document.getElementById('timeframe-btn-custom');
        const btnMonthly = document.getElementById('timeframe-btn-monthly');

        const inDaily = document.getElementById('filter-input-daily');
        const inCustom = document.getElementById('filter-input-custom');
        const inMonthly = document.getElementById('filter-input-monthly');

        [btnDaily, btnCustom, btnMonthly].forEach(b => {
            if (b) {
                b.className = 'px-2.5 py-1 rounded-md transition cursor-pointer text-slate-500 hover:text-slate-900';
            }
        });

        [inDaily, inCustom, inMonthly].forEach(i => {
            if (i) i.classList.add('hidden');
        });

        if (mode === 'monthly') {
            if (btnMonthly) btnMonthly.className = 'px-2.5 py-1 rounded-md transition cursor-pointer bg-white text-slate-950 shadow-2xs';
            if (inMonthly) inMonthly.classList.remove('hidden');
        } else if (mode === 'custom') {
            if (btnCustom) btnCustom.className = 'px-2.5 py-1 rounded-md transition cursor-pointer bg-white text-slate-950 shadow-2xs';
            if (inCustom) inCustom.classList.remove('hidden');
        } else {
            if (btnDaily) btnDaily.className = 'px-2.5 py-1 rounded-md transition cursor-pointer bg-white text-slate-950 shadow-2xs';
            if (inDaily) inDaily.classList.remove('hidden');
        }

        if (window.lucide) lucide.createIcons();
    }

    function toggleSummaryHeaderAccordion(headerId) {
        const itemContainer = document.getElementById('summary-items-' + headerId);
        const chevron = document.getElementById('summary-chevron-' + headerId);
        if (!itemContainer) return;

        if (itemContainer.classList.contains('hidden')) {
            itemContainer.classList.remove('hidden');
            if (chevron) chevron.classList.add('rotate-180');
        } else {
            itemContainer.classList.add('hidden');
            if (chevron) chevron.classList.remove('rotate-180');
        }
    }

    // SYNC MODAL OPEN STATE FOR FLOATING CONTROLS
    function syncModalOpenState() {
        const inModal = document.getElementById('in-header-modal');
        const outModal = document.getElementById('out-header-modal');
        const entrySheet = document.getElementById('header-entry-sheet');
        const productModal = document.getElementById('owner-product-modal');

        const hasOpenModal = (inModal && !inModal.classList.contains('hidden')) ||
                             (outModal && !outModal.classList.contains('hidden')) ||
                             (entrySheet && !entrySheet.classList.contains('hidden')) ||
                             (productModal && !productModal.classList.contains('hidden'));

        const jumpControls = document.getElementById('page-jump-controls');
        if (hasOpenModal) {
            document.body.classList.add('cashbook-modal-open');
            if (jumpControls) jumpControls.style.setProperty('display', 'none', 'important');
        } else {
            document.body.classList.remove('cashbook-modal-open');
            if (jumpControls) jumpControls.style.removeProperty('display');
        }
    }

    // MODAL BACKDROP & ESCAPE HANDLERS
    function handleModalBackdropClick(event, modalId) {
        if (event.target && event.target.id === modalId) {
            if (modalId === 'in-header-modal') {
                closeInHeaderModal();
            } else if (modalId === 'out-header-modal') {
                closeOutHeaderModal();
            } else if (modalId === 'header-entry-sheet') {
                closeHeaderEntrySheet();
            } else if (modalId === 'owner-product-modal') {
                closeOwnerProductModal();
            }
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const productModal = document.getElementById('owner-product-modal');
            const entrySheet = document.getElementById('header-entry-sheet');
            const inModal = document.getElementById('in-header-modal');
            const outModal = document.getElementById('out-header-modal');

            if (productModal && !productModal.classList.contains('hidden')) {
                closeOwnerProductModal();
            } else if (entrySheet && !entrySheet.classList.contains('hidden')) {
                closeHeaderEntrySheet();
            } else if (inModal && !inModal.classList.contains('hidden')) {
                closeInHeaderModal();
            } else if (outModal && !outModal.classList.contains('hidden')) {
                closeOutHeaderModal();
            }
        }
    });

    // IN / OUT MODALS
    function openInHeaderModal() {
        document.getElementById('in-header-modal').classList.remove('hidden');
        syncModalOpenState();
        if (window.lucide) lucide.createIcons();
    }

    function closeInHeaderModal() {
        document.getElementById('in-header-modal').classList.add('hidden');
        syncModalOpenState();
    }

    function openOutHeaderModal() {
        document.getElementById('out-header-modal').classList.remove('hidden');
        syncModalOpenState();
        if (window.lucide) lucide.createIcons();
    }

    function closeOutHeaderModal() {
        document.getElementById('out-header-modal').classList.add('hidden');
        syncModalOpenState();
    }

    // HEADER ENTRY SHEET
    function selectHeaderForEntry(headerId) {
        closeInHeaderModal();
        closeOutHeaderModal();

        activeHeaderId = String(headerId);
        const header = headers.find(h => String(h.id) === activeHeaderId);
        if (!header) return;

        // Hide all header form sections and show only the selected one
        headers.forEach(h => {
            const sec = document.getElementById('header-form-section-' + h.id);
            if (sec) sec.classList.add('hidden');
        });

        const activeSec = document.getElementById('header-form-section-' + activeHeaderId);
        if (activeSec) activeSec.classList.remove('hidden');

        document.getElementById('entry-sheet-title').textContent = header.name;
        document.getElementById('save-active-header-text').textContent = 'Save ' + header.name;

        updateActiveHeaderSubtotal();
        document.getElementById('header-entry-sheet').classList.remove('hidden');
        syncModalOpenState();
        if (window.lucide) lucide.createIcons();
    }

    function closeHeaderEntrySheet() {
        document.getElementById('header-entry-sheet').classList.add('hidden');
        activeHeaderId = null;
        syncModalOpenState();
    }

    function updateActiveHeaderSubtotal() {
        if (!activeHeaderId) return;
        const header = headers.find(h => String(h.id) === activeHeaderId);
        if (!header) return;

        let total = 0;
        (header.setting_ids || []).forEach(sId => {
            const amt = parseFloat(activeDayData[sId]) || 0;
            const s = settings.find(item => item.id === sId);
            const isMinus = s && (s.is_sales_deduction || s.payable_direction === 'minus');
            if (isMinus) {
                total -= amt;
            } else {
                total += amt;
            }
        });

        const pRows = productRowsState[activeHeaderId] || [];
        pRows.forEach(pr => {
            total += parseFloat(pr.amount) || 0;
        });

        const subEl = document.getElementById('entry-sheet-subtotal');
        if (subEl) subEl.textContent = formatCurrency(total);
    }

    function toggleNoteInput(settingId) {
        const wrapper = document.getElementById('note-wrapper-' + settingId);
        const btn = document.getElementById('note-toggle-btn-' + settingId);
        if (!wrapper) return;

        if (wrapper.classList.contains('hidden')) {
            wrapper.classList.remove('hidden');
            if (btn) btn.classList.add('hidden');
            const input = document.getElementById('input-note-' + settingId);
            if (input) input.focus();
        }
    }

    function onOwnerInputChange(inputElement) {
        const settingId = parseInt(inputElement.getAttribute('data-setting-id'));
        let val = parseFloat(inputElement.value);
        if (isNaN(val) || val < 0) val = 0;

        activeDayData[settingId] = val;
        updateActiveHeaderSubtotal();
        recalculateOwnerCashbook();
    }

    function onOwnerNoteInputChange(inputElement, settingId) {
        activeDayData.notes = activeDayData.notes || {};
        activeDayData.notes[settingId] = inputElement.value;
        validateOwnerNotes();
    }

    function validateOwnerNotes(targetSettingIds = null) {
        let allValid = true;
        const dayNotes = activeDayData.notes || {};

        settings.forEach(s => {
            if (targetSettingIds && !targetSettingIds.includes(s.id)) return;
            if (!s.requires_note) return;

            const amt = parseFloat(activeDayData[s.id]) || 0;
            const noteVal = (dayNotes[s.id] || '').trim();

            const inputEl = document.getElementById('input-note-' + s.id);
            const errorEl = document.getElementById('note-error-' + s.id);

            if (amt > 0 && noteVal.length === 0) {
                allValid = false;
                if (inputEl) {
                    inputEl.classList.add('border-rose-500', 'bg-rose-50/20');
                    inputEl.classList.remove('border-slate-200');
                }
                if (errorEl) errorEl.classList.remove('hidden');
            } else {
                if (inputEl) {
                    inputEl.classList.remove('border-rose-500', 'bg-rose-50/20');
                    inputEl.classList.add('border-slate-200');
                }
                if (errorEl) errorEl.classList.add('hidden');
            }
        });

        return allValid;
    }

    function formatInputOnBlur(inputElement) {
        let val = parseFloat(inputElement.value);
        if (isNaN(val) || val <= 0) {
            inputElement.value = '';
        } else {
            inputElement.value = val.toString();
        }
    }

    // CORE RECALCULATION ENGINE
    function recalculateOwnerCashbook() {
        let totalIncome = 0;
        let totalExpense = 0;
        let cashCollectedAtShop = 0;
        let expensesPaidFromShopCash = 0;
        let expensesPaidFromPetty = 0;
        let bankInflows = {};
        let directCompanyTotal = 0;
        let activeEntryCount = 0;

        accounts.forEach(acc => bankInflows[acc.id] = 0);

        const headerTotals = {};
        const headerActiveCounts = {};

        headers.forEach(h => {
            let headerTotal = 0;
            let headerCount = 0;

            (h.setting_ids || []).forEach(sId => {
                const amt = parseFloat(activeDayData[sId]) || 0;
                const s = settings.find(item => item.id === sId);
                const isMinus = s && (s.is_sales_deduction || s.payable_direction === 'minus');

                if (isMinus) {
                    headerTotal -= amt;
                } else {
                    headerTotal += amt;
                }

                if (s && amt > 0) {
                    headerCount++;
                    activeEntryCount++;
                    if (s.is_sales_deduction) {
                        totalIncome -= amt;
                        if (s.funding_source === 'sales' || s.funding_source === 'shop_cash') {
                            cashCollectedAtShop -= amt;
                        }
                    } else if (s.is_income) {
                        totalIncome += amt;
                        if (s.company_account_id) {
                            bankInflows[s.company_account_id] = (bankInflows[s.company_account_id] || 0) + amt;
                            directCompanyTotal += amt;
                        } else {
                            cashCollectedAtShop += amt;
                        }
                    } else {
                        totalExpense += amt;
                        if (s.funding_source === 'sales' || s.funding_source === 'shop_cash') {
                            expensesPaidFromShopCash += amt;
                        } else if (s.funding_source === 'petty') {
                            expensesPaidFromPetty += amt;
                        }
                    }
                }
            });

            // Product tagged rows subtotal
            const pRows = productRowsState[h.id] || [];
            pRows.forEach(pr => {
                const pAmt = parseFloat(pr.amount) || 0;
                if (pAmt > 0) {
                    headerTotal += pAmt;
                    headerCount++;
                    activeEntryCount++;
                    if (h.type === 'expense') {
                        totalExpense += pAmt;
                        expensesPaidFromShopCash += pAmt;
                    }
                }
            });

            headerTotals[h.id] = headerTotal;
            headerActiveCounts[h.id] = headerCount;

            // Update modal badges
            const inTotalEl = document.getElementById('in-modal-total-' + h.id);
            if (inTotalEl) inTotalEl.textContent = formatCurrency(headerTotal);
            const outTotalEl = document.getElementById('out-modal-total-' + h.id);
            if (outTotalEl) outTotalEl.textContent = formatCurrency(headerTotal);
        });

        const todayNetActivity = totalIncome - totalExpense;

        // Relations settlement
        let relationSettled = 0;
        let relationRule = 'previous_day_balance';
        let relHtml = '';

        if (relations.length > 0) {
            const rel = relations[0];
            relationRule = rel.eligibility_rule || 'previous_day_balance';
            let grossAdd = 0;
            let grossSub = 0;

            (rel.items || []).forEach(item => {
                const itemAmt = parseFloat(activeDayData[item.setting_id]) || 0;
                if (item.role === 'subtract') grossSub += itemAmt;
                else grossAdd += itemAmt;
            });

            const netRel = grossAdd - grossSub;
            const openingPayable = {{ (float) ($snapshot->closing_shop_position ?? 15000) }};
            const eligible = relationRule === 'previous_day_balance' ? Math.max(0, openingPayable) : Math.max(0, openingPayable + cashCollectedAtShop - expensesPaidFromShopCash);

            let pendingAmount = 0;
            if (netRel > 0) {
                relationSettled = Math.min(netRel, eligible);
                pendingAmount = netRel - relationSettled;
            } else {
                relationSettled = netRel;
                pendingAmount = 0;
            }

            if (relationSettled > 0 || Math.abs(netRel) > 0) {
                relHtml = `
                    <div class="flex justify-between py-1 text-xs">
                        <div>
                            <span class="block font-bold text-slate-900">${escapeHtml(rel.name || 'Supermarket Settlement')}</span>
                            <span class="text-[10px] text-slate-400 font-medium block">From: Previous Shop Balance</span>
                        </div>
                        <span class="font-mono text-rose-700 font-black">−${formatCurrency(relationSettled)}</span>
                    </div>
                `;
            }
        }

        // Update Summary Bill Relations
        const summaryRelContainer = document.getElementById('summary-bill-relations-container');
        const summaryRelContent = document.getElementById('summary-bill-relations-content');
        if (summaryRelContainer && summaryRelContent) {
            if (relHtml.trim().length > 0) {
                summaryRelContainer.classList.remove('hidden');
                summaryRelContent.innerHTML = relHtml;
            } else {
                summaryRelContainer.classList.add('hidden');
            }
        }

        const repRelContainer = document.getElementById('report-relations-container');
        const repRelEl = document.getElementById('report-relations-breakdown');
        if (repRelEl) repRelEl.innerHTML = relHtml;
        if (repRelContainer) {
            if (relHtml.trim().length > 0) {
                repRelContainer.classList.remove('hidden');
            } else {
                repRelContainer.classList.add('hidden');
            }
        }

        // Top KPIs
        const shopHeldNet = Math.max(0, cashCollectedAtShop - expensesPaidFromShopCash);
        const openShopBal = {{ (float) ($snapshot->closing_shop_position ?? 15000) }};
        const closingShopBal = openShopBal - relationSettled + shopHeldNet;
        const openingPetty = {{ (float) ($snapshot->petty_balance ?? 5440) }};
        const closingPetty = Math.max(0, openingPetty - expensesPaidFromPetty);

        // Update Dashboard Elements
        const kpiShopBal = document.getElementById('kpi-shop-balance');
        if (kpiShopBal) kpiShopBal.textContent = formatCurrency(closingShopBal);

        const kpiNet = document.getElementById('kpi-today-net-activity');
        if (kpiNet) {
            kpiNet.textContent = formatCurrency(todayNetActivity);
            kpiNet.className = 'font-mono text-base sm:text-xl font-black ' + (todayNetActivity >= 0 ? 'text-emerald-700' : 'text-rose-700');
        }

        const kpiCashHeld = document.getElementById('kpi-cash-held');
        if (kpiCashHeld) kpiCashHeld.textContent = formatCurrency(shopHeldNet);

        const kpiCompany = document.getElementById('kpi-reached-company');
        if (kpiCompany) kpiCompany.textContent = formatCurrency(directCompanyTotal);

        const kpiPetty = document.getElementById('kpi-petty-closing');
        if (kpiPetty) kpiPetty.textContent = formatCurrency(closingPetty);

        // Update Today's Row & Bill Footer
        const todayCountEl = document.getElementById('today-entry-count');
        if (todayCountEl) todayCountEl.textContent = activeEntryCount + (activeEntryCount === 1 ? ' Entry Recorded' : ' Entries Recorded');

        // Update Main Bill Sections, Today Summary Bill, and Report Breakdown
        renderSummaryBillHeaders();
        renderMainBillSections();
        renderReportBreakdown();

        // Update Report View Elements
        const repNet = document.getElementById('report-net-activity');
        if (repNet) {
            repNet.textContent = formatCurrency(todayNetActivity);
            repNet.className = 'font-mono text-xs sm:text-sm font-black ' + (todayNetActivity >= 0 ? 'text-emerald-700' : 'text-rose-700');
        }

        const repHeld = document.getElementById('report-pos-held');
        if (repHeld) repHeld.textContent = formatCurrency(shopHeldNet);

        const repComp = document.getElementById('report-pos-company');
        if (repComp) repComp.textContent = formatCurrency(directCompanyTotal);

        const repPetty = document.getElementById('report-pos-petty');
        if (repPetty) repPetty.textContent = formatCurrency(closingPetty);

        const repShop = document.getElementById('report-pos-shop-bal');
        if (repShop) repShop.textContent = formatCurrency(closingShopBal);

        if (window.lucide) lucide.createIcons();
    }

    function renderSummaryBillHeaders() {
        const container = document.getElementById('summary-bill-headers-container');
        if (!container) return;

        if (headers.length === 0) {
            container.innerHTML = '<div class="py-2 text-center text-xs text-slate-400">No headers configured</div>';
            return;
        }

        const incomeHeaders = headers.filter(h => h.type === 'income');
        const expenseHeaders = headers.filter(h => h.type === 'expense');
        const orderedHeaders = [...incomeHeaders, ...expenseHeaders];

        container.innerHTML = orderedHeaders.map(h => {
            const isIncome = h.type === 'income';
            let hTotal = 0;

            const childItems = [];
            (h.setting_ids || []).map(sId => {
                const amt = parseFloat(activeDayData[sId]) || 0;
                const s = settings.find(item => item.id === sId);
                if (!s) return;

                const isMinus = s.is_sales_deduction || s.payable_direction === 'minus';
                if (isMinus) {
                    hTotal -= amt;
                } else {
                    hTotal += amt;
                }

                if (amt > 0) {
                    const name = s.name || 'Item';
                    const signPrefix = isMinus ? '−' : '+';
                    const signClass = isMinus ? 'text-rose-600 font-bold' : 'text-slate-900 font-bold';
                    childItems.push(`
                        <div class="flex items-center justify-between py-1 text-xs text-slate-600">
                            <span class="truncate pr-2">${escapeHtml(name)}</span>
                            <span class="font-mono ${signClass} shrink-0">
                                ${signPrefix} ${formatCurrency(amt)}
                            </span>
                        </div>
                    `);
                }
            });

            const pRows = productRowsState[h.id] || [];
            pRows.forEach(pr => {
                const pAmt = parseFloat(pr.amount) || 0;
                const pQty = parseFloat(pr.qty);
                const hasQty = !isNaN(pQty) && pQty > 0;
                hTotal += pAmt;
                if (pAmt > 0 || hasQty) {
                    const avgStr = (hasQty && pAmt > 0) ? ` @ ₹${(pAmt / pQty).toFixed(2)}/${escapeHtml(pr.unit || 'unit')}` : '';
                    const detailStr = hasQty ? ` <span class="text-[10px] font-semibold text-slate-400">(${pQty} ${escapeHtml(pr.unit || '')}${avgStr})</span>` : '';
                    childItems.push(`
                        <div class="flex items-center justify-between py-1 text-xs text-slate-600">
                            <span class="truncate pr-2">${escapeHtml(pr.productName)}${detailStr}</span>
                            <span class="font-mono font-bold text-slate-900 shrink-0">+ ${formatCurrency(pAmt)}</span>
                        </div>
                    `);
                }
            });

            const signPrefix = isIncome ? '' : '−';
            const signTextClass = isIncome ? 'text-emerald-700' : 'text-rose-700';
            const formattedTotal = `${signPrefix}${formatCurrency(Math.abs(hTotal))}`;

            const childItemsHtml = childItems.length > 0
                ? childItems.join('')
                : '<div class="py-1 text-[11px] text-slate-400 italic">No entries recorded yet (tap to add)</div>';

            return `
                <div class="py-1.5">
                    <div class="flex items-center justify-between cursor-pointer group hover:bg-slate-50/80 p-1 rounded-lg transition" onclick="toggleSummaryHeaderAccordion('${h.id}')">
                        <div class="flex items-center gap-2 min-w-0 flex-1">
                            <span class="h-2 w-2 rounded-full ${isIncome ? 'bg-emerald-500' : 'bg-rose-500'} shrink-0"></span>
                            <span class="text-xs sm:text-sm font-bold text-slate-900 group-hover:text-emerald-700 transition truncate">${escapeHtml(h.name)}</span>
                            <span class="text-slate-400 group-hover:text-slate-700">
                                <i data-lucide="chevron-down" id="summary-chevron-${h.id}" class="h-3.5 w-3.5 inline transition-transform"></i>
                            </span>
                        </div>
                        <div class="flex items-center gap-2.5 shrink-0">
                            <span class="font-mono text-xs sm:text-sm font-black ${signTextClass}">
                                ${formattedTotal}
                            </span>
                            <button type="button" onclick="event.stopPropagation(); selectHeaderForEntry('${h.id}')"
                                    class="text-[10px] font-black text-slate-600 hover:text-emerald-700 px-2 py-0.5 rounded-md bg-slate-100 hover:bg-emerald-50 transition border border-slate-200/60 shadow-2xs">
                                Edit
                            </button>
                        </div>
                    </div>
                    <div id="summary-items-${h.id}" class="hidden pl-4 pr-1 pt-1 pb-1 space-y-0.5 text-xs border-l-2 border-slate-100 ml-2 mt-1">
                        ${childItemsHtml}
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderMainBillSections() {
        const container = document.getElementById('today-headers-summary-container');
        const emptyState = document.getElementById('today-empty-state');
        if (!container) return;

        if (headers.length === 0) {
            if (emptyState) emptyState.classList.remove('hidden');
            container.innerHTML = '';
            return;
        }

        if (emptyState) emptyState.classList.add('hidden');

        // Order: Income headers first, then Expense headers
        const incomeHeaders = headers.filter(h => h.type === 'income');
        const expenseHeaders = headers.filter(h => h.type === 'expense');
        const orderedHeaders = [...incomeHeaders, ...expenseHeaders];

        container.innerHTML = orderedHeaders.map(h => {
            const isIncome = h.type === 'income';
            let hTotal = 0;

            const childLines = (h.setting_ids || []).map(sId => {
                const amt = parseFloat(activeDayData[sId]) || 0;
                const s = settings.find(item => item.id === sId);
                if (!s) return '';

                const isMinus = s.is_sales_deduction || s.payable_direction === 'minus';
                if (isMinus) {
                    hTotal -= amt;
                } else {
                    hTotal += amt;
                }

                const name = s.name || 'Item';
                let sub = '';
                if (name.toLowerCase() === 'cash' || name.toLowerCase() === 'cash sales') {
                    sub = 'Remaining cash in shop';
                } else if (s.company_account_name) {
                    sub = s.company_account_name;
                } else if (s.destination_label) {
                    sub = s.destination_label;
                }
                const note = (activeDayData.notes && activeDayData.notes[sId]) ? activeDayData.notes[sId].trim() : '';

                const signPrefix = isMinus ? '−' : '+';
                const signBadgeClass = isMinus ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700';
                const signTextClass = isMinus ? 'text-rose-600' : 'text-emerald-700';

                return `
                    <div class="flex items-start justify-between gap-2 py-1">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-xs text-[9px] font-black ${signBadgeClass} shrink-0">${signPrefix}</span>
                                <span class="text-xs font-bold text-slate-800 leading-tight truncate">${escapeHtml(name)}</span>
                            </div>
                            ${sub ? `<span class="text-[10px] text-slate-400 font-medium block leading-tight mt-0.5 ml-5 truncate">${escapeHtml(sub)}</span>` : ''}
                            ${note ? `<span class="text-[10px] text-emerald-600 font-medium block leading-tight mt-0.5 ml-5 truncate">${escapeHtml(note)}</span>` : ''}
                        </div>
                        <span class="font-mono text-xs font-black shrink-0 ${amt > 0 ? (isMinus ? 'text-rose-600' : 'text-slate-900') : 'text-slate-400'}">
                            ${amt > 0 ? `<span class="${signTextClass} mr-0.5 font-bold">${signPrefix}</span>${formatCurrency(amt)}` : formatCurrency(amt)}
                        </span>
                    </div>
                `;
            }).filter(Boolean).join('');

            const pRows = productRowsState[h.id] || [];
            const productLines = pRows.map(pr => {
                const pAmt = parseFloat(pr.amount) || 0;
                const pQty = parseFloat(pr.qty);
                const hasQty = !isNaN(pQty) && pQty > 0;
                hTotal += pAmt;
                const avgStr = (hasQty && pAmt > 0) ? ` @ ₹${(pAmt / pQty).toFixed(2)}/${escapeHtml(pr.unit || 'unit')}` : '';
                const qtySubtitle = hasQty ? `${pQty} ${escapeHtml(pr.unit || '')}${avgStr}` : (pr.sku || '');
                return `
                    <div class="flex items-start justify-between gap-2 py-1">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-xs text-[9px] font-black bg-emerald-100 text-emerald-700 shrink-0">+</span>
                                <span class="text-xs font-bold text-slate-800 leading-tight truncate">${escapeHtml(pr.productName)}</span>
                            </div>
                            ${qtySubtitle ? `<span class="text-[10px] text-slate-400 font-medium block leading-tight mt-0.5 ml-5 truncate">${escapeHtml(qtySubtitle)}</span>` : ''}
                        </div>
                        <span class="font-mono text-xs font-black text-slate-900 shrink-0 ${pAmt > 0 ? '' : 'text-slate-400'}">
                            ${pAmt > 0 ? `<span class="text-emerald-700 mr-0.5 font-bold">+</span>${formatCurrency(pAmt)}` : formatCurrency(pAmt)}
                        </span>
                    </div>
                `;
            }).join('');

            const noProductsPrompt = (h.product_tagging_enabled && pRows.length === 0 && (h.setting_ids || []).length === 0)
                ? `<div class="py-1 text-[11px] text-slate-400 italic">No products recorded yet (tap to add)</div>`
                : '';

            return `
                <div onclick='selectHeaderForEntry(${JSON.stringify(h.id)})'
                     class="rounded-xl sm:rounded-2xl border border-slate-200 bg-white p-3.5 sm:p-4 shadow-xs hover:border-emerald-300 hover:shadow-sm transition cursor-pointer group space-y-2 select-none">
                    
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="h-2 w-2 rounded-full ${isIncome ? 'bg-emerald-500' : 'bg-rose-500'} shrink-0"></span>
                            <span class="text-xs sm:text-sm font-black uppercase text-slate-900 group-hover:text-emerald-700 transition truncate">${escapeHtml(h.name)}</span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="font-mono text-xs sm:text-sm font-black ${isIncome ? 'text-emerald-700' : 'text-slate-900'}">
                                ${formatCurrency(hTotal)}
                            </span>
                            <span class="inline-flex items-center text-[10px] font-bold text-slate-400 group-hover:text-emerald-700 transition">
                                <span>Edit</span>
                                <i data-lucide="chevron-right" class="h-3 w-3 ml-0.5"></i>
                            </span>
                        </div>
                    </div>

                    <div class="space-y-0.5 divide-y divide-slate-50">
                        ${childLines}
                        ${productLines}
                        ${noProductsPrompt}
                    </div>

                    <div class="flex items-center justify-between border-t border-slate-100 pt-1.5 text-[11px] font-bold text-slate-500">
                        <span>Total ${escapeHtml(h.name)}</span>
                        <span class="font-mono font-bold text-slate-900">${formatCurrency(hTotal)}</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderReportBreakdown() {
        const container = document.getElementById('report-headers-breakdown');
        if (!container) return;

        const headerSections = headers.map(h => {
            let hTotal = 0;

            const settingLines = (h.setting_ids || []).map(sId => {
                const amt = parseFloat(activeDayData[sId]) || 0;
                if (amt <= 0) return '';
                const s = settings.find(item => item.id === sId);
                const name = s ? s.name : 'Item';
                const isMinus = s && (s.is_sales_deduction || s.payable_direction === 'minus');

                if (isMinus) {
                    hTotal -= amt;
                } else {
                    hTotal += amt;
                }

                const signPrefix = isMinus ? '−' : '+';
                const signBadgeClass = isMinus ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700';

                return `
                    <div class="flex justify-between py-1 text-slate-700 font-medium">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-xs text-[9px] font-black ${signBadgeClass} shrink-0">${signPrefix}</span>
                            <span class="truncate">${escapeHtml(name)}</span>
                        </div>
                        <span class="font-mono font-bold ${isMinus ? 'text-rose-600' : 'text-slate-900'}">${isMinus ? '− ' : '+ '}${formatCurrency(amt)}</span>
                    </div>
                `;
            }).filter(Boolean).join('');

            const pRows = productRowsState[h.id] || [];
            const productLines = pRows.map(pr => {
                const pAmt = parseFloat(pr.amount) || 0;
                const pQty = parseFloat(pr.qty);
                const hasQty = !isNaN(pQty) && pQty > 0;
                if (pAmt <= 0 && !hasQty) return '';
                hTotal += pAmt;
                const avgStr = (hasQty && pAmt > 0) ? ` @ ₹${(pAmt / pQty).toFixed(2)}/${escapeHtml(pr.unit || 'unit')}` : '';
                const qtySubtitle = hasQty ? ` <span class="text-[10px] text-slate-400 font-normal">(${pQty} ${escapeHtml(pr.unit || '')}${avgStr})</span>` : '';
                return `
                    <div class="flex justify-between py-1 text-slate-700 font-medium">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-xs text-[9px] font-black bg-emerald-100 text-emerald-700 shrink-0">+</span>
                            <span class="truncate">${escapeHtml(pr.productName)}${qtySubtitle}</span>
                        </div>
                        <span class="font-mono font-bold text-slate-900">+ ${formatCurrency(pAmt)}</span>
                    </div>
                `;
            }).filter(Boolean).join('');

            if (!settingLines && !productLines) {
                return '';
            }

            return `
                <div class="space-y-2">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-1.5">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-900">${escapeHtml(h.name)}</span>
                        <span class="font-mono text-xs font-black text-slate-900">${formatCurrency(hTotal)}</span>
                    </div>
                    <div class="space-y-0.5 text-xs">
                        ${settingLines}
                        ${productLines}
                    </div>
                    <div class="flex justify-between border-t border-slate-100 pt-1.5 text-[11px] font-bold text-slate-500">
                        <span>Subtotal</span>
                        <span class="font-mono font-bold text-slate-900">${formatCurrency(hTotal)}</span>
                    </div>
                </div>
            `;
        }).filter(Boolean);

        if (headerSections.length > 0) {
            container.innerHTML = headerSections.join('<div class="border-t border-dashed border-slate-200 my-4"></div>');
        } else {
            container.innerHTML = `
                <div class="py-6 text-center text-xs font-medium text-slate-400">
                    No cashbook transactions recorded for this date.
                </div>
            `;
        }
    }

    // SAVE ACTIVE HEADER ENTRIES
    async function saveActiveHeaderEntries() {
        if (isSubmitting || !activeHeaderId) return;

        const header = headers.find(h => String(h.id) === activeHeaderId);
        if (!header) return;

        if (!validateOwnerNotes(header.setting_ids)) {
            alert('Please fill out all required notes for this header.');
            return;
        }

        const entriesPayload = [];
        // Include entries for all configured settings in active state (new/updated and zeroed out)
        settings.forEach(s => {
            const amt = parseFloat(activeDayData[s.id]) || 0;
            const wasRecorded = initialTxAmounts[s.id] !== undefined && initialTxAmounts[s.id] > 0;
            const isInActiveHeader = header.setting_ids && header.setting_ids.includes(s.id);

            if (amt > 0 || wasRecorded || isInActiveHeader) {
                const noteVal = (activeDayData.notes && activeDayData.notes[s.id]) ? activeDayData.notes[s.id].trim() : null;
                entriesPayload.push({
                    entry_type_code: s.code,
                    amount: amt,
                    funding_source: s.funding_source || 'none',
                    notes: noteVal
                });
            }
        });

        const btn = document.getElementById('save-active-header-btn');
        const textEl = document.getElementById('save-active-header-text');
        if (btn) btn.disabled = true;
        if (textEl) textEl.textContent = 'Saving...';
        isSubmitting = true;

        try {
            const response = await fetch('{{ route('shop-owner.cashbook.api.bulk-record-entries') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    business_date: '{{ $selectedDate->toDateString() }}',
                    entries: entriesPayload
                })
            });

            const data = await response.json();
            if (data.success) {
                // Update baseline initial amounts to match active saved data
                settings.forEach(s => {
                    const amt = parseFloat(activeDayData[s.id]) || 0;
                    if (amt > 0) {
                        initialTxAmounts[s.id] = amt;
                    } else {
                        delete initialTxAmounts[s.id];
                    }
                });

                closeHeaderEntrySheet();
                recalculateOwnerCashbook();
            } else {
                alert(data.message || 'Error saving cashbook header.');
            }
        } catch (err) {
            alert('Network error while saving cashbook. Please try again.');
        } finally {
            isSubmitting = false;
            if (btn) btn.disabled = false;
            if (textEl) textEl.textContent = 'Save ' + (header ? header.name : 'Header');
            if (window.lucide) lucide.createIcons();
        }
    }

    // PRODUCT TAGGING FUNCTIONS
    function openOwnerProductModal(headerId, headerName) {
        activeProductHeaderId = headerId;
        const titleEl = document.getElementById('owner-product-modal-title');
        if (titleEl) {
            titleEl.innerHTML = `<i data-lucide="tag" class="h-4 w-4 text-emerald-600"></i> <span>Select Product for ${escapeHtml(headerName)}</span>`;
        }
        document.getElementById('owner-product-modal').classList.remove('hidden');
        syncModalOpenState();
        fetchOwnerProducts('', 1);
        if (window.lucide) lucide.createIcons();
    }

    function closeOwnerProductModal() {
        document.getElementById('owner-product-modal').classList.add('hidden');
        syncModalOpenState();
    }

    function onOwnerProductSearchInput() {
        if (productSearchDebounceTimer) clearTimeout(productSearchDebounceTimer);
        productSearchDebounceTimer = setTimeout(() => {
            const input = document.getElementById('owner-product-search-input');
            productQuery = input ? input.value.trim() : '';
            fetchOwnerProducts(productQuery, 1);
        }, 250);
    }

    async function fetchOwnerProducts(query = '', page = 1) {
        const container = document.getElementById('owner-product-list');
        if (!container) return;
        container.innerHTML = `<div class="p-6 text-center text-xs font-bold text-slate-400">Searching products...</div>`;

        try {
            const url = new URL('{{ route('shop-owner.cashbook.api.products.search') }}', window.location.origin);
            if (query) url.searchParams.set('q', query);
            if (activeProductHeaderId) url.searchParams.set('header_id', activeProductHeaderId);
            url.searchParams.set('page', page);

            const res = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            if (data.products && data.products.length > 0) {
                const existing = productRowsState[activeProductHeaderId] || [];
                container.innerHTML = data.products.map(p => {
                    const isAdded = existing.some(r => r.productId === p.id);
                    const unitsJsonStr = escapeJsString(JSON.stringify(p.units || []));
                    const defUnit = escapeJsString(p.unit || 'unit');
                    const unitsCount = (p.units || []).length;
                    const unitBadge = unitsCount > 1 ? `${unitsCount} units` : (p.unit ? p.unit.toUpperCase() : 'UNIT');
                    return `
                        <div class="p-3 rounded-2xl bg-slate-50/70 border border-slate-100 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                <div class="w-8 h-8 rounded-full bg-white border border-slate-200/60 flex items-center justify-center text-slate-500 shrink-0">
                                    <i data-lucide="package" class="h-4 w-4 text-emerald-600"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-slate-900 truncate">${escapeHtml(p.name)}</span>
                                        <span class="text-[9px] font-black uppercase text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded">${escapeHtml(unitBadge)}</span>
                                    </div>
                                    <span class="text-[10px] font-semibold text-slate-400 block">${escapeHtml(p.sku || 'N/A')}</span>
                                </div>
                            </div>
                            ${isAdded ? '<span class="text-[10px] font-bold text-slate-400 bg-slate-200/60 px-2.5 py-1 rounded-full">Added</span>' : `
                                <button type="button" onclick="selectOwnerProduct(${p.id}, '${escapeJsString(p.name)}', '${escapeJsString(p.sku || '')}', '${defUnit}', '${unitsJsonStr}')"
                                        class="inline-flex items-center justify-center rounded-full bg-emerald-600 px-3 py-1 text-xs font-bold text-white hover:bg-emerald-700 active:scale-95 transition cursor-pointer shadow-2xs">
                                    Select
                                </button>
                            `}
                        </div>
                    `;
                }).join('');
            } else {
                container.innerHTML = `<div class="p-6 text-center text-xs font-bold text-slate-400 bg-slate-50 rounded-2xl border border-slate-100">No products found.</div>`;
            }
        } catch (e) {
            container.innerHTML = `<div class="p-6 text-center text-xs font-bold text-rose-500 bg-rose-50 rounded-2xl border border-rose-100">Error loading catalog.</div>`;
        }
    }

    let replacingProductId = null;

    function changeOwnerProductRow(hId, oldProductId) {
        replacingProductId = oldProductId;
        const h = ownerHeadersData.find(header => String(header.id) === String(hId));
        openOwnerProductModal(hId, h ? h.name : 'Header');
        const titleEl = document.getElementById('owner-product-modal-title');
        if (titleEl) {
            titleEl.innerHTML = `<i data-lucide="arrow-left-right" class="h-4 w-4 text-emerald-600"></i> <span>Change Product for ${escapeHtml(h ? h.name : 'Header')}</span>`;
        }
        if (window.lucide) lucide.createIcons();
    }

    function selectOwnerProduct(productId, productName, sku, defaultUnit, unitsJson) {
        const hId = activeProductHeaderId;
        if (!hId) return;

        let units = [];
        try {
            units = typeof unitsJson === 'string' ? JSON.parse(unitsJson) : (unitsJson || []);
        } catch (e) {
            units = [];
        }
        if (units.length === 0 && defaultUnit) {
            units = [{ unit: defaultUnit, label: defaultUnit.toUpperCase(), is_base: true }];
        }
        const unit = defaultUnit || (units.length > 0 ? units[0].unit : 'unit');

        productRowsState[hId] = productRowsState[hId] || [];

        if (replacingProductId !== null) {
            const idx = productRowsState[hId].findIndex(r => r.productId === replacingProductId);
            if (idx !== -1) {
                const oldRow = productRowsState[hId][idx];
                productRowsState[hId][idx] = {
                    productId,
                    productName,
                    sku,
                    qty: oldRow.qty || '',
                    unit: unit,
                    units: units,
                    amount: oldRow.amount || 0,
                    avgPrice: oldRow.avgPrice || null
                };
                const qty = parseFloat(oldRow.qty);
                const amt = parseFloat(oldRow.amount) || 0;
                if (!isNaN(qty) && qty > 0 && amt > 0) {
                    productRowsState[hId][idx].avgPrice = Math.round((amt / qty) * 100) / 100;
                }
            }
            replacingProductId = null;
        } else {
            if (!productRowsState[hId].some(r => r.productId === productId)) {
                productRowsState[hId].push({
                    productId,
                    productName,
                    sku,
                    qty: '',
                    unit: unit,
                    units: units,
                    amount: 0,
                    avgPrice: null
                });
            }
        }
        closeOwnerProductModal();
        renderOwnerProductRows(hId);
        updateActiveHeaderSubtotal();
        recalculateOwnerCashbook();
    }

    function removeOwnerProductRow(hId, productId) {
        if (!productRowsState[hId]) return;
        productRowsState[hId] = productRowsState[hId].filter(r => r.productId !== productId);
        renderOwnerProductRows(hId);
        updateActiveHeaderSubtotal();
        recalculateOwnerCashbook();
    }

    function renderOwnerProductRows(hId) {
        const container = document.getElementById('product-rows-container-' + hId);
        if (!container) return;

        const rows = productRowsState[hId] || [];
        if (rows.length === 0) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = rows.map(r => {
            const qty = parseFloat(r.qty);
            const amt = parseFloat(r.amount) || 0;
            const hasAvg = !isNaN(qty) && qty > 0 && amt > 0;
            const avgStr = hasAvg ? `Avg: ₹${(amt / qty).toFixed(2)} / ${escapeHtml(r.unit || 'unit')}` : (r.sku || 'Tagged Item');
            const avgClass = hasAvg
                ? 'inline-flex items-center gap-1 rounded-md bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 text-[10px] font-black text-emerald-800'
                : 'text-[10px] text-slate-400 font-semibold truncate';

            const unitsList = r.units || [];
            const hasMultipleUnits = unitsList.length > 1;

            return `
                <div class="p-2.5 rounded-xl bg-white border border-slate-200/90 shadow-2xs space-y-2" data-product-row="${r.productId}">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0 flex-1 flex items-center gap-1.5 flex-wrap">
                            <span class="text-xs font-bold text-slate-900 truncate">${escapeHtml(r.productName)}</span>
                            <span class="${avgClass}" id="avg-price-badge-${hId}-${r.productId}">
                                ${escapeHtml(avgStr)}
                            </span>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <button type="button" onclick="changeOwnerProductRow('${hId}', ${r.productId})" class="inline-flex items-center gap-1 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 px-2 py-1 text-[10px] font-extrabold transition cursor-pointer" title="Change to another product">
                                <i data-lucide="arrow-left-right" class="h-3 w-3 text-slate-500"></i>
                                <span>Change</span>
                            </button>
                            <button type="button" onclick="removeOwnerProductRow('${hId}', ${r.productId})" class="w-6 h-6 rounded-full hover:bg-rose-50 text-slate-400 hover:text-rose-600 flex items-center justify-center transition shrink-0 cursor-pointer" title="Remove product">
                                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-12 gap-2 items-center">
                        <div class="col-span-6 flex items-center gap-1">
                            <div class="relative flex-1">
                                <input type="number" inputmode="decimal" min="0" step="any"
                                       value="${r.qty !== null && r.qty !== undefined && r.qty !== '' ? r.qty : ''}"
                                       oninput="onOwnerProductQtyChange('${hId}', ${r.productId}, this)"
                                       placeholder="Qty"
                                       class="h-8 w-full rounded-lg border border-slate-200 bg-slate-50/80 px-2 text-right text-xs font-black font-mono text-slate-950 focus:bg-white focus:border-emerald-500 focus:outline-none transition">
                            </div>
                            ${hasMultipleUnits ? `
                                <select onchange="onOwnerProductUnitChange('${hId}', ${r.productId}, this)"
                                        class="h-8 rounded-lg border border-slate-200 bg-white px-1.5 text-[11px] font-black text-slate-800 uppercase focus:border-emerald-500 focus:outline-none cursor-pointer shrink-0">
                                    ${unitsList.map(u => `<option value="${escapeHtml(u.unit)}" ${u.unit.toLowerCase() === (r.unit || '').toLowerCase() ? 'selected' : ''}>${escapeHtml(u.label || u.unit.toUpperCase())}</option>`).join('')}
                                </select>
                            ` : `
                                <span class="h-8 inline-flex items-center justify-center rounded-lg bg-slate-100 border border-slate-200/80 px-2 text-[10px] font-black text-slate-700 uppercase shrink-0">
                                    ${escapeHtml(r.unit ? r.unit.toUpperCase() : 'UNIT')}
                                </span>
                            `}
                        </div>

                        <div class="col-span-6 relative">
                            <span class="absolute inset-y-0 left-0 pl-2 flex items-center text-slate-400 font-bold text-xs pointer-events-none">₹</span>
                            <input type="number" inputmode="decimal" min="0" step="0.01"
                                   value="${r.amount || ''}"
                                   oninput="onOwnerProductAmountChange('${hId}', ${r.productId}, this)"
                                   placeholder="0.00"
                                   class="h-8 w-full rounded-lg border border-slate-200 bg-slate-50/80 pl-5 pr-2 text-right text-xs sm:text-sm font-black font-mono text-slate-950 focus:bg-white focus:border-emerald-500 focus:outline-none transition">
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        if (window.lucide) lucide.createIcons();
    }

    function onOwnerProductQtyChange(hId, productId, inputEl) {
        const qtyVal = inputEl.value.trim();
        const qty = parseFloat(qtyVal);
        const rows = productRowsState[hId] || [];
        const row = rows.find(r => r.productId === productId);
        if (row) {
            row.qty = isNaN(qty) ? '' : qty;
            const amt = parseFloat(row.amount) || 0;
            if (row.qty > 0 && amt > 0) {
                row.avgPrice = Math.round((amt / row.qty) * 100) / 100;
            } else {
                row.avgPrice = null;
            }
            updateProductAvgPriceBadge(hId, productId, row);
        }
        updateActiveHeaderSubtotal();
        recalculateOwnerCashbook();
    }

    function onOwnerProductAmountChange(hId, productId, inputEl) {
        const amt = parseFloat(inputEl.value) || 0;
        const rows = productRowsState[hId] || [];
        const row = rows.find(r => r.productId === productId);
        if (row) {
            row.amount = amt;
            const qty = parseFloat(row.qty);
            if (!isNaN(qty) && qty > 0 && amt > 0) {
                row.avgPrice = Math.round((amt / qty) * 100) / 100;
            } else {
                row.avgPrice = null;
            }
            updateProductAvgPriceBadge(hId, productId, row);
        }
        updateActiveHeaderSubtotal();
        recalculateOwnerCashbook();
    }

    function onOwnerProductUnitChange(hId, productId, selectEl) {
        const rows = productRowsState[hId] || [];
        const row = rows.find(r => r.productId === productId);
        if (row) {
            row.unit = selectEl.value;
            updateProductAvgPriceBadge(hId, productId, row);
        }
        updateActiveHeaderSubtotal();
        recalculateOwnerCashbook();
    }

    function updateProductAvgPriceBadge(hId, productId, row) {
        const badgeEl = document.getElementById(`avg-price-badge-${hId}-${productId}`);
        if (!badgeEl) return;
        const qty = parseFloat(row.qty);
        const amt = parseFloat(row.amount) || 0;
        if (!isNaN(qty) && qty > 0 && amt > 0) {
            const avg = (amt / qty).toFixed(2);
            badgeEl.className = 'inline-flex items-center gap-1 rounded-md bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 text-[10px] font-black text-emerald-800';
            badgeEl.textContent = `Avg: ₹${avg} / ${row.unit || 'unit'}`;
        } else {
            badgeEl.className = 'text-[10px] text-slate-400 font-semibold truncate';
            badgeEl.textContent = row.sku || 'Tagged Item';
        }
    }

    function formatCurrency(amount, preserveSign = true) {
        const val = parseFloat(amount) || 0;
        const prefix = (preserveSign && val < -0.0001) ? '−' : '';
        return prefix + '₹' + Math.abs(val).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(str) {
        return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function escapeJsString(str) {
        return (str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }
</script>
