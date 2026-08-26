@php($cashbookSidebarShops = $shops ?? collect())

<!-- Mobile Overlay Backdrop -->
<div id="sidebar-backdrop" onclick="toggleMobileSidebar()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-30 hidden md:hidden transition-opacity"></div>

<!-- Sidebar Drawer -->
<aside id="main-sidebar" class="w-64 bg-white text-slate-700 flex flex-col justify-between fixed inset-y-0 left-0 z-40 border-r border-slate-200/90 shadow-sm transition-[width,transform] duration-300 ease-in-out -translate-x-full md:translate-x-0 lg:w-64">
    <div class="p-4 sm:p-5 space-y-5 sm:space-y-6 overflow-y-auto custom-scrollbar">

        <!-- Brand Header -->
        <div class="pb-4 border-b border-slate-100">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 sm:h-10 sm:w-10 rounded-xl bg-emerald-600 flex items-center justify-center shadow-md text-white flex-shrink-0">
                        <i data-lucide="leaf" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span data-cashbook-sidebar-label class="font-extrabold text-sm sm:text-base tracking-tight text-slate-900">{{ config('greenleaf.name', 'Green Leaf') }}</span>
                            <span data-cashbook-sidebar-label class="px-1.5 py-0.5 text-[9px] font-extrabold tracking-wider uppercase rounded bg-emerald-50 text-emerald-800 border border-emerald-200">Cashbook</span>
                        </div>
                        <p data-cashbook-sidebar-label class="text-[11px] text-slate-500 font-medium">Ledger &amp; Billing System</p>
                    </div>
                </div>
                <button id="cashbook-sidebar-collapse" type="button" class="hidden lg:inline-flex p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition" aria-label="Collapse sidebar" title="Collapse sidebar">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
                <!-- Mobile Close Button -->
                <button onclick="toggleMobileSidebar()" class="md:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <!-- Hierarchy breadcrumb -->
            <div class="flex items-center gap-1 text-[10px] text-slate-500 font-medium px-1 flex-wrap">
                <span data-cashbook-sidebar-label class="font-bold text-emerald-700">{{ config('greenleaf.name', 'Green Leaf') }}</span>
                <i data-lucide="chevron-right" class="w-3 h-3 text-slate-300"></i>
                <span data-cashbook-sidebar-label class="text-slate-400">{{ $cashbookSidebarShops->count() }} shops</span>
            </div>
        </div>

        @if(request()->routeIs('admin.cashbook.shop.show'))
            <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200/80 space-y-1.5">
                <span data-cashbook-sidebar-label class="text-[10px] font-extrabold uppercase text-slate-500 tracking-wider flex items-center gap-1">
                    <i data-lucide="store" class="w-3 h-3 text-slate-700"></i> Active Shop Context
                </span>
                <select
                    id="active-shop-selector"
                    onchange="window.location.href='/admin/cashbook/shops/' + this.options[this.selectedIndex].getAttribute('data-slug')"
                    class="w-full bg-white text-xs font-bold text-slate-900 px-2.5 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-600 cursor-pointer shadow-sm"
                >
                    @foreach($cashbookSidebarShops as $s)
                        <option value="{{ $s->shop_id }}" data-slug="{{ $s->slug ?: $s->shop_id }}"
                            {{ isset($currentShop) && $currentShop->shop_id == $s->shop_id ? 'selected' : '' }}>
                            {{ $s->name ? $s->name . ' (' . $s->code . ')' : 'Shop #' . $s->shop_id }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <!-- Navigation Sections -->
        <nav class="space-y-5">

            <!-- ← Back to main ERP admin -->
            <a href="{{ route('admin.overview') }}" class="sidebar-link text-slate-400 hover:text-slate-700">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Back to Admin</span>
            </a>

            <!-- FINANCE -->
            <div class="space-y-1">
                <span data-cashbook-sidebar-label class="px-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">FINANCE</span>
                <a href="{{ route('admin.cashbook.finance') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.finance') ? 'active-sidebar' : '' }}">
                    <i data-lucide="badge-dollar-sign" class="w-4 h-4"></i>
                    <span>Company Finance</span>
                </a>
                <a href="{{ route('admin.cashbook.finance.cheque-submission') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.finance.cheque-submission') ? 'active-sidebar' : '' }}">
                    <i data-lucide="file-check-2" class="w-4 h-4"></i>
                    <span>Cheque Bank Submit</span>
                </a>
                <a href="{{ route('admin.cashbook.finance.reconciliation') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.finance.reconciliation') ? 'active-sidebar' : '' }}">
                    <i data-lucide="git-compare-arrows" class="w-4 h-4"></i>
                    <span>Reconciliation</span>
                </a>
                <a href="{{ route('admin.cashbook.finance.journal') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.finance.journal*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="book-open-check" class="w-4 h-4"></i>
                    <span>All Transactions</span>
                </a>
                <a href="{{ route('admin.cashbook.finance.vendor-credit') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.finance.vendor-credit*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="truck" class="w-4 h-4"></i>
                    <span>Vendor Credit</span>
                </a>
                <a href="{{ route('admin.cashbook.finance.purchase') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.finance.purchase*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="shopping-basket" class="w-4 h-4"></i>
                    <span>Purchase</span>
                </a>
                <a href="{{ route('admin.cashbook.finance.direct-sales') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.finance.direct-sales*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="circle-dollar-sign" class="w-4 h-4"></i>
                    <span>Direct Company Sales</span>
                </a>
                <a href="{{ route('admin.cashbook.finance.gl-bills') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.finance.gl-bills*') || request()->routeIs('admin.cashbook.reports.gl-bills*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="receipt" class="w-4 h-4"></i>
                    <span>GL Bills</span>
                </a>
                <a href="{{ route('admin.cashbook.bank-accounts.create') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.bank-accounts.*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="landmark" class="w-4 h-4"></i>
                    <span>Bank &amp; Cash In Hand</span>
                </a>
            </div>

            <!-- SHOP -->
            <div class="space-y-1">
                <span data-cashbook-sidebar-label class="px-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">SHOP</span>
                <a href="{{ route('admin.cashbook.all-shops') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.all-shops') || request()->routeIs('admin.cashbook.index') ? 'active-sidebar' : '' }}">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i>
                    <span>All Shops Overview</span>
                </a>
                <a href="{{ route('admin.cashbook.shop.show', isset($currentShop) ? ($currentShop->slug ?: $currentShop->shop_id) : ($cashbookSidebarShops->first()?->slug ?? 1)) }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.shop.show') ? 'active-sidebar' : '' }}">
                    <i data-lucide="store" class="w-4 h-4"></i>
                    <span>Single Shop Ledger</span>
                </a>
                <a href="{{ route('admin.cashbook.income-expenses') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.income-expenses') ? 'active-sidebar' : '' }}">
                    <i data-lucide="receipt" class="w-4 h-4"></i>
                    <span>Income &amp; Expenses</span>
                </a>
                <a href="{{ route('admin.cashbook.post-entry') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.post-entry') || request()->routeIs('admin.cashbook.post-entry.shop') ? 'active-sidebar' : '' }}">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    <span>Post Entry Simulator</span>
                </a>
            </div>

            <!-- REPORTS -->
            <div class="space-y-1">
                <span data-cashbook-sidebar-label class="px-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">REPORTS</span>
                <a href="{{ route('admin.cashbook.reports') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.reports') ? 'active-sidebar' : '' }}">
                    <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                    <span>Main Financial Reports</span>
                </a>
                <a href="{{ route('admin.cashbook.inventory') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.inventory*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="boxes" class="w-4 h-4"></i>
                    <span>Inventory</span>
                </a>
                <a href="{{ route('admin.cashbook.bill-changes') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.bill-changes*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="receipt-text" class="w-4 h-4"></i>
                    <span>Bill Changes</span>
                </a>
                <a href="{{ route('admin.cashbook.payables') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.payables') ? 'active-sidebar' : '' }}">
                    <i data-lucide="arrow-down-left" class="w-4 h-4"></i>
                    <span>Shop Payables Report</span>
                </a>
            </div>

            <!-- MOBILE CASHBOOK -->
            <div class="space-y-1">
                <span data-cashbook-sidebar-label class="px-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">MOBILE CASHBOOK</span>
                <a href="{{ route('admin.cashbook.reports.hub') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.reports.hub') || request()->routeIs('admin.cashbook.reports.shop') || request()->routeIs('admin.cashbook.reports.mobile-ledger') ? 'active-sidebar' : '' }}">
                    <i data-lucide="layers" class="w-4 h-4"></i>
                    <span>Shop Cards Hub</span>
                </a>
                <a href="{{ route('admin.cashbook.reports.products') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.reports.products') || request()->routeIs('admin.cashbook.products') ? 'active-sidebar' : '' }}">
                    <i data-lucide="store" class="w-4 h-4"></i>
                    <span>Products Marketplace</span>
                </a>
                <a href="{{ route('admin.cashbook.reports.charts') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.reports.charts') ? 'active-sidebar' : '' }}">
                    <i data-lucide="pie-chart" class="w-4 h-4"></i>
                    <span>Category Charts</span>
                </a>
                <a href="{{ route('admin.cashbook.reports.analytics') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.reports.analytics') ? 'active-sidebar' : '' }}">
                    <i data-lucide="trending-up" class="w-4 h-4"></i>
                    <span>Profit Analytics</span>
                </a>
            </div>

            <!-- SETTINGS -->
            <div class="space-y-1">
                <span data-cashbook-sidebar-label class="px-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">SETTINGS</span>
                <a href="{{ route('admin.cashbook.settings') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.settings') || request()->routeIs('admin.cashbook.settings.shop') ? 'active-sidebar' : '' }}">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                    <span>Settings</span>
                </a>
            </div>

        </nav>

    </div>

    <!-- Sidebar Footer -->
    <div class="p-4 border-t border-slate-100 bg-slate-50/80 space-y-2">
        <div class="flex items-center justify-between text-xs text-slate-500">
            <span data-cashbook-sidebar-label class="flex items-center gap-1.5 font-bold text-slate-700">
                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span> Engine Active
            </span>
            <span data-cashbook-sidebar-label class="font-mono text-[10px] text-slate-400">v1.0.0</span>
        </div>
        <div data-cashbook-sidebar-label class="flex items-center gap-1.5 text-[10px] text-slate-400 font-medium">
            <i data-lucide="leaf" class="w-3 h-3 text-emerald-500"></i>
            {{ config('greenleaf.name', 'Green Leaf') }} · Cashbook
        </div>
    </div>
</aside>
