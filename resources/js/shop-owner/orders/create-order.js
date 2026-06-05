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

    const presets = presetsNode ? JSON.parse(presetsNode.textContent ?? '[]') : [];
    const productCatalog = productCatalogNode ? JSON.parse(productCatalogNode.textContent ?? '[]') : [];
    const productsById = new Map(productCatalog.map((product) => [String(product.id), product]));
    const masterInputsById = new Map(quantityInputs.map((input) => [input.getAttribute('data-product-id'), input]));

    const selectedProducts = () => productCatalog.filter((product) => {
        const input = masterInputsById.get(String(product.id));

        return input && Number.parseFloat(input.value) > 0;
    });

    const syncMasterInput = (productId, quantity) => {
        const input = masterInputsById.get(String(productId));
        if (!input) {
            return;
        }

        input.value = quantity > 0 ? quantity.toFixed(2) : '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const renderSelectedProducts = () => {
        if (!selectedProductsContainer || !emptySelectionState || !selectedCountBadge) {
            return;
        }

        const selected = selectedProducts();
        selectedProductsContainer.innerHTML = '';

        selectedCountBadge.textContent = `${selected.length} items selected`;
        emptySelectionState.classList.toggle('hidden', selected.length > 0);
        selectedTableHead?.classList.toggle('hidden', selected.length === 0);

        selected
            .sort((left, right) => left.name.localeCompare(right.name))
            .forEach((product) => {
                const input = masterInputsById.get(String(product.id));
                const quantity = input ? Number.parseFloat(input.value) : 0;
                const article = document.createElement('article');
                article.className = 'grid grid-cols-[1.5fr_90px_70px_36px] gap-2 items-center py-2.5 border-b border-slate-100 hover:bg-slate-50/50 transition sm:grid-cols-[1.5fr_110px_90px_48px]';
                article.setAttribute('data-selected-row', String(product.id));
                article.innerHTML = `
                    <div class="min-w-0">
                        <p class="font-bold text-slate-900 text-xs sm:text-sm truncate" title="${product.name}">${product.name}</p>
                        <p class="text-[10px] text-slate-500 truncate">${product.sku} · <span class="uppercase">${product.unit}</span></p>
                    </div>
                    <div>
                        <input type="number" step="0.01" min="0" value="${Number.isFinite(quantity) ? quantity.toFixed(2) : ''}" data-selected-qty-input="${product.id}" class="shop-owner-qty-input w-full border border-slate-200 rounded-lg bg-white px-2 py-1 text-right font-black text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none text-xs sm:text-sm" placeholder="0.00">
                    </div>
                    <div class="text-right text-xs font-bold text-slate-600">
                        ${Number(product.suggested_qty ?? 0).toFixed(2)}
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
            });

        selectedProductsContainer.querySelectorAll('[data-selected-qty-input]').forEach((input) => {
            input.addEventListener('input', (event) => {
                const target = event.currentTarget;
                const quantity = Number.parseFloat(target.value);
                syncMasterInput(target.getAttribute('data-selected-qty-input'), Number.isFinite(quantity) ? quantity : 0);
            });
        });

        selectedProductsContainer.querySelectorAll('[data-remove-product]').forEach((button) => {
            button.addEventListener('click', () => {
                syncMasterInput(button.getAttribute('data-remove-product'), 0);
            });
        });

        selectedProductsContainer.querySelectorAll('[data-qty-step]').forEach((button) => {
            button.addEventListener('click', () => {
                const productId = button.getAttribute('data-qty-step');
                const step = Number.parseFloat(button.getAttribute('data-step') ?? '0');
                const input = productId ? masterInputsById.get(String(productId)) : null;
                const currentValue = input ? Number.parseFloat(input.value) : 0;
                const nextValue = Math.max(0, (Number.isFinite(currentValue) ? currentValue : 0) + step);

                syncMasterInput(productId, nextValue);
            });
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

    renderSelectedProducts();
    renderSearchResults(searchInput instanceof HTMLInputElement ? searchInput.value : '');
});
