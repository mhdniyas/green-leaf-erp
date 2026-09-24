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

    $isMonthlyFinancialsActive = request()->routeIs('admin.cashbook.monthly-report.overview')
        || request()->routeIs('admin.cashbook.monthly-closing-summary*')
        || request()->routeIs('admin.cashbook.finance.purchase.monthly-summary*');

    $isSalesSectionActive = request()->routeIs('admin.cashbook.monthly-report.sale-split*')
        || request()->routeIs('admin.cashbook.monthly-report.section-reports*');

    $isExpensesActive = request()->routeIs('admin.cashbook.monthly-report.expense-report*')
        || request()->routeIs('admin.cashbook.monthly-report.other-expenses*');

    $isMonthlyReportsActive = $isMonthlyFinancialsActive
        || $isSalesSectionActive
        || $isExpensesActive
        || request()->routeIs('admin.cashbook.monthly-report.*');

    $monthlyReportsSidebarItem = [
        'label' => 'Reports',
        'href' => route('admin.cashbook.monthly-report.overview'),
        'active' => $isMonthlyReportsActive,
        'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>',
        'children' => [
            [
                'label' => '1. Monthly Financials',
                'href' => route('admin.cashbook.monthly-report.overview'),
                'active' => $isMonthlyFinancialsActive,
                'children' => [
                    [
                        'label' => 'Green Leaf Monthly Report',
                        'href' => route('admin.cashbook.monthly-report.overview'),
                        'active' => request()->routeIs('admin.cashbook.monthly-report.overview'),
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
            ],
            [
                'label' => '2. Sales & Section Split',
                'href' => route('admin.cashbook.monthly-report.sale-split'),
                'active' => $isSalesSectionActive,
                'children' => [
                    [
                        'label' => 'Monthly Sale Split',
                        'href' => route('admin.cashbook.monthly-report.sale-split'),
                        'active' => request()->routeIs('admin.cashbook.monthly-report.sale-split'),
                    ],
                    [
                        'label' => 'Section Reports',
                        'href' => route('admin.cashbook.monthly-report.section-reports'),
                        'active' => request()->routeIs('admin.cashbook.monthly-report.section-reports*'),
                    ],
                ],
            ],
            [
                'label' => '3. Expense Breakdown',
                'href' => route('admin.cashbook.monthly-report.expense-report'),
                'active' => $isExpensesActive,
                'children' => [
                    [
                        'label' => 'Expense Report',
                        'href' => route('admin.cashbook.monthly-report.expense-report'),
                        'active' => request()->routeIs('admin.cashbook.monthly-report.expense-report'),
                    ],
                    [
                        'label' => 'Other Expense',
                        'href' => route('admin.cashbook.monthly-report.other-expenses'),
                        'active' => request()->routeIs('admin.cashbook.monthly-report.other-expenses'),
                    ],
                ],
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

    $isFinanceActive = (request()->routeIs('admin.cashbook.finance*')
        && ! request()->routeIs('admin.cashbook.finance.purchase*'))
        || request()->routeIs('admin.cashbook.bank-accounts.*');

    $financeSidebarItem = [
        'label' => 'Finance',
        'href' => route('admin.cashbook.finance'),
        'active' => $isFinanceActive,
        'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>',
        'children' => [
            [
                'label' => 'Company Money',
                'href' => route('admin.cashbook.finance'),
                'active' => request()->routeIs('admin.cashbook.finance') && !request()->routeIs('admin.cashbook.finance.*'),
            ],
            [
                'label' => 'Income & Expense',
                'href' => route('admin.cashbook.finance.income-expense'),
                'active' => request()->routeIs('admin.cashbook.finance.income-expense*'),
            ],
            [
                'label' => 'Cheque Bank Submit',
                'href' => route('admin.cashbook.finance.cheque-submission'),
                'active' => request()->routeIs('admin.cashbook.finance.cheque-submission'),
            ],
            [
                'label' => 'Needs Attention',
                'href' => route('admin.cashbook.finance.reconciliation'),
                'active' => request()->routeIs('admin.cashbook.finance.reconciliation*'),
            ],
            [
                'label' => 'All Transactions',
                'href' => route('admin.cashbook.finance.journal'),
                'active' => request()->routeIs('admin.cashbook.finance.journal*'),
            ],
            [
                'label' => 'Vendor Credit',
                'href' => route('admin.cashbook.finance.vendor-credit'),
                'active' => request()->routeIs('admin.cashbook.finance.vendor-credit*'),
            ],
            [
                'label' => 'Direct Company Sales',
                'href' => route('admin.cashbook.finance.direct-sales'),
                'active' => request()->routeIs('admin.cashbook.finance.direct-sales*'),
            ],
            [
                'label' => 'GL Bills',
                'href' => route('admin.cashbook.finance.gl-bills'),
                'active' => request()->routeIs('admin.cashbook.finance.gl-bills*') || request()->routeIs('admin.cashbook.reports.gl-bills*'),
            ],
            [
                'label' => 'Bank & Cash Accounts',
                'href' => route('admin.cashbook.bank-accounts.create'),
                'active' => request()->routeIs('admin.cashbook.bank-accounts.*'),
            ],
        ],
    ];

    $allShopProfiles = \App\Models\Cashbook\ShopLedgerProfile::query()
        ->where('enabled', true)
        ->whereHas('shop', function ($query): void {
            $query->where('status', 'active');
        })
        ->with(['shop.client', 'client'])
        ->orderBy('name')
        ->get();

    if ($allShopProfiles->isEmpty()) {
        $allShopProfiles = \App\Models\Shop::query()
            ->where('status', 'active')
            ->with('client')
            ->orderBy('name')
            ->get();
    }

    $currentRouteShopParam = request()->route('shop');
    $currentShopIdentifier = null;
    $currentShopId = null;
    if ($currentRouteShopParam instanceof \App\Models\Cashbook\ShopLedgerProfile || $currentRouteShopParam instanceof \App\Models\Shop) {
        $currentShopIdentifier = (string) ($currentRouteShopParam->slug ?: ($currentRouteShopParam->code ?: ($currentRouteShopParam->shop_id ?? $currentRouteShopParam->id)));
        $currentShopId = (int) ($currentRouteShopParam->shop_id ?? $currentRouteShopParam->id);
    } elseif (is_string($currentRouteShopParam) || is_numeric($currentRouteShopParam)) {
        $currentShopIdentifier = (string) $currentRouteShopParam;
        $currentShopId = is_numeric($currentRouteShopParam) ? (int) $currentRouteShopParam : null;
    } elseif (isset($currentShop)) {
        $currentShopIdentifier = (string) ($currentShop->slug ?: ($currentShop->code ?: ($currentShop->shop_id ?? $currentShop->id)));
        $currentShopId = (int) ($currentShop->shop_id ?? $currentShop->id);
    }

    $isShopActive = function ($shopModel) use ($currentShopIdentifier, $currentShopId): bool {
        if (! request()->routeIs('admin.cashbook.shop.*')) {
            return false;
        }
        if ($currentShopIdentifier === null && $currentShopId === null) {
            return false;
        }
        $slug = (string) ($shopModel->slug ?? '');
        $code = (string) ($shopModel->code ?? '');
        $id = (int) ($shopModel->shop_id ?? $shopModel->id);

        if ($currentShopIdentifier !== null) {
            if ($slug !== '' && $slug === $currentShopIdentifier) {
                return true;
            }
            if ($code !== '' && strtolower($code) === strtolower($currentShopIdentifier)) {
                return true;
            }
            if ((string) $id === $currentShopIdentifier) {
                return true;
            }
        }
        if ($currentShopId !== null && $id === $currentShopId) {
            return true;
        }

        return false;
    };

    $cashbookClients = \App\Models\Client::query()
        ->where('status', 'active')
        ->orderBy('name')
        ->get();

    $isMoneyFlowAllShopsActive = request()->routeIs('admin.cashbook.money-flow')
        && ! request()->routeIs('admin.cashbook.money-flow.client*')
        && ! request()->routeIs('admin.cashbook.money-flow.direct*');

    $clientChildren = [];
    foreach ($cashbookClients as $clientItem) {
        $shopsForClient = $allShopProfiles->filter(function ($profile) use ($clientItem) {
            $profileClientId = $profile->client_id ?? ($profile->shop?->client_id ?? null);
            $erpClientId = $profile->shop?->client?->id ?? ($profile->client?->erp_client_id ?? null);

            return (int) $profileClientId === (int) $clientItem->id || (int) $erpClientId === (int) $clientItem->id;
        })->values();

        $subChildren = [];
        $hasActiveShop = false;
        foreach ($shopsForClient as $shopItem) {
            $active = $isShopActive($shopItem);
            if ($active) {
                $hasActiveShop = true;
            }
            $slug = $shopItem->slug ?: ($shopItem->code ? strtolower($shopItem->code) : (string) ($shopItem->shop_id ?? $shopItem->id));
            $subChildren[] = [
                'label' => $shopItem->name,
                'href' => route('admin.cashbook.shop.show', ['shop' => $slug]),
                'active' => $active,
            ];
        }

        $isThisClientActive = request()->routeIs('admin.cashbook.money-flow.client*')
            && (int) (request()->route('client')?->id ?? request()->route('client')) === (int) $clientItem->id;

        $clientChildren[] = [
            'label' => $clientItem->name,
            'href' => route('admin.cashbook.money-flow.client', $clientItem),
            'active' => $isThisClientActive || $hasActiveShop,
            'children' => $subChildren,
        ];
    }

    $directShopsList = $allShopProfiles->filter(function ($profile) {
        $profileClientId = $profile->client_id ?? ($profile->shop?->client_id ?? null);
        $erpClientId = $profile->shop?->client?->id ?? ($profile->client?->erp_client_id ?? null);

        return empty($profileClientId) && empty($erpClientId);
    })->values();

    $directSubChildren = [];
    $hasActiveDirectShop = false;
    foreach ($directShopsList as $shopItem) {
        $active = $isShopActive($shopItem);
        if ($active) {
            $hasActiveDirectShop = true;
        }
        $slug = $shopItem->slug ?: ($shopItem->code ? strtolower($shopItem->code) : (string) ($shopItem->shop_id ?? $shopItem->id));
        $directSubChildren[] = [
            'label' => $shopItem->name,
            'href' => route('admin.cashbook.shop.show', ['shop' => $slug]),
            'active' => $active,
        ];
    }

    $isDirectShopsActive = request()->routeIs('admin.cashbook.money-flow.direct*');

    $shopChildren = array_merge(
        [
            [
                'label' => 'All Shops',
                'href' => route('admin.cashbook.money-flow'),
                'active' => $isMoneyFlowAllShopsActive,
            ],
        ],
        $clientChildren,
        [
            [
                'label' => 'Direct Shops',
                'href' => route('admin.cashbook.money-flow.direct-shops'),
                'active' => $isDirectShopsActive || $hasActiveDirectShop,
                'children' => $directSubChildren,
            ],
        ]
    );

    $isShopModuleActive = request()->routeIs('admin.cashbook.money-flow*')
        || request()->routeIs('admin.cashbook.shop.*');

    $shopSidebarItem = [
        'label' => 'Shops',
        'href' => route('admin.cashbook.money-flow'),
        'active' => $isShopModuleActive,
        'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" /></svg>',
        'children' => $shopChildren,
    ];

    $isSettingsActive = request()->routeIs('admin.cashbook.assets.*')
        || request()->routeIs('admin.cashbook.categories.*')
        || request()->routeIs('admin.cashbook.settings*');

    $settingsSidebarItem = [
        'label' => 'Settings & Assets',
        'href' => route('admin.cashbook.settings'),
        'active' => $isSettingsActive,
        'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.6 6.6 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.216.456a1.125 1.125 0 0 1-1.37-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>',
        'children' => [
            [
                'label' => 'Trays & Assets',
                'href' => route('admin.cashbook.assets.trays.index'),
                'active' => request()->routeIs('admin.cashbook.assets.trays*'),
            ],
            [
                'label' => 'Cashbook Categories',
                'href' => route('admin.cashbook.categories.index'),
                'active' => request()->routeIs('admin.cashbook.categories.*'),
            ],
            [
                'label' => 'Settings',
                'href' => route('admin.cashbook.settings'),
                'active' => request()->routeIs('admin.cashbook.settings') || request()->routeIs('admin.cashbook.settings.shop'),
            ],
            [
                'label' => 'Final Report Settings',
                'href' => route('admin.cashbook.settings.final-report.index'),
                'active' => request()->routeIs('admin.cashbook.settings.final-report.*'),
            ],
        ],
    ];

    $isOthersActive = request()->routeIs('admin.cashbook.all-shops')
        || request()->routeIs('admin.cashbook.index')
        || request()->routeIs('admin.cashbook.income-expenses')
        || request()->routeIs('admin.cashbook.post-entry*');

    $othersSidebarItem = [
        'label' => 'Others',
        'href' => route('admin.cashbook.all-shops'),
        'active' => $isOthersActive,
        'icon' => '<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" /></svg>',
        'children' => [
            [
                'label' => 'All Shops Overview',
                'href' => route('admin.cashbook.all-shops'),
                'active' => request()->routeIs('admin.cashbook.all-shops') || request()->routeIs('admin.cashbook.index'),
            ],
            [
                'label' => 'Single Shop Ledger',
                'href' => route('admin.cashbook.shop.show', isset($currentShop) ? ($currentShop->slug ?: $currentShop->shop_id) : ($allShopProfiles->first()?->slug ?? 1)),
                'active' => false,
            ],
            [
                'label' => 'Shop Ledger CRUD',
                'href' => route('admin.cashbook.income-expenses'),
                'active' => request()->routeIs('admin.cashbook.income-expenses'),
            ],
            [
                'label' => 'Post Entry Simulator',
                'href' => route('admin.cashbook.post-entry'),
                'active' => request()->routeIs('admin.cashbook.post-entry') || request()->routeIs('admin.cashbook.post-entry.shop'),
            ],
        ],
    ];
@endphp

<!-- Mobile Overlay Backdrop -->
<div id="sidebar-backdrop" onclick="toggleMobileSidebar()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-40 hidden md:hidden transition-opacity duration-300"></div>

<!-- Sidebar Drawer -->
<aside id="main-sidebar" class="w-64 max-w-[85vw] bg-white text-slate-700 flex flex-col justify-between fixed inset-y-0 left-0 z-50 border-r border-slate-200/90 shadow-lg md:shadow-sm transition-[width,transform] duration-300 ease-in-out -translate-x-full md:translate-x-0 lg:w-64">
    <div class="p-4 sm:p-5 space-y-4 sm:space-y-5 overflow-y-auto custom-scrollbar">

        <!-- Brand Header -->
        <div class="pb-3.5 border-b border-slate-100">
            <div class="flex items-center justify-between">
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
                <button onclick="toggleMobileSidebar()" class="md:hidden p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition" aria-label="Close sidebar">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>

        <!-- Navigation Sections -->
        <nav class="space-y-4">

            <!-- ← Back to main ERP admin -->
            <a href="{{ route('admin.overview') }}" class="sidebar-link min-h-[44px] text-slate-400 hover:text-slate-700 rounded-2xl px-3.5 py-2.5">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span data-cashbook-sidebar-label>Back to Admin</span>
            </a>

            <!-- OVERVIEW -->
            <div class="space-y-1">
                <span data-cashbook-sidebar-label class="px-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">OVERVIEW</span>
                <a href="{{ route('admin.cashbook.cash-flow-tree.index') }}" class="sidebar-link min-h-[44px] {{ request()->routeIs('admin.cashbook.cash-flow-tree*') ? 'active-sidebar' : '' }}">
                    <i data-lucide="git-fork" class="w-4 h-4 text-indigo-600"></i>
                    <span data-cashbook-sidebar-label>Cash Flow Tree</span>
                </a>
            </div>

            <!-- CORE MODULES (NESTED HIERARCHIES) -->
            <div class="space-y-2">
                <span data-cashbook-sidebar-label class="px-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">MODULES</span>

                <!-- SHOPS -->
                <x-sidebar-link :item="$shopSidebarItem" label-attribute="data-cashbook-sidebar-label" />

                <!-- PURCHASE -->
                <x-sidebar-link :item="$purchaseSidebarItem" label-attribute="data-cashbook-sidebar-label" />

                <!-- ACCOUNT BALANCE -->
                <x-sidebar-link :item="$accountBalanceSidebarItem" label-attribute="data-cashbook-sidebar-label" />

                <!-- REPORTS -->
                <x-sidebar-link :item="$monthlyReportsSidebarItem" label-attribute="data-cashbook-sidebar-label" />

                <!-- FINANCE -->
                <x-sidebar-link :item="$financeSidebarItem" label-attribute="data-cashbook-sidebar-label" />

                <!-- SETTINGS & ASSETS -->
                <x-sidebar-link :item="$settingsSidebarItem" label-attribute="data-cashbook-sidebar-label" />

                <!-- OTHERS -->
                <x-sidebar-link :item="$othersSidebarItem" label-attribute="data-cashbook-sidebar-label" />
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
