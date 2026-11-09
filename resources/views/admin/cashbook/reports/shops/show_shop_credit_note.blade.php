@extends('admin.cashbook.layouts.app')

@section('title', 'Credit Note — ' . $creditNote->credit_note_number . ' — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="file-check-2" class="w-5 h-5 text-emerald-600"></i>
    <span>Shop Credit Note Details</span>
@endsection

@section('header_subtitle')
    Audited shop-level credit note, source adjustments, and bill-wise invoice applications.
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-6"
    x-data="creditNoteDetailView({
        creditNoteId: {{ $creditNote->id }},
        creditNoteNumber: '{{ $creditNote->credit_note_number }}',
        shopId: {{ $creditNote->shop_id }},
        shopName: '{{ addslashes($creditNote->shop?->name ?? '') }}',
        totalCreditAmount: {{ (float) $creditNote->total_credit_amount }},
        unappliedCredit: {{ (float) $unappliedCreditTotal }},
        appliedCredit: {{ (float) $appliedCreditTotal }},
        outstandingInvoicesUrl: '{{ route('admin.cashbook.reports.shops.credit-notes.outstanding-invoices', $creditNote) }}',
        applyCreditUrl: '{{ route('admin.cashbook.reports.shops.credit-notes.apply', $creditNote) }}',
        cancelUrl: '{{ route('admin.cashbook.reports.shops.credit-notes.cancel', $creditNote) }}',
        reverseAppUrlBase: '{{ url('admin/cashbook/reports/shops/credit-notes/' . $creditNote->credit_note_number . '/applications') }}',
    })">

    <!-- Top Breadcrumbs & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.cashbook.reports.shops.credit-notes') }}"
                class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-2xs">
                <i data-lucide="arrow-left" class="h-3.5 w-3.5 text-slate-400"></i>
                <span>Credit Notes</span>
            </a>
            <span class="text-slate-300">/</span>
            <span class="text-xs font-bold text-slate-500">Shop Credit Note</span>
            <span class="rounded-lg bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-xs font-mono font-bold text-emerald-900">
                {{ $creditNote->credit_note_number }}
            </span>
            <span class="rounded-lg bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 uppercase">
                {{ $creditNote->accounting_mode->value === 'shop_level' ? 'Shop-Level' : 'Legacy Invoice-Linked' }}
            </span>
        </div>

        <div class="flex items-center gap-2">
            <!-- Status Badge -->
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-extrabold uppercase tracking-wider
                {{ $creditNote->isApproved() ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : ($creditNote->isVoided() ? 'bg-rose-50 text-rose-800 border border-rose-200' : 'bg-amber-50 text-amber-800 border border-amber-200') }}">
                @if ($creditNote->isApproved())
                    <i data-lucide="check-circle" class="w-3.5 h-3.5 text-emerald-600"></i>
                @elseif ($creditNote->isVoided())
                    <i data-lucide="x-circle" class="w-3.5 h-3.5 text-rose-600"></i>
                @else
                    <i data-lucide="clock" class="w-3.5 h-3.5 text-amber-600"></i>
                @endif
                <span>{{ $creditNote->status->value }}</span>
            </span>

            @if ($creditNote->isApproved())
                @if ($canCancelCreditNote)
                    <button type="button" @click="openCancelModal()"
                        class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-rose-300 bg-rose-50 px-3.5 text-xs font-bold text-rose-700 hover:bg-rose-100 hover:text-rose-900 transition shadow-2xs">
                        <i data-lucide="ban" class="w-3.5 h-3.5 text-rose-600"></i>
                        <span>Cancel Credit Note</span>
                    </button>
                @endif

                @if ($unappliedCreditTotal > 0)
                    <button type="button" @click="openApplicationModal()"
                        class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-indigo-300 bg-indigo-600 px-3.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-xs">
                        <i data-lucide="split" class="w-3.5 h-3.5 text-white"></i>
                        <span>Apply Credit to Invoices</span>
                    </button>
                @endif
            @endif
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 shadow-xs flex items-center gap-3">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
            <span class="text-xs font-bold text-emerald-900">{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50/80 p-4 shadow-xs flex items-center gap-3">
            <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
            <span class="text-xs font-bold text-rose-800">{{ session('error') }}</span>
        </div>
    @endif

    <!-- Cancelled / Voided Alert Banner -->
    @if ($creditNote->isVoided())
        <div class="rounded-2xl border border-rose-200 bg-rose-50/90 p-4 shadow-xs space-y-1">
            <div class="flex items-center gap-2 text-rose-900 font-bold text-xs">
                <i data-lucide="x-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                <span>Credit Note Cancelled / Voided</span>
            </div>
            <p class="text-xs text-rose-700">
                This Credit Note was cancelled on <strong class="font-mono">{{ optional($creditNote->voided_at)->format('d M Y, H:i') }}</strong>
                @if ($creditNote->voidedBy)
                    by <strong>{{ $creditNote->voidedBy->name }}</strong>
                @endif
                @if ($creditNote->void_reason)
                    &bull; Reason: <em class="italic">"{{ $creditNote->void_reason }}"</em>
                @endif
            </p>
        </div>
    @endif

    <!-- Warehouse Reconciliation Warning Banner -->
    @if ($hasUnreconciledPhysicalReturns)
        <div class="rounded-2xl border border-amber-300 bg-amber-50/90 p-4 shadow-xs space-y-1">
            <div class="flex items-center gap-2 text-amber-950 font-bold text-xs">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0"></i>
                <span>Warehouse Reconciliation Required</span>
            </div>
            <p class="text-xs text-amber-800">
                This Credit Note contains active physical return receipts verified at the warehouse. Warehouse staff must reverse the stock receipts in the warehouse module before office cancellation can be performed.
            </p>
        </div>
    @endif

    <!-- 1. SUMMARY METRICS CARDS (4 Cards) -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <!-- Total Credit Amount -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Total Credit Amount</span>
            <span class="mt-1 block font-mono text-xl sm:text-2xl font-black text-emerald-700">
                -₹{{ number_format((float) $creditNote->total_credit_amount, 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">Total financial credit posted</span>
        </div>

        <!-- Applied Credit -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-indigo-700">Applied Credit</span>
            <span class="mt-1 block font-mono text-xl sm:text-2xl font-bold text-indigo-900">
                ₹{{ number_format((float) $appliedCreditTotal, 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">Allocated to specific invoices</span>
        </div>

        <!-- Remaining Unapplied Credit -->
        <div class="rounded-2xl border border-slate-200/80 p-4 shadow-xs {{ $unappliedCreditTotal > 0 ? 'bg-emerald-50/60 border-emerald-300' : 'bg-white' }}">
            <span class="block text-[10px] font-black uppercase tracking-wider {{ $unappliedCreditTotal > 0 ? 'text-emerald-800' : 'text-slate-500' }}">Remaining Unapplied</span>
            <span class="mt-1 block font-mono text-xl sm:text-2xl font-black {{ $unappliedCreditTotal > 0 ? 'text-emerald-700' : 'text-slate-500' }}">
                ₹{{ number_format((float) $unappliedCreditTotal, 2) }}
            </span>
            <span class="mt-0.5 block text-[10px] font-semibold text-slate-400">
                {{ $unappliedCreditTotal > 0 ? 'Available to apply' : 'Fully allocated' }}
            </span>
        </div>

        <!-- Shop & Date -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Shop</span>
            <span class="mt-1 block text-sm font-bold text-slate-900 truncate" title="{{ $creditNote->shop?->name }}">
                {{ $creditNote->shop?->name }}
            </span>
            <span class="mt-0.5 block text-[10px] font-mono font-bold text-slate-400">
                Date: {{ optional($creditNote->credit_note_date)->format('d M Y') }}
            </span>
        </div>
    </div>

    <!-- 2. AUDIT & METADATA CARD -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs">
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-500 mb-3 border-b border-slate-100 pb-2">Audit & Posting Information</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase">Created By</span>
                <span class="font-bold text-slate-800">{{ $creditNote->creator?->name ?? 'System' }}</span>
                <span class="block text-[10px] text-slate-400 font-mono">{{ $creditNote->created_at?->format('d M Y, H:i') }}</span>
            </div>
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase">Finalized By</span>
                <span class="font-bold text-slate-800">{{ $creditNote->approvedBy?->name ?? ($creditNote->isApproved() ? 'Office Staff' : '—') }}</span>
                <span class="block text-[10px] text-slate-400 font-mono">{{ $creditNote->approved_at?->format('d M Y, H:i') ?? '—' }}</span>
            </div>
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase">Primary Reason</span>
                <span class="font-bold text-slate-800 uppercase">{{ $creditNote->reason_type?->label() }}</span>
            </div>
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase">General Notes</span>
                <span class="text-slate-700 italic">{{ $creditNote->notes ?: 'None provided' }}</span>
            </div>
        </div>
    </div>

    <!-- 3. SOURCE INVOICE ADJUSTMENTS TABLE -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-sm font-black text-slate-900">Original Source Invoice Adjustments</h3>
                <p class="text-xs text-slate-500">Historical invoice lines referenced, billed prices, and custom credited rates.</p>
            </div>
            <span class="text-xs font-bold text-slate-600">Product Lines: {{ $productItems->count() }}</span>
        </div>

        @if ($productItems->isEmpty())
            <p class="text-xs text-slate-400 italic py-4">No product adjustment lines recorded on this credit note.</p>
        @else
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="py-2.5 px-3">Source Invoice</th>
                            <th class="py-2.5 px-3">Product & Grade</th>
                            <th class="py-2.5 px-3 text-right">Orig Billed Rate</th>
                            <th class="py-2.5 px-3 text-right">Credited Qty</th>
                            <th class="py-2.5 px-3 text-right">Custom Credit Rate</th>
                            <th class="py-2.5 px-3 text-right">Line Credit Total</th>
                            <th class="py-2.5 px-3">Inventory Treatment</th>
                            <th class="py-2.5 px-3">Physical Receipt Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($productItems as $item)
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    <span class="font-mono font-bold text-slate-900 block">{{ $item->sourceInvoice?->invoice_number ?? "INV-{$item->source_shop_invoice_id}" }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">{{ optional($item->sourceInvoice?->business_date)->format('d M Y') }}</span>
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="font-bold text-slate-900 block">{{ $item->product?->name ?? $item->product_name }}</span>
                                    <span class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-700">
                                        Grade {{ $item->product_grade }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-500">
                                    ₹{{ number_format((float) $item->original_unit_price, 2) }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-800">
                                    {{ number_format((float) $item->adjusted_quantity, 3) }} {{ $item->unit }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-800">
                                    ₹{{ number_format((float) ($item->credit_rate ?: $item->adjusted_unit_price), 2) }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-black text-emerald-700">
                                    -₹{{ number_format((float) $item->line_credit_amount, 2) }}
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider
                                        {{ $item->inventory_action->value === 'return_to_stock' ? 'bg-blue-50 text-blue-800 border border-blue-200' : ($item->inventory_action->value === 'write_off_wastage' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700') }}">
                                        {{ str_replace('_', ' ', $item->inventory_action->value) }}
                                    </span>
                                    @if ($item->received_grade && $item->received_grade !== $item->product_grade)
                                        <span class="block text-[10px] font-bold text-blue-700 mt-0.5">Exp Grade: {{ $item->received_grade }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider
                                        {{ ($item->receipt_status?->value ?? $item->receipt_status) === 'verified' ? 'bg-emerald-100 text-emerald-800' : (($item->receipt_status?->value ?? $item->receipt_status) === 'pending_receipt' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">
                                        {{ $item->receipt_status ? str_replace('_', ' ', $item->receipt_status instanceof \BackedEnum ? $item->receipt_status->value : (string) $item->receipt_status) : '—' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- 4. COMMERCIAL DISCOUNTS (IF PRESENT) -->
    @if ($discountItems->isNotEmpty())
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <h3 class="text-sm font-black text-slate-900">Commercial Discounts (Financial Only)</h3>
                <span class="font-mono font-bold text-emerald-700">Total: -₹{{ number_format((float) $commercialDiscountTotal, 2) }}</span>
            </div>
            <div class="divide-y divide-slate-100 text-xs">
                @foreach ($discountItems as $disc)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-slate-800">{{ $disc->notes ?: 'Commercial Discount' }}</span>
                            @if ($disc->sourceInvoice)
                                <span class="text-slate-400 block text-[10px]">Ref Invoice: {{ $disc->sourceInvoice->invoice_number }}</span>
                            @endif
                        </div>
                        <span class="font-mono font-bold text-emerald-700">-₹{{ number_format((float) $disc->line_credit_amount, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- 5. BILL-WISE CREDIT APPLICATIONS & HISTORY -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-sm font-black text-slate-900">Bill-Wise Credit Applications</h3>
                <p class="text-xs text-slate-500">Allocations of this credit note balance against specific outstanding shop invoices.</p>
            </div>
            <div class="flex items-center gap-2">
                @if ($creditNote->isApproved() && $unappliedCreditTotal > 0)
                    <button type="button" @click="openApplicationModal()"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-900 hover:bg-indigo-100 transition shadow-2xs">
                        <i data-lucide="plus" class="w-3.5 h-3.5 text-indigo-600"></i>
                        <span>Apply Unapplied Credit</span>
                    </button>
                @endif
            </div>
        </div>

        @if ($creditNote->applications->isEmpty())
            <div class="py-8 text-center text-xs text-slate-400 space-y-1 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
                <i data-lucide="split" class="w-8 h-8 mx-auto text-slate-300"></i>
                <p class="font-bold text-slate-600">No invoice applications recorded yet</p>
                <p class="text-[11px] text-slate-400">
                    The full credit of ₹{{ number_format((float) $creditNote->total_credit_amount, 2) }} remains as an unapplied credit balance for {{ $creditNote->shop?->name }}.
                </p>
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="py-2.5 px-3">Target Invoice # / Date</th>
                            <th class="py-2.5 px-3 text-right">Applied Credit Amount</th>
                            <th class="py-2.5 px-3">Application Date</th>
                            <th class="py-2.5 px-3">Applied By</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3">Notes</th>
                            <th class="py-2.5 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($creditNote->applications as $app)
                            <tr class="hover:bg-slate-50/60 transition {{ $app->isReversed() ? 'opacity-60 bg-slate-50/40' : '' }}">
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    <span class="font-mono font-bold text-slate-900 block">{{ $app->invoice?->invoice_number ?? "INV-{$app->shop_invoice_id}" }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">{{ optional($app->invoice?->business_date)->format('d M Y') }}</span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-indigo-700">
                                    ₹{{ number_format((float) $app->amount, 2) }}
                                </td>
                                <td class="py-2.5 px-3 text-slate-600 font-mono whitespace-nowrap">
                                    {{ optional($app->applied_at)->format('d M Y') }}
                                </td>
                                <td class="py-2.5 px-3 text-slate-800">
                                    {{ $app->appliedByUser?->name ?? 'Office User' }}
                                </td>
                                <td class="py-2.5 px-3">
                                    @if ($app->isReversed())
                                        <div class="space-y-0.5">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200 px-2 py-0.5 text-[10px] font-bold text-rose-800 uppercase">
                                                Reversed
                                            </span>
                                            <div class="text-[10px] text-slate-500 font-mono">
                                                {{ optional($app->reversed_at)->format('d M Y, H:i') }}
                                                @if ($app->reversedByUser)
                                                    by {{ $app->reversedByUser->name }}
                                                @endif
                                            </div>
                                            @if ($app->reversal_reason)
                                                <div class="text-[10px] text-rose-700 italic max-w-xs truncate" title="{{ $app->reversal_reason }}">
                                                    "{{ $app->reversal_reason }}"
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-bold text-emerald-800 uppercase">
                                            Active
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-slate-500 italic">
                                    {{ $app->notes ?: '—' }}
                                </td>
                                <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                    @if ($app->isActive() && $creditNote->isApproved() && $canReverseApp)
                                        <button type="button"
                                            @click="openReverseModal({{ $app->id }}, '{{ $app->invoice?->invoice_number ?? "INV-{$app->shop_invoice_id}" }}', {{ (float) $app->amount }})"
                                            class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] font-bold text-rose-700 bg-rose-50 border border-rose-200 hover:bg-rose-100 hover:text-rose-900 transition shadow-2xs">
                                            <i data-lucide="rotate-ccw" class="w-3 h-3 text-rose-600"></i>
                                            <span>Reverse</span>
                                        </button>
                                    @elseif ($app->isReversed())
                                        <span class="text-[10px] text-slate-400 font-semibold italic">Reversed</span>
                                    @else
                                        <span class="text-[10px] text-slate-400 font-semibold">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- 6. APPLICATION MODAL (ALPINE.JS) -->
    <div x-show="showModal" x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="closeApplicationModal()"
            class="relative w-full max-w-3xl rounded-3xl bg-white p-6 shadow-2xl space-y-5 border border-slate-200">

            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 font-bold">
                        <i data-lucide="split" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Apply Credit to Outstanding Invoices</h3>
                        <p class="text-xs text-slate-500">Allocate unapplied credit from <span class="font-mono font-bold" x-text="creditNoteNumber"></span></p>
                    </div>
                </div>
                <button type="button" @click="closeApplicationModal()"
                    class="h-8 w-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center font-bold">✕</button>
            </div>

            <!-- Modal Metrics Card -->
            <div class="grid grid-cols-3 gap-3 bg-slate-50/80 p-3.5 rounded-2xl border border-slate-100 text-xs">
                <div>
                    <span class="block text-[10px] font-bold text-slate-400 uppercase">Available Unapplied</span>
                    <span class="font-mono font-black text-emerald-700 text-sm">
                        ₹<span x-text="Number(unappliedCredit).toFixed(2)"></span>
                    </span>
                </div>
                <div>
                    <span class="block text-[10px] font-bold text-slate-400 uppercase">Total Being Allocated</span>
                    <span class="font-mono font-black text-indigo-700 text-sm">
                        ₹<span x-text="Number(totalAllocated).toFixed(2)"></span>
                    </span>
                </div>
                <div>
                    <span class="block text-[10px] font-bold text-slate-400 uppercase">Remaining After Apply</span>
                    <span class="font-mono font-bold text-sm"
                        :class="remainingAfterAlloc < 0 ? 'text-rose-600' : 'text-slate-800'">
                        ₹<span x-text="Number(remainingAfterAlloc).toFixed(2)"></span>
                    </span>
                </div>
            </div>

            <!-- Over-Allocation Warning -->
            <template x-if="remainingAfterAlloc < 0">
                <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs font-bold text-rose-800 flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                    <span>Total allocation exceeds available unapplied credit! Please adjust amounts.</span>
                </div>
            </template>

            <!-- Outstanding Invoices List -->
            <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                <template x-if="isLoadingInvoices">
                    <div class="py-12 text-center text-xs text-slate-400">
                        <i data-lucide="loader-2" class="w-5 h-5 animate-spin mx-auto text-slate-400 mb-2"></i>
                        <span>Loading outstanding invoices for <span x-text="shopName"></span>...</span>
                    </div>
                </template>

                <template x-if="!isLoadingInvoices && availableInvoices.length === 0">
                    <div class="py-8 text-center text-xs text-slate-400 bg-slate-50 rounded-xl">
                        No outstanding invoices found for this shop. All invoices appear fully paid.
                    </div>
                </template>

                <template x-if="!isLoadingInvoices && availableInvoices.length > 0">
                    <div class="divide-y divide-slate-100 border border-slate-200 rounded-xl overflow-hidden">
                        <template x-for="inv in availableInvoices" :key="inv.id">
                            <div class="p-3 bg-white hover:bg-slate-50/70 transition flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-slate-900" x-text="inv.invoice_number"></span>
                                        <span class="text-[10px] font-mono text-slate-400" x-text="inv.invoice_date"></span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5">
                                        <span>Billed: <strong class="font-mono">₹<span x-text="Number(inv.final_total).toFixed(2)"></span></strong></span>
                                        <span>&bull;</span>
                                        <span>Outstanding: <strong class="font-mono text-rose-700">₹<span x-text="Number(inv.effective_outstanding).toFixed(2)"></span></strong></span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button type="button" @click="allocateMax(inv)"
                                        class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-600 hover:bg-slate-100">
                                        Max
                                    </button>
                                    <div class="relative w-36">
                                        <span class="absolute left-2.5 top-1.5 text-xs text-slate-400 font-mono">₹</span>
                                        <input type="number" step="0.01" min="0" :max="inv.effective_outstanding"
                                            x-model.number="allocations[inv.id]"
                                            class="w-full rounded-lg border border-slate-200 bg-white pl-6 pr-2 py-1 text-xs font-mono font-bold text-slate-800 focus:border-indigo-500"
                                            placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Notes & Submission -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Application Remarks / Reference</label>
                <input type="text" x-model="applicationNotes" placeholder="Optional notes for this credit allocation batch..."
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-800 focus:border-indigo-500">
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                <button type="button" @click="closeApplicationModal()"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="button" @click="submitApplication()" :disabled="!canSubmitApplication || isSubmitting"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-5 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-xs disabled:opacity-50">
                    <template x-if="isSubmitting">
                        <span class="inline-flex items-center gap-1">
                            <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>
                            <span>Applying...</span>
                        </span>
                    </template>
                    <template x-if="!isSubmitting">
                        <span class="inline-flex items-center gap-1">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                            <span>Confirm Application</span>
                        </span>
                    </template>
                </button>
            </div>
        </div>
    </div>

    <!-- 7. CREDIT NOTE CANCELLATION MODAL (ALPINE.JS) -->
    <div x-show="showCancelModal" x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="closeCancelModal()"
            class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl space-y-5 border border-slate-200">

            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-50 text-rose-600 font-bold">
                        <i data-lucide="alert-octagon" class="w-4 h-4 text-rose-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Cancel Shop Credit Note</h3>
                        <p class="text-xs text-slate-500">Void credit note and reverse all financial credit</p>
                    </div>
                </div>
                <button type="button" @click="closeCancelModal()"
                    class="h-8 w-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center font-bold">✕</button>
            </div>

            <!-- Financial Summary -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-slate-500">Credit Note:</span>
                    <span class="font-mono font-bold text-slate-900" x-text="creditNoteNumber"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Shop:</span>
                    <span class="font-bold text-slate-800" x-text="shopName"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Financial Credit to Void:</span>
                    <span class="font-mono font-black text-rose-700 text-sm">-₹{{ number_format((float) $creditNote->total_credit_amount, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Active Applications:</span>
                    <span class="font-bold text-slate-800">{{ $activeApplicationsCount }} application(s) (₹{{ number_format((float) $appliedCreditTotal, 2) }})</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Unapplied Balance:</span>
                    <span class="font-mono font-bold text-emerald-700">₹{{ number_format((float) $unappliedCreditTotal, 2) }}</span>
                </div>
            </div>

            @if ($hasUnreconciledPhysicalReturns)
                <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-300 text-xs text-amber-950 space-y-1">
                    <div class="font-bold flex items-center gap-1.5">
                        <i data-lucide="shield-alert" class="w-4 h-4 text-amber-600 shrink-0"></i>
                        <span>Warehouse Reconciliation Required</span>
                    </div>
                    <p>Cancellation is blocked because active physical return receipts exist for this credit note. Warehouse staff must reverse the stock receipts in the warehouse module first.</p>
                </div>
            @else
                <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-900 space-y-1">
                    <div class="font-bold flex items-center gap-1.5">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                        <span>Irreversible Financial Reversal</span>
                    </div>
                    <p>Cancelling will immediately void the single Cashbook financial credit, reverse all {{ $activeApplicationsCount }} active bill applications, and mark this Credit Note as VOIDED. Original invoices and payments will remain unchanged.</p>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1">Mandatory Cancellation Reason <span class="text-rose-600">*</span></label>
                    <textarea x-model="cancelReason" rows="2" placeholder="Explain why this Credit Note is being cancelled (e.g. issued in error, customer dispute resolved)..."
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-rose-500"></textarea>
                </div>
            @endif

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                <button type="button" @click="closeCancelModal()"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                    Close
                </button>
                @if (! $hasUnreconciledPhysicalReturns)
                    <button type="button" @click="executeCancel()" :disabled="!cancelReason.trim() || isCancelling"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-5 py-2 text-xs font-bold text-white hover:bg-rose-700 transition shadow-xs disabled:opacity-50">
                        <template x-if="isCancelling">
                            <span class="inline-flex items-center gap-1">
                                <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>
                                <span>Cancelling...</span>
                            </span>
                        </template>
                        <template x-if="!isCancelling">
                            <span class="inline-flex items-center gap-1">
                                <i data-lucide="ban" class="w-3.5 h-3.5"></i>
                                <span>Confirm Cancellation</span>
                            </span>
                        </template>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- 8. APPLICATION REVERSAL MODAL (ALPINE.JS) -->
    <div x-show="showReverseAppModal" x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="closeReverseModal()"
            class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl space-y-5 border border-slate-200">

            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-amber-600 font-bold">
                        <i data-lucide="rotate-ccw" class="w-4 h-4 text-amber-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Reverse Credit Application</h3>
                        <p class="text-xs text-slate-500">Return allocation to Credit Note unapplied balance</p>
                    </div>
                </div>
                <button type="button" @click="closeReverseModal()"
                    class="h-8 w-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center font-bold">✕</button>
            </div>

            <!-- Allocation Summary -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-slate-500">Target Invoice:</span>
                    <span class="font-mono font-bold text-slate-900" x-text="targetInvoiceNumber"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Applied Credit to Reverse:</span>
                    <span class="font-mono font-black text-rose-700 text-sm">₹<span x-text="Number(targetAppAmount).toFixed(2)"></span></span>
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-950 space-y-1">
                <div class="font-bold flex items-center gap-1.5">
                    <i data-lucide="info" class="w-4 h-4 text-amber-600 shrink-0"></i>
                    <span>Allocation Reversal</span>
                </div>
                <p>Reversing will return ₹<span x-text="Number(targetAppAmount).toFixed(2)"></span> to the Credit Note's unapplied balance. The target invoice's outstanding balance will be restored accordingly. No Cashbook posting will occur.</p>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 mb-1">Mandatory Reversal Reason <span class="text-rose-600">*</span></label>
                <textarea x-model="reverseReason" rows="2" placeholder="e.g. Allocated to wrong invoice, office reallocation required..."
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-amber-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                <button type="button" @click="closeReverseModal()"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="button" @click="executeReverseApp()" :disabled="!reverseReason.trim() || isReversing"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 px-5 py-2 text-xs font-bold text-white hover:bg-amber-700 transition shadow-xs disabled:opacity-50">
                    <template x-if="isReversing">
                        <span class="inline-flex items-center gap-1">
                            <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>
                            <span>Reversing...</span>
                        </span>
                    </template>
                    <template x-if="!isReversing">
                        <span class="inline-flex items-center gap-1">
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                            <span>Confirm Reversal</span>
                        </span>
                    </template>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function creditNoteDetailView(config) {
        return {
            creditNoteId: config.creditNoteId,
            creditNoteNumber: config.creditNoteNumber,
            shopId: config.shopId,
            shopName: config.shopName,
            totalCreditAmount: config.totalCreditAmount,
            unappliedCredit: config.unappliedCredit,
            appliedCredit: config.appliedCredit,
            outstandingInvoicesUrl: config.outstandingInvoicesUrl,
            applyCreditUrl: config.applyCreditUrl,
            cancelUrl: config.cancelUrl,
            reverseAppUrlBase: config.reverseAppUrlBase,

            showModal: false,
            isLoadingInvoices: false,
            availableInvoices: [],
            allocations: {},
            applicationNotes: '',
            isSubmitting: false,

            // Cancellation state
            showCancelModal: false,
            cancelReason: '',
            isCancelling: false,

            // Reversal state
            showReverseAppModal: false,
            targetAppId: null,
            targetInvoiceNumber: '',
            targetAppAmount: 0.0,
            reverseReason: '',
            isReversing: false,

            openCancelModal() {
                this.showCancelModal = true;
                this.cancelReason = '';
                this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
            },

            closeCancelModal() {
                this.showCancelModal = false;
            },

            executeCancel() {
                if (!this.cancelReason.trim() || this.isCancelling) return;

                this.isCancelling = true;

                fetch(this.cancelUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        reason: this.cancelReason.trim()
                    })
                })
                .then(async res => {
                    const data = await res.json();
                    if (!res.ok) {
                        alert(data.message || 'Validation error while cancelling credit note.');
                        this.isCancelling = false;
                        return;
                    }

                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        window.location.reload();
                    }
                })
                .catch(err => {
                    console.error('Cancel error:', err);
                    alert('An unexpected error occurred while cancelling credit note.');
                    this.isCancelling = false;
                });
            },

            openReverseModal(id, invoiceNumber, amount) {
                this.targetAppId = id;
                this.targetInvoiceNumber = invoiceNumber;
                this.targetAppAmount = amount;
                this.reverseReason = '';
                this.showReverseAppModal = true;
                this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
            },

            closeReverseModal() {
                this.showReverseAppModal = false;
            },

            executeReverseApp() {
                if (!this.reverseReason.trim() || !this.targetAppId || this.isReversing) return;

                this.isReversing = true;
                const url = `${this.reverseAppUrlBase}/${this.targetAppId}/reverse`;

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        reason: this.reverseReason.trim()
                    })
                })
                .then(async res => {
                    const data = await res.json();
                    if (!res.ok) {
                        alert(data.message || 'Validation error while reversing credit application.');
                        this.isReversing = false;
                        return;
                    }

                    window.location.reload();
                })
                .catch(err => {
                    console.error('Reverse error:', err);
                    alert('An unexpected error occurred while reversing credit application.');
                    this.isReversing = false;
                });
            },

            openApplicationModal() {
                this.showModal = true;
                this.allocations = {};
                this.applicationNotes = '';
                this.loadOutstandingInvoices();
            },

            closeApplicationModal() {
                this.showModal = false;
            },

            loadOutstandingInvoices() {
                this.isLoadingInvoices = true;
                fetch(this.outstandingInvoicesUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(data => {
                    this.availableInvoices = data.invoices || [];
                    this.unappliedCredit = data.unapplied_credit ?? this.unappliedCredit;
                    this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
                })
                .catch(err => {
                    console.error('Error loading outstanding invoices:', err);
                })
                .finally(() => {
                    this.isLoadingInvoices = false;
                });
            },

            allocateMax(inv) {
                const currentTotal = this.totalAllocated - (parseFloat(this.allocations[inv.id]) || 0);
                const remainingCredit = Math.max(0, this.unappliedCredit - currentTotal);
                const maxAmount = Math.min(inv.effective_outstanding, remainingCredit);
                this.allocations[inv.id] = Math.round(maxAmount * 100) / 100;
            },

            get totalAllocated() {
                let sum = 0;
                for (const id in this.allocations) {
                    sum += (parseFloat(this.allocations[id]) || 0);
                }
                return Math.round(sum * 100) / 100;
            },

            get remainingAfterAlloc() {
                return Math.round((this.unappliedCredit - this.totalAllocated) * 100) / 100;
            },

            get canSubmitApplication() {
                return this.totalAllocated > 0.001 && this.remainingAfterAlloc >= -0.0001 && !this.isSubmitting;
            },

            submitApplication() {
                if (!this.canSubmitApplication) return;

                this.isSubmitting = true;

                const batch = [];
                for (const id in this.allocations) {
                    const amt = parseFloat(this.allocations[id]) || 0;
                    if (amt > 0.001) {
                        batch.push({
                            invoice_id: parseInt(id),
                            amount: amt,
                            notes: this.applicationNotes || null
                        });
                    }
                }

                fetch(this.applyCreditUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        allocations: batch
                    })
                })
                .then(async res => {
                    const data = await res.json();
                    if (!res.ok) {
                        alert(data.message || 'Validation error while applying credit.');
                        this.isSubmitting = false;
                        return;
                    }

                    // Reload page to display fresh applications and updated unapplied balance
                    window.location.reload();
                })
                .catch(err => {
                    console.error('Apply error:', err);
                    alert('An unexpected error occurred while applying credit.');
                    this.isSubmitting = false;
                });
            }
        };
    }

    if (window.Alpine) {
        window.Alpine.data('creditNoteDetailView', creditNoteDetailView);
    } else {
        document.addEventListener('alpine:init', function() {
            window.Alpine.data('creditNoteDetailView', creditNoteDetailView);
        });
    }
</script>
@endsection
