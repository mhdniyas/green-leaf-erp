@php
    $activeTab = $activeTab ?? 'categories';
    $resolvedShopKey = !empty($shopKey) ? $shopKey : ($currentShop->slug ?: ($currentShop->shop_id ?? $currentShop->id ?? ''));

    $tabRouteMap = [
        'general' => 'admin.cashbook.settings',
        'categories' => 'admin.cashbook.settings.shop',
        'vendors' => 'admin.cashbook.settings.shop.vendors.index',
        'settlements' => 'admin.cashbook.settings.shop.settlements.index',
        'payments' => 'admin.cashbook.settings.shop.payments.index',
        'salary' => 'admin.cashbook.settings.shop.salary.index',
    ];
    $targetRoute = $tabRouteMap[$activeTab] ?? 'admin.cashbook.settings.shop';

    $switcherShops = isset($shops) && $shops->isNotEmpty()
        ? $shops
        : \App\Models\Cashbook\ShopLedgerProfile::query()
            ->where('enabled', true)
            ->whereNotNull('client_id')
            ->with(['shop', 'client'])
            ->get()
            ->filter(fn ($p) => ! $p->shop || strtolower((string) $p->shop->status) === 'active')
            ->values();

    $switcherGroups = isset($clientGroups) && $clientGroups->isNotEmpty()
        ? $clientGroups
        : $switcherShops->groupBy(fn ($p) => (string) ($p->client?->name ?? $p->shop?->client?->name ?? 'Client Shops'));
@endphp

<div class="rounded-3xl border border-slate-200/80 bg-white p-3.5 sm:p-4 shadow-xs">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <!-- Left: Breadcrumb & Shop Switcher -->
        <div class="flex items-center flex-wrap gap-2.5">
            <a href="{{ route('admin.cashbook.settings') }}" class="inline-flex items-center gap-1.5 text-xs font-black text-slate-500 hover:text-emerald-700 transition">
                <i data-lucide="store" class="w-3.5 h-3.5 text-slate-400"></i>
                <span>Shop Settings</span>
            </a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-300"></i>

            <!-- Shop Switcher Dropdown in Tabs -->
            <div class="relative inline-flex items-center">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400">
                    <i data-lucide="building-2" class="h-3.5 w-3.5 text-emerald-600"></i>
                </div>
                <select onchange="if(this.value) window.location.href = this.value"
                        class="h-8.5 rounded-xl border border-slate-200 bg-slate-50/80 hover:bg-white pl-8 pr-7 text-xs font-black text-slate-900 shadow-2xs focus:border-emerald-500 focus:bg-white focus:outline-hidden focus:ring-1 focus:ring-emerald-500 transition cursor-pointer">
                    @foreach($switcherGroups as $cName => $cShops)
                        <optgroup label="{{ $cName }}">
                            @foreach($cShops as $s)
                                @php
                                    $sKey = $s->slug ?: $s->shop_id;
                                    $targetUrl = $targetRoute === 'admin.cashbook.settings'
                                        ? route('admin.cashbook.settings.shop', $sKey)
                                        : route($targetRoute, $sKey);
                                    $isSelected = ($s->shop_id == ($currentShop->shop_id ?? null)) || ($s->id == ($currentShop->id ?? null)) || ($s->slug === $resolvedShopKey);
                                @endphp
                                <option value="{{ $targetUrl }}" {{ $isSelected ? 'selected' : '' }}>
                                    {{ $s->name }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <span class="rounded-lg bg-slate-100 px-2 py-0.5 font-mono text-[10px] font-bold text-slate-500">
                {{ $currentShop->code ?: 'SHOP-'.($currentShop->shop_id ?? $currentShop->id) }}
            </span>
            @if($currentShop->client?->name ?? false)
                <span class="rounded-lg bg-emerald-50 border border-emerald-200/60 px-2 py-0.5 text-[10px] font-black text-emerald-800">
                    {{ $currentShop->client->name }}
                </span>
            @endif
        </div>

        <!-- Right: Modern Navigation Tabs -->
        <div class="inline-flex items-center gap-1 rounded-2xl bg-slate-100/90 p-1 border border-slate-200/80 overflow-x-auto max-w-full">
            <a href="{{ route('admin.cashbook.settings') }}"
               class="px-3 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap {{ $activeTab === 'general' ? 'bg-white text-slate-950 shadow-2xs font-black' : 'text-slate-600 hover:text-slate-950' }}">
                General
            </a>
            <a href="{{ route('admin.cashbook.settings.shop', $resolvedShopKey) }}"
               class="px-3 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap {{ $activeTab === 'categories' ? 'bg-white text-slate-950 shadow-2xs font-black' : 'text-slate-600 hover:text-slate-950' }}">
                Categories
            </a>
            <a href="{{ route('admin.cashbook.settings.shop.vendors.index', $resolvedShopKey) }}"
               class="px-3 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap {{ $activeTab === 'vendors' ? 'bg-white text-slate-950 shadow-2xs font-black' : 'text-slate-600 hover:text-slate-950' }}">
                Vendors
            </a>
            <a href="{{ route('admin.cashbook.settings.shop.settlements.index', $resolvedShopKey) }}"
               class="px-3 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap {{ $activeTab === 'settlements' ? 'bg-white text-slate-950 shadow-2xs font-black' : 'text-slate-600 hover:text-slate-950' }}">
                Settlements
            </a>
            <a href="{{ route('admin.cashbook.settings.shop.payments.index', $resolvedShopKey) }}"
               class="px-3 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap {{ $activeTab === 'payments' ? 'bg-white text-slate-950 shadow-2xs font-black' : 'text-slate-600 hover:text-slate-950' }}">
                Payments
            </a>
            <a href="{{ route('admin.cashbook.settings.shop.salary.index', $resolvedShopKey) }}"
               class="px-3 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap {{ $activeTab === 'salary' ? 'bg-white text-slate-950 shadow-2xs font-black' : 'text-slate-600 hover:text-slate-950' }}">
                Salary
            </a>
        </div>
    </div>
</div>

