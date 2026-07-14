@php
/** @var \App\Models\User $user */
$user = auth()->user();
$role = $user->getRoleNames()->first() ?? 'viewer';
$cutoffService = app(\App\Services\Purchasing\PurchaserBusinessDayService::class);
$cutoffTimeLabel = $cutoffService->cutoffLabel();
$cutoffTimeValue = $cutoffService->cutoffInputValue();
$cutoffTimeParts = explode(':', $cutoffTimeValue);
$cutoffHour = (int) ($cutoffTimeParts[0] ?? 21);
$cutoffMinute = (int) ($cutoffTimeParts[1] ?? 30);

$flatProducts = collect();
if (isset($productsByCategory) && $productsByCategory) {
    foreach ($productsByCategory as $cat) {
        $catName = $cat->name;
        foreach ($cat->products as $p) {
            $yesterdayQty = 0;
            $hasYesterdayOrder = isset($yesterdayOrder) && $yesterdayOrder;
            if ($hasYesterdayOrder) {
                $item = $yesterdayOrder->items->first(fn($i) => $i->product_id === $p->id);
                if ($item) {
                    $yesterdayQty = floatval($item->requested_qty);
                }
                $yesterday = $yesterdayQty;
                $suggested = $yesterdayQty;
            } else {
                $yesterday = (($p->id * 7) % 25) + 5; 
                if ($catName === 'Onion' || $p->name === 'Onion') { $yesterday = 100; }
                if ($p->name === 'Potato Agra') { $yesterday = 80; }
                $suggested = $yesterday;
            }

            $flatProducts->push([
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'unit' => $p->unit,
                'category' => $catName,
                'yesterday' => $yesterday,
                'suggested' => $suggested
            ]);
        }
    }
}


$roleConfig = [
    'admin'     => ['label' => 'Administrator',     'color' => 'bg-purple-100 text-purple-700 border-purple-200'],
    'shop'      => ['label' => 'Shop Owner',        'color' => 'bg-emerald-100 text-emerald-700 border-emerald-200'],
    'purchase'  => ['label' => 'Purchase Manager',  'color' => 'bg-amber-100 text-amber-700 border-amber-200'],
    'warehouse_receiver' => ['label' => 'Warehouse Receiver', 'color' => 'bg-cyan-100 text-cyan-700 border-cyan-200'],
];
$rc = $roleConfig[$role] ?? ['label' => ucfirst($role), 'color' => 'bg-gray-100 text-gray-600 border-gray-200'];


/**
 * Module tiles — each shown only if user has the required permission.
 * @var array<int, array{title: string, description: string, href: string, permission: string, icon: string, color: string, badge: string|null}>
 */
$modules = [
    [
        'title'       => 'Products',
        'description' => 'Manage product catalog, SKUs, and categories.',
        'href'        => route('inventory.products.index'),
        'permission'  => 'inventory.product.view',
        'icon'        => 'M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9',
        'color'       => 'bg-brand-50 text-brand-700 border-brand-100',
        'badge'       => null,
    ],
    [
        'title'       => 'Stock Levels',
        'description' => 'View live stock by product and grade.',
        'href'        => route('inventory.stock.index'),
        'permission'  => 'inventory.stock.view',
        'icon'        => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
        'color'       => 'bg-brand-50 text-brand-700 border-brand-100',
        'badge'       => $inventoryStats ? ($inventoryStats['stock_entries'] . ' lines') : null,
    ],
    [
        'title'       => 'Batches & Sorting',
        'description' => 'Receive batches and process grade sorting.',
        'href'        => route('inventory.batches.index'),
        'permission'  => 'inventory.sorting.view',
        'icon'        => 'M9 3.75H6.912a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859M12 3v8.25m0 0l-3-3m3 3l3-3',
        'color'       => 'bg-amber-50 text-amber-700 border-amber-100',
        'badge'       => $inventoryStats && $inventoryStats['pending_batches'] > 0 ? ($inventoryStats['pending_batches'] . ' pending') : null,
    ],
    [
        'title'       => 'Wastage Log',
        'description' => 'Track and record spoiled or damaged stock.',
        'href'        => route('inventory.wastage.index'),
        'permission'  => 'inventory.wastage.view',
        'icon'        => 'M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0',
        'color'       => 'bg-red-50 text-red-600 border-red-100',
        'badge'       => null,
    ],
    [
        'title'       => 'Approve Shop Orders',
        'description' => 'Primary purchasing review board for consolidating shop demand before approvals.',
        'href'        => route('requisitions.board'),
        'permission'  => 'purchasing.order.approve',
        'icon'        => 'M9 5.25h6M9 9.75h6M9 14.25h6M5.25 5.25h.008v.008H5.25V5.25zm0 4.5h.008v.008H5.25V9.75zm0 4.5h.008v.008H5.25V14.25zm-1.5-9A2.25 2.25 0 016 3h12a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0118 21H6a2.25 2.25 0 01-2.25-2.25V5.25z',
        'color'       => 'bg-amber-50 text-amber-700 border-amber-100',
        'badge'       => null,
    ],
    [
        'title'       => 'Approved Board',
        'description' => 'Finalize allocations, supplier selection, and purchase-order generation.',
        'href'        => route('requisitions.approved_board'),
        'permission'  => 'purchasing.order.approve',
        'icon'        => 'M9 12.75 11.25 15 15 9.75m6 2.25a9 9 0 11-18 0 9 9 0 0118 0Z',
        'color'       => 'bg-emerald-50 text-emerald-700 border-emerald-100',
        'badge'       => null,
    ],
    [
        'title'       => 'Purchase Orders',
        'description' => 'Continue supplier buying after board approval and PO generation.',
        'href'        => route('purchasing.orders.index'),
        'permission'  => 'purchasing.order.view',
        'icon'        => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z',
        'color'       => 'bg-amber-50 text-amber-700 border-amber-100',
        'badge'       => $purchasingStats && $purchasingStats['pending_pos'] > 0 ? ($purchasingStats['pending_pos'] . ' pending') : null,
    ],
    [
        'title'       => 'Customers',
        'description' => 'Manage customer records and active status.',
        'href'        => route('sales.customers.index'),
        'permission'  => 'sales.customer.view',
        'icon'        => 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z',
        'color'       => 'bg-blue-50 text-blue-700 border-blue-100',
        'badge'       => $salesStats && $salesStats['active_customers'] > 0 ? ($salesStats['active_customers'] . ' active') : null,
    ],
    [
        'title'       => 'Sales Orders',
        'description' => 'Manage customers, orders, and invoices.',
        'href'        => route('sales.orders.index'),
        'permission'  => 'sales.order.view',
        'icon'        => 'M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 4.5h.008v.008h-.008V13.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
        'color'       => 'bg-blue-50 text-blue-700 border-blue-100',
        'badge'       => $salesStats && $salesStats['pending_sos'] > 0 ? ($salesStats['pending_sos'] . ' pending') : null,
    ],
    [
        'title'       => 'Sales Invoices',
        'description' => 'Generate sales invoices, log payments, and track receivables.',
        'href'        => route('sales.invoices.index'),
        'permission'  => 'sales.invoice.view',
        'icon'        => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v7.5m2.25-6.466a9.016 9.016 0 0 0-3.461-.203c-.536.072-.974.478-1.021 1.017a4.559 4.559 0 0 0-.018.402c0 .464.336.844.775.994l2.95 1.012c.44.15.775.53.775.994 0 .136-.006.27-.018.402-.047.539-.485.945-1.021 1.017a9.077 9.077 0 0 1-3.461-.203M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
        'color'       => 'bg-blue-50 text-blue-700 border-blue-100',
        'badge'       => null,
    ],
    [
        'title'       => 'Users & Roles',
        'description' => 'Manage administrative user accounts, roles, and permissions.',
        'href'        => route('admin.users.index'),
        'permission'  => 'admin.user.view',
        'icon'        => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
        'color'       => 'bg-purple-50 text-purple-700 border-purple-100',
        'badge'       => null,
    ],
    [
        'title'       => 'Accounting',
        'description' => 'Ledgers, reports, and financial entries.',
        'href'        => '#',
        'permission'  => 'accounting.report.view',
        'icon'        => 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'color'       => 'bg-gray-50 text-gray-400 border-gray-100',
        'badge'       => 'Coming Soon',
    ],
];

// Filter to only modules the user has permission to see
$accessibleModules = array_filter($modules, fn ($m) => $user->hasPermissionTo($m['permission']));
@endphp

