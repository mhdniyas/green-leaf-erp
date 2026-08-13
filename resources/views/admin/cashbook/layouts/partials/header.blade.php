<header class="sticky top-0 z-30 w-full bg-white/90 border-b border-slate-200/80 backdrop-blur-md px-4 sm:px-6 py-3 flex items-center justify-between shadow-sm flex-wrap gap-2">
    <div class="flex items-center gap-3">
        <!-- Mobile Menu Toggle Button -->
        <button onclick="toggleMobileSidebar()" class="md:hidden p-2 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>
        <div>
            <h1 class="text-base sm:text-lg font-extrabold text-slate-900 flex items-center gap-2">
                @yield('header_title', 'Cashbook')
            </h1>
            <p class="text-[11px] sm:text-xs text-slate-500 font-medium">@yield('header_subtitle', 'Real-time multi-shop ledger &amp; operations.')</p>
        </div>
    </div>

    <div class="flex items-center gap-2 sm:gap-3 ml-auto">
        <div class="flex items-center gap-2 bg-slate-100 p-1.5 rounded-xl border border-slate-200">
            <i data-lucide="calendar" class="w-4 h-4 text-slate-500 ml-1"></i>
            <input
                type="date"
                id="global-date-input"
                value="{{ request('business_date', today()->toDateString()) }}"
                onchange="if(typeof syncGlobalDate === 'function') syncGlobalDate(this.value);"
                class="bg-transparent text-xs font-mono font-bold text-slate-800 border-none focus:outline-none cursor-pointer"
            >
        </div>

        @yield('header_actions')
    </div>
</header>
