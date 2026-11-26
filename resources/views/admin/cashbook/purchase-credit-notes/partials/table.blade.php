<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
        <thead class="bg-slate-50/80 text-[11px] font-bold uppercase tracking-wider text-slate-500">
            <tr>
                <th class="px-4 py-3">Credit Note #</th>
                <th class="px-4 py-3">Business Date</th>
                <th class="px-4 py-3">Vendor</th>
                <th class="px-4 py-3">Invoice Ref</th>
                <th class="px-4 py-3">Money Effect</th>
                <th class="px-4 py-3 text-right">Original Credit</th>
                <th class="px-4 py-3 text-right">Utilized</th>
                <th class="px-4 py-3 text-right">Available</th>
                <th class="px-4 py-3">Utilization</th>
                <th class="px-4 py-3 text-right">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
            @forelse ($creditNotes as $note)
                @php
                    $isVendorCredit = $note->money_effect === \App\Enums\Purchasing\PurchaseCreditMoneyEffect::VendorCredit;
                    $availableAmt = $isVendorCredit ? $note->available_credit : 0.0;
                    $utilizedAmt = $isVendorCredit && $note->isActive()
                        ? round(max(0.0, (float) $note->total_amount - $availableAmt), 2)
                        : 0.0;
                    $uStatus = $note->utilization_status;
                    $uBadge = match ($uStatus) {
                        'open' => 'bg-emerald-100 text-emerald-800',
                        'partially_utilized' => 'bg-amber-100 text-amber-800',
                        'fully_utilized' => 'bg-slate-100 text-slate-700',
                        'voided' => 'bg-rose-100 text-rose-800',
                        default => 'bg-blue-100 text-blue-800',
                    };
                    $uLabel = match ($uStatus) {
                        'open' => 'Open',
                        'partially_utilized' => 'Partially Utilized',
                        'fully_utilized' => 'Fully Utilized',
                        'voided' => 'Voided',
                        default => 'Settled',
                    };
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
                        @if ($note->supplier)
                            <a href="{{ route('admin.cashbook.finance.purchase.vendors.show', ['supplier' => $note->supplier->public_uuid]) }}"
                               class="hover:text-emerald-700 hover:underline">
                                {{ $note->supplier->name }}
                            </a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 font-mono text-[11px] text-indigo-700">
                        @if ($note->purchaseInvoice)
                            <span>{{ $note->purchaseInvoice->invoice_number }}</span>
                        @else
                            <span class="text-slate-400 font-sans">Standalone</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 font-semibold text-slate-700">
                        {{ $note->money_effect?->label() ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-right font-mono font-bold text-slate-900">
                        ₹{{ number_format((float) $note->total_amount, 2) }}
                    </td>
                    <td class="px-4 py-3 text-right font-mono font-semibold text-indigo-700">
                        {{ $isVendorCredit ? '₹'.number_format($utilizedAmt, 2) : '—' }}
                    </td>
                    <td class="px-4 py-3 text-right font-mono font-bold {{ $availableAmt > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                        {{ $isVendorCredit ? '₹'.number_format($availableAmt, 2) : '—' }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $uBadge }}">
                            {{ $uLabel }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            @if ($note->supplier && $availableAmt > 0.009)
                                <a href="{{ route('admin.cashbook.finance.vendor-credit.show', ['supplier' => $note->supplier->public_uuid]) }}"
                                   class="inline-flex h-8 items-center gap-1 rounded-lg bg-emerald-600 px-2.5 text-[11px] font-bold text-white hover:bg-emerald-700">
                                    <i data-lucide="credit-card" class="h-3.5 w-3.5"></i>
                                    <span>Settle Vendor</span>
                                </a>
                            @endif
                            @if ($note->supplier)
                                <a href="{{ route('admin.cashbook.finance.purchase.vendors.credit-notes.show', ['supplier' => $note->supplier->public_uuid, 'creditNote' => $note->public_uuid]) }}"
                                   class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-[11px] font-bold text-slate-700 hover:bg-slate-50">
                                    <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                    <span>View</span>
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="px-6 py-12 text-center">
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
