@props(['title' => 'Green Leaf Traders', 'showMobileNav' => true, 'showPageJump' => true])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#84cc16">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Green Leaf">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>{{ $title }} — Green Leaf Fruits and Vegetables</title>
    <meta name="description" content="Green Leaf Fruits and Vegetables — Distribution Management System">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <script>
        localStorage.setItem('theme', 'light');
        document.documentElement.classList.remove('dark');
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="flex min-h-screen flex-col bg-slate-100 font-sans antialiased">
@php
    $currentUser = auth()->user();
    $navDate = request()->input('date', app(\App\Services\Purchasing\PurchaserBusinessDayService::class)->operationalDate()->toDateString());
    $currentUserInitial = $currentUser ? strtoupper(substr($currentUser->name, 0, 1)) : 'U';
    $staffLandingUrl = \App\Support\StaffAccess::landingUrl($currentUser, request()->input('date', today()->toDateString()));
    $canAccessStaffWorkspace = \App\Support\StaffAccess::canAccessAny($currentUser);
    $showAdminWorkspaceLinks = $currentUser &&
        ($currentUser->hasRole('admin') || $currentUser->can('admin.user.view') || $currentUser->can('admin.daily-progress.view') || $currentUser->can('admin.activity-log.view'));
    $showAdminMobileNav = $showMobileNav && $currentUser &&
        ($currentUser->hasRole('admin') || $currentUser->can('admin.user.view') || $currentUser->can('admin.daily-progress.view') || $currentUser->can('admin.activity-log.view'));
    $showStaffMobileNav = $showMobileNav && $currentUser &&
        ! $showAdminMobileNav &&
        $canAccessStaffWorkspace;
    $showPurchaserMobileNav = $showMobileNav && $currentUser &&
        ! $showAdminMobileNav &&
        ! $showStaffMobileNav &&
        $currentUser->hasRole('purchaser');
    $showPurchaseMobileNav = $showMobileNav && $currentUser &&
        ! $showAdminMobileNav &&
        ! $showStaffMobileNav &&
        ! $showPurchaserMobileNav &&
        ($currentUser->hasRole('purchase') || $currentUser->can('purchasing.order.approve'));
    $showWarehouseReceiverMobileNav = $showMobileNav && $currentUser &&
        ! $showAdminMobileNav &&
        ! $showStaffMobileNav &&
        ! $showPurchaserMobileNav &&
        ! $showPurchaseMobileNav &&
        ($currentUser->hasRole('warehouse_receiver') || $currentUser->can('warehouse.receive.confirm'));

    $wrPendingCount = 0;
    $wrStockCount = 0;
    $wrLoadoutCount = 0;
    $wrDeliveryCount = 0;

    if ($showWarehouseReceiverMobileNav) {
        $wrDate = request()->input('date', \Illuminate\Support\Carbon::today()->format('Y-m-d'));

        $wrPendingCount = \App\Models\GoodsReceived::where('status', 'pending_approval')
            ->whereDate('received_at', $wrDate)
            ->count()
            + \App\Models\StockBatch::where('warehouse_receive_pending', true)
            ->whereDate('received_at', $wrDate)
            ->count();

        $wrStockCount = app(\App\Repositories\Inventory\StockMovementRepository::class)
            ->currentStockByProductAndGrade($wrDate)
            ->count();

        $wrLoadoutCount = \App\Models\ShopOrder::whereDate('business_date', $wrDate)
            ->whereIn('delivery_status', ['pending_delivery', 'ready_for_dispatch', 'in_transit'])
            ->count();
    }

    $adminMobileNavItems = [
        [
            'label' => 'Overview',
            'route' => 'admin.overview',
            'active' => request()->routeIs('admin.overview'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.5h8.25V3H3v10.5Zm0 7.5h8.25v-4.5H3V21Zm9.75 0H21V10.5h-8.25V21Zm0-12h8.25V3h-8.25v6Z"/></svg>',
            'visible' => $currentUser->hasRole('admin') || $currentUser->can('admin.user.view') || $currentUser->can('admin.daily-progress.view') || $currentUser->can('admin.activity-log.view'),
        ],
        [
            'label' => 'Users',
            'route' => 'admin.users.index',
            'active' => request()->routeIs('admin.users.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z"/></svg>',
            'visible' => $currentUser->hasRole('admin') || $currentUser->can('admin.user.view'),
        ],
        [
            'label' => 'Access',
            'route' => 'admin.user-access.index',
            'active' => request()->routeIs('admin.user-access.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25A3.75 3.75 0 1 1 12 1.5a3.75 3.75 0 0 1 3.75 3.75Zm-9 13.5a5.25 5.25 0 0 1 10.5 0v.75H6.75v-.75Zm12.75-6.75H21m0 0h2.25M21 12V9.75M21 12v2.25" /></svg>',
            'visible' => $currentUser->isMainAdmin(),
        ],
        [
            'label' => 'Progress',
            'route' => 'admin.daily-progress',
            'active' => request()->routeIs('admin.daily-progress'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 14l3-3 3 2 4-6"/></svg>',
            'visible' => $currentUser->hasRole('admin') || $currentUser->can('admin.daily-progress.view'),
        ],
        [
            'label' => 'Activity',
            'route' => 'admin.activity-logs.index',
            'active' => request()->routeIs('admin.activity-logs.index'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l3 8 4-16 3 8h4"/></svg>',
            'visible' => $currentUser->hasRole('admin') || $currentUser->can('admin.activity-log.view'),
        ],
        [
            'label' => 'Accounting',
            'route' => 'admin.accounting.index',
            'active' => request()->routeIs('admin.accounting.*') || request()->routeIs('finance.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m0 0c-2.21 0-4-1.343-4-3s1.79-3 4-3 4-1.343 4-3-1.79-3-4-3m0 12c2.21 0 4-1.343 4-3"/></svg>',
            'visible' => \App\Support\AccountingAccess::canViewDashboard($currentUser),
        ],
        [
            'label' => 'Staff',
            'route' => 'admin.staff.index',
            'params' => ['date' => request()->input('date', today()->toDateString())],
            'active' => request()->routeIs('admin.staff.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a8.97 8.97 0 0 0 3.74-1.04 4.5 4.5 0 0 0-7.48-2.23m3.74 3.27v.28A10.94 10.94 0 0 1 12 21c-2.33 0-4.5-.73-6.28-1.98v-.29m12.56 0a5.97 5.97 0 0 0-12.56 0M15 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 2.25a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>',
            'visible' => $canAccessStaffWorkspace && $staffLandingUrl !== null,
            'href' => $staffLandingUrl,
        ],
    ];
    $adminMobileNavItems = array_values(array_filter(
        $adminMobileNavItems,
        fn (array $item): bool => (bool) ($item['visible'] ?? true)
    ));
    $purchaseMobileNavItems = [
        [
            'label' => 'Dashboard',
            'route' => 'purchasing.dashboard',
            'active' => request()->routeIs('purchasing.dashboard') || request()->routeIs('purchasing.orders.index'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Approvals',
            'route' => 'requisitions.board',
            'active' => request()->routeIs('requisitions.board'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5.25h6M9 9.75h6M9 14.25h6M5.25 5.25h.008v.008H5.25V5.25zm0 4.5h.008v.008H5.25V9.75zm0 4.5h.008v.008H5.25V14.25zm-1.5-9A2.25 2.25 0 016 3h12a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0118 21H6a2.25 2.25 0 01-2.25-2.25V5.25z" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Approved',
            'route' => 'requisitions.approved_board',
            'params' => ['date' => $navDate],
            'active' => request()->routeIs('requisitions.approved_board') && ! request()->boolean('settings'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m6 2.25a9 9 0 11-18 0 9 9 0 0118 0Z" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Settings',
            'route' => 'requisitions.approved_board',
            'params' => ['date' => $navDate, 'settings' => 1],
            'active' => request()->routeIs('requisitions.approved_board') && request()->boolean('settings'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.094c.55 0 1.02.398 1.11.94l.149.894c.07.424.349.78.746.944.397.164.85.104 1.198-.148l.735-.535a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.535.735c-.252.348-.312.801-.148 1.198.164.397.52.676.944.746l.894.149c.542.09.94.56.94 1.11v1.094c0 .55-.398 1.02-.94 1.11l-.894.149c-.424.07-.78.349-.944.746-.164.397-.104.85.148 1.198l.535.735c.32.448.27 1.061-.12 1.45l-.773.774a1.125 1.125 0 0 1-1.45.12l-.735-.535c-.348-.252-.801-.312-1.198-.148-.397.164-.676.52-.746.944l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.02-.398-1.11-.94l-.149-.894c-.07-.424-.349-.78-.746-.944-.397-.164-.85-.104-1.198.148l-.735.535a1.125 1.125 0 0 1-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.535-.735c.252-.348.312-.801.148-1.198-.164-.397-.52-.676-.944-.746l-.894-.149a1.125 1.125 0 0 1-.94-1.11v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.78-.349.944-.746.164-.397.104-.85-.148-1.198l-.535-.735a1.125 1.125 0 0 1 .12-1.45l.773-.774a1.125 1.125 0 0 1 1.45-.12l.735.535c.348.252.801.312 1.198.148.397-.164.676-.52.746-.944l.149-.894Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Orders',
            'route' => 'purchasing.orders.index',
            'active' => request()->routeIs('purchasing.orders.*'),
            'icon' => '<svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0z" /></svg>',
            'type' => 'center',
        ],
        [
            'label' => 'Receipts',
            'route' => 'purchasing.grns.index',
            'active' => request()->routeIs('purchasing.grns.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3.75A.75.75 0 013.75 3h10.5a.75.75 0 01.53.22l5 5a.75.75 0 01.22.53v11.5a.75.75 0 01-.75.75H3.75a.75.75 0 01-.75-.75V3.75z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 12h7.5m-7.5 3h4.5" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Prices',
            'route' => 'purchasing.prices.index',
            'active' => request()->routeIs('purchasing.prices.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m3.75-9.75h-6a2.25 2.25 0 100 4.5h4.5a2.25 2.25 0 100 4.5h-6" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Bills',
            'route' => 'purchasing.invoices.index',
            'active' => request()->routeIs('purchasing.invoices.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75h7.5m-9 3h10.5A2.25 2.25 0 0118.75 9v10.5A2.25 2.25 0 0116.5 21h-9A2.25 2.25 0 015.25 18.75V9A2.25 2.25 0 017.5 6.75Zm0 4.5h9m-9 3h6" /></svg>',
            'type' => 'link',
        ],
    ];
    $staffMobileNavItems = [];
    if (\App\Support\StaffAccess::canViewDashboard($currentUser)) {
        $staffMobileNavItems[] = [
            'label' => 'Staff',
            'route' => 'admin.staff.index',
            'params' => ['date' => request()->input('date', today()->toDateString())],
            'active' => request()->routeIs('admin.staff.index'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75h7.5v7.5h-7.5v-7.5Zm9 0h7.5v7.5h-7.5v-7.5Zm-9 9h7.5v7.5h-7.5v-7.5Zm9 3h7.5v4.5h-7.5v-4.5Z"/></svg>',
        ];
    }
    if (\App\Support\StaffAccess::canViewAttendance($currentUser)) {
        $staffMobileNavItems[] = [
            'label' => 'Attend',
            'route' => 'admin.staff.attendance',
            'params' => ['date' => request()->input('date', today()->toDateString())],
            'active' => request()->routeIs('admin.staff.attendance'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5.25h6M9 9.75h6M9 14.25h3m-6.75 6h13.5A2.25 2.25 0 0 0 21 18V6A2.25 2.25 0 0 0 18.75 3.75H5.25A2.25 2.25 0 0 0 3 6v12A2.25 2.25 0 0 0 5.25 20.25Z"/></svg>',
        ];
    }
    if (\App\Support\StaffAccess::canViewLeaves($currentUser)) {
        $staffMobileNavItems[] = [
            'label' => 'Leave',
            'route' => 'admin.staff.leaves.index',
            'active' => request()->routeIs('admin.staff.leaves.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-8.25A2.25 2.25 0 0 0 17.25 3.75H6.75A2.25 2.25 0 0 0 4.5 6v12A2.25 2.25 0 0 0 6.75 20.25h5.379a3 3 0 0 1 2.121-.879h3a3 3 0 0 1 2.25 1.014V14.25ZM8.25 7.5h7.5m-7.5 4.5h4.5"/></svg>',
        ];
    }
    if (\App\Support\StaffAccess::canViewPayroll($currentUser)) {
        $staffMobileNavItems[] = [
            'label' => 'Payroll',
            'route' => 'admin.staff.payroll.index',
            'active' => request()->routeIs('admin.staff.payroll.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m0 0c-2.21 0-4-1.343-4-3s1.79-3 4-3 4-1.343 4-3-1.79-3-4-3m0 12c2.21 0 4-1.343 4-3"/></svg>',
        ];
    }
    $purchaserMobileNavItems = [
        [
            'label' => 'Daily',
            'route' => 'purchaser.daily',
            'active' => request()->routeIs('purchaser.daily') || request()->routeIs('purchaser.shop-orders.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7.5h16M4 12h16M4 16.5h10" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Cart',
            'route' => 'purchaser.vendors',
            'active' => request()->routeIs('purchaser.vendors') || request()->routeIs('purchaser.cart') || request()->routeIs('purchaser.bill'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>',
            'type' => 'center',
        ],
        [
            'label' => 'Vendors',
            'route' => 'purchaser.suppliers',
            'active' => request()->routeIs('purchaser.suppliers.*') || request()->routeIs('purchaser.suppliers'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M5.25 21V8.25A2.25 2.25 0 017.5 6h9a2.25 2.25 0 012.25 2.25V21M9 9.75h6M9 13.5h6M9 17.25h3" /></svg>',
            'type' => 'link',
        ],
        [
            'label' => 'Report',
            'route' => 'purchaser.history',
            'active' => request()->routeIs('purchaser.history'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>',
            'type' => 'link',
        ],
    ];
    $warehouseReceiverMobileNavItems = [
        [
            'label' => 'Receive',
            'route' => 'warehouse.receiver.checklist',
            'params' => ['tab' => 'pending'],
            'active' => request()->routeIs('warehouse.receiver.receive-grn') || (request()->routeIs('warehouse.receiver.checklist') && request()->query('tab', 'pending') === 'pending'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>',
            'badge' => $wrPendingCount,
            'type' => 'link',
        ],
        [
            'label' => 'Inventory',
            'route' => 'warehouse.receiver.checklist',
            'params' => ['tab' => 'inventory'],
            'active' => request()->routeIs('warehouse.receiver.checklist') && request()->query('tab') === 'inventory',
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>',
            'badge' => $wrStockCount,
            'type' => 'link',
        ],
        [
            'label' => 'Loadout',
            'route' => 'warehouse.loadout.index',
            'active' => request()->routeIs('warehouse.loadout.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.75A1.125 1.125 0 012.625 17.625V4.625L13.5 4.625v14.125m.125-14.125H16.5a1.5 1.5 0 011.06.44l2.625 2.625a1.5 1.5 0 01.44 1.06V17.625a1.125 1.125 0 01-1.125 1.125H18m0 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" /></svg>',
            'badge' => $wrLoadoutCount,
            'type' => 'link',
        ],
        [
            'label' => 'Sort',
            'route' => 'warehouse.receiver.sort-sheet.index',
            'active' => request()->routeIs('warehouse.receiver.sort-sheet.*'),
            'icon' => '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5m-16.5 5.25h16.5m-16.5 5.25h16.5" /></svg>',
            'type' => 'link',
        ],
    ];
    $showMobileBottomNav = $showAdminMobileNav || $showStaffMobileNav || $showPurchaseMobileNav || $showPurchaserMobileNav || $showWarehouseReceiverMobileNav;
@endphp

<div id="app-container" class="flex min-h-0 flex-1">

    {{-- ── Sidebar ─────────────────────────────────────────────── --}}
    <aside
        id="sidebar"
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-slate-100 text-slate-900 shadow-sm transition-transform duration-300 lg:translate-x-0 -translate-x-full"
        aria-label="Sidebar navigation"
    >
        {{-- Logo --}}
        <div class="border-b border-slate-200 px-6 py-5">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/logo.png') }}" alt="Green Leaf Logo" class="h-10 w-auto object-contain shrink-0">
                <div class="min-w-0">
                    <p class="truncate text-sm font-black leading-none text-slate-950">Green Leaf ERP</p>
                    <p class="mt-1 text-[10px] font-black uppercase tracking-[0.2em] text-lime-600">Fruits & Vegetables</p>
                </div>
                <button id="sidebar-close" class="ml-auto rounded-2xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 lg:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Signed In</p>
                <p class="mt-1 truncate text-sm font-bold text-slate-950">{{ auth()->user()->name }}</p>
                <p class="mt-1 truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 space-y-3 overflow-y-auto px-4 py-6">

            @php
                $isPurchaserOnlyWorkspace = $currentUser?->hasRole('purchaser')
                    && ! $currentUser?->hasRole('admin')
                    && ! $currentUser?->hasRole('shop')
                    && ! $currentUser?->hasRole('warehouse_receiver')
                    && ! $canAccessStaffWorkspace;
            @endphp

            {{-- Dashboard --}}
            @unless($isPurchaserOnlyWorkspace)
                <x-nav-item href="{{ route('dashboard') }}" icon="squares-2x2" :active="request()->routeIs('dashboard')">
                    Dashboard
                </x-nav-item>
            @endunless

            @if($currentUser?->hasRole('admin') && $currentUser?->hasRole('purchaser'))
                @php
                    $isAdminPurchaseFlowActive = request()->routeIs('admin.accounting.purchasers.*')
                        || request()->routeIs('purchaser.*')
                        || request()->routeIs('purchasing.invoices.*')
                        || request()->routeIs('requisitions.approved_board');
                @endphp
                <div class="sidebar-group space-y-1">
                    <button
                        type="button"
                        class="sidebar-group-toggle group flex w-full cursor-pointer items-center justify-between rounded-2xl px-4 py-3 text-sm font-bold transition-all {{ $isAdminPurchaseFlowActive ? 'bg-white text-slate-950 shadow-[0_10px_24px_rgba(15,23,42,0.08)] ring-1 ring-slate-200/80' : 'text-slate-500 hover:bg-white/70 hover:text-slate-950 hover:shadow-sm' }}"
                        aria-expanded="{{ $isAdminPurchaseFlowActive ? 'true' : 'false' }}"
                    >
                        <span class="flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0 opacity-80 transition-opacity group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                            </svg>
                            <span>Admin Purchase</span>
                        </span>
                        <svg class="chevron-icon h-3.5 w-3.5 transition-transform duration-200 {{ $isAdminPurchaseFlowActive ? 'rotate-90 opacity-100' : 'opacity-50 group-hover:opacity-100' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                    <div class="sidebar-group-items ml-6 space-y-1 border-l border-slate-200 py-1 pl-4 pr-1 transition-all duration-200 {{ $isAdminPurchaseFlowActive ? '' : 'hidden' }}">
                        <x-nav-item href="{{ route('admin.accounting.index', ['date' => $navDate]) }}" :active="request()->routeIs('admin.accounting.index')" :sub="true">
                            Accounting
                        </x-nav-item>
                        <x-nav-item href="{{ route('admin.accounting.purchasers.index') }}" :active="request()->routeIs('admin.accounting.purchasers.index') || request()->routeIs('admin.accounting.purchasers.show')" :sub="true">
                            Purchaser Ledger
                        </x-nav-item>
                        <x-nav-item href="{{ route('admin.accounting.purchasers.direct-purchase.create', ['date' => $navDate]) }}" :active="request()->routeIs('admin.accounting.purchasers.direct-purchase.*')" :sub="true">
                            Direct Purchase
                        </x-nav-item>
                        <x-nav-item href="{{ route('requisitions.approved_board', ['date' => $navDate, 'settings' => 1]) }}" :active="request()->routeIs('requisitions.approved_board') && request()->boolean('settings')" :sub="true">
                            Purchase Settings
                        </x-nav-item>
                        <x-nav-item href="{{ route('purchaser.daily', ['date' => $navDate]) }}" :active="request()->routeIs('purchaser.daily')" :sub="true">
                            Purchaser Daily
                        </x-nav-item>
                        <x-nav-item href="{{ route('purchaser.vendors', ['date' => $navDate]) }}" :active="request()->routeIs('purchaser.vendors') || request()->routeIs('purchaser.cart') || request()->routeIs('purchaser.bill')" :sub="true">
                            Vendor Carts
                        </x-nav-item>
                        <x-nav-item href="{{ route('purchaser.procurement-expenses.index', ['date' => $navDate]) }}" :active="request()->routeIs('purchaser.procurement-expenses.*')" :sub="true">
                            Procurement Expenses
                        </x-nav-item>
                        <x-nav-item href="{{ route('purchasing.invoices.index', ['date' => $navDate]) }}" :active="request()->routeIs('purchasing.invoices.*')" :sub="true">
                            Purchase Invoices
                        </x-nav-item>
                        <x-nav-item href="{{ route('admin.accounting.cash-flow', ['date' => $navDate]) }}" :active="request()->routeIs('admin.accounting.cash-flow')" :sub="true">
                            Cash Flow
                        </x-nav-item>
                    </div>
                </div>
            @endif

            @if(auth()->user()->hasRole('shop'))
                <x-nav-item href="{{ route('purchasing.orders.index') }}" icon="shopping-cart" :active="request()->routeIs('purchasing.orders.*')">
                    Purchase Orders
                </x-nav-item>

                <x-nav-item href="{{ route('inventory.deliveries.dashboard') }}" icon="truck" :active="request()->routeIs('inventory.deliveries.*')">
                    Deliveries
                </x-nav-item>

                <x-nav-item href="{{ route('shop-owner.finance.index') }}" icon="document-currency-dollar" :active="request()->routeIs('shop-owner.finance.*')">
                    Finance
                </x-nav-item>
            @endif

            @if(auth()->user()->hasRole('shop') && auth()->user()->ownedShopAssignments()->exists())
                <x-nav-item href="{{ route('shop-owner.staff.index') }}" icon="document-text" :active="request()->routeIs('shop-owner.staff.*')">
                    Staff Attendance
                </x-nav-item>
            @elseif($canAccessStaffWorkspace && $staffLandingUrl !== null)
                <x-nav-item href="{{ $staffLandingUrl }}" icon="users" :active="request()->routeIs('admin.staff.*')">
                    Staff Management
                </x-nav-item>
            @elseif(auth()->user()->hasRole('purchaser'))
                @php
                    $isPurchaserActive = request()->routeIs('purchaser.*');
                @endphp
                <div class="sidebar-group space-y-1">
                    <button
                        type="button"
                        class="sidebar-group-toggle group flex w-full cursor-pointer items-center justify-between rounded-2xl px-4 py-3 text-sm font-bold transition-all {{ $isPurchaserActive ? 'bg-white text-slate-950 shadow-[0_10px_24px_rgba(15,23,42,0.08)] ring-1 ring-slate-200/80' : 'text-slate-500 hover:bg-white/70 hover:text-slate-950 hover:shadow-sm' }}"
                        aria-expanded="{{ $isPurchaserActive ? 'true' : 'false' }}"
                    >
                        <span class="flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0 opacity-80 transition-opacity group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v13a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6a2 2 0 0 1 2-2Z" />
                            </svg>
                            <span>Purchaser</span>
                        </span>
                        <svg class="chevron-icon h-3.5 w-3.5 transition-transform duration-200 {{ $isPurchaserActive ? 'rotate-90 opacity-100' : 'opacity-50 group-hover:opacity-100' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                    <div class="sidebar-group-items ml-6 space-y-3 border-l border-slate-200 py-1 pl-4 pr-1 transition-all duration-200 {{ $isPurchaserActive ? '' : 'hidden' }}">
                        <div class="space-y-1">
                            <p class="px-3 text-[9px] font-black uppercase tracking-[0.16em] text-slate-400">Today</p>
                            <x-nav-item href="{{ route('purchaser.daily') }}" :active="request()->routeIs('purchaser.daily')" :sub="true">
                                Daily
                            </x-nav-item>
                            <x-nav-item href="{{ route('purchaser.daily-prices') }}" :active="request()->routeIs('purchaser.daily-prices')" :sub="true">
                                Daily Prices
                            </x-nav-item>
                        </div>
                        <div class="space-y-1">
                            <p class="px-3 text-[9px] font-black uppercase tracking-[0.16em] text-slate-400">Orders</p>
                            <x-nav-item href="{{ route('purchaser.shop-orders.index') }}" :active="request()->routeIs('purchaser.shop-orders.*')" :sub="true">
                                Shop Orders
                            </x-nav-item>
                            <x-nav-item href="{{ route('purchaser.add-ons.create') }}" :active="request()->routeIs('purchaser.add-ons.*')" :sub="true">
                                Add-ons
                            </x-nav-item>
                            <x-nav-item href="{{ route('purchaser.vendors') }}" :active="request()->routeIs('purchaser.vendors') || request()->routeIs('purchaser.cart') || request()->routeIs('purchaser.bill')" :sub="true">
                                Daily Carts
                            </x-nav-item>
                            <x-nav-item href="{{ route('purchaser.suppliers') }}" :active="request()->routeIs('purchaser.suppliers.*') || request()->routeIs('purchaser.suppliers')" :sub="true">
                                Vendor Hub
                            </x-nav-item>
                        </div>
                        <div class="space-y-1">
                            <p class="px-3 text-[9px] font-black uppercase tracking-[0.16em] text-slate-400">Money</p>
                            <x-nav-item href="{{ route('purchaser.finance') }}" :active="request()->routeIs('purchaser.finance')" :sub="true">
                                Finance
                            </x-nav-item>
                            <x-nav-item href="{{ route('purchaser.cash') }}" :active="request()->routeIs('purchaser.cash')" :sub="true">
                                Cash
                            </x-nav-item>
                            <x-nav-item href="{{ route('purchaser.procurement-expenses.index', ['date' => $navDate]) }}" :active="request()->routeIs('purchaser.procurement-expenses.*')" :sub="true">
                                Procurement Expenses
                            </x-nav-item>
                        </div>
                        <div class="space-y-1">
                            <p class="px-3 text-[9px] font-black uppercase tracking-[0.16em] text-slate-400">More</p>
                            <x-nav-item href="{{ route('purchaser.history') }}" :active="request()->routeIs('purchaser.history')" :sub="true">
                                Report
                            </x-nav-item>
                            <x-nav-item href="{{ route('purchaser.settings') }}" :active="request()->routeIs('purchaser.settings')" :sub="true">
                                Settings
                            </x-nav-item>
                        </div>
                    </div>
                </div>
            @elseif(auth()->user()->hasRole('warehouse_receiver'))
                @php
                    $isWarehouseReceiverActive = request()->routeIs('warehouse.receiver.*') || request()->routeIs('warehouse.loadout.*');
                @endphp
                <div class="sidebar-group space-y-1">
                    <button
                        type="button"
                        class="sidebar-group-toggle group flex w-full cursor-pointer items-center justify-between rounded-2xl px-4 py-3 text-sm font-bold transition-all {{ $isWarehouseReceiverActive ? 'bg-white text-slate-950 shadow-[0_10px_24px_rgba(15,23,42,0.08)] ring-1 ring-slate-200/80' : 'text-slate-500 hover:bg-white/70 hover:text-slate-950 hover:shadow-sm' }}"
                        aria-expanded="{{ $isWarehouseReceiverActive ? 'true' : 'false' }}"
                    >
                        <span class="flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0 opacity-80 transition-opacity group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 12 3l9 4.5M4.5 8.25v8.25A2.25 2.25 0 006.75 18.75h10.5A2.25 2.25 0 0019.5 16.5V8.25M9 12h6" />
                            </svg>
                            <span>Warehouse Desk</span>
                        </span>
                        <svg class="chevron-icon h-3.5 w-3.5 transition-transform duration-200 {{ $isWarehouseReceiverActive ? 'rotate-90 opacity-100' : 'opacity-50 group-hover:opacity-100' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                    <div class="sidebar-group-items ml-6 space-y-1 border-l border-slate-200 py-1 pl-4 pr-1 transition-all duration-200 {{ $isWarehouseReceiverActive ? '' : 'hidden' }}">
                        <x-nav-item href="{{ route('warehouse.receiver.checklist', ['tab' => 'pending']) }}" :active="request()->routeIs('warehouse.receiver.receive-grn') || (request()->routeIs('warehouse.receiver.checklist') && request()->query('tab', 'pending') === 'pending')" :sub="true" :badge="$wrPendingCount" badge-tone="warning">
                            Receive
                        </x-nav-item>
                        <x-nav-item href="{{ route('warehouse.receiver.checklist', ['tab' => 'inventory']) }}" :active="request()->routeIs('warehouse.receiver.checklist') && request()->query('tab') === 'inventory'" :sub="true" :badge="$wrStockCount" badge-tone="success">
                            Inventory
                        </x-nav-item>
                        <x-nav-item href="{{ route('warehouse.receiver.products.index') }}" :active="request()->routeIs('warehouse.receiver.products.*')" :sub="true">
                            Products
                        </x-nav-item>
                        <x-nav-item href="{{ route('warehouse.loadout.index') }}" :active="request()->routeIs('warehouse.loadout.*')" :sub="true" :badge="$wrLoadoutCount" badge-tone="success">
                            Loadout
                        </x-nav-item>
                        <x-nav-item href="{{ route('warehouse.receiver.sort-sheet.index') }}" :active="request()->routeIs('warehouse.receiver.sort-sheet.*')" :sub="true">
                            Sort Sheet
                        </x-nav-item>
                    </div>
                </div>
            @else
                {{-- Inventory Group --}}
                @if(
                    auth()->user()->can('inventory.product.view') ||
                    auth()->user()->can('inventory.stock.view') ||
                    auth()->user()->can('inventory.sorting.view') ||
                    auth()->user()->can('inventory.wastage.view')
                )
                @php
                    $isInventoryActive = request()->routeIs('inventory.*') || request()->routeIs('warehouse.receiver.*') || request()->routeIs('warehouse.loadout.*');
                @endphp
                <div class="sidebar-group space-y-1">
                    <button
                        type="button"
                        class="sidebar-group-toggle group flex w-full cursor-pointer items-center justify-between rounded-2xl px-4 py-3 text-sm font-bold transition-all {{ $isInventoryActive ? 'bg-white text-slate-950 shadow-[0_10px_24px_rgba(15,23,42,0.08)] ring-1 ring-slate-200/80' : 'text-slate-500 hover:bg-white/70 hover:text-slate-950 hover:shadow-sm' }}"
                        aria-expanded="{{ $isInventoryActive ? 'true' : 'false' }}"
                    >
                        <span class="flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0 opacity-80 transition-opacity group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                            <span>Inventory</span>
                        </span>
                        <svg class="chevron-icon h-3.5 w-3.5 transition-transform duration-200 {{ $isInventoryActive ? 'rotate-90 opacity-100' : 'opacity-50 group-hover:opacity-100' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                    <div class="sidebar-group-items ml-6 space-y-1 border-l border-slate-200 py-1 pl-4 pr-1 transition-all duration-200 {{ $isInventoryActive ? '' : 'hidden' }}">
                        <x-nav-item href="{{ route('inventory.dashboard') }}" :active="request()->routeIs('inventory.dashboard') || request()->routeIs('inventory.deliveries.dashboard')" :sub="true">
                            Dashboard
                        </x-nav-item>
                        <x-nav-item href="{{ route('warehouse.receiver.checklist', ['tab' => 'pending']) }}" :active="request()->routeIs('warehouse.receiver.receive-grn') || (request()->routeIs('warehouse.receiver.checklist') && request()->query('tab', 'pending') === 'pending')" :sub="true">
                            Receive (In)
                        </x-nav-item>
                        <x-nav-item href="{{ route('warehouse.loadout.index') }}" :active="request()->routeIs('warehouse.loadout.*')" :sub="true">
                            Loadout (Out)
                        </x-nav-item>
                    </div>
                </div>
                @endif

                {{-- Purchasing Group --}}
                @if(
                    auth()->user()->hasRole('purchase') ||
                    auth()->user()->can('purchasing.supplier.view') ||
                    auth()->user()->can('purchasing.order.view') ||
                    auth()->user()->can('purchasing.grn.view') ||
                    auth()->user()->can('viewAny', \App\Models\PurchaseInvoice::class)
                )
                @php
                    $isPurchasingActive = request()->routeIs('purchasing.*') || request()->routeIs('requisitions.board') || request()->routeIs('requisitions.approved_board');
                @endphp
                <x-nav-item href="{{ route('purchasing.dashboard') }}" icon="shopping-cart" :active="$isPurchasingActive">
                    Purchasing Dashboard
                </x-nav-item>
                @endif

                {{-- Sales Group --}}
                @if(
                    auth()->user()->can('sales.customer.view') ||
                    auth()->user()->can('sales.invoice.view')
                )
                @php
                    $isSalesActive = request()->routeIs('sales.*');
                @endphp
                <div class="sidebar-group space-y-1">
                    <button
                        type="button"
                        class="sidebar-group-toggle group flex w-full cursor-pointer items-center justify-between rounded-2xl px-4 py-3 text-sm font-bold transition-all {{ $isSalesActive ? 'bg-white text-slate-950 shadow-[0_10px_24px_rgba(15,23,42,0.08)] ring-1 ring-slate-200/80' : 'text-slate-500 hover:bg-white/70 hover:text-slate-950 hover:shadow-sm' }}"
                        aria-expanded="{{ $isSalesActive ? 'true' : 'false' }}"
                    >
                        <span class="flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0 opacity-80 transition-opacity group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                            </svg>
                            <span>Sales</span>
                        </span>
                        <svg class="chevron-icon h-3.5 w-3.5 transition-transform duration-200 {{ $isSalesActive ? 'rotate-90 opacity-100' : 'opacity-50 group-hover:opacity-100' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                    <div class="sidebar-group-items ml-6 space-y-1 border-l border-slate-200 py-1 pl-4 pr-1 transition-all duration-200 {{ $isSalesActive ? '' : 'hidden' }}">
                        @can('sales.customer.view')
                        <x-nav-item href="{{ route('sales.customers.index') }}" :active="request()->routeIs('sales.customers.*')" :sub="true">
                            Shops
                        </x-nav-item>
                        @endcan
                        @can('sales.invoice.view')
                        <x-nav-item href="{{ route('sales.invoices.index') }}" :active="request()->routeIs('sales.invoices.*')" :sub="true">
                            Sales Invoices
                        </x-nav-item>
                        @endcan
                    </div>
                </div>
                @endif

                {{-- Admin Group --}}
                @if(
                    auth()->user()->hasRole('admin') ||
                    auth()->user()->can('admin.user.view') ||
                    auth()->user()->can('admin.daily-progress.view') ||
                    auth()->user()->can('admin.activity-log.view') ||
                    auth()->user()->can('sort.sheet.view')
                )
                @php
                    $isAdminActive = request()->routeIs('admin.*') || request()->routeIs('sort-sheet.*') || request()->routeIs('segregation.*');
                @endphp
                <div class="sidebar-group space-y-1">
                    <button
                        type="button"
                        class="sidebar-group-toggle group flex w-full cursor-pointer items-center justify-between rounded-2xl px-4 py-3 text-sm font-bold transition-all {{ $isAdminActive ? 'bg-white text-slate-950 shadow-[0_10px_24px_rgba(15,23,42,0.08)] ring-1 ring-slate-200/80' : 'text-slate-500 hover:bg-white/70 hover:text-slate-950 hover:shadow-sm' }}"
                        aria-expanded="{{ $isAdminActive ? 'true' : 'false' }}"
                    >
                        <span class="flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0 opacity-80 transition-opacity group-hover:opacity-100" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                            <span>Admin</span>
                        </span>
                        <svg class="chevron-icon h-3.5 w-3.5 transition-transform duration-200 {{ $isAdminActive ? 'rotate-90 opacity-100' : 'opacity-50 group-hover:opacity-100' }}" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                    <div class="sidebar-group-items ml-6 space-y-1 border-l border-slate-200 py-1 pl-4 pr-1 transition-all duration-200 {{ $isAdminActive ? '' : 'hidden' }}">
                        @can('admin.user.view')
                        <x-nav-item href="{{ route('admin.overview') }}" :active="request()->routeIs('admin.overview')" :sub="true">
                            Admin Overview
                        </x-nav-item>
                        @endcan
                        @can('admin.user.view')
                        <x-nav-item href="{{ route('admin.users.index') }}" :active="request()->routeIs('admin.users.*')" :sub="true">
                            Users & Roles
                        </x-nav-item>
                        @endcan
                        @if(auth()->user()->isMainAdmin())
                        <x-nav-item href="{{ route('admin.user-access.index') }}" :active="request()->routeIs('admin.user-access.*')" :sub="true">
                            User Access
                        </x-nav-item>
                        @endif
                        @can('admin.user.view')
                        <x-nav-item href="{{ route('admin.warehouses.index') }}" :active="request()->routeIs('admin.warehouses.*')" :sub="true">
                            Warehouses
                        </x-nav-item>
                        @endcan
                        @can('admin.daily-progress.view')
                        <x-nav-item href="{{ route('admin.daily-progress') }}" :active="request()->routeIs('admin.daily-progress')" :sub="true">
                            Daily Progress
                        </x-nav-item>
                        @endcan
                        @can('admin.activity-log.view')
                        <x-nav-item href="{{ route('admin.activity-logs.index') }}" :active="request()->routeIs('admin.activity-logs.index')" :sub="true">
                            Activity Log
                        </x-nav-item>
                        @endcan
                        @if(auth()->user()->hasRole('admin') || auth()->user()->can('admin.discrepancies.view'))
                        <x-nav-item href="{{ route('admin.discrepancies.index') }}" :active="request()->routeIs('admin.discrepancies.*')" :sub="true">
                            Discrepancies & Wastage
                        </x-nav-item>
                        @endif
                        @can('sort.sheet.view')
                        <x-nav-item href="{{ route('sort-sheet.index') }}" :active="request()->routeIs('sort-sheet.*') || request()->routeIs('segregation.*')" :sub="true">
                            Print Dashboard
                        </x-nav-item>
                        @endcan
                    </div>
                </div>
                @endif
            @endif

        </nav>

        {{-- User footer --}}
        <div class="border-t border-slate-200 px-4 py-4">
            <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-cyan-50">
                    <span class="text-xs font-bold text-cyan-700">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold text-slate-950">{{ auth()->user()->name }}</p>
                    <p class="truncate text-[10px] text-slate-500">{{ auth()->user()->email }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Sign out" class="rounded-xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Sidebar overlay for mobile --}}
    <div id="sidebar-overlay" class="fixed inset-0 z-40 bg-black/50 hidden lg:hidden" aria-hidden="true"></div>

    {{-- ── Main content ─────────────────────────────────────────── --}}
    <div class="main-content-wrapper flex min-w-0 flex-1 flex-col transition-all duration-300 lg:ml-72">

        {{-- Top bar --}}
        <header class="fixed inset-x-2 top-2 z-30 rounded-2xl border border-slate-100 bg-white/95 px-3 py-3 shadow-[0_8px_30px_rgb(0,0,0,0.08)] backdrop-blur-md sm:inset-x-4 sm:px-5 lg:sticky lg:inset-x-0 lg:top-0 lg:left-0 lg:right-0 lg:rounded-none lg:border-0 lg:border-b lg:border-slate-200 lg:bg-white/95 lg:px-6 lg:py-0 lg:shadow-none">
            <div class="flex min-w-0 items-center gap-3 lg:min-h-22 lg:gap-4 lg:py-5">
                {{-- Collapse / open toggle --}}
                <button id="sidebar-open" class="-ml-1 rounded-2xl p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                {{-- Page breadcrumb / title --}}
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-black uppercase tracking-[0.32em] text-slate-400">Green Leaf Traders</p>
                    <h1 class="mt-1 truncate text-base font-black tracking-[-0.03em] text-slate-950 sm:text-lg lg:text-[1.9rem]">{{ $title }}</h1>
                </div>

                {{-- Header Actions Container --}}
                <div class="ml-auto flex shrink-0 items-center gap-2 sm:gap-3">
                    {{-- Theme Toggle Switch --}}
                    <button
                        id="theme-toggle"
                        type="button"
                        class="rounded-2xl p-2 text-slate-500 transition-colors duration-200 cursor-pointer hover:bg-slate-100 hover:text-slate-900 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100"
                        title="Toggle dark/light theme"
                    >
                        {{-- Moon Icon (for Light Mode) --}}
                        <svg id="theme-toggle-moon" class="w-5 h-5 hidden" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75C12.365 15.75 8 11.385 8 5.75c0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                        </svg>
                        {{-- Sun Icon (for Dark Mode) --}}
                        <svg id="theme-toggle-sun" class="w-5 h-5 hidden" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m-9-9h1.5m15 0H21m-2.121-6.879l-1.061 1.061m-10.606 10.606l-1.061 1.061M6.343 6.343l1.061 1.061m10.606 10.606l1.061 1.061M12 7.5a4.5 4.5 0 100 9 4.5 4.5 0 000-9z" />
                        </svg>
                    </button>

                    <details class="relative">
                        <summary class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-sm font-black text-slate-800 transition hover:border-slate-300 hover:bg-white">
                            {{ $currentUserInitial }}
                        </summary>

                        <div class="absolute right-0 top-14 w-52 rounded-3xl border border-slate-200 bg-white p-2 shadow-xl">
                            <div class="border-b border-slate-100 px-3 py-2">
                                <p class="truncate text-sm font-black text-slate-900">{{ $currentUser?->name }}</p>
                                <p class="mt-1 truncate text-[11px] font-semibold text-slate-500">{{ $currentUser?->email }}</p>
                            </div>

                            <div class="mt-2 space-y-1">
                                <a href="{{ route('profile.show') }}" class="flex items-center rounded-2xl px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100">
                                    Profile Update
                                </a>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center rounded-2xl px-3 py-2 text-left text-sm font-bold text-red-600 transition hover:bg-red-50">
                                        Sign Out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </details>
                </div>
            </div>

            @if($showAdminWorkspaceLinks)
                <div class="mt-3 border-t border-slate-100 pt-3 lg:mt-0 lg:border-t lg:pt-4">
                    @include('components.admin-dashboard-switcher')
                </div>
            @endif

            @if(isset($actions))
                <div class="mt-3 border-t border-slate-100 pt-3 lg:mt-0 lg:border-t-0 lg:pt-0">
                    <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                        {{ $actions }}
                    </div>
                </div>
            @endif
        </header>

        {{-- Flash messages --}}
        @if (session('success'))
        <div class="mx-4 mt-4 flex items-center gap-3 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 sm:mx-6" role="alert">
            <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-green-800 text-sm">{{ session('success') }}</p>
        </div>
        @endif

        @if (session('error'))
        <div class="mx-4 mt-4 flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 sm:mx-6" role="alert">
            <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            <p class="text-red-800 text-sm">{{ session('error') }}</p>
        </div>
        @endif

        <x-impersonation-banner />

        {{-- Page content --}}
        <main id="layout-page-main" class="w-full min-w-0 flex-1 px-3 {{ $showMobileBottomNav ? 'pb-40 lg:pb-6' : 'pb-16 lg:pb-6' }} {{ isset($actions) ? 'pt-28' : 'pt-20' }} sm:px-6 lg:p-6 lg:pt-6">
            {{ $slot }}
        </main>
    </div>
</div>

<x-global-footer />

@if($showPageJump)
<x-page-jump-controls bottom-class="bottom-24 lg:bottom-6" />
@endif

@if($showAdminMobileNav || $showStaffMobileNav || $showPurchaseMobileNav || $showPurchaserMobileNav || $showWarehouseReceiverMobileNav)
<div id="layout-mobile-nav" class="fixed inset-x-0 bottom-0 z-[70] px-2 pb-[max(env(safe-area-inset-bottom),0.5rem)] lg:hidden">
    <nav class="mx-auto flex min-h-[64px] w-full max-w-lg items-center gap-1 rounded-2xl border border-slate-100 bg-white/98 px-1.5 py-1.5 shadow-[0_-2px_10px_rgba(15,23,42,0.05),0_10px_40px_rgba(15,23,42,0.16)] backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/96">
        @php
            $mobileNavItems = match(true) {
                $showAdminMobileNav => $adminMobileNavItems,
                $showStaffMobileNav => $staffMobileNavItems,
                $showPurchaseMobileNav => $purchaseMobileNavItems,
                $showPurchaserMobileNav => $purchaserMobileNavItems,
                $showWarehouseReceiverMobileNav => $warehouseReceiverMobileNavItems,
                default => [],
            };
        @endphp
        @foreach ($mobileNavItems as $item)
            @php
                $isActive = $item['active'] ?? false;
                $showLabel = $isActive;
            @endphp
            <a
                href="{{ $item['href'] ?? route($item['route'], $item['params'] ?? []) }}"
                @class([
                    'group relative flex min-h-[50px] min-w-0 flex-1 items-center justify-center gap-1.5 rounded-xl border-0 px-1.5 font-bold transition-all duration-200',
                    'bg-cyan-500 text-white shadow-sm' => $isActive,
                    'bg-transparent text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300' => ! $isActive,
                ])
                title="{{ $item['label'] }}"
            >
                <span class="relative shrink-0 [&_svg]:h-[18px] [&_svg]:w-[18px]">
                    {!! $item['icon'] !!}
                    @if (isset($item['badge']) && $item['badge'] > 0)
                        <span class="absolute -top-1.5 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[8px] font-black text-white ring-1 ring-white">
                            {{ $item['badge'] }}
                        </span>
                    @endif
                </span>
                <span @class([
                    'min-w-0 truncate whitespace-nowrap text-[10px] font-black',
                    'hidden' => ! $showLabel,
                ])>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
</div>
@endif

@include('components.app-dialogs')

<script>
    // Sidebar toggle and collapse logic
    const appContainer = document.getElementById('app-container');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const openBtn = document.getElementById('sidebar-open');
    const closeBtn = document.getElementById('sidebar-close');

    // On load, restore desktop sidebar collapsed state
    if (window.innerWidth >= 1024 && localStorage.getItem('sidebar-collapsed') === 'true') {
        appContainer?.classList.add('sidebar-collapsed');
    }

    function openSidebar() {
        sidebar?.classList.remove('-translate-x-full');
        overlay?.classList.remove('hidden');
    }
    function closeSidebar() {
        sidebar?.classList.add('-translate-x-full');
        overlay?.classList.add('hidden');
    }

    openBtn?.addEventListener('click', () => {
        if (window.innerWidth >= 1024) {
            // Desktop: toggle collapse
            appContainer?.classList.toggle('sidebar-collapsed');
            const isCollapsed = appContainer?.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', isCollapsed ? 'true' : 'false');
        } else {
            // Mobile: open side drawer
            openSidebar();
        }
    });

    closeBtn?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    // Collapsible group logic
    document.querySelectorAll('.sidebar-group-toggle').forEach(button => {
        button.addEventListener('click', () => {
            const group = button.closest('.sidebar-group');
            const items = group.querySelector('.sidebar-group-items');
            const chevron = button.querySelector('.chevron-icon');
            
            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            
            if (isExpanded) {
                button.setAttribute('aria-expanded', 'false');
                items.classList.add('hidden');
                chevron.classList.remove('rotate-90', 'opacity-100');
                chevron.classList.add('opacity-50');
            } else {
                button.setAttribute('aria-expanded', 'true');
                items.classList.remove('hidden');
                chevron.classList.add('rotate-90', 'opacity-100');
                chevron.classList.remove('opacity-50');
            }
        });
    });

    // Theme Toggle Functionality
    const themeToggleBtn = document.getElementById('theme-toggle');
    const moonIcon = document.getElementById('theme-toggle-moon');
    const sunIcon = document.getElementById('theme-toggle-sun');

    if (themeToggleBtn && moonIcon && sunIcon) {
        // Initialize visible icon
        if (document.documentElement.classList.contains('dark')) {
            sunIcon.classList.remove('hidden');
        } else {
            moonIcon.classList.remove('hidden');
        }

        // Toggle theme on click
        themeToggleBtn.addEventListener('click', () => {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                sunIcon.classList.add('hidden');
                moonIcon.classList.remove('hidden');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                moonIcon.classList.add('hidden');
                sunIcon.classList.remove('hidden');
            }
        });
    }

    // Hide mobile bottom nav when any modal/popup is visible
    (() => {
        const mobileNav = document.getElementById('layout-mobile-nav');
        if (!mobileNav) {
            return;
        }

        const checkModals = () => {
            const modals = Array.from(document.querySelectorAll('.fixed.inset-0')).filter(el => {
                if (el === mobileNav || el.id === 'sidebar' || el.id === 'sidebar-overlay') {
                    return false;
                }
                const style = window.getComputedStyle(el);
                return !el.classList.contains('hidden') && style.display !== 'none' && style.visibility !== 'hidden';
            });

            if (modals.length > 0) {
                mobileNav.style.display = 'none';
            } else {
                mobileNav.style.removeProperty('display');
            }
        };

        checkModals();

        const observer = new MutationObserver(() => {
            checkModals();
        });

        observer.observe(document.body, {
            attributes: true,
            subtree: true,
            attributeFilter: ['class', 'style']
        });
    })();
</script>
@stack('scripts')
</body>
</html>
