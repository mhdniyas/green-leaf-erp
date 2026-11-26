@extends('admin.cashbook.layouts.app')

@section('title', 'Purchase Credit Note — ' . $creditNote->credit_note_number . ' — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="receipt-text" class="h-5 w-5 text-emerald-600"></i>
    <span>Purchase Credit Note Details</span>
@endsection

@section('header_subtitle')
    Audited purchase credit note, inventory movements, and double-entry accounting records.
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-6" x-data="{ showVoidModal: false, voidReason: '', isVoiding: false }">
    {{-- Top Navigation & Action Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200/80 pb-4">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $supplier->public_uuid]) }}#credits"
               class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-2xs">
                <i data-lucide="arrow-left" class="h-3.5 w-3.5 text-slate-400"></i>
                <span>{{ $supplier->name }}</span>
            </a>
            <span class="text-slate-300">/</span>
            <span class="text-xs font-bold text-slate-500">Credit Notes</span>
            <span class="text-slate-300">/</span>
            <span class="rounded-lg bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-xs font-mono font-bold text-emerald-900">
                {{ $creditNote->credit_note_number }}
            </span>

            {{-- Status Badge --}}
            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-extrabold uppercase tracking-wider {{ $creditNote->isActive() ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                <span class="h-1.5 w-1.5 rounded-full {{ $creditNote->isActive() ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                <span>{{ $creditNote->status->label() }}</span>
            </span>
        </div>

        <div class="flex items-center gap-2">
            @if($creditNote->available_credit > 0.009)
                <a href="{{ route('admin.cashbook.finance.vendor-credit.show', ['supplier' => $supplier->public_uuid]) }}"
                   class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 text-xs font-bold text-white hover:bg-emerald-700 transition shadow-2xs">
                    <i data-lucide="credit-card" class="h-3.5 w-3.5"></i>
                    <span>Settle Vendor</span>
                </a>
            @endif

            {{-- Print Button --}}
            <button type="button" onclick="window.print()"
                    class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-2xs">
                <i data-lucide="printer" class="h-3.5 w-3.5 text-slate-400"></i>
                <span>Print</span>
            </button>

            {{-- Void Button (if active & permitted) --}}
            @if($creditNote->isActive() && auth()->user()->can('cashbook.reconciliation.manage'))
                <button type="button" @click="showVoidModal = true"
                        class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 text-xs font-bold text-rose-700 hover:bg-rose-100 transition shadow-2xs">
                    <i data-lucide="ban" class="h-3.5 w-3.5 text-rose-600"></i>
                    <span>Void Credit Note</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-bold text-emerald-900 shadow-2xs flex items-center gap-2">
            <i data-lucide="check-circle-2" class="h-4 w-4 text-emerald-600 shrink-0"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs font-bold text-rose-800 shadow-2xs flex items-center gap-2">
            <i data-lucide="alert-circle" class="h-4 w-4 text-rose-600 shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Voided Banner --}}
    @if($creditNote->isVoided())
        <div class="rounded-2xl border border-rose-200 bg-rose-50/90 p-5 shadow-2xs space-y-2">
            <div class="flex items-center gap-2 text-rose-900 font-black text-sm">
                <i data-lucide="alert-triangle" class="h-5 w-5 text-rose-600 shrink-0"></i>
                <span>This Purchase Credit Note was VOIDED</span>
            </div>
            <p class="text-xs text-rose-800">
                Voided on <strong class="font-mono">{{ optional($creditNote->voided_at)->format('d M Y, h:i A') }}</strong>
                @if($creditNote->voidedBy)
                    by <strong class="text-rose-950">{{ $creditNote->voidedBy->name }}</strong>
                @endif
                @if($creditNote->void_reason)
                    &mdash; Reason: <em class="italic font-medium">"{{ $creditNote->void_reason }}"</em>
                @endif
            </p>
            <p class="text-[11px] text-rose-700 font-medium">
                All associated stock movements, damage write-offs, vendor balance adjustments, and journal entries have been completely reversed.
            </p>
        </div>
    @endif

    {{-- 1. Metric Overview Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Card 1: Total Credited --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-2xs">
            <span class="block text-[11px] font-black uppercase tracking-wider text-slate-500">Total Credited</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-black font-mono {{ $creditNote->isVoided() ? 'text-slate-400 line-through' : 'text-emerald-700' }}">
                    ₹{{ number_format((float) $creditNote->total_amount, 2) }}
                </span>
            </div>
            <span class="mt-1 block text-[11px] text-slate-400">
                {{ $creditNote->items->count() }} line item(s) credited
            </span>
        </div>

        {{-- Card 2: Money Effect --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-2xs">
            <span class="block text-[11px] font-black uppercase tracking-wider text-slate-500">Money Effect</span>
            <div class="mt-2">
                <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-black
                    {{ $creditNote->money_effect->value === 'vendor_credit' ? 'bg-indigo-50 text-indigo-800 border border-indigo-200' : '' }}
                    {{ $creditNote->money_effect->value === 'in' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : '' }}
                    {{ $creditNote->money_effect->value === 'out' ? 'bg-amber-50 text-amber-800 border border-amber-200' : '' }}">
                    {{ $creditNote->money_effect->label() }}
                </span>
            </div>
            <div class="mt-2 text-[11px] text-slate-600 space-y-0.5 font-medium">
                @if($creditNote->isV2() && $creditNote->money_effect->value === 'vendor_credit')
                    <div>Original Credit: <span class="font-mono font-bold text-slate-800">₹{{ number_format((float) $creditNote->total_amount, 2) }}</span></div>
                    <div>Utilized: <span class="font-mono font-bold text-amber-700">₹{{ number_format((float) $creditNote->utilized_amount, 2) }}</span></div>
                    <div>Available: <span class="font-mono font-bold text-emerald-700">₹{{ number_format((float) $creditNote->available_credit, 2) }}</span></div>
                    <div class="pt-0.5">
                        Status: <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-700">{{ str_replace('_', ' ', $creditNote->utilization_status) }}</span>
                    </div>
                @else
                    @if($creditNote->applied_to_invoice_amount > 0)
                        <div>Applied to Bill: <span class="font-mono font-bold text-slate-800">₹{{ number_format((float) $creditNote->applied_to_invoice_amount, 2) }}</span></div>
                    @endif
                    @if($creditNote->vendor_advance_amount > 0)
                        <div>Vendor Advance: <span class="font-mono font-bold text-slate-800">₹{{ number_format((float) $creditNote->vendor_advance_amount, 2) }}</span></div>
                    @endif
                    @if($creditNote->cash_refund_amount > 0)
                        <div>Refund: <span class="font-mono font-bold text-slate-800">₹{{ number_format((float) $creditNote->cash_refund_amount, 2) }}</span>
                        @if($creditNote->companyAccount)
                            via {{ $creditNote->companyAccount->account_name }}
                        @endif
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- Card 3: Inventory Action --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-2xs">
            <span class="block text-[11px] font-black uppercase tracking-wider text-slate-500">Inventory Action</span>
            <div class="mt-2">
                <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-black
                    {{ $creditNote->inventory_action->value === 'out' ? 'bg-amber-50 text-amber-800 border border-amber-200' : '' }}
                    {{ $creditNote->inventory_action->value === 'in' ? 'bg-teal-50 text-teal-800 border border-teal-200' : '' }}
                    {{ $creditNote->inventory_action->value === 'damage' ? 'bg-rose-50 text-rose-800 border border-rose-200' : '' }}
                    {{ $creditNote->inventory_action->value === 'none' ? 'bg-slate-50 text-slate-600 border border-slate-200' : '' }}">
                    {{ $creditNote->inventory_action->label() }}
                </span>
            </div>
            <div class="mt-2 text-[11px] text-slate-600 space-y-0.5 font-medium">
                @if($creditNote->warehouse)
                    <div>Warehouse: <span class="font-bold text-slate-800">{{ $creditNote->warehouse->name }}</span></div>
                @endif
                @if($creditNote->wastage_reason)
                    <div>Damage Reason: <span class="font-bold text-slate-800">{{ ucwords(str_replace('_', ' ', $creditNote->wastage_reason)) }}</span></div>
                    <div>Deduct Stock: <span class="font-bold {{ $creditNote->deduct_stock_for_damage ? 'text-amber-700' : 'text-slate-500' }}">{{ $creditNote->deduct_stock_for_damage ? 'Yes' : 'No' }}</span></div>
                @endif
            </div>
        </div>

        {{-- Card 4: Date & Bill Reference --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-2xs">
            <span class="block text-[11px] font-black uppercase tracking-wider text-slate-500">Details & Origin</span>
            <div class="mt-2 text-xs font-bold text-slate-900">
                Date: <span class="font-mono text-emerald-800">{{ $creditNote->credit_date?->format('d M Y') }}</span>
            </div>
            <div class="mt-1 text-[11px] text-slate-600 space-y-0.5 font-medium">
                <div>Reason: <span class="font-bold text-slate-800">{{ $creditNote->reason->label() }}</span></div>
                @if($creditNote->vendor_credit_note_ref)
                    <div>Supplier Ref: <span class="font-mono font-bold text-slate-800">{{ $creditNote->vendor_credit_note_ref }}</span></div>
                @endif
                @if($creditNote->purchaseInvoice)
                    <div>
                        {{ $creditNote->isV2() ? 'Invoice Ref (Reference Only):' : 'Linked Bill:' }}
                        <span class="font-mono font-bold text-indigo-700">{{ $creditNote->purchaseInvoice->invoice_number }}</span>
                    </div>
                @else
                    <div>Type: <span class="text-slate-500">Standalone Credit</span></div>
                @endif
            </div>
        </div>
    </div>

    {{-- 2. Line Items Table --}}
    <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-2xs">
        <div class="border-b border-slate-200 bg-slate-50/70 px-5 py-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="boxes" class="h-4 w-4 text-emerald-600"></i>
                <h2 class="text-sm font-black text-slate-900">Credited Items</h2>
            </div>
            <span class="text-xs font-bold text-slate-500">{{ $creditNote->items->count() }} item(s)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500">
                        <th class="py-3 pl-5 pr-3">Product</th>
                        <th class="px-3 py-3 text-center">Grade</th>
                        <th class="px-3 py-3 text-right">Quantity</th>
                        <th class="px-3 py-3 text-right">Unit Price</th>
                        <th class="px-3 py-3 text-right">Subtotal</th>
                        <th class="py-3 pl-3 pr-5 text-right">References</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($creditNote->items as $item)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-3 pl-5 pr-3 font-bold text-slate-900">
                                <div>{{ $item->product?->name ?? 'Unknown Product #' . $item->product_id }}</div>
                                @if($item->product?->code)
                                    <span class="text-[10px] font-mono text-slate-400">Code: {{ $item->product->code }}</span>
                                @endif
                                @if($item->notes)
                                    <div class="text-[11px] font-normal italic text-slate-500 mt-0.5">{{ $item->notes }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-700">
                                    Grade {{ $item->grade }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-right font-mono font-bold text-slate-900">
                                {{ number_format((float) $item->quantity, 2) }}
                                <span class="text-[10px] font-medium text-slate-400">{{ $item->product?->unit ?? 'kg' }}</span>
                            </td>
                            <td class="px-3 py-3 text-right font-mono text-slate-700">
                                ₹{{ number_format((float) $item->unit_price, 2) }}
                            </td>
                            <td class="px-3 py-3 text-right font-mono font-black text-slate-950">
                                ₹{{ number_format((float) $item->subtotal, 2) }}
                            </td>
                            <td class="py-3 pl-3 pr-5 text-right text-[11px] font-mono text-slate-400 space-y-0.5">
                                @if($item->stock_movement_id)
                                    <div>Stock Mov #{{ $item->stock_movement_id }}</div>
                                @endif
                                @if($item->wastage_entry_id)
                                    <div>Wastage #{{ $item->wastage_entry_id }}</div>
                                @endif
                                @if(!$item->stock_movement_id && !$item->wastage_entry_id)
                                    <span class="text-slate-300">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-xs text-slate-400 italic">No line items recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-slate-200 bg-slate-50 font-black text-slate-900">
                        <td colspan="2" class="py-3 pl-5 pr-3 text-xs uppercase tracking-wider text-slate-600">Total</td>
                        <td class="px-3 py-3 text-right font-mono text-xs">
                            {{ number_format((float) $creditNote->items->sum('quantity'), 2) }}
                        </td>
                        <td class="px-3 py-3 text-right text-slate-400">&mdash;</td>
                        <td class="px-3 py-3 text-right font-mono text-sm text-emerald-800">
                            ₹{{ number_format((float) $creditNote->total_amount, 2) }}
                        </td>
                        <td class="py-3 pl-3 pr-5"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- 2b. V2 Credit Allocation History --}}
    @if($creditNote->isV2() && $creditNote->money_effect->value === 'vendor_credit')
        <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-2xs">
            <div class="border-b border-slate-200 bg-slate-50/70 px-5 py-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="git-branch" class="h-4 w-4 text-emerald-600"></i>
                    <h2 class="text-sm font-black text-slate-900">Settlement Allocation History</h2>
                </div>
                <span class="text-xs font-bold text-slate-500">
                    Utilized: ₹{{ number_format((float) $creditNote->utilized_amount, 2) }} / ₹{{ number_format((float) $creditNote->total_amount, 2) }}
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500">
                            <th class="py-3 pl-5 pr-3">Settlement Date</th>
                            <th class="px-3 py-3">Settlement Ref</th>
                            <th class="px-3 py-3">Invoice</th>
                            <th class="px-3 py-3 text-right">Amount Applied (₹)</th>
                            <th class="py-3 pl-3 pr-5 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($creditNote->creditAllocations as $alloc)
                            <tr class="{{ $alloc->is_reversed ? 'bg-rose-50/40 text-slate-400' : 'hover:bg-slate-50/50' }}">
                                <td class="py-3 pl-5 pr-3 font-mono font-semibold">{{ $alloc->settlement?->payment_date?->format('d M Y') ?? $alloc->created_at?->format('d M Y') }}</td>
                                <td class="px-3 py-3 font-mono font-bold">{{ $alloc->settlement?->reference ?: ('SETTLE-'.$alloc->vendor_settlement_id) }}</td>
                                <td class="px-3 py-3 font-mono font-bold text-indigo-700">{{ $alloc->purchaseInvoice?->invoice_number ?? '#'.$alloc->purchase_invoice_id }}</td>
                                <td class="px-3 py-3 text-right font-mono font-bold {{ $alloc->is_reversed ? 'line-through' : 'text-emerald-700' }}">₹{{ number_format((float) $alloc->amount, 2) }}</td>
                                <td class="py-3 pl-3 pr-5 text-right">
                                    @if($alloc->is_reversed)
                                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold uppercase text-rose-700">Reversed</span>
                                    @else
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700">Applied</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-xs text-slate-400 italic">Not yet allocated to any vendor settlement. Full ₹{{ number_format($creditNote->available_credit, 2) }} is available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- 3. Double-Entry Accounting Journal Breakdown & Audit Details --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Journal Entry Transactions (2 Cols) --}}
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-2xs">
            <div class="border-b border-slate-200 bg-slate-50/70 px-5 py-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="scale" class="h-4 w-4 text-emerald-600"></i>
                    <h2 class="text-sm font-black text-slate-900">Double-Entry Accounting Journal</h2>
                </div>
                @if($creditNote->journalEntry)
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs font-bold text-slate-600">{{ $creditNote->journalEntry->entry_number }}</span>
                        @if($creditNote->journalEntry->is_balanced)
                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 uppercase">Balanced</span>
                        @else
                            <span class="rounded bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-800 uppercase">Unbalanced</span>
                        @endif
                    </div>
                @endif
            </div>

            @if($creditNote->journalEntry && $creditNote->journalEntry->transactions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-500">
                                <th class="py-3 pl-5 pr-3">Account Code & Name</th>
                                <th class="px-3 py-3 text-right">Debit (₹)</th>
                                <th class="px-3 py-3 text-right">Credit (₹)</th>
                                <th class="py-3 pl-3 pr-5">Memo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            @foreach($creditNote->journalEntry->transactions as $txn)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-3 pl-5 pr-3">
                                        <div class="font-mono font-bold text-slate-900">{{ $txn->account?->code ?? $txn->account_id }}</div>
                                        <div class="text-[11px] text-slate-500">{{ $txn->account?->name ?? 'Account' }}</div>
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono font-bold {{ $txn->debit > 0 ? 'text-slate-900' : 'text-slate-300' }}">
                                        {{ $txn->debit > 0 ? number_format((float) $txn->debit, 2) : '-' }}
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono font-bold {{ $txn->credit > 0 ? 'text-slate-900' : 'text-slate-300' }}">
                                        {{ $txn->credit > 0 ? number_format((float) $txn->credit, 2) : '-' }}
                                    </td>
                                    <td class="py-3 pl-3 pr-5 text-[11px] text-slate-500">
                                        {{ $txn->memo ?? $txn->description ?? '&mdash;' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-slate-200 bg-slate-50 font-black text-slate-900">
                                <td class="py-3 pl-5 pr-3 text-xs uppercase tracking-wider text-slate-600">Total</td>
                                <td class="px-3 py-3 text-right font-mono text-xs text-slate-900">
                                    ₹{{ number_format((float) $creditNote->journalEntry->transactions->sum('debit'), 2) }}
                                </td>
                                <td class="px-3 py-3 text-right font-mono text-xs text-slate-900">
                                    ₹{{ number_format((float) $creditNote->journalEntry->transactions->sum('credit'), 2) }}
                                </td>
                                <td class="py-3 pl-3 pr-5"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="p-6 text-center text-xs text-slate-400 italic">
                    No journal entry transactions attached to this credit note.
                </div>
            @endif
        </div>

        {{-- Audit & Operational Details (1 Col) --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-2xs space-y-4">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-500 border-b border-slate-100 pb-2">
                Audit Trail & References
            </h3>

            <div class="space-y-3 text-xs">
                <div>
                    <span class="block text-[11px] font-bold text-slate-400">Created By</span>
                    <span class="font-bold text-slate-800">{{ $creditNote->createdBy?->name ?? 'System' }}</span>
                    <div class="text-[10px] font-mono text-slate-400">{{ optional($creditNote->created_at)->format('d M Y, h:i A') }}</div>
                </div>

                <div>
                    <span class="block text-[11px] font-bold text-slate-400">Vendor / Supplier</span>
                    <span class="font-bold text-slate-800">{{ $supplier->name }}</span>
                    <div class="text-[10px] text-slate-500">Code: {{ $supplier->code ?? '#' . $supplier->id }}</div>
                </div>

                @if($creditNote->purchaseInvoice)
                    <div>
                        <span class="block text-[11px] font-bold text-slate-400">Linked Purchase Bill</span>
                        <span class="font-mono font-bold text-indigo-700">{{ $creditNote->purchaseInvoice->invoice_number }}</span>
                        <div class="text-[10px] text-slate-500">Bill Gross: ₹{{ number_format((float) $creditNote->purchaseInvoice->amount, 2) }}</div>
                    </div>
                @endif

                @if($creditNote->companyAccount)
                    <div>
                        <span class="block text-[11px] font-bold text-slate-400">Company Account</span>
                        <span class="font-bold text-slate-800">{{ $creditNote->companyAccount->account_name }}</span>
                        <div class="text-[10px] text-slate-500">Type: {{ $creditNote->companyAccount->account_type?->name ?? 'Account' }}</div>
                    </div>
                @endif

                @if($creditNote->warehouse)
                    <div>
                        <span class="block text-[11px] font-bold text-slate-400">Inventory Warehouse</span>
                        <span class="font-bold text-slate-800">{{ $creditNote->warehouse->name }}</span>
                    </div>
                @endif

                @if($creditNote->notes)
                    <div>
                        <span class="block text-[11px] font-bold text-slate-400">Notes / Remarks</span>
                        <p class="text-xs text-slate-700 bg-slate-50 p-2.5 rounded-lg border border-slate-100 font-medium">
                            {{ $creditNote->notes }}
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Void Confirmation Modal --}}
    @if($creditNote->isActive() && auth()->user()->can('cashbook.reconciliation.manage'))
        <div x-show="showVoidModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-xs"
             @keydown.escape.window="showVoidModal = false">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl space-y-4"
                 @click.away="showVoidModal = false">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
                        <i data-lucide="alert-triangle" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900">Void Purchase Credit Note</h3>
                        <p class="text-xs text-slate-500">{{ $creditNote->credit_note_number }}</p>
                    </div>
                </div>

                <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-3 text-xs text-rose-800 space-y-1 font-medium">
                    <p class="font-bold">Warning: This action is irreversible.</p>
                    <p>Voiding will automatically:</p>
                    <ul class="list-inside list-disc space-y-0.5 text-[11px]">
                        <li>Reverse credited amount from vendor pending debt</li>
                        <li>Reverse unapplied advance or cash refund entry</li>
                        <li>Create compensating stock movements for returned goods</li>
                        <li>Reverse or cancel any damage/wastage entries</li>
                        <li>Post reversing accounting journal entries</li>
                    </ul>
                </div>

                <form method="POST"
                      action="{{ route('admin.cashbook.finance.purchase.vendors.credit-notes.void', ['supplier' => $supplier->public_uuid, 'creditNote' => $creditNote->public_uuid]) }}"
                      @submit="isVoiding = true">
                    @csrf
                    <div>
                        <label class="block text-xs font-black text-slate-700">Reason for Voiding <span class="text-rose-500">*</span></label>
                        <textarea name="reason" x-model="voidReason" required minlength="3" rows="3"
                                  placeholder="Explain why this credit note is being voided..."
                                  class="mt-1.5 w-full rounded-lg border border-slate-300 p-2.5 text-xs text-slate-900 focus:border-rose-600 focus:ring-1 focus:ring-rose-600"></textarea>
                    </div>

                    <div class="mt-5 flex items-center justify-end gap-3">
                        <button type="button" @click="showVoidModal = false" :disabled="isVoiding"
                                class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" :disabled="isVoiding || voidReason.trim().length < 3"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-4 py-2 text-xs font-black text-white hover:bg-rose-700 disabled:opacity-50">
                            <i data-lucide="ban" class="h-4 w-4"></i>
                            <span x-text="isVoiding ? 'Voiding...' : 'Confirm Void'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
