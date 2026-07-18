<div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
    @include('shop-owner.components.action-button', [
        'href' => route('shop-owner.accounting.index', ['tab' => 'bills']),
        'label' => 'Bills',
        'classes' => (request()->routeIs('shop-owner.accounting.index') && ($tab ?? 'bills') === 'bills' ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-800') . ' justify-center w-full sm:w-auto text-center'
    ])
    @if ($shop->isOwnedAccountingEnabled())
        @include('shop-owner.components.action-button', [
            'href' => route('shop-owner.accounting.index', ['tab' => 'cashbook']),
            'label' => 'Cashbook',
            'classes' => (request()->routeIs('shop-owner.accounting.index') && ($tab ?? 'bills') === 'cashbook' ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-800') . ' justify-center w-full sm:w-auto text-center'
        ])
    @endif
    @if ($shop->isOwnedAccountingEnabled())
        @include('shop-owner.components.action-button', [
            'href' => route('shop-owner.accounting.daily-report', ['month' => request('month', today()->format('Y-m'))]),
            'label' => 'Daily Report',
            'classes' => (request()->routeIs('shop-owner.accounting.daily-report') ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-800') . ' justify-center w-full sm:w-auto text-center'
        ])
    @endif
    @include('shop-owner.components.action-button', [
        'href' => route('shop-owner.accounting.history', ['tab' => $tab ?? 'bills']),
        'label' => 'History',
        'classes' => (request()->routeIs('shop-owner.accounting.history') ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-800') . ' justify-center w-full sm:w-auto text-center'
    ])
</div>
