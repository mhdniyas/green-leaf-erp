<header class="fixed top-4 inset-x-4 z-30 mx-auto max-w-md bg-white/95 backdrop-blur-md shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-slate-100 rounded-[1.5rem] px-4 py-2.5 flex items-center justify-between lg:sticky lg:top-0 lg:left-0 lg:right-0 lg:inset-x-0 lg:max-w-none lg:mx-0 lg:rounded-none lg:border-0 lg:border-b lg:border-slate-200 lg:bg-white/95 lg:backdrop-blur lg:shadow-none lg:px-8 lg:py-4">
    <div class="flex items-center justify-between w-full">
        <div class="min-w-0">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Green Leaf ERP</p>
            <p class="mt-0.5 text-xs sm:text-sm font-bold text-slate-800 truncate max-w-[150px] sm:max-w-none" title="{{ auth()->user()->shop?->name ?? 'Shop Owner' }}">
                {{ auth()->user()->shop?->name ?? 'Shop Owner' }}
            </p>
        </div>

        <div class="text-right shrink-0">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Business Date</p>
            <p class="mt-0.5 text-xs sm:text-sm font-bold text-slate-800">
                {{ now()->format('d F Y') }}
            </p>
        </div>
    </div>
</header>
