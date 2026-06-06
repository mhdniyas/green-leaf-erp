@extends('purchase-manager.layouts.app')

@section('title', 'Create Purchase Invoice')
@section('page_title', 'Create Purchase Invoice')
@section('page_description', 'Match the supplier invoice to a verified goods receipt and start the payment workflow.')

@section('content')
    @php
        $expectedAmount = 0.00;
        foreach ($grn->items as $item) {
            $expectedAmount += (float) $item->received_qty * (float) ($item->purchaseOrderItem?->unit_price ?? 0);
        }
    @endphp

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <form method="POST" action="{{ route('purchasing.invoices.store') }}" class="purchase-manager-panel overflow-hidden">
            @csrf
            <input type="hidden" name="goods_received_id" value="{{ $grn->id }}">
            <input type="hidden" name="supplier_id" value="{{ $grn->purchaseOrder->supplier_id }}">

            <div class="border-b border-slate-200 px-5 py-5">
                <h2 class="text-lg font-black text-slate-950">Invoice Details</h2>
            </div>

            <div class="space-y-6 px-5 py-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="invoice_number" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Invoice Number</label>
                        <input id="invoice_number" type="text" name="invoice_number" value="{{ old('invoice_number', $suggestedInvoiceNumber) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                        <p class="mt-2 text-xs font-semibold text-slate-500">Generated with timestamp. You can replace it with the supplier's final invoice reference if needed.</p>
                    </div>
                    <div>
                        <label for="amount" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Invoice Amount</label>
                        <input id="amount" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', number_format($expectedAmount, 2, '.', '')) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                        <p class="mt-2 text-xs font-semibold text-slate-500">Expected material cost: INR {{ number_format($expectedAmount, 2) }}</p>
                    </div>
                </div>
                <div>
                    <label for="status" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Status</label>
                    <select id="status" name="status" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                        <option value="pending" @selected(old('status') === 'pending')>Pending Approval</option>
                        <option value="approved" @selected(old('status') === 'approved')>Approved for Payment</option>
                        <option value="paid" @selected(old('status') === 'paid')>Paid</option>
                    </select>
                </div>
                <div>
                    <label for="notes" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Notes</label>
                    <textarea id="notes" name="notes" rows="3" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">{{ old('notes') }}</textarea>
                </div>
                <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-6">
                    <x-purchase-manager.components.action-button type="submit" variant="primary">Create Invoice</x-purchase-manager.components.action-button>
                    <x-purchase-manager.components.action-button :href="route('purchasing.grns.show', $grn)" variant="secondary">Cancel</x-purchase-manager.components.action-button>
                </div>
            </div>
        </form>

        <aside class="space-y-5">
            <div class="purchase-manager-panel p-5">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Matched GRN</p>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between"><span class="text-slate-500">GRN Number</span><span class="font-mono font-bold text-slate-950">{{ $grn->grn_number }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">PO Number</span><a href="{{ route('purchasing.orders.show', $grn->purchaseOrder) }}" class="font-mono font-bold text-cyan-700">{{ $grn->purchaseOrder->po_number }}</a></div>
                    <div class="flex items-center justify-between"><span class="text-slate-500">Supplier</span><span class="font-semibold text-slate-950">{{ $grn->purchaseOrder->supplier->name }}</span></div>
                </div>
            </div>
        </aside>
    </div>
@endsection
