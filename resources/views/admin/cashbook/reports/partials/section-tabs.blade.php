<nav class="sticky top-[4.5rem] z-20 -mx-3 overflow-x-auto border-y border-slate-200 bg-slate-50/95 px-3 py-2 backdrop-blur sm:static sm:mx-0 sm:rounded-3xl sm:border sm:bg-white sm:p-2">
    <div class="flex min-w-max gap-2">
        @foreach([
            ['href' => '#executive-summary', 'label' => 'Summary'],
            ['href' => '#client-summary', 'label' => 'Clients'],
            ['href' => '#bank-accounts', 'label' => 'Accounts'],
            ['href' => '#report-matrix', 'label' => 'Shops'],
            ['href' => '#report-export', 'label' => 'Export'],
        ] as $tab)
            <a href="{{ $tab['href'] }}" class="inline-flex min-h-11 items-center rounded-2xl border border-slate-200 bg-white px-4 text-xs font-black uppercase tracking-[0.12em] text-slate-700 shadow-sm hover:border-slate-300 hover:bg-slate-50">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</nav>
