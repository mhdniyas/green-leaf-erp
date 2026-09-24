@php
    $cashbookSidebarShops = $shops ?? collect();

    $isPurchaseActive = (request()->routeIs('admin.cashbook.finance.purchase*')
        || request()->routeIs('admin.cashbook.finance.purchasers*')
        || request()->routeIs('admin.cashbook.purchaser-business-days.*'))
        && ! request()->routeIs('admin.cashbook.finance.purchase.monthly-summary*');

    $isReportsActive = request()->routeIs('admin.cashbook.finance.purchase.reports*')
        || request()->routeIs('admin.cashbook.finance.purchase.purchaser-expenses*');

    $purchaseSidebarItem = [
        'label' => 'Purchase',
        'href' => route('admin.cashbook.finance.purchase'),
        'active' => $isPurchaseActive,
        'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>',
        'children' => [
            [
                'label' => 'Dashboard',
                'href' => route('admin.cashbook.finance.purchase'),
                'active' => request()->routeIs('admin.cashbook.finance.purchase') && !request()->routeIs('admin.cashbook.finance.purchase.*'),
            ],
            [
                'label' => 'Reports',
                'href' => route('admin.cashbook.finance.purchase.reports'),
                'active' => $isReportsActive,
                'children' => [
                    [
                        'label' => 'Credit Purchases',
                        'href' => route('admin.cashbook.finance.purchase.reports.credit-purchases'),
                        'active' => request()->routeIs('admin.cashbook.finance.purchase.reports.credit-purchases'),
                    ],
                    [
                        'label' => 'Daily Purchases',
                        'href' => route('admin.cashbook.finance.purchase.reports.daily'),
                        'active' => request()->routeIs('admin.cashbook.finance.purchase.reports.daily'),
                    ],
                    [
                        'label' => 'Purchaser Expenses',
                        'href' => route('admin.cashbook.finance.purchase.purchaser-expenses'),
                        'active' => request()->routeIs('admin.cashbook.finance.purchase.purchaser-expenses*'),
                    ],
                    [
                        'label' => 'Purchaser Overview',
                        'href' => route('admin.cashbook.finance.purchase.reports.purchasers'),
                        'active' => request()->routeIs('admin.cashbook.finance.purchase.reports.purchasers'),
                    ],
                    [
                        'label' => 'Price Report',
                        'href' => route('admin.cashbook.finance.purchase.reports.prices'),
                        'active' => request()->routeIs('admin.cashbook.finance.purchase.reports.prices*'),
                    ],
                    [
                        'label' => 'Changed Items',
                        'href' => route('admin.cashbook.finance.purchase.reports.changed-items'),
                        'active' => request()->routeIs('admin.cashbook.finance.purchase.reports.changed-items*'),
                    ],
                    [
                        'label' => 'Purchaser Prices',
                        'href' => route('admin.cashbook.finance.purchase.reports.purchaser-prices'),
                        'active' => request()->routeIs('admin.cashbook.finance.purchase.reports.purchaser-prices*'),
                    ],
                    [
                        'label' => 'Product Allotments',
                        'href' => route('admin.cashbook.finance.purchase.product-allotments.index'),
                        'active' => request()->routeIs('admin.cashbook.finance.purchase.product-allotments.*'),
                    ],
                    [
                        'label' => 'Purchaser Business Days',
                        'href' => route('admin.cashbook.purchaser-business-days.index'),
                        'active' => request()->routeIs('admin.cashbook.purchaser-business-days.*'),
                    ],
                ],
            ],
            [
                'label' => 'Purchasers',
                'href' => route('admin.cashbook.finance.purchase.purchasers'),
                'active' => (request()->routeIs('admin.cashbook.finance.purchase.purchasers*') || request()->routeIs('admin.cashbook.finance.purchasers*')) && !request()->routeIs('admin.cashbook.finance.purchase.reports*'),
            ],
            [
                'label' => 'Vendors',
                'href' => route('admin.cashbook.finance.purchase.vendors'),
                'active' => request()->routeIs('admin.cashbook.finance.purchase.vendors*'),
            ],
            [
                'label' => 'Invoices',
                'href' => route('admin.cashbook.finance.purchase.invoices'),
                'active' => request()->routeIs('admin.cashbook.finance.purchase.invoices*'),
            ],
            [
                'label' => 'Categories',
                'href' => route('admin.cashbook.finance.purchase.categories'),
                'active' => request()->routeIs('admin.cashbook.finance.purchase.categories*'),
            ],
        ],
    ];

    $isMonthlyReportsActive = request()->routeIs('admin.cashbook.monthly-report.*')
        || request()->routeIs('admin.cashbook.monthly-closing-summary*')
        || request()->routeIs('admin.cashbook.finance.purchase.monthly-summary*');

    $dynamicSectionFilters = \App\Models\PurchaseProductFilter::where('is_active', true)->orderBy('id')->get();

    $sectionReportSubChildren = [
        [
            'label' => 'All Sections',
            'href' => route('admin.cashbook.monthly-report.section-reports'),
            'active' => request()->routeIs('admin.cashbook.monthly-report.section-reports*') && (! request()->has('section') || request('section') === 'all' || request('section') === ''),
        ],
    ];

    foreach ($dynamicSectionFilters as $filter) {
        $secKey = 'filter_'.$filter->id;
        $sectionReportSubChildren[] = [
            'label' => $filter->name,
            'href' => route('admin.cashbook.monthly-report.section-reports', ['section' => $secKey]),
            'active' => request()->routeIs('admin.cashbook.monthly-report.section-reports*') && request('section') === $secKey,
        ];
    }

    $sectionReportSubChildren[] = [
        'label' => 'Operating Expense',
        'href' => route('admin.cashbook.monthly-report.section-reports', ['section' => 'operating_expense']),
        'active' => request()->routeIs('admin.cashbook.monthly-report.section-reports*') && request('section') === 'operating_expense',
    ];

    $monthlyReportsSidebarItem = [
        'label' => 'Monthly Reports',
        'href' => route('admin.cashbook.monthly-report.overview'),
        'active' => $isMonthlyReportsActive,
        'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" /></svg>',
        'children' => [
            [
                'label' => 'Green Leaf Monthly Report',
                'href' => route('admin.cashbook.monthly-report.overview'),
                'active' => request()->routeIs('admin.cashbook.monthly-report.overview'),
            ],
            [
                'label' => 'Monthly Sale Split',
                'href' => route('admin.cashbook.monthly-report.sale-split'),
                'active' => request()->routeIs('admin.cashbook.monthly-report.sale-split') || request()->routeIs('admin.cashbook.monthly-report.section-reports*'),
                'children' => [
                    [
                        'label' => 'Sale Split Matrix',
                        'href' => route('admin.cashbook.monthly-report.sale-split'),
                        'active' => request()->routeIs('admin.cashbook.monthly-report.sale-split'),
                    ],
                    [
                        'label' => 'Section Reports',
                        'href' => route('admin.cashbook.monthly-report.section-reports'),
                        'active' => request()->routeIs('admin.cashbook.monthly-report.section-reports*'),
                        'children' => $sectionReportSubChildren,
                    ],
                ],
            ],
            [
                'label' => 'Other Expense',
                'href' => route('admin.cashbook.monthly-report.other-expenses'),
                'active' => request()->routeIs('admin.cashbook.monthly-report.other-expenses'),
            ],
            [
                'label' => 'Expense Report',
                'href' => route('admin.cashbook.monthly-report.expense-report'),
                'active' => request()->routeIs('admin.cashbook.monthly-report.expense-report'),
            ],
            [
                'label' => 'Shop Monthly Closing',
                'href' => route('admin.cashbook.monthly-closing-summary.index'),
                'active' => request()->routeIs('admin.cashbook.monthly-closing-summary*'),
            ],
            [
                'label' => 'Purchase Month',
                'href' => route('admin.cashbook.finance.purchase.monthly-summary.index'),
                'active' => request()->routeIs('admin.cashbook.finance.purchase.monthly-summary*'),
            ],
        ],
    ];
    $isAccountBalanceActive = request()->routeIs('admin.cashbook.account-balance*');

    $accountBalanceSidebarItem = [
        'label' => 'Account Balance',
        'href' => route('admin.cashbook.account-balance'),
        'active' => $isAccountBalanceActive,
        'icon' => '<svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.5H5.25A2.25 2.25 0 0 0 3 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25h13.5A2.25 2.25 0 0 0 21 17.25V6.75A2.25 2.25 0 0 0 18.75 4.5Z" /></svg>',
        'children' => [
            [
                'label' => '1. Accounts & Floating',
                'href' => route('admin.cashbook.account-balance').'#section-accounts',
                'active' => false,
                'children' => [
                    [
                        'label' => 'Bank & Cash Breakdown',
                        'href' => route('admin.cashbook.account-balance').'#section-accounts',
                        'active' => false,
                    ],
                    [
                        'label' => 'Floating In Money',
                        'href' => route('admin.cashbook.account-balance').'#section-floating-in',
                        'active' => false,
                    ],
                    [
                        'label' => 'Floating Out Money',
                        'href' => route('admin.cashbook.account-balance').'#section-floating-out',
                        'active' => false,
                    ],
                ],
            ],
            [
                'label' => '2. Receivables & Payables',
                'href' => route('admin.cashbook.account-balance').'#section-receivables',
                'active' => false,
                'children' => [
                    [
                        'label' => 'Shop Receivables',
                        'href' => route('admin.cashbook.account-balance').'#section-receivables',
                        'active' => false,
                    ],
                    [
                        'label' => 'Purchaser Payables & Payments',
                        'href' => route('admin.cashbook.account-balance').'#section-purchasers',
                        'active' => false,
                    ],
                    [
                        'label' => 'Vendor Payables & Payments',
                        'href' => route('admin.cashbook.account-balance').'#section-vendors',
                        'active' => false,
                    ],
                    [
                        'label' => 'Company Owes Shops & Petty',
                        'href' => route('admin.cashbook.account-balance').'#section-company-owes-shops',
                        'active' => false,
                    ],
                ],
            ],
            [
                'label' => '3. Movements & Audit Log',
                'href' => route('admin.cashbook.account-balance').'#section-movements',
                'active' => false,
                'children' => [
                    [
                        'label' => 'Period Movement Matrix',
                        'href' => route('admin.cashbook.account-balance').'#section-movements',
                        'active' => false,
                    ],
                    [
                        'label' => 'All Contributing Transactions',
                        'href' => route('admin.cashbook.account-balance').'#section-transactions',
                        'active' => false,
                    ],
                ],
            ],
        ],
    ];
