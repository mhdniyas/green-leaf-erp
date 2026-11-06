@props([
    'activeTab' => 'invoices',
])

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1 border-b border-slate-100 pb-3">
    <div class="flex items-center gap-2 sm:gap-3 min-w-0">
        <div class="shrink-0">
            <div class="flex items-center gap-2">
                <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900">Shops Reports</h1>
                <span class="rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider">
                    Audited
                </span>
            </div>
            <p class="text-xs font-bold text-slate-500 mt-0.5">
                Cashbook &bull; Reports &bull; <span class="text-slate-700 font-bold">Shops</span>
            </p>
        </div>

        <!-- 2-Option Tab Switcher (Invoices, Credit Notes) -->
        <div class="inline-flex items-center gap-0.5 rounded-2xl bg-slate-200/70 p-1 shrink-0 ml-2">
            <a href="{{ route('admin.cashbook.reports.shops.invoices') }}"
                class="rounded-xl px-3 sm:px-4 py-1 text-xs font-extrabold transition-all {{ $activeTab === 'invoices' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                <span class="flex items-center gap-1.5">
                    <i data-lucide="receipt" class="w-3.5 h-3.5 {{ $activeTab === 'invoices' ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                    <span>Invoices</span>
                </span>
            </a>
            <a href="{{ route('admin.cashbook.reports.shops.credit-notes') }}"
                class="rounded-xl px-3 sm:px-4 py-1 text-xs font-extrabold transition-all {{ $activeTab === 'credit-notes' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                <span class="flex items-center gap-1.5">
                    <i data-lucide="file-minus" class="w-3.5 h-3.5 {{ $activeTab === 'credit-notes' ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                    <span>Credit Notes</span>
                </span>
            </a>
        </div>
    </div>

    <div class="flex items-center gap-2">
        <a href="{{ route('admin.cashbook.reports') }}" class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-2xs">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5 text-slate-400"></i>
            <span>All Cashbook Reports</span>
        </a>
    </div>
</div>
