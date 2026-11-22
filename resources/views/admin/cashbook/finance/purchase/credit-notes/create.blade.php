@extends('admin.cashbook.layouts.app')

@section('title', 'New Purchase Credit Note — ' . $supplier->name)

@section('header_title')
    <i data-lucide="file-plus-2" class="h-5 w-5 text-emerald-600"></i>
    <span>New Purchase Credit Note</span>
@endsection

@section('header_subtitle')
    Record vendor credit, goods returned to vendor, physical damage write-offs, or refund received.
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-6" x-data="purchaseCreditNoteForm">
    {{-- Breadcrumb & Top Bar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $supplier->public_uuid]) }}#credits"
               class="inline-flex items-center gap-1.5 text-xs font-black text-emerald-700 hover:text-emerald-800">
                <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
                <span>Back to {{ $supplier->name }}</span>
            </a>
            <h1 class="mt-1 text-2xl font-black text-slate-950">Create Purchase Credit Note</h1>
            <p class="text-xs text-slate-500">Vendor: <span class="font-bold text-slate-800">{{ $supplier->name }}</span> (Code: {{ $supplier->code ?? '#' . $supplier->id }})</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $supplier->public_uuid]) }}#credits"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-black text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
            <button type="submit" form="credit-note-form"
                    :disabled="isSubmitting || isCheckingStock || items.length === 0 || totalAmount() <= 0"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2 text-xs font-black text-white hover:bg-emerald-700 disabled:opacity-50">
                <i data-lucide="check" class="h-4 w-4"></i>
                <span x-text="isCheckingStock ? 'Checking Stock...' : (isSubmitting ? 'Saving...' : 'Create Credit Note')"></span>
            </button>
        </div>
    </div>

    {{-- Error Banner --}}
    @if ($errors->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-xs font-bold text-rose-800">
            <div class="flex items-center gap-2 font-black text-rose-900">
                <i data-lucide="alert-circle" class="h-4 w-4"></i>
                <span>Please fix the following validation errors:</span>
            </div>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="credit-note-form" method="POST"
          action="{{ route('admin.cashbook.finance.purchase.vendors.credit-notes.store', $supplier) }}"
          @submit.prevent="handleSubmit($event)">
        @csrf
        <input type="hidden" name="allow_negative_stock" :value="allowNegativeStock ? 1 : 0">

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Left 2 cols: Main Parameters & Line Items --}}
            <div class="space-y-6 lg:col-span-2">
                {{-- Card 1: Configuration & Primary Routing --}}
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-5">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-500">1. Credit Note Configuration</h2>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {{-- Credit Date --}}
                        <div>
                            <label class="block text-xs font-black text-slate-700">Credit Date <span class="text-rose-500">*</span></label>
                            <input type="date" name="credit_date" x-model="creditDate" required
                                   class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-900 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">
                        </div>

                        {{-- Reason --}}
                        <div>
                            <label class="block text-xs font-black text-slate-700">Credit Reason <span class="text-rose-500">*</span></label>
                            <select name="reason" x-model="reason" required
                                    class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-900 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">
                                @foreach($reasons as $reasonCase)
                                    <option value="{{ $reasonCase->value }}">{{ $reasonCase->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Searchable Linked Purchase Invoice (Optional) --}}
                    <div class="relative" @click.outside="isInvoiceDropdownOpen = false">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-black text-slate-700">Link to Purchase Invoice / Bill (Optional)</label>
                            <span class="text-[11px] text-slate-400">Search by invoice #, amount, or date to auto-populate items & remaining debt</span>
                        </div>

                        {{-- Hidden input for form submission --}}
                        <input type="hidden" name="purchase_invoice_id" :value="purchaseInvoiceId">

                        <div class="relative mt-1.5">
                            <div class="relative flex items-center">
                                <i data-lucide="search" class="absolute left-3 h-3.5 w-3.5 text-slate-400 pointer-events-none"></i>
                                <input type="text"
                                       x-model="invoiceSearch"
                                       @focus="isInvoiceDropdownOpen = true"
                                       @input="isInvoiceDropdownOpen = true"
                                       :placeholder="selectedInvoiceData ? (selectedInvoiceData.invoice_number + ' (₹' + formatMoney(selectedInvoiceData.net_amount) + ') — Click to change') : 'Search bill by invoice #, date, or amount (₹)...'"
                                       class="w-full rounded-lg border border-slate-300 pl-9 pr-16 py-2 text-xs font-medium text-slate-900 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">

                                <div class="absolute right-2 flex items-center gap-1">
                                    <template x-if="purchaseInvoiceId">
                                        <button type="button" @click="selectInvoice(null)" title="Unlink bill (Standalone Credit)"
                                                class="rounded p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition">
                                            <i data-lucide="x" class="h-3.5 w-3.5"></i>
                                        </button>
                                    </template>
                                    <button type="button" @click="isInvoiceDropdownOpen = !isInvoiceDropdownOpen"
                                            class="rounded p-1 text-slate-400 hover:text-slate-600 transition">
                                        <i data-lucide="chevron-down" class="h-3.5 w-3.5 transition-transform" :class="isInvoiceDropdownOpen ? 'rotate-180' : ''"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Dropdown options --}}
                            <div x-show="isInvoiceDropdownOpen"
                                 x-cloak
                                 x-transition
                                 class="absolute left-0 right-0 z-30 mt-1 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl divide-y divide-slate-100">

                                {{-- Standalone option --}}
                                <div @click="selectInvoice(null)"
                                     class="flex items-center justify-between p-3 cursor-pointer hover:bg-slate-50 transition"
                                     :class="!purchaseInvoiceId ? 'bg-emerald-50/60' : ''">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="file-minus" class="h-4 w-4 text-slate-400"></i>
                                        <div>
                                            <div class="text-xs font-black text-slate-800">— Standalone / Direct Vendor Credit —</div>
                                            <div class="text-[10px] text-slate-400">No linked invoice. Excess credit will remain as Vendor Advance.</div>
                                        </div>
                                    </div>
                                    <span x-show="!purchaseInvoiceId" class="text-xs font-bold text-emerald-700">Active</span>
                                </div>

                                {{-- Invoices list --}}
                                <template x-for="inv in filteredInvoices()" :key="inv.id">
                                    <div @click="selectInvoice(inv)"
                                         class="p-3 cursor-pointer hover:bg-slate-50 transition flex flex-col gap-1.5"
                                         :class="purchaseInvoiceId == inv.id ? 'bg-emerald-50/60' : ''">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-mono font-bold text-xs text-slate-900" x-text="inv.invoice_number"></span>
                                                <span class="text-[11px] font-mono text-slate-500" x-text="inv.date"></span>
                                                <span class="rounded px-1.5 py-0.2 text-[10px] font-bold uppercase tracking-wider"
                                                      :class="inv.payment_class === 'Cash' ? 'bg-emerald-100 text-emerald-800' : 'bg-indigo-100 text-indigo-800'"
                                                      x-text="inv.payment_class"></span>
                                            </div>
                                            <span x-show="purchaseInvoiceId == inv.id" class="text-xs font-bold text-emerald-700">Selected</span>
                                        </div>

                                        <div class="flex items-center justify-between text-xs font-mono">
                                            <span class="text-slate-600">
                                                Bill: <strong class="text-slate-900">₹<span x-text="formatMoney(inv.net_amount)"></span></strong>
                                            </span>
                                            <span :class="inv.outstanding_amount > 0 ? 'text-rose-700 font-bold' : 'text-slate-400'">
                                                Out: ₹<span x-text="formatMoney(inv.outstanding_amount)"></span>
                                            </span>
                                        </div>
                                    </div>
                                </template>

                                {{-- Empty search result --}}
                                <div x-show="filteredInvoices().length === 0" class="p-4 text-center text-xs text-slate-400 italic">
                                    No purchase bills match "<span x-text="invoiceSearch"></span>"
                                </div>
                            </div>
                        </div>

                        {{-- Selected Invoice Summary Bar --}}
                        <template x-if="selectedInvoiceData">
                            <div class="mt-2.5 rounded-xl border border-indigo-100 bg-indigo-50/40 p-3.5 shadow-2xs space-y-2">
                                <div class="flex items-center justify-between flex-wrap gap-2">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="receipt" class="h-4 w-4 text-indigo-600"></i>
                                        <span class="font-mono font-black text-xs text-indigo-950" x-text="selectedInvoiceData.invoice_number"></span>
                                        <span class="text-slate-400">·</span>
                                        <span class="text-xs font-medium text-slate-600" x-text="selectedInvoiceData.date"></span>
                                        <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                              :class="selectedInvoiceData.payment_class === 'Cash' ? 'bg-emerald-100 text-emerald-800' : 'bg-indigo-100 text-indigo-800'"
                                              x-text="selectedInvoiceData.payment_class"></span>
                                    </div>
                                    <button type="button" @click="selectInvoice(null)" class="text-[11px] font-bold text-rose-600 hover:text-rose-800 hover:underline">
                                        Unlink / Standalone
                                    </button>
                                </div>

                                <div class="grid grid-cols-3 gap-2 pt-1 border-t border-indigo-100/60 text-xs">
                                    <div>
                                        <span class="block text-[10px] uppercase font-bold text-slate-500">Bill Net Amount</span>
                                        <span class="font-mono font-bold text-slate-900">₹<span x-text="formatMoney(selectedInvoiceData.net_amount)"></span></span>
                                    </div>
                                    <div>
                                        <span class="block text-[10px] uppercase font-bold text-slate-500">Paid / Credited</span>
                                        <span class="font-mono font-bold text-slate-700">₹<span x-text="formatMoney(selectedInvoiceData.settled_amount)"></span></span>
                                    </div>
                                    <div>
                                        <span class="block text-[10px] uppercase font-bold text-slate-500">Remaining Debt</span>
                                        <span class="font-mono font-black" :class="selectedInvoiceData.outstanding_amount > 0 ? 'text-rose-700' : 'text-slate-500'">
                                            ₹<span x-text="formatMoney(selectedInvoiceData.outstanding_amount)"></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Mode & Effect Matrix --}}
                    <div class="space-y-4 pt-2 border-t border-slate-100">
                        {{-- Money Effect Selection --}}
                        <div>
                            <label class="block text-xs font-black text-slate-700">Money / Financial Settlement Effect <span class="text-rose-500">*</span></label>
                            <div class="mt-2 grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                                @foreach($moneyEffects as $mEffect)
                                    <label class="relative flex cursor-pointer rounded-lg border p-3 shadow-xs focus:outline-none"
                                           :class="moneyEffect === '{{ $mEffect->value }}' ? 'border-emerald-600 bg-emerald-50/50 ring-2 ring-emerald-600' : 'border-slate-200 bg-white hover:bg-slate-50'">
                                        <input type="radio" name="money_effect" value="{{ $mEffect->value }}" x-model="moneyEffect" class="sr-only">
                                        <div class="flex flex-col">
                                            <span class="text-xs font-black text-slate-900">{{ $mEffect->label() }}</span>
                                            <span class="mt-1 text-[11px] text-slate-500">
                                                @if($mEffect->value === 'vendor_credit')
                                                    Reduces bill payable or creates vendor advance
                                                @elseif($mEffect->value === 'in')
                                                    Vendor refunded cash or bank transfer to company
                                                @else
                                                    Company pays settlement adjustment to vendor
                                                @endif
                                            </span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Inventory Action Selection --}}
                        <div>
                            <label class="block text-xs font-black text-slate-700">Inventory / Warehouse Stock Action <span class="text-rose-500">*</span></label>
                            <div class="mt-2 grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                                @foreach($inventoryActions as $iAction)
                                    <label class="relative flex cursor-pointer rounded-lg border p-3 shadow-xs focus:outline-none"
                                           :class="inventoryAction === '{{ $iAction->value }}' ? 'border-emerald-600 bg-emerald-50/50 ring-2 ring-emerald-600' : 'border-slate-200 bg-white hover:bg-slate-50'">
                                        <input type="radio" name="inventory_action" value="{{ $iAction->value }}" x-model="inventoryAction" class="sr-only">
                                        <div class="flex flex-col">
                                            <span class="text-xs font-black text-slate-900">{{ $iAction->label() }}</span>
                                            <span class="mt-0.5 text-[10px] text-slate-500">
                                                @if($iAction->value === 'out')
                                                    Deduct & return
                                                @elseif($iAction->value === 'in')
                                                    Add stock
                                                @elseif($iAction->value === 'damage')
                                                    Log wastage loss
                                                @else
                                                    Financial only
                                                @endif
                                            </span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Contextual Selectors based on Money & Inventory actions --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 pt-2">
                            {{-- Warehouse dropdown (required for out, in, damage) --}}
                            <div x-show="['out', 'in', 'damage'].includes(inventoryAction)" x-transition>
                                <label class="block text-xs font-black text-slate-700">Warehouse <span class="text-rose-500">*</span></label>
                                <select name="warehouse_id" x-model="warehouseId" :required="['out', 'in'].includes(inventoryAction)"
                                        class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-900 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">
                                    @foreach($warehouses as $wh)
                                        <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Company Account dropdown (required for money in / out) --}}
                            <div x-show="['in', 'out'].includes(moneyEffect)" x-transition>
                                <label class="block text-xs font-black text-slate-700">Company Bank / Cash Account <span class="text-rose-500">*</span></label>
                                <select name="company_account_id" x-model="companyAccountId" :required="['in', 'out'].includes(moneyEffect)"
                                        class="mt-1.5 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-900 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600">
                                    @foreach($companyAccounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->name }} ({{ ucfirst($acc->account_type) }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Damage specific options --}}
                        <div x-show="inventoryAction === 'damage'" x-transition class="rounded-lg border border-amber-200 bg-amber-50/60 p-4 space-y-3">
                            <div class="flex items-center gap-2">
                                <i data-lucide="alert-triangle" class="h-4 w-4 text-amber-600"></i>
                                <span class="text-xs font-black text-amber-900">Damage / Wastage Parameters</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-800">
                                    <input type="checkbox" name="deduct_stock_for_damage" value="1" x-model="deductStockForDamage"
                                           class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <span>Deduct physically damaged quantity from warehouse inventory?</span>
                                </label>
                            </div>
                            <p class="text-[11px] text-slate-600">
                                Uncheck this if the goods were already sorted/rejected at Goods Receiving and not currently in active inventory.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Card 2: Line Items --}}
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-200 p-4 bg-slate-50/50">
                        <div>
                            <h2 class="text-sm font-black uppercase tracking-wider text-slate-700">2. Credited Line Items</h2>
                            <p class="text-[11px] text-slate-500">Specify returned/credited items, product grade, quantity and unit rate.</p>
                        </div>
                        <button type="button" @click="addLineItem()"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-black text-slate-700 hover:bg-slate-50">
                            <i data-lucide="plus" class="h-3.5 w-3.5"></i> Add Line
                        </button>
                    </div>

                    {{-- Invoice Auto-Fill Banner --}}
                    <div x-show="isLoadingInvoiceItems" class="p-4 text-center text-xs text-slate-500 bg-slate-50">
                        <i data-lucide="loader" class="inline h-4 w-4 animate-spin text-emerald-600 mr-1.5"></i> Fetching returnable items from invoice...
                    </div>

                    {{-- Helpful message if no products linked to vendor --}}
                    @if($products->isEmpty())
                        <div class="m-4 rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-xs text-amber-900 flex items-start gap-3">
                            <i data-lucide="info" class="h-4 w-4 text-amber-600 shrink-0 mt-0.5"></i>
                            <div>
                                <div class="font-bold text-amber-950">No catalog products linked to this vendor</div>
                                <p class="mt-0.5 text-amber-800 font-medium">
                                    This vendor has no products linked in Vendor Settings and no past purchase history. You can link a Purchase Bill above to import items, or assign products to this vendor in Cashbook Settings.
                                </p>
                            </div>
                        </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[50rem] text-left text-xs">
                            <thead class="bg-slate-50 text-[10px] uppercase font-bold text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="p-3 w-10 text-center">#</th>
                                    <th class="p-3">Product</th>
                                    <th class="p-3 w-32 min-w-[110px]">Grade</th>
                                    <th class="p-3 w-32">Quantity</th>
                                    <th class="p-3 w-32">Rate (₹)</th>
                                    <th class="p-3 w-32 text-right">Line Total (₹)</th>
                                    <th class="p-3 w-12 text-center"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="(item, index) in items" :key="index">
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="p-3 text-center text-slate-400 font-mono" x-text="index + 1"></td>

                                        {{-- Product select / display --}}
                                        <td class="p-3">
                                            <template x-if="item.isFromInvoice">
                                                <div>
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="font-bold text-slate-900" x-text="item.product_name"></span>
                                                        <span class="rounded bg-indigo-50 text-indigo-700 px-1.5 py-0.2 text-[10px] font-bold">On Bill</span>
                                                    </div>
                                                    <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.product_id">
                                                    <input type="hidden" :name="'items[' + index + '][purchaser_cart_item_id]'" :value="item.purchaser_cart_item_id">
                                                    <div class="text-[10px] text-slate-400 mt-0.5" x-show="item.purchased_qty">
                                                        Purchased: <span x-text="item.purchased_qty"></span> <span x-text="item.unit"></span>
                                                        · Max returnable: <strong class="text-emerald-700" x-text="item.returnable_qty"></strong> <span x-text="item.unit"></span>
                                                    </div>
                                                </div>
                                            </template>
                                            <template x-if="!item.isFromInvoice">
                                                <div>
                                                    <select :name="'items[' + index + '][product_id]'" x-model.number="item.product_id" required
                                                            class="w-full rounded border border-slate-300 px-2 py-1.5 text-xs font-medium text-slate-900 focus:border-emerald-600">
                                                        <option value="">— Select Vendor Product —</option>
                                                        @foreach($products as $p)
                                                            <option value="{{ $p->id }}" data-unit="{{ $p->unit }}">{{ $p->name }} ({{ $p->unit ?? 'kg' }})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </template>
                                        </td>

                                        {{-- Grade select with min-w-[110px] so it is not clipped --}}
                                        <td class="p-3 w-32 min-w-[110px]">
                                            <select :name="'items[' + index + '][grade]'" x-model="item.grade" required
                                                    class="w-full min-w-[100px] rounded border border-slate-300 px-2 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-600">
                                                @foreach($grades as $gradeCase)
                                                    <option value="{{ $gradeCase->value }}">{{ $gradeCase->label() }}</option>
                                                @endforeach
                                            </select>
                                        </td>

                                        {{-- Quantity --}}
                                        <td class="p-3 w-32">
                                            <input type="number" step="0.001" min="0.001"
                                                   :name="'items[' + index + '][quantity]'"
                                                   x-model.number="item.quantity" required
                                                   class="w-full rounded border border-slate-300 px-2 py-1.5 text-xs font-mono font-bold text-slate-900 focus:border-emerald-600">
                                        </td>

                                        {{-- Unit Price --}}
                                        <td class="p-3 w-32">
                                            <input type="number" step="0.01" min="0"
                                                   :name="'items[' + index + '][unit_price]'"
                                                   x-model.number="item.unit_price" required
                                                   class="w-full rounded border border-slate-300 px-2 py-1.5 text-xs font-mono font-bold text-slate-900 focus:border-emerald-600">
                                        </td>

                                        {{-- Line Subtotal --}}
                                        <td class="p-3 w-32 text-right font-mono font-black text-slate-950">
                                            ₹<span x-text="formatMoney((parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0))"></span>
                                        </td>

                                        {{-- Remove Action --}}
                                        <td class="p-3 w-12 text-center">
                                            <button type="button" @click="removeItem(index)"
                                                    class="text-slate-400 hover:text-rose-600 p-1 rounded transition">
                                                <i data-lucide="trash-2" class="h-4 w-4"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-slate-50 font-black border-t border-slate-200">
                                <tr>
                                    <td colspan="5" class="p-3 text-right uppercase text-[11px] text-slate-500">Gross Credit Total:</td>
                                    <td class="p-3 text-right font-mono text-sm text-emerald-800">
                                        ₹<span x-text="formatMoney(totalAmount())"></span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Notes / Remarks --}}
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <label class="block text-xs font-black text-slate-700">Audit Notes / Reason Description (Optional)</label>
                    <textarea name="notes" x-model="notes" rows="2"
                              placeholder="Add any additional context, invoice references, or warehouse inspection remarks..."
                              class="mt-1.5 w-full rounded-lg border border-slate-300 p-2.5 text-xs text-slate-900 focus:border-emerald-600"></textarea>
                </div>
            </div>

            {{-- Right 1 col: Live Financial Breakdown & Settlement Projection --}}
            <div class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-4 sticky top-6">
                    <h2 class="text-sm font-black uppercase tracking-wider text-slate-700 border-b border-slate-100 pb-3">
                        Financial Impact Summary
                    </h2>

                    <div class="space-y-3">
                        {{-- Total Credit --}}
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-600">Total Credit Amount:</span>
                            <span class="font-mono text-base font-black text-emerald-700">
                                ₹<span x-text="formatMoney(totalAmount())"></span>
                            </span>
                        </div>

                        {{-- If linked to invoice: show allocation --}}
                        <div x-show="purchaseInvoiceId" class="space-y-2 pt-2 border-t border-slate-100 text-xs">
                            <div class="flex items-center justify-between text-slate-600">
                                <span>Applied to Selected Bill:</span>
                                <span class="font-mono font-bold text-slate-900">
                                    -₹<span x-text="formatMoney(appliedToBill())"></span>
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-indigo-700 font-bold" x-show="excessAdvance() > 0">
                                <span>Excess (Vendor Advance):</span>
                                <span class="font-mono font-black">
                                    +₹<span x-text="formatMoney(excessAdvance())"></span>
                                </span>
                            </div>
                        </div>

                        {{-- Money In / Out badge --}}
                        <div class="pt-3 border-t border-slate-100">
                            <span class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Settlement Routing</span>
                            <div class="mt-1.5 flex items-center gap-2">
                                <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-bold"
                                      :class="{
                                          'bg-indigo-50 text-indigo-800 border border-indigo-200': moneyEffect === 'vendor_credit',
                                          'bg-emerald-50 text-emerald-800 border border-emerald-200': moneyEffect === 'in',
                                          'bg-amber-50 text-amber-800 border border-amber-200': moneyEffect === 'out'
                                      }">
                                    <template x-if="moneyEffect === 'vendor_credit'">
                                        <span>Vendor Credit / Payable Offset</span>
                                    </template>
                                    <template x-if="moneyEffect === 'in'">
                                        <span>Company Cash/Bank Refund IN</span>
                                    </template>
                                    <template x-if="moneyEffect === 'out'">
                                        <span>Company Settlement OUT</span>
                                    </template>
                                </span>
                            </div>
                        </div>

                        {{-- Inventory badge --}}
                        <div class="pt-2">
                            <span class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Stock Routing</span>
                            <div class="mt-1.5 flex items-center gap-2">
                                <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-bold"
                                      :class="{
                                          'bg-amber-50 text-amber-800 border border-amber-200': inventoryAction === 'out',
                                          'bg-teal-50 text-teal-800 border border-teal-200': inventoryAction === 'in',
                                          'bg-rose-50 text-rose-800 border border-rose-200': inventoryAction === 'damage',
                                          'bg-slate-50 text-slate-700 border border-slate-200': inventoryAction === 'none'
                                      }">
                                    <template x-if="inventoryAction === 'out'">
                                        <span>Stock OUT (Physical Return)</span>
                                    </template>
                                    <template x-if="inventoryAction === 'in'">
                                        <span>Stock IN (Add to Inventory)</span>
                                    </template>
                                    <template x-if="inventoryAction === 'damage'">
                                        <span>Damage / Wastage Write-Off</span>
                                    </template>
                                    <template x-if="inventoryAction === 'none'">
                                        <span>No Inventory Movement</span>
                                    </template>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100">
                        <button type="submit" form="credit-note-form"
                                :disabled="isSubmitting || isCheckingStock || items.length === 0 || totalAmount() <= 0"
                                class="w-full rounded-lg bg-emerald-600 py-2.5 text-xs font-black text-white hover:bg-emerald-700 disabled:opacity-50 transition shadow-sm">
                            <span x-text="isCheckingStock ? 'Checking Stock...' : (isSubmitting ? 'Processing Transaction...' : 'Save & Post Credit Note')"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- Negative Stock Confirmation Modal --}}
    <div x-cloak x-show="showNegativeStockModal"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-xs"
         @keydown.escape.window="closeNegativeStockModal()">
        <div class="relative w-full max-w-2xl rounded-2xl border border-amber-300 bg-white shadow-2xl overflow-hidden"
             @click.outside="closeNegativeStockModal()">
            {{-- Modal Header --}}
            <div class="bg-amber-50 px-6 py-4 border-b border-amber-200">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                        <i data-lucide="alert-triangle" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-amber-950">Insufficient Warehouse Stock Warning</h3>
                        <p class="mt-0.5 text-xs text-amber-800">
                            The credit note you are attempting to create requires deducting more stock than is currently available in the selected warehouse.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Shortages Table --}}
            <div class="p-6 space-y-4">
                <p class="text-xs font-semibold text-slate-700">
                    The following product line items will push warehouse inventory into <span class="font-bold text-rose-600">negative stock</span>:
                </p>

                <div class="overflow-hidden rounded-xl border border-slate-200">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="px-3.5 py-2.5">Product</th>
                                <th class="px-3 py-2.5 text-center">Grade</th>
                                <th class="px-3 py-2.5 text-right">Available</th>
                                <th class="px-3 py-2.5 text-right">Requested</th>
                                <th class="px-3.5 py-2.5 text-right text-rose-600">Resulting Stock</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <template x-for="(shortage, idx) in shortages" :key="idx">
                                <tr class="hover:bg-slate-50/75">
                                    <td class="px-3.5 py-2.5 font-bold text-slate-900" x-text="shortage.product_name"></td>
                                    <td class="px-3 py-2.5 text-center">
                                        <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-black text-slate-700" x-text="'Grade ' + shortage.grade"></span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-medium text-slate-600" x-text="formatQty(shortage.available_qty)"></td>
                                    <td class="px-3 py-2.5 text-right font-medium text-slate-900" x-text="formatQty(shortage.requested_qty)"></td>
                                    <td class="px-3.5 py-2.5 text-right font-black text-rose-600" x-text="formatQty(shortage.resulting_stock)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="rounded-lg bg-amber-50/80 border border-amber-200/80 p-3 text-[11px] text-amber-900 flex items-start gap-2">
                    <i data-lucide="info" class="h-4 w-4 shrink-0 text-amber-700 mt-0.5"></i>
                    <span>
                        If you proceed, synthetic negative stock batches (<code>NEG-*</code>) will be recorded to maintain FIFO cost tracking and full auditability. Future goods receipts will automatically reconcile this negative inventory.
                    </span>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="flex items-center justify-end gap-3 bg-slate-50 px-6 py-3.5 border-t border-slate-200">
                <button type="button"
                        @click="closeNegativeStockModal()"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-black text-slate-700 hover:bg-slate-100 transition">
                    Cancel & Adjust Qty
                </button>
                <button type="button"
                        @click="confirmNegativeStock()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-4 py-2 text-xs font-black text-white hover:bg-amber-700 transition shadow-xs">
                    <i data-lucide="alert-triangle" class="h-3.5 w-3.5"></i>
                    <span>Proceed with Negative Stock</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const purchaseCreditNoteInvoices = {{ Js::from($invoices) }};
    const purchaseCreditNoteShortages = {{ Js::from(session('negative_stock_shortages') ?? []) }};
    const purchaseCreditNoteHasShortages = {{ session()->has('negative_stock_shortages') ? 'true' : 'false' }};

    function purchaseCreditNoteForm() {
        return {
            isSubmitting: false,
            isLoadingInvoiceItems: false,
            isCheckingStock: false,
            allowNegativeStock: false,
            showNegativeStockModal: purchaseCreditNoteHasShortages,
            shortages: purchaseCreditNoteShortages,
            creditDate: new Date().toISOString().slice(0, 10),
            reason: 'return',
            moneyEffect: 'vendor_credit',
            inventoryAction: 'out',
            purchaseInvoiceId: '',
            warehouseId: '{{ $warehouses->first()?->id ?? "" }}',
            companyAccountId: '{{ $companyAccounts->first()?->id ?? "" }}',
            deductStockForDamage: true,
            notes: '',
            invoicesList: purchaseCreditNoteInvoices || [],
        invoiceSearch: '',
        isInvoiceDropdownOpen: false,
        selectedInvoiceData: null,
        selectedInvoice: null,
        items: [
            {
                isFromInvoice: false,
                product_id: '',
                product_name: '',
                unit: 'kg',
                grade: 'A',
                quantity: 1,
                unit_price: 0,
                purchaser_cart_item_id: null,
                returnable_qty: null
            }
        ],

        init() {
            if (this.showNegativeStockModal) {
                this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
            }
        },

        formatMoney(val) {
            const num = parseFloat(val) || 0;
            return num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        formatQty(val) {
            const num = parseFloat(val) || 0;
            return num.toLocaleString('en-IN', { minimumFractionDigits: 1, maximumFractionDigits: 3 });
        },

        closeNegativeStockModal() {
            this.showNegativeStockModal = false;
            this.allowNegativeStock = false;
            this.isSubmitting = false;
            this.isCheckingStock = false;
        },

        confirmNegativeStock() {
            this.allowNegativeStock = true;
            this.showNegativeStockModal = false;
            this.isSubmitting = true;
            this.$nextTick(() => {
                document.getElementById('credit-note-form').submit();
            });
        },

        async handleSubmit(event) {
            if (this.isSubmitting || this.isCheckingStock) {
                return;
            }

            if (this.allowNegativeStock) {
                this.isSubmitting = true;
                event.target.submit();
                return;
            }

            const willDeduct = (this.inventoryAction === 'out') || (this.inventoryAction === 'damage' && this.deductStockForDamage);
            if (!willDeduct || !this.warehouseId) {
                this.isSubmitting = true;
                event.target.submit();
                return;
            }

            this.isCheckingStock = true;

            try {
                const response = await fetch('{{ route("admin.cashbook.finance.purchase.vendors.credit-notes.check-stock", $supplier) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        warehouse_id: this.warehouseId,
                        inventory_action: this.inventoryAction,
                        deduct_stock_for_damage: this.deductStockForDamage,
                        items: this.items.map(it => ({
                            product_id: it.product_id,
                            quantity: it.quantity,
                            grade: it.grade,
                            unit_price: it.unit_price,
                            inventory_action: it.inventory_action || this.inventoryAction
                        }))
                    })
                });

                const data = await response.json();
                this.isCheckingStock = false;

                if (data.has_shortage) {
                    this.shortages = data.shortages;
                    this.showNegativeStockModal = true;
                    this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
                    return;
                }

                this.isSubmitting = true;
                event.target.submit();
            } catch (err) {
                console.error('Stock pre-check error, falling back to form submit:', err);
                this.isCheckingStock = false;
                this.isSubmitting = true;
                event.target.submit();
            }
        },

        filteredInvoices() {
            if (!this.invoiceSearch || !this.invoiceSearch.trim()) {
                return this.invoicesList;
            }
            const q = this.invoiceSearch.toLowerCase().replace(/,/g, '').trim();
            return this.invoicesList.filter(inv => {
                const invNum = (inv.invoice_number || '').toLowerCase();
                const net = (inv.net_amount !== undefined ? inv.net_amount.toString().replace(/,/g, '') : '');
                const out = (inv.outstanding_amount !== undefined ? inv.outstanding_amount.toString().replace(/,/g, '') : '');
                const dt = (inv.date || '').toLowerCase();
                const rawDt = (inv.raw_date || '').toLowerCase();
                const pClass = (inv.payment_class || '').toLowerCase();

                return invNum.includes(q)
                    || net.includes(q)
                    || out.includes(q)
                    || dt.includes(q)
                    || rawDt.includes(q)
                    || pClass.includes(q);
            });
        },

        selectInvoice(inv) {
            if (inv) {
                this.purchaseInvoiceId = inv.id;
                this.selectedInvoiceData = inv;
                this.isInvoiceDropdownOpen = false;
                this.invoiceSearch = '';
                this.fetchInvoiceItems(inv.public_uuid);
            } else {
                this.purchaseInvoiceId = '';
                this.selectedInvoiceData = null;
                this.selectedInvoice = null;
                this.isInvoiceDropdownOpen = false;
                this.invoiceSearch = '';
                this.items = [
                    {
                        isFromInvoice: false,
                        product_id: '',
                        product_name: '',
                        unit: 'kg',
                        grade: 'A',
                        quantity: 1,
                        unit_price: 0,
                        purchaser_cart_item_id: null,
                        returnable_qty: null
                    }
                ];
            }
            this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
        },

        addLineItem() {
            this.items.push({
                isFromInvoice: false,
                product_id: '',
                product_name: '',
                unit: 'kg',
                grade: 'A',
                quantity: 1,
                unit_price: 0,
                purchaser_cart_item_id: null,
                returnable_qty: null
            });
            this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
        },

        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            } else {
                alert('At least one line item is required.');
            }
        },

        totalAmount() {
            return this.items.reduce((sum, it) => sum + ((parseFloat(it.quantity) || 0) * (parseFloat(it.unit_price) || 0)), 0);
        },

        appliedToBill() {
            if (!this.selectedInvoiceData) return 0;
            const outstanding = this.selectedInvoiceData.outstanding_amount !== undefined
                ? this.selectedInvoiceData.outstanding_amount
                : (this.selectedInvoice?.remaining_balance || 0);
            return Math.min(this.totalAmount(), Math.max(0, outstanding));
        },

        excessAdvance() {
            return Math.max(0, this.totalAmount() - this.appliedToBill());
        },

        async fetchInvoiceItems(invoiceUuid) {
            this.isLoadingInvoiceItems = true;
            try {
                const url = `{{ url('admin/cashbook/finance/purchase/vendors/' . $supplier->public_uuid . '/invoices') }}/${invoiceUuid}/returnable-items`;
                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    this.selectedInvoice = data.invoice;
                    if (data.items && data.items.length > 0) {
                        this.items = data.items.map(it => ({
                            isFromInvoice: true,
                            product_id: it.product_id,
                            product_name: it.product_name,
                            unit: it.unit || 'kg',
                            grade: it.grade || 'A',
                            quantity: it.returnable_qty > 0 ? it.returnable_qty : it.purchased_qty,
                            purchased_qty: it.purchased_qty,
                            returnable_qty: it.returnable_qty,
                            unit_price: it.unit_price || 0,
                            purchaser_cart_item_id: it.purchaser_cart_item_id
                        }));
                    }
                }
            } catch (err) {
                console.error('Failed to load invoice items:', err);
            } finally {
                this.isLoadingInvoiceItems = false;
                this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
            }
        }
    };

    window.purchaseCreditNoteForm = purchaseCreditNoteForm;

    if (window.Alpine) {
        window.Alpine.data('purchaseCreditNoteForm', purchaseCreditNoteForm);
    } else {
        document.addEventListener('alpine:init', function() {
            window.Alpine.data('purchaseCreditNoteForm', purchaseCreditNoteForm);
        });
    }
})();
</script>
@endsection
