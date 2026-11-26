<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
        <thead class="bg-slate-50/80 text-[11px] font-bold uppercase tracking-wider text-slate-500">
            <tr>
                <th class="px-4 py-3">Credit Note Number</th>
                <th class="px-4 py-3">Business Date</th>
                <th class="px-4 py-3">Vendor</th>
                <th class="px-4 py-3">Purchaser</th>
                <th class="px-4 py-3 text-center">Products</th>
                <th class="px-4 py-3">Money Effect</th>
                <th class="px-4 py-3 text-right">Amount</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3 text-right">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
            @forelse ($creditNotes as $note)
                @php
                    $purchaserName = $note->purchaseInvoice?->purchaserCart?->user?->name;
                @endphp
                <tr class="transition hover:bg-slate-50/70">
                    <td class="px-4 py-3 font-mono font-bold text-slate-900">
                        @if ($note->supplier)
                            <a href="{{ route('admin.cashbook.finance.purchase.vendors.credit-notes.show', ['supplier' => $note->supplier->public_uuid, 'creditNote' => $note->public_uuid]) }}"
                               class="hover:text-emerald-700 hover:underline">
                                {{ $note->credit_note_number }}
                            </a>
                        @else
                            {{ $note->credit_note_number }}
                        @endif
                    </td>
                    <td class="px-4 py-3 font-medium text-slate-700">
                        {{ $note->credit_date?->format('d M Y') ?? '—' }}
                    </td>
                    <td class="px-4 py-3 font-semibold text-slate-800">
                        {{ $note->supplier?->name ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-slate-600">
                        @if ($purchaserName)
                            <span class="font-semibold text-slate-800">{{ $purchaserName }}</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-500">Unassigned</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-slate-100 px-2 text-[11px] font-bold text-slate-700">
                            {{ $note->items_count }}
                        </span>
                    </td>
                    <td class="px-4 py-3 font-semibold text-slate-700">
                        {{ $note->money_effect?->label() ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                        ₹{{ number_format((float) $note->total_amount, 2) }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider {{ $note->isActive() ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                            {{ $note->status->label() }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if ($note->supplier)
                            <a href="{{ route('admin.cashbook.finance.purchase.vendors.credit-notes.show', ['supplier' => $note->supplier->public_uuid, 'creditNote' => $note->public_uuid]) }}"
                               class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-[11px] font-bold text-slate-700 hover:bg-slate-50">
                                <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                <span>View</span>
                            </a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center">
                        <div class="mx-auto flex max-w-sm flex-col items-center gap-2 text-slate-500">
                            <i data-lucide="inbox" class="h-8 w-8 text-slate-300"></i>
                            <p class="text-sm font-bold text-slate-700">No purchase credit notes found</p>
                            <p class="text-xs text-slate-500">Try adjusting your filters or create a new purchase credit note.</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($creditNotes->hasPages())
    <div class="border-t border-slate-200 bg-slate-50/50 px-4 py-3" data-pcn-pagination>
        {{ $creditNotes->links() }}
    </div>
@endif
