const menuToggle = document.querySelector('#menu-toggle');
const primaryNav = document.querySelector('#primary-nav');

if (menuToggle && primaryNav) {
    menuToggle.addEventListener('click', () => {
        const isOpen = !primaryNav.classList.contains('hidden');

        primaryNav.classList.toggle('hidden', isOpen);
        menuToggle.setAttribute('aria-expanded', String(!isOpen));
    });

    primaryNav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            primaryNav.classList.add('hidden');
            menuToggle.setAttribute('aria-expanded', 'false');
        });
    });
}

const productSearch = document.querySelector('#product-search');
const productCards = [...document.querySelectorAll('.product-card')];
const emptyProducts = document.querySelector('#empty-products');
let activeProductFilter = 'all';

const filterProducts = () => {
    const searchTerm = productSearch?.value.toLowerCase().trim() ?? '';
    let visibleCount = 0;

    productCards.forEach((card) => {
        const matchesCategory = activeProductFilter === 'all' || card.dataset.category === activeProductFilter;
        const matchesSearch = !searchTerm || card.dataset.name.includes(searchTerm);
        const isVisible = matchesCategory && matchesSearch;

        card.classList.toggle('hidden', !isVisible);
        visibleCount += isVisible ? 1 : 0;
    });

    emptyProducts?.classList.toggle('hidden', visibleCount > 0);
};

productSearch?.addEventListener('input', filterProducts);
document.querySelectorAll('.product-filter').forEach((filter) => {
    filter.addEventListener('click', () => {
        activeProductFilter = filter.dataset.filter;
        document.querySelectorAll('.product-filter').forEach((item) => {
            item.classList.toggle('bg-brand-700', item === filter);
            item.classList.toggle('text-white', item === filter);
            item.classList.toggle('bg-white', item !== filter);
        });
        filterProducts();
    });
});
