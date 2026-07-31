<x-layouts.accounting title="Direct Payments">
    @php
        $dateParam = $date->format('Y-m-d');
    @endphp

    <div class="mx-auto max-w-[96rem] space-y-5">
        @include('admin.finance-v2.partials.nav')

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-950 px-5 py-6 text-white sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-300">Finance Payments</p>
                        <h1 class="mt-2 text-3xl font-black tracking-tight">Direct Payments</h1>
                        <p class="mt-2 text-sm font-semibold text-slate-300">Pay outstanding purchase invoices and company bills.</p>
                    </div>
                    <a href="{{ route('admin.finance-v2.client-payments.index', ['date' => $dateParam]) }}" class="inline-flex h-11 items-center rounded-[1rem] border border-white/20 bg-white/10 px-5 text-xs font-black uppercase tracking-[0.16em] text-white hover:bg-white/15">Client Payments</a>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left">
                    <thead class="bg-slate-50">
                        <tr>
                            @foreach(['Bill', 'Supplier', 'Amount', 'Paid', 'Outstanding', ''] as $heading)
                                <th class="px-4 py-3 text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($invoices as $invoice)
                            @php
                                $paid = round((float) ($invoice->payments->sum('amount') ?: $invoice->paid_amount), 2);
                                $discount = round((float) ($invoice->payments->sum('discount_amount') ?: $invoice->discount_amount), 2);
                                $outstanding = round(max(0, (float) $invoice->amount - $paid - $discount), 2);
                            @endphp
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3 text-sm font-black text-slate-950">{{ $invoice->invoice_number }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-700">{{ $invoice->supplier?->name ?? 'Supplier' }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-700">Rs. {{ number_format((float) $invoice->amount, 2) }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-700">Rs. {{ number_format($paid, 2) }}</td>
                                <td class="px-4 py-3 text-sm font-black text-slate-950">Rs. {{ number_format($outstanding, 2) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.finance-v2.direct-payments.create', ['invoice' => $invoice, 'date' => $dateParam]) }}" class="text-xs font-black uppercase tracking-[0.14em] text-orange-600 hover:underline">Pay</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm font-bold text-slate-500">No outstanding bills.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.accounting>
