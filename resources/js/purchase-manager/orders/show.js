document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('[data-po-show-table]');
    const totalNode = document.querySelector('[data-po-show-total]');
    const previousPricesNode = document.getElementById('purchase-manager-previous-prices');

    if (!table || !totalNode || !previousPricesNode) {
        return;
    }

    const previousPrices = JSON.parse(previousPricesNode.textContent ?? '{}');

    const recalculateRow = (row) => {
        const productSelect = row.querySelector('[data-po-product]');
        const unitSelect = row.querySelector('[data-po-unit]');
        const unitPriceInput = row.querySelector('[data-po-unit-price]');
        const priceBasisSelect = row.querySelector('[data-po-price-basis]');
        const packetFields = row.querySelector('[data-po-packet-fields]');
        const packetQtyInput = row.querySelector('[data-po-packet-qty]');
        const packetWeightInput = row.querySelector('[data-po-packet-weight]');
        const quantityInput = row.querySelector('[data-po-quantity]');
        const expectedNode = row.querySelector('[data-po-expected]');
        const actualInput = row.querySelector('[data-po-actual-weight]');
        const discrepancyNode = row.querySelector('[data-po-discrepancy]');
        const subtotalNode = row.querySelector('[data-po-line-total]');
        const previousPriceNode = row.querySelector('[data-po-previous-price]');

        if (!unitSelect || !unitPriceInput || !quantityInput || !subtotalNode || !expectedNode) {
            return 0;
        }

        const unit = unitSelect.value;
        const unitPrice = Number.parseFloat(unitPriceInput.value) || 0;
        const priceBasis = priceBasisSelect?.value ?? 'per_kg';
        let expectedQuantity = 0;

        if (productSelect && previousPriceNode) {
            const previousPrice = previousPrices[productSelect.value];
            previousPriceNode.textContent = previousPrice
                ? `Prev. Price: INR ${Number(previousPrice).toFixed(4)}`
                : 'Prev. Price: None';
        }

        if (priceBasisSelect) {
            const perUnitOption = priceBasisSelect.querySelector('option[value="per_unit"]');
            if (perUnitOption) {
                perUnitOption.textContent = `per ${unit === 'kg' ? 'kg' : unit}`;
            }
        }

        if (unit === 'kg') {
            packetFields?.classList.add('hidden');
            quantityInput.classList.remove('hidden');
            quantityInput.readOnly = false;
            expectedQuantity = Number.parseFloat(quantityInput.value) || 0;
        } else {
            packetFields?.classList.remove('hidden');
            quantityInput.classList.add('hidden');
            quantityInput.readOnly = true;
            expectedQuantity = (Number.parseFloat(packetQtyInput?.value ?? '0') || 0) * (Number.parseFloat(packetWeightInput?.value ?? '0') || 0);
            quantityInput.value = expectedQuantity.toFixed(2);
        }

        expectedNode.textContent = `${expectedQuantity.toFixed(2)} kg`;

        const actualWeightValue = actualInput?.value?.trim() ?? '';
        const actualWeight = actualWeightValue === '' ? null : Number.parseFloat(actualWeightValue);

        if (discrepancyNode) {
            if (actualWeight !== null && actualWeight !== expectedQuantity) {
                const difference = actualWeight - expectedQuantity;
                discrepancyNode.textContent = `Diff: ${difference >= 0 ? '+' : ''}${difference.toFixed(2)} kg`;
                discrepancyNode.className = `mt-1 text-[11px] font-semibold ${difference >= 0 ? 'text-emerald-600' : 'text-rose-600'}`;
            } else {
                discrepancyNode.textContent = '';
                discrepancyNode.className = 'mt-1 text-[11px] font-semibold';
            }
        }

        const finalWeight = actualWeight ?? expectedQuantity;
        const pricedUnits = unit === 'kg' ? finalWeight : (Number.parseFloat(packetQtyInput?.value ?? '0') || 0);
        const subtotal = (priceBasis === 'per_unit' ? pricedUnits : finalWeight) * unitPrice;

        subtotalNode.textContent = `INR ${subtotal.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;

        return subtotal;
    };

    const recalculateAll = () => {
        let total = 0;
        table.querySelectorAll('[data-po-show-row]').forEach((row) => {
            total += recalculateRow(row);
        });

        totalNode.textContent = `INR ${total.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;
    };

    table.addEventListener('input', (event) => {
        if (event.target instanceof HTMLElement) {
            recalculateAll();
        }
    });

    table.addEventListener('change', (event) => {
        if (event.target instanceof HTMLElement) {
            recalculateAll();
        }
    });

    recalculateAll();
});
