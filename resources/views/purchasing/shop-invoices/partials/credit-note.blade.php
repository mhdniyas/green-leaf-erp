@php
    $creditNote = $invoice->creditNoteSummary();
    $rows = $creditNote['rows'];
    $originalInvoiceTotal = $creditNote['original_invoice_total'];
    $revisedInvoiceTotal = $creditNote['revised_invoice_total'];
    $totalCreditNote = $creditNote['total_credit_note'];
    $formatUnit = fn (?string $unit): string => \App\Models\ProductUnit::normalizeUnit($unit) === 'piece'
        ? 'PCE'
        : strtoupper(str_replace('_', ' ', \App\Models\ProductUnit::normalizeUnit($unit)));
    $money = fn (float|int|string|null $amount): string => '₹'.number_format((float) $amount, 2);
    $formatQty = fn (float|int $qty): string => rtrim(rtrim(number_format($qty, 4), '0'), '.');
@endphp

<section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div>
            <h3 class="text-sm font-black uppercase tracking-[0.16em] text-slate-950">Credit Note</h3>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Read-only credit note for quantity reductions recorded in invoice edit history.</p>
        </div>
        <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700">
            Read Only
        </span>
    </div>

    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">
                <tr>
                    <th class="px-3 py-2">Product</th>
                    <th class="px-3 py-2 text-right">Previous Qty</th>
                    <th class="px-3 py-2 text-right">Final Qty</th>
                    <th class="px-3 py-2 text-right">Credit Qty</th>
                    <th class="px-3 py-2 text-right">Rate</th>
                    <th class="px-3 py-2 text-right">Credit Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-semibold text-slate-800">
                @forelse ($rows as $row)
                    <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2 font-black text-slate-950">
                            {{ $row['product_name'] }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            {{ $formatQty($row['previous_qty']) }} {{ $formatUnit($row['unit']) }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            {{ $formatQty($row['final_qty']) }} {{ $formatUnit($row['unit']) }}
                        </td>
                        <td class="px-3 py-2 text-right font-black text-slate-950">
                            {{ $formatQty($row['credit_qty']) }} {{ $formatUnit($row['unit']) }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            {{ $money($row['rate']) }}
                        </td>
                        <td class="px-3 py-2 text-right font-black text-slate-950">
                            {{ $money($row['credit_amount']) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-4 text-center text-xs font-semibold text-slate-500">
                            No credit note adjustments for this invoice.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 border-t border-slate-200 pt-3">
        <div class="ml-auto max-w-xs space-y-1.5 text-xs font-bold text-slate-700">
            <div class="flex items-center justify-between">
                <span>Original Invoice Total</span>
                <span class="font-black text-slate-950">{{ $money($originalInvoiceTotal) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span>Revised Invoice Total</span>
                <span class="font-black text-slate-950">{{ $money($revisedInvoiceTotal) }}</span>
            </div>
            <div class="flex items-center justify-between border-t border-slate-200 pt-1.5 text-sm font-black text-emerald-600">
                <span>Credit Note Amount</span>
                <span>{{ $money($totalCreditNote) }}</span>
            </div>
        </div>
    </div>
</section>
