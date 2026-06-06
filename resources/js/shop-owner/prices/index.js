document.addEventListener('DOMContentLoaded', () => {
    const dataNode = document.getElementById('shop-owner-price-board-data');
    const searchInput = document.querySelector('[data-price-board-search]');
    const sortSelect = document.querySelector('[data-price-board-sort]');
    const tableBody = document.querySelector('[data-price-board-body]');
    const tableRows = Array.from(document.querySelectorAll('[data-price-board-row]'));
    const modal = document.getElementById('price-board-add-modal');
    const openModalButtons = Array.from(document.querySelectorAll('[data-open-price-board-modal]'));
    const closeModalButtons = Array.from(document.querySelectorAll('[data-close-price-board-modal]'));
    const modalName = document.querySelector('[data-modal-product-name]');
    const modalSku = document.querySelector('[data-modal-product-sku]');
    const modalPrice = document.querySelector('[data-modal-product-price]');
    const modalFrequency = document.querySelector('[data-modal-product-frequency]');
    const modalQuantity = document.querySelector('[data-modal-product-qty]');
    const modalAddDraftButton = document.querySelector('[data-modal-add-draft]');
    const qtyStepButtons = Array.from(document.querySelectorAll('[data-price-board-qty-step]'));
    const toast = document.getElementById('price-board-toast');
    const toastMessage = document.querySelector('[data-price-board-toast-message]');
    const draftStorageKey = 'shop-owner-order-draft';
    let toastTimer = null;

    if (!dataNode || !tableBody || !modal) {
        return;
    }

    const products = JSON.parse(dataNode.textContent ?? '[]');
    const productsById = new Map(products.map((product) => [String(product.id), product]));
    let activeProductId = null;

    const formatCurrency = (value) => `INR ${Number(value ?? 0).toFixed(2)}`;

    const getNormalizedQuantity = () => {
        if (!(modalQuantity instanceof HTMLInputElement)) {
            return 1;
        }

        const value = Number.parseFloat(modalQuantity.value);

        return value > 0 ? value : 1;
    };

    const readDraft = () => {
        try {
            const raw = window.localStorage.getItem(draftStorageKey);
            const parsed = raw ? JSON.parse(raw) : {};

            return typeof parsed === 'object' && parsed !== null ? parsed : {};
        } catch {
            return {};
        }
    };

    const writeDraft = (draft) => {
        window.localStorage.setItem(draftStorageKey, JSON.stringify(draft));
    };

    const showToast = (message) => {
        if (!(toast instanceof HTMLElement) || !toastMessage) {
            return;
        }

        toastMessage.textContent = message;
        toast.classList.remove('hidden');

        if (toastTimer) {
            window.clearTimeout(toastTimer);
        }

        toastTimer = window.setTimeout(() => {
            toast.classList.add('hidden');
        }, 2200);
    };

    const addProductToDraft = () => {
        if (!activeProductId) {
            return;
        }

        const product = productsById.get(String(activeProductId));
        if (!product) {
            return;
        }

        const draft = readDraft();
        draft[String(product.id)] = getNormalizedQuantity().toFixed(2);
        writeDraft(draft);
        closeModal();
        showToast(`${product.name} added to draft order.`);
    };

    const openModal = (productId) => {
        const product = productsById.get(String(productId));
        if (!product || !(modal instanceof HTMLElement)) {
            return;
        }

        activeProductId = String(product.id);

        if (modalName) {
            modalName.textContent = product.name;
        }
        if (modalSku) {
            modalSku.textContent = `${product.sku} · ${product.unit}`;
        }
        if (modalPrice) {
            modalPrice.textContent = formatCurrency(product.effective_price);
        }
        if (modalFrequency) {
            modalFrequency.textContent = product.order_count > 0
                ? `${product.order_count} recent orders · last qty ${Number(product.last_order_quantity ?? 0).toFixed(2)}`
                : 'New on this board';
        }
        if (modalQuantity instanceof HTMLInputElement) {
            const initialQuantity = Number(product.last_order_quantity ?? 0) > 0 ? Number(product.last_order_quantity) : 1;
            modalQuantity.value = initialQuantity.toFixed(2);
        }

        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    };

    const closeModal = () => {
        if (!(modal instanceof HTMLElement)) {
            return;
        }

        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        activeProductId = null;
    };

    const applyFiltersAndSort = () => {
        const query = searchInput instanceof HTMLInputElement ? searchInput.value.toLowerCase().trim() : '';
        const sortValue = sortSelect instanceof HTMLSelectElement ? sortSelect.value : 'frequent';

        const visibleRows = tableRows.filter((row) => {
            const searchableText = row.getAttribute('data-search-text') ?? '';
            const isVisible = query === '' || searchableText.includes(query);

            row.classList.toggle('hidden', !isVisible);

            return isVisible;
        });

        const sortedRows = visibleRows.sort((left, right) => {
            if (sortValue === 'name') {
                return (left.getAttribute('data-name') ?? '').localeCompare(right.getAttribute('data-name') ?? '');
            }

            if (sortValue === 'price_low') {
                return Number(left.getAttribute('data-effective-price') ?? 0) - Number(right.getAttribute('data-effective-price') ?? 0);
            }

            if (sortValue === 'price_high') {
                return Number(right.getAttribute('data-effective-price') ?? 0) - Number(left.getAttribute('data-effective-price') ?? 0);
            }

            const orderCountDiff = Number(right.getAttribute('data-order-count') ?? 0) - Number(left.getAttribute('data-order-count') ?? 0);
            if (orderCountDiff !== 0) {
                return orderCountDiff;
            }

            const lastQuantityDiff = Number(right.getAttribute('data-last-quantity') ?? 0) - Number(left.getAttribute('data-last-quantity') ?? 0);
            if (lastQuantityDiff !== 0) {
                return lastQuantityDiff;
            }

            return (left.getAttribute('data-name') ?? '').localeCompare(right.getAttribute('data-name') ?? '');
        });

        sortedRows.forEach((row) => {
            tableBody.appendChild(row);
        });
    };

    openModalButtons.forEach((button) => {
        button.addEventListener('click', () => {
            openModal(button.getAttribute('data-product-id'));
        });
    });

    closeModalButtons.forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    qtyStepButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!(modalQuantity instanceof HTMLInputElement)) {
                return;
            }

            const step = Number.parseFloat(button.getAttribute('data-price-board-qty-step') ?? '0');
            const nextValue = Math.max(0.01, getNormalizedQuantity() + step);
            modalQuantity.value = nextValue.toFixed(2);
        });
    });
    modalAddDraftButton?.addEventListener('click', addProductToDraft);

    searchInput?.addEventListener('input', applyFiltersAndSort);
    sortSelect?.addEventListener('change', applyFiltersAndSort);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    applyFiltersAndSort();
});
