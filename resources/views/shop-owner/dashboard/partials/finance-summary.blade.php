<section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xs sm:p-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-[9px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Bills and Payments</p>
            <h2 class="mt-0.5 text-base font-black text-slate-950 sm:text-lg">{{ $isOwnedAccountingShop ? 'Owned Shop Money Flow' : 'Shop Bill Collection' }}</h2>
        </div>
        <div class="flex shrink-0 gap-1.5">
            <a href="{{ route('shop-owner.finance.index', ['tab' => 'invoices', 'date' => $businessDate->toDateString()]) }}" class="inline-flex h-7 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 text-[10px] font-bold text-slate-800 hover:bg-slate-50 transition-colors">Bills</a>
            <a href="{{ route('shop-owner.payments.index') }}" class="inline-flex h-7 items-center justify-center rounded-lg bg-slate-950 px-2.5 text-[10px] font-bold text-white hover:bg-slate-800 transition-colors">Payments</a>
        </div>
    </div>

    <div class="mt-3 grid grid-cols-3 gap-2">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5 shadow-xs">
            <p class="truncate text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">Today Bill</p>
            <p class="mt-1 truncate text-xs font-black text-slate-950 sm:text-lg" title="Rs. {{ number_format((float) $financeSummary['today_bill_total'], 2) }}">Rs. {{ number_format((float) $financeSummary['today_bill_total'], 2) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5 shadow-xs">
            <p class="truncate text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">{{ $isOwnedAccountingShop ? 'Debit' : 'Outstanding' }}</p>
            <p class="mt-1 truncate text-xs font-black {{ $isOwnedAccountingShop ? 'text-rose-700' : 'text-red-600' }} sm:text-lg" title="Rs. {{ number_format((float) ($isOwnedAccountingShop ? $financeSummary['today_approved_bill_debit'] : $financeSummary['outstanding_balance']), 2) }}">Rs. {{ number_format((float) ($isOwnedAccountingShop ? $financeSummary['today_approved_bill_debit'] : $financeSummary['outstanding_balance']), 2) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5 shadow-xs">
            <p class="truncate text-[8px] font-black uppercase tracking-wider text-slate-500 sm:text-[10px]">{{ $isOwnedAccountingShop ? 'Closing' : 'Paid' }}</p>
            <p class="mt-1 truncate text-xs font-black text-emerald-700 sm:text-lg" title="Rs. {{ number_format((float) ($isOwnedAccountingShop ? $financeSummary['today_closing_balance'] : $financeSummary['paid_amount']), 2) }}">Rs. {{ number_format((float) ($isOwnedAccountingShop ? $financeSummary['today_closing_balance'] : $financeSummary['paid_amount']), 2) }}</p>
        </div>
    </div>

    @if ($recentInvoices->isNotEmpty())
        {{-- Single-row list --}}
        <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full border-collapse text-left text-xs whitespace-nowrap">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500">
                        <th class="px-3 py-2">Invoice</th>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2 text-right">{{ $isOwnedAccountingShop ? 'Bill Debit' : 'Balance' }}</th>
                        <th class="px-3 py-2 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @foreach ($recentInvoices->take(4) as $invoice)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-3 py-1.5 font-bold">
                                <a href="{{ route('shop-owner.finance.show', $invoice) }}" class="text-emerald-700 hover:text-emerald-900 hover:underline">
                                    {{ $invoice->invoice_number }}
                                </a>
                            </td>
                            <td class="px-3 py-1.5 font-semibold text-slate-500">{{ $invoice->business_date?->format('d M Y') }}</td>
                            <td class="px-3 py-1.5 text-right font-black text-slate-950">Rs. {{ number_format((float) ($isOwnedAccountingShop ? $invoice->final_total : $invoice->balance_amount), 2) }}</td>
                            <td class="px-3 py-1.5 text-right">
                                <a href="{{ route('shop-owner.finance.show', $invoice) }}" class="inline-flex items-center text-xs font-bold text-emerald-700 hover:text-emerald-900">
                                    Open &rarr;
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="mt-3 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-4 text-center">
            <p class="text-xs font-bold text-slate-500">No delivery bills available yet.</p>
        </div>
    @endif
</section>
