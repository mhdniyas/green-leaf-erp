@extends('admin.cashbook.layouts.app')

@section('title', 'New Purchase Credit Note — ' . $supplier->name)

@section('header_title')
    <i data-lucide="file-plus-2" class="h-5 w-5 text-emerald-600"></i>
    <span>New Purchase Credit Note</span>
@endsection

@section('header_subtitle')
    Internal ERP credit note for vendor balance adjustments, cash/bank refunds, or warehouse stock movements.
@endsection

@section('content')
@php
    $initialCreditDate = old('credit_date', $defaultCreditDate);
    $initialReason = old('reason', 'return');
    $initialMoneyEffect = old('money_effect', 'vendor_credit');
    $initialInventoryAction = old('inventory_action', 'none');
    $initialInvoiceId = old('purchase_invoice_id', '');
    $initialWarehouseId = old('warehouse_id', (string) ($warehouses->first()?->id ?? ''));
    $initialCompanyAccountId = old('company_account_id', (string) ($companyAccounts->first()?->id ?? ''));
    $initialDeductStock = old('deduct_stock_for_damage') !== null
        ? (bool) old('deduct_stock_for_damage')
        : true;
    $initialNotes = old('notes', '');
    $initialSubmissionToken = old('submission_token', $submissionToken);

    $productById = collect($productCatalog)->keyBy('id');
    $oldItems = old('items');
    if (is_array($oldItems) && count($oldItems) > 0) {
        $initialItems = collect($oldItems)->values()->map(function (array $row) use ($productById): array {
            $pid = (int) ($row['product_id'] ?? 0);
            $prod = $productById->get($pid);
            $baseUnit = $prod['base_unit'] ?? 'kg';

            return [
                'isFromInvoice' => ! empty($row['purchaser_cart_item_id']),
                'product_id' => $pid > 0 ? $pid : '',
                'product_name' => $prod['name'] ?? '',
                'productSearch' => $prod['name'] ?? '',
                'productDropdownOpen' => false,
                'grade' => (string) ($row['grade'] ?? 'A'),
                'unit' => (string) ($row['unit'] ?? $baseUnit),
                'quantity' => (float) ($row['quantity'] ?? 1),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'purchaser_cart_item_id' => ! empty($row['purchaser_cart_item_id']) ? (int) $row['purchaser_cart_item_id'] : null,
                'purchased_qty' => null,
                'returnable_qty' => null,
            ];
        })->all();
    } else {
        $initialItems = [[
            'isFromInvoice' => false,
            'product_id' => '',
            'product_name' => '',
            'productSearch' => '',
            'productDropdownOpen' => false,
            'grade' => 'A',
            'unit' => 'kg',
            'quantity' => 1,
            'unit_price' => 0,
            'purchaser_cart_item_id' => null,
            'purchased_qty' => null,
            'returnable_qty' => null,
        ]];
    }
@endphp

