<x-layouts.accounting title="Purchaser Credits — {{ $user->name }}">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.26em] text-slate-400">Purchaser Ledger</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ $user->name }}</h1>
                    <p class="mt-2 text-sm font-semibold text-slate-600">{{ $user->email }} • Purchaser Account Details</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.accounting.purchasers.index') }}" class="inline-flex h-11 items-center rounded-2xl border border-slate-200 px-4 text-sm font-black text-slate-700 transition hover:bg-slate-50">
                        Back to Purchasers
                    </a>
                </div>
            </div>
        </section>

        <!-- Stats Grid -->
        <section class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-700">Total Credits (In)</p>
                <p class="mt-2 text-3xl font-black text-emerald-950">₹{{ number_format($totalIn, 2) }}</p>
                <p class="mt-1 text-xs font-semibold text-slate-500">Funds given by Green Leaf</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-rose-700">Total Debits (Out)</p>
                <p class="mt-2 text-3xl font-black text-rose-950">₹{{ number_format($totalOut, 2) }}</p>
                <p class="mt-1 text-xs font-semibold text-slate-500">Invoices submitted & paid</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Net Balance</p>
                <p class="mt-2 text-3xl font-black {{ $balance >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                    ₹{{ number_format($balance, 2) }}
                </p>
                <p class="mt-1 text-xs font-semibold text-slate-500">Current cash balance left with purchaser</p>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Add Credit Form -->
            <section class="lg:col-span-1 rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm h-fit">
                <h2 class="text-lg font-black text-slate-950">Add Cash / Credit</h2>
                <p class="mt-1 text-xs font-semibold text-slate-500">Provide cash advance or credit to this purchaser from Green Leaf.</p>

                <form method="POST" action="{{ route('admin.accounting.purchasers.credits.store', $user) }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="amount" class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Amount (₹)</label>
                        <input id="amount" type="number" step="0.01" min="0.01" name="amount" required class="mt-2 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                    </div>

                    <div>
                        <label for="description" class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Description</label>
                        <input id="description" type="text" name="description" placeholder="e.g. Cash Advance for daily buying" class="mt-2 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                    </div>

                    <div>
                        <label for="business_date" class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400">Business Date</label>
                        <input id="business_date" type="date" name="business_date" value="{{ today()->toDateString() }}" required class="mt-2 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                    </div>

                    <button type="submit" class="w-full inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-600 text-sm font-black text-white hover:bg-emerald-500 shadow-sm transition">
                        Add Credit
                    </button>
                </form>
            </section>

            <!-- Ledger Table -->
            <section class="lg:col-span-2 rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-black text-slate-950">In & Out Transactions Ledger</h2>
                <p class="mt-1 text-xs font-semibold text-slate-500">A detailed statement of cash advances (In) and invoice payouts (Out) for this purchaser.</p>

                <div class="mt-5 overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">
                                <th class="pb-3 font-semibold">Date</th>
                                <th class="pb-3 font-semibold text-center">Type</th>
                                <th class="pb-3 font-semibold">Description / Invoice</th>
                                <th class="pb-3 font-semibold text-right">Amount</th>
                                <th class="pb-3 font-semibold text-right">Created By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse($credits as $credit)
                                <tr class="text-sm">
                                    <td class="py-3.5 font-bold text-slate-900">{{ $credit->business_date->format('d M Y') }}</td>
                                    <td class="py-3.5 text-center">
                                        @if($credit->type === 'in')
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-100">
                                                IN
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-bold text-rose-700 border border-rose-100">
                                                OUT
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 font-semibold text-slate-600">
                                        <div class="flex flex-col">
                                            <span>{{ $credit->description }}</span>
                                            @if($credit->purchaseInvoice)
                                                <a href="{{ route('purchasing.invoices.show', $credit->purchaseInvoice) }}" class="mt-0.5 text-xs font-black text-cyan-600 hover:underline inline-flex items-center gap-1">
                                                    <span>Invoice: {{ $credit->purchaseInvoice->invoice_number }}</span>
                                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                                    </svg>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-3.5 font-black text-right {{ $credit->type === 'in' ? 'text-emerald-700' : 'text-rose-700' }}">
                                        {{ $credit->type === 'in' ? '+' : '-' }}₹{{ number_format($credit->amount, 2) }}
                                    </td>
                                    <td class="py-3.5 font-semibold text-right text-slate-500">
                                        {{ $credit->creator?->name ?: 'System' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-sm font-bold text-slate-400">
                                        No transactions recorded on this account yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-layouts.accounting>
