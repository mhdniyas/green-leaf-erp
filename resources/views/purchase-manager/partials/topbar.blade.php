<header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="flex items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Green Leaf ERP</p>
            <p class="mt-1 text-sm font-semibold text-slate-700">{{ auth()->user()->name }}</p>
        </div>

        <div class="text-right">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Business Date</p>
            <p class="mt-1 text-sm font-semibold text-slate-700">{{ now()->format('d F Y') }}</p>
        </div>
    </div>
</header>
