<section data-order-panel="history" class="hidden">
    <div class="purchase-manager-panel overflow-hidden">
        @if ($approvalHistory->isEmpty())
            <div class="p-5">
                <x-purchase-manager.components.empty-state
                    title="No approval decisions yet"
                    description="Approved and rejected purchase orders will appear here after a draft order is reviewed."
                />
            </div>
        @else
            <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
                <table class="min-w-[720px] text-left">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Purchase Order</th>
                            <th class="px-5 py-4">Action</th>
                            <th class="px-5 py-4">By</th>
                            <th class="px-5 py-4">Recorded At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @foreach ($approvalHistory as $history)
                            <tr>
                                <td class="px-5 py-4 font-mono font-bold text-cyan-700">
                                    @if ($history->subject)
                                        <a href="{{ route('purchasing.orders.show', $history->subject) }}">{{ $history->subject->po_number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <x-purchase-manager.components.status-badge :label="$history->description" :tone="$history->description === 'Approved' ? 'emerald' : 'rose'" />
                                </td>
                                <td class="px-5 py-4 text-slate-700">{{ $history->causer?->name ?? 'System' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $history->created_at?->format('d M Y, h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
