@extends('admin.cashbook.layouts.app')

@section('title', 'New Warehouse Sale — Cashbook')

@section('header_title')
    <i data-lucide="plus-circle" class="w-5 h-5 text-emerald-600"></i> New Warehouse Sale
@endsection

@section('header_subtitle')
    <span class="inline-flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
        <a href="{{ route('admin.cashbook.warehouse-sales') }}" class="hover:text-emerald-600 font-semibold">Warehouse Sales</a>
        <span>/</span>
        <span class="font-bold text-slate-700 dark:text-slate-200">New Sale</span>
    </span>
@endsection

@section('content')
@php
    $pageDate = $date ?? \Carbon\Carbon::today()->toDateString();
@endphp

<div class="mx-auto max-w-7xl space-y-5"
     x-data="cashbookWarehouseSaleCreate()"
     x-init="init()"
     @keydown.window.escape="inventoryModalOpen = false">

    <!-- Error / Notice Banner -->
    <div x-show="errorMessage" x-cloak
         class="rounded-2xl border border-rose-200 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/40 px-4 py-3 text-xs font-bold text-rose-800 dark:text-rose-200 flex items-center justify-between gap-3">
        <span x-text="errorMessage"></span>
        <button type="button" @click="errorMessage = ''" class="text-rose-600 hover:text-rose-900 font-black">✕</button>
    </div>

    <div x-show="noticeMessage" x-cloak
         class="rounded-2xl border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40 px-4 py-3 text-xs font-bold text-amber-800 dark:text-amber-200 flex items-center justify-between gap-3">
        <span x-text="noticeMessage"></span>
        <button type="button" @click="noticeMessage = ''" class="text-amber-600 hover:text-amber-900 font-black">✕</button>
    </div>

    <!-- Top Context Bar -->
    <div class="rounded-3xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 p-4 shadow-xs">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 flex-1 max-w-xl">
                <div>
                    <label for="ws-warehouse" class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Warehouse</label>
                    <select id="ws-warehouse"
                            x-model.number="warehouseId"
                            @change="onWarehouseChange()"
                            class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                        @foreach($allowedWarehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="ws-date" class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Business Date</label>
                    <input id="ws-date"
                           type="date"
                           x-model="businessDate"
                           @change="onWarehouseChange()"
                           class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 px-3 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.cashbook.warehouse-sales') }}"
                   class="inline-flex h-10 items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 px-4 text-xs font-bold text-slate-700 transition">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    <span>Back to Warehouse Sales</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Workspace -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

        <!-- Left Column: Product Search & Sale Items Table -->
        <div class="lg:col-span-8 space-y-5">

            <!-- Search Products Card -->
            <div class="rounded-3xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 p-4 shadow-xs space-y-3 relative">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 dark:text-slate-100">Search Products</h2>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Search by product name or SKU. Use ↑ / ↓ and Enter to add quickly.</p>
                    </div>
                    <span x-show="searchingProducts" x-cloak class="text-[11px] font-bold text-emerald-600">Searching...</span>
                </div>

                <div class="relative">
                    <div class="relative flex items-center">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 pointer-events-none"></i>
                        <input id="ws-product-search"
                               type="text"
                               x-model="productQuery"
                               @input.debounce.180ms="searchProducts()"
                               @focus="openProductResults()"
                               @keydown.arrow-down.prevent="moveProductHighlight(1)"
                               @keydown.arrow-up.prevent="moveProductHighlight(-1)"
                               @keydown.enter.prevent="selectHighlightedProduct()"
                               @keydown.escape="productDropdownOpen = false"
                               placeholder="Search products by name or SKU..."
                               autocomplete="off"
                               class="w-full h-11 rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 pl-10 pr-10 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                        <button type="button"
                                x-show="productQuery !== ''"
                                x-cloak
                                @click="productQuery = ''; searchProducts()"
                                class="absolute right-3 text-slate-400 hover:text-slate-700 text-xs font-bold">✕</button>
                    </div>

                    <!-- Live Search Results Dropdown -->
                    <div x-show="productDropdownOpen"
                         x-cloak
                         @click.outside="productDropdownOpen = false"
                         class="absolute z-30 mt-2 w-full rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900 shadow-xl max-h-72 overflow-y-auto custom-scrollbar divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-if="productResults.length === 0">
                            <div class="p-4 text-center text-xs font-semibold text-slate-400">
                                No matching products found.
                            </div>
                        </template>
                        <template x-for="(prod, idx) in productResults" :key="prod.id">
                            <button type="button"
                                    @click="addProduct(prod)"
                                    @mouseenter="highlightedIndex = idx"
                                    :class="highlightedIndex === idx ? 'bg-emerald-50 dark:bg-emerald-950/40' : 'hover:bg-slate-50 dark:hover:bg-slate-800/70'"
                                    class="w-full px-4 py-2.5 text-left flex items-center justify-between gap-3 transition">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-black text-slate-900 dark:text-slate-100 truncate" x-text="prod.name"></span>
                                        <template x-if="prod.sku">
                                            <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[10px] font-mono font-bold text-slate-600 dark:text-slate-300" x-text="prod.sku"></span>
                                        </template>
                                    </div>
                                    <div class="mt-0.5 flex items-center gap-2 text-[11px]">
                                        <span @click.stop="openInventoryPopup(prod.id, prod.name)"
                                              title="View inventory details"
                                              :class="Number(prod.available_stock) < 0 ? 'text-rose-600 dark:text-rose-400' : (Number(prod.available_stock) === 0 ? 'text-slate-500 dark:text-slate-400' : 'text-emerald-700 dark:text-emerald-400')"
                                              class="font-bold font-mono hover:underline cursor-pointer"
                                              x-text="'Stock: ' + formatNum(prod.available_stock) + ' ' + prod.unit"></span>
                                        <span class="text-slate-300">•</span>
                                        <span class="text-slate-500 dark:text-slate-400 font-semibold" x-text="'Unit: ' + prod.unit"></span>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="font-mono text-xs font-black text-slate-900 dark:text-slate-100" x-text="'₹' + formatNum(prod.price)"></div>
                                    <span class="text-[10px] font-bold text-emerald-600" x-text="isProductAdded(prod.id) ? 'Added' : '+ Add'"></span>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Sale Items Table Card -->
            <div class="rounded-3xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 overflow-hidden shadow-xs">
                <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 dark:text-slate-100">Sale Items</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Adjust quantity, unit, or selling price. Totals update automatically.</p>
                    </div>
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
                        <span class="font-black text-slate-900 dark:text-slate-100" x-text="items.length"></span> items
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-800/60 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="py-3 px-3">Product</th>
                                <th class="py-3 px-3 text-right">Available Stock</th>
                                <th class="py-3 px-3 text-right w-24">Quantity</th>
                                <th class="py-3 px-3 w-24">Unit</th>
                                <th class="py-3 px-3 text-right w-28">Selling Price</th>
                                <th class="py-3 px-4 text-right">Total</th>
                                <th class="py-3 px-3 text-center w-14">Remove</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium text-slate-800 dark:text-slate-200">
                            <template x-if="items.length === 0">
                                <tr>
                                    <td colspan="7" class="py-10 text-center text-slate-400 font-semibold">
                                        Use the Search Products box above to add items to this sale.
                                    </td>
                                </tr>
                            </template>
                            <template x-for="(item, index) in items" :key="item.product_id">
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3 px-3">
                                        <button type="button"
                                                @click="openInventoryPopup(item.product_id, item.name)"
                                                title="View warehouse inventory details"
                                                class="text-left font-bold text-slate-900 dark:text-slate-100 hover:text-emerald-600 dark:hover:text-emerald-400 hover:underline transition cursor-pointer"
                                                x-text="item.name"></button>
                                        <div x-show="item.sku" class="text-[10px] font-mono text-slate-400" x-text="item.sku"></div>
                                    </td>
                                    <td class="py-3 px-3 text-right whitespace-nowrap">
                                        <button type="button"
                                                @click="openInventoryPopup(item.product_id, item.name)"
                                                title="View warehouse inventory details"
                                                :class="Number(item.available_stock) < 0 ? 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-800' : (Number(item.available_stock) === 0 ? 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700' : 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800')"
                                              class="inline-block px-2 py-0.5 rounded-md text-[11px] font-mono font-bold border hover:opacity-80 transition cursor-pointer"
                                              x-text="formatNum(item.available_stock) + ' ' + item.base_unit"></button>
                                    </td>
                                    <td class="py-3 px-3">
                                        <input type="number"
                                               step="0.01"
                                               min="0.01"
                                               x-model.number="item.qty"
                                               :data-qty-product="item.product_id"
                                               class="w-full h-9 rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 px-2.5 text-right font-mono text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                                    </td>
                                    <td class="py-3 px-3">
                                        <select x-model="item.unit"
                                                class="w-full h-9 rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 px-2 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                                            <option value="kg">kg</option>
                                            <option value="pcs">pcs</option>
                                            <option value="box">box</option>
                                            <option value="tray">tray</option>
                                            <option value="bunch">bunch</option>
                                            <option value="unit">unit</option>
                                        </select>
                                    </td>
                                    <td class="py-3 px-3">
                                        <input type="number"
                                               step="0.01"
                                               min="0"
                                               x-model.number="item.unit_price"
                                               class="w-full h-9 rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 px-2.5 text-right font-mono text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono font-black text-slate-950 dark:text-slate-100 whitespace-nowrap"
                                        x-text="'₹' + formatNum((Number(item.qty) || 0) * (Number(item.unit_price) || 0))"></td>
                                    <td class="py-3 px-3 text-center">
                                        <button type="button"
                                                @click="removeItem(index)"
                                                title="Remove item"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40 transition">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column: Customer, Payment & Sale Summary -->
        <div class="lg:col-span-4 space-y-5">

            <!-- Customer Card -->
            <div class="rounded-3xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 p-4 shadow-xs space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-black text-slate-900 dark:text-slate-100">Customer</h2>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                          x-text="customerTypeLabel()"></span>
                </div>

                <!-- Customer Type Tabs -->
                <div class="grid grid-cols-3 gap-1.5 rounded-2xl bg-slate-100 dark:bg-slate-800 p-1">
                    <button type="button"
                            @click="setCustomerType('cash_sales')"
                            :class="customerType === 'cash_sales' ? 'bg-white dark:bg-slate-900 text-slate-950 dark:text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="rounded-xl py-2 text-[11px] font-black transition">
                        Cash Sales
                    </button>
                    <button type="button"
                            @click="setCustomerType('shop')"
                            :class="customerType === 'shop' ? 'bg-white dark:bg-slate-900 text-slate-950 dark:text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="rounded-xl py-2 text-[11px] font-black transition">
                        Shop
                    </button>
                    <button type="button"
                            @click="setCustomerType('walking_customer')"
                            :class="customerType === 'walking_customer' ? 'bg-white dark:bg-slate-900 text-slate-950 dark:text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                            class="rounded-xl py-2 text-[11px] font-black transition">
                        Walking
                    </button>
                </div>

                <!-- Shop Selector -->
                <div x-show="customerType === 'shop'" x-cloak class="space-y-2">
                    <label for="ws-shop-id" class="block text-[10px] font-black uppercase tracking-wider text-slate-400">Select Shop</label>
                    <select id="ws-shop-id"
                            x-model.number="shopId"
                            class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 px-3 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                        <option value="">Choose a shop...</option>
                        @foreach($shops as $sh)
                            <option value="{{ $sh->id }}">{{ $sh->name }} {{ $sh->code ? "({$sh->code})" : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Walking Customer Search & Inline Add -->
                <div x-show="customerType === 'walking_customer'" x-cloak class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <label for="ws-customer-search" class="block text-[10px] font-black uppercase tracking-wider text-slate-400">Search or Enter Customer</label>
                        <button type="button"
                                @click="showNewCustomerForm = !showNewCustomerForm"
                                class="text-[11px] font-black text-emerald-600 hover:text-emerald-700">
                            <span x-text="showNewCustomerForm ? 'Cancel' : '+ New Customer'"></span>
                        </button>
                    </div>

                    <div x-show="!showNewCustomerForm" class="relative space-y-2">
                        <input id="ws-customer-search"
                               type="text"
                               x-model="customerSearch"
                               @input.debounce.200ms="searchCustomers()"
                               @focus="customerDropdownOpen = true; searchCustomers()"
                               placeholder="Search existing customer or type name..."
                               class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 px-3 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">

                        <div x-show="customerDropdownOpen && customerResults.length > 0"
                             x-cloak
                             @click.outside="customerDropdownOpen = false"
                             class="absolute z-20 w-full rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900 shadow-lg max-h-44 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
                            <template x-for="cust in customerResults" :key="cust.id">
                                <button type="button"
                                        @click="selectCustomer(cust)"
                                        class="w-full px-3 py-2 text-left hover:bg-emerald-50 dark:hover:bg-emerald-950/40 flex items-center justify-between text-xs">
                                    <span class="font-bold text-slate-900 dark:text-slate-100" x-text="cust.name"></span>
                                    <span class="font-mono text-[10px] text-slate-400" x-text="cust.phone || ''"></span>
                                </button>
                            </template>
                        </div>

                        <div x-show="customerId" x-cloak class="flex items-center justify-between rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 px-3 py-2 text-xs">
                            <div>
                                <span class="font-black text-emerald-900 dark:text-emerald-200" x-text="customerName"></span>
                                <span x-show="customerPhone" class="ml-1 font-mono text-[10px] text-emerald-700 dark:text-emerald-400" x-text="customerPhone"></span>
                            </div>
                            <button type="button" @click="clearSelectedCustomer()" class="text-[10px] font-black text-emerald-700 hover:text-emerald-900">Clear</button>
                        </div>
                    </div>

                    <!-- Inline Create Customer Form -->
                    <div x-show="showNewCustomerForm" x-cloak class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 p-3 space-y-2">
                        <input type="text"
                               x-model="newCustomer.name"
                               placeholder="Customer full name *"
                               class="w-full h-9 rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 px-3 text-xs font-semibold focus:border-emerald-500 focus:outline-none">
                        <input type="text"
                               x-model="newCustomer.phone"
                               placeholder="Phone number (optional)"
                               class="w-full h-9 rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 px-3 text-xs font-semibold focus:border-emerald-500 focus:outline-none">
                        <button type="button"
                                @click="createCustomer()"
                                :disabled="creatingCustomer"
                                class="w-full h-9 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-black transition disabled:opacity-50">
                            <span x-text="creatingCustomer ? 'Saving...' : 'Save & Select Customer'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Payment & Summary Card -->
            <div class="rounded-3xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900 p-4 shadow-xs space-y-4">
                <h2 class="text-sm font-black text-slate-900 dark:text-slate-100">Payment &amp; Summary</h2>

                <!-- Payment Method -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Payment Method</label>
                    <div class="grid grid-cols-5 gap-1.5">
                        @foreach(['cash' => 'Cash', 'upi' => 'UPI', 'bank' => 'Bank', 'card' => 'Card', 'credit' => 'Credit'] as $methodKey => $methodLabel)
                            <button type="button"
                                    @click="setPaymentMethod('{{ $methodKey }}')"
                                    :class="paymentMethod === '{{ $methodKey }}' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100'"
                                    class="h-9 rounded-xl border text-[11px] font-black transition">
                                {{ $methodLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Money Holder (for Cash) -->
                <div x-show="paymentMethod === 'cash'" x-cloak class="space-y-2.5 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-800/50 p-3">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Money Holder</label>
                        <div class="grid grid-cols-2 gap-1.5">
                            <button type="button"
                                    @click="moneyHolderType = 'company'; staffDropdownOpen = false"
                                    :class="moneyHolderType === 'company' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                    class="h-8 rounded-lg border text-[11px] font-bold transition">
                                Company Direct
                            </button>
                            <button type="button"
                                    @click="moneyHolderType = 'user'"
                                    :class="moneyHolderType === 'user' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                                    class="h-8 rounded-lg border text-[11px] font-bold transition">
                                Held by Staff
                            </button>
                        </div>
                    </div>

                    <div x-show="moneyHolderType === 'user'" x-cloak class="relative">
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Staff Member</label>
                        <button type="button"
                                @click="staffDropdownOpen = !staffDropdownOpen"
                                class="w-full h-9 rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 px-3 text-left text-xs font-bold text-slate-900 flex items-center justify-between gap-2 focus:border-emerald-500 focus:outline-none">
                            <span class="truncate" x-text="selectedStaffLabel()"></span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                        </button>

                        <div x-show="staffDropdownOpen"
                             x-cloak
                             @click.outside="staffDropdownOpen = false"
                             class="absolute z-30 mt-1.5 w-full rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900 shadow-xl overflow-hidden">
                            <div class="p-2 border-b border-slate-100 dark:border-slate-800">
                                <input type="text"
                                       x-model="staffSearch"
                                       placeholder="Search staff by name or role..."
                                       class="w-full h-8 rounded-lg border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 px-2.5 text-xs font-semibold focus:border-emerald-500 focus:outline-none">
                            </div>
                            <div class="max-h-52 overflow-y-auto custom-scrollbar divide-y divide-slate-100 dark:divide-slate-800">
                                <template x-if="filteredStaff().length === 0">
                                    <div class="p-3 text-center text-[11px] font-semibold text-slate-400">No matching staff found.</div>
                                </template>
                                <template x-for="st in filteredStaff()" :key="st.id">
                                    <button type="button"
                                            @click="moneyHolderUserId = st.id; staffDropdownOpen = false"
                                            :class="Number(moneyHolderUserId) === Number(st.id) ? 'bg-emerald-50 dark:bg-emerald-950/40' : 'hover:bg-slate-50 dark:hover:bg-slate-800'"
                                            class="w-full px-3 py-2 text-left flex items-center justify-between gap-2 text-xs transition">
                                        <span class="font-bold text-slate-900 dark:text-slate-100 truncate" x-text="st.name"></span>
                                        <span :class="st.is_purchaser ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                                              class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase shrink-0"
                                              x-text="st.role_label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Discount, Reference & Notes -->
                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label for="ws-discount" class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Discount (₹)</label>
                        <input id="ws-discount"
                               type="number"
                               step="0.01"
                               min="0"
                               :max="subtotal().toFixed(2)"
                               x-model.number="discount"
                               @input="validateDiscount()"
                               class="w-full h-9 rounded-xl border bg-slate-50 dark:bg-slate-800 dark:text-slate-100 px-3 text-right font-mono text-xs font-bold text-slate-900 focus:bg-white focus:outline-none"
                               :class="discountError ? 'border-rose-400 focus:border-rose-500' : 'border-slate-200 dark:border-slate-700 focus:border-emerald-500'">
                        <p x-show="discountError" x-cloak class="mt-1 text-[10px] font-bold text-rose-600" x-text="discountError"></p>
                    </div>
                    <div>
                        <label for="ws-reference" class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Reference</label>
                        <input id="ws-reference"
                               type="text"
                               x-model="reference"
                               placeholder="UPI / Ref #"
                               class="w-full h-9 rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 px-3 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                    </div>
                </div>

                <div>
                    <label for="ws-notes" class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Notes</label>
                    <input id="ws-notes"
                           type="text"
                           x-model="notes"
                           placeholder="Optional sale remarks..."
                           class="w-full h-9 rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 px-3 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                </div>

                <!-- Totals Summary -->
                <div class="border-t border-slate-200 dark:border-slate-800 pt-3 space-y-1.5 text-xs">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                        <span>Subtotal</span>
                        <span class="font-mono font-bold text-slate-800 dark:text-slate-200" x-text="'₹' + formatNum(subtotal())"></span>
                    </div>
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400" x-show="Number(discount) > 0">
                        <span>Discount</span>
                        <span class="font-mono font-bold text-rose-600" x-text="'-₹' + formatNum(discount)"></span>
                    </div>
                    <div class="flex items-center justify-between pt-1.5 border-t border-slate-100 dark:border-slate-800">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300">Total Amount</span>
                        <span class="font-mono text-lg font-black text-slate-950 dark:text-white" x-text="'₹' + formatNum(grandTotal())"></span>
                    </div>
                </div>

                <button type="button"
                        @click="submitSale()"
                        :disabled="submitting || items.length === 0"
                        class="w-full h-11 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black shadow-sm transition flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                    <span x-text="submitting ? 'Confirming Sale...' : 'Confirm Sale'"></span>
                </button>
            </div>
        </div>
    </div>
    <!-- Warehouse Inventory Product Card Popup -->
    <div x-show="inventoryModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs p-3 sm:p-4"
         @click.self="inventoryModalOpen = false">
        <div class="w-[1100px] max-w-[95vw] max-h-[90vh] rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xl overflow-hidden flex flex-col">
            <div class="px-5 py-3.5 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3 shrink-0 bg-white dark:bg-slate-900">
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-600" x-text="'Warehouse Inventory · ' + selectedWarehouseName()"></p>
                    <h3 class="text-sm font-black text-slate-900 dark:text-slate-100 truncate" x-text="inventoryModalTitle"></h3>
                </div>
                <button type="button"
                        @click="inventoryModalOpen = false"
                        aria-label="Close inventory details"
                        class="h-8 w-8 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-white flex items-center justify-center text-xs font-black transition cursor-pointer">
                    ✕
                </button>
            </div>

            <div class="p-4 sm:p-5 overflow-y-auto custom-scrollbar flex-1">
                <div x-show="inventoryModalLoading" class="py-12 text-center text-xs font-bold text-slate-400">
                    Loading warehouse inventory...
                </div>
                <div x-show="!inventoryModalLoading && inventoryModalError" class="rounded-xl border border-rose-200 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/40 p-3 text-xs font-bold text-rose-700 dark:text-rose-300" x-text="inventoryModalError"></div>
                <div x-show="!inventoryModalLoading && !inventoryModalError" x-html="inventoryModalHtml"></div>
            </div>
        </div>
    </div>
</div>

<script>
function cashbookWarehouseSaleCreate() {
    return {
        warehouseId: {{ (int) $selectedWarehouseId }},
        businessDate: @json($pageDate),
        productsCache: @json($productsWithStock),
        productQuery: '',
        productResults: [],
        productDropdownOpen: false,
        highlightedIndex: 0,
        searchingProducts: false,

        items: [],

        customerType: 'cash_sales',
        shopId: '',
        customerId: null,
        customerName: 'Cash Sales',
        customerPhone: '',
        customerSearch: '',
        customerResults: [],
        customerDropdownOpen: false,
        showNewCustomerForm: false,
        creatingCustomer: false,
        newCustomer: { name: '', phone: '' },

        paymentMethod: 'cash',
        moneyHolderType: 'company',
        staffUsers: @json($staffUsers),
        staffSearch: '',
        staffDropdownOpen: false,
        moneyHolderUserId: {{ (int) ($staffUsers->first()['id'] ?? auth()->id()) }},
        discount: 0,
        discountError: '',
        reference: '',
        notes: '',

        submitting: false,
        errorMessage: '',
        noticeMessage: '',

        inventoryModalOpen: false,
        inventoryModalTitle: '',
        inventoryModalLoading: false,
        inventoryModalHtml: '',
        inventoryModalError: '',

        csrfToken: @json(csrf_token()),
        storeUrl: @json(route('admin.cashbook.warehouse-sales.store')),
        searchProductsUrl: @json(route('admin.cashbook.warehouse-sales.search-products')),
        productInventoryUrlBase: @json(url('admin/cashbook/warehouse-sales/product-inventory')),
        searchCustomersUrl: @json(route('admin.cashbook.warehouse-sales.search-customers')),
        storeCustomerUrl: @json(route('admin.cashbook.warehouse-sales.customers.store')),

        init() {
            this.productResults = this.productsCache.slice(0, 20);
            this.$nextTick(() => {
                if (window.lucide) window.lucide.createIcons();
            });
        },

        formatNum(val) {
            return (Number(val) || 0).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },

        async onWarehouseChange() {
            this.searchingProducts = true;
            try {
                const url = `${this.searchProductsUrl}?warehouse_id=${encodeURIComponent(this.warehouseId)}&date=${encodeURIComponent(this.businessDate)}&q=${encodeURIComponent(this.productQuery)}`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (res.ok && json.data) {
                    if (this.productQuery === '') {
                        this.productsCache = json.data;
                    }
                    this.productResults = json.data.slice(0, 25);
                    this.items.forEach(item => {
                        const match = json.data.find(p => Number(p.id) === Number(item.product_id));
                        if (match) {
                            item.available_stock = Number(match.available_stock || 0);
                        }
                    });
                }
            } catch (e) {
                this.errorMessage = 'Failed to refresh warehouse stock.';
            } finally {
                this.searchingProducts = false;
            }
        },

        openProductResults() {
            this.productDropdownOpen = true;
            if (this.productResults.length === 0) {
                this.productResults = this.productsCache.slice(0, 20);
            }
        },

        async searchProducts() {
            this.productDropdownOpen = true;
            this.highlightedIndex = 0;
            this.searchingProducts = true;
            try {
                const url = `${this.searchProductsUrl}?warehouse_id=${encodeURIComponent(this.warehouseId)}&date=${encodeURIComponent(this.businessDate)}&q=${encodeURIComponent(this.productQuery)}`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (res.ok && json.data) {
                    this.productResults = json.data.slice(0, 25);
                }
            } catch (e) {
                const q = this.productQuery.toLowerCase();
                this.productResults = this.productsCache.filter(p =>
                    (p.name || '').toLowerCase().includes(q) || (p.sku || '').toLowerCase().includes(q)
                ).slice(0, 25);
            } finally {
                this.searchingProducts = false;
            }
        },

        moveProductHighlight(delta) {
            if (!this.productDropdownOpen) {
                this.openProductResults();
                return;
            }
            if (this.productResults.length === 0) return;
            const next = this.highlightedIndex + delta;
            if (next < 0) {
                this.highlightedIndex = this.productResults.length - 1;
            } else if (next >= this.productResults.length) {
                this.highlightedIndex = 0;
            } else {
                this.highlightedIndex = next;
            }
        },

        selectHighlightedProduct() {
            if (this.productDropdownOpen && this.productResults[this.highlightedIndex]) {
                this.addProduct(this.productResults[this.highlightedIndex]);
            }
        },

        isProductAdded(productId) {
            return this.items.some(i => Number(i.product_id) === Number(productId));
        },

        addProduct(prod) {
            this.noticeMessage = '';
            const existing = this.items.find(i => Number(i.product_id) === Number(prod.id));
            if (existing) {
                existing.qty = Number((Number(existing.qty || 0) + 1).toFixed(2));
                this.noticeMessage = `${prod.name} is already in the table — quantity incremented.`;
                this.productDropdownOpen = false;
                this.productQuery = '';
                this.$nextTick(() => {
                    const input = document.querySelector(`[data-qty-product="${prod.id}"]`);
                    if (input) input.focus();
                });
                return;
            }

            this.items.push({
                product_id: prod.id,
                name: prod.name,
                sku: prod.sku || '',
                base_unit: prod.unit || 'kg',
                unit: prod.unit || 'kg',
                available_stock: Number(prod.available_stock || 0),
                qty: 1,
                unit_price: Number(prod.price || 0),
            });

            this.productDropdownOpen = false;
            this.productQuery = '';
            this.$nextTick(() => {
                if (window.lucide) window.lucide.createIcons();
                const input = document.querySelector(`[data-qty-product="${prod.id}"]`);
                if (input) input.focus();
            });
        },

        removeItem(index) {
            this.items.splice(index, 1);
        },

        async openInventoryPopup(productId, productName) {
            this.inventoryModalTitle = productName || 'Product Inventory';
            this.inventoryModalOpen = true;
            this.inventoryModalLoading = true;
            this.inventoryModalError = '';
            this.inventoryModalHtml = '';

            try {
                const url = `${this.productInventoryUrlBase}/${encodeURIComponent(productId)}?warehouse_id=${encodeURIComponent(this.warehouseId)}&date=${encodeURIComponent(this.businessDate)}`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (!res.ok || !json.html) {
                    this.inventoryModalError = json.message || 'Could not load product inventory details.';
                    return;
                }
                this.inventoryModalHtml = json.html;
            } catch (e) {
                this.inventoryModalError = 'Network error while loading inventory details.';
            } finally {
                this.inventoryModalLoading = false;
            }
        },

        setCustomerType(type) {
            this.customerType = type;
            this.errorMessage = '';
            if (type === 'cash_sales') {
                this.shopId = '';
                this.customerId = null;
                this.customerName = 'Cash Sales';
                this.customerPhone = '';
            } else if (type === 'shop') {
                this.customerId = null;
                this.customerName = '';
                this.customerPhone = '';
            } else {
                this.shopId = '';
            }
        },

        customerTypeLabel() {
            if (this.customerType === 'shop') return 'Shop';
            if (this.customerType === 'walking_customer') return 'Walking';
            return 'Cash Sales';
        },

        async searchCustomers() {
            try {
                const url = `${this.searchCustomersUrl}?q=${encodeURIComponent(this.customerSearch)}`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (res.ok && json.data) {
                    this.customerResults = json.data;
                }
            } catch (e) {
                this.customerResults = [];
            }
        },

        selectCustomer(cust) {
            this.customerId = cust.id;
            this.customerName = cust.name;
            this.customerPhone = cust.phone || '';
            this.customerSearch = cust.name;
            this.customerDropdownOpen = false;
        },

        clearSelectedCustomer() {
            this.customerId = null;
            this.customerName = '';
            this.customerPhone = '';
            this.customerSearch = '';
        },

        async createCustomer() {
            if (!this.newCustomer.name.trim()) {
                this.errorMessage = 'Please enter the customer name.';
                return;
            }
            this.creatingCustomer = true;
            this.errorMessage = '';
            try {
                const res = await fetch(this.storeCustomerUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(this.newCustomer),
                });
                const json = await res.json();
                if (!res.ok) {
                    this.errorMessage = json.message || 'Could not create customer.';
                    return;
                }
                if (json.customer) {
                    this.selectCustomer(json.customer);
                }
                this.newCustomer = { name: '', phone: '' };
                this.showNewCustomerForm = false;
            } catch (e) {
                this.errorMessage = 'Failed to create customer.';
            } finally {
                this.creatingCustomer = false;
            }
        },

        setPaymentMethod(method) {
            this.paymentMethod = method;
            if (method !== 'cash') {
                this.moneyHolderType = 'company';
                this.staffDropdownOpen = false;
            }
        },

        filteredStaff() {
            const q = (this.staffSearch || '').trim().toLowerCase();
            if (!q) return this.staffUsers;
            return this.staffUsers.filter(s =>
                (s.name || '').toLowerCase().includes(q) || (s.role_label || '').toLowerCase().includes(q)
            );
        },

        selectedStaffLabel() {
            const found = this.staffUsers.find(s => Number(s.id) === Number(this.moneyHolderUserId));
            return found ? `${found.name} (${found.role_label})` : 'Select staff member...';
        },

        selectedWarehouseName() {
            const sel = document.getElementById('ws-warehouse');
            return sel && sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex].text : 'Selected Warehouse';
        },

        round2(val) {
            return Math.round((Number(val) || 0) * 100) / 100;
        },

        subtotal() {
            return this.round2(this.items.reduce((sum, item) => {
                return sum + this.round2((Number(item.qty) || 0) * (Number(item.unit_price) || 0));
            }, 0));
        },

        validateDiscount() {
            const sub = this.subtotal();
            const disc = this.round2(Math.max(0, Number(this.discount) || 0));
            if (disc > sub) {
                this.discountError = 'Discount cannot exceed subtotal.';
                return false;
            }
            this.discountError = '';
            return true;
        },

        grandTotal() {
            const sub = this.subtotal();
            const disc = this.round2(Math.max(0, Number(this.discount) || 0));
            return Math.max(0, this.round2(sub - disc));
        },

        async submitSale() {
            this.errorMessage = '';
            if (this.items.length === 0) {
                this.errorMessage = 'Please add at least one product.';
                return;
            }
            if (!this.validateDiscount()) {
                this.errorMessage = this.discountError;
                return;
            }
            if (this.customerType === 'shop' && !this.shopId) {
                this.errorMessage = 'Please select a shop.';
                return;
            }

            this.submitting = true;
            try {
                const payload = {
                    warehouse_id: this.warehouseId,
                    business_date: this.businessDate,
                    customer_type: this.customerType,
                    shop_id: this.customerType === 'shop' ? Number(this.shopId) : null,
                    customer_id: this.customerType === 'walking_customer' ? this.customerId : null,
                    customer_name: this.customerType === 'walking_customer'
                        ? (this.customerName || this.customerSearch || 'Walking Customer')
                        : null,
                    customer_phone: this.customerType === 'walking_customer' ? this.customerPhone : null,
                    payment_method: this.paymentMethod,
                    money_holder_type: this.paymentMethod === 'cash' ? this.moneyHolderType : 'company',
                    money_holder_user_id: (this.paymentMethod === 'cash' && this.moneyHolderType === 'user')
                        ? Number(this.moneyHolderUserId)
                        : null,
                    discount: Number(this.discount) || 0,
                    reference: this.reference || null,
                    notes: this.notes || null,
                    items: this.items.map(i => ({
                        product_id: i.product_id,
                        qty: Number(i.qty),
                        unit: i.unit,
                        unit_price: Number(i.unit_price),
                    })),
                };

                const res = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(payload),
                });

                const json = await res.json();
                if (!res.ok) {
                    const firstErr = json.errors ? Object.values(json.errors).flat()[0] : null;
                    this.errorMessage = firstErr || json.message || 'Failed to confirm sale.';
                    return;
                }

                window.location.href = json.redirect_url;
            } catch (e) {
                this.errorMessage = 'Network error while confirming sale.';
            } finally {
                this.submitting = false;
            }
        }
    };
}
</script>
@endsection
