<style>
    #gl-loader {
        position: fixed;
        top: 0;
        left: 0;
        width: 0;
        height: 3px;
        background: linear-gradient(90deg, #84cc16 0%, #10b981 50%, #0d9488 100%);
        z-index: 99999;
        transition: width 0.2s ease, opacity 0.3s ease;
        box-shadow: 0 0 10px rgba(16, 185, 129, 0.7), 0 0 4px rgba(132, 204, 22, 0.8);
        pointer-events: none;
    }
    #gl-loader.indeterminate {
        width: 80%;
        transition: width 1.8s cubic-bezier(0.1, 0.05, 0, 1);
    }
    #gl-loader.done {
        width: 100%;
        opacity: 0;
        transition: width 0.15s ease, opacity 0.3s ease 0.1s;
    }
    #gl-loader-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 99998;
        cursor: wait;
        pointer-events: auto;
    }
    #gl-loader-overlay.active {
        display: block;
    }
</style>
<div id="gl-loader" aria-hidden="true"></div>
<div id="gl-loader-overlay" aria-hidden="true"></div>
<script>
(function () {
    const bar = document.getElementById('gl-loader');
    const overlay = document.getElementById('gl-loader-overlay');
    let timer = null;
    let safetyTimer = null;

    function start() {
        if (!bar || !overlay) return;
        if (timer) clearTimeout(timer);
        if (safetyTimer) clearTimeout(safetyTimer);

        bar.className = '';
        bar.style.width = '0';
        overlay.classList.add('active');
        void bar.offsetWidth; // force reflow
        bar.classList.add('indeterminate');

        // Auto-safety fallback in case navigation was cancelled or blocked
        safetyTimer = setTimeout(finish, 15000);
    }

    function finish() {
        if (!bar || !overlay) return;
        if (timer) clearTimeout(timer);
        if (safetyTimer) clearTimeout(safetyTimer);

        overlay.classList.remove('active');
        bar.className = 'done';
        timer = setTimeout(() => {
            bar.className = '';
            bar.style.width = '0';
        }, 400);
    }

    // Expose helpers globally
    window.showGlobalLoader = start;
    window.hideGlobalLoader = finish;
    window.showLoader = start;
    window.hideLoader = finish;

    // 1. Link navigation click listener
    document.addEventListener('click', function (e) {
        // Ignore non-primary clicks (middle click, right click) or modifier keys (new tab)
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (e.defaultPrevented) return;

        const link = e.target.closest('a[href]');
        if (!link) return;

        const rawHref = link.getAttribute('href') || '';
        if (!rawHref || rawHref === '#' || rawHref.startsWith('#') ||
            rawHref.startsWith('javascript:') || rawHref.startsWith('tel:') ||
            rawHref.startsWith('mailto:')) {
            return;
        }

        // External domains, new window targets, or downloads
        if (link.origin !== window.location.origin) return;
        if (link.target && link.target !== '_self') return;
        if (link.hasAttribute('download')) return;

        // Custom client-side actions (modals, client-only triggers)
        if (link.hasAttribute('data-modal') ||
            link.hasAttribute('data-no-loader') ||
            link.hasAttribute('data-bs-toggle') ||
            link.hasAttribute('wire:click')) {
            return;
        }

        // Same-page hash navigation (without server request)
        if (link.pathname === window.location.pathname &&
            link.search === window.location.search &&
            link.hash) {
            return;
        }

        start();
    }, false);

    // 2. Form submission listener
    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        const form = e.target;
        if (!form || !(form instanceof HTMLFormElement)) return;

        if (form.target && form.target !== '_self') return;
        if (form.hasAttribute('data-no-loader')) return;

        // Check HTML5 constraint validation
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            return;
        }

        start();
    }, false);

    // 3. Programmatic form submit wrapper
    try {
        const originalSubmit = HTMLFormElement.prototype.submit;
        HTMLFormElement.prototype.submit = function () {
            if ((!this.target || this.target === '_self') && !this.hasAttribute('data-no-loader')) {
                start();
            }
            return originalSubmit.apply(this, arguments);
        };
    } catch (err) {
        // Ignore if prototype is sealed
    }

    // 4. Page restoration & navigation lifecycle
    window.addEventListener('pageshow', finish);
    window.addEventListener('popstate', finish);
    window.addEventListener('pagehide', finish);
    window.addEventListener('load', finish);
    document.addEventListener('DOMContentLoaded', finish);
})();
</script>
