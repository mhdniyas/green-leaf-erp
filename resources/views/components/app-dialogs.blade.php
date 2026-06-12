<div id="app-dialog-root" class="pointer-events-none fixed inset-0 z-[120] hidden" aria-live="polite">
    <div id="app-dialog-backdrop" class="absolute inset-0 bg-slate-950/55 opacity-0 transition-opacity duration-200"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4 sm:p-6">
        <div id="app-dialog-panel"
            class="pointer-events-auto w-full max-w-md scale-95 overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_30px_80px_rgba(15,23,42,0.28)] opacity-0 transition duration-200 ease-out dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start gap-3 px-5 pt-5 sm:px-6 sm:pt-6">
                <div id="app-dialog-icon"
                    class="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-950/30 dark:text-rose-300">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 3c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3Z" />
                    </svg>
                </div>

                <div class="min-w-0 flex-1">
                    <p id="app-dialog-eyebrow" class="text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">
                        Confirm Action
                    </p>
                    <h3 id="app-dialog-title" class="mt-1 text-lg font-black text-slate-900 dark:text-white">
                        Please confirm
                    </h3>
                    <p id="app-dialog-message" class="mt-2 text-sm font-medium leading-6 text-slate-600 dark:text-slate-300">
                        Are you sure you want to continue?
                    </p>
                </div>
            </div>

            <div class="mt-5 flex flex-col-reverse gap-2 px-5 pb-5 sm:flex-row sm:justify-end sm:px-6 sm:pb-6">
                <button type="button" id="app-dialog-cancel"
                    class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 cursor-pointer">
                    Cancel
                </button>
                <button type="button" id="app-dialog-confirm"
                    class="inline-flex items-center justify-center rounded-2xl bg-rose-600 px-4 py-3 text-sm font-black text-white shadow-sm transition hover:bg-rose-700 cursor-pointer">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (() => {
        if (window.__appDialogsInitialized) {
            return;
        }

        window.__appDialogsInitialized = true;

        const root = document.getElementById('app-dialog-root');
        const backdrop = document.getElementById('app-dialog-backdrop');
        const panel = document.getElementById('app-dialog-panel');
        const eyebrow = document.getElementById('app-dialog-eyebrow');
        const icon = document.getElementById('app-dialog-icon');
        const title = document.getElementById('app-dialog-title');
        const message = document.getElementById('app-dialog-message');
        const confirmButton = document.getElementById('app-dialog-confirm');
        const cancelButton = document.getElementById('app-dialog-cancel');

        if (!root || !backdrop || !panel || !eyebrow || !icon || !title || !message || !confirmButton || !cancelButton) {
            return;
        }

        const state = {
            onConfirm: null,
            onCancel: null,
            active: false,
        };

        const toneClasses = {
            danger: {
                icon: ['bg-rose-50', 'text-rose-600', 'dark:bg-rose-950/30', 'dark:text-rose-300'],
                button: ['bg-rose-600', 'hover:bg-rose-700'],
            },
            info: {
                icon: ['bg-cyan-50', 'text-cyan-600', 'dark:bg-cyan-950/30', 'dark:text-cyan-300'],
                button: ['bg-cyan-600', 'hover:bg-cyan-700'],
            },
            success: {
                icon: ['bg-emerald-50', 'text-emerald-600', 'dark:bg-emerald-950/30', 'dark:text-emerald-300'],
                button: ['bg-emerald-600', 'hover:bg-emerald-700'],
            },
        };

        function setTone(tone) {
            const selectedTone = toneClasses[tone] ?? toneClasses.danger;

            icon.className = 'mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl';
            icon.classList.add(...selectedTone.icon);

            confirmButton.className = 'inline-flex items-center justify-center rounded-2xl px-4 py-3 text-sm font-black text-white shadow-sm transition cursor-pointer';
            confirmButton.classList.add(...selectedTone.button);
        }

        function openDialog(options) {
            const dialogOptions = options ?? {};

            state.onConfirm = dialogOptions.onConfirm ?? null;
            state.onCancel = dialogOptions.onCancel ?? null;
            state.active = true;

            eyebrow.textContent = dialogOptions.eyebrow ?? (dialogOptions.variant === 'alert' ? 'Notice' : 'Confirm Action');
            title.textContent = dialogOptions.title ?? (dialogOptions.variant === 'alert' ? 'Attention needed' : 'Please confirm');
            message.textContent = dialogOptions.message ?? 'Are you sure you want to continue?';

            cancelButton.textContent = dialogOptions.cancelLabel ?? 'Cancel';
            confirmButton.textContent = dialogOptions.confirmLabel ?? (dialogOptions.variant === 'alert' ? 'OK' : 'Confirm');
            cancelButton.classList.toggle('hidden', dialogOptions.variant === 'alert');

            setTone(dialogOptions.tone ?? (dialogOptions.variant === 'alert' ? 'info' : 'danger'));

            root.classList.remove('hidden', 'pointer-events-none');

            requestAnimationFrame(() => {
                backdrop.classList.remove('opacity-0');
                panel.classList.remove('scale-95', 'opacity-0');
                panel.classList.add('scale-100', 'opacity-100');
            });
        }

        function closeDialog() {
            if (!state.active) {
                return;
            }

            state.active = false;
            backdrop.classList.add('opacity-0');
            panel.classList.add('scale-95', 'opacity-0');
            panel.classList.remove('scale-100', 'opacity-100');

            window.setTimeout(() => {
                root.classList.add('hidden', 'pointer-events-none');
            }, 200);
        }

        function runAndClose(callback) {
            const callbackToRun = callback;
            closeDialog();
            state.onConfirm = null;
            state.onCancel = null;

            if (typeof callbackToRun === 'function') {
                callbackToRun();
            }
        }

        function showAppConfirm(options) {
            openDialog({
                ...options,
                variant: 'confirm',
            });
        }

        function showAppAlert(input, options = {}) {
            const alertOptions = typeof input === 'string'
                ? { ...options, message: input }
                : (input ?? {});

            openDialog({
                ...alertOptions,
                variant: 'alert',
                cancelLabel: alertOptions.cancelLabel ?? 'Cancel',
                confirmLabel: alertOptions.confirmLabel ?? 'OK',
                onConfirm: alertOptions.onConfirm ?? alertOptions.onClose ?? null,
            });
        }

        function bindInlineConfirmForms() {
            document.querySelectorAll('form[onsubmit*="confirm("]').forEach((form) => {
                if (!(form instanceof HTMLFormElement) || form.dataset.appConfirmBound === 'true') {
                    return;
                }

                const inlineHandler = form.getAttribute('onsubmit') ?? '';
                let extractedMessage = 'Are you sure you want to continue?';

                const originalConfirm = window.confirm;
                window.confirm = (dialogMessage) => {
                    extractedMessage = String(dialogMessage);
                    return true;
                };

                try {
                    Function(inlineHandler).call(form);
                } catch (error) {
                    console.error(error);
                } finally {
                    window.confirm = originalConfirm;
                }

                form.dataset.appConfirmBound = 'true';
                form.dataset.appConfirmMessage = extractedMessage;
                form.removeAttribute('onsubmit');

                form.addEventListener('submit', (event) => {
                    if (form.dataset.appConfirmBypass === 'true') {
                        form.dataset.appConfirmBypass = 'false';
                        return;
                    }

                    event.preventDefault();

                    showAppConfirm({
                        title: 'Confirm action',
                        message: form.dataset.appConfirmMessage ?? extractedMessage,
                        confirmLabel: 'Confirm',
                        cancelLabel: 'Cancel',
                        tone: 'danger',
                        onConfirm: () => {
                            form.dataset.appConfirmBypass = 'true';
                            HTMLFormElement.prototype.submit.call(form);
                        },
                    });
                });
            });
        }

        backdrop.addEventListener('click', () => {
            if (!state.active) {
                return;
            }

            runAndClose(state.onCancel);
        });

        cancelButton.addEventListener('click', () => runAndClose(state.onCancel));
        confirmButton.addEventListener('click', () => runAndClose(state.onConfirm));

        document.addEventListener('keydown', (event) => {
            if (!state.active) {
                return;
            }

            if (event.key === 'Escape') {
                runAndClose(state.onCancel);
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                runAndClose(state.onConfirm);
            }
        });

        window.showAppConfirm = showAppConfirm;
        window.showAppAlert = showAppAlert;
        window.alert = (dialogMessage) => {
            showAppAlert({
                title: 'Attention needed',
                message: String(dialogMessage),
                tone: 'info',
                confirmLabel: 'OK',
            });
        };

        bindInlineConfirmForms();
        document.addEventListener('DOMContentLoaded', bindInlineConfirmForms);
    })();
</script>
