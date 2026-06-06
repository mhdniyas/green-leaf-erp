document.addEventListener('DOMContentLoaded', () => {
    const totalQuantityNode = document.querySelector('[data-order-total-qty]');
    const totalItemsNode = document.querySelector('[data-order-total-items]');
    const totalValueNode = document.querySelector('[data-order-total-value]');
    const quantityInputs = document.querySelectorAll('[data-master-qty]');

    if (! totalQuantityNode || ! totalItemsNode || ! totalValueNode || quantityInputs.length === 0) {
        return;
    }

    const updateTotals = () => {
        const selectedInputs = Array.from(quantityInputs).filter((input) => {
            const numericValue = Number.parseFloat(input.value);

            return Number.isFinite(numericValue) && numericValue > 0;
        });

        const total = selectedInputs.reduce((carry, input) => {
            const numericValue = Number.parseFloat(input.value);

            return carry + numericValue;
        }, 0);

        const totalValue = selectedInputs.reduce((carry, input) => {
            const numericValue = Number.parseFloat(input.value);
            const unitPrice = Number.parseFloat(input.getAttribute('data-effective-price') ?? '0');

            return carry + numericValue * unitPrice;
        }, 0);

        totalQuantityNode.textContent = total.toFixed(2);
        totalItemsNode.textContent = String(selectedInputs.length);
        totalValueNode.textContent = totalValue.toFixed(2);
    };

    quantityInputs.forEach((input) => input.addEventListener('input', updateTotals));
    updateTotals();
});