<div class="mx-auto max-w-[96rem] space-y-5 pb-12" x-data="purchaseCreditNoteForm">
    {{-- 1. Professional Document Header --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.cashbook.purchase-credit-notes.index', ['supplier_id' => $supplier->id]) }}"
                       class="inline-flex items-center gap-1 text-xs font-bold text-slate-500 hover:text-emerald-700">
                        <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
                        <span>Back to Credit Notes</span>
                    </a>
                    <span class="text-slate-300">·</span>
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                        Internal ERP Credit Note
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <h1 class="text-xl font-black tracking-tight text-slate-950">Purchase Credit Note</h1>
                    <span class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-800">
                        <i data-lucide="store" class="h-3.5 w-3.5 text-emerald-600"></i>
                        <span>{{ $vendorInfo['name'] }}</span>
                    </span>
                    @if (! empty($vendorInfo['primary_phone']))
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-slate-600">
                            <i data-lucide="phone" class="h-3.5 w-3.5 text-slate-400"></i>
                            <span>{{ $vendorInfo['primary_phone'] }}</span>
                        </span>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $supplier->public_uuid]) }}"
                   class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50">
                    <span>More Details</span>
                    <i data-lucide="external-link" class="h-3.5 w-3.5"></i>
                </a>
                <a href="{{ route('admin.cashbook.purchase-credit-notes.index', ['supplier_id' => $supplier->id]) }}"
                   class="inline-flex h-9 items-center rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 hover:bg-slate-50">
                    Cancel
                </a>
            </div>
        </div>

        {{-- Document Metadata Strip --}}
        <div class="grid grid-cols-1 gap-3 border-t border-slate-100 pt-3 text-xs sm:grid-cols-3">
            <div class="flex items-center gap-2 text-slate-600">
                <i data-lucide="hash" class="h-3.5 w-3.5 text-slate-400"></i>
                <span class="font-semibold">Credit Note #:</span>
                <span class="font-mono font-bold text-slate-800">Auto-generated on posting</span>
            </div>
            <div class="flex items-center gap-2 text-slate-600">
                <i data-lucide="user-check" class="h-3.5 w-3.5 text-slate-400"></i>
                <span class="font-semibold">Created By:</span>
                <span class="font-bold text-slate-800">{{ auth()->user()?->name ?? 'System' }}</span>
            </div>
            <div class="flex items-center gap-2 text-slate-600">
                <i data-lucide="calendar" class="h-3.5 w-3.5 text-slate-400"></i>
                <span class="font-semibold">Credit Date:</span>
                <span class="font-mono font-bold text-slate-800" x-text="creditDate"></span>
            </div>
        </div>

        {{-- 2. Compact Vendor Financial Summary --}}
        <div class="grid grid-cols-2 gap-3 border-t border-slate-100 pt-3 lg:grid-cols-4">
            <div class="rounded-xl border border-amber-200/80 bg-amber-50/50 px-3.5 py-2.5">
                <div class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Unpaid Bills ({{ number_format($vendorSummary['pending_bills_count']) }})</div>
                <div class="mt-0.5 text-sm font-black text-amber-900">₹{{ number_format($vendorSummary['outstanding_balance'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/50 px-3.5 py-2.5">
                <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Available Purchase Credit</div>
                <div class="mt-0.5 text-sm font-black text-emerald-900">₹{{ number_format($vendorSummary['available_purchase_credits'] ?? $vendorSummary['available_credit_note_balance'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-blue-200/80 bg-blue-50/50 px-3.5 py-2.5">
                <div class="text-[10px] font-bold uppercase tracking-wider text-blue-700">Actual Cash Advances</div>
                <div class="mt-0.5 text-sm font-black text-blue-900">₹{{ number_format($vendorSummary['actual_cash_vendor_advances'] ?? $vendorSummary['total_open_vendor_advances'], 2) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 px-3.5 py-2.5">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Net Payable</div>
                <div class="mt-0.5 text-sm font-black {{ ($vendorSummary['net_vendor_payable'] ?? 0) < 0 ? 'text-emerald-700' : 'text-slate-900' }}">
                    ₹{{ number_format($vendorSummary['net_vendor_payable'] ?? $vendorSummary['outstanding_balance'], 2) }}
                </div>
            </div>
        </div>
    </div>

    {{-- Error Banner --}}
    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-semibold text-rose-800">
            <div class="flex items-center gap-2 font-black text-rose-900">
                <i data-lucide="alert-circle" class="h-4 w-4 text-rose-600"></i>
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
          @submit.prevent="openReviewOrCheckStock()">
        @csrf
        <input type="hidden" name="allow_negative_stock" :value="allowNegativeStock ? 1 : 0">
        <input type="hidden" name="submission_token" value="{{ $initialSubmissionToken }}">

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Left 2 cols --}}
            <div class="space-y-6 lg:col-span-2">
                {{-- Card 1: Document Details & Options --}}
                <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs space-y-5">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h2 class="text-xs font-black uppercase tracking-wider text-slate-600">1. Credit Note Details &amp; Routing</h2>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700">Credit Note Date <span class="text-rose-500">*</span></label>
                            <input type="date" name="credit_date" x-model="creditDate" required
                                   class="mt-1.5 h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700">Reason <span class="text-rose-500">*</span></label>
                            <select name="reason" x-model="reason" required
                                    class="mt-1.5 h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                                @foreach($reasons as $reasonCase)
                                    <option value="{{ $reasonCase->value }}">{{ $reasonCase->label() }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[11px] font-medium text-slate-500" x-text="reasonSuggestionHint()"></p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700">Supplier Credit Note Ref (Optional)</label>
                            <input type="text" name="vendor_credit_note_ref" x-model="vendorCreditNoteRef" maxlength="100"
                                   value="{{ old('vendor_credit_note_ref', '') }}"
                                   placeholder="e.g. SUP-CN-2026-041"
                                   class="mt-1.5 h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                        </div>
                    </div>

                    {{-- Optional Linked Purchase Invoice --}}
                    <div class="relative" @click.outside="isInvoiceDropdownOpen = false">
                        <div class="flex flex-wrap items-center justify-between gap-1">
                            <div class="flex items-center gap-2">
                                <label class="block text-xs font-bold text-slate-700">Purchase Invoice Reference (Optional)</label>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600">Reference Only</span>
                            </div>
                            <span class="text-[11px] text-slate-400">
                                {{ $isV2Enabled ? 'For record reference only — bill balance stays unchanged until settlement' : 'Link a bill to auto-load items & balance' }}
                            </span>
                        </div>

                        <input type="hidden" name="purchase_invoice_id" :value="purchaseInvoiceId">

                        <div class="relative mt-1.5">
                            <i data-lucide="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400"></i>
                            <input type="text"
                                   x-model="invoiceSearch"
                                   @focus="isInvoiceDropdownOpen = true"
                                   @input="isInvoiceDropdownOpen = true"
                                   @keydown.escape="isInvoiceDropdownOpen = false"
                                   :placeholder="selectedInvoiceData ? (selectedInvoiceData.invoice_number + ' (₹' + formatMoney(selectedInvoiceData.net_amount) + ')') : 'Search bill by invoice #, date, or amount...'"
                                   class="h-9 w-full rounded-xl border border-slate-200 bg-white pl-9 pr-16 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">

                            <div class="absolute right-2 top-1.5 flex items-center gap-1">
                                <template x-if="purchaseInvoiceId">
                                    <button type="button" @click="selectInvoice(null)" title="Unlink bill"
                                            class="rounded-lg p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600">
                                        <i data-lucide="x" class="h-3.5 w-3.5"></i>
                                    </button>
                                </template>
                                <button type="button" @click="isInvoiceDropdownOpen = !isInvoiceDropdownOpen"
                                        class="rounded-lg p-1 text-slate-400 hover:text-slate-700">
                                    <i data-lucide="chevron-down" class="h-3.5 w-3.5 transition-transform" :class="isInvoiceDropdownOpen ? 'rotate-180' : ''"></i>
                                </button>
                            </div>

                            <div x-show="isInvoiceDropdownOpen" x-cloak
                                 class="absolute left-0 right-0 z-30 mt-1 max-h-64 overflow-y-auto divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white shadow-xl">
                                <div @click="selectInvoice(null)"
                                     class="flex cursor-pointer items-center justify-between p-3 hover:bg-slate-50"
                                     :class="!purchaseInvoiceId ? 'bg-emerald-50/60' : ''">
                                    <div>
                                        <div class="text-xs font-bold text-slate-800">Standalone Credit Note (No Bill Linked)</div>
                                        <div class="text-[11px] text-slate-400">Vendor Credit will remain as Open Vendor Advance.</div>
                                    </div>
                                    <span x-show="!purchaseInvoiceId" class="text-[11px] font-bold text-emerald-700">Selected</span>
                                </div>

                                <template x-for="inv in filteredInvoices()" :key="inv.id">
                                    <div @click="selectInvoice(inv)"
                                         class="cursor-pointer p-3 hover:bg-slate-50"
                                         :class="String(purchaseInvoiceId) === String(inv.id) ? 'bg-emerald-50/60' : ''">
                                        <div class="flex items-center justify-between text-xs">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-bold text-slate-900" x-text="inv.invoice_number"></span>
                                                <span class="text-[11px] text-slate-500" x-text="inv.date"></span>
                                            </div>
                                            <span class="font-mono font-bold" :class="inv.outstanding_amount > 0 ? 'text-rose-700' : 'text-slate-400'">
                                                Out: ₹<span x-text="formatMoney(inv.outstanding_amount)"></span>
                                            </span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <template x-if="selectedInvoiceData">
                            <div class="mt-2.5 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-indigo-100 bg-indigo-50/50 px-3.5 py-2.5 text-xs">
                                <div class="flex flex-wrap items-center gap-2">
                                    <i data-lucide="receipt" class="h-4 w-4 text-indigo-600"></i>
                                    <span class="font-mono font-bold text-indigo-950" x-text="selectedInvoiceData.invoice_number"></span>
                                    <span class="text-slate-500" x-text="selectedInvoiceData.date"></span>
                                    <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-indigo-700">Reference Only</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-3 font-mono">
                                    <span class="text-slate-600">Original: <strong class="text-slate-900">₹<span x-text="formatMoney(selectedInvoiceData.net_amount)"></span></strong></span>
                                    <span class="text-slate-600">Current Outstanding (Info): <strong class="text-rose-700">₹<span x-text="formatMoney(selectedInvoiceData.outstanding_amount)"></span></strong></span>
                                    <button type="button" @click="importInvoiceItems()"
                                            class="inline-flex h-7 items-center gap-1 rounded-lg border border-indigo-200 bg-white px-2.5 font-sans text-[11px] font-bold text-indigo-700 hover:bg-indigo-50">
                                        <i data-lucide="download" class="h-3 w-3"></i>
                                        <span>Import Invoice Products</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Financial Effect --}}
                    <div class="space-y-2 border-t border-slate-100 pt-4">
                        <label class="block text-xs font-bold text-slate-700">Financial Effect <span class="text-rose-500">*</span></label>
                        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                            @foreach($moneyEffects as $mEffect)
                                <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border p-3 transition"
                                       :class="moneyEffect === '{{ $mEffect->value }}' ? 'border-emerald-600 bg-emerald-50/50 ring-1 ring-emerald-600' : 'border-slate-200 bg-white hover:bg-slate-50'">
                                    <input type="radio" name="money_effect" value="{{ $mEffect->value }}" x-model="moneyEffect" class="mt-0.5 text-emerald-600 focus:ring-emerald-500">
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">
                                            @if($mEffect->value === 'vendor_credit')
                                                Add to Vendor Credit Balance
                                            @elseif($mEffect->value === 'in')
                                                Receive Money from Vendor
                                            @else
                                                Pay Money to Vendor
                                            @endif
                                        </div>
                                        <div class="mt-0.5 text-[11px] text-slate-500">
                                            @if($mEffect->value === 'vendor_credit')
                                                This amount will be available for your next vendor settlement.
                                            @elseif($mEffect->value === 'in')
                                                Receive cash or bank refund from vendor
                                            @else
                                                Pay cash or bank settlement to vendor
                                            @endif
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Stock Adjustment --}}
                    <div class="space-y-2 border-t border-slate-100 pt-4">
                        <label class="block text-xs font-bold text-slate-700">Stock Adjustment <span class="text-rose-500">*</span></label>
                        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach($inventoryActions as $iAction)
                                <label class="flex cursor-pointer items-start gap-2 rounded-xl border p-3 transition"
                                       :class="inventoryAction === '{{ $iAction->value }}' ? 'border-emerald-600 bg-emerald-50/50 ring-1 ring-emerald-600' : 'border-slate-200 bg-white hover:bg-slate-50'">
                                    <input type="radio" name="inventory_action" value="{{ $iAction->value }}" x-model="inventoryAction" @change="refreshRowStockLevels()" class="mt-0.5 text-emerald-600 focus:ring-emerald-500">
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">
                                            @if($iAction->value === 'none')
                                                No Stock Movement
                                            @elseif($iAction->value === 'out')
                                                Return Stock to Vendor
                                            @elseif($iAction->value === 'in')
                                                Receive Replacement Stock
                                            @else
                                                Damage / Wastage
                                            @endif
                                        </div>
                                        <div class="mt-0.5 text-[11px] text-slate-500">
                                            @if($iAction->value === 'none')
                                                Financial adjustment only
                                            @elseif($iAction->value === 'out')
                                                Deduct from warehouse (FIFO cost)
                                            @elseif($iAction->value === 'in')
                                                Add replacement batch to warehouse
                                            @else
                                                Record damaged or spoiled stock
                                            @endif
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Conditional Account & Warehouse --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                         x-show="['in', 'out'].includes(moneyEffect) || ['out', 'in', 'damage'].includes(inventoryAction)"
                         x-cloak>
                        <div x-show="['in', 'out'].includes(moneyEffect)" x-cloak>
                            <label class="block text-xs font-bold text-slate-700">Cash / Bank Account <span class="text-rose-500">*</span></label>
                            <select name="company_account_id" x-model="companyAccountId" :required="['in', 'out'].includes(moneyEffect)"
                                    class="mt-1.5 h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                                @foreach($companyAccounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->name }} ({{ ucfirst($acc->account_type) }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="['out', 'in', 'damage'].includes(inventoryAction)" x-cloak>
                            <label class="block text-xs font-bold text-slate-700">Warehouse <span class="text-rose-500">*</span></label>
                            <select name="warehouse_id" x-model="warehouseId" @change="refreshRowStockLevels()" :required="['out', 'in'].includes(inventoryAction)"
                                    class="mt-1.5 h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Damage Toggle --}}
                    <div x-show="inventoryAction === 'damage'" x-cloak class="rounded-xl border border-amber-200 bg-amber-50/70 p-3.5">
                        <label class="flex cursor-pointer items-center gap-2 text-xs font-bold text-amber-950">
                            <input type="checkbox" name="deduct_stock_for_damage" value="1" x-model="deductStockForDamage" @change="refreshRowStockLevels()"
                                   class="h-4 w-4 rounded border-amber-300 text-emerald-600 focus:ring-emerald-500">
                            <span>Deduct physically damaged quantity from warehouse stock</span>
                        </label>
                        <p class="mt-1 pl-6 text-[11px] text-amber-800">
                            Uncheck if damaged items were already rejected at receiving and never entered warehouse stock.
                        </p>
                    </div>
                </div>

                {{-- Card 2: Product Entry --}}
                <div class="rounded-2xl border border-slate-200/80 bg-white shadow-2xs">
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-t-2xl border-b border-slate-200 bg-slate-50/70 px-5 py-3.5">
                        <div>
                            <h2 class="text-xs font-black uppercase tracking-wider text-slate-700">2. Products &amp; Credit Amounts</h2>
                            <p class="text-[11px] text-slate-500">Vendor credit rate determines the credit note amount. FIFO warehouse cost is calculated automatically on posting for Stock OUT / Damage.</p>
                        </div>
                        <button type="button" @click="addLineItem()"
                                class="inline-flex h-8 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50">
                            <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                            <span>Add Product</span>
                        </button>
                    </div>

                    <div x-show="isLoadingInvoiceItems" x-cloak class="bg-slate-50 px-5 py-3 text-xs font-semibold text-slate-500">
                        <i data-lucide="loader-2" class="mr-1.5 inline h-4 w-4 animate-spin text-emerald-600"></i>
                        Loading returnable items from selected invoice...
                    </div>

                    <div class="divide-y divide-slate-100 p-4 space-y-4">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="pt-4 first:pt-0 relative" :class="item.productDropdownOpen ? 'z-40' : 'z-0'">
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
                                    {{-- Searchable Product --}}
                                    <div class="relative md:col-span-4" :class="item.productDropdownOpen ? 'z-40' : 'z-10'" @click.outside="item.productDropdownOpen = false">
                                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                            Product <span class="text-rose-500">*</span>
                                        </label>

                                        <template x-if="item.isFromInvoice">
                                            <div class="rounded-xl border border-indigo-200 bg-indigo-50/40 px-3 py-2">
                                                <div class="flex items-center justify-between gap-1">
                                                    <span class="text-xs font-bold text-slate-900" x-text="item.product_name"></span>
                                                    <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700">On Bill</span>
                                                </div>
                                                <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.product_id">
                                                <input type="hidden" :name="'items[' + index + '][purchaser_cart_item_id]'" :value="item.purchaser_cart_item_id">
                                            </div>
                                        </template>

                                        <template x-if="!item.isFromInvoice">
                                            <div>
                                                <input type="hidden" :name="'items[' + index + '][product_id]'" :value="item.product_id">
                                                <input type="text"
                                                       x-model="item.productSearch"
                                                       @focus="openProductDropdown(item)"
                                                       @input="openProductDropdown(item)"
                                                       @keydown.escape="item.productDropdownOpen = false"
                                                       placeholder="Search vendor product..."
                                                       autocomplete="off"
                                                       class="h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">

                                                <div x-show="item.productDropdownOpen" x-cloak
                                                     class="absolute left-0 right-0 z-50 mt-1 max-h-52 overflow-y-auto divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white shadow-xl">
                                                    <template x-for="prod in filteredProducts(item.productSearch)" :key="prod.id">
                                                        <button type="button"
                                                                @click="selectProduct(item, prod)"
                                                                class="flex w-full items-center justify-between px-3 py-2 text-left text-xs hover:bg-emerald-50/70">
                                                            <span class="font-bold text-slate-900" x-text="prod.name"></span>
                                                            <span class="text-[11px] text-slate-500" x-text="prod.base_unit"></span>
                                                        </button>
                                                    </template>
                                                    <div x-show="filteredProducts(item.productSearch).length === 0" class="px-3 py-2 text-xs text-slate-400">
                                                        No matching vendor products
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    {{-- Grade --}}
                                    <div class="md:col-span-2">
                                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Grade</label>
                                        <select :name="'items[' + index + '][grade]'" x-model="item.grade" @change="refreshRowStockLevels()" required
                                                class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:outline-none">
                                            @foreach($grades as $gradeCase)
                                                <option value="{{ $gradeCase->value }}">{{ $gradeCase->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- Unit --}}
                                    <div class="md:col-span-2">
                                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Unit</label>
                                        <select :name="'items[' + index + '][unit]'" x-model="item.unit" required
                                                class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                                            <template x-for="u in unitsForProduct(item.product_id)" :key="u.unit">
                                                <option :value="u.unit" x-text="u.label" :selected="u.unit === item.unit"></option>
                                            </template>
                                        </select>
                                    </div>

                                    {{-- Quantity --}}
                                    <div class="md:col-span-1">
                                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Qty</label>
                                        <input type="number" step="0.001" min="0.001"
                                               :name="'items[' + index + '][quantity]'"
                                               x-model.number="item.quantity" required
                                               class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-mono font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                                    </div>

                                    {{-- Vendor Credit Rate --}}
                                    <div class="md:col-span-2">
                                        <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Rate (₹)</label>
                                        <input type="number" step="0.01" min="0"
                                               :name="'items[' + index + '][unit_price]'"
                                               x-model.number="item.unit_price" required
                                               class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-mono font-bold text-slate-900 focus:border-emerald-500 focus:outline-none">
                                    </div>

                                    {{-- Remove --}}
                                    <div class="flex items-center justify-between md:col-span-1 md:justify-end">
                                        <span class="font-mono text-xs font-black text-slate-900 md:hidden">
                                            ₹<span x-text="formatMoney((parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0))"></span>
                                        </span>
                                        <button type="button" @click="removeItem(index)" title="Remove row"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-400 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600">
                                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                                        </button>
                                    </div>
                                </div>

                                {{-- Row Footer: Calculated Amount & Available Stock --}}
                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[11px]">
                                    <div class="flex items-center gap-3 text-slate-500">
                                        <span x-show="shouldShowAvailableStock() && item.product_id" x-cloak class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-0.5 font-semibold text-slate-700">
                                            <i data-lucide="package" class="h-3 w-3 text-slate-500"></i>
                                            <span>Available Stock: <strong x-text="availableStockLabel(item)"></strong></span>
                                        </span>
                                        <span x-show="item.returnable_qty !== null && item.returnable_qty !== undefined" x-cloak class="text-emerald-700 font-semibold">
                                            Max returnable on bill: <span x-text="item.returnable_qty"></span>
                                        </span>
                                    </div>
                                    <div class="hidden font-mono text-xs font-black text-slate-900 md:block">
                                        Line Total: ₹<span x-text="formatMoney((parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0))"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="flex items-center justify-between rounded-b-2xl border-t border-slate-200 bg-slate-50/80 px-5 py-3 text-xs font-black">
                        <span class="uppercase tracking-wider text-slate-500">Estimated Credit Total</span>
                        <span class="font-mono text-base text-emerald-800">₹<span x-text="formatMoney(totalAmount())"></span></span>
                    </div>
                </div>

                {{-- Remarks --}}
                <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs">
                    <label class="block text-xs font-bold text-slate-700">Remarks / Notes (Optional)</label>
                    <textarea name="notes" x-model="notes" rows="2"
                              placeholder="Optional remarks, supplier credit note reference, or inspection details..."
                              class="mt-1.5 w-full rounded-xl border border-slate-200 p-3 text-xs text-slate-900 focus:border-emerald-500 focus:outline-none"></textarea>
                </div>
            </div>

            {{-- Right 1 col: Sticky Summary & Primary Action --}}
            <div class="space-y-4">
                <div class="sticky top-6 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs space-y-4">
                    <h2 class="border-b border-slate-100 pb-3 text-xs font-black uppercase tracking-wider text-slate-700">
                        Credit Note Summary
                    </h2>

                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Total Credit Amount</span>
                            <span class="font-mono text-base font-black text-emerald-700">₹<span x-text="formatMoney(totalAmount())"></span></span>
                        </div>

                        <div x-show="moneyEffect === 'vendor_credit'" class="space-y-1.5 border-t border-slate-100 pt-2">
                            @if($isV2Enabled)
                                <div class="flex items-center justify-between text-emerald-700 font-semibold">
                                    <span>New Available Vendor Credit</span>
                                    <span class="font-mono font-bold">₹<span x-text="formatMoney(totalAmount())"></span></span>
                                </div>
                                <div x-show="selectedInvoiceData" class="flex items-center justify-between text-slate-600">
                                    <span>Invoice Balance Unchanged</span>
                                    <span class="font-mono font-bold text-slate-700" x-text="selectedInvoiceData?.invoice_number + ' (Ref Only)'"></span>
                                </div>
                            @else
                                <div class="flex items-center justify-between text-slate-600">
                                    <span>Invoice Adjustment</span>
                                    <span class="font-mono font-bold text-slate-900">₹<span x-text="formatMoney(appliedToBill())"></span></span>
                                </div>
                                <div class="flex items-center justify-between text-indigo-700 font-semibold">
                                    <span>Excess Vendor Advance</span>
                                    <span class="font-mono font-bold">₹<span x-text="formatMoney(excessAdvance())"></span></span>
                                </div>
                            @endif
                        </div>

                        <div class="border-t border-slate-100 pt-2.5 space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Cash / Bank Effect</span>
                                <span class="font-bold text-slate-800" x-text="cashBankSummaryLabel()"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Warehouse Stock</span>
                                <span class="font-bold text-slate-800" x-text="stockSummaryLabel()"></span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" form="credit-note-form"
                            :disabled="isSubmitting || isCheckingStock || items.length === 0 || totalAmount() <= 0"
                            class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white shadow-xs transition hover:bg-emerald-700 disabled:opacity-50">
                        <i data-lucide="check-circle-2" class="h-4 w-4"></i>
                        <span x-text="isCheckingStock ? 'Checking Stock...' : (isSubmitting ? 'Posting...' : 'Review & Post Credit Note')"></span>
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- Finalization Preview Modal --}}
    <div x-cloak x-show="showPreviewModal"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-xs"
         @keydown.escape.window="showPreviewModal = false">
        <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl" @click.outside="showPreviewModal = false">
            <div class="border-b border-slate-100 bg-slate-50 px-5 py-4">
                <h3 class="text-sm font-black text-slate-900">Confirm Purchase Credit Note</h3>
                <p class="mt-0.5 text-xs text-slate-500">Review the impact below before posting to vendor balance, cashbook, and inventory.</p>
            </div>
            <div class="divide-y divide-slate-100 px-5 py-3 text-xs">
                <div class="flex justify-between py-2"><span class="text-slate-500">Vendor</span><span class="font-bold text-slate-900">{{ $vendorInfo['name'] }}</span></div>
                <div class="flex justify-between py-2"><span class="text-slate-500">Credit Note Date</span><span class="font-mono font-bold text-slate-900" x-text="creditDate"></span></div>
                <div class="flex justify-between py-2" x-show="selectedInvoiceData"><span class="text-slate-500">Invoice Reference</span><span class="font-mono font-bold text-indigo-700" x-text="(selectedInvoiceData?.invoice_number || '') + ' (Reference Only)'"></span></div>
                <div class="flex justify-between py-2" x-show="vendorCreditNoteRef"><span class="text-slate-500">Supplier Credit Ref</span><span class="font-mono font-bold text-slate-900" x-text="vendorCreditNoteRef"></span></div>
                <div class="flex justify-between py-2"><span class="text-slate-500">Total Credit Amount</span><span class="font-mono font-black text-emerald-700">₹<span x-text="formatMoney(totalAmount())"></span></span></div>
                @if($isV2Enabled)
                    <div class="flex justify-between py-2" x-show="moneyEffect === 'vendor_credit'"><span class="text-slate-500">New Available Vendor Credit</span><span class="font-mono font-bold text-emerald-700">₹<span x-text="formatMoney(totalAmount())"></span></span></div>
                    <div class="flex justify-between py-2" x-show="moneyEffect === 'vendor_credit' && selectedInvoiceData"><span class="text-slate-500">Invoice Balance Unchanged</span><span class="font-bold text-slate-700">Settled separately</span></div>
                @else
                    <div class="flex justify-between py-2"><span class="text-slate-500">Invoice Adjustment</span><span class="font-mono font-bold text-slate-900">₹<span x-text="formatMoney(appliedToBill())"></span></span></div>
                    <div class="flex justify-between py-2"><span class="text-slate-500">Excess Vendor Advance</span><span class="font-mono font-bold text-indigo-700">₹<span x-text="formatMoney(excessAdvance())"></span></span></div>
                @endif
                <div class="flex justify-between py-2"><span class="text-slate-500">Cash / Bank Effect</span><span class="font-bold text-slate-900" x-text="cashBankSummaryLabel()"></span></div>
                <div class="flex justify-between py-2"><span class="text-slate-500">Stock Adjustment</span><span class="font-bold text-slate-900" x-text="stockSummaryLabel()"></span></div>
            </div>
            <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                <button type="button" @click="showPreviewModal = false"
                        class="h-9 rounded-xl border border-slate-200 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-100">
                    Back to Edit
                </button>
                <button type="button" @click="confirmAndPost()"
                        class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white hover:bg-emerald-700">
                    <i data-lucide="check" class="h-3.5 w-3.5"></i>
                    <span>Confirm &amp; Post Credit Note</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Negative Stock Confirmation Modal --}}
    <div x-cloak x-show="showNegativeStockModal"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-xs"
         @keydown.escape.window="closeNegativeStockModal()">
        <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl border border-amber-300 bg-white shadow-2xl" @click.outside="closeNegativeStockModal()">
            <div class="border-b border-amber-200 bg-amber-50 px-6 py-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                        <i data-lucide="alert-triangle" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-amber-950">Insufficient Warehouse Stock Warning</h3>
                        <p class="mt-0.5 text-xs text-amber-800">Proceeding will push the items below into negative stock.</p>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-hidden rounded-xl border border-slate-200">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-3.5 py-2.5">Product</th>
                                <th class="px-3.5 py-2.5 text-center">Grade</th>
                                <th class="px-3.5 py-2.5 text-right">Available</th>
                                <th class="px-3.5 py-2.5 text-right">Requested</th>
                                <th class="px-3.5 py-2.5 text-right text-rose-600">Resulting</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <template x-for="(shortage, idx) in shortages" :key="idx">
                                <tr>
                                    <td class="px-3.5 py-2.5 font-bold text-slate-900" x-text="shortage.product_name"></td>
                                    <td class="px-3.5 py-2.5 text-center" x-text="'Grade ' + shortage.grade"></td>
                                    <td class="px-3.5 py-2.5 text-right" x-text="formatQty(shortage.available_qty)"></td>
                                    <td class="px-3.5 py-2.5 text-right" x-text="formatQty(shortage.requested_qty)"></td>
                                    <td class="px-3.5 py-2.5 text-right font-black text-rose-600" x-text="formatQty(shortage.resulting_stock)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-3.5">
                <button type="button" @click="closeNegativeStockModal()" class="h-9 rounded-xl border border-slate-200 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-100">
                    Cancel &amp; Adjust Qty
                </button>
                <button type="button" @click="confirmNegativeStock()" class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-amber-600 px-4 text-xs font-bold text-white hover:bg-amber-700">
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
    const purchaseCreditNoteProducts = {{ Js::from($productCatalog) }};
    const purchaseCreditNoteShortages = {{ Js::from(session('negative_stock_shortages') ?? []) }};
    const purchaseCreditNoteHasShortages = {{ session()->has('negative_stock_shortages') ? 'true' : 'false' }};
    const initialInvoicesById = Object.fromEntries((purchaseCreditNoteInvoices || []).map(i => [String(i.id), i]));

    function purchaseCreditNoteForm() {
        const initInvoiceId = @json((string) $initialInvoiceId);
        return {
            isSubmitting: false,
            isLoadingInvoiceItems: false,
            isCheckingStock: false,
            allowNegativeStock: {{ old('allow_negative_stock') ? 'true' : 'false' }},
            showNegativeStockModal: purchaseCreditNoteHasShortages,
            showPreviewModal: false,
            shortages: purchaseCreditNoteShortages,
            creditDate: @json((string) $initialCreditDate),
            vendorCreditNoteRef: @json((string) old('vendor_credit_note_ref', '')),
            isV2Enabled: {{ $isV2Enabled ? 'true' : 'false' }},
            reason: @json((string) $initialReason),
            moneyEffect: @json((string) $initialMoneyEffect),
            inventoryAction: @json((string) $initialInventoryAction),
            purchaseInvoiceId: initInvoiceId,
            warehouseId: @json((string) $initialWarehouseId),
            companyAccountId: @json((string) $initialCompanyAccountId),
            deductStockForDamage: {{ $initialDeductStock ? 'true' : 'false' }},
            notes: @json((string) $initialNotes),
            invoicesList: purchaseCreditNoteInvoices || [],
            productsList: purchaseCreditNoteProducts || [],
            stockMap: {},
            invoiceSearch: '',
            isInvoiceDropdownOpen: false,
            selectedInvoiceData: initInvoiceId ? (initialInvoicesById[String(initInvoiceId)] || null) : null,
            selectedInvoice: null,
            items: @json($initialItems),

            init() {
                this.refreshRowStockLevels();
                this.$nextTick(() => window.lucide?.createIcons());
            },

            formatMoney(val) {
                const num = parseFloat(val) || 0;
                return num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            formatQty(val) {
                const num = parseFloat(val) || 0;
                return num.toLocaleString('en-IN', { minimumFractionDigits: 1, maximumFractionDigits: 3 });
            },

            reasonSuggestionHint() {
                const map = {
                    return: 'Suggested: Stock OUT & Vendor Credit',
                    damage_wastage: 'Suggested: Damage / Wastage & Vendor Credit',
                    shortage: 'Suggested: No Stock Movement & Vendor Credit',
                    price_adjustment: 'Suggested: No Stock Movement & Vendor Credit',
                    other: 'Choose the financial and stock routing that matches this adjustment',
                };
                return map[this.reason] || '';
            },

            openProductDropdown(targetItem) {
                this.items.forEach(it => {
                    if (it !== targetItem) it.productDropdownOpen = false;
                });
                targetItem.productDropdownOpen = true;
            },

            filteredProducts(search) {
                const q = (search || '').toLowerCase().trim();
                if (!q) return this.productsList.slice(0, 30);
                return this.productsList.filter(p => (p.name || '').toLowerCase().includes(q)).slice(0, 30);
            },

            selectProduct(item, prod) {
                item.product_id = prod.id;
                item.product_name = prod.name;
                item.productSearch = prod.name;
                item.unit = prod.base_unit || 'kg';
                item.productDropdownOpen = false;
                this.refreshRowStockLevels();
            },

            unitsForProduct(productId) {
                const prod = this.productsList.find(p => Number(p.id) === Number(productId));
                if (prod && Array.isArray(prod.units) && prod.units.length > 0) {
                    return prod.units;
                }
                return [{ unit: 'kg', label: 'KG (Base)', conversion_to_base: 1 }];
            },

            shouldShowAvailableStock() {
                return Boolean(this.warehouseId) && (this.inventoryAction === 'out' || (this.inventoryAction === 'damage' && this.deductStockForDamage));
            },

            availableStockLabel(item) {
                const key = `${item.product_id}_${item.grade || 'A'}`;
                if (this.stockMap[key] === undefined) return '...';
                return `${this.formatQty(this.stockMap[key])} kg`;
            },

            async refreshRowStockLevels() {
                if (!this.shouldShowAvailableStock()) return;
                const validItems = this.items.filter(it => Number(it.product_id) > 0);
                if (validItems.length === 0) return;

                try {
                    const res = await fetch('{{ route("admin.cashbook.finance.purchase.vendors.credit-notes.check-stock", $supplier) }}', {
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
                            items: validItems.map(it => ({
                                product_id: it.product_id,
                                quantity: it.quantity || 1,
                                grade: it.grade || 'A',
                                unit: it.unit || 'kg'
                            }))
                        })
                    });
                    if (res.ok) {
                        const data = await res.json();
                        if (data.items_stock) {
                            this.stockMap = Object.assign({}, this.stockMap, data.items_stock);
                        }
                    }
                } catch (e) {
                    // Non-blocking stock hint
                }
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
                this.showPreviewModal = true;
                this.$nextTick(() => window.lucide?.createIcons());
            },

            async openReviewOrCheckStock() {
                if (this.isSubmitting || this.isCheckingStock) return;
                const willDeduct = (this.inventoryAction === 'out') || (this.inventoryAction === 'damage' && this.deductStockForDamage);

                if (!willDeduct || !this.warehouseId || this.allowNegativeStock) {
                    this.showPreviewModal = true;
                    this.$nextTick(() => window.lucide?.createIcons());
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
                                unit: it.unit,
                                unit_price: it.unit_price
                            }))
                        })
                    });

                    const data = await response.json();
                    this.isCheckingStock = false;
                    if (data.items_stock) {
                        this.stockMap = Object.assign({}, this.stockMap, data.items_stock);
                    }
                    if (data.has_shortage) {
                        this.shortages = data.shortages;
                        this.showNegativeStockModal = true;
                        this.$nextTick(() => window.lucide?.createIcons());
                        return;
                    }
                    this.showPreviewModal = true;
                    this.$nextTick(() => window.lucide?.createIcons());
                } catch (err) {
                    this.isCheckingStock = false;
                    this.showPreviewModal = true;
                }
            },

            confirmAndPost() {
                if (this.isSubmitting) return;
                this.showPreviewModal = false;
                this.isSubmitting = true;
                document.getElementById('credit-note-form').submit();
            },

            cashBankSummaryLabel() {
                if (this.moneyEffect === 'in') return 'Money IN (+₹' + this.formatMoney(this.totalAmount()) + ')';
                if (this.moneyEffect === 'out') return 'Money OUT (-₹' + this.formatMoney(this.totalAmount()) + ')';
                return 'None (Vendor Credit)';
            },

            stockSummaryLabel() {
                if (this.inventoryAction === 'out') return 'Stock OUT (FIFO Deduction)';
                if (this.inventoryAction === 'in') return 'Stock IN (Add Batch)';
                if (this.inventoryAction === 'damage') return this.deductStockForDamage ? 'Damage + Stock Deduction' : 'Damage Log Only';
                return 'No Stock Movement';
            },

            filteredInvoices() {
                if (!this.invoiceSearch || !this.invoiceSearch.trim()) return this.invoicesList;
                const q = this.invoiceSearch.toLowerCase().replace(/,/g, '').trim();
                return this.invoicesList.filter(inv =>
                    (inv.invoice_number || '').toLowerCase().includes(q)
                    || String(inv.net_amount || '').includes(q)
                    || String(inv.outstanding_amount || '').includes(q)
                    || (inv.date || '').toLowerCase().includes(q)
                );
            },

            selectInvoice(inv) {
                if (inv) {
                    this.purchaseInvoiceId = inv.id;
                    this.selectedInvoiceData = inv;
                    this.isInvoiceDropdownOpen = false;
                    this.invoiceSearch = '';
                    if (!this.isV2Enabled) {
                        this.fetchInvoiceItems(inv.public_uuid);
                    }
                } else {
                    this.purchaseInvoiceId = '';
                    this.selectedInvoiceData = null;
                    this.selectedInvoice = null;
                    this.isInvoiceDropdownOpen = false;
                    this.invoiceSearch = '';
                }
                this.$nextTick(() => window.lucide?.createIcons());
            },

            importInvoiceItems() {
                if (!this.selectedInvoiceData) return;
                const hasEnteredData = this.items.some(it => Number(it.product_id) > 0 || (parseFloat(it.unit_price) || 0) > 0);
                if (hasEnteredData && !window.confirm('Import products from this invoice? This will replace your currently entered product rows.')) {
                    return;
                }
                this.fetchInvoiceItems(this.selectedInvoiceData.public_uuid);
            },

            addLineItem() {
                this.items.push({
                    isFromInvoice: false,
                    product_id: '',
                    product_name: '',
                    productSearch: '',
                    productDropdownOpen: false,
                    unit: 'kg',
                    grade: 'A',
                    quantity: 1,
                    unit_price: 0,
                    purchaser_cart_item_id: null,
                    returnable_qty: null
                });
                this.$nextTick(() => window.lucide?.createIcons());
            },

            removeItem(index) {
                if (this.items.length > 1) {
                    this.items.splice(index, 1);
                }
            },

            totalAmount() {
                return this.items.reduce((sum, it) => sum + ((parseFloat(it.quantity) || 0) * (parseFloat(it.unit_price) || 0)), 0);
            },

            appliedToBill() {
                if (this.moneyEffect !== 'vendor_credit' || !this.selectedInvoiceData) return 0;
                const outstanding = this.selectedInvoiceData.outstanding_amount !== undefined
                    ? this.selectedInvoiceData.outstanding_amount
                    : (this.selectedInvoice?.remaining_balance || 0);
                return Math.min(this.totalAmount(), Math.max(0, outstanding));
            },

            excessAdvance() {
                if (this.moneyEffect !== 'vendor_credit') return 0;
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
                                productSearch: it.product_name,
                                productDropdownOpen: false,
                                unit: it.unit || 'kg',
                                grade: it.grade || 'A',
                                quantity: it.returnable_qty > 0 ? it.returnable_qty : it.purchased_qty,
                                purchased_qty: it.purchased_qty,
                                returnable_qty: it.returnable_qty,
                                unit_price: it.unit_price || 0,
                                purchaser_cart_item_id: it.purchaser_cart_item_id
                            }));
                            this.refreshRowStockLevels();
                        }
                    }
                } catch (err) {
                    console.error('Failed to load invoice items:', err);
                } finally {
                    this.isLoadingInvoiceItems = false;
                    this.$nextTick(() => window.lucide?.createIcons());
                }
            }
        };
    }

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
