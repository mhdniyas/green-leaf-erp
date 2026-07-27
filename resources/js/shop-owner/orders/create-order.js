document.addEventListener('DOMContentLoaded', () => {
    const formNode = document.getElementById('shop-owner-order-form');
    const productCatalogNode = document.getElementById('shop-owner-product-catalog');
    const presetsNode = document.getElementById('shop-owner-presets-data');
    const presetSelect = document.querySelector('[data-preset-select]');
    const applyPresetButton = document.querySelector('[data-apply-preset]');
    const searchInput = document.querySelector('[data-product-search]');
    const categoryPills = Array.from(document.querySelectorAll('[data-category-pill]'));
    const productRows = Array.from(document.querySelectorAll('[data-product-card]'));
    const quantityInputs = Array.from(document.querySelectorAll('[data-inline-qty]'));
    const productListContainer = document.getElementById('product-list-container');
    const noSearchResults = document.getElementById('no-search-results');
    const currentListTitle = document.getElementById('current-list-title');
    const listResultsCount = document.getElementById('list-results-count');
    const draftCartBar = document.getElementById('draft-cart-bar');
    const draftCartSummary = document.getElementById('draft-cart-summary');
    const draftCartClear = document.getElementById('draft-cart-clear');
    const draftCartSubmit = document.getElementById('draft-cart-submit');
    const pageSubmitButtons = Array.from(document.querySelectorAll('[data-open-cart-submit]'));
    const itemsErrorBanner = document.querySelector('[data-items-error-banner]');
    const savePresetForm = document.querySelector('[data-save-preset-form]');
    const hiddenPresetNameInput = document.querySelector('[data-preset-name-input]');

    if (!formNode || !productCatalogNode || quantityInputs.length === 0 || !draftCartBar || !draftCartSummary || !draftCartClear || !draftCartSubmit) {
        return;
    }

    const productCatalog = JSON.parse(productCatalogNode.textContent ?? '[]');
    const productsById = new Map(productCatalog.map((product) => [String(product.id), product]));
    const presets = presetsNode ? JSON.parse(presetsNode.textContent ?? '[]') : [];
    const inputsByProductId = new Map(quantityInputs.map((input) => [String(input.getAttribute('data-product-id')), input]));
    const draftStorageKey = `shop-owner-order-draft:${formNode.action}:${formNode.querySelector('[name="business_date"]')?.value ?? window.location.pathname}`;

    let activeCategory = categoryPills.find((pill) => pill.hasAttribute('data-default-category'))?.getAttribute('data-default-category') ?? 'all';

    const parseQty = (value) => {
        const quantity = Number.parseFloat(String(value));

        return Number.isFinite(quantity) && quantity > 0 ? quantity : 0;
    };

    const selectedRows = () => quantityInputs
        .map((input) => {
            const productId = String(input.getAttribute('data-product-id'));
            const product = productsById.get(productId);
            const quantity = parseQty(input.value);

            return product && quantity > 0 ? { input, product, quantity } : null;
        })
        .filter(Boolean);

    const updateRowState = (input) => {
        const row = input.closest('[data-product-card]');
        if (!row) {
            return;
        }

        const isSelected = parseQty(input.value) > 0;
        row.classList.toggle('border-emerald-200', isSelected);
        row.classList.toggle('bg-emerald-50', isSelected);
        row.classList.toggle('shadow-sm', isSelected);
        row.classList.toggle('border-slate-200', !isSelected);
        row.classList.toggle('bg-white', !isSelected);
        row.querySelector('[data-row-selection-label]')?.classList.toggle('hidden', !isSelected);
    };

    const syncSubmitButtons = () => {
        const hasSelected = selectedRows().length > 0;

        pageSubmitButtons.forEach((button) => {
            button.disabled = !hasSelected;
            button.classList.toggle('opacity-60', !hasSelected);
            button.classList.toggle('cursor-not-allowed', !hasSelected);
        });

        itemsErrorBanner?.classList.toggle('hidden', hasSelected);
    };

    const saveDraft = () => {
        const draft = {};
        selectedRows().forEach((row) => {
            draft[row.product.id] = row.quantity;
        });

        if (Object.keys(draft).length > 0) {
            window.localStorage.setItem(draftStorageKey, JSON.stringify(draft));
        } else {
            window.localStorage.removeItem(draftStorageKey);
        }
    };

    const syncDraftBar = () => {
        const rows = selectedRows();
        const count = rows.length;
        const total = rows.reduce((sum, row) => sum + row.quantity, 0);
        const units = new Set(rows.map((row) => String(row.product.unit).toUpperCase()));
        const quantityLabel = units.size === 1 ? `${total.toFixed(2)} ${Array.from(units)[0]}` : `${total.toFixed(2)} total`;

        if (count > 0) {
            draftCartSummary.textContent = `${count} selected • ${quantityLabel}`;
            draftCartBar.classList.remove('hidden');
        } else {
            draftCartSummary.textContent = '0 selected';
            draftCartBar.classList.add('hidden');
        }

        syncSubmitButtons();
        document.dispatchEvent(new Event('shop-owner-order-input-change'));
    };

    const setInputQuantity = (productId, quantity) => {
        const input = inputsByProductId.get(String(productId));
        if (!input) {
            return;
        }

        input.value = quantity > 0 ? quantity.toFixed(2) : '';
        updateRowState(input);
    };

    const syncAll = ({ persist = true } = {}) => {
        quantityInputs.forEach(updateRowState);
        syncDraftBar();
        if (persist) {
            saveDraft();
        }
    };

    const visibleQuantityInputs = () => productRows
        .filter((row) => !row.classList.contains('hidden'))
        .map((row) => row.querySelector('[data-inline-qty]'))
        .filter(Boolean);

    const focusNextInput = (currentInput) => {
        const visibleInputs = visibleQuantityInputs();
        const currentIndex = visibleInputs.indexOf(currentInput);
        const nextInput = visibleInputs[currentIndex + 1] ?? visibleInputs[0];

        if (nextInput && nextInput !== currentInput) {
            nextInput.focus();
            nextInput.select();
        }
    };

    const filterProducts = () => {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        let visibleCount = 0;

        productRows.forEach((row) => {
            const category = row.getAttribute('data-category') ?? '';
            const isFrequent = row.getAttribute('data-is-frequent') === 'true';
            const searchText = row.getAttribute('data-search-text') ?? '';
            const matchesSearch = query === '' || searchText.includes(query);
            const matchesCategory = query !== ''
                || activeCategory === 'all'
                || (activeCategory === 'frequent' && isFrequent)
                || category === activeCategory;
            const shouldShow = matchesSearch && matchesCategory;

            row.classList.toggle('hidden', !shouldShow);
            if (shouldShow) {
                visibleCount += 1;
            }
        });

        listResultsCount && (listResultsCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'product' : 'products'}`);
        noSearchResults?.classList.toggle('hidden', visibleCount > 0);
        productListContainer?.classList.toggle('hidden', visibleCount === 0);

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
            activeCategory = pill.getAttribute('data-category-pill') ?? 'all';

            categoryPills.forEach((candidate) => {
                candidate.classList.remove('bg-emerald-600', 'text-white', 'shadow-sm');
                candidate.classList.add('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200');
            });

            pill.classList.remove('bg-slate-100', 'text-slate-600', 'hover:bg-slate-200');
            pill.classList.add('bg-emerald-600', 'text-white', 'shadow-sm');

            if (searchInput) {
                searchInput.value = '';
            }

            filterProducts();
        });
    });

    searchInput?.addEventListener('input', filterProducts);

    quantityInputs.forEach((input) => {
        input.addEventListener('input', () => {
            updateRowState(input);
            syncDraftBar();
            saveDraft();
        });

        input.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            focusNextInput(input);
        });
    });

    document.querySelectorAll('[data-next-product]').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('[data-product-card]');
            const input = row?.querySelector('[data-inline-qty]');
            if (input) {
                focusNextInput(input);
            }
        });
    });

    draftCartClear.addEventListener('click', () => {
        quantityInputs.forEach((input) => {
            input.value = '';
        });
        syncAll();
    });

    const submitSelectedRows = () => {
        if (selectedRows().length === 0) {
            syncSubmitButtons();
            return;
        }

        window.localStorage.removeItem(draftStorageKey);
        formNode.submit();
    };

    draftCartSubmit.addEventListener('click', submitSelectedRows);
    pageSubmitButtons.forEach((button) => {
        button.addEventListener('click', submitSelectedRows);
    });

    const applyPreset = () => {
        if (!presetSelect) {
            return;
        }

        const selectedPreset = presets.find((preset) => String(preset.id) === presetSelect.value);
        if (!selectedPreset) {
            return;
        }

        const quantitiesByProductId = new Map(
            selectedPreset.items.map((item) => [String(item.product_id), parseQty(item.quantity)])
        );

        quantityInputs.forEach((input) => {
            const productId = String(input.getAttribute('data-product-id'));
            const quantity = quantitiesByProductId.get(productId) ?? 0;
            input.value = quantity > 0 ? quantity.toFixed(2) : '';
        });

        syncAll();
        filterProducts();
    };

    applyPresetButton?.addEventListener('click', applyPreset);

    savePresetForm?.addEventListener('submit', (event) => {
        const form = event.currentTarget;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        form.querySelectorAll('[data-generated-preset-item]').forEach((node) => node.remove());

        selectedRows().forEach((row, index) => {
            const productField = document.createElement('input');
            productField.type = 'hidden';
            productField.name = `items[${index}][product_id]`;
            productField.value = row.product.id;
            productField.setAttribute('data-generated-preset-item', 'true');

            const quantityField = document.createElement('input');
            quantityField.type = 'hidden';
            quantityField.name = `items[${index}][quantity]`;
            quantityField.value = row.quantity.toFixed(2);
            quantityField.setAttribute('data-generated-preset-item', 'true');

            form.append(productField, quantityField);
        });
    });

    document.addEventListener('shop-owner-save-current-draft-as-preset', () => {
        if (!hiddenPresetNameInput || !savePresetForm) {
            return;
        }

        const name = window.prompt('List name');
        if (!name || selectedRows().length === 0) {
            return;
        }

        hiddenPresetNameInput.value = name.trim();
        savePresetForm.submit();
    });

    const loadDraft = () => {
        try {
            const raw = window.localStorage.getItem(draftStorageKey);
            const draft = raw ? JSON.parse(raw) : null;
            if (!draft || typeof draft !== 'object') {
                return;
            }

            Object.entries(draft).forEach(([productId, quantity]) => {
                setInputQuantity(productId, parseQty(quantity));
            });
        } catch {
            window.localStorage.removeItem(draftStorageKey);
        }
    };

    const autoAddProductFromQuery = () => {
        const url = new URL(window.location.href);
        const productId = url.searchParams.get('product');
        const requestedQuantity = parseQty(url.searchParams.get('qty') ?? '');

        if (!productId || !productsById.has(String(productId))) {
            return;
        }

        const product = productsById.get(String(productId));
        const fallbackQuantity = parseQty(product?.suggested_qty ?? 0) || 1;
        setInputQuantity(productId, requestedQuantity || fallbackQuantity);

        url.searchParams.delete('product');
        url.searchParams.delete('price_date');
        url.searchParams.delete('qty');
        window.history.replaceState({}, '', url.toString());
    };

    const hiddenReasonInput = document.getElementById('hidden-reason-input');
    const reasonPageInput = document.getElementById('visible-reason-page');

    if (hiddenReasonInput && reasonPageInput) {
        hiddenReasonInput.value = reasonPageInput.value;
        reasonPageInput.addEventListener('input', (event) => {
            hiddenReasonInput.value = event.target.value;
        });
    }

    loadDraft();
    autoAddProductFromQuery();
    syncAll({ persist: false });
    filterProducts();
});
