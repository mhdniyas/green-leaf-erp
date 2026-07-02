@props([
    'bottomClass' => 'bottom-24 lg:bottom-6',
])

<div class="fixed right-4 z-[60] flex flex-col gap-2 {{ $bottomClass }}">
    <button type="button"
            onclick="jumpAppPageTop()"
            class="flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white/95 text-slate-700 shadow-lg backdrop-blur-sm transition hover:bg-slate-50 cursor-pointer"
            title="Go to top"
            aria-label="Go to top">
        <span class="sr-only">Go to top</span>
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75 12 8.25l7.5 7.5" />
        </svg>
    </button>
    <button type="button"
            onclick="jumpAppPageBottom()"
            class="flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-white/95 text-slate-700 shadow-lg backdrop-blur-sm transition hover:bg-slate-50 cursor-pointer"
            title="Go to bottom"
            aria-label="Go to bottom">
        <span class="sr-only">Go to bottom</span>
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25 12 15.75l-7.5-7.5" />
        </svg>
    </button>
</div>

<script>
    if (typeof window.jumpAppPageTop !== 'function') {
        window.jumpAppPageTop = function jumpAppPageTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };
    }

    if (typeof window.jumpAppPageBottom !== 'function') {
        window.jumpAppPageBottom = function jumpAppPageBottom() {
            window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });
        };
    }
</script>
