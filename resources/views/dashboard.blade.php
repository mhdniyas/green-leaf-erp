@php
/** @var \App\Models\User $user */
$user = auth()->user();
$role = $user->getRoleNames()->first() ?? 'viewer';

$roleConfig = [
    'super-admin'         => ['label' => 'Super Admin',         'color' => 'bg-purple-100 text-purple-700 border-purple-200'],
    'admin'               => ['label' => 'Administrator',       'color' => 'bg-purple-100 text-purple-700 border-purple-200'],
    'inventory-manager'   => ['label' => 'Inventory Manager',   'color' => 'bg-brand-100 text-brand-700 border-brand-200'],
    'inventory-staff'     => ['label' => 'Inventory Staff',     'color' => 'bg-brand-100 text-brand-700 border-brand-200'],
    'sales-manager'       => ['label' => 'Sales Manager',       'color' => 'bg-blue-100 text-blue-700 border-blue-200'],
    'cashier'             => ['label' => 'Cashier',             'color' => 'bg-blue-100 text-blue-700 border-blue-200'],
    'purchasing-manager'  => ['label' => 'Purchasing Manager',  'color' => 'bg-amber-100 text-amber-700 border-amber-200'],
    'accountant'          => ['label' => 'Accountant',          'color' => 'bg-teal-100 text-teal-700 border-teal-200'],
    'hr-manager'          => ['label' => 'HR Manager',          'color' => 'bg-pink-100 text-pink-700 border-pink-200'],
    'viewer'              => ['label' => 'Read-only Viewer',    'color' => 'bg-gray-100 text-gray-600 border-gray-200'],
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
        'title'       => 'Purchase Orders',
        'description' => 'Manage suppliers, orders, and receive goods.',
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

    {{-- Welcome banner --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', $user->name)[0] }} 👋</h1>
            <p class="text-sm text-gray-500 mt-0.5">Here's what's happening in Green Leaf ERP today.</p>
        </div>
        <span class="self-start sm:self-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-semibold {{ $rc['color'] }}">
            <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
            {{ $rc['label'] }}
        </span>
    </div>

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

        {{-- Active Suppliers --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124l-.318-5.085a1.5 1.5 0 0 0-1.496-1.408h-2.483c-.767 0-1.42.545-1.5 1.3L12.5 14.25m0 0v-4.5m0 4.5h6.75m-6.75-4.5H8.25M6.75 8.25h.008v.008H6.75V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium">Active Suppliers</p>
                <p class="text-2xl font-bold text-gray-900 mt-0.5">{{ $purchasingStats['active_suppliers'] }}</p>
                @can('purchasing.supplier.view')
                <a href="{{ route('purchasing.suppliers.index') }}" class="text-xs text-amber-600 font-medium hover:underline mt-0.5 block">View suppliers →</a>
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
                @can('viewAny', \App\Models\PurchaseInvoice::class)
                <a href="{{ route('purchasing.invoices.index') }}" class="text-xs text-teal-600 font-medium hover:underline mt-0.5 block">View invoices →</a>
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
    @if($user->hasRole(['super-admin', 'admin']))
    <div class="mt-6 bg-white rounded-2xl border border-gray-200 p-5">
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Demo Accounts</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach([
                ['email' => 'admin@greenleaf.com',   'role' => 'Administrator',       'color' => 'bg-purple-100 text-purple-700'],
                ['email' => 'manager@greenleaf.com', 'role' => 'Inventory Manager',   'color' => 'bg-brand-100 text-brand-700'],
                ['email' => 'cashier@greenleaf.com', 'role' => 'Cashier',             'color' => 'bg-blue-100 text-blue-700'],
                ['email' => 'sales@greenleaf.com',   'role' => 'Sales Manager',       'color' => 'bg-blue-100 text-blue-700'],
                ['email' => 'accounts@greenleaf.com','role' => 'Accountant',          'color' => 'bg-teal-100 text-teal-700'],
                ['email' => 'viewer@greenleaf.com',  'role' => 'Viewer',              'color' => 'bg-gray-100 text-gray-600'],
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

</x-layouts.app>
