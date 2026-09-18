@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?? 'Shop').' - Vendor Settings')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">

    {{-- Navigation Tabs --}}
    @include('admin.cashbook.settings.partials.tabs', [
        'activeTab' => 'vendors',
        'shopKey' => $shopKey,
        'currentShop' => $currentShop
    ])

    {{-- Alerts --}}
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-bold text-emerald-800 shadow-2xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="check-circle-2" class="h-4 w-4 text-emerald-600 shrink-0"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900 text-xs font-bold">Dismiss</button>
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-bold text-rose-800 shadow-2xs flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="alert-circle" class="h-4 w-4 text-rose-600 shrink-0"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-900 text-xs font-bold">Dismiss</button>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-800 shadow-2xs space-y-1">
            <div class="flex items-center gap-2 font-bold text-rose-900">
                <i data-lucide="alert-triangle" class="h-4 w-4 text-rose-600 shrink-0"></i>
                <span>Please correct the errors below:</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-rose-700 pl-5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Shop Purchasing Disabled Warning --}}
    @if(!$shop->isPurchasingEnabled())
        <div class="rounded-3xl border border-amber-300 bg-amber-50/90 p-5 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3.5">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-100 text-amber-900 border border-amber-300 shadow-2xs shrink-0">
                    <i data-lucide="alert-triangle" class="h-5 w-5 text-amber-700"></i>
                </span>
                <div>
                    <h3 class="text-sm font-black text-amber-950 uppercase tracking-tight">Shop Purchasing is disabled</h3>
                    <p class="text-xs text-amber-800 font-medium mt-0.5">Vendors are configured, but Shop Owner cannot create purchases.</p>
                </div>
            </div>

            <form action="{{ route('admin.cashbook.settings.shop.toggle-purchasing', $shopKey) }}" method="POST" class="shrink-0">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-amber-700 transition shadow-xs cursor-pointer">
                    <i data-lucide="power" class="h-3.5 w-3.5"></i>
                    <span>Enable Shop Purchasing</span>
                </button>
            </form>
        </div>
    @endif

    {{-- Page Header --}}
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-800 border border-amber-200 shadow-2xs shrink-0">
                <i data-lucide="truck" class="h-6 w-6"></i>
            </span>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-widest text-amber-700">Shop Relations</span>
                    @if($shop->isPurchasingEnabled())
                        <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-700 border border-emerald-200">Shop Purchasing: Enabled</span>
                    @else
                        <span class="rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-black uppercase text-amber-800 border border-amber-200">Shop Purchasing: Disabled</span>
                    @endif
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-950 tracking-tight mt-0.5">SHOP VENDORS</h1>
                <p class="text-xs text-slate-500 font-medium">Manage vendors linked to {{ $currentShop->name }} for Shop Purchasing & Vendor Liabilities.</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 shrink-0 flex-wrap">
            <button type="button" onclick="openLinkVendorModal()"
                    class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 transition shadow-xs cursor-pointer">
                <i data-lucide="link" class="h-3.5 w-3.5"></i>
                <span>+ Link Vendor</span>
            </button>
            <button type="button" onclick="openCreateVendorModal()"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-emerald-700 transition shadow-xs cursor-pointer">
                <i data-lucide="plus-circle" class="h-3.5 w-3.5"></i>
                <span>+ Create Vendor</span>
            </button>
        </div>
    </div>

    {{-- Shop Owner Permissions Card --}}
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-start sm:items-center gap-3.5">
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-700 border border-indigo-200 shrink-0">
                <i data-lucide="shield-check" class="h-5 w-5"></i>
            </span>
            <div>
                <span class="text-[10px] font-black uppercase tracking-widest text-indigo-700">SHOP OWNER PERMISSIONS</span>
                <h3 class="text-sm font-black text-slate-950 mt-0.5">Allow Shop Owner to Create Vendors</h3>
                <p class="text-xs text-slate-500 font-medium">When enabled, the Shop Owner can register new vendors from their Cashbook Purchase modal (credit defaults to disabled until Admin approves).</p>
            </div>
        </div>

        <form action="{{ route('admin.cashbook.settings.shop.vendors.toggle-creation-permission', ['shop' => $shopKey]) }}" method="POST" class="shrink-0">
            @csrf
            @if($shop->isVendorCreationAllowed())
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white hover:bg-emerald-700 transition shadow-xs cursor-pointer">
                    <span class="h-2 w-2 rounded-full bg-white animate-pulse"></span>
                    <span>ON</span>
                </button>
            @else
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-slate-200 px-4 py-2.5 text-xs font-black text-slate-700 hover:bg-slate-300 transition shadow-xs cursor-pointer">
                    <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                    <span>OFF</span>
                </button>
            @endif
        </form>
    </div>

    {{-- Vendor Purchase Settings Card --}}
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-start sm:items-center gap-3.5">
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-50 text-amber-700 border border-amber-200 shrink-0">
                <i data-lucide="clock" class="h-5 w-5"></i>
            </span>
            <div>
                <span class="text-[10px] font-black uppercase tracking-widest text-amber-700">VENDOR PURCHASE SETTINGS</span>
                <h3 class="text-sm font-black text-slate-950 mt-0.5">Vendor Purchase Edit Window</h3>
                <p class="text-xs text-slate-500 font-medium">Controls how far back the Shop Owner can create, edit, or cancel Vendor Purchases for {{ $shop->name }}.</p>
            </div>
        </div>

        <form action="{{ route('admin.cashbook.settings.shop.vendors.update-purchase-settings', ['shop' => $shopKey]) }}" method="POST" class="shrink-0 flex items-center gap-2">
            @csrf
            <div class="flex items-center gap-1.5 bg-slate-50 p-1.5 rounded-2xl border border-slate-200">
                <input type="number" min="1" max="720" name="vendor_purchase_edit_window_value"
                       value="{{ old('vendor_purchase_edit_window_value', $vendorPurchaseEditWindow['value'] ?? 3) }}"
                       class="w-16 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-900 text-center focus:border-amber-500 focus:outline-none">
                <select name="vendor_purchase_edit_window_unit"
                        class="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-900 focus:border-amber-500 focus:outline-none">
                    <option value="hours" {{ old('vendor_purchase_edit_window_unit', $vendorPurchaseEditWindow['unit'] ?? 'days') === 'hours' ? 'selected' : '' }}>Hours</option>
                    <option value="days" {{ old('vendor_purchase_edit_window_unit', $vendorPurchaseEditWindow['unit'] ?? 'days') === 'days' ? 'selected' : '' }}>Days</option>
                </select>
                <button type="submit"
                        class="rounded-xl bg-slate-900 px-3.5 py-1.5 text-xs font-bold text-white hover:bg-slate-800 transition cursor-pointer">
                    Save
                </button>
            </div>
        </form>
    </div>

    {{-- Stats & Filter Bar --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2 bg-slate-50 border border-slate-200/80 rounded-xl px-3 py-1.5">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">Total Linked:</span>
                <span class="text-xs font-black text-slate-900">{{ $totalVendorsCount }}</span>
            </div>
            <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-200/80 rounded-xl px-3 py-1.5">
                <span class="text-[10px] font-black uppercase tracking-wider text-emerald-700">Active:</span>
                <span class="text-xs font-black text-emerald-800">{{ $activeVendorsCount }}</span>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.cashbook.settings.shop.vendors.index', ['shop' => $shopKey]) }}" class="flex items-center gap-2 flex-wrap">
            <div class="relative min-w-[180px]">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search vendor name, phone..."
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-8 pr-3 py-1.5 text-xs font-semibold text-slate-900 focus:bg-white focus:border-amber-500 focus:outline-none">
                <i data-lucide="search" class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400"></i>
            </div>

            <select name="status" onchange="this.form.submit()"
                    class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-900 focus:bg-white focus:border-amber-500 focus:outline-none cursor-pointer">
                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Status</option>
                <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active Only</option>
                <option value="disabled" {{ $status === 'disabled' ? 'selected' : '' }}>Disabled Only</option>
            </select>

            <button type="submit" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-2xs">
                Filter
            </button>
            @if($search !== '' || $status !== 'all')
                <a href="{{ route('admin.cashbook.settings.shop.vendors.index', ['shop' => $shopKey]) }}"
                   class="text-xs font-bold text-slate-500 hover:text-slate-800 underline">Clear</a>
            @endif
        </form>
    </div>

    {{-- Vendors Table --}}
    <div class="rounded-3xl border border-slate-200 bg-white overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/80 border-b border-slate-200/80 text-[10px] uppercase font-black tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3.5">Vendor Name</th>
                        <th class="px-4 py-3.5">Contact / Phone</th>
                        <th class="px-4 py-3.5">Status</th>
                        <th class="px-4 py-3.5">Credit Enabled</th>
                        <th class="px-4 py-3.5 text-center">Shop Bills</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($shopSuppliers as $supplier)
                        @php
                            $isActive = (bool) ($supplier->pivot->is_active ?? true);
                            $isCreditApproved = (bool) ($supplier->credit_approved ?? false);
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-5 py-4">
                                <div class="font-extrabold text-slate-900 text-sm">{{ $supplier->name }}</div>
                                @if($supplier->category || $supplier->type)
                                    <div class="text-[10px] font-semibold text-slate-400 mt-0.5">
                                        {{ ucfirst(str_replace('_', ' ', $supplier->category ?: $supplier->type)) }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-mono text-slate-700 font-semibold">{{ $supplier->mobile_number ?: '—' }}</div>
                                @if($supplier->contact)
                                    <div class="text-[10px] text-slate-500">{{ $supplier->contact }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                @if($isActive)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-200">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                        Disabled
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                @if($isCreditApproved)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-cyan-50 px-2 py-0.5 text-[10px] font-bold text-cyan-700 border border-cyan-200">
                                        <i data-lucide="check" class="h-3 w-3"></i>
                                        Credit Approved
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500 border border-slate-200">
                                        Cash Only
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-center font-mono text-xs font-bold text-slate-700">
                                {{ number_format((int) ($supplier->purchase_invoices_count ?? 0)) }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-1.5 justify-end">
                                    {{-- Edit Button --}}
                                    <button type="button"
                                            onclick="openEditVendorModal({{ json_encode([
                                                'id' => $supplier->id,
                                                'name' => $supplier->name,
                                                'mobile_number' => $supplier->mobile_number,
                                                'contact' => $supplier->contact,
                                                'credit_approved' => (bool) $supplier->credit_approved,
                                            ]) }})"
                                            class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-2xs cursor-pointer">
                                        Edit
                                    </button>

                                    {{-- Toggle Enable / Disable Form --}}
                                    <form method="POST" action="{{ route('admin.cashbook.settings.shop.vendors.toggle-status', ['shop' => $shopKey, 'supplier' => $supplier->id]) }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="rounded-lg border px-2.5 py-1 text-xs font-bold transition shadow-2xs cursor-pointer {{ $isActive ? 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100' : 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100' }}"
                                                title="{{ $isActive ? 'Disable vendor for this shop' : 'Enable vendor for this shop' }}">
                                            {{ $isActive ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>

                                    {{-- Safe Unlink Form --}}
                                    <form method="POST" action="{{ route('admin.cashbook.settings.shop.vendors.unlink', ['shop' => $shopKey, 'supplier' => $supplier->id]) }}" class="inline"
                                          onsubmit="return confirm('Unlink vendor \'{{ addslashes($supplier->name) }}\' from {{ addslashes($currentShop->name) }}? Historical bills will remain intact.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700 hover:bg-rose-100 transition shadow-2xs cursor-pointer"
                                                title="Unlink from this shop">
                                            Unlink
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-12 text-center">
                                <div class="flex flex-col items-center justify-center space-y-2 text-slate-400">
                                    <i data-lucide="truck" class="h-10 w-10 text-slate-300"></i>
                                    <span class="text-sm font-bold text-slate-700">No vendors found for this shop</span>
                                    <p class="text-xs text-slate-500 max-w-sm">Use <strong>+ Link Vendor</strong> to connect an existing supplier or <strong>+ Create Vendor</strong> to add a new local vendor.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($shopSuppliers->hasPages())
            <div class="border-t border-slate-100 p-4">
                {{ $shopSuppliers->links() }}
            </div>
        @endif
    </div>
</div>

{{-- MODAL 1: LINK VENDOR MODAL --}}
<div id="link-vendor-modal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-800">
                    <i data-lucide="link" class="h-4 w-4"></i>
                </span>
                <div>
                    <h3 class="text-base font-black text-slate-900">Link Existing Vendor</h3>
                    <p class="text-[11px] text-slate-500 font-medium">Select a global supplier to link to {{ $currentShop->name }}.</p>
                </div>
            </div>
            <button type="button" onclick="closeLinkVendorModal()" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.cashbook.settings.shop.vendors.link', ['shop' => $shopKey]) }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1.5">Search Vendor</label>
                <div class="relative">
                    <input type="text" id="link-vendor-search-input" placeholder="Type vendor name or phone..."
                           oninput="debounceSearchVendors(this.value)"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 pl-9 pr-3.5 py-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:border-amber-500 focus:outline-none">
                    <i data-lucide="search" class="absolute left-3 top-3 h-4 w-4 text-slate-400"></i>
                </div>
            </div>

            <div>
                <label class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1.5">Select Vendor to Link</label>
                <div id="link-vendor-list" class="max-h-60 overflow-y-auto divide-y divide-slate-100 rounded-xl border border-slate-200 bg-slate-50/50 p-1 space-y-1">
                    <div class="p-4 text-center text-xs text-slate-400 italic">Type above to search vendors...</div>
                </div>
                <input type="hidden" name="supplier_id" id="selected-link-supplier-id" required>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeLinkVendorModal()" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" id="submit-link-vendor-btn" disabled
                        class="rounded-xl bg-slate-900 px-5 py-2 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                    Link Selected Vendor
                </button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL 2: CREATE VENDOR MODAL --}}
