document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.querySelector('[data-product-search]');

    if (! searchInput) {
        return;
    }

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.toLowerCase().trim();

        document.querySelectorAll('[data-product-row]').forEach((row) => {
            const searchableText = row.getAttribute('data-search-text') ?? row.textContent.toLowerCase();

            row.classList.toggle('hidden', query !== '' && ! searchableText.includes(query));
        });
    });
});
