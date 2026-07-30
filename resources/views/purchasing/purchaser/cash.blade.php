<x-layouts.app title="Purchaser Cash Ledger">
    <div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 lg:max-w-5xl lg:gap-4 lg:px-6 lg:py-4">
        @include('purchasing.purchaser.partials.feedback')

        {{-- Page Header --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-xs">
            <div>
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-500">Purchaser Cash</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">Cash Ledger</h1>
            </div>
            <a href="{{ route('purchaser.daily') }}" class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-xs font-black text-slate-700 hover:bg-slate-100">
                ← Back to Daily
            </a>
        </div>

        {{-- 3-Card Summary Row --}}
        <div class="grid grid-cols-3 gap-2 sm:gap-3">
            {{-- Cash In --}}
            <div class="relative overflow-hidden rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-600 to-emerald-500 p-4 text-white shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-emerald-100">Cash In</p>
                        <p class="mt-2 text-xl font-black sm:text-2xl">₹{{ number_format($totalIn, 2) }}</p>
                        <p class="mt-1 text-[10px] font-semibold text-emerald-200">Funds received</p>
                    </div>
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/20">
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </div>
                </div>
                <div class="absolute -bottom-4 -right-4 h-16 w-16 rounded-full bg-white/10"></div>
            </div>

            {{-- Cash Out --}}
            <div class="relative overflow-hidden rounded-2xl border border-rose-200 bg-gradient-to-br from-rose-600 to-rose-500 p-4 text-white shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-rose-100">Cash Out</p>
                        <p class="mt-2 text-xl font-black sm:text-2xl">₹{{ number_format($totalOut, 2) }}</p>
                        <p class="mt-1 text-[10px] font-semibold text-rose-200">Invoice purchases</p>
                    </div>
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/20">
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
                        </svg>
                    </div>
                </div>
                <div class="absolute -bottom-4 -right-4 h-16 w-16 rounded-full bg-white/10"></div>
            </div>

            {{-- Balance --}}
            <div class="relative overflow-hidden rounded-2xl border {{ $balance >= 0 ? 'border-slate-300 bg-gradient-to-br from-slate-800 to-slate-700' : 'border-rose-300 bg-gradient-to-br from-rose-800 to-rose-700' }} p-4 text-white shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-300">Balance</p>
                        <p class="mt-2 text-xl font-black sm:text-2xl {{ $balance >= 0 ? 'text-white' : 'text-rose-200' }}">₹{{ number_format($balance, 2) }}</p>
                        <p class="mt-1 text-[10px] font-semibold {{ $balance >= 0 ? 'text-slate-300' : 'text-rose-300' }}">{{ $balance >= 0 ? 'Available' : 'Deficit' }}</p>
                    </div>
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/20">
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="absolute -bottom-4 -right-4 h-16 w-16 rounded-full bg-white/10"></div>
            </div>
        </div>

        {{-- Ledger Table --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                <div>
                    <h2 class="text-sm font-black text-slate-950">Cash Ledger Entries</h2>
                    <p class="mt-0.5 text-[11px] font-semibold text-slate-500">All in/out entries recorded for this purchaser account.</p>
                </div>
                <span class="flex h-7 min-w-7 items-center justify-center rounded-full bg-slate-100 px-2 text-[10px] font-black text-slate-700">{{ $credits->count() }}</span>
            </div>

            {{-- Mobile Cards --}}
            <div class="divide-y divide-slate-100 lg:hidden">
                @forelse ($credits as $credit)
                    <div class="flex items-start justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] {{ $credit->type === 'in' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                    {{ strtoupper($credit->type) }}
                                </span>
                                <p class="text-xs font-black text-slate-950">{{ $credit->business_date?->format('d M Y') }}</p>
                            </div>
                            <p class="mt-1 text-[11px] font-semibold text-slate-600 truncate">{{ $credit->description }}</p>
                            <p class="mt-0.5 text-[10px] text-slate-400">{{ $credit->creator?->name ?: 'System' }}</p>
                            @if ($credit->purchaseInvoice)
                                <a href="{{ route('purchaser.invoices.show', $credit->purchaseInvoice) }}" class="mt-1 inline-flex items-center gap-1 text-[10px] font-black text-cyan-700 hover:underline">
                                    → {{ $credit->purchaseInvoice->invoice_number }}
                                </a>
                            @endif
                        </div>
                        <p class="shrink-0 text-sm font-black {{ $credit->type === 'in' ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ $credit->type === 'in' ? '+' : '-' }}₹{{ number_format((float) $credit->amount, 2) }}
                        </p>
                    </div>
                @empty
                    <div class="px-4 py-10 text-center text-sm font-bold text-slate-400">No cash entries recorded yet.</div>
                @endforelse
            </div>

            {{-- Desktop Table --}}
            <div class="hidden overflow-x-auto lg:block">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">Description</th>
                            <th class="px-4 py-3 text-left">Invoice</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-right">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($credits as $credit)
                            <tr class="bg-white hover:bg-slate-50/60 transition-colors">
                                <td class="px-4 py-3 text-xs font-black text-slate-950 whitespace-nowrap">{{ $credit->business_date?->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.14em] {{ $credit->type === 'in' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                        {{ strtoupper($credit->type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs font-semibold text-slate-700 max-w-xs truncate">{{ $credit->description }}</td>
                                <td class="px-4 py-3">
                                    @if ($credit->purchaseInvoice)
                                        <a href="{{ route('purchaser.invoices.show', $credit->purchaseInvoice) }}" class="text-[11px] font-black text-cyan-700 hover:underline whitespace-nowrap">
                                            {{ $credit->purchaseInvoice->invoice_number }}
                                        </a>
                                    @else
                                        <span class="text-[11px] text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-black whitespace-nowrap {{ $credit->type === 'in' ? 'text-emerald-700' : 'text-rose-700' }}">
                                    {{ $credit->type === 'in' ? '+' : '-' }}₹{{ number_format((float) $credit->amount, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-[11px] font-semibold text-slate-500 whitespace-nowrap">{{ $credit->creator?->name ?: 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-sm font-bold text-slate-400">No cash entries recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
