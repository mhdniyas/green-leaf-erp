@hasSection('page_back_url')
    <div class="mb-3 flex items-center justify-between gap-3 sm:mb-6">
        <div class="flex min-w-0 items-center gap-2">
            <a href="@yield('page_back_url')" class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-black text-slate-800 shadow-sm transition hover:bg-slate-50 sm:h-10 sm:px-3">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m15 18-6-6 6-6" />
                </svg>
                <span>@yield('page_back_label', 'Back')</span>
            </a>
            <div class="min-w-0">
                <p class="hidden text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700 sm:block">@yield('eyebrow', 'Shop Incharge')</p>
                <h1 class="truncate text-base font-black tracking-tight text-slate-950 sm:mt-0.5 sm:text-3xl">@yield('page_title')</h1>
                @hasSection('page_description')
                    <p class="truncate text-[11px] font-semibold text-slate-500 sm:mt-1 sm:max-w-3xl sm:text-sm">@yield('page_description')</p>
                @endif
            </div>
        </div>

        @hasSection('page_actions')
            <div class="hidden flex-wrap gap-2 sm:flex">
                @yield('page_actions')
            </div>
        @endif
    </div>
@else
    <div class="mb-4 flex flex-col gap-3 sm:mb-6 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="hidden text-xs font-black uppercase tracking-[0.2em] text-emerald-700 sm:block">@yield('eyebrow', 'Shop Incharge')</p>
            <h1 class="mt-0.5 text-2xl font-black tracking-tight text-slate-950 sm:mt-1 sm:text-3xl">@yield('page_title')</h1>
            @hasSection('page_description')
                <p class="mt-1 hidden max-w-3xl text-xs text-slate-600 sm:mt-2 sm:block sm:text-sm">@yield('page_description')</p>
            @endif
        </div>

        @hasSection('page_actions')
            <div class="flex flex-wrap gap-2">
                @yield('page_actions')
            </div>
        @endif
    </div>
@endif
