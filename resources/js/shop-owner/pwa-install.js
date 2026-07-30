const installButton = document.querySelector('[data-pwa-install-button]');
const installLabel = document.querySelector('[data-pwa-install-label]');
let deferredInstallPrompt = null;

const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

const isIosSafari = () => /iphone|ipad|ipod/i.test(window.navigator.userAgent) && !isStandalone();

const showInstallButton = (label = 'Install App') => {
    if (!installButton) {
        return;
    }

    installButton.hidden = false;

    if (installLabel) {
        installLabel.textContent = label;
    }
};

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').then((registration) => {
            registration.addEventListener('updatefound', () => {
                const worker = registration.installing;

                worker?.addEventListener('statechange', () => {
                    if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                        worker.postMessage({ type: 'SKIP_WAITING' });
                    }
                });
            });
        }).catch(() => {});
    });

    navigator.serviceWorker.addEventListener('controllerchange', () => {
        window.location.reload();
    });
}

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    showInstallButton();
});

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;

    if (installButton) {
        installButton.hidden = true;
    }
});

if (isIosSafari()) {
    showInstallButton('Add To Home Screen');
}

installButton?.addEventListener('click', async () => {
    if (deferredInstallPrompt) {
        deferredInstallPrompt.prompt();
        await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;
        return;
    }

    window.alert('Use Safari Share, then Add to Home Screen.');
});
