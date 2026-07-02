(() => {
    const containers = document.querySelectorAll('[data-cash-flow-tabs]');

    containers.forEach((container) => {
        const buttons = Array.from(container.querySelectorAll('[data-cash-flow-tab-button]'));
        const panels = Array.from(container.querySelectorAll('[data-cash-flow-tab-panel]'));

        if (buttons.length === 0 || panels.length === 0) {
            return;
        }

        const setActiveTab = (tabKey) => {
            buttons.forEach((button) => {
                const isActive = button.getAttribute('data-cash-flow-tab-button') === tabKey;

                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                button.classList.toggle('bg-slate-950', isActive);
                button.classList.toggle('text-white', isActive);
                button.classList.toggle('shadow-sm', isActive);
                button.classList.toggle('text-slate-700', !isActive);
                button.classList.toggle('hover:bg-white', !isActive);
            });

            panels.forEach((panel) => {
                const isActive = panel.getAttribute('data-cash-flow-tab-panel') === tabKey;

                panel.classList.toggle('hidden', !isActive);
            });

            container.setAttribute('data-cash-flow-active-tab', tabKey);
            const url = new URL(window.location.href);
            url.searchParams.set('cash_tab', tabKey);
            window.history.replaceState({}, '', url);
        };

        const initialTab = container.getAttribute('data-cash-flow-active-tab') ?? buttons[0].getAttribute('data-cash-flow-tab-button');

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const tabKey = button.getAttribute('data-cash-flow-tab-button');

                if (tabKey !== null) {
                    setActiveTab(tabKey);
                }
            });
        });

        if (initialTab !== null) {
            setActiveTab(initialTab);
        }
    });
})();
