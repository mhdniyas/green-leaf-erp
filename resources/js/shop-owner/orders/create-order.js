document.addEventListener('DOMContentLoaded', () => {
    // ── DOM ELEMENTS ────────────────────────────────────────────────────────
    const formNode = document.getElementById('shop-owner-order-form');
    const presetsNode = document.getElementById('shop-owner-presets-data');
    const productCatalogNode = document.getElementById('shop-owner-product-catalog');
    const presetSelect = document.querySelector('[data-preset-select]');
    const applyPresetButton = document.querySelector('[data-apply-preset]');
    const masterQtyInputs = Array.from(document.querySelectorAll('[data-master-qty]'));
    const savePresetForm = document.querySelector('[data-save-preset-form]');
    
    // Search & Category Filters
    const searchInput = document.querySelector('[data-product-search]');
    const categoryPills = Array.from(document.querySelectorAll('[data-category-pill]'));
    const productCards = Array.from(document.querySelectorAll('[data-product-card]'));
    const productListContainer = document.getElementById('product-list-container');
    const noSearchResults = document.getElementById('no-search-results');
    const currentListTitle = document.getElementById('current-list-title');
    const listResultsCount = document.getElementById('list-results-count');

    // Bottom Sheet Quantity Modal
    const qtyModalBackdrop = document.getElementById('qty-modal-backdrop');
    const qtyModalSheet = document.getElementById('qty-modal-sheet');
    const qtyModalClose = document.getElementById('qty-modal-close');
    const modalSku = document.getElementById('modal-product-sku');
    const modalName = document.getElementById('modal-product-name');
    const modalUnitToggleContainer = document.getElementById('modal-unit-toggle-container');
    const modalUnitBtnStandard = document.getElementById('modal-unit-btn-standard');
    const modalUnitBtnBox = document.getElementById('modal-unit-btn-box');
    const modalQtyInput = document.getElementById('modal-qty-input');
    const modalQtyUnitLabel = document.getElementById('modal-qty-unit-label');
    const modalSuggestedBadge = document.getElementById('modal-suggested-badge');
    const modalStepperMinus = document.getElementById('modal-stepper-minus');
    const modalStepperPlus = document.getElementById('modal-stepper-plus');
    const modalConversionHelper = document.getElementById('modal-conversion-helper');
    const modalConversionFactorText = document.getElementById('modal-conversion-factor-text');
    const modalConversionCalc = document.getElementById('modal-conversion-calc');
    const modalQuickPills = document.getElementById('modal-quick-pills');
    const modalRemoveBtn = document.getElementById('modal-remove-btn');
    const modalAddBtn = document.getElementById('modal-add-btn');

    // Persistent Floating Cart Bar
    const floatingCartBar = document.getElementById('floating-cart-bar');
    const cartBarItemsCount = document.getElementById('cart-bar-items-count');
    const cartBarReviewBtn = document.getElementById('cart-bar-review-btn');
    const pageOpenCartButtons = Array.from(document.querySelectorAll('[data-open-cart-submit]'));

    // Cart Review Drawer
    const cartReviewBackdrop = document.getElementById('cart-review-backdrop');
    const cartReviewDrawer = document.getElementById('cart-review-drawer');
    const cartReviewClose = document.getElementById('cart-review-close');
    const reviewItemsCount = document.getElementById('review-items-count');
    const reviewItemsList = document.getElementById('review-items-list');
    const reviewAddMoreBtn = document.getElementById('review-add-more-btn');
    const reviewSubmitBtn = document.getElementById('review-submit-btn');

    if (
        !formNode ||
        !productCatalogNode ||
        !qtyModalBackdrop ||
        !qtyModalSheet ||
        !cartReviewBackdrop ||
        !cartReviewDrawer ||
        !floatingCartBar ||
        !cartBarItemsCount ||
        !cartBarReviewBtn ||
        !reviewItemsCount ||
        !reviewItemsList ||
        !reviewAddMoreBtn ||
        !reviewSubmitBtn
    ) {
        return;
    }

    // ── DATA STATE ──────────────────────────────────────────────────────────
    const presets = presetsNode ? JSON.parse(presetsNode.textContent ?? '[]') : [];
    const productCatalog = productCatalogNode ? JSON.parse(productCatalogNode.textContent ?? '[]') : [];
    const productsById = new Map(productCatalog.map((p) => [String(p.id), p]));
    const masterInputsById = new Map(masterQtyInputs.map((input) => [input.getAttribute('data-product-id'), input]));
    const draftStorageKey = 'shop-owner-order-draft';

    // Active Modal Context
    let currentModalProduct = null;
    let currentModalUnitMode = 'standard'; // 'standard' or 'box'
    let currentModalConversionFactor = 10; // default for kg

    // ── HELPERS ─────────────────────────────────────────────────────────────
    const getConversionFactor = (unit) => {
        const lowerUnit = String(unit).toLowerCase().trim();
        if (lowerUnit === 'kg') {
            return 10; // 1 Box = 10 Kg
        }
        if (['piece', 'pcs', 'bunch', 'bag', 'roll'].includes(lowerUnit)) {
            return 24; // 1 Box = 24 units
        }
        return 1; // Already box or other unit
    };

    const syncMasterInput = (productId, quantity, shouldRender = true) => {
        const input = masterInputsById.get(String(productId));
        if (!input) {
            return;
        }
        input.value = quantity > 0 ? quantity.toFixed(2) : '';
        if (shouldRender) {
            input.dispatchEvent(new Event('input', { bubbles: true }));
            document.dispatchEvent(new Event('shop-owner-order-input-change'));
        }
    };

    const getSelectedProducts = () => productCatalog.filter((product) => {
        const input = masterInputsById.get(String(product.id));
        return input && Number.parseFloat(input.value) > 0;
    });

    const syncPageOpenCartButtons = () => {
        const selectedCount = getSelectedProducts().length;

        pageOpenCartButtons.forEach((button) => {
            button.disabled = selectedCount === 0;
            button.classList.toggle('opacity-60', selectedCount === 0);
            button.classList.toggle('cursor-not-allowed', selectedCount === 0);
        });
    };

    // ── RENDER & UI SYNC ────────────────────────────────────────────────────
    const updateProductCardBadge = (productId, qty, unit) => {
        const card = document.querySelector(`[data-product-card][data-product-id="${productId}"]`);
        if (!card) {
            return;
        }
        const badgeContainer = card.querySelector(`[data-badge-container="${productId}"]`);
        if (!badgeContainer) {
            return;
        }

        if (qty > 0) {
            badgeContainer.innerHTML = `
                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-700 border border-emerald-100">
                    ${qty.toFixed(2)} ${unit}
                </span>
            `;
        } else {
            badgeContainer.innerHTML = `
                <div class="w-8 h-8 rounded-full border border-slate-200 flex items-center justify-center text-slate-400 transition hover:border-emerald-500 hover:text-emerald-500">
                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                </div>
            `;
        }
    };

    const syncFloatingCartBar = () => {
        const selected = getSelectedProducts();
        const totalItems = selected.length;

        if (totalItems > 0) {
            cartBarItemsCount.textContent = `${totalItems} ${totalItems === 1 ? 'item' : 'items'} selected`;

            if (floatingCartBar.classList.contains('hidden')) {
                floatingCartBar.classList.remove('hidden');
                setTimeout(() => {
                    floatingCartBar.classList.remove('translate-y-6', 'opacity-0');
                }, 50);
            }
        } else {
            floatingCartBar.classList.add('translate-y-6', 'opacity-0');
            setTimeout(() => {
                if (getSelectedProducts().length === 0) {
                    floatingCartBar.classList.add('hidden');
                }
            }, 300);
        }

        syncPageOpenCartButtons();
    };

    // ── CATEGORY & SEARCH FILTERING ─────────────────────────────────────────
    let activeCategory = categoryPills.find((pill) => pill.hasAttribute('data-default-category'))
        ?.getAttribute('data-default-category') ?? 'all';

    const filterProducts = () => {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        let visibleCount = 0;

        productCards.forEach((card) => {
            const sku = card.getAttribute('data-sku') ?? '';
            const name = card.getAttribute('data-name') ?? '';
            const category = card.getAttribute('data-category') ?? '';
            const isFrequent = card.getAttribute('data-is-frequent') === 'true';
            const searchText = card.getAttribute('data-search-text') ?? '';

            let matchesSearch = query === '' || searchText.includes(query);
            let matchesCategory = false;

            if (query !== '') {
                matchesCategory = true; // when searching, category pills are secondary
            } else if (activeCategory === 'all') {
                matchesCategory = true;
            } else if (activeCategory === 'frequent') {
                matchesCategory = isFrequent;
            } else {
                matchesCategory = category === activeCategory;
            }

            const shouldShow = matchesSearch && matchesCategory;
            card.classList.toggle('hidden', !shouldShow);
            if (shouldShow) {
                visibleCount++;
            }
        });

        // Update counts and empty states
        if (listResultsCount) {
            listResultsCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'product' : 'products'}`;
        }
        if (noSearchResults) {
            noSearchResults.classList.toggle('hidden', visibleCount > 0);
        }
        if (productListContainer) {
            productListContainer.classList.toggle('hidden', visibleCount === 0);
        }

        // Update list title
        if (currentListTitle) {
            if (query !== '') {
                currentListTitle.textContent = `Search Results for "${query}"`;
            } else if (activeCategory === 'frequent') {
                currentListTitle.textContent = 'Frequently Ordered';
            } else if (activeCategory === 'all') {
                currentListTitle.textContent = 'All Products';
            } else {
                currentListTitle.textContent = activeCategory;
            }
        }
    };

    categoryPills.forEach((pill) => {
        pill.addEventListener('click', () => {
            activeCategory = pill.getAttribute('data-category-pill');
            
            // Update active styles
            categoryPills.forEach((p) => {
                p.classList.remove('bg-emerald-600', 'text-white', 'shadow-sm');
                p.classList.add('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200');
            });
            pill.classList.remove('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200');
            pill.classList.add('bg-emerald-600', 'text-white', 'shadow-sm');

            if (searchInput) {
                searchInput.value = ''; // clear search when switching categories
            }

            filterProducts();
        });
    });

    searchInput?.addEventListener('input', () => {
        filterProducts();
    });

    // ── QUANTITY BOTTOM SHEET MODAL ─────────────────────────────────────────
    const openQtyModal = (productId) => {
        const product = productsById.get(String(productId));
        if (!product) {
            return;
        }

        currentModalProduct = product;
        currentModalConversionFactor = getConversionFactor(product.unit);

        // Populate details
        modalSku.textContent = product.sku;
        modalName.textContent = product.name;
        modalSuggestedBadge.textContent = `Sug: ${Number(product.suggested_qty ?? 0).toFixed(2)}`;

        // Read current qty
        const masterInput = masterInputsById.get(String(product.id));
        const currentQty = masterInput ? Number.parseFloat(masterInput.value) : 0;
        const finalQty = Number.isFinite(currentQty) && currentQty > 0 ? currentQty : 0;

        // Reset toggles depending on unit
        const isBoxOnly = String(product.unit).toLowerCase().trim() === 'box';
        const hasBoxOption = currentModalConversionFactor > 1;

        if (isBoxOnly) {
            modalUnitToggleContainer.classList.add('hidden');
            currentModalUnitMode = 'box';
            modalQtyUnitLabel.textContent = 'BOX';
        } else if (hasBoxOption) {
            modalUnitToggleContainer.classList.remove('hidden');
            // By default, set to Box mode if already ordering boxes, else standard
            if (finalQty > 0 && finalQty % currentModalConversionFactor === 0) {
                setModalUnitMode('box');
            } else {
                setModalUnitMode('standard');
            }
        } else {
            modalUnitToggleContainer.classList.add('hidden');
            currentModalUnitMode = 'standard';
            modalQtyUnitLabel.textContent = String(product.unit).toUpperCase();
        }

        // Set input value
        if (finalQty > 0) {
            if (currentModalUnitMode === 'box') {
            modalQtyInput.value = (finalQty / currentModalConversionFactor).toString();
            } else {
                modalQtyInput.value = finalQty.toString();
            }
            modalRemoveBtn.classList.remove('hidden');
            modalAddBtn.textContent = 'Update Cart';
        } else {
            modalQtyInput.value = '';
            modalRemoveBtn.classList.add('hidden');
            modalAddBtn.textContent = 'Add to Cart';
        }

        updateModalConversionPreview();
        renderQuickPills();

        // Reveal sheet
        document.body.classList.add('overflow-hidden');
        qtyModalBackdrop.classList.remove('hidden');
        qtyModalSheet.classList.remove('hidden');
        
        const mobileNav = document.getElementById('layout-mobile-nav');
        if (mobileNav) {
            mobileNav.classList.add('hidden');
        }

        setTimeout(() => {
            qtyModalBackdrop.classList.remove('opacity-0');
            qtyModalBackdrop.classList.add('opacity-100');
            qtyModalSheet.classList.remove('translate-y-full');
        }, 50);

        setTimeout(() => {
            modalQtyInput.focus();
        }, 150);
    };

    const closeQtyModal = () => {
        document.body.classList.remove('overflow-hidden');
        qtyModalSheet.classList.add('translate-y-full');
        qtyModalBackdrop.classList.remove('opacity-100');
        qtyModalBackdrop.classList.add('opacity-0');
        setTimeout(() => {
            qtyModalBackdrop.classList.add('hidden');
            qtyModalSheet.classList.add('hidden');
            currentModalProduct = null;
            
            // Only restore mobile nav if the other drawer (cart review) is not open
            const cartReviewDrawer = document.getElementById('cart-review-drawer');
            if (!cartReviewDrawer || cartReviewDrawer.classList.contains('hidden')) {
                const mobileNav = document.getElementById('layout-mobile-nav');
                if (mobileNav) {
                    mobileNav.classList.remove('hidden');
                }
            }
        }, 300);
    };

    const setModalUnitMode = (mode) => {
        currentModalUnitMode = mode;
        if (mode === 'box') {
            modalUnitBtnStandard.classList.remove('bg-white', 'text-slate-900', 'shadow-sm');
            modalUnitBtnStandard.classList.add('text-slate-400', 'hover:text-slate-600');
            modalUnitBtnBox.classList.remove('text-slate-400', 'hover:text-slate-600');
            modalUnitBtnBox.classList.add('bg-white', 'text-slate-900', 'shadow-sm');
            modalQtyUnitLabel.textContent = 'BOX';
        } else {
            modalUnitBtnBox.classList.remove('bg-white', 'text-slate-900', 'shadow-sm');
            modalUnitBtnBox.classList.add('text-slate-400', 'hover:text-slate-600');
            modalUnitBtnStandard.classList.remove('text-slate-400', 'hover:text-slate-600');
            modalUnitBtnStandard.classList.add('bg-white', 'text-slate-900', 'shadow-sm');
            modalQtyUnitLabel.textContent = String(currentModalProduct?.unit ?? 'KG').toUpperCase();
        }
        renderQuickPills();
        updateModalConversionPreview();
    };

    const updateModalConversionPreview = () => {
        if (!currentModalProduct) {
            return;
        }

        const value = Number.parseFloat(modalQtyInput.value) || 0;
        let finalQty = value;

        if (currentModalUnitMode === 'box') {
            finalQty = value * currentModalConversionFactor;
            modalConversionFactorText.textContent = String(currentModalConversionFactor);
            modalConversionCalc.textContent = `${finalQty.toFixed(2)} ${currentModalProduct.unit}`;
            modalConversionHelper.classList.remove('hidden');
        } else {
            modalConversionHelper.classList.add('hidden');
        }
    };

    const renderQuickPills = () => {
        modalQuickPills.innerHTML = '';
        if (!currentModalProduct) {
            return;
        }

        const standardPills = [1, 2, 5, 10, 20, 50];
        const boxPills = [1, 2, 3, 5, 10];
        const pills = currentModalUnitMode === 'box' ? boxPills : standardPills;

        pills.forEach((p) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 active:scale-95 duration-100';
            button.textContent = `+${p}`;
            button.addEventListener('click', () => {
                const currentVal = Number.parseFloat(modalQtyInput.value) || 0;
                modalQtyInput.value = (currentVal + p).toString();
                updateModalSubtotalAndConversion();
            });
            modalQuickPills.appendChild(button);
        });
    };

    // Modal Action Listeners
    qtyModalBackdrop.addEventListener('click', closeQtyModal);
    qtyModalClose.addEventListener('click', closeQtyModal);
    modalQtyInput.addEventListener('input', updateModalConversionPreview);

    modalUnitBtnStandard.addEventListener('click', () => setModalUnitMode('standard'));
    modalUnitBtnBox.addEventListener('click', () => setModalUnitMode('box'));

    modalStepperMinus.addEventListener('click', () => {
        const currentVal = Number.parseFloat(modalQtyInput.value) || 0;
        modalQtyInput.value = Math.max(0, currentVal - 1).toString();
        updateModalConversionPreview();
    });

    modalStepperPlus.addEventListener('click', () => {
        const currentVal = Number.parseFloat(modalQtyInput.value) || 0;
        modalQtyInput.value = (currentVal + 1).toString();
        updateModalConversionPreview();
    });

    modalRemoveBtn.addEventListener('click', () => {
        if (!currentModalProduct) {
            return;
        }
        syncMasterInput(currentModalProduct.id, 0);
        updateProductCardBadge(currentModalProduct.id, 0, currentModalProduct.unit);
        closeQtyModal();
    });

    modalAddBtn.addEventListener('click', () => {
        if (!currentModalProduct) {
            return;
        }

        const value = Number.parseFloat(modalQtyInput.value) || 0;
        let finalQty = value;

        if (currentModalUnitMode === 'box') {
            finalQty = value * currentModalConversionFactor;
        }

        syncMasterInput(currentModalProduct.id, finalQty);
        updateProductCardBadge(currentModalProduct.id, finalQty, currentModalProduct.unit);
        closeQtyModal();
    });

    // ── PERSISTENT FLOATING CART BAR ──
    cartBarReviewBtn.addEventListener('click', () => openCartReview());

    // ── CART REVIEW DRAWER ──
    const openCartReview = () => {
        renderReviewDrawerItems();

        document.body.classList.add('overflow-hidden');
        cartReviewBackdrop.classList.remove('hidden');
        cartReviewDrawer.classList.remove('hidden');
        
        const mobileNav = document.getElementById('layout-mobile-nav');
        if (mobileNav) {
            mobileNav.classList.add('hidden');
        }

        setTimeout(() => {
            cartReviewBackdrop.classList.remove('opacity-0');
            cartReviewBackdrop.classList.add('opacity-100');
            cartReviewDrawer.classList.remove('translate-y-full');
        }, 50);
    };

    const closeCartReview = () => {
        document.body.classList.remove('overflow-hidden');
        cartReviewDrawer.classList.add('translate-y-full');
        cartReviewBackdrop.classList.remove('opacity-100');
        cartReviewBackdrop.classList.add('opacity-0');
        setTimeout(() => {
            cartReviewBackdrop.classList.add('hidden');
            cartReviewDrawer.classList.add('hidden');
            
            // Only restore mobile nav if the other modal (qty modal) is not open
            const qtyModalSheet = document.getElementById('qty-modal-sheet');
            if (!qtyModalSheet || qtyModalSheet.classList.contains('hidden')) {
                const mobileNav = document.getElementById('layout-mobile-nav');
                if (mobileNav) {
                    mobileNav.classList.remove('hidden');
                }
            }
        }, 300);
    };

    const renderReviewDrawerItems = () => {
        reviewItemsList.innerHTML = '';
        const selected = getSelectedProducts();
        
        reviewItemsCount.textContent = `${selected.length} ${selected.length === 1 ? 'item' : 'items'} selected`;

        selected
            .sort((a, b) => a.name.localeCompare(b.name))
            .forEach((p) => {
                const masterInput = masterInputsById.get(String(p.id));
                const qty = masterInput ? Number.parseFloat(masterInput.value) : 0;

                const row = document.createElement('article');
                row.className = 'py-3.5 flex items-center justify-between gap-4 border-b border-slate-100 last:border-0';
                row.innerHTML = `
                    <div class="min-w-0 flex-1">
                        <h4 class="font-bold text-slate-900 text-sm truncate" title="${p.name}">${p.name}</h4>
                        <p class="text-[11px] text-slate-500 truncate mt-0.5">
                            <span class="font-black text-slate-400 text-[10px] uppercase">${p.sku}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <div class="flex items-center gap-1.5 bg-slate-50 rounded-xl p-1 border border-slate-200">
                            <button type="button" data-review-decrement="${p.id}" class="flex h-7 w-7 items-center justify-center rounded-lg bg-white border border-slate-200 text-xs font-black text-slate-700 shadow-sm active:scale-95 transition">-</button>
                            <span class="w-14 text-center text-xs font-black text-slate-950">${qty.toFixed(2)} ${p.unit}</span>
                            <button type="button" data-review-increment="${p.id}" class="flex h-7 w-7 items-center justify-center rounded-lg bg-white border border-slate-200 text-xs font-black text-slate-700 shadow-sm active:scale-95 transition">+</button>
                        </div>
                        <button type="button" data-review-delete="${p.id}" class="text-slate-400 hover:text-rose-600 p-1.5 rounded-lg hover:bg-rose-50 transition">
                            <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                `;

                reviewItemsList.appendChild(row);

                // Wire up actions
                row.querySelector(`[data-review-decrement="${p.id}"]`).addEventListener('click', () => {
                    const nextVal = Math.max(0, qty - (getConversionFactor(p.unit) > 1 ? 1 : 0.5));
                    syncMasterInput(p.id, nextVal);
                    updateProductCardBadge(p.id, nextVal, p.unit);
                    renderReviewDrawerItems();
                });

                row.querySelector(`[data-review-increment="${p.id}"]`).addEventListener('click', () => {
                    const nextVal = qty + (getConversionFactor(p.unit) > 1 ? 1 : 0.5);
                    syncMasterInput(p.id, nextVal);
                    updateProductCardBadge(p.id, nextVal, p.unit);
                    renderReviewDrawerItems();
                });

                row.querySelector(`[data-review-delete="${p.id}"]`).addEventListener('click', () => {
                    syncMasterInput(p.id, 0);
                    updateProductCardBadge(p.id, 0, p.unit);
                    if (getSelectedProducts().length === 0) {
                        closeCartReview();
                    } else {
                        renderReviewDrawerItems();
                    }
                });
            });
    };

    cartReviewBackdrop.addEventListener('click', closeCartReview);
    cartReviewClose.addEventListener('click', closeCartReview);
    reviewAddMoreBtn.addEventListener('click', closeCartReview);
    pageOpenCartButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (getSelectedProducts().length === 0) {
                return;
            }

            openCartReview();
        });
    });

    reviewSubmitBtn.addEventListener('click', () => {
        if (!formNode) {
            return;
        }
        
        // Clear the draft when submitting
        window.localStorage.removeItem(draftStorageKey);
        formNode.submit();
    });

    // ── INITIAL BINDINGS & LISTENERS ────────────────────────────────────────
    productCards.forEach((card) => {
        card.addEventListener('click', () => {
            const id = card.getAttribute('data-product-id');
            openQtyModal(id);
        });
    });

    // Listen to changes on master inputs (like when preset loads or inputs manually update)
    document.addEventListener('shop-owner-order-input-change', () => {
        syncFloatingCartBar();
        saveDraftProductsToStorage();
    });

    // Initialize badges and cart on load
    productCatalog.forEach((p) => {
        const input = masterInputsById.get(String(p.id));
        const qty = input ? Number.parseFloat(input.value) : 0;
        updateProductCardBadge(p.id, qty, p.unit);
    });

    syncFloatingCartBar();
    filterProducts(); // Initial filter list of products

    // ── PRESETS & DRAFTS INTEGRATION ────────────────────────────────────────
    const applyPreset = () => {
        if (!presetSelect) {
            return;
        }

        const selectedPreset = presets.find((preset) => String(preset.id) === presetSelect.value);
        if (!selectedPreset) {
            return;
        }

        const quantitiesByProductId = new Map(
            selectedPreset.items.map((item) => [String(item.product_id), Number.parseFloat(item.quantity)])
        );

        masterQtyInputs.forEach((input) => {
            const productId = input.getAttribute('data-product-id');
            const quantity = productId ? quantitiesByProductId.get(productId) : undefined;
            const finalQty = Number.isFinite(quantity) ? quantity : 0;

            input.value = finalQty > 0 ? finalQty.toFixed(2) : '';
            const product = productsById.get(productId);
            if (product) {
                updateProductCardBadge(productId, finalQty, product.unit);
            }
        });

        document.dispatchEvent(new Event('shop-owner-order-input-change'));
        filterProducts();
    };

    applyPresetButton?.addEventListener('click', applyPreset);

    // Save Preset handler & triggers
    const reviewSavePresetTrigger = document.getElementById('review-save-preset-trigger');
    const reviewSavePresetFormContainer = document.getElementById('review-save-preset-form-container');
    const reviewPresetNameInput = document.getElementById('review-preset-name-input');
    const reviewSavePresetBtn = document.getElementById('review-save-preset-btn');
    const hiddenPresetNameInput = document.querySelector('[data-preset-name-input]');

    reviewSavePresetTrigger?.addEventListener('click', () => {
        reviewSavePresetFormContainer?.classList.toggle('hidden');
        if (!reviewSavePresetFormContainer?.classList.contains('hidden')) {
            reviewPresetNameInput?.focus();
        }
    });

    reviewSavePresetBtn?.addEventListener('click', () => {
        if (!reviewPresetNameInput || !hiddenPresetNameInput || !savePresetForm) {
            return;
        }

        const nameValue = reviewPresetNameInput.value.trim();
        if (nameValue === '') {
            alert('Please enter a name for your custom list.');
            reviewPresetNameInput.focus();
            return;
        }

        const selected = getSelectedProducts();
        if (selected.length === 0) {
            alert('Cannot save an empty list. Please add some products to your order first.');
            return;
        }

        hiddenPresetNameInput.value = nameValue;
        
        // Trigger form submit
        const submitEvent = new Event('submit', { cancelable: true, bubbles: true });
        savePresetForm.dispatchEvent(submitEvent);
        if (!submitEvent.defaultPrevented) {
            savePresetForm.submit();
        }
    });

    savePresetForm?.addEventListener('submit', (event) => {
        const form = event.currentTarget;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        form.querySelectorAll('[data-generated-preset-item]').forEach((node) => node.remove());

        let itemIndex = 0;
        masterQtyInputs.forEach((input) => {
            const quantity = Number.parseFloat(input.value);
            const productId = input.getAttribute('data-product-id');

            if (!productId || !Number.isFinite(quantity) || quantity <= 0) {
                return;
            }

            const productField = document.createElement('input');
            productField.type = 'hidden';
            productField.name = `items[${itemIndex}][product_id]`;
            productField.value = productId;
            productField.setAttribute('data-generated-preset-item', 'true');

            const quantityField = document.createElement('input');
            quantityField.type = 'hidden';
            quantityField.name = `items[${itemIndex}][quantity]`;
            quantityField.value = quantity.toFixed(2);
            quantityField.setAttribute('data-generated-preset-item', 'true');

            form.append(productField, quantityField);
            itemIndex += 1;
        });
    });

    const saveDraftProductsToStorage = () => {
        try {
            const draft = {};
            masterQtyInputs.forEach((input) => {
                const productId = input.getAttribute('data-product-id');
                const quantity = Number.parseFloat(input.value);
                
                if (productId && Number.isFinite(quantity) && quantity > 0) {
                    draft[productId] = quantity;
                }
            });

            if (Object.keys(draft).length > 0) {
                window.localStorage.setItem(draftStorageKey, JSON.stringify(draft));
            } else {
                window.localStorage.removeItem(draftStorageKey);
            }
        } catch {
            console.error('Failed to save draft to localStorage');
        }
    };

    const loadDraftProductsFromStorage = () => {
        try {
            const raw = window.localStorage.getItem(draftStorageKey);
            const draft = raw ? JSON.parse(raw) : null;

            if (!draft || typeof draft !== 'object') {
                return;
            }

            Object.entries(draft).forEach(([productId, quantity]) => {
                if (!productsById.has(String(productId))) {
                    return;
                }

                const parsedQuantity = Number.parseFloat(String(quantity));
                if (Number.isFinite(parsedQuantity) && parsedQuantity > 0) {
                    syncMasterInput(productId, parsedQuantity);
                    const product = productsById.get(String(productId));
                    if (product) {
                        updateProductCardBadge(productId, parsedQuantity, product.unit);
                    }
                }
            });

            document.dispatchEvent(new Event('shop-owner-order-input-change'));
        } catch {
            window.localStorage.removeItem(draftStorageKey);
        }
    };

    const autoAddProductFromQuery = () => {
        const url = new URL(window.location.href);
        const productId = url.searchParams.get('product');
        const requestedQuantity = Number.parseFloat(url.searchParams.get('qty') ?? '');

        if (!productId || !productsById.has(String(productId))) {
            return;
        }

        const product = productsById.get(String(productId));
        const fallbackQuantity = Number(product?.suggested_qty ?? 0) > 0 ? Number(product?.suggested_qty) : 1;
        const finalQuantity = Number.isFinite(requestedQuantity) && requestedQuantity > 0 ? requestedQuantity : fallbackQuantity;

        syncMasterInput(productId, finalQuantity);
        if (product) {
            updateProductCardBadge(productId, finalQuantity, product.unit);
        }

        url.searchParams.delete('product');
        url.searchParams.delete('price_date');
        url.searchParams.delete('qty');
        window.history.replaceState({}, '', url.toString());
        
        document.dispatchEvent(new Event('shop-owner-order-input-change'));
        openQtyModal(productId);
    };

    // ── REASON FOR CHANGE SYNCHRONIZATION (UPDATE REQUESTS) ────────────────
    const hiddenReasonInput = document.getElementById('hidden-reason-input');
    const reasonPageInput = document.getElementById('visible-reason-page');
    const reasonDrawerInput = document.getElementById('visible-reason-drawer');

    if (hiddenReasonInput) {
        const syncReason = (val) => {
            hiddenReasonInput.value = val;
            if (reasonPageInput && reasonPageInput.value !== val) {
                reasonPageInput.value = val;
            }
            if (reasonDrawerInput && reasonDrawerInput.value !== val) {
                reasonDrawerInput.value = val;
            }
        };

        reasonPageInput?.addEventListener('input', (e) => syncReason(e.target.value));
        reasonDrawerInput?.addEventListener('input', (e) => syncReason(e.target.value));

        // Initial sync
        if (reasonPageInput) {
            hiddenReasonInput.value = reasonPageInput.value;
        } else if (reasonDrawerInput) {
            hiddenReasonInput.value = reasonDrawerInput.value;
        }
    }

    loadDraftProductsFromStorage();
    autoAddProductFromQuery();
});
