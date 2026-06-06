document.addEventListener('DOMContentLoaded', () => {
    const presetsNode = document.getElementById('shop-owner-presets-data');
    const productCatalogNode = document.getElementById('shop-owner-product-catalog');
    const presetSelect = document.querySelector('[data-preset-select]');
    const applyPresetButton = document.querySelector('[data-apply-preset]');
    const quantityInputs = Array.from(document.querySelectorAll('[data-master-qty]'));
    const savePresetForm = document.querySelector('[data-save-preset-form]');
    const searchInput = document.querySelector('[data-product-search]');
    const searchResultsWrap = document.querySelector('[data-search-results-wrap]');
    const searchResultsContainer = document.querySelector('[data-search-results]');
    const searchEmptyState = document.querySelector('[data-search-empty]');
    const clearSearchButton = document.querySelector('[data-clear-search]');
    const selectedProductsContainer = document.querySelector('[data-selected-products]');
    const emptySelectionState = document.querySelector('[data-empty-selection]');
    const selectedCountBadge = document.querySelector('[data-selected-count-badge]');
    const selectedTableHead = document.querySelector('[data-selected-table-head]');
    const fullCatalog = document.querySelector('[data-full-catalog]');
    const catalogCards = Array.from(document.querySelectorAll('[data-product-row]'));
    const addButtons = Array.from(document.querySelectorAll('[data-add-product]'));
    const draftStorageKey = 'shop-owner-order-draft';

    const presets = presetsNode ? JSON.parse(presetsNode.textContent ?? '[]') : [];
    const productCatalog = productCatalogNode ? JSON.parse(productCatalogNode.textContent ?? '[]') : [];
    const productsById = new Map(productCatalog.map((product) => [String(product.id), product]));
    const masterInputsById = new Map(quantityInputs.map((input) => [input.getAttribute('data-product-id'), input]));
    const selectedRowInputHandlers = new WeakMap();

    const selectedProducts = () => productCatalog.filter((product) => {
        const input = masterInputsById.get(String(product.id));

        return input && Number.parseFloat(input.value) > 0;
    });

    const formatCurrency = (value) => `INR ${Number(value ?? 0).toFixed(2)}`;

    const syncMasterInput = (productId, quantity, shouldRender = true) => {
        const input = masterInputsById.get(String(productId));
        if (!input) {
            return;
        }

        input.value = quantity > 0 ? quantity.toFixed(2) : '';
        if (shouldRender) {
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };

    const updateSelectedProductCount = (count) => {
        selectedCountBadge.textContent = `${count} items selected`;
        emptySelectionState.classList.toggle('hidden', count > 0);
        selectedTableHead?.classList.toggle('hidden', count === 0);
    };

    const attachSelectedRowInputEvents = (input) => {
        if (selectedRowInputHandlers.has(input)) {
            return;
        }

        const productId = input.getAttribute('data-selected-qty-input');
        const handleInput = (event) => {
            const target = event.currentTarget;
            const quantity = Number.parseFloat(target.value);

            syncMasterInput(productId, Number.isFinite(quantity) ? quantity : 0, false);
            document.dispatchEvent(new Event('shop-owner-order-input-change'));
        };

        const handleBlur = (event) => {
            const target = event.currentTarget;
            const quantity = Number.parseFloat(target.value);

            syncMasterInput(productId, Number.isFinite(quantity) ? quantity : 0);
        };

        input.addEventListener('input', handleInput);
        input.addEventListener('blur', handleBlur);
        selectedRowInputHandlers.set(input, true);
    };

    const renderSelectedProducts = () => {
        if (!selectedProductsContainer || !emptySelectionState || !selectedCountBadge) {
            return;
        }

        const selected = selectedProducts();
        const selectedIds = new Set(selected.map((product) => String(product.id)));

        updateSelectedProductCount(selected.length);

        Array.from(selectedProductsContainer.querySelectorAll('[data-selected-row]')).forEach((row) => {
            if (!selectedIds.has(row.getAttribute('data-selected-row'))) {
                row.remove();
            }
        });

        selected
            .sort((left, right) => left.name.localeCompare(right.name))
            .forEach((product) => {
                const input = masterInputsById.get(String(product.id));
                const quantity = input ? Number.parseFloat(input.value) : 0;
                const formattedQuantity = Number.isFinite(quantity) ? quantity.toFixed(2) : '';
                const productPrice = Number(product.price ?? 0);
                const lineTotal = (Number.isFinite(quantity) ? quantity : 0) * productPrice;
                let article = selectedProductsContainer.querySelector(`[data-selected-row="${product.id}"]`);

                if (!article) {
                    article = document.createElement('article');
                    article.className = 'grid grid-cols-[minmax(0,1.4fr)_90px_150px_90px_36px] gap-2 items-center py-2.5 border-b border-slate-100 hover:bg-slate-50/50 transition sm:grid-cols-[minmax(0,1.5fr)_100px_170px_110px_48px]';
                    article.setAttribute('data-selected-row', String(product.id));
                    article.innerHTML = `
                        <div class="min-w-0">
                            <p class="font-bold text-slate-900 text-xs sm:text-sm truncate" title="${product.name}">${product.name}</p>
                            <p class="text-[10px] text-slate-500 truncate">${product.sku} · <span class="uppercase">${product.unit}</span></p>
                        </div>
                        <div class="text-right text-xs font-black text-cyan-700">${formatCurrency(productPrice)}</div>
                        <div class="flex items-center justify-end gap-1">
                            <button type="button" data-qty-step="${product.id}" data-step="-1" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-sm font-black text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700">-</button>
                            <input type="number" step="0.01" min="0" value="${formattedQuantity}" data-selected-qty-input="${product.id}" class="shop-owner-qty-input w-20 rounded-xl border border-slate-200 bg-white px-2 py-2 text-right text-xs font-black text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none sm:w-24 sm:text-sm" placeholder="0.00">
                            <button type="button" data-qty-step="${product.id}" data-step="1" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-sm font-black text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700">+</button>
                        </div>
                        <div class="text-right text-xs font-black text-slate-900" data-selected-line-total="${product.id}">
                            ${formatCurrency(lineTotal)}
                        </div>
                        <div class="flex justify-end">
                            <button type="button" data-remove-product="${product.id}" class="text-slate-400 hover:text-rose-600 p-1 rounded transition" title="Remove">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    `;

                    selectedProductsContainer.appendChild(article);
                }

                const selectedInput = article.querySelector('[data-selected-qty-input]');
                if (selectedInput && selectedInput !== document.activeElement) {
                    selectedInput.value = formattedQuantity;
                }
                if (selectedInput) {
                    attachSelectedRowInputEvents(selectedInput);
                }

                const lineTotalNode = article.querySelector(`[data-selected-line-total="${product.id}"]`);
                if (lineTotalNode) {
                    lineTotalNode.textContent = formatCurrency(lineTotal);
                }
            });

        selectedProductsContainer.querySelectorAll('[data-selected-qty-input]').forEach((input) => {
            attachSelectedRowInputEvents(input);
        });

        selectedProductsContainer.querySelectorAll('[data-remove-product]').forEach((button) => {
            if (button.dataset.bound === 'true') {
                return;
            }

            button.addEventListener('click', () => {
                syncMasterInput(button.getAttribute('data-remove-product'), 0);
            });
            button.dataset.bound = 'true';
        });

        selectedProductsContainer.querySelectorAll('[data-qty-step]').forEach((button) => {
            if (button.dataset.bound === 'true') {
                return;
            }

            button.addEventListener('click', () => {
                const productId = button.getAttribute('data-qty-step');
                const step = Number.parseFloat(button.getAttribute('data-step') ?? '0');
                const input = productId ? masterInputsById.get(String(productId)) : null;
                const currentValue = input ? Number.parseFloat(input.value) : 0;
                const nextValue = Math.max(0, (Number.isFinite(currentValue) ? currentValue : 0) + step);

                syncMasterInput(productId, nextValue);
            });
            button.dataset.bound = 'true';
        });
    };

    const addProductToOrder = (productId) => {
        const product = productId ? productsById.get(String(productId)) : null;

        if (!product) {
            return;
        }

        const fallbackQuantity = Number(product.suggested_qty ?? 0) > 0 ? Number(product.suggested_qty) : 1;
        const existingInput = masterInputsById.get(String(product.id));
        const existingValue = existingInput ? Number.parseFloat(existingInput.value) : 0;

        syncMasterInput(product.id, existingValue > 0 ? existingValue : fallbackQuantity);

        if (searchInput instanceof HTMLInputElement) {
            searchInput.value = '';
            renderSearchResults('');
            searchInput.focus();
        }
    };

    const autoAddProductFromQuery = () => {
        const url = new URL(window.location.href);
        const productId = url.searchParams.get('product');
        const requestedQuantity = Number.parseFloat(url.searchParams.get('qty') ?? '');

        if (!productId || !productsById.has(String(productId))) {
            return;
        }

        addProductToOrder(productId);

        if (Number.isFinite(requestedQuantity) && requestedQuantity > 0) {
            syncMasterInput(productId, requestedQuantity);
        }

        const selectedRow = selectedProductsContainer?.querySelector(`[data-selected-row="${productId}"]`);
        selectedRow?.scrollIntoView({ behavior: 'smooth', block: 'center' });

        url.searchParams.delete('product');
        url.searchParams.delete('price_date');
        url.searchParams.delete('qty');
        window.history.replaceState({}, '', url.toString());
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
                addProductToOrder(productId);

                if (Number.isFinite(parsedQuantity) && parsedQuantity > 0) {
                    syncMasterInput(productId, parsedQuantity);
                }
            });

            window.localStorage.removeItem(draftStorageKey);
        } catch {
            window.localStorage.removeItem(draftStorageKey);
        }
    };

    const renderSearchResults = (query) => {
        if (!searchResultsWrap || !searchResultsContainer || !searchEmptyState) {
            return;
        }

        const normalizedQuery = query.toLowerCase().trim();

        if (normalizedQuery === '') {
            searchResultsWrap.classList.add('hidden');
            searchResultsContainer.innerHTML = '';
            searchEmptyState.classList.add('hidden');
            return;
        }

        const matchedProducts = productCatalog
            .filter((product) => `${product.name} ${product.sku} ${product.category}`.toLowerCase().includes(normalizedQuery))
            .slice(0, 12);

        searchResultsWrap.classList.remove('hidden');
        searchResultsContainer.innerHTML = '';
        searchEmptyState.classList.toggle('hidden', matchedProducts.length > 0);

        matchedProducts.forEach((product) => {
            const article = document.createElement('article');
            article.className = 'flex items-center justify-between py-2.5';
            article.innerHTML = `
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-slate-900 text-xs sm:text-sm truncate">${product.name}</p>
                    <p class="text-[10px] text-slate-500 truncate">${product.sku} · ${product.category} · <span class="uppercase">${product.unit}</span></p>
                </div>
                <div class="flex items-center gap-4 shrink-0 pl-3">
                    <div class="text-right">
                        <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Price</p>
                        <p class="text-xs font-bold text-cyan-700">INR ${Number(product.price ?? 0).toFixed(2)}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Sug.</p>
                        <p class="text-xs font-bold text-slate-700">${Number(product.suggested_qty ?? 0).toFixed(2)}</p>
                    </div>
                    <button type="button" data-search-add-product="${product.id}" class="rounded-lg bg-emerald-600 hover:bg-emerald-700 active:scale-95 transition text-xs font-black text-white px-3 py-1.5 shadow-sm">
                        Add
                    </button>
                </div>
            `;

            searchResultsContainer.appendChild(article);
        });

        searchResultsContainer.querySelectorAll('[data-search-add-product]').forEach((button) => {
            button.addEventListener('click', () => {
                addProductToOrder(button.getAttribute('data-search-add-product'));
            });
        });
    };

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

        quantityInputs.forEach((input) => {
            const productId = input.getAttribute('data-product-id');
            const quantity = productId ? quantitiesByProductId.get(productId) : undefined;

            input.value = Number.isFinite(quantity) ? quantity.toFixed(2) : '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    };

    applyPresetButton?.addEventListener('click', applyPreset);

    addButtons.forEach((button) => {
        button.addEventListener('click', () => {
            addProductToOrder(button.getAttribute('data-add-product'));
        });
    });

    searchInput?.addEventListener('input', () => {
        const query = searchInput.value.toLowerCase().trim();

        renderSearchResults(query);

        if (fullCatalog instanceof HTMLDetailsElement && query !== '') {
            fullCatalog.open = true;
        }

        catalogCards.forEach((card) => {
            const searchableText = card.getAttribute('data-search-text') ?? '';
            card.classList.toggle('hidden', query !== '' && !searchableText.includes(query));
        });
    });

    clearSearchButton?.addEventListener('click', () => {
        if (!(searchInput instanceof HTMLInputElement)) {
            return;
        }

        searchInput.value = '';
        renderSearchResults('');

        catalogCards.forEach((card) => {
            card.classList.remove('hidden');
        });
    });

    savePresetForm?.addEventListener('submit', (event) => {
        const form = event.currentTarget;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        form.querySelectorAll('[data-generated-preset-item]').forEach((node) => node.remove());

        let itemIndex = 0;
        quantityInputs.forEach((input) => {
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

    quantityInputs.forEach((input) => {
        input.addEventListener('input', renderSelectedProducts);
    });

    document.addEventListener('shop-owner-order-input-change', renderSelectedProducts);

    renderSelectedProducts();
    renderSearchResults(searchInput instanceof HTMLInputElement ? searchInput.value : '');
    loadDraftProductsFromStorage();
    autoAddProductFromQuery();
});
