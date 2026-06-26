<x-layouts.app title="My Credits">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-6xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')
        @include('purchasing.purchaser.partials.deadline_alert')

        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Credits Ledger</p>
                    <h1 class="mt-1 text-xl font-black text-slate-950">My Credits Ledger</h1>
                    <p class="mt-1 text-xs font-semibold text-slate-600">Track cash advances from the company and your purchases in real-time.</p>
                </div>
            </div>
        </section>

        <!-- Stats cards -->
        <section class="grid grid-cols-3 gap-2 lg:gap-4">
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-2xl lg:p-5">
                <p class="text-[9px] font-black uppercase tracking-[0.12em] text-emerald-700">Total Credits (In)</p>
                <p class="mt-1.5 text-base font-black text-emerald-950 lg:text-2xl">₹{{ number_format($totalIn, 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-2xl lg:p-5">
                <p class="text-[9px] font-black uppercase tracking-[0.12em] text-rose-700">Total Debits (Out)</p>
                <p class="mt-1.5 text-base font-black text-rose-950 lg:text-2xl">₹{{ number_format($totalOut, 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-2xl lg:p-5">
                <p class="text-[9px] font-black uppercase tracking-[0.12em] text-slate-500">Balance Left</p>
                <p class="mt-1.5 text-base font-black lg:text-2xl {{ $balance >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                    ₹{{ number_format($balance, 2) }}
                </p>
            </div>
        </section>

        <!-- Ledger Table -->
        <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:rounded-[2rem] lg:p-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">
                            <th class="pb-3 font-semibold">Date</th>
                            <th class="pb-3 font-semibold text-center">Type</th>
                            <th class="pb-3 font-semibold">Description</th>
                            <th class="pb-3 font-semibold text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse($credits as $credit)
                            <tr class="text-sm">
                                <td class="py-3 font-bold text-slate-900">{{ $credit->business_date->format('d M Y') }}</td>
                                <td class="py-3 text-center">
                                    @if($credit->type === 'in')
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-100">
                                            IN
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700 border border-rose-100">
                                            OUT
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 font-semibold text-slate-600">
                                    <div class="flex flex-col">
                                        <span>{{ $credit->description }}</span>
                                        @if($credit->purchaseInvoice)
                                            <a href="{{ route('purchaser.invoices.show', $credit->purchaseInvoice) }}" class="mt-0.5 text-xs font-black text-cyan-600 hover:underline inline-flex items-center gap-1">
                                                <span>Invoice: {{ $credit->purchaseInvoice->invoice_number }}</span>
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 font-black text-right {{ $credit->type === 'in' ? 'text-emerald-700' : 'text-rose-700' }}">
                                    {{ $credit->type === 'in' ? '+' : '-' }}₹{{ number_format($credit->amount, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-sm font-bold text-slate-400">
                                    No transactions recorded on your account yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
