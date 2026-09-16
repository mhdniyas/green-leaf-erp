@php
    $summary = $invoice->creditNoteSummary();
    $rows = $summary['rows'];
    $previousInvoiceTotal = $summary['previous_invoice_total'];
    $revisedInvoiceTotal = $summary['revised_invoice_total'];
    $netDifference = $summary['net_difference'];
    $isVerified = $summary['is_verified'];
    $verifiedByName = $summary['verified_by_name'];
    $verifiedAt = $summary['verified_at'];

    $formatUnit = fn (?string $unit): string => \App\Models\ProductUnit::normalizeUnit($unit) === 'piece'
        ? 'PCE'
        : strtoupper(str_replace('_', ' ', \App\Models\ProductUnit::normalizeUnit($unit)));
    $money = fn (float|int|string|null $amount): string => ((float) $amount < 0 ? '-₹' : '₹').number_format(abs((float) $amount), 2);
    $formatQty = fn (float|int $qty): string => rtrim(rtrim(number_format($qty, 4), '0'), '.');
@endphp

<section class="space-y-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div>
            <h3 class="text-sm font-black uppercase tracking-[0.16em] text-slate-950">Credit Note / Invoice Changes</h3>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Actual invoice edits made before company finalization.</p>
        </div>
        <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] text-slate-700">
            Read Only
        </span>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase tracking-[0.12em] text-slate-500">
                <tr>
                    <th class="px-3 py-2">Product</th>
                    <th class="px-3 py-2 text-right">Previous Qty</th>
                    <th class="px-3 py-2 text-right">Revised Qty</th>
                    <th class="px-3 py-2 text-right">Rate</th>
                    <th class="px-3 py-2 text-right">Previous Amount</th>
                    <th class="px-3 py-2 text-right">Revised Amount</th>
                    <th class="px-3 py-2 text-right">Difference</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-semibold text-slate-800">
                @forelse ($rows as $row)
                    @php
                        $diff = $row['difference'];
                        $diffDisplay = ($diff > 0 ? '+' : '').$money($diff);
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-3 py-2 font-black text-slate-950">
                            {{ $row['product_name'] }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            {{ $formatQty($row['previous_qty']) }} {{ $formatUnit($row['unit']) }}
                        </td>
                        <td class="px-3 py-2 text-right font-black text-slate-950">
                            {{ $formatQty($row['revised_qty']) }} {{ $formatUnit($row['unit']) }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            {{ $money($row['rate']) }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            {{ $money($row['previous_amount']) }}
                        </td>
                        <td class="px-3 py-2 text-right font-black text-slate-950">
                            {{ $money($row['revised_amount']) }}
                        </td>
                        <td class="px-3 py-2 text-right font-black {{ $diff > 0 ? 'text-emerald-700' : ($diff < 0 ? 'text-rose-700' : 'text-slate-900') }}">
                            {{ $diffDisplay }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-4 text-center text-xs font-semibold text-slate-500">
                            No invoice edits recorded for this bill.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="border-t border-slate-100 pt-3">
        <div class="ml-auto max-w-xs space-y-1.5 text-xs font-bold text-slate-700">
            <div class="flex items-center justify-between">
                <span>Previous Invoice Total</span>
                <span class="font-black text-slate-950">{{ $money($previousInvoiceTotal) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span>Revised Invoice Total</span>
                <span class="font-black text-slate-950">{{ $money($revisedInvoiceTotal) }}</span>
            </div>
            <div class="flex items-center justify-between border-t border-slate-200 pt-1.5 text-sm font-black {{ $netDifference > 0 ? 'text-emerald-700' : ($netDifference < 0 ? 'text-rose-700' : 'text-slate-950') }}">
                <span>Net Difference</span>
                <span>{{ ($netDifference > 0 ? '+' : '').$money($netDifference) }}</span>
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 sm:p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black uppercase tracking-[0.14em] text-slate-700">Shop Change Verification:</span>
                    @if ($isVerified)
                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-800">
                            Verified
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-amber-800">
                            Pending
                        </span>
                    @endif
                </div>
                @if ($isVerified)
                    <div class="mt-1 text-xs font-semibold text-slate-600 space-y-0.5">
                        <p>Verified By: <span class="font-bold text-slate-900">{{ $verifiedByName ?? 'Shop Manager' }}</span></p>
                        @if ($verifiedAt)
                            <p>Verified At: <span class="font-bold text-slate-900">{{ $verifiedAt->format('d M Y, h:i A') }}</span></p>
                        @endif
                    </div>
                @else
                    <p class="mt-1 text-xs font-semibold text-slate-500">
                        Shop Manager must review and verify all invoice changes before company finalization.
                    </p>
                @endif
            </div>

            @if (! $isVerified && auth()->user()?->hasRole('shop') && ($invoice->order?->order_number ?? null))
                <form action="{{ route('shop-owner.deliveries.verify-changes', $invoice->order->order_number) }}" method="POST" class="shrink-0">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 text-xs font-black uppercase tracking-[0.12em] transition-all shadow-md active:scale-95 cursor-pointer border-none"
                    >
                        Verify Invoice Changes
                    </button>
                </form>
            @endif
        </div>
    </div>
</section>
