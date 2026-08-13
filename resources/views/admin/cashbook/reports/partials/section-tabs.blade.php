<nav class="sticky top-[4.5rem] z-20 -mx-3 overflow-x-auto border-y border-slate-200 bg-slate-50/95 px-3 py-2 backdrop-blur sm:static sm:mx-0 sm:rounded-3xl sm:border sm:bg-white sm:p-2">
    <div class="flex min-w-max gap-2" id="report-tabs-header">
        @foreach([
            ['key' => 'summary', 'label' => 'Executive Summary', 'icon' => 'pie-chart'],
            ['key' => 'clients', 'label' => 'Client Reconciliation', 'icon' => 'users'],
            ['key' => 'bills', 'label' => 'Bills & Ageing', 'icon' => 'receipt'],
            ['key' => 'shops', 'label' => 'Shop Matrix', 'icon' => 'store'],
            ['key' => 'accounts', 'label' => 'Bank Accounts', 'icon' => 'building-2'],
            ['key' => 'all', 'label' => 'All Sections', 'icon' => 'layers'],
        ] as $tab)
            <button type="button" 
                    data-tab-button="{{ $tab['key'] }}"
                    onclick="switchReportTab('{{ $tab['key'] }}')"
                    class="inline-flex min-h-11 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 text-xs font-black uppercase tracking-[0.12em] text-slate-700 shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50">
                <i data-lucide="{{ $tab['icon'] }}" class="h-4 w-4"></i>
                <span>{{ $tab['label'] }}</span>
            </button>
        @endforeach
    </div>
</nav>
