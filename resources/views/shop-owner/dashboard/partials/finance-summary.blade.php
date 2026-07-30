<section class="rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Bills and Payments</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">{{ $isOwnedAccountingShop ? 'Owned shop money flow' : 'Shop bill collection' }}</h2>
        </div>
        <div class="flex flex-wrap gap-2">
            @include('shop-owner.components.action-button', ['href' => route('shop-owner.accounting.index', ['tab' => 'bills', 'date' => $businessDate->toDateString()]), 'label' => 'Open Bills', 'classes' => 'border border-slate-200 bg-white text-slate-800'])
            @include('shop-owner.components.action-button', ['href' => route('shop-owner.payments.index'), 'label' => 'Open Payments', 'classes' => 'bg-slate-950 text-white'])
        </div>
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-3">
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Today Bill</p>
            <p class="mt-3 text-2xl font-black text-slate-950">Rs. {{ number_format((float) $financeSummary['today_bill_total'], 2) }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">{{ $isOwnedAccountingShop ? 'Approved Debit' : 'Outstanding' }}</p>
            <p class="mt-3 text-2xl font-black {{ $isOwnedAccountingShop ? 'text-rose-700' : 'text-red-600' }}">Rs. {{ number_format((float) ($isOwnedAccountingShop ? $financeSummary['today_approved_bill_debit'] : $financeSummary['outstanding_balance']), 2) }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">{{ $isOwnedAccountingShop ? 'Closing Balance' : 'Paid Amount' }}</p>
            <p class="mt-3 text-2xl font-black text-emerald-700">Rs. {{ number_format((float) ($isOwnedAccountingShop ? $financeSummary['today_closing_balance'] : $financeSummary['paid_amount']), 2) }}</p>
        </div>
    </div>

    @if ($recentInvoices->isNotEmpty())
        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full border-collapse text-left">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                        <th class="py-3 pr-4">Invoice</th>
                        <th class="py-3 pr-4">Date</th>
                        <th class="py-3 pr-4 text-right">{{ $isOwnedAccountingShop ? 'Bill Debit' : 'Balance' }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    @foreach ($recentInvoices->take(4) as $invoice)
                        <tr>
                            <td class="py-3 pr-4">
                                <a href="{{ route('shop-owner.finance.show', $invoice) }}" class="font-bold text-slate-900 hover:text-emerald-700">{{ $invoice->invoice_number }}</a>
                                <span class="block text-xs font-semibold text-slate-500">{{ $invoice->items->count() }} item{{ $invoice->items->count() === 1 ? '' : 's' }}</span>
                            </td>
                            <td class="py-3 pr-4 font-semibold text-slate-600">{{ $invoice->business_date?->format('d M Y') }}</td>
                            <td class="py-3 pr-4 text-right font-bold text-slate-900">Rs. {{ number_format((float) ($isOwnedAccountingShop ? $invoice->final_total : $invoice->balance_amount), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="mt-5">
            @include('shop-owner.components.empty-state', ['title' => 'No bills yet', 'description' => 'Delivery bills will appear here after dispatch is verified.'])
        </div>
    @endif
</section>
