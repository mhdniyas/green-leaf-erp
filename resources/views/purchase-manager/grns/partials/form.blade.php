@php
    $grnItems = $grn ? $grn->items->keyBy('purchase_order_item_id') : collect();
@endphp

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
    <form method="POST" action="{{ $formAction }}" class="purchase-manager-panel overflow-hidden">
        @csrf
        @if ($formMethod !== 'POST')
            @method($formMethod)
        @endif
        <input type="hidden" name="purchase_order_id" value="{{ $sourceOrder->id }}">

        <div class="border-b border-slate-200 px-5 py-5">
            <h2 class="text-lg font-black text-slate-950">{{ $grn ? 'Edit Warehouse Receipt' : 'Warehouse Receipt Entry' }}</h2>
        </div>

        <div class="space-y-6 px-5 py-5">
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label for="received_at" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Received Date</label>
                    <input id="received_at" type="date" name="received_at" value="{{ old('received_at', $grn?->received_at?->toDateString() ?? today()->toDateString()) }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                </div>
                <div>
                    <label for="transport_cost" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Transport Cost</label>
                    <input id="transport_cost" type="number" step="0.01" min="0" name="transport_cost" value="{{ old('transport_cost', $grn?->transport_cost ?? '0.00') }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                </div>
                <div>
                    <label for="labour_cost" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Labour Cost</label>
                    <input id="labour_cost" type="number" step="0.01" min="0" name="labour_cost" value="{{ old('labour_cost', $grn?->labour_cost ?? '0.00') }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label for="notes" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Receipt Notes</label>
                <textarea id="notes" name="notes" rows="3" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">{{ old('notes', $grn?->notes) }}</textarea>
            </div>

            <div class="space-y-4 border-t border-slate-200 pt-6">
                @foreach ($sourceOrder->items as $index => $item)
                    @php
                        $existingReceipt = $grnItems->get($item->id);
                        $receivedQty = old("items.{$index}.received_qty", $existingReceipt ? number_format((float) $existingReceipt->received_qty, 3, '.', '') : number_format((float) $item->quantity, 3, '.', ''));
                    @endphp
                    <div class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-4">
                        <input type="hidden" name="items[{{ $index }}][purchase_order_item_id]" value="{{ $item->id }}">
                        <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $item->product_id }}">
                        <div class="grid gap-4 md:grid-cols-[minmax(0,2fr)_140px_160px] md:items-end">
                            <div>
                                <p class="font-bold text-slate-900">{{ $item->product->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $item->product->sku }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Ordered Qty</p>
                                <p class="mt-2 text-lg font-black text-slate-950">{{ number_format((float) $item->quantity, 3) }} kg</p>
                            </div>
                            <div>
                                <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Received Qty</label>
                                <input type="number" step="0.001" min="0" required name="items[{{ $index }}][received_qty]" value="{{ $receivedQty }}" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-right text-sm font-semibold text-slate-900 focus:border-cyan-500 focus:outline-none">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-6">
                <x-purchase-manager.components.action-button type="submit" variant="primary">{{ $submitLabel }}</x-purchase-manager.components.action-button>
                <x-purchase-manager.components.action-button :href="$cancelHref" variant="secondary">Cancel</x-purchase-manager.components.action-button>
            </div>
        </div>
    </form>

    <aside class="space-y-5">
        <div class="purchase-manager-panel p-5">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Purchase Order</p>
            <div class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between"><span class="text-slate-500">PO Number</span><span class="font-mono font-bold text-slate-950">{{ $sourceOrder->po_number }}</span></div>
                <div class="flex items-center justify-between"><span class="text-slate-500">Supplier</span><span class="font-semibold text-slate-950">{{ $sourceOrder->supplier->name }}</span></div>
                <div class="flex items-center justify-between"><span class="text-slate-500">Status</span><span class="font-semibold text-slate-950">{{ $sourceOrder->status->label() }}</span></div>
            </div>
        </div>
        <div class="purchase-manager-panel p-5">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Receipt Guidance</p>
            <p class="mt-3 text-sm leading-6 text-slate-600">Transport and labour costs entered here are added to the receipt workflow so warehouse and finance teams can reconcile landed stock value later.</p>
        </div>
    </aside>
</div>
