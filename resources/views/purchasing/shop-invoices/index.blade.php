<x-layouts.app title="Shop Daily Invoices">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Purchasing</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">Shop Daily Invoices</h1>
                <p class="mt-1 text-sm text-slate-600">Review generated shop invoices, delivery impact, and payment balances.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-slate-700">
                {{ $invoices->total() }} invoices
            </span>
        </div>

        @if ($invoices->isEmpty())
            <div class="px-5 py-16 text-center text-sm text-slate-500">
                No shop invoices have been generated yet.
            </div>
        @else
            <div class="space-y-3 p-4 md:hidden">
                @foreach ($invoices as $invoice)
                    <article class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-mono text-xs font-black text-cyan-700">{{ $invoice->invoice_number }}</p>
                                <h2 class="mt-1 text-base font-black text-slate-950">{{ $invoice->shop?->name }}</h2>
                                <p class="mt-1 text-xs text-slate-500">{{ $invoice->business_date->format('d M Y') }}</p>
                            </div>
                            <a href="{{ route('purchasing.shop-invoices.show', $invoice) }}" class="text-sm font-bold text-slate-900">Open</a>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full bg-white px-2.5 py-1 font-black text-slate-700">{{ str($invoice->delivery_status)->replace('_', ' ')->title() }}</span>
                            <span class="rounded-full bg-white px-2.5 py-1 font-black text-slate-700">{{ str($invoice->payment_status)->replace('_', ' ')->title() }}</span>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Final</p>
                                <p class="mt-1 font-black text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Balance</p>
                                <p class="mt-1 font-black text-red-600">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</p>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Invoice</th>
                            <th class="px-5 py-4">Shop</th>
                            <th class="px-5 py-4">Date</th>
                            <th class="px-5 py-4">Delivery</th>
                            <th class="px-5 py-4">Payment</th>
                            <th class="px-5 py-4 text-right">Final</th>
                            <th class="px-5 py-4 text-right">Balance</th>
                            <th class="px-5 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td class="px-5 py-4 font-mono font-black text-cyan-700">{{ $invoice->invoice_number }}</td>
                                <td class="px-5 py-4 font-semibold text-slate-950">{{ $invoice->shop?->name }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $invoice->business_date->format('d M Y') }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ str($invoice->delivery_status)->replace('_', ' ')->title() }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ str($invoice->payment_status)->replace('_', ' ')->title() }}</td>
                                <td class="px-5 py-4 text-right font-black text-slate-950">Rs. {{ number_format((float) $invoice->final_total, 2) }}</td>
                                <td class="px-5 py-4 text-right font-black text-red-600">Rs. {{ number_format((float) $invoice->balance_amount, 2) }}</td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('purchasing.shop-invoices.show', $invoice) }}" class="font-bold text-cyan-700 hover:text-cyan-900">Open</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($invoices->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $invoices->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>
</x-layouts.app>
