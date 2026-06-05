document.addEventListener('DOMContentLoaded', () => {
    const tabContainer = document.querySelector('[data-orders-active-tab]');
    if (!tabContainer) {
        return;
    }

    const activeTab = tabContainer.getAttribute('data-orders-active-tab') ?? 'all';

    const setTab = (tabName) => {
        document.querySelectorAll('[data-order-tab]').forEach((button) => {
            const isActive = button.getAttribute('data-order-tab') === tabName;
            button.classList.toggle('bg-white', isActive);
            button.classList.toggle('text-slate-900', isActive);
            button.classList.toggle('border', isActive);
            button.classList.toggle('border-slate-200', isActive);
            button.classList.toggle('shadow-sm', isActive);
            button.classList.toggle('text-slate-500', !isActive);
        });

        document.querySelectorAll('[data-order-panel]').forEach((panel) => {
            panel.classList.toggle('hidden', panel.getAttribute('data-order-panel') !== tabName);
        });
    };

    document.querySelectorAll('[data-order-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            const tabName = button.getAttribute('data-order-tab') ?? 'all';
            setTab(tabName);
        });
    });

    const dateFilter = document.getElementById('date_filter');
    const customDateInputs = document.getElementById('custom-date-inputs');

    dateFilter?.addEventListener('change', () => {
        customDateInputs?.classList.toggle('hidden', dateFilter.value !== 'custom');
        customDateInputs?.classList.toggle('grid', dateFilter.value === 'custom');
    });

    setTab(activeTab);
});
