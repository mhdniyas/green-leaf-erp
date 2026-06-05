document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-delivery-shortage]').forEach((element) => {
        const rawValue = Number.parseFloat(element.textContent ?? '0');

        if (rawValue > 0) {
            element.classList.add('text-red-600');
        }
    });
});
