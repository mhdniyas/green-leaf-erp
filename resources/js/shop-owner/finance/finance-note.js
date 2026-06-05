document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-finance-note]').forEach((element) => {
        element.addEventListener('input', () => {
            element.dataset.noteLength = String(element.value.length);
        });
    });
});
