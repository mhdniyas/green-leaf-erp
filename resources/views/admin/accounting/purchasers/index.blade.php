<x-layouts.accounting title="Purchasers Ledger">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Purchasers Ledger</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Purchasers Accounts</h1>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Track and manage credit limits, in/out cash flows, and overall outstanding balances for each purchaser.</p>
                </div>
                <a href="{{ route('admin.accounting.index') }}" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 px-4 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                    Back to Dashboard
                </a>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            @forelse($purchasers as $purchaser)
                @php
                    $totalIn = (float) $purchaser->total_in;
                    $totalOut = (float) $purchaser->total_out;
                    $balance = $totalIn - $totalOut;
                @endphp
                <article class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xl font-black text-slate-950">{{ $purchaser->name }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $purchaser->email }}</p>
                        </div>
                        <a href="{{ route('admin.accounting.purchasers.show', $purchaser) }}" class="inline-flex h-10 items-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 text-xs font-black uppercase tracking-[0.18em] text-emerald-700 transition hover:bg-emerald-100">
                            Ledger & Add Credit
                        </a>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl bg-emerald-50/50 border border-emerald-100 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Total Credits (In)</p>
                            <p class="mt-2 text-lg font-black text-emerald-950">₹{{ number_format($totalIn, 2) }}</p>
                        </div>
                        <div class="rounded-2xl bg-rose-50/50 border border-rose-100 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">Total Debits (Out)</p>
                            <p class="mt-2 text-lg font-black text-rose-950">₹{{ number_format($totalOut, 2) }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Balance</p>
                            <p class="mt-2 text-lg font-black {{ $balance >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                ₹{{ number_format($balance, 2) }}
                            </p>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-[1.75rem] border border-dashed border-slate-300 px-4 py-12 text-center text-sm font-bold text-slate-500 lg:col-span-2">
                    No users with the 'purchaser' role were found.
                </div>
            @endforelse
        </section>
    </div>
</x-layouts.accounting>
