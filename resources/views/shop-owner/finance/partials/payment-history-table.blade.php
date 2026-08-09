@if ($invoices->isNotEmpty())
    {{-- Mobile View: Compact Single-Row Style Cards --}}
    <div class="space-y-2 md:hidden">
        @foreach ($invoices as $invoice)
            @php
                $finalTotal = (float) $invoice->final_total;
                $balanceAmt = (float) $invoice->balance_amount;
            @endphp
            <article class="rounded-xl border border-slate-200 bg-white p-2.5 shadow-xs transition hover:border-slate-300">
                <div class="flex items-center justify-between gap-2">
                    {{-- Date & Invoice Ref --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-black text-slate-900">{{ $invoice->business_date->format('d M Y') }}</span>
                            <span class="truncate font-mono text-[11px] font-bold text-slate-500">{{ $invoice->invoice_number }}</span>
                        </div>
                        <div class="mt-1 flex items-center gap-2 text-[11px]">
                            @include('shop-owner.finance.partials.payment-status-badge', ['invoice' => $invoice])
                            @if ($balanceAmt > 0)
                                <span class="font-bold text-red-600">Bal: Rs. {{ number_format($balanceAmt, 2) }}</span>
                            @else
                                <span class="font-bold text-emerald-700">Paid</span>
                            @endif
                        </div>
                    </div>

                    {{-- Invoice Total & Actions --}}
                    <div class="flex items-center gap-2 text-right">
                        <div>
                            <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-400">Total</span>
                            <span class="text-xs font-black text-slate-950">Rs. {{ number_format($finalTotal, 2) }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('shop-owner.finance.pdf', $invoice) }}" target="_blank"
                               class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-black uppercase text-slate-600 hover:bg-slate-100">
                                PDF
                            </a>
                            <a href="{{ route('shop-owner.finance.show', $invoice) }}"
                               class="inline-flex items-center justify-center rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 hover:bg-emerald-100 transition-colors">
                                Open
                            </a>
                        </div>
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    {{-- Desktop Table View --}}
    <div class="hidden overflow-x-auto md:block">
        <table class="min-w-full border-collapse text-left">
            <thead>
                <tr class="border-b border-slate-100 text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                    <th class="py-2.5 pr-3">Date</th>
                    <th class="py-2.5 pr-3">Invoice</th>
                    <th class="py-2.5 pr-3">Status</th>
                    <th class="py-2.5 pr-3 text-right">Final Amount</th>
                    <th class="py-2.5 pr-3 text-right">Balance</th>
                    <th class="py-2.5 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                @foreach ($invoices as $invoice)
                    @php
                        $finalTotal = (float) $invoice->final_total;
                        $balanceAmt = (float) $invoice->balance_amount;
                    @endphp
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="py-2.5 pr-3 font-bold text-slate-900">{{ $invoice->business_date->format('d M Y') }}</td>
                        <td class="py-2.5 pr-3 font-mono text-xs font-bold text-slate-600">{{ $invoice->invoice_number }}</td>
                        <td class="py-2.5 pr-3">@include('shop-owner.finance.partials.payment-status-badge', ['invoice' => $invoice])</td>
                        <td class="py-2.5 pr-3 text-right font-black text-slate-950">Rs. {{ number_format($finalTotal, 2) }}</td>
                        <td class="py-2.5 pr-3 text-right font-bold {{ $balanceAmt > 0 ? 'text-red-600' : 'text-emerald-700' }}">
                            Rs. {{ number_format($balanceAmt, 2) }}
                        </td>
                        <td class="py-2.5 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('shop-owner.finance.pdf', $invoice) }}" target="_blank"
                                   class="text-[10px] font-black uppercase tracking-wider text-slate-500 hover:text-slate-800">
                                    PDF
                                </a>
                                <a href="{{ route('shop-owner.finance.show', $invoice) }}"
                                   class="font-bold text-emerald-700 hover:text-emerald-900">
                                    Open &rarr;
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($invoices instanceof \Illuminate\Contracts\Pagination\Paginator)
        <div class="mt-3 border-t border-slate-100 pt-2">
            {{ $invoices->links() }}
        </div>
    @endif
@else
    @include('shop-owner.components.empty-state', ['title' => 'No finance activity', 'description' => 'Paid and delivered orders will appear here with balances and notes.'])
@endif
