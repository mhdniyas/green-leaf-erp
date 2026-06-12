<div class="mb-4 sm:mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
    <div>
        <p class="hidden sm:block text-xs font-black uppercase tracking-[0.2em] text-emerald-700">@yield('eyebrow', 'Shop Owner')</p>
        <h1 class="mt-0.5 sm:mt-1 text-2xl sm:text-3xl font-black tracking-tight text-slate-950">@yield('page_title')</h1>
        @hasSection('page_description')
            <p class="hidden sm:block mt-1 sm:mt-2 max-w-3xl text-xs sm:text-sm text-slate-600">@yield('page_description')</p>
        @endif
    </div>

    @hasSection('page_actions')
        <div class="flex flex-wrap gap-2">
            @yield('page_actions')
        </div>
    @endif
</div>
