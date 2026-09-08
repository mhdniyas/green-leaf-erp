@php
    $activeTab = $activeTab ?? 'categories';
    $shopKey = $shopKey ?? ($currentShop->slug ?: $currentShop->shop_id);
@endphp
<div class="rounded-2xl border border-slate-200 bg-white p-3 sm:p-4 shadow-xs">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-2">
            <span class="text-[11px] font-black uppercase tracking-wider text-slate-400">Cashbook Settings</span>
            <span class="text-slate-300 font-bold">/</span>
            <span class="text-xs sm:text-sm font-extrabold text-slate-900">{{ $currentShop->name }}</span>
            <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[10px] font-bold text-slate-500">{{ $currentShop->code ?: 'SHOP-'.$currentShop->shop_id }}</span>
        </div>
        <div class="inline-flex items-center gap-1 rounded-xl bg-slate-100/90 p-1 border border-slate-200/80 overflow-x-auto max-w-full">
            <a href="{{ route('admin.cashbook.settings') }}"
               class="px-3 py-1.5 rounded-lg text-xs font-bold transition whitespace-nowrap {{ $activeTab === 'general' ? 'bg-white text-slate-950 shadow-2xs font-extrabold' : 'text-slate-600 hover:text-slate-950' }}">
                General
            </a>
            <a href="{{ route('admin.cashbook.settings.shop', $shopKey) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-bold transition whitespace-nowrap {{ $activeTab === 'categories' ? 'bg-white text-slate-950 shadow-2xs font-extrabold' : 'text-slate-600 hover:text-slate-950' }}">
                Categories
            </a>
            <a href="{{ route('admin.cashbook.settings.shop.settlements.index', $shopKey) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-bold transition whitespace-nowrap {{ $activeTab === 'settlements' ? 'bg-white text-slate-950 shadow-2xs font-extrabold' : 'text-slate-600 hover:text-slate-950' }}">
                Settlements
            </a>
            <a href="{{ route('admin.cashbook.settings.shop.payments.index', $shopKey) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-bold transition whitespace-nowrap {{ $activeTab === 'payments' ? 'bg-white text-slate-950 shadow-2xs font-extrabold' : 'text-slate-600 hover:text-slate-950' }}">
                Payments
            </a>
        </div>
    </div>
</div>
