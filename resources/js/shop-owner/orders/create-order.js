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
                article.className = 'rounded-2xl border border-slate-200 bg-slate-50 p-4 md:grid md:grid-cols-[minmax(0,1.6fr)_140px_120px_88px] md:items-center md:gap-3 md:rounded-none md:border-0 md:border-b md:border-slate-100 md:bg-transparent md:px-0 md:py-3';
                article.setAttribute('data-selected-row', String(product.id));
                article.innerHTML = `
                    <div class="flex items-start justify-between gap-3 md:block">
                        <div>
                            <p class="font-bold text-slate-900">${product.name}</p>
                            <p class="mt-1 text-xs text-slate-500">${product.sku} · ${product.category}</p>
                        </div>
                        <button type="button" data-remove-product="${product.id}" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-black text-slate-600 md:hidden">Remove</button>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500 md:hidden">Qty</label>
                        <div class="mt-1 flex items-center gap-3 md:mt-0 md:justify-end">
                            <div class="inline-flex items-center rounded-2xl border border-slate-200 bg-white p-1 shadow-sm">
                                <button type="button" data-qty-step="${product.id}" data-step="-1" class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-xl font-black text-slate-700 transition hover:bg-slate-200">-</button>
                                <input type="number" step="0.01" min="0" value="${Number.isFinite(quantity) ? quantity.toFixed(2) : ''}" data-selected-qty-input="${product.id}" class="shop-owner-qty-input w-28 border-0 bg-transparent px-2 text-center text-2xl font-black text-slate-900 focus:outline-none md:w-24">
                                <button type="button" data-qty-step="${product.id}" data-step="1" class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-xl font-black text-slate-700 transition hover:bg-slate-200">+</button>
                            </div>
                            <span class="min-w-10 text-sm font-black uppercase tracking-[0.12em] text-slate-500">${product.unit}</span>
                        </div>
                    </div>
                    <div class="mt-4 md:mt-0 md:text-right">
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500 md:hidden">Suggested</p>
                        <p class="mt-1 text-sm font-bold text-slate-900 md:mt-0">${Number(product.suggested_qty ?? 0).toFixed(2)} ${product.unit}</p>
                    </div>
                    <div class="mt-4 hidden items-center justify-end gap-2 md:mt-0 md:flex">
                        <button type="button" data-remove-product="${product.id}" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-black text-slate-600">Remove</button>
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
            article.className = 'rounded-2xl border border-slate-200 bg-slate-50 p-4';
            article.innerHTML = `
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-bold text-slate-900">${product.name}</p>
                        <p class="mt-1 text-xs text-slate-500">${product.sku} · ${product.category}</p>
                    </div>
                    <span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-slate-600">${product.unit}</span>
                </div>
                <div class="mt-4 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Suggested</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">${Number(product.suggested_qty ?? 0).toFixed(2)} ${product.unit}</p>
                    </div>
                    <button type="button" data-search-add-product="${product.id}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">
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