<div id="create-vendor-modal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800">
                    <i data-lucide="plus-circle" class="h-4 w-4"></i>
                </span>
                <div>
                    <h3 class="text-base font-black text-slate-900">Create New Vendor</h3>
                    <p class="text-[11px] text-slate-500 font-medium">Create a vendor and link it automatically to {{ $currentShop->name }}.</p>
                </div>
            </div>
            <button type="button" onclick="closeCreateVendorModal()" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.cashbook.settings.shop.vendors.create', ['shop' => $shopKey]) }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1">Vendor / Firm Name *</label>
                <input type="text" name="name" required placeholder="e.g. Metro Produce Hub"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:border-emerald-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1">Mobile / Phone</label>
                    <input type="text" name="mobile_number" placeholder="e.g. 9876543210"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:border-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1">Contact Person</label>
                    <input type="text" name="contact" placeholder="e.g. Rajesh"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:border-emerald-500 focus:outline-none">
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-900">Enable Credit Purchasing</div>
                    <div class="text-[11px] text-slate-500 font-medium">Allow shop credit bills to create vendor liabilities.</div>
                </div>
                <input type="checkbox" name="credit_approved" value="1" checked
                       class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer">
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeCreateVendorModal()" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit"
                        class="rounded-xl bg-emerald-600 px-5 py-2 text-xs font-bold text-white hover:bg-emerald-700 shadow-xs cursor-pointer">
                    Save & Link Vendor
                </button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL 3: EDIT VENDOR MODAL --}}
