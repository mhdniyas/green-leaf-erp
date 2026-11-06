@extends('admin.cashbook.layouts.app')

@section('title', 'Add Credit Note — ' . $invoice->invoice_number . ' — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="file-plus" class="w-5 h-5 text-emerald-600"></i> Add Credit Note
@endsection

@section('header_subtitle')
    Independent price, quantity, shortage, or return adjustments for {{ $invoice->shop?->name }} ({{ $invoice->invoice_number }}).
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-6" x-data="creditNoteForm()">

    <!-- Top Breadcrumbs & Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.cashbook.reports.shops.invoices') }}"
                class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-2xs">
                <i data-lucide="arrow-left" class="h-3.5 w-3.5 text-slate-400"></i>
                <span>Back to Invoices Report</span>
            </a>
            <span class="text-slate-300">/</span>
            <span class="text-xs font-bold text-slate-500">Invoice</span>
            <span class="rounded-lg bg-slate-100 px-2 py-0.5 text-xs font-mono font-bold text-slate-900">
                {{ $invoice->invoice_number }}
            </span>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('purchasing.shop-invoices.show', $invoice) }}"
                target="_blank"
                class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-2xs">
                <i data-lucide="external-link" class="h-3.5 w-3.5 text-slate-400"></i>
                <span>View Original Invoice</span>
            </a>
        </div>
    </div>

    <!-- Alert / Validation Errors -->
    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50/70 p-4 shadow-xs">
            <div class="flex items-start gap-3">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600 shrink-0 mt-0.5"></i>
                <div class="space-y-1">
                    <h4 class="text-xs font-bold text-rose-900">Please correct the following errors before saving:</h4>
                    <ul class="list-disc list-inside text-xs font-semibold text-rose-700 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50/70 p-4 shadow-xs flex items-center gap-3">
            <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
            <span class="text-xs font-bold text-rose-800">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Invoice Header & Context Cards -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <!-- 1. Shop Info -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Shop</span>
            <span class="mt-1 block text-sm font-bold text-slate-900 truncate" title="{{ $invoice->shop?->name }}">
                {{ $invoice->shop?->name }}
            </span>
            <span class="mt-0.5 block text-[10px] font-bold font-mono text-slate-400 uppercase">
                {{ $invoice->shop?->code }}
            </span>
        </div>

        <!-- 2. Invoice Date -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Invoice Date</span>
            <span class="mt-1 block text-sm font-bold text-slate-900 font-mono">
                {{ optional($invoice->business_date ?? $invoice->created_at)->format('d M Y') }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">
                Status: <span class="uppercase font-bold text-emerald-700">{{ $invoice->status }}</span>
            </span>
        </div>

        <!-- 3. Original Bill -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Original Billed</span>
            <span class="mt-1 block font-mono text-base sm:text-lg font-bold text-slate-900">
                ₹{{ number_format((float) $invoice->final_total, 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">Immutable baseline</span>
        </div>

        <!-- 4. Existing Adjustments -->
        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/60 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Prior Adjustments</span>
            <span class="mt-1 block font-mono text-base sm:text-lg font-bold text-slate-800">
                -₹{{ number_format($invoice->approvedCreditTotal(), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">
                +₹{{ number_format($invoice->approvedDebitTotal(), 2) }} Debits
            </span>
        </div>

        <!-- 5. Current Net Bill -->
        <div class="rounded-2xl border border-slate-900 bg-slate-900 p-4 shadow-xs text-white">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-300">Current Net Bill</span>
            <span class="mt-1 block font-mono text-base sm:text-lg font-bold text-white">
                ₹{{ number_format($invoice->effectiveFinalTotal(), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">
                Paid: ₹{{ number_format((float) $invoice->paid_amount, 2) }}
            </span>
        </div>

        <!-- 6. Outstanding Due -->
        <div class="rounded-2xl border border-rose-200/80 bg-rose-50/40 p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-rose-800">Current Due</span>
            <span class="mt-1 block font-mono text-base sm:text-lg font-bold text-rose-700">
                ₹{{ number_format($invoice->effectiveBalanceAmount(), 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-rose-600">Before new note</span>
        </div>
    </div>

    <!-- Live Adjustment Impact Banner (Reactive Alpine.js) -->
    <div class="rounded-3xl border border-indigo-200/90 bg-gradient-to-r from-indigo-50/80 via-white to-emerald-50/60 p-4 sm:p-5 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-lg bg-indigo-600 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-white">
                        Live Impact
                    </span>
                    <h3 class="text-sm font-black text-slate-900">Projected Final Bill After This Credit Note</h3>
                </div>
                <p class="text-xs font-medium text-slate-500">
                    Values recompute in real-time as you edit revised quantities or unit prices below.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-4 sm:gap-6 font-mono text-xs">
                <!-- Total Line Adjustments -->
                <div class="border-l-2 border-indigo-300 pl-3">
                    <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">Total Adjustment</span>
                    <span class="text-base font-bold"
                        :class="netAdjustment < 0 ? 'text-emerald-700' : (netAdjustment > 0 ? 'text-blue-700' : 'text-slate-400')"
                        x-text="netAdjustmentLabel">
                        —
                    </span>
                </div>

                <!-- New Net Bill Payable -->
                <div class="border-l-2 border-slate-300 pl-3">
                    <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">New Net Bill</span>
                    <span class="text-base font-black text-slate-900" x-text="'₹' + projectedNetPayableFormatted">
                        ₹{{ number_format($invoice->effectiveFinalTotal(), 2) }}
                    </span>
                </div>

                <!-- Projected Balance Remaining -->
                <div class="border-l-2 border-rose-300 pl-3">
                    <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">Projected Due</span>
                    <span class="text-base font-black"
                        :class="projectedRemainingBalance > 0 ? 'text-rose-700' : 'text-emerald-700'"
                        x-text="'₹' + projectedRemainingBalanceFormatted">
                        ₹{{ number_format($invoice->effectiveBalanceAmount(), 2) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Creation Form -->
    <form method="POST" action="{{ route('admin.cashbook.reports.shops.credit-notes.store') }}" class="space-y-6">
        @csrf
        <input type="hidden" name="selection_token" value="{{ $selectionToken }}">

        <!-- Header Controls Card -->
        <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-4">
            <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                <i data-lucide="sliders" class="w-4 h-4 text-emerald-600"></i>
                <span>Credit Note Details</span>
            </h3>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 items-start">
                <!-- Credit Note Date -->
                <label class="space-y-1 block">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">
                        Credit Note Date <span class="text-rose-500">*</span>
                    </span>
                    <input type="date"
                        name="credit_note_date"
                        value="{{ old('credit_note_date', today()->toDateString()) }}"
                        required
                        class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <span class="text-[10px] text-slate-400 block">Date of accounting correction</span>
                </label>

                <!-- Primary Reason -->
                <label class="space-y-1 block">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">
                        Primary Reason <span class="text-rose-500">*</span>
                    </span>
                    <select name="reason_type"
                        required
                        class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-2xs cursor-pointer focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                        @foreach ($reasons as $reason)
                            <option value="{{ $reason->value }}" @selected(old('reason_type', 'price_correction') === $reason->value)>
                                {{ $reason->label() }}
                            </option>
                        @endforeach
                    </select>
                    <span class="text-[10px] text-slate-400 block">Audit classification</span>
                </label>

                <!-- Internal Notes / Memo -->
                <label class="space-y-1 block sm:col-span-2">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">
                        Internal Notes / Explanation
                    </span>
                    <input type="text"
                        name="notes"
                        value="{{ old('notes') }}"
                        placeholder="e.g. Quality rate revision agreed with shop manager..."
                        class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-800 shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <span class="text-[10px] text-slate-400 block">Preserved for audit trail and reports</span>
                </label>
            </div>
        </div>

        <!-- Product Revision Table Card -->
        <div class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-1">
                <div>
                    <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                        <i data-lucide="package" class="w-4 h-4 text-emerald-600"></i>
                        <span>Product Revision & Stock Adjustment</span>
                    </h3>
                    <p class="text-[11px] font-medium text-slate-400">
                        Edit the Corrected Quantity or Price for each product you wish to adjust. Untouched rows will not create redundant notes.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                        @click="resetAllRows()"
                        class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition shadow-2xs">
                        <i data-lucide="rotate-ccw" class="w-3.5 h-3.5 text-slate-400"></i>
                        <span>Reset All Revisions</span>
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-400 bg-slate-50/50">
                            <th class="py-3 px-3">#</th>
                            <th class="py-3 px-3">Product & Grade</th>
                            <th class="py-3 px-3 text-right">Original Billed</th>
                            <th class="py-3 px-3 text-right">Current Effective</th>
                            <th class="py-3 px-3 w-32">Corrected Qty</th>
                            <th class="py-3 px-3 w-32">Corrected Rate (₹)</th>
                            <th class="py-3 px-3 text-right w-28">Line Adjustment</th>
                            <th class="py-3 px-3 w-48">Inventory Action</th>
                            <th class="py-3 px-3 w-44">Line Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-800">
                        <template x-for="(item, index) in items" :key="item.id">
                            <tr class="hover:bg-slate-50/80 transition" :class="item.hasChanged ? 'bg-indigo-50/20' : ''">
                                <!-- Index -->
                                <td class="py-3 px-3 text-slate-400 font-mono text-[11px]" x-text="index + 1"></td>

                                <!-- Product & Grade -->
                                <td class="py-3 px-3">
                                    <span class="font-bold text-slate-900 block" x-text="item.product_name"></span>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">
                                        Grade <span x-text="item.product_grade"></span> &bull; <span x-text="item.unit"></span>
                                    </span>
                                    <input type="hidden" :name="'items[' + index + '][shop_invoice_item_id]'" :value="item.id">
                                </td>

                                <!-- Original Billed -->
                                <td class="py-3 px-3 text-right font-mono whitespace-nowrap text-slate-600">
                                    <div><span x-text="item.original_quantity"></span> <span class="text-[10px]" x-text="item.unit"></span></div>
                                    <div class="text-[10px] text-slate-400">@ ₹<span x-text="item.original_unit_price.toFixed(2)"></span></div>
                                </td>

                                <!-- Current Effective -->
                                <td class="py-3 px-3 text-right font-mono whitespace-nowrap text-slate-900">
                                    <div><span class="font-bold" x-text="item.effective_quantity"></span> <span class="text-[10px]" x-text="item.unit"></span></div>
                                    <div class="text-[10px] text-slate-400">₹<span x-text="item.effective_line_total.toFixed(2)"></span> total</div>
                                </td>

                                <!-- Corrected Qty Input -->
                                <td class="py-3 px-3">
                                    <div class="relative">
                                        <input type="number"
                                            step="0.01"
                                            min="0"
                                            :name="'items[' + index + '][revised_quantity]'"
                                            x-model.number="item.revised_quantity"
                                            @input="recalculateRow(item)"
                                            class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-mono font-bold text-slate-800 shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-right"
                                            :placeholder="item.effective_quantity">
                                    </div>
                                </td>

                                <!-- Corrected Rate Input -->
                                <td class="py-3 px-3">
                                    <div class="relative">
                                        <input type="number"
                                            step="0.01"
                                            min="0"
                                            :name="'items[' + index + '][revised_unit_price]'"
                                            x-model.number="item.revised_unit_price"
                                            @input="recalculateRow(item)"
                                            class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-mono font-bold text-slate-800 shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-right"
                                            :placeholder="item.original_unit_price">
                                    </div>
                                </td>

                                <!-- Line Adjustment Preview -->
                                <td class="py-3 px-3 text-right font-mono whitespace-nowrap">
                                    <template x-if="item.diff < 0">
                                        <span class="inline-flex items-center rounded-md bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 text-xs font-bold text-emerald-700">
                                            -₹<span x-text="Math.abs(item.diff).toFixed(2)"></span>
                                        </span>
                                    </template>
                                    <template x-if="item.diff > 0">
                                        <span class="inline-flex items-center rounded-md bg-blue-50 border border-blue-200 px-1.5 py-0.5 text-xs font-bold text-blue-700">
                                            +₹<span x-text="item.diff.toFixed(2)"></span>
                                        </span>
                                    </template>
                                    <template x-if="item.diff === 0">
                                        <span class="text-slate-300 font-normal">—</span>
                                    </template>
                                </td>

                                <!-- Inventory Action Dropdown -->
                                <td class="py-3 px-3">
                                    <div class="space-y-1.5">
                                        <select :name="'items[' + index + '][inventory_action]'"
                                            x-model="item.inventory_action"
                                            class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2 text-[11px] font-bold text-slate-800 shadow-2xs cursor-pointer focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                                            <option value="none">Financial Only</option>
                                            <option value="return_to_stock">Return to Stock</option>
                                            <option value="write_off_wastage">Move to Damage (Wastage)</option>
                                            <option value="deduct_from_stock" disabled class="text-slate-400 bg-slate-100">
                                                Deduct from stock (Not supported)
                                            </option>
                                        </select>

                                        <div x-show="item.inventory_action === 'write_off_wastage'" x-cloak>
                                            <span class="block text-[9px] font-black uppercase tracking-wider text-amber-700 mb-0.5">Damage Reason <span class="text-rose-500">*</span></span>
                                            <select :name="'items[' + index + '][damage_reason]'"
                                                x-model="item.damage_reason"
                                                class="h-8 w-full rounded-lg border border-amber-300 bg-amber-50/70 px-2 text-[11px] font-bold text-amber-900 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 cursor-pointer">
                                                @foreach ($wastageReasons ?? \App\Enums\Inventory\WastageReason::cases() as $wReason)
                                                    <option value="{{ $wReason->value }}">
                                                        {{ $wReason->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </td>

                                <!-- Line Note -->
                                <td class="py-3 px-3">
                                    <input type="text"
                                        :name="'items[' + index + '][notes]'"
                                        x-model="item.notes"
                                        placeholder="Reason..."
                                        class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-[11px] font-medium text-slate-700 shadow-2xs focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Inventory Action Helper Note -->
            <div class="rounded-2xl bg-slate-50 border border-slate-200/60 p-3.5 flex items-start gap-3 text-xs text-slate-600">
                <i data-lucide="info" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5"></i>
                <div class="space-y-1">
                    <p class="font-bold text-slate-800">Inventory Action Guidance:</p>
                    <ul class="list-disc list-inside space-y-0.5 text-[11px] text-slate-500">
                        <li><strong>Financial Only:</strong> Adjusts customer billing and cashbook ledger balances without modifying warehouse stock quantities.</li>
                        <li><strong>Return to Stock:</strong> Safely restores physically returned goods into warehouse stock batches using the existing Stock Ledger system.</li>
                        <li><strong>Move to Damage:</strong> Records physical wastage in Damage &amp; Loss reporting against the original loadout batch without double-deducting warehouse inventory.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Form Submission Footer -->
        <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.cashbook.reports.shops.invoices') }}"
                    class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-2xs">
                    Cancel
                </a>
            </div>

            <div class="flex items-center gap-3">
                <!-- Save Draft -->
                <button type="submit"
                    name="action"
                    value="draft"
                    class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-300 bg-white px-5 text-xs font-black text-slate-800 hover:bg-slate-50 hover:border-slate-400 transition shadow-2xs">
                    <i data-lucide="save" class="w-4 h-4 text-slate-500"></i>
                    <span>Save Draft</span>
                </button>

                <!-- Save & Finalize -->
                <button type="submit"
                    name="action"
                    value="finalize"
                    class="inline-flex h-11 items-center gap-2 rounded-xl bg-emerald-600 px-6 text-xs font-black text-white hover:bg-emerald-700 transition shadow-xs">
                    <i data-lucide="check-circle" class="w-4 h-4 text-white"></i>
                    <span>Save & Finalize</span>
                </button>
            </div>
        </div>
    </form>

</div>

<script>
    window.__creditNoteConfig = {
        originalTotal: {{ (float) $invoice->final_total }},
        paidAmount: {{ (float) $invoice->paid_amount }},
        currentEffectiveTotal: {{ (float) $invoice->effectiveFinalTotal() }},
        items: @json($itemRows)
    };

    function creditNoteForm(config) {
        config = config || window.__creditNoteConfig || {};
        return {
            originalTotal: Number(config.originalTotal || 0),
            paidAmount: Number(config.paidAmount || 0),
            currentEffectiveTotal: Number(config.currentEffectiveTotal || 0),
            items: (config.items || []).map(function(item) {
                return {
                    id: item.id,
                    product_id: item.product_id,
                    product_name: item.product_name,
                    product_grade: item.product_grade,
                    unit: item.unit,
                    original_quantity: Number(item.original_quantity || 0),
                    original_unit_price: Number(item.original_unit_price || 0),
                    original_line_total: Number(item.original_line_total || 0),
                    effective_quantity: Number(item.effective_quantity || 0),
                    effective_unit_price: Number(item.effective_unit_price || 0),
                    effective_line_total: Number(item.effective_line_total || 0),
                    remaining_creditable_quantity: Number(item.remaining_creditable_quantity || 0),
                    remaining_creditable_amount: Number(item.remaining_creditable_amount || 0),
                    revised_quantity: Number(item.effective_quantity || 0),
                    revised_unit_price: Number(item.original_unit_price || 0),
                    inventory_action: 'none',
                    damage_reason: 'transit_damage',
                    notes: '',
                    diff: 0,
                    hasChanged: false,
                };
            }),

            recalculateRow: function(item) {
                const qty = item.revised_quantity !== null && item.revised_quantity !== undefined && item.revised_quantity !== ''
                    ? parseFloat(item.revised_quantity)
                    : item.effective_quantity;
                const price = item.revised_unit_price !== null && item.revised_unit_price !== undefined && item.revised_unit_price !== ''
                    ? parseFloat(item.revised_unit_price)
                    : item.original_unit_price;

                const revisedTotal = Math.round(qty * price * 100) / 100;
                const diff = Math.round((revisedTotal - item.effective_line_total) * 100) / 100;

                item.diff = diff;
                item.hasChanged = (Math.abs(qty - item.effective_quantity) > 0.0001) || (Math.abs(price - item.original_unit_price) > 0.001);
            },

            resetAllRows: function() {
                const self = this;
                this.items.forEach(function(item) {
                    item.revised_quantity = item.effective_quantity;
                    item.revised_unit_price = item.original_unit_price;
                    item.inventory_action = 'none';
                    item.notes = '';
                    self.recalculateRow(item);
                });
            },

            get netAdjustment() {
                let total = 0;
                this.items.forEach(function(item) {
                    total += item.diff;
                });
                return Math.round(total * 100) / 100;
            },

            get netAdjustmentLabel() {
                if (this.netAdjustment < 0) {
                    return '-₹' + Math.abs(this.netAdjustment).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' (Credit)';
                } else if (this.netAdjustment > 0) {
                    return '+₹' + this.netAdjustment.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' (Debit)';
                }
                return '₹0.00';
            },

            get projectedNetPayable() {
                const net = this.currentEffectiveTotal + this.netAdjustment;
                return Math.max(0, Math.round(net * 100) / 100);
            },

            get projectedNetPayableFormatted() {
                return this.projectedNetPayable.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            get projectedRemainingBalance() {
                const bal = this.projectedNetPayable - this.paidAmount;
                return Math.max(0, Math.round(bal * 100) / 100);
            },

            get projectedRemainingBalanceFormatted() {
                return this.projectedRemainingBalance.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        };
    }

    if (window.Alpine) {
        window.Alpine.data('creditNoteForm', creditNoteForm);
    } else {
        document.addEventListener('alpine:init', function() {
            window.Alpine.data('creditNoteForm', creditNoteForm);
        });
    }

    // Tab-scoped selection token persistence and clean URL enforcement
    try {
        if (window.location.search) {
            window.history.replaceState({}, document.title, "{{ route('admin.cashbook.reports.shops.credit-notes.create') }}");
        }
        sessionStorage.setItem('credit_note_selection_token', '{{ $selectionToken }}');
    } catch (e) {}
</script>
@endsection
