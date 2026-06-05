<div class="flex flex-wrap gap-2">
    @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.index'), 'label' => 'Overview', 'classes' => request()->routeIs('shop-owner.orders.index') ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-800'])
    @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.create'), 'label' => 'Create Tomorrow Order', 'classes' => request()->routeIs('shop-owner.orders.create') ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-800'])
    @include('shop-owner.components.action-button', ['href' => route('shop-owner.orders.history'), 'label' => 'History', 'classes' => request()->routeIs('shop-owner.orders.history') ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-800'])
</div>
