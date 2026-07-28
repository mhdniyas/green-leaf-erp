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
    const unitInputs = Array.from(document.querySelectorAll('[data-inline-unit]'));
    const unitPickers = Array.from(document.querySelectorAll('[data-inline-unit-picker]'));
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
    const mobileNav = document.getElementById('layout-mobile-nav');

    if (!formNode || !productCatalogNode || quantityInputs.length === 0 || !draftCartBar || !draftCartSummary || !draftCartClear || !draftCartSubmit) {
        return;
    }

    const productCatalog = JSON.parse(productCatalogNode.textContent ?? '[]');
    const productsById = productCatalog.reduce((map, product) => {
        const id = String(product.id);
        if (!map.has(id)) {
            map.set(id, product);
        }

        return map;
    }, new Map());
    const productsByLineKey = new Map(productCatalog.map((product) => [String(product.line_key ?? product.id), product]));
    const presets = presetsNode ? JSON.parse(presetsNode.textContent ?? '[]') : [];
    const inputsByLineKey = new Map(quantityInputs.map((input) => [String(input.getAttribute('data-line-key') ?? input.getAttribute('data-product-id')), input]));
    const unitInputsByLineKey = new Map(unitInputs.map((input) => [String(input.getAttribute('data-line-key') ?? input.getAttribute('data-product-id')), input]));
    const draftStorageKey = `shop-owner-order-draft:${formNode.action}:${formNode.querySelector('[name="business_date"]')?.value ?? window.location.pathname}`;

    let activeCategory = categoryPills.find((pill) => pill.hasAttribute('data-default-category'))?.getAttribute('data-default-category') ?? 'all';
    let mobileNavRestoreTimer = null;

    const parseQty = (value) => {
        const quantity = Number.parseFloat(String(value));

        return Number.isFinite(quantity) && quantity > 0 ? quantity : 0;
    };

    const formatQuantity = (value) => {
        const rounded = Math.round((Number(value) + Number.EPSILON) * 100) / 100;

        return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
    };

    const unitDefinitionForProduct = (product, unit) => {
        if (!product || !Array.isArray(product.order_units)) {
            return null;
        }

        return product.order_units.find((candidate) => candidate.public_uuid === unit || candidate.unit === unit || candidate.label === unit) ?? null;
    };

    const productForInput = (input) => {
        const lineKey = String(input.getAttribute('data-line-key') ?? input.getAttribute('data-product-id'));
        const productId = String(input.getAttribute('data-product-id'));

        return productsByLineKey.get(lineKey) ?? productsById.get(productId);
    };

    const measureInputForUnitInput = (unitInput) => unitInput
        ?.closest('[data-inline-unit-picker]')
        ?.querySelector('[data-inline-measure]')
        ?? unitInput
            ?.closest('[data-product-card]')
            ?.querySelector(`[data-inline-measure][data-line-key="${unitInput.getAttribute('data-line-key')}"]`);

    const syncUnitConversionInfo = (input) => {
        const row = input.closest('[data-extra-measure-line]') ?? input.closest('[data-product-card]');
        const lineKey = String(input.getAttribute('data-line-key') ?? input.getAttribute('data-product-id'));
        const product = productForInput(input);
        const unitInput = unitInputsByLineKey.get(lineKey);
        const measureInput = measureInputForUnitInput(unitInput);
        const selectedUnit = measureInput?.value || unitInput?.value || product?.current_unit || product?.unit;
        const unitDefinition = unitDefinitionForProduct(product, selectedUnit);
        const info = row?.querySelector('[data-unit-conversion-info]');
        const conversion = Number.parseFloat(String(unitDefinition?.conversion_to_base ?? '1'));

        if (!info || !product || !unitDefinition || !Number.isFinite(conversion) || conversion <= 0 || Math.abs(conversion - 1) < 0.0001) {
            info?.classList.add('hidden');
            return;
        }

        const enteredQuantity = parseQty(input.value);
        const displayQuantity = enteredQuantity > 0 ? enteredQuantity : 1;
        const baseQuantity = displayQuantity * conversion;
        const selectedLabel = String(unitDefinition?.label ?? selectedUnit).toUpperCase();
        const baseLabel = String(product.unit).toUpperCase();

        info.textContent = `${formatQuantity(displayQuantity)} ${selectedLabel} = ${formatQuantity(baseQuantity)} ${baseLabel}`;
        info.classList.remove('hidden');
    };

    const selectedRows = () => quantityInputs
        .map((input) => {
            const lineKey = String(input.getAttribute('data-line-key') ?? input.getAttribute('data-product-id'));
            const product = productForInput(input);
            const quantity = parseQty(input.value);
            const unitInput = unitInputsByLineKey.get(lineKey);
            const unit = unitInput?.value || product?.current_unit || product?.unit;
            const measureInput = measureInputForUnitInput(unitInput);
            const unitDefinition = unitDefinitionForProduct(product, measureInput?.value || unit);

            return product && quantity > 0 ? { input, product, quantity, unitInput, unit, lineKey, unitDefinition } : null;
        })
        .filter(Boolean);

    const updateRowState = (input) => {
        const row = input.closest('[data-product-card]');
        if (!row) {
            return;
        }

        const isSelected = Array.from(row.querySelectorAll('[data-inline-qty]')).some((candidate) => parseQty(candidate.value) > 0);
        const measureEntry = input.closest('[data-measure-entry]');
        const isMeasureSelected = parseQty(input.value) > 0;

        measureEntry?.classList.toggle('border-emerald-200', isMeasureSelected);
        measureEntry?.classList.toggle('bg-emerald-50', isMeasureSelected);
        measureEntry?.classList.toggle('border-slate-100', !isMeasureSelected);
        measureEntry?.classList.toggle('bg-slate-50/70', !isMeasureSelected);
        row.classList.toggle('border-emerald-200', isSelected);
        row.classList.toggle('bg-emerald-50', isSelected);
        row.classList.toggle('shadow-sm', isSelected);
        row.classList.toggle('border-slate-200', !isSelected);
        row.classList.toggle('bg-white', !isSelected);
        row.querySelector('[data-row-selection-label]')?.classList.toggle('hidden', !isSelected);
        syncUnitConversionInfo(input);
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
            draft[row.lineKey] = {
                quantity: row.quantity,
                unit: row.unit,
                measure: measureInputForUnitInput(row.unitInput)?.value ?? null,
            };
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
        const convertedRows = rows.filter((row) => row.unitDefinition?.conversion_to_base !== null && row.unitDefinition?.conversion_to_base !== undefined);
        const unconvertedRows = rows.filter((row) => !convertedRows.includes(row));
        const baseTotals = new Map();

        convertedRows.forEach((row) => {
            const baseUnit = String(row.product.unit || row.unit).toUpperCase();
            const conversion = Number.parseFloat(String(row.unitDefinition?.conversion_to_base ?? 1));
            baseTotals.set(baseUnit, (baseTotals.get(baseUnit) ?? 0) + (row.quantity * conversion));
        });

        unconvertedRows.forEach((row) => {
            const label = String(row.unitDefinition?.label || row.unit || row.product.unit).toUpperCase();
            baseTotals.set(label, (baseTotals.get(label) ?? 0) + row.quantity);
        });

        const quantityLabel = Array.from(baseTotals.entries())
            .map(([unit, total]) => `${formatQuantity(total)} ${unit}`)
            .join(' + ') || '0 selected';

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

    const setInputQuantity = (lineKey, quantity, unit = null) => {
        const input = inputsByLineKey.get(String(lineKey));
        if (!input) {
            return;
        }

        input.value = quantity > 0 ? quantity.toFixed(2) : '';
        if (quantity > 0) {
            input.closest('[data-extra-measure-line]')?.classList.remove('hidden');
        }
        if (unit) {
            setProductUnit(lineKey, unit, { persist: false });
        }
        updateRowState(input);
    };

    const closeUnitPickers = (exceptPicker = null) => {
        unitPickers.forEach((picker) => {
            if (picker === exceptPicker) {
                return;
            }

            picker.querySelector('[data-unit-picker-menu]')?.classList.add('hidden');
            picker.querySelector('[data-unit-picker-trigger]')?.setAttribute('aria-expanded', 'false');
        });
    };

    const syncUnitPickerOptions = (picker, selectedUnit) => {
        const label = picker.querySelector('[data-unit-picker-label]');
        const product = productsByLineKey.get(String(picker.getAttribute('data-line-key') ?? picker.getAttribute('data-product-id')))
            ?? productsById.get(String(picker.getAttribute('data-product-id')));
        const selectedDefinition = unitDefinitionForProduct(product, selectedUnit);
        if (label) {
            label.textContent = String(selectedDefinition?.label ?? selectedUnit).toUpperCase();
        }

        picker.querySelectorAll('[data-unit-picker-option]').forEach((option) => {
            const isSelected = option.getAttribute('data-unit-value') === selectedUnit
                || option.getAttribute('data-unit-name') === selectedUnit;
            option.setAttribute('aria-selected', isSelected ? 'true' : 'false');
            option.classList.toggle('bg-emerald-600', isSelected);
            option.classList.toggle('text-white', isSelected);
            option.classList.toggle('text-slate-700', !isSelected);
            option.classList.toggle('hover:bg-slate-100', !isSelected);
            option.querySelector('[data-unit-picker-check]')?.classList.toggle('invisible', !isSelected);
        });
    };

    function setProductUnit(lineKey, unit, { persist = true } = {}) {
        const unitInput = unitInputsByLineKey.get(String(lineKey));
        if (!unitInput) {
            return;
        }

        const product = productsByLineKey.get(String(lineKey))
            ?? productsById.get(String(unitInput.getAttribute('data-product-id')));
        const unitDefinition = unitDefinitionForProduct(product, unit);
        const measureInput = measureInputForUnitInput(unitInput);

        unitInput.value = unitDefinition?.unit ?? unit;

        if (measureInput) {
            measureInput.value = unitDefinition?.public_uuid ?? '';
        }

        const picker = unitInput.closest('[data-inline-unit-picker]');
        if (picker) {
            syncUnitPickerOptions(picker, unitDefinition?.public_uuid ?? unitDefinition?.unit ?? unit);
        }

        const quantityInput = inputsByLineKey.get(String(lineKey));
        if (quantityInput) {
            syncUnitConversionInfo(quantityInput);
        }

        if (persist) {
            syncDraftBar();
            saveDraft();
        }
    }

    const syncAll = ({ persist = true } = {}) => {
        quantityInputs.forEach(updateRowState);
        syncDraftBar();
        if (persist) {
            saveDraft();
        }
    };

    const setMobileNavHiddenForInput = (isHidden) => {
        if (!mobileNav) {
            return;
        }

        window.clearTimeout(mobileNavRestoreTimer);

        if (isHidden) {
            mobileNav.classList.add('hidden');
            draftCartBar.classList.remove('bottom-16');
            draftCartBar.classList.add('bottom-0');

            return;
        }

        mobileNavRestoreTimer = window.setTimeout(() => {
            const activeElement = document.activeElement;
            const isStillEditingQuantity = activeElement instanceof HTMLElement && activeElement.matches('[data-inline-qty], [data-inline-unit]');

            if (!isStillEditingQuantity) {
                mobileNav.classList.remove('hidden');
                draftCartBar.classList.remove('bottom-0');
                draftCartBar.classList.add('bottom-16');
            }
        }, 120);
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

    document.querySelectorAll('[data-add-measure-line]').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('[data-product-card]');
            const nextLine = row?.querySelector('[data-extra-measure-line].hidden');

            if (!nextLine) {
                return;
            }

            const usedMeasures = new Set(
                Array.from(row.querySelectorAll('[data-inline-measure]'))
                    .filter((input) => !input.closest('[data-extra-measure-line]')?.classList.contains('hidden'))
                    .map((input) => input.value || input.closest('[data-inline-unit-picker]')?.querySelector('[data-inline-unit]')?.value)
                    .filter(Boolean)
            );
            const picker = nextLine.querySelector('[data-inline-unit-picker]');
            const fallbackOption = picker?.querySelector('[data-unit-picker-option]');
            const unusedOption = Array.from(picker?.querySelectorAll('[data-unit-picker-option]') ?? [])
                .find((option) => !usedMeasures.has(option.getAttribute('data-unit-value')));
            const selectedOption = unusedOption ?? fallbackOption;
            const lineKey = picker?.getAttribute('data-line-key');
            const selectedMeasure = selectedOption?.getAttribute('data-unit-value');

            nextLine.classList.remove('hidden');
            if (lineKey && selectedMeasure) {
                setProductUnit(lineKey, selectedMeasure, { persist: false });
            }
            const input = nextLine.querySelector('[data-inline-qty]');
            if (input) {
                input.focus();
                input.select();
                updateRowState(input);
            }

            button.classList.toggle('hidden', !row.querySelector('[data-extra-measure-line].hidden'));
        });
    });

    document.querySelectorAll('[data-remove-measure-line]').forEach((button) => {
        button.addEventListener('click', () => {
            const line = button.closest('[data-extra-measure-line]');
            const input = line?.querySelector('[data-inline-qty]');

            if (input) {
                input.value = '';
                updateRowState(input);
            }

            line?.classList.add('hidden');
            line?.closest('[data-product-card]')?.querySelector('[data-add-measure-line]')?.classList.remove('hidden');
            syncDraftBar();
            saveDraft();
        });
    });

    quantityInputs.forEach((input) => {
        input.addEventListener('focus', () => setMobileNavHiddenForInput(true));
        input.addEventListener('blur', () => setMobileNavHiddenForInput(false));

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

    unitInputs.forEach((input) => {
        input.addEventListener('focus', () => setMobileNavHiddenForInput(true));
        input.addEventListener('blur', () => setMobileNavHiddenForInput(false));
        input.addEventListener('change', () => {
            syncDraftBar();
            saveDraft();
        });
    });

    unitPickers.forEach((picker) => {
        const trigger = picker.querySelector('[data-unit-picker-trigger]');
        const menu = picker.querySelector('[data-unit-picker-menu]');

        trigger?.addEventListener('click', () => {
            const willOpen = menu?.classList.contains('hidden') ?? false;
            closeUnitPickers(picker);
            menu?.classList.toggle('hidden', !willOpen);
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            setMobileNavHiddenForInput(willOpen);
        });

        picker.querySelectorAll('[data-unit-picker-option]').forEach((option) => {
            option.addEventListener('click', () => {
                const productId = picker.getAttribute('data-product-id');
                const lineKey = picker.getAttribute('data-line-key') ?? productId;
                const unit = option.getAttribute('data-unit-value');

                if (lineKey && unit) {
                    setProductUnit(lineKey, unit);
                }

                menu?.classList.add('hidden');
                trigger?.setAttribute('aria-expanded', 'false');
                setMobileNavHiddenForInput(false);
            });
        });
    });

    document.addEventListener('click', (event) => {
        if (event.target instanceof HTMLElement && event.target.closest('[data-inline-unit-picker]')) {
            return;
        }

        closeUnitPickers();
        setMobileNavHiddenForInput(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeUnitPickers();
            setMobileNavHiddenForInput(false);
        }
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
            const lineKey = String(input.getAttribute('data-line-key') ?? productId);
            const quantity = quantitiesByProductId.get(productId) ?? 0;
            const isFirstProductRow = quantityInputs.find((candidate) => String(candidate.getAttribute('data-product-id')) === productId) === input;
            input.value = quantity > 0 && isFirstProductRow ? quantity.toFixed(2) : '';
            if (!isFirstProductRow) {
                window.localStorage.removeItem(`${draftStorageKey}:${lineKey}`);
            }
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

            const measureField = document.createElement('input');
            measureField.type = 'hidden';
            measureField.name = `items[${index}][product_unit_uuid]`;
            measureField.value = row.product.current_measure_uuid ?? '';
            measureField.setAttribute('data-generated-preset-item', 'true');

            form.append(productField, quantityField, measureField);
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

            Object.entries(draft).forEach(([lineKey, value]) => {
                if (value && typeof value === 'object') {
                    setInputQuantity(lineKey, parseQty(value.quantity), value.measure ?? value.unit ?? null);
                    return;
                }

                setInputQuantity(lineKey, parseQty(value));
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
        const lineKey = String(product?.line_key ?? productId);
        const fallbackQuantity = parseQty(product?.suggested_qty ?? 0) || 1;
        setInputQuantity(lineKey, requestedQuantity || fallbackQuantity);

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
