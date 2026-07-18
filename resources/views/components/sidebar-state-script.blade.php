@props([
    'storageKey',
    'shellId',
    'sidebarId',
    'mainId',
    'overlayId',
    'openButtonId',
    'closeButtonId',
    'collapseButtonId' => null,
    'toggleButtonId' => null,
    'labelSelector' => '[data-sidebar-label]',
])

<script>
    (() => {
        const storageKey = @js($storageKey);
        const shell = document.getElementById(@js($shellId));
        const sidebar = document.getElementById(@js($sidebarId));
        const main = document.getElementById(@js($mainId));
        const overlay = document.getElementById(@js($overlayId));
        const openButton = document.getElementById(@js($openButtonId));
        const closeButton = document.getElementById(@js($closeButtonId));
        const collapseButton = @js($collapseButtonId) ? document.getElementById(@js($collapseButtonId)) : null;
        const toggleButton = @js($toggleButtonId) ? document.getElementById(@js($toggleButtonId)) : null;
        const labels = document.querySelectorAll(@js($labelSelector));

        if (!shell || !sidebar || !main || !overlay || !openButton || !closeButton) {
            return;
        }

        const syncDesktopState = (state) => {
            const isCollapsed = state === 'collapsed';
            shell.dataset.sidebarState = state;

            if (window.innerWidth >= 1024) {
                sidebar.classList.toggle('lg:w-72', !isCollapsed);
                sidebar.classList.toggle('lg:w-24', isCollapsed);
                main.classList.toggle('lg:pl-72', !isCollapsed);
                main.classList.toggle('lg:pl-24', isCollapsed);
                labels.forEach((label) => label.classList.toggle('hidden', isCollapsed));
            } else {
                sidebar.classList.remove('lg:w-24');
                sidebar.classList.add('lg:w-72');
                main.classList.remove('lg:pl-24');
                main.classList.add('lg:pl-72');
                labels.forEach((label) => label.classList.remove('hidden'));
            }
        };

        const setDesktopState = (state) => {
            localStorage.setItem(storageKey, state);
            syncDesktopState(state);
        };

        const openSidebar = () => {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        };

        const closeSidebar = () => {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        };

        openButton.addEventListener('click', () => {
            if (window.innerWidth >= 1024 && !toggleButton && !collapseButton) {
                setDesktopState(shell.dataset.sidebarState === 'collapsed' ? 'expanded' : 'collapsed');

                return;
            }

            openSidebar();
        });

        closeButton.addEventListener('click', closeSidebar);
        overlay.addEventListener('click', closeSidebar);

        const toggleDesktopSidebar = () => {
            if (window.innerWidth < 1024) {
                openSidebar();

                return;
            }

            setDesktopState(shell.dataset.sidebarState === 'collapsed' ? 'expanded' : 'collapsed');
        };

        collapseButton?.addEventListener('click', toggleDesktopSidebar);
        toggleButton?.addEventListener('click', toggleDesktopSidebar);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeSidebar();
            }
        });

        window.addEventListener('resize', () => {
            syncDesktopState(localStorage.getItem(storageKey) === 'collapsed' ? 'collapsed' : 'expanded');

            if (window.innerWidth >= 1024) {
                overlay.classList.add('hidden');
                sidebar.classList.remove('-translate-x-full');
            } else {
                sidebar.classList.add('-translate-x-full');
            }
        });

        syncDesktopState(localStorage.getItem(storageKey) === 'collapsed' ? 'collapsed' : 'expanded');

        if (window.innerWidth >= 1024) {
            sidebar.classList.remove('-translate-x-full');
        }
    })();
</script>
