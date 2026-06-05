document.addEventListener('DOMContentLoaded', () => {
    const itemList = document.querySelector('[data-po-items-list]');
    const addButton = document.querySelector('[data-add-po-item]');
    const rowTemplate = document.getElementById('purchase-manager-po-item-template');
    const grandTotal = document.querySelector('[data-po-grand-total]');

    if (!itemList || !rowTemplate || !grandTotal) {
        return;
    }

    const updateRowNames = () => {
        itemList.querySelectorAll('[data-po-item-row]').forEach((row, index) => {
            row.querySelectorAll('[data-field]').forEach((field) => {
                const fieldName = field.getAttribute('data-field');
                field.setAttribute('name', `items[${index}][${fieldName}]`);
            });
        });
    };

    const calculateTotals = () => {
        let total = 0;

        itemList.querySelectorAll('[data-po-item-row]').forEach((row) => {
            const quantityInput = row.querySelector('[data-field="quantity"]');
            const priceInput = row.querySelector('[data-field="unit_price"]');
            const subtotalNode = row.querySelector('[data-po-subtotal]');
            const quantity = Number.parseFloat(quantityInput?.value ?? '0') || 0;
            const price = Number.parseFloat(priceInput?.value ?? '0') || 0;
            const subtotal = quantity * price;

            if (subtotalNode) {
                subtotalNode.textContent = subtotal.toLocaleString('en-IN', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            }

            total += subtotal;
        });

        grandTotal.textContent = total.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    };

    const toggleRemoveButtons = () => {
        const rows = itemList.querySelectorAll('[data-po-item-row]');
        rows.forEach((row) => {
            const button = row.querySelector('[data-remove-po-item]');
            button?.classList.toggle('hidden', rows.length <= 1);
        });
    };

    const bindRow = (row) => {
        row.querySelectorAll('input, select').forEach((field) => {
            field.addEventListener('input', calculateTotals);
            field.addEventListener('change', calculateTotals);
        });

        row.querySelector('[data-remove-po-item]')?.addEventListener('click', () => {
            row.remove();
            updateRowNames();
            toggleRemoveButtons();
            calculateTotals();
        });
    };

    addButton?.addEventListener('click', () => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = rowTemplate.innerHTML.trim();
        const row = wrapper.firstElementChild;

        if (!row) {
            return;
        }

        itemList.appendChild(row);
        bindRow(row);
        updateRowNames();
        toggleRemoveButtons();
        calculateTotals();
    });

    itemList.querySelectorAll('[data-po-item-row]').forEach((row) => bindRow(row));
    updateRowNames();
    toggleRemoveButtons();
    calculateTotals();
});