<div id="edit-vendor-modal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-800">
                    <i data-lucide="edit-3" class="h-4 w-4"></i>
                </span>
                <div>
                    <h3 class="text-base font-black text-slate-900">Edit Vendor</h3>
                    <p class="text-[11px] text-slate-500 font-medium">Update vendor information.</p>
                </div>
            </div>
            <button type="button" onclick="closeEditVendorModal()" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <form id="edit-vendor-form" method="POST" action="" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1">Vendor / Firm Name *</label>
                <input type="text" name="name" id="edit-vendor-name" required
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:border-amber-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1">Mobile / Phone</label>
                    <input type="text" name="mobile_number" id="edit-vendor-mobile"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:border-amber-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1">Contact Person</label>
                    <input type="text" name="contact" id="edit-vendor-contact"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:border-amber-500 focus:outline-none">
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-slate-900">Credit Purchasing Approved</div>
                    <div class="text-[11px] text-slate-500 font-medium">Allow shop credit bills to create liabilities for this vendor.</div>
                </div>
                <input type="checkbox" name="credit_approved" id="edit-vendor-credit-approved" value="1"
                       class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer">
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeEditVendorModal()" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit"
                        class="rounded-xl bg-slate-900 px-5 py-2 text-xs font-bold text-white hover:bg-slate-800 shadow-xs cursor-pointer">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let searchDebounceTimer = null;
    const searchUrl = @json(route('admin.cashbook.settings.shop.vendors.search-global', ['shop' => $shopKey]));
    const updateBaseUrl = @json(url('admin/cashbook/settings/shops/'.$shopKey.'/vendors'));

    function openLinkVendorModal() {
        document.getElementById('link-vendor-modal').classList.remove('hidden');
        document.getElementById('link-vendor-search-input').value = '';
        document.getElementById('selected-link-supplier-id').value = '';
        document.getElementById('submit-link-vendor-btn').disabled = true;
        fetchGlobalVendors('');
    }

    function closeLinkVendorModal() {
        document.getElementById('link-vendor-modal').classList.add('hidden');
    }

    function openCreateVendorModal() {
        document.getElementById('create-vendor-modal').classList.remove('hidden');
    }

    function closeCreateVendorModal() {
        document.getElementById('create-vendor-modal').classList.add('hidden');
    }

    function openEditVendorModal(supplier) {
        document.getElementById('edit-vendor-name').value = supplier.name || '';
        document.getElementById('edit-vendor-mobile').value = supplier.mobile_number || '';
        document.getElementById('edit-vendor-contact').value = supplier.contact || '';
        document.getElementById('edit-vendor-credit-approved').checked = Boolean(supplier.credit_approved);
        document.getElementById('edit-vendor-form').action = updateBaseUrl + '/' + supplier.id;
        document.getElementById('edit-vendor-modal').classList.remove('hidden');
    }

    function closeEditVendorModal() {
        document.getElementById('edit-vendor-modal').classList.add('hidden');
    }

    function debounceSearchVendors(q) {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => {
            fetchGlobalVendors(q);
        }, 300);
    }

    async function fetchGlobalVendors(query) {
        const listEl = document.getElementById('link-vendor-list');
        listEl.innerHTML = '<div class="p-4 text-center text-xs text-slate-400">Loading vendors...</div>';

        try {
            const url = new URL(searchUrl, window.location.origin);
            if (query) url.searchParams.set('q', query);

            const res = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            const suppliers = data.suppliers || [];

            if (suppliers.length === 0) {
                listEl.innerHTML = '<div class="p-4 text-center text-xs text-slate-400">No matching vendors found.</div>';
                return;
            }

            listEl.innerHTML = suppliers.map(s => {
                const alreadyBadge = s.is_already_linked ? '<span class="text-[9px] font-bold bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded">Already Linked</span>' : '';
                return `
                    <label class="flex items-center justify-between p-2.5 rounded-lg hover:bg-white transition cursor-pointer border border-transparent hover:border-slate-200">
                        <div class="flex items-center gap-2.5">
                            <input type="radio" name="supplier_radio" value="${s.id}" onchange="selectSupplierForLink(${s.id})"
                                   class="h-4 w-4 text-slate-900 border-slate-300 focus:ring-slate-900">
                            <div>
                                <div class="text-xs font-bold text-slate-900">${s.name} ${alreadyBadge}</div>
                                <div class="text-[10px] text-slate-500 font-mono">${s.mobile_number || 'No phone'} ${s.contact ? '• ' + s.contact : ''}</div>
                            </div>
                        </div>
                        <span class="text-[10px] font-bold text-slate-400">${s.credit_approved ? 'Credit' : 'Cash'}</span>
                    </label>
                `;
            }).join('');

        } catch (e) {
            listEl.innerHTML = '<div class="p-4 text-center text-xs text-rose-500">Failed to load vendors.</div>';
        }
    }

    function selectSupplierForLink(supplierId) {
        document.getElementById('selected-link-supplier-id').value = supplierId;
        document.getElementById('submit-link-vendor-btn').disabled = false;
    }
</script>
@endsection