@endphp

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

            <!-- MONEY FLOW -->
            <div class="space-y-1">
                <span data-cashbook-sidebar-label class="px-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">OVERVIEW</span>
                <a href="{{ route('admin.cashbook.money-flow') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.money-flow') ? 'active-sidebar' : '' }}">
                    <i data-lucide="activity" class="w-4 h-4 text-emerald-600"></i>
                    <span>Money Flow</span>
                </a>
                <a href="{{ route('admin.cashbook.cash-flow-tree.index') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.cash-flow-tree*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="git-fork" class="w-4 h-4 text-indigo-600"></i>
                    <span>Cash Flow Tree</span>
                </a>
            </div>

            <!-- PURCHASE (NESTED HIERARCHY) -->
            <x-sidebar-link :item="$purchaseSidebarItem" label-attribute="data-cashbook-sidebar-label" />

            <!-- ACCOUNT BALANCE (NESTED HIERARCHY SUBSECTIONS) -->
            <x-sidebar-link :item="$accountBalanceSidebarItem" label-attribute="data-cashbook-sidebar-label" />

            <!-- MONTHLY REPORTS (NESTED HIERARCHY) -->
            <x-sidebar-link :item="$monthlyReportsSidebarItem" label-attribute="data-cashbook-sidebar-label" />

            <!-- FINANCE -->
            <div class="space-y-1">
                <span data-cashbook-sidebar-label class="px-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">FINANCE</span>
                <a href="{{ route('admin.cashbook.finance') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.finance') ? 'active-sidebar' : '' }}">
                    <i data-lucide="badge-dollar-sign" class="w-4 h-4"></i>
                    <span>Company Money</span>
                    <span class="sr-only">Company Finance</span>
                </a>
                <a href="{{ route('admin.cashbook.finance.income-expense') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.finance.income-expense*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="arrow-left-right" class="w-4 h-4 text-emerald-600"></i>
                    <span>Company Income &amp; Expense</span>
                </a>
                <a href="{{ route('admin.cashbook.finance.cheque-submission') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.finance.cheque-submission') ? 'active-sidebar' : '' }}">
                    <i data-lucide="file-check-2" class="w-4 h-4"></i>
                    <span>Cheque Bank Submit</span>
                </a>
                <a href="{{ route('admin.cashbook.finance.reconciliation') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.finance.reconciliation*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="shield-alert" class="w-4 h-4"></i>
                    <span>Needs Attention</span>
                    <span class="sr-only">Reconciliation</span>
                </a>
                <a href="{{ route('admin.cashbook.finance.journal') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.finance.journal*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="book-open-check" class="w-4 h-4"></i>
                    <span>All Transactions</span>
                </a>
                <a href="{{ route('admin.cashbook.finance.vendor-credit') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.finance.vendor-credit*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="truck" class="w-4 h-4"></i>
                    <span>Vendor Credit</span>
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
                    <span>Shop Ledger CRUD</span>
                    <span class="sr-only">Shop Income &amp; Expenses</span>
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
                <a href="{{ route('admin.cashbook.warehouse-sales') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.warehouse-sales*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="shopping-cart" class="w-4 h-4 text-emerald-600"></i>
                    <span>Warehouse Sales</span>
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

            
            <!-- ASSETS -->
            <div class="space-y-1">
                <span data-cashbook-sidebar-label class="px-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">ASSETS</span>
                <a href="{{ route('admin.cashbook.assets.trays.index') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.assets.trays*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="inbox" class="w-4 h-4 text-blue-600"></i>
                    <span>Trays</span>
                </a>
            </div>
            
            <div class="space-y-1">
                <span data-cashbook-sidebar-label class="px-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">SETTINGS</span>
                <a href="{{ route('admin.cashbook.categories.index') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.categories.*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="layers" class="w-4 h-4"></i>
                    <span>Cashbook Categories</span>
                </a>
                <a href="{{ route('admin.cashbook.settings') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.settings') || request()->routeIs('admin.cashbook.settings.shop') ? 'active-sidebar' : '' }}">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                    <span>Settings</span>
                </a>
                <a href="{{ route('admin.cashbook.settings.final-report.index') }}" class="sidebar-link {{ request()->routeIs('admin.cashbook.settings.final-report.*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="file-check" class="w-4 h-4"></i>
                    <span>Final Report Settings</span>
                </a>
            </div>

        </nav>

    </div>

    <!-- Sidebar Footer -->
    <div class="p-4 border-t border-slate-100 bg-slate-50/80 space-y-3">
        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-rose-600 shadow-xs transition hover:bg-rose-50 hover:border-rose-200 hover:text-rose-700" title="Sign Out">
                <i data-lucide="log-out" class="w-4 h-4 flex-shrink-0"></i>
                <span data-cashbook-sidebar-label>Sign Out</span>
            </button>
        </form>

        <div class="space-y-1.5 pt-1 border-t border-slate-200/60">
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
    </div>
</aside>
