@if ($invoices->isNotEmpty())
    <div class="space-y-3 md:hidden">
        @foreach ($invoices as $invoice)
            <article class="rounded-3xl border border-slate-200 bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-black text-slate-900">{{ $invoice->business_date->format('d M Y') }}</p>
                        <p class="mt-1 font-mono text-xs font-bold text-slate-600">{{ $invoice->invoice_number }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <a href="{{ route('shop-owner.finance.show', $invoice) }}" class="text-sm font-bold text-emerald-700">Open</a>
                        <a href="{{ route('shop-owner.finance.pdf', $invoice) }}" target="_blank" class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">PDF</a>
                    </div>
                </div>
                <div class="mt-4">
                    @include('shop-owner.finance.partials.payment-status-badge', ['invoice' => $invoice])
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Final</p>
                        <p class="mt-1 text-sm font-bold text-slate-900">Rs. {{ number_format((float) $invoice->final_total, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Balance</p>
                        <p class="mt-1 text-sm font-bold text-red-600">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</p>
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <div class="hidden overflow-x-auto md:block">
        <table class="min-w-full border-collapse text-left">
            <thead>
                <tr class="border-b border-slate-100 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                    <th class="py-3 pr-4">Date</th>
                    <th class="py-3 pr-4">Invoice</th>
                    <th class="py-3 pr-4">Status</th>
                    <th class="py-3 pr-4 text-right">Final</th>
                    <th class="py-3 pr-4 text-right">Balance</th>
                    <th class="py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                @foreach ($invoices as $invoice)
                    <tr>
                        <td class="py-4 pr-4 font-bold text-slate-900">{{ $invoice->business_date->format('d M Y') }}</td>
                        <td class="py-4 pr-4 font-mono text-xs font-bold text-slate-600">{{ $invoice->invoice_number }}</td>
                        <td class="py-4 pr-4">@include('shop-owner.finance.partials.payment-status-badge', ['invoice' => $invoice])</td>
                        <td class="py-4 pr-4 text-right font-bold text-slate-900">Rs. {{ number_format((float) $invoice->final_total, 2) }}</td>
                        <td class="py-4 pr-4 text-right font-bold text-red-600">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</td>
                        <td class="py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('shop-owner.finance.pdf', $invoice) }}" target="_blank" class="font-black uppercase tracking-[0.14em] text-slate-500 hover:text-slate-700">PDF</a>
                                <a href="{{ route('shop-owner.finance.show', $invoice) }}" class="font-bold text-emerald-700 hover:text-emerald-900">Open</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($invoices instanceof \Illuminate\Contracts\Pagination\Paginator)
        <div class="mt-5">{{ $invoices->links() }}</div>
    @endif
@else
    @include('shop-owner.components.empty-state', ['title' => 'No finance activity', 'description' => 'Paid and delivered orders will appear here with balances and notes.'])
@endif
