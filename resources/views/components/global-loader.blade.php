<style>
    #gl-loader {
        position: fixed;
        top: 0;
        left: 0;
        width: 0;
        height: 3px;
        background: #84cc16;
        z-index: 99999;
        transition: width 0.2s ease;
        box-shadow: 0 0 8px #84cc16;
    }
    #gl-loader.indeterminate {
        width: 70%;
        transition: width 2s cubic-bezier(0.1, 0.05, 0, 1);
    }
    #gl-loader.done {
        width: 100%;
        opacity: 0;
        transition: width 0.15s ease, opacity 0.3s ease 0.15s;
    }
    #gl-loader-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 99998;
        cursor: wait;
    }
    #gl-loader-overlay.active { display: block; }
</style>
<div id="gl-loader"></div>
<div id="gl-loader-overlay"></div>
<script>
(function () {
    const bar = document.getElementById('gl-loader');
    const overlay = document.getElementById('gl-loader-overlay');
    let timer = null;

    function start() {
        if (timer) clearTimeout(timer);
        bar.className = '';
        bar.style.width = '0';
        overlay.classList.add('active');
        bar.getBoundingClientRect(); // force reflow
        bar.classList.add('indeterminate');
    }

    function finish() {
        if (timer) clearTimeout(timer);
        overlay.classList.remove('active');
        bar.className = 'done';
        timer = setTimeout(() => {
            bar.className = '';
            bar.style.width = '0';
        }, 500);
    }

    document.addEventListener('click', function (e) {
        const link = e.target.closest('a[href]');
        if (!link) return;
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript') ||
            link.hostname !== location.hostname ||
            link.target === '_blank' || link.hasAttribute('download')) return;
        if (link.hasAttribute('data-modal') || link.hasAttribute('wire:click')) return;
        start();
    });

    window.addEventListener('pageshow', finish);
    window.addEventListener('load', finish);
})();
</script>