<x-layouts.app title="Dashboard">

    @if($role === 'shop')
    {{-- ========================================================================= --}}
    {{-- 🏪 SHOP OWNER - OPERATIONS CONTROL CENTER                                   --}}
    {{-- ========================================================================= --}}
    <div class="space-y-6 animate-fade-in" id="shop-dashboard-container">

        {{-- 1. HEADER SECTION --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 bg-slate-950 text-white rounded-3xl p-6 md:p-8 shadow-xl border border-slate-800 relative overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_30%,oklch(0.62_0.17_145_/_0.08),transparent_50%)]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_70%_80%,oklch(0.45_0.20_145_/_0.05),transparent_40%)]"></div>
            <div class="relative z-10 space-y-1">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[10px] font-black text-emerald-400 uppercase tracking-widest">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Live Control Console
                </div>
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight mt-1.5">Good Morning, CASIO HYPERMARKET</h1>
                <p class="text-xs text-slate-400">Green Leaf Distribution Network · Node #SH-8842</p>
            </div>
            <div class="relative z-10 flex flex-wrap items-center gap-4 shrink-0">
                {{-- Daily Status Badge --}}
                <div id="dashboard-status-badge" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-slate-900 border border-slate-800 transition-all duration-300">
                    <span class="w-2.5 h-2.5 rounded-full bg-slate-500" id="badge-dot"></span>
                    <span id="badge-text" class="text-xs font-bold tracking-wide">Checking Requisition Status...</span>
                </div>
                {{-- Deadline Timer --}}
                <div class="inline-flex flex-col bg-slate-900 border border-slate-800 rounded-2xl px-4 py-2 text-center min-w-[130px] shadow-inner">
                    <span class="text-[9px] font-black text-slate-500 tracking-widest uppercase">Order Closes In</span>
                    <span id="deadline-timer-display" class="text-sm font-black text-amber-400 tracking-widest mt-0.5">Calculating...</span>
                </div>
            </div>
        </div>

        {{-- TABS NAVIGATION BAR --}}
        <div class="flex overflow-x-auto border border-slate-200 bg-slate-50/50 p-1.5 rounded-2xl gap-2 w-full shadow-sm select-none scrollbar-none">
            <button type="button" onclick="switchTab('overview')" id="tab-btn-overview" class="inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none bg-white text-slate-900 border border-slate-200 shadow-sm whitespace-nowrap shrink-0">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" /></svg>
                Overview
            </button>
            <button type="button" onclick="switchTab('requisition')" id="tab-btn-requisition" class="inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none text-slate-600 hover:text-slate-900 hover:bg-slate-100/50 whitespace-nowrap shrink-0">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                Tomorrow's Order
                <span id="tab-badge-requisition" class="hidden text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold ml-1">0</span>
            </button>
            <button type="button" onclick="switchTab('approvals')" id="tab-btn-approvals" class="inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none text-slate-600 hover:text-slate-900 hover:bg-slate-100/50 whitespace-nowrap shrink-0">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Manager Approval
                <span id="tab-badge-approvals" class="hidden text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold ml-1">Pending</span>
            </button>
            <button type="button" onclick="switchTab('delivery')" id="tab-btn-delivery" class="inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none text-slate-600 hover:text-slate-900 hover:bg-slate-100/50 whitespace-nowrap shrink-0">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1" /></svg>
                Today's Delivery
            </button>
            <button type="button" onclick="switchTab('shortages')" id="tab-btn-shortages" class="inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none text-slate-600 hover:text-slate-900 hover:bg-slate-100/50 whitespace-nowrap shrink-0">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                Shortage Log
            </button>
            <button type="button" onclick="switchTab('finance')" id="tab-btn-finance" class="inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none text-slate-600 hover:text-slate-900 hover:bg-slate-100/50 whitespace-nowrap shrink-0">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M12 16v1M3 12a9 9 0 1118 0 9 9 0 01-18 0z" /></svg>
                Due Balance
            </button>
        </div>

        {{-- TAB 1: OVERVIEW PANEL --}}
        <div id="tab-panel-overview" class="space-y-6">
            {{-- 1.1 EXECUTIVE PULSE HUD (Instantly answers the 5 critical questions) --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4" id="operations-pulse-hud">
                {{-- Q1: Did I submit tomorrow's order? --}}
                <div onclick="switchTab('requisition')" class="relative bg-white/95 border border-slate-200/90 rounded-2xl p-4 shadow-sm hover:shadow-md hover:border-slate-300 transition-all duration-200 group cursor-pointer">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black text-slate-400 tracking-widest uppercase">Tomorrow's Order</span>
                        <span id="hud-q1-dot" class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                    </div>
                    <p class="text-[10px] text-slate-500 font-medium leading-none">1. Submitted?</p>
                    <p id="hud-q1-val" class="text-sm font-black text-slate-900 mt-1.5">Checking...</p>
                    <p id="hud-q1-sub" class="text-[9px] text-slate-400 font-medium mt-1 leading-none">-</p>
                </div>

                {{-- Q2: Was it approved? --}}
                <div onclick="switchTab('approvals')" class="relative bg-white/95 border border-slate-200/90 rounded-2xl p-4 shadow-sm hover:shadow-md hover:border-slate-300 transition-all duration-200 group cursor-pointer">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black text-slate-400 tracking-widest uppercase">Manager Approval</span>
                        <span id="hud-q2-dot" class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                    </div>
                    <p class="text-[10px] text-slate-500 font-medium leading-none">2. Was it approved?</p>
                    <p id="hud-q2-val" class="text-sm font-black text-slate-900 mt-1.5">Checking...</p>
                    <p id="hud-q2-sub" class="text-[9px] text-slate-400 font-medium mt-1 leading-none">-</p>
                </div>

                {{-- Q3: What am I receiving today? --}}
                <div onclick="switchTab('delivery')" class="relative bg-white/95 border border-slate-200/90 rounded-2xl p-4 shadow-sm hover:shadow-md hover:border-slate-300 transition-all duration-200 group cursor-pointer">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black text-slate-400 tracking-widest uppercase">Today's Delivery</span>
                        <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    </div>
                    <p class="text-[10px] text-slate-500 font-medium leading-none">3. What is coming?</p>
                    <p id="hud-q3-val" class="text-sm font-black text-slate-900 mt-1.5">Checking...</p>
                    <p id="hud-q3-sub" class="text-[9px] text-slate-400 font-medium mt-1 leading-none">-</p>
                </div>

                {{-- Q4: Was there any shortage? --}}
                <div onclick="switchTab('shortages')" class="relative bg-white/95 border border-slate-200/90 rounded-2xl p-4 shadow-sm hover:shadow-md hover:border-slate-300 transition-all duration-200 group cursor-pointer">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black text-slate-400 tracking-widest uppercase">Shortage Log</span>
                        <span id="hud-q4-dot" class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                    </div>
                    <p class="text-[10px] text-slate-500 font-medium leading-none">4. Shortage?</p>
                    <p id="hud-q4-val" class="text-sm font-black text-slate-900 mt-1.5">Checking...</p>
                    <p id="hud-q4-sub" class="text-[9px] text-slate-400 font-medium mt-1 leading-none">-</p>
                </div>

                {{-- Q5: How much do I owe? --}}
                <div onclick="switchTab('finance')" class="relative bg-white/95 border border-slate-200/90 rounded-2xl p-4 shadow-sm hover:shadow-md hover:border-slate-300 transition-all duration-200 group cursor-pointer col-span-2 md:col-span-1">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[9px] font-black text-slate-400 tracking-widest uppercase">Due Balance</span>
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse"></span>
                    </div>
                    <p class="text-[10px] text-slate-500 font-medium leading-none">5. How much I owe?</p>
                    <p id="hud-q5-val" class="text-sm font-black text-red-600 mt-1.5">₹1,24,550</p>
                    <p class="text-[9px] text-red-500 font-semibold mt-1 leading-none">Overdue: 3 days</p>
                </div>
            </div>

            {{-- 2. NOTIFICATION CENTER (Sticky Alert Stack) --}}
            <div class="space-y-2.5" id="notification-center-stack">
                {{-- Rendered dynamically to reflect status transitions and keep UI alive --}}
            </div>

            {{-- 7. RECENT REQUISITIONS HISTORY --}}
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
                <div class="pb-4 border-b border-slate-100 mb-4">
                    <h2 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <svg class="w-5 h-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Recent Requisitions History
                    </h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">Historical overview of daily shop requisitions</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[500px]">
                        <thead>
                            <tr class="border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-2.5">Date</th>
                                <th class="py-2.5 text-right w-[80px]">Items</th>
                                <th class="py-2.5 text-right w-[150px]">Approved Qty</th>
                                <th class="py-2.5 text-center w-[120px]">Status</th>
                                <th class="py-2.5 text-right w-[100px]">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            @forelse($recentRequisitions ?? [] as $requisition)
                                <tr class="hover:bg-slate-50/20">
                                    <td class="py-3 font-semibold text-slate-900">
                                        {{ \Carbon\Carbon::parse($requisition->business_date)->format('d M Y') }}
                                        <span class="block text-[9px] text-slate-400 font-mono mt-0.5">{{ $requisition->order_number }}</span>
                                    </td>
                                    <td class="py-3 text-right">{{ $requisition->items->count() }}</td>
                                    <td class="py-3 text-right font-bold text-slate-900">
                                        {{ $requisition->items->sum('approved_qty') ?: $requisition->items->sum('requested_qty') }} items
                                    </td>
                                    <td class="py-3 text-center">
                                        @if($requisition->state === 'submitted')
                                            <span class="inline-flex items-center bg-blue-50 text-blue-700 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-blue-100">Submitted</span>
                                        @elseif($requisition->state === 'approved')
                                            <span class="inline-flex items-center bg-green-50 text-green-700 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-green-100">Approved</span>
                                        @elseif($requisition->state === 'update_requested')
                                            <span class="inline-flex items-center bg-indigo-50 text-indigo-700 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-indigo-100">Update Req</span>
                                        @elseif($requisition->state === 'rejected')
                                            <span class="inline-flex items-center bg-red-50 text-red-700 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-red-100">Rejected</span>
                                        @else
                                            <span class="inline-flex items-center bg-slate-50 text-slate-700 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-slate-100">Draft</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-right">
                                        <a href="{{ route('requisitions.show', $requisition->order_number) }}" class="text-emerald-600 hover:text-emerald-800 font-bold hover:underline cursor-pointer focus:outline-none">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr class="hover:bg-slate-50/20">
                                    <td class="py-3 font-semibold text-slate-900">25 May 2026</td>
                                    <td class="py-3 text-right">30</td>
                                    <td class="py-3 text-right font-bold text-slate-900">28 items (240 kg)</td>
                                    <td class="py-3 text-center">
                                        <span class="inline-flex items-center bg-green-50 text-green-700 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-green-100">Delivered</span>
                                    </td>
                                    <td class="py-3 text-right">
                                        <span class="text-slate-400 text-xs italic">N/A</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- TAB 2: DAILY REQUISITION SHEET PANEL --}}
        <div id="tab-panel-requisition" class="hidden space-y-6">
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6" id="requisition-builder-card">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100 mb-6">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                            Tomorrow Requisition Sheet
                        </h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">Requisition Delivery: <span class="font-bold text-slate-700">Tomorrow ({{ now()->addDay()->format('d M') }})</span></p>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-4 w-full bg-slate-50 p-3 rounded-2xl border border-slate-200/60 shadow-inner">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider mr-1">Templates:</span>
                            <button type="button" onclick="copyPriorOrder('yesterday')" class="bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold px-3 py-1.5 rounded-xl border border-slate-200 transition-all cursor-pointer focus:outline-none">Copy Yesterday</button>
                                                     <div class="relative inline-block {{ (!isset($presets) || $presets->count() === 0) ? 'hidden' : '' }}" id="custom-presets-dropdown-container">
                                    <select id="select-preset" onchange="applyCustomPreset(this.value)" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1.5 rounded-xl border border-emerald-100 transition-all cursor-pointer focus:outline-none appearance-none pr-8 relative">
                                        <option value="">Apply Custom Preset...</option>
                                        @foreach($presets ?? [] as $preset)
                                            <option value="{{ $preset->id }}">{{ $preset->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-2.5 pointer-events-none text-emerald-800">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                    </div>
                                </div>

                                <a href="{{ route('requisitions.presets.index') }}" class="inline-flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold px-3 py-1.5 rounded-xl transition-all cursor-pointer focus:outline-none border border-slate-200">
                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Custom Lists
                                </a>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" id="update-preset-btn" onclick="updateCurrentPreset()" class="hidden bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-bold px-3.5 py-1.5 rounded-xl border border-amber-100 transition-all cursor-pointer focus:outline-none flex items-center gap-1.5 shadow-sm">
                                    <svg class="w-3.5 h-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                                    Update Preset
                                </button>
                                <button type="button" id="save-new-preset-btn" onclick="saveAsNewPreset()" class="bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-bold px-3.5 py-1.5 rounded-xl border border-blue-100 transition-all cursor-pointer focus:outline-none flex items-center gap-1.5 shadow-sm">
                                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Save as List
                                </button>
                                <button type="button" onclick="clearAllRequisitions()" class="bg-red-50 hover:bg-red-100 text-red-700 text-xs font-bold px-3.5 py-1.5 rounded-xl border border-red-100 transition-all cursor-pointer focus:outline-none flex items-center gap-1.5 shadow-sm">
                                    <svg class="w-3.5 h-3.5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    Clear All
                                </button>
                            </div>  </div>
                    </div>
                </div>

                {{-- Search Box & Autocomplete Dropdown --}}
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 mb-6 relative">
                    <div class="flex items-start gap-2.5 mb-3">
                        <svg class="w-5 h-5 text-slate-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                        <div class="text-xs text-slate-600 leading-normal">
                            <span class="font-bold text-slate-800">Fuzzy Search & Add:</span> Start typing product name or SKU, then select to insert it. Focus is set automatically for quick entry.
                        </div>
                    </div>
                    <div class="relative w-full max-w-xl">
                        <input
                            type="text"
                            id="catalog-search"
                            oninput="searchCatalogProducts()"
                            placeholder="Search product (e.g. Tomato)..."
                            class="w-full text-sm rounded-xl border border-slate-200 bg-white pl-10 pr-4 py-2.5 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/10 transition-all"
                        >
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        
                        {{-- Autocomplete Dropdown List --}}
                        <div id="search-autocomplete-dropdown" class="hidden absolute left-0 right-0 mt-1 max-h-60 overflow-y-auto bg-white border border-slate-200 rounded-xl shadow-lg z-30 divide-y divide-slate-100">
                            {{-- Autocomplete rows --}}
                        </div>
                    </div>
                </div>

                {{-- Requisition Items Container --}}
                <div class="space-y-4">
                    {{-- Empty State (default show when no items added) --}}
                    <div id="requisition-empty-state" class="text-center py-12 border-2 border-dashed border-slate-200 rounded-3xl">
                        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">Your Requisition is Empty</h3>
                        <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Use the templates at the top or search for products above to begin adding items for tomorrow's order.</p>
                    </div>

                    {{-- Table for Added Products (initially hidden until items added) --}}
                    <div id="requisition-table-wrapper" class="hidden overflow-x-auto border border-slate-200 rounded-2xl">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                    <th class="py-3 px-4">Product</th>
                                    <th class="py-3 px-4 text-right w-[100px]">Yesterday</th>
                                    <th class="py-3 px-4 text-right w-[100px]">Suggested</th>
                                    <th class="py-3 px-4 text-center w-[150px]">Qty Input</th>
                                    <th class="py-3 px-4 text-left w-[80px]">Unit</th>
                                    <th class="py-3 px-4 text-center w-[80px]">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs text-slate-700" id="requisition-table-body">
                                {{-- Dynamically rendered row --}}
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Special delivery notes --}}
                <div class="space-y-1.5 mt-6">
                    <label for="order-notes" class="block text-xs font-bold text-slate-700 uppercase tracking-wide">Special Delivery Notes</label>
                    <textarea id="order-notes" rows="2" oninput="saveNotesDraft()" placeholder="e.g. Need fresh quality spinach, deliver first wave if possible..." class="w-full rounded-2xl border border-slate-200 bg-slate-50/20 px-4 py-3 text-xs text-slate-800 placeholder-slate-400 focus:bg-white focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 transition-all"></textarea>
                </div>

                {{-- Summary + Submit --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-4 border-t border-slate-100 mt-6">
                    <div class="text-xs text-slate-500 font-medium">
                        Total items to order: <span id="summary-total-items" class="font-bold text-slate-800">0</span> | Estimated Weight: <span id="summary-total-weight" class="font-bold text-slate-800">0</span> kg
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="discardOrderDraft()" class="text-slate-500 hover:text-slate-800 text-xs font-bold px-4 py-2 cursor-pointer transition-colors focus:outline-none">Discard Draft</button>
                        <button type="button" onclick="submitOrderDraft()" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-6 py-2.5 rounded-xl shadow-md transition-all cursor-pointer focus:outline-none">Submit Requisition</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB 3: MANAGER APPROVAL DETAILS PANEL --}}
        <div id="tab-panel-approvals" class="hidden space-y-6">
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col" id="approval-card">
                <div class="pb-4 border-b border-slate-100 mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Tomorrow Order Details
                        </h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">Line breakdown of manager adjustments for tomorrow ({{ now()->addDay()->format('d M') }})</p>
                    </div>
                    <span id="approval-header-badge" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-slate-200"></span>
                </div>
                
                <div class="overflow-x-auto border border-slate-100 rounded-2xl">
                    <table class="w-full text-left border-collapse min-w-[360px]">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-4">Product</th>
                                <th class="py-3 px-4 text-right">Requested</th>
                                <th class="py-3 px-4 text-right">Approved</th>
                                <th class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700" id="approval-details-tbody">
                            {{-- Injected dynamically based on submission --}}
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 bg-slate-50 border border-slate-100 rounded-2xl p-4 flex gap-3" id="approval-manager-note-box">
                    <svg class="w-5 h-5 text-slate-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" /></svg>
                    <div class="text-xs text-slate-600 leading-normal">
                        <span class="font-bold text-slate-800">Manager Note:</span> <span id="approval-manager-note">Awaiting review...</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB 4: TODAY'S DELIVERY PANEL --}}
        <div id="tab-panel-delivery" class="hidden space-y-6">
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col" id="delivery-card">
                <div class="pb-4 border-b border-slate-100 mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" /></svg>
                            Today's Delivery Check
                        </h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">Verify and log quantities delivered today ({{ now()->format('d M Y') }})</p>
                    </div>
                    <span id="delivery-check-header-status" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold">Pending Verify</span>
                </div>
                
                <div class="overflow-x-auto border border-slate-100 rounded-2xl">
                    <table class="w-full text-left border-collapse min-w-[360px]" id="delivery-check-table">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-4">Product</th>
                                <th class="py-3 px-4 text-right w-[100px]">Approved</th>
                                <th class="py-3 px-4 text-center w-[150px]">Received</th>
                                <th class="py-3 px-4 text-right w-[100px]">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                            <tr class="hover:bg-slate-50/30" id="delivery-check-row-tomato">
                                <td class="py-3 px-4 font-semibold text-slate-900">Tomato H</td>
                                <td class="py-3 px-4 text-right font-medium">10 kg</td>
                                <td class="py-2 px-4 text-center">
                                    <input type="number" id="received-qty-tomato" min="0" value="10" oninput="checkDiscrepancies()" class="w-20 rounded-lg border border-slate-200 px-2.5 py-1 text-center font-black focus:border-emerald-500 focus:outline-none transition-all">
                                </td>
                                <td class="py-3 px-4 text-right font-bold" id="row-status-tomato">
                                    <span class="text-blue-500">Pending</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-slate-50/30" id="delivery-check-row-carrot">
                                <td class="py-3 px-4 font-semibold text-slate-900">Carrot</td>
                                <td class="py-3 px-4 text-right font-medium">10 kg</td>
                                <td class="py-2 px-4 text-center">
                                    <input type="number" id="received-qty-carrot" min="0" value="10" oninput="checkDiscrepancies()" class="w-20 rounded-lg border border-slate-200 px-2.5 py-1 text-center font-black focus:border-emerald-500 focus:outline-none transition-all">
                                </td>
                                <td class="py-3 px-4 text-right font-bold" id="row-status-carrot">
                                    <span class="text-blue-500">Pending</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Discrepancy details wrapper (collapsible) --}}
                <div id="discrepancy-form-block" class="hidden mt-4 pt-4 border-t border-slate-100 space-y-4">
                    <div class="rounded-2xl border border-red-200 bg-red-50/40 p-4 flex gap-3">
                        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                        <div class="space-y-1">
                            <p class="text-xs font-bold text-red-950">Discrepancy Detected</p>
                            <p class="text-[11px] text-red-800 leading-normal">You have specified a difference between approved and received weights. A variance log will be sent for review.</p>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label for="discrepancy-reason" class="block text-[10px] font-bold text-slate-700 uppercase tracking-wide">Reason for discrepancy</label>
                        <select id="discrepancy-reason" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-red-500/10">
                            <option value="missing">Missing quantity (Shortage)</option>
                            <option value="damaged">Damaged goods / Spoiled</option>
                            <option value="wrong_item">Incorrect product delivered</option>
                        </select>
                    </div>
                </div>

                {{-- Action Bar --}}
                <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" onclick="submitDiscrepancyLog()" id="discrepancy-submit-btn" class="hidden bg-red-600 hover:bg-red-700 text-white text-xs font-bold px-5 py-2.5 rounded-xl shadow-sm transition-all cursor-pointer">Submit to Admin</button>
                    <button type="button" onclick="confirmCleanDelivery()" id="delivery-confirm-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold px-5 py-2.5 rounded-xl shadow-sm transition-all cursor-pointer">Received Correctly</button>
                </div>
            </div>
        </div>

        {{-- TAB 5: SHORTAGE LOG PANEL --}}
        <div id="tab-panel-shortages" class="hidden space-y-6">
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6" id="shortage-log-card">
                <div class="pb-4 border-b border-slate-100 mb-4">
                    <h2 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        Shortage & Variance Logs
                    </h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">Logs of shortages reported during Today's Delivery verification</p>
                </div>

                {{-- Empty State --}}
                <div id="shortage-empty-state" class="text-center py-12 border-2 border-dashed border-slate-200 rounded-3xl">
                    <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900">No Shortages Reported Today</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Today's delivery check has either not been run yet, or was confirmed with zero discrepancies.</p>
                </div>

                {{-- Shortage details table --}}
                <div id="shortage-table-wrapper" class="hidden overflow-x-auto border border-slate-200 rounded-2xl">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4">Product</th>
                                <th class="py-3 px-4 text-right w-[100px]">Approved</th>
                                <th class="py-3 px-4 text-right w-[100px]">Received</th>
                                <th class="py-3 px-4 text-right w-[100px]">Shortage</th>
                                <th class="py-3 px-4 text-left w-[120px]">Reason</th>
                                <th class="py-3 px-4 text-center w-[120px]">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700" id="shortage-table-body">
                            {{-- Injected dynamically --}}
                        </tbody>
                    </table>
                    <div class="p-4 bg-slate-50 border-t border-slate-200 flex gap-2.5">
                        <svg class="w-5 h-5 text-amber-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <div class="text-xs text-slate-600 leading-normal">
                            <span class="font-bold text-slate-800">Finance Status:</span> Shortages are reviewed by the accounts team. Approved claims will credit your next day's invoice value automatically.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB 6: DUE BALANCE PANEL --}}
        <div id="tab-panel-finance" class="hidden space-y-6">
            {{-- 6. SECTION FOUR — FINANCIAL SUMMARY --}}
            <div class="space-y-3" id="finance-section">
                <h2 class="text-xs font-black text-slate-400 uppercase tracking-widest">Financial Summary</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {{-- Card 1: Today's Purchase --}}
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-4 shadow-sm">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-700 shrink-0 font-bold text-lg">₹</div>
                        <div>
                            <p class="text-xs text-slate-500 font-semibold">Today's Purchase</p>
                            <p class="text-2xl font-black text-slate-900 mt-0.5">₹18,450</p>
                            <span class="text-[10px] text-slate-400 block mt-1">Based on approved weights</span>
                        </div>
                    </div>

                    {{-- Card 2: Pending Balance --}}
                    <div class="bg-white rounded-2xl border border-red-100 p-5 flex items-start gap-4 shadow-sm">
                        <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-semibold">Pending Balance</p>
                            <p class="text-2xl font-black text-red-700 mt-0.5">₹1,24,550</p>
                            <span class="text-[10px] text-red-500 font-semibold block mt-1">Overdue: 3 days</span>
                        </div>
                    </div>

                    {{-- Card 3: Credit Limit --}}
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-start gap-4 shadow-sm">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" /></svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-semibold">Credit Limit</p>
                            <p class="text-2xl font-black text-slate-900 mt-0.5">₹3,00,000</p>
                            <div class="w-full bg-slate-100 rounded-full h-1.5 mt-2 overflow-hidden shadow-inner">
                                <div class="bg-emerald-600 h-1.5 rounded-full" style="width: 41%;"></div>
                            </div>
                            <span class="text-[10px] text-slate-400 block mt-1.5">Used: <span class="font-bold text-slate-700">41%</span></span>
                        </div>
                    </div>

                    {{-- Card 4: Pending Adjustments --}}
                    <div class="bg-white rounded-2xl border border-amber-100 p-5 flex items-start gap-4 shadow-sm">
                        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 font-semibold">Pending Adjustments</p>
                            <p class="text-2xl font-black text-amber-700 mt-0.5" id="pending-adjustments-value">₹320</p>
                            <span class="text-[10px] text-amber-600 font-semibold block mt-1" id="pending-adjustments-note">Tomato shortage credit pending</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom Browser-Based Alerts & Confirms Modals -->
        <div id="custom-modal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
            <div class="fixed inset-0 bg-slate-950/40 backdrop-blur-[2px] transition-opacity" onclick="closeCustomModal(false)"></div>
            <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl p-6 max-w-sm w-full mx-4 relative z-10 transform scale-95 opacity-0 transition-all duration-200" id="custom-modal-content">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mb-4" id="modal-icon-container">
                    <!-- Dynamic Icon -->
                </div>
                <h3 class="text-base font-extrabold text-slate-900" id="modal-title">Confirm Action</h3>
                <p class="text-xs text-slate-500 mt-2 leading-relaxed" id="modal-body">Are you sure you want to do this?</p>
                <input type="text" id="modal-input" class="hidden w-full text-xs font-bold text-slate-900 bg-slate-50 border border-slate-200 rounded-xl py-2.5 px-3.5 focus:bg-white focus:border-emerald-500 focus:outline-none transition-all mt-4 animate-fade-in" placeholder="Enter name...">
                <div class="mt-6 flex items-center justify-end gap-3">
                    <button onclick="closeCustomModal(false)" class="text-slate-500 hover:text-slate-800 text-xs font-bold px-4 py-2 cursor-pointer transition-colors" id="modal-cancel-btn">Cancel</button>
                    <button onclick="closeCustomModal(true)" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-5 py-2.5 rounded-xl shadow-md cursor-pointer transition-all" id="modal-confirm-btn">Confirm</button>
                </div>
            </div>
        </div>

        <!-- Custom Toast Notifications Container -->
        <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-2.5 max-w-sm w-full pointer-events-none"></div>

    </div>

    {{-- Interactive states script for the Operations Control Center --}}
    <script>
        // Custom Dialog & Toast Utilities
        let modalCallback = null;

        function showCustomConfirm(title, message, callback, type = 'warning') {
            const input = document.getElementById('modal-input');
            if (input) input.classList.add('hidden');

            document.getElementById('modal-title').textContent = title;
            document.getElementById('modal-body').textContent = message;
            document.getElementById('modal-cancel-btn').classList.remove('hidden');
            
            const confirmBtn = document.getElementById('modal-confirm-btn');
            confirmBtn.textContent = 'Confirm';
            
            const iconContainer = document.getElementById('modal-icon-container');
            if (type === 'warning') {
                iconContainer.className = "w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mb-4";
                iconContainer.innerHTML = `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286z" /></svg>`;
                confirmBtn.className = "bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold px-5 py-2.5 rounded-xl shadow-md cursor-pointer transition-colors";
            } else if (type === 'danger') {
                iconContainer.className = "w-12 h-12 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center mb-4";
                iconContainer.innerHTML = `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>`;
                confirmBtn.className = "bg-red-600 hover:bg-red-700 text-white text-xs font-bold px-5 py-2.5 rounded-xl shadow-md cursor-pointer transition-colors";
            }
            
            modalCallback = callback;
            
            const modal = document.getElementById('custom-modal');
            const content = document.getElementById('custom-modal-content');
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 50);
        }

        function showCustomPrompt(title, body, callback, placeholder = 'Enter name...') {
            document.getElementById('modal-title').textContent = title;
            document.getElementById('modal-body').textContent = body;
            document.getElementById('modal-cancel-btn').classList.remove('hidden');
            
            const input = document.getElementById('modal-input');
            if (input) {
                input.value = '';
                input.placeholder = placeholder;
                input.classList.remove('hidden');
                setTimeout(() => input.focus(), 150);
            }
            
            const confirmBtn = document.getElementById('modal-confirm-btn');
            confirmBtn.textContent = 'Save';
            confirmBtn.className = "bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-5 py-2.5 rounded-xl shadow-md cursor-pointer transition-colors";
            
            const iconContainer = document.getElementById('modal-icon-container');
            iconContainer.className = "w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4";
            iconContainer.innerHTML = `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
            
            modalCallback = function(confirmed) {
                const val = input.value;
                input.classList.add('hidden'); // Re-hide input
                if (confirmed) {
                    callback(val);
                }
            };
            
            const modal = document.getElementById('custom-modal');
            const content = document.getElementById('custom-modal-content');
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 50);
        }

        function showCustomAlert(title, message, type = 'success') {
            const input = document.getElementById('modal-input');
            if (input) input.classList.add('hidden');

            document.getElementById('modal-title').textContent = title;
            document.getElementById('modal-body').textContent = message;
            document.getElementById('modal-cancel-btn').classList.add('hidden');
            
            const confirmBtn = document.getElementById('modal-confirm-btn');
            confirmBtn.textContent = 'Okay';
            
            const iconContainer = document.getElementById('modal-icon-container');
            if (type === 'success') {
                iconContainer.className = "w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-4";
                iconContainer.innerHTML = `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>`;
                confirmBtn.className = "bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-5 py-2.5 rounded-xl shadow-md cursor-pointer transition-colors";
            } else if (type === 'warning') {
                iconContainer.className = "w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mb-4";
                iconContainer.innerHTML = `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286z" /></svg>`;
                confirmBtn.className = "bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold px-5 py-2.5 rounded-xl shadow-md cursor-pointer transition-colors";
            } else if (type === 'danger') {
                iconContainer.className = "w-12 h-12 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center mb-4";
                iconContainer.innerHTML = `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
                confirmBtn.className = "bg-red-600 hover:bg-red-700 text-white text-xs font-bold px-5 py-2.5 rounded-xl shadow-md cursor-pointer transition-colors";
            } else {
                iconContainer.className = "w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4";
                iconContainer.innerHTML = `<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
                confirmBtn.className = "bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-5 py-2.5 rounded-xl shadow-md cursor-pointer transition-colors";
            }
            
            modalCallback = null;
            
            const modal = document.getElementById('custom-modal');
            const content = document.getElementById('custom-modal-content');
            modal.classList.remove('hidden');
            setTimeout(() => {
                content.classList.remove('scale-95', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            }, 50);
        }

        function closeCustomModal(confirmed) {
            const modal = document.getElementById('custom-modal');
            const content = document.getElementById('custom-modal-content');
            
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                if (modalCallback) {
                    modalCallback(confirmed);
                    modalCallback = null;
                }
            }, 200);
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            if (!container) return;

            const toast = document.createElement('div');
            let typeClasses = "bg-slate-900 border-slate-800 text-white";
            let icon = `<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;

            if (type === 'success') {
                typeClasses = "bg-emerald-950/95 border-emerald-800 text-emerald-300";
                icon = `<svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
            } else if (type === 'warning') {
                typeClasses = "bg-amber-950/95 border-amber-800 text-amber-300";
                icon = `<svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>`;
            } else if (type === 'danger') {
                typeClasses = "bg-red-950/95 border-red-800 text-red-300";
                icon = `<svg class="w-4 h-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>`;
            }

            toast.className = `flex items-center gap-2.5 border px-4 py-3 rounded-2xl shadow-xl transform translate-y-4 opacity-0 transition-all duration-300 ${typeClasses} pointer-events-auto`;
            toast.innerHTML = `
                <div class="shrink-0">${icon}</div>
                <div class="text-xs font-bold leading-normal">${message}</div>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.remove('translate-y-4', 'opacity-0');
                toast.classList.add('translate-y-0', 'opacity-100');
            }, 50);

            setTimeout(() => {
                toast.classList.remove('translate-y-0', 'opacity-100');
                toast.classList.add('translate-y-2', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        // Constants
        const CUTOFF_HOUR = @json($cutoffHour);
        const CUTOFF_MINUTE = @json($cutoffMinute);

        // Scroll to section helper
        function scrollToSection(id) {
            const el = document.getElementById(id);
            if (el) {
                el.classList.remove('hidden');
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        // Flat Catalog Products from backend
        const ALL_CATALOG_PRODUCTS = @json($flatProducts);

        // Custom Presets from backend
        const CUSTOM_PRESETS = @json($presets ?? []);

        // SVG Status Badge Builder
        function getStatusHtml(label) {
            let svg = '';
            if (label === 'Submitted') {
                svg = `<svg class="w-4 h-4 inline-block mr-1.5 stroke-current align-text-bottom text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
            } else if (label === 'Pending') {
                svg = `<svg class="w-4 h-4 inline-block mr-1.5 stroke-current align-text-bottom text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
            } else if (label === 'Partial Approval') {
                svg = `<svg class="w-4 h-4 inline-block mr-1.5 stroke-current align-text-bottom text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>`;
            } else if (label === 'No') {
                svg = `<svg class="w-4 h-4 inline-block mr-1.5 stroke-current align-text-bottom text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
            } else if (label === 'Checked & Verified') {
                svg = `<svg class="w-4 h-4 inline-block mr-1.5 stroke-current align-text-bottom text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>`;
            } else if (label === 'None') {
                svg = `<svg class="w-4 h-4 inline-block mr-1.5 stroke-current align-text-bottom text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
            } else if (label === 'Discrepancy Logged') {
                svg = `<svg class="w-4 h-4 inline-block mr-1.5 stroke-current align-text-bottom text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 011-1v-4a1 1 0 01.816-.49l3.056-.51a1 1 0 011.184.99V16m-1 1h1m-10 0h6" /></svg>`;
            } else if (label === 'Shortage') {
                svg = `<svg class="w-4 h-4 inline-block mr-1.5 stroke-current align-text-bottom text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>`;
            } else if (label.includes('items')) {
                svg = `<svg class="w-4 h-4 inline-block mr-1.5 stroke-current align-text-bottom text-slate-700" fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 011-1v-4a1 1 0 01.816-.49l3.056-.51a1 1 0 011.184.99V16m-1 1h1m-10 0h6" /></svg>`;
            }
            return svg + label;
        }

        let activeRequisition = {};
        let activePresetId = localStorage.getItem('green_leaf_active_preset_id') ? parseInt(localStorage.getItem('green_leaf_active_preset_id')) : null;

        // Tab Switching logic
        function switchTab(tabId) {
            const tabs = ['overview', 'requisition', 'approvals', 'delivery', 'shortages', 'finance'];
            
            // Toggle panels
            tabs.forEach(t => {
                const panel = document.getElementById(`tab-panel-${t}`);
                const btn = document.getElementById(`tab-btn-${t}`);
                if (panel) {
                    if (t === tabId) {
                        panel.classList.remove('hidden');
                    } else {
                        panel.classList.add('hidden');
                    }
                }
                if (btn) {
                    if (t === tabId) {
                        btn.className = "inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none bg-white text-slate-900 border border-slate-200 shadow-sm whitespace-nowrap shrink-0";
                    } else {
                        btn.className = "inline-flex items-center justify-center gap-2 py-2.5 px-4 text-xs font-bold rounded-xl transition-all cursor-pointer focus:outline-none text-slate-600 hover:text-slate-900 hover:bg-slate-100/50 whitespace-nowrap shrink-0";
                    }
                }
            });

            // Focus on Search Input if switching to Requisition tab
            if (tabId === 'requisition') {
                setTimeout(() => {
                    const searchInput = document.getElementById('catalog-search');
                    if (searchInput) {
                        searchInput.focus();
                    }
                }, 50);
            }
        }

        // Autocomplete Catalog Search
        function searchCatalogProducts() {
            const query = document.getElementById('catalog-search').value.toLowerCase().trim();
            const dropdown = document.getElementById('search-autocomplete-dropdown');
            if (!dropdown) return;

            if (query.length === 0) {
                dropdown.classList.add('hidden');
                dropdown.innerHTML = '';
                return;
            }

            const matches = ALL_CATALOG_PRODUCTS.filter(p => 
                p.name.toLowerCase().includes(query) || 
                p.sku.toLowerCase().includes(query)
            ).slice(0, 10);

            if (matches.length === 0) {
                dropdown.innerHTML = `
                    <div class="px-4 py-3 text-xs text-slate-500 italic">No matching products found</div>
                `;
            } else {
                dropdown.innerHTML = matches.map(product => `
                    <button type="button" onclick="addProductToRequisition('${product.sku}')" class="w-full text-left px-4 py-3 hover:bg-slate-50 flex items-center justify-between transition-colors focus:outline-none focus:bg-slate-50 border-b border-slate-100 last:border-0 cursor-pointer border-0">
                        <div class="flex flex-col">
                            <span class="text-xs font-bold text-slate-800">${product.name}</span>
                            <span class="text-[10px] text-slate-400 font-medium">${product.sku} · ${product.category}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-bold">Suggested: ${product.suggested}${product.unit}</span>
                            <span class="text-[10px] text-emerald-600 font-extrabold">+ Add</span>
                        </div>
                    </button>
                `).join('');
            }

            dropdown.classList.remove('hidden');
        }

        // Add Product to active requisition
        function addProductToRequisition(sku) {
            if (activeRequisition[sku] === undefined) {
                const prod = ALL_CATALOG_PRODUCTS.find(p => p.sku === sku);
                activeRequisition[sku] = prod ? (prod.suggested > 0 ? prod.suggested : 1) : 1;
            }
            renderRequisitionTable();
            saveDraftToLocalStorage();
            document.getElementById('catalog-search').value = '';
            document.getElementById('search-autocomplete-dropdown').classList.add('hidden');
            
            setTimeout(() => {
                const input = document.getElementById(`qty-input-${sku}`);
                if (input) {
                    input.focus();
                    input.select();
                }
            }, 50);
        }

        // Remove Product from active requisition
        function removeProductFromRequisition(sku) {
            delete activeRequisition[sku];
            renderRequisitionTable();
            saveDraftToLocalStorage();
        }

        // Auto-Suggest filler
        function useSuggest(sku, val) {
            const input = document.getElementById('qty-input-' + sku);
            if (input) {
                input.value = val;
                activeRequisition[sku] = val;
                onQuantityChange(sku);
            }
        }

        // Trigger warning on low quantities
        function onQuantityChange(sku) {
            const input = document.getElementById('qty-input-' + sku);
            const warningCol = document.getElementById('warning-' + sku);
            if (!input) return;

            const val = parseFloat(input.value) || 0;
            activeRequisition[sku] = val;

            const suggested = parseFloat(input.getAttribute('data-suggested')) || 0;

            if (warningCol) {
                if (val > 0 && val < suggested * 0.5) {
                    warningCol.innerHTML = `
                        <span class="inline-flex items-center gap-1 text-[10px] text-amber-600 bg-amber-50 px-2 py-0.5 rounded-lg border border-amber-100 font-bold animate-pulse">
                            <svg class="w-3 h-3 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg> Low Qty (Avg: ${suggested})
                        </span>
                    `;
                } else {
                    warningCol.innerHTML = '';
                }
            }

            calculateSheetTotals();
            saveDraftToLocalStorage();
        }

        // Calculate sheet metrics
        function calculateSheetTotals() {
            let itemsCount = 0;
            let totalWeight = 0;

            Object.keys(activeRequisition).forEach(sku => {
                const val = parseFloat(activeRequisition[sku]) || 0;
                if (val > 0) {
                    itemsCount++;
                    totalWeight += val;
                }
            });

            const summaryItems = document.getElementById('summary-total-items');
            const summaryWeight = document.getElementById('summary-total-weight');

            if (summaryItems) summaryItems.textContent = itemsCount;
            if (summaryWeight) summaryWeight.textContent = Math.round(totalWeight);
            
            updateTabBadges();
        }

        // Templates copy helpers
        function copyPriorOrder(type) {
            activeRequisition = {};
            if (type === 'yesterday') {
                ALL_CATALOG_PRODUCTS.forEach(p => {
                    if (p.yesterday > 0) {
                        activeRequisition[p.sku] = p.yesterday;
                    }
                });
            } else if (type === 'lastweek') {
                ALL_CATALOG_PRODUCTS.forEach(p => {
                    if (p.suggested > 0) {
                        activeRequisition[p.sku] = Math.max(0, Math.round(p.suggested * (0.95 + Math.random() * 0.15)));
                    }
                });
            } else if (type === 'favorites') {
                const staples = ['1', '2', '13', '15', '101', '104'];
                ALL_CATALOG_PRODUCTS.forEach(p => {
                    if (staples.includes(p.sku)) {
                        activeRequisition[p.sku] = p.suggested;
                    }
                });
            }

            renderRequisitionTable();
            saveDraftToLocalStorage();
            showToast(`Template applied: "${type.toUpperCase()}". Requisition loaded.`, 'success');
        }

        function copyHistoricalOrder(weightVal) {
            copyPriorOrder('yesterday');
            showToast(`Prior order copied (${weightVal} kg).`, 'success');
        }

        // Apply a custom preset
        function applyCustomPreset(presetId) {
            if (!presetId) {
                activePresetId = null;
                localStorage.removeItem('green_leaf_active_preset_id');
                updatePresetButtonsState();
                return;
            }
            const preset = CUSTOM_PRESETS.find(p => p.id == presetId);
            if (!preset) return;

            activeRequisition = {};
            preset.items.forEach(item => {
                if (item.product && parseFloat(item.quantity) > 0) {
                    activeRequisition[item.product.sku] = parseFloat(item.quantity);
                }
            });

            activePresetId = preset.id;
            localStorage.setItem('green_leaf_active_preset_id', activePresetId);
            updatePresetButtonsState();

            renderRequisitionTable();
            saveDraftToLocalStorage();
            showToast(`Preset "${preset.name}" applied.`, 'success');

            // Reset select dropdown value
            const selectElement = document.getElementById('select-preset');
            if (selectElement) {
                selectElement.value = '';
            }
        }

        // Toggle visibility and content of Update/Save preset buttons
        function updatePresetButtonsState() {
            const updateBtn = document.getElementById('update-preset-btn');
            const saveNewBtn = document.getElementById('save-new-preset-btn');
            
            if (activePresetId) {
                const preset = CUSTOM_PRESETS.find(p => p.id == activePresetId);
                if (preset) {
                    if (updateBtn) {
                        updateBtn.classList.remove('hidden');
                        updateBtn.innerHTML = `
                            <svg class="w-3.5 h-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                            Update "${preset.name}"
                        `;
                    }
                    if (saveNewBtn) {
                        saveNewBtn.innerHTML = `
                            <svg class="w-3.5 h-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Save as New
                        `;
                    }
                } else {
                    activePresetId = null;
                    localStorage.removeItem('green_leaf_active_preset_id');
                    updatePresetButtonsState();
                }
            } else {
                if (updateBtn) {
                    updateBtn.classList.add('hidden');
                }
                if (saveNewBtn) {
                    saveNewBtn.innerHTML = `
                        <svg class="w-3.5 h-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        Save as List
                    `;
                }
            }
        }

        // AJAX update currently selected preset with current requisition items
        function updateCurrentPreset() {
            if (!activePresetId) return;
            const preset = CUSTOM_PRESETS.find(p => p.id == activePresetId);
            if (!preset) return;

            // Formulate items payload
            const itemsArray = Object.keys(activeRequisition).map(sku => {
                const product = ALL_CATALOG_PRODUCTS.find(p => p.sku === sku);
                return {
                    product_id: product ? product.id : null,
                    quantity: activeRequisition[sku]
                };
            }).filter(item => item.product_id !== null && item.quantity > 0);

            if (itemsArray.length === 0) {
                showCustomAlert('Empty Preset', 'Cannot update preset with an empty list of products.', 'warning');
                return;
            }

            fetch(`/requisitions/presets/${activePresetId}`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}",
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    _method: "PUT",
                    name: preset.name,
                    items: itemsArray
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => { throw new Error(err.message || 'Update failed'); });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update cache
                    const index = CUSTOM_PRESETS.findIndex(p => p.id == activePresetId);
                    if (index !== -1) {
                        CUSTOM_PRESETS[index] = data.preset;
                    }
                    showToast(`Preset "${preset.name}" updated successfully with current quantities.`, 'success');
                }
            })
            .catch(error => {
                showToast(error.message, 'danger');
            });
        }

        // AJAX save current requisition as a new preset list
        function saveAsNewPreset() {
            // Formulate items payload
            const itemsArray = Object.keys(activeRequisition).map(sku => {
                const product = ALL_CATALOG_PRODUCTS.find(p => p.sku === sku);
                return {
                    product_id: product ? product.id : null,
                    quantity: activeRequisition[sku]
                };
            }).filter(item => item.product_id !== null && item.quantity > 0);

            if (itemsArray.length === 0) {
                showCustomAlert('Empty Requisition', 'Cannot save an empty requisition as a preset list.', 'warning');
                return;
            }

            showCustomPrompt('Save as Preset List', 'Enter a name for this custom preset list:', function(name) {
                if (!name || name.trim() === '') {
                    showToast('Preset list name is required.', 'warning');
                    return;
                }

                fetch("/requisitions/presets", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({
                        name: name.trim(),
                        items: itemsArray
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => { throw new Error(err.message || 'Creation failed'); });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        CUSTOM_PRESETS.push(data.preset);
                        activePresetId = data.preset.id;
                        localStorage.setItem('green_leaf_active_preset_id', activePresetId);
                        
                        // Update select-preset dropdown dynamically
                        const selectElement = document.getElementById('select-preset');
                        if (selectElement) {
                            const option = document.createElement('option');
                            option.value = data.preset.id;
                            option.textContent = data.preset.name;
                            selectElement.appendChild(option);
                            
                            const container = document.getElementById('custom-presets-dropdown-container');
                            if (container) {
                                container.classList.remove('hidden');
                            }
                        }
                        
                        updatePresetButtonsState();
                        showToast(`Preset list "${data.preset.name}" created successfully.`, 'success');
                    }
                })
                .catch(error => {
                    showToast(error.message, 'danger');
                });
            }, 'e.g. My Favorites');
        }

        // Clear all requisition items
        function clearAllRequisitions() {
            showCustomConfirm(
                'Clear Requisition Sheet',
                'Are you sure you want to clear all items in tomorrow\'s requisition sheet? This action cannot be undone.',
                function(confirmed) {
                    if (confirmed) {
                        activeRequisition = {};
                        activePresetId = null;
                        localStorage.removeItem('green_leaf_active_preset_id');
                        updatePresetButtonsState();
                        renderRequisitionTable();
                        saveDraftToLocalStorage();
                        showToast('Requisition sheet cleared.', 'info');
                    }
                },
                'danger'
            );
        }

        // Save Notes
        function saveNotesDraft() {
            const notes = document.getElementById('order-notes').value;
            localStorage.setItem('green_leaf_notes_draft', notes);
        }

        // Save Draft to LocalStorage
        function saveDraftToLocalStorage() {
            localStorage.setItem('green_leaf_tomorrow_order_items', JSON.stringify(activeRequisition));
        }

        // Discard Draft
        function discardOrderDraft() {
            showCustomConfirm('Discard Draft Requisition', 'Are you sure you want to discard your draft? All inputs will reset. This action cannot be undone.', function(confirmed) {
                if (confirmed) {
                    activeRequisition = {};
                    activePresetId = null;
                    localStorage.removeItem('green_leaf_active_preset_id');
                    updatePresetButtonsState();
                    renderRequisitionTable();
                    saveDraftToLocalStorage();
                    showToast('Requisition draft cleared.', 'info');
                }
            }, 'danger');
        }

        // Submit Requisition
        function submitOrderDraft() {
            let itemsCount = 0;
            let totalWeight = 0;

            Object.keys(activeRequisition).forEach(sku => {
                const val = parseFloat(activeRequisition[sku]) || 0;
                if (val > 0) {
                    itemsCount++;
                    totalWeight += val;
                }
            });

            if (itemsCount === 0) {
                showCustomAlert('Empty Requisition', 'Please enter quantities for at least one item before submitting.', 'warning');
                return;
            }

            // AJAX Submission to persistent database
            fetch("{{ route('requisitions.store') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}",
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    items: activeRequisition
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => { throw new Error(err.error || 'Submission failed'); });
                }
                return response.json();
            })
            .then(data => {
                // Sync LocalStorage for downstream features
                localStorage.setItem('green_leaf_tomorrow_order_status', 'Submitted');
                localStorage.setItem('green_leaf_submitted_time', new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
                localStorage.setItem('green_leaf_submitted_items_count', itemsCount);
                localStorage.setItem('green_leaf_submitted_weight', Math.round(totalWeight));
                localStorage.setItem('green_leaf_submitted_requisition', JSON.stringify(activeRequisition));

                updateDashboardState();
                
                // Redirect user to the persistent showpage
                window.location.href = data.redirect_url;
            })
            .catch(error => {
                showCustomAlert('Submission Error', error.message, 'danger');
            });
        }

        // Today's Delivery verification discrepancies check
        function checkDiscrepancies() {
            const approvedTomato = 10;
            const receivedTomato = parseFloat(document.getElementById('received-qty-tomato').value);

            const approvedCarrot = 10;
            const receivedCarrot = parseFloat(document.getElementById('received-qty-carrot').value);

            const formBlock = document.getElementById('discrepancy-form-block');
            const submitBtn = document.getElementById('discrepancy-submit-btn');
            const confirmBtn = document.getElementById('delivery-confirm-btn');

            const isTomatoValNaN = isNaN(receivedTomato);
            const isCarrotValNaN = isNaN(receivedCarrot);

            if (isTomatoValNaN || isCarrotValNaN) return;

            const isTomatoDiff = receivedTomato !== approvedTomato;
            const isCarrotDiff = receivedCarrot !== approvedCarrot;

            if (isTomatoDiff) {
                const label = receivedTomato < approvedTomato ? 'Shortage' : 'Excess';
                const color = receivedTomato < approvedTomato ? 'text-red-500' : 'text-amber-500';
                document.getElementById('row-status-tomato').innerHTML = `<span class="${color}">${label}</span>`;
            } else {
                document.getElementById('row-status-tomato').innerHTML = `<span class="text-blue-500">Ready</span>`;
            }

            if (isCarrotDiff) {
                const label = receivedCarrot < approvedCarrot ? 'Shortage' : 'Excess';
                const color = receivedCarrot < approvedCarrot ? 'text-red-500' : 'text-amber-500';
                document.getElementById('row-status-carrot').innerHTML = `<span class="${color}">${label}</span>`;
            } else {
                document.getElementById('row-status-carrot').innerHTML = `<span class="text-blue-500">Ready</span>`;
            }

            if (isTomatoDiff || isCarrotDiff) {
                if (formBlock) formBlock.classList.remove('hidden');
                if (submitBtn) submitBtn.classList.remove('hidden');
                if (confirmBtn) confirmBtn.classList.add('hidden');
            } else {
                if (formBlock) formBlock.classList.add('hidden');
                if (submitBtn) submitBtn.classList.add('hidden');
                if (confirmBtn) confirmBtn.classList.remove('hidden');
            }
        }

        // Received Clean Delivery
        function confirmCleanDelivery() {
            localStorage.setItem('green_leaf_today_delivery_status', 'Verified');
            localStorage.setItem('green_leaf_today_delivery_items', JSON.stringify({ tomato: 10, carrot: 10 }));
            localStorage.removeItem('green_leaf_today_discrepancy_reason');
            localStorage.removeItem('green_leaf_today_discrepancy_submitted');

            updateDashboardState();
            showCustomAlert('Delivery Verified', "Today's delivery successfully checked and confirmed as correct.", 'success');
        }

        // Submit Discrepancy Log
        function submitDiscrepancyLog() {
            const receivedTomato = parseFloat(document.getElementById('received-qty-tomato').value) || 0;
            const receivedCarrot = parseFloat(document.getElementById('received-qty-carrot').value) || 0;
            const reason = document.getElementById('discrepancy-reason').value;

            localStorage.setItem('green_leaf_today_delivery_status', 'Discrepancy');
            localStorage.setItem('green_leaf_today_delivery_items', JSON.stringify({ tomato: receivedTomato, carrot: receivedCarrot }));
            localStorage.setItem('green_leaf_today_discrepancy_reason', reason);
            localStorage.setItem('green_leaf_today_discrepancy_submitted', 'true');

            updateDashboardState();
            showCustomAlert('Discrepancy Logged', 'Discrepancy report successfully filed and sent to the administrator. Financial ledger will update.', 'warning');
        }

        // Notification management
        function addNotification(id, type, title, subtitle) {
            const stack = document.getElementById('notification-center-stack');
            if (!stack || document.getElementById(id)) return;

            let bgClass = "bg-slate-50 border-slate-200 text-slate-800";
            let iconSvg = "";
            let btnColor = "text-slate-400 hover:text-slate-700";

            if (type === 'success') {
                bgClass = "bg-emerald-50/90 border-emerald-200 text-emerald-950";
                btnColor = "text-emerald-400 hover:text-emerald-700";
                iconSvg = `<div class="w-7 h-7 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0 text-emerald-700">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                </div>`;
            } else if (type === 'warning') {
                bgClass = "bg-amber-50/90 border-amber-200 text-amber-950";
                btnColor = "text-amber-400 hover:text-amber-700";
                iconSvg = `<div class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center shrink-0 text-amber-700">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.249-8.25-3.286z" /></svg>
                </div>`;
            } else if (type === 'danger') {
                bgClass = "bg-red-50/90 border-red-200 text-red-950";
                btnColor = "text-red-400 hover:text-red-700";
                iconSvg = `<div class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center shrink-0 text-red-700">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>`;
            } else if (type === 'info') {
                bgClass = "bg-blue-50/90 border-blue-200 text-blue-950";
                btnColor = "text-blue-400 hover:text-blue-700";
                iconSvg = `<div class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0 text-blue-700">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>`;
            }

            const alertDiv = document.createElement('div');
            alertDiv.id = id;
            alertDiv.className = `rounded-2xl border ${bgClass} px-4 py-3.5 flex items-start gap-3 shadow-sm hover:shadow-md transition-all duration-200 relative group`;
            alertDiv.innerHTML = `
                ${iconSvg}
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold leading-normal">${title}</p>
                    <p class="text-[11px] leading-relaxed opacity-90 mt-0.5">${subtitle}</p>
                </div>
                <button onclick="dismissAlert('${id}')" class="absolute top-3.5 right-3.5 ${btnColor} cursor-pointer p-0.5 rounded-md focus:outline-none"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button>
            `;

            stack.insertBefore(alertDiv, stack.firstChild);
        }

        function dismissAlert(id) {
            const el = document.getElementById(id);
            if (el) {
                el.classList.add('opacity-0', 'scale-95');
                setTimeout(() => el.remove(), 200);
            }
        }

        // Tab Badges Controller
        function updateTabBadges() {
            const itemsCount = Object.keys(activeRequisition).filter(sku => parseFloat(activeRequisition[sku]) > 0).length;
            const reqBadge = document.getElementById('tab-badge-requisition');
            if (reqBadge) {
                if (itemsCount > 0) {
                    reqBadge.textContent = itemsCount;
                    reqBadge.classList.remove('hidden');
                } else {
                    reqBadge.classList.add('hidden');
                }
            }

            const appBadge = document.getElementById('tab-badge-approvals');
            const orderStatus = localStorage.getItem('green_leaf_tomorrow_order_status') || 'Draft';
            if (appBadge) {
                if (orderStatus === 'Submitted') {
                    appBadge.textContent = 'Pending';
                    appBadge.className = 'text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold ml-1';
                    appBadge.classList.remove('hidden');
                } else if (orderStatus === 'Approved' || orderStatus === 'Draft') {
                    appBadge.textContent = 'Partial';
                    appBadge.className = 'text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold ml-1';
                    appBadge.classList.remove('hidden');
                } else {
                    appBadge.classList.add('hidden');
                }
            }
        }

        // Global State Synchronizer
        function updateDashboardState() {
            const orderStatus = localStorage.getItem('green_leaf_tomorrow_order_status') || 'Draft';
            const submittedTime = localStorage.getItem('green_leaf_submitted_time') || '';
            const itemsCount = localStorage.getItem('green_leaf_submitted_items_count') || '0';
            const weightVal = localStorage.getItem('green_leaf_submitted_weight') || '0';
            
            const deliveryStatus = localStorage.getItem('green_leaf_today_delivery_status') || 'Verified';

            // Clean alerts stack
            const notificationCenterStack = document.getElementById('notification-center-stack');
            if (notificationCenterStack) notificationCenterStack.innerHTML = '';

            // 1. Sync Tomorrow's Order Status Badges & Buttons
            const badge = document.getElementById('dashboard-status-badge');
            const dot = document.getElementById('badge-dot');
            const text = document.getElementById('badge-text');

            const approvalCard = document.getElementById('approval-card');
            const approvalHeaderBadge = document.getElementById('approval-header-badge');
            const approvalDetailsBody = document.getElementById('approval-details-tbody');
            const approvalManagerNote = document.getElementById('approval-manager-note');

            if (orderStatus === 'Submitted') {
                if (dot) dot.className = "w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse";
                if (text) {
                    text.textContent = "Tomorrow Order Submitted";
                    text.className = "text-xs font-bold text-emerald-300";
                }
                if (badge) badge.className = "inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/30 px-4 py-2.5 rounded-2xl";

                // HUD Q1: Submitted?
                document.getElementById('hud-q1-dot').className = "w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse";
                document.getElementById('hud-q1-val').innerHTML = getStatusHtml("Submitted");
                document.getElementById('hud-q1-val').className = "text-sm font-black text-emerald-600 mt-1.5";
                document.getElementById('hud-q1-sub').textContent = `At ${submittedTime}`;

                // HUD Q2: Approved? (Since tomorrow's order is submitted but not reviewed, Q2 is pending)
                document.getElementById('hud-q2-dot').className = "w-2.5 h-2.5 rounded-full bg-amber-500";
                document.getElementById('hud-q2-val').innerHTML = getStatusHtml("Pending");
                document.getElementById('hud-q2-val').className = "text-sm font-black text-amber-500 mt-1.5";
                document.getElementById('hud-q2-sub').textContent = "Awaiting review";

                // Tomorrow Order Details Card (Approval Panel)
                if (approvalCard) approvalCard.classList.remove('hidden');
                if (approvalHeaderBadge) {
                    approvalHeaderBadge.className = "inline-flex items-center bg-amber-50 text-amber-700 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-amber-100";
                    approvalHeaderBadge.textContent = "Awaiting Review";
                }
                if (approvalManagerNote) approvalManagerNote.textContent = "Your requisition is consolidated. Purchase Manager will review and allocate quantities shortly.";

                // Construct approval breakdown table with TBD
                if (approvalDetailsBody) {
                    let rowsHtml = '';
                    const submittedItems = JSON.parse(localStorage.getItem('green_leaf_submitted_requisition')) || {};
                    Object.keys(submittedItems).forEach(sku => {
                        const prod = ALL_CATALOG_PRODUCTS.find(p => p.sku === sku);
                        if (prod) {
                            rowsHtml += `
                                <tr class="hover:bg-slate-50/20">
                                    <td class="py-2.5 px-2 font-semibold text-slate-900">${prod.name}</td>
                                    <td class="py-2.5 px-2 text-right">${submittedItems[sku]} ${prod.unit}</td>
                                    <td class="py-2.5 px-2 text-right font-black text-slate-400">TBD</td>
                                    <td class="py-2.5 px-2 text-center"><span class="text-slate-400 italic">Pending</span></td>
                                </tr>
                            `;
                        }
                    });
                    approvalDetailsBody.innerHTML = rowsHtml || `
                        <tr class="hover:bg-slate-50/20">
                            <td class="py-2.5 px-2 font-semibold text-slate-900">Tomato H</td>
                            <td class="py-2.5 px-2 text-right">15 kg</td>
                            <td class="py-2.5 px-2 text-right font-black text-slate-400">TBD</td>
                            <td class="py-2.5 px-2 text-center"><span class="text-slate-400 italic">Pending</span></td>
                        </tr>
                    `;
                }

                // Add alert
                addNotification('alert-submit-success', 'success', 'Tomorrow requisition submitted successfully', `Draft containing ${itemsCount} items (${weightVal} kg) sent to Purchase Manager at ${submittedTime}.`);
            } else if (orderStatus === 'Approved') {
                if (dot) dot.className = "w-2.5 h-2.5 rounded-full bg-emerald-500";
                if (text) {
                    text.textContent = "Tomorrow Order Approved";
                    text.className = "text-xs font-bold text-emerald-300";
                }
                if (badge) badge.className = "inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/30 px-4 py-2.5 rounded-2xl";

                // HUD Q1: Submitted?
                document.getElementById('hud-q1-dot').className = "w-2.5 h-2.5 rounded-full bg-emerald-500";
                document.getElementById('hud-q1-val').innerHTML = getStatusHtml("Submitted");
                document.getElementById('hud-q1-val').className = "text-sm font-black text-emerald-600 mt-1.5";
                document.getElementById('hud-q1-sub').textContent = `At ${submittedTime}`;

                // HUD Q2: Approved?
                document.getElementById('hud-q2-dot').className = "w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse";
                document.getElementById('hud-q2-val').innerHTML = getStatusHtml("Partial Approval");
                document.getElementById('hud-q2-val').className = "text-xs font-black text-amber-600 mt-2.5";
                document.getElementById('hud-q2-sub').textContent = "Tomato quantity adjusted";

                // Tomorrow Order Details Card (Approval Panel)
                if (approvalCard) approvalCard.classList.remove('hidden');
                if (approvalHeaderBadge) {
                    approvalHeaderBadge.className = "inline-flex items-center bg-amber-50 text-amber-700 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-amber-100";
                    approvalHeaderBadge.textContent = "Partial Approval";
                }
                if (approvalManagerNote) approvalManagerNote.textContent = "Tomato shortage today from farmer market. Adjusted Tomato H to 10 kg. Carrot fully approved. Banana rejected due to quality check.";

                // Construct approval breakdown table with adjustments
                if (approvalDetailsBody) {
                    approvalDetailsBody.innerHTML = `
                        <tr class="hover:bg-slate-50/20">
                            <td class="py-2.5 px-2 font-semibold text-slate-900">Tomato H</td>
                            <td class="py-2.5 px-2 text-right">15 kg</td>
                            <td class="py-2.5 px-2 text-right font-black text-slate-900">10 kg</td>
                            <td class="py-2.5 px-2 text-center">
                                <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-amber-100">
                                    <svg class="w-3 h-3 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    Partial
                                </span>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50/20">
                            <td class="py-2.5 px-2 font-semibold text-slate-900">Carrot</td>
                            <td class="py-2.5 px-2 text-right">10 kg</td>
                            <td class="py-2.5 px-2 text-right font-black text-slate-900">10 kg</td>
                            <td class="py-2.5 px-2 text-center">
                                <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-emerald-100">
                                    <svg class="w-3 h-3 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Approved
                                </span>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50/20">
                            <td class="py-2.5 px-2 font-semibold text-slate-900">Banana Robusta</td>
                            <td class="py-2.5 px-2 text-right">5 box</td>
                            <td class="py-2.5 px-2 text-right font-black text-slate-900">0 box</td>
                            <td class="py-2.5 px-2 text-center">
                                <span class="inline-flex items-center gap-1 bg-red-50 text-red-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-red-100">
                                    <svg class="w-3 h-3 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Rejected
                                </span>
                            </td>
                        </tr>
                    `;
                }

                // Add alerts
                addNotification('alert-approved', 'success', 'Tomorrow order partially approved', 'Tomato H adjusted to 10 kg (Stock shortage from farmer). Banana unavailable due to receiving quality issues.');
            } else {
                // Draft status / Not submitted
                if (dot) dot.className = "w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse";
                if (text) {
                    text.textContent = "Order Not Submitted";
                    text.className = "text-xs font-bold text-red-300";
                }
                if (badge) badge.className = "inline-flex items-center gap-2 bg-red-500/10 border border-red-500/30 px-4 py-2.5 rounded-2xl";

                // HUD Q1: Submitted?
                document.getElementById('hud-q1-dot').className = "w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse";
                document.getElementById('hud-q1-val').innerHTML = getStatusHtml("No");
                document.getElementById('hud-q1-val').className = "text-sm font-black text-red-600 mt-1.5";
                document.getElementById('hud-q1-sub').textContent = "Deadline: {{ $cutoffTimeLabel }}";

                // HUD Q2: Approved? (Show the partial approval of the last order cycle by default)
                document.getElementById('hud-q2-dot').className = "w-2.5 h-2.5 rounded-full bg-amber-500";
                document.getElementById('hud-q2-val').innerHTML = getStatusHtml("Partial Approval");
                document.getElementById('hud-q2-val').className = "text-xs font-black text-amber-600 mt-2.5";
                document.getElementById('hud-q2-sub').textContent = "Tomato quantity adjusted";

                // Tomorrow Order Details Card (Approval Panel)
                // Show the default manager approval details card even if tomorrow's order isn't submitted yet
                if (approvalCard) approvalCard.classList.remove('hidden');
                if (approvalHeaderBadge) {
                    approvalHeaderBadge.className = "inline-flex items-center bg-amber-50 text-amber-700 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-amber-100";
                    approvalHeaderBadge.textContent = "Partial Approval";
                }
                if (approvalManagerNote) approvalManagerNote.textContent = "Tomato shortage today from farmer market. Adjusted Tomato H to 10 kg. Carrot fully approved. Banana rejected due to quality check.";

                // Construct approval breakdown table with adjustments
                if (approvalDetailsBody) {
                    approvalDetailsBody.innerHTML = `
                        <tr class="hover:bg-slate-50/20">
                            <td class="py-2.5 px-2 font-semibold text-slate-900">Tomato H</td>
                            <td class="py-2.5 px-2 text-right">15 kg</td>
                            <td class="py-2.5 px-2 text-right font-black text-slate-900">10 kg</td>
                            <td class="py-2.5 px-2 text-center">
                                <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-amber-100">
                                    <svg class="w-3 h-3 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    Partial
                                </span>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50/20">
                            <td class="py-2.5 px-2 font-semibold text-slate-900">Carrot</td>
                            <td class="py-2.5 px-2 text-right">10 kg</td>
                            <td class="py-2.5 px-2 text-right font-black text-slate-900">10 kg</td>
                            <td class="py-2.5 px-2 text-center">
                                <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-emerald-100">
                                    <svg class="w-3 h-3 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Approved
                                </span>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50/20">
                            <td class="py-2.5 px-2 font-semibold text-slate-900">Banana Robusta</td>
                            <td class="py-2.5 px-2 text-right">5 box</td>
                            <td class="py-2.5 px-2 text-right font-black text-slate-900">0 box</td>
                            <td class="py-2.5 px-2 text-center">
                                <span class="inline-flex items-center gap-1 bg-red-50 text-red-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-red-100">
                                    <svg class="w-3 h-3 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Rejected
                                </span>
                            </td>
                        </tr>
                    `;
                }

                // If past cutoff show warning alert
                const now = new Date();
                if (now.getHours() > CUTOFF_HOUR || (now.getHours() === CUTOFF_HOUR && now.getMinutes() >= CUTOFF_MINUTE)) {
                    addNotification('alert-deadline-closed', 'danger', 'Requisition deadline closed ({{ $cutoffTimeLabel }})', 'Requisitions are now locked. Please contact the Purchasing Manager for permission to submit a late order.');
                } else {
                    addNotification('alert-deadline-warning', 'warning', 'Requisition draft pending submission', 'Complete tomorrow\'s order sheet and click submit before the {{ $cutoffTimeLabel }} cutoff.');
                }
            }

            // 2. Sync Today's Delivery Check Status
            const delHeaderBadge = document.getElementById('delivery-check-header-status');
            const tomatoQtyInput = document.getElementById('received-qty-tomato');
            const carrotQtyInput = document.getElementById('received-qty-carrot');
            const discrepancySubmitBtn = document.getElementById('discrepancy-submit-btn');
            const deliveryConfirmBtn = document.getElementById('delivery-confirm-btn');
            const discrepancyFormBlock = document.getElementById('discrepancy-form-block');

            const storedDelQty = JSON.parse(localStorage.getItem('green_leaf_today_delivery_items')) || { tomato: 10, carrot: 10 };
            if (tomatoQtyInput) tomatoQtyInput.value = storedDelQty.tomato;
            if (carrotQtyInput) carrotQtyInput.value = storedDelQty.carrot;

            // Sync Shortage Table elements
            const shortageEmptyState = document.getElementById('shortage-empty-state');
            const shortageTableWrapper = document.getElementById('shortage-table-wrapper');
            const shortageTableBody = document.getElementById('shortage-table-body');

            if (deliveryStatus === 'Verified') {
                if (delHeaderBadge) {
                    delHeaderBadge.className = "inline-flex items-center bg-emerald-50 text-emerald-700 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-emerald-100";
                    delHeaderBadge.textContent = "Verified";
                }

                if (tomatoQtyInput) tomatoQtyInput.disabled = true;
                if (carrotQtyInput) carrotQtyInput.disabled = true;
                if (discrepancySubmitBtn) discrepancySubmitBtn.classList.add('hidden');
                if (deliveryConfirmBtn) deliveryConfirmBtn.classList.add('hidden');
                if (discrepancyFormBlock) discrepancyFormBlock.classList.add('hidden');

                const rst = document.getElementById('row-status-tomato');
                const rsc = document.getElementById('row-status-carrot');
                if (rst) rst.innerHTML = `<span class="text-emerald-600 font-bold flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg> Verified</span>`;
                if (rsc) rsc.innerHTML = `<span class="text-emerald-600 font-bold flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg> Verified</span>`;

                // HUD Q3: Today's Delivery
                document.getElementById('hud-q3-val').innerHTML = getStatusHtml("Checked & Verified");
                document.getElementById('hud-q3-val').className = "text-sm font-black text-emerald-600 mt-1.5";
                document.getElementById('hud-q3-sub').textContent = "All quantities match";

                // HUD Q4: Shortage Alert?
                document.getElementById('hud-q4-dot').className = "w-2.5 h-2.5 rounded-full bg-emerald-500";
                document.getElementById('hud-q4-val').innerHTML = getStatusHtml("None");
                document.getElementById('hud-q4-val').className = "text-sm font-black text-emerald-600 mt-1.5";
                document.getElementById('hud-q4-sub').textContent = "Verified correctly";

                // Shortage table elements state
                if (shortageEmptyState) shortageEmptyState.classList.remove('hidden');
                if (shortageTableWrapper) shortageTableWrapper.classList.add('hidden');

                // Finance adjust
                const padj = document.getElementById('pending-adjustments-value');
                const padjn = document.getElementById('pending-adjustments-note');
                if (padj) padj.textContent = "₹0";
                if (padjn) padjn.textContent = "No pending adjustments";
            } else if (deliveryStatus === 'Discrepancy') {
                if (delHeaderBadge) {
                    delHeaderBadge.className = "inline-flex items-center bg-red-50 text-red-700 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-red-100";
                    delHeaderBadge.textContent = "Shortage Logged";
                }

                if (tomatoQtyInput) tomatoQtyInput.disabled = true;
                if (carrotQtyInput) carrotQtyInput.disabled = true;
                if (discrepancySubmitBtn) discrepancySubmitBtn.classList.add('hidden');
                if (deliveryConfirmBtn) deliveryConfirmBtn.classList.add('hidden');
                if (discrepancyFormBlock) discrepancyFormBlock.classList.add('hidden');

                const tomDiff = 10 - storedDelQty.tomato;
                const carDiff = 10 - storedDelQty.carrot;

                const rst = document.getElementById('row-status-tomato');
                const rsc = document.getElementById('row-status-carrot');
                if (rst) {
                    if (tomDiff !== 0) {
                        rst.innerHTML = `<span class="text-red-600 font-bold">${storedDelQty.tomato}kg (Short: ${tomDiff}kg)</span>`;
                    } else {
                        rst.innerHTML = `<span class="text-emerald-600 font-bold">Verified</span>`;
                    }
                }

                if (rsc) {
                    if (carDiff !== 0) {
                        rsc.innerHTML = `<span class="text-red-600 font-bold">${storedDelQty.carrot}kg (Short: ${carDiff}kg)</span>`;
                    } else {
                        rsc.innerHTML = `<span class="text-emerald-600 font-bold">Verified</span>`;
                    }
                }

                // HUD Q3: Today's Delivery
                document.getElementById('hud-q3-val').innerHTML = getStatusHtml("Discrepancy Logged");
                document.getElementById('hud-q3-val').className = "text-xs font-black text-red-600 mt-2.5";
                document.getElementById('hud-q3-sub').textContent = "Under admin review";

                // HUD Q4: Shortage Alert?
                document.getElementById('hud-q4-dot').className = "w-2.5 h-2.5 rounded-full bg-red-500 animate-pulse";
                document.getElementById('hud-q4-val').innerHTML = getStatusHtml("Shortage");
                document.getElementById('hud-q4-val').className = "text-sm font-black text-red-600 mt-1.5";
                document.getElementById('hud-q4-sub').textContent = `${tomDiff > 0 ? 'Tomato ' : ''}${carDiff > 0 ? 'Carrot ' : ''}shortage logged`;

                // Shortage table elements state
                if (shortageEmptyState) shortageEmptyState.classList.add('hidden');
                if (shortageTableWrapper) shortageTableWrapper.classList.remove('hidden');
                if (shortageTableBody) {
                    let tableContent = '';
                    const reason = localStorage.getItem('green_leaf_today_discrepancy_reason') || 'missing';
                    if (tomDiff > 0) {
                        tableContent += `
                            <tr class="hover:bg-slate-50/20">
                                <td class="py-3 px-4 font-semibold text-slate-900">${new Date().toLocaleDateString()}</td>
                                <td class="py-3 px-4 font-semibold text-slate-900">Tomato H</td>
                                <td class="py-3 px-4 text-right">10 kg</td>
                                <td class="py-3 px-4 text-right">${storedDelQty.tomato} kg</td>
                                <td class="py-3 px-4 text-right text-red-600 font-bold">${tomDiff} kg</td>
                                <td class="py-3 px-4 text-left font-medium text-slate-500">${reason.toUpperCase()}</td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-flex items-center bg-amber-50 text-amber-700 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-amber-100">Pending Review</span>
                                </td>
                            </tr>
                        `;
                    }
                    if (carDiff > 0) {
                        tableContent += `
                            <tr class="hover:bg-slate-50/20">
                                <td class="py-3 px-4 font-semibold text-slate-900">${new Date().toLocaleDateString()}</td>
                                <td class="py-3 px-4 font-semibold text-slate-900">Carrot</td>
                                <td class="py-3 px-4 text-right">10 kg</td>
                                <td class="py-3 px-4 text-right">${storedDelQty.carrot} kg</td>
                                <td class="py-3 px-4 text-right text-red-600 font-bold">${carDiff} kg</td>
                                <td class="py-3 px-4 text-left font-medium text-slate-500">${reason.toUpperCase()}</td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-flex items-center bg-amber-50 text-amber-700 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-amber-100">Pending Review</span>
                                </td>
                            </tr>
                        `;
                    }
                    shortageTableBody.innerHTML = tableContent;
                }

                // Finance adjust (shortage adds credit)
                const padj = document.getElementById('pending-adjustments-value');
                const padjn = document.getElementById('pending-adjustments-note');
                if (padj) padj.textContent = "₹640";
                if (padjn) padjn.textContent = "Tomato shortage + variance credit pending";

                // Add alert
                const reason = localStorage.getItem('green_leaf_today_discrepancy_reason') || 'missing';
                addNotification('alert-discrepancy-logged', 'danger', 'Discrepancy reported under review', `Variance logged: Tomato H received: ${storedDelQty.tomato} kg (Approved: 10 kg). Reason: ${reason.toUpperCase()}. Ledger adjustment pending confirmation.`);
            } else {
                // Pending
                if (delHeaderBadge) {
                    delHeaderBadge.className = "inline-flex items-center bg-indigo-50 text-indigo-700 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-indigo-100 animate-pulse";
                    delHeaderBadge.textContent = "Awaiting Verify";
                }

                if (tomatoQtyInput) tomatoQtyInput.disabled = false;
                if (carrotQtyInput) carrotQtyInput.disabled = false;
                if (deliveryConfirmBtn) deliveryConfirmBtn.classList.remove('hidden');

                // HUD Q3: Today's Delivery
                document.getElementById('hud-q3-val').innerHTML = getStatusHtml("2 items (20 kg)");
                document.getElementById('hud-q3-val').className = "text-sm font-black text-slate-900 mt-1.5";
                document.getElementById('hud-q3-sub').textContent = "Check truck delivery";

                // HUD Q4: Shortage Alert?
                document.getElementById('hud-q4-dot').className = "w-2.5 h-2.5 rounded-full bg-slate-300";
                document.getElementById('hud-q4-val').innerHTML = getStatusHtml("Pending");
                document.getElementById('hud-q4-val').className = "text-sm font-black text-slate-400 mt-1.5";
                document.getElementById('hud-q4-sub').textContent = "Run delivery check";

                if (shortageEmptyState) shortageEmptyState.classList.remove('hidden');
                if (shortageTableWrapper) shortageTableWrapper.classList.add('hidden');

                checkDiscrepancies();
            }

            // Sync Tab Badges
            updateTabBadges();
        }

        // Live countdown clock to the business-day cutoff
        function updateDeadlineTimer() {
            const now = new Date();
            const target = new Date();
            target.setHours(CUTOFF_HOUR, CUTOFF_MINUTE, 0, 0);

            const diffMs = target - now;

            if (diffMs < 0) {
                const display = document.getElementById('deadline-timer-display');
                if (display) {
                    display.textContent = "Order Closed";
                    display.className = "text-sm font-black text-red-500 tracking-wider mt-0.5 animate-pulse";
                }
            } else {
                const hours = Math.floor(diffMs / 3600000);
                const minutes = Math.floor((diffMs % 3600000) / 60000);
                const hrsStr = String(hours).padStart(2, '0');
                const minsStr = String(minutes).padStart(2, '0');
                const display = document.getElementById('deadline-timer-display');
                if (display) {
                    display.textContent = `${hrsStr}h ${minsStr}m`;
                }
            }
        }

        // Render Requisition Table helper
        function renderRequisitionTable() {
            const tbody = document.getElementById('requisition-table-body');
            const emptyState = document.getElementById('requisition-empty-state');
            const tableWrapper = document.getElementById('requisition-table-wrapper');
            
            if (!tbody || !emptyState || !tableWrapper) return;

            const skus = Object.keys(activeRequisition);
            if (skus.length === 0) {
                emptyState.classList.remove('hidden');
                tableWrapper.classList.add('hidden');
                calculateSheetTotals();
                return;
            }

            emptyState.classList.add('hidden');
            tableWrapper.classList.remove('hidden');
            tbody.innerHTML = '';

            skus.forEach(sku => {
                const product = ALL_CATALOG_PRODUCTS.find(p => p.sku === sku);
                if (!product) return;

                const row = document.createElement('tr');
                row.className = 'hover:bg-slate-50/20';
                row.id = `row-${sku}`;
                
                row.innerHTML = `
                    <td class="py-3 px-4">
                        <div class="flex flex-col">
                            <span class="font-bold text-slate-900">${product.name}</span>
                            <span class="text-[10px] text-slate-400 font-bold">${product.sku} · ${product.category}</span>
                            <div id="warning-${sku}" class="mt-1"></div>
                        </div>
                    </td>
                    <td class="py-3 px-4 text-right font-medium">${product.yesterday}</td>
                    <td class="py-3 px-4 text-right font-medium">
                        <button type="button" onclick="useSuggest('${sku}', ${product.suggested})" class="text-indigo-600 hover:text-indigo-800 font-bold hover:underline cursor-pointer focus:outline-none border-0">
                            ${product.suggested}
                        </button>
                    </td>
                    <td class="py-2 px-4 text-center">
                        <input type="number" id="qty-input-${sku}" min="0" value="${activeRequisition[sku]}" oninput="onQuantityChange('${sku}')" data-suggested="${product.suggested}" class="w-20 rounded-lg border border-slate-200 px-2.5 py-1 text-center font-black focus:border-emerald-500 focus:outline-none transition-all">
                    </td>
                    <td class="py-3 px-4 text-left font-medium text-slate-500">${product.unit}</td>
                    <td class="py-3 px-4 text-center">
                        <button type="button" onclick="removeProductFromRequisition('${sku}')" class="text-red-500 hover:text-red-700 font-bold cursor-pointer focus:outline-none border-0">
                            Delete
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
                
                // Trigger low quantity warnings on load/render
                const val = parseFloat(activeRequisition[sku]) || 0;
                const suggested = product.suggested;
                const warningCol = row.querySelector(`#warning-${sku}`);
                if (warningCol && val > 0 && val < suggested * 0.5) {
                    warningCol.innerHTML = `
                        <span class="inline-flex items-center gap-1 text-[10px] text-amber-600 bg-amber-50 px-2 py-0.5 rounded-lg border border-amber-100 font-bold animate-pulse">
                            <svg class="w-3 h-3 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg> Low Qty (Avg: ${suggested})
                        </span>
                    `;
                }
            });

            calculateSheetTotals();
        }

        // Close dropdown when clicking outside search area
        document.addEventListener('click', function(e) {
            const searchInput = document.getElementById('catalog-search');
            const dropdown = document.getElementById('search-autocomplete-dropdown');
            if (searchInput && dropdown && !searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // Initial setup on DOM Ready
        document.addEventListener('DOMContentLoaded', () => {
            // Restore notes
            const notes = localStorage.getItem('green_leaf_notes_draft');
            if (notes) {
                const notesTextarea = document.getElementById('order-notes');
                if (notesTextarea) notesTextarea.value = notes;
            }

            // Restore active requisition
            activeRequisition = JSON.parse(localStorage.getItem('green_leaf_tomorrow_order_items')) || {};
            renderRequisitionTable();

            // Restore active preset ID and update buttons
            activePresetId = localStorage.getItem('green_leaf_active_preset_id') ? parseInt(localStorage.getItem('green_leaf_active_preset_id')) : null;
            updatePresetButtonsState();

            updateDeadlineTimer();
            setInterval(updateDeadlineTimer, 60000); // refresh every minute

            // Ensure today's delivery status defaults to Verified if not set
            if (!localStorage.getItem('green_leaf_today_delivery_status')) {
                localStorage.setItem('green_leaf_today_delivery_status', 'Verified');
            }

            updateDashboardState();

            // Hash-based tab switching
            const hash = window.location.hash;
            if (hash) {
                const tabName = hash.replace('#', '');
                const validTabs = ['overview', 'requisition', 'approvals', 'delivery', 'shortages', 'finance'];
                if (validTabs.includes(tabName)) {
                    switchTab(tabName);
                }
            }
        });
    </script>
    @else
    {{-- ========================================================================= --}}
    {{-- 🏢 DEFAULT ERP DASHBOARD FOR MANAGEMENT/STAFF                              --}}
    {{-- ========================================================================= --}}

    {{-- Welcome banner --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', $user->name)[0] }} 👋</h1>
            <p class="text-sm text-gray-500 mt-0.5">Here's what's happening in Green Leaf Traders today.</p>
        </div>
        <span class="self-start sm:self-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-semibold {{ $rc['color'] }}">
            <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
            {{ $rc['label'] }}
        </span>
    </div>

    {{-- Warehouse daily operational window: Receive Goods to Start Process --}}
    @if($user->hasRole(['warehouse_receiver', 'admin']))
    <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-5 border-b border-gray-100">
            <div>
                <h2 class="text-base font-black text-slate-800 tracking-tight flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500 animate-pulse"></span>
                    Warehouse Receiver Desk: Start With Goods Receive
                </h2>
                <p class="text-xs text-slate-400 mt-1">Receive approved purchase orders into the warehouse to automatically populate daily stock batches and checklists.</p>
            </div>
            <a href="{{ route('warehouse.receiver.checklist', ['tab' => 'pending']) }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-black text-white hover:bg-brand-700 transition-colors shadow-sm cursor-pointer">
                Open Warehouse Desk
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 pt-5">
            @forelse($pendingPOsForReceipt ?? [] as $po)
                <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-5 flex flex-col justify-between hover:shadow-xs transition-all">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h4 class="text-sm font-black text-slate-800">{{ $po->po_number }}</h4>
                                <p class="text-[10px] text-slate-500 font-bold mt-0.5">{{ $po->supplier->name }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-blue-50 text-blue-700 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider border border-blue-200">
                                Approved
                            </span>
                        </div>
                        <div class="divide-y divide-slate-100 mt-4">
                            @foreach($po->items->take(3) as $poItem)
                                <div class="py-1.5 flex items-center justify-between text-[10px]">
                                    <span class="font-bold text-slate-600 truncate max-w-[150px]">{{ $poItem->product->name }}</span>
                                    <span class="font-mono text-slate-500">{{ number_format((float)$poItem->quantity, 2) }} {{ $poItem->purchase_unit }}</span>
                                </div>
                            @endforeach
                            @if($po->items->count() > 3)
                                <div class="pt-1.5 text-[9px] font-black text-slate-400 text-right">+{{ $po->items->count() - 3 }} more items</div>
                            @endif
                        </div>
                    </div>
                    <div class="mt-5 pt-3 border-t border-slate-100/50 flex justify-end">
                        <a href="{{ route('warehouse.receiver.checklist', ['tab' => 'pending']) }}" class="px-3.5 py-1.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-[10px] font-bold shadow-xs transition-colors cursor-pointer text-center">
                            Receive & Verify Goods
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-8 text-center bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
                    <div class="w-10 h-10 rounded-2xl bg-white flex items-center justify-center mx-auto mb-2 text-slate-400 shadow-xs">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    </div>
                    <p class="text-xs font-bold text-slate-700">All Goods Received</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">There are no approved purchase orders awaiting receipt today.</p>
                </div>
            @endforelse
        </div>
    </div>
    @endif

    {{-- Inventory stats row (only for users with inventory access) --}}
    @if($inventoryStats)
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        {{-- Pending Batches --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859M12 3v8.25m0 0l-3-3m3 3l3-3" />
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Pending Batches</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ $inventoryStats['pending_batches'] }}</p>
                @if($inventoryStats['pending_batches'] > 0)
                <a href="{{ route('inventory.batches.index') }}" class="text-xs text-amber-600 font-medium hover:underline mt-0.5 block">Sort now →</a>
                @endif
            </div>
        </div>

        {{-- Active Products --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-brand-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Active Products</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ $inventoryStats['total_products'] }}</p>
                <a href="{{ route('inventory.products.index') }}" class="text-xs text-brand-600 font-medium hover:underline mt-0.5 block">View catalog →</a>
            </div>
        </div>

        {{-- Stock Lines --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Stock Grade Lines</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ $inventoryStats['stock_entries'] }}</p>
                <a href="{{ route('inventory.stock.index') }}" class="text-xs text-green-600 font-medium hover:underline mt-0.5 block">View stock →</a>
            </div>
        </div>

        {{-- Today's Wastage Cost --}}
        <div class="bg-white rounded-2xl border {{ $inventoryStats['today_wastage'] > 0 ? 'border-red-200' : 'border-gray-200' }} p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl {{ $inventoryStats['today_wastage'] > 0 ? 'bg-red-100' : 'bg-gray-100' }} flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 {{ $inventoryStats['today_wastage'] > 0 ? 'text-red-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Today's Wastage</p>
                <p class="text-2xl font-bold {{ $inventoryStats['today_wastage'] > 0 ? 'text-red-700' : 'text-gray-900' }} mt-0.5">
                    INR {{ number_format($inventoryStats['today_wastage'], 2) }}
                </p>
                <a href="{{ route('inventory.wastage.index') }}" class="text-xs text-gray-500 font-medium hover:underline mt-0.5 block">View log →</a>
            </div>
        </div>

    </div>
    @endif

    {{-- Purchasing stats row (only for users with purchasing access) --}}
    @if($purchasingStats)
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

        {{-- Approved shop orders --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5m-16.5 4.5h16.5m-16.5 4.5h10.5M6.75 3.75h10.5a3 3 0 0 1 3 3v10.5a3 3 0 0 1-3 3H6.75a3 3 0 0 1-3-3V6.75a3 3 0 0 1 3-3Z" />
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Approved Shop Orders</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ $purchasingStats['pending_pos'] }}</p>
                @can('purchasing.order.view')
                <a href="{{ route('requisitions.approved_board') }}" class="text-xs text-amber-600 font-medium hover:underline mt-0.5 block">Open approved board →</a>
                @endcan
            </div>
        </div>

        {{-- Pending Purchase Orders --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Pending POs</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ $purchasingStats['pending_pos'] }}</p>
                @can('purchasing.order.view')
                <a href="{{ route('purchasing.orders.index') }}" class="text-xs text-amber-600 font-medium hover:underline mt-0.5 block">View orders →</a>
                @endcan
            </div>
        </div>

        {{-- Monthly Purchases --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-teal-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Monthly Procurement</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">INR {{ number_format($purchasingStats['monthly_purchases'], 2) }}</p>
                @can('viewAny', \App\Models\ShopInvoice::class)
                <a href="{{ route('purchasing.shop-invoices.index') }}" class="text-xs text-teal-600 font-medium hover:underline mt-0.5 block">View shop invoices →</a>
                @endcan
            </div>
        </div>

    </div>
    @endif

    {{-- Sales stats row (only for users with sales access) --}}
    @if($salesStats)
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

        {{-- Active Customers --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Active Customers</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ $salesStats['active_customers'] }}</p>
                @can('sales.customer.view')
                <a href="{{ route('sales.customers.index') }}" class="text-xs text-blue-600 font-medium hover:underline mt-0.5 block">View customers →</a>
                @endcan
            </div>
        </div>

        {{-- Pending Sales Orders --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 4.5h.008v.008h-.008V13.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Pending Sales Orders</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ $salesStats['pending_sos'] }}</p>
                @can('sales.order.view')
                <a href="{{ route('sales.orders.index') }}" class="text-xs text-blue-600 font-medium hover:underline mt-0.5 block">View orders →</a>
                @endcan
            </div>
        </div>

        {{-- Monthly Sales --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v7.5m2.25-6.466a9.016 9.016 0 0 0-3.461-.203c-.536.072-.974.478-1.021 1.017a4.559 4.559 0 0 0-.018.402c0 .464.336.844.775.994l2.95 1.012c.44.15.775.53.775.994 0 .136-.006.27-.018.402-.047.539-.485.945-1.021 1.017a9.077 9.077 0 0 1-3.461-.203M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Monthly Sales</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">INR {{ number_format($salesStats['monthly_sales'], 2) }}</p>
                @can('sales.invoice.view')
                <a href="{{ route('sales.invoices.index') }}" class="text-xs text-green-600 font-medium hover:underline mt-0.5 block">View invoices →</a>
                @endcan
            </div>
        </div>

    </div>
    @endif

    {{-- Quick actions for inventory roles --}}
    @if($user->hasPermissionTo('inventory.sorting.process') && $inventoryStats && $inventoryStats['pending_batches'] > 0)
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6 flex items-center gap-4">
        <div class="w-9 h-9 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-amber-900">
                {{ $inventoryStats['pending_batches'] }} {{ Str::plural('batch', $inventoryStats['pending_batches']) }} awaiting sorting
            </p>
            <p class="text-xs text-amber-700 mt-0.5 truncate">These batches must be sorted before stock is updated.</p>
        </div>
        <a href="{{ route('inventory.batches.index') }}"
           class="shrink-0 inline-flex items-center gap-1.5 text-xs font-bold text-amber-900 bg-amber-100 border border-amber-300 px-3 py-1.5 rounded-xl hover:bg-amber-200 transition-colors">
            Sort Batches →
        </a>
    </div>
    @endif

    @if($sortingProgress)
    {{-- Warehouse Dispatch Sorting Progress --}}
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-5 border-b border-slate-100">
            <div>
                <h2 class="text-lg font-black text-slate-800 tracking-tight">Warehouse Receiver Progress</h2>
                <p class="text-xs text-slate-400 mt-0.5">Live shopwise allocation and dispatch progress from the active warehouse flow</p>
            </div>
            <a href="{{ route('warehouse.loadout.index') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-black text-white hover:bg-brand-700 transition-colors shadow-sm">
                Open Loadout
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-5">
            {{-- Today's Progress --}}
            <div class="bg-slate-50/50 rounded-2xl border border-slate-100 p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Today's Orders ({{ today()->format('d M Y') }})</span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-slate-600 border border-slate-200">
                            {{ $sortingProgress['today']['sorted'] }} / {{ $sortingProgress['today']['total'] }} items
                        </span>
                    </div>
                    <p class="text-3xl font-black text-slate-800 mt-2">{{ $sortingProgress['today']['percentage'] }}%</p>
                </div>
                <div class="w-full bg-slate-200 h-2 rounded-full mt-4 overflow-hidden">
                    <div class="h-full bg-brand-500 rounded-full transition-all duration-505" style="width: {{ $sortingProgress['today']['percentage'] }}%;"></div>
                </div>
            </div>

            {{-- Tomorrow's Progress --}}
            <div class="bg-slate-50/50 rounded-2xl border border-slate-100 p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tomorrow's Orders ({{ \Illuminate\Support\Carbon::tomorrow()->format('d M Y') }})</span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-slate-600 border border-slate-200">
                            {{ $sortingProgress['tomorrow']['sorted'] }} / {{ $sortingProgress['tomorrow']['total'] }} items
                        </span>
                    </div>
                    <p class="text-3xl font-black text-slate-800 mt-2">{{ $sortingProgress['tomorrow']['percentage'] }}%</p>
                </div>
                <div class="w-full bg-slate-200 h-2 rounded-full mt-4 overflow-hidden">
                    <div class="h-full bg-brand-500 rounded-full transition-all duration-505" style="width: {{ $sortingProgress['tomorrow']['percentage'] }}%;"></div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($role === 'purchase' || $user->can('purchasing.order.approve'))
    @php
        $purchaseToneClasses = [
            'amber' => 'border-amber-200 bg-amber-50 text-amber-800',
            'violet' => 'border-violet-200 bg-violet-50 text-violet-800',
            'blue' => 'border-blue-200 bg-blue-50 text-blue-800',
            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'slate' => 'border-slate-200 bg-slate-50 text-slate-800',
        ];
        $purchaseButtonClasses = [
            'amber' => 'bg-amber-500 hover:bg-amber-600 text-white',
            'violet' => 'bg-violet-600 hover:bg-violet-700 text-white',
            'blue' => 'bg-blue-600 hover:bg-blue-700 text-white',
            'emerald' => 'bg-emerald-600 hover:bg-emerald-700 text-white',
            'slate' => 'bg-slate-900 hover:bg-slate-800 text-white',
        ];
    @endphp

    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5">
            <div>
                <h2 class="text-xl font-black text-slate-900 tracking-tight">Purchase Manager Daily Desk</h2>
                <p class="text-xs text-slate-500 mt-1">Start here for today’s approvals, supplier buying, receipt tracking, and invoice follow-up.</p>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 lg:w-[480px]">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-amber-700">Pending Review</p>
                    <p class="mt-1 text-2xl font-black text-amber-900">{{ $purchaseDashboard['headline']['pending_review'] }}</p>
                </div>
                <div class="rounded-2xl border border-violet-200 bg-violet-50 px-4 py-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-violet-700">Approved Tomorrow</p>
                    <p class="mt-1 text-2xl font-black text-violet-900">{{ $purchaseDashboard['headline']['approved_tomorrow'] }}</p>
                </div>
                <div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-blue-700">Open POs</p>
                    <p class="mt-1 text-2xl font-black text-blue-900">{{ $purchaseDashboard['headline']['open_purchase_orders'] }}</p>
                </div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-700">GRN Recheck</p>
                    <p class="mt-1 text-2xl font-black text-emerald-900">{{ $purchaseDashboard['headline']['grns_awaiting_approval'] }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-700">Pending Invoices</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ $purchaseDashboard['headline']['pending_invoices'] }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Focus Date</p>
                    <p class="mt-1 text-lg font-black text-slate-900">{{ \Carbon\Carbon::parse($purchaseDashboard['tomorrow'])->format('d M Y') }}</p>
                </div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.65fr)] gap-6">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($purchaseDashboard['focus_cards'] as $card)
                    <div class="rounded-3xl border p-5 {{ $purchaseToneClasses[$card['tone']] ?? $purchaseToneClasses['slate'] }}">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em]">{{ $card['title'] }}</p>
                        <p class="mt-3 text-4xl font-black">{{ $card['count'] }}</p>
                        <p class="mt-3 text-xs font-semibold leading-5 opacity-80">{{ $card['detail'] }}</p>
                        <a href="{{ $card['href'] }}" class="mt-4 inline-flex items-center justify-center rounded-xl px-3.5 py-2 text-[11px] font-black transition-colors {{ $purchaseButtonClasses[$card['tone']] ?? $purchaseButtonClasses['slate'] }}">
                            {{ $card['action'] }}
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="rounded-3xl border border-slate-200 bg-slate-950 p-5 text-white">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-cyan-300">What To Do Today</p>
                <div class="mt-4 space-y-3">
                    @foreach($purchaseDashboard['today_tasks'] as $index => $task)
                        <a href="{{ $task['href'] }}" class="block rounded-2xl border border-white/10 bg-white/5 px-4 py-3 hover:bg-white/10 transition-colors">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-cyan-400 text-[10px] font-black text-slate-950">{{ $index + 1 }}</span>
                                <div>
                                    <p class="text-sm font-black text-white">{{ $task['title'] }}</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-300">{{ $task['description'] }}</p>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Notifications</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">New requisitions, revisions, and PO creation</p>
                    </div>
                    <span class="rounded-full bg-white px-3 py-1 text-[11px] font-black text-slate-700 border border-slate-200">{{ $purchaseDashboard['notifications']->count() }} unread</span>
                </div>
                <div class="mt-4 space-y-2">
                    @forelse($purchaseDashboard['notifications'] as $notification)
                        @php
                            $payload = $notification->data;
                        @endphp
                        <a href="{{ $payload['route'] ?? route('dashboard') }}" class="block rounded-2xl bg-white px-4 py-3 border border-slate-200 hover:border-slate-300 transition-colors">
                            <p class="text-sm font-black text-slate-900">{{ $payload['title'] ?? 'Purchasing update' }}</p>
                            <p class="mt-0.5 text-[11px] font-semibold leading-5 text-slate-500">{{ $payload['message'] ?? 'A new purchasing update is available.' }}</p>
                        </a>
                    @empty
                        <p class="rounded-2xl bg-white px-4 py-4 text-xs font-semibold text-slate-400 border border-slate-200">No unread purchasing notifications.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Recent POs</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">Continue supplier workflow</p>
                    </div>
                    <a href="{{ route('purchasing.orders.index') }}" class="text-[11px] font-black text-blue-700 hover:text-blue-800">View all</a>
                </div>
                <div class="mt-4 space-y-2">
                    @forelse($purchaseDashboard['recent_purchase_orders'] as $po)
                        <a href="{{ route('purchasing.orders.show', $po) }}" class="flex items-center justify-between rounded-2xl bg-white px-4 py-3 border border-slate-200 hover:border-slate-300 transition-colors">
                            <div>
                                <p class="text-sm font-black text-slate-900">{{ $po->po_number }}</p>
                                <p class="mt-0.5 text-[11px] font-semibold text-slate-500">{{ $po->supplier?->name ?? 'No Supplier' }}</p>
                            </div>
                            <span class="text-[10px] font-black uppercase text-slate-500">{{ str($po->status->value)->replace('_', ' ') }}</span>
                        </a>
                    @empty
                        <p class="rounded-2xl bg-white px-4 py-4 text-xs font-semibold text-slate-400 border border-slate-200">No open purchase orders.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Recent Receipts</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">Warehouse-approved goods receipts</p>
                    </div>
                    <a href="{{ route('purchasing.grns.index') }}" class="text-[11px] font-black text-emerald-700 hover:text-emerald-800">Open queue</a>
                </div>
                <div class="mt-4 space-y-2">
                    @forelse($purchaseDashboard['recent_grns'] as $grn)
                        <a href="{{ route('purchasing.grns.show', $grn) }}" class="flex items-center justify-between rounded-2xl bg-white px-4 py-3 border border-slate-200 hover:border-slate-300 transition-colors">
                            <div>
                                <p class="text-sm font-black text-slate-900">{{ $grn->grn_number }}</p>
                                <p class="mt-0.5 text-[11px] font-semibold text-slate-500">{{ $grn->purchaseOrder?->supplier?->name ?? 'No Supplier' }}</p>
                            </div>
                            <span class="text-[10px] font-black uppercase text-emerald-600">Approved</span>
                        </a>
                    @empty
                        <p class="rounded-2xl bg-white px-4 py-4 text-xs font-semibold text-slate-400 border border-slate-200">No approved receipts recorded today.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Invoice Follow-up</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">Pending shop invoice exceptions</p>
                    </div>
                    <a href="{{ route('purchasing.shop-invoices.index') }}" class="text-[11px] font-black text-slate-700 hover:text-slate-900">Open invoices</a>
                </div>
                <div class="mt-4 space-y-2">
                    @forelse($purchaseDashboard['recent_invoices'] as $invoice)
                        <a href="{{ route('purchasing.shop-invoices.show', $invoice) }}" class="flex items-center justify-between rounded-2xl bg-white px-4 py-3 border border-slate-200 hover:border-slate-300 transition-colors">
                            <div>
                                <p class="text-sm font-black text-slate-900">{{ $invoice->invoice_number }}</p>
                                <p class="mt-0.5 text-[11px] font-semibold text-slate-500">{{ $invoice->shop?->name ?? 'No Shop' }}</p>
                            </div>
                            <span class="text-[10px] font-black uppercase text-slate-500">{{ str($invoice->status)->replace('_', ' ') }}</span>
                        </a>
                    @empty
                        <p class="rounded-2xl bg-white px-4 py-4 text-xs font-semibold text-slate-400 border border-slate-200">No pending shop invoice exceptions.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Daily order progress timeline --}}
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-5 border-b border-slate-100">
            <div>
                <h2 class="text-lg font-black text-slate-800 tracking-tight">Daily Order Progress</h2>
                <p class="text-xs text-slate-400 mt-0.5">Track each delivery date from requisition to approved board, purchase order, and warehouse receiving</p>
            </div>
            <a href="{{ route('requisitions.approved_board') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white hover:bg-slate-800 transition-colors">
                Open Approved Board
            </a>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($dailyOrderStatuses as $status)
                @php
                    $stageIndex = match ($status['stage']) {
                        'not_started' => 0,
                        'requisition' => 1,
                        'approved_board' => 2,
                        'purchase_order' => 3,
                        'received' => 4,
                        default => 0,
                    };
                    $stages = [
                        ['key' => 'requisition', 'label' => 'Requisition'],
                        ['key' => 'approved_board', 'label' => 'Approved'],
                        ['key' => 'purchase_order', 'label' => 'Purchase'],
                        ['key' => 'received', 'label' => 'Received'],
                    ];
                    $stageColor = match ($status['stage']) {
                        'received' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        'purchase_order' => 'bg-blue-50 text-blue-700 border-blue-200',
                        'approved_board' => 'bg-violet-50 text-violet-700 border-violet-200',
                        'requisition' => 'bg-amber-50 text-amber-700 border-amber-200',
                        default => 'bg-slate-50 text-slate-600 border-slate-200',
                    };
                @endphp
                <div class="py-4 flex flex-col xl:flex-row xl:items-center gap-4">
                    <div class="xl:w-64 shrink-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-black text-slate-900">{{ \Carbon\Carbon::parse($status['date'])->format('d M Y') }}</p>
                            @if($status['date'] === today()->addDay()->toDateString())
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-700">Tomorrow</span>
                            @elseif($status['date'] === today()->toDateString())
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-600">Today</span>
                            @endif
                        </div>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider {{ $stageColor }}">
                                {{ $status['label'] }}
                            </span>
                            <span class="text-[11px] font-semibold text-slate-500">{{ $status['description'] }}</span>
                        </div>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="grid grid-cols-4 gap-2">
                            @foreach($stages as $index => $step)
                                @php
                                    $isComplete = ($index + 1) <= $stageIndex;
                                    $isCurrent = $step['key'] === $status['stage'];
                                @endphp
                                <div @class([
                                    'rounded-xl border px-3 py-2 text-center transition-colors',
                                    'bg-slate-900 border-slate-900 text-white' => $isCurrent,
                                    'bg-emerald-50 border-emerald-200 text-emerald-700' => $isComplete && ! $isCurrent,
                                    'bg-slate-50 border-slate-200 text-slate-400' => ! $isComplete && ! $isCurrent,
                                ])>
                                    <p class="text-[10px] font-black uppercase tracking-wider">{{ $step['label'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 xl:w-72 xl:justify-end shrink-0">
                        @if($status['stage'] === 'purchase_order')
                            <a href="{{ route('purchasing.orders.index') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-3 py-2 text-[11px] font-black text-white hover:bg-blue-700 transition-colors">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272" /></svg>
                                Continue in Purchase Orders
                            </a>
                            @foreach($status['purchase_orders']->take(2) as $po)
                                <a href="{{ route('purchasing.orders.show', $po) }}" class="inline-flex items-center rounded-xl bg-blue-50 px-3 py-2 text-[11px] font-black text-blue-700 border border-blue-200 hover:bg-blue-100 transition-colors">
                                    {{ $po->po_number }}
                                </a>
                            @endforeach
                            @if($status['po_count'] > 2)
                                <span class="inline-flex items-center rounded-xl bg-slate-50 px-3 py-2 text-[11px] font-black text-slate-500 border border-slate-200">
                                    +{{ $status['po_count'] - 2 }} more
                                </span>
                            @endif
                        @elseif($status['po_count'] > 0)
                            @foreach($status['purchase_orders']->take(2) as $po)
                                <a href="{{ route('purchasing.orders.show', $po) }}" class="inline-flex items-center rounded-xl bg-emerald-600 px-3 py-2 text-[11px] font-black text-white hover:bg-emerald-700 transition-colors">
                                    {{ $po->po_number }}
                                </a>
                            @endforeach
                            @if($status['po_count'] > 2)
                                <a href="{{ route('purchasing.orders.index') }}" class="inline-flex items-center rounded-xl bg-emerald-50 px-3 py-2 text-[11px] font-black text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition-colors">
                                    +{{ $status['po_count'] - 2 }} more
                                </a>
                            @endif
                        @elseif($status['approved_count'] > 0)
                            <a href="{{ route('requisitions.approved_board', ['date' => $status['date']]) }}" class="inline-flex items-center rounded-xl bg-violet-600 px-3 py-2 text-[11px] font-black text-white hover:bg-violet-700 transition-colors">
                                Open Approved Board
                            </a>
                        @elseif($status['submitted_count'] > 0)
                            <a href="{{ route('requisitions.board', ['date' => $status['date']]) }}" class="inline-flex items-center rounded-xl bg-amber-500 px-3 py-2 text-[11px] font-black text-white hover:bg-amber-600 transition-colors">
                                Review Requisitions
                            </a>
                        @else
                            <span class="inline-flex items-center rounded-xl bg-slate-50 px-3 py-2 text-[11px] font-black text-slate-400 border border-slate-200">
                                Waiting for shops
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-8 text-center text-xs font-semibold text-slate-400">No daily order activity yet.</div>
            @endforelse
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- 🛒 SHOP REQUISITIONS APPROVAL CENTER (PURCHASE MANAGER)                    --}}
    {{-- ========================================================================= --}}
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-5 border-b border-slate-100">
            <div>
                <h2 class="text-lg font-black text-slate-800 tracking-tight">Shop Requisitions Approval Center</h2>
                <p class="text-xs text-slate-400 mt-0.5">Review and approve daily order requests submitted by shop owners</p>
            </div>
            
            {{-- Tabs --}}
            <div class="flex bg-slate-100 p-1 rounded-xl shrink-0 self-start sm:self-auto">
                <button type="button" onclick="switchApprovalTab('pending')" id="tab-btn-pending" class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all bg-white text-slate-800 shadow-sm cursor-pointer border-0">
                    Pending Review
                    <span class="ml-1 px-1.5 py-0.5 rounded-md bg-amber-500 text-white text-[10px] font-black" id="pending-count">0</span>
                </button>
                <button type="button" onclick="switchApprovalTab('all')" id="tab-btn-all" class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all text-slate-500 hover:text-slate-800 cursor-pointer border-0">
                    All Requisitions
                </button>
            </div>
        </div>

        {{-- Filters & Search bar --}}
        <div class="flex flex-col md:flex-row gap-3 py-4">
            <div class="relative flex-1">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </div>
                <input type="text" id="requisition-search" oninput="filterRequisitions()" placeholder="Search by Shop name or Order ID..." class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2.5 focus:bg-white focus:outline-none focus:border-slate-300 transition-all">
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto border border-slate-100 rounded-2xl">
            <table class="w-full text-left border-collapse" id="approval-requisitions-table">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-4">Target Date</th>
                        <th class="py-3 px-4">Order ID</th>
                        <th class="py-3 px-4">Shop</th>
                        <th class="py-3 px-4 text-center">Items</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Submitted At</th>
                        <th class="py-3 px-4 text-center w-[120px]">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-xs text-slate-700">
                    @forelse($allShopOrders as $order)
                        @php
                            $isPending = in_array($order->state, ['submitted', 'update_requested']);
                            $itemsCount = $order->items->count();
                        @endphp
                        <tr class="hover:bg-slate-50/20 transition-all requisition-row" 
                            data-state="{{ $order->state }}" 
                            data-pending="{{ $isPending ? 'true' : 'false' }}"
                            data-shop="{{ strtolower($order->shop ? $order->shop->name : 'Casio Hypermarket') }}"
                            data-id="{{ strtolower($order->order_number) }}">
                            <td class="py-3.5 px-4 font-bold text-slate-900">
                                {{ \Carbon\Carbon::parse($order->business_date)->format('d M Y') }}
                            </td>
                            <td class="py-3.5 px-4 font-mono font-bold text-slate-500">
                                {{ $order->order_number }}
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-slate-800">{{ $order->shop ? $order->shop->name : 'Casio Hypermarket' }}</div>
                                <div class="text-[10px] text-slate-400 font-medium">Submitted by: {{ $order->creator ? $order->creator->name : 'N/A' }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold text-slate-600">
                                {{ $itemsCount }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if($order->state === 'submitted')
                                    <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-amber-100 uppercase tracking-wider">
                                        Submitted
                                    </span>
                                @elseif($order->state === 'approved')
                                    <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-emerald-100 uppercase tracking-wider">
                                        Approved
                                    </span>
                                @elseif($order->state === 'update_requested')
                                    <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-indigo-100 uppercase tracking-wider animate-pulse">
                                        Update Requested
                                    </span>
                                @elseif($order->state === 'rejected')
                                    <span class="inline-flex items-center gap-1 bg-red-50 text-red-700 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-red-100 uppercase tracking-wider">
                                        Rejected
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 bg-slate-50 text-slate-600 px-2.5 py-0.5 rounded-full text-[9px] font-black border border-slate-200 uppercase tracking-wider">
                                        {{ $order->state }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right font-medium text-slate-500">
                                {{ $order->submitted_at ? $order->submitted_at->format('d M Y, h:i A') : 'N/A' }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <a href="{{ route('requisitions.show', $order->order_number) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-800 bg-slate-100 hover:bg-slate-200 border border-slate-200 px-3 py-1.5 rounded-xl transition-all cursor-pointer">
                                    {{ $isPending ? 'Review' : 'View' }}
                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr id="no-requisitions-row">
                            <td colspan="7" class="py-12 text-center text-slate-400 font-medium italic bg-slate-50/10">No requisitions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        let currentApprovalTab = 'pending';

        function switchApprovalTab(tab) {
            currentApprovalTab = tab;
            
            const btnPending = document.getElementById('tab-btn-pending');
            const btnAll = document.getElementById('tab-btn-all');

            if (tab === 'pending') {
                btnPending.className = "px-4 py-1.5 rounded-lg text-xs font-bold transition-all bg-white text-slate-800 shadow-sm cursor-pointer border-0";
                btnAll.className = "px-4 py-1.5 rounded-lg text-xs font-bold transition-all text-slate-500 hover:text-slate-800 cursor-pointer border-0";
            } else {
                btnAll.className = "px-4 py-1.5 rounded-lg text-xs font-bold transition-all bg-white text-slate-800 shadow-sm cursor-pointer border-0";
                btnPending.className = "px-4 py-1.5 rounded-lg text-xs font-bold transition-all text-slate-500 hover:text-slate-800 cursor-pointer border-0";
            }

            filterRequisitions();
        }

        function filterRequisitions() {
            const query = document.getElementById('requisition-search').value.toLowerCase().trim();
            const rows = document.querySelectorAll('.requisition-row');
            let visibleCount = 0;
            let totalPending = 0;

            rows.forEach(row => {
                const isPending = row.getAttribute('data-pending') === 'true';
                const shop = row.getAttribute('data-shop');
                const orderId = row.getAttribute('data-id');

                if (isPending) {
                    totalPending++;
                }

                // Filter by Tab
                let matchesTab = true;
                if (currentApprovalTab === 'pending') {
                    matchesTab = isPending;
                }

                // Filter by Search Query
                let matchesSearch = true;
                if (query) {
                    matchesSearch = shop.includes(query) || orderId.includes(query);
                }

                if (matchesTab && matchesSearch) {
                    row.classList.remove('hidden');
                    visibleCount++;
                } else {
                    row.classList.add('hidden');
                }
            });

            // Update pending counter badge
            const pendingCountEl = document.getElementById('pending-count');
            if (pendingCountEl) {
                pendingCountEl.textContent = totalPending;
            }

            // Show "no results" row if no rows are visible
            const table = document.getElementById('approval-requisitions-table');
            const noResultsRow = document.getElementById('no-requisitions-row');
            
            if (visibleCount === 0) {
                if (!noResultsRow) {
                    const tr = document.createElement('tr');
                    tr.id = 'no-requisitions-row';
                    tr.innerHTML = `<td colspan="7" class="py-12 text-center text-slate-400 font-medium italic bg-slate-50/10">No requisitions match your criteria.</td>`;
                    table.querySelector('tbody').appendChild(tr);
                } else {
                    noResultsRow.classList.remove('hidden');
                }
            } else if (noResultsRow) {
                noResultsRow.classList.add('hidden');
            }
        }

        // Initialize counters and state on load
        document.addEventListener('DOMContentLoaded', () => {
            filterRequisitions();
        });
    </script>
    @endif

    {{-- Module tiles grid --}}
    <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Your Modules</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($accessibleModules as $module)
        @php
            $isComingSoon = $module['badge'] === 'Coming Soon';
        @endphp
        <a href="{{ $module['href'] }}"
           @class([
               'group relative flex flex-col gap-3 rounded-2xl border p-5 transition-all duration-200',
               $module['color'],
               'hover:shadow-md hover:-translate-y-0.5 cursor-pointer' => !$isComingSoon,
               'opacity-60 cursor-not-allowed pointer-events-none'     => $isComingSoon,
           ])
        >
            <div class="flex items-start justify-between gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/60 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $module['icon'] }}" />
                    </svg>
                </div>
                @if($module['badge'] && $module['badge'] !== 'Coming Soon')
                <span class="inline-flex items-center text-[10px] font-bold px-2 py-0.5 rounded-full bg-white/70 backdrop-blur-sm">
                    {{ $module['badge'] }}
                </span>
                @elseif($isComingSoon)
                <span class="inline-flex items-center text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-200 text-gray-500">
                    Soon
                </span>
                @endif
            </div>
            <div>
                <p class="text-sm font-bold">{{ $module['title'] }}</p>
                <p class="text-xs mt-0.5 opacity-75 leading-relaxed">{{ $module['description'] }}</p>
            </div>
            @if(!$isComingSoon)
            <div class="text-xs font-semibold opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-1 mt-auto">
                Open <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
            </div>
            @endif
        </a>
        @empty
        <div class="col-span-full bg-white rounded-2xl border border-gray-200 py-12 text-center">
            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-900">No modules assigned</p>
            <p class="text-xs text-gray-500 mt-1">Contact your administrator to get access.</p>
        </div>
        @endforelse
    </div>

    {{-- User profile & role info (admin-only) --}}
    @if($user->hasRole('admin'))
    <div class="mt-6 bg-white rounded-2xl border border-gray-200 p-5">
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Demo Accounts</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach([
                ['email' => 'admin@greenleaf.com',     'role' => 'Administrator',       'color' => 'bg-purple-100 text-purple-700'],
                ['email' => 'shop@greenleaf.com',      'role' => 'Shop Owner',          'color' => 'bg-emerald-100 text-emerald-700'],
                ['email' => 'purchase@greenleaf.com',  'role' => 'Purchase Manager',    'color' => 'bg-amber-100 text-amber-700'],
                ['email' => 'receiver@greenleaf.com',  'role' => 'Warehouse Receiver',  'color' => 'bg-cyan-100 text-cyan-700'],
            ] as $demo)
            <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
                <div class="w-7 h-7 rounded-lg {{ $demo['color'] }} flex items-center justify-center shrink-0">
                    <span class="text-[10px] font-bold">{{ strtoupper(substr($demo['role'], 0, 1)) }}</span>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-gray-800 truncate">{{ $demo['email'] }}</p>
                    <p class="text-[10px] text-gray-500">{{ $demo['role'] }} · password</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
    @endif

</x-layouts.app>
