<header class="fixed top-4 inset-x-4 z-30 mx-auto max-w-md rounded-[1.5rem] border border-slate-100 bg-white/95 px-4 py-2.5 shadow-[0_8px_30px_rgb(0,0,0,0.08)] backdrop-blur-md lg:sticky lg:top-0 lg:left-0 lg:right-0 lg:inset-x-0 lg:max-w-none lg:mx-0 lg:rounded-none lg:border-0 lg:border-b lg:border-slate-200 lg:bg-white/95 lg:px-8 lg:py-4 lg:shadow-none lg:backdrop-blur">
    <div class="flex w-full items-center justify-between">
        <div class="min-w-0">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Green Leaf ERP</p>
            <p class="mt-0.5 max-w-[150px] truncate text-xs font-bold text-slate-800 sm:max-w-none sm:text-sm" title="{{ auth()->user()->name }}">{{ auth()->user()->name }}</p>
        </div>

        <div class="shrink-0 text-right">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Business Date</p>
            <p class="mt-0.5 text-xs font-bold text-slate-800 sm:text-sm">{{ now()->format('d F Y') }}</p>
        </div>
    </div>
</header>
