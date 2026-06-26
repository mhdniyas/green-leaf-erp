<div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
    @include('shop-owner.components.action-button', [
        'href' => route('shop-owner.accounting.index'),
        'label' => 'Daily',
        'classes' => (request()->routeIs('shop-owner.accounting.index') ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-800') . ' justify-center w-full sm:w-auto text-center'
    ])
    @include('shop-owner.components.action-button', [
        'href' => route('shop-owner.accounting.history'),
        'label' => 'History',
        'classes' => (request()->routeIs('shop-owner.accounting.history') ? 'bg-slate-950 text-white' : 'border border-slate-200 bg-white text-slate-800') . ' justify-center w-full sm:w-auto text-center'
    ])
</div>
