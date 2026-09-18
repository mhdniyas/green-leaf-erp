<script>
    const vpPurchasableProducts = @json($purchasableProducts ?? $products ?? []);
    const vpLinkedVendorsData = @json($linkedVendors ?? $suppliers ?? []);
    const vpIsVendorCreationAllowed = @json($shop->isVendorCreationAllowed());
    const vpStorePurchaseUrl = @json(route('shop-owner.purchasing.store'));
    const vpCreateVendorUrl = @json(route('shop-owner.purchasing.vendors.store'));
    const vpActiveBusinessDate = @json(isset($selectedDate) ? ($selectedDate instanceof \Carbon\CarbonInterface ? $selectedDate->toDateString() : (string) $selectedDate) : today()->toDateString());
    const vpSettingsList = @json($categories ?? []);

    let vpRowIndex = 0;
    let isVpSubmitting = false;
    let isCreatingVendor = false;

    function handleModalBackdropClick(event, modalId) {
        if (event.target === event.currentTarget) {
            closeModal(modalId);
        }
    }

    function closeModal(modalId) {
        const el = document.getElementById(modalId);
        if (el) el.classList.add('hidden');
    }

    function openModal(modalId) {
        const el = document.getElementById(modalId);
        if (el) el.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    function closeVpVendorDropdown() {
        const menu = document.getElementById('vp-vendor-menu');
        if (menu) menu.classList.add('hidden');
    }

    function toggleVpVendorDropdown(event) {
        if (event) {
            event.stopPropagation();
        }

        const menu = document.getElementById('vp-vendor-menu');
        if (!menu) return;

        const isCurrentlyOpen = !menu.classList.contains('hidden');
        closeAllVpProductDropdowns();
        closeVpVendorDropdown();

        if (!isCurrentlyOpen) {
            menu.classList.remove('hidden');
            const searchInput = document.getElementById('vp-vendor-search');
            if (searchInput) {
                searchInput.value = '';
                renderVpVendorOptions('');
                setTimeout(() => searchInput.focus(), 50);
            }
        }
    }

    function getActiveModalVendors() {
        const categoryId = document.getElementById('vp-category-id')?.value;
        if (!categoryId) {
            return vpLinkedVendorsData || [];
        }
        const s = (vpSettingsList || []).find(item => String(item.id) === String(categoryId));
        if (!s) {
            return vpLinkedVendorsData || [];
        }
        if (s.vendor_access_mode === 'defined_only') {
            const definedIds = (s.defined_supplier_ids || []).map(id => parseInt(id, 10));
            return (vpLinkedVendorsData || []).filter(v => definedIds.includes(parseInt(v.id, 10)));
        }
        return vpLinkedVendorsData || [];
    }

    function renderVpVendorOptions(filterTerm = '') {
        const container = document.getElementById('vp-vendor-options');
        if (!container) return;

        const hiddenInput = document.getElementById('vp-vendor-select');
        const selectedId = hiddenInput ? hiddenInput.value : '';
        const term = (filterTerm || '').trim().toLowerCase();
        const activeVendors = getActiveModalVendors();

        const filtered = activeVendors.filter(v => {
            if (!term) return true;
            const nameMatch = (v.name || '').toLowerCase().includes(term);
            const mobileMatch = (v.mobile_number || '').toLowerCase().includes(term);
            return nameMatch || mobileMatch;
        });

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="px-3 py-3 text-center text-xs font-medium text-slate-400">
                    No vendors match "${escapeHtml(filterTerm)}"
                </div>
            `;
            return;
        }

        let html = '';
        filtered.forEach(v => {
            const isSelected = String(v.id) === String(selectedId);
            html += `
                <button type="button" role="option" data-vendor-id="${v.id}"
                        onclick="selectVpVendor(${v.id}, event)"
                        class="vp-vendor-option-item w-full px-3 py-2 text-left hover:bg-emerald-50 transition cursor-pointer ${isSelected ? 'bg-emerald-50/80 font-bold' : ''}">
                    <div class="font-bold text-slate-900 truncate">${escapeHtml(v.name)}</div>
                    ${v.mobile_number ? `<div class="text-[10px] text-slate-400 font-mono truncate mt-0.5">${escapeHtml(v.mobile_number)}</div>` : ''}
                </button>
            `;
        });

        container.innerHTML = html;
    }

    function filterVpVendorList() {
        const searchInput = document.getElementById('vp-vendor-search');
        renderVpVendorOptions(searchInput ? searchInput.value : '');
    }

    function handleVpVendorKeyNav(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeVpVendorDropdown();
            const btn = document.getElementById('vp-vendor-btn');
            if (btn) btn.focus();
            return;
        }

        const options = Array.from(document.querySelectorAll('#vp-vendor-options .vp-vendor-option-item'));
        if (options.length === 0) return;

        let activeIndex = options.findIndex(el => el.classList.contains('bg-emerald-100') || el.classList.contains('ring-2'));

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (activeIndex >= 0) {
                options[activeIndex].classList.remove('bg-emerald-100', 'ring-2', 'ring-emerald-400');
            }
            activeIndex = (activeIndex + 1) % options.length;
            options[activeIndex].classList.add('bg-emerald-100', 'ring-2', 'ring-emerald-400');
            options[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (activeIndex >= 0) {
                options[activeIndex].classList.remove('bg-emerald-100', 'ring-2', 'ring-emerald-400');
            }
            activeIndex = (activeIndex - 1 + options.length) % options.length;
            options[activeIndex].classList.add('bg-emerald-100', 'ring-2', 'ring-emerald-400');
            options[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const target = activeIndex >= 0 ? options[activeIndex] : options[0];
            if (target) {
                const vid = parseInt(target.dataset.vendorId, 10);
                selectVpVendor(vid);
            }
        }
    }

    function selectVpVendor(vendorId, event) {
        if (event) {
            event.stopPropagation();
        }

        const activeVendors = getActiveModalVendors();
        const vendor = activeVendors.find(v => String(v.id) === String(vendorId));
        const hiddenInput = document.getElementById('vp-vendor-select');
        const labelEl = document.getElementById('vp-vendor-label');
        const sublabelEl = document.getElementById('vp-vendor-sublabel');

        if (hiddenInput) {
            hiddenInput.value = vendor ? vendor.id : '';
            hiddenInput.dataset.credit = vendor && (vendor.credit_approved || vendor.pivot?.credit_approved) ? '1' : '0';
            hiddenInput.dataset.mobile = vendor ? (vendor.mobile_number || '') : '';
        }

        if (labelEl) {
            if (vendor) {
                labelEl.textContent = vendor.name;
                labelEl.className = 'block truncate text-slate-900 font-bold';
            } else {
                labelEl.textContent = '-- Select Linked Active Vendor --';
                labelEl.className = 'block truncate text-slate-400 font-medium';
            }
        }

        if (sublabelEl) {
            if (vendor && vendor.mobile_number) {
                sublabelEl.textContent = vendor.mobile_number;
                sublabelEl.classList.remove('hidden');
            } else {
                sublabelEl.textContent = '';
                sublabelEl.classList.add('hidden');
            }
        }

        closeVpVendorDropdown();
        onVendorPurchaseSupplierChange();
    }

    function openVendorPurchaseModal(settingId = null) {
        const modal = document.getElementById('vendor-purchase-modal');
        if (!modal) return;

        closeVpVendorDropdown();
        closeAllVpProductDropdowns();

        const errAlert = document.getElementById('vp-error-alert');
        if (errAlert) errAlert.classList.add('hidden');

        const catIdInput = document.getElementById('vp-category-id');
        const catBadge = document.getElementById('vp-category-badge');
        const settBadge = document.getElementById('vp-settlement-badge');
        const newVendorContainer = document.getElementById('vp-new-vendor-btn-container');
        const noVendorsWarning = document.getElementById('vp-no-vendors-warning');
        const noVendorsTitle = document.getElementById('vp-no-vendors-title');
        const noVendorsDesc = document.getElementById('vp-no-vendors-desc');
        const vendorDropdownContainer = document.getElementById('vp-vendor-dropdown-container');

        let currentSetting = null;
        if (settingId) {
            currentSetting = (vpSettingsList || []).find(item => String(item.id) === String(settingId));
        }

        if (currentSetting) {
            if (catIdInput) catIdInput.value = currentSetting.id;
            if (catBadge) {
                catBadge.textContent = currentSetting.displayName ? currentSetting.displayName : (currentSetting.name || 'Vendor Purchase');
                catBadge.classList.remove('hidden');
            }
            if (settBadge) {
                if (currentSetting.vendor_settlement_name || currentSetting.vendor_settlement_relation?.name) {
                    settBadge.textContent = 'Settlement: ' + (currentSetting.vendor_settlement_name || currentSetting.vendor_settlement_relation?.name);
                    settBadge.classList.remove('hidden');
                } else {
                    settBadge.classList.add('hidden');
                }
            }

            if (newVendorContainer) {
                if (currentSetting.vendor_access_mode === 'linked_create' && vpIsVendorCreationAllowed) {
                    newVendorContainer.classList.remove('hidden');
                } else {
                    newVendorContainer.classList.add('hidden');
                }
            }
        } else {
            if (catIdInput) catIdInput.value = '';
            if (catBadge) catBadge.classList.add('hidden');
            if (settBadge) settBadge.classList.add('hidden');
            if (newVendorContainer) {
                if (vpIsVendorCreationAllowed) {
                    newVendorContainer.classList.remove('hidden');
                } else {
                    newVendorContainer.classList.add('hidden');
                }
            }
        }

        const activeVendors = getActiveModalVendors();
        if (activeVendors.length === 0) {
            if (noVendorsWarning) {
                if (currentSetting && currentSetting.vendor_access_mode === 'defined_only') {
                    if (noVendorsTitle) noVendorsTitle.textContent = 'No vendors mapped to this category';
                    if (noVendorsDesc) noVendorsDesc.textContent = 'Configure mapped vendors for this category in Admin Cashbook Settings.';
                } else {
                    if (noVendorsTitle) noVendorsTitle.textContent = 'No active vendors linked to this shop';
                    if (noVendorsDesc) noVendorsDesc.textContent = vpIsVendorCreationAllowed
                        ? 'Add new vendors or link existing vendors in Cashbook Settings.'
                        : 'Link active vendors in Cashbook Settings before recording purchases.';
                }
                noVendorsWarning.classList.remove('hidden');
            }
            if (vendorDropdownContainer) vendorDropdownContainer.classList.add('hidden');
        } else {
            if (noVendorsWarning) noVendorsWarning.classList.add('hidden');
            if (vendorDropdownContainer) vendorDropdownContainer.classList.remove('hidden');
        }

        selectVpVendor('');

        const billInput = document.getElementById('vp-bill-number');
        if (billInput) billInput.value = '';
        const notesInput = document.getElementById('vp-notes');
        if (notesInput) notesInput.value = '';

        const container = document.getElementById('vp-products-list');
        if (container) {
            container.innerHTML = '';
            vpRowIndex = 0;
            addVendorPurchaseRow();
        }

        modal.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    function closeVendorPurchaseModal() {
        closeVpVendorDropdown();
        closeAllVpProductDropdowns();
        const modal = document.getElementById('vendor-purchase-modal');
        if (modal) modal.classList.add('hidden');
    }

    function onVendorPurchaseSupplierChange() {
        const vendorSelect = document.getElementById('vp-vendor-select');
        const creditRadio = document.getElementById('vp-payment-credit');
        const creditLabel = document.getElementById('vp-payment-credit-label');
        const creditNotice = document.getElementById('vp-credit-notice');
        const cashRadio = document.getElementById('vp-payment-cash');

        if (!vendorSelect || !creditRadio) return;

        const isCreditApproved = vendorSelect.dataset.credit === '1';

        if (isCreditApproved) {
            creditRadio.disabled = false;
            if (creditLabel) {
                creditLabel.classList.remove('opacity-50', 'cursor-not-allowed');
                creditLabel.classList.add('cursor-pointer');
            }
            if (creditNotice) creditNotice.classList.add('hidden');
        } else {
            creditRadio.disabled = true;
            if (creditLabel) {
                creditLabel.classList.add('opacity-50', 'cursor-not-allowed');
                creditLabel.classList.remove('cursor-pointer');
            }
            if (creditRadio.checked) {
                if (cashRadio) cashRadio.checked = true;
                if (creditNotice) creditNotice.classList.remove('hidden');
            } else {
                if (creditNotice) creditNotice.classList.add('hidden');
            }
        }
    }

    function closeAllVpProductDropdowns() {
        document.querySelectorAll('[id^="vp-prod-menu-"]').forEach(menu => {
            menu.classList.add('hidden');
        });
    }

    function toggleVpProductDropdown(rowId, event) {
        if (event) {
            event.stopPropagation();
        }

        const menu = document.getElementById(`vp-prod-menu-${rowId}`);
        if (!menu) return;

        const isCurrentlyOpen = !menu.classList.contains('hidden');
        closeAllVpProductDropdowns();

        if (!isCurrentlyOpen) {
            menu.classList.remove('hidden');
            const searchInput = document.getElementById(`vp-prod-search-${rowId}`);
            if (searchInput) {
                searchInput.value = '';
                renderVpProductOptions(rowId, '');
                setTimeout(() => searchInput.focus(), 50);
            }
        }
    }

    function renderVpProductOptions(rowId, filterTerm = '') {
        const container = document.getElementById(`vp-prod-options-${rowId}`);
        if (!container) return;

        const hiddenInput = document.getElementById(`vp-prod-id-${rowId}`);
        const selectedId = hiddenInput ? hiddenInput.value : '';
        const term = (filterTerm || '').trim().toLowerCase();

        const filtered = (vpPurchasableProducts || []).filter(p => {
            if (!term) return true;
            const nameMatch = (p.name || '').toLowerCase().includes(term);
            const skuMatch = (p.sku || '').toLowerCase().includes(term);
            const catMatch = (p.category_name || '').toLowerCase().includes(term);
            return nameMatch || skuMatch || catMatch;
        });

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="px-3 py-3 text-center text-xs font-medium text-slate-400">
                    No products match "${escapeHtml(filterTerm)}"
                </div>
            `;
            return;
        }

        let html = '';
        filtered.forEach(p => {
            const isSelected = String(p.id) === String(selectedId);
            html += `
                <button type="button" role="option" data-product-id="${p.id}"
                        onclick="selectVpProduct(${rowId}, ${p.id}, event)"
                        class="vp-option-item w-full px-3 py-2 text-left hover:bg-emerald-50 transition cursor-pointer ${isSelected ? 'bg-emerald-50/80 font-bold' : ''}">
                    <div class="font-bold text-slate-900 truncate">${escapeHtml(p.name)}</div>
                    <div class="text-[10px] text-slate-400 truncate mt-0.5">
                        ${p.sku ? 'SKU: ' + escapeHtml(p.sku) + ' · ' : ''}${p.category_name ? escapeHtml(p.category_name) + ' · ' : ''}Unit: ${escapeHtml(p.unit || 'kg')}
                    </div>
                </button>
            `;
        });

        container.innerHTML = html;
    }

    function filterVpProductList(rowId) {
        const searchInput = document.getElementById(`vp-prod-search-${rowId}`);
        renderVpProductOptions(rowId, searchInput ? searchInput.value : '');
    }

    function handleVpProductKeyNav(event, rowId) {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeAllVpProductDropdowns();
            const btn = document.getElementById(`vp-prod-btn-${rowId}`);
            if (btn) btn.focus();
            return;
        }

        const options = Array.from(document.querySelectorAll(`#vp-prod-options-${rowId} .vp-option-item`));
        if (options.length === 0) return;

        let activeIndex = options.findIndex(el => el.classList.contains('bg-emerald-100') || el.classList.contains('ring-2'));

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (activeIndex >= 0) {
                options[activeIndex].classList.remove('bg-emerald-100', 'ring-2', 'ring-emerald-400');
            }
            activeIndex = (activeIndex + 1) % options.length;
            options[activeIndex].classList.add('bg-emerald-100', 'ring-2', 'ring-emerald-400');
            options[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (activeIndex >= 0) {
                options[activeIndex].classList.remove('bg-emerald-100', 'ring-2', 'ring-emerald-400');
            }
            activeIndex = (activeIndex - 1 + options.length) % options.length;
            options[activeIndex].classList.add('bg-emerald-100', 'ring-2', 'ring-emerald-400');
            options[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const target = activeIndex >= 0 ? options[activeIndex] : options[0];
            if (target) {
                const pid = parseInt(target.dataset.productId, 10);
                selectVpProduct(rowId, pid);
            }
        }
    }

    function selectVpProduct(rowId, productId, event) {
        if (event) {
            event.stopPropagation();
        }

        const product = (vpPurchasableProducts || []).find(p => String(p.id) === String(productId));
        const hiddenInput = document.getElementById(`vp-prod-id-${rowId}`);
        const labelEl = document.getElementById(`vp-prod-label-${rowId}`);

        if (hiddenInput) {
            hiddenInput.value = productId || '';
            hiddenInput.dataset.unit = product ? (product.unit || 'kg') : 'kg';
            hiddenInput.dataset.avg = product ? (product.avg_purchase_price || 0) : 0;
        }

        if (labelEl) {
            if (product) {
                labelEl.textContent = product.name;
                labelEl.className = 'truncate text-slate-900 font-bold';
            } else {
                labelEl.textContent = '-- Select / Search Product --';
                labelEl.className = 'truncate text-slate-400 font-medium';
            }
        }

        closeAllVpProductDropdowns();
        updateRowAveragePriceDisplay(rowId);
        recalculateVendorPurchaseTotals();

        const qtyInput = document.querySelector(`#vp-row-${rowId} .vp-item-qty`);
        if (qtyInput) {
            qtyInput.focus();
        }
    }

    function updateRowAveragePriceDisplay(rowId) {
        const avgDisplay = document.getElementById(`vp-prod-avg-${rowId}`);
        if (!avgDisplay) return;

        const hiddenInput = document.getElementById(`vp-prod-id-${rowId}`);
        const productId = hiddenInput ? parseInt(hiddenInput.value, 10) : 0;

        if (!productId) {
            avgDisplay.innerHTML = '';
            return;
        }

        const product = (vpPurchasableProducts || []).find(p => String(p.id) === String(productId));
        if (!product) {
            avgDisplay.innerHTML = '';
            return;
        }

        const skuText = product.sku ? `SKU ${escapeHtml(product.sku)} · ` : '';
        const unit = escapeHtml(product.unit || 'kg');
        const qtyInput = document.querySelector(`#vp-row-${rowId} .vp-item-qty`);
        const totalInput = document.querySelector(`#vp-row-${rowId} .vp-item-total`);

        const qty = parseFloat(qtyInput ? qtyInput.value : 0) || 0;
        const total = parseFloat(totalInput ? totalInput.value : 0) || 0;

        let infoText = '';
        if (qty > 0 && total > 0) {
            const avg = (total / qty).toFixed(2);
            infoText = `${skuText}Avg Buy ₹${avg}/${unit}`;
        } else {
            infoText = `${skuText}Avg Buy: —`;
        }

        avgDisplay.innerHTML = `<span class="text-[10px] text-slate-400 font-semibold leading-tight truncate block mt-0.5">${infoText}</span>`;
    }

    function onVendorPurchaseRowQtyTotalChange(rowId) {
        updateRowAveragePriceDisplay(rowId);
        recalculateVendorPurchaseTotals();
    }

    function addVendorPurchaseRow(defaultProductId = '', defaultQty = '', defaultTotal = '') {
        const container = document.getElementById('vp-products-list');
        if (!container) return;

        const rowId = vpRowIndex++;
        const div = document.createElement('div');
        div.className = 'py-2 px-1 border-b border-slate-100 hover:bg-slate-50/60 transition';
        div.id = `vp-row-${rowId}`;

        const selectedProduct = defaultProductId
            ? (vpPurchasableProducts || []).find(p => String(p.id) === String(defaultProductId))
            : null;

        const labelText = selectedProduct
            ? selectedProduct.name
            : '-- Select / Search Product --';
        const labelClass = selectedProduct
            ? 'truncate text-slate-900 font-bold'
            : 'truncate text-slate-400 font-medium';
        const initialUnit = selectedProduct ? (selectedProduct.unit || 'kg') : 'kg';

        div.innerHTML = `
            <div class="grid grid-cols-12 gap-2 items-start">
                <div class="col-span-11 sm:col-span-5 relative">
                    <input type="hidden" class="vp-item-product" id="vp-prod-id-${rowId}" value="${defaultProductId}" data-unit="${escapeHtml(initialUnit)}">
                    <button type="button" id="vp-prod-btn-${rowId}" onclick="toggleVpProductDropdown(${rowId}, event)"
                            class="h-8 w-full flex items-center justify-between gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-left text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-none transition cursor-pointer">
                        <span id="vp-prod-label-${rowId}" class="${labelClass}">
                            ${escapeHtml(labelText)}
                        </span>
                        <svg class="h-3.5 w-3.5 shrink-0 text-slate-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div id="vp-prod-menu-${rowId}" onclick="event.stopPropagation()"
                         class="hidden absolute left-0 top-full mt-1 w-full sm:min-w-[280px] z-40 rounded-xl border border-slate-200 bg-white shadow-2xl overflow-hidden">
                        <div class="p-2 border-b border-slate-100 bg-slate-50/80">
                            <div class="relative">
                                <input type="text" id="vp-prod-search-${rowId}" placeholder="Search product name or SKU..." autocomplete="off"
                                       oninput="filterVpProductList(${rowId})"
                                       onkeydown="handleVpProductKeyNav(event, ${rowId})"
                                       class="h-8 w-full rounded-lg border border-slate-200 bg-white pl-7 pr-2.5 text-xs font-semibold text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none">
                                <svg class="absolute left-2 top-2 h-4 w-4 text-slate-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>
                        <div id="vp-prod-options-${rowId}" class="max-h-48 overflow-y-auto divide-y divide-slate-100 py-1 text-xs">
                        </div>
                    </div>

                    <div id="vp-prod-avg-${rowId}" class="px-0.5 min-h-[14px]">
                    </div>
                </div>

                <div class="col-span-1 sm:hidden text-right flex justify-end">
                    <button type="button" onclick="removeVendorPurchaseRow(${rowId})" aria-label="Remove item"
                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>

                <div class="col-span-6 sm:col-span-3">
                    <div class="relative">
                        <input type="number" step="0.01" min="0.01" value="${defaultQty}" placeholder="Qty"
                               class="vp-item-qty h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-900 font-mono focus:border-emerald-500 focus:outline-none"
                               oninput="onVendorPurchaseRowQtyTotalChange(${rowId})">
                    </div>
                </div>

                <div class="col-span-6 sm:col-span-3">
                    <div class="relative">
                        <input type="number" step="0.01" min="0" value="${defaultTotal}" placeholder="Total ₹"
                               class="vp-item-total h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-900 font-mono focus:border-emerald-500 focus:outline-none"
                               oninput="onVendorPurchaseRowQtyTotalChange(${rowId})">
                    </div>
                </div>

                <div class="hidden sm:flex sm:col-span-1 items-center justify-center h-8">
                    <button type="button" onclick="removeVendorPurchaseRow(${rowId})" aria-label="Remove item"
                            class="inline-flex items-center justify-center h-7 w-7 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer">
                        <i data-lucide="x" class="h-3.5 w-3.5"></i>
                    </button>
                </div>
            </div>
        `;

        container.appendChild(div);
        renderVpProductOptions(rowId, '');
        updateRowAveragePriceDisplay(rowId);

        if (window.lucide) lucide.createIcons();
        recalculateVendorPurchaseTotals();
    }

    function removeVendorPurchaseRow(rowId) {
        const row = document.getElementById(`vp-row-${rowId}`);
        if (row) {
            row.remove();
            recalculateVendorPurchaseTotals();
        }
    }

    function recalculateVendorPurchaseTotals() {
        const rows = document.querySelectorAll('#vp-products-list > div');
        let grandTotal = 0;

        rows.forEach(row => {
            const totalInput = row.querySelector('.vp-item-total');
            const lineTotal = parseFloat(totalInput ? totalInput.value : 0) || 0;
            grandTotal += lineTotal;
        });

        const grandTotalEl = document.getElementById('vp-grand-total');
        if (grandTotalEl) {
            grandTotalEl.textContent = '₹' + grandTotal.toFixed(2);
        }
    }

    function showVendorPurchaseError(msg) {
        const errAlert = document.getElementById('vp-error-alert');
        const errMsg = document.getElementById('vp-error-message');
        if (errAlert && errMsg) {
            errMsg.innerHTML = msg;
            errAlert.classList.remove('hidden');
        } else {
            alert(msg);
        }
    }

    async function submitVendorPurchase() {
        if (isVpSubmitting) return;

        const errAlert = document.getElementById('vp-error-alert');
        if (errAlert) errAlert.classList.add('hidden');

        const vendorSelect = document.getElementById('vp-vendor-select');
        const supplierId = vendorSelect ? vendorSelect.value : '';
        if (!supplierId) {
            showVendorPurchaseError('Please select an active vendor.');
            return;
        }

        const paymentCash = document.getElementById('vp-payment-cash');
        const paymentMethod = (paymentCash && paymentCash.checked) ? 'Cash' : 'Credit';

        const rows = document.querySelectorAll('#vp-products-list > div');
        const itemsMap = {};

        for (const row of rows) {
            const productInput = row.querySelector('.vp-item-product');
            const qtyInput = row.querySelector('.vp-item-qty');
            const totalInput = row.querySelector('.vp-item-total');

            const productId = productInput ? parseInt(productInput.value, 10) : 0;
            const qty = parseFloat(qtyInput ? qtyInput.value : 0) || 0;
            const totalPrice = parseFloat(totalInput ? totalInput.value : 0) || 0;
            const unit = productInput ? (productInput.dataset.unit || 'kg') : 'kg';

            if (productId > 0) {
                if (qty <= 0) {
                    showVendorPurchaseError('Quantity must be greater than 0 for all selected products.');
                    return;
                }
                if (totalPrice <= 0) {
                    showVendorPurchaseError('Total price must be greater than 0 for all selected products.');
                    return;
                }

                const grade = 'A';
                const key = `${productId}-${grade}`;
                if (!itemsMap[key]) {
                    itemsMap[key] = {
                        product_id: productId,
                        quantity: qty,
                        total_price: totalPrice,
                        unit: unit,
                        grade: grade
                    };
                } else {
                    itemsMap[key].quantity += qty;
                    itemsMap[key].total_price += totalPrice;
                }
            }
        }

        const items = Object.values(itemsMap).map(item => {
            const derivedRate = item.quantity > 0 ? Math.round((item.total_price / item.quantity) * 10000) / 10000 : 0;
            return {
                product_id: item.product_id,
                quantity: item.quantity,
                total_price: item.total_price,
                unit_price: derivedRate,
                unit: item.unit,
                grade: item.grade
            };
        });

        if (items.length === 0) {
            showVendorPurchaseError('Please select at least one product with quantity and total price.');
            return;
        }

        const billNumber = document.getElementById('vp-bill-number')?.value?.trim() || '';
        const notes = document.getElementById('vp-notes')?.value?.trim() || '';
        const catId = document.getElementById('vp-category-id')?.value;

        const payload = {
            shop_ledger_entry_setting_id: catId ? parseInt(catId, 10) : null,
            supplier_id: parseInt(supplierId, 10),
            payment_method: paymentMethod,
            business_date: vpActiveBusinessDate,
            bill_number: billNumber,
            notes: notes,
            items: items
        };

        const submitBtn = document.getElementById('vp-submit-btn');
        const spinner = document.getElementById('vp-submit-spinner');
        const submitText = document.getElementById('vp-submit-text');

        try {
            isVpSubmitting = true;
            if (submitBtn) submitBtn.disabled = true;
            if (spinner) spinner.classList.remove('hidden');
            if (submitText) submitText.textContent = 'Saving...';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const response = await fetch(vpStorePurchaseUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                let message = data.message || 'Failed to save purchase.';
                if (data.errors) {
                    const errs = Object.values(data.errors).flat();
                    if (errs.length > 0) {
                        message = errs.join('<br>');
                    }
                }
                showVendorPurchaseError(message);
                return;
            }

            closeVendorPurchaseModal();
            window.location.reload();
        } catch (err) {
            showVendorPurchaseError('Network error while saving purchase. Please try again.');
        } finally {
            isVpSubmitting = false;
            if (submitBtn) submitBtn.disabled = false;
            if (spinner) spinner.classList.add('hidden');
            if (submitText) submitText.textContent = 'Save Purchase';
        }
    }

    function openShopOwnerCreateVendorModal() {
        const modal = document.getElementById('vp-new-vendor-modal');
        if (!modal) return;

        const errAlert = document.getElementById('vp-new-vendor-error');
        if (errAlert) errAlert.classList.add('hidden');

        document.getElementById('vp-nv-name').value = '';
        document.getElementById('vp-nv-mobile').value = '';
        document.getElementById('vp-nv-contact').value = '';
        document.getElementById('vp-nv-notes').value = '';

        modal.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    function closeShopOwnerCreateVendorModal() {
        const modal = document.getElementById('vp-new-vendor-modal');
        if (modal) modal.classList.add('hidden');
    }

    async function submitShopOwnerCreateVendor() {
        if (isCreatingVendor) return;

        const name = document.getElementById('vp-nv-name')?.value?.trim();
        const mobile = document.getElementById('vp-nv-mobile')?.value?.trim();
        const contact = document.getElementById('vp-nv-contact')?.value?.trim();
        const notes = document.getElementById('vp-nv-notes')?.value?.trim();
        const errAlert = document.getElementById('vp-new-vendor-error');
        const errMsg = document.getElementById('vp-new-vendor-error-msg');

        if (!name) {
            if (errAlert && errMsg) {
                errMsg.textContent = 'Vendor name is required.';
                errAlert.classList.remove('hidden');
            }
            return;
        }

        const submitBtn = document.getElementById('vp-nv-submit-btn');
        const spinner = document.getElementById('vp-nv-spinner');
        const submitText = document.getElementById('vp-nv-submit-text');

        try {
            isCreatingVendor = true;
            if (submitBtn) submitBtn.disabled = true;
            if (spinner) spinner.classList.remove('hidden');
            if (submitText) submitText.textContent = 'Creating...';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const response = await fetch(vpCreateVendorUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    name: name,
                    mobile_number: mobile,
                    contact_person: contact,
                    address: notes
                })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                let message = data.message || 'Failed to create vendor.';
                if (data.errors) {
                    const errs = Object.values(data.errors).flat();
                    if (errs.length > 0) message = errs.join('<br>');
                }
                if (errAlert && errMsg) {
                    errMsg.innerHTML = message;
                    errAlert.classList.remove('hidden');
                }
                return;
            }

            const newVendor = data.vendor || data.supplier;
            if (newVendor) {
                vpLinkedVendorsData.push({
                    id: newVendor.id,
                    name: newVendor.name,
                    mobile_number: newVendor.mobile_number,
                    credit_approved: false
                });

                closeShopOwnerCreateVendorModal();
                selectVpVendor(newVendor.id);

                const warning = document.getElementById('vp-no-vendors-warning');
                if (warning) warning.classList.add('hidden');
                const dropdown = document.getElementById('vp-vendor-dropdown-container');
                if (dropdown) dropdown.classList.remove('hidden');
            }
        } catch (err) {
            if (errAlert && errMsg) {
                errMsg.textContent = 'Network error while creating vendor.';
                errAlert.classList.remove('hidden');
            }
        } finally {
            isCreatingVendor = false;
            if (submitBtn) submitBtn.disabled = false;
            if (spinner) spinner.classList.add('hidden');
            if (submitText) submitText.textContent = 'Create Vendor';
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[m]));
    }
</script>
