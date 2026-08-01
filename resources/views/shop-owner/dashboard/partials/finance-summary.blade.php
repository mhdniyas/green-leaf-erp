<section class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:rounded-2xl">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-[9px] font-black uppercase tracking-[0.14em] text-slate-500 sm:text-[10px]">Bills and Payments</p>
            <h2 class="mt-0.5 text-base font-black text-slate-950 sm:text-lg">{{ $isOwnedAccountingShop ? 'Owned shop money flow' : 'Shop bill collection' }}</h2>
        </div>
        <div class="flex shrink-0 gap-1.5">
            <a href="{{ route('shop-owner.accounting.index', ['tab' => 'bills', 'date' => $businessDate->toDateString()]) }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 text-[10px] font-black text-slate-800 hover:bg-slate-50 sm:px-3 sm:text-xs">Bills</a>
            <a href="{{ route('shop-owner.payments.index') }}" class="inline-flex h-8 items-center justify-center rounded-lg bg-slate-950 px-2.5 text-[10px] font-black text-white hover:bg-slate-800 sm:px-3 sm:text-xs">Payments</a>
        </div>
    </div>

    <div class="mt-3 grid grid-cols-3 gap-2">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
            <p class="truncate text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[10px]">Today Bill</p>
            <p class="mt-1 truncate text-sm font-black text-slate-950 sm:text-xl" title="Rs. {{ number_format((float) $financeSummary['today_bill_total'], 2) }}">Rs. {{ number_format((float) $financeSummary['today_bill_total'], 2) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
            <p class="truncate text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[10px]">{{ $isOwnedAccountingShop ? 'Debit' : 'Outstanding' }}</p>
            <p class="mt-1 truncate text-sm font-black {{ $isOwnedAccountingShop ? 'text-rose-700' : 'text-red-600' }} sm:text-xl" title="Rs. {{ number_format((float) ($isOwnedAccountingShop ? $financeSummary['today_approved_bill_debit'] : $financeSummary['outstanding_balance']), 2) }}">Rs. {{ number_format((float) ($isOwnedAccountingShop ? $financeSummary['today_approved_bill_debit'] : $financeSummary['outstanding_balance']), 2) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
            <p class="truncate text-[8px] font-black uppercase tracking-[0.1em] text-slate-500 sm:text-[10px]">{{ $isOwnedAccountingShop ? 'Closing' : 'Paid' }}</p>
            <p class="mt-1 truncate text-sm font-black text-emerald-700 sm:text-xl" title="Rs. {{ number_format((float) ($isOwnedAccountingShop ? $financeSummary['today_closing_balance'] : $financeSummary['paid_amount']), 2) }}">Rs. {{ number_format((float) ($isOwnedAccountingShop ? $financeSummary['today_closing_balance'] : $financeSummary['paid_amount']), 2) }}</p>
        </div>
    </div>

    @if ($recentInvoices->isNotEmpty())
        <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full border-collapse text-left text-xs sm:text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50 text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">
                        <th class="px-3 py-2">Invoice</th>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2 text-right">{{ $isOwnedAccountingShop ? 'Bill Debit' : 'Balance' }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @foreach ($recentInvoices->take(4) as $invoice)
                        <tr>
                            <td class="px-3 py-2">
                                <a href="{{ route('shop-owner.finance.show', $invoice) }}" class="font-bold text-slate-900 hover:text-emerald-700">{{ $invoice->invoice_number }}</a>
                                <span class="block text-[10px] font-semibold text-slate-500">{{ $invoice->items->count() }} item{{ $invoice->items->count() === 1 ? '' : 's' }}</span>
                            </td>
                            <td class="px-3 py-2 font-semibold text-slate-600">{{ $invoice->business_date?->format('d M Y') }}</td>
                            <td class="px-3 py-2 text-right font-bold text-slate-900">Rs. {{ number_format((float) ($isOwnedAccountingShop ? $invoice->final_total : $invoice->balance_amount), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="mt-3 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-5 text-center">
            @include('shop-owner.components.empty-state', ['title' => 'No bills yet', 'description' => 'Delivery bills will appear here after dispatch is verified.'])
        </div>
    @endif
</section>
