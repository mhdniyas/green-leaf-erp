<div class="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
    <div>
        <p class="text-xs font-black uppercase tracking-[0.2em] text-cyan-700">@yield('eyebrow', 'Purchase Manager')</p>
        <h1 class="mt-1 text-3xl font-black tracking-tight text-slate-950">@yield('page_title')</h1>
        @hasSection('page_description')
            <p class="mt-2 max-w-3xl text-sm text-slate-600">@yield('page_description')</p>
        @endif
    </div>

    @hasSection('page_actions')
        <div class="flex flex-wrap gap-2">
            @yield('page_actions')
        </div>
    @endif
</div>
